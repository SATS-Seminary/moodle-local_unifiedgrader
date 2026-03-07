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
 * Language strings for local_unifiedgrader (Spanish).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Calificador unificado';
$string['grading_interface'] = 'Calificador unificado';
$string['nopermission'] = 'No tiene permiso para usar el calificador unificado.';
$string['invalidactivitytype'] = 'Este tipo de actividad no es compatible con el calificador unificado.';
$string['invalidmodule'] = 'M\u00f3dulo de actividad no v\u00e1lido.';
$string['viewfeedback'] = 'Ver retroalimentaci\u00f3n';

// Attempts.
$string['attempt'] = 'Intento';

// Capabilities.
$string['unifiedgrader:grade'] = 'Usar el calificador unificado para calificar';
$string['unifiedgrader:viewall'] = 'Ver todos los estudiantes en el calificador unificado';
$string['unifiedgrader:viewnotes'] = 'Ver notas privadas del profesor';
$string['unifiedgrader:managenotes'] = 'Crear y editar notas privadas del profesor';
$string['unifiedgrader:viewfeedback'] = 'Ver retroalimentaci\u00f3n anotada del calificador unificado';

// Settings.
$string['setting_enable_assign'] = 'Habilitar para Tareas';
$string['setting_enable_assign_desc'] = 'Permitir que el calificador unificado se use para actividades de tarea.';
$string['setting_enable_forum'] = 'Habilitar para Foros';
$string['setting_enable_forum_desc'] = 'Permitir que el calificador unificado se use para actividades de foro.';
$string['setting_enable_quiz'] = 'Habilitar para Cuestionarios';
$string['setting_enable_quiz_desc'] = 'Permitir que el calificador unificado se use para actividades de cuestionario.';
$string['setting_enable_quiz_post_grades'] = 'Habilitar publicaci\u00f3n de calificaciones para cuestionarios';
$string['setting_enable_quiz_post_grades_desc'] = 'La visibilidad de las calificaciones del cuestionario normalmente se gestiona mediante las opciones de revisi\u00f3n del cuestionario. Cuando est\u00e1 habilitado, el bot\u00f3n "Publicar calificaciones" del calificador unificado actualizar\u00e1 las opciones de revisi\u00f3n del cuestionario program\u00e1ticamente para mostrar u ocultar las notas. Cuando est\u00e1 deshabilitado (por defecto), el bot\u00f3n de publicar calificaciones se oculta para los cuestionarios.';
$string['setting_allow_manual_override'] = 'Permitir anulaci\u00f3n manual de calificaci\u00f3n';
$string['setting_allow_manual_override_desc'] = 'Cuando est\u00e1 habilitado, los profesores pueden escribir manualmente una calificaci\u00f3n incluso cuando hay una r\u00fabrica o gu\u00eda de calificaci\u00f3n configurada. Cuando est\u00e1 deshabilitado, la calificaci\u00f3n se calcula exclusivamente a partir de los criterios de la r\u00fabrica o gu\u00eda de calificaci\u00f3n.';

// Grading interface.
$string['grade'] = 'Calificaci\u00f3n';
$string['savegrade'] = 'Guardar calificaci\u00f3n';
$string['savefeedback'] = 'Guardar retroalimentaci\u00f3n';
$string['savinggrade'] = 'Guardando calificaci\u00f3n...';
$string['gradesaved'] = 'Calificaci\u00f3n guardada';
$string['error_saving'] = 'Error al guardar la calificaci\u00f3n.';
$string['error_network'] = 'No se pudo conectar al servidor. Por favor, verifique su conexi\u00f3n e int\u00e9ntelo de nuevo.';
$string['error_offline_comments'] = 'No se pueden agregar comentarios sin conexi\u00f3n.';
$string['feedback'] = 'Retroalimentaci\u00f3n';
$string['overall_feedback'] = 'Retroalimentaci\u00f3n general';
$string['feedback_saved'] = 'Retroalimentaci\u00f3n (guardada)';
$string['edit_feedback'] = 'Editar';
$string['delete_feedback'] = 'Eliminar';
$string['confirm_delete_feedback'] = '\u00bfEst\u00e1 seguro de que desea eliminar esta retroalimentaci\u00f3n? La calificaci\u00f3n se conservar\u00e1.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Expandir';

