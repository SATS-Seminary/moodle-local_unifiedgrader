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
 * German language strings for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Einheitlicher Bewerter';
$string['grading_interface'] = 'Einheitlicher Bewerter';
$string['nopermission'] = 'Sie haben keine Berechtigung, die einheitlichen Bewerter zu verwenden.';
$string['invalidactivitytype'] = 'Dieser Aktivit\u00e4tstyp wird von der einheitlichen Bewerter nicht unterst\u00fctzt.';
$string['invalidmodule'] = 'Ung\u00fcltiges Aktivit\u00e4tsmodul.';
$string['viewfeedback'] = 'Feedback anzeigen';

// Attempts.
$string['attempt'] = 'Versuch';

// Capabilities.
$string['unifiedgrader:grade'] = 'die einheitlichen Bewerter zum Bewerten verwenden';
$string['unifiedgrader:viewall'] = 'Alle Teilnehmer/innen in der einheitlichen Bewerter anzeigen';
$string['unifiedgrader:viewnotes'] = 'Private Dozentennotizen anzeigen';
$string['unifiedgrader:managenotes'] = 'Private Dozentennotizen erstellen und bearbeiten';
$string['unifiedgrader:viewfeedback'] = 'Kommentiertes Feedback aus der einheitlichen Bewerter anzeigen';

// Settings.
$string['setting_enable_assign'] = 'F\u00fcr Aufgaben aktivieren';
$string['setting_enable_assign_desc'] = 'Die einheitlichen Bewerter f\u00fcr Aufgabenaktivit\u00e4ten verwenden.';
$string['setting_enable_submission_comments'] = 'Abgabekommentare ersetzen';
$string['setting_enable_submission_comments_desc'] = 'Ersetzt Moodles Kern-Abgabekommentare in der Aufgabenansicht der Studierenden durch die Messenger-Kommentare des einheitlichen Bewerters (mit Benachrichtigungsunterst\u00fctzung). Studierende k\u00f6nnen Dozenten vor und nach der Bewertung Nachrichten senden.';
$string['setting_enable_forum'] = 'F\u00fcr Foren aktivieren';
$string['setting_enable_forum_desc'] = 'Die einheitlichen Bewerter f\u00fcr Forenaktivit\u00e4ten verwenden.';
$string['setting_enable_quiz'] = 'F\u00fcr Tests aktivieren';
$string['setting_enable_quiz_desc'] = 'Die einheitlichen Bewerter f\u00fcr Testaktivit\u00e4ten verwenden.';
$string['setting_enable_quiz_post_grades'] = 'Bewertungen ver\u00f6ffentlichen f\u00fcr Tests aktivieren';
$string['setting_enable_quiz_post_grades_desc'] = 'Die Sichtbarkeit der Testbewertung wird normalerweise \u00fcber die \u00dcberpr\u00fcfungsoptionen des Tests gesteuert. Wenn aktiviert, aktualisiert der Schalter \u201eBewertungen ver\u00f6ffentlichen\u201c in der einheitlichen Bewerter die \u00dcberpr\u00fcfungsoptionen des Tests programmatisch, um Bewertungen ein- oder auszublenden. Wenn deaktiviert (Standard), wird der Schalter f\u00fcr Tests ausgeblendet.';
$string['setting_allow_manual_override'] = 'Manuelle Bewertungs\u00fcberschreibung erlauben';
$string['setting_allow_manual_override_desc'] = 'Wenn aktiviert, k\u00f6nnen Dozenten manuell eine Bewertung eingeben, auch wenn ein Bewertungsraster oder ein Bewertungsleitfaden konfiguriert ist. Wenn deaktiviert, wird die Bewertung ausschlie\u00dflich aus den Kriterien des Bewertungsrasters oder Bewertungsleitfadens berechnet.';

