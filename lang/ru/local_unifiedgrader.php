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
 * Language strings for local_unifiedgrader (Russian).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Единая система оценивания';
$string['grading_interface'] = 'Единая система оценивания';
$string['nopermission'] = 'У вас нет прав для использования единой системы оценивания.';
$string['invalidactivitytype'] = 'Этот тип активности не поддерживается единой системы оценивания.';
$string['invalidmodule'] = 'Недопустимый модуль активности.';
$string['viewfeedback'] = 'Просмотр отзыва';

// Attempts.
$string['attempt'] = 'Попытка';

// Capabilities.
$string['unifiedgrader:grade'] = 'Использовать единой системы оценивания для оценивания';
$string['unifiedgrader:viewall'] = 'Просматривать всех студентов в единой системы оценивания';
$string['unifiedgrader:viewnotes'] = 'Просматривать личные заметки преподавателя';
$string['unifiedgrader:managenotes'] = 'Создавать и редактировать личные заметки преподавателя';
$string['unifiedgrader:viewfeedback'] = 'Просматривать аннотированный отзыв из единой системы оценивания';

// Settings.
$string['setting_enable_assign'] = 'Включить для заданий';
$string['setting_enable_assign_desc'] = 'Разрешить использование единой системы оценивания для заданий.';
$string['setting_enable_submission_comments'] = 'Заменить комментарии к ответу';
$string['setting_enable_submission_comments_desc'] = 'Заменяет стандартные комментарии к ответу Moodle в представлении задания студента комментариями в стиле мессенджера единой системы оценивания (с поддержкой уведомлений). Студенты могут отправлять сообщения преподавателям до и после оценивания.';
$string['setting_enable_forum'] = 'Включить для форумов';
$string['setting_enable_forum_desc'] = 'Разрешить использование единой системы оценивания для форумов.';
$string['setting_enable_quiz'] = 'Включить для тестов';
$string['setting_enable_quiz_desc'] = 'Разрешить использование единой системы оценивания для тестов.';
$string['setting_enable_quiz_post_grades'] = 'Включить публикацию оценок для тестов';
$string['setting_enable_quiz_post_grades_desc'] = 'Видимость оценок за тесты обычно управляется настройками просмотра теста. При включении переключатель «Опубликовать оценки» в единой системы оценивания будет программно обновлять настройки просмотра теста для отображения или скрытия оценок. При отключении (по умолчанию) переключатель публикации оценок скрыт для тестов.';
$string['setting_allow_manual_override'] = 'Разрешить ручное переопределение оценки';
$string['setting_allow_manual_override_desc'] = 'При включении преподаватели могут вручную вводить оценку, даже если настроена рубрика или руководство по оцениванию. При отключении оценка вычисляется исключительно на основе критериев рубрики или руководства по оцениванию.';

// Grading interface.
$string['grade'] = 'Оценка';
$string['savegrade'] = 'Сохранить оценку';
$string['savefeedback'] = 'Сохранить отзыв';
$string['savinggrade'] = 'Сохранение оценки...';
$string['gradesaved'] = 'Оценка сохранена';
$string['error_saving'] = 'Ошибка сохранения оценки.';
$string['error_network'] = 'Не удалось подключиться к серверу. Пожалуйста, проверьте подключение и попробуйте снова.';
$string['error_offline_comments'] = 'Невозможно добавить комментарии в автономном режиме.';
$string['feedback'] = 'Отзыв';
$string['overall_feedback'] = 'Общий отзыв';
$string['feedback_saved'] = 'Отзыв (сохранён)';
$string['edit_feedback'] = 'Редактировать';
$string['delete_feedback'] = 'Удалить';
$string['confirm_delete_feedback'] = 'Вы уверены, что хотите удалить этот отзыв? Оценка будет сохранена.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Развернуть';

// Submissions.
$string['submission'] = 'Ответ на задание';
$string['nosubmission'] = 'Ответ не отправлен';
$string['previewpanel'] = 'Предпросмотр ответа';
$string['markingpanel'] = 'Панель оценивания';
$string['onlinetext'] = 'Текст онлайн';
$string['submittedfiles'] = 'Отправленные файлы';
$string['viewfile'] = 'Просмотреть файл';

