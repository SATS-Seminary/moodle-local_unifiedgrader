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
$string['invalidmodule'] = 'Módulo de actividad no válido.';
$string['viewfeedback'] = 'Ver retroalimentación';

// Attempts.
$string['attempt'] = 'Intento';

// Capabilities.
$string['unifiedgrader:grade'] = 'Usar el calificador unificado para calificar';
$string['unifiedgrader:viewall'] = 'Ver todos los estudiantes en el calificador unificado';
$string['unifiedgrader:viewnotes'] = 'Ver notas privadas del profesor';
$string['unifiedgrader:managenotes'] = 'Crear y editar notas privadas del profesor';
$string['unifiedgrader:viewfeedback'] = 'Ver retroalimentación anotada del calificador unificado';

// Settings.
$string['setting_enable_assign'] = 'Habilitar para Tareas';
$string['setting_enable_assign_desc'] = 'Permitir que el calificador unificado se use para actividades de tarea.';
$string['setting_enable_submission_comments'] = 'Reemplazar comentarios de entrega';
$string['setting_enable_submission_comments_desc'] = 'Reemplaza los comentarios de entrega nativos de Moodle en la vista de tarea del estudiante con los comentarios estilo mensajería del calificador unificado (con soporte de notificaciones). Los estudiantes pueden enviar mensajes a los profesores antes y después de la calificación.';
$string['setting_enable_forum'] = 'Habilitar para Foros';
$string['setting_enable_forum_desc'] = 'Permitir que el calificador unificado se use para actividades de foro.';
$string['setting_enable_quiz'] = 'Habilitar para Cuestionarios';
$string['setting_enable_quiz_desc'] = 'Permitir que el calificador unificado se use para actividades de cuestionario.';
$string['setting_enable_quiz_post_grades'] = 'Habilitar publicación de calificaciones para cuestionarios';
$string['setting_enable_quiz_post_grades_desc'] = 'La visibilidad de las calificaciones del cuestionario normalmente se gestiona mediante las opciones de revisión del cuestionario. Cuando está habilitado, el botón "Publicar calificaciones" del calificador unificado actualizará las opciones de revisión del cuestionario programáticamente para mostrar u ocultar las notas. Cuando está deshabilitado (por defecto), el botón de publicar calificaciones se oculta para los cuestionarios.';
$string['setting_allow_manual_override'] = 'Permitir anulación manual de calificación';
$string['setting_allow_manual_override_desc'] = 'Cuando está habilitado, los profesores pueden escribir manualmente una calificación incluso cuando hay una rúbrica o guía de calificación configurada. Cuando está deshabilitado, la calificación se calcula exclusivamente a partir de los criterios de la rúbrica o guía de calificación.';

// Grading interface.
$string['grade'] = 'Calificación';
$string['savegrade'] = 'Guardar calificación';
$string['savefeedback'] = 'Guardar retroalimentación';
$string['savinggrade'] = 'Guardando calificación...';
$string['gradesaved'] = 'Calificación guardada';
$string['error_saving'] = 'Error al guardar la calificación.';
$string['error_network'] = 'No se pudo conectar al servidor. Por favor, verifique su conexión e inténtelo de nuevo.';
$string['error_offline_comments'] = 'No se pueden agregar comentarios sin conexión.';
$string['feedback'] = 'Retroalimentación';
$string['overall_feedback'] = 'Retroalimentación general';
$string['feedback_saved'] = 'Retroalimentación (guardada)';
$string['edit_feedback'] = 'Editar';
$string['delete_feedback'] = 'Eliminar';
$string['confirm_delete_feedback'] = '¿Está seguro de que desea eliminar esta retroalimentación? La calificación se conservará.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Expandir';

// Submissions.
$string['submission'] = 'Entrega';
$string['nosubmission'] = 'Sin entrega';
$string['previewpanel'] = 'Vista previa de la entrega';
$string['markingpanel'] = 'Panel de calificación';
$string['onlinetext'] = 'Texto en línea';
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
$string['filter_mygroups'] = 'Todos mis grupos';
$string['studentcount'] = '{$a->current} de {$a->total}';

