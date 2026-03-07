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
 * Language strings for local_unifiedgrader (Xhosa / isiXhosa).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Umklasisi Omanyeneyo';
$string['grading_interface'] = 'Umklasisi Omanyeneyo';
$string['nopermission'] = 'Awunayo imvume yokusebenzisa uMklasisi Omanyeneyo.';
$string['invalidactivitytype'] = 'Olu hlobo lomsebenzi aluxhaswanga nguMklasisi Omanyeneyo.';
$string['invalidmodule'] = 'Imodyuli yomsebenzi engasebenziyo.';
$string['viewfeedback'] = 'Jonga impendulo';

// Attempts.
$string['attempt'] = 'Umzamo';

// Capabilities.
$string['unifiedgrader:grade'] = 'Sebenzisa uMklasisi Omanyeneyo ukuklasisa';
$string['unifiedgrader:viewall'] = 'Jonga bonke abafundi kuMklasisi Omanyeneyo';
$string['unifiedgrader:viewnotes'] = 'Jonga amanqaku abucala katitshala';
$string['unifiedgrader:managenotes'] = 'Yila kwaye uhlele amanqaku abucala katitshala';
$string['unifiedgrader:viewfeedback'] = 'Jonga impendulo enobhalo kuMklasisi Omanyeneyo';

// Settings.
$string['setting_enable_assign'] = 'Vumela kwiiAkhawunti zokuNikwa uMsebenzi';
$string['setting_enable_assign_desc'] = 'Vumela uMklasisi Omanyeneyo ukuba asetyenziswe kwimisebenzi yokunikwa umsebenzi.';
$string['setting_enable_forum'] = 'Vumela kwiiForamu';
$string['setting_enable_forum_desc'] = 'Vumela uMklasisi Omanyeneyo ukuba asetyenziswe kwimisebenzi yeeforamu.';
$string['setting_enable_quiz'] = 'Vumela kwiiQuiz';
$string['setting_enable_quiz_desc'] = 'Vumela uMklasisi Omanyeneyo ukuba asetyenziswe kwimisebenzi yeequiz.';
$string['setting_enable_quiz_post_grades'] = 'Vumela ukuthumela amanqaku kwii-quiz';
$string['setting_enable_quiz_post_grades_desc'] = 'Ukubonakala kwamanqaku equiz ngokuqhelekileyo kulawulwa ziinketho zokujonga kwakhona zequiz. Xa kuvulelwe, iqhosha elithi "Thumela amanqaku" loMklasisi Omanyeneyo liza kuhlaziya iinketho zokujonga kwakhona zequiz ngokwekhompyutha ukubonisa okanye ukufihla amanqaku. Xa kungavulelwanga (okungagqibekanga), iqhosha lokuthumela amanqaku liyafihlwa kwii-quiz.';
$string['setting_allow_manual_override'] = 'Vumela ukutshintsha amanqaku ngesandla';
$string['setting_allow_manual_override_desc'] = 'Xa kuvulelwe, ootitshala bangatayipha amanqaku ngesandla nangona irubhriki okanye isikhokelo sokuklasisa silungiselelwe. Xa kungavulelwanga, amanqaku abalwa ngokupheleleyo ngemiqathango yerubhriki okanye yesikhokelo sokuklasisa.';

// Grading interface.
$string['grade'] = 'Amanqaku';
$string['savegrade'] = 'Gcina amanqaku';
$string['savefeedback'] = 'Gcina impendulo';
$string['savinggrade'] = 'Kugcinwa amanqaku...';
$string['gradesaved'] = 'Amanqaku agciniwe';
$string['error_saving'] = 'Imposiso ekugcineni amanqaku.';
$string['error_network'] = 'Ayikwazi ukuqhagamshelana neseva. Nceda ujonge uqhagamshelwano lwakho uze uzame kwakhona.';
$string['error_offline_comments'] = 'Awunakongeza amagqabaza ngelixa ungaqhagamshelwanga.';
$string['feedback'] = 'Impendulo';
$string['overall_feedback'] = 'Impendulo Epheleleyo';
$string['feedback_saved'] = 'Impendulo (igciniwe)';
$string['edit_feedback'] = 'Hlela';
$string['delete_feedback'] = 'Cima';
$string['confirm_delete_feedback'] = 'Uqinisekile ukuba ufuna ukucima le mpendulo? Amanqaku aya kugcinwa.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Nweba';

