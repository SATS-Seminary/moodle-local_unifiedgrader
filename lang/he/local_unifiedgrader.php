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
 * Language strings for local_unifiedgrader - Hebrew (he).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'מתרג מאוחד';
$string['grading_interface'] = 'מתרג מאוחד';
$string['nopermission'] = 'אין לך הרשאה להשתמש בהמתרג המאוחד.';
$string['invalidactivitytype'] = 'סוג פעילות זה אינו נתמך על ידי המתרג המאוחד.';
$string['invalidmodule'] = 'מודול פעילות לא חוקי.';
$string['viewfeedback'] = 'צפייה במשוב';

// Attempts.
$string['attempt'] = 'ניסיון';

// Capabilities.
$string['unifiedgrader:grade'] = 'שימוש בהמתרג המאוחד לצורך מתן ציונים';
$string['unifiedgrader:viewall'] = 'צפייה בכל התלמידים בהמתרג המאוחד';
$string['unifiedgrader:viewnotes'] = 'צפייה בהערות פרטיות של מורים';
$string['unifiedgrader:managenotes'] = 'יצירה ועריכה של הערות פרטיות של מורים';
$string['unifiedgrader:viewfeedback'] = 'צפייה במשוב מוער מהמתרג המאוחד';

// Settings.
$string['setting_enable_assign'] = 'הפעלה עבור מטלות';
$string['setting_enable_assign_desc'] = 'אפשר שימוש בהמתרג המאוחד עבור פעילויות מטלה.';
$string['setting_enable_submission_comments'] = 'החלפת תגובות הגשה';
$string['setting_enable_submission_comments_desc'] = 'מחליף את תגובות ההגשה המובנות של Moodle בתצוגת המטלה של הסטודנט בתגובות בסגנון מסנג\'ר של המתרג המאוחד (עם תמיכה בהתראות). סטודנטים יכולים לשלוח הודעות למרצים לפני ואחרי הציון.';
$string['setting_enable_forum'] = 'הפעלה עבור פורומים';
$string['setting_enable_forum_desc'] = 'אפשר שימוש בהמתרג המאוחד עבור פעילויות פורום.';
$string['setting_enable_quiz'] = 'הפעלה עבור בחנים';
$string['setting_enable_quiz_desc'] = 'אפשר שימוש בהמתרג המאוחד עבור פעילויות בוחן.';
$string['setting_enable_quiz_post_grades'] = 'הפעלת פרסום ציונים עבור בחנים';
$string['setting_enable_quiz_post_grades_desc'] = 'נראות ציוני בוחן מנוהלת בדרך כלל באמצעות אפשרויות הסקירה של הבוחן. כאשר מופעל, לחצן "פרסום ציונים" של המתרג המאוחד יעדכן את אפשרויות הסקירה של הבוחן באופן תכנותי כדי להציג או להסתיר ציונים. כאשר מושבת (ברירת מחדל), לחצן פרסום הציונים מוסתר עבור בחנים.';
$string['setting_allow_manual_override'] = 'אפשר דריסה ידנית של ציון';
$string['setting_allow_manual_override_desc'] = 'כאשר מופעל, מורים יכולים להקליד ציון באופן ידני גם כאשר מחוון או מדריך ציונים מוגדרים. כאשר מושבת, הציון מחושב אך ורק מקריטריוני המחוון או מדריך הציונים.';

// Grading interface.
$string['grade'] = 'ציון';
$string['savegrade'] = 'שמור ציון';
$string['savefeedback'] = 'שמור משוב';
$string['savinggrade'] = 'שומר ציון...';
$string['gradesaved'] = 'הציון נשמר';
$string['error_saving'] = 'שגיאה בשמירת הציון.';
$string['error_network'] = 'לא ניתן להתחבר לשרת. אנא בדוק את החיבור ונסה שוב.';
$string['error_offline_comments'] = 'לא ניתן להוסיף הערות במצב לא מקוון.';
$string['feedback'] = 'משוב';
$string['overall_feedback'] = 'משוב כללי';
$string['feedback_saved'] = 'משוב (נשמר)';
$string['edit_feedback'] = 'עריכה';
$string['delete_feedback'] = 'מחיקה';
$string['confirm_delete_feedback'] = 'האם אתה בטוח שברצונך למחוק משוב זה? הציון יישמר.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'הרחב';