// Statuses.
$string['status_draft'] = 'Borrador';
$string['status_submitted'] = 'Entregado';
$string['status_graded'] = 'Calificado';
$string['status_nosubmission'] = 'Sin entrega';
$string['status_needsgrading'] = 'Necesita calificación';
$string['status_new'] = 'No entregado';
$string['status_late'] = 'Tarde: {$a}';

// Teacher notes.
$string['notes'] = 'Notas del profesor';
$string['notes_desc'] = 'Notas privadas visibles solo para profesores y moderadores.';
$string['savenote'] = 'Guardar nota';
$string['deletenote'] = 'Eliminar';
$string['addnote'] = 'Agregar nota';
$string['nonotes'] = 'Aún no hay notas.';
$string['confirmdelete_note'] = '¿Está seguro de que desea eliminar esta nota?';

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
$string['rubric'] = 'Rúbrica';
$string['markingguide'] = 'Guía de calificación';
$string['criterion'] = 'Criterio';
$string['score'] = 'Puntuación';
$string['remark'] = 'Observación';
$string['total'] = 'Total: {$a}';
$string['viewallsubmissions'] = 'Ver todas las entregas';
$string['layout_both'] = 'Vista dividida';
$string['layout_preview'] = 'Solo vista previa';
$string['layout_grade'] = 'Solo calificación';
$string['manualquestions'] = 'Preguntas manuales';
$string['response'] = 'Respuesta';
$string['teachercomment'] = 'Comentario del profesor';

// Submission comments.
$string['submissioncomments'] = 'Comentarios de la entrega';
$string['nocommentsyet'] = 'Aún no hay comentarios';
$string['addcomment'] = 'Agregar un comentario...';
$string['postcomment'] = 'Publicar';
$string['deletesubmissioncomment'] = 'Eliminar comentario';

// Feedback files.
$string['feedbackfiles'] = 'Archivos de retroalimentación';

// Plagiarism.
$string['plagiarism'] = 'Plagio';
$string['plagiarism_noresults'] = 'No hay resultados de plagio disponibles.';
$string['plagiarism_pending'] = 'Análisis de plagio en progreso';
$string['plagiarism_error'] = 'El análisis de plagio falló';

// Student feedback view.
$string['assessment_criteria'] = 'Criterios de evaluación';
$string['teacher_remark'] = 'Retroalimentación del profesor';
$string['view_feedback'] = 'Ver retroalimentación';
$string['view_annotated_feedback'] = 'Ver retroalimentación anotada';
$string['feedback_not_available'] = 'Su retroalimentación aún no está disponible. Por favor, vuelva a consultar después de que su entrega haya sido calificada y publicada.';
$string['no_annotated_files'] = 'No hay archivos PDF anotados para su entrega.';
$string['feedback_banner_default'] = 'Su profesor ha proporcionado retroalimentación sobre su entrega.';

// Document conversion.
$string['conversion_failed'] = 'Este archivo no pudo ser convertido a PDF para la vista previa.';
$string['converting_file'] = 'Convirtiendo documento a PDF...';
$string['conversion_timeout'] = 'La conversión del documento está tardando demasiado. Por favor, inténtelo más tarde.';
$string['download_annotated_pdf'] = 'Descargar PDF anotado';
$string['download_original_submission'] = 'Descargar entrega original: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Notas privadas del profesor almacenadas por estudiante y actividad en el calificador unificado.';
$string['privacy:metadata:notes:cmid'] = 'El ID del módulo del curso al que pertenece la nota.';
$string['privacy:metadata:notes:userid'] = 'El estudiante sobre el que trata la nota.';
$string['privacy:metadata:notes:authorid'] = 'El profesor que escribió la nota.';
$string['privacy:metadata:notes:content'] = 'El contenido de la nota.';
$string['privacy:metadata:comments'] = 'Entradas reutilizables de la biblioteca de comentarios en el calificador unificado.';
$string['privacy:metadata:comments:userid'] = 'El profesor propietario del comentario.';
$string['privacy:metadata:comments:content'] = 'El contenido del comentario.';
$string['privacy:metadata:preferences'] = 'Preferencias del usuario para la interfaz del calificador unificado.';
$string['privacy:metadata:preferences:userid'] = 'El usuario al que pertenecen las preferencias.';
$string['privacy:metadata:preferences:data'] = 'Los datos de preferencias codificados en JSON.';
$string['privacy:metadata:annotations'] = 'Anotaciones de documentos almacenadas en el calificador unificado.';
$string['privacy:metadata:annotations:cmid'] = 'El ID del módulo del curso al que pertenece la anotación.';
$string['privacy:metadata:annotations:userid'] = 'El estudiante cuya entrega está anotada.';
$string['privacy:metadata:annotations:authorid'] = 'El profesor que creó la anotación.';
$string['privacy:metadata:annotations:data'] = 'Los datos de la anotación (JSON de Fabric.js).';
$string['annotations'] = 'Anotaciones';

