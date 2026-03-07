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
 * Language strings for local_unifiedgrader (Zulu / isiZulu).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Umklasisi Ohlanganisiwe';
$string['grading_interface'] = 'Umklasisi Ohlanganisiwe';
$string['nopermission'] = 'Awunayo imvume yokusebenzisa uMklasisi Ohlanganisiwe.';
$string['invalidactivitytype'] = 'Lolu hlobo lomsebenzi alusekelwa yuMklasisi Ohlanganisiwe.';
$string['invalidmodule'] = 'Imodyuli yomsebenzi engavumelekile.';
$string['viewfeedback'] = 'Buka impendulo';

// Attempts.
$string['attempt'] = 'Umzamo';

// Capabilities.
$string['unifiedgrader:grade'] = 'Sebenzisa uMklasisi Ohlanganisiwe ukuklasisa';
$string['unifiedgrader:viewall'] = 'Buka bonke abafundi kuMklasisi Ohlanganisiwe';
$string['unifiedgrader:viewnotes'] = 'Buka amanothi ayimfihlo kathisha';
$string['unifiedgrader:managenotes'] = 'Dala uphinde uhlele amanothi ayimfihlo kathisha';
$string['unifiedgrader:viewfeedback'] = 'Buka impendulo enezibhalo kuMklasisi Ohlanganisiwe';

// Settings.
$string['setting_enable_assign'] = 'Vumela kuma-Assignment';
$string['setting_enable_assign_desc'] = 'Vumela uMklasisi Ohlanganisiwe ukuthi isetshenziswe emisebenzini yama-assignment.';
$string['setting_enable_forum'] = 'Vumela kumaForamu';
$string['setting_enable_forum_desc'] = 'Vumela uMklasisi Ohlanganisiwe ukuthi isetshenziswe emisebenzini yamaforamu.';
$string['setting_enable_quiz'] = 'Vumela kumaQuiz';
$string['setting_enable_quiz_desc'] = 'Vumela uMklasisi Ohlanganisiwe ukuthi isetshenziswe emisebenzini yamaquiz.';
$string['setting_enable_quiz_post_grades'] = 'Vumela ukuthumela amamaki kumaquiz';
$string['setting_enable_quiz_post_grades_desc'] = 'Ukubonakala kwamamaki equiz kujwayelekile kuphathwa izinketho zokubuyekeza zequiz. Uma kuvulelwe, inkinobho ethi "Thumela amamaki" yoMklasisi Ohlanganisiwe izobuyekeza izinketho zokubuyekeza zequiz ngohlelo ukuze kuboniswe noma kufihle amamaki. Uma kungavulelwanga (okuzenzakalelayo), inkinobho yokuthumela amamaki iyafihlwa kumaquiz.';
$string['setting_allow_manual_override'] = 'Vumela ukuchezuka kwamamaki ngesandla';
$string['setting_allow_manual_override_desc'] = 'Uma kuvulelwe, othisha bangathayipha amamaki ngesandla ngisho noma irubhriki noma umhlahlandlela wokuklasisa usekelwe. Uma kungavulelwanga, amamaki abalwa ngokuphelele ngemibandela yerubhriki noma yomhlahlandlela wokuklasisa.';

// Grading interface.
$string['grade'] = 'Amamaki';
$string['savegrade'] = 'Gcina amamaki';
$string['savefeedback'] = 'Gcina impendulo';
$string['savinggrade'] = 'Kugcinwa amamaki...';
$string['gradesaved'] = 'Amamaki agciniwe';
$string['error_saving'] = 'Iphutha ekugcineni amamaki.';
$string['error_network'] = 'Ayikwazi ukuxhumana neseva. Sicela uhlole uxhumano lwakho bese uzama futhi.';
$string['error_offline_comments'] = 'Awukwazi ukungeza amazwana ngenkathi ungaxhuniwe.';
$string['feedback'] = 'Impendulo';
$string['overall_feedback'] = 'Impendulo Ephelele';
$string['feedback_saved'] = 'Impendulo (igciniwe)';
$string['edit_feedback'] = 'Hlela';
$string['delete_feedback'] = 'Susa';
$string['confirm_delete_feedback'] = 'Uqinisekile ukuthi ufuna ukususa le mpendulo? Amamaki azogcinwa.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Nweba';

