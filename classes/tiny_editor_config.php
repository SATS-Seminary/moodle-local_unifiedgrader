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
 * TinyMCE configuration for editors the grader creates in JavaScript.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

/**
 * Builds the configuration editor_tiny's setupForTarget() expects, without
 * attaching an editor to anything.
 *
 * use_editor() can only set up a textarea that is already in the page. The
 * per-question comment editors of a quiz are built by the marking panel after
 * each student loads, one per manually marked question, so the page ships this
 * configuration once and the panel clones it, giving each copy the question's
 * own draft area.
 */
class tiny_editor_config extends \editor_tiny\editor {
    /**
     * Get the editor configuration.
     *
     * Mirrors the configuration block of \editor_tiny\editor::use_editor(),
     * which is identical in Moodle 5.0 and 5.3.
     *
     * @param \context $context Context the editor's files and plugins belong to.
     * @param array $options Editor options, as passed to use_editor().
     * @param array $fpoptions File picker options, as passed to use_editor().
     * @return array
     */
    public function get_config(\context $context, array $options, array $fpoptions): array {
        global $PAGE;

        self::set_default_configuration($this->manager);

        $siteconfig = get_config('editor_tiny');
        $config = (object) [
            'css' => $PAGE->theme->editor_css_url()->out(false),
            'context' => $context->id,
            'filepicker' => (object) $fpoptions,
            'draftitemid' => 0,
            'currentLanguage' => current_language(),
            'branding' => property_exists($siteconfig, 'branding') ? !empty($siteconfig->branding) : true,
            'extended_valid_elements' => $siteconfig->extended_valid_elements ?? 'script[*],p[*],i[*]',
            'language' => [
                'currentlang' => current_language(),
                'installed' => get_string_manager()->get_list_of_translations(true),
                'available' => get_string_manager()->get_list_of_languages(),
            ],
            'placeholderSelectors' => [],
            'plugins' => $this->manager->get_plugin_configuration($context, $options, $fpoptions, $this),
        ];

        if (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) {
            $config->placeholderSelectors = ['.behat-tinymce-placeholder'];
        }

        return convert_to_array($config);
    }
}