// Submissions.
$string['submission'] = 'הגשה';
$string['nosubmission'] = 'אין הגשה';
$string['previewpanel'] = 'תצוגה מקדימה של הגשה';
$string['markingpanel'] = 'לוח ציונים';
$string['onlinetext'] = 'טקסט מקוון';
$string['submittedfiles'] = 'קבצים שהוגשו';
$string['viewfile'] = 'צפייה בקובץ';

// Participants.
$string['participants'] = 'משתתפים';
$string['search'] = 'חיפוש משתתפים...';
$string['sortby'] = 'מיון לפי';
$string['sortby_fullname'] = 'שם מלא';
$string['sortby_submittedat'] = 'תאריך הגשה';
$string['sortby_status'] = 'סטטוס';
$string['filter_all'] = 'כל המשתתפים';
$string['filter_submitted'] = 'הוגש';
$string['filter_needsgrading'] = 'לא מצוין';
$string['filter_notsubmitted'] = 'לא הוגש';
$string['filter_graded'] = 'צוין';
$string['filter_late'] = 'באיחור';
$string['filter_allgroups'] = 'כל הקבוצות';
$string['filter_mygroups'] = 'כל הקבוצות שלי';
$string['studentcount'] = '{$a->current} מתוך {$a->total}';

// Statuses.
$string['status_draft'] = 'טיוטה';
$string['status_submitted'] = 'הוגש';
$string['status_graded'] = 'צוין';
$string['status_nosubmission'] = 'אין הגשה';
$string['status_needsgrading'] = 'דרוש ציון';
$string['status_new'] = 'לא הוגש';
$string['status_late'] = 'באיחור: {$a}';

// Teacher notes.
$string['notes'] = 'הערות מורה';
$string['notes_desc'] = 'הערות פרטיות הנראות רק למורים ולמנחים.';
$string['savenote'] = 'שמור הערה';
$string['deletenote'] = 'מחק';
$string['addnote'] = 'הוסף הערה';
$string['nonotes'] = 'אין הערות עדיין.';
$string['confirmdelete_note'] = 'האם אתה בטוח שברצונך למחוק הערה זו?';

// Comment library.
$string['commentlibrary'] = 'ספריית הערות';
$string['savecomment'] = 'שמור בספרייה';
$string['insertcomment'] = 'הוסף';
$string['deletecomment'] = 'הסר';
$string['newcomment'] = 'הערה חדשה...';
$string['nocomments'] = 'אין הערות שמורות.';

// UI.
$string['loading'] = 'טוען...';
$string['saving'] = 'שומר...';
$string['saved'] = 'נשמר';
$string['previousstudent'] = 'התלמיד הקודם';
$string['nextstudent'] = 'התלמיד הבא';
$string['expandfilters'] = 'הצג מסננים';
$string['collapsefilters'] = 'הסתר מסננים';
$string['backtocourse'] = 'חזרה לקורס';
$string['rubric'] = 'מחוון';
$string['markingguide'] = 'מדריך ציונים';
$string['criterion'] = 'קריטריון';
$string['score'] = 'ניקוד';
$string['remark'] = 'הערה';
$string['total'] = 'סה"כ: {$a}';
$string['viewallsubmissions'] = 'צפייה בכל ההגשות';
$string['layout_both'] = 'תצוגה מפוצלת';
$string['layout_preview'] = 'תצוגה מקדימה בלבד';
$string['layout_grade'] = 'ציון בלבד';
$string['manualquestions'] = 'שאלות ידניות';
$string['response'] = 'תשובה';
$string['teachercomment'] = 'הערת מורה';

// Submission comments.
$string['submissioncomments'] = 'הערות על ההגשה';
$string['nocommentsyet'] = 'אין הערות עדיין';
$string['addcomment'] = 'הוסף הערה...';
$string['postcomment'] = 'פרסם';
$string['deletesubmissioncomment'] = 'מחק הערה';

// Feedback files.
$string['feedbackfiles'] = 'קבצי משוב';

// Plagiarism.
$string['plagiarism'] = 'גניבה ספרותית';
$string['plagiarism_noresults'] = 'אין תוצאות גניבה ספרותית זמינות.';
$string['plagiarism_pending'] = 'סריקת גניבה ספרותית מתבצעת';
$string['plagiarism_error'] = 'סריקת גניבה ספרותית נכשלה';