// Submissions.
$string['submission'] = 'Ukuthunyelwe';
$string['nosubmission'] = 'Akukho okuthunyelwe';
$string['previewpanel'] = 'Ukubuka kuqala okuthunyelwe';
$string['markingpanel'] = 'Iphaneli yokuklasisa';
$string['onlinetext'] = 'Umbhalo ku-inthanethi';
$string['submittedfiles'] = 'Amafayela athunyelwe';
$string['viewfile'] = 'Buka ifayela';

// Participants.
$string['participants'] = 'Ababambiqhaza';
$string['search'] = 'Sesha ababambiqhaza...';
$string['sortby'] = 'Hlunga ngoku';
$string['sortby_fullname'] = 'Igama eligcwele';
$string['sortby_submittedat'] = 'Usuku lokuthumela';
$string['sortby_status'] = 'Isimo';
$string['filter_all'] = 'Bonke ababambiqhaza';
$string['filter_submitted'] = 'Okuthunyelwe';
$string['filter_needsgrading'] = 'Okungakaklasiswa';
$string['filter_notsubmitted'] = 'Okungathunyelwanga';
$string['filter_graded'] = 'Okuklasisiwe';
$string['filter_late'] = 'Okushiywe yisikhathi';
$string['filter_allgroups'] = 'Wonke amaqembu';
$string['studentcount'] = '{$a->current} ku-{$a->total}';

// Statuses.
$string['status_draft'] = 'Isihloko sokuqala';
$string['status_submitted'] = 'Kuthunyelwe';
$string['status_graded'] = 'Kuklasisiwe';
$string['status_nosubmission'] = 'Akukho okuthunyelwe';
$string['status_needsgrading'] = 'Kudinga ukuhlolwa';
$string['status_new'] = 'Akuthunyelwanga';
$string['status_late'] = 'Kushiywe yisikhathi: {$a}';

// Teacher notes.
$string['notes'] = 'Amanothi kathisha';
$string['notes_desc'] = 'Amanothi ayimfihlo abonwa othisha nabahloli kuphela.';
$string['savenote'] = 'Gcina inothi';
$string['deletenote'] = 'Susa';
$string['addnote'] = 'Engeza inothi';
$string['nonotes'] = 'Awekho amanothi okwamanje.';
$string['confirmdelete_note'] = 'Uqinisekile ukuthi ufuna ukususa leli nothi?';

// Comment library.
$string['commentlibrary'] = 'Umtapo wamazwana';
$string['savecomment'] = 'Gcina emtapweni';
$string['insertcomment'] = 'Faka';
$string['deletecomment'] = 'Susa';
$string['newcomment'] = 'Amazwana amasha...';
$string['nocomments'] = 'Awekho amazwana agciniwe.';

// UI.
$string['loading'] = 'Kuyalayisha...';
$string['saving'] = 'Kuyagcina...';
$string['saved'] = 'Kugciniwe';
$string['previousstudent'] = 'Umfundi ongaphambili';
$string['nextstudent'] = 'Umfundi olandelayo';
$string['expandfilters'] = 'Bonisa izihluzi';
$string['collapsefilters'] = 'Fihla izihluzi';
$string['backtocourse'] = 'Buyela ekhasini';
$string['rubric'] = 'Irubhriki';
$string['markingguide'] = 'Umhlahlandlela wokuklasisa';
$string['criterion'] = 'Umbandela';
$string['score'] = 'Amaphuzu';
$string['remark'] = 'Amazwana';
$string['total'] = 'Isamba: {$a}';
$string['viewallsubmissions'] = 'Buka konke okuthunyelwe';
$string['layout_both'] = 'Ukubuka okuhlukanisiwe';
$string['layout_preview'] = 'Ukubuka kuqala kuphela';
$string['layout_grade'] = 'Ukuhlola kuphela';
$string['manualquestions'] = 'Imibuzo yesandla';
$string['response'] = 'Impendulo';
$string['teachercomment'] = 'Amazwana kathisha';

