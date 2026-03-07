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
 * Afrikaans language strings for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Ge\u00efntegreerde Nasiener';
$string['grading_interface'] = 'Ge\u00efntegreerde Nasiener';
$string['nopermission'] = 'Jy het nie toestemming om die Geïntegreerde Nasiener te gebruik nie.';
$string['invalidactivitytype'] = 'Hierdie aktiwiteittipe word nie deur die Geïntegreerde Nasiener ondersteun nie.';
$string['invalidmodule'] = 'Ongeldige aktiwiteitsmodule.';
$string['viewfeedback'] = 'Bekyk terugvoer';

// Attempts.
$string['attempt'] = 'Poging';

// Capabilities.
$string['unifiedgrader:grade'] = 'Gebruik die Geïntegreerde Nasiener om te beoordeel';
$string['unifiedgrader:viewall'] = 'Bekyk alle studente in die Geïntegreerde Nasiener';
$string['unifiedgrader:viewnotes'] = 'Bekyk privaat dosentenotas';
$string['unifiedgrader:managenotes'] = 'Skep en wysig privaat dosentenotas';
$string['unifiedgrader:viewfeedback'] = 'Bekyk geannoteerde terugvoer van die Geïntegreerde Nasiener';

// Settings.
$string['setting_enable_assign'] = 'Aktiveer vir Opdragte';
$string['setting_enable_assign_desc'] = 'Laat die Geïntegreerde Nasiener toe om vir opdragaktiwiteite gebruik te word.';
$string['setting_enable_forum'] = 'Aktiveer vir Forums';
$string['setting_enable_forum_desc'] = 'Laat die Geïntegreerde Nasiener toe om vir forumaktiwiteite gebruik te word.';
$string['setting_enable_quiz'] = 'Aktiveer vir Vasvrae';
$string['setting_enable_quiz_desc'] = 'Laat die Geïntegreerde Nasiener toe om vir vasvraaaktiwiteite gebruik te word.';
$string['setting_enable_quiz_post_grades'] = 'Aktiveer plaas punte vir vasvrae';
$string['setting_enable_quiz_post_grades_desc'] = 'Vasvraapuntsigbaarheid word normaalweg deur die vasvra se hersiening-opsies bestuur. Wanneer geaktiveer, sal die Geïntegreerde Nasiener se "Plaas punte"-skakelaar die vasvra se hersiening-opsies programmaties opdateer om punte te wys of te verberg. Wanneer gedeaktiveer (verstek), word die plaas punte-skakelaar vir vasvrae verberg.';
$string['setting_allow_manual_override'] = 'Laat handmatige puntetoekenning toe';
$string['setting_allow_manual_override_desc'] = 'Wanneer geaktiveer, kan dosente handmatig \'n punt intik selfs wanneer \'n rubriek of nasiengids opgestel is. Wanneer gedeaktiveer, word die punt uitsluitlik uit die rubriek- of nasiengidskriteria bereken.';

// Grading interface.
$string['grade'] = 'Punt';
$string['savegrade'] = 'Stoor punt';
$string['savefeedback'] = 'Stoor terugvoer';
$string['savinggrade'] = 'Stoor punt...';
$string['gradesaved'] = 'Punt gestoor';
$string['error_saving'] = 'Fout met die stoor van punt.';
$string['error_network'] = 'Kan nie met die bediener verbind nie. Kontroleer asseblief jou verbinding en probeer weer.';
$string['error_offline_comments'] = 'Kan nie opmerkings byvoeg terwyl jy aflyn is nie.';
$string['feedback'] = 'Terugvoer';
$string['overall_feedback'] = 'Algehele Terugvoer';
$string['feedback_saved'] = 'Terugvoer (gestoor)';
$string['edit_feedback'] = 'Wysig';
$string['delete_feedback'] = 'Verwyder';
$string['confirm_delete_feedback'] = 'Is jy seker jy wil hierdie terugvoer verwyder? Die punt sal behoue bly.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Vou oop';

