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
 * Draws the marker's text-anchored marks onto a submission PDF.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za) (https://www.sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\pdf;

/**
 * Puts comment pins, ticks, crosses, queries, highlights and strike-outs on the
 * pages of a student's submission, where the marker placed them.
 *
 * A mark is stored against a phrase of the document's text, not against a place
 * on the page: on the web the browser finds the phrase in the rendered text and
 * paints the mark over it. A PDF has no browser to do that, so the marks never
 * reached the feedback PDF. Here Ghostscript reports where every character of
 * the document sits, the phrase is found in that, and the mark is drawn at those
 * coordinates with the same page importer Moodle's own PDF annotator uses.
 *
 * Everything here is best effort. Without Ghostscript the document is returned
 * as it came, and a comment whose phrase cannot be found is reported as not
 * placed, so the summary can list it instead.
 */
class submission_marks_pdf {
    /** @var int[][] RGB of the tint painted behind a marked phrase, by mark type. */
    private const TINTS = [
        'comment' => [239, 69, 64],
        'tick' => [25, 135, 84],
        'cross' => [220, 53, 69],
        'query' => [13, 110, 253],
        'highlight' => [255, 214, 0],
    ];

    /** @var float Width of the margin the comment cards sit in, as a share of the page's width. */
    private const MARGIN_SHARE = 0.36;

    /** @var string A font with the reach for comments in any of the site's languages. */
    private const CARD_FONT = 'freesans';

    /** @var float How far above the baseline a line of text reaches, as a share of the font size. */
    private const ASCENT = 0.8;

    /** @var float Height of the tint behind a line of text, as a share of the font size. */
    private const LINE_HEIGHT = 1.08;

    /**
     * Whether the tools this needs are there: Ghostscript and the page importer.
     *
     * @return bool
     */
    public static function is_available(): bool {
        global $CFG;

        return !empty($CFG->pathtogs)
            && is_executable($CFG->pathtogs)
            && class_exists('\assignfeedback_editpdf\pdf');
    }