// Submission comments.
$string['submissioncomments'] = 'Amazwana okuthunyelwe';
$string['nocommentsyet'] = 'Awekho amazwana okwamanje';
$string['addcomment'] = 'Engeza amazwana...';
$string['postcomment'] = 'Thumela';
$string['deletesubmissioncomment'] = 'Susa amazwana';

// Feedback files.
$string['feedbackfiles'] = 'Amafayela empendulo';

// Plagiarism.
$string['plagiarism'] = 'Ukweba imibhalo';
$string['plagiarism_noresults'] = 'Awekho amaphumela okweba imibhalo atholakalayo.';
$string['plagiarism_pending'] = 'Ukuhlolwa kokweba imibhalo kuyaqhubeka';
$string['plagiarism_error'] = 'Ukuhlolwa kokweba imibhalo kuhlulekile';

// Student feedback view.
$string['assessment_criteria'] = 'Imibandela yokuhlola';
$string['teacher_remark'] = 'Impendulo kathisha';
$string['view_feedback'] = 'Buka impendulo';
$string['view_annotated_feedback'] = 'Buka Impendulo Enezibhalo';
$string['feedback_not_available'] = 'Impendulo yakho ayikatholakali okwamanje. Sicela ubuye uhlole ngemuva kokuba umsebenzi wakho usuhlolwe futhi wakhishwa.';
$string['no_annotated_files'] = 'Awekho amafayela e-PDF anezibhalo okuthunyelwe kwakho.';
$string['feedback_banner_default'] = 'Uthisha wakho unikeze impendulo ngokuthunyelwe kwakho.';

// Document conversion.
$string['conversion_failed'] = 'Leli fayela alikhonanga ukuguqulwa libe yi-PDF ukuze libukwe kuqala.';
$string['converting_file'] = 'Kuguqulwa idokhumenti ibe yi-PDF...';
$string['conversion_timeout'] = 'Ukuguqulwa kwedokhumenti kuthatha isikhathi eside kakhulu. Sicela uzame futhi kamuva.';
$string['download_annotated_pdf'] = 'Landa i-PDF enezibhalo';
$string['download_original_submission'] = 'Landa okuthunyelwe kwangempela: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Amanothi ayimfihlo kathisha agcinwa ngomfundi ngamunye ngomsebenzi ngamunye kuMklasisi Ohlanganisiwe.';
$string['privacy:metadata:notes:cmid'] = 'I-ID yemodyuli yekhasini inothi eliphathelene nayo.';
$string['privacy:metadata:notes:userid'] = 'Umfundi inothi eliphathelene naye.';
$string['privacy:metadata:notes:authorid'] = 'Uthisha owabhala inothi.';
$string['privacy:metadata:notes:content'] = 'Okuqukethwe kwenothi.';
$string['privacy:metadata:comments'] = 'Okufakiwe emtapweni wamazwana asetshenziswa kabusha kuMklasisi Ohlanganisiwe.';
$string['privacy:metadata:comments:userid'] = 'Uthisha ongumnikazi wamazwana.';
$string['privacy:metadata:comments:content'] = 'Okuqukethwe kwamazwana.';
$string['privacy:metadata:preferences'] = 'Izintandokazi zomsebenzisi woMklasisi Ohlanganisiwe.';
$string['privacy:metadata:preferences:userid'] = 'Umsebenzisi izintandokazi ezingezakhe.';
$string['privacy:metadata:preferences:data'] = 'Idatha yezintandokazi eyi-JSON.';
$string['privacy:metadata:annotations'] = 'Izibhalo zedokhumenti ezigcinwe kuMklasisi Ohlanganisiwe.';
$string['privacy:metadata:annotations:cmid'] = 'I-ID yemodyuli yekhasini isibhalo esiphathelene nayo.';
$string['privacy:metadata:annotations:userid'] = 'Umfundi okuthunyelwe kwakhe kunezibhalo.';
$string['privacy:metadata:annotations:authorid'] = 'Uthisha owadala isibhalo.';
$string['privacy:metadata:annotations:data'] = 'Idatha yesibhalo (Fabric.js JSON).';
$string['annotations'] = 'Izibhalo';