// Submissions.
$string['submission'] = 'Ukuthunyelwe';
$string['nosubmission'] = 'Akukho kuthunyelweyo';
$string['previewpanel'] = 'Ukujonga kwangaphambili okuthunyelweyo';
$string['markingpanel'] = 'Iphaneli yokuklasisa';
$string['onlinetext'] = 'Umbhalo wekhu-intanethi';
$string['submittedfiles'] = 'Iifayili ezithunyelweyo';
$string['viewfile'] = 'Jonga ifayili';

// Participants.
$string['participants'] = 'Abathathi-nxaxheba';
$string['search'] = 'Khangela abathathi-nxaxheba...';
$string['sortby'] = 'Hlela ngoku';
$string['sortby_fullname'] = 'Igama elipheleleyo';
$string['sortby_submittedat'] = 'Umhla wokuthumela';
$string['sortby_status'] = 'Isimo';
$string['filter_all'] = 'Bonke abathathi-nxaxheba';
$string['filter_submitted'] = 'Okuthunyelweyo';
$string['filter_needsgrading'] = 'Okungakaklasiswayo';
$string['filter_notsubmitted'] = 'Okungathunyelwanga';
$string['filter_graded'] = 'Okuklasisweyo';
$string['filter_late'] = 'Okushiywe lixesha';
$string['filter_allgroups'] = 'Onke amaqela';
$string['studentcount'] = '{$a->current} kwe-{$a->total}';

// Statuses.
$string['status_draft'] = 'Isicwangciso sokuqala';
$string['status_submitted'] = 'Kuthunyelwe';
$string['status_graded'] = 'Kuklasisiwe';
$string['status_nosubmission'] = 'Akukho kuthunyelweyo';
$string['status_needsgrading'] = 'Kufuna ukuhlolwa';
$string['status_new'] = 'Akuthunyelwanga';
$string['status_late'] = 'Kushiywe lixesha: {$a}';

// Teacher notes.
$string['notes'] = 'Amanqaku katitshala';
$string['notes_desc'] = 'Amanqaku abucala abonwa ngootitshala nabahloli kuphela.';
$string['savenote'] = 'Gcina inqaku';
$string['deletenote'] = 'Cima';
$string['addnote'] = 'Yongeza inqaku';
$string['nonotes'] = 'Akukho manqaku okwangoku.';
$string['confirmdelete_note'] = 'Uqinisekile ukuba ufuna ukucima eli nqaku?';

// Comment library.
$string['commentlibrary'] = 'Ithala lamagqabaza';
$string['savecomment'] = 'Gcina ethaleni';
$string['insertcomment'] = 'Faka';
$string['deletecomment'] = 'Susa';
$string['newcomment'] = 'Igqabaza elitsha...';
$string['nocomments'] = 'Akukho magqabaza agciniweyo.';

// UI.
$string['loading'] = 'Kuyalayisha...';
$string['saving'] = 'Kuyagcina...';
$string['saved'] = 'Kugciniwe';
$string['previousstudent'] = 'Umfundi ongaphambili';
$string['nextstudent'] = 'Umfundi olandelayo';
$string['expandfilters'] = 'Bonisa izihluzi';
$string['collapsefilters'] = 'Fihla izihluzi';
$string['backtocourse'] = 'Buyela kwikosi';
$string['rubric'] = 'Irubhriki';
$string['markingguide'] = 'Isikhokelo sokuklasisa';
$string['criterion'] = 'Umqathango';
$string['score'] = 'Amanqaku';
$string['remark'] = 'Amagqabaza';
$string['total'] = 'Iyonke: {$a}';
$string['viewallsubmissions'] = 'Jonga konke okuthunyelweyo';
$string['layout_both'] = 'Imboniselo ehahlulweyo';
$string['layout_preview'] = 'Ukujonga kuphela';
$string['layout_grade'] = 'Ukuhlola kuphela';
$string['manualquestions'] = 'Imibuzo yesandla';
$string['response'] = 'Impendulo';
$string['teachercomment'] = 'Igqabaza likatitshala';

