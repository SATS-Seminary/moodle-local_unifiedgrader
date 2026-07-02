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
$string['invalidactivitytype'] = 'Ce type d\'activité n\'est pas pris en charge par le correcteur unifié.';
$string['invalidmodule'] = 'Module d\'activité invalide.';
$string['viewfeedback'] = 'Voir le commentaire';

// Attempts.
$string['attempt'] = 'Tentative';

// Capabilities.
$string['unifiedgrader:grade'] = 'Utiliser le correcteur unifié pour noter';
$string['unifiedgrader:viewall'] = 'Voir tous les étudiants dans le correcteur unifié';
$string['unifiedgrader:viewnotes'] = 'Voir les notes privées de l\'enseignant';
$string['unifiedgrader:managenotes'] = 'Créer et modifier les notes privées de l\'enseignant';
$string['unifiedgrader:viewfeedback'] = 'Voir les commentaires annotés du correcteur unifié';

// Settings.
$string['setting_enable_assign'] = 'Activer pour les devoirs';
$string['setting_enable_assign_desc'] = 'Permettre l\'utilisation du correcteur unifié pour les activités de type devoir.';
$string['setting_enable_submission_comments'] = 'Remplacer les commentaires de soumission';
$string['setting_enable_submission_comments_desc'] = 'Remplace les commentaires de soumission natifs de Moodle dans la vue devoir de l\'étudiant par les commentaires style messagerie du correcteur unifié (avec support des notifications). Les étudiants peuvent envoyer des messages aux enseignants avant et après la correction.';
$string['setting_enable_forum'] = 'Activer pour les forums';
$string['setting_enable_forum_desc'] = 'Permettre l\'utilisation du correcteur unifié pour les activités de type forum.';
$string['setting_enable_quiz'] = 'Activer pour les tests';
$string['setting_enable_quiz_desc'] = 'Permettre l\'utilisation du correcteur unifié pour les activités de type test.';
$string['setting_enable_quiz_post_grades'] = 'Activer la publication des notes pour les tests';
$string['setting_enable_quiz_post_grades_desc'] = 'La visibilité des notes de test est normalement gérée par les options de relecture du test. Lorsque cette option est activée, le bouton « Publier les notes » du correcteur unifié met à jour les options de relecture du test de manière programmatique pour afficher ou masquer les notes. Lorsque cette option est désactivée (par défaut), le bouton de publication des notes est masqué pour les tests.';
$string['setting_allow_manual_override'] = 'Autoriser la modification manuelle de la note';
$string['setting_allow_manual_override_desc'] = 'Lorsque cette option est activée, les enseignants peuvent saisir manuellement une note même lorsqu\'une grille d\'évaluation ou un guide de notation est configuré. Lorsque cette option est désactivée, la note est calculée exclusivement à partir des critères de la grille ou du guide.';

// Grading interface.
$string['grade'] = 'Note';
$string['savegrade'] = 'Enregistrer la note';
$string['savefeedback'] = 'Enregistrer le commentaire';
$string['savinggrade'] = 'Enregistrement de la note...';
$string['gradesaved'] = 'Note enregistrée';
$string['error_saving'] = 'Erreur lors de l\'enregistrement de la note.';
$string['error_network'] = 'Impossible de se connecter au serveur. Veuillez vérifier votre connexion et réessayer.';
$string['error_offline_comments'] = 'Impossible d\'ajouter des commentaires hors ligne.';
$string['feedback'] = 'Commentaire';
$string['overall_feedback'] = 'Commentaire général';
$string['feedback_saved'] = 'Commentaire (enregistré)';
$string['edit_feedback'] = 'Modifier';
$string['delete_feedback'] = 'Supprimer';
$string['confirm_delete_feedback'] = 'Êtes-vous sûr de vouloir supprimer ce commentaire ? La note sera conservée.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Développer';

// Submissions.
$string['submission'] = 'Remise';
$string['nosubmission'] = 'Aucune remise';
$string['previewpanel'] = 'Aperçu de la remise';
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
$string['filter_needsgrading'] = 'Non noté';
$string['filter_notsubmitted'] = 'Non remis';
$string['filter_graded'] = 'Noté';
$string['filter_late'] = 'En retard';
$string['filter_allgroups'] = 'Tous les groupes';
$string['filter_mygroups'] = 'Tous mes groupes';
$string['studentcount'] = '{$a->current} sur {$a->total}';

