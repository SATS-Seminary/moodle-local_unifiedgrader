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
 * Language strings for local_unifiedgrader (Greek / Ελληνικά).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Ενοποιημένος βαθμολογητής';
$string['grading_interface'] = 'Ενοποιημένος βαθμολογητής';
$string['nopermission'] = 'Δεν έχετε δικαίωμα να χρησιμοποιήσετε τον ενοποιημένο βαθμολογητή.';
$string['invalidactivitytype'] = 'Αυτός ο τύπος δραστηριότητας δεν υποστηρίζεται από τον ενοποιημένο βαθμολογητή.';
$string['invalidmodule'] = 'Μη έγκυρο άρθρωμα δραστηριότητας.';
$string['viewfeedback'] = 'Προβολή ανατροφοδότησης';

// Attempts.
$string['attempt'] = 'Προσπάθεια';

// Capabilities.
$string['unifiedgrader:grade'] = 'Χρήση του ενοποιημένου βαθμολογητή για βαθμολόγηση';
$string['unifiedgrader:viewall'] = 'Προβολή όλων των σπουδαστών στον ενοποιημένο βαθμολογητή';
$string['unifiedgrader:viewnotes'] = 'Προβολή ιδιωτικών σημειώσεων εκπαιδευτικού';
$string['unifiedgrader:managenotes'] = 'Δημιουργία και επεξεργασία ιδιωτικών σημειώσεων εκπαιδευτικού';
$string['unifiedgrader:viewfeedback'] = 'Προβολή σχολιασμένης ανατροφοδότησης από τον ενοποιημένο βαθμολογητή';

// Settings.
$string['setting_enable_assign'] = 'Ενεργοποίηση για εργασίες';
$string['setting_enable_assign_desc'] = 'Επιτρέπει τη χρήση του ενοποιημένου βαθμολογητή για δραστηριότητες εργασιών.';
$string['setting_enable_forum'] = 'Ενεργοποίηση για φόρουμ';
$string['setting_enable_forum_desc'] = 'Επιτρέπει τη χρήση του ενοποιημένου βαθμολογητή για δραστηριότητες φόρουμ.';
$string['setting_enable_quiz'] = 'Ενεργοποίηση για κουίζ';
$string['setting_enable_quiz_desc'] = 'Επιτρέπει τη χρήση του ενοποιημένου βαθμολογητή για δραστηριότητες κουίζ.';
$string['setting_enable_quiz_post_grades'] = 'Ενεργοποίηση δημοσίευσης βαθμών για κουίζ';
$string['setting_enable_quiz_post_grades_desc'] = 'Η ορατότητα βαθμών κουίζ κανονικά διαχειρίζεται από τις επιλογές αναθεώρησης του κουίζ. Όταν ενεργοποιηθεί, ο διακόπτης «Δημοσίευση βαθμών» του ενοποιημένου βαθμολογητή θα ενημερώνει τις επιλογές αναθεώρησης του κουίζ μέσω προγραμματισμού για εμφάνιση ή απόκρυψη βαθμών. Όταν απενεργοποιηθεί (προεπιλογή), ο διακόπτης δημοσίευσης βαθμών είναι κρυφός για κουίζ.';
$string['setting_allow_manual_override'] = 'Επιτρέπεται η χειροκίνητη αντικατάσταση βαθμού';
$string['setting_allow_manual_override_desc'] = 'Όταν ενεργοποιηθεί, οι εκπαιδευτικοί μπορούν να πληκτρολογήσουν βαθμό χειροκίνητα ακόμα και όταν έχει ρυθμιστεί ρουμπρίκα ή οδηγός βαθμολόγησης. Όταν απενεργοποιηθεί, ο βαθμός υπολογίζεται αποκλειστικά από τα κριτήρια της ρουμπρίκας ή του οδηγού βαθμολόγησης.';

// Grading interface.
$string['grade'] = 'Βαθμός';
$string['savegrade'] = 'Αποθήκευση βαθμού';
$string['savefeedback'] = 'Αποθήκευση ανατροφοδότησης';
$string['savinggrade'] = 'Αποθήκευση βαθμού...';
$string['gradesaved'] = 'Ο βαθμός αποθηκεύτηκε';
$string['error_saving'] = 'Σφάλμα κατά την αποθήκευση βαθμού.';
$string['error_network'] = 'Αδυναμία σύνδεσης με τον διακομιστή. Ελέγξτε τη σύνδεσή σας και δοκιμάστε ξανά.';
$string['error_offline_comments'] = 'Δεν είναι δυνατή η προσθήκη σχολίων χωρίς σύνδεση.';
$string['feedback'] = 'Ανατροφοδότηση';
$string['overall_feedback'] = 'Συνολική ανατροφοδότηση';
$string['feedback_saved'] = 'Ανατροφοδότηση (αποθηκεύτηκε)';
$string['edit_feedback'] = 'Επεξεργασία';
$string['delete_feedback'] = 'Διαγραφή';
$string['confirm_delete_feedback'] = 'Είστε βέβαιοι ότι θέλετε να διαγράψετε αυτή την ανατροφοδότηση; Ο βαθμός θα διατηρηθεί.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Ανάπτυξη';