// Student feedback view.
$string['assessment_criteria'] = 'קריטריונים להערכה';
$string['teacher_remark'] = 'משוב מורה';
$string['view_feedback'] = 'צפייה במשוב';
$string['view_annotated_feedback'] = 'צפייה במשוב מוער';
$string['feedback_not_available'] = 'המשוב שלך עדיין אינו זמין. אנא בדוק שוב לאחר שההגשה שלך תצוין ותשוחרר.';
$string['no_annotated_files'] = 'אין קבצי PDF מוערים עבור ההגשה שלך.';
$string['feedback_banner_default'] = 'המורה שלך סיפק משוב על ההגשה שלך.';

// Document conversion.
$string['conversion_failed'] = 'לא ניתן היה להמיר קובץ זה ל-PDF לתצוגה מקדימה.';
$string['converting_file'] = 'ממיר מסמך ל-PDF...';
$string['conversion_timeout'] = 'המרת המסמך לוקחת יותר מדי זמן. אנא נסה שוב מאוחר יותר.';
$string['download_annotated_pdf'] = 'הורד PDF מוער';
$string['download_original_submission'] = 'הורד הגשה מקורית: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'הערות מורה פרטיות המאוחסנות לכל תלמיד בכל פעילות בהמתרג המאוחד.';
$string['privacy:metadata:notes:cmid'] = 'מזהה מודול הקורס שאליו ההערה מתייחסת.';
$string['privacy:metadata:notes:userid'] = 'התלמיד שעליו ההערה.';
$string['privacy:metadata:notes:authorid'] = 'המורה שכתב את ההערה.';
$string['privacy:metadata:notes:content'] = 'תוכן ההערה.';
$string['privacy:metadata:comments'] = 'רשומות ספריית הערות לשימוש חוזר בהמתרג המאוחד.';
$string['privacy:metadata:comments:userid'] = 'המורה שבבעלותו ההערה.';
$string['privacy:metadata:comments:content'] = 'תוכן ההערה.';
$string['privacy:metadata:preferences'] = 'העדפות משתמש עבור ממשק המתרג המאוחד.';
$string['privacy:metadata:preferences:userid'] = 'המשתמש שההעדפות שייכות לו.';
$string['privacy:metadata:preferences:data'] = 'נתוני ההעדפות בקידוד JSON.';
$string['privacy:metadata:annotations'] = 'הערות מסמך המאוחסנות בהמתרג המאוחד.';
$string['privacy:metadata:annotations:cmid'] = 'מזהה מודול הקורס שאליו ההערה מתייחסת.';
$string['privacy:metadata:annotations:userid'] = 'התלמיד שההגשה שלו הוערה.';
$string['privacy:metadata:annotations:authorid'] = 'המורה שיצר את ההערה.';
$string['privacy:metadata:annotations:data'] = 'נתוני ההערה (Fabric.js JSON).';
$string['annotations'] = 'הערות';

// PDF viewer.
$string['pdf_prevpage'] = 'עמוד קודם';
$string['pdf_nextpage'] = 'עמוד הבא';
$string['pdf_zoomin'] = 'הגדל';
$string['pdf_zoomout'] = 'הקטן';
$string['pdf_zoomfit'] = 'התאם לרוחב';
$string['pdf_search'] = 'חיפוש במסמך';

// Annotation tools.
$string['annotate_tools'] = 'כלי הערות';
$string['annotate_select'] = 'בחירה';
$string['annotate_textselect'] = 'בחירת טקסט';
$string['annotate_comment'] = 'הערה';
$string['annotate_highlight'] = 'הדגשה';
$string['annotate_pen'] = 'עט';
$string['annotate_pen_fine'] = 'דק';
$string['annotate_pen_medium'] = 'בינוני';
$string['annotate_pen_thick'] = 'עבה';
$string['annotate_stamps'] = 'חותמות';
$string['annotate_stamp_check'] = 'חותמת סימון';
$string['annotate_stamp_cross'] = 'חותמת איקס';
$string['annotate_stamp_question'] = 'חותמת שאלה';
$string['annotate_red'] = 'אדום';
$string['annotate_yellow'] = 'צהוב';
$string['annotate_green'] = 'ירוק';
$string['annotate_blue'] = 'כחול';
$string['annotate_black'] = 'שחור';
$string['annotate_shape'] = 'צורות';
$string['annotate_shape_rect'] = 'מלבן';
$string['annotate_shape_circle'] = 'עיגול';
$string['annotate_shape_arrow'] = 'חץ';
$string['annotate_shape_line'] = 'קו';
$string['annotate_undo'] = 'בטל';
$string['annotate_redo'] = 'בצע שוב';
$string['annotate_delete'] = 'מחק נבחרים';
$string['annotate_clearall'] = 'נקה הכל';
$string['annotate_clear_confirm'] = 'האם אתה בטוח שברצונך לנקות את כל ההערות בעמוד זה? לא ניתן לבטל פעולה זו.';