// Statuses.
$string['status_draft'] = 'Brouillon';
$string['status_submitted'] = 'Remis';
$string['status_graded'] = 'Noté';
$string['status_nosubmission'] = 'Aucune remise';
$string['status_needsgrading'] = 'À noter';
$string['status_new'] = 'Non remis';
$string['status_late'] = 'En retard : {$a}';

// Teacher notes.
$string['notes'] = 'Notes de l\'enseignant';
$string['notes_desc'] = 'Notes privées visibles uniquement par les enseignants et les modérateurs.';
$string['savenote'] = 'Enregistrer la note';
$string['deletenote'] = 'Supprimer';
$string['addnote'] = 'Ajouter une note';
$string['nonotes'] = 'Aucune note pour le moment.';
$string['confirmdelete_note'] = 'Êtes-vous sûr de vouloir supprimer cette note ?';

// Comment library.
$string['commentlibrary'] = 'Bibliothèque de commentaires';
$string['savecomment'] = 'Enregistrer dans la bibliothèque';
$string['insertcomment'] = 'Insérer';
$string['deletecomment'] = 'Supprimer';
$string['newcomment'] = 'Nouveau commentaire...';
$string['nocomments'] = 'Aucun commentaire enregistré.';

// UI.
$string['loading'] = 'Chargement...';
$string['saving'] = 'Enregistrement...';
$string['saved'] = 'Enregistré';
$string['previousstudent'] = 'Étudiant précédent';
$string['nextstudent'] = 'Étudiant suivant';
$string['expandfilters'] = 'Afficher les filtres';
$string['collapsefilters'] = 'Masquer les filtres';
$string['backtocourse'] = 'Retour au cours';
$string['rubric'] = 'Grille d\'évaluation';
$string['markingguide'] = 'Guide de notation';
$string['criterion'] = 'Critère';
$string['score'] = 'Score';
$string['remark'] = 'Remarque';
$string['total'] = 'Total : {$a}';
$string['viewallsubmissions'] = 'Voir toutes les remises';
$string['layout_both'] = 'Vue partagée';
$string['layout_preview'] = 'Aperçu uniquement';
$string['layout_grade'] = 'Notation uniquement';
$string['manualquestions'] = 'Questions manuelles';
$string['response'] = 'Réponse';
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
$string['plagiarism_noresults'] = 'Aucun résultat de plagiat disponible.';
$string['plagiarism_pending'] = 'Analyse de plagiat en cours';
$string['plagiarism_error'] = 'L\'analyse de plagiat a échoué';

// Student feedback view.
$string['assessment_criteria'] = 'Critères d\'évaluation';
$string['teacher_remark'] = 'Commentaire de l\'enseignant';
$string['view_feedback'] = 'Voir le commentaire';
$string['view_annotated_feedback'] = 'Voir le commentaire annoté';
$string['feedback_not_available'] = 'Votre commentaire n\'est pas encore disponible. Veuillez vérifier après que votre remise a été notée et publiée.';
$string['no_annotated_files'] = 'Il n\'y a pas de fichiers PDF annotés pour votre remise.';
$string['feedback_banner_default'] = 'Votre enseignant a fourni un commentaire sur votre remise.';

// Document conversion.
$string['conversion_failed'] = 'Ce fichier n\'a pas pu être converti en PDF pour l\'aperçu.';
$string['converting_file'] = 'Conversion du document en PDF...';
$string['conversion_timeout'] = 'La conversion du document prend trop de temps. Veuillez réessayer plus tard.';
$string['download_annotated_pdf'] = 'Télécharger le PDF annoté';
$string['download_original_submission'] = 'Télécharger la remise originale : {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Notes privées de l\'enseignant stockées par étudiant et par activité dans le correcteur unifié.';
$string['privacy:metadata:notes:cmid'] = 'L\'identifiant du module de cours auquel la note se rapporte.';
$string['privacy:metadata:notes:userid'] = 'L\'étudiant concerné par la note.';
$string['privacy:metadata:notes:authorid'] = 'L\'enseignant qui a rédigé la note.';
$string['privacy:metadata:notes:content'] = 'Le contenu de la note.';
$string['privacy:metadata:comments'] = 'Entrées réutilisables de la bibliothèque de commentaires dans le correcteur unifié.';
$string['privacy:metadata:comments:userid'] = 'L\'enseignant propriétaire du commentaire.';
$string['privacy:metadata:comments:content'] = 'Le contenu du commentaire.';
$string['privacy:metadata:preferences'] = 'Préférences de l\'utilisateur pour l\'interface du correcteur unifié.';
$string['privacy:metadata:preferences:userid'] = 'L\'utilisateur à qui appartiennent les préférences.';
$string['privacy:metadata:preferences:data'] = 'Les données de préférences encodées en JSON.';
$string['privacy:metadata:annotations'] = 'Annotations de documents stockées dans le correcteur unifié.';
$string['privacy:metadata:annotations:cmid'] = 'L\'identifiant du module de cours auquel l\'annotation se rapporte.';
$string['privacy:metadata:annotations:userid'] = 'L\'étudiant dont la remise est annotée.';
$string['privacy:metadata:annotations:authorid'] = 'L\'enseignant qui a créé l\'annotation.';
$string['privacy:metadata:annotations:data'] = 'Les données d\'annotation (JSON Fabric.js).';
$string['annotations'] = 'Annotations';