// PDF viewer.
$string['pdf_prevpage'] = 'Ikhasi elingaphambili';
$string['pdf_nextpage'] = 'Ikhasi elilandelayo';
$string['pdf_zoomin'] = 'Sondeza';
$string['pdf_zoomout'] = 'Dedisa';
$string['pdf_zoomfit'] = 'Lungisa ububanzi';
$string['pdf_search'] = 'Sesha edokhumentini';

// Annotation tools.
$string['annotate_tools'] = 'Amathuluzi ezibhalo';
$string['annotate_select'] = 'Khetha';
$string['annotate_textselect'] = 'Khetha umbhalo';
$string['annotate_comment'] = 'Amazwana';
$string['annotate_highlight'] = 'Gqamisa';
$string['annotate_pen'] = 'Ipeni';
$string['annotate_pen_fine'] = 'Elicolile';
$string['annotate_pen_medium'] = 'Eliphakathi';
$string['annotate_pen_thick'] = 'Eligqamile';
$string['annotate_stamps'] = 'Izitembu';
$string['annotate_stamp_check'] = 'Isitembu sokuphawula';
$string['annotate_stamp_cross'] = 'Isitembu sesiphambano';
$string['annotate_stamp_question'] = 'Isitembu sombuzo';
$string['annotate_red'] = 'Okubomvu';
$string['annotate_yellow'] = 'Okuphuzi';
$string['annotate_green'] = 'Okuluhlaza';
$string['annotate_blue'] = 'Okuluhlaza sasibhakabhaka';
$string['annotate_black'] = 'Okumnyama';
$string['annotate_shape'] = 'Izimo';
$string['annotate_shape_rect'] = 'Unxantathu onezinhlangothi ezine';
$string['annotate_shape_circle'] = 'Indilinga';
$string['annotate_shape_arrow'] = 'Umcibisholo';
$string['annotate_shape_line'] = 'Umugqa';
$string['annotate_undo'] = 'Buyisela emuva';
$string['annotate_redo'] = 'Phinda';
$string['annotate_delete'] = 'Susa okukhethiwe';
$string['annotate_clearall'] = 'Sula konke';
$string['annotate_clear_confirm'] = 'Uqinisekile ukuthi ufuna ukusula zonke izibhalo kuleli khasi? Lokhu akukwazi ukubuyiselwa emuva.';

// Document info.
$string['docinfo'] = 'Ulwazi lwedokhumenti';
$string['docinfo_filename'] = 'Igama lefayela';
$string['docinfo_filesize'] = 'Ubukhulu befayela';
$string['docinfo_pages'] = 'Amakhasi';
$string['docinfo_wordcount'] = 'Inani lamagama';
$string['docinfo_author'] = 'Umbhali';
$string['docinfo_creator'] = 'Umdali';
$string['docinfo_created'] = 'Kudalwe';
$string['docinfo_modified'] = 'Kushintshiwe';
$string['docinfo_calculating'] = 'Kubalwa...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Buka Impendulo Yeforamu';
$string['forum_your_posts'] = 'Okuthunyelwe kwakho kweforamu';
$string['forum_no_posts'] = 'Awukathumi lutho kule foramu.';
$string['forum_feedback_banner'] = 'Uthisha wakho uhlole ukubamba kwakho iqhaza kule foramu.';
$string['forum_wordcount'] = '{$a} amagama';
$string['forum_posts_pill'] = 'Okuthunyelwe';
$string['submission_content_pill'] = 'Okuthunyelwe';
$string['forum_tab_posts'] = 'Okuthunyelwe';
$string['forum_tab_files'] = 'Amafayela Anezibhalo';
$string['view_quiz_feedback'] = 'Buka Impendulo Yequiz';
$string['quiz_feedback_banner'] = 'Uthisha wakho unikeze impendulo ngequiz yakho.';
$string['quiz_your_attempt'] = 'Umzamo Wakho';
$string['quiz_no_attempt'] = 'Awukaqedi mizamo kule quiz.';
$string['quiz_select_attempt'] = 'Khetha umzamo';
$string['select_attempt'] = 'Khetha umzamo';
$string['attempt_label'] = 'Umzamo {$a}';

