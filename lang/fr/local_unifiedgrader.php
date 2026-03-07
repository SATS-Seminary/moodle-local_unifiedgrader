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
 * Language strings for local_unifiedgrader (French).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Correcteur unifié';
$string['grading_interface'] = 'Correcteur unifié';
$string['nopermission'] = 'Vous n\'avez pas la permission d\'utiliser le correcteur unifié.';
$string['invalidactivitytype'] = 'Ce type d\'activit\u00e9 n\'est pas pris en charge par le correcteur unifié.';
$string['invalidmodule'] = 'Module d\'activit\u00e9 invalide.';
$string['viewfeedback'] = 'Voir le commentaire';

// Attempts.
$string['attempt'] = 'Tentative';

// Capabilities.
$string['unifiedgrader:grade'] = 'Utiliser le correcteur unifié pour noter';
$string['unifiedgrader:viewall'] = 'Voir tous les \u00e9tudiants dans le correcteur unifié';
$string['unifiedgrader:viewnotes'] = 'Voir les notes priv\u00e9es de l\'enseignant';
$string['unifiedgrader:managenotes'] = 'Cr\u00e9er et modifier les notes priv\u00e9es de l\'enseignant';
$string['unifiedgrader:viewfeedback'] = 'Voir les commentaires annot\u00e9s du correcteur unifié';

// Settings.
$string['setting_enable_assign'] = 'Activer pour les devoirs';
$string['setting_enable_assign_desc'] = 'Permettre l\'utilisation du correcteur unifié pour les activit\u00e9s de type devoir.';
$string['setting_enable_forum'] = 'Activer pour les forums';
$string['setting_enable_forum_desc'] = 'Permettre l\'utilisation du correcteur unifié pour les activit\u00e9s de type forum.';
$string['setting_enable_quiz'] = 'Activer pour les tests';
$string['setting_enable_quiz_desc'] = 'Permettre l\'utilisation du correcteur unifié pour les activit\u00e9s de type test.';
$string['setting_enable_quiz_post_grades'] = 'Activer la publication des notes pour les tests';
$string['setting_enable_quiz_post_grades_desc'] = 'La visibilit\u00e9 des notes de test est normalement g\u00e9r\u00e9e par les options de relecture du test. Lorsque cette option est activ\u00e9e, le bouton \u00ab Publier les notes \u00bb du correcteur unifié met \u00e0 jour les options de relecture du test de mani\u00e8re programmatique pour afficher ou masquer les notes. Lorsque cette option est d\u00e9sactiv\u00e9e (par d\u00e9faut), le bouton de publication des notes est masqu\u00e9 pour les tests.';
$string['setting_allow_manual_override'] = 'Autoriser la modification manuelle de la note';
$string['setting_allow_manual_override_desc'] = 'Lorsque cette option est activ\u00e9e, les enseignants peuvent saisir manuellement une note m\u00eame lorsqu\'une grille d\'\u00e9valuation ou un guide de notation est configur\u00e9. Lorsque cette option est d\u00e9sactiv\u00e9e, la note est calcul\u00e9e exclusivement \u00e0 partir des crit\u00e8res de la grille ou du guide.';

// Grading interface.
$string['grade'] = 'Note';
$string['savegrade'] = 'Enregistrer la note';
$string['savefeedback'] = 'Enregistrer le commentaire';
$string['savinggrade'] = 'Enregistrement de la note...';
$string['gradesaved'] = 'Note enregistr\u00e9e';
$string['error_saving'] = 'Erreur lors de l\'enregistrement de la note.';
$string['error_network'] = 'Impossible de se connecter au serveur. Veuillez v\u00e9rifier votre connexion et r\u00e9essayer.';
$string['error_offline_comments'] = 'Impossible d\'ajouter des commentaires hors ligne.';
$string['feedback'] = 'Commentaire';
$string['overall_feedback'] = 'Commentaire g\u00e9n\u00e9ral';
$string['feedback_saved'] = 'Commentaire (enregistr\u00e9)';
$string['edit_feedback'] = 'Modifier';
$string['delete_feedback'] = 'Supprimer';
$string['confirm_delete_feedback'] = '\u00cates-vous s\u00fbr de vouloir supprimer ce commentaire ? La note sera conserv\u00e9e.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'D\u00e9velopper';