// Document info.
$string['docinfo'] = 'פרטי מסמך';
$string['docinfo_filename'] = 'שם קובץ';
$string['docinfo_filesize'] = 'גודל קובץ';
$string['docinfo_pages'] = 'עמודים';
$string['docinfo_wordcount'] = 'ספירת מילים';
$string['docinfo_author'] = 'מחבר';
$string['docinfo_creator'] = 'יוצר';
$string['docinfo_created'] = 'נוצר';
$string['docinfo_modified'] = 'שונה';
$string['docinfo_calculating'] = 'מחשב...';

// Forum feedback view.
$string['view_forum_feedback'] = 'צפייה במשוב פורום';
$string['forum_your_posts'] = 'הפרסומים שלך בפורום';
$string['forum_no_posts'] = 'לא פרסמת הודעות בפורום זה.';
$string['forum_feedback_banner'] = 'המורה שלך נתן ציון על השתתפותך בפורום.';
$string['forum_wordcount'] = '{$a} מילים';
$string['forum_posts_pill'] = 'פרסומים';
$string['submission_content_pill'] = 'הגשה';
$string['forum_tab_posts'] = 'פרסומים';
$string['forum_tab_files'] = 'קבצים מוערים';
$string['view_quiz_feedback'] = 'צפייה במשוב בוחן';
$string['quiz_feedback_banner'] = 'המורה שלך סיפק משוב על הבוחן שלך.';
$string['quiz_your_attempt'] = 'הניסיון שלך';
$string['quiz_no_attempt'] = 'לא השלמת ניסיונות עבור בוחן זה.';
$string['quiz_select_attempt'] = 'בחר ניסיון';
$string['select_attempt'] = 'בחר ניסיון';
$string['attempt_label'] = 'ניסיון {$a}';

// Post grades.
$string['grades_posted'] = 'הציונים פורסמו';
$string['grades_hidden'] = 'הציונים הוסתרו';
$string['post_grades'] = 'פרסם ציונים';
$string['unpost_grades'] = 'בטל פרסום ציונים';
$string['confirm_post_grades'] = 'לפרסם את כל הציונים עבור פעילות זו? התלמידים יוכלו לראות את הציונים והמשוב שלהם.';
$string['confirm_unpost_grades'] = 'לבטל פרסום כל הציונים עבור פעילות זו? התלמידים לא יוכלו עוד לראות את הציונים והמשוב שלהם.';
$string['schedule_post'] = 'פרסום בתאריך';
$string['schedule_post_btn'] = 'תזמן';
$string['grades_scheduled'] = 'מפרסם {$a}';
$string['schedule_must_be_future'] = 'התאריך המתוזמן חייב להיות בעתיד.';
$string['quiz_post_grades_disabled'] = 'פרסום ציונים אינו זמין עבור בחנים. נראות הציונים נשלטת על ידי אפשרויות הסקירה של הבוחן.';
$string['quiz_post_grades_no_schedule'] = 'תזמון אינו זמין עבור בחנים. השתמש בפרסום או ביטול פרסום במקום זאת.';