// Grading interface.
$string['grade'] = 'Bewertung';
$string['savegrade'] = 'Bewertung speichern';
$string['savefeedback'] = 'Feedback speichern';
$string['savinggrade'] = 'Bewertung wird gespeichert...';
$string['gradesaved'] = 'Bewertung gespeichert';
$string['error_saving'] = 'Fehler beim Speichern der Bewertung.';
$string['error_network'] = 'Keine Verbindung zum Server m\u00f6glich. Bitte \u00fcberpr\u00fcfen Sie Ihre Verbindung und versuchen Sie es erneut.';
$string['error_offline_comments'] = 'Kommentare k\u00f6nnen im Offline-Modus nicht hinzugef\u00fcgt werden.';
$string['feedback'] = 'Feedback';
$string['overall_feedback'] = 'Gesamtfeedback';
$string['feedback_saved'] = 'Feedback (gespeichert)';
$string['edit_feedback'] = 'Bearbeiten';
$string['delete_feedback'] = 'L\u00f6schen';
$string['confirm_delete_feedback'] = 'M\u00f6chten Sie dieses Feedback wirklich l\u00f6schen? Die Bewertung bleibt erhalten.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Erweitern';

// Submissions.
$string['submission'] = 'Abgabe';
$string['nosubmission'] = 'Keine Abgabe';
$string['previewpanel'] = 'Abgabevorschau';
$string['markingpanel'] = 'Bewertungsbereich';
$string['onlinetext'] = 'Onlinetext';
$string['submittedfiles'] = 'Eingereichte Dateien';
$string['viewfile'] = 'Datei anzeigen';

// Participants.
$string['participants'] = 'Teilnehmer/innen';
$string['search'] = 'Teilnehmer/innen suchen...';
$string['sortby'] = 'Sortieren nach';
$string['sortby_fullname'] = 'Vollst\u00e4ndiger Name';
$string['sortby_submittedat'] = 'Abgabedatum';
$string['sortby_status'] = 'Status';
$string['filter_all'] = 'Alle Teilnehmer/innen';
$string['filter_submitted'] = 'Abgegeben';
$string['filter_needsgrading'] = 'Unbewertet';
$string['filter_notsubmitted'] = 'Nicht abgegeben';
$string['filter_graded'] = 'Bewertet';
$string['filter_late'] = 'Versp\u00e4tet';
$string['filter_allgroups'] = 'Alle Gruppen';
$string['filter_mygroups'] = 'Alle meine Gruppen';
$string['studentcount'] = '{$a->current} von {$a->total}';

// Statuses.
$string['status_draft'] = 'Entwurf';
$string['status_submitted'] = 'Abgegeben';
$string['status_graded'] = 'Bewertet';
$string['status_nosubmission'] = 'Keine Abgabe';
$string['status_needsgrading'] = 'Bewertung erforderlich';
$string['status_new'] = 'Nicht abgegeben';
$string['status_late'] = 'Versp\u00e4tet: {$a}';

// Teacher notes.
$string['notes'] = 'Dozentennotizen';
$string['notes_desc'] = 'Private Notizen, die nur f\u00fcr Dozenten und Moderatoren sichtbar sind.';
$string['savenote'] = 'Notiz speichern';
$string['deletenote'] = 'L\u00f6schen';
$string['addnote'] = 'Notiz hinzuf\u00fcgen';
$string['nonotes'] = 'Noch keine Notizen.';
$string['confirmdelete_note'] = 'M\u00f6chten Sie diese Notiz wirklich l\u00f6schen?';

// Comment library.
$string['commentlibrary'] = 'Kommentarbibliothek';
$string['savecomment'] = 'In Bibliothek speichern';
$string['insertcomment'] = 'Einf\u00fcgen';
$string['deletecomment'] = 'Entfernen';
$string['newcomment'] = 'Neuer Kommentar...';
$string['nocomments'] = 'Keine gespeicherten Kommentare.';

// UI.
$string['loading'] = 'Wird geladen...';
$string['saving'] = 'Wird gespeichert...';
$string['saved'] = 'Gespeichert';
$string['previousstudent'] = 'Vorheriger Teilnehmer';
$string['nextstudent'] = 'N\u00e4chster Teilnehmer';
$string['expandfilters'] = 'Filter anzeigen';
$string['collapsefilters'] = 'Filter ausblenden';
$string['backtocourse'] = 'Zur\u00fcck zum Kurs';
$string['rubric'] = 'Bewertungsraster';
$string['markingguide'] = 'Bewertungsleitfaden';
$string['criterion'] = 'Kriterium';
$string['score'] = 'Punktzahl';
$string['remark'] = 'Anmerkung';
$string['total'] = 'Gesamt: {$a}';
$string['viewallsubmissions'] = 'Alle Abgaben anzeigen';
$string['layout_both'] = 'Geteilte Ansicht';
$string['layout_preview'] = 'Nur Vorschau';
$string['layout_grade'] = 'Nur Bewertung';
$string['manualquestions'] = 'Manuelle Fragen';
$string['response'] = 'Antwort';
$string['teachercomment'] = 'Dozentenkommentar';

