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
 * Teacher-facing view of their own comment library, grouped by course code.
 *
 * Lets a teacher move their own comments between course buckets, export or
 * import their library as CSV, and delete comments (including bulk-clearing
 * duplicates) without involving an admin — the self-service counterpart to
 * moderate_libraries.php. Every operation here is scoped to $USER->id at the
 * manager level, so a teacher can only ever rewrite their own rows.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_unifiedgrader\library_audit;
use local_unifiedgrader\library_csv;

require_login();

if (isguestuser()) {
    throw new moodle_exception('noguest');
}
// The comment library is for people who grade, not for every logged-in user.
\local_unifiedgrader\access::require_library_access();

$context = context_user::instance($USER->id);
$action = optional_param('action', 'list', PARAM_ALPHA);
$code = optional_param('code', '', PARAM_TEXT);

$baseurl = new moodle_url('/local/unifiedgrader/my_library.php');

$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('clibmod_my_library', 'local_unifiedgrader'));
$PAGE->set_heading(get_string('clibmod_my_library', 'local_unifiedgrader'));

if ($action === 'recode') {
    require_sesskey();
    $commentids = optional_param_array('commentids', [], PARAM_INT);
    $newcode = trim(optional_param('newcode', '', PARAM_TEXT));

    // The owner argument is what confines this to the teacher's own rows.
    $changed = library_audit::recode_comments($commentids, $newcode, $USER->id);

    \local_unifiedgrader\event\library_repaired::create([
        'context' => $context,
        'other' => [
            'action' => 'selfrecode',
            'count' => $changed,
            'detail' => $newcode,
        ],
    ])->trigger();

    redirect(
        $baseurl,
        get_string('clibmod_recoded', 'local_unifiedgrader', (object) [
            'count' => $changed,
            'code' => $newcode !== '' ? $newcode : get_string('clibmod_universal', 'local_unifiedgrader'),
        ]),
    );
}

if ($action === 'deletecomments') {
    require_sesskey();
    $commentids = optional_param_array('commentids', [], PARAM_INT);

    // Owner-scoped: this is what makes it safe for a teacher to call directly.
    $deleted = library_audit::delete_comments($commentids, $USER->id);

    \local_unifiedgrader\event\library_repaired::create([
        'context' => $context,
        'other' => [
            'action' => 'selfdelete',
            'count' => $deleted,
            'detail' => 'bulk delete',
        ],
    ])->trigger();

    redirect($baseurl, get_string('clibmod_deleted', 'local_unifiedgrader', $deleted));
}