// Participants.
$string['participants'] = 'Участники';
$string['search'] = 'Поиск участников...';
$string['sortby'] = 'Сортировать по';
$string['sortby_fullname'] = 'Полное имя';
$string['sortby_submittedat'] = 'Дата отправки';
$string['sortby_status'] = 'Статус';
$string['filter_all'] = 'Все участники';
$string['filter_submitted'] = 'Отправлено';
$string['filter_needsgrading'] = 'Без оценки';
$string['filter_notsubmitted'] = 'Не отправлено';
$string['filter_graded'] = 'Оценено';
$string['filter_late'] = 'С опозданием';
$string['filter_allgroups'] = 'Все группы';
$string['filter_mygroups'] = 'Все мои группы';
$string['studentcount'] = '{$a->current} из {$a->total}';

// Statuses.
$string['status_draft'] = 'Черновик';
$string['status_submitted'] = 'Отправлено';
$string['status_graded'] = 'Оценено';
$string['status_nosubmission'] = 'Ответ не отправлен';
$string['status_needsgrading'] = 'Требует оценки';
$string['status_new'] = 'Не отправлено';
$string['status_late'] = 'С опозданием: {$a}';

// Teacher notes.
$string['notes'] = 'Заметки преподавателя';
$string['notes_desc'] = 'Личные заметки, видимые только преподавателям и модераторам.';
$string['savenote'] = 'Сохранить заметку';
$string['deletenote'] = 'Удалить';
$string['addnote'] = 'Добавить заметку';
$string['nonotes'] = 'Заметок пока нет.';
$string['confirmdelete_note'] = 'Вы уверены, что хотите удалить эту заметку?';

// Comment library.
$string['commentlibrary'] = 'Библиотека комментариев';
$string['savecomment'] = 'Сохранить в библиотеку';
$string['insertcomment'] = 'Вставить';
$string['deletecomment'] = 'Удалить';
$string['newcomment'] = 'Новый комментарий...';
$string['nocomments'] = 'Сохранённых комментариев нет.';

// UI.
$string['loading'] = 'Загрузка...';
$string['saving'] = 'Сохранение...';
$string['saved'] = 'Сохранено';
$string['previousstudent'] = 'Предыдущий студент';
$string['nextstudent'] = 'Следующий студент';
$string['expandfilters'] = 'Показать фильтры';
$string['collapsefilters'] = 'Скрыть фильтры';
$string['backtocourse'] = 'Вернуться к курсу';
$string['rubric'] = 'Рубрика';
$string['markingguide'] = 'Руководство по оцениванию';
$string['criterion'] = 'Критерий';
$string['score'] = 'Балл';
$string['remark'] = 'Комментарий';
$string['total'] = 'Итого: {$a}';
$string['viewallsubmissions'] = 'Просмотреть все ответы';
$string['layout_both'] = 'Разделённый вид';
$string['layout_preview'] = 'Только предпросмотр';
$string['layout_grade'] = 'Только оценивание';
$string['manualquestions'] = 'Вопросы для ручной проверки';
$string['response'] = 'Ответ';
$string['teachercomment'] = 'Комментарий преподавателя';

// Submission comments.
$string['submissioncomments'] = 'Комментарии к ответу';
$string['nocommentsyet'] = 'Комментариев пока нет';
$string['addcomment'] = 'Добавить комментарий...';
$string['postcomment'] = 'Отправить';
$string['deletesubmissioncomment'] = 'Удалить комментарий';

// Feedback files.
$string['feedbackfiles'] = 'Файлы отзыва';

// Plagiarism.
$string['plagiarism'] = 'Плагиат';
$string['plagiarism_noresults'] = 'Результаты проверки на плагиат недоступны.';
$string['plagiarism_pending'] = 'Проверка на плагиат выполняется';
$string['plagiarism_error'] = 'Ошибка проверки на плагиат';

