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
 * Privacy API implementation for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider declaring what personal data the plugin stores.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_unifiedgrader_notes', [
            'cmid' => 'privacy:metadata:notes:cmid',
            'userid' => 'privacy:metadata:notes:userid',
            'authorid' => 'privacy:metadata:notes:authorid',
            'content' => 'privacy:metadata:notes:content',
        ], 'privacy:metadata:notes');

        $collection->add_database_table('local_unifiedgrader_comments', [
            'userid' => 'privacy:metadata:comments:userid',
            'content' => 'privacy:metadata:comments:content',
        ], 'privacy:metadata:comments');

        $collection->add_database_table('local_unifiedgrader_clib', [
            'userid' => 'privacy:metadata:clib:userid',
            'coursecode' => 'privacy:metadata:clib:coursecode',
            'content' => 'privacy:metadata:clib:content',
        ], 'privacy:metadata:clib');

        $collection->add_database_table('local_unifiedgrader_cltag', [
            'userid' => 'privacy:metadata:cltag:userid',
            'name' => 'privacy:metadata:cltag:name',
        ], 'privacy:metadata:cltag');

        $collection->add_database_table('local_unifiedgrader_annot', [
            'cmid' => 'privacy:metadata:annotations:cmid',
            'userid' => 'privacy:metadata:annotations:userid',
            'authorid' => 'privacy:metadata:annotations:authorid',
            'annotationdata' => 'privacy:metadata:annotations:data',
        ], 'privacy:metadata:annotations');

        $collection->add_database_table('local_unifiedgrader_prefs', [
            'userid' => 'privacy:metadata:preferences:userid',
            'preferences' => 'privacy:metadata:preferences:data',
        ], 'privacy:metadata:preferences');

        $collection->add_database_table('local_unifiedgrader_penalty', [
            'userid' => 'privacy:metadata:penalty:userid',
            'authorid' => 'privacy:metadata:penalty:authorid',
            'category' => 'privacy:metadata:penalty:category',
            'label' => 'privacy:metadata:penalty:label',
            'percentage' => 'privacy:metadata:penalty:percentage',
        ], 'privacy:metadata:penalty');

        $collection->add_database_table('local_unifiedgrader_fext', [
            'userid' => 'privacy:metadata:fext:userid',
            'authorid' => 'privacy:metadata:fext:authorid',
            'extensionduedate' => 'privacy:metadata:fext:extensionduedate',
        ], 'privacy:metadata:fext');

        $collection->add_database_table('local_unifiedgrader_qfb', [
            'userid' => 'privacy:metadata:qfb:userid',
            'grader' => 'privacy:metadata:qfb:grader',
            'feedback' => 'privacy:metadata:qfb:feedback',
            'attemptnumber' => 'privacy:metadata:qfb:attemptnumber',
        ], 'privacy:metadata:qfb');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Notes where user is the subject or the author.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {local_unifiedgrader_notes} n
                  JOIN {course_modules} cm ON cm.id = n.cmid
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE n.userid = :userid1 OR n.authorid = :userid2";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid,
        ]);

        // Annotations where user is the subject or the author.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {local_unifiedgrader_annot} a
                  JOIN {course_modules} cm ON cm.id = a.cmid
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE a.userid = :userid1 OR a.authorid = :userid2";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid,
        ]);

        // Penalties where user is the subject or the author.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {local_unifiedgrader_penalty} p
                  JOIN {course_modules} cm ON cm.id = p.cmid
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE p.userid = :userid1 OR p.authorid = :userid2";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid,
        ]);

        // Forum extensions where user is the subject or the author.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {local_unifiedgrader_fext} fe
                  JOIN {course_modules} cm ON cm.id = fe.cmid
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE fe.userid = :userid1 OR fe.authorid = :userid2";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid,
        ]);

        // Per-attempt quiz feedback where user is the subject or the grader.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {local_unifiedgrader_qfb} qfb
                  JOIN {course_modules} cm ON cm.id = qfb.cmid
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE qfb.userid = :userid1 OR qfb.grader = :userid2";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid1' => $userid,
            'userid2' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $sql = "SELECT DISTINCT n.userid
                  FROM {local_unifiedgrader_notes} n
                  JOIN {course_modules} cm ON cm.id = n.cmid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);

        $sql = "SELECT DISTINCT n.authorid
                  FROM {local_unifiedgrader_notes} n
                  JOIN {course_modules} cm ON cm.id = n.cmid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('authorid', $sql, ['cmid' => $context->instanceid]);

        // Annotations: subjects and authors.
        $sql = "SELECT DISTINCT a.userid
                  FROM {local_unifiedgrader_annot} a
                  JOIN {course_modules} cm ON cm.id = a.cmid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);

        $sql = "SELECT DISTINCT a.authorid
                  FROM {local_unifiedgrader_annot} a
                  JOIN {course_modules} cm ON cm.id = a.cmid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('authorid', $sql, ['cmid' => $context->instanceid]);

        // Penalties: subjects and authors.
        $sql = "SELECT DISTINCT p.userid
                  FROM {local_unifiedgrader_penalty} p
                  JOIN {course_modules} cm ON cm.id = p.cmid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);

        $sql = "SELECT DISTINCT p.authorid
                  FROM {local_unifiedgrader_penalty} p
                  JOIN {course_modules} cm ON cm.id = p.cmid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('authorid', $sql, ['cmid' => $context->instanceid]);

        // Forum extensions: subjects and authors.
        $sql = "SELECT DISTINCT fe.userid
                  FROM {local_unifiedgrader_fext} fe
                  JOIN {course_modules} cm ON cm.id = fe.cmid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);

        $sql = "SELECT DISTINCT fe.authorid
                  FROM {local_unifiedgrader_fext} fe
                  JOIN {course_modules} cm ON cm.id = fe.cmid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('authorid', $sql, ['cmid' => $context->instanceid]);

        // Per-attempt quiz feedback: subjects and graders.
        $sql = "SELECT DISTINCT qfb.userid
                  FROM {local_unifiedgrader_qfb} qfb
                  JOIN {course_modules} cm ON cm.id = qfb.cmid
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);

        $sql = "SELECT DISTINCT qfb.grader
                  FROM {local_unifiedgrader_qfb} qfb
                  JOIN {course_modules} cm ON cm.id = qfb.cmid
                 WHERE cm.id = :cmid AND qfb.grader > 0";
        $userlist->add_from_sql('grader', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Export personal data for the given approved_contextlist.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // Export notes about this user.
            $notes = $DB->get_records('local_unifiedgrader_notes', [
                'cmid' => $cm->id,
                'userid' => $userid,
            ]);

            if ($notes) {
                $exportdata = array_map(function($note) {
                    return [
                        'content' => $note->content,
                        'timecreated' => \core_privacy\local\request\transform::datetime($note->timecreated),
                    ];
                }, $notes);

                writer::with_context($context)->export_data(
                    [get_string('notes', 'local_unifiedgrader')],
                    (object) ['notes' => array_values($exportdata)],
                );
            }

            // Export notes authored by this user.
            $authored = $DB->get_records('local_unifiedgrader_notes', [
                'cmid' => $cm->id,
                'authorid' => $userid,
            ]);

            if ($authored) {
                $exportdata = array_map(function($note) {
                    return [
                        'content' => $note->content,
                        'timecreated' => \core_privacy\local\request\transform::datetime($note->timecreated),
                    ];
                }, $authored);

                writer::with_context($context)->export_data(
                    [get_string('notes', 'local_unifiedgrader'), 'authored'],
                    (object) ['authored_notes' => array_values($exportdata)],
                );
            }

            // Export annotations about this user.
            $annotations = $DB->get_records('local_unifiedgrader_annot', [
                'cmid' => $cm->id,
                'userid' => $userid,
            ]);

            if ($annotations) {
                $exportdata = array_map(function($annot) {
                    return [
                        'fileid' => (int) $annot->fileid,
                        'pagenum' => (int) $annot->pagenum,
                        'annotationdata' => $annot->annotationdata,
                        'timecreated' => \core_privacy\local\request\transform::datetime($annot->timecreated),
                    ];
                }, $annotations);

                writer::with_context($context)->export_data(
                    [get_string('annotations', 'local_unifiedgrader')],
                    (object) ['annotations' => array_values($exportdata)],
                );
            }

            // Export penalties applied to this user.
            $penalties = $DB->get_records('local_unifiedgrader_penalty', [
                'cmid' => $cm->id,
                'userid' => $userid,
            ]);

            if ($penalties) {
                $exportdata = array_map(function($penalty) {
                    return [
                        'category' => $penalty->category,
                        'label' => $penalty->label,
                        'percentage' => (int) $penalty->percentage,
                        'timecreated' => \core_privacy\local\request\transform::datetime($penalty->timecreated),
                    ];
                }, $penalties);

                writer::with_context($context)->export_data(
                    [get_string('penalties', 'local_unifiedgrader')],
                    (object) ['penalties' => array_values($exportdata)],
                );
            }

            // Export forum extensions for this user.
            $fexts = $DB->get_records('local_unifiedgrader_fext', [
                'cmid' => $cm->id,
                'userid' => $userid,
            ]);

            if ($fexts) {
                $exportdata = array_map(function($fext) {
                    return [
                        'extensionduedate' => \core_privacy\local\request\transform::datetime(
                            $fext->extensionduedate
                        ),
                        'timecreated' => \core_privacy\local\request\transform::datetime($fext->timecreated),
                    ];
                }, $fexts);

                writer::with_context($context)->export_data(
                    [get_string('privacy_forum_extensions', 'local_unifiedgrader')],
                    (object) ['forum_extensions' => array_values($exportdata)],
                );
            }

            // Export per-attempt quiz feedback for this user.
            $qfbs = $DB->get_records('local_unifiedgrader_qfb', [
                'cmid' => $cm->id,
                'userid' => $userid,
            ]);

            if ($qfbs) {
                $exportdata = array_map(function($qfb) {
                    return [
                        'attemptnumber' => (int) $qfb->attemptnumber,
                        'feedback' => $qfb->feedback,
                        'feedbackformat' => (int) $qfb->feedbackformat,
                        'timemodified' => \core_privacy\local\request\transform::datetime($qfb->timemodified),
                    ];
                }, $qfbs);

                writer::with_context($context)->export_data(
                    [get_string('privacy_quiz_feedback', 'local_unifiedgrader')],
                    (object) ['quiz_feedback' => array_values($exportdata)],
                );
            }
        }

        // Export comment library (user-level, not context-specific).
        $comments = $DB->get_records('local_unifiedgrader_comments', ['userid' => $userid]);
        if ($comments) {
            $exportdata = array_map(function($comment) {
                return [
                    'content' => $comment->content,
                    'timecreated' => \core_privacy\local\request\transform::datetime($comment->timecreated),
                ];
            }, $comments);

            writer::with_context(\context_system::instance())->export_data(
                [get_string('commentlibrary', 'local_unifiedgrader')],
                (object) ['comments' => array_values($exportdata)],
            );
        }

        // Export comment library v2 (clib).
        $clibitems = $DB->get_records('local_unifiedgrader_clib', ['userid' => $userid]);
        if ($clibitems) {
            $exportdata = array_map(function($item) {
                return [
                    'coursecode' => $item->coursecode,
                    'content' => $item->content,
                    'shared' => (int) $item->shared,
                    'timecreated' => \core_privacy\local\request\transform::datetime($item->timecreated),
                ];
            }, $clibitems);

            writer::with_context(\context_system::instance())->export_data(
                [get_string('clib_title', 'local_unifiedgrader')],
                (object) ['comments' => array_values($exportdata)],
            );
        }

        // Export comment library v2 tags.
        $tags = $DB->get_records('local_unifiedgrader_cltag', ['userid' => $userid]);
        if ($tags) {
            $exportdata = array_map(function($tag) {
                return [
                    'name' => $tag->name,
                    'timecreated' => \core_privacy\local\request\transform::datetime($tag->timecreated),
                ];
            }, $tags);

            writer::with_context(\context_system::instance())->export_data(
                [get_string('clib_title', 'local_unifiedgrader'), get_string('clib_tags', 'local_unifiedgrader')],
                (object) ['tags' => array_values($exportdata)],
            );
        }

        // Export preferences.
        $prefs = $DB->get_record('local_unifiedgrader_prefs', ['userid' => $userid]);
        if ($prefs) {
            writer::with_context(\context_system::instance())->export_data(
                [get_string('pluginname', 'local_unifiedgrader'), 'preferences'],
                (object) ['preferences' => $prefs->preferences],
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $DB->delete_records('local_unifiedgrader_notes', ['cmid' => $context->instanceid]);
        $DB->delete_records('local_unifiedgrader_annot', ['cmid' => $context->instanceid]);
        $DB->delete_records('local_unifiedgrader_penalty', ['cmid' => $context->instanceid]);
        $DB->delete_records('local_unifiedgrader_fext', ['cmid' => $context->instanceid]);
        $DB->delete_records('local_unifiedgrader_qfb', ['cmid' => $context->instanceid]);
    }

    /**
     * Delete all data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $DB->delete_records('local_unifiedgrader_notes', [
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ]);
            $DB->delete_records('local_unifiedgrader_notes', [
                'cmid' => $context->instanceid,
                'authorid' => $userid,
            ]);
            $DB->delete_records('local_unifiedgrader_annot', [
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ]);
            $DB->delete_records('local_unifiedgrader_penalty', [
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ]);
            $DB->delete_records('local_unifiedgrader_penalty', [
                'cmid' => $context->instanceid,
                'authorid' => $userid,
            ]);
            $DB->delete_records('local_unifiedgrader_fext', [
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ]);
            $DB->delete_records('local_unifiedgrader_fext', [
                'cmid' => $context->instanceid,
                'authorid' => $userid,
            ]);
            $DB->delete_records('local_unifiedgrader_qfb', [
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ]);
        }

        // Delete user-level data.
        $DB->delete_records('local_unifiedgrader_comments', ['userid' => $userid]);
        $DB->delete_records('local_unifiedgrader_prefs', ['userid' => $userid]);

        // Delete comment library v2 data.
        $clibids = $DB->get_fieldset_select('local_unifiedgrader_clib', 'id', 'userid = ?', [$userid]);
        if ($clibids) {
            [$insql, $inparams] = $DB->get_in_or_equal($clibids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_unifiedgrader_clmap', "commentid {$insql}", $inparams);
        }
        $DB->delete_records('local_unifiedgrader_clib', ['userid' => $userid]);
        $DB->delete_records('local_unifiedgrader_cltag', ['userid' => $userid]);
    }

    /**
     * Delete multiple users' data for a single context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql1, $inparams1] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid1');
        [$insql2, $inparams2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid2');

        $DB->delete_records_select('local_unifiedgrader_notes',
            "cmid = :cmid AND (userid {$insql1} OR authorid {$insql2})",
            array_merge(['cmid' => $context->instanceid], $inparams1, $inparams2),
        );

        [$insql3, $inparams3] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid3');
        $DB->delete_records_select('local_unifiedgrader_annot',
            "cmid = :cmid2 AND userid {$insql3}",
            array_merge(['cmid2' => $context->instanceid], $inparams3),
        );

        [$insql4, $inparams4] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid4');
        [$insql5, $inparams5] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid5');
        $DB->delete_records_select('local_unifiedgrader_penalty',
            "cmid = :cmid3 AND (userid {$insql4} OR authorid {$insql5})",
            array_merge(['cmid3' => $context->instanceid], $inparams4, $inparams5),
        );

        [$insql6, $inparams6] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid6');
        [$insql7, $inparams7] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid7');
        $DB->delete_records_select('local_unifiedgrader_fext',
            "cmid = :cmid4 AND (userid {$insql6} OR authorid {$insql7})",
            array_merge(['cmid4' => $context->instanceid], $inparams6, $inparams7),
        );

        [$insql8, $inparams8] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid8');
        $DB->delete_records_select('local_unifiedgrader_qfb',
            "cmid = :cmid5 AND userid {$insql8}",
            array_merge(['cmid5' => $context->instanceid], $inparams8),
        );

        // Delete user-level data.
        $DB->delete_records_list('local_unifiedgrader_comments', 'userid', $userids);
        $DB->delete_records_list('local_unifiedgrader_prefs', 'userid', $userids);

        // Delete comment library v2 data.
        [$cinsql, $cinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'clib');
        $clibids = $DB->get_fieldset_select('local_unifiedgrader_clib', 'id', "userid {$cinsql}", $cinparams);
        if ($clibids) {
            [$minsql, $minparams] = $DB->get_in_or_equal($clibids, SQL_PARAMS_NAMED, 'map');
            $DB->delete_records_select('local_unifiedgrader_clmap', "commentid {$minsql}", $minparams);
        }
        $DB->delete_records_list('local_unifiedgrader_clib', 'userid', $userids);
        $DB->delete_records_list('local_unifiedgrader_cltag', 'userid', $userids);
    }
}
