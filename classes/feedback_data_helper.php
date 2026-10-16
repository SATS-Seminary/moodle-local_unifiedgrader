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

namespace local_unifiedgrader;

/**
 * Helper for parsing and formatting feedback data.
 *
 * Shared between the student feedback view (view_feedback.php) and the
 * feedback PDF download endpoint (download_feedback.php).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za) (https://www.sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_data_helper {
    /**
     * Format the grade display string and compute percentage.
     *
     * @param array $gradedata From adapter get_grade_data()
     * @param array $activityinfo From adapter get_activity_info()
     * @return array {gradedisplay: string, gradevalue: float|null, maxgrade: float, percentage: int|null}
     */
    public static function format_grade(array $gradedata, array $activityinfo): array {
        $gradedisplay = '';
        $gradevalue = null;
        $percentage = null;
        $maxgrade = round($activityinfo['maxgrade'], 2);

        if ($gradedata['grade'] !== null) {
            $gradevalue = round($gradedata['grade'], 2);
            $percentage = $maxgrade > 0 ? round(($gradevalue / $maxgrade) * 100) : 0;
            $gradedisplay = self::format_scale_value($gradevalue, $activityinfo);

            // A scale value has no meaningful denominator or percentage —
            // "Proficient" is the whole answer, and "3 / 4 (75%)" invents a
            // precision the scale does not carry.
            if ($gradedisplay === '') {
                $gradedisplay = $gradevalue . ' / ' . $maxgrade . ' (' . $percentage . '%)';
            } else {
                $percentage = null;
            }
        }

        return [
            'gradedisplay' => $gradedisplay,
            'gradevalue' => $gradevalue,
            'maxgrade' => $maxgrade,
            'percentage' => $percentage,
            // Present only for rating forums, so the student can see how their
            // post ratings were combined into the number above.
            'aggregatelabel' => (string) ($activityinfo['ratingaggregatelabel'] ?? ''),
        ];
    }

    /**
     * Render a grade as its scale label, where the activity uses a scale.
     *
     * Follows core's rule for rating aggregates: a scale value indexes back
     * into the scale, except under SUM and COUNT where the number is the point.
     *
     * @param float $gradevalue
     * @param array $activityinfo From adapter get_activity_info()
     * @return string The label, or an empty string when not scale-graded.
     */
    private static function format_scale_value(float $gradevalue, array $activityinfo): string {
        global $CFG;

        if (empty($activityinfo['usescale']) || empty($activityinfo['scaleitems'])) {
            return '';
        }

        $method = (int) ($activityinfo['ratingaggregatemethod'] ?? 0);
        if ($method !== 0) {
            // This helper is also reached from the assign and quiz feedback
            // views, where rating/lib.php has never been loaded.
            require_once($CFG->dirroot . '/rating/lib.php');
            if ($method === RATING_AGGREGATE_COUNT || $method === RATING_AGGREGATE_SUM) {
                return (string) (int) round($gradevalue);
            }
        }

        $index = (int) round($gradevalue);
        foreach ($activityinfo['scaleitems'] as $item) {
            if ((int) $item['value'] === $index) {
                return (string) $item['label'];
            }
        }
        return '';
    }

    /**
     * Format penalty data into display badges.
     *
     * @param int $cmid Course module ID
     * @param int $userid User ID
     * @return array {haspenalties: bool, penalties: array}
     */
    public static function format_penalties(int $cmid, int $userid): array {
        $penaltybadges = [];
        foreach (self::penalty_rows($cmid, $userid) as $row) {
            $penaltybadges[] = [
                'text' => '-' . $row['percentage'] . '% ' . $row['reason'],
            ];
        }

        return [
            'haspenalties' => !empty($penaltybadges),
            'penalties' => $penaltybadges,
        ];
    }

    /**
     * The penalties on a student's grade, each with the reason a student can read.
     *
     * A late penalty carries how late in its label ("2 day(s) late"), which is
     * detail rather than the reason; a teacher's own penalty carries the reason
     * itself ("Plagiarism").
     *
     * @param int $cmid Course module ID
     * @param int $userid User ID
     * @return array[] Each {category: string, reason: string, detail: string, percentage: int}
     */
    private static function penalty_rows(int $cmid, int $userid): array {
        // A rated forum whose ratings cannot take a penalty has none to show,
        // whatever rows a past setting left behind: none is taken off its grade.
        if (self::is_rated_forum_without_penalties($cmid)) {
            return [];
        }

        $rows = [];

        foreach (penalty_manager::get_penalties($cmid, $userid) as $p) {
            $detail = '';
            if ($p['category'] === 'late') {
                $reason = get_string('penalty_late', 'local_unifiedgrader');
                $detail = (string) $p['label'];
            } else if ($p['category'] === 'wordcount') {
                $reason = get_string('penalty_wordcount', 'local_unifiedgrader');
            } else {
                $reason = $p['label'] ?: get_string('penalty_other', 'local_unifiedgrader');
            }
            $rows[] = [
                'category' => in_array($p['category'], ['late', 'wordcount'], true) ? $p['category'] : 'other',
                'reason' => $reason,
                'detail' => $detail,
                'percentage' => (int) $p['percentage'],
            ];
        }

        // For quizzes, detect late penalty applied by quizaccess_duedate plugin.
        // Calculate directly from settings + attempt time (same logic as the observer).
        // Cannot rely on gradebook feedback text — teachers may overwrite it.
        // Before Moodle 5.3 only: from 5.3 the late penalty is one of the rows above.
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        if ($cm->modname === 'quiz' && \local_unifiedgrader\penalty\compat::use_quizaccess_duedate()) {
            global $DB;
            $settings = $DB->get_record('quizaccess_duedate_instances', ['quizid' => $cm->instance]);
            if ($settings && $settings->penaltyenabled && $settings->duedate) {
                $effectiveduedate = \quizaccess_duedate\override_manager::get_effective_duedate(
                    $cm->instance,
                    $userid,
                );
                if ($effectiveduedate) {
                    $firstattempt = $DB->get_record_sql(
                        'SELECT timefinish FROM {quiz_attempts}
                          WHERE quiz = ? AND userid = ? AND timefinish > 0
                          ORDER BY timefinish ASC LIMIT 1',
                        [$cm->instance, $userid],
                    );
                    if ($firstattempt && $firstattempt->timefinish > $effectiveduedate) {
                        $secondslate = $firstattempt->timefinish - $effectiveduedate;
                        $dayslate = ceil($secondslate / 86400);
                        $totalpenalty = $dayslate * (float) $settings->penalty;
                        if ($settings->penaltycapenabled && $settings->penaltycap > 0) {
                            $totalpenalty = min($totalpenalty, (float) $settings->penaltycap);
                        } else {
                            $totalpenalty = min($totalpenalty, 100);
                        }
                        $pct = (int) round($totalpenalty);
                        if ($pct > 0) {
                            $rows[] = [
                                'category' => 'late',
                                'reason' => get_string('penalty_late', 'local_unifiedgrader'),
                                'detail' => get_string('penalty_late_days', 'local_unifiedgrader', (int) $dayslate),
                                'percentage' => $pct,
                            ];
                        }
                    }
                }
            }
        }

        // Late first, then word count, then the marker's own, so the card, the
        // PDF list and the segments of its ring always read in the same order.
        $order = ['late' => 0, 'wordcount' => 1, 'other' => 2];
        usort($rows, fn(array $a, array $b): int => $order[$a['category']] <=> $order[$b['category']]);

        return $rows;
    }

    /**
     * Whether an activity is a forum graded by post ratings that takes no penalties.
     *
     * @param int $cmid Course module ID
     * @return bool
     */
    private static function is_rated_forum_without_penalties(int $cmid): bool {
        global $DB;

        $cm = get_coursemodule_from_id('forum', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return false;
        }
        $forum = $DB->get_record('forum', ['id' => $cm->instance], 'id, grade_forum, assessed, scale');
        return $forum
            && (int) $forum->grade_forum === 0
            && (int) $forum->assessed > 0
            && !\local_unifiedgrader\adapter\forum_rating_helper::penalties_apply($forum);
    }

    /**
     * Work a student's penalties through to the grade they end up with.
     *
     * The feedback page and the feedback PDF both showed the mark as the marker
     * typed it, with the penalties as bare "-10% Late" chips beside it. Nothing
     * said what the chips were taken from or what was left, and the figure on
     * the page did not match the one in the gradebook. This lays the sum out:
     * the mark awarded, each penalty with its reason and what it cost, and the
     * final grade.
     *
     * Penalties are percentages of the maximum grade, summed, and the result
     * does not go below zero: the same arithmetic the marking panel shows the
     * teacher. A scale grade has no arithmetic to show, so the rows are listed
     * without marks.
     *
     * @param int $cmid Course module ID
     * @param int $userid User ID
     * @param array $gradeinfo From format_grade()
     * @return array {haspenalties: bool, hasgrade: bool, rows: array[], maxgrade: string,
     *     awarded: string, awardedpercentage: float|null, totalpercentage: int, totalmarks: string, finalvalue: float|null,
     *     finalpercentage: int|null, final: string, finaldisplay: string, headerbadge: string}
     */
    public static function penalty_breakdown(int $cmid, int $userid, array $gradeinfo): array {
        $rows = self::penalty_rows($cmid, $userid);
        $maxgrade = (float) $gradeinfo['maxgrade'];
        $hasgrade = $gradeinfo['gradevalue'] !== null && $gradeinfo['percentage'] !== null && $maxgrade > 0;

        $totalpercentage = 0;
        foreach ($rows as $i => $row) {
            $totalpercentage += $row['percentage'];
            $rows[$i]['percentdisplay'] = '-' . $row['percentage'] . '%';
            // Under the reason: how late, where that applies, and the percentage the marks came from.
            $rows[$i]['subline'] = ($row['detail'] !== '' ? $row['detail'] . ' · ' : '') . $rows[$i]['percentdisplay'];
            $rows[$i]['marks'] = $hasgrade ? '-' . round(($row['percentage'] / 100) * $maxgrade, 2) : '';
        }

        $breakdown = [
            'haspenalties' => !empty($rows),
            'hasgrade' => $hasgrade,
            'rows' => $rows,
            'maxgrade' => (string) round($maxgrade, 2),
            'awarded' => '',
            'awardedpercentage' => null,
            'totalpercentage' => $totalpercentage,
            'totalmarks' => '',
            'finalvalue' => null,
            'finalpercentage' => null,
            'final' => '',
            'finaldisplay' => '',
            'headerbadge' => get_string('penalty_header_badge', 'local_unifiedgrader', $totalpercentage),
        ];
        if (!$rows || !$hasgrade) {
            return $breakdown;
        }

        $awarded = (float) $gradeinfo['gradevalue'];
        $final = round(max(0, $awarded - ($totalpercentage / 100) * $maxgrade), 2);
        $finalpercentage = (int) round(($final / $maxgrade) * 100);

        $breakdown['awarded'] = round($awarded, 2) . ' / ' . round($maxgrade, 2);
        $breakdown['awardedpercentage'] = max(0.0, min(100.0, ($awarded / $maxgrade) * 100));
        // What was actually taken off, which is less than the sum of the rows
        // when the penalties come to more than the mark.
        $breakdown['totalmarks'] = '-' . round($awarded - $final, 2);
        $breakdown['finalvalue'] = $final;
        $breakdown['finalpercentage'] = $finalpercentage;
        $breakdown['final'] = $final . ' / ' . round($maxgrade, 2);
        $breakdown['finaldisplay'] = $breakdown['final'] . ' (' . $finalpercentage . '%)';
        return $breakdown;
    }

    /**
     * Put the penalty card into a feedback page's template data.
     *
     * With penalties on the grade, the headline becomes the grade the student
     * ends up with (the one in the gradebook), the card that explains it is
     * added, and the right-hand column is shown even when it would otherwise be
     * empty, because that is where the card sits.
     *
     * @param array $data Template data for one of the feedback_view templates
     * @param int $cmid Course module ID
     * @param int $userid User ID
     * @param array $gradeinfo From format_grade()
     * @return array
     */
    public static function with_penalty_card(array $data, int $cmid, int $userid, array $gradeinfo): array {
        $breakdown = self::penalty_breakdown($cmid, $userid, $gradeinfo);
        $data['penaltycard'] = $breakdown['haspenalties'] ? $breakdown : null;
        if ($breakdown['haspenalties']) {
            $data['showrightcolumn'] = true;
            if ($breakdown['finaldisplay'] !== '') {
                $data['gradedisplay'] = $breakdown['finaldisplay'];
            }
        }
        return $data;
    }

    /**
     * The rubric/marking-guide keys every feedback template expects.
     *
     * Each activity type builds its own template context, and the nine keys
     * below were repeated in each of them. In v2.12.0 the BigBlueButton branch
     * parsed its grading data and then did not pass it on: the criteria reached
     * the PDF but never the screen, and the omission was invisible because the
     * surrounding lines are identical in all four branches. Naming the set once
     * makes a branch that forgets it obvious.
     *
     * @param array $gradinginfo Output of parse_grading_data().
     * @return array Merge into the template context with the + operator.
     */
    public static function grading_template_data(array $gradinginfo): array {
        return [
            'hasrubric' => $gradinginfo['hasrubric'],
            'rubriccriteria' => $gradinginfo['rubriccriteria'],
            'rubrictotal' => $gradinginfo['rubrictotal'],
            'hasguide' => $gradinginfo['hasguide'],
            'guidecriteria' => $gradinginfo['guidecriteria'],
            'guidetotal' => $gradinginfo['guidetotal'],
            'guidemaxtotal' => $gradinginfo['guidemaxtotal'],
            'hasadvancedgrading' => $gradinginfo['hasadvancedgrading'],
            'gradingmethodname' => $gradinginfo['gradingmethodname'],
        ];
    }

    /**
     * A local copy of the site's logo for the feedback PDF, or null when unset.
     *
     * TCPDF needs a file it can read, not a pluginfile URL — fetching that over
     * HTTP would mean the server calling itself and authenticating to do so. The
     * stored file is copied into the request's own temp directory instead, which
     * Moodle removes at the end of the request without us tracking it.
     *
     * Looked up in the order Moodle itself prefers: the compact site logo, then
     * the full one, then the active theme's equivalents for themes that keep
     * their own rather than setting the core pair. A site with none configured
     * gets null and the PDF simply renders without a logo.
     *
     * @return string|null Absolute path to a readable image file.
     */
    public static function resolve_site_logo_path(): ?string {
        global $CFG;

        $fs = \get_file_storage();
        $systemcontext = \context_system::instance();

        $candidates = [
            ['core_admin', 'logocompact'],
            ['core_admin', 'logo'],
            ['theme_' . $CFG->theme, 'logocompact'],
            ['theme_' . $CFG->theme, 'logo'],
        ];

        foreach ($candidates as [$component, $filearea]) {
            $files = $fs->get_area_files(
                $systemcontext->id,
                $component,
                $filearea,
                0,
                'itemid',
                false,
            );
            $file = reset($files);
            if (!$file) {
                continue;
            }
            // Guard the format: TCPDF reads PNG, JPEG and GIF, and an SVG site
            // logo (perfectly valid on screen) would otherwise abort the PDF.
            if (!in_array($file->get_mimetype(), ['image/png', 'image/jpeg', 'image/gif'], true)) {
                continue;
            }

            $extension = match ($file->get_mimetype()) {
                'image/jpeg' => '.jpg',
                'image/gif' => '.gif',
                default => '.png',
            };
            $path = \make_request_directory() . '/logo' . $extension;
            $file->copy_content_to($path);

            return $path;
        }

        return null;
    }

    /**
     * Parse rubric/marking guide data for display.
     *
     * The teacher-authored HTML fields (`description`, `definition`, `remark`)
     * are run through `format_text()` before being returned because the student
     * feedback view renders them via Mustache triple-stache (`{{{ ... }}}`).
     * Without formatting, a teacher with edit rights could store XSS that fires
     * in the student's browser.
     *
     * @param array $gradedata From adapter get_grade_data().
     * @param \context $context Module context — required for media filters /
     *                          pluginfile rewrites in the formatted HTML.
     * @return array {
     *     hasrubric: bool, rubriccriteria: array, rubrictotal: float,
     *     hasguide: bool, guidecriteria: array, guidetotal: float, guidemaxtotal: float,
     *     hasadvancedgrading: bool, gradingmethod: string, gradingmethodname: string
     * }
     */
    public static function parse_grading_data(array $gradedata, \context $context): array {
        $gradingdefinition = null;
        $rubricdata = null;
        $hasrubric = false;
        $hasguide = false;
        $rubriccriteria = [];
        $guidecriteria = [];
        $rubrictotal = 0;
        $guidetotal = 0;
        $guidemaxtotal = 0;
        $gradingmethod = 'simple';

        if (!empty($gradedata['gradingdefinition'])) {
            $gradingdefinition = json_decode($gradedata['gradingdefinition'], true);
        }
        if (!empty($gradedata['rubricdata'])) {
            $rubricdata = json_decode($gradedata['rubricdata'], true);
        }

        $formatopts = ['context' => $context];

        if ($gradingdefinition && !empty($gradingdefinition['criteria'])) {
            $gradingmethod = $gradingdefinition['method'] ?? 'simple';

            if (grading_method_helper::is_rubric($gradingmethod)) {
                $hasrubric = true;
                $isranged = grading_method_helper::is_ranged_rubric($gradingmethod);
                $fillmap = [];
                if ($rubricdata && !empty($rubricdata['criteria'])) {
                    foreach ($rubricdata['criteria'] as $critid => $critdata) {
                        $fillmap[(int) $critid] = [
                            'levelid' => !empty($critdata['levelid']) ? (int) $critdata['levelid'] : 0,
                            'remark' => $critdata['remark'] ?? '',
                            // Ranged criteria award a score inside the chosen
                            // level's band rather than the level's own score.
                            'grade' => isset($critdata['grade']) && $critdata['grade'] !== ''
                                ? (float) $critdata['grade']
                                : null,
                        ];
                    }
                }
                foreach ($gradingdefinition['criteria'] as $criterion) {
                    $levels = [];
                    $selectedscore = null;
                    $fill = $fillmap[$criterion['id']] ?? ['levelid' => 0, 'remark' => '', 'grade' => null];
                    $criterionranged = $isranged && !empty($criterion['isranged']);
                    foreach ($criterion['levels'] as $level) {
                        $isselected = $fill['levelid'] && $fill['levelid'] === $level['id'];
                        $levels[] = [
                            // Ranged criteria are marked against a band, so show
                            // the band rather than a single point value.
                            'score' => $criterionranged && isset($level['rangelabel'])
                                ? $level['rangelabel']
                                : $level['score'],
                            'definition' => format_text($level['definition'] ?? '', FORMAT_HTML, $formatopts),
                            'selected' => $isselected,
                        ];
                        if ($isselected) {
                            $selectedscore = $level['score'];
                        }
                    }
                    // For a ranged criterion the awarded score is what the marker
                    // entered, not the score attached to the level.
                    if ($criterionranged && $fill['grade'] !== null) {
                        $selectedscore = $fill['grade'];
                    }
                    $rubriccriteria[] = [
                        'description' => format_text($criterion['description'] ?? '', FORMAT_HTML, $formatopts),
                        'levels' => $levels,
                        'selectedscore' => $selectedscore,
                        'hasselection' => $selectedscore !== null,
                        'remark' => format_text($fill['remark'] ?? '', FORMAT_HTML, $formatopts),
                        'hasremark' => !empty($fill['remark']),
                    ];
                    if ($selectedscore !== null) {
                        $rubrictotal += $selectedscore;
                    }
                }
            } else if ($gradingmethod === 'guide') {
                $hasguide = true;
                $fillmap = [];
                if ($rubricdata && !empty($rubricdata['criteria'])) {
                    foreach ($rubricdata['criteria'] as $critid => $critdata) {
                        $fillmap[(int) $critid] = [
                            'score' => $critdata['score'] ?? '',
                            'remark' => $critdata['remark'] ?? '',
                        ];
                    }
                }
                foreach ($gradingdefinition['criteria'] as $criterion) {
                    $fill = $fillmap[$criterion['id']] ?? ['score' => '', 'remark' => ''];
                    $score = $fill['score'] !== '' ? (float) $fill['score'] : null;
                    $guidecriteria[] = [
                        'shortname' => $criterion['shortname'],
                        'description' => format_text($criterion['description'] ?? '', FORMAT_HTML, $formatopts),
                        'maxscore' => $criterion['maxscore'],
                        'score' => $score,
                        'hasscore' => $score !== null,
                        'remark' => format_text($fill['remark'] ?? '', FORMAT_HTML, $formatopts),
                        'hasremark' => !empty($fill['remark']),
                    ];
                    if ($score !== null) {
                        $guidetotal += $score;
                    }
                    $guidemaxtotal += (float) $criterion['maxscore'];
                }
            }
        }

        $hasadvancedgrading = $hasrubric || $hasguide;
        $gradingmethodname = $hasrubric
            ? get_string('rubric', 'local_unifiedgrader')
            : ($hasguide ? get_string('markingguide', 'local_unifiedgrader') : '');

        return [
            'hasrubric' => $hasrubric,
            'rubriccriteria' => $rubriccriteria,
            'rubrictotal' => $rubrictotal,
            'hasguide' => $hasguide,
            'guidecriteria' => $guidecriteria,
            'guidetotal' => round($guidetotal, 2),
            'guidemaxtotal' => round($guidemaxtotal, 2),
            'hasadvancedgrading' => $hasadvancedgrading,
            'gradingmethod' => $gradingmethod,
            'gradingmethodname' => $gradingmethodname,
        ];
    }

    /**
     * Build the student-facing translated annotation-comment list.
     *
     * Collects grader annotation comment texts (UG Fabric.js annotations and
     * assignfeedback_editpdf comments), looks up each in the local_nida store in
     * the viewer's language, and returns a page-keyed list of pre-rendered display
     * HTML. Store lookups are batched into a single query (no per-comment query).
     *
     * Returns an empty structure (hasannotations=false) when local_nida is absent,
     * the viewer reads English, or there are no annotation comments.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param \context $context Module context (for format_text media/pluginfile rewrites).
     * @param int|null $gradeid The assign grade row ID (for editpdf comments), or null.
     * @param string $lang The viewer's language code.
     * @return array {hasannotations: bool, pages: array}
     */
    public static function build_annotation_translations(
        int $cmid,
        int $userid,
        \context $context,
        ?int $gradeid,
        string $lang,
    ): array {
        global $DB;

        $empty = ['hasannotations' => false, 'pages' => []];

        // English viewers see the original; nothing to translate.
        if ($lang === '' || strpos($lang, 'en') === 0) {
            return $empty;
        }
        // Degrade gracefully when local_nida is not installed.
        if (!class_exists('\local_nida\local\store') || !class_exists('\local_nida\local\hasher')) {
            return $empty;
        }

        // Normalise all annotation comment sources into ['pagenum', 'text'] rows.
        $rows = [];

        // UG Fabric.js annotations — one DB row per page, many comments per row.
        $annotrows = $DB->get_records(
            'local_unifiedgrader_annot',
            ['cmid' => $cmid, 'userid' => $userid],
            'pagenum ASC',
        );
        foreach ($annotrows as $annot) {
            $texts = annotation_comment_list_builder::extract_page_texts((string) $annot->annotationdata);
            foreach ($texts as $text) {
                $rows[] = ['pagenum' => (int) $annot->pagenum, 'text' => $text];
            }
        }

        // Editpdf comments (assignfeedback_editpdf) for this grade (plain rawtext).
        if ($gradeid && $DB->get_manager()->table_exists('assignfeedback_editpdf_cmnt')) {
            $cmnts = $DB->get_records(
                'assignfeedback_editpdf_cmnt',
                ['gradeid' => $gradeid],
                'pageno ASC',
            );
            foreach ($cmnts as $cmnt) {
                if (trim((string) $cmnt->rawtext) !== '') {
                    // Editpdf pages are 0-based; present them 1-based to students.
                    $rows[] = ['pagenum' => (int) $cmnt->pageno + 1, 'text' => (string) $cmnt->rawtext];
                }
            }
        }

        if (empty($rows)) {
            return $empty;
        }

        // Batch: hash every source text once and resolve all translations in a
        // single store query, then pick the best per hash for this language.
        $hashes = [];
        foreach ($rows as $row) {
            $hashes[] = \local_nida\local\hasher::hash($row['text']);
        }
        $map = self::resolve_translation_map(array_values(array_unique($hashes)), $lang);

        $resolver = function (string $text) use ($map): ?string {
            return $map[\local_nida\local\hasher::hash($text)] ?? null;
        };

        $list = annotation_comment_list_builder::build($rows, $resolver);
        if (empty($list)) {
            return $empty;
        }

        // Pre-render each comment for safe display: translated text (delivered as
        // HTML by local_nida) via format_text(FORMAT_HTML); the plain original
        // fallback via s().
        $pages = [];
        foreach ($list as $page) {
            $comments = [];
            foreach ($page['comments'] as $comment) {
                if ($comment['hastranslation']) {
                    $displayhtml = format_text(
                        $comment['translated'],
                        FORMAT_HTML,
                        ['context' => $context, 'para' => false],
                    );
                } else {
                    $displayhtml = s($comment['original']);
                }
                $comments[] = [
                    'displayhtml' => $displayhtml,
                    'hastranslation' => $comment['hastranslation'],
                ];
            }
            $pages[] = [
                'page' => $page['page'],
                'comments' => $comments,
            ];
        }

        return ['hasannotations' => true, 'pages' => $pages];
    }

    /**
     * Build the student-facing translated segment-comment list (Phase 2).
     *
     * Collects the grader's phrase-anchored comments (local_unifiedgrader_segcomment)
     * for one graded attempt, resolves each comment's translated text from the
     * local_nida store in the viewer's language, and returns a flat list pairing the
     * anchored source phrase (quoted from the student's own original submission text)
     * with the comment. For a non-English viewer with local_nida, each comment is
     * translated into the viewer's language (English original as fallback), batched
     * into a single store query; English viewers (and sites without nida) get the
     * grader's original text — every student must be able to read their feedback.
     * Stamps (tick/cross/…) and empty comments are skipped; each entry carries the
     * pin's number and colour so the list reads like the grader's marginalia.
     *
     * Returns an empty structure (hassegmentcomments=false) when the segcomment
     * table does not exist or there are no text comments for this attempt.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param \context $context Module context (for format_text media/pluginfile rewrites).
     * @param int $attempt The attempt number being viewed.
     * @param string $lang The viewer's language code.
     * @return array {hassegmentcomments: bool, comments: array}
     */
    public static function build_segment_comment_translations(
        int $cmid,
        int $userid,
        \context $context,
        int $attempt,
        string $lang,
    ): array {
        global $DB;

        $empty = ['hassegmentcomments' => false, 'comments' => []];

        // The segcomment table is created by an earlier Phase-2 work package; guard
        // so the feedback page is unchanged when the table (or its data) is absent.
        if (!$DB->get_manager()->table_exists('local_unifiedgrader_segcomment')) {
            return $empty;
        }

        // Document order (page, then offset) so the numbering matches the pins the
        // grader placed top-to-bottom.
        $rows = $DB->get_records(
            'local_unifiedgrader_segcomment',
            ['cmid' => $cmid, 'userid' => $userid, 'attemptnumber' => $attempt],
            'page ASC, startoffset ASC, timecreated ASC',
        );
        if (empty($rows)) {
            return $empty;
        }

        // Translate only for a non-English viewer when local_nida is available.
        // English viewers (and sites without nida) get the grader's original text —
        // the point is that every student can read the feedback, translated or not.
        $cantranslate = ($lang !== '' && strpos($lang, 'en') !== 0)
            && class_exists('\local_nida\local\store')
            && class_exists('\local_nida\local\hasher');
        $map = [];
        if ($cantranslate) {
            $hashes = [];
            foreach ($rows as $row) {
                $hashes[] = \local_nida\local\hasher::hash((string) $row->commenttext);
            }
            $map = self::resolve_translation_map(array_values(array_unique($hashes)), $lang);
        }

        $comments = [];
        $number = 0;
        foreach ($rows as $row) {
            // Text comments only — stamps (tick/cross/…) carry no feedback text and
            // are positional, so they belong on the PDF, not this list.
            if (($row->marktype ?? 'comment') !== 'comment') {
                continue;
            }
            $commenttext = (string) $row->commenttext;
            if (trim($commenttext) === '') {
                continue;
            }
            $number++;

            $translated = $cantranslate
                ? ($map[\local_nida\local\hasher::hash($commenttext)] ?? null)
                : null;
            $hastranslation = $translated !== null;
            if ($hastranslation) {
                // Translated comment text is delivered as HTML by local_nida.
                $displayhtml = format_text(
                    $translated,
                    FORMAT_HTML,
                    ['context' => $context, 'para' => false],
                );
            } else {
                // Original: render the grader's comment in its own format.
                $displayhtml = format_text(
                    $commenttext,
                    (int) $row->commentformat,
                    ['context' => $context, 'para' => false],
                );
            }

            // A 1-character anchor is a point/caret comment — quoting a single
            // letter is noise, so suppress the quote; phrase comments keep it.
            $anchor = trim((string) $row->anchortext);
            $showanchor = \core_text::strlen($anchor) >= 2;
            // Mirror the pin: the stored colour + an auto-contrast number ink.
            $color = segment_comment_manager::normalise_color($row->color ?? null) ?? '#0f6cbf';

            $comments[] = [
                'number' => $number,
                'color' => $color,
                'inkcolor' => self::marker_ink($color),
                // Anchor phrase is plain source text — escape it for safe display.
                'anchortext' => s($anchor),
                'showanchor' => $showanchor,
                'displayhtml' => $displayhtml,
                'hastranslation' => $hastranslation,
            ];
        }

        if (empty($comments)) {
            return $empty;
        }
        return ['hassegmentcomments' => true, 'comments' => $comments];
    }

    /**
     * The marker's in-document marks for the feedback PDF.
     *
     * Two views of the same rows. 'marks' is every mark with where it belongs,
     * for drawing on the pages of the submission, a written comment carrying its
     * text and author for the card beside the page. 'comments' is the written
     * comments only, numbered as their pins are, for the summary to list any
     * that could not be put on a page.
     *
     * The numbering and the wording come from build_segment_comment_translations(),
     * so a pin carries the same number on the page, in the list and on the web,
     * and a student reading in another language gets the translated comment.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param \context $context Module context.
     * @param int $attempt The attempt number.
     * @param string $lang The viewer's language code.
     * @return array {comments: array[], marks: array[]}
     */
    public static function document_marks(int $cmid, int $userid, \context $context, int $attempt, string $lang): array {
        global $DB;

        $result = ['comments' => [], 'marks' => []];
        if (!$DB->get_manager()->table_exists('local_unifiedgrader_segcomment')) {
            return $result;
        }
        $rows = $DB->get_records(
            'local_unifiedgrader_segcomment',
            ['cmid' => $cmid, 'userid' => $userid, 'attemptnumber' => $attempt],
            'page ASC, startoffset ASC, timecreated ASC',
        );
        if (!$rows) {
            return $result;
        }

        $written = self::build_segment_comment_translations($cmid, $userid, $context, $attempt, $lang)['comments'];

        // Who wrote each comment, as the web view shows it, unless the
        // assignment keeps its markers' names from students.
        $authors = [];
        $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
        $hidegrader = $cm && $cm->modname === 'assign'
            && $DB->get_field('assign', 'hidegrader', ['id' => $cm->instance]);
        if (!$hidegrader) {
            $authorids = array_unique(array_map(fn(\stdClass $row): int => (int) $row->authorid, $rows));
            $namefields = implode(', ', \core_user\fields::get_name_fields());
            foreach ($DB->get_records_list('user', 'id', $authorids, '', 'id, ' . $namefields) as $author) {
                $authors[(int) $author->id] = fullname($author);
            }
        }

        $number = 0;
        foreach ($rows as $row) {
            $marktype = segment_comment_manager::normalise_marktype((string) ($row->marktype ?? 'comment'));
            $color = segment_comment_manager::normalise_color($row->color ?? null) ?? '#0f6cbf';
            $pin = null;
            $text = '';
            // The same test the list builder applies, so the two stay in step.
            if ($marktype === 'comment' && trim((string) $row->commenttext) !== '') {
                $entry = $written[$number] ?? null;
                $number++;
                $pin = $number;
                $anchor = trim((string) $row->anchortext);
                $html = $entry['displayhtml'] ?? format_text(
                    (string) $row->commenttext,
                    (int) $row->commentformat,
                    ['context' => $context, 'para' => false],
                );
                // The card on the page is plain text: what the comment says, without its markup.
                $text = trim(html_to_text(\local_unifiedgrader\pdf\pdf_text::flatten($html), 0, false));
                $result['comments'][] = [
                    'number' => $number,
                    'color' => $color,
                    'page' => (int) $row->page,
                    'anchortext' => \core_text::strlen($anchor) >= 2 ? $anchor : '',
                    'html' => $html,
                ];
            } else if ($marktype === 'comment') {
                // A comment pin with nothing written has nothing to point to.
                continue;
            }
            $result['marks'][] = [
                'fileid' => (int) $row->fileid,
                'page' => (int) $row->page,
                'startoffset' => (int) $row->startoffset,
                'anchortext' => (string) $row->anchortext,
                'marktype' => $marktype,
                'color' => $color,
                'number' => $pin,
                'text' => $text,
                'author' => $authors[(int) $row->authorid] ?? '',
            ];
        }

        return $result;
    }

    /**
     * The legible number ink (#fff or a dark ink) for a marker background colour,
     * mirroring the client-side _textOn: white unless it fails WCAG AA (4.5:1) on
     * the given background, as on a bright yellow pin.
     *
     * @param string $hex A #RRGGBB colour.
     * @return string '#fff' or '#212529'.
     */
    private static function marker_ink(string $hex): string {
        $m = [];
        if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            return '#fff';
        }
        $lin = function (string $c): float {
            $v = hexdec($c) / 255;
            return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        };
        $l = 0.2126 * $lin($m[1]) + 0.7152 * $lin($m[2]) + 0.0722 * $lin($m[3]);
        return (1.05 / ($l + 0.05)) >= 4.5 ? '#fff' : '#212529';
    }

    /**
     * Resolve a set of source hashes to their best published translation text.
     *
     * Uses the store's batched lookup (single query) then filters to the target
     * language and picks the highest-ranked status per hash — overridden, then
     * reviewed, then machine, most-recently-modified winning ties. Mirrors the
     * ordering of store::find_published_translation() without a query per hash.
     *
     * @param string[] $hashes Distinct source hashes.
     * @param string $lang Target language code.
     * @return array<string, string> Map of sourcehash => translated text.
     */
    private static function resolve_translation_map(array $hashes, string $lang): array {
        if (empty($hashes)) {
            return [];
        }
        // Defensive: this helper is only reached today through class_exists-guarded
        // callers, but keep the graceful-fail guarantee local so a future caller
        // cannot fatal the grader when Nida is uninstalled.
        if (!class_exists('\local_nida\local\store')) {
            return [];
        }
        $rows = (new \local_nida\local\store())->published_for_hashes($hashes);

        $rank = [
            \local_nida\local\store::STATUS_OVERRIDDEN => 0,
            \local_nida\local\store::STATUS_REVIEWED => 1,
        ];
        $best = [];
        $bestrank = [];
        foreach ($rows as $row) {
            if ((string) $row->targetlang !== $lang) {
                continue;
            }
            $hash = (string) $row->sourcehash;
            $currentrank = $rank[$row->status] ?? 2;
            if (
                !isset($best[$hash])
                || $currentrank < $bestrank[$hash]['rank']
                || ($currentrank === $bestrank[$hash]['rank']
                    && (int) $row->timemodified > $bestrank[$hash]['time'])
            ) {
                $best[$hash] = (string) $row->translatedtext;
                $bestrank[$hash] = ['rank' => $currentrank, 'time' => (int) $row->timemodified];
            }
        }
        return $best;
    }
}