// Submissions.
$string['submission'] = 'Υποβολή';
$string['nosubmission'] = 'Χωρίς υποβολή';
$string['previewpanel'] = 'Προεπισκόπηση υποβολής';
$string['markingpanel'] = 'Πίνακας βαθμολόγησης';
$string['onlinetext'] = 'Κείμενο σε σύνδεση';
$string['submittedfiles'] = 'Υποβληθέντα αρχεία';
$string['viewfile'] = 'Προβολή αρχείου';

// Participants.
$string['participants'] = 'Συμμετέχοντες';
$string['search'] = 'Αναζήτηση συμμετεχόντων...';
$string['sortby'] = 'Ταξινόμηση κατά';
$string['sortby_fullname'] = 'Πλήρες όνομα';
$string['sortby_submittedat'] = 'Ημερομηνία υποβολής';
$string['sortby_status'] = 'Κατάσταση';
$string['filter_all'] = 'Όλοι οι συμμετέχοντες';
$string['filter_submitted'] = 'Υποβλήθηκε';
$string['filter_needsgrading'] = 'Αβαθμολόγητο';
$string['filter_notsubmitted'] = 'Δεν υποβλήθηκε';
$string['filter_graded'] = 'Βαθμολογήθηκε';
$string['filter_late'] = 'Εκπρόθεσμο';
$string['filter_allgroups'] = 'Όλες οι ομάδες';
$string['studentcount'] = '{$a->current} από {$a->total}';

// Statuses.
$string['status_draft'] = 'Πρόχειρο';
$string['status_submitted'] = 'Υποβλήθηκε';
$string['status_graded'] = 'Βαθμολογήθηκε';
$string['status_nosubmission'] = 'Χωρίς υποβολή';
$string['status_needsgrading'] = 'Χρειάζεται βαθμολόγηση';
$string['status_new'] = 'Δεν υποβλήθηκε';
$string['status_late'] = 'Εκπρόθεσμο: {$a}';

// Teacher notes.
$string['notes'] = 'Σημειώσεις εκπαιδευτικού';
$string['notes_desc'] = 'Ιδιωτικές σημειώσεις ορατές μόνο από εκπαιδευτικούς και συντονιστές.';
$string['savenote'] = 'Αποθήκευση σημείωσης';
$string['deletenote'] = 'Διαγραφή';
$string['addnote'] = 'Προσθήκη σημείωσης';
$string['nonotes'] = 'Δεν υπάρχουν σημειώσεις ακόμα.';
$string['confirmdelete_note'] = 'Είστε βέβαιοι ότι θέλετε να διαγράψετε αυτή τη σημείωση;';

// Comment library.
$string['commentlibrary'] = 'Βιβλιοθήκη σχολίων';
$string['savecomment'] = 'Αποθήκευση στη βιβλιοθήκη';
$string['insertcomment'] = 'Εισαγωγή';
$string['deletecomment'] = 'Αφαίρεση';
$string['newcomment'] = 'Νέο σχόλιο...';
$string['nocomments'] = 'Δεν υπάρχουν αποθηκευμένα σχόλια.';

// UI.
$string['loading'] = 'Φόρτωση...';
$string['saving'] = 'Αποθήκευση...';
$string['saved'] = 'Αποθηκεύτηκε';
$string['previousstudent'] = 'Προηγούμενος σπουδαστής';
$string['nextstudent'] = 'Επόμενος σπουδαστής';
$string['expandfilters'] = 'Εμφάνιση φίλτρων';
$string['collapsefilters'] = 'Απόκρυψη φίλτρων';
$string['backtocourse'] = 'Επιστροφή στο μάθημα';
$string['rubric'] = 'Ρουμπρίκα';
$string['markingguide'] = 'Οδηγός βαθμολόγησης';
$string['criterion'] = 'Κριτήριο';
$string['score'] = 'Βαθμολογία';
$string['remark'] = 'Παρατήρηση';
$string['total'] = 'Σύνολο: {$a}';
$string['viewallsubmissions'] = 'Προβολή όλων των υποβολών';
$string['layout_both'] = 'Διπλή προβολή';
$string['layout_preview'] = 'Μόνο προεπισκόπηση';
$string['layout_grade'] = 'Μόνο βαθμολόγηση';
$string['manualquestions'] = 'Ερωτήσεις χειροκίνητης βαθμολόγησης';
$string['response'] = 'Απάντηση';
$string['teachercomment'] = 'Σχόλιο εκπαιδευτικού';

