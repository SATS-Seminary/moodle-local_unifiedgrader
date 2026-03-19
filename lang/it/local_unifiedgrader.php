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
 * Language strings for local_unifiedgrader (Italian).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Valutatore unificato';
$string['grading_interface'] = 'Valutatore unificato';
$string['nopermission'] = 'Non hai il permesso di utilizzare il valutatore unificato.';
$string['invalidactivitytype'] = 'Questo tipo di attività non è supportato dal valutatore unificato.';
$string['invalidmodule'] = 'Modulo attività non valido.';
$string['viewfeedback'] = 'Visualizza feedback';

// Attempts.
$string['attempt'] = 'Tentativo';

// Capabilities.
$string['unifiedgrader:grade'] = 'Utilizzare il valutatore unificato per valutare';
$string['unifiedgrader:viewall'] = 'Visualizzare tutti gli studenti nel valutatore unificato';
$string['unifiedgrader:viewnotes'] = 'Visualizzare le note private del docente';
$string['unifiedgrader:managenotes'] = 'Creare e modificare le note private del docente';
$string['unifiedgrader:viewfeedback'] = 'Visualizzare il feedback annotato dal valutatore unificato';

// Settings.
$string['setting_enable_assign'] = 'Abilita per i compiti';
$string['setting_enable_assign_desc'] = 'Consenti l\'utilizzo del valutatore unificato per le attività di tipo compito.';
$string['setting_enable_submission_comments'] = 'Sostituisci commenti di consegna';
$string['setting_enable_submission_comments_desc'] = 'Sostituisce i commenti di consegna nativi di Moodle nella vista compito dello studente con i commenti stile messaggistica del valutatore unificato (con supporto notifiche). Gli studenti possono inviare messaggi ai docenti prima e dopo la valutazione.';
$string['setting_enable_forum'] = 'Abilita per i forum';
$string['setting_enable_forum_desc'] = 'Consenti l\'utilizzo del valutatore unificato per le attività di tipo forum.';
$string['setting_enable_quiz'] = 'Abilita per i quiz';
$string['setting_enable_quiz_desc'] = 'Consenti l\'utilizzo del valutatore unificato per le attività di tipo quiz.';
$string['setting_enable_quiz_post_grades'] = 'Abilita pubblicazione voti per i quiz';
$string['setting_enable_quiz_post_grades_desc'] = 'La visibilità dei voti del quiz è normalmente gestita dalle opzioni di revisione del quiz. Quando abilitato, il pulsante "Pubblica voti" del valutatore unificato aggiornerà le opzioni di revisione del quiz per mostrare o nascondere i voti. Quando disabilitato (predefinito), il pulsante di pubblicazione voti è nascosto per i quiz.';
$string['setting_allow_manual_override'] = 'Consenti sovrascrittura manuale del voto';
$string['setting_allow_manual_override_desc'] = 'Quando abilitato, i docenti possono inserire manualmente un voto anche quando è configurata una rubrica o una guida alla valutazione. Quando disabilitato, il voto viene calcolato esclusivamente dai criteri della rubrica o della guida alla valutazione.';

// Grading interface.
$string['grade'] = 'Voto';
$string['savegrade'] = 'Salva voto';
$string['savefeedback'] = 'Salva feedback';
$string['savinggrade'] = 'Salvataggio voto...';
$string['gradesaved'] = 'Voto salvato';
$string['error_saving'] = 'Errore durante il salvataggio del voto.';
$string['error_network'] = 'Impossibile connettersi al server. Verifica la connessione e riprova.';
$string['error_offline_comments'] = 'Impossibile aggiungere commenti in modalità offline.';
$string['feedback'] = 'Feedback';
$string['overall_feedback'] = 'Feedback complessivo';
$string['feedback_saved'] = 'Feedback (salvato)';
$string['edit_feedback'] = 'Modifica';
$string['delete_feedback'] = 'Elimina';
$string['confirm_delete_feedback'] = 'Sei sicuro di voler eliminare questo feedback? Il voto verrà conservato.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Espandi';

