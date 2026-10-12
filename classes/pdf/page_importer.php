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
 * Copies the pages of a PDF into a new one, so they can be drawn on.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za) (https://www.sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\pdf;

/**
 * Moodle's own PDF page importer, with two things added: a page can be given a
 * rotation as it is copied, and a margin down its right-hand side.
 *
 * The importer copies a page as its content was laid out and drops the turn the
 * original was displayed at. A scan stored sideways would come out sideways the
 * other way round from what the student handed in, so the turn is put back.
 */
class page_importer extends \assignfeedback_editpdf\pdf {
    /** @var float[][] Width and height of each copied page, in points, by 1-based page number. */
    private array $pagesizes = [];

    /**
     * Copy the next page of the source and make it the current page.
     *
     * @param int $rotate Degrees clockwise the page is to be displayed at: 0, 90, 180 or 270.
     * @param float $margin Width of an empty margin to add down the right-hand side, in points.
     * @return bool False when there are no more pages.
     */
    public function copy_next_page(int $rotate = 0, float $margin = 0.0): bool {
        if (!$this->filename || $this->currentpage >= $this->pagecount) {
            return false;
        }
        $this->currentpage++;

        $template = $this->importPage($this->currentpage);
        $size = $this->getTemplateSize($template);
        $this->pagesizes[$this->currentpage] = [(float) $size['width'], (float) $size['height']];

        $width = $size['width'] + $margin;
        $orientation = $width > $size['height'] ? 'L' : 'P';
        $this->AddPage($orientation, [
            'MediaBox' => ['llx' => 0, 'lly' => 0, 'urx' => $width, 'ury' => $size['height']],
            'Rotate' => $rotate,
        ]);
        // No automatic page break: a mark near the foot of a page must not start a new one.
        $this->setPageOrientation($orientation, false, 0);
        // The page keeps its own size and place; the margin is left clear beside it.
        $this->useTemplate($template, 0, 0, $size['width'], $size['height']);

        return true;
    }

    /**
     * How many pages the source has.
     *
     * @return int
     */
    public function source_page_count(): int {
        return (int) $this->pagecount;
    }

    /**
     * The size of a page already copied.
     *
     * @param int $page 1-based page number.
     * @return float[] Width and height in points, or two zeros for a page not copied.
     */
    public function copied_page_size(int $page): array {
        return $this->pagesizes[$page] ?? [0.0, 0.0];
    }
}