// Submissions.
$string['submission'] = 'Remise';
$string['nosubmission'] = 'Aucune remise';
$string['previewpanel'] = 'Aper\u00e7u de la remise';
$string['markingpanel'] = 'Panneau de notation';
$string['onlinetext'] = 'Texte en ligne';
$string['submittedfiles'] = 'Fichiers remis';
$string['viewfile'] = 'Voir le fichier';

// Participants.
$string['participants'] = 'Participants';
$string['search'] = 'Rechercher des participants...';
$string['sortby'] = 'Trier par';
$string['sortby_fullname'] = 'Nom complet';
$string['sortby_submittedat'] = 'Date de remise';
$string['sortby_status'] = 'Statut';
$string['filter_all'] = 'Tous les participants';
$string['filter_submitted'] = 'Remis';
$string['filter_needsgrading'] = 'Non not\u00e9';
$string['filter_notsubmitted'] = 'Non remis';
$string['filter_graded'] = 'Not\u00e9';
$string['filter_late'] = 'En retard';
$string['filter_allgroups'] = 'Tous les groupes';
$string['studentcount'] = '{$a->current} sur {$a->total}';

// Statuses.
$string['status_draft'] = 'Brouillon';
$string['status_submitted'] = 'Remis';
$string['status_graded'] = 'Not\u00e9';
$string['status_nosubmission'] = 'Aucune remise';
$string['status_needsgrading'] = '\u00c0 noter';
$string['status_new'] = 'Non remis';
$string['status_late'] = 'En retard : {$a}';

// Teacher notes.
$string['notes'] = 'Notes de l\'enseignant';
$string['notes_desc'] = 'Notes priv\u00e9es visibles uniquement par les enseignants et les mod\u00e9rateurs.';
$string['savenote'] = 'Enregistrer la note';
$string['deletenote'] = 'Supprimer';
$string['addnote'] = 'Ajouter une note';
$string['nonotes'] = 'Aucune note pour le moment.';
$string['confirmdelete_note'] = '\u00cates-vous s\u00fbr de vouloir supprimer cette note ?';

// Comment library.
$string['commentlibrary'] = 'Biblioth\u00e8que de commentaires';
$string['savecomment'] = 'Enregistrer dans la biblioth\u00e8que';
$string['insertcomment'] = 'Ins\u00e9rer';
$string['deletecomment'] = 'Supprimer';
$string['newcomment'] = 'Nouveau commentaire...';
$string['nocomments'] = 'Aucun commentaire enregistr\u00e9.';

// UI.
$string['loading'] = 'Chargement...';
$string['saving'] = 'Enregistrement...';
$string['saved'] = 'Enregistr\u00e9';
$string['previousstudent'] = '\u00c9tudiant pr\u00e9c\u00e9dent';
$string['nextstudent'] = '\u00c9tudiant suivant';
$string['expandfilters'] = 'Afficher les filtres';
$string['collapsefilters'] = 'Masquer les filtres';
$string['backtocourse'] = 'Retour au cours';
$string['rubric'] = 'Grille d\'\u00e9valuation';
$string['markingguide'] = 'Guide de notation';
$string['criterion'] = 'Crit\u00e8re';
$string['score'] = 'Score';
$string['remark'] = 'Remarque';
$string['total'] = 'Total : {$a}';
$string['viewallsubmissions'] = 'Voir toutes les remises';
$string['layout_both'] = 'Vue partag\u00e9e';
$string['layout_preview'] = 'Aper\u00e7u uniquement';
$string['layout_grade'] = 'Notation uniquement';
$string['manualquestions'] = 'Questions manuelles';
$string['response'] = 'R\u00e9ponse';
$string['teachercomment'] = 'Commentaire de l\'enseignant';

