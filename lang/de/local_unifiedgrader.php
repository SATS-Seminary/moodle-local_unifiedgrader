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
$string['invalidactivitytype'] = 'Dieser Aktivitätstyp wird von der einheitlichen Bewerter nicht unterstützt.';
$string['invalidmodule'] = 'Ungültiges Aktivitätsmodul.';
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
$string['setting_enable_assign'] = 'Für Aufgaben aktivieren';
$string['setting_enable_assign_desc'] = 'Die einheitlichen Bewerter für Aufgabenaktivitäten verwenden.';
$string['setting_enable_submission_comments'] = 'Abgabekommentare ersetzen';
$string['setting_enable_submission_comments_desc'] = 'Ersetzt Moodles Kern-Abgabekommentare in der Aufgabenansicht der Studierenden durch die Messenger-Kommentare des einheitlichen Bewerters (mit Benachrichtigungsunterstützung). Studierende können Dozenten vor und nach der Bewertung Nachrichten senden.';
$string['setting_enable_forum'] = 'Für Foren aktivieren';
$string['setting_enable_forum_desc'] = 'Die einheitlichen Bewerter für Forenaktivitäten verwenden.';
$string['setting_enable_quiz'] = 'Für Tests aktivieren';
$string['setting_enable_quiz_desc'] = 'Die einheitlichen Bewerter für Testaktivitäten verwenden.';
$string['setting_enable_quiz_post_grades'] = 'Bewertungen veröffentlichen für Tests aktivieren';
$string['setting_enable_quiz_post_grades_desc'] = 'Die Sichtbarkeit der Testbewertung wird normalerweise über die Überprüfungsoptionen des Tests gesteuert. Wenn aktiviert, aktualisiert der Schalter „Bewertungen veröffentlichen“ in der einheitlichen Bewerter die Überprüfungsoptionen des Tests programmatisch, um Bewertungen ein- oder auszublenden. Wenn deaktiviert (Standard), wird der Schalter für Tests ausgeblendet.';
$string['setting_allow_manual_override'] = 'Manuelle Bewertungsüberschreibung erlauben';
$string['setting_allow_manual_override_desc'] = 'Wenn aktiviert, können Dozenten manuell eine Bewertung eingeben, auch wenn ein Bewertungsraster oder ein Bewertungsleitfaden konfiguriert ist. Wenn deaktiviert, wird die Bewertung ausschließlich aus den Kriterien des Bewertungsrasters oder Bewertungsleitfadens berechnet.';

// Grading interface.
$string['grade'] = 'Bewertung';
$string['savegrade'] = 'Bewertung speichern';
$string['savefeedback'] = 'Feedback speichern';
$string['savinggrade'] = 'Bewertung wird gespeichert...';
$string['gradesaved'] = 'Bewertung gespeichert';
$string['error_saving'] = 'Fehler beim Speichern der Bewertung.';
$string['error_network'] = 'Keine Verbindung zum Server möglich. Bitte überprüfen Sie Ihre Verbindung und versuchen Sie es erneut.';
$string['error_offline_comments'] = 'Kommentare können im Offline-Modus nicht hinzugefügt werden.';
$string['feedback'] = 'Feedback';
$string['overall_feedback'] = 'Gesamtfeedback';
$string['feedback_saved'] = 'Feedback (gespeichert)';
$string['edit_feedback'] = 'Bearbeiten';
$string['delete_feedback'] = 'Löschen';
$string['confirm_delete_feedback'] = 'Möchten Sie dieses Feedback wirklich löschen? Die Bewertung bleibt erhalten.';
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
$string['sortby_fullname'] = 'Vollständiger Name';
$string['sortby_submittedat'] = 'Abgabedatum';
$string['sortby_status'] = 'Status';
$string['filter_all'] = 'Alle Teilnehmer/innen';
$string['filter_submitted'] = 'Abgegeben';
$string['filter_needsgrading'] = 'Unbewertet';
$string['filter_notsubmitted'] = 'Nicht abgegeben';
$string['filter_graded'] = 'Bewertet';
$string['filter_late'] = 'Verspätet';
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
$string['status_late'] = 'Verspätet: {$a}';