// Submission comments.
$string['submissioncomments'] = 'Abgabekommentare';
$string['nocommentsyet'] = 'Noch keine Kommentare';
$string['addcomment'] = 'Kommentar hinzuf\u00fcgen...';
$string['postcomment'] = 'Absenden';
$string['deletesubmissioncomment'] = 'Kommentar l\u00f6schen';

// Feedback files.
$string['feedbackfiles'] = 'Feedback-Dateien';

// Plagiarism.
$string['plagiarism'] = 'Plagiat';
$string['plagiarism_noresults'] = 'Keine Plagiatsergebnisse verf\u00fcgbar.';
$string['plagiarism_pending'] = 'Plagiatspr\u00fcfung l\u00e4uft';
$string['plagiarism_error'] = 'Plagiatspr\u00fcfung fehlgeschlagen';

// Student feedback view.
$string['assessment_criteria'] = 'Bewertungskriterien';
$string['teacher_remark'] = 'Dozentenfeedback';
$string['view_feedback'] = 'Feedback anzeigen';
$string['view_annotated_feedback'] = 'Kommentiertes Feedback anzeigen';
$string['feedback_not_available'] = 'Ihr Feedback ist noch nicht verf\u00fcgbar. Bitte schauen Sie sp\u00e4ter noch einmal nach, wenn Ihre Abgabe bewertet und freigegeben wurde.';
$string['no_annotated_files'] = 'Es gibt keine kommentierten PDF-Dateien f\u00fcr Ihre Abgabe.';
$string['feedback_banner_default'] = 'Ihr Dozent hat Feedback zu Ihrer Abgabe gegeben.';

// Document conversion.
$string['conversion_failed'] = 'Diese Datei konnte nicht zur Vorschau in PDF konvertiert werden.';
$string['converting_file'] = 'Dokument wird in PDF konvertiert...';
$string['conversion_timeout'] = 'Die Dokumentenkonvertierung dauert zu lange. Bitte versuchen Sie es sp\u00e4ter erneut.';
$string['download_annotated_pdf'] = 'Kommentiertes PDF herunterladen';
$string['download_original_submission'] = 'Originalabgabe herunterladen: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Private Dozentennotizen, die pro Teilnehmer und Aktivit\u00e4t in der einheitlichen Bewerter gespeichert werden.';
$string['privacy:metadata:notes:cmid'] = 'Die Kursmodul-ID, auf die sich die Notiz bezieht.';
$string['privacy:metadata:notes:userid'] = 'Der/die Teilnehmer/in, auf den/die sich die Notiz bezieht.';
$string['privacy:metadata:notes:authorid'] = 'Der Dozent, der die Notiz geschrieben hat.';
$string['privacy:metadata:notes:content'] = 'Der Inhalt der Notiz.';
$string['privacy:metadata:comments'] = 'Wiederverwendbare Kommentarbibliothekseintr\u00e4ge in der einheitlichen Bewerter.';
$string['privacy:metadata:comments:userid'] = 'Der Dozent, dem der Kommentar geh\u00f6rt.';
$string['privacy:metadata:comments:content'] = 'Der Inhalt des Kommentars.';
$string['privacy:metadata:preferences'] = 'Benutzereinstellungen f\u00fcr die Oberfl\u00e4che der einheitlichen Bewerter.';
$string['privacy:metadata:preferences:userid'] = 'Der Benutzer, dem die Einstellungen geh\u00f6ren.';
$string['privacy:metadata:preferences:data'] = 'Die JSON-kodierten Einstellungsdaten.';
$string['privacy:metadata:annotations'] = 'Dokumentannotationen, die in der einheitlichen Bewerter gespeichert werden.';
$string['privacy:metadata:annotations:cmid'] = 'Die Kursmodul-ID, auf die sich die Annotation bezieht.';
$string['privacy:metadata:annotations:userid'] = 'Der/die Teilnehmer/in, dessen/deren Abgabe annotiert wurde.';
$string['privacy:metadata:annotations:authorid'] = 'Der Dozent, der die Annotation erstellt hat.';
$string['privacy:metadata:annotations:data'] = 'Die Annotationsdaten (Fabric.js JSON).';
$string['annotations'] = 'Annotationen';

