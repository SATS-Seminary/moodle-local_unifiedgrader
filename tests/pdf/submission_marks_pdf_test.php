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

/**
 * Tests for placing the marker's text-anchored marks on a submission PDF.
 *
 * The question every test here asks is the one that matters to a student: does
 * the mark land on the words it was put on.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\pdf\submission_marks_pdf
 * @covers \local_unifiedgrader\pdf\page_importer
 */
final class submission_marks_pdf_test extends \advanced_testcase {
    /**
     * A line of text as Ghostscript lists it: one entry per character, each
     * 6 points wide, on the given baseline.
     *
     * @param string $text
     * @param float $x Where the line starts.
     * @param float $y The baseline.
     * @param float $size The type size.
     * @return array[]
     */
    private function line(string $text, float $x, float $y, float $size = 12.0): array {
        $chars = [];
        foreach (mb_str_split($text) as $i => $c) {
            $chars[] = ['c' => $c, 'x0' => $x + ($i * 6), 'x1' => $x + ($i * 6) + 6, 'y' => $y, 'size' => $size];
        }
        return $chars;
    }

    /**
     * A mark as the download hands it over.
     *
     * @param string $anchor The marked phrase.
     * @param int $offset Where in the page's text it began.
     * @param string $type
     * @param int $page
     * @return array
     */
    private function mark(string $anchor, int $offset = 0, string $type = 'highlight', int $page = 1): array {
        return ['page' => $page, 'startoffset' => $offset, 'anchortext' => $anchor, 'marktype' => $type,
            'color' => '#ef4540', 'number' => $type === 'comment' ? 1 : null];
    }

    /**
     * Ghostscript's listing is read into characters with their place and size.
     */
    public function test_reads_the_character_listing(): void {
        $listing = implode("\n", [
            '<page>',
            '<span bbox="31 49 60 49" font="Helvetica-Bold" size="16.0000">',
            '<char bbox="31 49 41 49" c="T"/>',
            '<char bbox="41 49 51 49" c="&amp;"/>',
            '</span>',
            '</page>',
            '<page>',
            '<span bbox="28 75 40 75" font="Helvetica" size="11.0000">',
            '<char bbox="28 75 34.5 75" c="&quot;"/>',
            '</span>',
            '</page>',
        ]);

        $pages = submission_marks_pdf::parse_characters($listing);

        $this->assertSame([1, 2], array_keys($pages));
        $this->assertSame(['c' => 'T', 'x0' => 31.0, 'x1' => 41.0, 'y' => 49.0, 'size' => 16.0], $pages[1][0]);
        $this->assertSame('&', $pages[1][1]['c']);
        $this->assertSame('"', $pages[2][0]['c']);
        $this->assertSame(34.5, $pages[2][0]['x1']);
        $this->assertSame(11.0, $pages[2][0]['size']);
    }

    /**
     * A phrase is found where it is, with one rectangle from its first letter to its last.
     */
    public function test_a_phrase_is_placed_on_its_words(): void {
        $pages = [1 => $this->line('The sower went out to sow.', 50, 100)];

        $placed = submission_marks_pdf::place_marks($pages, [$this->mark('went out', 10)]);

        $this->assertCount(1, $placed[1]);
        // The word "went" starts at the eleventh character: 50 + (10 * 6).
        $this->assertEquals([['x0' => 110.0, 'x1' => 158.0, 'y' => 100.0, 'size' => 12.0]], $placed[1][0]['lines']);
    }

    /**
     * A phrase that runs over a line break gets a rectangle on each line, and is
     * found whether or not the two sources agree about the space at the break.
     */
    public function test_a_phrase_across_a_line_break(): void {
        // Ghostscript joins the two lines with nothing between them.
        $pages = [1 => array_merge($this->line('seed fell among', 50, 100), $this->line('thorns and was choked', 50, 116))];

        // The browser joined them with a space.
        $placed = submission_marks_pdf::place_marks($pages, [$this->mark('fell among thorns', 5)]);

        $lines = $placed[1][0]['lines'];
        $this->assertCount(2, $lines);
        $this->assertEquals(['x0' => 80.0, 'x1' => 140.0, 'y' => 100.0, 'size' => 12.0], $lines[0]);
        $this->assertEquals(['x0' => 50.0, 'x1' => 86.0, 'y' => 116.0, 'size' => 12.0], $lines[1]);
    }