if ($action === 'importcsv') {
    require_sesskey();
    if (empty($_FILES['csvfile']['tmp_name']) || !is_uploaded_file($_FILES['csvfile']['tmp_name'])) {
        redirect($baseurl, get_string('clibcsv_no_file', 'local_unifiedgrader'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $csvcontent = file_get_contents($_FILES['csvfile']['tmp_name']);

    // Owner is always forced to $USER->id here (allowownercolumn=false), so
    // an "ownerid" column in an uploaded file — e.g. one exported by an
    // admin across many teachers — is simply ignored rather than letting a
    // teacher import into someone else's library.
    $result = library_csv::import($csvcontent, $USER->id, null, false, false);

    \local_unifiedgrader\event\library_repaired::create([
        'context' => $context,
        'other' => [
            'action' => 'selfimport',
            'count' => $result['imported'],
            'detail' => 'skipped ' . $result['skipped'],
        ],
    ])->trigger();

    $message = get_string('clibcsv_import_result', 'local_unifiedgrader', (object) [
        'imported' => $result['imported'],
        'skipped' => $result['skipped'],
    ]);
    redirect(
        $baseurl,
        $message,
        null,
        empty($result['errors']) ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING,
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('clibmod_my_library', 'local_unifiedgrader'), 2);
echo html_writer::tag('p', get_string('clibmod_my_library_intro', 'local_unifiedgrader'), [
    'class' => 'text-muted',
]);

// Export / import — offered even when the library is empty, since import
// is exactly how you'd populate one (e.g. restoring from a backup).
echo html_writer::start_div('d-flex align-items-start gap-4 flex-wrap mb-4');

echo html_writer::link(
    new moodle_url('/local/unifiedgrader/export_library_csv.php', ['scope' => 'mine']),
    '<i class="fa fa-download me-1"></i>' . get_string('clibmod_export_mine', 'local_unifiedgrader'),
    ['class' => 'btn btn-outline-secondary'],
);

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $baseurl->out(false),
    'enctype' => 'multipart/form-data',
    'class' => 'd-flex align-items-end gap-2 flex-wrap',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'importcsv']);
echo html_writer::div(
    html_writer::label(get_string('clibmod_import_csv', 'local_unifiedgrader'), 'mylib-csvfile', true, [
        'class' => 'form-label small mb-1',
    ])
    . html_writer::empty_tag('input', [
        'type' => 'file',
        'id' => 'mylib-csvfile',
        'name' => 'csvfile',
        'accept' => '.csv,text/csv',
        'class' => 'form-control form-control-sm',
    ]),
);
echo html_writer::tag('button', get_string('clibmod_import_csv_button', 'local_unifiedgrader'), [
    'type' => 'submit',
    'class' => 'btn btn-outline-primary',
]);
echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::tag('p', get_string('clibmod_my_import_csv_help', 'local_unifiedgrader'), [
    'class' => 'text-muted small mb-4',
]);

$inventory = library_audit::get_inventory($USER->id);

if (empty($inventory)) {
    echo html_writer::tag('p', get_string('clibmod_my_library_empty', 'local_unifiedgrader'), [
        'class' => 'text-muted',
    ]);
    echo $OUTPUT->footer();
    exit;
}

// Bucket overview — one row per course code the teacher has comments under.
$overview = new html_table();
$overview->head = [
    get_string('clibmod_coursecode', 'local_unifiedgrader'),
    get_string('clibmod_matching_courses', 'local_unifiedgrader'),
    get_string('clibmod_numcomments', 'local_unifiedgrader'),
    get_string('clibmod_lastmodified', 'local_unifiedgrader'),
    get_string('actions'),
];
$overview->attributes['class'] = 'generaltable';
$overview->data = [];

foreach ($inventory as $row) {
    $codecell = $row['isuniversal']
        ? html_writer::span(get_string('clibmod_universal', 'local_unifiedgrader'), 'badge bg-info text-dark')
        : html_writer::tag('code', s($row['coursecode']));

    $coursecell = '';
    foreach ($row['courses'] as $course) {
        $coursecell .= html_writer::div(s($course['shortname']));
    }
    if ($coursecell === '' && !$row['isuniversal']) {
        $coursecell = html_writer::span(
            get_string('clibmod_no_matching_course', 'local_unifiedgrader'),
            'text-danger',
        );
    }

    $overview->data[] = [
        $codecell,
        $coursecell,
        $row['numcomments'],
        userdate($row['lastmodified'], get_string('strftimedatetimeshort')),
        html_writer::link(
            new moodle_url($baseurl, ['action' => 'view', 'code' => $row['coursecode']]),
            get_string('clibmod_inspect', 'local_unifiedgrader'),
            ['class' => 'btn btn-sm btn-outline-primary'],
        ),
    ];
}
echo html_writer::table($overview);

// Possible duplicate comments — exact repeats of the same content under the
// same course code, most often left behind by a double-submit or a re-run
// import.
$duplicates = library_audit::find_duplicate_comments($USER->id);
if (!empty($duplicates)) {
    echo $OUTPUT->heading(get_string('clibmod_duplicates_heading', 'local_unifiedgrader'), 3, 'mt-4');
    echo html_writer::tag('p', get_string('clibmod_duplicates_found', 'local_unifiedgrader', count($duplicates)));

    $duptable = new html_table();
    $duptable->head = [
        get_string('clibmod_coursecode', 'local_unifiedgrader'),
        get_string('clib_comment_content', 'local_unifiedgrader'),
        get_string('clibmod_duplicates_copies', 'local_unifiedgrader'),
        get_string('actions'),
    ];
    $duptable->attributes['class'] = 'generaltable';
    $duptable->data = [];

    foreach ($duplicates as $group) {
        // Every copy but the oldest is the "extra" this button removes —
        // the oldest is kept as the one true record.
        $extraids = array_slice(array_column($group['comments'], 'id'), 1);

        $codecell = $group['coursecode'] !== ''
            ? html_writer::tag('code', s($group['coursecode']))
            : html_writer::span(get_string('clibmod_universal', 'local_unifiedgrader'), 'badge bg-info text-dark');

        $viewurl = new moodle_url($baseurl, ['action' => 'view', 'code' => $group['coursecode']]);

        $actions = html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'd-inline']);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deletecomments']);
        foreach ($extraids as $id) {
            $actions .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'commentids[]', 'value' => $id]);
        }
        $actions .= html_writer::tag('button', get_string(
            'clibmod_duplicates_keep_oldest',
            'local_unifiedgrader',
            count($extraids),
        ), [
            'type' => 'submit',
            'class' => 'btn btn-sm btn-outline-danger',
            'onclick' => 'return confirm('
                . json_encode(get_string('clibmod_duplicates_confirm', 'local_unifiedgrader')) . ');',
        ]);
        $actions .= html_writer::end_tag('form');
        $actions .= html_writer::link($viewurl, get_string('clibmod_inspect', 'local_unifiedgrader'), [
            'class' => 'btn btn-sm btn-outline-primary ms-1',
        ]);

        $duptable->data[] = [
            $codecell,
            shorten_text(format_text($group['content'], FORMAT_PLAIN), 200),
            count($group['comments']),
            $actions,
        ];
    }
    echo html_writer::table($duptable);
}