// Submissions.
$string['submission'] = 'Entrega';
$string['nosubmission'] = 'Sin entrega';
$string['previewpanel'] = 'Vista previa de la entrega';
$string['markingpanel'] = 'Panel de calificaci\u00f3n';
$string['onlinetext'] = 'Texto en l\u00ednea';
$string['submittedfiles'] = 'Archivos entregados';
$string['viewfile'] = 'Ver archivo';

// Participants.
$string['participants'] = 'Participantes';
$string['search'] = 'Buscar participantes...';
$string['sortby'] = 'Ordenar por';
$string['sortby_fullname'] = 'Nombre completo';
$string['sortby_submittedat'] = 'Fecha de entrega';
$string['sortby_status'] = 'Estado';
$string['filter_all'] = 'Todos los participantes';
$string['filter_submitted'] = 'Entregado';
$string['filter_needsgrading'] = 'Sin calificar';
$string['filter_notsubmitted'] = 'No entregado';
$string['filter_graded'] = 'Calificado';
$string['filter_late'] = 'Tarde';
$string['filter_allgroups'] = 'Todos los grupos';
$string['studentcount'] = '{$a->current} de {$a->total}';

// Statuses.
$string['status_draft'] = 'Borrador';
$string['status_submitted'] = 'Entregado';
$string['status_graded'] = 'Calificado';
$string['status_nosubmission'] = 'Sin entrega';
$string['status_needsgrading'] = 'Necesita calificaci\u00f3n';
$string['status_new'] = 'No entregado';
$string['status_late'] = 'Tarde: {$a}';

// Teacher notes.
$string['notes'] = 'Notas del profesor';
$string['notes_desc'] = 'Notas privadas visibles solo para profesores y moderadores.';
$string['savenote'] = 'Guardar nota';
$string['deletenote'] = 'Eliminar';
$string['addnote'] = 'Agregar nota';
$string['nonotes'] = 'A\u00fan no hay notas.';
$string['confirmdelete_note'] = '\u00bfEst\u00e1 seguro de que desea eliminar esta nota?';

// Comment library.
$string['commentlibrary'] = 'Biblioteca de comentarios';
$string['savecomment'] = 'Guardar en la biblioteca';
$string['insertcomment'] = 'Insertar';
$string['deletecomment'] = 'Eliminar';
$string['newcomment'] = 'Nuevo comentario...';
$string['nocomments'] = 'No hay comentarios guardados.';

// UI.
$string['loading'] = 'Cargando...';
$string['saving'] = 'Guardando...';
$string['saved'] = 'Guardado';
$string['previousstudent'] = 'Estudiante anterior';
$string['nextstudent'] = 'Siguiente estudiante';
$string['expandfilters'] = 'Mostrar filtros';
$string['collapsefilters'] = 'Ocultar filtros';
$string['backtocourse'] = 'Volver al curso';
$string['rubric'] = 'R\u00fabrica';
$string['markingguide'] = 'Gu\u00eda de calificaci\u00f3n';
$string['criterion'] = 'Criterio';
$string['score'] = 'Puntuaci\u00f3n';
$string['remark'] = 'Observaci\u00f3n';
$string['total'] = 'Total: {$a}';
$string['viewallsubmissions'] = 'Ver todas las entregas';
$string['layout_both'] = 'Vista dividida';
$string['layout_preview'] = 'Solo vista previa';
$string['layout_grade'] = 'Solo calificaci\u00f3n';
$string['manualquestions'] = 'Preguntas manuales';
$string['response'] = 'Respuesta';
$string['teachercomment'] = 'Comentario del profesor';

// Submission comments.
$string['submissioncomments'] = 'Comentarios de la entrega';
$string['nocommentsyet'] = 'A\u00fan no hay comentarios';
$string['addcomment'] = 'Agregar un comentario...';
$string['postcomment'] = 'Publicar';
$string['deletesubmissioncomment'] = 'Eliminar comentario';

// Feedback files.
$string['feedbackfiles'] = 'Archivos de retroalimentaci\u00f3n';

// Plagiarism.
$string['plagiarism'] = 'Plagio';
$string['plagiarism_noresults'] = 'No hay resultados de plagio disponibles.';
$string['plagiarism_pending'] = 'An\u00e1lisis de plagio en progreso';
$string['plagiarism_error'] = 'El an\u00e1lisis de plagio fall\u00f3';

