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
 * Language strings for local_unifiedgrader (Portuguese).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Avaliador unificado';
$string['grading_interface'] = 'Avaliador unificado';
$string['nopermission'] = 'Não tem permissão para utilizar o avaliador unificado.';
$string['invalidactivitytype'] = 'Este tipo de atividade não é suportado pelo avaliador unificado.';
$string['invalidmodule'] = 'Módulo de atividade inválido.';
$string['viewfeedback'] = 'Ver feedback';

// Attempts.
$string['attempt'] = 'Tentativa';

// Capabilities.
$string['unifiedgrader:grade'] = 'Utilizar o avaliador unificado para avaliar';
$string['unifiedgrader:viewall'] = 'Ver todos os alunos no avaliador unificado';
$string['unifiedgrader:viewnotes'] = 'Ver notas privadas do professor';
$string['unifiedgrader:managenotes'] = 'Criar e editar notas privadas do professor';
$string['unifiedgrader:viewfeedback'] = 'Ver feedback anotado do avaliador unificado';

// Settings.
$string['setting_enable_assign'] = 'Ativar para Trabalhos';
$string['setting_enable_assign_desc'] = 'Permitir a utilização do avaliador unificado para atividades de trabalho.';
$string['setting_enable_submission_comments'] = 'Substituir comentários de submissão';
$string['setting_enable_submission_comments_desc'] = 'Substitui os comentários de submissão nativos do Moodle na vista de trabalho do estudante pelos comentários estilo mensageiro do avaliador unificado (com suporte a notificações). Os estudantes podem enviar mensagens aos professores antes e depois da avaliação.';
$string['setting_enable_forum'] = 'Ativar para Fóruns';
$string['setting_enable_forum_desc'] = 'Permitir a utilização do avaliador unificado para atividades de fórum.';
$string['setting_enable_quiz'] = 'Ativar para Testes';
$string['setting_enable_quiz_desc'] = 'Permitir a utilização do avaliador unificado para atividades de teste.';
$string['setting_enable_quiz_post_grades'] = 'Ativar publicação de notas para testes';
$string['setting_enable_quiz_post_grades_desc'] = 'A visibilidade das notas dos testes é normalmente gerida pelas opções de revisão do teste. Quando ativado, o botão "Publicar notas" do avaliador unificado atualiza programaticamente as opções de revisão do teste para mostrar ou ocultar as notas. Quando desativado (predefinição), o botão de publicar notas fica oculto para testes.';
$string['setting_allow_manual_override'] = 'Permitir substituição manual da nota';
$string['setting_allow_manual_override_desc'] = 'Quando ativado, os professores podem introduzir manualmente uma nota mesmo quando uma rubrica ou guia de avaliação está configurada. Quando desativado, a nota é calculada exclusivamente a partir dos critérios da rubrica ou guia de avaliação.';

// Grading interface.
$string['grade'] = 'Nota';
$string['savegrade'] = 'Guardar nota';
$string['savefeedback'] = 'Guardar feedback';
$string['savinggrade'] = 'A guardar nota...';
$string['gradesaved'] = 'Nota guardada';
$string['error_saving'] = 'Erro ao guardar a nota.';
$string['error_network'] = 'Não foi possível ligar ao servidor. Verifique a sua ligação e tente novamente.';
$string['error_offline_comments'] = 'Não é possível adicionar comentários em modo offline.';
$string['feedback'] = 'Feedback';
$string['overall_feedback'] = 'Feedback geral';
$string['feedback_saved'] = 'Feedback (guardado)';
$string['edit_feedback'] = 'Editar';
$string['delete_feedback'] = 'Eliminar';
$string['confirm_delete_feedback'] = 'Tem a certeza de que pretende eliminar este feedback? A nota será preservada.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Expandir';

// Submissions.
$string['submission'] = 'Submissão';
$string['nosubmission'] = 'Sem submissão';
$string['previewpanel'] = 'Pré-visualização da submissão';
$string['markingpanel'] = 'Painel de avaliação';
$string['onlinetext'] = 'Texto online';
$string['submittedfiles'] = 'Ficheiros submetidos';
$string['viewfile'] = 'Ver ficheiro';