// Student feedback view.
$string['assessment_criteria'] = 'Критерии оценивания';
$string['teacher_remark'] = 'Отзыв преподавателя';
$string['view_feedback'] = 'Просмотр отзыва';
$string['view_annotated_feedback'] = 'Просмотр аннотированного отзыва';
$string['feedback_not_available'] = 'Ваш отзыв ещё недоступен. Пожалуйста, проверьте позже, после того как ваш ответ будет оценён и опубликован.';
$string['no_annotated_files'] = 'Для вашего ответа нет аннотированных PDF-файлов.';
$string['feedback_banner_default'] = 'Ваш преподаватель оставил отзыв на ваш ответ.';

// Document conversion.
$string['conversion_failed'] = 'Не удалось преобразовать этот файл в PDF для предпросмотра.';
$string['converting_file'] = 'Преобразование документа в PDF...';
$string['conversion_timeout'] = 'Преобразование документа занимает слишком много времени. Пожалуйста, попробуйте позже.';
$string['download_annotated_pdf'] = 'Скачать аннотированный PDF';
$string['download_original_submission'] = 'Скачать оригинальный ответ: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Личные заметки преподавателя, хранящиеся по студенту и активности в единой системы оценивания.';
$string['privacy:metadata:notes:cmid'] = 'Идентификатор модуля курса, к которому относится заметка.';
$string['privacy:metadata:notes:userid'] = 'Студент, о котором написана заметка.';
$string['privacy:metadata:notes:authorid'] = 'Преподаватель, написавший заметку.';
$string['privacy:metadata:notes:content'] = 'Содержание заметки.';
$string['privacy:metadata:comments'] = 'Многоразовые записи библиотеки комментариев в единой системы оценивания.';
$string['privacy:metadata:comments:userid'] = 'Преподаватель, владеющий комментарием.';
$string['privacy:metadata:comments:content'] = 'Содержание комментария.';
$string['privacy:metadata:preferences'] = 'Пользовательские настройки интерфейса единой системы оценивания.';
$string['privacy:metadata:preferences:userid'] = 'Пользователь, которому принадлежат настройки.';
$string['privacy:metadata:preferences:data'] = 'Данные настроек в формате JSON.';
$string['privacy:metadata:annotations'] = 'Аннотации документов, хранящиеся в единой системы оценивания.';
$string['privacy:metadata:annotations:cmid'] = 'Идентификатор модуля курса, к которому относится аннотация.';
$string['privacy:metadata:annotations:userid'] = 'Студент, чей ответ аннотирован.';
$string['privacy:metadata:annotations:authorid'] = 'Преподаватель, создавший аннотацию.';
$string['privacy:metadata:annotations:data'] = 'Данные аннотации (JSON Fabric.js).';
$string['annotations'] = 'Аннотации';

// PDF viewer.
$string['pdf_prevpage'] = 'Предыдущая страница';
$string['pdf_nextpage'] = 'Следующая страница';
$string['pdf_zoomin'] = 'Увеличить';
$string['pdf_zoomout'] = 'Уменьшить';
$string['pdf_zoomfit'] = 'По ширине';
$string['pdf_search'] = 'Поиск в документе';