// Student feedback view.
$string['assessment_criteria'] = 'Criterios de evaluaci\u00f3n';
$string['teacher_remark'] = 'Retroalimentaci\u00f3n del profesor';
$string['view_feedback'] = 'Ver retroalimentaci\u00f3n';
$string['view_annotated_feedback'] = 'Ver retroalimentaci\u00f3n anotada';
$string['feedback_not_available'] = 'Su retroalimentaci\u00f3n a\u00fan no est\u00e1 disponible. Por favor, vuelva a consultar despu\u00e9s de que su entrega haya sido calificada y publicada.';
$string['no_annotated_files'] = 'No hay archivos PDF anotados para su entrega.';
$string['feedback_banner_default'] = 'Su profesor ha proporcionado retroalimentaci\u00f3n sobre su entrega.';

// Document conversion.
$string['conversion_failed'] = 'Este archivo no pudo ser convertido a PDF para la vista previa.';
$string['converting_file'] = 'Convirtiendo documento a PDF...';
$string['conversion_timeout'] = 'La conversi\u00f3n del documento est\u00e1 tardando demasiado. Por favor, int\u00e9ntelo m\u00e1s tarde.';
$string['download_annotated_pdf'] = 'Descargar PDF anotado';
$string['download_original_submission'] = 'Descargar entrega original: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Notas privadas del profesor almacenadas por estudiante y actividad en el calificador unificado.';
$string['privacy:metadata:notes:cmid'] = 'El ID del m\u00f3dulo del curso al que pertenece la nota.';
$string['privacy:metadata:notes:userid'] = 'El estudiante sobre el que trata la nota.';
$string['privacy:metadata:notes:authorid'] = 'El profesor que escribi\u00f3 la nota.';
$string['privacy:metadata:notes:content'] = 'El contenido de la nota.';
$string['privacy:metadata:comments'] = 'Entradas reutilizables de la biblioteca de comentarios en el calificador unificado.';
$string['privacy:metadata:comments:userid'] = 'El profesor propietario del comentario.';
$string['privacy:metadata:comments:content'] = 'El contenido del comentario.';
$string['privacy:metadata:preferences'] = 'Preferencias del usuario para la interfaz del calificador unificado.';
$string['privacy:metadata:preferences:userid'] = 'El usuario al que pertenecen las preferencias.';
$string['privacy:metadata:preferences:data'] = 'Los datos de preferencias codificados en JSON.';
$string['privacy:metadata:annotations'] = 'Anotaciones de documentos almacenadas en el calificador unificado.';
$string['privacy:metadata:annotations:cmid'] = 'El ID del m\u00f3dulo del curso al que pertenece la anotaci\u00f3n.';
$string['privacy:metadata:annotations:userid'] = 'El estudiante cuya entrega est\u00e1 anotada.';
$string['privacy:metadata:annotations:authorid'] = 'El profesor que cre\u00f3 la anotaci\u00f3n.';
$string['privacy:metadata:annotations:data'] = 'Los datos de la anotaci\u00f3n (JSON de Fabric.js).';
$string['annotations'] = 'Anotaciones';

// PDF viewer.
$string['pdf_prevpage'] = 'P\u00e1gina anterior';
$string['pdf_nextpage'] = 'P\u00e1gina siguiente';
$string['pdf_zoomin'] = 'Acercar';
$string['pdf_zoomout'] = 'Alejar';
$string['pdf_zoomfit'] = 'Ajustar al ancho';
$string['pdf_search'] = 'Buscar en el documento';

// Annotation tools.
$string['annotate_tools'] = 'Herramientas de anotaci\u00f3n';
$string['annotate_select'] = 'Seleccionar';
$string['annotate_textselect'] = 'Seleccionar texto';
$string['annotate_comment'] = 'Comentario';
$string['annotate_highlight'] = 'Resaltar';
$string['annotate_pen'] = 'L\u00e1piz';
$string['annotate_pen_fine'] = 'Fino';
$string['annotate_pen_medium'] = 'Medio';
$string['annotate_pen_thick'] = 'Grueso';
$string['annotate_stamps'] = 'Sellos';
$string['annotate_stamp_check'] = 'Sello de verificaci\u00f3n';
$string['annotate_stamp_cross'] = 'Sello de cruz';
$string['annotate_stamp_question'] = 'Sello de interrogaci\u00f3n';
$string['annotate_red'] = 'Rojo';
$string['annotate_yellow'] = 'Amarillo';
$string['annotate_green'] = 'Verde';
$string['annotate_blue'] = 'Azul';
$string['annotate_black'] = 'Negro';
$string['annotate_shape'] = 'Formas';
$string['annotate_shape_rect'] = 'Rect\u00e1ngulo';
$string['annotate_shape_circle'] = 'C\u00edrculo';
$string['annotate_shape_arrow'] = 'Flecha';
$string['annotate_shape_line'] = 'L\u00ednea';
$string['annotate_undo'] = 'Deshacer';
$string['annotate_redo'] = 'Rehacer';
$string['annotate_delete'] = 'Eliminar selecci\u00f3n';
$string['annotate_clearall'] = 'Borrar todo';
$string['annotate_clear_confirm'] = '\u00bfEst\u00e1 seguro de que desea borrar todas las anotaciones de esta p\u00e1gina? Esta acci\u00f3n no se puede deshacer.';