// Post grades.
$string['grades_posted'] = 'Amamaki athunyelwe';
$string['grades_hidden'] = 'Amamaki afihliwe';
$string['post_grades'] = 'Thumela amamaki';
$string['unpost_grades'] = 'Buyisela amamaki';
$string['confirm_post_grades'] = 'Thumela wonke amamaki alo msebenzi? Abafundi bazokwazi ukubona amamaki nempendulo yabo.';
$string['confirm_unpost_grades'] = 'Buyisela wonke amamaki alo msebenzi? Abafundi ngeke besakwazi ukubona amamaki nempendulo yabo.';
$string['schedule_post'] = 'Thumela ngosuku';
$string['schedule_post_btn'] = 'Hlela isikhathi';
$string['grades_scheduled'] = 'Kuzothunyelwa {$a}';
$string['schedule_must_be_future'] = 'Usuku oluhlelelwe kumele lube ngolwesikhathi esizayo.';
$string['quiz_post_grades_disabled'] = 'Ukuthumela amamaki akutholakali kumaquiz. Ukubonakala kwamamaki kulawulwa izinketho zokubuyekeza zequiz.';
$string['quiz_post_grades_no_schedule'] = 'Ukuhlela isikhathi akutholakali kumaquiz. Sebenzisa ukuThumela noma ukuBuyisela esikhundleni salokho.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Buyisela esihlokoweni sokuqala';
$string['action_remove_submission'] = 'Susa okuthunyelwe';
$string['action_lock'] = 'Vimbela izinguquko zokuthunyelwe';
$string['action_unlock'] = 'Vumela izinguquko zokuthunyelwe';
$string['action_edit_submission'] = 'Hlela okuthunyelwe';
$string['action_grant_extension'] = 'Nika isikhathi esengeziwe';
$string['action_edit_extension'] = 'Hlela isikhathi esengeziwe';
$string['action_submit_for_grading'] = 'Thumela ukuze kuhlolwe';
$string['confirm_revert_to_draft'] = 'Uqinisekile ukuthi ufuna ukubuyisela lokhu okuthunyelwe esimweni sesihloko sokuqala?';
$string['confirm_remove_submission'] = 'Uqinisekile ukuthi ufuna ukususa lokhu okuthunyelwe? Lokhu akukwazi ukubuyiselwa emuva.';
$string['confirm_lock_submission'] = 'Vimbela lo mfundi ukuthi enze izinguquko kokuthunyelwe?';
$string['confirm_unlock_submission'] = 'Vumela lo mfundi ukuthi enze izinguquko kokuthunyelwe?';
$string['confirm_submit_for_grading'] = 'Thumela lesi sihloko sokuqala egameni lomfundi?';
$string['invalidaction'] = 'Isenzo sokuthumela esingavumelekile.';