// Annotation tools.
$string['annotate_tools'] = 'Инструменты аннотирования';
$string['annotate_select'] = 'Выбрать';
$string['annotate_textselect'] = 'Выделить текст';
$string['annotate_comment'] = 'Комментарий';
$string['annotate_highlight'] = 'Выделение';
$string['annotate_pen'] = 'Перо';
$string['annotate_pen_fine'] = 'Тонкое';
$string['annotate_pen_medium'] = 'Среднее';
$string['annotate_pen_thick'] = 'Толстое';
$string['annotate_stamps'] = 'Штампы';
$string['annotate_stamp_check'] = 'Штамп «галочка»';
$string['annotate_stamp_cross'] = 'Штамп «крестик»';
$string['annotate_stamp_question'] = 'Штамп «вопрос»';
$string['annotate_red'] = 'Красный';
$string['annotate_yellow'] = 'Жёлтый';
$string['annotate_green'] = 'Зелёный';
$string['annotate_blue'] = 'Синий';
$string['annotate_black'] = 'Чёрный';
$string['annotate_shape'] = 'Фигуры';
$string['annotate_shape_rect'] = 'Прямоугольник';
$string['annotate_shape_circle'] = 'Круг';
$string['annotate_shape_arrow'] = 'Стрелка';
$string['annotate_shape_line'] = 'Линия';
$string['annotate_undo'] = 'Отменить';
$string['annotate_redo'] = 'Повторить';
$string['annotate_delete'] = 'Удалить выбранное';
$string['annotate_clearall'] = 'Очистить всё';
$string['annotate_clear_confirm'] = 'Вы уверены, что хотите удалить все аннотации на этой странице? Это действие нельзя отменить.';

// Document info.
$string['docinfo'] = 'Информация о документе';
$string['docinfo_filename'] = 'Имя файла';
$string['docinfo_filesize'] = 'Размер файла';
$string['docinfo_pages'] = 'Страницы';
$string['docinfo_wordcount'] = 'Количество слов';
$string['docinfo_author'] = 'Автор';
$string['docinfo_creator'] = 'Создатель';
$string['docinfo_created'] = 'Создан';
$string['docinfo_modified'] = 'Изменён';
$string['docinfo_calculating'] = 'Вычисление...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Просмотр отзыва по форуму';
$string['forum_your_posts'] = 'Ваши сообщения на форуме';
$string['forum_no_posts'] = 'Вы не оставляли сообщений на этом форуме.';
$string['forum_feedback_banner'] = 'Ваш преподаватель оценил ваше участие в форуме.';
$string['forum_wordcount'] = '{$a} слов';
$string['forum_posts_pill'] = 'Сообщения';
$string['submission_content_pill'] = 'Ответ';
$string['forum_tab_posts'] = 'Сообщения';
$string['forum_tab_files'] = 'Аннотированные файлы';
$string['view_quiz_feedback'] = 'Просмотр отзыва по тесту';
$string['quiz_feedback_banner'] = 'Ваш преподаватель оставил отзыв по вашему тесту.';
$string['quiz_your_attempt'] = 'Ваша попытка';
$string['quiz_no_attempt'] = 'Вы не завершили ни одной попытки по этому тесту.';
$string['quiz_select_attempt'] = 'Выбрать попытку';
$string['select_attempt'] = 'Выбрать попытку';
$string['attempt_label'] = 'Попытка {$a}';

// Post grades.
$string['grades_posted'] = 'Оценки опубликованы';
$string['grades_hidden'] = 'Оценки скрыты';
$string['post_grades'] = 'Опубликовать оценки';
$string['unpost_grades'] = 'Скрыть оценки';
$string['confirm_post_grades'] = 'Опубликовать все оценки для этой активности? Студенты смогут видеть свои оценки и отзывы.';
$string['confirm_unpost_grades'] = 'Скрыть все оценки для этой активности? Студенты больше не смогут видеть свои оценки и отзывы.';
$string['schedule_post'] = 'Опубликовать по дате';
$string['schedule_post_btn'] = 'Запланировать';
$string['grades_scheduled'] = 'Публикация {$a}';
$string['schedule_must_be_future'] = 'Запланированная дата должна быть в будущем.';
$string['quiz_post_grades_disabled'] = 'Публикация оценок недоступна для тестов. Видимость оценок управляется настройками просмотра теста.';
$string['quiz_post_grades_no_schedule'] = 'Планирование недоступно для тестов. Используйте «Опубликовать» или «Скрыть».';