if ($action !== 'view') {
    echo $OUTPUT->footer();
    exit;
}

// Drill-down: pick comments in one bucket and move or delete them.
$comments = library_audit::get_comments_for($USER->id, $code);

echo $OUTPUT->heading(
    $code !== ''
        ? get_string('clibmod_my_bucket_heading', 'local_unifiedgrader', s($code))
        : get_string('clibmod_my_universal_heading', 'local_unifiedgrader'),
    3,
    'mt-4',
);

if (!empty($comments)) {
    echo html_writer::link(
        new moodle_url('/local/unifiedgrader/export_library_csv.php', ['scope' => 'bucket', 'owner' => $USER->id, 'code' => $code]),
        '<i class="fa fa-download me-1"></i>' . get_string('clibmod_export_bucket', 'local_unifiedgrader'),
        ['class' => 'btn btn-sm btn-outline-secondary mb-3'],
    );
}

if (empty($comments)) {
    echo html_writer::tag('p', get_string('clibmod_no_comments', 'local_unifiedgrader'), ['class' => 'text-muted']);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$table = new html_table();
$table->head = [
    html_writer::checkbox('mylib-selectall', 1, false, '', ['id' => 'mylib-selectall']),
    get_string('clib_comment_content', 'local_unifiedgrader'),
    get_string('clib_tags', 'local_unifiedgrader'),
    get_string('clibmod_created', 'local_unifiedgrader'),
];
$table->attributes['class'] = 'generaltable';
$table->data = [];

foreach ($comments as $comment) {
    $tagpills = '';
    foreach ($comment['tags'] as $tagname) {
        $tagpills .= html_writer::span(format_string($tagname), 'badge bg-secondary me-1');
    }
    $table->data[] = [
        html_writer::checkbox('commentids[]', $comment['id'], false, '', ['class' => 'mylib-check']),
        shorten_text(format_text($comment['content'], FORMAT_PLAIN), 300),
        $tagpills ?: html_writer::tag('span', '—', ['class' => 'text-muted']),
        userdate($comment['timecreated'], get_string('strftimedatetimeshort')),
    ];
}
echo html_writer::table($table);

// Only offer codes for courses this teacher can actually grade in — the
// admin tool offers every code on the site, but a teacher moving comments
// into a course they don't teach would just lose them again.
$datalist = '';
$mycourses = enrol_get_my_courses(['id', 'shortname'], 'shortname ASC');
$offered = [];
foreach ($mycourses as $course) {
    $mycode = \local_unifiedgrader\course_code_helper::extract_code($course->shortname);
    if (trim($mycode) === '' || isset($offered[$mycode])) {
        continue;
    }
    $offered[$mycode] = true;
    $datalist .= html_writer::empty_tag('option', ['value' => $mycode]);
}
echo html_writer::tag('datalist', $datalist, ['id' => 'mylib-known-codes']);

echo html_writer::start_div('card p-3 mb-3');
echo html_writer::tag('h4', get_string('clibmod_recode_heading', 'local_unifiedgrader'), ['class' => 'h5']);
echo html_writer::tag('p', get_string('clibmod_my_recode_help', 'local_unifiedgrader'), [
    'class' => 'text-muted small',
]);
echo html_writer::start_div('d-flex align-items-center gap-2 flex-wrap');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'newcode',
    'list' => 'mylib-known-codes',
    'class' => 'form-control w-auto',
    'placeholder' => get_string('clibmod_newcode_placeholder', 'local_unifiedgrader'),
]);
echo html_writer::tag('button', get_string('clibmod_recode_button', 'local_unifiedgrader'), [
    'type' => 'submit',
    'name' => 'action',
    'value' => 'recode',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('card p-3');
echo html_writer::tag('h4', get_string('clibmod_delete_heading', 'local_unifiedgrader'), ['class' => 'h5']);
echo html_writer::tag('p', get_string('clibmod_delete_help', 'local_unifiedgrader'), [
    'class' => 'text-muted small',
]);
echo html_writer::tag('button', get_string('clibmod_delete_button', 'local_unifiedgrader'), [
    'type' => 'submit',
    'name' => 'action',
    'value' => 'deletecomments',
    'class' => 'btn btn-outline-danger',
    'onclick' => 'return confirm(' . json_encode(get_string('clibmod_delete_confirm', 'local_unifiedgrader')) . ');',
]);
echo html_writer::end_div();

echo html_writer::end_tag('form');

$PAGE->requires->js_amd_inline("
    require([], function() {
        var all = document.getElementById('mylib-selectall');
        if (!all) { return; }
        all.addEventListener('change', function() {
            document.querySelectorAll('.mylib-check').forEach(function(box) {
                box.checked = all.checked;
            });
        });
    });
");

echo $OUTPUT->footer();