// Submissions.
$string['submission'] = 'Indiening';
$string['nosubmission'] = 'Geen indiening';
$string['previewpanel'] = 'Indiening-voorskou';
$string['markingpanel'] = 'Nasienvenster';
$string['onlinetext'] = 'Aanlynteks';
$string['submittedfiles'] = 'Ingedienide lêers';
$string['viewfile'] = 'Bekyk lêer';

// Participants.
$string['participants'] = 'Deelnemers';
$string['search'] = 'Soek deelnemers...';
$string['sortby'] = 'Sorteer volgens';
$string['sortby_fullname'] = 'Volle naam';
$string['sortby_submittedat'] = 'Indieningsdatum';
$string['sortby_status'] = 'Status';
$string['filter_all'] = 'Alle deelnemers';
$string['filter_submitted'] = 'Ingedien';
$string['filter_needsgrading'] = 'Onbepunt';
$string['filter_notsubmitted'] = 'Nie ingedien';
$string['filter_graded'] = 'Bepunt';
$string['filter_late'] = 'Laat';
$string['filter_allgroups'] = 'Alle groepe';
$string['studentcount'] = '{$a->current} van {$a->total}';

// Statuses.
$string['status_draft'] = 'Konsep';
$string['status_submitted'] = 'Ingedien';
$string['status_graded'] = 'Bepunt';
$string['status_nosubmission'] = 'Geen indiening';
$string['status_needsgrading'] = 'Moet bepunt word';
$string['status_new'] = 'Nie ingedien';
$string['status_late'] = 'Laat: {$a}';

// Teacher notes.
$string['notes'] = 'Dosentenotas';
$string['notes_desc'] = 'Privaat notas slegs sigbaar vir dosente en moderators.';
$string['savenote'] = 'Stoor nota';
$string['deletenote'] = 'Verwyder';
$string['addnote'] = 'Voeg nota by';
$string['nonotes'] = 'Nog geen notas nie.';
$string['confirmdelete_note'] = 'Is jy seker jy wil hierdie nota verwyder?';

// Comment library.
$string['commentlibrary'] = 'Opmerkingsbiblioteek';
$string['savecomment'] = 'Stoor na biblioteek';
$string['insertcomment'] = 'Voeg in';
$string['deletecomment'] = 'Verwyder';
$string['newcomment'] = 'Nuwe opmerking...';
$string['nocomments'] = 'Geen gestoorde opmerkings nie.';

// UI.
$string['loading'] = 'Laai tans...';
$string['saving'] = 'Stoor tans...';
$string['saved'] = 'Gestoor';
$string['previousstudent'] = 'Vorige student';
$string['nextstudent'] = 'Volgende student';
$string['expandfilters'] = 'Wys filters';
$string['collapsefilters'] = 'Verberg filters';
$string['backtocourse'] = 'Terug na kursus';
$string['rubric'] = 'Rubriek';
$string['markingguide'] = 'Nasiengids';
$string['criterion'] = 'Kriterium';
$string['score'] = 'Telling';
$string['remark'] = 'Opmerking';
$string['total'] = 'Totaal: {$a}';
$string['viewallsubmissions'] = 'Bekyk alle indienings';
$string['layout_both'] = 'Verdeelde aansig';
$string['layout_preview'] = 'Slegs voorskou';
$string['layout_grade'] = 'Slegs nasiening';
$string['manualquestions'] = 'Handmatige vrae';
$string['response'] = 'Antwoord';
$string['teachercomment'] = 'Dosenteopmerking';

// Submission comments.
$string['submissioncomments'] = 'Indieningsopmerkings';
$string['nocommentsyet'] = 'Nog geen opmerkings nie';
$string['addcomment'] = 'Voeg \'n opmerking by...';
$string['postcomment'] = 'Plaas';
$string['deletesubmissioncomment'] = 'Verwyder opmerking';

// Feedback files.
$string['feedbackfiles'] = 'Terugvoerlêers';

// Plagiarism.
$string['plagiarism'] = 'Plagiaat';
$string['plagiarism_noresults'] = 'Geen plagiaatresultate beskikbaar nie.';
$string['plagiarism_pending'] = 'Plagiaatskandering is aan die gang';
$string['plagiarism_error'] = 'Plagiaatskandering het misluk';

