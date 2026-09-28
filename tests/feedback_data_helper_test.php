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
 * Tests for feedback_data_helper::build_segment_comment_translations (Phase 2).
 *
 * The segment-comment list is an optional feature layered on two other plugins:
 * the local_unifiedgrader_segcomment table (frozen by WP-P1) and the local_nida
 * translation store. Tests that need either dependency skip gracefully when it is
 * absent, so the suite passes both with and without them installed.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\feedback_data_helper
 */
final class feedback_data_helper_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create an assignment module and return its context (instanceid is the cmid).
     *
     * @return \context_module
     */
    private function make_module_context(): \context_module {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id, 0, false, MUST_EXIST);
        return \context_module::instance($cm->id);
    }

    /**
     * Skip the current test unless the frozen segcomment table exists.
     */
    private function require_segcomment_table(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_unifiedgrader_segcomment')) {
            $this->markTestSkipped('local_unifiedgrader_segcomment table not present (WP-P1 schema not applied).');
        }
    }

    /**
     * Insert one segment-comment row for the given student/attempt.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $authorid Grader user ID.
     * @param string $anchortext Source phrase (plaintext).
     * @param string $commenttext Grader comment (HTML).
     * @param int $attempt Attempt number.
     * @param int $timecreated Creation time (controls display order).
     * @return int The new row ID.
     */
    private function insert_segcomment(
        int $cmid,
        int $userid,
        int $authorid,
        string $anchortext,
        string $commenttext,
        int $attempt,
        int $timecreated,
    ): int {
        global $DB;
        return (int) $DB->insert_record('local_unifiedgrader_segcomment', (object) [
            'cmid' => $cmid,
            'userid' => $userid,
            'authorid' => $authorid,
            'submissionid' => 0,
            'attemptnumber' => $attempt,
            'sourcetype' => 'onlinetext',
            'fileid' => 0,
            'segmentid' => 0,
            'startoffset' => 0,
            'endoffset' => \core_text::strlen($anchortext),
            'anchortext' => $anchortext,
            'commenttext' => $commenttext,
            'commentformat' => FORMAT_HTML,
            'timecreated' => $timecreated,
            'timemodified' => $timecreated,
        ]);
    }

    /**
     * English (and English-derived) viewers short-circuit before any DB access.
     */
    public function test_english_viewer_short_circuits(): void {
        $context = \context_system::instance();

        $result = feedback_data_helper::build_segment_comment_translations(123, 456, $context, 0, 'en');
        $this->assertFalse($result['hassegmentcomments']);
        $this->assertSame([], $result['comments']);

        // A regional English variant (e.g. en_us) short-circuits too.
        $result = feedback_data_helper::build_segment_comment_translations(123, 456, $context, 0, 'en_us');
        $this->assertFalse($result['hassegmentcomments']);
        $this->assertSame([], $result['comments']);
    }

    /**
     * A non-English viewer with no segment comments gets an empty structure.
     */
    public function test_no_rows_returns_empty(): void {
        $this->require_segcomment_table();

        $context = $this->make_module_context();
        $student = $this->getDataGenerator()->create_user();

        $result = feedback_data_helper::build_segment_comment_translations(
            $context->instanceid,
            (int) $student->id,
            $context,
            0,
            'fr',
        );
        $this->assertFalse($result['hassegmentcomments']);
        $this->assertSame([], $result['comments']);
    }

    /**
     * A non-English viewer sees each comment resolved from the nida store (with the
     * English original as fallback) alongside its s()-escaped anchor phrase.
     */
    public function test_resolves_translation_and_anchor(): void {
        $this->require_segcomment_table();
        if (!class_exists('\local_nida\local\store') || !class_exists('\local_nida\local\hasher')) {
            $this->markTestSkipped('local_nida is not installed.');
        }

        $context = $this->make_module_context();
        $cmid = (int) $context->instanceid;
        $student = $this->getDataGenerator()->create_user();
        $grader = $this->getDataGenerator()->create_user();

        $now = time();
        // Comment that WILL have a French translation. Anchor holds characters s()
        // must escape, to prove the anchor is escaped and not rendered raw.
        $translatedcomment = '<p>Well argued, but cite a source.</p>';
        $anchor = 'Augustine <the bishop> wrote';
        $seg1id = $this->insert_segcomment(
            $cmid,
            (int) $student->id,
            (int) $grader->id,
            $anchor,
            $translatedcomment,
            0,
            $now,
        );

        // Comment with NO translation → English-original fallback path.
        $untranslatedcomment = '<p>Reference needed here.</p>';
        $this->insert_segcomment(
            $cmid,
            (int) $student->id,
            (int) $grader->id,
            'a later phrase',
            $untranslatedcomment,
            0,
            $now + 1,
        );

        // Publish a French translation for the first comment, keyed by its hash.
        $store = new \local_nida\local\store();
        $segment = $store->upsert_segment(
            $context->id,
            'local_unifiedgrader',
            'segcomment',
            $seg1id,
            'commenttext',
            $translatedcomment,
        );
        $store->save_translation(
            $segment->id,
            'fr',
            '<p>Bien argumenté, mais citez une source.</p>',
            \local_nida\local\store::STATUS_MACHINE,
            'test-model',
            $segment->sourcehash,
        );

        $result = feedback_data_helper::build_segment_comment_translations(
            $cmid,
            (int) $student->id,
            $context,
            0,
            'fr',
        );

        $this->assertTrue($result['hassegmentcomments']);
        $this->assertCount(2, $result['comments']);

        // Ordered by timecreated ASC: the translated comment comes first.
        $first = $result['comments'][0];
        $this->assertTrue($first['hastranslation']);
        $this->assertStringContainsString('Bien argumenté', $first['displayhtml']);
        // Anchor is s()-escaped; angle brackets appear as entities, never raw markup.
        $this->assertSame(s($anchor), $first['anchortext']);
        $this->assertStringContainsString('&lt;the bishop&gt;', $first['anchortext']);
        $this->assertStringNotContainsString('<the bishop>', $first['anchortext']);

        // The untranslated comment falls back to the grader's English original.
        $second = $result['comments'][1];
        $this->assertFalse($second['hastranslation']);
        $this->assertStringContainsString('Reference needed here', $second['displayhtml']);
        $this->assertSame(s('a later phrase'), $second['anchortext']);
    }

    /**
     * The shared grading keys carry every field the feedback templates read.
     */
    public function test_grading_template_data_covers_every_template_key(): void {
        $parsed = feedback_data_helper::parse_grading_data([], \context_system::instance());
        $data = feedback_data_helper::grading_template_data($parsed);

        foreach (
            [
            'hasrubric', 'rubriccriteria', 'rubrictotal',
            'hasguide', 'guidecriteria', 'guidetotal', 'guidemaxtotal',
            'hasadvancedgrading', 'gradingmethodname',
            ] as $key
        ) {
            $this->assertArrayHasKey($key, $data);
        }
    }

    /**
     * Every feedback view must merge the grading keys into its template context.
     *
     * view_feedback.php builds a separate context per activity type, and in
     * v2.12.0 the BigBlueButton branch parsed its grading data and then failed
     * to pass it on — so a student saw the overall feedback with no rubric or
     * marking guide beside it, while the same criteria appeared in the PDF. The
     * surrounding lines are identical in all four branches, which is what hid
     * it. This asserts the wiring rather than the rendering, because the branch
     * that broke is in a script no unit test can execute.
     */
    public function test_every_feedback_view_merges_the_grading_keys(): void {
        global $CFG;

        $src = file_get_contents($CFG->dirroot . '/local/unifiedgrader/view_feedback.php');
        $this->assertNotFalse($src);

        $renders = preg_match_all(
            "~render_from_template\('local_unifiedgrader/feedback_view~",
            $src
        );
        $merges = preg_match_all('~\$templatedata \+= .*grading_template_data\(~', $src);

        $this->assertGreaterThan(0, $renders, 'Expected feedback views to render');
        $this->assertSame(
            $renders,
            $merges,
            'Each feedback view must merge grading_template_data() into its context'
        );

        // In v2.12.1 the quiz branch merged a $gradinginfo it never assigned,
        // and the typed helper threw on the null for every student.
        $parses = preg_match_all('~\$gradinginfo = feedback_data_helper::parse_grading_data\(~', $src);
        $this->assertSame(
            $renders,
            $parses,
            'Each feedback view must parse its own grading data before merging it'
        );
    }
}