// Teacher notes.
$string['notes'] = 'Dozentennotizen';
$string['notes_desc'] = 'Private Notizen, die nur für Dozenten und Moderatoren sichtbar sind.';
$string['savenote'] = 'Notiz speichern';
$string['deletenote'] = 'Löschen';
$string['addnote'] = 'Notiz hinzufügen';
$string['nonotes'] = 'Noch keine Notizen.';
$string['confirmdelete_note'] = 'Möchten Sie diese Notiz wirklich löschen?';

// Comment library.
$string['commentlibrary'] = 'Kommentarbibliothek';
$string['savecomment'] = 'In Bibliothek speichern';
$string['insertcomment'] = 'Einfügen';
$string['deletecomment'] = 'Entfernen';
$string['newcomment'] = 'Neuer Kommentar...';
$string['nocomments'] = 'Keine gespeicherten Kommentare.';

// UI.
$string['loading'] = 'Wird geladen...';
$string['saving'] = 'Wird gespeichert...';
$string['saved'] = 'Gespeichert';
$string['previousstudent'] = 'Vorheriger Teilnehmer';
$string['nextstudent'] = 'Nächster Teilnehmer';
$string['expandfilters'] = 'Filter anzeigen';
$string['collapsefilters'] = 'Filter ausblenden';
$string['backtocourse'] = 'Zurück zum Kurs';
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
$string['addcomment'] = 'Kommentar hinzufügen...';
$string['postcomment'] = 'Absenden';
$string['deletesubmissioncomment'] = 'Kommentar löschen';

// Feedback files.
$string['feedbackfiles'] = 'Feedback-Dateien';

// Plagiarism.
$string['plagiarism'] = 'Plagiat';
$string['plagiarism_noresults'] = 'Keine Plagiatsergebnisse verfügbar.';
$string['plagiarism_pending'] = 'Plagiatsprüfung läuft';
$string['plagiarism_error'] = 'Plagiatsprüfung fehlgeschlagen';

// Student feedback view.
$string['assessment_criteria'] = 'Bewertungskriterien';
$string['teacher_remark'] = 'Dozentenfeedback';
$string['view_feedback'] = 'Feedback anzeigen';
$string['view_annotated_feedback'] = 'Kommentiertes Feedback anzeigen';
$string['feedback_not_available'] = 'Ihr Feedback ist noch nicht verfügbar. Bitte schauen Sie später noch einmal nach, wenn Ihre Abgabe bewertet und freigegeben wurde.';
$string['no_annotated_files'] = 'Es gibt keine kommentierten PDF-Dateien für Ihre Abgabe.';
$string['feedback_banner_default'] = 'Ihr Dozent hat Feedback zu Ihrer Abgabe gegeben.';

// Document conversion.
$string['conversion_failed'] = 'Diese Datei konnte nicht zur Vorschau in PDF konvertiert werden.';
$string['converting_file'] = 'Dokument wird in PDF konvertiert...';
$string['conversion_timeout'] = 'Die Dokumentenkonvertierung dauert zu lange. Bitte versuchen Sie es später erneut.';
$string['download_annotated_pdf'] = 'Kommentiertes PDF herunterladen';
$string['download_original_submission'] = 'Originalabgabe herunterladen: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Private Dozentennotizen, die pro Teilnehmer und Aktivität in der einheitlichen Bewerter gespeichert werden.';
$string['privacy:metadata:notes:cmid'] = 'Die Kursmodul-ID, auf die sich die Notiz bezieht.';
$string['privacy:metadata:notes:userid'] = 'Der/die Teilnehmer/in, auf den/die sich die Notiz bezieht.';
$string['privacy:metadata:notes:authorid'] = 'Der Dozent, der die Notiz geschrieben hat.';
$string['privacy:metadata:notes:content'] = 'Der Inhalt der Notiz.';
$string['privacy:metadata:comments'] = 'Wiederverwendbare Kommentarbibliothekseinträge in der einheitlichen Bewerter.';
$string['privacy:metadata:comments:userid'] = 'Der Dozent, dem der Kommentar gehört.';
$string['privacy:metadata:comments:content'] = 'Der Inhalt des Kommentars.';
$string['privacy:metadata:preferences'] = 'Benutzereinstellungen für die Oberfläche der einheitlichen Bewerter.';
$string['privacy:metadata:preferences:userid'] = 'Der Benutzer, dem die Einstellungen gehören.';
$string['privacy:metadata:preferences:data'] = 'Die JSON-kodierten Einstellungsdaten.';
$string['privacy:metadata:annotations'] = 'Dokumentannotationen, die in der einheitlichen Bewerter gespeichert werden.';
$string['privacy:metadata:annotations:cmid'] = 'Die Kursmodul-ID, auf die sich die Annotation bezieht.';
$string['privacy:metadata:annotations:userid'] = 'Der/die Teilnehmer/in, dessen/deren Abgabe annotiert wurde.';
$string['privacy:metadata:annotations:authorid'] = 'Der Dozent, der die Annotation erstellt hat.';
$string['privacy:metadata:annotations:data'] = 'Die Annotationsdaten (Fabric.js JSON).';
$string['annotations'] = 'Annotationen';