    /**
     * A phrase that occurs more than once is placed on the occurrence the marker chose.
     */
    public function test_a_repeated_phrase_goes_to_the_right_occurrence(): void {
        $pages = [1 => array_merge(
            $this->line('the good soil here', 50, 100),
            $this->line('the good soil there', 50, 116),
            $this->line('the good soil again', 50, 132),
        )];

        // Offsets 4, 22 and 41 are where "good soil" starts on each line.
        foreach ([4 => 100.0, 22 => 116.0, 41 => 132.0] as $offset => $baseline) {
            $placed = submission_marks_pdf::place_marks($pages, [$this->mark('good soil', $offset)]);
            $this->assertSame($baseline, $placed[1][0]['lines'][0]['y'], "offset $offset");
            $this->assertSame(74.0, $placed[1][0]['lines'][0]['x0']);
        }
    }

    /**
     * A ligature in the page and its letters in the phrase are the same thing.
     */
    public function test_ligatures_are_matched_to_their_letters(): void {
        $pages = [1 => $this->line("an e\u{FB03}cient \u{FB01}eld", 50, 100)];

        $placed = submission_marks_pdf::place_marks($pages, [$this->mark('efficient field', 3)]);

        $this->assertEquals(68.0, $placed[1][0]['lines'][0]['x0']);
        // Fifteen characters on the page, the last ending at 50 + (15 * 6).
        $this->assertEquals(140.0, $placed[1][0]['lines'][0]['x1']);
    }

    /**
     * A page that has lost the letters behind its ligatures still takes the mark,
     * on what is left of the phrase.
     */
    public function test_a_phrase_with_lost_letters_is_still_placed(): void {
        // What is left of "a kind of attention, and" once "tt" and "ti" have gone.
        $pages = [1 => $this->line('but a kind of aenon, and that', 50, 100)];

        $placed = submission_marks_pdf::place_marks($pages, [$this->mark('a kind of attention', 4)]);

        $this->assertCount(1, $placed[1]);
        $this->assertEquals(74.0, $placed[1][0]['lines'][0]['x0']);
        // It ends on the "n" of what remains of the word.
        $this->assertEquals(164.0, $placed[1][0]['lines'][0]['x1']);
    }

    /**
     * A mark is never put somewhere it does not belong: a phrase that is not on
     * the page is left out, and so is a mark for a page the document lacks.
     */
    public function test_a_phrase_that_is_not_there_is_not_placed(): void {
        $pages = [1 => $this->line('The sower went out to sow.', 50, 100)];

        $placed = submission_marks_pdf::place_marks($pages, [
            $this->mark('the reaper came back'),
            $this->mark('sower', 4, 'highlight', 7),
            $this->mark('xy'),
        ]);

        $this->assertSame([], $placed);
    }

    /**
     * The copy's measurements are used where its text survived, and the
     * original's where it did not, moved to where the copy puts the page.
     */
    public function test_measurements_are_reconciled_page_by_page(): void {
        $original = [
            1 => $this->line('attention', 50, 100),
            2 => $this->line('plain text', 50, 100),
            3 => $this->line('turned', 50, 100),
        ];
        $copy = [
            // Lost its ligature, and sits 10 points right and 20 down (a crop box).
            1 => $this->line('aenon', 60, 120),
            // Intact, but measured somewhere else: the copy is what is drawn on.
            2 => $this->line('plain text', 55, 105),
            // A page stored turned is only measurable on the copy.
            3 => $this->line('turne', 200, 300),
        ];
        $info = [1 => ['rotate' => 0], 2 => ['rotate' => 0], 3 => ['rotate' => 90]];

        $pages = submission_marks_pdf::reconcile($original, $copy, $info);

        $this->assertSame('attention', implode('', array_column($pages[1], 'c')));
        $this->assertSame(60.0, $pages[1][0]['x0']);
        $this->assertSame(120.0, $pages[1][0]['y']);
        $this->assertSame(55.0, $pages[2][0]['x0']);
        $this->assertSame(200.0, $pages[3][0]['x0']);
    }

