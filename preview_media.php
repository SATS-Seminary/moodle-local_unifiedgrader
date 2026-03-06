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
 * Render an audio or video file in a styled media player.
 *
 * Loaded in an iframe by the preview panel. Serves a minimal HTML page
 * with a styled HTML5 media element whose src points to preview_file.php.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$fileid = required_param('fileid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

// Validate context and capability.
$context = context_module::instance($cmid);
require_login(
    get_course($context->get_course_context()->instanceid),
    false,
    get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST),
);
require_capability('local/unifiedgrader:grade', $context);

// Fetch the file to get metadata.
$fs = get_file_storage();
$file = $fs->get_file_by_id($fileid);

if (!$file || $file->is_directory()) {
    throw new moodle_exception('filenotfound', 'error');
}

if ((int) $file->get_contextid() !== (int) $context->id) {
    throw new moodle_exception('filenotfound', 'error');
}

$mimetype = $file->get_mimetype();
$isaudio = str_starts_with($mimetype, 'audio/');
$isvideo = str_starts_with($mimetype, 'video/');

if (!$isaudio && !$isvideo) {
    throw new moodle_exception('filenotfound', 'error');
}

$filename = $file->get_filename();
$filesize = display_size($file->get_filesize());
$filesrc = (new moodle_url('/local/unifiedgrader/preview_file.php', [
    'fileid' => $fileid,
    'cmid' => $cmid,
]))->out(false);

// Output a standalone HTML page (no Moodle chrome needed).
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo s($filename); ?></title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background: #111827;
        color: #e5e7eb;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .player-container {
        width: 100%;
        max-width: 720px;
        padding: 2rem;
    }

    /* Audio-specific layout. */
    .audio-wrapper {
        background: #1f2937;
        border-radius: 1rem;
        padding: 2rem 2.5rem;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.4);
    }

    .file-icon {
        width: 48px;
        height: 48px;
        background: #6366f1;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.25rem;
    }

    .file-icon svg {
        width: 24px;
        height: 24px;
        fill: #fff;
    }

    .file-name {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        word-break: break-word;
    }

    .file-meta {
        font-size: 0.8rem;
        color: #9ca3af;
        margin-bottom: 1.5rem;
    }

    audio {
        width: 100%;
        height: 54px;
        border-radius: 999px;
        outline: none;
    }

    audio::-webkit-media-controls-panel {
        background: #374151;
        border-radius: 999px;
    }

    /* Video-specific layout. */
    .video-wrapper {
        width: 100vw;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #000;
    }

    video {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    /* Hidden until JS decides which layout to use for video/* MIME types. */
    .detect-layout { display: none; }
</style>
</head>
<body>
<?php if ($isaudio): ?>
    <!--
        Pure audio/* MIME type (mp3, m4a, aac, ogg, wav, etc.) — render audio player directly.
    -->
    <div class="player-container">
        <div class="audio-wrapper">
            <div class="file-icon">
                <svg viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
            </div>
            <div class="file-name"><?php echo s($filename); ?></div>
            <div class="file-meta"><?php echo s($filesize); ?></div>
            <audio controls preload="metadata" src="<?php echo s($filesrc); ?>"></audio>
        </div>
    </div>
<?php else: ?>
    <!--
        video/* MIME type — could be a real video OR an audio-only container
        (e.g. .mp4 with audio track only). Use a <video> probe to detect
        whether there is a video track; if not, switch to the audio layout.
    -->
    <div id="audio-layout" class="player-container detect-layout">
        <div class="audio-wrapper">
            <div class="file-icon">
                <svg viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
            </div>
            <div class="file-name"><?php echo s($filename); ?></div>
            <div class="file-meta"><?php echo s($filesize); ?></div>
            <audio id="audio-el" controls preload="metadata" src="<?php echo s($filesrc); ?>"></audio>
        </div>
    </div>
    <div id="video-layout" class="video-wrapper detect-layout">
        <video id="video-el" controls preload="metadata" src="<?php echo s($filesrc); ?>"></video>
    </div>
    <script>
        // Probe the video element to detect audio-only containers.
        // Files like .mp4 and .mpeg get a video/* MIME type even when they
        // contain only an audio track. After metadata loads we check
        // videoWidth — if 0, the file has no video track.
        const probe = document.getElementById('video-el');
        probe.addEventListener('loadedmetadata', function() {
            if (probe.videoWidth === 0 && probe.videoHeight === 0) {
                // Audio-only file in a video container — show audio layout.
                document.getElementById('audio-layout').style.display = '';
                document.getElementById('audio-layout').classList.remove('detect-layout');
                // Pause the probe video so only the audio element plays.
                probe.pause();
                probe.removeAttribute('src');
                probe.load();
            } else {
                // Real video — show video layout.
                document.getElementById('video-layout').style.display = '';
                document.getElementById('video-layout').classList.remove('detect-layout');
            }
        });
        // Fallback: if metadata never loads (error), show video layout anyway.
        probe.addEventListener('error', function() {
            document.getElementById('video-layout').style.display = '';
            document.getElementById('video-layout').classList.remove('detect-layout');
        });
    </script>
<?php endif; ?>
</body>
</html>
<?php
exit;