// PDF viewer.
$string['pdf_prevpage'] = 'Vorherige Seite';
$string['pdf_nextpage'] = 'Nächste Seite';
$string['pdf_zoomin'] = 'Vergrößern';
$string['pdf_zoomout'] = 'Verkleinern';
$string['pdf_zoomfit'] = 'An Breite anpassen';
$string['pdf_search'] = 'Im Dokument suchen';

// Annotation tools.
$string['annotate_tools'] = 'Annotationswerkzeuge';
$string['annotate_select'] = 'Auswählen';
$string['annotate_textselect'] = 'Text auswählen';
$string['annotate_comment'] = 'Kommentar';
$string['annotate_highlight'] = 'Hervorheben';
$string['annotate_pen'] = 'Stift';
$string['annotate_pen_fine'] = 'Fein';
$string['annotate_pen_medium'] = 'Mittel';
$string['annotate_pen_thick'] = 'Dick';
$string['annotate_stamps'] = 'Stempel';
$string['annotate_stamp_check'] = 'Häkchen-Stempel';
$string['annotate_stamp_cross'] = 'Kreuz-Stempel';
$string['annotate_stamp_question'] = 'Fragezeichen-Stempel';
$string['annotate_red'] = 'Rot';
$string['annotate_yellow'] = 'Gelb';
$string['annotate_green'] = 'Grün';
$string['annotate_blue'] = 'Blau';
$string['annotate_black'] = 'Schwarz';
$string['annotate_shape'] = 'Formen';
$string['annotate_shape_rect'] = 'Rechteck';
$string['annotate_shape_circle'] = 'Kreis';
$string['annotate_shape_arrow'] = 'Pfeil';
$string['annotate_shape_line'] = 'Linie';
$string['annotate_undo'] = 'Rückgängig';
$string['annotate_redo'] = 'Wiederherstellen';
$string['annotate_delete'] = 'Auswahl löschen';
$string['annotate_clearall'] = 'Alles löschen';
$string['annotate_clear_confirm'] = 'Möchten Sie wirklich alle Annotationen auf dieser Seite löschen? Dies kann nicht rückgängig gemacht werden.';

