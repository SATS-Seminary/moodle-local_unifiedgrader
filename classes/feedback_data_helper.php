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

defined('MOODLE_INTERNAL') || die();

/**
 * Helper for parsing and formatting feedback data.
 *
 * Shared between the student feedback view (view_feedback.php) and the
 * feedback PDF download endpoint (download_feedback.php).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (https://www.sats.ac.za)
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
            $gradedisplay = $gradevalue . ' / ' . $maxgrade . ' (' . $percentage . '%)';
        }

        return [
            'gradedisplay' => $gradedisplay,
            'gradevalue' => $gradevalue,
            'maxgrade' => $maxgrade,
            'percentage' => $percentage,
        ];
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
            $label = $p['category'] === 'wordcount'
                ? get_string('penalty_wordcount', 'local_unifiedgrader')
                : ($p['label'] ?: get_string('penalty_other', 'local_unifiedgrader'));
            $penaltybadges[] = [
                'text' => '-' . $p['percentage'] . '% ' . $label,
            ];
        }

        return [
            'haspenalties' => !empty($penaltybadges),
            'penalties' => $penaltybadges,
        ];
    }

    /**
     * Parse rubric/marking guide data for display.
     *
     * @param array $gradedata From adapter get_grade_data()
     * @return array {
     *     hasrubric: bool, rubriccriteria: array, rubrictotal: float,
     *     hasguide: bool, guidecriteria: array, guidetotal: float, guidemaxtotal: float,
     *     hasadvancedgrading: bool, gradingmethod: string, gradingmethodname: string
     * }
     */
    public static function parse_grading_data(array $gradedata): array {
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
                            'definition' => $level['definition'],
                            'selected' => $isselected,
                        ];
                        if ($isselected) {
                            $selectedscore = $level['score'];
                        }
                    }
                    $rubriccriteria[] = [
                        'description' => $criterion['description'],
                        'levels' => $levels,
                        'selectedscore' => $selectedscore,
                        'hasselection' => $selectedscore !== null,
                        'remark' => $fill['remark'],
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
                        'description' => $criterion['description'] ?? '',
                        'maxscore' => $criterion['maxscore'],
                        'score' => $score,
                        'hasscore' => $score !== null,
                        'remark' => $fill['remark'],
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
}