// Student feedback view.
$string['assessment_criteria'] = 'Assesseringskriteria';
$string['teacher_remark'] = 'Dosenteterugvoer';
$string['view_feedback'] = 'Bekyk terugvoer';
$string['view_annotated_feedback'] = 'Bekyk Geannoteerde Terugvoer';
$string['feedback_not_available'] = 'Jou terugvoer is nog nie beskikbaar nie. Kom asseblief later terug nadat jou indiening bepunt en vrygestel is.';
$string['no_annotated_files'] = 'Daar is geen geannoteerde PDF-lêers vir jou indiening nie.';
$string['feedback_banner_default'] = 'Jou dosent het terugvoer op jou indiening verskaf.';

// Document conversion.
$string['conversion_failed'] = 'Hierdie lêer kon nie na PDF omgeskakel word vir voorskou nie.';
$string['converting_file'] = 'Dokument word na PDF omgeskakel...';
$string['conversion_timeout'] = 'Dokumentomskakeling neem te lank. Probeer asseblief later weer.';
$string['download_annotated_pdf'] = 'Laai geannoteerde PDF af';
$string['download_original_submission'] = 'Laai oorspronklike indiening af: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Privaat dosentenotas wat per student per aktiwiteit in die Geïntegreerde Nasiener gestoor word.';
$string['privacy:metadata:notes:cmid'] = 'Die kursusmodule-ID waarop die nota betrekking het.';
$string['privacy:metadata:notes:userid'] = 'Die student waaroor die nota gaan.';
$string['privacy:metadata:notes:authorid'] = 'Die dosent wat die nota geskryf het.';
$string['privacy:metadata:notes:content'] = 'Die inhoud van die nota.';
$string['privacy:metadata:comments'] = 'Herbruikbare opmerkingsbiblioteek-inskrywings in die Geïntegreerde Nasiener.';
$string['privacy:metadata:comments:userid'] = 'Die dosent aan wie die opmerking behoort.';
$string['privacy:metadata:comments:content'] = 'Die inhoud van die opmerking.';
$string['privacy:metadata:preferences'] = 'Gebruikersvoorkeure vir die Geïntegreerde Nasiener-koppelvlak.';
$string['privacy:metadata:preferences:userid'] = 'Die gebruiker aan wie die voorkeure behoort.';
$string['privacy:metadata:preferences:data'] = 'Die JSON-gekodeerde voorkeurdata.';
$string['privacy:metadata:annotations'] = 'Dokumentannotasies wat in die Geïntegreerde Nasiener gestoor word.';
$string['privacy:metadata:annotations:cmid'] = 'Die kursusmodule-ID waarop die annotasie betrekking het.';
$string['privacy:metadata:annotations:userid'] = 'Die student wie se indiening geannoteer is.';
$string['privacy:metadata:annotations:authorid'] = 'Die dosent wat die annotasie geskep het.';
$string['privacy:metadata:annotations:data'] = 'Die annotasiedata (Fabric.js JSON).';
$string['annotations'] = 'Annotasies';

// PDF viewer.
$string['pdf_prevpage'] = 'Vorige bladsy';
$string['pdf_nextpage'] = 'Volgende bladsy';
$string['pdf_zoomin'] = 'Zoom in';
$string['pdf_zoomout'] = 'Zoom uit';
$string['pdf_zoomfit'] = 'Pas by breedte';
$string['pdf_search'] = 'Soek in dokument';