    /**
     * Draw the marks on the document.
     *
     * When any of the marks is a written comment, every page gains a margin on
     * the right, and each comment is set there as a card joined to its pin by a
     * dotted line: the layout the student already knows from the web view.
     *
     * @param string $pdfbytes The submission PDF.
     * @param array[] $marks Each {page: int, startoffset: int, anchortext: string, marktype: string,
     *     color: string, number: int|null, text: string, author: string}, with page 1-based, and number
     *     and text set for a comment.
     * @return array {pdf: string, placed: int[]} The PDF with the marks on it (or as given when nothing
     *     could be drawn), and the numbers of the comments that made it onto a page.
     */
    public static function apply(string $pdfbytes, array $marks): array {
        $untouched = ['pdf' => $pdfbytes, 'placed' => []];
        if (!$marks || !self::is_available()) {
            return $untouched;
        }

        try {
            $dir = make_request_directory();
            $source = $dir . '/submission.pdf';
            file_put_contents($source, $pdfbytes);

            // The importer reads PDF 1.4 and below; Ghostscript rewrites anything newer.
            $compatible = \assignfeedback_editpdf\pdf::ensure_pdf_file_compatible($source);
            if (!$compatible) {
                return $untouched;
            }

            // First a plain copy, with nothing drawn, measured as well as the
            // original. The copy is the space the marks are drawn in: whatever
            // the importer does with a crop box, an odd page size or a page
            // stored turned, measuring the copy sees the same thing the drawing
            // does. But rewriting a PDF can lose the text behind a ligature (the
            // single glyph a font draws for "tt" or "Th"), and then a phrase
            // containing one is no longer there to be found. So the copy is used
            // where its text survived, and the original where it did not.
            $plain = $dir . '/plain.pdf';
            $sizes = self::copy_pages($compatible, $plain, [], [], false);
            $pageinfo = self::read_page_info($source);
            $characters = self::reconcile(self::read_characters($source), self::read_characters($plain), $pageinfo);

            $placed = self::place_marks($characters, $marks);
            if (!$placed) {
                return $untouched;
            }

            $numbers = [];
            foreach ($placed as $pagemarks) {
                foreach ($pagemarks as $mark) {
                    if ($mark['marktype'] === 'comment' && !empty($mark['number'])) {
                        $numbers[] = (int) $mark['number'];
                    }
                }
            }

            // With comments the pages are widened for their cards, and are left
            // the way their content was laid out: a margin added to a turned
            // page would end up along its top or bottom.
            $withcards = !empty($numbers);
            $rotations = $withcards ? [] : self::rotations_to_restore($pageinfo, $sizes);
            $marked = $dir . '/marked.pdf';
            self::copy_pages($plain, $marked, $placed, $rotations, $withcards);
            $result = (string) file_get_contents($marked);

            return $result !== '' ? ['pdf' => $result, 'placed' => $numbers] : $untouched;
        } catch (\Throwable $e) {
            debugging('Could not draw the marks on the submission PDF: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $untouched;
        }
    }

    /**
     * Copy every page of one PDF into another, drawing on them as they go.
     *
     * @param string $from Path of the PDF to copy.
     * @param string $to Path to write.
     * @param array[][] $placed Marks to draw, by 1-based page number.
     * @param int[] $rotations Degrees to display each page at, by 1-based page number.
     * @param bool $withcards Whether to add the margin for comment cards to every page.
     * @return float[][] Width and height of each page, in points, by 1-based page number.
     */
    private static function copy_pages(string $from, string $to, array $placed, array $rotations, bool $withcards): array {
        // One margin width for the whole document, taken from its first page, so
        // the cards line up from page to page even when the pages differ in size.
        $margin = 0.0;
        if ($withcards) {
            $probe = new page_importer();
            $probe->load_pdf($from);
            $probe->copy_next_page();
            $margin = max(150.0, min(230.0, $probe->copied_page_size(1)[0] * self::MARGIN_SHARE));
            $probe->Close();
        }

        $pdf = new page_importer();
        $pagecount = $pdf->load_pdf($from);
        $sizes = [];
        for ($page = 1; $page <= $pagecount; $page++) {
            $pdf->copy_next_page($rotations[$page] ?? 0, $margin);
            $sizes[$page] = $pdf->copied_page_size($page);

            [$width, $height] = $sizes[$page];
            if ($margin > 0) {
                self::draw_margin($pdf, $width, $height, $margin);
            }
            $comments = [];
            foreach ($placed[$page] ?? [] as $mark) {
                $pin = self::draw_mark($pdf, $mark);
                if ($pin) {
                    $comments[] = $mark + ['pin' => $pin];
                }
            }
            if ($margin > 0 && $comments) {
                self::draw_cards($pdf, $comments, $width, $height, $margin);
            }
        }
        $pdf->save_pdf($to);

        return $sizes;
    }

    /**
     * Shade the margin beside a page, so it reads as margin and not as part of the document.
     *
     * @param page_importer $pdf
     * @param float $width Width of the page itself.
     * @param float $height
     * @param float $margin
     */
    private static function draw_margin(page_importer $pdf, float $width, float $height, float $margin): void {
        $pdf->SetFillColor(247, 248, 250);
        $pdf->Rect($width, 0, $margin, $height, 'F');
        $pdf->Line($width, 0, $width, $height, ['width' => 0.5, 'dash' => 0, 'color' => [222, 226, 230]]);
    }

    /**
     * Set each comment on a page as a card in the margin, joined to its pin.
     *
     * A card starts level with its pin where it can. Where two pins are close
     * the cards stack, and where the last would run off the foot of the page the
     * whole stack is moved up to fit; a page with more comment than it has room
     * for gets smaller type. The dotted line is what keeps a card and its pin
     * together once they are no longer level.
     *
     * @param page_importer $pdf
     * @param array[] $comments Placed comment marks, each with its 'pin' {x, y, r}.
     * @param float $width Width of the page itself.
     * @param float $height
     * @param float $margin
     */
    private static function draw_cards(page_importer $pdf, array $comments, float $width, float $height, float $margin): void {
        usort($comments, fn(array $a, array $b): int => $a['pin']['y'] <=> $b['pin']['y']);

        $inset = 7.0;
        $pad = 6.0;
        $gap = 5.0;
        $cardx = $width + $inset;
        $cardwidth = $margin - ($inset * 2);
        $textwidth = $cardwidth - ($pad * 2);
        $headheight = 15.0;

        // Type size: the largest of these at which every card fits on the page.
        $heights = [];
        $fontsize = 8.0;
        foreach ([8.0, 7.0, 6.2, 5.5] as $fontsize) {
            $pdf->SetFont(self::CARD_FONT, '', $fontsize);
            $heights = [];
            foreach ($comments as $comment) {
                $heights[] = $headheight + $pdf->getStringHeight($textwidth, (string) $comment['text']) + $pad;
            }
            if (array_sum($heights) + ($gap * (count($comments) + 1)) <= $height) {
                break;
            }
        }

        // Level with the pin where possible, pushed down past the card above.
        $tops = [];
        $floor = $gap;
        foreach ($comments as $i => $comment) {
            $tops[$i] = max($floor, $comment['pin']['y'] - 8);
            $floor = $tops[$i] + $heights[$i] + $gap;
        }
        // Then pulled back up from the foot of the page.
        $ceiling = $height - $gap;
        for ($i = count($comments) - 1; $i >= 0; $i--) {
            $tops[$i] = max($gap, min($tops[$i], $ceiling - $heights[$i]));
            $ceiling = $tops[$i] - $gap;
        }

        foreach ($comments as $i => $comment) {
            $top = $tops[$i];
            $tint = self::rgb((string) ($comment['color'] ?? ''));

            // The leader, from the pin to the card's number.
            $pdf->Line(
                $comment['pin']['x'] + $comment['pin']['r'],
                $comment['pin']['y'],
                $cardx,
                $top + 9,
                ['width' => 0.6, 'dash' => '1.2,2.4', 'cap' => 'round', 'color' => [120, 150, 205]]
            );

            // The card.
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetLineStyle(['width' => 0.6, 'dash' => 0, 'color' => [222, 226, 230]]);
            $pdf->RoundedRect($cardx, $top, $cardwidth, $heights[$i], 4, '1111', 'DF');

            // Its number, as on the pin, and who wrote it.
            $pdf->SetFillColor(...$tint);
            $pdf->Circle($cardx + $pad + 5, $top + 9, 5, 0, 360, 'F');
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 6.5);
            $pdf->SetXY($cardx + $pad, $top + 4);
            $pdf->Cell(10, 10, (string) $comment['number'], 0, 0, 'C', false, '', 0, false, 'T', 'M');
            if (($comment['author'] ?? '') !== '') {
                $pdf->SetTextColor(108, 117, 125);
                $pdf->SetFont(self::CARD_FONT, '', 7);
                $pdf->SetXY($cardx + $pad + 13, $top + 4);
                $pdf->Cell($textwidth - 13, 10, (string) $comment['author'], 0, 0, 'L', false, '', 1, false, 'T', 'M');
            }

            // What it says.
            $pdf->SetTextColor(33, 37, 41);
            $pdf->SetFont(self::CARD_FONT, '', $fontsize);
            $pdf->MultiCell($textwidth, 0, (string) $comment['text'], 0, 'L', false, 1, $cardx + $pad, $top + $headheight);
        }
    }

