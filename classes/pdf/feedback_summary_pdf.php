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

namespace local_unifiedgrader\pdf;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Generates a feedback summary cover page PDF.
 *
 * Produces a professionally styled summary page containing the grade,
 * overall feedback, rubric/marking guide with teacher comments, and penalties.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za) (https://www.sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_summary_pdf extends \pdf {
    /** @var int Color for grades >= 75%. */
    private const COLOR_GREEN = 0x28A745;

    /** @var int Color for grades 50-74%. */
    private const COLOR_AMBER = 0xFD7E14;

    /** @var int Color for grades < 50%. */
    private const COLOR_RED = 0xDC3545;

    /** @var int Primary blue accent color. */
    private const COLOR_PRIMARY = 0x0D6EFD;

    /** @var int Dark text color. */
    private const COLOR_DARK = 0x212529;

    /** @var int Muted text color. */
    private const COLOR_MUTED = 0x6C757D;

    /** @var int White. */
    private const COLOR_WHITE = 0xFFFFFF;

    /** @var int Tint of the primary blue, for captions on a primary-filled tile. */
    private const COLOR_PRIMARY_TINT = 0xCFE2FF;

    /** @var int Hairline border colour. */
    private const COLOR_BORDER = 0xDEE2E6;

    /** @var float Corner radius for tiles and chips, in mm. */
    private const RADIUS = 1.6;

    /** @var float Width of the score column in the top band, in mm. */
    private const SCORE_COLUMN_W = 54;

    /** @var int Page left/right margin in mm. */
    private const MARGIN_H = 15;

    /** @var int Page top margin in mm. */
    private const MARGIN_TOP = 10;

    /**
     * Generate the feedback summary PDF.
     *
     * @param array $data Feedback data with keys:
     *   - activityname (string)
     *   - coursename (string)
     *   - studentname (string)
     *   - gradevalue (float|null)
     *   - maxgrade (float)
     *   - percentage (int|null)
     *   - feedback (string) HTML feedback text
     *   - gradingmethod (string) 'simple', 'rubric', or 'guide'
     *   - rubriccriteria (array)
     *   - guidecriteria (array)
     *   - penalties (array) Each with 'text' key
     *   - dategraded (string)
     *   - plagiarismlinks (array) Optional. Each with 'label' and 'html' keys.
     *   - additionalcontent (string) Optional. HTML content for additional pages
     *     (e.g. rendered quiz attempt). Bootstrap classes are converted to
     *     inline styles for TCPDF compatibility.
     *   - additionalcontenttitle (string) Optional. Heading for additional pages.
     *   - analytics (array) Optional. Engagement figures for attendance-graded
     *     activities: {hasengagement, sessioncount, totals, sessions[]}.
     *   - annotations (array) Optional. Timestamped recording comments, each
     *     with 'timestamp', 'text' and 'sessionlabel'.
     *   - logopath (string|null) Optional. Absolute path to a readable image
     *     file for the site logo, as resolved by
     *     feedback_data_helper::resolve_site_logo_path().
     * @return string PDF content as binary string
     */
    public function generate(array $data): string {
        // Footer through TCPDF's own callback rather than drawn once: the
        // annotation pages are added after the summary, and a footer painted a
        // single time left them with no date, no attribution and no page
        // numbering at all.
        $this->footerdata = $data;
        $this->setPrintHeader(false);
        $this->setPrintFooter(true);
        $this->SetFooterMargin(12);
        $this->SetMargins(self::MARGIN_H, self::MARGIN_TOP, self::MARGIN_H);
        $this->SetAutoPageBreak(true, 18);
        $this->setPageUnit('mm');

        $this->AddPage('P', 'A4');
        $this->SetFont('helvetica', '', 10);

        $this->render_header_band($data);
        // Score and feedback share the top band: the mark on the left, what the
        // teacher said about it on the right. A student opening this wants both
        // at once, and stacking them full width pushed the feedback below the
        // fold on every summary.
        $this->render_grade_and_feedback($data);
        $this->render_penalties($data);
        // Engagement below the band. On an attendance-graded activity these
        // figures are the evidence the mark rests on, so they stay above the
        // criterion-by-criterion breakdown.
        $this->render_analytics_widgets($data);
        $this->render_plagiarism_section($data);
        $this->render_grading_section($data);

        // Timestamped recording comments (BBB). A recording cannot be played
        // from a PDF, so the annotations are listed as text against their
        // position in the video instead of embedding the player.
        if (!empty($data['annotations'])) {
            $this->render_annotation_comments($data);
        }

        // Additional content pages (e.g. quiz attempt).
        if (!empty($data['additionalcontent'])) {
            $this->render_additional_content($data);
        }

        return $this->Output('', 'S');
    }

    /**
     * Render the coloured header band with activity and course names.
     *
     * @param array $data
     */
    private function render_header_band(array $data): void {
        $pagewidth = $this->getPageWidth();
        $bandheight = 24;
        $y = $this->GetY();

        // Blue header band, full bleed.
        $this->set_fill_from_hex(self::COLOR_PRIMARY);
        $this->Rect(0, $y, $pagewidth, $bandheight, 'F');

        // The logo sits on a white chip rather than straight on the blue. A site
        // logo is drawn for a light background far more often than a dark one,
        // and a dark mark on a dark band is unreadable; the chip makes any logo
        // legible without us having to know what is in it.
        $textwidth = $pagewidth - (self::MARGIN_H * 2);
        $logopath = $data['logopath'] ?? null;
        if (!empty($logopath) && is_readable($logopath)) {
            $chipheight = 16;
            $padding = 2;
            // Size the chip to the logo rather than fixing it: a square roundel
            // in a letterbox chip reads as a mistake, and a wide wordmark in a
            // square one has nowhere to go. The image's own ratio sets the
            // width, clamped so neither extreme takes over the band.
            $imageheight = $chipheight - ($padding * 2);
            $aspect = 1.0;
            $dimensions = @getimagesize($logopath);
            if ($dimensions && (int) $dimensions[1] > 0) {
                $aspect = (int) $dimensions[0] / (int) $dimensions[1];
            }
            $chipwidth = min(46, max(16, ($imageheight * $aspect) + ($padding * 2)));
            $chipx = $pagewidth - self::MARGIN_H - $chipwidth;
            $chipy = $y + (($bandheight - $chipheight) / 2);

            $this->set_fill_from_hex(self::COLOR_WHITE);
            $this->RoundedRect($chipx, $chipy, $chipwidth, $chipheight, self::RADIUS, '1111', 'F');

            // The 'CM' alignment fits the image inside the box, centred and
            // keeping its ratio, so a tall roundel and a wide wordmark both sit
            // correctly.
            $this->Image(
                $logopath,
                $chipx + $padding,
                $chipy + $padding,
                $chipwidth - ($padding * 2),
                $chipheight - ($padding * 2),
                '',
                '',
                '',
                false,
                300,
                '',
                false,
                false,
                0,
                'CM'
            );

            // Keep the heading clear of the chip.
            $textwidth = $chipx - self::MARGIN_H - 4;
        }

        // Activity name, then course and student beneath it. Stacked on the left
        // rather than split across the band: a course name carrying a code, a
        // title and a term runs long, and it used to collide with the heading.
        $this->set_text_from_hex(self::COLOR_WHITE);
        $this->SetFont('helvetica', 'B', 14);
        $this->SetXY(self::MARGIN_H, $y + 4);
        $this->Cell($textwidth, 6, $data['activityname'], 0, 0, 'L');

        $this->set_text_from_hex(self::COLOR_PRIMARY_TINT);
        $this->SetFont('helvetica', '', 8.5);
        $this->SetXY(self::MARGIN_H, $y + 11);
        $this->Cell($textwidth, 4, $data['coursename'], 0, 0, 'L');

        $this->SetXY(self::MARGIN_H, $y + 15.5);
        $this->SetFont('helvetica', 'B', 8.5);
        $this->Cell($textwidth, 4, $data['studentname'], 0, 0, 'L');

        $this->SetY($y + $bandheight + 6);
    }

    /**
     * Render the score and the overall feedback side by side.
     *
     * Shared by every adapter, so an assignment, a forum, a quiz and a BBB
     * session all open the same way; only what follows the band differs. When a
     * summary carries no written feedback the score keeps its column rather than
     * recentring, so two students' PDFs still line up page for page.
     *
     * @param array $data
     */
    private function render_grade_and_feedback(array $data): void {
        $pagewidth = $this->getPageWidth();
        $contentwidth = $pagewidth - (self::MARGIN_H * 2);
        $top = $this->GetY();

        // Left: the score.
        $this->render_score_donut($data, self::MARGIN_H, $top, self::SCORE_COLUMN_W);
        $leftbottom = $this->GetY();

        // Right: what the teacher wrote about it.
        $gutter = 8;
        $rightx = self::MARGIN_H + self::SCORE_COLUMN_W + $gutter;
        $rightwidth = $contentwidth - self::SCORE_COLUMN_W - $gutter;

        $this->SetY($top);
        $rightbottom = $top;
        $feedback = trim($data['feedback'] ?? '');
        if ($feedback !== '') {
            $this->render_section_heading(
                get_string('feedback_summary_overall_feedback', 'local_unifiedgrader'),
                0xF4AD, // Icon: comment-dots.
                $rightx,
                $rightwidth
            );

            // Flatten filter_nida markup to the translated content only (the
            // badge and hidden original are web chrome), then sanitise for TCPDF.
            $html = '<div style="font-size: 9pt; color: #212529; line-height: 1.5;">'
                . $this->sanitise_feedback_html(pdf_text::flatten($feedback))
                . '</div>';

            $this->writeHTMLCell(
                $rightwidth,
                0,
                $rightx,
                $this->GetY(),
                $html,
                0,
                1,
                false,
                true,
                'L'
            );
            $rightbottom = $this->GetY();
        }

        $this->SetY(max($leftbottom, $rightbottom) + 4);
    }

    /**
     * Draw the grade as a donut: a grey track with the achieved share drawn over
     * it, and the percentage in the hole.
     *
     * A ring rather than a filled disc because the hole is what makes the
     * proportion readable — a solid circle is the same shape at 40% as at 90%
     * and says nothing until you read the number inside it.
     *
     * @param array $data
     * @param float $x Left edge of the score column.
     * @param float $y Top of the score column.
     * @param float $width Column width; the donut is centred in it.
     */
    private function render_score_donut(array $data, float $x, float $y, float $width): void {
        $hasgrade = $data['gradevalue'] !== null && $data['percentage'] !== null;
        $percentage = $hasgrade ? max(0, min(100, (float) $data['percentage'])) : 0.0;

        if (!$hasgrade) {
            $arccolor = self::COLOR_MUTED;
        } else if ($percentage >= 75) {
            $arccolor = self::COLOR_GREEN;
        } else if ($percentage >= 50) {
            $arccolor = self::COLOR_AMBER;
        } else {
            $arccolor = self::COLOR_RED;
        }

        $radius = 17;
        $thickness = 6;
        $centrex = $x + ($width / 2);
        $centrey = $y + $radius + 2;

        // The full ring is drawn as two filled discs — one the ring's outer
        // diameter, one punched back out of its middle — rather than as a
        // stroked circle. A thick stroke closes on itself at three o'clock and
        // leaves a visible notch and a stray radial hairline there; a filled
        // annulus has no seam to show.
        $this->set_fill_from_hex($percentage >= 100 ? $arccolor : 0xE9ECEF);
        $this->Circle($centrex, $centrey, $radius + ($thickness / 2), 0, 360, 'F');
        $this->set_fill_from_hex(self::COLOR_WHITE);
        $this->Circle($centrex, $centrey, $radius - ($thickness / 2), 0, 360, 'F');

        // Achieved share, drawn from twelve o'clock clockwise. TCPDF sweeps
        // anticlockwise, so the start angle is set back by the sweep instead. A
        // full ring is already filled above, so only a partial one is stroked —
        // which is exactly the case a stroke handles without a seam.
        if ($percentage > 0 && $percentage < 100) {
            $sweep = 360 * ($percentage / 100);
            $this->Circle($centrex, $centrey, $radius, 90 - $sweep, 90, 'D', [
                'width' => $thickness,
                'cap' => 'butt',
                'color' => $this->hex_to_rgb($arccolor),
            ]);
        }

        // Percentage in the hole.
        $this->set_text_from_hex($hasgrade ? self::COLOR_DARK : self::COLOR_MUTED);
        if ($hasgrade) {
            $this->SetFont('helvetica', 'B', 18);
            $label = round($percentage) . '%';
        } else {
            $this->SetFont('helvetica', 'B', 9);
            $label = get_string('feedback_summary_no_grade', 'local_unifiedgrader');
        }
        $this->SetXY($x, $centrey - 4.5);
        $this->Cell($width, 9, $label, 0, 0, 'C');

        // Fraction beneath the ring.
        $bottom = $centrey + $radius + ($thickness / 2) + 3;
        if ($hasgrade) {
            $this->set_text_from_hex(self::COLOR_DARK);
            $this->SetFont('helvetica', 'B', 12);
            $this->SetXY($x, $bottom);
            $this->Cell(
                $width,
                6,
                round($data['gradevalue'], 2) . ' / ' . round($data['maxgrade'], 2),
                0,
                0,
                'C'
            );
            $bottom += 6;
        }

        $this->SetY($bottom);
    }

    /**
     * Render penalty badges if any.
     *
     * @param array $data
     */
    private function render_penalties(array $data): void {
        if (empty($data['penalties'])) {
            return;
        }

        $pagewidth = $this->getPageWidth();
        $contentwidth = $pagewidth - (self::MARGIN_H * 2);
        $y = $this->GetY();

        // Build HTML for penalty badges.
        $html = '<div style="text-align: center;">';
        foreach ($data['penalties'] as $penalty) {
            $html .= '<span style="background-color: #FFF3CD; color: #856404; '
                . 'font-size: 8pt; border-radius: 3px;">'
                . '&nbsp;&nbsp;' . htmlspecialchars(pdf_text::plain($penalty['text'])) . '&nbsp;&nbsp;'
                . '</span>&nbsp;&nbsp;';
        }
        $html .= '</div>';

        $this->SetXY(self::MARGIN_H, $y);
        $this->writeHTMLCell($contentwidth, 0, self::MARGIN_H, $y, $html, 0, 1, false, true, 'C');
        $this->SetY($this->GetY() + 3);
    }


    /**
     * Render the rubric or marking guide section.
     *
     * @param array $data
     */
    private function render_grading_section(array $data): void {
        if (
            \local_unifiedgrader\grading_method_helper::is_rubric($data['gradingmethod'])
                && !empty($data['rubriccriteria'])
        ) {
            $this->render_rubric($data);
        } else if ($data['gradingmethod'] === 'guide' && !empty($data['guidecriteria'])) {
            $this->render_marking_guide($data);
        }
    }

    /**
     * Render rubric criteria table.
     *
     * @param array $data
     */
    private function render_rubric(array $data): void {
        $pagewidth = $this->getPageWidth();
        $contentwidth = $pagewidth - (self::MARGIN_H * 2) - 6;

        $this->render_section_heading(
            get_string('rubric', 'local_unifiedgrader'),
            0xF00A, // Icon: table-cells.
            null,
            null,
            20
        );

        $html = '<table cellpadding="4" cellspacing="0" style="font-size: 8pt;">';

        // Header row.
        $html .= '<tr>'
            . '<th style="background-color: #0D6EFD; color: #FFFFFF; font-weight: bold; width: 30%;">'
            . get_string('criterion', 'local_unifiedgrader') . '</th>'
            . '<th style="background-color: #0D6EFD; color: #FFFFFF; font-weight: bold; width: 55%;">'
            . get_string('levels', 'local_unifiedgrader') . '</th>'
            . '<th style="background-color: #0D6EFD; color: #FFFFFF; font-weight: bold; width: 15%; text-align: center;">'
            . get_string('score', 'local_unifiedgrader') . '</th>'
            . '</tr>';

        $total = 0;
        $rowindex = 0;

        foreach ($data['rubriccriteria'] as $criterion) {
            $rowbg = ($rowindex % 2 === 0) ? '#FFFFFF' : '#F8F9FA';

            // Build levels display - highlight selected level.
            $levelshtml = '';
            foreach ($criterion['levels'] as $level) {
                if (!empty($level['selected'])) {
                    $levelshtml .= '<span style="background-color: #CFE2FF; color: #084298; '
                        . 'font-weight: bold;">'
                        . htmlspecialchars(pdf_text::plain($level['definition']))
                        . ' (' . $level['score'] . ' pts)</span><br/>';
                } else {
                    $levelshtml .= '<span style="color: #6C757D;">'
                        . htmlspecialchars(pdf_text::plain($level['definition']))
                        . ' (' . $level['score'] . ' pts)</span><br/>';
                }
            }

            $scorehtml = '';
            if ($criterion['hasselection']) {
                $scorehtml = '<span style="font-weight: bold; font-size: 10pt;">'
                    . $criterion['selectedscore'] . '</span>';
                $total += (float) $criterion['selectedscore'];
            } else {
                $scorehtml = '<span style="color: #6C757D;">-</span>';
            }

            $html .= '<tr>'
                . '<td style="background-color: ' . $rowbg . '; font-weight: bold; vertical-align: top;">'
                . htmlspecialchars(pdf_text::plain($criterion['description'])) . '</td>'
                . '<td style="background-color: ' . $rowbg . '; vertical-align: top;">'
                . $levelshtml . '</td>'
                . '<td style="background-color: ' . $rowbg . '; text-align: center; vertical-align: top;">'
                . $scorehtml . '</td>'
                . '</tr>';

            // Remark row.
            if (!empty($criterion['hasremark']) && !empty($criterion['remark'])) {
                $html .= '<tr>'
                    . '<td colspan="3" style="background-color: #E8F4FD; color: #055160; '
                    . 'font-style: italic; font-size: 7.5pt; padding-left: 10px;">'
                    . '<span style="font-weight: bold;">Comment:</span> '
                    . htmlspecialchars(pdf_text::plain($criterion['remark']))
                    . '</td></tr>';
            }

            $rowindex++;
        }

        // Total row.
        $totalstr = get_string('feedback_summary_total', 'local_unifiedgrader');
        $html .= '<tr>'
            . '<td colspan="2" style="background-color: #E2E3E5; font-weight: bold; text-align: right;">'
            . $totalstr . '</td>'
            . '<td style="background-color: #E2E3E5; font-weight: bold; text-align: center; font-size: 10pt;">'
            . round($total, 2) . '</td>'
            . '</tr>';

        $html .= '</table>';

        $this->writeHTMLCell(
            $contentwidth,
            0,
            self::MARGIN_H + 6,
            $this->GetY(),
            $html,
            0,
            1,
            false,
            true,
            'L'
        );

        $this->SetY($this->GetY() + 4);
    }

    /**
     * Render marking guide criteria table.
     *
     * @param array $data
     */
    private function render_marking_guide(array $data): void {
        $pagewidth = $this->getPageWidth();
        $contentwidth = $pagewidth - (self::MARGIN_H * 2) - 6;

        $this->render_section_heading(
            get_string('markingguide', 'local_unifiedgrader'),
            0xF0AE, // Icon: list-check.
            null,
            null,
            20
        );

        $html = '<table cellpadding="4" cellspacing="0" style="font-size: 8pt;">';

        // Header row.
        $html .= '<tr>'
            . '<th style="background-color: #0D6EFD; color: #FFFFFF; font-weight: bold; width: 25%;">'
            . get_string('criterion', 'local_unifiedgrader') . '</th>'
            . '<th style="background-color: #0D6EFD; color: #FFFFFF; font-weight: bold; width: 15%; text-align: center;">'
            . get_string('score', 'local_unifiedgrader') . '</th>'
            . '<th style="background-color: #0D6EFD; color: #FFFFFF; font-weight: bold; width: 60%;">'
            . get_string('feedback_summary_remark', 'local_unifiedgrader') . '</th>'
            . '</tr>';

        $total = 0;
        $maxtotal = 0;
        $rowindex = 0;

        foreach ($data['guidecriteria'] as $criterion) {
            $rowbg = ($rowindex % 2 === 0) ? '#FFFFFF' : '#F8F9FA';

            // Score with colour coding.
            $scorehtml = '';
            if ($criterion['hasscore']) {
                $scorepct = $criterion['maxscore'] > 0
                    ? ($criterion['score'] / $criterion['maxscore']) * 100
                    : 0;

                if ($scorepct >= 75) {
                    $scorecolor = '#198754';
                } else if ($scorepct >= 50) {
                    $scorecolor = '#FD7E14';
                } else {
                    $scorecolor = '#DC3545';
                }

                $scorehtml = '<span style="font-weight: bold; color: ' . $scorecolor . '; font-size: 10pt;">'
                    . round($criterion['score'], 2) . '</span>'
                    . ' / ' . round($criterion['maxscore'], 2);
                $total += (float) $criterion['score'];
            } else {
                $scorehtml = '<span style="color: #6C757D;">- / '
                    . round($criterion['maxscore'], 2) . '</span>';
            }
            $maxtotal += (float) $criterion['maxscore'];

            $remarkhtml = '';
            if (!empty($criterion['hasremark']) && !empty($criterion['remark'])) {
                $remarkhtml = htmlspecialchars(pdf_text::plain($criterion['remark']));
            } else {
                $remarkhtml = '<span style="color: #ADB5BD; font-style: italic;">No comment</span>';
            }

            $html .= '<tr>'
                . '<td style="background-color: ' . $rowbg . '; font-weight: bold; vertical-align: top;">'
                . htmlspecialchars(pdf_text::plain($criterion['shortname'])) . '</td>'
                . '<td style="background-color: ' . $rowbg . '; text-align: center; vertical-align: top;">'
                . $scorehtml . '</td>'
                . '<td style="background-color: ' . $rowbg . '; vertical-align: top;">'
                . $remarkhtml . '</td>'
                . '</tr>';

            $rowindex++;
        }

        // Total row.
        $totalstr = get_string('feedback_summary_total', 'local_unifiedgrader');
        $html .= '<tr>'
            . '<td style="background-color: #E2E3E5; font-weight: bold; text-align: right;">'
            . $totalstr . '</td>'
            . '<td style="background-color: #E2E3E5; font-weight: bold; text-align: center; font-size: 10pt;">'
            . round($total, 2) . ' / ' . round($maxtotal, 2) . '</td>'
            . '<td style="background-color: #E2E3E5;"></td>'
            . '</tr>';

        $html .= '</table>';

        $this->writeHTMLCell(
            $contentwidth,
            0,
            self::MARGIN_H + 6,
            $this->GetY(),
            $html,
            0,
            1,
            false,
            true,
            'L'
        );

        $this->SetY($this->GetY() + 4);
    }

    /**
     * Render plagiarism report links on the summary page.
     *
     * @param array $data
     */
    private function render_plagiarism_section(array $data): void {
        if (empty($data['plagiarismlinks'])) {
            return;
        }

        $pagewidth = $this->getPageWidth();
        $contentwidth = $pagewidth - (self::MARGIN_H * 2);

        $this->render_section_heading(
            get_string('plagiarism', 'local_unifiedgrader'),
            0xF3ED // Icon: shield-halved.
        );

        $html = '<table cellpadding="3" cellspacing="0" style="font-size: 8pt;">';
        foreach ($data['plagiarismlinks'] as $link) {
            $label = htmlspecialchars(pdf_text::plain($link['label'] ?? ''));
            // Reduce plagiarism output to text content (links won't work in a
            // PDF), flattening any nida markup so badge captions cannot bleed.
            $text = pdf_text::plain($link['html'] ?? '');
            if (empty($text)) {
                $text = get_string('plagiarism_pending', 'local_unifiedgrader');
            }

            $html .= '<tr>'
                . '<td style="font-weight: bold; width: 35%; vertical-align: top; '
                . 'border-bottom: 1px solid #DEE2E6;">'
                . $label . '</td>'
                . '<td style="width: 65%; vertical-align: top; '
                . 'border-bottom: 1px solid #DEE2E6;">'
                . htmlspecialchars($text) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        $this->writeHTMLCell(
            $contentwidth - 6,
            0,
            self::MARGIN_H + 6,
            $this->GetY(),
            $html,
            0,
            1,
            false,
            true,
            'L'
        );

        $this->SetY($this->GetY() + 4);
    }

    /**
     * Render additional content (e.g. quiz attempt) on new pages after the summary.
     *
     * Converts Bootstrap-class HTML from the adapter's render methods into
     * TCPDF-compatible inline-styled HTML, then renders with auto page breaks.
     *
     * @param array $data
     */
    private function render_additional_content(array $data): void {
        // Re-enable auto page break for multi-page content.
        $this->SetAutoPageBreak(true, 15);
        $this->AddPage('P', 'A4');

        $pagewidth = $this->getPageWidth();
        $contentwidth = $pagewidth - (self::MARGIN_H * 2);

        // Section heading.
        $title = $data['additionalcontenttitle']
            ?? get_string('quiz_your_attempt', 'local_unifiedgrader');
        $this->render_section_heading($title);

        // Flatten filter_nida markup to the translation, then convert Bootstrap
        // HTML to TCPDF-compatible inline styles.
        $html = $this->convert_bootstrap_to_pdf_html(pdf_text::flatten($data['additionalcontent']));

        // Sanitise (strip media, event handlers).
        $html = $this->sanitise_feedback_html($html);

        // Wrap in styled container.
        $html = '<div style="font-size: 8pt; color: #212529; line-height: 1.4;">'
            . $html . '</div>';

        $this->writeHTMLCell(
            $contentwidth,
            0,
            self::MARGIN_H,
            $this->GetY(),
            $html,
            0,
            1,
            false,
            true,
            'L'
        );
    }

    /**
     * Convert Bootstrap-class HTML to TCPDF-compatible inline-styled HTML.
     *
     * The quiz adapter's render_attempt_as_html() uses Bootstrap classes
     * (card, badge, etc.) that TCPDF cannot interpret. This method replaces
     * those class-based patterns with inline CSS that TCPDF supports.
     *
     * @param string $html
     * @return string
     */
    private function convert_bootstrap_to_pdf_html(string $html): string {
        // Strip all class attributes — we'll rely on structural replacements.
        // First, do targeted replacements for known patterns.

        // Card containers → bordered divs.
        $html = preg_replace(
            '/<div\s+class="card[^"]*"/',
            '<div style="border: 1px solid #DEE2E6; margin-bottom: 4mm;"',
            $html,
        );

        // Card headers → shaded header divs.
        $html = preg_replace(
            '/<div\s+class="card-header[^"]*"/',
            '<div style="background-color: #F8F9FA; padding: 2mm 3mm; font-weight: bold; '
            . 'border-bottom: 1px solid #DEE2E6;"',
            $html,
        );

        // Card bodies → padded content.
        $html = preg_replace(
            '/<div\s+class="card-body[^"]*"/',
            '<div style="padding: 2mm 3mm;"',
            $html,
        );

        // Badges → inline styled spans.
        $badgemap = [
            'bg-secondary' => 'background-color: #6C757D; color: #FFFFFF;',
            'bg-success'   => 'background-color: #198754; color: #FFFFFF;',
            'bg-warning'   => 'background-color: #FFC107; color: #000000;',
            'bg-danger'    => 'background-color: #DC3545; color: #FFFFFF;',
            'bg-info'      => 'background-color: #0DCAF0; color: #000000;',
            'bg-primary'   => 'background-color: #0D6EFD; color: #FFFFFF;',
        ];
        foreach ($badgemap as $class => $style) {
            $html = preg_replace(
                '/<span\s+class="badge\s+' . preg_quote($class, '/') . '[^"]*"/',
                '<span style="' . $style . ' font-size: 7pt; padding: 1px 3px;"',
                $html,
            );
        }

        // Catch any remaining badge spans.
        $html = preg_replace(
            '/<span\s+class="badge[^"]*"/',
            '<span style="background-color: #6C757D; color: #FFFFFF; font-size: 7pt; padding: 1px 3px;"',
            $html,
        );

        // Bold text (fw-bold).
        $html = preg_replace(
            '/<(span|div)\s+class="[^"]*fw-bold[^"]*"/',
            '<$1 style="font-weight: bold;"',
            $html,
        );

        // Muted text (text-muted).
        $html = preg_replace(
            '/<(span|div)\s+class="[^"]*text-muted[^"]*"/',
            '<$1 style="color: #6C757D;"',
            $html,
        );

        // Border-top dividers.
        $html = preg_replace(
            '/<div\s+class="[^"]*border-top[^"]*"/',
            '<div style="border-top: 1px solid #DEE2E6; padding-top: 2mm; margin-top: 2mm;"',
            $html,
        );

        // Strong tags are fine for TCPDF. Clean up remaining class attrs.
        $html = preg_replace('/\s+class="[^"]*"/', '', $html);

        return $html;
    }

    /**
     * Render a section heading with a blue accent bar.
     *
     * @param string $title
     * @param int|null $icon Font Awesome codepoint to set beside the title.
     * @param float|null $x Left edge, for a heading inside a column.
     * @param float|null $width Heading width; defaults to the content width.
     * @param float $keepwithnext Height of the block that must stay on the same
     *                            page as this heading, in mm.
     */
    private function render_section_heading(
        string $title,
        ?int $icon = null,
        ?float $x = null,
        ?float $width = null,
        float $keepwithnext = 0
    ): void {
        $x ??= self::MARGIN_H;
        $width ??= $this->getPageWidth() - (self::MARGIN_H * 2);

        // A heading alone at the foot of a page, with what it introduces on the
        // next, reads as a mistake. Callers say how much of what follows has to
        // travel with it.
        $this->ensure_space(8 + $keepwithnext);

        $y = $this->GetY();

        // Blue accent bar.
        $this->set_fill_from_hex(self::COLOR_PRIMARY);
        $this->Rect($x, $y, 1.5, 6, 'F');

        $textx = $x + 5;

        // Section mark, in the accent colour, matching the icon the same section
        // carries on screen so the PDF reads as the page it came from.
        $iconfont = $icon !== null ? $this->icon_font() : null;
        if ($iconfont) {
            $this->set_text_from_hex(self::COLOR_PRIMARY);
            $this->SetFont($iconfont, '', 9);
            $this->SetXY($textx, $y + 0.4);
            $this->Cell(5, 6, \mb_chr($icon, 'UTF-8'), 0, 0, 'L');
            $textx += 6;
        }

        // Heading text.
        $this->set_text_from_hex(self::COLOR_DARK);
        $this->SetFont('helvetica', 'B', 11);
        $this->SetXY($textx, $y);
        $this->Cell($width - ($textx - $x), 6, $title, 0, 1, 'L');
        $this->SetY($this->GetY() + 2);
    }

    /**
     * Font Awesome glyphs for the engagement metrics, keyed as the tiles are.
     *
     * Codepoints from the Solid set Moodle bundles, so the PDF shows the same
     * marks as the Activity Points card on screen rather than a second visual
     * vocabulary a student would have to learn twice.
     *
     * @var array<string, int>
     */
    private const METRIC_ICONS = [
        'chats' => 0xF086, // Icon: comments.
        'talks' => 0xF130, // Icon: microphone.
        'raisehand' => 0xF0A6, // Icon: hand-point-up.
        'pollvotes' => 0xF681, // Icon: square-poll-vertical.
        'emojis' => 0xF118, // Icon: face-smile.
        'duration' => 0xF017, // Icon: clock.
    ];

    /** @var array Footer content, kept for TCPDF's per-page Footer() callback. */
    private array $footerdata = [];

    /** @var string|null Registered icon font family, or null when unavailable. */
    private ?string $iconfont = null;

    /** @var bool Whether icon font resolution has been attempted. */
    private bool $iconfontresolved = false;

    /**
     * Register Moodle's bundled Font Awesome with TCPDF, once per document.
     *
     * TCPDF cannot read the WOFF2 the browser gets, but core also ships the
     * TrueType original, which TCPDF converts into its own font definition. The
     * conversion is slow enough to be worth keeping, so the result is written to
     * the local cache and reused; a student downloading feedback pays for it
     * only the first time after a cache purge.
     *
     * Every failure path returns null and the tiles render without icons. A
     * missing font is a cosmetic loss, and must never be the reason a student
     * cannot download their feedback.
     *
     * @return string|null The TCPDF font family name.
     */
    private function icon_font(): ?string {
        global $CFG;

        if ($this->iconfontresolved) {
            return $this->iconfont;
        }
        $this->iconfontresolved = true;

        $ttf = $CFG->libdir . '/fonts/fa-solid-900.ttf';
        if (!is_readable($ttf)) {
            return null;
        }

        try {
            $outpath = \make_localcache_directory('local_unifiedgrader/tcpdffonts') . '/';
            $fontname = \TCPDF_FONTS::addTTFfont($ttf, 'TrueTypeUnicode', '', 32, $outpath);
            if (empty($fontname) || !is_readable($outpath . $fontname . '.php')) {
                return null;
            }
            $this->AddFont($fontname, '', $outpath . $fontname . '.php');
            $this->iconfont = $fontname;
        } catch (\Throwable $e) {
            // Conversion can fail on an unwritable cache or a font TCPDF will
            // not parse. Neither is worth failing the download over.
            debugging(
                'local_unifiedgrader: could not register the icon font for the feedback PDF: '
                    . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            $this->iconfont = null;
        }

        return $this->iconfont;
    }

    /**
     * Render the engagement figures as a row of dashboard tiles.
     *
     * Drawn rather than written as HTML: TCPDF lays out a Bootstrap grid as a
     * vertical list of numbers, which is what this replaces. Every metric is
     * shown even at zero, so two students' summaries can be read side by side
     * and a zero is visibly a zero rather than a missing tile.
     *
     * @param array $data
     */
    private function render_analytics_widgets(array $data): void {
        $analytics = $data['analytics'] ?? [];
        if (empty($analytics['hasengagement'])) {
            return;
        }

        // The totals row travels with the heading: a lone "Activity Points" at
        // the foot of a page says nothing on its own.
        $this->render_section_heading(
            get_string('bbb_activitypoints_heading', 'local_unifiedgrader'),
            0xE0E3, // Icon: chart-column.
            null,
            null,
            $this->icon_font() ? 32 : 27
        );

        $totals = $analytics['totals'] ?? [];
        $sessioncount = (int) ($analytics['sessioncount'] ?? 0);
        $this->render_metric_row(
            get_string('feedback_summary_sessions_total', 'local_unifiedgrader', $sessioncount),
            $totals,
            true
        );

        // Per-session rows, when there is more than one session to separate.
        foreach ($analytics['sessions'] ?? [] as $session) {
            $label = get_string('bbb_session_label_prefix', 'local_unifiedgrader')
                . ' ' . ($session['sessionlabel'] ?? '');
            $this->render_metric_row($label, $session, false);
        }

        $this->SetY($this->GetY() + 3);
    }

    /**
     * Draw one labelled row of metric tiles.
     *
     * @param string $label Row caption, e.g. the session date.
     * @param array $metrics Carries chats, talks, raisehand, pollvotes, emojis
     *                       and durationformatted.
     * @param bool $emphasis Whether to draw the row as the headline totals.
     */
    private function render_metric_row(string $label, array $metrics, bool $emphasis): void {
        $pagewidth = $this->getPageWidth();
        $contentwidth = $pagewidth - (self::MARGIN_H * 2);

        $tiles = [
            ['chats', (string) ($metrics['chats'] ?? 0),
                get_string('bbb_metric_chats', 'local_unifiedgrader')],
            ['talks', (string) ($metrics['talks'] ?? 0),
                get_string('bbb_metric_talks', 'local_unifiedgrader')],
            ['raisehand', (string) ($metrics['raisehand'] ?? 0),
                get_string('bbb_metric_raisehand', 'local_unifiedgrader')],
            ['pollvotes', (string) ($metrics['pollvotes'] ?? 0),
                get_string('bbb_metric_pollvotes', 'local_unifiedgrader')],
            ['emojis', (string) ($metrics['emojis'] ?? 0),
                get_string('bbb_metric_emojis', 'local_unifiedgrader')],
            ['duration', (string) ($metrics['durationformatted'] ?? '0m'),
                get_string('bbb_metric_duration', 'local_unifiedgrader')],
        ];

        $iconfont = $this->icon_font();
        // Without the icon font the tile keeps its old proportions rather than
        // reserving a band of empty space for a mark that is not coming.
        $tileheight = $emphasis ? ($iconfont ? 24 : 19) : ($iconfont ? 20 : 15);
        $gap = 2;
        $tilewidth = ($contentwidth - ($gap * (count($tiles) - 1))) / count($tiles);

        // Keep a row whole: break to a new page rather than split label from tiles.
        $this->ensure_space($tileheight + 8);

        // Row caption. The totals row is titled in the same blue that fills its
        // tiles, so the eye pairs the two before reading either.
        $this->SetFont('helvetica', 'B', $emphasis ? 8.5 : 8);
        $this->set_text_from_hex($emphasis ? self::COLOR_PRIMARY : self::COLOR_MUTED);
        $this->SetXY(self::MARGIN_H, $this->GetY());
        $this->Cell($contentwidth, 4, $label, 0, 1, 'L');

        // The totals read as solid blue tiles and each session as a quiet
        // outlined one, so the summary and its parts are told apart at a glance
        // rather than by reading the captions. Same six metrics either way: the
        // rows stay comparable column by column.
        $y = $this->GetY() + 1;
        $x = self::MARGIN_H;
        foreach ($tiles as [$key, $value, $caption]) {
            if ($emphasis) {
                $this->set_fill_from_hex(self::COLOR_PRIMARY);
                $this->RoundedRect($x, $y, $tilewidth, $tileheight, self::RADIUS, '1111', 'F');
            } else {
                $this->set_fill_from_hex(self::COLOR_WHITE);
                $this->set_draw_from_hex(self::COLOR_BORDER);
                $this->SetLineWidth(0.2);
                $this->RoundedRect($x, $y, $tilewidth, $tileheight, self::RADIUS, '1111', 'DF');
            }

            // Icon, above the figure. Drawn in the caption's colour rather than
            // the value's so it reads as part of the label, not as data.
            $valuetop = $y + ($emphasis ? 3.5 : 2.5);
            if ($iconfont && isset(self::METRIC_ICONS[$key])) {
                $this->set_text_from_hex($emphasis ? self::COLOR_PRIMARY_TINT : self::COLOR_MUTED);
                $this->SetFont($iconfont, '', $emphasis ? 9 : 7.5);
                $this->SetXY($x, $y + 2);
                $this->Cell($tilewidth, 5, \mb_chr(self::METRIC_ICONS[$key], 'UTF-8'), 0, 0, 'C');
                $valuetop = $y + ($emphasis ? 7.5 : 6);
            }

            // Value.
            $this->set_text_from_hex($emphasis ? self::COLOR_WHITE : self::COLOR_DARK);
            $this->SetFont('helvetica', 'B', $emphasis ? 15 : 11.5);
            $this->SetXY($x, $valuetop);
            $this->Cell($tilewidth, $emphasis ? 8 : 6, $value, 0, 0, 'C');

            // Caption.
            $this->set_text_from_hex($emphasis ? self::COLOR_PRIMARY_TINT : self::COLOR_MUTED);
            $this->SetFont('helvetica', '', 6.5);
            $this->SetXY($x, $y + $tileheight - 5.5);
            $this->Cell($tilewidth, 4, $caption, 0, 0, 'C');

            $x += $tilewidth + $gap;
        }

        $this->SetY($y + $tileheight + ($emphasis ? 4 : 2.5));
    }

    /**
     * Render timestamped recording comments as a readable list.
     *
     * @param array $data
     */
    private function render_annotation_comments(array $data): void {
        $this->SetAutoPageBreak(true, 15);
        $this->AddPage('P', 'A4');

        $pagewidth = $this->getPageWidth();
        $contentwidth = $pagewidth - (self::MARGIN_H * 2);

        $this->render_section_heading(
            get_string('feedback_summary_recording_comments', 'local_unifiedgrader'),
            0xF008 // Icon: film.
        );

        $rows = '';
        $lastsession = null;
        foreach ($data['annotations'] as $annotation) {
            $sessionlabel = (string) ($annotation['sessionlabel'] ?? '');
            // Group under a session heading, but only once the comments actually
            // span more than one recording.
            if ($sessionlabel !== '' && $sessionlabel !== $lastsession) {
                if ($lastsession !== null) {
                    $rows .= '<tr><td colspan="2" style="height: 4mm;"></td></tr>';
                }
                $rows .= '<tr><td colspan="2" style="font-size: 8pt; color: #6C757D;"><b>'
                    . htmlspecialchars($sessionlabel) . '</b></td></tr>';
                $lastsession = $sessionlabel;
            }
            $rows .= '<tr>'
                . '<td width="15%" style="font-size: 9pt; color: #0D6EFD;"><b>'
                . htmlspecialchars((string) $annotation['timestamp']) . '</b></td>'
                . '<td width="85%" style="font-size: 9pt; color: #212529;">'
                . nl2br(htmlspecialchars((string) $annotation['text'])) . '</td>'
                . '</tr>'
                . '<tr><td colspan="2" style="height: 2mm;"></td></tr>';
        }

        $this->writeHTMLCell(
            $contentwidth,
            0,
            self::MARGIN_H,
            $this->GetY(),
            '<table cellpadding="2">' . $rows . '</table>',
            0,
            1,
            false,
            true,
            'L'
        );
    }

    /**
     * Draw the footer on every page.
     *
     * Called by TCPDF for each page as it closes, so it covers the annotation
     * pages the summary adds after itself as well as the summary page, and the
     * pages TCPDF adds by itself when long feedback overflows.
     *
     * The capitalised name is TCPDF's, not ours: this overrides a parent method
     * and renaming it would simply stop it being called. Hence the blanket sniff
     * exemption on the declaration.
     */
    // phpcs:ignore
    public function Footer(): void {
        $pagewidth = $this->getPageWidth();
        $y = $this->getPageHeight() - 12;

        // Thin separator line.
        $this->set_draw_from_hex(self::COLOR_BORDER);
        $this->SetLineWidth(0.2);
        $this->Line(self::MARGIN_H, $y, $pagewidth - self::MARGIN_H, $y);

        $y += 2;
        $this->SetFont('helvetica', '', 7);
        $this->set_text_from_hex(self::COLOR_MUTED);

        // Graded date (left).
        if (!empty($this->footerdata['dategraded'])) {
            $this->SetXY(self::MARGIN_H, $y);
            $this->Cell(70, 4, get_string(
                'feedback_summary_graded_on',
                'local_unifiedgrader',
                $this->footerdata['dategraded']
            ), 0, 0, 'L');
        }

        // Page number (centre). getAliasNbPages() returns a placeholder that
        // TCPDF substitutes when the document is closed, which is the only
        // point at which the total is known.
        $this->SetXY(self::MARGIN_H, $y);
        $this->Cell($pagewidth - (self::MARGIN_H * 2), 4, get_string(
            'feedback_summary_page_of',
            'local_unifiedgrader',
            (object) [
                'page' => $this->getAliasNumPage(),
                'total' => $this->getAliasNbPages(),
            ]
        ), 0, 0, 'C');

        // Generated by (right).
        $this->SetXY($pagewidth - self::MARGIN_H - 60, $y);
        $this->Cell(60, 4, get_string(
            'feedback_summary_generated_by',
            'local_unifiedgrader'
        ), 0, 0, 'R');
    }

    /**
     * Sanitise feedback HTML for TCPDF rendering.
     *
     * Strips media tags (img, video, audio, iframe, object, embed) that TCPDF
     * cannot render from authenticated URLs, and appends a note if any were removed.
     *
     * @param string $html
     * @return string
     */
    private function sanitise_feedback_html(string $html): string {
        $mediatags = '/<(img|video|audio|iframe|object|embed|source)\b[^>]*\/?>/i';
        $cleaned = preg_replace($mediatags, '', $html);

        // Also remove empty video/audio/object containers.
        $containers = '/<(video|audio|object|iframe)[^>]*>.*?<\/\1>/is';
        $cleaned = preg_replace($containers, '', $cleaned);

        // If we removed anything, append a note.
        if (strlen($cleaned) < strlen($html)) {
            $cleaned .= '<p style="color: #6C757D; font-style: italic; font-size: 8pt;">'
                . get_string('feedback_summary_media_note', 'local_unifiedgrader')
                . '</p>';
        }

        // Strip JS event handlers.
        $cleaned = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $cleaned);
        $cleaned = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $cleaned);

        // Remove <script>/<style> blocks with their contents. The PDF writer has no
        // notion of them, so the code between the tags would simply be typeset into
        // the document as text (see pdf_text::plain() for the same hazard).
        $cleaned = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $cleaned);
        $cleaned = preg_replace('#<(script|style)\b[^>]*>.*$#is', '', $cleaned);

        return $cleaned;
    }

    /**
     * Set fill colour from a hex integer.
     *
     * @param int $hex e.g. 0x0D6EFD
     */
    /**
     * Break to a new page unless $needed mm remain above the footer.
     *
     * @param float $needed Height the next block needs, in mm.
     */
    private function ensure_space(float $needed): void {
        $limit = $this->getPageHeight() - $this->getBreakMargin();
        if (($this->GetY() + $needed) > $limit) {
            $this->AddPage('P', 'A4');
        }
    }

    /**
     * Split a packed hex colour into the [R, G, B] triple TCPDF's style arrays want.
     *
     * @param int $hex
     * @return int[]
     */
    private function hex_to_rgb(int $hex): array {
        return [($hex >> 16) & 0xFF, ($hex >> 8) & 0xFF, $hex & 0xFF];
    }

    /**
     * Set the fill colour from a packed hex value.
     *
     * @param int $hex
     */
    private function set_fill_from_hex(int $hex): void {
        $this->SetFillColor(($hex >> 16) & 0xFF, ($hex >> 8) & 0xFF, $hex & 0xFF);
    }

    /**
     * Set text colour from a hex integer.
     *
     * @param int $hex
     */
    private function set_text_from_hex(int $hex): void {
        $this->SetTextColor(($hex >> 16) & 0xFF, ($hex >> 8) & 0xFF, $hex & 0xFF);
    }

    /**
     * Set draw colour from a hex integer.
     *
     * @param int $hex
     */
    private function set_draw_from_hex(int $hex): void {
        $this->SetDrawColor(($hex >> 16) & 0xFF, ($hex >> 8) & 0xFF, $hex & 0xFF);
    }
}