// Submissions.
$string['submission'] = 'Consegna';
$string['nosubmission'] = 'Nessuna consegna';
$string['previewpanel'] = 'Anteprima della consegna';
$string['markingpanel'] = 'Pannello di valutazione';
$string['onlinetext'] = 'Testo online';
$string['submittedfiles'] = 'File consegnati';
$string['viewfile'] = 'Visualizza file';

// Participants.
$string['participants'] = 'Partecipanti';
$string['search'] = 'Cerca partecipanti...';
$string['sortby'] = 'Ordina per';
$string['sortby_fullname'] = 'Nome completo';
$string['sortby_submittedat'] = 'Data di consegna';
$string['sortby_status'] = 'Stato';
$string['filter_all'] = 'Tutti i partecipanti';
$string['filter_submitted'] = 'Consegnato';
$string['filter_needsgrading'] = 'Da valutare';
$string['filter_notsubmitted'] = 'Non consegnato';
$string['filter_graded'] = 'Valutato';
$string['filter_late'] = 'In ritardo';
$string['filter_allgroups'] = 'Tutti i gruppi';
$string['filter_mygroups'] = 'Tutti i miei gruppi';
$string['studentcount'] = '{$a->current} di {$a->total}';

// Statuses.
$string['status_draft'] = 'Bozza';
$string['status_submitted'] = 'Consegnato';
$string['status_graded'] = 'Valutato';
$string['status_nosubmission'] = 'Nessuna consegna';
$string['status_needsgrading'] = 'Da valutare';
$string['status_new'] = 'Non consegnato';
$string['status_late'] = 'In ritardo: {$a}';

// Teacher notes.
$string['notes'] = 'Note del docente';
$string['notes_desc'] = 'Note private visibili solo ai docenti e ai moderatori.';
$string['savenote'] = 'Salva nota';
$string['deletenote'] = 'Elimina';
$string['addnote'] = 'Aggiungi nota';
$string['nonotes'] = 'Nessuna nota presente.';
$string['confirmdelete_note'] = 'Sei sicuro di voler eliminare questa nota?';

// Comment library.
$string['commentlibrary'] = 'Libreria commenti';
$string['savecomment'] = 'Salva nella libreria';
$string['insertcomment'] = 'Inserisci';
$string['deletecomment'] = 'Rimuovi';
$string['newcomment'] = 'Nuovo commento...';
$string['nocomments'] = 'Nessun commento salvato.';

// UI.
$string['loading'] = 'Caricamento...';
$string['saving'] = 'Salvataggio...';
$string['saved'] = 'Salvato';
$string['previousstudent'] = 'Studente precedente';
$string['nextstudent'] = 'Studente successivo';
$string['expandfilters'] = 'Mostra filtri';
$string['collapsefilters'] = 'Nascondi filtri';
$string['backtocourse'] = 'Torna al corso';
$string['rubric'] = 'Rubrica';
$string['markingguide'] = 'Guida alla valutazione';
$string['criterion'] = 'Criterio';
$string['score'] = 'Punteggio';
$string['remark'] = 'Osservazione';
$string['total'] = 'Totale: {$a}';
$string['viewallsubmissions'] = 'Visualizza tutte le consegne';
$string['layout_both'] = 'Vista divisa';
$string['layout_preview'] = 'Solo anteprima';
$string['layout_grade'] = 'Solo valutazione';
$string['manualquestions'] = 'Domande manuali';
$string['response'] = 'Risposta';
$string['teachercomment'] = 'Commento del docente';

// Submission comments.
$string['submissioncomments'] = 'Commenti alla consegna';
$string['nocommentsyet'] = 'Nessun commento presente';
$string['addcomment'] = 'Aggiungi un commento...';
$string['postcomment'] = 'Pubblica';
$string['deletesubmissioncomment'] = 'Elimina commento';

// Feedback files.
$string['feedbackfiles'] = 'File di feedback';

// Plagiarism.
$string['plagiarism'] = 'Plagio';
$string['plagiarism_noresults'] = 'Nessun risultato di plagio disponibile.';
$string['plagiarism_pending'] = 'Scansione antiplagio in corso';
$string['plagiarism_error'] = 'Scansione antiplagio fallita';