// Submission comments.
$string['submissioncomments'] = 'Commentaires de la remise';
$string['nocommentsyet'] = 'Aucun commentaire pour le moment';
$string['addcomment'] = 'Ajouter un commentaire...';
$string['postcomment'] = 'Publier';
$string['deletesubmissioncomment'] = 'Supprimer le commentaire';

// Feedback files.
$string['feedbackfiles'] = 'Fichiers de commentaire';

// Plagiarism.
$string['plagiarism'] = 'Plagiat';
$string['plagiarism_noresults'] = 'Aucun r\u00e9sultat de plagiat disponible.';
$string['plagiarism_pending'] = 'Analyse de plagiat en cours';
$string['plagiarism_error'] = 'L\'analyse de plagiat a \u00e9chou\u00e9';

// Student feedback view.
$string['assessment_criteria'] = 'Crit\u00e8res d\'\u00e9valuation';
$string['teacher_remark'] = 'Commentaire de l\'enseignant';
$string['view_feedback'] = 'Voir le commentaire';
$string['view_annotated_feedback'] = 'Voir le commentaire annot\u00e9';
$string['feedback_not_available'] = 'Votre commentaire n\'est pas encore disponible. Veuillez v\u00e9rifier apr\u00e8s que votre remise a \u00e9t\u00e9 not\u00e9e et publi\u00e9e.';
$string['no_annotated_files'] = 'Il n\'y a pas de fichiers PDF annot\u00e9s pour votre remise.';
$string['feedback_banner_default'] = 'Votre enseignant a fourni un commentaire sur votre remise.';

// Document conversion.
$string['conversion_failed'] = 'Ce fichier n\'a pas pu \u00eatre converti en PDF pour l\'aper\u00e7u.';
$string['converting_file'] = 'Conversion du document en PDF...';
$string['conversion_timeout'] = 'La conversion du document prend trop de temps. Veuillez r\u00e9essayer plus tard.';
$string['download_annotated_pdf'] = 'T\u00e9l\u00e9charger le PDF annot\u00e9';
$string['download_original_submission'] = 'T\u00e9l\u00e9charger la remise originale : {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Notes priv\u00e9es de l\'enseignant stock\u00e9es par \u00e9tudiant et par activit\u00e9 dans le correcteur unifié.';
$string['privacy:metadata:notes:cmid'] = 'L\'identifiant du module de cours auquel la note se rapporte.';
$string['privacy:metadata:notes:userid'] = 'L\'\u00e9tudiant concern\u00e9 par la note.';
$string['privacy:metadata:notes:authorid'] = 'L\'enseignant qui a r\u00e9dig\u00e9 la note.';
$string['privacy:metadata:notes:content'] = 'Le contenu de la note.';
$string['privacy:metadata:comments'] = 'Entr\u00e9es r\u00e9utilisables de la biblioth\u00e8que de commentaires dans le correcteur unifié.';
$string['privacy:metadata:comments:userid'] = 'L\'enseignant propri\u00e9taire du commentaire.';
$string['privacy:metadata:comments:content'] = 'Le contenu du commentaire.';
$string['privacy:metadata:preferences'] = 'Pr\u00e9f\u00e9rences de l\'utilisateur pour l\'interface du correcteur unifié.';
$string['privacy:metadata:preferences:userid'] = 'L\'utilisateur \u00e0 qui appartiennent les pr\u00e9f\u00e9rences.';
$string['privacy:metadata:preferences:data'] = 'Les donn\u00e9es de pr\u00e9f\u00e9rences encod\u00e9es en JSON.';
$string['privacy:metadata:annotations'] = 'Annotations de documents stock\u00e9es dans le correcteur unifié.';
$string['privacy:metadata:annotations:cmid'] = 'L\'identifiant du module de cours auquel l\'annotation se rapporte.';
$string['privacy:metadata:annotations:userid'] = 'L\'\u00e9tudiant dont la remise est annot\u00e9e.';
$string['privacy:metadata:annotations:authorid'] = 'L\'enseignant qui a cr\u00e9\u00e9 l\'annotation.';
$string['privacy:metadata:annotations:data'] = 'Les donn\u00e9es d\'annotation (JSON Fabric.js).';
$string['annotations'] = 'Annotations';