// Document info.
$string['docinfo'] = 'Dokumentinformationen';
$string['docinfo_filename'] = 'Dateiname';
$string['docinfo_filesize'] = 'Dateigröße';
$string['docinfo_pages'] = 'Seiten';
$string['docinfo_wordcount'] = 'Wortanzahl';
$string['docinfo_author'] = 'Autor';
$string['docinfo_creator'] = 'Ersteller';
$string['docinfo_created'] = 'Erstellt';
$string['docinfo_modified'] = 'Geändert';
$string['docinfo_calculating'] = 'Wird berechnet...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Forum-Feedback anzeigen';
$string['forum_your_posts'] = 'Ihre Forenbeiträge';
$string['forum_no_posts'] = 'Sie haben noch keine Beiträge in diesem Forum verfasst.';
$string['forum_feedback_banner'] = 'Ihr Dozent hat Ihre Forenbeteiligung bewertet.';
$string['forum_wordcount'] = '{$a} Wörter';
$string['forum_posts_pill'] = 'Beiträge';
$string['submission_content_pill'] = 'Abgabe';
$string['forum_tab_posts'] = 'Beiträge';
$string['forum_tab_files'] = 'Kommentierte Dateien';
$string['view_quiz_feedback'] = 'Test-Feedback anzeigen';
$string['quiz_feedback_banner'] = 'Ihr Dozent hat Feedback zu Ihrem Test gegeben.';
$string['quiz_your_attempt'] = 'Ihr Versuch';
$string['quiz_no_attempt'] = 'Sie haben noch keinen Versuch für diesen Test abgeschlossen.';
$string['quiz_select_attempt'] = 'Versuch auswählen';
$string['select_attempt'] = 'Versuch auswählen';
$string['attempt_label'] = 'Versuch {$a}';

// Post grades.
$string['grades_posted'] = 'Bewertungen veröffentlicht';
$string['grades_hidden'] = 'Bewertungen verborgen';
$string['post_grades'] = 'Bewertungen veröffentlichen';
$string['unpost_grades'] = 'Bewertungen verbergen';
$string['confirm_post_grades'] = 'Alle Bewertungen für diese Aktivität veröffentlichen? Teilnehmer/innen können dann ihre Bewertungen und ihr Feedback sehen.';
$string['confirm_unpost_grades'] = 'Alle Bewertungen für diese Aktivität verbergen? Teilnehmer/innen können ihre Bewertungen und ihr Feedback dann nicht mehr sehen.';
$string['schedule_post'] = 'An einem Datum veröffentlichen';
$string['schedule_post_btn'] = 'Planen';
$string['grades_scheduled'] = 'Veröffentlichung {$a}';
$string['schedule_must_be_future'] = 'Das geplante Datum muss in der Zukunft liegen.';
$string['quiz_post_grades_disabled'] = 'Die Veröffentlichung von Bewertungen ist für Tests nicht verfügbar. Die Sichtbarkeit der Bewertungen wird über die Überprüfungsoptionen des Tests gesteuert.';
$string['quiz_post_grades_no_schedule'] = 'Zeitplanung ist für Tests nicht verfügbar. Verwenden Sie stattdessen Veröffentlichen oder Verbergen.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Auf Entwurf zurücksetzen';
$string['action_remove_submission'] = 'Abgabe entfernen';
$string['action_lock'] = 'Abgabeänderungen verhindern';
$string['action_unlock'] = 'Abgabeänderungen erlauben';
$string['action_edit_submission'] = 'Abgabe bearbeiten';
$string['action_grant_extension'] = 'Verlängerung gewähren';
$string['action_edit_extension'] = 'Verlängerung bearbeiten';
$string['action_submit_for_grading'] = 'Zur Bewertung einreichen';
$string['confirm_revert_to_draft'] = 'Möchten Sie diese Abgabe wirklich auf den Entwurfsstatus zurücksetzen?';
$string['confirm_remove_submission'] = 'Möchten Sie diese Abgabe wirklich entfernen? Dies kann nicht rückgängig gemacht werden.';
$string['confirm_lock_submission'] = 'Diesen Teilnehmer daran hindern, Änderungen an der Abgabe vorzunehmen?';
$string['confirm_unlock_submission'] = 'Diesem Teilnehmer erlauben, Änderungen an der Abgabe vorzunehmen?';
$string['confirm_submit_for_grading'] = 'Diesen Entwurf im Namen des Teilnehmers einreichen?';
$string['invalidaction'] = 'Ungültige Abgabeaktion.';