// Student feedback view.
$string['assessment_criteria'] = 'Criteri di valutazione';
$string['teacher_remark'] = 'Feedback del docente';
$string['view_feedback'] = 'Visualizza feedback';
$string['view_annotated_feedback'] = 'Visualizza feedback annotato';
$string['feedback_not_available'] = 'Il tuo feedback non è ancora disponibile. Ricontrolla dopo che la tua consegna sarà stata valutata e pubblicata.';
$string['no_annotated_files'] = 'Non sono presenti file PDF annotati per la tua consegna.';
$string['feedback_banner_default'] = 'Il tuo docente ha fornito un feedback sulla tua consegna.';

// Document conversion.
$string['conversion_failed'] = 'Impossibile convertire questo file in PDF per l\'anteprima.';
$string['converting_file'] = 'Conversione del documento in PDF...';
$string['conversion_timeout'] = 'La conversione del documento sta richiedendo troppo tempo. Riprova più tardi.';
$string['download_annotated_pdf'] = 'Scarica PDF annotato';
$string['download_original_submission'] = 'Scarica la consegna originale: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Note private del docente memorizzate per studente e per attività nel valutatore unificato.';
$string['privacy:metadata:notes:cmid'] = 'L\'ID del modulo del corso a cui la nota si riferisce.';
$string['privacy:metadata:notes:userid'] = 'Lo studente a cui la nota si riferisce.';
$string['privacy:metadata:notes:authorid'] = 'Il docente che ha scritto la nota.';
$string['privacy:metadata:notes:content'] = 'Il contenuto della nota.';
$string['privacy:metadata:comments'] = 'Voci riutilizzabili della libreria commenti nel valutatore unificato.';
$string['privacy:metadata:comments:userid'] = 'Il docente proprietario del commento.';
$string['privacy:metadata:comments:content'] = 'Il contenuto del commento.';
$string['privacy:metadata:preferences'] = 'Preferenze utente per l\'interfaccia del valutatore unificato.';
$string['privacy:metadata:preferences:userid'] = 'L\'utente a cui appartengono le preferenze.';
$string['privacy:metadata:preferences:data'] = 'I dati delle preferenze codificati in JSON.';
$string['privacy:metadata:annotations'] = 'Annotazioni sui documenti memorizzate nel valutatore unificato.';
$string['privacy:metadata:annotations:cmid'] = 'L\'ID del modulo del corso a cui l\'annotazione si riferisce.';
$string['privacy:metadata:annotations:userid'] = 'Lo studente la cui consegna è stata annotata.';
$string['privacy:metadata:annotations:authorid'] = 'Il docente che ha creato l\'annotazione.';
$string['privacy:metadata:annotations:data'] = 'I dati dell\'annotazione (JSON Fabric.js).';
$string['annotations'] = 'Annotazioni';

// PDF viewer.
$string['pdf_prevpage'] = 'Pagina precedente';
$string['pdf_nextpage'] = 'Pagina successiva';
$string['pdf_zoomin'] = 'Ingrandisci';
$string['pdf_zoomout'] = 'Riduci';
$string['pdf_zoomfit'] = 'Adatta alla larghezza';
$string['pdf_search'] = 'Cerca nel documento';