// Override actions.
$string['override'] = 'Ukuchezuka';
$string['action_add_override'] = 'Engeza ukuchezuka';
$string['action_edit_override'] = 'Hlela ukuchezuka';
$string['action_delete_override'] = 'Susa ukuchezuka';
$string['confirm_delete_override'] = 'Uqinisekile ukuthi ufuna ukususa lokhu kuchezuka komsebenzisi?';
$string['override_saved'] = 'Ukuchezuka kugcine ngempumelelo.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Susa isikhathi esengeziwe';
$string['confirm_delete_extension'] = 'Uqinisekile ukuthi ufuna ukususa lesi sikhathi esengeziwe sosuku lokugcina?';
$string['quiz_extension_original_duedate'] = 'Usuku lokugcina lwangempela';
$string['quiz_extension_current_extension'] = 'Isikhathi esengeziwe samanje';
$string['quiz_extension_new_duedate'] = 'Usuku lokugcina lwesengeziwe';
$string['quiz_extension_must_be_after_duedate'] = 'Usuku lwesengeziwe kumele lube ngemuva kosuku lokugcina lwamanje.';
$string['quiz_extension_plugin_missing'] = 'I-plugin ye-quizaccess_duedate iyadingeka ukuze kwandiswe isikhathi sequiz kodwa ayifakiwe.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Usuku lokugcina lweforamu';
$string['forum_extension_current_extension'] = 'Isikhathi esengeziwe samanje';
$string['forum_extension_new_duedate'] = 'Usuku lokugcina lwesengeziwe';
$string['forum_extension_must_be_after_duedate'] = 'Usuku lwesengeziwe kumele lube ngemuva kosuku lokugcina lweforamu.';

// Student profile popout.
$string['profile_view_full'] = 'Buka iphrofayili ephelele';
$string['profile_login_as'] = 'Ngena njengo';
$string['profile_no_email'] = 'Alukho i-imeyili etholakalayo';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'I-regex yekhodi yekhasini';
$string['setting_coursecode_regex_desc'] = 'Umtapo Wamazwana uhlela amazwana agciniwe ngekhodi yekhasini, ukuze othisha bakwazi ukuphinda basebenzise impendulo kuminikelo ehlukene yekhasini elifanayo (isib. isimesta ngesimesta). Lesi silungiselelo silawula indlela amakhodi ekhasini akhishwa ngayo emagameni amafushane ekhasini le-Moodle. Faka iphethini ye-regex ye-PHP elingana nengxenye yekhodi yamagama akho amafushane (isib. <code>/[A-Z]{3}\\d{4}/</code> ingakhipha <strong>THE2201</strong> egameni elifushane elifana no-<em>THE2201-2026-S1</em>). Shiya kungenalutho ukuze igama elifushane liphelele lisetshenziswe njengekhodi yekhasini.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Vumela ifomu yokubika ukungathembeki kwezemfundo';
$string['setting_enable_report_form_desc'] = 'Uma kuvulelwe, inkinobho ethi "Bika ukungathembeki kwezemfundo" ivela ezingxenyeni zokweba imibhalo, exhumana nefomu yokubika yangaphandle.';
$string['setting_report_form_url'] = 'Isifanekiso se-URL sefomu yokubika';
$string['setting_report_form_url_desc'] = 'I-URL yefomu yokubika ukungathembeki kwezemfundo. Izindawo ezisekelwayo: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Lezi zishintshwa ngesikhathi sokusebenza ngamanani a-URL-encoded. Ngamafomu e-Microsoft Forms, sebenzisa isici esithi "Get Pre-filled URL" ukuthola amagama amapharamitha.';
$string['report_impropriety'] = 'Bika ukungathembeki kwezemfundo';