// Participants.
$string['participants'] = 'Participantes';
$string['search'] = 'Pesquisar participantes...';
$string['sortby'] = 'Ordenar por';
$string['sortby_fullname'] = 'Nome completo';
$string['sortby_submittedat'] = 'Data de submissão';
$string['sortby_status'] = 'Estado';
$string['filter_all'] = 'Todos os participantes';
$string['filter_submitted'] = 'Submetidos';
$string['filter_needsgrading'] = 'Por avaliar';
$string['filter_notsubmitted'] = 'Não submetidos';
$string['filter_graded'] = 'Avaliados';
$string['filter_late'] = 'Atrasados';
$string['filter_allgroups'] = 'Todos os grupos';
$string['filter_mygroups'] = 'Todos os meus grupos';
$string['studentcount'] = '{$a->current} de {$a->total}';

// Statuses.
$string['status_draft'] = 'Rascunho';
$string['status_submitted'] = 'Submetido';
$string['status_graded'] = 'Avaliado';
$string['status_nosubmission'] = 'Sem submissão';
$string['status_needsgrading'] = 'Por avaliar';
$string['status_new'] = 'Não submetido';
$string['status_late'] = 'Atrasado: {$a}';

// Teacher notes.
$string['notes'] = 'Notas do professor';
$string['notes_desc'] = 'Notas privadas visíveis apenas para professores e moderadores.';
$string['savenote'] = 'Guardar nota';
$string['deletenote'] = 'Eliminar';
$string['addnote'] = 'Adicionar nota';
$string['nonotes'] = 'Ainda sem notas.';
$string['confirmdelete_note'] = 'Tem a certeza de que pretende eliminar esta nota?';

// Comment library.
$string['commentlibrary'] = 'Biblioteca de comentários';
$string['savecomment'] = 'Guardar na biblioteca';
$string['insertcomment'] = 'Inserir';
$string['deletecomment'] = 'Remover';
$string['newcomment'] = 'Novo comentário...';
$string['nocomments'] = 'Sem comentários guardados.';

// UI.
$string['loading'] = 'A carregar...';
$string['saving'] = 'A guardar...';
$string['saved'] = 'Guardado';
$string['previousstudent'] = 'Aluno anterior';
$string['nextstudent'] = 'Próximo aluno';
$string['expandfilters'] = 'Mostrar filtros';
$string['collapsefilters'] = 'Ocultar filtros';
$string['backtocourse'] = 'Voltar ao curso';
$string['rubric'] = 'Rubrica';
$string['markingguide'] = 'Guia de avaliação';
$string['criterion'] = 'Critério';
$string['score'] = 'Pontuação';
$string['remark'] = 'Observação';
$string['total'] = 'Total: {$a}';
$string['viewallsubmissions'] = 'Ver todas as submissões';
$string['layout_both'] = 'Vista dividida';
$string['layout_preview'] = 'Apenas pré-visualização';
$string['layout_grade'] = 'Apenas avaliação';
$string['manualquestions'] = 'Perguntas manuais';
$string['response'] = 'Resposta';
$string['teachercomment'] = 'Comentário do professor';

// Submission comments.
$string['submissioncomments'] = 'Comentários da submissão';
$string['nocommentsyet'] = 'Ainda sem comentários';
$string['addcomment'] = 'Adicionar comentário...';
$string['postcomment'] = 'Publicar';
$string['deletesubmissioncomment'] = 'Eliminar comentário';

// Feedback files.
$string['feedbackfiles'] = 'Ficheiros de feedback';

// Plagiarism.
$string['plagiarism'] = 'Plágio';
$string['plagiarism_noresults'] = 'Não existem resultados de plágio disponíveis.';
$string['plagiarism_pending'] = 'Análise de plágio em curso';
$string['plagiarism_error'] = 'A análise de plágio falhou';