// Submission comments.
$string['submissioncomments'] = 'Σχόλια υποβολής';
$string['nocommentsyet'] = 'Δεν υπάρχουν σχόλια ακόμα';
$string['addcomment'] = 'Προσθήκη σχολίου...';
$string['postcomment'] = 'Δημοσίευση';
$string['deletesubmissioncomment'] = 'Διαγραφή σχολίου';

// Feedback files.
$string['feedbackfiles'] = 'Αρχεία ανατροφοδότησης';

// Plagiarism.
$string['plagiarism'] = 'Λογοκλοπή';
$string['plagiarism_noresults'] = 'Δεν υπάρχουν διαθέσιμα αποτελέσματα λογοκλοπής.';
$string['plagiarism_pending'] = 'Ο έλεγχος λογοκλοπής βρίσκεται σε εξέλιξη';
$string['plagiarism_error'] = 'Ο έλεγχος λογοκλοπής απέτυχε';

// Student feedback view.
$string['assessment_criteria'] = 'Κριτήρια αξιολόγησης';
$string['teacher_remark'] = 'Ανατροφοδότηση εκπαιδευτικού';
$string['view_feedback'] = 'Προβολή ανατροφοδότησης';
$string['view_annotated_feedback'] = 'Προβολή σχολιασμένης ανατροφοδότησης';
$string['feedback_not_available'] = 'Η ανατροφοδότησή σας δεν είναι ακόμα διαθέσιμη. Ελέγξτε ξανά αφού βαθμολογηθεί και δημοσιευτεί η υποβολή σας.';
$string['no_annotated_files'] = 'Δεν υπάρχουν σχολιασμένα αρχεία PDF για την υποβολή σας.';
$string['feedback_banner_default'] = 'Ο εκπαιδευτικός σας έχει παράσχει ανατροφοδότηση για την υποβολή σας.';

// Document conversion.
$string['conversion_failed'] = 'Αυτό το αρχείο δεν μπόρεσε να μετατραπεί σε PDF για προεπισκόπηση.';
$string['converting_file'] = 'Μετατροπή εγγράφου σε PDF...';
$string['conversion_timeout'] = 'Η μετατροπή του εγγράφου διαρκεί πολύ. Δοκιμάστε ξανά αργότερα.';
$string['download_annotated_pdf'] = 'Λήψη σχολιασμένου PDF';
$string['download_original_submission'] = 'Λήψη αρχικής υποβολής: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Ιδιωτικές σημειώσεις εκπαιδευτικού ανά σπουδαστή ανά δραστηριότητα στον ενοποιημένο βαθμολογητή.';
$string['privacy:metadata:notes:cmid'] = 'Το αναγνωριστικό αρθρώματος μαθήματος στο οποίο αναφέρεται η σημείωση.';
$string['privacy:metadata:notes:userid'] = 'Ο σπουδαστής στον οποίο αναφέρεται η σημείωση.';
$string['privacy:metadata:notes:authorid'] = 'Ο εκπαιδευτικός που έγραψε τη σημείωση.';
$string['privacy:metadata:notes:content'] = 'Το περιεχόμενο της σημείωσης.';
$string['privacy:metadata:comments'] = 'Επαναχρησιμοποιήσιμες καταχωρίσεις βιβλιοθήκης σχολίων στον ενοποιημένο βαθμολογητή.';
$string['privacy:metadata:comments:userid'] = 'Ο εκπαιδευτικός στον οποίο ανήκει το σχόλιο.';
$string['privacy:metadata:comments:content'] = 'Το περιεχόμενο του σχολίου.';
$string['privacy:metadata:preferences'] = 'Προτιμήσεις χρήστη για τη διεπαφή του ενοποιημένου βαθμολογητή.';
$string['privacy:metadata:preferences:userid'] = 'Ο χρήστης στον οποίο ανήκουν οι προτιμήσεις.';
$string['privacy:metadata:preferences:data'] = 'Τα δεδομένα προτιμήσεων σε μορφή JSON.';
$string['privacy:metadata:annotations'] = 'Σχολιασμοί εγγράφων που αποθηκεύονται στον ενοποιημένο βαθμολογητή.';
$string['privacy:metadata:annotations:cmid'] = 'Το αναγνωριστικό αρθρώματος μαθήματος στο οποίο αναφέρεται ο σχολιασμός.';
$string['privacy:metadata:annotations:userid'] = 'Ο σπουδαστής του οποίου η υποβολή σχολιάστηκε.';
$string['privacy:metadata:annotations:authorid'] = 'Ο εκπαιδευτικός που δημιούργησε τον σχολιασμό.';
$string['privacy:metadata:annotations:data'] = 'Τα δεδομένα σχολιασμού (JSON Fabric.js).';
$string['annotations'] = 'Σχολιασμοί';