// Annotation tools.
$string['annotate_tools'] = 'Strumenti di annotazione';
$string['annotate_select'] = 'Seleziona';
$string['annotate_textselect'] = 'Seleziona testo';
$string['annotate_comment'] = 'Commento';
$string['annotate_highlight'] = 'Evidenzia';
$string['annotate_pen'] = 'Penna';
$string['annotate_pen_fine'] = 'Sottile';
$string['annotate_pen_medium'] = 'Medio';
$string['annotate_pen_thick'] = 'Spesso';
$string['annotate_stamps'] = 'Timbri';
$string['annotate_stamp_check'] = 'Timbro segno di spunta';
$string['annotate_stamp_cross'] = 'Timbro croce';
$string['annotate_stamp_question'] = 'Timbro punto interrogativo';
$string['annotate_red'] = 'Rosso';
$string['annotate_yellow'] = 'Giallo';
$string['annotate_green'] = 'Verde';
$string['annotate_blue'] = 'Blu';
$string['annotate_black'] = 'Nero';
$string['annotate_shape'] = 'Forme';
$string['annotate_shape_rect'] = 'Rettangolo';
$string['annotate_shape_circle'] = 'Cerchio';
$string['annotate_shape_arrow'] = 'Freccia';
$string['annotate_shape_line'] = 'Linea';
$string['annotate_undo'] = 'Annulla';
$string['annotate_redo'] = 'Ripeti';
$string['annotate_delete'] = 'Elimina selezionato';
$string['annotate_clearall'] = 'Cancella tutto';
$string['annotate_clear_confirm'] = 'Sei sicuro di voler cancellare tutte le annotazioni su questa pagina? Questa azione non può essere annullata.';

// Document info.
$string['docinfo'] = 'Informazioni documento';
$string['docinfo_filename'] = 'Nome file';
$string['docinfo_filesize'] = 'Dimensione file';
$string['docinfo_pages'] = 'Pagine';
$string['docinfo_wordcount'] = 'Conteggio parole';
$string['docinfo_author'] = 'Autore';
$string['docinfo_creator'] = 'Creatore';
$string['docinfo_created'] = 'Creato';
$string['docinfo_modified'] = 'Modificato';
$string['docinfo_calculating'] = 'Calcolo in corso...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Visualizza feedback del forum';
$string['forum_your_posts'] = 'I tuoi interventi nel forum';
$string['forum_no_posts'] = 'Non hai pubblicato alcun intervento in questo forum.';
$string['forum_feedback_banner'] = 'Il tuo docente ha valutato la tua partecipazione al forum.';
$string['forum_wordcount'] = '{$a} parole';
$string['forum_posts_pill'] = 'Interventi';
$string['submission_content_pill'] = 'Consegna';
$string['forum_tab_posts'] = 'Interventi';
$string['forum_tab_files'] = 'File annotati';
$string['view_quiz_feedback'] = 'Visualizza feedback del quiz';
$string['quiz_feedback_banner'] = 'Il tuo docente ha fornito un feedback sul tuo quiz.';
$string['quiz_your_attempt'] = 'Il tuo tentativo';
$string['quiz_no_attempt'] = 'Non hai completato alcun tentativo per questo quiz.';
$string['quiz_select_attempt'] = 'Seleziona tentativo';
$string['select_attempt'] = 'Seleziona tentativo';
$string['attempt_label'] = 'Tentativo {$a}';

// Post grades.
$string['grades_posted'] = 'Voti pubblicati';
$string['grades_hidden'] = 'Voti nascosti';
$string['post_grades'] = 'Pubblica voti';
$string['unpost_grades'] = 'Nascondi voti';
$string['confirm_post_grades'] = 'Pubblicare tutti i voti per questa attività? Gli studenti potranno visualizzare i loro voti e il feedback.';
$string['confirm_unpost_grades'] = 'Nascondere tutti i voti per questa attività? Gli studenti non potranno più visualizzare i loro voti e il feedback.';
$string['schedule_post'] = 'Pubblica in una data';
$string['schedule_post_btn'] = 'Programma';
$string['grades_scheduled'] = 'Pubblicazione {$a}';
$string['schedule_must_be_future'] = 'La data programmata deve essere nel futuro.';
$string['quiz_post_grades_disabled'] = 'La pubblicazione dei voti non è disponibile per i quiz. La visibilità dei voti è controllata dalle opzioni di revisione del quiz.';
$string['quiz_post_grades_no_schedule'] = 'La programmazione non è disponibile per i quiz. Utilizza Pubblica o Nascondi.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Ripristina come bozza';
$string['action_remove_submission'] = 'Rimuovi consegna';
$string['action_lock'] = 'Impedisci modifiche alla consegna';
$string['action_unlock'] = 'Consenti modifiche alla consegna';
$string['action_edit_submission'] = 'Modifica consegna';
$string['action_grant_extension'] = 'Concedi proroga';
$string['action_edit_extension'] = 'Modifica proroga';
$string['action_submit_for_grading'] = 'Consegna per la valutazione';
$string['confirm_revert_to_draft'] = 'Sei sicuro di voler ripristinare questa consegna come bozza?';
$string['confirm_remove_submission'] = 'Sei sicuro di voler rimuovere questa consegna? Questa azione non può essere annullata.';
$string['confirm_lock_submission'] = 'Impedire a questo studente di modificare la consegna?';
$string['confirm_unlock_submission'] = 'Consentire a questo studente di modificare la consegna?';
$string['confirm_submit_for_grading'] = 'Consegnare questa bozza per conto dello studente?';
$string['invalidaction'] = 'Azione sulla consegna non valida.';