// Submission comments.
$string['submissioncomments'] = 'Amagqabaza okuthunyelweyo';
$string['nocommentsyet'] = 'Akukho magqabaza okwangoku';
$string['addcomment'] = 'Yongeza igqabaza...';
$string['postcomment'] = 'Thumela';
$string['deletesubmissioncomment'] = 'Cima igqabaza';

// Feedback files.
$string['feedbackfiles'] = 'Iifayili zempendulo';

// Plagiarism.
$string['plagiarism'] = 'Ukweba imibhalo';
$string['plagiarism_noresults'] = 'Akukho ziphumo zokweba imibhalo ezifumanekayo.';
$string['plagiarism_pending'] = 'Ukuhlolwa kokweba imibhalo kuyaqhubeka';
$string['plagiarism_error'] = 'Ukuhlolwa kokweba imibhalo kusilele';

// Student feedback view.
$string['assessment_criteria'] = 'Imiqathango yovavanyo';
$string['teacher_remark'] = 'Impendulo katitshala';
$string['view_feedback'] = 'Jonga impendulo';
$string['view_annotated_feedback'] = 'Jonga Impendulo Enobhalo';
$string['feedback_not_available'] = 'Impendulo yakho ayikafumaneki okwangoku. Nceda ubuye ujonge emva kokuba umsebenzi wakho uhlolwe kwaye wakhutshwa.';
$string['no_annotated_files'] = 'Akukho iifayili ze-PDF ezinobhalo zokuthunyelwa kwakho.';
$string['feedback_banner_default'] = 'Utitshala wakho unikezele impendulo ngokuthunyelwa kwakho.';

// Document conversion.
$string['conversion_failed'] = 'Le fayili ayikwazanga ukuguqulwa ibe yi-PDF ukuze ijongwe kwangaphambili.';
$string['converting_file'] = 'Kuguqulwa uxwebhu lube yi-PDF...';
$string['conversion_timeout'] = 'Ukuguqulwa kwexwebhu kuthatha ixesha elide kakhulu. Nceda uzame kwakhona kamva.';
$string['download_annotated_pdf'] = 'Khuphela i-PDF enobhalo';
$string['download_original_submission'] = 'Khuphela okuthunyelwe kwantlandlolo: {$a}';

// Privacy.
$string['privacy:metadata:notes'] = 'Amanqaku abucala katitshala agcinwe ngomfundi ngamnye ngomsebenzi ngamnye kuMklasisi Omanyeneyo.';
$string['privacy:metadata:notes:cmid'] = 'I-ID yemodyuli yekosi inqaku eliphathelele kuyo.';
$string['privacy:metadata:notes:userid'] = 'Umfundi inqaku eliphathelele kuye.';
$string['privacy:metadata:notes:authorid'] = 'Utitshala owabhala inqaku.';
$string['privacy:metadata:notes:content'] = 'Umxholo wenqaku.';
$string['privacy:metadata:comments'] = 'Amagqabaza asetshenziswa kwakhona kwithala lamagqabaza kuMklasisi Omanyeneyo.';
$string['privacy:metadata:comments:userid'] = 'Utitshala ongumnini wegqabaza.';
$string['privacy:metadata:comments:content'] = 'Umxholo wegqabaza.';
$string['privacy:metadata:preferences'] = 'Izikhethwa zomsebenzisi woMklasisi Omanyeneyo.';
$string['privacy:metadata:preferences:userid'] = 'Umsebenzisi izikhethwa ezizezakhe.';
$string['privacy:metadata:preferences:data'] = 'Idatha yezikhethwa eyi-JSON.';
$string['privacy:metadata:annotations'] = 'Ubhalo lwexwebhu olugcinwe kuMklasisi Omanyeneyo.';
$string['privacy:metadata:annotations:cmid'] = 'I-ID yemodyuli yekosi ubhalo oluphathelele kuyo.';
$string['privacy:metadata:annotations:userid'] = 'Umfundi okuthunyelwe kwakhe kunobhalo.';
$string['privacy:metadata:annotations:authorid'] = 'Utitshala owenze ubhalo.';
$string['privacy:metadata:annotations:data'] = 'Idatha yobhalo (Fabric.js JSON).';
$string['annotations'] = 'Ubhalo';