// Submission status actions.
$string['action_revert_to_draft'] = 'החזר לטיוטה';
$string['action_remove_submission'] = 'הסר הגשה';
$string['action_lock'] = 'מנע שינויים בהגשה';
$string['action_unlock'] = 'אפשר שינויים בהגשה';
$string['action_edit_submission'] = 'ערוך הגשה';
$string['action_grant_extension'] = 'הענק הארכה';
$string['action_edit_extension'] = 'ערוך הארכה';
$string['action_submit_for_grading'] = 'הגש לציון';
$string['confirm_revert_to_draft'] = 'האם אתה בטוח שברצונך להחזיר הגשה זו לסטטוס טיוטה?';
$string['confirm_remove_submission'] = 'האם אתה בטוח שברצונך להסיר הגשה זו? לא ניתן לבטל פעולה זו.';
$string['confirm_lock_submission'] = 'למנוע מתלמיד זה לבצע שינויים בהגשה?';
$string['confirm_unlock_submission'] = 'לאפשר לתלמיד זה לבצע שינויים בהגשה?';
$string['confirm_submit_for_grading'] = 'להגיש טיוטה זו בשם התלמיד?';
$string['invalidaction'] = 'פעולת הגשה לא חוקית.';

// Override actions.
$string['override'] = 'דריסה';
$string['action_add_override'] = 'הוסף דריסה';
$string['action_edit_override'] = 'ערוך דריסה';
$string['action_delete_override'] = 'מחק דריסה';
$string['confirm_delete_override'] = 'האם אתה בטוח שברצונך למחוק דריסת משתמש זו?';
$string['override_saved'] = 'הדריסה נשמרה בהצלחה.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'מחק הארכה';
$string['confirm_delete_extension'] = 'האם אתה בטוח שברצונך למחוק הארכת מועד זו?';
$string['quiz_extension_original_duedate'] = 'תאריך הגשה מקורי';
$string['quiz_extension_current_extension'] = 'הארכה נוכחית';
$string['quiz_extension_new_duedate'] = 'תאריך הגשה מוארך';
$string['quiz_extension_must_be_after_duedate'] = 'תאריך ההארכה חייב להיות אחרי תאריך ההגשה הנוכחי.';
$string['quiz_extension_plugin_missing'] = 'התוסף quizaccess_duedate נדרש עבור הארכות בוחן אך אינו מותקן.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'תאריך הגשת פורום';
$string['forum_extension_current_extension'] = 'הארכה נוכחית';
$string['forum_extension_new_duedate'] = 'תאריך הגשה מוארך';
$string['forum_extension_must_be_after_duedate'] = 'תאריך ההארכה חייב להיות אחרי תאריך ההגשה של הפורום.';

// Student profile popout.
$string['profile_view_full'] = 'צפייה בפרופיל המלא';
$string['profile_login_as'] = 'התחבר בתור';
$string['profile_no_email'] = 'אין כתובת דוא"ל זמינה';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'ביטוי רגולרי לקוד קורס';
$string['setting_coursecode_regex_desc'] = 'ספריית ההערות מארגנת הערות שמורות לפי קוד קורס, כך שמורים יכולים לעשות שימוש חוזר במשוב בין הצעות שונות של אותו קורס (למשל מסמסטר לסמסטר). הגדרה זו קובעת כיצד קודי קורס מחולצים משמות קורסים קצרים ב-Moodle. הזן תבנית ביטוי רגולרי PHP שמתאימה לחלק הקוד של השמות הקצרים שלך (למשל <code>/[A-Z]{3}\\d{4}/</code> יחלץ את <strong>THE2201</strong> משם קצר כמו <em>THE2201-2026-S1</em>). השאר ריק כדי להשתמש בשם הקצר המלא כקוד קורס.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'הפעלת טופס דיווח על עבירה אקדמית';
$string['setting_enable_report_form_desc'] = 'כאשר מופעל, כפתור "דווח על עבירה אקדמית" מופיע בסעיפי גניבה ספרותית, ומקשר לטופס דיווח חיצוני.';
$string['setting_report_form_url'] = 'תבנית כתובת URL של טופס דיווח';
$string['setting_report_form_url_desc'] = 'כתובת URL עבור טופס דיווח על עבירה אקדמית. מצייני מיקום נתמכים: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. אלה מוחלפים בזמן ריצה בערכים מקודדי URL. עבור Microsoft Forms, השתמש בתכונת "קבל כתובת URL ממולאת מראש" כדי למצוא שמות פרמטרים.';
$string['report_impropriety'] = 'דווח על עבירה אקדמית';