// Override actions.
$string['override'] = 'Sovrascrittura';
$string['action_add_override'] = 'Aggiungi sovrascrittura';
$string['action_edit_override'] = 'Modifica sovrascrittura';
$string['action_delete_override'] = 'Elimina sovrascrittura';
$string['confirm_delete_override'] = 'Sei sicuro di voler eliminare questa sovrascrittura utente?';
$string['override_saved'] = 'Sovrascrittura salvata con successo.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Elimina proroga';
$string['confirm_delete_extension'] = 'Sei sicuro di voler eliminare questa proroga della data di scadenza?';
$string['quiz_extension_original_duedate'] = 'Data di scadenza originale';
$string['quiz_extension_current_extension'] = 'Proroga attuale';
$string['quiz_extension_new_duedate'] = 'Data di scadenza della proroga';
$string['quiz_extension_must_be_after_duedate'] = 'La data della proroga deve essere successiva alla data di scadenza attuale.';
$string['quiz_extension_plugin_missing'] = 'Il plugin quizaccess_duedate è necessario per le proroghe dei quiz ma non è installato.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Data di scadenza del forum';
$string['forum_extension_current_extension'] = 'Proroga attuale';
$string['forum_extension_new_duedate'] = 'Data di scadenza della proroga';
$string['forum_extension_must_be_after_duedate'] = 'La data della proroga deve essere successiva alla data di scadenza del forum.';

// Student profile popout.
$string['profile_view_full'] = 'Visualizza profilo completo';
$string['profile_login_as'] = 'Accedi come';
$string['profile_no_email'] = 'Nessun indirizzo email disponibile';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Regex codice corso';
$string['setting_coursecode_regex_desc'] = 'La libreria commenti organizza i commenti salvati per codice corso, in modo che i docenti possano riutilizzare i feedback tra diverse edizioni dello stesso corso (ad esempio da un semestre all\'altro). Questa impostazione controlla come i codici corso vengono estratti dai nomi brevi dei corsi Moodle. Inserisci un pattern regex PHP che corrisponda alla porzione di codice dei tuoi nomi brevi (ad esempio <code>/[A-Z]{3}\\d{4}/</code> estrarrebbe <strong>THE2201</strong> da un nome breve come <em>THE2201-2026-S1</em>). Lascia vuoto per utilizzare il nome breve completo come codice corso.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Abilita modulo di segnalazione irregolarità accademica';
$string['setting_enable_report_form_desc'] = 'Quando abilitato, un pulsante "Segnala irregolarità accademica" appare nelle sezioni antiplagio, collegandosi a un modulo di segnalazione esterno.';
$string['setting_report_form_url'] = 'Modello URL del modulo di segnalazione';
$string['setting_report_form_url_desc'] = 'URL del modulo di segnalazione di irregolarità accademica. Segnaposto supportati: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Questi vengono sostituiti in fase di esecuzione con valori codificati per URL. Per Microsoft Forms, utilizza la funzione "Ottieni URL precompilato" per trovare i nomi dei parametri.';
$string['report_impropriety'] = 'Segnala irregolarità accademica';