// Comment library v2.
$string['clib_title'] = 'Umtapo Wamazwana';
$string['clib_all'] = 'Konke';
$string['clib_quick_add'] = 'Engeza amazwana ngokushesha...';
$string['clib_manage'] = 'Phatha Umtapo';
$string['clib_no_comments'] = 'Awekho amazwana okwamanje.';
$string['clib_insert'] = 'Faka';
$string['clib_copied'] = 'Amazwana akopishwe kubhodi lokunamathisela';
$string['clib_my_library'] = 'Umtapo Wami';
$string['clib_shared_library'] = 'Umtapo Ohlanganyele';
$string['clib_new_comment'] = 'Amazwana amasha';
$string['clib_edit_comment'] = 'Hlela amazwana';
$string['clib_delete_comment'] = 'Susa amazwana';
$string['clib_confirm_delete'] = 'Uqinisekile ukuthi ufuna ukususa la mazwana?';
$string['clib_share'] = 'Yabelana';
$string['clib_unshare'] = 'Yeka ukwabelana';
$string['clib_import'] = 'Ngenisa';
$string['clib_imported'] = 'Amazwana angenisiwe emtapweni wakho';
$string['clib_copy_to_course'] = 'Kopishela ekhasini';
$string['clib_all_courses'] = 'Wonke amakhasini';
$string['clib_tags'] = 'Amathegi';
$string['clib_manage_tags'] = 'Phatha amathegi';
$string['clib_new_tag'] = 'Ithegi entsha';
$string['clib_edit_tag'] = 'Hlela ithegi';
$string['clib_delete_tag'] = 'Susa ithegi';
$string['clib_confirm_delete_tag'] = 'Uqinisekile ukuthi ufuna ukususa le thegi? Izosuswa kuwo wonke amazwana.';
$string['clib_system_tag'] = 'Okuzenzakalelayo kwesistimu';
$string['clib_shared_by'] = 'Kwabelanwe ngu-{$a}';
$string['clib_no_shared'] = 'Awekho amazwana ahlanganyele atholakalayo.';
$string['clib_picker_freetext'] = 'Noma bhala okwakho...';
$string['clib_picker_loading'] = 'Kulayishwa amazwana...';
$string['clib_offline_mode'] = 'Kuboniswa amazwana agciniwe — ukuhlela akutholakali ngaphandle kwenethiwekhi.';
$string['unifiedgrader:sharecomments'] = 'Yabelana ngamazwana emtapweni nabanye othisha';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Okufakiwe emtapweni wamazwana kuMklasisi Ohlanganisiwe.';
$string['privacy:metadata:clib:userid'] = 'Uthisha ongumnikazi wamazwana.';
$string['privacy:metadata:clib:coursecode'] = 'Ikhodi yekhasini amazwana ahlobene nayo.';
$string['privacy:metadata:clib:content'] = 'Okuqukethwe kwamazwana.';
$string['privacy:metadata:cltag'] = 'Amathegi omtapo wamazwana kuMklasisi Ohlanganisiwe.';
$string['privacy:metadata:cltag:userid'] = 'Uthisha ongumnikazi wethegi.';
$string['privacy:metadata:cltag:name'] = 'Igama lethegi.';