// PDF viewer.
$string['pdf_prevpage'] = 'Page précédente';
$string['pdf_nextpage'] = 'Page suivante';
$string['pdf_zoomin'] = 'Zoom avant';
$string['pdf_zoomout'] = 'Zoom arrière';
$string['pdf_zoomfit'] = 'Ajuster à la largeur';
$string['pdf_search'] = 'Rechercher dans le document';

// Annotation tools.
$string['annotate_tools'] = 'Outils d\'annotation';
$string['annotate_select'] = 'Sélectionner';
$string['annotate_textselect'] = 'Sélectionner le texte';
$string['annotate_comment'] = 'Commentaire';
$string['annotate_highlight'] = 'Surligner';
$string['annotate_pen'] = 'Stylo';
$string['annotate_pen_fine'] = 'Fin';
$string['annotate_pen_medium'] = 'Moyen';
$string['annotate_pen_thick'] = 'Épais';
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
$string['annotate_shape_arrow'] = 'Flèche';
$string['annotate_shape_line'] = 'Ligne';
$string['annotate_undo'] = 'Annuler';
$string['annotate_redo'] = 'Rétablir';
$string['annotate_delete'] = 'Supprimer la sélection';
$string['annotate_clearall'] = 'Tout effacer';
$string['annotate_clear_confirm'] = 'Êtes-vous sûr de vouloir effacer toutes les annotations de cette page ? Cette action est irréversible.';

// Document info.
$string['docinfo'] = 'Informations du document';
$string['docinfo_filename'] = 'Nom du fichier';
$string['docinfo_filesize'] = 'Taille du fichier';
$string['docinfo_pages'] = 'Pages';
$string['docinfo_wordcount'] = 'Nombre de mots';
$string['docinfo_author'] = 'Auteur';
$string['docinfo_creator'] = 'Créateur';
$string['docinfo_created'] = 'Créé';
$string['docinfo_modified'] = 'Modifié';
$string['docinfo_calculating'] = 'Calcul en cours...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Voir le commentaire du forum';
$string['forum_your_posts'] = 'Vos messages du forum';
$string['forum_no_posts'] = 'Vous n\'avez publié aucun message dans ce forum.';
$string['forum_feedback_banner'] = 'Votre enseignant a noté votre participation au forum.';
$string['forum_wordcount'] = '{$a} mots';
$string['forum_posts_pill'] = 'Messages';
$string['submission_content_pill'] = 'Remise';
$string['forum_tab_posts'] = 'Messages';
$string['forum_tab_files'] = 'Fichiers annotés';
$string['view_quiz_feedback'] = 'Voir le commentaire du test';
$string['quiz_feedback_banner'] = 'Votre enseignant a fourni un commentaire sur votre test.';
$string['quiz_your_attempt'] = 'Votre tentative';
$string['quiz_no_attempt'] = 'Vous n\'avez complété aucune tentative pour ce test.';
$string['quiz_select_attempt'] = 'Sélectionner la tentative';
$string['select_attempt'] = 'Sélectionner la tentative';
$string['attempt_label'] = 'Tentative {$a}';