// Comment library v2.
$string['clib_title'] = 'Libreria commenti';
$string['clib_all'] = 'Tutti';
$string['clib_quick_add'] = 'Aggiungi commento rapido...';
$string['clib_manage'] = 'Gestisci libreria';
$string['clib_no_comments'] = 'Nessun commento presente.';
$string['clib_insert'] = 'Inserisci';
$string['clib_copied'] = 'Commento copiato negli appunti';
$string['clib_my_library'] = 'La mia libreria';
$string['clib_shared_library'] = 'Libreria condivisa';
$string['clib_new_comment'] = 'Nuovo commento';
$string['clib_edit_comment'] = 'Modifica commento';
$string['clib_delete_comment'] = 'Elimina commento';
$string['clib_confirm_delete'] = 'Sei sicuro di voler eliminare questo commento?';
$string['clib_share'] = 'Condividi';
$string['clib_unshare'] = 'Annulla condivisione';
$string['clib_import'] = 'Importa';
$string['clib_imported'] = 'Commento importato nella tua libreria';
$string['clib_copy_to_course'] = 'Copia nel corso';
$string['clib_all_courses'] = 'Tutti i corsi';
$string['clib_tags'] = 'Etichette';
$string['clib_manage_tags'] = 'Gestisci etichette';
$string['clib_new_tag'] = 'Nuova etichetta';
$string['clib_edit_tag'] = 'Modifica etichetta';
$string['clib_delete_tag'] = 'Elimina etichetta';
$string['clib_confirm_delete_tag'] = 'Sei sicuro di voler eliminare questa etichetta? Verrà rimossa da tutti i commenti.';
$string['clib_system_tag'] = 'Predefinito di sistema';
$string['clib_shared_by'] = 'Condiviso da {$a}';
$string['clib_no_shared'] = 'Nessun commento condiviso disponibile.';
$string['clib_picker_freetext'] = 'Oppure scrivi il tuo...';
$string['clib_picker_loading'] = 'Caricamento commenti...';
$string['clib_offline_mode'] = 'Visualizzazione dei commenti dalla cache — la modifica non è disponibile offline.';
$string['unifiedgrader:sharecomments'] = 'Condividere commenti nella libreria con altri docenti';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Voci della libreria commenti nel valutatore unificato.';
$string['privacy:metadata:clib:userid'] = 'Il docente proprietario del commento.';
$string['privacy:metadata:clib:coursecode'] = 'Il codice corso associato al commento.';
$string['privacy:metadata:clib:content'] = 'Il contenuto del commento.';
$string['privacy:metadata:cltag'] = 'Etichette della libreria commenti nel valutatore unificato.';
$string['privacy:metadata:cltag:userid'] = 'Il docente proprietario dell\'etichetta.';
$string['privacy:metadata:cltag:name'] = 'Il nome dell\'etichetta.';