// PDF viewer.
$string['pdf_prevpage'] = 'Page pr\u00e9c\u00e9dente';
$string['pdf_nextpage'] = 'Page suivante';
$string['pdf_zoomin'] = 'Zoom avant';
$string['pdf_zoomout'] = 'Zoom arri\u00e8re';
$string['pdf_zoomfit'] = 'Ajuster \u00e0 la largeur';
$string['pdf_search'] = 'Rechercher dans le document';

// Annotation tools.
$string['annotate_tools'] = 'Outils d\'annotation';
$string['annotate_select'] = 'S\u00e9lectionner';
$string['annotate_textselect'] = 'S\u00e9lectionner le texte';
$string['annotate_comment'] = 'Commentaire';
$string['annotate_highlight'] = 'Surligner';
$string['annotate_pen'] = 'Stylo';
$string['annotate_pen_fine'] = 'Fin';
$string['annotate_pen_medium'] = 'Moyen';
$string['annotate_pen_thick'] = '\u00c9pais';
$string['annotate_stamps'] = 'Tampons';
$string['annotate_stamp_check'] = 'Tampon coche';
$string['annotate_stamp_cross'] = 'Tampon croix';
$string['annotate_stamp_question'] = 'Tampon question';
$string['annotate_red'] = 'Rouge';
$string['annotate_yellow'] = 'Jaune';
$string['annotate_green'] = 'Vert';
$string['annotate_blue'] = 'Bleu';
$string['annotate_black'] = 'Noir';
$string['annotate_shape'] = 'Formes';
$string['annotate_shape_rect'] = 'Rectangle';
$string['annotate_shape_circle'] = 'Cercle';
$string['annotate_shape_arrow'] = 'Fl\u00e8che';
$string['annotate_shape_line'] = 'Ligne';
$string['annotate_undo'] = 'Annuler';
$string['annotate_redo'] = 'R\u00e9tablir';
$string['annotate_delete'] = 'Supprimer la s\u00e9lection';
$string['annotate_clearall'] = 'Tout effacer';
$string['annotate_clear_confirm'] = '\u00cates-vous s\u00fbr de vouloir effacer toutes les annotations de cette page ? Cette action est irr\u00e9versible.';

// Document info.
$string['docinfo'] = 'Informations du document';
$string['docinfo_filename'] = 'Nom du fichier';
$string['docinfo_filesize'] = 'Taille du fichier';
$string['docinfo_pages'] = 'Pages';
$string['docinfo_wordcount'] = 'Nombre de mots';
$string['docinfo_author'] = 'Auteur';
$string['docinfo_creator'] = 'Cr\u00e9ateur';
$string['docinfo_created'] = 'Cr\u00e9\u00e9';
$string['docinfo_modified'] = 'Modifi\u00e9';
$string['docinfo_calculating'] = 'Calcul en cours...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Voir le commentaire du forum';
$string['forum_your_posts'] = 'Vos messages du forum';
$string['forum_no_posts'] = 'Vous n\'avez publi\u00e9 aucun message dans ce forum.';
$string['forum_feedback_banner'] = 'Votre enseignant a not\u00e9 votre participation au forum.';
$string['forum_wordcount'] = '{$a} mots';
$string['forum_posts_pill'] = 'Messages';
$string['submission_content_pill'] = 'Remise';
$string['forum_tab_posts'] = 'Messages';
$string['forum_tab_files'] = 'Fichiers annot\u00e9s';
$string['view_quiz_feedback'] = 'Voir le commentaire du test';
$string['quiz_feedback_banner'] = 'Votre enseignant a fourni un commentaire sur votre test.';
$string['quiz_your_attempt'] = 'Votre tentative';
$string['quiz_no_attempt'] = 'Vous n\'avez compl\u00e9t\u00e9 aucune tentative pour ce test.';
$string['quiz_select_attempt'] = 'S\u00e9lectionner la tentative';
$string['select_attempt'] = 'S\u00e9lectionner la tentative';
$string['attempt_label'] = 'Tentative {$a}';