    /**
     * Page sizes and stored rotations are read from Ghostscript's page listing,
     * in both the forms it writes numbers in.
     */
    public function test_reads_the_page_listing(): void {
        $listing = implode("\n", [
            '        File has 3 pages.',
            'Page 1 MediaBox: [0.000000 0.000000 595.276001 841.890015] CropBox: [0.000000 0.000000 595.276001 841.890015]',
            'Page 2 MediaBox: [0 0 612 792]    Rotate = 90',
            'Page 3 MediaBox: [0 0 612 792]    Rotate = -90',
        ]);

        $pages = submission_marks_pdf::parse_page_info($listing);

        $this->assertEqualsWithDelta(595.28, $pages[1]['width'], 0.01);
        $this->assertSame(0, $pages[1]['rotate']);
        $this->assertSame(90, $pages[2]['rotate']);
        $this->assertSame(270, $pages[3]['rotate']);
    }

    /**
     * A page the importer copied without its turn gets the turn back; one that
     * came out already turned is left alone.
     */
    public function test_a_turned_page_is_turned_back_once(): void {
        $original = [
            1 => ['width' => 612.0, 'height' => 792.0, 'rotate' => 0],
            2 => ['width' => 612.0, 'height' => 792.0, 'rotate' => 90],
            3 => ['width' => 612.0, 'height' => 792.0, 'rotate' => 90],
            4 => ['width' => 612.0, 'height' => 792.0, 'rotate' => 180],
        ];
        $copied = [1 => [612.0, 792.0], 2 => [612.0, 792.0], 3 => [792.0, 612.0], 4 => [612.0, 792.0]];

        $this->assertSame([2 => 90, 4 => 180], submission_marks_pdf::rotations_to_restore($original, $copied));
    }

    /**
     * With nothing to draw, or nothing to draw with, the document comes back untouched.
     */
    public function test_the_document_is_returned_as_given_when_nothing_can_be_drawn(): void {
        global $CFG;
        $this->resetAfterTest();

        $untouched = ['pdf' => '%PDF-not-really', 'placed' => []];
        $this->assertSame($untouched, submission_marks_pdf::apply('%PDF-not-really', []));

        $CFG->pathtogs = '/nowhere/gs';
        $this->assertFalse(submission_marks_pdf::is_available());
        $this->assertSame($untouched, submission_marks_pdf::apply('%PDF-not-really', [$this->mark('anything')]));
    }