// Annotation tools.
$string['annotate_tools'] = 'Annotasiegereedskap';
$string['annotate_select'] = 'Kies';
$string['annotate_textselect'] = 'Kies teks';
$string['annotate_comment'] = 'Opmerking';
$string['annotate_highlight'] = 'Uitlig';
$string['annotate_pen'] = 'Pen';
$string['annotate_pen_fine'] = 'Fyn';
$string['annotate_pen_medium'] = 'Medium';
$string['annotate_pen_thick'] = 'Dik';
$string['annotate_stamps'] = 'Stempels';
$string['annotate_stamp_check'] = 'Regmerkstempel';
$string['annotate_stamp_cross'] = 'Kruisstempel';
$string['annotate_stamp_question'] = 'Vraagtekenstempel';
$string['annotate_red'] = 'Rooi';
$string['annotate_yellow'] = 'Geel';
$string['annotate_green'] = 'Groen';
$string['annotate_blue'] = 'Blou';
$string['annotate_black'] = 'Swart';
$string['annotate_shape'] = 'Vorms';
$string['annotate_shape_rect'] = 'Reghoek';
$string['annotate_shape_circle'] = 'Sirkel';
$string['annotate_shape_arrow'] = 'Pyltjie';
$string['annotate_shape_line'] = 'Lyn';
$string['annotate_undo'] = 'Ontdoen';
$string['annotate_redo'] = 'Herdoen';
$string['annotate_delete'] = 'Verwyder geselekteerde';
$string['annotate_clearall'] = 'Verwyder alles';
$string['annotate_clear_confirm'] = 'Is jy seker jy wil alle annotasies op hierdie bladsy verwyder? Dit kan nie ongedaan gemaak word nie.';

// Document info.
$string['docinfo'] = 'Dokumentinligting';
$string['docinfo_filename'] = 'Lêernaam';
$string['docinfo_filesize'] = 'Lêergrootte';
$string['docinfo_pages'] = 'Bladsye';
$string['docinfo_wordcount'] = 'Woordtelling';
$string['docinfo_author'] = 'Outeur';
$string['docinfo_creator'] = 'Skepper';
$string['docinfo_created'] = 'Geskep';
$string['docinfo_modified'] = 'Gewysig';
$string['docinfo_calculating'] = 'Bereken tans...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Bekyk Forumterugvoer';
$string['forum_your_posts'] = 'Jou forumplasings';
$string['forum_no_posts'] = 'Jy het nog geen plasings in hierdie forum gemaak nie.';
$string['forum_feedback_banner'] = 'Jou dosent het jou forumdeelname bepunt.';
$string['forum_wordcount'] = '{$a} woorde';
$string['forum_posts_pill'] = 'Plasings';
$string['submission_content_pill'] = 'Indiening';
$string['forum_tab_posts'] = 'Plasings';
$string['forum_tab_files'] = 'Geannoteerde Lêers';
$string['view_quiz_feedback'] = 'Bekyk Vasvraterugvoer';
$string['quiz_feedback_banner'] = 'Jou dosent het terugvoer op jou vasvra verskaf.';
$string['quiz_your_attempt'] = 'Jou Poging';
$string['quiz_no_attempt'] = 'Jy het nog geen pogings vir hierdie vasvra voltooi nie.';
$string['quiz_select_attempt'] = 'Kies poging';
$string['select_attempt'] = 'Kies poging';
$string['attempt_label'] = 'Poging {$a}';