// Student feedback view.
$string['assessment_criteria'] = 'Critérios de avaliação';
$string['teacher_remark'] = 'Feedback do professor';
$string['view_feedback'] = 'Ver feedback';
$string['view_annotated_feedback'] = 'Ver Feedback Anotado';
$string['feedback_not_available'] = 'O seu feedback ainda não está disponível. Verifique novamente após a sua submissão ter sido avaliada e publicada.';
$string['no_annotated_files'] = 'Não existem ficheiros PDF anotados para a sua submissão.';
$string['feedback_banner_default'] = 'O seu professor forneceu feedback sobre a sua submissão.';

// Document conversion.
$string['conversion_failed'] = 'Não foi possível converter este ficheiro para PDF para pré-visualização.';
$string['converting_file'] = 'A converter documento para PDF...';
$string['conversion_timeout'] = 'A conversão do documento está a demorar demasiado tempo. Tente novamente mais tarde.';
$string['download_annotated_pdf'] = 'Transferir PDF anotado';
$string['download_original_submission'] = 'Transferir submissão original: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Notas privadas do professor armazenadas por aluno e por atividade no avaliador unificado.';
$string['privacy:metadata:notes:cmid'] = 'O ID do módulo do curso a que a nota se refere.';
$string['privacy:metadata:notes:userid'] = 'O aluno a quem a nota se refere.';
$string['privacy:metadata:notes:authorid'] = 'O professor que escreveu a nota.';
$string['privacy:metadata:notes:content'] = 'O conteúdo da nota.';
$string['privacy:metadata:comments'] = 'Entradas reutilizáveis da biblioteca de comentários no avaliador unificado.';
$string['privacy:metadata:comments:userid'] = 'O professor proprietário do comentário.';
$string['privacy:metadata:comments:content'] = 'O conteúdo do comentário.';
$string['privacy:metadata:preferences'] = 'Preferências do utilizador para a interface do avaliador unificado.';
$string['privacy:metadata:preferences:userid'] = 'O utilizador a quem pertencem as preferências.';
$string['privacy:metadata:preferences:data'] = 'Os dados das preferências codificados em JSON.';
$string['privacy:metadata:annotations'] = 'Anotações de documentos armazenadas no avaliador unificado.';
$string['privacy:metadata:annotations:cmid'] = 'O ID do módulo do curso a que a anotação se refere.';
$string['privacy:metadata:annotations:userid'] = 'O aluno cuja submissão foi anotada.';
$string['privacy:metadata:annotations:authorid'] = 'O professor que criou a anotação.';
$string['privacy:metadata:annotations:data'] = 'Os dados da anotação (JSON Fabric.js).';
$string['annotations'] = 'Anotações';

// PDF viewer.
$string['pdf_prevpage'] = 'Página anterior';
$string['pdf_nextpage'] = 'Página seguinte';
$string['pdf_zoomin'] = 'Ampliar';
$string['pdf_zoomout'] = 'Reduzir';
$string['pdf_zoomfit'] = 'Ajustar à largura';
$string['pdf_search'] = 'Pesquisar no documento';

// Annotation tools.
$string['annotate_tools'] = 'Ferramentas de anotação';
$string['annotate_select'] = 'Selecionar';
$string['annotate_textselect'] = 'Selecionar texto';
$string['annotate_comment'] = 'Comentário';
$string['annotate_highlight'] = 'Realçar';
$string['annotate_pen'] = 'Caneta';
$string['annotate_pen_fine'] = 'Fina';
$string['annotate_pen_medium'] = 'Média';
$string['annotate_pen_thick'] = 'Grossa';
$string['annotate_stamps'] = 'Carimbos';
$string['annotate_stamp_check'] = 'Carimbo de verificação';
$string['annotate_stamp_cross'] = 'Carimbo de cruz';
$string['annotate_stamp_question'] = 'Carimbo de interrogação';
$string['annotate_red'] = 'Vermelho';
$string['annotate_yellow'] = 'Amarelo';
$string['annotate_green'] = 'Verde';
$string['annotate_blue'] = 'Azul';
$string['annotate_black'] = 'Preto';
$string['annotate_shape'] = 'Formas';
$string['annotate_shape_rect'] = 'Retângulo';
$string['annotate_shape_circle'] = 'Círculo';
$string['annotate_shape_arrow'] = 'Seta';
$string['annotate_shape_line'] = 'Linha';
$string['annotate_undo'] = 'Desfazer';
$string['annotate_redo'] = 'Refazer';
$string['annotate_delete'] = 'Eliminar selecionado';
$string['annotate_clearall'] = 'Limpar tudo';
$string['annotate_clear_confirm'] = 'Tem a certeza de que pretende limpar todas as anotações desta página? Esta ação não pode ser revertida.';