    /**
     * End to end, with Ghostscript: a comment's numbered pin is drawn at the
     * start of the phrase it belongs to, on the right page; its card sits in a
     * margin to the right of the page with the comment and the marker's name;
     * and the page's own text is still where it was.
     */
    public function test_a_pin_is_drawn_at_its_phrase_with_its_card_beside_the_page(): void {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        $this->resetAfterTest();

        $gs = null;
        foreach ([$CFG->pathtogs ?? '', '/usr/bin/gs', '/usr/local/bin/gs', '/opt/homebrew/bin/gs'] as $candidate) {
            if ($candidate !== '' && is_executable($candidate)) {
                $gs = $candidate;
                break;
            }
        }
        if ($gs === null) {
            $this->markTestSkipped('Ghostscript is not installed.');
        }
        $CFG->pathtogs = $gs;
        if (!submission_marks_pdf::is_available()) {
            $this->markTestSkipped('The PDF page importer is not available.');
        }

        // Two A4 pages. The phrase is on the second, and so is a decoy higher up.
        $pdf = new \pdf();
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->AddPage('P', 'A4');
        $pdf->MultiCell(0, 7, 'The first page says nothing of note.', 0, 'L');
        $pdf->AddPage('P', 'A4');
        $pdf->MultiCell(0, 7, "A decoy: the marked words sit here too.\n\n\n\n\nFurther down, the marked words are these.", 0, 'L');
        $source = $pdf->Output('', 'S');
        $pagewidth = 595.28;

        // The second "marked words" is the one the marker chose: it starts well into the page's text.
        $result = submission_marks_pdf::apply($source, [
            [
                'page' => 2, 'startoffset' => 56, 'anchortext' => 'marked words', 'marktype' => 'comment',
                'color' => '#ef4540', 'number' => 7, 'text' => 'Zeugma belongs here.', 'author' => 'Quentin Marker',
            ],
            [
                'page' => 2, 'startoffset' => 0, 'anchortext' => 'words that are nowhere', 'marktype' => 'comment',
                'color' => '#ef4540', 'number' => 8, 'text' => 'Never drawn.', 'author' => 'Quentin Marker',
            ],
        ]);

        // The comment that was found is reported as placed; the one that was not is left for the summary.
        $this->assertSame([7], $result['placed']);
        $marked = $result['pdf'];
        $this->assertStringStartsWith('%PDF', $marked);
        $this->assertNotSame($source, $marked);

        // Read the result back the way the marks were placed: by where its characters are.
        $read = new \ReflectionMethod(submission_marks_pdf::class, 'read_characters');
        $read->setAccessible(true);
        $path = make_request_directory() . '/marked.pdf';
        file_put_contents($path, $marked);
        $pages = $read->invoke(null, $path);

        $this->assertCount(2, $pages);
        $text = fn(int $page): string => implode('', array_column($pages[$page], 'c'));
        $onpage = fn(int $page): array => array_values(array_filter($pages[$page], fn($c) => $c['x1'] <= $pagewidth));
        $inmargin = fn(int $page): array => array_values(array_filter($pages[$page], fn($c) => $c['x0'] > $pagewidth));
        $this->assertStringContainsString('The first page says nothing of note.', $text(1));
        $this->assertSame([], $inmargin(1), 'A page with no comment has an empty margin.');

        // The phrase's two occurrences on page 2, still where the student put them.
        $pagetext = implode('', array_column($onpage(2), 'c'));
        $occurrences = [];
        $offset = 0;
        while (($at = mb_strpos($pagetext, 'marked words', $offset)) !== false) {
            $occurrences[] = $onpage(2)[$at];
            $offset = $at + 1;
        }
        $this->assertCount(2, $occurrences);
        [$decoy, $chosen] = $occurrences;

        // The pin: its number, at the start of the second occurrence, just above its line.
        $pins = array_values(array_filter($onpage(2), fn(array $char): bool => $char['c'] === '7'));
        $this->assertCount(1, $pins, 'The pin number is drawn once on the page.');
        $pin = $pins[0];
        $this->assertEqualsWithDelta($chosen['x0'], ($pin['x0'] + $pin['x1']) / 2, 4.0, 'The pin is at the start of the phrase.');
        $this->assertGreaterThan($chosen['y'] - 24, $pin['y']);
        $this->assertLessThanOrEqual($chosen['y'], $pin['y']);
        $this->assertGreaterThan(40, abs($pin['y'] - $decoy['y']), 'The pin is not on the other occurrence.');

        // The card: in the margin, level with the pin, carrying the number, the marker and the comment.
        $margin = $inmargin(2);
        $margintext = implode('', array_column($margin, 'c'));
        $this->assertStringContainsString('7', $margintext);
        $this->assertStringContainsString('Quentin Marker', $margintext);
        $this->assertStringContainsString('Zeugma belongs here.', $margintext);
        $this->assertStringNotContainsString('Never drawn.', $margintext);
        $cardtop = min(array_column($margin, 'y'));
        $this->assertEqualsWithDelta($pin['y'], $cardtop, 14.0, 'The card starts level with its pin.');
    }
}