// PDF viewer.
$string['pdf_prevpage'] = 'Página anterior';
$string['pdf_nextpage'] = 'Página siguiente';
$string['pdf_zoomin'] = 'Acercar';
$string['pdf_zoomout'] = 'Alejar';
$string['pdf_zoomfit'] = 'Ajustar al ancho';
$string['pdf_search'] = 'Buscar en el documento';

// Annotation tools.
$string['annotate_tools'] = 'Herramientas de anotación';
$string['annotate_select'] = 'Seleccionar';
$string['annotate_textselect'] = 'Seleccionar texto';
$string['annotate_comment'] = 'Comentario';
$string['annotate_highlight'] = 'Resaltar';
$string['annotate_pen'] = 'Lápiz';
$string['annotate_pen_fine'] = 'Fino';
$string['annotate_pen_medium'] = 'Medio';
$string['annotate_pen_thick'] = 'Grueso';
$string['annotate_stamps'] = 'Sellos';
$string['annotate_stamp_check'] = 'Sello de verificación';
$string['annotate_stamp_cross'] = 'Sello de cruz';
$string['annotate_stamp_question'] = 'Sello de interrogación';
$string['annotate_red'] = 'Rojo';
$string['annotate_yellow'] = 'Amarillo';
$string['annotate_green'] = 'Verde';
$string['annotate_blue'] = 'Azul';
$string['annotate_black'] = 'Negro';
$string['annotate_shape'] = 'Formas';
$string['annotate_shape_rect'] = 'Rectángulo';
$string['annotate_shape_circle'] = 'Círculo';
$string['annotate_shape_arrow'] = 'Flecha';
$string['annotate_shape_line'] = 'Línea';
$string['annotate_undo'] = 'Deshacer';
$string['annotate_redo'] = 'Rehacer';
$string['annotate_delete'] = 'Eliminar selección';
$string['annotate_clearall'] = 'Borrar todo';
$string['annotate_clear_confirm'] = '¿Está seguro de que desea borrar todas las anotaciones de esta página? Esta acción no se puede deshacer.';

// Document info.
$string['docinfo'] = 'Información del documento';
$string['docinfo_filename'] = 'Nombre del archivo';
$string['docinfo_filesize'] = 'Tamaño del archivo';
$string['docinfo_pages'] = 'Páginas';
$string['docinfo_wordcount'] = 'Recuento de palabras';
$string['docinfo_author'] = 'Autor';
$string['docinfo_creator'] = 'Creador';
$string['docinfo_created'] = 'Creado';
$string['docinfo_modified'] = 'Modificado';
$string['docinfo_calculating'] = 'Calculando...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Ver retroalimentación del foro';
$string['forum_your_posts'] = 'Sus publicaciones en el foro';
$string['forum_no_posts'] = 'No ha realizado ninguna publicación en este foro.';
$string['forum_feedback_banner'] = 'Su profesor ha calificado su participación en el foro.';
$string['forum_wordcount'] = '{$a} palabras';
$string['forum_posts_pill'] = 'Publicaciones';
$string['submission_content_pill'] = 'Entrega';
$string['forum_tab_posts'] = 'Publicaciones';
$string['forum_tab_files'] = 'Archivos anotados';
$string['view_quiz_feedback'] = 'Ver retroalimentación del cuestionario';
$string['quiz_feedback_banner'] = 'Su profesor ha proporcionado retroalimentación sobre su cuestionario.';
$string['quiz_your_attempt'] = 'Su intento';
$string['quiz_no_attempt'] = 'No ha completado ningún intento para este cuestionario.';
$string['quiz_select_attempt'] = 'Seleccionar intento';
$string['select_attempt'] = 'Seleccionar intento';
$string['attempt_label'] = 'Intento {$a}';