// Comment library v2.
$string['clib_title'] = 'ספריית הערות';
$string['clib_all'] = 'הכל';
$string['clib_quick_add'] = 'הוספת הערה מהירה...';
$string['clib_manage'] = 'ניהול ספרייה';
$string['clib_no_comments'] = 'אין הערות עדיין.';
$string['clib_insert'] = 'הוסף';
$string['clib_copied'] = 'ההערה הועתקה ללוח';
$string['clib_my_library'] = 'הספרייה שלי';
$string['clib_shared_library'] = 'ספרייה משותפת';
$string['clib_new_comment'] = 'הערה חדשה';
$string['clib_edit_comment'] = 'ערוך הערה';
$string['clib_delete_comment'] = 'מחק הערה';
$string['clib_confirm_delete'] = 'האם אתה בטוח שברצונך למחוק הערה זו?';
$string['clib_share'] = 'שתף';
$string['clib_unshare'] = 'בטל שיתוף';
$string['clib_import'] = 'ייבוא';
$string['clib_imported'] = 'ההערה יובאה לספרייה שלך';
$string['clib_copy_to_course'] = 'העתק לקורס';
$string['clib_all_courses'] = 'כל הקורסים';
$string['clib_tags'] = 'תגיות';
$string['clib_manage_tags'] = 'ניהול תגיות';
$string['clib_new_tag'] = 'תגית חדשה';
$string['clib_edit_tag'] = 'ערוך תגית';
$string['clib_delete_tag'] = 'מחק תגית';
$string['clib_confirm_delete_tag'] = 'האם אתה בטוח שברצונך למחוק תגית זו? היא תוסר מכל ההערות.';
$string['clib_system_tag'] = 'ברירת מחדל של המערכת';
$string['clib_shared_by'] = 'שותף על ידי {$a}';
$string['clib_no_shared'] = 'אין הערות משותפות זמינות.';
$string['clib_picker_freetext'] = 'או כתוב בעצמך...';
$string['clib_picker_loading'] = 'טוען הערות...';
$string['clib_offline_mode'] = 'מציג הערות שמורות במטמון — עריכה אינה זמינה במצב לא מקוון.';
$string['unifiedgrader:sharecomments'] = 'שיתוף הערות בספרייה עם מורים אחרים';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'רשומות ספריית הערות בהמתרג המאוחד.';
$string['privacy:metadata:clib:userid'] = 'המורה שבבעלותו ההערה.';
$string['privacy:metadata:clib:coursecode'] = 'קוד הקורס שאליו ההערה משויכת.';
$string['privacy:metadata:clib:content'] = 'תוכן ההערה.';
$string['privacy:metadata:cltag'] = 'תגיות ספריית הערות בהמתרג המאוחד.';
$string['privacy:metadata:cltag:userid'] = 'המורה שבבעלותו התגית.';
$string['privacy:metadata:cltag:name'] = 'שם התגית.';