// Post grades.
$string['grades_posted'] = 'Notes publi\u00e9es';
$string['grades_hidden'] = 'Notes masqu\u00e9es';
$string['post_grades'] = 'Publier les notes';
$string['unpost_grades'] = 'Masquer les notes';
$string['confirm_post_grades'] = 'Publier toutes les notes de cette activit\u00e9 ? Les \u00e9tudiants pourront voir leurs notes et commentaires.';
$string['confirm_unpost_grades'] = 'Masquer toutes les notes de cette activit\u00e9 ? Les \u00e9tudiants ne pourront plus voir leurs notes et commentaires.';
$string['schedule_post'] = 'Publier \u00e0 une date';
$string['schedule_post_btn'] = 'Programmer';
$string['grades_scheduled'] = 'Publication le {$a}';
$string['schedule_must_be_future'] = 'La date programm\u00e9e doit \u00eatre dans le futur.';
$string['quiz_post_grades_disabled'] = 'La publication des notes n\'est pas disponible pour les tests. La visibilit\u00e9 des notes est contr\u00f4l\u00e9e par les options de relecture du test.';
$string['quiz_post_grades_no_schedule'] = 'La programmation n\'est pas disponible pour les tests. Utilisez Publier ou Masquer \u00e0 la place.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Remettre en brouillon';
$string['action_remove_submission'] = 'Supprimer la remise';
$string['action_lock'] = 'Emp\u00eacher les modifications de la remise';
$string['action_unlock'] = 'Autoriser les modifications de la remise';
$string['action_edit_submission'] = 'Modifier la remise';
$string['action_grant_extension'] = 'Accorder une prolongation';
$string['action_edit_extension'] = 'Modifier la prolongation';
$string['action_submit_for_grading'] = 'Soumettre pour notation';
$string['confirm_revert_to_draft'] = '\u00cates-vous s\u00fbr de vouloir remettre cette remise en brouillon ?';
$string['confirm_remove_submission'] = '\u00cates-vous s\u00fbr de vouloir supprimer cette remise ? Cette action est irr\u00e9versible.';
$string['confirm_lock_submission'] = 'Emp\u00eacher cet \u00e9tudiant de modifier sa remise ?';
$string['confirm_unlock_submission'] = 'Autoriser cet \u00e9tudiant \u00e0 modifier sa remise ?';
$string['confirm_submit_for_grading'] = 'Soumettre ce brouillon au nom de l\'\u00e9tudiant ?';
$string['invalidaction'] = 'Action de remise invalide.';

// Override actions.
$string['override'] = 'D\u00e9rogation';
$string['action_add_override'] = 'Ajouter une d\u00e9rogation';
$string['action_edit_override'] = 'Modifier la d\u00e9rogation';
$string['action_delete_override'] = 'Supprimer la d\u00e9rogation';
$string['confirm_delete_override'] = '\u00cates-vous s\u00fbr de vouloir supprimer cette d\u00e9rogation utilisateur ?';
$string['override_saved'] = 'D\u00e9rogation enregistr\u00e9e avec succ\u00e8s.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Supprimer la prolongation';
$string['confirm_delete_extension'] = '\u00cates-vous s\u00fbr de vouloir supprimer cette prolongation de date limite ?';
$string['quiz_extension_original_duedate'] = 'Date limite originale';
$string['quiz_extension_current_extension'] = 'Prolongation actuelle';
$string['quiz_extension_new_duedate'] = 'Date limite de prolongation';
$string['quiz_extension_must_be_after_duedate'] = 'La date de prolongation doit \u00eatre post\u00e9rieure \u00e0 la date limite actuelle.';
$string['quiz_extension_plugin_missing'] = 'Le plugin quizaccess_duedate est requis pour les prolongations de test mais n\'est pas install\u00e9.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Date limite du forum';
$string['forum_extension_current_extension'] = 'Prolongation actuelle';
$string['forum_extension_new_duedate'] = 'Date limite de prolongation';
$string['forum_extension_must_be_after_duedate'] = 'La date de prolongation doit \u00eatre post\u00e9rieure \u00e0 la date limite du forum.';