// PDF viewer.
$string['pdf_prevpage'] = 'Προηγούμενη σελίδα';
$string['pdf_nextpage'] = 'Επόμενη σελίδα';
$string['pdf_zoomin'] = 'Μεγέθυνση';
$string['pdf_zoomout'] = 'Σμίκρυνση';
$string['pdf_zoomfit'] = 'Προσαρμογή στο πλάτος';
$string['pdf_search'] = 'Αναζήτηση στο έγγραφο';

// Annotation tools.
$string['annotate_tools'] = 'Εργαλεία σχολιασμού';
$string['annotate_select'] = 'Επιλογή';
$string['annotate_textselect'] = 'Επιλογή κειμένου';
$string['annotate_comment'] = 'Σχόλιο';
$string['annotate_highlight'] = 'Επισήμανση';
$string['annotate_pen'] = 'Στυλό';
$string['annotate_pen_fine'] = 'Λεπτό';
$string['annotate_pen_medium'] = 'Μεσαίο';
$string['annotate_pen_thick'] = 'Χοντρό';
$string['annotate_stamps'] = 'Σφραγίδες';
$string['annotate_stamp_check'] = 'Σφραγίδα επιβεβαίωσης';
$string['annotate_stamp_cross'] = 'Σφραγίδα σταυρού';
$string['annotate_stamp_question'] = 'Σφραγίδα ερωτηματικού';
$string['annotate_red'] = 'Κόκκινο';
$string['annotate_yellow'] = 'Κίτρινο';
$string['annotate_green'] = 'Πράσινο';
$string['annotate_blue'] = 'Μπλε';
$string['annotate_black'] = 'Μαύρο';
$string['annotate_shape'] = 'Σχήματα';
$string['annotate_shape_rect'] = 'Ορθογώνιο';
$string['annotate_shape_circle'] = 'Κύκλος';
$string['annotate_shape_arrow'] = 'Βέλος';
$string['annotate_shape_line'] = 'Γραμμή';
$string['annotate_undo'] = 'Αναίρεση';
$string['annotate_redo'] = 'Επανάληψη';
$string['annotate_delete'] = 'Διαγραφή επιλεγμένου';
$string['annotate_clearall'] = 'Εκκαθάριση όλων';
$string['annotate_clear_confirm'] = 'Είστε βέβαιοι ότι θέλετε να εκκαθαρίσετε όλους τους σχολιασμούς σε αυτή τη σελίδα; Δεν μπορεί να αναιρεθεί.';

// Document info.
$string['docinfo'] = 'Πληροφορίες εγγράφου';
$string['docinfo_filename'] = 'Όνομα αρχείου';
$string['docinfo_filesize'] = 'Μέγεθος αρχείου';
$string['docinfo_pages'] = 'Σελίδες';
$string['docinfo_wordcount'] = 'Αριθμός λέξεων';
$string['docinfo_author'] = 'Συγγραφέας';
$string['docinfo_creator'] = 'Δημιουργός';
$string['docinfo_created'] = 'Δημιουργήθηκε';
$string['docinfo_modified'] = 'Τροποποιήθηκε';
$string['docinfo_calculating'] = 'Υπολογισμός...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Προβολή ανατροφοδότησης φόρουμ';
$string['forum_your_posts'] = 'Οι δημοσιεύσεις σας στο φόρουμ';
$string['forum_no_posts'] = 'Δεν έχετε κάνει δημοσιεύσεις σε αυτό το φόρουμ.';
$string['forum_feedback_banner'] = 'Ο εκπαιδευτικός σας βαθμολόγησε τη συμμετοχή σας στο φόρουμ.';
$string['forum_wordcount'] = '{$a} λέξεις';
$string['forum_posts_pill'] = 'Δημοσιεύσεις';
$string['submission_content_pill'] = 'Υποβολή';
$string['forum_tab_posts'] = 'Δημοσιεύσεις';
$string['forum_tab_files'] = 'Σχολιασμένα αρχεία';
$string['view_quiz_feedback'] = 'Προβολή ανατροφοδότησης κουίζ';
$string['quiz_feedback_banner'] = 'Ο εκπαιδευτικός σας έχει παράσχει ανατροφοδότηση για το κουίζ σας.';
$string['quiz_your_attempt'] = 'Η προσπάθειά σας';
$string['quiz_no_attempt'] = 'Δεν έχετε ολοκληρώσει καμία προσπάθεια για αυτό το κουίζ.';
$string['quiz_select_attempt'] = 'Επιλογή προσπάθειας';
$string['select_attempt'] = 'Επιλογή προσπάθειας';
$string['attempt_label'] = 'Προσπάθεια {$a}';