// Penalties.
$string['penalties'] = 'עונשים';
$string['penalty_late'] = 'הגשה באיחור';
$string['penalty_late_days'] = '{$a} ימים באיחור';
$string['penalty_late_auto'] = 'מחושב אוטומטית על בסיס כללי העונשים';
$string['penalty_wordcount'] = 'ספירת מילים';
$string['penalty_other'] = 'אחר';
$string['penalty_custom'] = 'מותאם אישית';
$string['penalty_label_placeholder'] = 'תווית (עד 15 תווים)';
$string['penalty_active'] = 'עונשים פעילים';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['penalty_late_label'] = 'באיחור';
$string['penalty_late_applied'] = 'קנס איחור של {$a}% הוחל';
$string['late_days'] = '{$a} ימים';
$string['late_day'] = 'יום {$a}';
$string['late_hours'] = '{$a} שעות';
$string['late_hour'] = 'שעה {$a}';
$string['late_mins'] = '{$a} דקות';
$string['late_min'] = 'דקה {$a}';
$string['late_lessthanmin'] = '< דקה אחת';
$string['finalgradeafterpenalties'] = 'ציון סופי לאחר עונשים:';
$string['cannotdeleteautopenalty'] = 'עונשי איחור מחושבים אוטומטית ולא ניתן למחוק אותם.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'הורד PDF משוב';
$string['feedback_summary_overall_feedback'] = 'משוב כללי';
$string['feedback_summary_graded_on'] = 'צוין בתאריך {$a}';
$string['feedback_summary_generated_by'] = 'נוצר על ידי המתרג המאוחד';
$string['feedback_summary_media_note'] = 'תוכן מדיה זמין בתצוגת המשוב המקוונת.';
$string['feedback_summary_no_grade'] = 'לא זמין';
$string['feedback_summary_remark'] = 'הערת מורה';
$string['feedback_summary_total'] = 'סה"כ';
$string['levels'] = 'רמות';
$string['error_gs_not_configured'] = 'GhostScript אינו מוגדר בשרת Moodle זה. על מנהל המערכת להגדיר את נתיב GhostScript בניהול אתר > תוספים > מודולי פעילות > מטלה > משוב > הער PDF.';
$string['error_pdf_combine_failed'] = 'שילוב קבצי PDF נכשל: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'עונשי ציון שהוחלו על ידי מורים בהמתרג המאוחד.';
$string['privacy:metadata:penalty:userid'] = 'התלמיד שעליו הוחל העונש.';
$string['privacy:metadata:penalty:authorid'] = 'המורה שהחיל את העונש.';
$string['privacy:metadata:penalty:category'] = 'קטגוריית העונש (ספירת מילים או אחר).';
$string['privacy:metadata:penalty:label'] = 'התווית המותאמת אישית של העונש.';
$string['privacy:metadata:penalty:percentage'] = 'אחוז העונש.';
$string['privacy:metadata:fext'] = 'הארכות מועד הגשה בפורום שהוענקו על ידי מורים בהמתרג המאוחד.';
$string['privacy:metadata:fext:userid'] = 'התלמיד שקיבל את ההארכה.';
$string['privacy:metadata:fext:authorid'] = 'המורה שהעניק את ההארכה.';
$string['privacy:metadata:fext:extensionduedate'] = 'תאריך ההגשה המוארך.';
$string['privacy:metadata:qfb'] = 'משוב בוחן לכל ניסיון המאוחסן על ידי המתרג המאוחד.';
$string['privacy:metadata:qfb:userid'] = 'התלמיד שהמשוב מיועד לו.';
$string['privacy:metadata:qfb:grader'] = 'המורה שסיפק את המשוב.';
$string['privacy:metadata:qfb:feedback'] = 'טקסט המשוב.';
$string['privacy:metadata:qfb:attemptnumber'] = 'מספר ניסיון הבוחן.';
$string['privacy:metadata:scomm'] = 'הערות הגשה המאוחסנות על ידי Unified Grader.';
$string['privacy:metadata:scomm:cmid'] = 'מודול הקורס שאליו שייכת ההערה.';
$string['privacy:metadata:scomm:userid'] = 'הסטודנט שעליו נושא שרשור ההערות.';
$string['privacy:metadata:scomm:authorid'] = 'המשתמש שכתב את ההערה.';
$string['privacy:metadata:scomm:content'] = 'תוכן ההערה.';
$string['privacy_forum_extensions'] = 'הארכות פורום';
$string['privacy_quiz_feedback'] = 'משוב בוחן';

// Notification strings.
$string['messageprovider:submission_comment'] = 'התראות על הערות הגשה';
$string['notification_comment_subject'] = 'הערה חדשה ב-{$a->activityname}';
$string['notification_comment_body'] = '<p><strong>{$a->authorfullname}</strong> פרסם הערה ב-<a href="{$a->activityurl}">{$a->activityname}</a> ב-{$a->coursename} ({$a->timecreated}):</p><blockquote>{$a->content}</blockquote>';
$string['notification_comment_small'] = '{$a->authorfullname} הגיב ב-{$a->activityname}';

// Offline cache and save status.
$string['allchangessaved'] = 'כל השינויים נשמרו';
$string['editing'] = 'עורך...';
$string['offlinesavedlocally'] = 'לא מקוון — נשמר מקומית';
$string['connectionlost'] = 'החיבור אבד — העבודה שלך נשמרה מקומית ותסונכרן כאשר החיבור יחודש.';
$string['recoveredunsavedchanges'] = 'שוחזרו שינויים שלא נשמרו מההפעלה האחרונה שלך.';
$string['restore'] = 'שחזר';
$string['discard'] = 'בטל';
$string['mark_as_graded'] = 'סמן כמצוין';