// Penalties.
$string['penalties'] = 'Penalità';
$string['penalty_late'] = 'Consegna in ritardo';
$string['penalty_late_days'] = '{$a} giorno/i di ritardo';
$string['penalty_late_auto'] = 'Calcolata automaticamente in base alle regole di penalità';
$string['penalty_wordcount'] = 'Conteggio parole';
$string['penalty_other'] = 'Altro';
$string['penalty_custom'] = 'Personalizzata';
$string['penalty_label_placeholder'] = 'Etichetta (max 15 car.)';
$string['penalty_active'] = 'Penalità attive';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['penalty_late_label'] = 'In ritardo';
$string['penalty_late_applied'] = 'Penalità per ritardo del {$a}% applicata';
$string['late_days'] = '{$a} giorni';
$string['late_day'] = '{$a} giorno';
$string['late_hours'] = '{$a} ore';
$string['late_hour'] = '{$a} ora';
$string['late_mins'] = '{$a} min';
$string['late_min'] = '{$a} min';
$string['late_lessthanmin'] = '< 1 min';
$string['finalgradeafterpenalties'] = 'Voto finale dopo le penalità:';
$string['cannotdeleteautopenalty'] = 'Le penalità per ritardo sono calcolate automaticamente e non possono essere eliminate.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Scarica PDF del feedback';
$string['feedback_summary_overall_feedback'] = 'Feedback complessivo';
$string['feedback_summary_graded_on'] = 'Valutato il {$a}';
$string['feedback_summary_generated_by'] = 'Generato dal valutatore unificato';
$string['feedback_summary_media_note'] = 'I contenuti multimediali sono disponibili nella visualizzazione online del feedback.';
$string['feedback_summary_no_grade'] = 'N/D';
$string['feedback_summary_remark'] = 'Commento del docente';
$string['feedback_summary_total'] = 'Totale';
$string['levels'] = 'Livelli';
$string['error_gs_not_configured'] = 'GhostScript non è configurato su questo server Moodle. L\'amministratore deve impostare il percorso di GhostScript in Amministrazione del sito > Plugin > Moduli attività > Compito > Feedback > Annota PDF.';
$string['error_pdf_combine_failed'] = 'Impossibile combinare i file PDF: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Penalità sui voti applicate dai docenti nel valutatore unificato.';
$string['privacy:metadata:penalty:userid'] = 'Lo studente a cui è stata applicata la penalità.';
$string['privacy:metadata:penalty:authorid'] = 'Il docente che ha applicato la penalità.';
$string['privacy:metadata:penalty:category'] = 'La categoria della penalità (conteggio parole o altro).';
$string['privacy:metadata:penalty:label'] = 'L\'etichetta personalizzata della penalità.';
$string['privacy:metadata:penalty:percentage'] = 'La percentuale della penalità.';
$string['privacy:metadata:fext'] = 'Proroghe delle date di scadenza del forum concesse dai docenti nel valutatore unificato.';
$string['privacy:metadata:fext:userid'] = 'Lo studente a cui è stata concessa la proroga.';
$string['privacy:metadata:fext:authorid'] = 'Il docente che ha concesso la proroga.';
$string['privacy:metadata:fext:extensionduedate'] = 'La data di scadenza prorogata.';
$string['privacy:metadata:qfb'] = 'Feedback per tentativo di quiz memorizzato dal valutatore unificato.';
$string['privacy:metadata:qfb:userid'] = 'Lo studente a cui si riferisce il feedback.';
$string['privacy:metadata:qfb:grader'] = 'Il docente che ha fornito il feedback.';
$string['privacy:metadata:qfb:feedback'] = 'Il testo del feedback.';
$string['privacy:metadata:qfb:attemptnumber'] = 'Il numero del tentativo di quiz.';
$string['privacy:metadata:scomm'] = 'Commenti alla consegna memorizzati dall\'Unified Grader.';
$string['privacy:metadata:scomm:cmid'] = 'Il modulo del corso a cui appartiene il commento.';
$string['privacy:metadata:scomm:userid'] = 'Lo studente a cui si riferisce il thread di commenti.';
$string['privacy:metadata:scomm:authorid'] = 'L\'utente che ha scritto il commento.';
$string['privacy:metadata:scomm:content'] = 'Il contenuto del commento.';
$string['privacy_forum_extensions'] = 'Proroghe del forum';
$string['privacy_quiz_feedback'] = 'Feedback del quiz';

// Notification strings.
$string['messageprovider:submission_comment'] = 'Notifiche dei commenti alla consegna';
$string['notification_comment_subject'] = 'Nuovo commento su {$a->activityname}';
$string['notification_comment_body'] = '<p><strong>{$a->authorfullname}</strong> ha pubblicato un commento su <a href="{$a->activityurl}">{$a->activityname}</a> in {$a->coursename} ({$a->timecreated}):</p><blockquote>{$a->content}</blockquote>';
$string['notification_comment_small'] = '{$a->authorfullname} ha commentato su {$a->activityname}';

// Offline cache and save status.
$string['allchangessaved'] = 'Tutte le modifiche sono state salvate';
$string['editing'] = 'Modifica in corso...';
$string['offlinesavedlocally'] = 'Offline — salvato localmente';
$string['connectionlost'] = 'Connessione persa — il tuo lavoro è salvato localmente e verrà sincronizzato al ripristino della connessione.';
$string['recoveredunsavedchanges'] = 'Recuperate modifiche non salvate dalla sessione precedente.';
$string['restore'] = 'Ripristina';
$string['discard'] = 'Scarta';
$string['mark_as_graded'] = 'Segna come valutato';