// Post grades.
$string['grades_posted'] = 'Notes publiées';
$string['grades_hidden'] = 'Notes masquées';
$string['post_grades'] = 'Publier les notes';
$string['unpost_grades'] = 'Masquer les notes';
$string['confirm_post_grades'] = 'Publier toutes les notes de cette activité ? Les étudiants pourront voir leurs notes et commentaires.';
$string['confirm_unpost_grades'] = 'Masquer toutes les notes de cette activité ? Les étudiants ne pourront plus voir leurs notes et commentaires.';
$string['schedule_post'] = 'Publier à une date';
$string['schedule_post_btn'] = 'Programmer';
$string['grades_scheduled'] = 'Publication le {$a}';
$string['schedule_must_be_future'] = 'La date programmée doit être dans le futur.';
$string['quiz_post_grades_disabled'] = 'La publication des notes n\'est pas disponible pour les tests. La visibilité des notes est contrôlée par les options de relecture du test.';
$string['quiz_post_grades_no_schedule'] = 'La programmation n\'est pas disponible pour les tests. Utilisez Publier ou Masquer à la place.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Remettre en brouillon';
$string['action_remove_submission'] = 'Supprimer la remise';
$string['action_lock'] = 'Empêcher les modifications de la remise';
$string['action_unlock'] = 'Autoriser les modifications de la remise';
$string['action_edit_submission'] = 'Modifier la remise';
$string['action_grant_extension'] = 'Accorder une prolongation';
$string['action_edit_extension'] = 'Modifier la prolongation';
$string['action_submit_for_grading'] = 'Soumettre pour notation';
$string['confirm_revert_to_draft'] = 'Êtes-vous sûr de vouloir remettre cette remise en brouillon ?';
$string['confirm_remove_submission'] = 'Êtes-vous sûr de vouloir supprimer cette remise ? Cette action est irréversible.';
$string['confirm_lock_submission'] = 'Empêcher cet étudiant de modifier sa remise ?';
$string['confirm_unlock_submission'] = 'Autoriser cet étudiant à modifier sa remise ?';
$string['confirm_submit_for_grading'] = 'Soumettre ce brouillon au nom de l\'étudiant ?';
$string['invalidaction'] = 'Action de remise invalide.';

// Override actions.
$string['override'] = 'Dérogation';
$string['action_add_override'] = 'Ajouter une dérogation';
$string['action_edit_override'] = 'Modifier la dérogation';
$string['action_delete_override'] = 'Supprimer la dérogation';
$string['confirm_delete_override'] = 'Êtes-vous sûr de vouloir supprimer cette dérogation utilisateur ?';
$string['override_saved'] = 'Dérogation enregistrée avec succès.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Supprimer la prolongation';
$string['confirm_delete_extension'] = 'Êtes-vous sûr de vouloir supprimer cette prolongation de date limite ?';
$string['quiz_extension_original_duedate'] = 'Date limite originale';
$string['quiz_extension_current_extension'] = 'Prolongation actuelle';
$string['quiz_extension_new_duedate'] = 'Date limite de prolongation';
$string['quiz_extension_must_be_after_duedate'] = 'La date de prolongation doit être postérieure à la date limite actuelle.';
$string['quiz_extension_plugin_missing'] = 'Le plugin quizaccess_duedate est requis pour les prolongations de test mais n\'est pas installé.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Date limite du forum';
$string['forum_extension_current_extension'] = 'Prolongation actuelle';
$string['forum_extension_new_duedate'] = 'Date limite de prolongation';
$string['forum_extension_must_be_after_duedate'] = 'La date de prolongation doit être postérieure à la date limite du forum.';

// Student profile popout.
$string['profile_view_full'] = 'Voir le profil complet';
$string['profile_login_as'] = 'Se connecter en tant que';
$string['profile_no_email'] = 'Aucune adresse courriel disponible';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Expression régulière du code de cours';
$string['setting_coursecode_regex_desc'] = 'La bibliothèque de commentaires organise les commentaires enregistrés par code de cours, afin que les enseignants puissent réutiliser leurs commentaires d\'une session à l\'autre (par exemple d\'un semestre à l\'autre). Ce paramètre contrôle comment les codes de cours sont extraits des noms abrégés des cours Moodle. Entrez une expression régulière PHP correspondant à la portion code de vos noms abrégés (par exemple <code>/[A-Z]{3}\\d{4}/</code> extrairait <strong>THE2201</strong> d\'un nom abrégé comme <em>THE2201-2026-S1</em>). Laissez vide pour utiliser le nom abrégé complet comme code de cours.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Activer le formulaire de signalement d\'improbité académique';
$string['setting_enable_report_form_desc'] = 'Lorsque cette option est activée, un bouton « Signaler une improbité académique » apparaît dans les sections de plagiat, menant à un formulaire de signalement externe.';
$string['setting_report_form_url'] = 'Modèle d\'URL du formulaire de signalement';
$string['setting_report_form_url_desc'] = 'URL du formulaire de signalement d\'improbité académique. Espaces réservés pris en charge : <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Ceux-ci sont remplacés à l\'exécution par des valeurs encodées pour URL. Pour Microsoft Forms, utilisez la fonctionnalité « Obtenir l\'URL préremplie » pour trouver les noms des paramètres.';
$string['report_impropriety'] = 'Signaler une improbité académique';