// Post grades.
$string['grades_posted'] = 'Οι βαθμοί δημοσιεύτηκαν';
$string['grades_hidden'] = 'Οι βαθμοί είναι κρυφοί';
$string['post_grades'] = 'Δημοσίευση βαθμών';
$string['unpost_grades'] = 'Απόσυρση βαθμών';
$string['confirm_post_grades'] = 'Δημοσίευση όλων των βαθμών για αυτή τη δραστηριότητα; Οι σπουδαστές θα μπορούν να δουν τους βαθμούς και την ανατροφοδότησή τους.';
$string['confirm_unpost_grades'] = 'Απόσυρση όλων των βαθμών για αυτή τη δραστηριότητα; Οι σπουδαστές δεν θα μπορούν πλέον να δουν τους βαθμούς και την ανατροφοδότησή τους.';
$string['schedule_post'] = 'Δημοσίευση σε ημερομηνία';
$string['schedule_post_btn'] = 'Προγραμματισμός';
$string['grades_scheduled'] = 'Δημοσίευση {$a}';
$string['schedule_must_be_future'] = 'Η προγραμματισμένη ημερομηνία πρέπει να είναι στο μέλλον.';
$string['quiz_post_grades_disabled'] = 'Η δημοσίευση βαθμών δεν είναι διαθέσιμη για κουίζ. Η ορατότητα βαθμών ελέγχεται από τις επιλογές αναθεώρησης του κουίζ.';
$string['quiz_post_grades_no_schedule'] = 'Ο προγραμματισμός δεν είναι διαθέσιμος για κουίζ. Χρησιμοποιήστε Δημοσίευση ή Απόσυρση.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Επαναφορά σε πρόχειρο';
$string['action_remove_submission'] = 'Αφαίρεση υποβολής';
$string['action_lock'] = 'Αποτροπή αλλαγών υποβολής';
$string['action_unlock'] = 'Επιτρέπονται αλλαγές υποβολής';
$string['action_edit_submission'] = 'Επεξεργασία υποβολής';
$string['action_grant_extension'] = 'Χορήγηση παράτασης';
$string['action_edit_extension'] = 'Επεξεργασία παράτασης';
$string['action_submit_for_grading'] = 'Υποβολή για βαθμολόγηση';
$string['confirm_revert_to_draft'] = 'Είστε βέβαιοι ότι θέλετε να επαναφέρετε αυτή την υποβολή σε κατάσταση πρόχειρου;';
$string['confirm_remove_submission'] = 'Είστε βέβαιοι ότι θέλετε να αφαιρέσετε αυτή την υποβολή; Δεν μπορεί να αναιρεθεί.';
$string['confirm_lock_submission'] = 'Αποτροπή αυτού του σπουδαστή από αλλαγές στην υποβολή;';
$string['confirm_unlock_submission'] = 'Να επιτραπούν αλλαγές στην υποβολή για αυτόν τον σπουδαστή;';
$string['confirm_submit_for_grading'] = 'Υποβολή αυτού του πρόχειρου εκ μέρους του σπουδαστή;';
$string['invalidaction'] = 'Μη έγκυρη ενέργεια υποβολής.';

