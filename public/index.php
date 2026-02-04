<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
$baseUrl = $config['app_url'];
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
$required_fields = $config['required_fields'] ?? [];
$campus_names = $config['campus_names'] ?? [];
$ministry_names = $config['ministry_names'] ?? [];
$useAiSummary = !empty($config['use_ai_for_minutes_summary']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Meeting Minutes</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                    <a href="<?= htmlspecialchars($baseUrl . '/index.php') ?>" class="underline font-medium">Submit another</a>
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
                <h2 class="text-lg font-medium text-slate-700 mb-3">Meeting Chairperson's information
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="chair_first_name" class="block text-sm font-medium text-slate-600 mb-1">First name<?= in_array('chair_first_name', $required_fields) ? ' *' : '' ?></label>
                        <input type="text" name="chair_first_name" id="chair_first_name"<?= in_array('chair_first_name', $required_fields) ? ' required' : '' ?>
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="chair_last_name" class="block text-sm font-medium text-slate-600 mb-1">Last name<?= in_array('chair_last_name', $required_fields) ? ' *' : '' ?></label>
                        <input type="text" name="chair_last_name" id="chair_last_name"<?= in_array('chair_last_name', $required_fields) ? ' required' : '' ?>
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="mt-4">
                    <label for="chair_email" class="block text-sm font-medium text-slate-600 mb-1">Email<?= in_array('chair_email', $required_fields) ? ' *' : '' ?></label>
                    <input type="email" name="chair_email" id="chair_email"<?= in_array('chair_email', $required_fields) ? ' required' : '' ?>
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </section>

            <hr class="my-6 border-slate-200">
            <section>
                <h2 class="text-lg font-medium text-slate-700 mb-3">Meeting details</h2>
                <div class="space-y-4">
                    <div>
                        <span class="block text-sm font-medium text-slate-600 mb-2">Campus name<?= in_array('campus_name', $required_fields) ? ' *' : '' ?></span>
                        <div class="grid grid-cols-2 gap-2" role="group" aria-label="Campus name">
                            <?php foreach ($campus_names as $i => $name): ?>
                                <label class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 cursor-pointer">
                                    <input type="radio" name="campus_name" value="<?= htmlspecialchars($name) ?>"<?= in_array('campus_name', $required_fields) && $i === 0 ? ' required' : '' ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-slate-700"><?= htmlspecialchars($name) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-slate-600 mb-2">Ministry<?= in_array('ministry', $required_fields) ? ' *' : '' ?></span>
                        <div class="grid grid-cols-2 gap-2" role="group" aria-label="Ministry">
                            <?php foreach ($ministry_names as $i => $name): ?>
                                <label class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 cursor-pointer">
                                    <input type="radio" name="ministry" value="<?= htmlspecialchars($name) ?>"<?= in_array('ministry', $required_fields) && $i === 0 ? ' required' : '' ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 ministry-radio">
                                    <span class="ml-2 text-slate-700"><?= htmlspecialchars($name) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="ministry-other-wrap" class="mt-3 hidden p-4 rounded-lg bg-green-50 border border-green-200">
                            <label for="ministry_other" class="block text-sm font-medium text-slate-600 mb-1">Please specify ministry name<?= in_array('ministry_other', $required_fields) ? ' *' : '' ?></label>
                            <input type="text" name="ministry_other" id="ministry_other" autocomplete="off"
                                placeholder="Enter ministry name"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                            <p id="ministry-other-error" class="text-sm text-red-600 mt-1 hidden">Please enter the ministry name.</p>
                        </div>
                    </div>
                    <div>
                        <label for="pastor_in_charge" class="block text-sm font-medium text-slate-600 mb-1">Pastor-in-charge<?= in_array('pastor_in_charge', $required_fields) ? ' *' : '' ?></label>
                        <input type="text" name="pastor_in_charge" id="pastor_in_charge"<?= in_array('pastor_in_charge', $required_fields) ? ' required' : '' ?>
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="attendees" class="block text-sm font-medium text-slate-600 mb-1">Attendees (optional, if not in the minutes)</label>
                        <textarea name="attendees" id="attendees" rows="2"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">Meeting type<?= in_array('meeting_type', $required_fields) ? ' *' : '' ?></label>
                        <div class="flex gap-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="meeting_type" value="in_person"<?= in_array('meeting_type', $required_fields) ? ' required' : '' ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">In person</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="meeting_type" value="online" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">Online</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-slate-600 mb-1">Short description of the meeting<?= in_array('description', $required_fields) ? ' *' : ' (Optional)' ?></label>
                        <?php if ($useAiSummary): ?>
                        <p id="ai-summary-status" class="hidden text-sm text-blue-600 mb-2"></p>
                        <p id="ai-summary-done-hint" class="hidden text-sm text-slate-600 mb-2">You can edit the result below. Or click <strong>Re-generate</strong> to ask AI again.</p>
                        <p id="ai-summary-timeout" class="hidden text-sm text-amber-700 mb-2">Oops. AI is taking a bit too long to generate the summary. Please retry if you want to ask AI again.</p>
                        <div id="ai-summary-actions" class="hidden mb-2">
                            <button type="button" id="ai-regenerate-btn" class="px-3 py-1.5 text-sm bg-slate-100 hover:bg-slate-200 rounded-lg font-medium text-slate-700">Re-generate</button>
                            <button type="button" id="ai-retry-btn" class="hidden px-3 py-1.5 text-sm bg-amber-100 hover:bg-amber-200 rounded-lg font-medium text-amber-800">Retry</button>
                        </div>
                        <textarea name="description" id="description" rows="3"<?= in_array('description', $required_fields) ? ' required' : '' ?> placeholder="Summary will appear here when you upload a file, or enter manually."
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        <?php else: ?>
                        <textarea name="description" id="description" rows="3"<?= in_array('description', $required_fields) ? ' required' : '' ?> placeholder="Enter a short description of the meeting."
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <hr class="my-6 border-slate-200">
            <section>
                <h2 class="text-lg font-medium text-slate-700 mb-3">Document (minutes)</h2>
                <p class="text-sm text-slate-600 mb-2">Upload a file or provide a link. At least one is required.</p>

                <div id="drop-zone" class="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center bg-slate-50 hover:bg-slate-100 transition-colors cursor-pointer">
                    <input type="file" name="document_file" id="document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.txt,.rtf" class="hidden">
                    <p id="drop-zone-text" class="text-slate-600">Drag and drop a file here, or <span class="text-blue-600 font-medium">browse</span></p>
                    <p class="text-sm text-slate-500 mt-1">PDF, Word, Excel, and common document formats</p>
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

                <p class="text-sm text-slate-500 mt-2 text-center">— or —</p>

                <div class="mt-4">
                    <label for="document_url" class="block text-sm font-medium text-slate-600 mb-1">Link to document (if shared in the cloud)</label>
                    <input type="url" name="document_url" id="document_url" placeholder="https://..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <p id="doc-error" class="text-sm text-red-600 mt-1 hidden">Please provide either a file or a document URL.</p>
            </section>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Submit
                </button>
                <a href="<?= htmlspecialchars($baseUrl) ?>/index.php" class="px-4 py-2 border border-slate-300 rounded-lg font-medium text-slate-700 hover:bg-slate-50">Clear</a>
            </div>
        </form>
        </div>
    </div>

    <script>
        window.useAiSummary = <?= $useAiSummary ? 'true' : 'false'; ?>;
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

            var aiStatus = 'idle';
            var descriptionEl = document.getElementById('description');
            <?php if ($useAiSummary): ?>
            if (window.useAiSummary) {
                var aiSummaryStatus = document.getElementById('ai-summary-status');
                var aiSummaryDoneHint = document.getElementById('ai-summary-done-hint');
                var aiSummaryTimeout = document.getElementById('ai-summary-timeout');
                var aiSummaryActions = document.getElementById('ai-summary-actions');
                var aiRegenerateBtn = document.getElementById('ai-regenerate-btn');
                var aiRetryBtn = document.getElementById('ai-retry-btn');

                function showAiState(generating, doneHint, timeout, retry) {
                    if (aiSummaryStatus) aiSummaryStatus.classList.toggle('hidden', !generating);
                    if (aiSummaryStatus) aiSummaryStatus.textContent = generating ? 'Summary auto generating by AI…' : '';
                    if (aiSummaryDoneHint) aiSummaryDoneHint.classList.toggle('hidden', !doneHint);
                    if (aiSummaryTimeout) aiSummaryTimeout.classList.toggle('hidden', !timeout);
                    if (aiSummaryActions) aiSummaryActions.classList.toggle('hidden', !(doneHint || timeout));
                    if (aiRegenerateBtn) aiRegenerateBtn.classList.toggle('hidden', !doneHint);
                    if (aiRetryBtn) aiRetryBtn.classList.toggle('hidden', !retry);
                }

                window.requestAISummary = function () {
                    if (!fileInput.files || fileInput.files.length === 0) return;
                    var file = fileInput.files[0];
                    aiStatus = 'pending';
                    submitBtn.disabled = true;
                    showAiState(true, false, false, false);
                    if (descriptionEl) descriptionEl.value = '';

                    var formData = new FormData();
                    formData.append('document_file', file);
                    var xhr = new XMLHttpRequest();
                    var baseUrl = form.action.replace('/submit.php', '');
                    xhr.open('POST', baseUrl + '/generate-summary.php');
                    xhr.timeout = 12000;

                    xhr.addEventListener('load', function () {
                        aiStatus = 'done';
                        submitBtn.disabled = false;
                        showAiState(false, false, false, false);
                        try {
                            var data = JSON.parse(xhr.responseText || '{}');
                            if (data.success && data.summary) {
                                if (descriptionEl) descriptionEl.value = data.summary;
                                showAiState(false, true, false, false);
                            } else if (data.timeout) {
                                if (aiSummaryTimeout) aiSummaryTimeout.classList.remove('hidden');
                                if (aiRetryBtn) aiRetryBtn.classList.remove('hidden');
                                if (aiSummaryActions) aiSummaryActions.classList.remove('hidden');
                            } else {
                                if (aiSummaryStatus) aiSummaryStatus.textContent = data.error || 'Could not generate summary.';
                                if (aiSummaryStatus) aiSummaryStatus.classList.remove('hidden');
                            }
                        } catch (err) {
                            if (aiSummaryStatus) aiSummaryStatus.textContent = 'Could not parse response. Enter description manually.';
                            if (aiSummaryStatus) aiSummaryStatus.classList.remove('hidden');
                        }
                    });

                    xhr.addEventListener('timeout', function () {
                        aiStatus = 'done';
                        submitBtn.disabled = false;
                        showAiState(false, false, false, false);
                        if (aiSummaryTimeout) aiSummaryTimeout.classList.remove('hidden');
                        if (aiRetryBtn) aiRetryBtn.classList.remove('hidden');
                        if (aiSummaryActions) aiSummaryActions.classList.remove('hidden');
                    });

                    xhr.addEventListener('error', function () {
                        aiStatus = 'done';
                        submitBtn.disabled = false;
                        showAiState(false, false, false, false);
                        if (aiSummaryStatus) aiSummaryStatus.textContent = 'Network error. You can enter the description manually.';
                        if (aiSummaryStatus) aiSummaryStatus.classList.remove('hidden');
                    });

                    xhr.send(formData);
                };

                if (aiRegenerateBtn) aiRegenerateBtn.addEventListener('click', function () { window.requestAISummary(); });
                if (aiRetryBtn) aiRetryBtn.addEventListener('click', function () { window.requestAISummary(); });
            }
            <?php endif; ?>

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
                    if (window.useAiSummary && window.requestAISummary) window.requestAISummary();
                } else {
                    aiStatus = 'idle';
                    submitBtn.disabled = false;
                    if (window.useAiSummary && typeof showAiState === 'function') showAiState(false, false, false, false);
                }
            });

            clearFileBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                fileInput.value = '';
                fileUploadedBox.classList.add('hidden');
                dropZoneText.innerHTML = dropZoneDefaultHtml;
                aiStatus = 'idle';
                submitBtn.disabled = false;
                if (window.useAiSummary && typeof showAiState === 'function') showAiState(false, false, false, false);
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
                    if (window.useAiSummary && window.requestAISummary) window.requestAISummary();
                }
            });

            form.addEventListener('submit', function (e) {
                var hasFile = fileInput.files && fileInput.files.length > 0;
                var hasUrl = document.getElementById('document_url').value.trim() !== '';
                if (!hasFile && !hasUrl) {
                    e.preventDefault();
                    docError.classList.remove('hidden');
                    docError.scrollIntoView({ behavior: 'smooth' });
                    return;
                }
                if (window.useAiSummary && hasFile && aiStatus === 'pending') {
                    e.preventDefault();
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
                        docError.textContent = 'Something went wrong. Please try again.';
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