// Comment library v2.
$string['clib_title'] = 'Bibliothèque de commentaires';
$string['clib_all'] = 'Tous';
$string['clib_quick_add'] = 'Ajout rapide de commentaire...';
$string['clib_manage'] = 'Gérer la bibliothèque';
$string['clib_no_comments'] = 'Aucun commentaire pour le moment.';
$string['clib_insert'] = 'Insérer';
$string['clib_copied'] = 'Commentaire copié dans le presse-papiers';
$string['clib_my_library'] = 'Ma bibliothèque';
$string['clib_shared_library'] = 'Bibliothèque partagée';
$string['clib_new_comment'] = 'Nouveau commentaire';
$string['clib_edit_comment'] = 'Modifier le commentaire';
$string['clib_delete_comment'] = 'Supprimer le commentaire';
$string['clib_confirm_delete'] = 'Êtes-vous sûr de vouloir supprimer ce commentaire ?';
$string['clib_share'] = 'Partager';
$string['clib_unshare'] = 'Ne plus partager';
$string['clib_import'] = 'Importer';
$string['clib_imported'] = 'Commentaire importé dans votre bibliothèque';
$string['clib_copy_to_course'] = 'Copier vers le cours';
$string['clib_all_courses'] = 'Tous les cours';
$string['clib_tags'] = 'Étiquettes';
$string['clib_manage_tags'] = 'Gérer les étiquettes';
$string['clib_new_tag'] = 'Nouvelle étiquette';
$string['clib_edit_tag'] = 'Modifier l\'étiquette';
$string['clib_delete_tag'] = 'Supprimer l\'étiquette';
$string['clib_confirm_delete_tag'] = 'Êtes-vous sûr de vouloir supprimer cette étiquette ? Elle sera retirée de tous les commentaires.';
$string['clib_system_tag'] = 'Par défaut du système';
$string['clib_shared_by'] = 'Partagé par {$a}';
$string['clib_no_shared'] = 'Aucun commentaire partagé disponible.';
$string['clib_picker_freetext'] = 'Ou écrivez le vôtre...';
$string['clib_picker_loading'] = 'Chargement des commentaires...';
$string['clib_offline_mode'] = 'Affichage des commentaires en cache — la modification n\'est pas disponible hors ligne.';
$string['unifiedgrader:sharecomments'] = 'Partager des commentaires de la bibliothèque avec d\'autres enseignants';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Entrées de la bibliothèque de commentaires dans le correcteur unifié.';
$string['privacy:metadata:clib:userid'] = 'L\'enseignant propriétaire du commentaire.';
$string['privacy:metadata:clib:coursecode'] = 'Le code de cours associé au commentaire.';
$string['privacy:metadata:clib:content'] = 'Le contenu du commentaire.';
$string['privacy:metadata:cltag'] = 'Étiquettes de la bibliothèque de commentaires dans le correcteur unifié.';
$string['privacy:metadata:cltag:userid'] = 'L\'enseignant propriétaire de l\'étiquette.';
$string['privacy:metadata:cltag:name'] = 'Le nom de l\'étiquette.';