// PDF viewer.
$string['pdf_prevpage'] = 'Vorherige Seite';
$string['pdf_nextpage'] = 'N\u00e4chste Seite';
$string['pdf_zoomin'] = 'Vergr\u00f6\u00dfern';
$string['pdf_zoomout'] = 'Verkleinern';
$string['pdf_zoomfit'] = 'An Breite anpassen';
$string['pdf_search'] = 'Im Dokument suchen';

// Annotation tools.
$string['annotate_tools'] = 'Annotationswerkzeuge';
$string['annotate_select'] = 'Ausw\u00e4hlen';
$string['annotate_textselect'] = 'Text ausw\u00e4hlen';
$string['annotate_comment'] = 'Kommentar';
$string['annotate_highlight'] = 'Hervorheben';
$string['annotate_pen'] = 'Stift';
$string['annotate_pen_fine'] = 'Fein';
$string['annotate_pen_medium'] = 'Mittel';
$string['annotate_pen_thick'] = 'Dick';
$string['annotate_stamps'] = 'Stempel';
$string['annotate_stamp_check'] = 'H\u00e4kchen-Stempel';
$string['annotate_stamp_cross'] = 'Kreuz-Stempel';
$string['annotate_stamp_question'] = 'Fragezeichen-Stempel';
$string['annotate_red'] = 'Rot';
$string['annotate_yellow'] = 'Gelb';
$string['annotate_green'] = 'Gr\u00fcn';
$string['annotate_blue'] = 'Blau';
$string['annotate_black'] = 'Schwarz';
$string['annotate_shape'] = 'Formen';
$string['annotate_shape_rect'] = 'Rechteck';
$string['annotate_shape_circle'] = 'Kreis';
$string['annotate_shape_arrow'] = 'Pfeil';
$string['annotate_shape_line'] = 'Linie';
$string['annotate_undo'] = 'R\u00fcckg\u00e4ngig';
$string['annotate_redo'] = 'Wiederherstellen';
$string['annotate_delete'] = 'Auswahl l\u00f6schen';
$string['annotate_clearall'] = 'Alles l\u00f6schen';
$string['annotate_clear_confirm'] = 'M\u00f6chten Sie wirklich alle Annotationen auf dieser Seite l\u00f6schen? Dies kann nicht r\u00fcckg\u00e4ngig gemacht werden.';

// Document info.
$string['docinfo'] = 'Dokumentinformationen';
$string['docinfo_filename'] = 'Dateiname';
$string['docinfo_filesize'] = 'Dateigr\u00f6\u00dfe';
$string['docinfo_pages'] = 'Seiten';
$string['docinfo_wordcount'] = 'Wortanzahl';
$string['docinfo_author'] = 'Autor';
$string['docinfo_creator'] = 'Ersteller';
$string['docinfo_created'] = 'Erstellt';
$string['docinfo_modified'] = 'Ge\u00e4ndert';
$string['docinfo_calculating'] = 'Wird berechnet...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Forum-Feedback anzeigen';
$string['forum_your_posts'] = 'Ihre Forenbeitr\u00e4ge';
$string['forum_no_posts'] = 'Sie haben noch keine Beitr\u00e4ge in diesem Forum verfasst.';
$string['forum_feedback_banner'] = 'Ihr Dozent hat Ihre Forenbeteiligung bewertet.';
$string['forum_wordcount'] = '{$a} W\u00f6rter';
$string['forum_posts_pill'] = 'Beitr\u00e4ge';
$string['submission_content_pill'] = 'Abgabe';
$string['forum_tab_posts'] = 'Beitr\u00e4ge';
$string['forum_tab_files'] = 'Kommentierte Dateien';
$string['view_quiz_feedback'] = 'Test-Feedback anzeigen';
$string['quiz_feedback_banner'] = 'Ihr Dozent hat Feedback zu Ihrem Test gegeben.';
$string['quiz_your_attempt'] = 'Ihr Versuch';
$string['quiz_no_attempt'] = 'Sie haben noch keinen Versuch f\u00fcr diesen Test abgeschlossen.';
$string['quiz_select_attempt'] = 'Versuch ausw\u00e4hlen';
$string['select_attempt'] = 'Versuch ausw\u00e4hlen';
$string['attempt_label'] = 'Versuch {$a}';

