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
 * Notification helper for submission comments.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\notification;

defined('MOODLE_INTERNAL') || die();

/**
 * Sends Moodle notifications when submission comments are posted.
 */
class submission_comment_notification {

    /**
     * Send notification(s) for a new submission comment.
     *
     * If the author is a teacher (has grade capability), notifies the student.
     * If the author is a student, notifies all teachers with grade capability.
     *
     * @param int $cmid Course module ID.
     * @param int $studentuserid The student user ID (whom the comment thread is about).
     * @param int $authorid The user who posted the comment.
     * @param string $content The comment content.
     */
    public static function send(int $cmid, int $studentuserid, int $authorid, string $content): void {
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $context = \context_module::instance($cmid);
        $author = \core_user::get_user($authorid);

        if (!$author) {
            return;
        }

        $isgrader = has_capability('local/unifiedgrader:grade', $context, $authorid);

        if ($isgrader) {
            // Teacher posted — notify the student.
            $student = \core_user::get_user($studentuserid);
            if ($student) {
                $url = new \moodle_url('/local/unifiedgrader/view_feedback.php', ['cmid' => $cmid]);
                self::send_message($author, $student, $course, $cm, $url, $content);
            }
        } else {
            // Student posted — notify all teachers with grading capability.
            $graders = get_enrolled_users($context, 'local/unifiedgrader:grade');
            $graderurl = new \moodle_url('/local/unifiedgrader/grade.php', ['cmid' => $cmid]);
            foreach ($graders as $grader) {
                // Don't notify the author themselves.
                if ((int) $grader->id === $authorid) {
                    continue;
                }
                self::send_message($author, $grader, $course, $cm, $graderurl, $content);
            }
        }
    }

    /**
     * Build and send a single notification message.
     *
     * @param \stdClass $author The comment author.
     * @param \stdClass $recipient The notification recipient.
     * @param \stdClass $course The course object.
     * @param \cm_info $cm The course module info.
     * @param \moodle_url $url The URL to link to.
     * @param string $content The comment content.
     */
    private static function send_message(
        \stdClass $author,
        \stdClass $recipient,
        \stdClass $course,
        \cm_info $cm,
        \moodle_url $url,
        string $content
    ): void {
        $activityname = format_string($cm->name, true, ['context' => \context_module::instance($cm->id)]);
        $coursename = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);

        $a = (object) [
            'authorfullname' => fullname($author),
            'activityname' => $activityname,
            'coursename' => $coursename,
            'activityurl' => $url->out(false),
            'content' => format_text($content, FORMAT_HTML),
            'timecreated' => userdate(time(), get_string('strftimedaydatetime', 'langconfig')),
        ];

        $subject = get_string('notification_comment_subject', 'local_unifiedgrader', $a);
        $htmlbody = get_string('notification_comment_body', 'local_unifiedgrader', $a);
        $smallmessage = get_string('notification_comment_small', 'local_unifiedgrader', $a);

        $message = new \core\message\message();
        $message->component = 'local_unifiedgrader';
        $message->name = 'submission_comment';
        $message->userfrom = $author;
        $message->userto = $recipient;
        $message->subject = $subject;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessage = html_to_text($htmlbody);
        $message->fullmessagehtml = $htmlbody;
        $message->smallmessage = $smallmessage;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = $activityname;
        $message->customdata = [
            'cmid' => $cm->id,
        ];

        message_send($message);
    }
}