// Override actions.
$string['override'] = 'Παράκαμψη';
$string['action_add_override'] = 'Προσθήκη παράκαμψης';
$string['action_edit_override'] = 'Επεξεργασία παράκαμψης';
$string['action_delete_override'] = 'Διαγραφή παράκαμψης';
$string['confirm_delete_override'] = 'Είστε βέβαιοι ότι θέλετε να διαγράψετε αυτή την παράκαμψη χρήστη;';
$string['override_saved'] = 'Η παράκαμψη αποθηκεύτηκε επιτυχώς.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Διαγραφή παράτασης';
$string['confirm_delete_extension'] = 'Είστε βέβαιοι ότι θέλετε να διαγράψετε αυτή την παράταση προθεσμίας;';
$string['quiz_extension_original_duedate'] = 'Αρχική προθεσμία';
$string['quiz_extension_current_extension'] = 'Τρέχουσα παράταση';
$string['quiz_extension_new_duedate'] = 'Προθεσμία παράτασης';
$string['quiz_extension_must_be_after_duedate'] = 'Η ημερομηνία παράτασης πρέπει να είναι μετά την τρέχουσα προθεσμία.';
$string['quiz_extension_plugin_missing'] = 'Το πρόσθετο quizaccess_duedate απαιτείται για παρατάσεις κουίζ αλλά δεν είναι εγκατεστημένο.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Προθεσμία φόρουμ';
$string['forum_extension_current_extension'] = 'Τρέχουσα παράταση';
$string['forum_extension_new_duedate'] = 'Προθεσμία παράτασης';
$string['forum_extension_must_be_after_duedate'] = 'Η ημερομηνία παράτασης πρέπει να είναι μετά την προθεσμία του φόρουμ.';

// Student profile popout.
$string['profile_view_full'] = 'Προβολή πλήρους προφίλ';
$string['profile_login_as'] = 'Σύνδεση ως';
$string['profile_no_email'] = 'Δεν υπάρχει διαθέσιμο email';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Κανονική έκφραση κωδικού μαθήματος';
$string['setting_coursecode_regex_desc'] = 'Η Βιβλιοθήκη σχολίων οργανώνει τα αποθηκευμένα σχόλια ανά κωδικό μαθήματος, ώστε οι εκπαιδευτικοί να μπορούν να επαναχρησιμοποιούν ανατροφοδοτήσεις σε διαφορετικές προσφορές του ίδιου μαθήματος (π.χ. από εξάμηνο σε εξάμηνο). Αυτή η ρύθμιση ελέγχει τον τρόπο εξαγωγής κωδικών μαθημάτων από τα σύντομα ονόματα μαθημάτων του Moodle. Εισάγετε ένα μοτίβο κανονικής έκφρασης PHP που αντιστοιχεί στο τμήμα κωδικού των σύντομων ονομάτων σας (π.χ. <code>/[A-Z]{3}\\d{4}/</code> θα εξάγει <strong>THE2201</strong> από ένα σύντομο όνομα όπως <em>THE2201-2026-S1</em>). Αφήστε κενό για να χρησιμοποιηθεί ολόκληρο το σύντομο όνομα ως κωδικός μαθήματος.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Ενεργοποίηση φόρμας αναφοράς ακαδημαϊκής ατασθαλίας';
$string['setting_enable_report_form_desc'] = 'Όταν ενεργοποιηθεί, εμφανίζεται ένα κουμπί «Αναφορά ακαδημαϊκής ατασθαλίας» στις ενότητες λογοκλοπής, με σύνδεσμο σε εξωτερική φόρμα αναφοράς.';
$string['setting_report_form_url'] = 'Πρότυπο URL φόρμας αναφοράς';
$string['setting_report_form_url_desc'] = 'URL για τη φόρμα αναφοράς ακαδημαϊκής ατασθαλίας. Υποστηριζόμενα σύμβολα κράτησης θέσης: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Αυτά αντικαθίστανται κατά την εκτέλεση με τιμές κωδικοποιημένες σε URL. Για Microsoft Forms, χρησιμοποιήστε τη λειτουργία «Λήψη προσυμπληρωμένου URL» για να βρείτε τα ονόματα παραμέτρων.';
$string['report_impropriety'] = 'Αναφορά ακαδημαϊκής ατασθαλίας';