    /**
     * Read each page's size and stored rotation from the original.
     *
     * @param string $path Path to the PDF.
     * @return array[] By 1-based page number: {width: float, height: float, rotate: int}.
     */
    private static function read_page_info(string $path): array {
        global $CFG;

        $command = escapeshellarg($CFG->pathtogs) . ' -q -dSAFER -dBATCH -dNOPAUSE -dNODISPLAY -dPDFINFO '
            . escapeshellarg($path) . ' 2>&1';
        exec($command, $output);

        return self::parse_page_info(implode("\n", $output));
    }

    /**
     * Read Ghostscript's page listing.
     *
     * @param string $listing The -dPDFINFO output.
     * @return array[] As read_page_info().
     */
    public static function parse_page_info(string $listing): array {
        $pages = [];
        foreach (preg_split('/\R/', $listing) as $line) {
            if (!preg_match('/^Page (\d+) MediaBox: \[\s*(-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+)\s*\]/', $line, $m)) {
                continue;
            }
            $rotate = preg_match('/Rotate = (-?\d+)/', $line, $r) ? ((((int) $r[1]) % 360) + 360) % 360 : 0;
            $pages[(int) $m[1]] = [
                'width' => abs((float) $m[4] - (float) $m[2]),
                'height' => abs((float) $m[5] - (float) $m[3]),
                'rotate' => $rotate,
            ];
        }

        return $pages;
    }

