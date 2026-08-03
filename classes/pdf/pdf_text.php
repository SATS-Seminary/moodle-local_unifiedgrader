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
 * Text preparation for the PDF renderers.
 *
 * When filter_nida is active, formatted teacher HTML reaching the PDF layer
 * carries the filter's per-viewer translation markup:
 *
 *   <div class="nida-block">
 *     <div class="nida-tx">translated content</div>
 *     <div class="nida-orig">original content</div>
 *     <span class="nida-badge" title="Show original">translated from X</span>
 *   </div>
 *
 * That markup is interactive web chrome — CSS hides the original and JS
 * toggles it — and has no meaning in a static PDF: rendering it prints both
 * languages plus the badge caption, and escaping it prints naked HTML. Every
 * string entering a PDF must therefore be flattened first: keep ONLY the
 * translated content and drop the original + badge.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za) (https://www.sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_text {
    /**
     * Flatten filter_nida translation markup to its translated content only,
     * preserving the surrounding (and inner) HTML for renderers that accept
     * HTML. Non-nida input is returned unchanged.
     *
     * @param string $html Formatted HTML (possibly carrying nida markup).
     * @return string The HTML with nida blocks reduced to their translation.
     */
    public static function flatten(string $html): string {
        if ($html === '' || stripos($html, 'nida-') === false) {
            return $html;
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="nida-pdf-root">' . $html . '</div>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        if (!$loaded) {
            // Unparseable input: fail safe with the original string.
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $bytoken = static function (string $class): string {
            return '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
        };

        // Drop the hidden original and the interactive badge outright.
        foreach (['nida-orig', 'nida-badge'] as $class) {
            foreach (iterator_to_array($xpath->query($bytoken($class))) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
        // Unwrap the translation and its block container, keeping their contents.
        foreach (['nida-tx', 'nida-block'] as $class) {
            foreach (iterator_to_array($xpath->query($bytoken($class))) as $node) {
                self::unwrap($node);
            }
        }

        $root = $doc->getElementById('nida-pdf-root');
        if (!$root) {
            return $html;
        }
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    /**
     * Reduce formatted HTML to plain text ready for a PDF table cell: nida
     * markup flattened to the translation, tags stripped (block boundaries
     * become spaces so words never glue together), entities decoded and
     * whitespace collapsed. Callers escape the result themselves.
     *
     * @param string $html Formatted HTML (possibly carrying nida markup).
     * @return string Plain text.
     */
    public static function plain(string $html): string {
        if ($html === '') {
            return '';
        }
        $flat = self::flatten($html);
        // Drop <script>/<style> blocks INCLUDING their contents first. strip_tags()
        // removes the tags but keeps the text between them, so a plugin that ships
        // an inline helper alongside its markup would print raw JavaScript into the
        // PDF — as the plagiarism panel did with Copyleaks' copy-to-clipboard
        // function, which appeared verbatim in the student's feedback.
        $flat = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', ' ', $flat);
        // An unclosed <script> would otherwise leak everything after it.
        $flat = preg_replace('#<(script|style)\b[^>]*>.*$#is', ' ', $flat);
        // Space out block/line boundaries before stripping tags.
        $flat = preg_replace('#</(p|div|li|h[1-6]|tr)\s*>#i', ' ', $flat);
        $flat = preg_replace('#<br\s*/?\s*>#i', ' ', $flat);
        $text = html_entity_decode(strip_tags($flat), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Replace a node with its own children (in place).
     *
     * @param \DOMNode $node The node to unwrap.
     */
    private static function unwrap(\DOMNode $node): void {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }
}