// Comment library v2.
$string['clib_title'] = 'Βιβλιοθήκη σχολίων';
$string['clib_all'] = 'Όλα';
$string['clib_quick_add'] = 'Γρήγορη προσθήκη σχολίου...';
$string['clib_manage'] = 'Διαχείριση βιβλιοθήκης';
$string['clib_no_comments'] = 'Δεν υπάρχουν σχόλια ακόμα.';
$string['clib_insert'] = 'Εισαγωγή';
$string['clib_copied'] = 'Το σχόλιο αντιγράφηκε στο πρόχειρο';
$string['clib_my_library'] = 'Η βιβλιοθήκη μου';
$string['clib_shared_library'] = 'Κοινόχρηστη βιβλιοθήκη';
$string['clib_new_comment'] = 'Νέο σχόλιο';
$string['clib_edit_comment'] = 'Επεξεργασία σχολίου';
$string['clib_delete_comment'] = 'Διαγραφή σχολίου';
$string['clib_confirm_delete'] = 'Είστε βέβαιοι ότι θέλετε να διαγράψετε αυτό το σχόλιο;';
$string['clib_share'] = 'Κοινοποίηση';
$string['clib_unshare'] = 'Κατάργηση κοινοποίησης';
$string['clib_import'] = 'Εισαγωγή';
$string['clib_imported'] = 'Το σχόλιο εισήχθη στη βιβλιοθήκη σας';
$string['clib_copy_to_course'] = 'Αντιγραφή σε μάθημα';
$string['clib_all_courses'] = 'Όλα τα μαθήματα';
$string['clib_tags'] = 'Ετικέτες';
$string['clib_manage_tags'] = 'Διαχείριση ετικετών';
$string['clib_new_tag'] = 'Νέα ετικέτα';
$string['clib_edit_tag'] = 'Επεξεργασία ετικέτας';
$string['clib_delete_tag'] = 'Διαγραφή ετικέτας';
$string['clib_confirm_delete_tag'] = 'Είστε βέβαιοι ότι θέλετε να διαγράψετε αυτή την ετικέτα; Θα αφαιρεθεί από όλα τα σχόλια.';
$string['clib_system_tag'] = 'Προεπιλογή συστήματος';
$string['clib_shared_by'] = 'Κοινοποιήθηκε από {$a}';
$string['clib_no_shared'] = 'Δεν υπάρχουν διαθέσιμα κοινόχρηστα σχόλια.';
$string['clib_picker_freetext'] = 'Ή γράψτε το δικό σας...';
$string['clib_picker_loading'] = 'Φόρτωση σχολίων...';
$string['clib_offline_mode'] = 'Εμφάνιση αποθηκευμένων σχολίων — η επεξεργασία δεν είναι διαθέσιμη χωρίς σύνδεση.';
$string['unifiedgrader:sharecomments'] = 'Κοινοποίηση σχολίων στη βιβλιοθήκη με άλλους εκπαιδευτικούς';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Καταχωρίσεις βιβλιοθήκης σχολίων στον ενοποιημένο βαθμολογητή.';
$string['privacy:metadata:clib:userid'] = 'Ο εκπαιδευτικός στον οποίο ανήκει το σχόλιο.';
$string['privacy:metadata:clib:coursecode'] = 'Ο κωδικός μαθήματος με τον οποίο σχετίζεται το σχόλιο.';
$string['privacy:metadata:clib:content'] = 'Το περιεχόμενο του σχολίου.';
$string['privacy:metadata:cltag'] = 'Ετικέτες βιβλιοθήκης σχολίων στον ενοποιημένο βαθμολογητή.';
$string['privacy:metadata:cltag:userid'] = 'Ο εκπαιδευτικός στον οποίο ανήκει η ετικέτα.';
$string['privacy:metadata:cltag:name'] = 'Το όνομα της ετικέτας.';