// Post grades.
$string['grades_posted'] = 'Bewertungen ver\u00f6ffentlicht';
$string['grades_hidden'] = 'Bewertungen verborgen';
$string['post_grades'] = 'Bewertungen ver\u00f6ffentlichen';
$string['unpost_grades'] = 'Bewertungen verbergen';
$string['confirm_post_grades'] = 'Alle Bewertungen f\u00fcr diese Aktivit\u00e4t ver\u00f6ffentlichen? Teilnehmer/innen k\u00f6nnen dann ihre Bewertungen und ihr Feedback sehen.';
$string['confirm_unpost_grades'] = 'Alle Bewertungen f\u00fcr diese Aktivit\u00e4t verbergen? Teilnehmer/innen k\u00f6nnen ihre Bewertungen und ihr Feedback dann nicht mehr sehen.';
$string['schedule_post'] = 'An einem Datum ver\u00f6ffentlichen';
$string['schedule_post_btn'] = 'Planen';
$string['grades_scheduled'] = 'Ver\u00f6ffentlichung {$a}';
$string['schedule_must_be_future'] = 'Das geplante Datum muss in der Zukunft liegen.';
$string['quiz_post_grades_disabled'] = 'Die Ver\u00f6ffentlichung von Bewertungen ist f\u00fcr Tests nicht verf\u00fcgbar. Die Sichtbarkeit der Bewertungen wird \u00fcber die \u00dcberpr\u00fcfungsoptionen des Tests gesteuert.';
$string['quiz_post_grades_no_schedule'] = 'Zeitplanung ist f\u00fcr Tests nicht verf\u00fcgbar. Verwenden Sie stattdessen Ver\u00f6ffentlichen oder Verbergen.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Auf Entwurf zur\u00fccksetzen';
$string['action_remove_submission'] = 'Abgabe entfernen';
$string['action_lock'] = 'Abgabe\u00e4nderungen verhindern';
$string['action_unlock'] = 'Abgabe\u00e4nderungen erlauben';
$string['action_edit_submission'] = 'Abgabe bearbeiten';
$string['action_grant_extension'] = 'Verl\u00e4ngerung gew\u00e4hren';
$string['action_edit_extension'] = 'Verl\u00e4ngerung bearbeiten';
$string['action_submit_for_grading'] = 'Zur Bewertung einreichen';
$string['confirm_revert_to_draft'] = 'M\u00f6chten Sie diese Abgabe wirklich auf den Entwurfsstatus zur\u00fccksetzen?';
$string['confirm_remove_submission'] = 'M\u00f6chten Sie diese Abgabe wirklich entfernen? Dies kann nicht r\u00fcckg\u00e4ngig gemacht werden.';
$string['confirm_lock_submission'] = 'Diesen Teilnehmer daran hindern, \u00c4nderungen an der Abgabe vorzunehmen?';
$string['confirm_unlock_submission'] = 'Diesem Teilnehmer erlauben, \u00c4nderungen an der Abgabe vorzunehmen?';
$string['confirm_submit_for_grading'] = 'Diesen Entwurf im Namen des Teilnehmers einreichen?';
$string['invalidaction'] = 'Ung\u00fcltige Abgabeaktion.';

// Override actions.
$string['override'] = '\u00dcberschreibung';
$string['action_add_override'] = '\u00dcberschreibung hinzuf\u00fcgen';
$string['action_edit_override'] = '\u00dcberschreibung bearbeiten';
$string['action_delete_override'] = '\u00dcberschreibung l\u00f6schen';
$string['confirm_delete_override'] = 'M\u00f6chten Sie diese Benutzer\u00fcberschreibung wirklich l\u00f6schen?';
$string['override_saved'] = '\u00dcberschreibung erfolgreich gespeichert.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Verl\u00e4ngerung l\u00f6schen';
$string['confirm_delete_extension'] = 'M\u00f6chten Sie diese F\u00e4lligkeitsdatum-Verl\u00e4ngerung wirklich l\u00f6schen?';
$string['quiz_extension_original_duedate'] = 'Urspr\u00fcngliches F\u00e4lligkeitsdatum';
$string['quiz_extension_current_extension'] = 'Aktuelle Verl\u00e4ngerung';
$string['quiz_extension_new_duedate'] = 'Neues F\u00e4lligkeitsdatum';
$string['quiz_extension_must_be_after_duedate'] = 'Das Verl\u00e4ngerungsdatum muss nach dem aktuellen F\u00e4lligkeitsdatum liegen.';
$string['quiz_extension_plugin_missing'] = 'Das Plugin quizaccess_duedate wird f\u00fcr Testverl\u00e4ngerungen ben\u00f6tigt, ist aber nicht installiert.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Forum-F\u00e4lligkeitsdatum';
$string['forum_extension_current_extension'] = 'Aktuelle Verl\u00e4ngerung';
$string['forum_extension_new_duedate'] = 'Neues F\u00e4lligkeitsdatum';
$string['forum_extension_must_be_after_duedate'] = 'Das Verl\u00e4ngerungsdatum muss nach dem Forum-F\u00e4lligkeitsdatum liegen.';