// Penalties.
$string['penalties'] = 'Izijeziso';
$string['penalty_late'] = 'Ukuthumela sekwedlule isikhathi';
$string['penalty_late_days'] = '{$a} usuku/izinsuku sekwedlule';
$string['penalty_late_auto'] = 'Kubalwa ngokuzenzakalelayo ngokwemithetho yezijeziso';
$string['penalty_wordcount'] = 'Inani lamagama';
$string['penalty_other'] = 'Okunye';
$string['penalty_custom'] = 'Okwenziwe ngokwezifiso';
$string['penalty_label_placeholder'] = 'Isiqephu (okuningi izinhlamvu ezingu-15)';
$string['penalty_active'] = 'Izijeziso ezisebenzayo';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['penalty_late_label'] = 'Kuphuzile';
$string['penalty_late_applied'] = 'Inhlawulo yokuphuza ka-{$a}% isebenzile';
$string['late_days'] = 'izinsuku ezingu-{$a}';
$string['late_day'] = 'usuku olungu-{$a}';
$string['late_hours'] = 'amahora angu-{$a}';
$string['late_hour'] = 'ihora elingu-{$a}';
$string['late_mins'] = 'imizuzu engu-{$a}';
$string['late_min'] = 'umzuzu ongu-{$a}';
$string['late_lessthanmin'] = '< umzuzu owodwa';
$string['finalgradeafterpenalties'] = 'Amamaki okugcina ngemuva kwezijeziso:';
$string['cannotdeleteautopenalty'] = 'Izijeziso zokushiywa isikhathi zibalwa ngokuzenzakalelayo futhi azikwazi ukususwa.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Landa i-PDF yempendulo';
$string['feedback_summary_overall_feedback'] = 'Impendulo Ephelele';
$string['feedback_summary_graded_on'] = 'Kuklasisiwe ngo-{$a}';
$string['feedback_summary_generated_by'] = 'Kwakhiwe yuMklasisi Ohlanganisiwe';
$string['feedback_summary_media_note'] = 'Okuqukethwe kwemidiya kutholakala ekubukeni impendulo ku-inthanethi.';
$string['feedback_summary_no_grade'] = 'Akutholakali';
$string['feedback_summary_remark'] = 'Amazwana Kathisha';
$string['feedback_summary_total'] = 'Isamba';
$string['levels'] = 'Amazinga';
$string['error_gs_not_configured'] = 'I-GhostScript ayilungiselelwanga kule seva ye-Moodle. Umphathi kumele ahlele indlela ye-GhostScript ku-Site administration > Plugins > Activity modules > Assignment > Feedback > Annotate PDF.';
$string['error_pdf_combine_failed'] = 'Kuhlulekile ukuhlanganisa amafayela e-PDF: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Izijeziso zamamaki ezibekwe othisha kuMklasisi Ohlanganisiwe.';
$string['privacy:metadata:penalty:userid'] = 'Umfundi isijeziso esibekwe kuye.';
$string['privacy:metadata:penalty:authorid'] = 'Uthisha obeke isijeziso.';
$string['privacy:metadata:penalty:category'] = 'Isigaba sesijeziso (inani lamagama noma okunye).';
$string['privacy:metadata:penalty:label'] = 'Isiqephu esiyisifiso sesijeziso.';
$string['privacy:metadata:penalty:percentage'] = 'Iphesenti lesijeziso.';
$string['privacy:metadata:fext'] = 'Izikhathi ezengeziwe zosuku lokugcina lweforamu ezinikezwe othisha kuMklasisi Ohlanganisiwe.';
$string['privacy:metadata:fext:userid'] = 'Umfundi onikezwe isikhathi esengeziwe.';
$string['privacy:metadata:fext:authorid'] = 'Uthisha onikeze isikhathi esengeziwe.';
$string['privacy:metadata:fext:extensionduedate'] = 'Usuku lokugcina olwandiswayo.';
$string['privacy:metadata:qfb'] = 'Impendulo yequiz ngomzamo egcinwe yuMklasisi Ohlanganisiwe.';
$string['privacy:metadata:qfb:userid'] = 'Umfundi impendulo engeyakhe.';
$string['privacy:metadata:qfb:grader'] = 'Uthisha onikeze impendulo.';
$string['privacy:metadata:qfb:feedback'] = 'Umbhalo wempendulo.';
$string['privacy:metadata:qfb:attemptnumber'] = 'Inombolo yomzamo wequiz.';
$string['privacy_forum_extensions'] = 'Izikhathi ezengeziwe zeforamu';
$string['privacy_quiz_feedback'] = 'Impendulo yequiz';

// Offline cache and save status.
$string['allchangessaved'] = 'Zonke izinguquko zigciniwe';
$string['editing'] = 'Kuyahlela...';
$string['offlinesavedlocally'] = 'Ayixhuniwe — kugciniwe endaweni yakho';
$string['connectionlost'] = 'Uxhumano lulahlekile — umsebenzi wakho ugciniwe endaweni yakho futhi uzovumelaniswa uma uxhumana futhi.';
$string['recoveredunsavedchanges'] = 'Kubuyiswe izinguquko ezingagcinwanga eseshini yakho edlule.';
$string['restore'] = 'Buyisela';
$string['discard'] = 'Lahla';
$string['mark_as_graded'] = 'Phawula njengokuklasisiwe';