// Submission status actions.
$string['action_revert_to_draft'] = 'Вернуть в черновик';
$string['action_remove_submission'] = 'Удалить ответ';
$string['action_lock'] = 'Запретить изменение ответа';
$string['action_unlock'] = 'Разрешить изменение ответа';
$string['action_edit_submission'] = 'Редактировать ответ';
$string['action_grant_extension'] = 'Предоставить продление';
$string['action_edit_extension'] = 'Изменить продление';
$string['action_submit_for_grading'] = 'Отправить на оценку';
$string['confirm_revert_to_draft'] = 'Вы уверены, что хотите вернуть этот ответ в статус черновика?';
$string['confirm_remove_submission'] = 'Вы уверены, что хотите удалить этот ответ? Это действие нельзя отменить.';
$string['confirm_lock_submission'] = 'Запретить этому студенту вносить изменения в ответ?';
$string['confirm_unlock_submission'] = 'Разрешить этому студенту вносить изменения в ответ?';
$string['confirm_submit_for_grading'] = 'Отправить этот черновик от имени студента?';
$string['invalidaction'] = 'Недопустимое действие с ответом.';

// Override actions.
$string['override'] = 'Переопределение';
$string['action_add_override'] = 'Добавить переопределение';
$string['action_edit_override'] = 'Изменить переопределение';
$string['action_delete_override'] = 'Удалить переопределение';
$string['confirm_delete_override'] = 'Вы уверены, что хотите удалить это пользовательское переопределение?';
$string['override_saved'] = 'Переопределение успешно сохранено.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Удалить продление';
$string['confirm_delete_extension'] = 'Вы уверены, что хотите удалить это продление срока сдачи?';
$string['quiz_extension_original_duedate'] = 'Исходный срок сдачи';
$string['quiz_extension_current_extension'] = 'Текущее продление';
$string['quiz_extension_new_duedate'] = 'Новый срок сдачи';
$string['quiz_extension_must_be_after_duedate'] = 'Дата продления должна быть позже текущего срока сдачи.';
$string['quiz_extension_plugin_missing'] = 'Плагин quizaccess_duedate необходим для продления тестов, но не установлен.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Срок сдачи форума';
$string['forum_extension_current_extension'] = 'Текущее продление';
$string['forum_extension_new_duedate'] = 'Новый срок сдачи';
$string['forum_extension_must_be_after_duedate'] = 'Дата продления должна быть позже срока сдачи форума.';

// Student profile popout.
$string['profile_view_full'] = 'Просмотреть полный профиль';
$string['profile_login_as'] = 'Войти как';
$string['profile_no_email'] = 'Электронная почта недоступна';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Регулярное выражение кода курса';
$string['setting_coursecode_regex_desc'] = 'Библиотека комментариев организует сохранённые комментарии по коду курса, чтобы преподаватели могли повторно использовать отзывы в разных потоках одного курса (например, от семестра к семестру). Этот параметр определяет, как коды курсов извлекаются из кратких названий курсов Moodle. Введите шаблон регулярного выражения PHP, соответствующий части кода в кратких названиях (например, <code>/[A-Z]{3}\\d{4}/</code> извлечёт <strong>THE2201</strong> из краткого названия <em>THE2201-2026-S1</em>). Оставьте пустым, чтобы использовать полное краткое название в качестве кода курса.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Включить форму отчёта об академическом нарушении';
$string['setting_enable_report_form_desc'] = 'При включении кнопка «Сообщить об академическом нарушении» появляется в разделах проверки на плагиат, ведущая на внешнюю форму отчёта.';
$string['setting_report_form_url'] = 'Шаблон URL формы отчёта';
$string['setting_report_form_url_desc'] = 'URL для формы отчёта об академическом нарушении. Поддерживаемые заполнители: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Они заменяются при выполнении URL-кодированными значениями. Для Microsoft Forms используйте функцию «Получить предварительно заполненный URL», чтобы найти имена параметров.';
$string['report_impropriety'] = 'Сообщить об академическом нарушении';