    /**
     * Which pages need their rotation put back after copying.
     *
     * The importer copies a turned page as its content was laid out, the turn
     * forgotten. Where that has happened the copy is the size of the page before
     * it was turned, and the turn is put back so the student sees the page the
     * way they submitted it, marks and all. If a copy ever comes out already
     * turned (its sides swapped), it is left alone rather than turned twice.
     *
     * @param array[] $original From read_page_info().
     * @param float[][] $copied Width and height of each copied page.
     * @return int[] Degrees by 1-based page number, for the pages that need it.
     */
    public static function rotations_to_restore(array $original, array $copied): array {
        $rotations = [];
        foreach ($original as $page => $info) {
            if (!$info['rotate'] || empty($copied[$page])) {
                continue;
            }
            [$width, $height] = $copied[$page];
            $sideways = $info['rotate'] % 180 !== 0;
            $alreadyturned = $sideways
                && abs($width - $info['height']) < 2 && abs($height - $info['width']) < 2
                && abs($info['width'] - $info['height']) >= 2;
            if (!$alreadyturned) {
                $rotations[$page] = $info['rotate'];
            }
        }

        return $rotations;
    }

    /**
     * Ask Ghostscript where every character of the document is.
     *
     * @param string $path Path to the PDF.
     * @return array[][] Per 1-based page, each character as {c: string, x0: float, x1: float, y: float, size: float},
     *     in points from the top left of the page, with y the baseline.
     */
    private static function read_characters(string $path): array {
        global $CFG;

        $out = $path . '.xml';
        $command = escapeshellarg($CFG->pathtogs)
            . ' -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=txtwrite -dTextFormat=0'
            . ' -sOutputFile=' . escapeshellarg($out) . ' ' . escapeshellarg($path) . ' 2>&1';
        exec($command, $output, $returncode);
        if ($returncode !== 0 || !is_readable($out)) {
            return [];
        }

        return self::parse_characters(file_get_contents($out));
    }

    /**
     * Read Ghostscript's character listing.
     *
     * Parsed line by line rather than as XML: the listing is not always well
     * formed (a stray control character in the text is enough), and one bad
     * character should not cost the student every mark in the document.
     *
     * @param string $listing The txtwrite output.
     * @return array[][] As read_characters().
     */
    public static function parse_characters(string $listing): array {
        $pages = [];
        $page = 0;
        $size = 10.0;
        foreach (preg_split('/\R/', $listing) as $line) {
            if (strpos($line, '<page') !== false) {
                $page++;
                $pages[$page] = [];
                continue;
            }
            if (preg_match('/<span [^>]*size="([\d.]+)"/', $line, $m)) {
                $size = (float) $m[1];
                continue;
            }
            if ($page && preg_match('/<char bbox="(-?[\d.]+) (-?[\d.]+) (-?[\d.]+) (-?[\d.]+)" c="(.*)"\/>/u', $line, $m)) {
                $pages[$page][] = [
                    'c' => html_entity_decode($m[5], ENT_QUOTES | ENT_XML1, 'UTF-8'),
                    'x0' => (float) $m[1],
                    'x1' => (float) $m[3],
                    'y' => (float) $m[2],
                    'size' => $size,
                ];
            }
        }

        return $pages;
    }

