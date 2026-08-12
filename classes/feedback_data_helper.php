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
        $penalties = penalty_manager::get_penalties($cmid, $userid);
        $penaltybadges = [];

        foreach ($penalties as $p) {
            if ($p['category'] === 'late') {
                $label = get_string('penalty_late', 'local_unifiedgrader');
            } else if ($p['category'] === 'wordcount') {
                $label = get_string('penalty_wordcount', 'local_unifiedgrader');
            } else {
                $label = $p['label'] ?: get_string('penalty_other', 'local_unifiedgrader');
            }
            $penaltybadges[] = [
                'text' => '-' . $p['percentage'] . '% ' . $label,
            ];
        }

        // For quizzes, detect late penalty applied by quizaccess_duedate plugin.
        // Calculate directly from settings + attempt time (same logic as the observer).
        // Cannot rely on gradebook feedback text — teachers may overwrite it.
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        if ($cm->modname === 'quiz' && class_exists('\quizaccess_duedate\override_manager')) {
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
                            $penaltybadges[] = [
                                'text' => '-' . $pct . '% ' . get_string('penalty_late', 'local_unifiedgrader'),
                            ];
                        }
                    }
                }
            }
        }

        return [
            'haspenalties' => !empty($penaltybadges),
            'penalties' => $penaltybadges,
        ];
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

            if ($gradingmethod === 'rubric') {
                $hasrubric = true;
                $fillmap = [];
                if ($rubricdata && !empty($rubricdata['criteria'])) {
                    foreach ($rubricdata['criteria'] as $critid => $critdata) {
                        $fillmap[(int) $critid] = [
                            'levelid' => !empty($critdata['levelid']) ? (int) $critdata['levelid'] : 0,
                            'remark' => $critdata['remark'] ?? '',
                        ];
                    }
                }
                foreach ($gradingdefinition['criteria'] as $criterion) {
                    $levels = [];
                    $selectedscore = null;
                    $fill = $fillmap[$criterion['id']] ?? ['levelid' => 0, 'remark' => ''];
                    foreach ($criterion['levels'] as $level) {
                        $isselected = $fill['levelid'] && $fill['levelid'] === $level['id'];
                        $levels[] = [
                            'score' => $level['score'],
                            'definition' => format_text($level['definition'] ?? '', FORMAT_HTML, $formatopts),
                            'selected' => $isselected,
                        ];
                        if ($isselected) {
                            $selectedscore = $level['score'];
                        }
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