// Post grades.
$string['grades_posted'] = 'Punte geplaas';
$string['grades_hidden'] = 'Punte verberg';
$string['post_grades'] = 'Plaas punte';
$string['unpost_grades'] = 'Ontplaas punte';
$string['confirm_post_grades'] = 'Plaas alle punte vir hierdie aktiwiteit? Studente sal hul punte en terugvoer kan sien.';
$string['confirm_unpost_grades'] = 'Ontplaas alle punte vir hierdie aktiwiteit? Studente sal nie meer hul punte en terugvoer kan sien nie.';
$string['schedule_post'] = 'Plaas op \'n datum';
$string['schedule_post_btn'] = 'Skeduleer';
$string['grades_scheduled'] = 'Word geplaas op {$a}';
$string['schedule_must_be_future'] = 'Die geskeduleerde datum moet in die toekoms wees.';
$string['quiz_post_grades_disabled'] = 'Plaas punte is nie beskikbaar vir vasvrae nie. Puntsigbaarheid word deur die vasvra se hersiening-opsies beheer.';
$string['quiz_post_grades_no_schedule'] = 'Skedulering is nie beskikbaar vir vasvrae nie. Gebruik Plaas of Ontplaas in plaas daarvan.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Stel terug na konsep';
$string['action_remove_submission'] = 'Verwyder indiening';
$string['action_lock'] = 'Verhoed indieningsveranderings';
$string['action_unlock'] = 'Laat indieningsveranderings toe';
$string['action_edit_submission'] = 'Wysig indiening';
$string['action_grant_extension'] = 'Verleen verlenging';
$string['action_edit_extension'] = 'Wysig verlenging';
$string['action_submit_for_grading'] = 'Dien in vir nasiening';
$string['confirm_revert_to_draft'] = 'Is jy seker jy wil hierdie indiening na konsepstatus terugstel?';
$string['confirm_remove_submission'] = 'Is jy seker jy wil hierdie indiening verwyder? Dit kan nie ongedaan gemaak word nie.';
$string['confirm_lock_submission'] = 'Verhoed dat hierdie student indieningsveranderings maak?';
$string['confirm_unlock_submission'] = 'Laat hierdie student toe om indieningsveranderings te maak?';
$string['confirm_submit_for_grading'] = 'Dien hierdie konsep namens die student in?';
$string['invalidaction'] = 'Ongeldige indieningsaksie.';

// Override actions.
$string['override'] = 'Oorheersing';
$string['action_add_override'] = 'Voeg oorheersing by';
$string['action_edit_override'] = 'Wysig oorheersing';
$string['action_delete_override'] = 'Verwyder oorheersing';
$string['confirm_delete_override'] = 'Is jy seker jy wil hierdie gebruikeroorheersing verwyder?';
$string['override_saved'] = 'Oorheersing suksesvol gestoor.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Verwyder verlenging';
$string['confirm_delete_extension'] = 'Is jy seker jy wil hierdie sperdatumverlenging verwyder?';
$string['quiz_extension_original_duedate'] = 'Oorspronklike sperdatum';
$string['quiz_extension_current_extension'] = 'Huidige verlenging';
$string['quiz_extension_new_duedate'] = 'Verlengde sperdatum';
$string['quiz_extension_must_be_after_duedate'] = 'Die verlengingsdatum moet na die huidige sperdatum wees.';
$string['quiz_extension_plugin_missing'] = 'Die quizaccess_duedate-inprop word vir vasvraaverlenging benodig, maar is nie geinstalleer nie.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Forum-sperdatum';
$string['forum_extension_current_extension'] = 'Huidige verlenging';
$string['forum_extension_new_duedate'] = 'Verlengde sperdatum';
$string['forum_extension_must_be_after_duedate'] = 'Die verlengingsdatum moet na die forum-sperdatum wees.';

// Student profile popout.
$string['profile_view_full'] = 'Bekyk volledige profiel';
$string['profile_login_as'] = 'Meld aan as';
$string['profile_no_email'] = 'Geen e-posadres beskikbaar nie';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Kursuskode-regulêre uitdrukking';
$string['setting_coursecode_regex_desc'] = 'Die Opmerkingsbiblioteek organiseer gestoorde opmerkings volgens kursuskode, sodat dosente terugvoer oor verskillende aanbiedings van dieselfde kursus kan hergebruik (bv. semester tot semester). Hierdie instelling beheer hoe kursuskodes uit Moodle-kursus-kortname onttrek word. Voer \'n PHP-regulêre uitdrukkingspatroon in wat by die kodegedeelte van jou kortname pas (bv. <code>/[A-Z]{3}\\d{4}/</code> sou <strong>THE2201</strong> uit \'n kortnaam soos <em>THE2201-2026-S1</em> onttrek). Laat leeg om die volle kortnaam as die kursuskode te gebruik.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Aktiveer akademiese wangedragverslagvorm';
$string['setting_enable_report_form_desc'] = 'Wanneer geaktiveer, verskyn \'n "Rapporteer akademiese wangedrag"-knoppie in plagiaatafdelings wat na \'n eksterne verslagvorm skakel.';
$string['setting_report_form_url'] = 'Verslagvorm-URL-sjabloon';
$string['setting_report_form_url_desc'] = 'URL vir die akademiese wangedragverslagvorm. Ondersteunde plekhouers: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Hierdie word tydens uitvoering met URL-gekodeerde waardes vervang. Vir Microsoft Forms, gebruik die "Kry Vooraf-ingevulde URL"-funksie om parametername te vind.';
$string['report_impropriety'] = 'Rapporteer akademiese wangedrag';