// Document info.
$string['docinfo'] = 'Informações do documento';
$string['docinfo_filename'] = 'Nome do ficheiro';
$string['docinfo_filesize'] = 'Tamanho do ficheiro';
$string['docinfo_pages'] = 'Páginas';
$string['docinfo_wordcount'] = 'Contagem de palavras';
$string['docinfo_author'] = 'Autor';
$string['docinfo_creator'] = 'Criador';
$string['docinfo_created'] = 'Criado';
$string['docinfo_modified'] = 'Modificado';
$string['docinfo_calculating'] = 'A calcular...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Ver Feedback do Fórum';
$string['forum_your_posts'] = 'As suas publicações no fórum';
$string['forum_no_posts'] = 'Não efetuou quaisquer publicações neste fórum.';
$string['forum_feedback_banner'] = 'O seu professor avaliou a sua participação no fórum.';
$string['forum_wordcount'] = '{$a} palavras';
$string['forum_posts_pill'] = 'Publicações';
$string['submission_content_pill'] = 'Submissão';
$string['forum_tab_posts'] = 'Publicações';
$string['forum_tab_files'] = 'Ficheiros Anotados';
$string['view_quiz_feedback'] = 'Ver Feedback do Teste';
$string['quiz_feedback_banner'] = 'O seu professor forneceu feedback sobre o seu teste.';
$string['quiz_your_attempt'] = 'A Sua Tentativa';
$string['quiz_no_attempt'] = 'Não completou nenhuma tentativa para este teste.';
$string['quiz_select_attempt'] = 'Selecionar tentativa';
$string['select_attempt'] = 'Selecionar tentativa';
$string['attempt_label'] = 'Tentativa {$a}';

// Post grades.
$string['grades_posted'] = 'Notas publicadas';
$string['grades_hidden'] = 'Notas ocultas';
$string['post_grades'] = 'Publicar notas';
$string['unpost_grades'] = 'Ocultar notas';
$string['confirm_post_grades'] = 'Publicar todas as notas desta atividade? Os alunos poderão ver as suas notas e feedback.';
$string['confirm_unpost_grades'] = 'Ocultar todas as notas desta atividade? Os alunos deixarão de poder ver as suas notas e feedback.';
$string['schedule_post'] = 'Publicar numa data';
$string['schedule_post_btn'] = 'Agendar';
$string['grades_scheduled'] = 'Publicação em {$a}';
$string['schedule_must_be_future'] = 'A data agendada deve ser no futuro.';
$string['quiz_post_grades_disabled'] = 'A publicação de notas não está disponível para testes. A visibilidade das notas é controlada pelas opções de revisão do teste.';
$string['quiz_post_grades_no_schedule'] = 'O agendamento não está disponível para testes. Utilize Publicar ou Ocultar.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Reverter para rascunho';
$string['action_remove_submission'] = 'Remover submissão';
$string['action_lock'] = 'Impedir alterações à submissão';
$string['action_unlock'] = 'Permitir alterações à submissão';
$string['action_edit_submission'] = 'Editar submissão';
$string['action_grant_extension'] = 'Conceder extensão';
$string['action_edit_extension'] = 'Editar extensão';
$string['action_submit_for_grading'] = 'Submeter para avaliação';
$string['confirm_revert_to_draft'] = 'Tem a certeza de que pretende reverter esta submissão para o estado de rascunho?';
$string['confirm_remove_submission'] = 'Tem a certeza de que pretende remover esta submissão? Esta ação não pode ser revertida.';
$string['confirm_lock_submission'] = 'Impedir este aluno de efetuar alterações à submissão?';
$string['confirm_unlock_submission'] = 'Permitir que este aluno efetue alterações à submissão?';
$string['confirm_submit_for_grading'] = 'Submeter este rascunho em nome do aluno?';
$string['invalidaction'] = 'Ação de submissão inválida.';