// Comment library v2.
$string['clib_title'] = 'Библиотека комментариев';
$string['clib_all'] = 'Все';
$string['clib_quick_add'] = 'Быстрое добавление комментария...';
$string['clib_manage'] = 'Управление библиотекой';
$string['clib_no_comments'] = 'Комментариев пока нет.';
$string['clib_insert'] = 'Вставить';
$string['clib_copied'] = 'Комментарий скопирован в буфер обмена';
$string['clib_my_library'] = 'Моя библиотека';
$string['clib_shared_library'] = 'Общая библиотека';
$string['clib_new_comment'] = 'Новый комментарий';
$string['clib_edit_comment'] = 'Редактировать комментарий';
$string['clib_delete_comment'] = 'Удалить комментарий';
$string['clib_confirm_delete'] = 'Вы уверены, что хотите удалить этот комментарий?';
$string['clib_share'] = 'Поделиться';
$string['clib_unshare'] = 'Отменить общий доступ';
$string['clib_import'] = 'Импортировать';
$string['clib_imported'] = 'Комментарий импортирован в вашу библиотеку';
$string['clib_copy_to_course'] = 'Копировать в курс';
$string['clib_all_courses'] = 'Все курсы';
$string['clib_tags'] = 'Теги';
$string['clib_manage_tags'] = 'Управление тегами';
$string['clib_new_tag'] = 'Новый тег';
$string['clib_edit_tag'] = 'Редактировать тег';
$string['clib_delete_tag'] = 'Удалить тег';
$string['clib_confirm_delete_tag'] = 'Вы уверены, что хотите удалить этот тег? Он будет удалён из всех комментариев.';
$string['clib_system_tag'] = 'Системный по умолчанию';
$string['clib_shared_by'] = 'Автор: {$a}';
$string['clib_no_shared'] = 'Общих комментариев нет.';
$string['clib_picker_freetext'] = 'Или напишите свой...';
$string['clib_picker_loading'] = 'Загрузка комментариев...';
$string['clib_offline_mode'] = 'Показаны кэшированные комментарии — редактирование недоступно в автономном режиме.';
$string['unifiedgrader:sharecomments'] = 'Делиться комментариями в библиотеке с другими преподавателями';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Записи библиотеки комментариев в единой системы оценивания.';
$string['privacy:metadata:clib:userid'] = 'Преподаватель, владеющий комментарием.';
$string['privacy:metadata:clib:coursecode'] = 'Код курса, с которым связан комментарий.';
$string['privacy:metadata:clib:content'] = 'Содержание комментария.';
$string['privacy:metadata:cltag'] = 'Теги библиотеки комментариев в единой системы оценивания.';
$string['privacy:metadata:cltag:userid'] = 'Преподаватель, владеющий тегом.';
$string['privacy:metadata:cltag:name'] = 'Название тега.';