// Penalties.
$string['penalties'] = 'Pénalités';
$string['penalty_late'] = 'Remise en retard';
$string['penalty_late_days'] = '{$a} jour(s) de retard';
$string['penalty_late_auto'] = 'Calculée automatiquement selon les règles de pénalité';
$string['penalty_wordcount'] = 'Nombre de mots';
$string['penalty_other'] = 'Autre';
$string['penalty_custom'] = 'Personnalisée';
$string['penalty_label_placeholder'] = 'Libellé (15 caractères max)';
$string['penalty_active'] = 'Pénalités actives';
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
$string['finalgradeafterpenalties'] = 'Note finale après pénalités :';
$string['cannotdeleteautopenalty'] = 'Les pénalités de retard sont calculées automatiquement et ne peuvent pas être supprimées.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Télécharger le PDF de commentaire';
$string['feedback_summary_overall_feedback'] = 'Commentaire général';
$string['feedback_summary_graded_on'] = 'Noté le {$a}';
$string['feedback_summary_generated_by'] = 'Généré par le correcteur unifié';
$string['feedback_summary_media_note'] = 'Le contenu média est disponible dans la vue de commentaire en ligne.';
$string['feedback_summary_no_grade'] = 'N/A';
$string['feedback_summary_remark'] = 'Commentaire de l\'enseignant';
$string['feedback_summary_total'] = 'Total';
$string['levels'] = 'Niveaux';
$string['error_gs_not_configured'] = 'GhostScript n\'est pas configuré sur ce serveur Moodle. L\'administrateur doit définir le chemin de GhostScript dans Administration du site > Plugins > Modules d\'activité > Devoir > Commentaire > Annoter le PDF.';
$string['error_pdf_combine_failed'] = 'Échec de la combinaison des fichiers PDF : {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Pénalités de note appliquées par les enseignants dans le correcteur unifié.';
$string['privacy:metadata:penalty:userid'] = 'L\'étudiant à qui la pénalité a été appliquée.';
$string['privacy:metadata:penalty:authorid'] = 'L\'enseignant qui a appliqué la pénalité.';
$string['privacy:metadata:penalty:category'] = 'La catégorie de la pénalité (nombre de mots ou autre).';
$string['privacy:metadata:penalty:label'] = 'Le libellé personnalisé de la pénalité.';
$string['privacy:metadata:penalty:percentage'] = 'Le pourcentage de la pénalité.';
$string['privacy:metadata:fext'] = 'Prolongations de date limite de forum accordées par les enseignants dans le correcteur unifié.';
$string['privacy:metadata:fext:userid'] = 'L\'étudiant à qui la prolongation a été accordée.';
$string['privacy:metadata:fext:authorid'] = 'L\'enseignant qui a accordé la prolongation.';
$string['privacy:metadata:fext:extensionduedate'] = 'La date limite prolongée.';
$string['privacy:metadata:qfb'] = 'Commentaires de test par tentative stockés par le correcteur unifié.';
$string['privacy:metadata:qfb:userid'] = 'L\'étudiant concerné par le commentaire.';
$string['privacy:metadata:qfb:grader'] = 'L\'enseignant qui a fourni le commentaire.';
$string['privacy:metadata:qfb:feedback'] = 'Le texte du commentaire.';
$string['privacy:metadata:qfb:attemptnumber'] = 'Le numéro de tentative du test.';
$string['privacy:metadata:scomm'] = 'Commentaires de soumission stockés par le Unified Grader.';
$string['privacy:metadata:scomm:cmid'] = 'Le module de cours auquel appartient le commentaire.';
$string['privacy:metadata:scomm:userid'] = 'L\'étudiant concerné par le fil de commentaires.';
$string['privacy:metadata:scomm:authorid'] = 'L\'utilisateur qui a écrit le commentaire.';
$string['privacy:metadata:scomm:content'] = 'Le contenu du commentaire.';
$string['privacy_forum_extensions'] = 'Prolongations de forum';
$string['privacy_quiz_feedback'] = 'Commentaires de test';

// Notification strings.
$string['messageprovider:submission_comment'] = 'Notifications de commentaires de soumission';
$string['notification_comment_subject'] = 'Nouveau commentaire sur {$a->activityname}';
$string['notification_comment_body'] = '<p><strong>{$a->authorfullname}</strong> a publié un commentaire sur <a href="{$a->activityurl}">{$a->activityname}</a> dans {$a->coursename} ({$a->timecreated}) :</p><blockquote>{$a->content}</blockquote>';
$string['notification_comment_small'] = '{$a->authorfullname} a commenté sur {$a->activityname}';

// Offline cache and save status.
$string['allchangessaved'] = 'Toutes les modifications sont enregistrées';
$string['editing'] = 'Modification en cours...';
$string['offlinesavedlocally'] = 'Hors ligne — enregistré localement';
$string['connectionlost'] = 'Connexion perdue — votre travail est enregistré localement et sera synchronisé lors de la reconnexion.';
$string['recoveredunsavedchanges'] = 'Modifications non enregistrées récupérées de votre dernière session.';
$string['restore'] = 'Restaurer';
$string['discard'] = 'Abandonner';
$string['mark_as_graded'] = 'Marquer comme noté';
$string['manuallymarkedquestions'] = 'Questions notées manuellement';