// Override actions.
$string['override'] = 'Substituição';
$string['action_add_override'] = 'Adicionar substituição';
$string['action_edit_override'] = 'Editar substituição';
$string['action_delete_override'] = 'Eliminar substituição';
$string['confirm_delete_override'] = 'Tem a certeza de que pretende eliminar esta substituição de utilizador?';
$string['override_saved'] = 'Substituição guardada com sucesso.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Eliminar extensão';
$string['confirm_delete_extension'] = 'Tem a certeza de que pretende eliminar esta extensão de prazo?';
$string['quiz_extension_original_duedate'] = 'Data limite original';
$string['quiz_extension_current_extension'] = 'Extensão atual';
$string['quiz_extension_new_duedate'] = 'Nova data limite da extensão';
$string['quiz_extension_must_be_after_duedate'] = 'A data da extensão deve ser posterior à data limite atual.';
$string['quiz_extension_plugin_missing'] = 'O plugin quizaccess_duedate é necessário para extensões de testes mas não está instalado.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Data limite do fórum';
$string['forum_extension_current_extension'] = 'Extensão atual';
$string['forum_extension_new_duedate'] = 'Nova data limite da extensão';
$string['forum_extension_must_be_after_duedate'] = 'A data da extensão deve ser posterior à data limite do fórum.';

// Student profile popout.
$string['profile_view_full'] = 'Ver perfil completo';
$string['profile_login_as'] = 'Iniciar sessão como';
$string['profile_no_email'] = 'Sem email disponível';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Expressão regular do código de curso';
$string['setting_coursecode_regex_desc'] = 'A Biblioteca de Comentários organiza os comentários guardados por código de curso, para que os professores possam reutilizar feedback em diferentes edições do mesmo curso (por exemplo, de semestre para semestre). Esta definição controla como os códigos de curso são extraídos dos nomes curtos dos cursos Moodle. Introduza um padrão de expressão regular PHP que corresponda à parte do código dos seus nomes curtos (por exemplo, <code>/[A-Z]{3}\\d{4}/</code> extrairia <strong>THE2201</strong> de um nome curto como <em>THE2201-2026-S1</em>). Deixe vazio para utilizar o nome curto completo como código de curso.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Ativar formulário de denúncia de impropriedade académica';
$string['setting_enable_report_form_desc'] = 'Quando ativado, um botão "Denunciar impropriedade académica" aparece nas secções de plágio, ligando a um formulário de denúncia externo.';
$string['setting_report_form_url'] = 'Modelo de URL do formulário de denúncia';
$string['setting_report_form_url_desc'] = 'URL do formulário de denúncia de impropriedade académica. Marcadores suportados: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Estes são substituídos em tempo de execução por valores codificados para URL. Para Microsoft Forms, utilize a funcionalidade "Obter URL pré-preenchido" para encontrar os nomes dos parâmetros.';
$string['report_impropriety'] = 'Denunciar impropriedade académica';

