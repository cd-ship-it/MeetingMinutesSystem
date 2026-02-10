<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
$baseUrl = $config['app_url'];
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
$required_fields = $config['required_fields'] ?? [];
$required_fields_ui = ($config['env'] === 'development') ? $required_fields : $required_fields;
$campus_names = $config['campus_names'] ?? [];
$ministry_names = $config['ministry_names'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Meeting Minutes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        #doc-paste-editor:empty::before { content: attr(data-placeholder); color: #94a3b8; }
        .doc-panel {
            overflow: hidden;
            transition: max-height 0.4s ease-out, opacity 0.3s ease-out, transform 0.3s ease-out;
        }
        .doc-panel.doc-panel-hidden {
            max-height: 0;
            opacity: 0;
            transform: translateY(-10px);
            pointer-events: none;
        }
        .doc-panel:not(.doc-panel-hidden) {
            max-height: 900px;
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-8 px-4">
    <div class="max-w-2xl mx-auto">
        <header class="flex items-center gap-4 mb-6">
            <img src="<?= htmlspecialchars($baseUrl) ?>/Xpt-ID2015_color_round-1.png" alt="Crosspoint" class="h-14 w-auto object-contain">
            <h1 class="text-2xl font-semibold text-slate-800">Submit Meeting Minutes (Crosspoint Internal use only)</h1>
        </header>

        <?php if ($success === '1'): ?>
            <div class="mb-6 p-6 bg-green-100 border border-green-300 text-green-800 rounded-xl">
                <p class="font-medium">Minutes submitted successfully.</p>
                <p class="mt-2">You can close this window now.</p>
                <p class="mt-4">
                    <a href="<?= htmlspecialchars($baseUrl . '/') ?>" class="underline font-medium">Submit another</a>
                </p>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-300 text-red-800 rounded-lg">
                <?= htmlspecialchars(urldecode($error)) ?>
            </div>
        <?php endif; ?>

        <div id="minutes-form-wrap"<?= $success === '1' ? ' class="hidden"' : '' ?>>
        <form action="<?= htmlspecialchars($baseUrl) ?>/submit.php" method="post" enctype="multipart/form-data" class="space-y-6 bg-white rounded-xl shadow-sm border border-slate-200 p-6" id="minutes-form">
        <section>
                <h2 class="text-xl font-semibold text-slate-800 mb-4">Submit meeting minutes *</h2>

                
                <div class="px-4 pt-5 pb-5 rounded-lg border border-slate-200 bg-blue-50 mt-4 space-y-4">
                    <p cclass="block text-base font-semibold text-slate-600 mb-1"">Please choose one method to submit your meeting minutes.</p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4" role="group" aria-label="Document source">
                        <button type="button" id="doc-option-upload" class="doc-source-option rounded-xl border-2 border-slate-200 bg-white p-4 text-left transition-all cursor-pointer hover:border-slate-300 hover:bg-slate-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:border-blue-500 flex flex-col items-start" data-doc-source="upload" aria-pressed="false">
                            <span class="flex-shrink-0 w-10 h-10 text-slate-500 mb-3" aria-hidden="true">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                            </span>
                            <span class="block text-base font-semibold text-slate-800 mb-1">Upload file</span>
                            <span class="text-sm text-slate-500">PDF, Word, or .txt</span>Link to document

                        </button>
                        <button type="button" id="doc-option-link" class="doc-source-option rounded-xl border-2 border-slate-200 bg-white p-4 text-left transition-all cursor-pointer hover:border-slate-300 hover:bg-slate-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:border-blue-500 flex flex-col items-start" data-doc-source="link" aria-pressed="false">
                            <span class="flex-shrink-0 w-10 h-10 text-slate-500 mb-3" aria-hidden="true">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.303l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                            </span>
                            <span class="block text-base font-semibold text-slate-800 mb-1">Web link</span>
                            <span class="text-sm text-slate-500">Google Doc, Dropbox, etc.</span>
                        </button>
                        <button type="button" id="doc-option-paste" class="doc-source-option rounded-xl border-2 border-slate-200 bg-white p-4 text-left transition-all cursor-pointer hover:border-slate-300 hover:bg-slate-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:border-blue-500 flex flex-col items-start" data-doc-source="paste" aria-pressed="false">
                            <span class="flex-shrink-0 w-10 h-10 text-slate-500 mb-3" aria-hidden="true">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                            </span>
                            <span class="block text-base font-semibold text-slate-800 mb-1">Copy and paste</span>
                            <span class="text-sm text-slate-500">Paste content from your document</span>
                        </button>
                    </div>

                    <div id="doc-panel-upload" class="doc-panel doc-panel-hidden">
                        <div id="drop-zone" class="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center bg-slate-50 hover:bg-slate-100 transition-colors cursor-pointer">
                            <input type="file" name="document_file" id="document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.txt,.rtf" class="hidden">
                            <p id="drop-zone-text" class="text-slate-600">Drag and drop a file here, or <span class="text-blue-600 font-medium">browse</span></p>
                            <p class="text-sm text-slate-500 mt-1">PDF, Word, or .txt</p>
                        </div>
                        <div id="file-uploaded-box" class="hidden mt-3 flex items-center gap-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                            <span class="flex-shrink-0 text-green-600" aria-hidden="true">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </span>
                            <span id="file-uploaded-name" class="flex-1 min-w-0 truncate text-sm font-medium text-green-800">filename</span>
                            <button type="button" id="clear-file" class="flex-shrink-0 p-1 rounded text-slate-500 hover:bg-slate-200 hover:text-slate-700 focus:ring-2 focus:ring-blue-500" title="Clear file">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div id="upload-progress-wrap" class="hidden mt-3">
                            <div class="flex items-center justify-between text-sm text-slate-600 mb-1">
                                <span>Uploading…</span>
                                <span id="upload-progress-pct">0%</span>
                            </div>
                            <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div id="upload-progress-bar" class="h-full bg-blue-600 transition-all duration-200" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <div id="doc-panel-link" class="doc-panel doc-panel-hidden">
                        <label for="document_url" class="block text-base font-semibold text-slate-600 mb-1">Link to document</label>
                        <input type="url" name="document_url" id="document_url" placeholder="https://..."
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                    </div>

                    <div id="doc-panel-paste" class="doc-panel doc-panel-hidden">
                        <label class="block text-base font-semibold text-slate-600 mb-1">Paste your document content</label>
                        <input type="hidden" name="document_paste" id="document_paste" value="">
                        <div class="rounded-lg border border-slate-300 bg-white focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500 overflow-hidden">
                            <div id="doc-paste-toolbar" class="flex flex-wrap items-center gap-1 p-2 border-b border-slate-200 bg-slate-50" role="toolbar" aria-label="Formatting">
                                <button type="button" class="doc-format-btn p-2 rounded hover:bg-slate-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-1" data-cmd="bold" title="Bold"><span class="font-bold text-slate-800">B</span></button>
                                <button type="button" class="doc-format-btn p-2 rounded hover:bg-slate-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-1" data-cmd="italic" title="Italic"><span class="italic text-slate-800">I</span></button>
                                <button type="button" class="doc-format-btn p-2 rounded hover:bg-slate-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-1" data-cmd="underline" title="Underline"><span class="underline text-slate-800">U</span></button>
                                <span class="w-px h-6 bg-slate-300 mx-1" aria-hidden="true"></span>
                                <button type="button" class="doc-format-btn p-2 rounded hover:bg-slate-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-1" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                                <button type="button" class="doc-format-btn p-2 rounded hover:bg-slate-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-1" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
                            </div>
                            <div class="min-h-[200px] p-3" contenteditable="true" id="doc-paste-editor" data-placeholder="Paste or type your meeting minutes here…"></div>
                        </div>
                    </div>
                    <p id="doc-error" class="text-sm text-red-600 mt-1 hidden">Please upload a file, provide a web link, or paste your document content.</p>
                </div>
            </section>
            <hr class="my-6 border-slate-200">

            <section>
                <h2 class="text-xl font-semibold text-slate-800">Meeting Chairperson's information</h2>
                <div class="block text-base font-light text-slate-600 mb-4">
                    The person who called or hosted the meeting.
                </div>
                <div class="px-4 pt-5 pb-5 rounded-lg border border-slate-200 bg-blue-50 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="chair_first_name" class="block text-base font-semibold text-slate-600 mb-1">First name<?= in_array('chair_first_name', $required_fields_ui) ? ' *' : '' ?></label>
                            <input type="text" name="chair_first_name" id="chair_first_name"<?= in_array('chair_first_name', $required_fields_ui) ? ' required' : ' placeholder="Optional"' ?>
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                        </div>
                        <div>
                            <label for="chair_last_name" class="block text-base font-semibold text-slate-600 mb-1">Last name<?= in_array('chair_last_name', $required_fields_ui) ? ' *' : '' ?></label>
                            <input type="text" name="chair_last_name" id="chair_last_name"<?= in_array('chair_last_name', $required_fields_ui) ? ' required' : ' placeholder="Optional"' ?>
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                        </div>
                    </div>
                    <div>
                        <label for="chair_email" class="block text-base font-semibold text-slate-600 mb-1">Email<?= in_array('chair_email', $required_fields_ui) ? ' *' : '' ?></label>
                        <input type="email" name="chair_email" id="chair_email"<?= in_array('chair_email', $required_fields_ui) ? ' required' : ' placeholder="Optional"' ?>
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                    </div>
                </div>
            </section>

            <hr class="my-6 border-slate-200">
            <section>
                <h2 class="text-xl font-semibold text-slate-800 mb-4">Meeting details</h2>
                <div class="space-y-6">
                    <div class="px-4 pt-5 pb-5 rounded-lg border border-slate-200 bg-blue-50">
                        <span class="block text-base font-semibold text-slate-600 mb-2">Campus name<?= in_array('campus_name', $required_fields_ui) ? ' *' : '' ?></span>
                        <div class="grid grid-cols-2 gap-2" role="group" aria-label="Campus name">
                            <?php foreach ($campus_names as $i => $name): ?>
                                <label class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-100 cursor-pointer bg-white">
                                    <input type="radio" name="campus_name" value="<?= htmlspecialchars($name) ?>"<?= in_array('campus_name', $required_fields_ui) && $i === 0 ? ' required' : '' ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-slate-700"><?= htmlspecialchars($name) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="px-4 pt-5 pb-5 rounded-lg border border-slate-200 bg-blue-50">
                        <span class="block text-base font-semibold text-slate-600 mb-2">Ministry<?= in_array('ministry', $required_fields_ui) ? ' *' : '' ?></span>
                        <div class="grid grid-cols-2 gap-2" role="group" aria-label="Ministry">
                            <?php foreach ($ministry_names as $i => $name): ?>
                                <label class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-100 cursor-pointer bg-white">
                                    <input type="radio" name="ministry" value="<?= htmlspecialchars($name) ?>"<?= in_array('ministry', $required_fields_ui) && $i === 0 ? ' required' : '' ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 ministry-radio">
                                    <span class="ml-2 text-slate-700"><?= htmlspecialchars($name) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="ministry-other-wrap" class="mt-3 hidden p-4 rounded-lg bg-green-50 border border-green-200">
                            <label for="ministry_other" class="block text-base font-semibold text-slate-600 mb-1">Please specify ministry name<?= in_array('ministry_other', $required_fields_ui) ? ' *' : '' ?></label>
                            <input type="text" name="ministry_other" id="ministry_other" autocomplete="off"
                                placeholder="<?= in_array('ministry_other', $required_fields_ui) ? 'Enter ministry name' : 'Optional' ?>"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                            <p id="ministry-other-error" class="text-sm text-red-600 mt-1 hidden">Please enter the ministry name.</p>
                        </div>
                    </div>
                    <div class="px-4 pt-5 pb-5 rounded-lg border border-slate-200 bg-blue-50">
                        <label for="pastor_in_charge" class="block text-base font-semibold text-slate-600 mb-1">Pastor-in-charge<?= in_array('pastor_in_charge', $required_fields_ui) ? ' *' : '' ?></label>
                        <input type="text" name="pastor_in_charge" id="pastor_in_charge"<?= in_array('pastor_in_charge', $required_fields_ui) ? ' required' : ' placeholder="Optional"' ?>
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                    </div>
                    <div class="px-4 pt-5 pb-5 rounded-lg border border-slate-200 bg-blue-50">
                        <label for="attendees" class="block text-base font-semibold text-slate-600 mb-1">Attendees </label>
                        <textarea name="attendees" id="attendees" rows="2" placeholder="Optional"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"></textarea>
                    </div>
                    <div class="px-4 pt-5 pb-5 rounded-lg border border-slate-200 bg-blue-50">
                        <label class="block text-base font-semibold text-slate-600 mb-2">Meeting type<?= in_array('meeting_type', $required_fields_ui) ? ' *' : ' (Optional)' ?></label>
                        <div class="flex gap-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="meeting_type" value="in_person"<?= in_array('meeting_type', $required_fields_ui) ? ' required' : '' ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">In person</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="meeting_type" value="online" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">Online</span>
                            </label>
                        </div>
                    </div>
                    <div class="px-4 pt-5 pb-5 rounded-lg border border-slate-200 bg-blue-50">
                        <label for="description" class="block text-base font-semibold text-slate-600 mb-1">Short description of the meeting</label>
                        <textarea name="description" id="description" rows="3" placeholder="Optional"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"></textarea>
                    </div>
                </div>
            </section>

            <hr class="my-6 border-slate-200">
         

            <div class="px-4 pt-5 pb-5 rounded-lg border border-slate-200 bg-blue-50 mt-4">
                <label for="comments" class="block text-base font-semibold text-slate-600 mb-1">Anything else you want to tell us about the meeting</label>
                <textarea name="comments" id="comments" rows="3" placeholder="Optional"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Submit
                </button>
                <a href="<?= htmlspecialchars($baseUrl) ?>/" class="px-4 py-2 border border-slate-300 rounded-lg font-medium text-slate-700 hover:bg-slate-50">Clear</a>
            </div>
        </form>
        </div>
    </div>

    <script>
        (function () {
            // Ministry "Others" → show required custom field when selected
            (function () {
                var ministryRadios = document.querySelectorAll('input[name="ministry"]');
                var ministryOtherWrap = document.getElementById('ministry-other-wrap');
                var ministryOtherInput = document.getElementById('ministry_other');
                var ministryOtherError = document.getElementById('ministry-other-error');
                if (!ministryOtherWrap || !ministryOtherInput) return;

                function updateOthersVisibility() {
                    var checked = document.querySelector('input[name="ministry"]:checked');
                    var isOthers = checked && checked.value === 'Others';
                    ministryOtherWrap.classList.toggle('hidden', !isOthers);
                    ministryOtherInput.required = isOthers;
                    if (!isOthers) {
                        ministryOtherInput.value = '';
                        if (ministryOtherError) ministryOtherError.classList.add('hidden');
                    }
                }

                ministryRadios.forEach(function (r) {
                    r.addEventListener('change', updateOthersVisibility);
                });
            })();

            var dropZone = document.getElementById('drop-zone');
            var dropZoneText = document.getElementById('drop-zone-text');
            var fileInput = document.getElementById('document_file');
            var fileUploadedBox = document.getElementById('file-uploaded-box');
            var fileUploadedName = document.getElementById('file-uploaded-name');
            var clearFileBtn = document.getElementById('clear-file');
            var uploadProgressWrap = document.getElementById('upload-progress-wrap');
            var uploadProgressBar = document.getElementById('upload-progress-bar');
            var uploadProgressPct = document.getElementById('upload-progress-pct');
            var form = document.getElementById('minutes-form');
            var docError = document.getElementById('doc-error');
            var submitUrl = form.getAttribute('action');
            var submitBtn = form.querySelector('button[type="submit"]');

            
            var descriptionEl = document.getElementById('description');

            (function () {
                var docOptions = document.querySelectorAll('.doc-source-option');
                var docPanels = document.querySelectorAll('.doc-panel');
                var selectedClasses = 'border-blue-500 bg-blue-100 ring-2 ring-blue-500';
                var baseClasses = 'border-slate-200 bg-white';

                function setDocSource(source) {
                    docOptions.forEach(function (btn) {
                        var isSelected = btn.getAttribute('data-doc-source') === source;
                        btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                        btn.classList.remove('border-blue-500', 'bg-blue-100', 'ring-2', 'ring-blue-500', 'border-slate-200', 'bg-white');
                        if (isSelected) {
                            btn.classList.add('border-blue-500', 'bg-blue-100', 'ring-2', 'ring-blue-500');
                        } else {
                            btn.classList.add('border-slate-200', 'bg-white');
                        }
                    });
                    docPanels.forEach(function (panel) {
                        var panelId = panel.id;
                        var show = (source === 'upload' && panelId === 'doc-panel-upload') ||
                            (source === 'link' && panelId === 'doc-panel-link') ||
                            (source === 'paste' && panelId === 'doc-panel-paste');
                        panel.classList.toggle('doc-panel-hidden', !show);
                    });
                }

                docOptions.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        setDocSource(btn.getAttribute('data-doc-source'));
                    });
                });
            })();

            (function () {
                var pasteEditor = document.getElementById('doc-paste-editor');
                var toolbar = document.getElementById('doc-paste-toolbar');
                if (!pasteEditor) return;

                function sanitizePasteHtml(html) {
                    if (!html || typeof html !== 'string') return '';
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var allowed = { p: 1, br: 1, b: 1, i: 1, u: 1, strong: 1, em: 1, ul: 1, ol: 1, li: 1, h1: 1, h2: 1, h3: 1, span: 1, div: 1 };
                    function sanitizeNode(node) {
                        if (node.nodeType === 3) return node.cloneNode(true);
                        if (node.nodeType !== 1) return null;
                        var tag = node.tagName.toLowerCase();
                        if (!allowed[tag]) return null;
                        var out = doc.createElement(tag);
                        for (var i = 0; i < node.childNodes.length; i++) {
                            var c = sanitizeNode(node.childNodes[i]);
                            if (c) out.appendChild(c);
                        }
                        return out;
                    }
                    var body = doc.body;
                    var frag = doc.createDocumentFragment();
                    for (var i = 0; i < body.childNodes.length; i++) {
                        var c = sanitizeNode(body.childNodes[i]);
                        if (c) frag.appendChild(c);
                    }
                    var wrap = doc.createElement('div');
                    wrap.appendChild(frag);
                    return wrap.innerHTML;
                }
                window.sanitizePasteHtml = sanitizePasteHtml;

                if (toolbar) {
                    toolbar.querySelectorAll('.doc-format-btn').forEach(function (btn) {
                        btn.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            pasteEditor.focus();
                            document.execCommand(btn.getAttribute('data-cmd'), false, null);
                        });
                    });
                }

                pasteEditor.addEventListener('paste', function (e) {
                    var html = (e.clipboardData || window.clipboardData).getData('text/html');
                    if (html) {
                        e.preventDefault();
                        var clean = sanitizePasteHtml(html);
                        document.execCommand('insertHTML', false, clean);
                    }
                });
            })();

            var dropZoneDefaultHtml = 'Drag and drop a file here, or <span class="text-blue-600 font-medium">browse</span>';

            function updateFileUI() {
                var hasFile = fileInput.files && fileInput.files.length > 0;
                if (hasFile) {
                    fileUploadedName.textContent = fileInput.files[0].name;
                    fileUploadedBox.classList.remove('hidden');
                    docError.classList.add('hidden');
                } else {
                    fileUploadedBox.classList.add('hidden');
                    dropZoneText.innerHTML = dropZoneDefaultHtml;
                }
            }

            dropZone.addEventListener('click', function (e) {
                if (!e.target.closest('#clear-file')) fileInput.click();
            });
            fileInput.addEventListener('change', function () {
                if (fileInput.files.length) {
                    fileUploadedName.textContent = fileInput.files[0].name;
                    fileUploadedBox.classList.remove('hidden');
                    docError.classList.add('hidden');
                } else {
                    submitBtn.disabled = false;
                }
            });

            clearFileBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                fileInput.value = '';
                fileUploadedBox.classList.add('hidden');
                dropZoneText.innerHTML = dropZoneDefaultHtml;
                submitBtn.disabled = false;
            });

            dropZone.addEventListener('dragover', function (e) {
                e.preventDefault();
                dropZone.classList.add('border-blue-500', 'bg-blue-50');
            });
            dropZone.addEventListener('dragleave', function () {
                dropZone.classList.remove('border-blue-500', 'bg-blue-50');
            });
            dropZone.addEventListener('drop', function (e) {
                e.preventDefault();
                dropZone.classList.remove('border-blue-500', 'bg-blue-50');
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    updateFileUI();
                }
            });

            form.addEventListener('submit', function (e) {
                var pasteEditor = document.getElementById('doc-paste-editor');
                var pasteInput = document.getElementById('document_paste');
                if (pasteEditor && pasteInput) {
                    var rawHtml = pasteEditor.innerHTML || '';
                    pasteInput.value = (typeof window.sanitizePasteHtml === 'function' ? window.sanitizePasteHtml(rawHtml) : rawHtml);
                }
                var hasFile = fileInput.files && fileInput.files.length > 0;
                var hasUrl = document.getElementById('document_url').value.trim() !== '';
                var hasPaste = pasteInput && (pasteEditor && (pasteEditor.innerText || pasteEditor.textContent || '').trim() !== '');
                if (!hasFile && !hasUrl && !hasPaste) {
                    e.preventDefault();
                    docError.classList.remove('hidden');
                    docError.scrollIntoView({ behavior: 'smooth' });
                    return;
                }


                var ministryChecked = document.querySelector('input[name="ministry"]:checked');
                var ministryOtherInput = document.getElementById('ministry_other');
                var ministryOtherError = document.getElementById('ministry-other-error');
                if (ministryChecked && ministryChecked.value === 'Others' && !(ministryOtherInput && ministryOtherInput.value.trim())) {
                    e.preventDefault();
                    if (ministryOtherError) ministryOtherError.classList.remove('hidden');
                    if (ministryOtherInput) ministryOtherInput.focus();
                    return;
                }
                if (ministryOtherError) ministryOtherError.classList.add('hidden');

                e.preventDefault();

                var submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                uploadProgressWrap.classList.remove('hidden');
                uploadProgressBar.style.width = '0%';
                uploadProgressPct.textContent = '0%';

                var xhr = new XMLHttpRequest();
                var formData = new FormData(form);

                xhr.upload.addEventListener('progress', function (ev) {
                    if (ev.lengthComputable) {
                        var pct = Math.round((ev.loaded / ev.total) * 100);
                        uploadProgressBar.style.width = pct + '%';
                        uploadProgressPct.textContent = pct + '%';
                    } else {
                        uploadProgressPct.textContent = '…';
                    }
                });

                xhr.addEventListener('load', function () {
                    uploadProgressBar.style.width = '100%';
                    uploadProgressPct.textContent = '100%';
                    submitBtn.disabled = false;

                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success && data.redirect) {
                            window.location.href = data.redirect;
                            return;
                        }
                        if (!data.success && data.error) {
                            docError.textContent = data.error;
                            docError.classList.remove('hidden');
                            docError.scrollIntoView({ behavior: 'smooth' });
                        }
                    } catch (err) {
                        docError.textContent = 'Something went wrong. Please try again. Check logs/app.log for details.';
                        docError.classList.remove('hidden');
                    }
                    uploadProgressWrap.classList.add('hidden');
                });

                xhr.addEventListener('error', function () {
                    submitBtn.disabled = false;
                    uploadProgressWrap.classList.add('hidden');
                    docError.textContent = 'Network error. Please try again.';
                    docError.classList.remove('hidden');
                });

                xhr.open('POST', submitUrl);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send(formData);
            });
        })();
    </script>
</body>
</html>
