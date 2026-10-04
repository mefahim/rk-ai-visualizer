(function () {
    'use strict';

    document.querySelectorAll('[data-rkaiviz]').forEach(function (root) {
        var form = root.querySelector('[data-viz-form]');
        if (!form) { return; }
        var leadForm = root.querySelector('[data-viz-leadform]');
        var lead = root.querySelector('[data-viz-lead]');
        var file = form.querySelector('input[type=file]');
        var image = root.querySelector('[data-viz-img]');
        var empty = root.querySelector('[data-viz-empty]');
        var busy = root.querySelector('[data-viz-busy]');
        var done = root.querySelector('[data-viz-done]');
        var error = root.querySelector('[data-viz-error]');
        var quota = root.querySelector('[data-viz-quota]');
        var submit = form.querySelector('[data-viz-submit]');
        var leadSubmit = leadForm ? leadForm.querySelector('button[type=submit]') : null;
        var api = root.getAttribute('data-api');
        var original = null;
        var generating = false;
        var quotaBlocked = false;
        var leadSubmitting = false;

        function show(element, visible) {
            if (element) { element.hidden = !visible; }
        }
        function say(element, message) {
            if (element) {
                element.textContent = message || '';
                show(element, !!message);
            }
        }
        function updateSubmit() {
            if (submit) { submit.disabled = generating || quotaBlocked; }
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
        function setQuota(state) {
            if (!state) { return; }
            if (quota) { quota.textContent = state.message || ''; }
            quotaBlocked = state.available === false || (state.canGenerate === false && state.phase !== 'needs_lead');
            updateSubmit();
        }
        function refresh() {
            request('quota').then(function (result) {
                setQuota(result.data);
            }).catch(function () {});
        }
        function updateVisibility() {
            form.querySelectorAll('[data-visible-field],[data-required-field]').forEach(function (wrapper) {
                function matches(id, expected) {
                    var controls = form.elements[id];
                    var control = controls && controls.length && controls[0].type === 'radio'
                        ? (form.querySelector('[name="' + id + '"]:checked') || null)
                        : controls;
                    if (!control) { return false; }
                    var value = control.type === 'checkbox' ? (control.checked ? '1' : '0') : control.value;
                    return String(value) === String(expected);
                }
                if (wrapper.dataset.visibleField) {
                    wrapper.hidden = !matches(wrapper.dataset.visibleField, wrapper.dataset.visibleEquals);
                }
                if (wrapper.dataset.requiredField) {
                    var required = matches(wrapper.dataset.requiredField, wrapper.dataset.requiredEquals);
                    wrapper.querySelectorAll('input,select,textarea').forEach(function (control) {
                        control.required = required;
                    });
                }
            });
        }
        function complete(url) {
            if (!image || typeof url !== 'string' || !url) {
                setGenerating(false);
                say(error, 'The visualization was generated, but could not be displayed.');
                return;
            }
            image.onload = function () {
                show(busy, false);
                show(empty, false);
                show(image, true);
                show(done, true);
                var originalImage = root.querySelector('[data-viz-orig]');
                if (originalImage && original) { originalImage.src = original; }
                setGenerating(false);
            };
            image.onerror = function () {
                image.onload = null;
                image.onerror = null;
                show(busy, false);
                say(error, 'The visualization was generated, but it could not be displayed.');
                if (original) { image.src = original; show(image, true); }
                setGenerating(false);
                refresh();
            };
            image.src = url;
        }
        function poll(id, started) {
            if (Date.now() - started > 300000) {
                say(error, 'The visualization is taking longer than expected. Please try again.');
                show(busy, false);
                setGenerating(false);
                refresh();
                return;
            }
            setTimeout(function () {
                request('status?job=' + encodeURIComponent(id)).then(function (result) {
                    if (result.response.ok && result.data.status === 'pending') {
                        poll(id, started);
                        return;
                    }
                    if (result.response.ok && result.data.status === 'success') {
                        complete(result.data.imageUrl);
                        return;
                    }
                    say(error, result.data.message || 'We could not generate the visualization. Please try again.');
                    show(busy, false);
                    setGenerating(false);
                    refresh();
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
            body.set('options', JSON.stringify(options));
            say(error, '');
            show(done, false);
            show(busy, true);
            setGenerating(true);
            request('generate', { method: 'POST', body: body }).then(function (result) {
                var data = result.data;
                if (data.kind === 'quota_denied' && data.phase === 'needs_lead' && lead && lead.showModal) {
                    setQuota(data);
                    show(busy, false);
                    setGenerating(false);
                    lead.showModal();
                    return;
                }
                if (!result.response.ok) {
                    say(error, data.message || 'Please review the fields and try again.');
                    show(busy, false);
                    setGenerating(false);
                    setQuota(data);
                    return;
                }
                if (data.quota) { setQuota(data.quota); }
                if (data.status === 'success') {
                    complete(data.imageUrl);
                } else if (data.status === 'pending' && data.job) {
                    poll(data.job, Date.now());
                } else {
                    say(error, 'We could not generate the visualization. Please try again.');
                    show(busy, false);
                    setGenerating(false);
                    refresh();
                }
            }).catch(function () {
                show(busy, false);
                setGenerating(false);
                say(error, 'We could not send your photo. Please try again.');
                refresh();
            });
        }

        form.addEventListener('change', updateVisibility);
        if (file) {
            file.addEventListener('change', function () {
                if (original) { URL.revokeObjectURL(original); }
                original = file.files[0] ? URL.createObjectURL(file.files[0]) : null;
                show(done, false);
                say(error, '');
                if (original) {
                    image.src = original;
                    show(image, true);
                    show(empty, false);
                } else {
                    show(image, false);
                    show(empty, true);
                }
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
                        say(root.querySelector('[data-viz-lead-error]'), result.data.message || 'Please check your contact details.');
                        return;
                    }
                    setQuota(result.data);
                    lead.close();
                    generate();
                }).catch(function () {
                    say(root.querySelector('[data-viz-lead-error]'), 'We could not send that. Please try again.');
                }).then(function () {
                    leadSubmitting = false;
                    if (leadSubmit) { leadSubmit.disabled = false; }
                });
            });
        }
        refresh();
        updateVisibility();
    });
}());