// Document info.
$string['docinfo'] = 'Informaci\u00f3n del documento';
$string['docinfo_filename'] = 'Nombre del archivo';
$string['docinfo_filesize'] = 'Tama\u00f1o del archivo';
$string['docinfo_pages'] = 'P\u00e1ginas';
$string['docinfo_wordcount'] = 'Recuento de palabras';
$string['docinfo_author'] = 'Autor';
$string['docinfo_creator'] = 'Creador';
$string['docinfo_created'] = 'Creado';
$string['docinfo_modified'] = 'Modificado';
$string['docinfo_calculating'] = 'Calculando...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Ver retroalimentaci\u00f3n del foro';
$string['forum_your_posts'] = 'Sus publicaciones en el foro';
$string['forum_no_posts'] = 'No ha realizado ninguna publicaci\u00f3n en este foro.';
$string['forum_feedback_banner'] = 'Su profesor ha calificado su participaci\u00f3n en el foro.';
$string['forum_wordcount'] = '{$a} palabras';
$string['forum_posts_pill'] = 'Publicaciones';
$string['submission_content_pill'] = 'Entrega';
$string['forum_tab_posts'] = 'Publicaciones';
$string['forum_tab_files'] = 'Archivos anotados';
$string['view_quiz_feedback'] = 'Ver retroalimentaci\u00f3n del cuestionario';
$string['quiz_feedback_banner'] = 'Su profesor ha proporcionado retroalimentaci\u00f3n sobre su cuestionario.';
$string['quiz_your_attempt'] = 'Su intento';
$string['quiz_no_attempt'] = 'No ha completado ning\u00fan intento para este cuestionario.';
$string['quiz_select_attempt'] = 'Seleccionar intento';
$string['select_attempt'] = 'Seleccionar intento';
$string['attempt_label'] = 'Intento {$a}';

// Post grades.
$string['grades_posted'] = 'Calificaciones publicadas';
$string['grades_hidden'] = 'Calificaciones ocultas';
$string['post_grades'] = 'Publicar calificaciones';
$string['unpost_grades'] = 'Ocultar calificaciones';
$string['confirm_post_grades'] = '\u00bfPublicar todas las calificaciones de esta actividad? Los estudiantes podr\u00e1n ver sus calificaciones y retroalimentaci\u00f3n.';
$string['confirm_unpost_grades'] = '\u00bfOcultar todas las calificaciones de esta actividad? Los estudiantes ya no podr\u00e1n ver sus calificaciones y retroalimentaci\u00f3n.';
$string['schedule_post'] = 'Publicar en una fecha';
$string['schedule_post_btn'] = 'Programar';
$string['grades_scheduled'] = 'Publicaci\u00f3n {$a}';
$string['schedule_must_be_future'] = 'La fecha programada debe ser en el futuro.';
$string['quiz_post_grades_disabled'] = 'La publicaci\u00f3n de calificaciones no est\u00e1 disponible para cuestionarios. La visibilidad de las calificaciones se controla mediante las opciones de revisi\u00f3n del cuestionario.';
$string['quiz_post_grades_no_schedule'] = 'La programaci\u00f3n no est\u00e1 disponible para cuestionarios. Use Publicar u Ocultar en su lugar.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Revertir a borrador';
$string['action_remove_submission'] = 'Eliminar entrega';
$string['action_lock'] = 'Impedir cambios en la entrega';
$string['action_unlock'] = 'Permitir cambios en la entrega';
$string['action_edit_submission'] = 'Editar entrega';
$string['action_grant_extension'] = 'Conceder extensi\u00f3n';
$string['action_edit_extension'] = 'Editar extensi\u00f3n';
$string['action_submit_for_grading'] = 'Enviar para calificaci\u00f3n';
$string['confirm_revert_to_draft'] = '\u00bfEst\u00e1 seguro de que desea revertir esta entrega al estado de borrador?';
$string['confirm_remove_submission'] = '\u00bfEst\u00e1 seguro de que desea eliminar esta entrega? Esta acci\u00f3n no se puede deshacer.';
$string['confirm_lock_submission'] = '\u00bfImpedir que este estudiante realice cambios en su entrega?';
$string['confirm_unlock_submission'] = '\u00bfPermitir que este estudiante realice cambios en su entrega?';
$string['confirm_submit_for_grading'] = '\u00bfEnviar este borrador en nombre del estudiante?';
$string['invalidaction'] = 'Acci\u00f3n de entrega no v\u00e1lida.';