// Post grades.
$string['grades_posted'] = 'Calificaciones publicadas';
$string['grades_hidden'] = 'Calificaciones ocultas';
$string['post_grades'] = 'Publicar calificaciones';
$string['unpost_grades'] = 'Ocultar calificaciones';
$string['confirm_post_grades'] = '¿Publicar todas las calificaciones de esta actividad? Los estudiantes podrán ver sus calificaciones y retroalimentación.';
$string['confirm_unpost_grades'] = '¿Ocultar todas las calificaciones de esta actividad? Los estudiantes ya no podrán ver sus calificaciones y retroalimentación.';
$string['schedule_post'] = 'Publicar en una fecha';
$string['schedule_post_btn'] = 'Programar';
$string['grades_scheduled'] = 'Publicación {$a}';
$string['schedule_must_be_future'] = 'La fecha programada debe ser en el futuro.';
$string['quiz_post_grades_disabled'] = 'La publicación de calificaciones no está disponible para cuestionarios. La visibilidad de las calificaciones se controla mediante las opciones de revisión del cuestionario.';
$string['quiz_post_grades_no_schedule'] = 'La programación no está disponible para cuestionarios. Use Publicar u Ocultar en su lugar.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Revertir a borrador';
$string['action_remove_submission'] = 'Eliminar entrega';
$string['action_lock'] = 'Impedir cambios en la entrega';
$string['action_unlock'] = 'Permitir cambios en la entrega';
$string['action_edit_submission'] = 'Editar entrega';
$string['action_grant_extension'] = 'Conceder extensión';
$string['action_edit_extension'] = 'Editar extensión';
$string['action_submit_for_grading'] = 'Enviar para calificación';
$string['confirm_revert_to_draft'] = '¿Está seguro de que desea revertir esta entrega al estado de borrador?';
$string['confirm_remove_submission'] = '¿Está seguro de que desea eliminar esta entrega? Esta acción no se puede deshacer.';
$string['confirm_lock_submission'] = '¿Impedir que este estudiante realice cambios en su entrega?';
$string['confirm_unlock_submission'] = '¿Permitir que este estudiante realice cambios en su entrega?';
$string['confirm_submit_for_grading'] = '¿Enviar este borrador en nombre del estudiante?';
$string['invalidaction'] = 'Acción de entrega no válida.';

// Override actions.
$string['override'] = 'Anulación';
$string['action_add_override'] = 'Agregar anulación';
$string['action_edit_override'] = 'Editar anulación';
$string['action_delete_override'] = 'Eliminar anulación';
$string['confirm_delete_override'] = '¿Está seguro de que desea eliminar esta anulación de usuario?';
$string['override_saved'] = 'Anulación guardada correctamente.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Eliminar extensión';
$string['confirm_delete_extension'] = '¿Está seguro de que desea eliminar esta extensión de fecha límite?';
$string['quiz_extension_original_duedate'] = 'Fecha límite original';
$string['quiz_extension_current_extension'] = 'Extensión actual';
$string['quiz_extension_new_duedate'] = 'Nueva fecha límite de extensión';
$string['quiz_extension_must_be_after_duedate'] = 'La fecha de extensión debe ser posterior a la fecha límite actual.';
$string['quiz_extension_plugin_missing'] = 'El plugin quizaccess_duedate es necesario para las extensiones de cuestionario pero no está instalado.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Fecha límite del foro';
$string['forum_extension_current_extension'] = 'Extensión actual';
$string['forum_extension_new_duedate'] = 'Nueva fecha límite de extensión';
$string['forum_extension_must_be_after_duedate'] = 'La fecha de extensión debe ser posterior a la fecha límite del foro.';