// Penalties.
$string['penalties'] = 'Штрафы';
$string['penalty_late'] = 'Поздняя сдача';
$string['penalty_late_days'] = '{$a} дн. с опозданием';
$string['penalty_late_auto'] = 'Автоматически рассчитано на основе правил штрафования';
$string['penalty_wordcount'] = 'Количество слов';
$string['penalty_other'] = 'Другое';
$string['penalty_custom'] = 'Пользовательский';
$string['penalty_label_placeholder'] = 'Метка (макс. 15 символов)';
$string['penalty_active'] = 'Активные штрафы';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['penalty_late_label'] = 'Просрочено';
$string['penalty_late_applied'] = 'Штраф за опоздание {$a}% применён';
$string['late_days'] = '{$a} дн.';
$string['late_day'] = '{$a} день';
$string['late_hours'] = '{$a} ч.';
$string['late_hour'] = '{$a} ч.';
$string['late_mins'] = '{$a} мин.';
$string['late_min'] = '{$a} мин.';
$string['late_lessthanmin'] = '< 1 мин.';
$string['finalgradeafterpenalties'] = 'Итоговая оценка после штрафов:';
$string['cannotdeleteautopenalty'] = 'Штрафы за опоздание рассчитываются автоматически и не могут быть удалены.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Скачать PDF с отзывом';
$string['feedback_summary_overall_feedback'] = 'Общий отзыв';
$string['feedback_summary_graded_on'] = 'Оценено {$a}';
$string['feedback_summary_generated_by'] = 'Сформировано единой системы оценивания';
$string['feedback_summary_media_note'] = 'Медиаконтент доступен в онлайн-версии отзыва.';
$string['feedback_summary_no_grade'] = 'Н/Д';
$string['feedback_summary_remark'] = 'Комментарий преподавателя';
$string['feedback_summary_total'] = 'Итого';
$string['levels'] = 'Уровни';
$string['error_gs_not_configured'] = 'GhostScript не настроен на этом сервере Moodle. Администратор должен указать путь к GhostScript в разделе Администрирование > Плагины > Модули активности > Задание > Отзыв > Аннотирование PDF.';
$string['error_pdf_combine_failed'] = 'Не удалось объединить PDF-файлы: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Штрафы к оценкам, применённые преподавателями в единой системы оценивания.';
$string['privacy:metadata:penalty:userid'] = 'Студент, к которому применён штраф.';
$string['privacy:metadata:penalty:authorid'] = 'Преподаватель, применивший штраф.';
$string['privacy:metadata:penalty:category'] = 'Категория штрафа (количество слов или другое).';
$string['privacy:metadata:penalty:label'] = 'Пользовательская метка штрафа.';
$string['privacy:metadata:penalty:percentage'] = 'Процент штрафа.';
$string['privacy:metadata:fext'] = 'Продления сроков сдачи форумов, предоставленные преподавателями в единой системы оценивания.';
$string['privacy:metadata:fext:userid'] = 'Студент, которому предоставлено продление.';
$string['privacy:metadata:fext:authorid'] = 'Преподаватель, предоставивший продление.';
$string['privacy:metadata:fext:extensionduedate'] = 'Продлённый срок сдачи.';
$string['privacy:metadata:qfb'] = 'Отзывы по попыткам тестов, сохранённые единой системы оценивания.';
$string['privacy:metadata:qfb:userid'] = 'Студент, для которого предназначен отзыв.';
$string['privacy:metadata:qfb:grader'] = 'Преподаватель, оставивший отзыв.';
$string['privacy:metadata:qfb:feedback'] = 'Текст отзыва.';
$string['privacy:metadata:qfb:attemptnumber'] = 'Номер попытки теста.';
$string['privacy:metadata:scomm'] = 'Комментарии к работам, сохранённые Unified Grader.';
$string['privacy:metadata:scomm:cmid'] = 'Модуль курса, к которому относится комментарий.';
$string['privacy:metadata:scomm:userid'] = 'Студент, к которому относится тема комментариев.';
$string['privacy:metadata:scomm:authorid'] = 'Пользователь, написавший комментарий.';
$string['privacy:metadata:scomm:content'] = 'Содержание комментария.';
$string['privacy_forum_extensions'] = 'Продления форумов';
$string['privacy_quiz_feedback'] = 'Отзывы по тестам';

// Notification strings.
$string['messageprovider:submission_comment'] = 'Уведомления о комментариях к работам';
$string['notification_comment_subject'] = 'Новый комментарий к {$a->activityname}';
$string['notification_comment_body'] = '<p><strong>{$a->authorfullname}</strong> опубликовал комментарий к <a href="{$a->activityurl}">{$a->activityname}</a> в {$a->coursename} ({$a->timecreated}):</p><blockquote>{$a->content}</blockquote>';
$string['notification_comment_small'] = '{$a->authorfullname} прокомментировал {$a->activityname}';

// Offline cache and save status.
$string['allchangessaved'] = 'Все изменения сохранены';
$string['editing'] = 'Редактирование...';
$string['offlinesavedlocally'] = 'Автономный режим — сохранено локально';
$string['connectionlost'] = 'Соединение потеряно — ваша работа сохранена локально и будет синхронизирована при восстановлении подключения.';
$string['recoveredunsavedchanges'] = 'Восстановлены несохранённые изменения из предыдущего сеанса.';
$string['restore'] = 'Восстановить';
$string['discard'] = 'Отменить';
$string['mark_as_graded'] = 'Отметить как оценённое';