// Override actions.
$string['override'] = 'Anulaci\u00f3n';
$string['action_add_override'] = 'Agregar anulaci\u00f3n';
$string['action_edit_override'] = 'Editar anulaci\u00f3n';
$string['action_delete_override'] = 'Eliminar anulaci\u00f3n';
$string['confirm_delete_override'] = '\u00bfEst\u00e1 seguro de que desea eliminar esta anulaci\u00f3n de usuario?';
$string['override_saved'] = 'Anulaci\u00f3n guardada correctamente.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Eliminar extensi\u00f3n';
$string['confirm_delete_extension'] = '\u00bfEst\u00e1 seguro de que desea eliminar esta extensi\u00f3n de fecha l\u00edmite?';
$string['quiz_extension_original_duedate'] = 'Fecha l\u00edmite original';
$string['quiz_extension_current_extension'] = 'Extensi\u00f3n actual';
$string['quiz_extension_new_duedate'] = 'Nueva fecha l\u00edmite de extensi\u00f3n';
$string['quiz_extension_must_be_after_duedate'] = 'La fecha de extensi\u00f3n debe ser posterior a la fecha l\u00edmite actual.';
$string['quiz_extension_plugin_missing'] = 'El plugin quizaccess_duedate es necesario para las extensiones de cuestionario pero no est\u00e1 instalado.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Fecha l\u00edmite del foro';
$string['forum_extension_current_extension'] = 'Extensi\u00f3n actual';
$string['forum_extension_new_duedate'] = 'Nueva fecha l\u00edmite de extensi\u00f3n';
$string['forum_extension_must_be_after_duedate'] = 'La fecha de extensi\u00f3n debe ser posterior a la fecha l\u00edmite del foro.';

// Student profile popout.
$string['profile_view_full'] = 'Ver perfil completo';
$string['profile_login_as'] = 'Iniciar sesi\u00f3n como';
$string['profile_no_email'] = 'Correo electr\u00f3nico no disponible';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Expresi\u00f3n regular del c\u00f3digo de curso';
$string['setting_coursecode_regex_desc'] = 'La biblioteca de comentarios organiza los comentarios guardados por c\u00f3digo de curso, para que los profesores puedan reutilizar la retroalimentaci\u00f3n en diferentes ofertas del mismo curso (p. ej., de semestre a semestre). Esta configuraci\u00f3n controla c\u00f3mo se extraen los c\u00f3digos de curso de los nombres cortos de los cursos de Moodle. Ingrese un patr\u00f3n de expresi\u00f3n regular PHP que coincida con la porci\u00f3n del c\u00f3digo de sus nombres cortos (p. ej., <code>/[A-Z]{3}\\d{4}/</code> extraer\u00eda <strong>THE2201</strong> de un nombre corto como <em>THE2201-2026-S1</em>). Deje vac\u00edo para usar el nombre corto completo como c\u00f3digo de curso.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Habilitar formulario de reporte de falta acad\u00e9mica';
$string['setting_enable_report_form_desc'] = 'Cuando est\u00e1 habilitado, aparece un bot\u00f3n "Reportar falta acad\u00e9mica" en las secciones de plagio, con enlace a un formulario de reporte externo.';
$string['setting_report_form_url'] = 'Plantilla de URL del formulario de reporte';
$string['setting_report_form_url_desc'] = 'URL del formulario de reporte de falta acad\u00e9mica. Marcadores de posici\u00f3n admitidos: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Estos se reemplazan en tiempo de ejecuci\u00f3n con valores codificados para URL. Para Microsoft Forms, use la funci\u00f3n "Obtener URL pre-rellenada" para encontrar los nombres de los par\u00e1metros.';
$string['report_impropriety'] = 'Reportar falta acad\u00e9mica';