// Student profile popout.
$string['profile_view_full'] = 'Voir le profil complet';
$string['profile_login_as'] = 'Se connecter en tant que';
$string['profile_no_email'] = 'Aucune adresse courriel disponible';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Expression r\u00e9guli\u00e8re du code de cours';
$string['setting_coursecode_regex_desc'] = 'La biblioth\u00e8que de commentaires organise les commentaires enregistr\u00e9s par code de cours, afin que les enseignants puissent r\u00e9utiliser leurs commentaires d\'une session \u00e0 l\'autre (par exemple d\'un semestre \u00e0 l\'autre). Ce param\u00e8tre contr\u00f4le comment les codes de cours sont extraits des noms abr\u00e9g\u00e9s des cours Moodle. Entrez une expression r\u00e9guli\u00e8re PHP correspondant \u00e0 la portion code de vos noms abr\u00e9g\u00e9s (par exemple <code>/[A-Z]{3}\\d{4}/</code> extrairait <strong>THE2201</strong> d\'un nom abr\u00e9g\u00e9 comme <em>THE2201-2026-S1</em>). Laissez vide pour utiliser le nom abr\u00e9g\u00e9 complet comme code de cours.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Activer le formulaire de signalement d\'improbit\u00e9 acad\u00e9mique';
$string['setting_enable_report_form_desc'] = 'Lorsque cette option est activ\u00e9e, un bouton \u00ab Signaler une improbit\u00e9 acad\u00e9mique \u00bb appara\u00eet dans les sections de plagiat, menant \u00e0 un formulaire de signalement externe.';
$string['setting_report_form_url'] = 'Mod\u00e8le d\'URL du formulaire de signalement';
$string['setting_report_form_url_desc'] = 'URL du formulaire de signalement d\'improbit\u00e9 acad\u00e9mique. Espaces r\u00e9serv\u00e9s pris en charge : <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Ceux-ci sont remplac\u00e9s \u00e0 l\'ex\u00e9cution par des valeurs encod\u00e9es pour URL. Pour Microsoft Forms, utilisez la fonctionnalit\u00e9 \u00ab Obtenir l\'URL pr\u00e9remplie \u00bb pour trouver les noms des param\u00e8tres.';
$string['report_impropriety'] = 'Signaler une improbit\u00e9 acad\u00e9mique';