// Student profile popout.
$string['profile_view_full'] = 'Ver perfil completo';
$string['profile_login_as'] = 'Iniciar sesión como';
$string['profile_no_email'] = 'Correo electrónico no disponible';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Expresión regular del código de curso';
$string['setting_coursecode_regex_desc'] = 'La biblioteca de comentarios organiza los comentarios guardados por código de curso, para que los profesores puedan reutilizar la retroalimentación en diferentes ofertas del mismo curso (p. ej., de semestre a semestre). Esta configuración controla cómo se extraen los códigos de curso de los nombres cortos de los cursos de Moodle. Ingrese un patrón de expresión regular PHP que coincida con la porción del código de sus nombres cortos (p. ej., <code>/[A-Z]{3}\\d{4}/</code> extraería <strong>THE2201</strong> de un nombre corto como <em>THE2201-2026-S1</em>). Deje vacío para usar el nombre corto completo como código de curso.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Habilitar formulario de reporte de falta académica';
$string['setting_enable_report_form_desc'] = 'Cuando está habilitado, aparece un botón "Reportar falta académica" en las secciones de plagio, con enlace a un formulario de reporte externo.';
$string['setting_report_form_url'] = 'Plantilla de URL del formulario de reporte';
$string['setting_report_form_url_desc'] = 'URL del formulario de reporte de falta académica. Marcadores de posición admitidos: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Estos se reemplazan en tiempo de ejecución con valores codificados para URL. Para Microsoft Forms, use la función "Obtener URL pre-rellenada" para encontrar los nombres de los parámetros.';
$string['report_impropriety'] = 'Reportar falta académica';

// Comment library v2.
$string['clib_title'] = 'Biblioteca de comentarios';
$string['clib_all'] = 'Todos';
$string['clib_quick_add'] = 'Agregar comentario rápido...';
$string['clib_manage'] = 'Administrar biblioteca';
$string['clib_no_comments'] = 'Aún no hay comentarios.';
$string['clib_insert'] = 'Insertar';
$string['clib_copied'] = 'Comentario copiado al portapapeles';
$string['clib_my_library'] = 'Mi biblioteca';
$string['clib_shared_library'] = 'Biblioteca compartida';
$string['clib_new_comment'] = 'Nuevo comentario';
$string['clib_edit_comment'] = 'Editar comentario';
$string['clib_delete_comment'] = 'Eliminar comentario';
$string['clib_confirm_delete'] = '¿Está seguro de que desea eliminar este comentario?';
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
$string['clib_confirm_delete_tag'] = '¿Está seguro de que desea eliminar esta etiqueta? Se eliminará de todos los comentarios.';
$string['clib_system_tag'] = 'Predeterminado del sistema';
$string['clib_shared_by'] = 'Compartido por {$a}';
$string['clib_no_shared'] = 'No hay comentarios compartidos disponibles.';
$string['clib_picker_freetext'] = 'O escriba el suyo...';
$string['clib_picker_loading'] = 'Cargando comentarios...';
$string['clib_offline_mode'] = 'Mostrando comentarios en caché — la edición no está disponible sin conexión.';
$string['unifiedgrader:sharecomments'] = 'Compartir comentarios en la biblioteca con otros profesores';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Entradas de la biblioteca de comentarios en el calificador unificado.';
$string['privacy:metadata:clib:userid'] = 'El profesor propietario del comentario.';
$string['privacy:metadata:clib:coursecode'] = 'El código de curso asociado al comentario.';
$string['privacy:metadata:clib:content'] = 'El contenido del comentario.';
$string['privacy:metadata:cltag'] = 'Etiquetas de la biblioteca de comentarios en el calificador unificado.';
$string['privacy:metadata:cltag:userid'] = 'El profesor propietario de la etiqueta.';
$string['privacy:metadata:cltag:name'] = 'El nombre de la etiqueta.';