// PDF viewer.
$string['pdf_prevpage'] = 'Iphepha elingaphambili';
$string['pdf_nextpage'] = 'Iphepha elilandelayo';
$string['pdf_zoomin'] = 'Sondeza';
$string['pdf_zoomout'] = 'Beka kude';
$string['pdf_zoomfit'] = 'Lungisa ububanzi';
$string['pdf_search'] = 'Khangela kwixwebhu';

// Annotation tools.
$string['annotate_tools'] = 'Izixhobo zobhalo';
$string['annotate_select'] = 'Khetha';
$string['annotate_textselect'] = 'Khetha umbhalo';
$string['annotate_comment'] = 'Igqabaza';
$string['annotate_highlight'] = 'Qaqambisa';
$string['annotate_pen'] = 'Ipeni';
$string['annotate_pen_fine'] = 'Ecoliweyo';
$string['annotate_pen_medium'] = 'Ephakathi';
$string['annotate_pen_thick'] = 'Eqaqambileyo';
$string['annotate_stamps'] = 'Izitampu';
$string['annotate_stamp_check'] = 'Isitampu sokuphawula';
$string['annotate_stamp_cross'] = 'Isitampu somnqamlezo';
$string['annotate_stamp_question'] = 'Isitampu sombuzo';
$string['annotate_red'] = 'Okubomvu';
$string['annotate_yellow'] = 'Okutyheli';
$string['annotate_green'] = 'Okuluhlaza';
$string['annotate_blue'] = 'Okuzuba';
$string['annotate_black'] = 'Okumnyama';
$string['annotate_shape'] = 'Imilo';
$string['annotate_shape_rect'] = 'Uxande';
$string['annotate_shape_circle'] = 'Isangqa';
$string['annotate_shape_arrow'] = 'Utolo';
$string['annotate_shape_line'] = 'Umgca';
$string['annotate_undo'] = 'Buyisela umva';
$string['annotate_redo'] = 'Phinda';
$string['annotate_delete'] = 'Cima okukhethiweyo';
$string['annotate_clearall'] = 'Sula konke';
$string['annotate_clear_confirm'] = 'Uqinisekile ukuba ufuna ukusula bonke ubhalo kweli phepha? Oku akunakubuyiselwa umva.';

// Document info.
$string['docinfo'] = 'Ulwazi lwexwebhu';
$string['docinfo_filename'] = 'Igama lefayili';
$string['docinfo_filesize'] = 'Ubungakanani befayili';
$string['docinfo_pages'] = 'Amaphepha';
$string['docinfo_wordcount'] = 'Inani lamagama';
$string['docinfo_author'] = 'Umbhali';
$string['docinfo_creator'] = 'Umenzi';
$string['docinfo_created'] = 'Yenziwe';
$string['docinfo_modified'] = 'Iguqulwe';
$string['docinfo_calculating'] = 'Kubalwa...';

