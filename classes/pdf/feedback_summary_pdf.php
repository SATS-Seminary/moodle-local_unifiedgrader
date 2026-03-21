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

    /** @var int Light background color. */
    private const COLOR_LIGHT_BG = 0xF8F9FA;

    /** @var int White. */
    private const COLOR_WHITE = 0xFFFFFF;

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
     * @return string PDF content as binary string
     */
    public function generate(array $data): string {
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetMargins(self::MARGIN_H, self::MARGIN_TOP, self::MARGIN_H);
        $this->SetAutoPageBreak(true, 15);
        $this->setPageUnit('mm');

        $this->AddPage('P', 'A4');
        $this->SetFont('helvetica', '', 10);

        $this->render_header_band($data);
        $this->render_grade_display($data);
        $this->render_penalties($data);
        $this->render_plagiarism_section($data);
        $this->render_feedback_section($data);
        $this->render_grading_section($data);
        $this->render_footer($data);

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
        $bandheight = 14;
        $x = 0;
        $y = $this->GetY();

        // Blue header band.
        $this->set_fill_from_hex(self::COLOR_PRIMARY);
        $this->Rect($x, $y, $pagewidth, $bandheight, 'F');

        // Activity name (left).
        $this->SetXY(self::MARGIN_H, $y + 2);
        $this->set_text_from_hex(self::COLOR_WHITE);
        $this->SetFont('helvetica', 'B', 13);
        $this->Cell(100, 5, $data['activityname'], 0, 0, 'L');

        // Course name (right).
        $this->SetFont('helvetica', '', 9);
        $this->SetXY($pagewidth - self::MARGIN_H - 80, $y + 2);
        $this->Cell(80, 5, $data['coursename'], 0, 0, 'R');

        // Student name (left, second line).
        $this->SetXY(self::MARGIN_H, $y + 8);
        $this->SetFont('helvetica', '', 9);
        $this->set_text_from_hex(0xCFE2FF);
        $this->Cell(100, 4, $data['studentname'], 0, 0, 'L');

        $this->SetY($y + $bandheight + 4);
    }

    /**
     * Render the large grade display with coloured circle.
     *
     * @param array $data
     */
    private function render_grade_display(array $data): void {
        $pagewidth = $this->getPageWidth();
        $centrex = $pagewidth / 2;
        $y = $this->GetY();

        $hasgrade = $data['gradevalue'] !== null && $data['percentage'] !== null;

        // Determine circle colour.
        if (!$hasgrade) {
            $circlecolor = self::COLOR_MUTED;
        } else if ($data['percentage'] >= 75) {
            $circlecolor = self::COLOR_GREEN;
        } else if ($data['percentage'] >= 50) {
            $circlecolor = self::COLOR_AMBER;
        } else {
            $circlecolor = self::COLOR_RED;
        }

        // Draw filled circle.
        $radius = 14;
        $this->set_fill_from_hex($circlecolor);
        $this->Circle($centrex, $y + $radius, $radius, 0, 360, 'F');

        // Percentage text inside circle.
        $this->set_text_from_hex(self::COLOR_WHITE);
        if ($hasgrade) {
            $this->SetFont('helvetica', 'B', 22);
            $pcttext = $data['percentage'] . '%';
        } else {
            $this->SetFont('helvetica', 'B', 11);
            $pcttext = get_string('feedback_summary_no_grade', 'local_unifiedgrader');
        }
        $textwidth = $this->GetStringWidth($pcttext);
        $this->SetXY($centrex - ($textwidth / 2), $y + $radius - 5);
        $this->Cell($textwidth, 10, $pcttext, 0, 0, 'C');

        // Grade fraction below circle.
        $belowy = $y + ($radius * 2) + 3;
        $this->set_text_from_hex(self::COLOR_DARK);
        if ($hasgrade) {
            $this->SetFont('helvetica', 'B', 13);
            $gradetext = round($data['gradevalue'], 2) . ' / ' . round($data['maxgrade'], 2);
            $this->SetXY(self::MARGIN_H, $belowy);
            $this->Cell($pagewidth - (self::MARGIN_H * 2), 6, $gradetext, 0, 1, 'C');
        }

        $this->SetY($belowy + 8);
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
                . '&nbsp;&nbsp;' . htmlspecialchars($penalty['text']) . '&nbsp;&nbsp;'
                . '</span>&nbsp;&nbsp;';
        }
        $html .= '</div>';

        $this->SetXY(self::MARGIN_H, $y);
        $this->writeHTMLCell($contentwidth, 0, self::MARGIN_H, $y, $html, 0, 1, false, true, 'C');
        $this->SetY($this->GetY() + 3);
    }

    /**
     * Render the overall feedback section with accent bar.
     *
     * @param array $data
     */
    private function render_feedback_section(array $data): void {
        $feedback = trim($data['feedback'] ?? '');
        if ($feedback === '') {
            return;
        }

        $pagewidth = $this->getPageWidth();
        $contentwidth = $pagewidth - (self::MARGIN_H * 2);

        $this->render_section_heading(
            get_string('feedback_summary_overall_feedback', 'local_unifiedgrader')
        );

        // Sanitise HTML for TCPDF rendering.
        $feedback = $this->sanitise_feedback_html($feedback);

        // Wrap in styled container.
        $html = '<div style="font-size: 9pt; color: #212529; line-height: 1.5;">'
            . $feedback
            . '</div>';

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
     * Render the rubric or marking guide section.
     *
     * @param array $data
     */
    private function render_grading_section(array $data): void {
        if ($data['gradingmethod'] === 'rubric' && !empty($data['rubriccriteria'])) {
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
            get_string('rubric', 'local_unifiedgrader')
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
                        . htmlspecialchars($level['definition'])
                        . ' (' . $level['score'] . ' pts)</span><br/>';
                } else {
                    $levelshtml .= '<span style="color: #6C757D;">'
                        . htmlspecialchars($level['definition'])
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
                . htmlspecialchars($criterion['description']) . '</td>'
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
                    . htmlspecialchars($criterion['remark'])
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
            get_string('markingguide', 'local_unifiedgrader')
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
                $remarkhtml = htmlspecialchars($criterion['remark']);
            } else {
                $remarkhtml = '<span style="color: #ADB5BD; font-style: italic;">No comment</span>';
            }

            $html .= '<tr>'
                . '<td style="background-color: ' . $rowbg . '; font-weight: bold; vertical-align: top;">'
                . htmlspecialchars($criterion['shortname']) . '</td>'
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
            get_string('plagiarism', 'local_unifiedgrader')
        );

        $html = '<table cellpadding="3" cellspacing="0" style="font-size: 8pt;">';
        foreach ($data['plagiarismlinks'] as $link) {
            $label = htmlspecialchars($link['label'] ?? '');
            // Strip HTML tags from plagiarism output but keep the text content.
            // The HTML often contains <a> tags with report links that won't work in PDF.
            $text = trim(strip_tags($link['html'] ?? ''));
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

        // Convert Bootstrap HTML to TCPDF-compatible inline styles.
        $html = $this->convert_bootstrap_to_pdf_html($data['additionalcontent']);

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
     */
    private function render_section_heading(string $title): void {
        $y = $this->GetY();

        // Blue accent bar.
        $this->set_fill_from_hex(self::COLOR_PRIMARY);
        $this->Rect(self::MARGIN_H, $y, 1.5, 6, 'F');

        // Heading text.
        $this->set_text_from_hex(self::COLOR_DARK);
        $this->SetFont('helvetica', 'B', 11);
        $this->SetXY(self::MARGIN_H + 5, $y);
        $this->Cell(100, 6, $title, 0, 1, 'L');
        $this->SetY($this->GetY() + 2);
    }

    /**
     * Render the footer line.
     *
     * @param array $data
     */
    private function render_footer(array $data): void {
        // Disable auto page break so footer doesn't trigger new pages.
        $this->SetAutoPageBreak(false, 0);

        $pagewidth = $this->getPageWidth();
        $pageheight = $this->getPageHeight();
        $y = $pageheight - 12;

        // Thin separator line.
        $this->set_draw_from_hex(0xDEE2E6);
        $this->Line(self::MARGIN_H, $y, $pagewidth - self::MARGIN_H, $y);

        $y += 2;
        $this->SetFont('helvetica', '', 7);
        $this->set_text_from_hex(self::COLOR_MUTED);

        // Graded date (left).
        if (!empty($data['dategraded'])) {
            $this->SetXY(self::MARGIN_H, $y);
            $gradedonstr = get_string(
                'feedback_summary_graded_on',
                'local_unifiedgrader',
                $data['dategraded']
            );
            $this->Cell(80, 4, $gradedonstr, 0, 0, 'L');
        }

        // Generated by (right).
        $this->SetXY($pagewidth - self::MARGIN_H - 60, $y);
        $generatedbystr = get_string(
            'feedback_summary_generated_by',
            'local_unifiedgrader'
        );
        $this->Cell(60, 4, $generatedbystr, 0, 0, 'R');
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

        return $cleaned;
    }

    /**
     * Set fill colour from a hex integer.
     *
     * @param int $hex e.g. 0x0D6EFD
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