// Comment library v2.
$string['clib_title'] = 'Biblioteca de Comentários';
$string['clib_all'] = 'Todos';
$string['clib_quick_add'] = 'Adicionar comentário rápido...';
$string['clib_manage'] = 'Gerir Biblioteca';
$string['clib_no_comments'] = 'Ainda sem comentários.';
$string['clib_insert'] = 'Inserir';
$string['clib_copied'] = 'Comentário copiado para a área de transferência';
$string['clib_my_library'] = 'A Minha Biblioteca';
$string['clib_shared_library'] = 'Biblioteca Partilhada';
$string['clib_new_comment'] = 'Novo comentário';
$string['clib_edit_comment'] = 'Editar comentário';
$string['clib_delete_comment'] = 'Eliminar comentário';
$string['clib_confirm_delete'] = 'Tem a certeza de que pretende eliminar este comentário?';
$string['clib_share'] = 'Partilhar';
$string['clib_unshare'] = 'Deixar de partilhar';
$string['clib_import'] = 'Importar';
$string['clib_imported'] = 'Comentário importado para a sua biblioteca';
$string['clib_copy_to_course'] = 'Copiar para o curso';
$string['clib_all_courses'] = 'Todos os cursos';
$string['clib_tags'] = 'Etiquetas';
$string['clib_manage_tags'] = 'Gerir etiquetas';
$string['clib_new_tag'] = 'Nova etiqueta';
$string['clib_edit_tag'] = 'Editar etiqueta';
$string['clib_delete_tag'] = 'Eliminar etiqueta';
$string['clib_confirm_delete_tag'] = 'Tem a certeza de que pretende eliminar esta etiqueta? Será removida de todos os comentários.';
$string['clib_system_tag'] = 'Predefinição do sistema';
$string['clib_shared_by'] = 'Partilhado por {$a}';
$string['clib_no_shared'] = 'Sem comentários partilhados disponíveis.';
$string['clib_picker_freetext'] = 'Ou escreva o seu próprio...';
$string['clib_picker_loading'] = 'A carregar comentários...';
$string['clib_offline_mode'] = 'A mostrar comentários em cache — a edição não está disponível offline.';
$string['unifiedgrader:sharecomments'] = 'Partilhar comentários na biblioteca com outros professores';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Entradas da biblioteca de comentários no avaliador unificado.';
$string['privacy:metadata:clib:userid'] = 'O professor proprietário do comentário.';
$string['privacy:metadata:clib:coursecode'] = 'O código do curso associado ao comentário.';
$string['privacy:metadata:clib:content'] = 'O conteúdo do comentário.';
$string['privacy:metadata:cltag'] = 'Etiquetas da biblioteca de comentários no avaliador unificado.';
$string['privacy:metadata:cltag:userid'] = 'O professor proprietário da etiqueta.';
$string['privacy:metadata:cltag:name'] = 'O nome da etiqueta.';