// Forum feedback view.
$string['view_forum_feedback'] = 'Jonga Impendulo Yeforamu';
$string['forum_your_posts'] = 'Izithunyelwe zakho zeforamu';
$string['forum_no_posts'] = 'Awukathumi nto kule foramu.';
$string['forum_feedback_banner'] = 'Utitshala wakho uhlole ukuthatha kwakho inxaxheba kule foramu.';
$string['forum_wordcount'] = '{$a} amagama';
$string['forum_posts_pill'] = 'Izithunyelwe';
$string['submission_content_pill'] = 'Okuthunyelweyo';
$string['forum_tab_posts'] = 'Izithunyelwe';
$string['forum_tab_files'] = 'Iifayili Ezinobhalo';
$string['view_quiz_feedback'] = 'Jonga Impendulo Yequiz';
$string['quiz_feedback_banner'] = 'Utitshala wakho unikezele impendulo ngequiz yakho.';
$string['quiz_your_attempt'] = 'Umzamo Wakho';
$string['quiz_no_attempt'] = 'Awukagqibi mizamo kule quiz.';
$string['quiz_select_attempt'] = 'Khetha umzamo';
$string['select_attempt'] = 'Khetha umzamo';
$string['attempt_label'] = 'Umzamo {$a}';

// Post grades.
$string['grades_posted'] = 'Amanqaku athunyelwe';
$string['grades_hidden'] = 'Amanqaku afihliwe';
$string['post_grades'] = 'Thumela amanqaku';
$string['unpost_grades'] = 'Buyisela amanqaku';
$string['confirm_post_grades'] = 'Thumela onke amanqaku alo msebenzi? Abafundi baya kukwazi ukubona amanqaku nempendulo yabo.';
$string['confirm_unpost_grades'] = 'Buyisela onke amanqaku alo msebenzi? Abafundi abasayi kukwazi ukubona amanqaku nempendulo yabo.';
$string['schedule_post'] = 'Thumela ngomhla';
$string['schedule_post_btn'] = 'Cwangcisa';
$string['grades_scheduled'] = 'Kuza kuthunyelwa {$a}';
$string['schedule_must_be_future'] = 'Umhla ocwangcisiweyo kufuneka ube ngowexesha elizayo.';
$string['quiz_post_grades_disabled'] = 'Ukuthumela amanqaku akufumaneki kwii-quiz. Ukubonakala kwamanqaku kulawulwa ziinketho zokujonga kwakhona zequiz.';
$string['quiz_post_grades_no_schedule'] = 'Ukucwangcisa akufumaneki kwii-quiz. Sebenzisa ukuThumela okanye ukuBuyisela endaweni yoko.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Buyisela kwisicwangciso sokuqala';
$string['action_remove_submission'] = 'Susa okuthunyelweyo';
$string['action_lock'] = 'Thintela utshintsho lokuthunyelweyo';
$string['action_unlock'] = 'Vumela utshintsho lokuthunyelweyo';
$string['action_edit_submission'] = 'Hlela okuthunyelweyo';
$string['action_grant_extension'] = 'Nika ixesha elongeziweyo';
$string['action_edit_extension'] = 'Hlela ixesha elongeziweyo';
$string['action_submit_for_grading'] = 'Thumela ukuze kuhlolwe';
$string['confirm_revert_to_draft'] = 'Uqinisekile ukuba ufuna ukubuyisela oku kuthunyelweyo kwisimo sesicwangciso sokuqala?';
$string['confirm_remove_submission'] = 'Uqinisekile ukuba ufuna ukususa oku kuthunyelweyo? Oku akunakubuyiselwa umva.';
$string['confirm_lock_submission'] = 'Thintela lo mfundi ekwenzeni utshintsho kokuthunyelweyo?';
$string['confirm_unlock_submission'] = 'Vumela lo mfundi ukuba enze utshintsho kokuthunyelweyo?';
$string['confirm_submit_for_grading'] = 'Thumela esi sicwangciso sokuqala egameni lomfundi?';
$string['invalidaction'] = 'Isenzo sokuthumela esingasebenziyo.';