// Override actions.
$string['override'] = 'Überschreibung';
$string['action_add_override'] = 'Überschreibung hinzufügen';
$string['action_edit_override'] = 'Überschreibung bearbeiten';
$string['action_delete_override'] = 'Überschreibung löschen';
$string['confirm_delete_override'] = 'Möchten Sie diese Benutzerüberschreibung wirklich löschen?';
$string['override_saved'] = 'Überschreibung erfolgreich gespeichert.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Verlängerung löschen';
$string['confirm_delete_extension'] = 'Möchten Sie diese Fälligkeitsdatum-Verlängerung wirklich löschen?';
$string['quiz_extension_original_duedate'] = 'Ursprüngliches Fälligkeitsdatum';
$string['quiz_extension_current_extension'] = 'Aktuelle Verlängerung';
$string['quiz_extension_new_duedate'] = 'Neues Fälligkeitsdatum';
$string['quiz_extension_must_be_after_duedate'] = 'Das Verlängerungsdatum muss nach dem aktuellen Fälligkeitsdatum liegen.';
$string['quiz_extension_plugin_missing'] = 'Das Plugin quizaccess_duedate wird für Testverlängerungen benötigt, ist aber nicht installiert.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Forum-Fälligkeitsdatum';
$string['forum_extension_current_extension'] = 'Aktuelle Verlängerung';
$string['forum_extension_new_duedate'] = 'Neues Fälligkeitsdatum';
$string['forum_extension_must_be_after_duedate'] = 'Das Verlängerungsdatum muss nach dem Forum-Fälligkeitsdatum liegen.';

// Student profile popout.
$string['profile_view_full'] = 'Vollständiges Profil anzeigen';
$string['profile_login_as'] = 'Anmelden als';
$string['profile_no_email'] = 'Keine E-Mail-Adresse verfügbar';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Kurscode-Regex';
$string['setting_coursecode_regex_desc'] = 'Die Kommentarbibliothek organisiert gespeicherte Kommentare nach Kurscode, damit Dozenten Feedback über verschiedene Durchführungen desselben Kurses wiederverwenden können (z.B. von Semester zu Semester). Diese Einstellung steuert, wie Kurscodes aus den Moodle-Kurznamen extrahiert werden. Geben Sie ein PHP-Regex-Muster ein, das den Code-Teil Ihrer Kurznamen erkennt (z.B. <code>/[A-Z]{3}\\d{4}/</code> würde <strong>THE2201</strong> aus einem Kurznamen wie <em>THE2201-2026-S1</em> extrahieren). Leer lassen, um den vollständigen Kurznamen als Kurscode zu verwenden.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Meldeformular für akademisches Fehlverhalten aktivieren';
$string['setting_enable_report_form_desc'] = 'Wenn aktiviert, erscheint in den Plagiatsabschnitten eine Schaltfläche „Akademisches Fehlverhalten melden“, die auf ein externes Meldeformular verlinkt.';
$string['setting_report_form_url'] = 'URL-Vorlage für Meldeformular';
$string['setting_report_form_url_desc'] = 'URL für das Meldeformular für akademisches Fehlverhalten. Unterstützte Platzhalter: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Diese werden zur Laufzeit durch URL-kodierte Werte ersetzt. Für Microsoft Forms verwenden Sie die Funktion „Vorausgefüllte URL abrufen“, um Parameternamen zu finden.';
$string['report_impropriety'] = 'Akademisches Fehlverhalten melden';