// Comment library v2.
$string['clib_title'] = 'Biblioth\u00e8que de commentaires';
$string['clib_all'] = 'Tous';
$string['clib_quick_add'] = 'Ajout rapide de commentaire...';
$string['clib_manage'] = 'G\u00e9rer la biblioth\u00e8que';
$string['clib_no_comments'] = 'Aucun commentaire pour le moment.';
$string['clib_insert'] = 'Ins\u00e9rer';
$string['clib_copied'] = 'Commentaire copi\u00e9 dans le presse-papiers';
$string['clib_my_library'] = 'Ma biblioth\u00e8que';
$string['clib_shared_library'] = 'Biblioth\u00e8que partag\u00e9e';
$string['clib_new_comment'] = 'Nouveau commentaire';
$string['clib_edit_comment'] = 'Modifier le commentaire';
$string['clib_delete_comment'] = 'Supprimer le commentaire';
$string['clib_confirm_delete'] = '\u00cates-vous s\u00fbr de vouloir supprimer ce commentaire ?';
$string['clib_share'] = 'Partager';
$string['clib_unshare'] = 'Ne plus partager';
$string['clib_import'] = 'Importer';
$string['clib_imported'] = 'Commentaire import\u00e9 dans votre biblioth\u00e8que';
$string['clib_copy_to_course'] = 'Copier vers le cours';
$string['clib_all_courses'] = 'Tous les cours';
$string['clib_tags'] = '\u00c9tiquettes';
$string['clib_manage_tags'] = 'G\u00e9rer les \u00e9tiquettes';
$string['clib_new_tag'] = 'Nouvelle \u00e9tiquette';
$string['clib_edit_tag'] = 'Modifier l\'\u00e9tiquette';
$string['clib_delete_tag'] = 'Supprimer l\'\u00e9tiquette';
$string['clib_confirm_delete_tag'] = '\u00cates-vous s\u00fbr de vouloir supprimer cette \u00e9tiquette ? Elle sera retir\u00e9e de tous les commentaires.';
$string['clib_system_tag'] = 'Par d\u00e9faut du syst\u00e8me';
$string['clib_shared_by'] = 'Partag\u00e9 par {$a}';
$string['clib_no_shared'] = 'Aucun commentaire partag\u00e9 disponible.';
$string['clib_picker_freetext'] = 'Ou \u00e9crivez le v\u00f4tre...';
$string['clib_picker_loading'] = 'Chargement des commentaires...';
$string['clib_offline_mode'] = 'Affichage des commentaires en cache \u2014 la modification n\'est pas disponible hors ligne.';
$string['unifiedgrader:sharecomments'] = 'Partager des commentaires de la biblioth\u00e8que avec d\'autres enseignants';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Entr\u00e9es de la biblioth\u00e8que de commentaires dans le correcteur unifié.';
$string['privacy:metadata:clib:userid'] = 'L\'enseignant propri\u00e9taire du commentaire.';
$string['privacy:metadata:clib:coursecode'] = 'Le code de cours associ\u00e9 au commentaire.';
$string['privacy:metadata:clib:content'] = 'Le contenu du commentaire.';
$string['privacy:metadata:cltag'] = '\u00c9tiquettes de la biblioth\u00e8que de commentaires dans le correcteur unifié.';
$string['privacy:metadata:cltag:userid'] = 'L\'enseignant propri\u00e9taire de l\'\u00e9tiquette.';
$string['privacy:metadata:cltag:name'] = 'Le nom de l\'\u00e9tiquette.';