// Override actions.
$string['override'] = 'Ukutshintsha';
$string['action_add_override'] = 'Yongeza ukutshintsha';
$string['action_edit_override'] = 'Hlela ukutshintsha';
$string['action_delete_override'] = 'Cima ukutshintsha';
$string['confirm_delete_override'] = 'Uqinisekile ukuba ufuna ukucima oku kutshintsha komsebenzisi?';
$string['override_saved'] = 'Ukutshintsha kugcinwe ngempumelelo.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Cima ixesha elongeziweyo';
$string['confirm_delete_extension'] = 'Uqinisekile ukuba ufuna ukucima eli xesha elongeziweyo lomhla wokugqibela?';
$string['quiz_extension_original_duedate'] = 'Umhla wokugqibela wantlandlolo';
$string['quiz_extension_current_extension'] = 'Ixesha elongeziweyo langoku';
$string['quiz_extension_new_duedate'] = 'Umhla wokugqibela elongeziweyo';
$string['quiz_extension_must_be_after_duedate'] = 'Umhla wongezelo kufuneka ube emva komhla wokugqibela wangoku.';
$string['quiz_extension_plugin_missing'] = 'I-plugin ye-quizaccess_duedate iyafuneka ukwandisa ixesha lequiz kodwa ayifakwanga.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Umhla wokugqibela weforamu';
$string['forum_extension_current_extension'] = 'Ixesha elongeziweyo langoku';
$string['forum_extension_new_duedate'] = 'Umhla wokugqibela elongeziweyo';
$string['forum_extension_must_be_after_duedate'] = 'Umhla wongezelo kufuneka ube emva komhla wokugqibela weforamu.';

// Student profile popout.
$string['profile_view_full'] = 'Jonga iprofayili epheleleyo';
$string['profile_login_as'] = 'Ngena njenge';
$string['profile_no_email'] = 'Akukho imeyili efumanekayo';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'I-regex yekhowudi yekosi';
$string['setting_coursecode_regex_desc'] = 'Ithala Lamagqabaza lihlela amagqabaza agciniweyo ngekhowudi yekosi, ukuze ootitshala bakwazi ukuphinda basebenzise impendulo kuminikelo eyahlukeneyo yekosi efanayo (umz. isimesta ngesimesta). Esi silungiselelo silawula indlela iikhowudi zekosi ezikhutshwa ngayo kumagama amafutshane ekosi ye-Moodle. Faka iphatheni ye-regex ye-PHP ehambelana nengxenye yekhowudi yamagama akho amafutshane (umz. <code>/[A-Z]{3}\\d{4}/</code> ingakhupha <strong>THE2201</strong> kwigama elifutshane elifana no-<em>THE2201-2026-S1</em>). Shiya ingenanto ukuze igama elifutshane lipheleleyo lisetyenziswe njengekhowudi yekosi.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Vumela ifomu yokuchaza ukungathembeki kwezifundo';
$string['setting_enable_report_form_desc'] = 'Xa kuvulelwe, iqhosha elithi "Chaza ukungathembeki kwezifundo" livela kumacandelo okweba imibhalo, liqhagamshelana nefomu yokuchaza yangaphandle.';
$string['setting_report_form_url'] = 'Isifanekiso se-URL sefomu yokuchaza';
$string['setting_report_form_url_desc'] = 'I-URL yefomu yokuchaza ukungathembeki kwezifundo. Iindawo ezixhaswayo: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. Ezi zitshintshwa ngexesha lokusebenza ngamaxabiso a-URL-encoded. Kwiifomu ze-Microsoft Forms, sebenzisa isici esithi "Get Pre-filled URL" ukufumana amagama eeparamitha.';
$string['report_impropriety'] = 'Chaza ukungathembeki kwezifundo';