// Penalties.
$string['penalties'] = 'Penalizaciones';
$string['penalty_late'] = 'Entrega tardía';
$string['penalty_late_days'] = '{$a} día(s) de retraso';
$string['penalty_late_auto'] = 'Calculada automáticamente según las reglas de penalización';
$string['penalty_wordcount'] = 'Recuento de palabras';
$string['penalty_other'] = 'Otra';
$string['penalty_custom'] = 'Personalizada';
$string['penalty_label_placeholder'] = 'Etiqueta (máx. 15 caracteres)';
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
$string['finalgradeafterpenalties'] = 'Calificación final después de penalizaciones:';
$string['cannotdeleteautopenalty'] = 'Las penalizaciones por retraso se calculan automáticamente y no se pueden eliminar.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Descargar PDF de retroalimentación';
$string['feedback_summary_overall_feedback'] = 'Retroalimentación general';
$string['feedback_summary_graded_on'] = 'Calificado el {$a}';
$string['feedback_summary_generated_by'] = 'Generado por el calificador unificado';
$string['feedback_summary_media_note'] = 'El contenido multimedia está disponible en la vista de retroalimentación en línea.';
$string['feedback_summary_no_grade'] = 'N/D';
$string['feedback_summary_remark'] = 'Comentario del profesor';
$string['feedback_summary_total'] = 'Total';
$string['levels'] = 'Niveles';
$string['error_gs_not_configured'] = 'GhostScript no está configurado en este servidor Moodle. El administrador debe establecer la ruta de GhostScript en Administración del sitio > Plugins > Módulos de actividad > Tarea > Retroalimentación > Anotar PDF.';
$string['error_pdf_combine_failed'] = 'No se pudieron combinar los archivos PDF: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Penalizaciones de calificación aplicadas por profesores en el calificador unificado.';
$string['privacy:metadata:penalty:userid'] = 'El estudiante al que se aplicó la penalización.';
$string['privacy:metadata:penalty:authorid'] = 'El profesor que aplicó la penalización.';
$string['privacy:metadata:penalty:category'] = 'La categoría de la penalización (recuento de palabras u otra).';
$string['privacy:metadata:penalty:label'] = 'La etiqueta personalizada de la penalización.';
$string['privacy:metadata:penalty:percentage'] = 'El porcentaje de la penalización.';
$string['privacy:metadata:fext'] = 'Extensiones de fecha límite de foros concedidas por profesores en el calificador unificado.';
$string['privacy:metadata:fext:userid'] = 'El estudiante al que se le concedió la extensión.';
$string['privacy:metadata:fext:authorid'] = 'El profesor que concedió la extensión.';
$string['privacy:metadata:fext:extensionduedate'] = 'La fecha límite extendida.';
$string['privacy:metadata:qfb'] = 'Retroalimentación por intento de cuestionario almacenada por el calificador unificado.';
$string['privacy:metadata:qfb:userid'] = 'El estudiante al que corresponde la retroalimentación.';
$string['privacy:metadata:qfb:grader'] = 'El profesor que proporcionó la retroalimentación.';
$string['privacy:metadata:qfb:feedback'] = 'El texto de la retroalimentación.';
$string['privacy:metadata:qfb:attemptnumber'] = 'El número de intento del cuestionario.';
$string['privacy:metadata:scomm'] = 'Comentarios de entrega almacenados por el Unified Grader.';
$string['privacy:metadata:scomm:cmid'] = 'El módulo del curso al que pertenece el comentario.';
$string['privacy:metadata:scomm:userid'] = 'El estudiante sobre el que trata el hilo de comentarios.';
$string['privacy:metadata:scomm:authorid'] = 'El usuario que escribió el comentario.';
$string['privacy:metadata:scomm:content'] = 'El contenido del comentario.';
$string['privacy_forum_extensions'] = 'Extensiones de foro';
$string['privacy_quiz_feedback'] = 'Retroalimentación de cuestionario';

// Notification strings.
$string['messageprovider:submission_comment'] = 'Notificaciones de comentarios de entrega';
$string['notification_comment_subject'] = 'Nuevo comentario en {$a->activityname}';
$string['notification_comment_body'] = '<p><strong>{$a->authorfullname}</strong> publicó un comentario en <a href="{$a->activityurl}">{$a->activityname}</a> en {$a->coursename} ({$a->timecreated}):</p><blockquote>{$a->content}</blockquote>';
$string['notification_comment_small'] = '{$a->authorfullname} comentó en {$a->activityname}';

// Offline cache and save status.
$string['allchangessaved'] = 'Todos los cambios guardados';
$string['editing'] = 'Editando...';
$string['offlinesavedlocally'] = 'Sin conexión — guardado localmente';
$string['connectionlost'] = 'Conexión perdida — su trabajo se ha guardado localmente y se sincronizará cuando se restablezca la conexión.';
$string['recoveredunsavedchanges'] = 'Se recuperaron cambios no guardados de su última sesión.';
$string['restore'] = 'Restaurar';
$string['discard'] = 'Descartar';
$string['mark_as_graded'] = 'Marcar como calificado';
$string['manuallymarkedquestions'] = 'Preguntas calificadas manualmente';