// Penalties.
$string['penalties'] = 'Ποινές';
$string['penalty_late'] = 'Εκπρόθεσμη υποβολή';
$string['penalty_late_days'] = '{$a} ημέρα(-ες) εκπρόθεσμα';
$string['penalty_late_auto'] = 'Αυτόματος υπολογισμός βάσει κανόνων ποινών';
$string['penalty_wordcount'] = 'Αριθμός λέξεων';
$string['penalty_other'] = 'Άλλο';
$string['penalty_custom'] = 'Προσαρμοσμένη';
$string['penalty_label_placeholder'] = 'Ετικέτα (μέγ. 15 χαρακτήρες)';
$string['penalty_active'] = 'Ενεργές ποινές';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['penalty_late_label'] = 'Καθυστερημένο';
$string['penalty_late_applied'] = 'Ποινή καθυστέρησης {$a}% εφαρμόστηκε';
$string['late_days'] = '{$a} ημέρες';
$string['late_day'] = '{$a} ημέρα';
$string['late_hours'] = '{$a} ώρες';
$string['late_hour'] = '{$a} ώρα';
$string['late_mins'] = '{$a} λεπτά';
$string['late_min'] = '{$a} λεπτό';
$string['late_lessthanmin'] = '< 1 λεπτό';
$string['finalgradeafterpenalties'] = 'Τελικός βαθμός μετά τις ποινές:';
$string['cannotdeleteautopenalty'] = 'Οι ποινές καθυστέρησης υπολογίζονται αυτόματα και δεν μπορούν να διαγραφούν.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Λήψη PDF ανατροφοδότησης';
$string['feedback_summary_overall_feedback'] = 'Συνολική ανατροφοδότηση';
$string['feedback_summary_graded_on'] = 'Βαθμολογήθηκε στις {$a}';
$string['feedback_summary_generated_by'] = 'Δημιουργήθηκε από τον ενοποιημένο βαθμολογητή';
$string['feedback_summary_media_note'] = 'Το περιεχόμενο πολυμέσων είναι διαθέσιμο στη διαδικτυακή προβολή ανατροφοδότησης.';
$string['feedback_summary_no_grade'] = 'Δ/Υ';
$string['feedback_summary_remark'] = 'Σχόλιο εκπαιδευτικού';
$string['feedback_summary_total'] = 'Σύνολο';
$string['levels'] = 'Επίπεδα';
$string['error_gs_not_configured'] = 'Το GhostScript δεν είναι ρυθμισμένο σε αυτόν τον διακομιστή Moodle. Ο διαχειριστής πρέπει να ορίσει τη διαδρομή GhostScript στο Διαχείριση ιστοτόπου > Πρόσθετα > Αρθρώματα δραστηριοτήτων > Εργασία > Ανατροφοδότηση > Σχολιασμός PDF.';
$string['error_pdf_combine_failed'] = 'Αποτυχία συνδυασμού αρχείων PDF: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Ποινές βαθμολόγησης που εφαρμόστηκαν από εκπαιδευτικούς στον ενοποιημένο βαθμολογητή.';
$string['privacy:metadata:penalty:userid'] = 'Ο σπουδαστής στον οποίο εφαρμόστηκε η ποινή.';
$string['privacy:metadata:penalty:authorid'] = 'Ο εκπαιδευτικός που εφάρμοσε την ποινή.';
$string['privacy:metadata:penalty:category'] = 'Η κατηγορία ποινής (αριθμός λέξεων ή άλλο).';
$string['privacy:metadata:penalty:label'] = 'Η προσαρμοσμένη ετικέτα της ποινής.';
$string['privacy:metadata:penalty:percentage'] = 'Το ποσοστό ποινής.';
$string['privacy:metadata:fext'] = 'Παρατάσεις προθεσμίας φόρουμ που χορηγήθηκαν από εκπαιδευτικούς στον ενοποιημένο βαθμολογητή.';
$string['privacy:metadata:fext:userid'] = 'Ο σπουδαστής στον οποίο χορηγήθηκε η παράταση.';
$string['privacy:metadata:fext:authorid'] = 'Ο εκπαιδευτικός που χορήγησε την παράταση.';
$string['privacy:metadata:fext:extensionduedate'] = 'Η παρατεταμένη προθεσμία.';
$string['privacy:metadata:qfb'] = 'Ανατροφοδότηση κουίζ ανά προσπάθεια που αποθηκεύεται από τον ενοποιημένο βαθμολογητή.';
$string['privacy:metadata:qfb:userid'] = 'Ο σπουδαστής για τον οποίο προορίζεται η ανατροφοδότηση.';
$string['privacy:metadata:qfb:grader'] = 'Ο εκπαιδευτικός που παρείχε την ανατροφοδότηση.';
$string['privacy:metadata:qfb:feedback'] = 'Το κείμενο ανατροφοδότησης.';
$string['privacy:metadata:qfb:attemptnumber'] = 'Ο αριθμός προσπάθειας κουίζ.';
$string['privacy_forum_extensions'] = 'Παρατάσεις φόρουμ';
$string['privacy_quiz_feedback'] = 'Ανατροφοδότηση κουίζ';

// Offline cache and save status.
$string['allchangessaved'] = 'Όλες οι αλλαγές αποθηκεύτηκαν';
$string['editing'] = 'Επεξεργασία...';
$string['offlinesavedlocally'] = 'Εκτός σύνδεσης — αποθηκεύτηκε τοπικά';
$string['connectionlost'] = 'Η σύνδεση χάθηκε — η εργασία σας αποθηκεύτηκε τοπικά και θα συγχρονιστεί όταν αποκατασταθεί η σύνδεση.';
$string['recoveredunsavedchanges'] = 'Ανακτήθηκαν μη αποθηκευμένες αλλαγές από την τελευταία σας συνεδρία.';
$string['restore'] = 'Επαναφορά';
$string['discard'] = 'Απόρριψη';
$string['mark_as_graded'] = 'Σήμανση ως βαθμολογημένο';