// Comment library v2.
$string['clib_title'] = 'Ithala Lamagqabaza';
$string['clib_all'] = 'Konke';
$string['clib_quick_add'] = 'Yongeza igqabaza ngokukhawuleza...';
$string['clib_manage'] = 'Lawula Ithala';
$string['clib_no_comments'] = 'Akukho magqabaza okwangoku.';
$string['clib_insert'] = 'Faka';
$string['clib_copied'] = 'Igqabaza likhutshelwe kwibhodi yokunamathisela';
$string['clib_my_library'] = 'Ithala Lam';
$string['clib_shared_library'] = 'Ithala Elabelwano';
$string['clib_new_comment'] = 'Igqabaza elitsha';
$string['clib_edit_comment'] = 'Hlela igqabaza';
$string['clib_delete_comment'] = 'Cima igqabaza';
$string['clib_confirm_delete'] = 'Uqinisekile ukuba ufuna ukucima eli gqabaza?';
$string['clib_share'] = 'Yabelana';
$string['clib_unshare'] = 'Yeka ukwabelana';
$string['clib_import'] = 'Ngenisa';
$string['clib_imported'] = 'Igqabaza lingeniswe kwithala lakho';
$string['clib_copy_to_course'] = 'Khuphela kwikosi';
$string['clib_all_courses'] = 'Zonke iikosi';
$string['clib_tags'] = 'Iithegi';
$string['clib_manage_tags'] = 'Lawula iithegi';
$string['clib_new_tag'] = 'Ithegi entsha';
$string['clib_edit_tag'] = 'Hlela ithegi';
$string['clib_delete_tag'] = 'Cima ithegi';
$string['clib_confirm_delete_tag'] = 'Uqinisekile ukuba ufuna ukucima le thegi? Iya kususwa kuwo onke amagqabaza.';
$string['clib_system_tag'] = 'Okungagqibekanga kwesistim';
$string['clib_shared_by'] = 'Kwabelwano ngu-{$a}';
$string['clib_no_shared'] = 'Akukho magqabaza abelwano afumanekayo.';
$string['clib_picker_freetext'] = 'Okanye bhala okwakho...';
$string['clib_picker_loading'] = 'Kulayishwa amagqabaza...';
$string['clib_offline_mode'] = 'Kuboniswa amagqabaza agciniweyo — ukuhlela akufumaneki ngaphandle kwenethiwekhi.';
$string['unifiedgrader:sharecomments'] = 'Yabelana ngamagqabaza ethaleni nabanye ootitshala';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Amagqabaza kwithala lamagqabaza kuMklasisi Omanyeneyo.';
$string['privacy:metadata:clib:userid'] = 'Utitshala ongumnini wegqabaza.';
$string['privacy:metadata:clib:coursecode'] = 'Ikhowudi yekosi igqabaza eliphathelele kuyo.';
$string['privacy:metadata:clib:content'] = 'Umxholo wegqabaza.';
$string['privacy:metadata:cltag'] = 'Iithegi zethala lamagqabaza kuMklasisi Omanyeneyo.';
$string['privacy:metadata:cltag:userid'] = 'Utitshala ongumnini wethegi.';
$string['privacy:metadata:cltag:name'] = 'Igama lethegi.';