// Comment library v2.
$string['clib_title'] = 'Biblioteca de comentarios';
$string['clib_all'] = 'Todos';
$string['clib_quick_add'] = 'Agregar comentario r\u00e1pido...';
$string['clib_manage'] = 'Administrar biblioteca';
$string['clib_no_comments'] = 'A\u00fan no hay comentarios.';
$string['clib_insert'] = 'Insertar';
$string['clib_copied'] = 'Comentario copiado al portapapeles';
$string['clib_my_library'] = 'Mi biblioteca';
$string['clib_shared_library'] = 'Biblioteca compartida';
$string['clib_new_comment'] = 'Nuevo comentario';
$string['clib_edit_comment'] = 'Editar comentario';
$string['clib_delete_comment'] = 'Eliminar comentario';
$string['clib_confirm_delete'] = '\u00bfEst\u00e1 seguro de que desea eliminar este comentario?';
$string['clib_share'] = 'Compartir';
$string['clib_unshare'] = 'Dejar de compartir';
$string['clib_import'] = 'Importar';
$string['clib_imported'] = 'Comentario importado a su biblioteca';
$string['clib_copy_to_course'] = 'Copiar al curso';
$string['clib_all_courses'] = 'Todos los cursos';
$string['clib_tags'] = 'Etiquetas';
$string['clib_manage_tags'] = 'Administrar etiquetas';
$string['clib_new_tag'] = 'Nueva etiqueta';
$string['clib_edit_tag'] = 'Editar etiqueta';
$string['clib_delete_tag'] = 'Eliminar etiqueta';
$string['clib_confirm_delete_tag'] = '\u00bfEst\u00e1 seguro de que desea eliminar esta etiqueta? Se eliminar\u00e1 de todos los comentarios.';
$string['clib_system_tag'] = 'Predeterminado del sistema';
$string['clib_shared_by'] = 'Compartido por {$a}';
$string['clib_no_shared'] = 'No hay comentarios compartidos disponibles.';
$string['clib_picker_freetext'] = 'O escriba el suyo...';
$string['clib_picker_loading'] = 'Cargando comentarios...';
$string['clib_offline_mode'] = 'Mostrando comentarios en cach\u00e9 \u2014 la edici\u00f3n no est\u00e1 disponible sin conexi\u00f3n.';
$string['unifiedgrader:sharecomments'] = 'Compartir comentarios en la biblioteca con otros profesores';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Entradas de la biblioteca de comentarios en el calificador unificado.';
$string['privacy:metadata:clib:userid'] = 'El profesor propietario del comentario.';
$string['privacy:metadata:clib:coursecode'] = 'El c\u00f3digo de curso asociado al comentario.';
$string['privacy:metadata:clib:content'] = 'El contenido del comentario.';
$string['privacy:metadata:cltag'] = 'Etiquetas de la biblioteca de comentarios en el calificador unificado.';
$string['privacy:metadata:cltag:userid'] = 'El profesor propietario de la etiqueta.';
$string['privacy:metadata:cltag:name'] = 'El nombre de la etiqueta.';