// Comment library v2.
$string['clib_title'] = 'Opmerkingsbiblioteek';
$string['clib_all'] = 'Alles';
$string['clib_quick_add'] = 'Vinnig opmerking byvoeg...';
$string['clib_manage'] = 'Bestuur Biblioteek';
$string['clib_no_comments'] = 'Nog geen opmerkings nie.';
$string['clib_insert'] = 'Voeg in';
$string['clib_copied'] = 'Opmerking na knipbord gekopieer';
$string['clib_my_library'] = 'My Biblioteek';
$string['clib_shared_library'] = 'Gedeelde Biblioteek';
$string['clib_new_comment'] = 'Nuwe opmerking';
$string['clib_edit_comment'] = 'Wysig opmerking';
$string['clib_delete_comment'] = 'Verwyder opmerking';
$string['clib_confirm_delete'] = 'Is jy seker jy wil hierdie opmerking verwyder?';
$string['clib_share'] = 'Deel';
$string['clib_unshare'] = 'Ontdeel';
$string['clib_import'] = 'Invoer';
$string['clib_imported'] = 'Opmerking na jou biblioteek ingevoer';
$string['clib_copy_to_course'] = 'Kopieer na kursus';
$string['clib_all_courses'] = 'Alle kursusse';
$string['clib_tags'] = 'Etikette';
$string['clib_manage_tags'] = 'Bestuur etikette';
$string['clib_new_tag'] = 'Nuwe etiket';
$string['clib_edit_tag'] = 'Wysig etiket';
$string['clib_delete_tag'] = 'Verwyder etiket';
$string['clib_confirm_delete_tag'] = 'Is jy seker jy wil hierdie etiket verwyder? Dit sal van alle opmerkings verwyder word.';
$string['clib_system_tag'] = 'Stelselverstek';
$string['clib_shared_by'] = 'Gedeel deur {$a}';
$string['clib_no_shared'] = 'Geen gedeelde opmerkings beskikbaar nie.';
$string['clib_picker_freetext'] = 'Of skryf jou eie...';
$string['clib_picker_loading'] = 'Laai opmerkings...';
$string['clib_offline_mode'] = 'Wys gekas opmerkings — redigering is aflyn nie beskikbaar nie.';
$string['unifiedgrader:sharecomments'] = 'Deel opmerkings in die biblioteek met ander dosente';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Opmerkingsbiblioteek-inskrywings in die Geïntegreerde Nasiener.';
$string['privacy:metadata:clib:userid'] = 'Die dosent aan wie die opmerking behoort.';
$string['privacy:metadata:clib:coursecode'] = 'Die kursuskode waaraan die opmerking gekoppel is.';
$string['privacy:metadata:clib:content'] = 'Die inhoud van die opmerking.';
$string['privacy:metadata:cltag'] = 'Opmerkingsbiblioteek-etikette in die Geïntegreerde Nasiener.';
$string['privacy:metadata:cltag:userid'] = 'Die dosent aan wie die etiket behoort.';
$string['privacy:metadata:cltag:name'] = 'Die etiketnaam.';