// Comment library v2.
$string['clib_title'] = 'Kommentarbibliothek';
$string['clib_all'] = 'Alle';
$string['clib_quick_add'] = 'Kommentar schnell hinzufügen...';
$string['clib_manage'] = 'Bibliothek verwalten';
$string['clib_no_comments'] = 'Noch keine Kommentare.';
$string['clib_insert'] = 'Einfügen';
$string['clib_copied'] = 'Kommentar in die Zwischenablage kopiert';
$string['clib_my_library'] = 'Meine Bibliothek';
$string['clib_shared_library'] = 'Geteilte Bibliothek';
$string['clib_new_comment'] = 'Neuer Kommentar';
$string['clib_edit_comment'] = 'Kommentar bearbeiten';
$string['clib_delete_comment'] = 'Kommentar löschen';
$string['clib_confirm_delete'] = 'Möchten Sie diesen Kommentar wirklich löschen?';
$string['clib_share'] = 'Teilen';
$string['clib_unshare'] = 'Freigabe aufheben';
$string['clib_import'] = 'Importieren';
$string['clib_imported'] = 'Kommentar in Ihre Bibliothek importiert';
$string['clib_copy_to_course'] = 'In Kurs kopieren';
$string['clib_all_courses'] = 'Alle Kurse';
$string['clib_tags'] = 'Schlagwörter';
$string['clib_manage_tags'] = 'Schlagwörter verwalten';
$string['clib_new_tag'] = 'Neues Schlagwort';
$string['clib_edit_tag'] = 'Schlagwort bearbeiten';
$string['clib_delete_tag'] = 'Schlagwort löschen';
$string['clib_confirm_delete_tag'] = 'Möchten Sie dieses Schlagwort wirklich löschen? Es wird von allen Kommentaren entfernt.';
$string['clib_system_tag'] = 'Systemstandard';
$string['clib_shared_by'] = 'Geteilt von {$a}';
$string['clib_no_shared'] = 'Keine geteilten Kommentare verfügbar.';
$string['clib_picker_freetext'] = 'Oder eigenen Text schreiben...';
$string['clib_picker_loading'] = 'Kommentare werden geladen...';
$string['clib_offline_mode'] = 'Zwischengespeicherte Kommentare werden angezeigt — Bearbeitung ist offline nicht möglich.';
$string['unifiedgrader:sharecomments'] = 'Kommentare in der Bibliothek mit anderen Dozenten teilen';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Kommentarbibliothekseinträge in der einheitlichen Bewerter.';
$string['privacy:metadata:clib:userid'] = 'Der Dozent, dem der Kommentar gehört.';
$string['privacy:metadata:clib:coursecode'] = 'Der Kurscode, dem der Kommentar zugeordnet ist.';
$string['privacy:metadata:clib:content'] = 'Der Inhalt des Kommentars.';
$string['privacy:metadata:cltag'] = 'Kommentarbibliotheks-Schlagwörter in der einheitlichen Bewerter.';
$string['privacy:metadata:cltag:userid'] = 'Der Dozent, dem das Schlagwort gehört.';
$string['privacy:metadata:cltag:name'] = 'Der Name des Schlagworts.';