// Penalties.
$string['penalties'] = 'Izohlwayo';
$string['penalty_late'] = 'Ukuthumela kushiywe lixesha';
$string['penalty_late_days'] = '{$a} usuku/iintsuku kushiywe lixesha';
$string['penalty_late_auto'] = 'Kubalwa ngokuzenzekelayo ngokwemithetho yezohlwayo';
$string['penalty_wordcount'] = 'Inani lamagama';
$string['penalty_other'] = 'Okunye';
$string['penalty_custom'] = 'Okwenziwe ngokunqwenelwa';
$string['penalty_label_placeholder'] = 'Ileyibheli (ubuncinane oonobumba abayi-15)';
$string['penalty_active'] = 'Izohlwayo ezisebenzayo';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['penalty_late_label'] = 'Ukufika kade';
$string['penalty_late_applied'] = 'Isohlwayo sokufika kade se-{$a}% sisetyenzisiwe';
$string['late_days'] = 'iintsuku ezingu-{$a}';
$string['late_day'] = 'usuku olungu-{$a}';
$string['late_hours'] = 'iiyure ezingu-{$a}';
$string['late_hour'] = 'iyure engu-{$a}';
$string['late_mins'] = 'imizuzu engu-{$a}';
$string['late_min'] = 'umzuzu ongu-{$a}';
$string['late_lessthanmin'] = '< umzuzu omnye';
$string['finalgradeafterpenalties'] = 'Amanqaku okugqibela emva kwezohlwayo:';
$string['cannotdeleteautopenalty'] = 'Izohlwayo zokushiywa lixesha zibalwa ngokuzenzekelayo kwaye azinakucinywa.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Khuphela i-PDF yempendulo';
$string['feedback_summary_overall_feedback'] = 'Impendulo Epheleleyo';
$string['feedback_summary_graded_on'] = 'Kuklasisiwe ngo-{$a}';
$string['feedback_summary_generated_by'] = 'Kwenziwe nguMklasisi Omanyeneyo';
$string['feedback_summary_media_note'] = 'Umxholo wemidiya ufumaneka ekujongeni impendulo kwi-intanethi.';
$string['feedback_summary_no_grade'] = 'Akufumaneki';
$string['feedback_summary_remark'] = 'Igqabaza Likatitshala';
$string['feedback_summary_total'] = 'Iyonke';
$string['levels'] = 'Amanqanaba';
$string['error_gs_not_configured'] = 'I-GhostScript ayilungiselelwanga kule seva ye-Moodle. Umlawuli kufuneka ahlele indlela ye-GhostScript ku-Site administration > Plugins > Activity modules > Assignment > Feedback > Annotate PDF.';
$string['error_pdf_combine_failed'] = 'Kusilele ukudibanisa iifayili ze-PDF: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Izohlwayo zamanqaku ezibekwe ngootitshala kuMklasisi Omanyeneyo.';
$string['privacy:metadata:penalty:userid'] = 'Umfundi isohlwayo esibekwe kuye.';
$string['privacy:metadata:penalty:authorid'] = 'Utitshala obeke isohlwayo.';
$string['privacy:metadata:penalty:category'] = 'Udidi lwesohlwayo (inani lamagama okanye okunye).';
$string['privacy:metadata:penalty:label'] = 'Ileyibheli eyenziwe ngokunqwenelwa yesohlwayo.';
$string['privacy:metadata:penalty:percentage'] = 'Ipesenti yesohlwayo.';
$string['privacy:metadata:fext'] = 'Amaxesha ongezelelweyo omhla wokugqibela weforamu anikwe ngootitshala kuMklasisi Omanyeneyo.';
$string['privacy:metadata:fext:userid'] = 'Umfundi onikwe ixesha elongeziweyo.';
$string['privacy:metadata:fext:authorid'] = 'Utitshala onikeze ixesha elongeziweyo.';
$string['privacy:metadata:fext:extensionduedate'] = 'Umhla wokugqibela owongezelelweyo.';
$string['privacy:metadata:qfb'] = 'Impendulo yequiz ngomzamo egcinwe nguMklasisi Omanyeneyo.';
$string['privacy:metadata:qfb:userid'] = 'Umfundi impendulo eyeyakhe.';
$string['privacy:metadata:qfb:grader'] = 'Utitshala onikezele impendulo.';
$string['privacy:metadata:qfb:feedback'] = 'Umbhalo wempendulo.';
$string['privacy:metadata:qfb:attemptnumber'] = 'Inombolo yomzamo wequiz.';
$string['privacy_forum_extensions'] = 'Amaxesha ongezelelweyo eforamu';
$string['privacy_quiz_feedback'] = 'Impendulo yequiz';

// Offline cache and save status.
$string['allchangessaved'] = 'Zonke iinguqu zigciniwe';
$string['editing'] = 'Kuyahlela...';
$string['offlinesavedlocally'] = 'Ayiqhagamshelwanga — kugciniwe kwindawo yakho';
$string['connectionlost'] = 'Uqhagamshelwano lulahlekile — umsebenzi wakho ugciniwe kwindawo yakho kwaye uza kuhambelana xa uqhagamshelwa kwakhona.';
$string['recoveredunsavedchanges'] = 'Kubuyiswe iinguqu ezingagcinwanga kwiseshoni yakho edlulileyo.';
$string['restore'] = 'Buyisela';
$string['discard'] = 'Lahla';
$string['mark_as_graded'] = 'Phawula njengokuklasisiweyo';