// Penalties.
$string['penalties'] = 'Boetes';
$string['penalty_late'] = 'Laat indiening';
$string['penalty_late_days'] = '{$a} dag(e) laat';
$string['penalty_late_auto'] = 'Outomaties bereken op grond van boetereëls';
$string['penalty_wordcount'] = 'Woordtelling';
$string['penalty_other'] = 'Ander';
$string['penalty_custom'] = 'Pasgemaak';
$string['penalty_label_placeholder'] = 'Etiket (maks 15 karakters)';
$string['penalty_active'] = 'Aktiewe boetes';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['penalty_late_label'] = 'Laat';
$string['penalty_late_applied'] = 'Laat-boete van {$a}% toegepas';
$string['late_days'] = '{$a} dae';
$string['late_day'] = '{$a} dag';
$string['late_hours'] = '{$a} ure';
$string['late_hour'] = '{$a} uur';
$string['late_mins'] = '{$a} min';
$string['late_min'] = '{$a} min';
$string['late_lessthanmin'] = '< 1 min';
$string['finalgradeafterpenalties'] = 'Finale punt na boetes:';
$string['cannotdeleteautopenalty'] = 'Laatboetes word outomaties bereken en kan nie verwyder word nie.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Laai terugvoer-PDF af';
$string['feedback_summary_overall_feedback'] = 'Algehele Terugvoer';
$string['feedback_summary_graded_on'] = 'Bepunt op {$a}';
$string['feedback_summary_generated_by'] = 'Gegenereer deur Geïntegreerde Nasiener';
$string['feedback_summary_media_note'] = 'Media-inhoud is beskikbaar in die aanlyn terugvoer-aansig.';
$string['feedback_summary_no_grade'] = 'N.v.t.';
$string['feedback_summary_remark'] = 'Dosenteopmerking';
$string['feedback_summary_total'] = 'Totaal';
$string['levels'] = 'Vlakke';
$string['error_gs_not_configured'] = 'GhostScript is nie op hierdie Moodle-bediener opgestel nie. Die administrateur moet die GhostScript-pad in Werfadministrasie > Inproppe > Aktiwiteitsmodules > Opdrag > Terugvoer > Annoteer PDF instel.';
$string['error_pdf_combine_failed'] = 'Kon nie PDF-lêers kombineer nie: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Punteboetes wat deur dosente in die Geïntegreerde Nasiener toegepas is.';
$string['privacy:metadata:penalty:userid'] = 'Die student op wie die boete toegepas is.';
$string['privacy:metadata:penalty:authorid'] = 'Die dosent wat die boete toegepas het.';
$string['privacy:metadata:penalty:category'] = 'Die boetekategorie (woordtelling of ander).';
$string['privacy:metadata:penalty:label'] = 'Die pasgemaakte etiket vir die boete.';
$string['privacy:metadata:penalty:percentage'] = 'Die boetepersentasie.';
$string['privacy:metadata:fext'] = 'Forum-sperdatumverlengings wat deur dosente in die Geïntegreerde Nasiener toegestaan is.';
$string['privacy:metadata:fext:userid'] = 'Die student aan wie die verlenging toegestaan is.';
$string['privacy:metadata:fext:authorid'] = 'Die dosent wat die verlenging toegestaan het.';
$string['privacy:metadata:fext:extensionduedate'] = 'Die verlengde sperdatum.';
$string['privacy:metadata:qfb'] = 'Per-poging vasvraterugvoer wat deur die Geïntegreerde Nasiener gestoor word.';
$string['privacy:metadata:qfb:userid'] = 'Die student vir wie die terugvoer is.';
$string['privacy:metadata:qfb:grader'] = 'Die dosent wat die terugvoer verskaf het.';
$string['privacy:metadata:qfb:feedback'] = 'Die terugvoerteks.';
$string['privacy:metadata:qfb:attemptnumber'] = 'Die vasvraapogingnommer.';
$string['privacy_forum_extensions'] = 'Forumverlengings';
$string['privacy_quiz_feedback'] = 'Vasvraterugvoer';

// Offline cache and save status.
$string['allchangessaved'] = 'Alle veranderings gestoor';
$string['editing'] = 'Redigeer tans...';
$string['offlinesavedlocally'] = 'Aflyn — plaaslik gestoor';
$string['connectionlost'] = 'Verbinding verloor — jou werk is plaaslik gestoor en sal sinkroniseer wanneer die verbinding herstel word.';
$string['recoveredunsavedchanges'] = 'Ongestoorde veranderings van jou vorige sessie is herwin.';
$string['restore'] = 'Herstel';
$string['discard'] = 'Gooi weg';
$string['mark_as_graded'] = 'Merk as bepunt';