// Penalties.
$string['penalties'] = 'P\u00e9nalit\u00e9s';
$string['penalty_late'] = 'Remise en retard';
$string['penalty_late_days'] = '{$a} jour(s) de retard';
$string['penalty_late_auto'] = 'Calcul\u00e9e automatiquement selon les r\u00e8gles de p\u00e9nalit\u00e9';
$string['penalty_wordcount'] = 'Nombre de mots';
$string['penalty_other'] = 'Autre';
$string['penalty_custom'] = 'Personnalis\u00e9e';
$string['penalty_label_placeholder'] = 'Libell\u00e9 (15 caract\u00e8res max)';
$string['penalty_active'] = 'P\u00e9nalit\u00e9s actives';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['penalty_late_label'] = 'En retard';
$string['penalty_late_applied'] = 'Pénalité de retard de {$a}% appliquée';
$string['late_days'] = '{$a} jours';
$string['late_day'] = '{$a} jour';
$string['late_hours'] = '{$a} heures';
$string['late_hour'] = '{$a} heure';
$string['late_mins'] = '{$a} min';
$string['late_min'] = '{$a} min';
$string['late_lessthanmin'] = '< 1 min';
$string['finalgradeafterpenalties'] = 'Note finale apr\u00e8s p\u00e9nalit\u00e9s :';
$string['cannotdeleteautopenalty'] = 'Les p\u00e9nalit\u00e9s de retard sont calcul\u00e9es automatiquement et ne peuvent pas \u00eatre supprim\u00e9es.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'T\u00e9l\u00e9charger le PDF de commentaire';
$string['feedback_summary_overall_feedback'] = 'Commentaire g\u00e9n\u00e9ral';
$string['feedback_summary_graded_on'] = 'Not\u00e9 le {$a}';
$string['feedback_summary_generated_by'] = 'G\u00e9n\u00e9r\u00e9 par le correcteur unifié';
$string['feedback_summary_media_note'] = 'Le contenu m\u00e9dia est disponible dans la vue de commentaire en ligne.';
$string['feedback_summary_no_grade'] = 'N/A';
$string['feedback_summary_remark'] = 'Commentaire de l\'enseignant';
$string['feedback_summary_total'] = 'Total';
$string['levels'] = 'Niveaux';
$string['error_gs_not_configured'] = 'GhostScript n\'est pas configur\u00e9 sur ce serveur Moodle. L\'administrateur doit d\u00e9finir le chemin de GhostScript dans Administration du site > Plugins > Modules d\'activit\u00e9 > Devoir > Commentaire > Annoter le PDF.';
$string['error_pdf_combine_failed'] = '\u00c9chec de la combinaison des fichiers PDF : {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'P\u00e9nalit\u00e9s de note appliqu\u00e9es par les enseignants dans le correcteur unifié.';
$string['privacy:metadata:penalty:userid'] = 'L\'\u00e9tudiant \u00e0 qui la p\u00e9nalit\u00e9 a \u00e9t\u00e9 appliqu\u00e9e.';
$string['privacy:metadata:penalty:authorid'] = 'L\'enseignant qui a appliqu\u00e9 la p\u00e9nalit\u00e9.';
$string['privacy:metadata:penalty:category'] = 'La cat\u00e9gorie de la p\u00e9nalit\u00e9 (nombre de mots ou autre).';
$string['privacy:metadata:penalty:label'] = 'Le libell\u00e9 personnalis\u00e9 de la p\u00e9nalit\u00e9.';
$string['privacy:metadata:penalty:percentage'] = 'Le pourcentage de la p\u00e9nalit\u00e9.';
$string['privacy:metadata:fext'] = 'Prolongations de date limite de forum accord\u00e9es par les enseignants dans le correcteur unifié.';
$string['privacy:metadata:fext:userid'] = 'L\'\u00e9tudiant \u00e0 qui la prolongation a \u00e9t\u00e9 accord\u00e9e.';
$string['privacy:metadata:fext:authorid'] = 'L\'enseignant qui a accord\u00e9 la prolongation.';
$string['privacy:metadata:fext:extensionduedate'] = 'La date limite prolong\u00e9e.';
$string['privacy:metadata:qfb'] = 'Commentaires de test par tentative stock\u00e9s par le correcteur unifié.';
$string['privacy:metadata:qfb:userid'] = 'L\'\u00e9tudiant concern\u00e9 par le commentaire.';
$string['privacy:metadata:qfb:grader'] = 'L\'enseignant qui a fourni le commentaire.';
$string['privacy:metadata:qfb:feedback'] = 'Le texte du commentaire.';
$string['privacy:metadata:qfb:attemptnumber'] = 'Le num\u00e9ro de tentative du test.';
$string['privacy_forum_extensions'] = 'Prolongations de forum';
$string['privacy_quiz_feedback'] = 'Commentaires de test';

// Offline cache and save status.
$string['allchangessaved'] = 'Toutes les modifications sont enregistr\u00e9es';
$string['editing'] = 'Modification en cours...';
$string['offlinesavedlocally'] = 'Hors ligne \u2014 enregistr\u00e9 localement';
$string['connectionlost'] = 'Connexion perdue \u2014 votre travail est enregistr\u00e9 localement et sera synchronis\u00e9 lors de la reconnexion.';
$string['recoveredunsavedchanges'] = 'Modifications non enregistr\u00e9es r\u00e9cup\u00e9r\u00e9es de votre derni\u00e8re session.';
$string['restore'] = 'Restaurer';
$string['discard'] = 'Abandonner';
$string['mark_as_graded'] = 'Marquer comme not\u00e9';