    /**
     * Choose, page by page, which measurement of the characters to trust.
     *
     * @param array[][] $original Characters measured on the PDF as submitted.
     * @param array[][] $copy Characters measured on the plain copy the marks are drawn on.
     * @param array[] $pageinfo From read_page_info().
     * @return array[][] Characters per page, positioned in the copy's space.
     */
    public static function reconcile(array $original, array $copy, array $pageinfo): array {
        $pages = [];
        foreach (array_unique(array_merge(array_keys($original), array_keys($copy))) as $page) {
            $fromoriginal = $original[$page] ?? [];
            $fromcopy = $copy[$page] ?? [];
            $text = fn(array $chars): string => self::fold(implode('', array_column($chars, 'c')));

            // The copy kept all its text, or the page is stored turned (where the
            // original cannot be measured letter by letter): use the copy.
            if (!$fromoriginal || $text($fromoriginal) === $text($fromcopy) || !empty($pageinfo[$page]['rotate'])) {
                $pages[$page] = $fromcopy ?: $fromoriginal;
                continue;
            }

            // The copy lost text. Use the original's characters, moved by however
            // far the copy's first character sits from the original's: nothing
            // for an ordinary page, the crop offset for a cropped one.
            $dx = 0.0;
            $dy = 0.0;
            if ($fromcopy && $fromcopy[0]['c'] === $fromoriginal[0]['c']) {
                $dx = $fromcopy[0]['x0'] - $fromoriginal[0]['x0'];
                $dy = $fromcopy[0]['y'] - $fromoriginal[0]['y'];
            }
            $pages[$page] = array_map(function (array $char) use ($dx, $dy): array {
                $char['x0'] += $dx;
                $char['x1'] += $dx;
                $char['y'] += $dy;
                return $char;
            }, $fromoriginal);
        }

        return $pages;
    }

    /**
     * Find each mark's phrase on its page.
     *
     * @param array[][] $pages From read_characters().
     * @param array[] $marks As apply().
     * @return array[][] Per page, the marks that were found, each with a 'lines' list of
     *     {x0, x1, y, size} rectangles, one per line of text the phrase runs over.
     */
    public static function place_marks(array $pages, array $marks): array {
        $placed = [];
        $index = [];
        foreach ($marks as $mark) {
            $page = (int) $mark['page'];
            if (empty($pages[$page])) {
                continue;
            }
            $index[$page] ??= self::index_page($pages[$page]);
            $found = self::locate($index[$page], (string) $mark['anchortext'], (int) $mark['startoffset']);
            if (!$found) {
                continue;
            }
            $mark['lines'] = self::lines($pages[$page], $found);
            $placed[$page][] = $mark;
        }

        return $placed;
    }

    /**
     * The page's text with the white space taken out, and where each remaining
     * character came from.
     *
     * The browser's text and Ghostscript's do not agree on white space: one
     * joins two lines with a space where the other joins them with nothing. With
     * it removed from both, the phrase is found whichever way the line broke.
     *
     * @param array[] $chars One page's characters.
     * @return array {text: string, map: int[]} with map from a position in text to an index in $chars.
     */
    private static function index_page(array $chars): array {
        $text = '';
        $map = [];
        foreach ($chars as $i => $char) {
            foreach (mb_str_split(self::fold($char['c'])) as $piece) {
                $text .= $piece;
                $map[] = $i;
            }
        }

        return ['text' => $text, 'map' => $map];
    }

    /**
     * Reduce text to what both sides can be compared on: no white space, no soft
     * hyphens, ligatures spelled out.
     *
     * @param string $text
     * @return string
     */
    private static function fold(string $text): string {
        $text = strtr($text, ["\u{FB00}" => 'ff', "\u{FB01}" => 'fi', "\u{FB02}" => 'fl', "\u{FB03}" => 'ffi',
            "\u{FB04}" => 'ffl', "\u{00AD}" => '']);

        return preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', $text) ?? '';
    }