// Student profile popout.
$string['profile_view_full'] = 'Vollst\u00e4ndiges Profil anzeigen';
$string['profile_login_as'] = 'Anmelden als';
$string['profile_no_email'] = 'Keine E-Mail-Adresse verf\u00fcgbar';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Kurscode-Regex';
$string['setting_coursecode_regex_desc'] = 'Die Kommentarbibliothek organisiert gespeicherte Kommentare nach Kurscode, damit Dozenten Feedback \u00fcber verschiedene Durchf\u00fchrungen desselben Kurses wiederverwenden k\u00f6nnen (z.B. von Semester zu Semester). Diese Einstellung steuert, wie Kurscodes aus den Moodle-Kurznamen extrahiert werden. Geben Sie ein PHP-Regex-Muster ein, das den Code-Teil Ihrer Kurznamen erkennt (z.B. <code>/[A-Z]{3}\\d{4}/</code> w\u00fcrde <strong>THE2201</strong> aus einem Kurznamen wie <em>THE2201-2026-S1</em> extrahieren). Leer lassen, um den vollst\u00e4ndigen Kurznamen als Kurscode zu verwenden.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Meldeformular f\u00fcr akademisches Fehlverhalten aktivieren';
$string['setting_enable_report_form_desc'] = 'Wenn aktiviert, erscheint in den Plagiatsabschnitten eine Schaltfl\u00e4che \u201eAkademisches Fehlverhalten melden\u201c, die auf ein externes Meldeformular verlinkt.';
$string['setting_report_form_url'] = 'URL-Vorlage f\u00fcr Meldeformular';
$string['setting_report_form_url_desc'] = 'URL f\u00fcr das Meldeformular f\u00fcr akademisches Fehlverhalten. Unterst\u00fctzte Platzhalter: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Diese werden zur Laufzeit durch URL-kodierte Werte ersetzt. F\u00fcr Microsoft Forms verwenden Sie die Funktion \u201eVorausgef\u00fcllte URL abrufen\u201c, um Parameternamen zu finden.';
$string['report_impropriety'] = 'Akademisches Fehlverhalten melden';