// Penalties.
$string['penalties'] = 'Penalizaciones';
$string['penalty_late'] = 'Entrega tard\u00eda';
$string['penalty_late_days'] = '{$a} d\u00eda(s) de retraso';
$string['penalty_late_auto'] = 'Calculada autom\u00e1ticamente seg\u00fan las reglas de penalizaci\u00f3n';
$string['penalty_wordcount'] = 'Recuento de palabras';
$string['penalty_other'] = 'Otra';
$string['penalty_custom'] = 'Personalizada';
$string['penalty_label_placeholder'] = 'Etiqueta (m\u00e1x. 15 caracteres)';
$string['penalty_active'] = 'Penalizaciones activas';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['penalty_late_label'] = 'Tardío';
$string['penalty_late_applied'] = 'Penalización por retraso del {$a}% aplicada';
$string['late_days'] = '{$a} días';
$string['late_day'] = '{$a} día';
$string['late_hours'] = '{$a} horas';
$string['late_hour'] = '{$a} hora';
$string['late_mins'] = '{$a} min';
$string['late_min'] = '{$a} min';
$string['late_lessthanmin'] = '< 1 min';
$string['finalgradeafterpenalties'] = 'Calificaci\u00f3n final despu\u00e9s de penalizaciones:';
$string['cannotdeleteautopenalty'] = 'Las penalizaciones por retraso se calculan autom\u00e1ticamente y no se pueden eliminar.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Descargar PDF de retroalimentaci\u00f3n';
$string['feedback_summary_overall_feedback'] = 'Retroalimentaci\u00f3n general';
$string['feedback_summary_graded_on'] = 'Calificado el {$a}';
$string['feedback_summary_generated_by'] = 'Generado por el calificador unificado';
$string['feedback_summary_media_note'] = 'El contenido multimedia est\u00e1 disponible en la vista de retroalimentaci\u00f3n en l\u00ednea.';
$string['feedback_summary_no_grade'] = 'N/D';
$string['feedback_summary_remark'] = 'Comentario del profesor';
$string['feedback_summary_total'] = 'Total';
$string['levels'] = 'Niveles';
$string['error_gs_not_configured'] = 'GhostScript no est\u00e1 configurado en este servidor Moodle. El administrador debe establecer la ruta de GhostScript en Administraci\u00f3n del sitio > Plugins > M\u00f3dulos de actividad > Tarea > Retroalimentaci\u00f3n > Anotar PDF.';
$string['error_pdf_combine_failed'] = 'No se pudieron combinar los archivos PDF: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Penalizaciones de calificaci\u00f3n aplicadas por profesores en el calificador unificado.';
$string['privacy:metadata:penalty:userid'] = 'El estudiante al que se aplic\u00f3 la penalizaci\u00f3n.';
$string['privacy:metadata:penalty:authorid'] = 'El profesor que aplic\u00f3 la penalizaci\u00f3n.';
$string['privacy:metadata:penalty:category'] = 'La categor\u00eda de la penalizaci\u00f3n (recuento de palabras u otra).';
$string['privacy:metadata:penalty:label'] = 'La etiqueta personalizada de la penalizaci\u00f3n.';
$string['privacy:metadata:penalty:percentage'] = 'El porcentaje de la penalizaci\u00f3n.';
$string['privacy:metadata:fext'] = 'Extensiones de fecha l\u00edmite de foros concedidas por profesores en el calificador unificado.';
$string['privacy:metadata:fext:userid'] = 'El estudiante al que se le concedi\u00f3 la extensi\u00f3n.';
$string['privacy:metadata:fext:authorid'] = 'El profesor que concedi\u00f3 la extensi\u00f3n.';
$string['privacy:metadata:fext:extensionduedate'] = 'La fecha l\u00edmite extendida.';
$string['privacy:metadata:qfb'] = 'Retroalimentaci\u00f3n por intento de cuestionario almacenada por el calificador unificado.';
$string['privacy:metadata:qfb:userid'] = 'El estudiante al que corresponde la retroalimentaci\u00f3n.';
$string['privacy:metadata:qfb:grader'] = 'El profesor que proporcion\u00f3 la retroalimentaci\u00f3n.';
$string['privacy:metadata:qfb:feedback'] = 'El texto de la retroalimentaci\u00f3n.';
$string['privacy:metadata:qfb:attemptnumber'] = 'El n\u00famero de intento del cuestionario.';
$string['privacy_forum_extensions'] = 'Extensiones de foro';
$string['privacy_quiz_feedback'] = 'Retroalimentaci\u00f3n de cuestionario';

// Offline cache and save status.
$string['allchangessaved'] = 'Todos los cambios guardados';
$string['editing'] = 'Editando...';
$string['offlinesavedlocally'] = 'Sin conexi\u00f3n \u2014 guardado localmente';
$string['connectionlost'] = 'Conexi\u00f3n perdida \u2014 su trabajo se ha guardado localmente y se sincronizar\u00e1 cuando se restablezca la conexi\u00f3n.';
$string['recoveredunsavedchanges'] = 'Se recuperaron cambios no guardados de su \u00faltima sesi\u00f3n.';
$string['restore'] = 'Restaurar';
$string['discard'] = 'Descartar';
$string['mark_as_graded'] = 'Marcar como calificado';