    /**
     * Find a phrase on a page.
     *
     * A phrase that occurs more than once is told apart by where the marker
     * placed it: the occurrence nearest the stored offset wins.
     *
     * @param array $index From index_page().
     * @param string $anchor The marked phrase.
     * @param int $startoffset Where in the page's text the marker's phrase began.
     * @return int[] Indexes of the characters the phrase covers, or none when it is not on the page.
     */
    private static function locate(array $index, string $anchor, int $startoffset): array {
        $needle = self::fold($anchor);
        if ($needle === '') {
            return [];
        }
        $length = mb_strlen($needle);

        $best = null;
        $bestdistance = PHP_INT_MAX;
        $from = 0;
        while (($at = mb_strpos($index['text'], $needle, $from)) !== false) {
            $distance = abs($index['map'][$at] - $startoffset);
            if ($distance < $bestdistance) {
                $best = $at;
                $bestdistance = $distance;
            }
            $from = $at + 1;
        }
        if ($best === null) {
            return self::locate_loosely($index, $needle, $startoffset);
        }

        return array_values(array_unique(array_slice($index['map'], $best, $length)));
    }

    /**
     * Find a phrase the page has only most of.
     *
     * The last resort, for a page whose text has lost the letters behind its
     * ligatures: "attention" may be all there is of "attention" as "aenon". The
     * phrase is matched letter by letter, letting a few of its letters be missing
     * from the page, and never the other way round: nothing on the page is
     * skipped, so a match is always one unbroken run of the page's text.
     *
     * @param array $index From index_page().
     * @param string $needle The folded phrase.
     * @param int $startoffset Where in the page's text the marker's phrase began.
     * @return int[] Indexes of the characters the phrase covers, or none.
     */
    private static function locate_loosely(array $index, string $needle, int $startoffset): array {
        $letters = mb_str_split($needle);
        $count = count($letters);
        if ($count < 6) {
            // Too short to tell a damaged match from a coincidence.
            return [];
        }
        $page = mb_str_split($index['text']);
        // A word like "attention" loses four of its nine letters to two ligatures,
        // so the allowance has to be generous to be of any use.
        $allowed = max(2, (int) floor($count * 0.3));

        $best = null;
        $bestscore = PHP_INT_MAX;
        foreach ($page as $start => $unused) {
            $missing = 0;
            $at = $start;
            $matched = 0;
            foreach ($letters as $letter) {
                if (isset($page[$at]) && $page[$at] === $letter) {
                    $at++;
                    $matched++;
                } else if (++$missing > $allowed) {
                    break;
                }
            }
            if ($missing > $allowed || $matched < 4) {
                continue;
            }
            // Fewest letters missing first, then nearest to where the marker put it.
            $score = ($missing * 100000) + abs($index['map'][$start] - $startoffset);
            if ($score < $bestscore) {
                $best = [$start, $matched];
                $bestscore = $score;
            }
        }
        if ($best === null) {
            return [];
        }

        return array_values(array_unique(array_slice($index['map'], $best[0], $best[1])));
    }

    /**
     * Turn the characters of a phrase into one rectangle per line of text.
     *
     * @param array[] $chars One page's characters.
     * @param int[] $found Indexes of the characters in the phrase.
     * @return array[] Each {x0, x1, y, size}.
     */
    private static function lines(array $chars, array $found): array {
        $lines = [];
        foreach ($found as $i) {
            $char = $chars[$i];
            $last = count($lines) - 1;
            if ($last >= 0 && abs($lines[$last]['y'] - $char['y']) < 1.5) {
                $lines[$last]['x0'] = min($lines[$last]['x0'], $char['x0']);
                $lines[$last]['x1'] = max($lines[$last]['x1'], $char['x1']);
                $lines[$last]['size'] = max($lines[$last]['size'], $char['size']);
            } else {
                $lines[] = ['x0' => $char['x0'], 'x1' => $char['x1'], 'y' => $char['y'], 'size' => $char['size']];
            }
        }

        return $lines;
    }

