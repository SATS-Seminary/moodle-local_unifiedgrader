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
 * Tests for the PDF nida-markup flattener.
 *
 * The fixtures mirror filter_nida's real output: a nida-block div holding the
 * translated content (nida-tx), the hidden original (nida-orig) and the
 * interactive badge (nida-badge). A student's feedback PDF must carry ONLY the
 * translated content — never the escaped markup, the duplicated original, or
 * the badge caption (the regression this guards against).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za) (https://www.sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_unifiedgrader\pdf\pdf_text
 */
final class pdf_text_test extends \basic_testcase {
    /**
     * Build a filter_nida block exactly as the filter emits it.
     *
     * @param string $tx Translated inner HTML.
     * @param string $orig Original inner HTML.
     * @return string The nida-block markup.
     */
    private function nida_block(string $tx, string $orig): string {
        return '<div class="nida-block"><div class="nida-tx">' . $tx . '</div>'
            . '<div class="nida-orig">' . $orig . '</div>'
            . '<span class="nida-badge" title="Show original">translated from Anglais</span></div>';
    }

    /**
     * Non-nida input passes through both helpers untouched (flatten) / as text (plain).
     */
    public function test_passthrough_without_nida_markup(): void {
        $html = '<p>Broadly <strong>correct</strong>.</p>';
        $this->assertSame($html, pdf_text::flatten($html));
        $this->assertSame('Broadly correct.', pdf_text::plain($html));
    }

    /**
     * A nida block flattens to its translated content only: the original and
     * the badge caption are gone.
     */
    public function test_flatten_keeps_translation_only(): void {
        $flat = pdf_text::flatten($this->nida_block('Largement correct', 'Broadly correct'));

        $this->assertStringContainsString('Largement correct', $flat);
        $this->assertStringNotContainsString('Broadly correct', $flat);
        $this->assertStringNotContainsString('translated from', $flat);
        $this->assertStringNotContainsString('nida-', $flat);
    }

    /**
     * plain() yields escaped-ready text for a table cell — the screenshot
     * regression: rubric levels must never show naked markup or the original.
     */
    public function test_plain_rubric_level(): void {
        $text = pdf_text::plain($this->nida_block('Largement correct', 'Broadly correct'));
        $this->assertSame('Largement correct', $text);
    }

    /**
     * Multi-paragraph translated feedback keeps its inner HTML through
     * flatten() (the overall-feedback section renders HTML), original dropped.
     */
    public function test_flatten_preserves_inner_html(): void {
        $tx = '<p>Un excellent <em>essai</em>.</p><p>Continuez ainsi.</p>';
        $flat = pdf_text::flatten($this->nida_block($tx, '<p>An excellent essay.</p>'));

        $this->assertStringContainsString('<em>essai</em>', $flat);
        $this->assertStringContainsString('Continuez ainsi.', $flat);
        $this->assertStringNotContainsString('excellent essay', $flat);
    }

    /**
     * Several blocks in one field all flatten; surrounding plain HTML is kept.
     */
    public function test_flatten_multiple_blocks(): void {
        $html = '<p>Intro.</p>'
            . $this->nida_block('Premier', 'First')
            . $this->nida_block('Second bloc', 'Second block');
        $flat = pdf_text::flatten($html);

        $this->assertStringContainsString('<p>Intro.</p>', $flat);
        $this->assertStringContainsString('Premier', $flat);
        $this->assertStringContainsString('Second bloc', $flat);
        $this->assertStringNotContainsString('First', $flat);
        $this->assertStringNotContainsString('nida-', $flat);
    }

    /**
     * plain() spaces block boundaries (no glued words), decodes entities and
     * collapses whitespace; multibyte content survives.
     */
    public function test_plain_spacing_entities_and_multibyte(): void {
        $this->assertSame('one two', pdf_text::plain('<p>one</p><p>two</p>'));
        $this->assertSame('R&D — coûts', pdf_text::plain('R&amp;D &mdash; co&ucirc;ts'));
        $text = pdf_text::plain($this->nida_block('Le terme « ὑπόστασις » désigne', 'The term'));
        $this->assertSame('Le terme « ὑπόστασις » désigne', $text);
    }

    /**
     * Empty input stays empty.
     */
    public function test_empty_input(): void {
        $this->assertSame('', pdf_text::flatten(''));
        $this->assertSame('', pdf_text::plain(''));
    }

    /**
     * Script and style contents must not survive into the PDF.
     *
     * strip_tags() removes the tags but keeps the text between them, so a
     * plagiarism plugin that ships an inline helper next to its scores printed the
     * raw JavaScript into the student's feedback PDF. The scores around it still
     * have to come through.
     */
    public function test_plain_drops_script_and_style_contents(): void {
        $html = 'AI content score <span>0%</span> Similarity Score <b>18%</b>'
            . '<script type="text/javascript">function cl_copy_to_clipboard26631()'
            . '{var copyText = document.getElementById(\'clsreporturlinput_5df\');'
            . 'navigator.clipboard.writeText(copyText.value);}</script>'
            . '<style>.cl-badge { color: red; }</style> Report';

        $text = pdf_text::plain($html);

        $this->assertStringNotContainsString('cl_copy_to_clipboard', $text);
        $this->assertStringNotContainsString('getElementById', $text);
        $this->assertStringNotContainsString('navigator.clipboard', $text);
        $this->assertStringNotContainsString('color: red', $text);
        // The information the teacher and student actually want is preserved.
        $this->assertStringContainsString('AI content score', $text);
        $this->assertStringContainsString('18%', $text);
        $this->assertStringContainsString('Report', $text);
    }

    /**
     * An unclosed script tag must not leak everything that follows it.
     */
    public function test_plain_contains_unclosed_script(): void {
        $this->assertSame('Score 5%', pdf_text::plain('Score 5% <script>var a = 1; tail'));
    }
}
