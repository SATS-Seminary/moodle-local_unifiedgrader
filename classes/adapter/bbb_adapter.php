<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * BigBlueButton adapter for the unified grading interface.
 *
 * Renders recordings inline (via iframe to BBB's playback wrapper) and
 * displays per-user "Activity Points" engagement metrics aggregated
 * across all sessions, sourced from bigbluebuttonbn_logs.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\adapter;

defined('MOODLE_INTERNAL') || die();

use local_unifiedgrader\bbb\engagement_service;
use local_unifiedgrader\submission_comment_manager;

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/mod/bigbluebuttonbn/lib.php');

/**
 * Concrete adapter wrapping mod_bigbluebuttonbn's recording + engagement data.
 */
class bbb_adapter extends base_adapter {
    /** @var \stdClass The raw bigbluebuttonbn DB record. */
    private \stdClass $bbb;

    /**
     * Constructor.
     *
     * @param \cm_info $cm Course module info.
     * @param \context_module $context Module context.
     * @param \stdClass $course Course record.
     */
    public function __construct(\cm_info $cm, \context_module $context, \stdClass $course) {
        parent::__construct($cm, $context, $course);

        global $DB;
        $this->bbb = $DB->get_record('bigbluebuttonbn', ['id' => $cm->instance], '*', MUST_EXIST);
    }

    /**
     * Get BBB activity metadata.
     *
     * @return array
     */
    public function get_activity_info(): array {
        // Detect scale-based grading (negative grade = scale ID).
        // Grade type "None" means grade == 0.
        $rawgrade = (int) $this->bbb->grade;
        $gradingenabled = $rawgrade !== 0;
        $usescale = $rawgrade < 0;
        $scaleitems = [];
        $maxgrade = (float) $this->bbb->grade;
        if ($usescale) {
            $menu = make_grades_menu($rawgrade);
            foreach ($menu as $value => $label) {
                $scaleitems[] = ['value' => (int) $value, 'label' => (string) $label];
            }
            $maxgrade = (float) count($scaleitems);
        }

        // The bbbext_advgrd extension wires BBB into Moodle's standard
        // advanced-grading API on the (bbbext_advgrd, participation) area. When
        // it's installed and configured for a method, render the rubric/guide
        // in the marking pane the same way forum/assign do.
        $advgrdmethod = $this->get_advgrd_method();
        $gradingmethod = $advgrdmethod ?? 'simple';

        return [
            'id' => (int) $this->cm->id,
            'name' => format_string($this->bbb->name),
            'type' => 'bigbluebuttonbn',
            'duedate' => 0,
            'cutoffdate' => 0,
            'maxgrade' => $maxgrade,
            'usescale' => $usescale,
            'scaleitems' => $scaleitems,
            'intro' => format_text(
                $this->bbb->intro ?? '',
                $this->bbb->introformat ?? FORMAT_HTML,
                ['context' => $this->context],
            ),
            'gradingmethod' => $gradingenabled ? $gradingmethod : 'simple',
            'gradingdisabled' => !$gradingenabled,
            'teamsubmission' => false,
            'blindmarking' => false,
            'canmanageoverrides' => false,
            'hasduedateplugin' => false,
            'canmanageextensions' => false,
            'maxattempts' => 1,
            'gradepenaltyenabled' => false,
            'hasactivitypointsplugin' => $advgrdmethod !== null,
        ];
    }

    /**
     * Get participant list with attendance/grade status.
     *
     * Status mapping:
     * - 'graded'       — has a gradebook grade.
     * - 'submitted'    — attended at least one session (has a Join or Summary log).
     * - 'nosubmission' — did not attend.
     *
     * Attendance is derived from the union of EVENT_JOIN (logged by Moodle when
     * the student clicks Join) and EVENT_SUMMARY (logged when the BBB server's
     * analytics callback fires). Including JOIN ensures we still mark students
     * as attended in setups where the engagement callback hasn't been wired or
     * the meeting ended before BBB sent the summary.
     *
     * @param array $filters Optional: status, group, search, sort, sortdir.
     * @return array
     */
    public function get_participants(array $filters = []): array {
        global $DB, $PAGE;

        $groupids = $this->get_group_ids($filters);
        $bbbid = (int) $this->bbb->id;

        // Get enrolled users who can join meetings (active enrolments only).
        $enrolledusers = $this->get_enrolled_users_multigroup(
            $this->context,
            'mod/bigbluebuttonbn:view',
            $groupids,
            'u.*',
            'u.lastname, u.firstname',
        );

        // Exclude users who can grade — teachers should not appear in the student list.
        $graders = $this->get_enrolled_users_multigroup(
            $this->context,
            'local/unifiedgrader:grade',
            $groupids,
            'u.id',
        );
        $enrolledusers = array_diff_key($enrolledusers, $graders);

        // Batch-load attendance log stats per user — join + summary signals together.
        $logstats = $this->get_attendance_stats($bbbid);

        // Batch-load grades from the gradebook (BBB has no native grades table).
        $grades = [];
        $gradeitem = $this->fetch_grade_item();
        if ($gradeitem) {
            $gradegrades = \grade_grade::fetch_all(['itemid' => $gradeitem->id]);
            if ($gradegrades) {
                foreach ($gradegrades as $gg) {
                    $grades[(int) $gg->userid] = $gg;
                }
            }
        }

        $result = [];
        foreach ($enrolledusers as $user) {
            $userid = (int) $user->id;
            $stats = $logstats[$userid] ?? null;
            $usergrade = $grades[$userid] ?? null;

            $hasattended = $stats && (int) $stats->sessioncount > 0;
            // The teacher's mark, matching what assign and quiz list here — both
            // read their activity's own stored grade rather than the penalised
            // gradebook figure. See sync_gradebook_penalty() for the split.
            $usergradevalue = $usergrade ? ($usergrade->rawgrade ?? $usergrade->finalgrade) : null;
            $hasgrade = $usergradevalue !== null;

            if ($hasgrade) {
                $status = 'graded';
            } else if ($hasattended) {
                $status = 'submitted';
            } else {
                $status = 'nosubmission';
            }

            $userpicture = new \user_picture($user);
            $userpicture->size = 64;
            $profileimageurl = $userpicture->get_url($PAGE)->out(false);

            $entry = [
                'id' => $userid,
                'fullname' => fullname($user),
                'email' => $user->email,
                'profileimageurl' => $profileimageurl,
                'status' => $status,
                'submittedat' => $stats ? (int) $stats->lastattended : 0,
                'gradevalue' => $hasgrade ? (float) $usergradevalue : null,
                'locked' => false,
                'hasoverride' => false,
                'hasextension' => false,
                'islate' => false,
            ];

            // Apply status filter.
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                if (!$this->matches_filter($filters['status'], $entry, 0)) {
                    continue;
                }
            }

            // Apply search filter.
            if (!empty($filters['search'])) {
                $needle = \core_text::strtolower($filters['search']);
                if (strpos(\core_text::strtolower($entry['fullname']), $needle) === false) {
                    continue;
                }
            }

            $result[] = $entry;
        }

        // Sort.
        $sort = $filters['sort'] ?? 'fullname';
        $sortdir = $filters['sortdir'] ?? 'asc';
        $validkeys = ['fullname', 'submittedat', 'status', 'gradevalue'];
        if (!in_array($sort, $validkeys)) {
            $sort = 'fullname';
        }

        usort($result, function ($a, $b) use ($sort, $sortdir) {
            $va = $a[$sort] ?? '';
            $vb = $b[$sort] ?? '';
            if (is_string($va)) {
                $cmp = strcasecmp($va, $vb);
            } else {
                $cmp = ($va ?? 0) <=> ($vb ?? 0);
            }
            return $sortdir === 'desc' ? -$cmp : $cmp;
        });

        return $result;
    }

    /**
     * Get submission data — recordings + engagement metrics rendered as HTML.
     *
     * @param int $userid
     * @return array
     */
    public function get_submission_data(int $userid): array {
        global $OUTPUT, $DB;

        $recordings = $this->get_recordings_for_user($userid);
        $activitypoints = $this->get_engagement_summary($userid);

        // Attendance is signalled by either an engagement summary log or a join
        // log — see get_participants() for why we accept both.
        $hasjoined = $DB->record_exists_select(
            'bigbluebuttonbn_logs',
            'bigbluebuttonbnid = :bbbid AND userid = :userid AND log IN (:joinlog, :summarylog)',
            [
                'bbbid' => (int) $this->bbb->id,
                'userid' => $userid,
                'joinlog' => \mod_bigbluebuttonbn\logger::EVENT_JOIN,
                'summarylog' => \mod_bigbluebuttonbn\logger::EVENT_SUMMARY,
            ],
        );
        $hasattended = $hasjoined || $activitypoints['sessioncount'] > 0;

        // The pills are scoped to the sessions this student actually attended.
        // On BBB the attendance *is* the submission, so a session they were not
        // in is not theirs to be marked on. filter_to_attended_recordings()
        // carries the guards that stop a missing roster reading as absence.
        $allrecordings = $recordings;
        $filtered = $this->filter_to_attended_recordings(
            $recordings,
            $activitypoints['sessions'],
            $userid,
            $hasjoined,
        );
        $recordings = $filtered['recordings'];
        // Why the filter stood down, if it did. Surfaced in the pane so a teacher
        // is not left wondering why a student they know was absent still shows
        // every session, and so the id mismatch behind it is visible to an admin
        // without a database query.
        $filterreason = $filtered['reason'];
        // Everything filtered out: the student attended none of the sessions.
        // Kept distinct from "this activity has no recordings at all" so the
        // template can say which of the two it is.
        $didnotattendsessions = !empty($allrecordings) && empty($recordings);
        $hasrecordings = !empty($recordings);

        // Recording switcher selection. The top-of-pane pills reload this preview
        // with ?recordingid=<bbb recording id> to pin the annotation overlay and
        // the Activity Points tiles to a single session; an absent (or
        // unrecognised) id means "All sessions" — the aggregate tiles with the
        // first recording loaded in the player. We read the request param here
        // rather than threading it through get_submission_data() so the switcher
        // stays self-contained to the BBB adapter: the other callers of this
        // method (the AJAX external, which posts its args as a JSON body) never
        // send the param, so they transparently get the aggregate view.
        $selectedrecordingid = optional_param('recordingid', '', PARAM_RAW_TRIMMED);
        $activeindex = 0;
        $isaggregate = true;
        if ($selectedrecordingid !== '' && $hasrecordings) {
            foreach ($recordings as $i => $rec) {
                if ((string) $rec['bbbrecordingid'] === $selectedrecordingid) {
                    $activeindex = $i;
                    $isaggregate = false;
                    break;
                }
            }
            // Unknown id — fall back to the aggregate view and don't hand the
            // stray id to the overlay renderer.
            if ($isaggregate) {
                $selectedrecordingid = '';
            }
        }
        // Student viewing their OWN feedback with no explicit pick: land on a
        // recording that actually carries their feedback (rescued above from the
        // group filter) so the overlay opens on their comments rather than the
        // aggregate/first recording — which, in a separate-groups activity, may be
        // a different session or one they could not otherwise see.
        global $USER;
        if ($selectedrecordingid === '' && $hasrecordings && $userid === (int) $USER->id) {
            $feedbackrecids = $this->get_feedback_recording_ids($userid);
            foreach ($recordings as $i => $rec) {
                if ($feedbackrecids && in_array((string) $rec['bbbrecordingid'], $feedbackrecids, true)) {
                    $activeindex = $i;
                    $isaggregate = false;
                    $selectedrecordingid = (string) $rec['bbbrecordingid'];
                    break;
                }
            }
        }
        // No explicit pick: open on the session the student was present longest
        // for. "All sessions" is an aggregate, and defaulting to it meant the
        // player loaded whichever recording happened to sort first — which for a
        // student who dropped into a session for ten minutes by mistake is the
        // wrong video to be marking. The longest attendance is the session that
        // carries the work, so it is the one to land on; the totals stay one
        // click away on the "All sessions" pill.
        if ($selectedrecordingid === '' && $hasrecordings) {
            $longestref = $this->get_longest_attended_ref($userid, $activitypoints['sessions']);
            foreach ($recordings as $i => $rec) {
                if ($longestref !== '' && (string) $rec['bbbrecordingid'] === $longestref) {
                    $activeindex = $i;
                    $isaggregate = false;
                    $selectedrecordingid = $longestref;
                    break;
                }
            }
        }

        // Flag the active recording so its switcher pill renders selected
        // server-side (correct highlight on first paint, no JS required).
        foreach ($recordings as $i => $rec) {
            $recordings[$i]['isactive'] = (!$isaggregate && $i === $activeindex);
        }

        // Surface a config diagnostic when the engagement summary is missing.
        // BBB only POSTs the analytics callback (which writes EVENT_SUMMARY rows)
        // when the "Register live sessions" admin setting is enabled — see
        // mod/bigbluebuttonbn/classes/meeting.php::create_meeting_data_metadata.
        $missingsummary = $hasjoined && $activitypoints['sessioncount'] === 0;
        $callbackenabled = (bool) get_config('mod_bigbluebuttonbn', 'meetingevents_enabled');
        $cansiteconfig = has_capability('moodle/site:config', \context_system::instance());
        $bbbsettingsurl = (new \moodle_url('/admin/settings.php', ['section' => 'modsettingbigbluebuttonbn']))->out(false);

        // Surface the BBB-hosted Statistics dashboard URLs so the teacher can
        // open the full per-attendee breakdown in a new tab. We always render
        // one link per recording when there is more than one (so the teacher
        // can pick a session), and a single inline link otherwise.
        $statisticsentries = [];
        foreach ($recordings as $rec) {
            if (!empty($rec['hasstatisticsurl'])) {
                $statisticsentries[] = [
                    'url' => $rec['statisticsurl'],
                    'sessionlabel' => $rec['sessionlabel'],
                ];
            }
        }

        // Decorate each engagement session with the matching recording's label
        // so the per-session Activity Points blocks can show "Session of <date>"
        // headings. Sessions that don't match a recording (e.g. recording was
        // deleted, or a participant joined a meeting that was never recorded)
        // get a fallback label derived from the log timestamp.
        $recordingsbyref = [];
        foreach ($recordings as $rec) {
            if (!empty($rec['bbbrecordingid'])) {
                $recordingsbyref[$rec['bbbrecordingid']] = $rec;
            }
        }
        $hasactivesession = false;
        if (!empty($activitypoints['sessions'])) {
            foreach ($activitypoints['sessions'] as $i => $session) {
                $matched = $recordingsbyref[$session['recordingref']] ?? null;
                if ($matched) {
                    $activitypoints['sessions'][$i]['sessionlabel'] = $matched['sessionlabel'];
                    $activitypoints['sessions'][$i]['hasrecording'] = true;
                } else {
                    $activitypoints['sessions'][$i]['sessionlabel'] = $session['timecreated'] > 0
                        ? userdate($session['timecreated'])
                        : get_string('bbb_session_unmatched', 'local_unifiedgrader');
                    $activitypoints['sessions'][$i]['hasrecording'] = false;
                }
                // Pre-select the picked session's tiles so the card renders the
                // right block on first paint (the switcher JS keeps them in sync
                // thereafter).
                $isactivesession = !$isaggregate && $session['recordingref'] === $selectedrecordingid;
                $activitypoints['sessions'][$i]['isactive'] = $isactivesession;
                if ($isactivesession) {
                    $hasactivesession = true;
                }
            }
        }
        // Aggregate tiles show by default, and also whenever a picked recording
        // has no matching engagement session — so the card is never left empty.
        $showaggregatetiles = $isaggregate || !$hasactivesession;

        // Optional: bbbext_advgrd can render an own-player annotation overlay (timeline
        // + comments + audio/video feedback callout) in place of the BBB iframe. Loose
        // coupling: we probe the lib.php file directly (procedural functions aren't
        // covered by the PSR-4 autoloader the way classes are, so function_exists()
        // alone returns false until lib.php has been required somewhere). When
        // bbbext_advgrd isn't installed the file doesn't exist and the template falls
        // back to the iframe path.
        global $CFG;
        $overlayhtml = '';
        $advgrdlib = $CFG->dirroot . '/mod/bigbluebuttonbn/extension/advgrd/lib.php';
        if ($hasrecordings && file_exists($advgrdlib)) {
            require_once($advgrdlib);
            if (function_exists('bbbext_advgrd_render_overlay')) {
                // Always name the recording, even in the aggregate view. Passing
                // null lets the overlay choose for itself, and it chooses from
                // every recording on the activity rather than the ones this
                // student attended — landing on the activity's first recording.
                // A student with a single attended session renders no switcher
                // pills, so nothing would ever correct that choice and the
                // teacher would mark the wrong video with no way to tell.
                $overlayrecordingid = $selectedrecordingid;
                if ($overlayrecordingid === '' && $hasrecordings) {
                    $overlayrecordingid = (string) $recordings[$activeindex]['bbbrecordingid'];
                }
                $overlay = bbbext_advgrd_render_overlay(
                    (int) $this->cm->id,
                    $userid,
                    $overlayrecordingid !== '' ? $overlayrecordingid : null,
                );
                if ($overlay !== null) {
                    $overlayhtml = $overlay;
                }
            }
        }

        $templatedata = [
            'cmid' => (int) $this->cm->id,
            'recordings' => $recordings,
            'hasrecordings' => $hasrecordings,
            'hasmultiplerecordings' => count($recordings) > 1,
            'activerecordingurl' => $hasrecordings ? $recordings[$activeindex]['playbackurl'] : '',
            'isaggregate' => $isaggregate,
            'showaggregatetiles' => $showaggregatetiles,
            'activitypoints' => $activitypoints,
            'hasattended' => $hasattended,
            'hassummary' => $activitypoints['sessioncount'] > 0,
            'missingsummary' => $missingsummary,
            'callbackenabled' => $callbackenabled,
            'cansiteconfig' => $cansiteconfig,
            'bbbsettingsurl' => $bbbsettingsurl,
            'didnotattendsessions' => $didnotattendsessions,
            'filternotice' => $filterreason !== ''
                ? get_string('bbb_filter_' . $filterreason, 'local_unifiedgrader')
                : '',
            'hasfilternotice' => $filterreason !== '',
            'filterdiagnostic' => ($filterreason === 'unreconciled' && $cansiteconfig)
                ? get_string('bbb_filter_iddiagnostic', 'local_unifiedgrader', (object) [
                    'sessionref' => s($filtered['sessionref']),
                    'recordingref' => s($filtered['recordingref']),
                ])
                : '',
            'statisticslinks' => $statisticsentries,
            'hasstatisticslinks' => !empty($statisticsentries),
            'singlestatisticsurl' => count($statisticsentries) === 1 ? $statisticsentries[0]['url'] : '',
            'overlayhtml' => $overlayhtml,
            'hasoverlay' => $overlayhtml !== '',
        ];

        $content = $OUTPUT->render_from_template('local_unifiedgrader/preview_bbb', $templatedata);

        // Determine status (mirrors get_participants logic).
        $gradeitem = $this->fetch_grade_item();
        $hasgrade = false;
        if ($gradeitem) {
            $gg = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
            $hasgrade = $gg && $gg->finalgrade !== null;
        }
        $status = $hasgrade ? 'graded' : ($hasattended ? 'submitted' : 'nosubmission');

        return [
            'userid' => $userid,
            'status' => $status,
            'content' => $content,
            'hascontent' => $hasrecordings || $hasattended,
            'files' => [],
            'onlinetext' => '',
            'timecreated' => 0,
            'timemodified' => 0,
            // BBB doesn't have a per-user "submitted at" concept the way
            // assignments / forums / quizzes do — attendance is a stream
            // of events, not a discrete submission. Surface 0 so the
            // marking-panel late badge stays hidden for BBB regardless.
            'submittedat' => 0,
            'attemptnumber' => 0,
            'commentcount' => submission_comment_manager::count_comments($this->cm->id, $userid),
        ];
    }

    /**
     * Get current grade and feedback for a user.
     *
     * @param int $userid
     * @return array
     */
    public function get_grade_data(int $userid): array {
        $gradeitem = $this->fetch_grade_item();
        $hasgrade = false;
        $gradevalue = null;
        $feedbacktext = '';
        $feedbackformat = (int) FORMAT_HTML;
        $timegraded = 0;
        $grader = 0;

        if ($gradeitem) {
            $gradegrade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
            if ($gradegrade) {
                // Show the mark the teacher gave, not the penalised gradebook
                // figure. sync_gradebook_penalty() keeps rawgrade holding the
                // former and finalgrade the latter; reading finalgrade here is
                // what let a re-save deduct a second time from an already
                // reduced mark. Fall back for rows predating that split.
                $storedgrade = $gradegrade->rawgrade ?? $gradegrade->finalgrade;
                if ($storedgrade !== null) {
                    $hasgrade = true;
                    $gradevalue = (float) $storedgrade;
                }
                if (!empty($gradegrade->feedback)) {
                    $feedbacktext = file_rewrite_pluginfile_urls(
                        $gradegrade->feedback,
                        'pluginfile.php',
                        $this->context->id,
                        'local_unifiedgrader',
                        'bbbfeedback',
                        (int) $gradegrade->id,
                    );
                    $feedbackformat = (int) ($gradegrade->feedbackformat ?? FORMAT_HTML);
                }
                $timegraded = (int) ($gradegrade->timemodified ?? 0);
                $grader = (int) ($gradegrade->usermodified ?? 0);
            }
        }

        // Advanced grading (rubric / marking guide) via bbbext_advgrd.
        $rubricdata = null;
        $gradingdefinition = null;
        $controller = $this->get_advgrd_controller();
        if ($controller) {
            $gradingdefinition = $this->serialize_grading_definition($controller, $userid);
            $rubricdata = $this->get_rubric_fill($controller, $userid);
        }

        return [
            'grade' => $hasgrade ? $gradevalue : null,
            'feedback' => format_text($feedbacktext, $feedbackformat, ['context' => $this->context]),
            'feedbackformat' => $feedbackformat,
            'rubricdata' => $rubricdata ? json_encode($rubricdata) : '',
            'gradingdefinition' => $gradingdefinition ? json_encode($gradingdefinition) : '',
            'timegraded' => $timegraded,
            'grader' => $grader,
        ];
    }

    /**
     * Push this student's penalised mark to the gradebook.
     *
     * BigBlueButton has no grades table of its own — bigbluebuttonbn_update_grades()
     * says as much, and bigbluebuttonbn_grade_item_update() writes straight to the
     * gradebook. That is why this activity was left on the old model where the
     * already-reduced mark was stored, and it carried every fault assignments were
     * moved off raw storage to escape: a penalty added after grading never reached
     * the gradebook, removing one never restored the mark, and re-saving deducted a
     * second time from a figure that had already been reduced.
     *
     * The raw mark does have a home, though. grade_grades.rawgrade is written even
     * when the cell is overridden — grade_item::update_raw_grade() guards only
     * finalgrade behind the override check, and refuses outright only on a locked
     * grade. So this adapter splits the two columns by intent:
     *
     * - rawgrade holds what the teacher typed (or what the rubric scored), written
     *   by save_grade() through the module's own grade_item_update().
     * - finalgrade holds rawgrade minus the current penalties, written only here.
     *
     * Which is why this pushes finalgrade directly rather than re-entering
     * bigbluebuttonbn_grade_item_update() the way the quiz adapter does. That call
     * would set rawgrade to the penalised figure, and the next sync would deduct
     * from an already-reduced mark — the very compounding this replaces. Starting
     * from rawgrade and the current penalty rows on every call is what keeps
     * repeated syncs idempotent.
     *
     * Both grading routes converge here: the simple path and bbbext_advgrd's
     * record_grade() both push their score through bigbluebuttonbn_grade_item_update(),
     * so rawgrade holds the un-penalised mark either way and the rubric path needs
     * no special case.
     *
     * @param int $userid The student user ID.
     */
    public function sync_gradebook_penalty(int $userid): void {
        $maxgrade = (float) $this->bbb->grade;
        if ($maxgrade <= 0) {
            // Grade type "none", or a scale — where deducting a percentage would
            // subtract from a scale index and mean nothing.
            return;
        }

        $gradeitem = $this->fetch_grade_item();
        if (!$gradeitem) {
            return;
        }

        $gradegrade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
        if (!$gradegrade) {
            return;
        }

        // A locked grade is an administrative decision, not ours to undo.
        if (!empty($gradegrade->locked)) {
            return;
        }

        // The teacher's own mark. Deliberately NOT falling back to finalgrade:
        // once a penalty has pinned the cell, a cleared grade leaves rawgrade
        // null while finalgrade still holds the old penalised figure, and reading
        // that back would resurrect the mark the teacher just removed.
        $raw = $gradegrade->rawgrade;

        $deduction = \local_unifiedgrader\penalty_manager::get_total_deduction(
            (int) $this->cm->id,
            $userid,
            $maxgrade,
        );
        $ispinned = !empty($gradegrade->overridden);

        if ($deduction <= 0 && !$ispinned) {
            // Nothing to deduct and no pin of ours to release. Leaving the cell
            // untouched here is what keeps a grade set straight in the gradebook
            // by an administrator from being quietly overwritten.
            return;
        }

        if ($raw !== null && $deduction > 0) {
            // Pin the reduced figure. update_final_grade() sets overridden, which
            // is what stops the module reclaiming the cell on its next update.
            //
            // The feedback argument must be false, not null: update_final_grade()
            // treats anything other than false as "here is new feedback" and
            // writes it, so null erases the marker's comment. BBB keeps its
            // feedback in this very row, so passing null wiped the comment every
            // time a penalty was synced.
            $gradeitem->update_final_grade(
                $userid,
                max(0, (float) $raw - $deduction),
                'local/unifiedgrader',
                false,
            );
            return;
        }

        // Either the last penalty has just been removed, or the grade itself was
        // cleared while a penalty remained. Both mean the cell should follow the
        // raw mark again — including when that mark is now null, which is how a
        // cleared grade reaches the gradebook instead of leaving the last
        // penalised figure stranded there.
        //
        // Refresh is suppressed on the un-pin because the push below is the
        // deterministic version of the recompute it would trigger.
        if ($ispinned) {
            $gradegrade->set_overridden(false, false);
        }

        $bbb = clone $this->bbb;
        $bbb->cmidnumber = $this->cm->idnumber;
        bigbluebuttonbn_grade_item_update($bbb, (object) [
            'userid' => $userid,
            'rawgrade' => $raw === null ? null : (float) $raw,
            'usermodified' => (int) $gradegrade->usermodified,
            'datesubmitted' => 0,
            'dategraded' => (int) ($gradegrade->timemodified ?: time()),
        ]);
    }

    /**
     * Get the advanced-grading definition (rubric/guide) for this BBB activity.
     *
     * @return array|null
     */
    public function get_grading_definition(): ?array {
        $controller = $this->get_advgrd_controller();
        if (!$controller) {
            return null;
        }
        return $this->serialize_grading_definition($controller);
    }

    /**
     * Save a grade and feedback for a user.
     *
     * @param int $userid
     * @param float|null $grade
     * @param string $feedback
     * @param int $feedbackformat
     * @param array $advancedgradingdata Unused (no rubric support yet).
     * @param int $draftitemid Draft area item ID for feedback file uploads.
     * @param int $feedbackfilesdraftid Unused (BBB has no feedback files plugin).
     * @param int $attemptnumber Unused (BBB has no attempts model).
     * @return bool
     */
    public function save_grade(
        int $userid,
        ?float $grade,
        string $feedback,
        int $feedbackformat = FORMAT_HTML,
        array $advancedgradingdata = [],
        int $draftitemid = 0,
        int $feedbackfilesdraftid = 0,
        int $attemptnumber = -1,
    ): bool {
        global $USER;

        // Advanced grading (bbbext_advgrd) — when criteria data was submitted,
        // run it through the grading instance so the rubric/guide fillings persist
        // and the per-user evidence snapshot is captured. record_grade() also
        // pushes the resulting score to the gradebook (passthroughtogradebook=1
        // by default), so the simple grade flow below is skipped in that path.
        $controller = $this->get_advgrd_controller();
        if ($controller && !empty($advancedgradingdata)) {
            $this->save_grade_via_advgrd($controller, $userid, $advancedgradingdata);
        } else {
            $bbb = clone $this->bbb;
            $bbb->cmidnumber = $this->cm->idnumber;

            $gradeobj = (object) [
                'userid' => $userid,
                'rawgrade' => $grade,
                'usermodified' => (int) $USER->id,
                'datesubmitted' => 0,
                'dategraded' => time(),
            ];
            bigbluebuttonbn_grade_item_update($bbb, $gradeobj);
        }

        // Process feedback files from the draft area, then persist feedback in
        // the gradebook (BBB has no dedicated feedback table).
        $feedbacktosave = $feedback;
        $gradeitem = $this->fetch_grade_item();
        if ($gradeitem) {
            $gradegrade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
            if ($gradegrade) {
                if ($draftitemid > 0) {
                    $feedbacktosave = file_save_draft_area_files(
                        $draftitemid,
                        $this->context->id,
                        'local_unifiedgrader',
                        'bbbfeedback',
                        (int) $gradegrade->id,
                        $this->get_editor_options(),
                        $feedback,
                    );
                }
                $gradegrade->feedback = $feedbacktosave;
                $gradegrade->feedbackformat = $feedbackformat;
                $gradegrade->update('local/unifiedgrader');
            }
        }

        return true;
    }

    /**
     * Prepare the feedback draft area for a student.
     *
     * Mirrors the forum adapter pattern: clear the shared draft area, copy the
     * student's existing feedback files into it, and return the feedback HTML
     * with draftfile.php URLs.
     *
     * @param int $userid The student user ID.
     * @param int $draftitemid The shared draft area item ID.
     * @param int $attemptnumber Unused.
     * @return array With key 'feedbackhtml'.
     */
    public function prepare_feedback_draft(int $userid, int $draftitemid, int $attemptnumber = -1): array {
        global $USER;

        $feedbacktext = '';
        $gradegradeid = 0;

        $gradeitem = $this->fetch_grade_item();
        if ($gradeitem) {
            $gradegrade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
            if ($gradegrade) {
                $gradegradeid = (int) $gradegrade->id;
                $feedbacktext = $gradegrade->feedback ?? '';
            }
        }

        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $fs->delete_area_files($usercontext->id, 'user', 'draft', $draftitemid);

        if ($gradegradeid) {
            $files = $fs->get_area_files(
                $this->context->id,
                'local_unifiedgrader',
                'bbbfeedback',
                $gradegradeid,
            );
            $filerecord = [
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
            ];
            foreach ($files as $file) {
                if ($file->is_directory() && $file->get_filepath() === '/') {
                    continue;
                }
                $fs->create_file_from_storedfile($filerecord, $file);
            }
        }

        if (!empty($feedbacktext)) {
            $feedbacktext = file_rewrite_pluginfile_urls(
                $feedbacktext,
                'draftfile.php',
                $usercontext->id,
                'user',
                'draft',
                $draftitemid,
                $this->get_editor_options(),
            );
        }

        return ['feedbackhtml' => $feedbacktext];
    }

    /**
     * Get submission files. BBB recordings are remote, not Moodle files.
     *
     * @param int $userid
     * @return array
     */
    public function get_submission_files(int $userid): array {
        return [];
    }

    /**
     * Check feature support.
     *
     * @param string $feature
     * @return bool
     */
    public function supports_feature(string $feature): bool {
        $advgrdmethod = $this->get_advgrd_method();
        return match ($feature) {
            'onlinetext' => true,
            'rubric' => $advgrdmethod === 'rubric',
            'markingguide' => $advgrdmethod === 'guide',
            'filesubmission', 'blindmarking', 'annotations' => false,
            default => false,
        };
    }

    /**
     * Check whether the BBB grade is released and visible to the student.
     *
     * @param int $userid
     * @return bool
     */
    public function is_grade_released(int $userid): bool {
        $gradeitem = $this->fetch_grade_item();
        if (!$gradeitem) {
            return false;
        }
        if ($gradeitem->is_hidden()) {
            return false;
        }
        $gradegrade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
        return $gradegrade && $gradegrade->finalgrade !== null;
    }

    /**
     * Resolve the active bbbext_advgrd grading method for this BBB instance.
     *
     * Returns 'rubric' or 'guide' when the extension is installed, configured,
     * and the configured method actually has a defined form. Returns null when
     * the extension is missing, disabled, or has no usable definition — in which
     * case we fall back to simple grading.
     *
     * @return string|null
     */
    private function get_advgrd_method(): ?string {
        $controller = $this->get_advgrd_controller();
        if (!$controller) {
            return null;
        }
        $method = get_grading_manager($this->context, 'bbbext_advgrd', 'participation')->get_active_method();
        return ($method === 'rubric' || $method === 'guide') ? $method : null;
    }

    /**
     * Get the bbbext_advgrd grading controller when it's installed, configured,
     * and a definition has been published. Returns null otherwise.
     *
     * @return \gradingform_controller|null
     */
    private function get_advgrd_controller(): ?\gradingform_controller {
        if (!class_exists('\\bbbext_advgrd\\local\\grader')) {
            return null;
        }
        try {
            $manager = \bbbext_advgrd\local\grader::get_grading_manager((int) $this->bbb->id);
        } catch (\Throwable $e) {
            // The grader::get_grading_manager throws when no method is configured.
            return null;
        }
        $controller = $manager->get_active_controller();
        if (!$controller || !$controller->is_form_defined()) {
            return null;
        }
        // The grading form must have its scale aligned to this activity's grade max
        // before fillings or scores are read; matches what bbbext_advgrd does on
        // its own grading page.
        $maxgrade = (int) ($this->bbb->grade > 0 ? $this->bbb->grade : 100);
        $controller->set_grade_range(make_grades_menu($maxgrade), $this->bbb->grade > 0);
        return $controller;
    }

    /**
     * Serialise the rubric or marking guide definition to the same shape the
     * forum / assign adapters use, plus a 'suggestions' map keyed by criterion
     * id when the user is provided. The grading panel can use suggestions to
     * highlight metric-driven recommended levels.
     *
     * @param \gradingform_controller $controller
     * @param int|null $userid Optional user id for which to compute suggestions.
     * @return array|null
     */
    private function serialize_grading_definition(
        \gradingform_controller $controller,
        ?int $userid = null,
    ): ?array {
        $definition = $controller->get_definition();
        if (!$definition) {
            return null;
        }

        $method = get_grading_manager($this->context, 'bbbext_advgrd', 'participation')->get_active_method();

        $result = [
            'id' => (int) $definition->id,
            'method' => $method,
            'name' => $definition->name ?? '',
            'description' => $definition->description ?? '',
            'area' => 'bbbext_advgrd/participation',
        ];

        if ($method === 'rubric' && !empty($definition->rubric_criteria)) {
            $criteria = [];
            foreach ($definition->rubric_criteria as $criterionid => $criterion) {
                $levels = [];
                if (!empty($criterion['levels'])) {
                    foreach ($criterion['levels'] as $levelid => $level) {
                        $levels[] = [
                            'id' => (int) $levelid,
                            'score' => (float) ($level['score'] ?? 0),
                            'definition' => $level['definition'] ?? '',
                        ];
                    }
                    usort($levels, fn($a, $b) => $a['score'] <=> $b['score']);
                }
                $criteria[] = [
                    'id' => (int) $criterionid,
                    'description' => $criterion['description'] ?? '',
                    'levels' => $levels,
                ];
            }
            $result['criteria'] = $criteria;
        } else if ($method === 'guide' && !empty($definition->guide_criteria)) {
            $criteria = [];
            foreach ($definition->guide_criteria as $criterionid => $criterion) {
                $criteria[] = [
                    'id' => (int) $criterionid,
                    'shortname' => $criterion['shortname'] ?? '',
                    'description' => $criterion['description'] ?? '',
                    'descriptionmarkers' => format_text(
                        $criterion['descriptionmarkers'] ?? '',
                        FORMAT_HTML,
                        ['context' => $this->context],
                    ),
                    'maxscore' => (float) ($criterion['maxscore'] ?? 0),
                ];
            }
            $result['criteria'] = $criteria;
        }

        // Metrics-driven suggested levels per criterion (rubric: level score;
        // guide: numeric mark) — see bbbext_advgrd\local\grader::suggest_levels.
        if ($userid !== null && class_exists('\\bbbext_advgrd\\local\\grader')) {
            try {
                $suggestions = \bbbext_advgrd\local\grader::suggest_levels((int) $this->bbb->id, $userid);
                if (!empty($suggestions)) {
                    // For rubric, resolve each (criterionid, score) → levelid so the
                    // marking pane can highlight the suggested cell directly.
                    $result['suggestions'] = $this->resolve_rubric_suggestions($method, $suggestions);
                }
            } catch (\Throwable $e) {
                // Suggestions are advisory — never block grading.
                debugging('bbbext_advgrd suggestion lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return $result;
    }

    /**
     * Convert raw suggestion values (rubric level scores / guide numeric marks)
     * into a shape the marking pane can consume directly.
     *
     * @param string $method 'rubric' or 'guide'.
     * @param array $suggestions Map of criterionid => raw value (level score or numeric mark).
     * @return array Map of criterionid => ['score' => float, 'levelid' => int].
     */
    private function resolve_rubric_suggestions(string $method, array $suggestions): array {
        global $DB;

        $resolved = [];
        if ($method === 'rubric' && !empty($suggestions)) {
            // One query per criterion is fine — definitions rarely exceed a few criteria.
            foreach ($suggestions as $cid => $score) {
                $level = $DB->get_record_sql(
                    "SELECT id FROM {gradingform_rubric_levels}
                      WHERE criterionid = :cid AND score = :score
                   ORDER BY id ASC",
                    ['cid' => (int) $cid, 'score' => (float) $score],
                    IGNORE_MULTIPLE,
                );
                $entry = ['score' => (float) $score];
                if ($level) {
                    $entry['levelid'] = (int) $level->id;
                }
                $resolved[(int) $cid] = $entry;
            }
        } else {
            foreach ($suggestions as $cid => $score) {
                $resolved[(int) $cid] = ['score' => (float) $score];
            }
        }
        return $resolved;
    }

    /**
     * Read the existing rubric/guide fill for a user, if one has been recorded
     * via bbbext_advgrd's grader::record_grade.
     *
     * @param \gradingform_controller $controller
     * @param int $userid
     * @return array|null
     */
    private function get_rubric_fill(\gradingform_controller $controller, int $userid): ?array {
        try {
            // The bbbext_advgrd plugin creates grading instances with itemid = userid
            // (see grader.php / grade.php: get_or_create_instance($iid, $rater, $userid)).
            $instances = $controller->get_active_instances($userid);
            if (empty($instances)) {
                return null;
            }
            $instance = end($instances);
            if ($instance instanceof \gradingform_guide_instance) {
                return $instance->get_guide_filling();
            } else if ($instance instanceof \gradingform_rubric_instance) {
                return $instance->get_rubric_filling();
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Persist a rubric/guide grading via the bbbext_advgrd flow.
     *
     * Creates (or fetches) the active grading instance for this rater+user,
     * submits the criteria fillings, and delegates to grader::record_grade —
     * which writes the per-user evidence snapshot, pushes the score to the
     * gradebook, and (in analytic rubric mode) emits per-presence sub-scores.
     *
     * @param \gradingform_controller $controller
     * @param int $userid
     * @param array $advancedgradingdata Submitted from the rubric/guide form.
     */
    private function save_grade_via_advgrd(
        \gradingform_controller $controller,
        int $userid,
        array $advancedgradingdata,
    ): void {
        global $DB, $USER;

        // The marking-pane JS hands us the criteria payload but no instanceid.
        // Reuse the previously-created grading instance for this user where
        // possible — otherwise every save creates a fresh grading_instances
        // row, orphaning the prior one without its fillings, and the post-save
        // refresh shows empty cells.
        $instanceid = (int) ($advancedgradingdata['instanceid'] ?? 0) ?: null;
        if ($instanceid === null) {
            $instanceid = (int) ($DB->get_field_sql(
                'SELECT g.gradinginstanceid
                   FROM {bbbext_advgrd_grade} g
                   JOIN {bbbext_advgrd_config} c ON c.id = g.configid
                  WHERE c.bigbluebuttonbnid = :bbbid AND g.userid = :userid',
                ['bbbid' => (int) $this->bbb->id, 'userid' => $userid],
            ) ?: 0) ?: null;
        }
        $gradinginstance = $controller->get_or_create_instance($instanceid, (int) $USER->id, $userid);

        // The gradingform_{rubric,guide}_instance::is_empty_form / update / validate
        // all read $elementvalue['criteria'][$id][...] — they expect the full
        // payload with 'criteria' at the top level, not just the inner map.
        // Unwrapping here was making is_empty_form() return true, which silently
        // cleared the fillings and forced rawscore to -1 (→ 0 in the gradebook).
        $rawscore = $gradinginstance->submit_and_get_grade($advancedgradingdata, $userid);

        \bbbext_advgrd\local\grader::record_grade(
            (int) $this->bbb->id,
            $userid,
            (int) $USER->id,
            $rawscore !== false ? (float) $rawscore : null,
            (int) $gradinginstance->get_id(),
        );
    }

    /**
     * Get per-user attendance stats from join + summary logs.
     *
     * Returns one record per attended user with:
     *   - sessioncount: number of distinct attendance log rows (Join + Summary).
     *   - lastattended: max timecreated across those rows.
     *
     * @param int $bbbid The BBB instance id.
     * @return array<int, \stdClass> Keyed by userid.
     */
    private function get_attendance_stats(int $bbbid): array {
        global $DB;

        $sql = "SELECT userid,
                       COUNT(*) AS sessioncount,
                       MAX(timecreated) AS lastattended
                  FROM {bigbluebuttonbn_logs}
                 WHERE bigbluebuttonbnid = :bbbid AND log IN (:joinlog, :summarylog)
              GROUP BY userid";
        $stats = $DB->get_records_sql($sql, [
            'bbbid' => $bbbid,
            'joinlog' => \mod_bigbluebuttonbn\logger::EVENT_JOIN,
            'summarylog' => \mod_bigbluebuttonbn\logger::EVENT_SUMMARY,
        ]);

        // Merge in scraped engagement (when callbacks are not configured) so
        // students who only appear on the BBB statistics page still register
        // as having attended.
        $scraped = engagement_service::get_attendance_stats((int) $this->cm->id);
        foreach ($scraped as $userid => $row) {
            if (!isset($stats[$userid])) {
                $stats[$userid] = (object) [
                    'userid' => (int) $userid,
                    'sessioncount' => (int) $row->sessioncount,
                    'lastattended' => (int) $row->lastattended,
                ];
            }
        }

        return $stats;
    }

    /**
     * Get recordings visible to the current user, simplified for JSON output.
     *
     * Filters by group mode (delegated to BBB's own recording API). Returns
     * recordings ordered by start time ascending (oldest first).
     *
     * Protected (not private) so unit tests can stub it with synthetic recordings:
     * real recording metadata (playbacks, start time) is only available from the
     * (mock) BBB server, which PHPUnit has no access to.
     *
     * @param int $userid The student whose recordings are being inspected.
     *                    Note: BBB recordings are scoped to the meeting/group, not
     *                    the participant — so the userid only affects group filtering.
     * @return array Each entry: {recordingid, name, playbackurl, starttime, endtime, groupid, sessionlabel}.
     */
    protected function get_recordings_for_user(int $userid): array {
        $instance = \mod_bigbluebuttonbn\instance::get_from_cmid($this->cm->id);
        if (!$instance) {
            return [];
        }

        // NB: BBB filters this by the *viewer's* group, so a student in a
        // separate-groups activity may see fewer recordings than the grader — or
        // none at all when meetings ran with groupid 0.
        $recordings = \mod_bigbluebuttonbn\recording::get_recordings_for_instance($instance);

        $result = [];
        $present = [];
        foreach ($recordings as $rec) {
            $entry = $this->build_recording_entry($rec);
            if ($entry === null) {
                continue;
            }
            $result[] = $entry;
            $present[$entry['bbbrecordingid']] = true;
        }

        // Personal-feedback rescue: surface every recording this user has
        // annotation feedback on — fetched by id, which bypasses the group filter
        // above — so a student can always reach feedback addressed to them and the
        // annotation overlay has a recording to render. Graders already see all
        // recordings, so this adds nothing for them.
        foreach ($this->get_feedback_recording_ids($userid) as $recid) {
            if ($recid === '' || isset($present[$recid])) {
                continue;
            }
            $rec = \mod_bigbluebuttonbn\recording::get_record([
                'bigbluebuttonbnid' => (int) $this->bbb->id,
                'recordingid' => $recid,
            ]);
            $entry = $rec ? $this->build_recording_entry($rec) : null;
            if ($entry !== null) {
                $result[] = $entry;
                $present[$recid] = true;
            }
        }

        usort($result, fn($a, $b) => $a['starttime'] <=> $b['starttime']);

        return $result;
    }

    /**
     * Narrow the recording list to the sessions $userid actually attended.
     *
     * Attendance is what a BBB submission is, so a teacher marking a student
     * should be shown that student's sessions and not the whole activity's.
     * The per-session attendance rows come from EVENT_SUMMARY logs (or the
     * scraped cache) and carry the BBB recording id, so the join is exact.
     *
     * Two guards keep "we have no record" from being rendered as "they were
     * absent". Each fires on its own positive signal rather than on the absence
     * of this student's rows — which is the same evidence as absence, and would
     * make the filter contradict itself:
     *
     * 1. A recording no one has attendance data for. Attendance only started
     *    being recorded when the analytics callback was switched on (or the
     *    scrape first ran), so earlier recordings have no roster at all and
     *    nobody can be shown to have missed them. Judged per recording, not per
     *    student, so a site that enabled the callback midway keeps its older
     *    sessions visible while newer ones filter properly.
     * 2. A join log with no summary. EVENT_JOIN is written when the student
     *    clicks Join and carries no recording id, so it is hard evidence of
     *    attendance that cannot be attributed to a session. This also covers a
     *    summary whose meta arrived without a recordid: sessions exist but none
     *    resolves, and guessing would be worse than showing everything.
     *
     * Recordings carrying annotation feedback for this student are always kept
     * — feedback addressed to them must stay reachable however they came by it.
     *
     * @param array $recordings Entries from get_recordings_for_user().
     * @param array $sessions Engagement sessions for this user (each has 'recordingref').
     * @param int $userid
     * @param bool $hasjoined Whether the user has a join or summary log for this activity.
     * @return array The kept subset, in the order given.
     */
    private function filter_to_attended_recordings(
        array $recordings,
        array $sessions,
        int $userid,
        bool $hasjoined
    ): array {
        $attendedrefs = $this->get_attended_recording_refs($userid, $sessions);

        // Guard 2 — present, but not attributable to any session.
        if (empty($attendedrefs) && $hasjoined) {
            return [
                'recordings' => $recordings,
                'reason' => 'noattribution',
                'sessionref' => '',
                'recordingref' => (string) ($recordings[0]['bbbrecordingid'] ?? ''),
            ];
        }

        $rostered = $this->get_recordings_with_attendance_data();
        $feedbackrefs = array_flip($this->get_feedback_recording_ids($userid));

        $kept = [];
        foreach ($recordings as $rec) {
            $ref = (string) $rec['bbbrecordingid'];
            if (
                isset($attendedrefs[$ref])
                || isset($feedbackrefs[$ref])
                // Guard 1 — no roster for this recording, so absence is unknowable.
                || !isset($rostered[$ref])
            ) {
                $kept[] = $rec;
            }
        }

        // Guard 3 — the student demonstrably attended something, yet not one of
        // their sessions matched a recording. That is a contradiction, not an
        // absence: the two identifiers have failed to reconcile, and the honest
        // response is to show the sessions rather than report a student who was
        // there as having missed everything. Without this, a site whose session
        // ids do not line up with its recording ids loses every pill the moment
        // its recordings acquire rosters.
        if (empty($kept) && !empty($attendedrefs)) {
            return [
                'recordings' => $recordings,
                'reason' => 'unreconciled',
                'sessionref' => (string) array_key_first($attendedrefs),
                'recordingref' => (string) ($recordings[0]['bbbrecordingid'] ?? ''),
            ];
        }

        // Nothing was hidden, and only because no recording had a roster to
        // judge against — worth saying so, since it looks identical on screen to
        // a filter that simply did not run.
        $reason = '';
        if (count($kept) === count($recordings) && empty($rostered)) {
            $reason = 'norosters';
        }

        return [
            'recordings' => $kept,
            'reason' => $reason,
            'sessionref' => (string) (array_key_first($attendedrefs) ?? ''),
            'recordingref' => (string) ($recordings[0]['bbbrecordingid'] ?? ''),
        ];
    }

    /**
     * BBB recording ids this user attended, from every source that records one.
     *
     * Deliberately a union rather than the single source get_engagement_summary()
     * settles on for the metrics tiles. That method prefers summary logs and only
     * falls back to the cached rows, which is right for display — the callback
     * payload is richer — but wrong for deciding attendance, because the two
     * sources identify a session differently:
     *
     *  - Cached rows are stamped with the recording id by the refresh itself,
     *    which walks the recordings one at a time. They cannot disagree.
     *  - Summary logs carry whatever the BBB server put in the callback's
     *    `recordid`. On sites where that does not correspond to the recording
     *    ids, every session the student attended resolves to nothing.
     *
     * Reading only the preferred source meant that on a site holding both, the
     * roster was built from the cached rows while the student's own sessions came
     * from the logs — so the comparison was between two different id spaces and
     * every recording looked attended by nobody.
     *
     * @param int $userid
     * @param array $sessions Sessions already resolved for the metrics tiles.
     * @return array<string, true> Set keyed by BBB recording id.
     */
    private function get_attended_recording_refs(int $userid, array $sessions): array {
        global $DB;

        $refs = [];
        foreach ($sessions as $session) {
            $ref = (string) ($session['recordingref'] ?? '');
            if ($ref !== '') {
                $refs[$ref] = true;
            }
        }

        $cached = $DB->get_fieldset_select(
            'local_unifiedgrader_bbbeng',
            'DISTINCT recordingid',
            'cmid = :cmid AND userid = :userid',
            ['cmid' => (int) $this->cm->id, 'userid' => $userid],
        );
        foreach ($cached as $ref) {
            if ((string) $ref !== '') {
                $refs[(string) $ref] = true;
            }
        }

        return $refs;
    }

    /**
     * The recording this user was present longest for, or '' when unknown.
     *
     * Read from the same two sources as get_attended_recording_refs(), for the
     * same reason: whichever one carries a usable recording id should decide,
     * rather than only the one the metrics tiles happen to prefer. Where both
     * describe the same session the longer figure wins, so a partial record
     * never displaces a complete one.
     *
     * @param int $userid
     * @param array $sessions Sessions already resolved for the metrics tiles.
     * @return string BBB recording id, or '' when no attendance carries one.
     */
    private function get_longest_attended_ref(int $userid, array $sessions): string {
        global $DB;

        $durations = [];
        foreach ($sessions as $session) {
            $ref = (string) ($session['recordingref'] ?? '');
            if ($ref === '') {
                continue;
            }
            $durations[$ref] = max($durations[$ref] ?? 0, (int) ($session['duration'] ?? 0));
        }

        $cached = $DB->get_records_sql(
            "SELECT recordingid, MAX(duration) AS duration
               FROM {local_unifiedgrader_bbbeng}
              WHERE cmid = :cmid AND userid = :userid
           GROUP BY recordingid",
            ['cmid' => (int) $this->cm->id, 'userid' => $userid],
        );
        foreach ($cached as $row) {
            $ref = (string) $row->recordingid;
            if ($ref !== '') {
                $durations[$ref] = max($durations[$ref] ?? 0, (int) $row->duration);
            }
        }

        if (empty($durations)) {
            return '';
        }
        arsort($durations);

        return (string) array_key_first($durations);
    }

    /**
     * BBB recording ids that anyone has attendance data against, for this activity.
     *
     * Answers "did we ever learn who attended this session?", which is what
     * separates a genuine absence from a session we never had a roster for.
     * Deliberately not scoped to a user.
     *
     * Protected so tests can declare which recordings have rosters without
     * seeding attendee logs for every participant.
     *
     * @return array<string, true> Set keyed by BBB recording id.
     */
    protected function get_recordings_with_attendance_data(): array {
        global $DB;

        $rostered = [];

        // The recording id sits inside the JSON meta of each summary log, so
        // this cannot be a GROUP BY — decode and collect. One row per attendee
        // per meeting keeps the set small enough to walk.
        $metas = $DB->get_fieldset_select(
            'bigbluebuttonbn_logs',
            'meta',
            'bigbluebuttonbnid = :bbbid AND log = :logtype',
            [
                'bbbid' => (int) $this->bbb->id,
                'logtype' => \mod_bigbluebuttonbn\logger::EVENT_SUMMARY,
            ],
        );
        foreach ($metas as $meta) {
            if (empty($meta)) {
                continue;
            }
            $decoded = json_decode($meta);
            $ref = (string) ($decoded->recordid ?? '');
            if ($ref !== '') {
                $rostered[$ref] = true;
            }
        }

        // Scraped rows carry the recording id in a column of its own.
        $scraped = $DB->get_fieldset_select(
            'local_unifiedgrader_bbbeng',
            'DISTINCT recordingid',
            'cmid = :cmid',
            ['cmid' => (int) $this->cm->id],
        );
        foreach ($scraped as $ref) {
            if ((string) $ref !== '') {
                $rostered[(string) $ref] = true;
            }
        }

        return $rostered;
    }

    /**
     * Build a switcher/preview entry from a recording entity, or null when the
     * recording has no usable playback URL.
     *
     * @param \mod_bigbluebuttonbn\recording $rec
     * @return array|null
     */
    private function build_recording_entry(\mod_bigbluebuttonbn\recording $rec): ?array {
        $playbackurl = $this->extract_playback_url($rec);
        if (empty($playbackurl)) {
            return null;
        }

        $groupid = (int) ($rec->get('groupid') ?? 0);
        $playbackurl = $this->pin_group_param($playbackurl, $groupid);

        $starttime = (int) ($rec->get('starttime') ?? 0);
        // BBB stores starttime in milliseconds; normalise to seconds.
        if ($starttime > 1000000000000) {
            $starttime = (int) ($starttime / 1000);
        }
        $endtime = (int) ($rec->get('endtime') ?? 0);
        if ($endtime > 1000000000000) {
            $endtime = (int) ($endtime / 1000);
        }

        $name = trim((string) $rec->get('name'));
        if ($name === '') {
            $name = format_string($this->bbb->name);
        }

        // Direct link to BBB's hosted Statistics dashboard (opened target=_blank
        // from the preview pane): the full per-attendee breakdown, polls, etc.
        $statisticsurl = $rec->get_remote_playback_url('statistics');

        return [
            'recordingid' => (int) $rec->get('id'),
            // BBB internal recording id — same string used in the SUMMARY log
            // meta.recordid and in local_unifiedgrader_bbbeng, and the id the
            // switcher pills reload with to pivot the overlay + tiles.
            'bbbrecordingid' => (string) ($rec->get('recordingid') ?? ''),
            'name' => $name,
            'playbackurl' => $playbackurl,
            'statisticsurl' => $statisticsurl ?: '',
            'hasstatisticsurl' => !empty($statisticsurl),
            'starttime' => $starttime,
            'endtime' => $endtime,
            'groupid' => $groupid,
            'sessionlabel' => $starttime > 0 ? userdate($starttime) : $name,
        ];
    }

    /**
     * BBB recording ids that carry annotation feedback for $userid, via
     * bbbext_advgrd. Empty when the extension is absent.
     *
     * Protected so tests can stub it — the underlying lookup needs bbbext_advgrd
     * installed with real annotation rows.
     *
     * @param int $userid
     * @return string[]
     */
    protected function get_feedback_recording_ids(int $userid): array {
        global $CFG;
        // Procedural functions aren't PSR-4 autoloaded, so require the lib before
        // probing for the public entry point (idempotent — see get_submission_data).
        $advgrdlib = $CFG->dirroot . '/mod/bigbluebuttonbn/extension/advgrd/lib.php';
        if (file_exists($advgrdlib)) {
            require_once($advgrdlib);
        }
        if (!function_exists('bbbext_advgrd_recording_ids_with_feedback')) {
            return [];
        }
        try {
            return bbbext_advgrd_recording_ids_with_feedback((int) $this->cm->id, $userid);
        } catch (\Throwable $e) {
            debugging(
                'bbbext_advgrd_recording_ids_with_feedback failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER,
            );
            return [];
        }
    }

    /**
     * Extract a playback URL from a recording, preferring richer formats.
     *
     * Order: presentation (slides + video + chat replay) → video (raw video)
     * → first available playback. The URL routes through Moodle's bbb_view.php
     * wrapper so the EVENT_PLAYED log is recorded and authentication flows correctly.
     *
     * @param \mod_bigbluebuttonbn\recording $rec
     * @return string|null
     */
    private function extract_playback_url(\mod_bigbluebuttonbn\recording $rec): ?string {
        $playbacks = $rec->get('playbacks');
        if (!is_array($playbacks) || empty($playbacks)) {
            return null;
        }

        $bytype = [];
        foreach ($playbacks as $pb) {
            if (!isset($pb['type'], $pb['url'])) {
                continue;
            }
            $url = $pb['url'];
            $bytype[$pb['type']] = $url instanceof \moodle_url ? $url->out(false) : (string) $url;
        }

        if (empty($bytype)) {
            return null;
        }

        return $bytype['presentation'] ?? $bytype['video'] ?? reset($bytype);
    }

    /**
     * Pin a playback URL to the recording's own group.
     *
     * BBB builds playback URLs as bbb_view.php?action=play&bn=…&rid=… with no group.
     * bbb_view.php then resolves the group with groups_get_activity_group($cm, true),
     * which — absent a `group` param — falls back to the viewer's *sticky* active
     * group in $SESSION. When that sticky group differs from the recording's group,
     * instance::get_recordings() filters the recording out and bbb_view.php answers
     * "The recording was not found." and bounces to the activity page — inside our
     * preview iframe, which is all the teacher sees. Teachers pick up a sticky group
     * simply by using a group selector anywhere in the course, so the grader must say
     * explicitly which group it means.
     *
     * group=0 ("All participants") is only honoured for viewers with accessallgroups
     * or in visible-groups mode, and is ignored otherwise — harmless either way.
     *
     * @param string $url The bbb_view.php playback URL.
     * @param int $groupid The recording's group (0 = no group / all participants).
     * @return string
     */
    private function pin_group_param(string $url, int $groupid): string {
        $moodleurl = new \moodle_url($url);
        $moodleurl->param('group', $groupid);
        // out(false) — the caller hands this to Mustache/html_writer, which escapes.
        return $moodleurl->out(false);
    }

    /**
     * Aggregate engagement metrics across all sessions for a user, plus a
     * per-session breakdown so the marking pane can pivot the Activity Points
     * card to a single session when the teacher selects it in the recording
     * switcher.
     *
     * Sessions come from EVENT_SUMMARY logs when present (the BBB analytics
     * callback path) or from cached scrape rows in local_unifiedgrader_bbbeng
     * as a fallback. Each session entry exposes 'recordingref' which matches
     * the recording's BBB internal id (for switcher matching), and the
     * BBB-computed Activity Score 0-10 when available (scraped data only —
     * the score is computed client-side by the BBB statistics page, not
     * shipped in the meeting-events callback payload).
     *
     * @param int $userid
     * @return array {chats, talks, raisehand, pollvotes, emojis, duration,
     *                sessioncount, durationformatted, source, sessions[],
     *                activityscore (avg|null), activityscoreformatted,
     *                hasactivityscore, hassessions}
     */
    private function get_engagement_summary(int $userid): array {
        $sessions = $this->extract_sessions_from_logs($userid);
        $source = !empty($sessions) ? 'callback' : null;

        if (empty($sessions)) {
            $sessions = $this->extract_sessions_from_scrape($userid);
            if (!empty($sessions)) {
                $source = 'scraped';
            }
        }

        $summary = $this->aggregate_session_metrics($sessions);
        if ($source !== null) {
            $summary['source'] = $source;
        }
        $summary['sessions'] = $sessions;
        $summary['hassessions'] = !empty($sessions);
        $summary['durationformatted'] = $this->format_duration($summary['duration']);

        return $summary;
    }

    /**
     * Extract per-session engagement entries from EVENT_SUMMARY rows.
     *
     * @param int $userid
     * @return array<int, array> Each entry: {recordingref, timecreated, chats, talks,
     *                                        raisehand, pollvotes, emojis, duration,
     *                                        durationformatted, activityscore,
     *                                        activityscoreformatted, hasactivityscore}.
     */
    private function extract_sessions_from_logs(int $userid): array {
        global $DB;

        $logs = $DB->get_records_select(
            'bigbluebuttonbn_logs',
            'bigbluebuttonbnid = :bbbid AND userid = :userid AND log = :logtype',
            [
                'bbbid' => (int) $this->bbb->id,
                'userid' => $userid,
                'logtype' => \mod_bigbluebuttonbn\logger::EVENT_SUMMARY,
            ],
            'timecreated ASC',
        );

        $sessions = [];
        foreach ($logs as $log) {
            if (empty($log->meta)) {
                continue;
            }
            $meta = json_decode($log->meta);
            if (!$meta || !isset($meta->data)) {
                continue;
            }
            $duration = (int) ($meta->data->duration ?? 0);
            $eng = $meta->data->engagement ?? null;
            $sessions[] = [
                // Note: 'recordid' (singular) is BBB's key inside the SUMMARY meta
                // payload — see meeting::process_meeting_events. It matches the
                // recording entity's 'recordingid' string, so we can join on it.
                'recordingref' => (string) ($meta->recordid ?? ''),
                'timecreated' => (int) $log->timecreated,
                'chats'     => $eng ? (int) ($eng->chats ?? 0) : 0,
                'talks'     => $eng ? (int) ($eng->talks ?? 0) : 0,
                'raisehand' => $eng ? (int) ($eng->raisehand ?? 0) : 0,
                'pollvotes' => $eng ? (int) ($eng->poll_votes ?? 0) : 0,
                'emojis'    => $eng ? (int) ($eng->emojis ?? 0) : 0,
                'duration'  => $duration,
                'durationformatted' => $this->format_duration($duration),
                // BBB's 0-10 Activity Score is computed client-side by the
                // statistics playback page; it's not in the callback payload.
                'activityscore' => null,
                'activityscoreformatted' => '',
                'hasactivityscore' => false,
            ];
        }
        return $sessions;
    }

    /**
     * Extract per-session engagement entries from the scraped cache.
     *
     * @param int $userid
     * @return array<int, array>
     */
    private function extract_sessions_from_scrape(int $userid): array {
        global $DB;

        $rows = $DB->get_records('local_unifiedgrader_bbbeng', [
            'cmid' => (int) $this->cm->id,
            'userid' => $userid,
        ], 'timefetched ASC');

        $sessions = [];
        foreach ($rows as $row) {
            $duration = (int) $row->duration;
            $hasscore = $row->activityscore !== null;
            $sessions[] = [
                'recordingref' => (string) $row->recordingid,
                'timecreated' => (int) $row->timefetched,
                'chats'     => (int) $row->chats,
                'talks'     => (int) $row->talks,
                'raisehand' => (int) $row->raisehand,
                'pollvotes' => (int) $row->polls,
                'emojis'    => (int) $row->emojis,
                'duration'  => $duration,
                'durationformatted' => $this->format_duration($duration),
                'activityscore' => $hasscore ? (float) $row->activityscore : null,
                'activityscoreformatted' => $hasscore ? number_format((float) $row->activityscore, 1) : '',
                'hasactivityscore' => $hasscore,
            ];
        }
        return $sessions;
    }

    /**
     * Sum the per-session metrics into the cumulative figures the existing
     * Activity Points card expects, plus an averaged Activity Score across
     * sessions that have one.
     *
     * @param array $sessions
     * @return array
     */
    private function aggregate_session_metrics(array $sessions): array {
        $totals = [
            'chats' => 0, 'talks' => 0, 'raisehand' => 0,
            'pollvotes' => 0, 'emojis' => 0, 'duration' => 0,
            'sessioncount' => count($sessions),
        ];
        $scoresum = 0.0;
        $scorecount = 0;
        foreach ($sessions as $s) {
            $totals['chats']     += (int) $s['chats'];
            $totals['talks']     += (int) $s['talks'];
            $totals['raisehand'] += (int) $s['raisehand'];
            $totals['pollvotes'] += (int) $s['pollvotes'];
            $totals['emojis']    += (int) $s['emojis'];
            $totals['duration']  += (int) $s['duration'];
            if (!empty($s['hasactivityscore'])) {
                $scoresum += (float) $s['activityscore'];
                $scorecount++;
            }
        }
        if ($scorecount > 0) {
            $avg = $scoresum / $scorecount;
            $totals['activityscore'] = $avg;
            $totals['activityscoreformatted'] = number_format($avg, 1);
            $totals['hasactivityscore'] = true;
        } else {
            $totals['activityscore'] = null;
            $totals['activityscoreformatted'] = '';
            $totals['hasactivityscore'] = false;
        }
        return $totals;
    }

    /**
     * Format a duration in seconds as "Xh Ym" or "Xm Ys".
     *
     * @param int $seconds
     * @return string
     */
    private function format_duration(int $seconds): string {
        if ($seconds <= 0) {
            return '0m';
        }
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        $secs = $seconds % 60;
        if ($minutes > 0) {
            return $minutes . 'm';
        }
        return $secs . 's';
    }

    /**
     * Editor options for the feedback editor.
     *
     * @return array
     */
    private function get_editor_options(): array {
        global $CFG;
        return [
            'maxfiles' => -1,
            'maxbytes' => $CFG->maxbytes,
            'context' => $this->context,
            'subdirs' => true,
        ];
    }
}