    /**
     * Draw one mark on the current page.
     *
     * The same vocabulary as the web view: a tint behind the phrase in the
     * mark's colour, then its sign at the start of the phrase: a numbered pin
     * for a comment, a tick, a cross or a question mark. A strike-out is a line
     * through the text and nothing else.
     *
     * @param page_importer $pdf
     * @param array $mark A placed mark, with its 'lines'.
     * @return array|null For a comment, where its pin was drawn: {x, y, r}. Null for any other mark.
     */
    private static function draw_mark(page_importer $pdf, array $mark): ?array {
        $type = (string) $mark['marktype'];
        $first = $mark['lines'][0];

        if ($type === 'strikethrough') {
            foreach ($mark['lines'] as $line) {
                $y = $line['y'] - ($line['size'] * 0.28);
                $pdf->Line($line['x0'], $y, $line['x1'], $y, ['width' => 1.1, 'dash' => 0, 'color' => self::TINTS['cross']]);
            }
            return null;
        }

        $tint = $type === 'comment' ? self::rgb((string) ($mark['color'] ?? '')) : (self::TINTS[$type] ?? self::TINTS['comment']);
        $pdf->SetAlpha($type === 'highlight' ? 0.4 : 0.22);
        $pdf->SetFillColor(...$tint);
        foreach ($mark['lines'] as $line) {
            $pdf->Rect(
                $line['x0'],
                $line['y'] - ($line['size'] * self::ASCENT),
                max(1.0, $line['x1'] - $line['x0']),
                $line['size'] * self::LINE_HEIGHT,
                'F'
            );
        }
        $pdf->SetAlpha(1);

        // The sign sits above the start of the phrase. It is sized to the type it
        // sits over: full size on ordinary text, smaller on small type, where a
        // full-sized pin would cover the words of the line above.
        $k = max(0.55, min(1.0, $first['size'] / 12));
        $x = $first['x0'];
        $y = $first['y'] - ($first['size'] * self::ASCENT) - (2.2 * $k);
        $stroke = ['width' => 1.2 * $k, 'cap' => 'round', 'join' => 'round', 'dash' => 0, 'color' => $tint];
        $pin = null;

        switch ($type) {
            case 'comment':
                $radius = 5.2 * $k;
                $pdf->SetFillColor(...$tint);
                $pdf->Circle($x, $y - (1.5 * $k), $radius, 0, 360, 'F');
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('helvetica', 'B', 6.5 * $k);
                $pdf->SetXY($x - $radius, $y - (1.5 * $k) - $radius);
                $pdf->Cell($radius * 2, $radius * 2, (string) ($mark['number'] ?? ''), 0, 0, 'C', false, '', 0, false, 'T', 'M');
                $pin = ['x' => $x, 'y' => $y - (1.5 * $k), 'r' => $radius];
                break;
            case 'tick':
                $pdf->Line($x - (2.4 * $k), $y - (1.2 * $k), $x - (0.4 * $k), $y + (0.9 * $k), $stroke);
                $pdf->Line($x - (0.4 * $k), $y + (0.9 * $k), $x + (3.4 * $k), $y - (3.6 * $k), $stroke);
                break;
            case 'cross':
                $pdf->Line($x - (2.2 * $k), $y - (3.4 * $k), $x + (2.2 * $k), $y + (0.9 * $k), $stroke);
                $pdf->Line($x + (2.2 * $k), $y - (3.4 * $k), $x - (2.2 * $k), $y + (0.9 * $k), $stroke);
                break;
            case 'query':
                $pdf->SetTextColor(...$tint);
                $pdf->SetFont('helvetica', 'B', 8 * $k);
                $pdf->SetXY($x - (3 * $k), $y - (5 * $k));
                $pdf->Cell(6 * $k, 6 * $k, '?', 0, 0, 'C', false, '', 0, false, 'T', 'M');
                break;
        }

        return $pin;
    }

    /**
     * A #rrggbb colour as RGB, or the comment colour when it is not one.
     *
     * @param string $hex
     * @return int[]
     */
    private static function rgb(string $hex): array {
        if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m)) {
            return self::TINTS['comment'];
        }

        return [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
    }
}