// Comment library v2.
$string['clib_title'] = 'Kommentarbibliothek';
$string['clib_all'] = 'Alle';
$string['clib_quick_add'] = 'Kommentar schnell hinzuf\u00fcgen...';
$string['clib_manage'] = 'Bibliothek verwalten';
$string['clib_no_comments'] = 'Noch keine Kommentare.';
$string['clib_insert'] = 'Einf\u00fcgen';
$string['clib_copied'] = 'Kommentar in die Zwischenablage kopiert';
$string['clib_my_library'] = 'Meine Bibliothek';
$string['clib_shared_library'] = 'Geteilte Bibliothek';
$string['clib_new_comment'] = 'Neuer Kommentar';
$string['clib_edit_comment'] = 'Kommentar bearbeiten';
$string['clib_delete_comment'] = 'Kommentar l\u00f6schen';
$string['clib_confirm_delete'] = 'M\u00f6chten Sie diesen Kommentar wirklich l\u00f6schen?';
$string['clib_share'] = 'Teilen';
$string['clib_unshare'] = 'Freigabe aufheben';
$string['clib_import'] = 'Importieren';
$string['clib_imported'] = 'Kommentar in Ihre Bibliothek importiert';
$string['clib_copy_to_course'] = 'In Kurs kopieren';
$string['clib_all_courses'] = 'Alle Kurse';
$string['clib_tags'] = 'Schlagw\u00f6rter';
$string['clib_manage_tags'] = 'Schlagw\u00f6rter verwalten';
$string['clib_new_tag'] = 'Neues Schlagwort';
$string['clib_edit_tag'] = 'Schlagwort bearbeiten';
$string['clib_delete_tag'] = 'Schlagwort l\u00f6schen';
$string['clib_confirm_delete_tag'] = 'M\u00f6chten Sie dieses Schlagwort wirklich l\u00f6schen? Es wird von allen Kommentaren entfernt.';
$string['clib_system_tag'] = 'Systemstandard';
$string['clib_shared_by'] = 'Geteilt von {$a}';
$string['clib_no_shared'] = 'Keine geteilten Kommentare verf\u00fcgbar.';
$string['clib_picker_freetext'] = 'Oder eigenen Text schreiben...';
$string['clib_picker_loading'] = 'Kommentare werden geladen...';
$string['clib_offline_mode'] = 'Zwischengespeicherte Kommentare werden angezeigt \u2014 Bearbeitung ist offline nicht m\u00f6glich.';
$string['unifiedgrader:sharecomments'] = 'Kommentare in der Bibliothek mit anderen Dozenten teilen';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Kommentarbibliothekseintr\u00e4ge in der einheitlichen Bewerter.';
$string['privacy:metadata:clib:userid'] = 'Der Dozent, dem der Kommentar geh\u00f6rt.';
$string['privacy:metadata:clib:coursecode'] = 'Der Kurscode, dem der Kommentar zugeordnet ist.';
$string['privacy:metadata:clib:content'] = 'Der Inhalt des Kommentars.';
$string['privacy:metadata:cltag'] = 'Kommentarbibliotheks-Schlagw\u00f6rter in der einheitlichen Bewerter.';
$string['privacy:metadata:cltag:userid'] = 'Der Dozent, dem das Schlagwort geh\u00f6rt.';
$string['privacy:metadata:cltag:name'] = 'Der Name des Schlagworts.';