// Penalties.
$string['penalties'] = 'Penalizações';
$string['penalty_late'] = 'Submissão atrasada';
$string['penalty_late_days'] = '{$a} dia(s) de atraso';
$string['penalty_late_auto'] = 'Calculada automaticamente com base nas regras de penalização';
$string['penalty_wordcount'] = 'Contagem de palavras';
$string['penalty_other'] = 'Outra';
$string['penalty_custom'] = 'Personalizada';
$string['penalty_label_placeholder'] = 'Rótulo (máx. 15 caract.)';
$string['penalty_active'] = 'Penalizações ativas';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['penalty_late_label'] = 'Atrasado';
$string['penalty_late_applied'] = 'Penalidade por atraso de {$a}% aplicada';
$string['late_days'] = '{$a} dias';
$string['late_day'] = '{$a} dia';
$string['late_hours'] = '{$a} horas';
$string['late_hour'] = '{$a} hora';
$string['late_mins'] = '{$a} min';
$string['late_min'] = '{$a} min';
$string['late_lessthanmin'] = '< 1 min';
$string['finalgradeafterpenalties'] = 'Nota final após penalizações:';
$string['cannotdeleteautopenalty'] = 'As penalizações por atraso são calculadas automaticamente e não podem ser eliminadas.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Transferir PDF de feedback';
$string['feedback_summary_overall_feedback'] = 'Feedback geral';
$string['feedback_summary_graded_on'] = 'Avaliado em {$a}';
$string['feedback_summary_generated_by'] = 'Gerado pelo avaliador unificado';
$string['feedback_summary_media_note'] = 'O conteúdo multimédia está disponível na visualização de feedback online.';
$string['feedback_summary_no_grade'] = 'N/D';
$string['feedback_summary_remark'] = 'Comentário do Professor';
$string['feedback_summary_total'] = 'Total';
$string['levels'] = 'Níveis';
$string['error_gs_not_configured'] = 'O GhostScript não está configurado neste servidor Moodle. O administrador deve definir o caminho do GhostScript em Administração do site > Plugins > Módulos de atividade > Trabalho > Feedback > Anotar PDF.';
$string['error_pdf_combine_failed'] = 'Falha ao combinar ficheiros PDF: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Penalizações de nota aplicadas por professores no avaliador unificado.';
$string['privacy:metadata:penalty:userid'] = 'O aluno a quem a penalização foi aplicada.';
$string['privacy:metadata:penalty:authorid'] = 'O professor que aplicou a penalização.';
$string['privacy:metadata:penalty:category'] = 'A categoria da penalização (contagem de palavras ou outra).';
$string['privacy:metadata:penalty:label'] = 'O rótulo personalizado da penalização.';
$string['privacy:metadata:penalty:percentage'] = 'A percentagem da penalização.';
$string['privacy:metadata:fext'] = 'Extensões de prazo de fórum concedidas por professores no avaliador unificado.';
$string['privacy:metadata:fext:userid'] = 'O aluno a quem a extensão foi concedida.';
$string['privacy:metadata:fext:authorid'] = 'O professor que concedeu a extensão.';
$string['privacy:metadata:fext:extensionduedate'] = 'A data limite estendida.';
$string['privacy:metadata:qfb'] = 'Feedback de teste por tentativa armazenado pelo avaliador unificado.';
$string['privacy:metadata:qfb:userid'] = 'O aluno a quem o feedback se destina.';
$string['privacy:metadata:qfb:grader'] = 'O professor que forneceu o feedback.';
$string['privacy:metadata:qfb:feedback'] = 'O texto do feedback.';
$string['privacy:metadata:qfb:attemptnumber'] = 'O número da tentativa do teste.';
$string['privacy:metadata:scomm'] = 'Comentários de submissão armazenados pelo Unified Grader.';
$string['privacy:metadata:scomm:cmid'] = 'O módulo do curso ao qual o comentário pertence.';
$string['privacy:metadata:scomm:userid'] = 'O estudante sobre quem o tópico de comentários se refere.';
$string['privacy:metadata:scomm:authorid'] = 'O utilizador que escreveu o comentário.';
$string['privacy:metadata:scomm:content'] = 'O conteúdo do comentário.';
$string['privacy_forum_extensions'] = 'Extensões de fórum';
$string['privacy_quiz_feedback'] = 'Feedback de teste';

// Notification strings.
$string['messageprovider:submission_comment'] = 'Notificações de comentários de submissão';
$string['notification_comment_subject'] = 'Novo comentário em {$a->activityname}';
$string['notification_comment_body'] = '<p><strong>{$a->authorfullname}</strong> publicou um comentário em <a href="{$a->activityurl}">{$a->activityname}</a> em {$a->coursename} ({$a->timecreated}):</p><blockquote>{$a->content}</blockquote>';
$string['notification_comment_small'] = '{$a->authorfullname} comentou em {$a->activityname}';

// Offline cache and save status.
$string['allchangessaved'] = 'Todas as alterações guardadas';
$string['editing'] = 'A editar...';
$string['offlinesavedlocally'] = 'Offline — guardado localmente';
$string['connectionlost'] = 'Ligação perdida — o seu trabalho está guardado localmente e será sincronizado quando a ligação for restabelecida.';
$string['recoveredunsavedchanges'] = 'Foram recuperadas alterações não guardadas da sua última sessão.';
$string['restore'] = 'Restaurar';
$string['discard'] = 'Descartar';
$string['mark_as_graded'] = 'Marcar como avaliado';
