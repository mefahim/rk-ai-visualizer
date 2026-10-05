(function () {
    'use strict';

    document.querySelectorAll('[data-rkaiviz]').forEach(function (root) {
        var form = root.querySelector('[data-viz-form]');
        if (!form) { return; }

        var leadForm = root.querySelector('[data-viz-leadform]');
        var lead = root.querySelector('[data-viz-lead]');
        var file = form.querySelector('[data-viz-file]');
        var dropzone = root.querySelector('[data-viz-dropzone]');
        var image = root.querySelector('[data-viz-img]');
        var empty = root.querySelector('[data-viz-empty]');
        var busy = root.querySelector('[data-viz-busy]');
        var busyTitle = root.querySelector('[data-viz-busy-title]');
        var busyCopy = root.querySelector('[data-viz-busy-copy]');
        var done = root.querySelector('[data-viz-done]');
        var error = root.querySelector('[data-viz-error]');
        var quota = root.querySelector('[data-viz-quota]');
        var summary = root.querySelector('[data-viz-summary]');
        var fileStatus = root.querySelector('[data-viz-file-status]');
        var uploadActions = root.querySelector('[data-viz-upload-actions]');
        var submit = form.querySelector('[data-viz-submit]');
        var submitLabel = submit ? submit.querySelector('[data-viz-submit-label]') : null;
        var retry = root.querySelector('[data-viz-retry]');
        var newButton = root.querySelector('[data-viz-new]');
        var removeButton = root.querySelector('[data-viz-remove]');
        var leadSubmit = leadForm ? leadForm.querySelector('[data-viz-lead-submit]') : null;
        var api = root.getAttribute('data-api') || '';
        var allowedTypes = (root.getAttribute('data-allowed-types') || 'image/jpeg,image/png,image/webp').split(',');
        var maxSize = parseInt(root.getAttribute('data-max-size') || '0', 10);
        var minWidth = parseInt(root.getAttribute('data-min-width') || '1', 10);
        var minHeight = parseInt(root.getAttribute('data-min-height') || '1', 10);
        var original = null;
        var selectedFile = null;
        var generating = false;
        var quotaBlocked = false;
        var leadSubmitting = false;
        var fileCheckId = 0;
        var originalSubmitText = submitLabel ? submitLabel.textContent : '';

        function show(element, visible) {
            if (element) { element.hidden = !visible; }
        }

        function say(element, message) {
            if (!element) { return; }
            element.textContent = message || '';
            show(element, !!message);
        }

        function setState(message) {
            var state = root.querySelector('[data-viz-state-label]') || root.querySelector('[data-viz-state]');
            if (state && message) { state.textContent = message; }
        }

        function setStep(step) {
            if (root.setAttribute) { root.setAttribute('data-step', step); }
            root.querySelectorAll('[data-viz-step-item]').forEach(function (item) {
                var itemStep = item.getAttribute('data-viz-step-item');
                var current = itemStep === step;
                if (item.classList) {
                    item.classList.toggle('is-current', current);
                    item.classList.toggle('is-complete', itemStep === 'upload' && step !== 'upload' || itemStep === 'configure' && step === 'result');
                }
                if (current && item.setAttribute) { item.setAttribute('aria-current', 'step'); }
                else if (item.removeAttribute) { item.removeAttribute('aria-current'); }
            });
        }

        function updateSubmit() {
            var disabled = generating || quotaBlocked;
            if (submit) { submit.disabled = disabled; }
            if (retry) { retry.disabled = disabled; }
            if (submitLabel) { submitLabel.textContent = generating ? 'Creating your visualization…' : originalSubmitText; }
        }

        function setGenerating(value) {
            generating = value;
            updateSubmit();
        }

        function request(path, options) {
            options = options || {};
            options.credentials = 'same-origin';
            return fetch(api + path, options).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    return { response: response, data: data };
                });
            });
        }

        function safeMessage(data, fallback) {
            data = data || {};
            if (data.phase === 'rate_limited') { return 'There have been too many requests from this network. Please wait a little and try again.'; }
            if (data.phase === 'cooldown') { return 'Your free visualizations are on cooldown. Please come back later.'; }
            if (data.phase === 'needs_lead') { return 'Share your contact details to unlock one more visualization.'; }
            var messages = {
                rk_viz_no_image: 'Add a room photo before you continue.',
                rk_viz_too_large: 'This photo is larger than the upload limit. Choose a smaller photo.',
                rk_viz_bad_type: 'This file type is not supported. Choose a JPG, PNG, or WebP photo.',
                rk_viz_too_small: 'This photo is too small for a clear result. Choose a higher-resolution image.',
                rk_viz_bad_options: 'Please check your design choices and try again.',
                rk_viz_unavailable: 'This visualizer is temporarily unavailable. Please try again later.',
                rk_viz_timeout: 'This is taking longer than expected. Please try again in a moment.',
                rk_viz_provider: 'We could not create that visualization right now. Please try again.',
                rk_viz_lead_invalid: 'Please check your name and email, then try again.',
                rk_viz_rate: 'There have been too many requests. Please wait a little and try again.'
            };
            return messages[data.code] || fallback;
        }

        function setQuota(state) {
            if (!state) { return; }
            if (quota) {
                quota.textContent = state.available === false
                    ? 'This visualizer is temporarily unavailable. Please try again later.'
                    : (state.code || state.kind === 'quota_denied'
                        ? safeMessage(state, 'Please review your available generations and try again.')
                        : (state.message || 'Your available generations are up to date.'));
            }
            quotaBlocked = state.available === false || (state.canGenerate === false && state.phase !== 'needs_lead');
            updateSubmit();
        }

        function refresh() {
            request('quota').then(function (result) {
                if (result.response.ok) { setQuota(result.data); }
                else { say(quota, 'Generation availability could not be checked. You can still try your request.'); }
            }).catch(function () {
                say(quota, 'Generation availability could not be checked. You can still try your request.');
            });
        }

        function conditionMatches(id, expected) {
            var controls = form.elements[id];
            var control = controls && controls.length && controls[0].type === 'radio'
                ? (form.querySelector('[name="' + id + '"]:checked') || null)
                : controls;
            if (!control) { return false; }
            var value = control.type === 'checkbox' ? (control.checked ? '1' : '0') : control.value;
            return String(value) === String(expected);
        }

        function updateVisibility() {
            form.querySelectorAll('[data-visible-field],[data-required-field]').forEach(function (wrapper) {
                if (wrapper.dataset.visibleField) {
                    wrapper.hidden = !conditionMatches(wrapper.dataset.visibleField, wrapper.dataset.visibleEquals);
                }
                if (wrapper.dataset.requiredField) {
                    var required = conditionMatches(wrapper.dataset.requiredField, wrapper.dataset.requiredEquals);
                    wrapper.querySelectorAll('input,select,textarea').forEach(function (control) { control.required = required; });
                }
            });
        }

        function updateRangeValues() {
            root.querySelectorAll('[data-viz-range-for]').forEach(function (output) {
                var control = form.elements[output.getAttribute('data-viz-range-for')];
                if (control) { output.textContent = control.value; }
            });
        }

        function updateSummary() {
            if (!summary) { return; }
            var chosen = [];
            form.querySelectorAll('[data-viz-field]').forEach(function (wrapper) {
                if (wrapper.hidden) { return; }
                var label = wrapper.getAttribute('data-field-label') || '';
                var type = wrapper.getAttribute('data-field-type') || '';
                var value = '';
                if (type === 'radio' || type === 'cards' || type === 'swatches' || type === 'image') {
                    var selected = wrapper.querySelector('input:checked');
                    if (selected) {
                        var choiceLabel = selected.parentNode && selected.parentNode.querySelector('.rkaiviz-choice-label');
                        value = choiceLabel ? choiceLabel.textContent.trim() : selected.value;
                    }
                } else {
                    var control = wrapper.querySelector('input,select,textarea');
                    if (control && control.type === 'checkbox') { value = control.checked ? 'Yes' : ''; }
                    else if (control && control.tagName === 'SELECT') {
                        if (control.selectedIndex >= 0 && control.options && control.options[control.selectedIndex]) { value = control.options[control.selectedIndex].text; }
                    } else if (control) { value = String(control.value || '').trim(); }
                }
                if (value) { chosen.push(label + ': ' + value); }
            });
            summary.textContent = chosen.length ? chosen.join(' · ') : 'Your selected design details will appear here.';
        }

        function clearObjectUrl() {
            if (original && window.URL && typeof window.URL.revokeObjectURL === 'function') { window.URL.revokeObjectURL(original); }
            original = null;
        }

        function clearPhoto(resetFileInput) {
            fileCheckId++;
            selectedFile = null;
            clearObjectUrl();
            if (resetFileInput && file) { file.value = ''; file.required = true; }
            if (image) {
                if (image.removeAttribute) { image.removeAttribute('src'); }
                else { image.src = ''; }
                image.alt = 'Uploaded photo preview';
            }
            var originalImage = root.querySelector('[data-viz-orig]');
            if (originalImage) {
                if (originalImage.removeAttribute) { originalImage.removeAttribute('src'); }
                else { originalImage.src = ''; }
                show(originalImage, false);
            }
            show(image, false);
            show(empty, true);
            show(busy, false);
            show(done, false);
            show(uploadActions, false);
            say(fileStatus, '');
            say(error, '');
            setStep('upload');
            setState('Ready to begin');
            updateSubmit();
            updateSummary();
        }

        function rejectFile(message, candidateUrl) {
            if (candidateUrl && window.URL && typeof window.URL.revokeObjectURL === 'function') { window.URL.revokeObjectURL(candidateUrl); }
            if (file) { file.value = ''; file.required = true; }
            selectedFile = null;
            clearObjectUrl();
            if (image) {
                if (image.removeAttribute) { image.removeAttribute('src'); }
                else { image.src = ''; }
            }
            show(image, false);
            show(empty, true);
            show(done, false);
            show(uploadActions, false);
            say(fileStatus, '');
            say(error, message);
            setStep('upload');
            setState('Photo needs attention');
            updateSubmit();
            updateSummary();
        }

        function mimeForName(name) {
            var extension = String(name || '').toLowerCase().split('.').pop();
            return extension === 'jpg' || extension === 'jpeg' ? 'image/jpeg' : (extension === 'png' ? 'image/png' : (extension === 'webp' ? 'image/webp' : ''));
        }

        function handleFile(candidate) {
            if (!candidate) { return; }
            var thisCheck = ++fileCheckId;
            var mime = candidate.type || mimeForName(candidate.name);
            if (allowedTypes.indexOf(mime) === -1) {
                rejectFile('Choose a JPG, PNG, or WebP image.', null);
                return;
            }
            if (maxSize > 0 && candidate.size > maxSize) {
                rejectFile('This photo is too large. Choose an image under the size limit shown above.', null);
                return;
            }
            if (!window.URL || typeof window.URL.createObjectURL !== 'function' || typeof window.Image !== 'function') {
                rejectFile('This browser cannot preview that photo. Please try a current browser.', null);
                return;
            }
            say(error, '');
            say(fileStatus, 'Checking photo dimensions…');
            setState('Checking your photo');
            var candidateUrl = window.URL.createObjectURL(candidate);
            var probe = new window.Image();
            probe.onload = function () {
                if (thisCheck !== fileCheckId) { window.URL.revokeObjectURL(candidateUrl); return; }
                if (probe.naturalWidth < minWidth || probe.naturalHeight < minHeight) {
                    rejectFile('This photo is too small. Choose an image at least ' + minWidth + ' × ' + minHeight + ' pixels.', candidateUrl);
                    return;
                }
                clearObjectUrl();
                original = candidateUrl;
                selectedFile = candidate;
                if (file && !file.files.length) { file.required = false; }
                if (image) {
                    image.onload = null;
                    image.onerror = null;
                    image.alt = 'Preview of the photo selected for your visualization';
                    image.src = candidateUrl;
                }
                show(image, true);
                show(empty, false);
                show(done, false);
                show(uploadActions, true);
                say(fileStatus, 'Photo ready. Your original image will remain available as a reference.');
                setStep('configure');
                setState('Photo added — choose your design details');
                updateSubmit();
                updateSummary();
            };
            probe.onerror = function () {
                if (thisCheck !== fileCheckId) { window.URL.revokeObjectURL(candidateUrl); return; }
                rejectFile('We could not read that photo. Try a different JPG, PNG, or WebP image.', candidateUrl);
            };
            probe.src = candidateUrl;
        }

        function complete(url) {
            if (!image || typeof url !== 'string' || !url) {
                show(busy, false);
                setGenerating(false);
                say(error, 'We could not display the visualization. Your photo and choices are still here; please try again.');
                setStep('configure');
                setState('Ready to try again');
                return;
            }
            image.onload = function () {
                show(busy, false);
                show(empty, false);
                show(image, true);
                show(done, true);
                image.alt = 'AI-generated visualization result';
                var originalImage = root.querySelector('[data-viz-orig]');
                if (originalImage && original) {
                    originalImage.src = original;
                    show(originalImage, true);
                }
                var viewLabel = root.querySelector('[data-viz-view-label]');
                if (viewLabel) { viewLabel.textContent = 'Concept ready'; }
                setGenerating(false);
                setStep('result');
                setState('Your visualization is ready');
            };
            image.onerror = function () {
                image.onload = null;
                image.onerror = null;
                show(busy, false);
                say(error, 'The visualization was created, but could not be displayed. Your photo and choices are still here.');
                if (original) {
                    image.src = original;
                    image.alt = 'Preview of the original uploaded photo';
                    show(image, true);
                }
                show(done, false);
                setGenerating(false);
                setStep('configure');
                setState('Ready to try again');
                refresh();
            };
            image.src = url;
        }

        function showFailure(data, fallback) {
            say(error, safeMessage(data, fallback));
            show(busy, false);
            setGenerating(false);
            setStep(selectedFile ? 'configure' : 'upload');
            setState('Ready to try again');
            refresh();
        }

        function poll(id, started) {
            if (Date.now() - started > 300000) {
                showFailure({ code: 'rk_viz_timeout' }, 'This is taking longer than expected. Please try again in a moment.');
                return;
            }
            if (busyTitle) { busyTitle.textContent = 'The image service is working'; }
            if (busyCopy) { busyCopy.textContent = 'Your photo and selected details are being processed. This can take a few minutes.'; }
            setState('Creating your visualization');
            window.setTimeout(function () {
                request('status?job=' + encodeURIComponent(id)).then(function (result) {
                    if (result.response.ok && result.data.status === 'pending') {
                        poll(id, started);
                        return;
                    }
                    if (result.response.ok && result.data.status === 'success') {
                        complete(result.data.imageUrl);
                        return;
                    }
                    showFailure(result.data, 'We could not create that visualization right now. Please try again.');
                }).catch(function () {
                    poll(id, started);
                });
            }, 3000);
        }

        function generate() {
            if (generating) { return; }
            var body = new FormData(form);
            var options = {};
            form.querySelectorAll('input[name],select[name],textarea[name]').forEach(function (control) {
                if (control.name === 'image') { return; }
                if (control.type === 'radio') {
                    if (control.checked) { options[control.name] = control.value; }
                } else if (control.type === 'checkbox') {
                    options[control.name] = control.checked;
                } else {
                    options[control.name] = control.value;
                }
            });
            if (selectedFile && body.set) { body.set('image', selectedFile, selectedFile.name); }
            body.set('options', JSON.stringify(options));
            say(error, '');
            show(done, false);
            show(busy, true);
            if (busyTitle) { busyTitle.textContent = 'Preparing your request'; }
            if (busyCopy) { busyCopy.textContent = 'Your photo and selected details are being sent securely to the image service.'; }
            setGenerating(true);
            setStep('result');
            setState('Creating your visualization');
            request('generate', { method: 'POST', body: body }).then(function (result) {
                var data = result.data || {};
                if (data.kind === 'quota_denied' && data.phase === 'needs_lead' && lead && typeof lead.showModal === 'function') {
                    setQuota(data);
                    show(busy, false);
                    setGenerating(false);
                    setStep('configure');
                    setState('One more detail needed');
                    lead.showModal();
                    return;
                }
                if (!result.response.ok) {
                    showFailure(data, 'We could not create that visualization. Please review your choices and try again.');
                    setQuota(data);
                    return;
                }
                if (data.quota) { setQuota(data.quota); }
                if (data.status === 'success') {
                    complete(data.imageUrl);
                } else if (data.status === 'pending' && data.job) {
                    poll(data.job, Date.now());
                } else {
                    showFailure({}, 'We could not create that visualization right now. Please try again.');
                }
            }).catch(function () {
                showFailure({}, 'We could not send your photo. Check your connection and try again.');
            });
        }

        function startAnother() {
            if (typeof form.reset === 'function') { form.reset(); }
            clearPhoto(true);
            updateVisibility();
            updateRangeValues();
            updateSummary();
            setState('Ready to begin');
        }

        form.addEventListener('change', function (event) {
            if (event.target === file) {
                handleFile(file.files && file.files.length ? file.files[0] : null);
            }
            updateVisibility();
            updateRangeValues();
            updateSummary();
            if (root.getAttribute('data-step') === 'upload' && file && file.files && file.files.length) { setStep('configure'); }
        });

        if (dropzone) {
            dropzone.addEventListener('dragover', function (event) {
                event.preventDefault();
                if (dropzone.classList) { dropzone.classList.add('is-dragging'); }
            });
            dropzone.addEventListener('dragleave', function () {
                if (dropzone.classList) { dropzone.classList.remove('is-dragging'); }
            });
            dropzone.addEventListener('drop', function (event) {
                event.preventDefault();
                if (dropzone.classList) { dropzone.classList.remove('is-dragging'); }
                var files = event.dataTransfer && event.dataTransfer.files;
                if (!files || !files.length) { return; }
                if (file && typeof window.DataTransfer === 'function') {
                    try {
                        var transfer = new window.DataTransfer();
                        transfer.items.add(files[0]);
                        file.files = transfer.files;
                    } catch (errorDuringTransfer) { /* selectedFile remains the submission source */ }
                }
                handleFile(files[0]);
            });
        }

        if (removeButton) { removeButton.addEventListener('click', function () { clearPhoto(true); }); }
        if (newButton) { newButton.addEventListener('click', startAnother); }
        if (retry) {
            retry.addEventListener('click', function () {
                if (typeof form.reportValidity === 'function' && !form.reportValidity()) { return; }
                generate();
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            generate();
        });

        if (lead) {
            var close = lead.querySelector('[data-viz-close]');
            if (close) { close.addEventListener('click', function () { lead.close(); }); }
        }

        if (leadForm) {
            leadForm.addEventListener('submit', function (event) {
                event.preventDefault();
                if (leadSubmitting) { return; }
                leadSubmitting = true;
                if (leadSubmit) { leadSubmit.disabled = true; }
                var body = {};
                new FormData(leadForm).forEach(function (value, key) { body[key] = value; });
                request('lead', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(function (result) {
                    if (!result.response.ok) {
                        say(root.querySelector('[data-viz-lead-error]'), safeMessage(result.data, 'Please check your contact details and try again.'));
                        return;
                    }
                    setQuota(result.data);
                    lead.close();
                    generate();
                }).catch(function () {
                    say(root.querySelector('[data-viz-lead-error]'), 'We could not send that. Check your connection and try again.');
                }).then(function () {
                    leadSubmitting = false;
                    if (leadSubmit) { leadSubmit.disabled = false; }
                });
            });
        }

        refresh();
        updateVisibility();
        updateRangeValues();
        updateSummary();
    });
}());