// Penalties.
$string['penalties'] = 'Abz\u00fcge';
$string['penalty_late'] = 'Versp\u00e4tete Abgabe';
$string['penalty_late_days'] = '{$a} Tag(e) versp\u00e4tet';
$string['penalty_late_auto'] = 'Automatisch auf Basis der Abzugsregeln berechnet';
$string['penalty_wordcount'] = 'Wortanzahl';
$string['penalty_other'] = 'Sonstiges';
$string['penalty_custom'] = 'Benutzerdefiniert';
$string['penalty_label_placeholder'] = 'Bezeichnung (max. 15 Zeichen)';
$string['penalty_active'] = 'Aktive Abz\u00fcge';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['penalty_late_label'] = 'Verspätet';
$string['penalty_late_applied'] = 'Verspätungsabzug von {$a}% angewendet';
$string['late_days'] = '{$a} Tage';
$string['late_day'] = '{$a} Tag';
$string['late_hours'] = '{$a} Stunden';
$string['late_hour'] = '{$a} Stunde';
$string['late_mins'] = '{$a} Min.';
$string['late_min'] = '{$a} Min.';
$string['late_lessthanmin'] = '< 1 Min.';
$string['finalgradeafterpenalties'] = 'Endnote nach Abz\u00fcgen:';
$string['cannotdeleteautopenalty'] = 'Versp\u00e4tungsabz\u00fcge werden automatisch berechnet und k\u00f6nnen nicht gel\u00f6scht werden.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Feedback-PDF herunterladen';
$string['feedback_summary_overall_feedback'] = 'Gesamtfeedback';
$string['feedback_summary_graded_on'] = 'Bewertet am {$a}';
$string['feedback_summary_generated_by'] = 'Erstellt von der einheitlichen Bewerter';
$string['feedback_summary_media_note'] = 'Medieninhalte sind in der Online-Feedback-Ansicht verf\u00fcgbar.';
$string['feedback_summary_no_grade'] = 'k.A.';
$string['feedback_summary_remark'] = 'Dozentenkommentar';
$string['feedback_summary_total'] = 'Gesamt';
$string['levels'] = 'Stufen';
$string['error_gs_not_configured'] = 'GhostScript ist auf diesem Moodle-Server nicht konfiguriert. Der Administrator muss den GhostScript-Pfad unter Website-Administration > Plugins > Aktivit\u00e4ten > Aufgabe > Feedback > PDF annotieren festlegen.';
$string['error_pdf_combine_failed'] = 'PDF-Dateien konnten nicht zusammengef\u00fchrt werden: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Von Dozenten in der einheitlichen Bewerter angewendete Bewertungsabz\u00fcge.';
$string['privacy:metadata:penalty:userid'] = 'Der/die Teilnehmer/in, auf den/die der Abzug angewendet wurde.';
$string['privacy:metadata:penalty:authorid'] = 'Der Dozent, der den Abzug angewendet hat.';
$string['privacy:metadata:penalty:category'] = 'Die Abzugskategorie (Wortanzahl oder Sonstiges).';
$string['privacy:metadata:penalty:label'] = 'Die benutzerdefinierte Bezeichnung des Abzugs.';
$string['privacy:metadata:penalty:percentage'] = 'Der Abzugsprozentsatz.';
$string['privacy:metadata:fext'] = 'Von Dozenten in der einheitlichen Bewerter gew\u00e4hrte Foren-F\u00e4lligkeitsverl\u00e4ngerungen.';
$string['privacy:metadata:fext:userid'] = 'Der/die Teilnehmer/in, dem/der die Verl\u00e4ngerung gew\u00e4hrt wurde.';
$string['privacy:metadata:fext:authorid'] = 'Der Dozent, der die Verl\u00e4ngerung gew\u00e4hrt hat.';
$string['privacy:metadata:fext:extensionduedate'] = 'Das verl\u00e4ngerte F\u00e4lligkeitsdatum.';
$string['privacy:metadata:qfb'] = 'Von der einheitlichen Bewerter gespeichertes versuchsbezogenes Test-Feedback.';
$string['privacy:metadata:qfb:userid'] = 'Der/die Teilnehmer/in, f\u00fcr den/die das Feedback bestimmt ist.';
$string['privacy:metadata:qfb:grader'] = 'Der Dozent, der das Feedback gegeben hat.';
$string['privacy:metadata:qfb:feedback'] = 'Der Feedbacktext.';
$string['privacy:metadata:qfb:attemptnumber'] = 'Die Testversuchsnummer.';
$string['privacy:metadata:scomm'] = 'Abgabekommentare, die vom Unified Grader gespeichert werden.';
$string['privacy:metadata:scomm:cmid'] = 'Das Kursmodul, zu dem der Kommentar gehört.';
$string['privacy:metadata:scomm:userid'] = 'Der Student, über den der Kommentarthread handelt.';
$string['privacy:metadata:scomm:authorid'] = 'Der Benutzer, der den Kommentar geschrieben hat.';
$string['privacy:metadata:scomm:content'] = 'Der Kommentarinhalt.';
$string['privacy_forum_extensions'] = 'Foren-Verl\u00e4ngerungen';
$string['privacy_quiz_feedback'] = 'Test-Feedback';

// Notification strings.
$string['messageprovider:submission_comment'] = 'Benachrichtigungen über Abgabekommentare';
$string['notification_comment_subject'] = 'Neuer Kommentar zu {$a->activityname}';
$string['notification_comment_body'] = '<p><strong>{$a->authorfullname}</strong> hat einen Kommentar zu <a href="{$a->activityurl}">{$a->activityname}</a> in {$a->coursename} ({$a->timecreated}) gepostet:</p><blockquote>{$a->content}</blockquote>';
$string['notification_comment_small'] = '{$a->authorfullname} hat {$a->activityname} kommentiert';

// Offline cache and save status.
$string['allchangessaved'] = 'Alle \u00c4nderungen gespeichert';
$string['editing'] = 'Wird bearbeitet...';
$string['offlinesavedlocally'] = 'Offline \u2014 lokal gespeichert';
$string['connectionlost'] = 'Verbindung verloren \u2014 Ihre Arbeit wird lokal gespeichert und synchronisiert, sobald die Verbindung wiederhergestellt ist.';
$string['recoveredunsavedchanges'] = 'Nicht gespeicherte \u00c4nderungen aus Ihrer letzten Sitzung wurden wiederhergestellt.';
$string['restore'] = 'Wiederherstellen';
$string['discard'] = 'Verwerfen';
$string['mark_as_graded'] = 'Als bewertet markieren';