// Penalties.
$string['penalties'] = 'Abzüge';
$string['penalty_late'] = 'Verspätete Abgabe';
$string['penalty_late_days'] = '{$a} Tag(e) verspätet';
$string['penalty_late_auto'] = 'Automatisch auf Basis der Abzugsregeln berechnet';
$string['penalty_wordcount'] = 'Wortanzahl';
$string['penalty_other'] = 'Sonstiges';
$string['penalty_custom'] = 'Benutzerdefiniert';
$string['penalty_label_placeholder'] = 'Bezeichnung (max. 15 Zeichen)';
$string['penalty_active'] = 'Aktive Abzüge';
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
$string['finalgradeafterpenalties'] = 'Endnote nach Abzügen:';
$string['cannotdeleteautopenalty'] = 'Verspätungsabzüge werden automatisch berechnet und können nicht gelöscht werden.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Feedback-PDF herunterladen';
$string['feedback_summary_overall_feedback'] = 'Gesamtfeedback';
$string['feedback_summary_graded_on'] = 'Bewertet am {$a}';
$string['feedback_summary_generated_by'] = 'Erstellt von der einheitlichen Bewerter';
$string['feedback_summary_media_note'] = 'Medieninhalte sind in der Online-Feedback-Ansicht verfügbar.';
$string['feedback_summary_no_grade'] = 'k.A.';
$string['feedback_summary_remark'] = 'Dozentenkommentar';
$string['feedback_summary_total'] = 'Gesamt';
$string['levels'] = 'Stufen';
$string['error_gs_not_configured'] = 'GhostScript ist auf diesem Moodle-Server nicht konfiguriert. Der Administrator muss den GhostScript-Pfad unter Website-Administration > Plugins > Aktivitäten > Aufgabe > Feedback > PDF annotieren festlegen.';
$string['error_pdf_combine_failed'] = 'PDF-Dateien konnten nicht zusammengeführt werden: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Von Dozenten in der einheitlichen Bewerter angewendete Bewertungsabzüge.';
$string['privacy:metadata:penalty:userid'] = 'Der/die Teilnehmer/in, auf den/die der Abzug angewendet wurde.';
$string['privacy:metadata:penalty:authorid'] = 'Der Dozent, der den Abzug angewendet hat.';
$string['privacy:metadata:penalty:category'] = 'Die Abzugskategorie (Wortanzahl oder Sonstiges).';
$string['privacy:metadata:penalty:label'] = 'Die benutzerdefinierte Bezeichnung des Abzugs.';
$string['privacy:metadata:penalty:percentage'] = 'Der Abzugsprozentsatz.';
$string['privacy:metadata:fext'] = 'Von Dozenten in der einheitlichen Bewerter gewährte Foren-Fälligkeitsverlängerungen.';
$string['privacy:metadata:fext:userid'] = 'Der/die Teilnehmer/in, dem/der die Verlängerung gewährt wurde.';
$string['privacy:metadata:fext:authorid'] = 'Der Dozent, der die Verlängerung gewährt hat.';
$string['privacy:metadata:fext:extensionduedate'] = 'Das verlängerte Fälligkeitsdatum.';
$string['privacy:metadata:qfb'] = 'Von der einheitlichen Bewerter gespeichertes versuchsbezogenes Test-Feedback.';
$string['privacy:metadata:qfb:userid'] = 'Der/die Teilnehmer/in, für den/die das Feedback bestimmt ist.';
$string['privacy:metadata:qfb:grader'] = 'Der Dozent, der das Feedback gegeben hat.';
$string['privacy:metadata:qfb:feedback'] = 'Der Feedbacktext.';
$string['privacy:metadata:qfb:attemptnumber'] = 'Die Testversuchsnummer.';
$string['privacy:metadata:scomm'] = 'Abgabekommentare, die vom Unified Grader gespeichert werden.';
$string['privacy:metadata:scomm:cmid'] = 'Das Kursmodul, zu dem der Kommentar gehört.';
$string['privacy:metadata:scomm:userid'] = 'Der Student, über den der Kommentarthread handelt.';
$string['privacy:metadata:scomm:authorid'] = 'Der Benutzer, der den Kommentar geschrieben hat.';
$string['privacy:metadata:scomm:content'] = 'Der Kommentarinhalt.';
$string['privacy_forum_extensions'] = 'Foren-Verlängerungen';
$string['privacy_quiz_feedback'] = 'Test-Feedback';

// Notification strings.
$string['messageprovider:submission_comment'] = 'Benachrichtigungen über Abgabekommentare';
$string['notification_comment_subject'] = 'Neuer Kommentar zu {$a->activityname}';
$string['notification_comment_body'] = '<p><strong>{$a->authorfullname}</strong> hat einen Kommentar zu <a href="{$a->activityurl}">{$a->activityname}</a> in {$a->coursename} ({$a->timecreated}) gepostet:</p><blockquote>{$a->content}</blockquote>';
$string['notification_comment_small'] = '{$a->authorfullname} hat {$a->activityname} kommentiert';

// Offline cache and save status.
$string['allchangessaved'] = 'Alle Änderungen gespeichert';
$string['editing'] = 'Wird bearbeitet...';
$string['offlinesavedlocally'] = 'Offline — lokal gespeichert';
$string['connectionlost'] = 'Verbindung verloren — Ihre Arbeit wird lokal gespeichert und synchronisiert, sobald die Verbindung wiederhergestellt ist.';
$string['recoveredunsavedchanges'] = 'Nicht gespeicherte Änderungen aus Ihrer letzten Sitzung wurden wiederhergestellt.';
$string['restore'] = 'Wiederherstellen';
$string['discard'] = 'Verwerfen';
$string['mark_as_graded'] = 'Als bewertet markieren';
$string['manuallymarkedquestions'] = 'Manuell bewertete Fragen';
