'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
    constructor(attributes = {}) {
        this.listeners = {};
        this.children = {};
        this.lists = {};
        this.attributes = { ...attributes };
        this.dataset = {};
        this.hidden = false;
        this.disabled = false;
        this.textContent = '';
        this.elements = {};
        this.files = [];
        this.required = false;
        this.classList = {
            add() {},
            remove() {},
            toggle() {},
        };
    }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    querySelector(selector) { return this.children[selector] || null; }
    querySelectorAll(selector) { return this.lists[selector] || []; }
    getAttribute(name) { return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null; }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    removeAttribute(name) { delete this.attributes[name]; if (name === 'src') { this._src = ''; } }
    showModal() { this.open = true; }
    close() { this.open = false; }
    set src(value) {
        this._src = value;
        if (typeof this.onload === 'function') { this.onload(); }
    }
    get src() { return this._src || ''; }
    set value(value) {
        this._value = value;
        if (value === '') { this.files = []; }
    }
    get value() { return this._value || ''; }
}

class FakeFormData {
    constructor(source) { this.values = source && source.formDataValues ? { ...source.formDataValues } : {}; }
    set(key, value) { this.values[key] = value; }
    forEach(callback) { Object.keys(this.values).forEach((key) => callback(this.values[key], key)); }
}

function response(ok, data) {
    return { ok, json: () => Promise.resolve(data) };
}

async function flush() {
    await new Promise((resolve) => setImmediate(resolve));
}

async function run() {
    let previewDimensions = [900, 650];
    let previewFails = false;
    let objectUrlCounter = 0;
    const revokedUrls = [];
    const timers = [];
    const fakeUrl = {
        createObjectURL() { objectUrlCounter += 1; return 'blob:preview-' + objectUrlCounter; },
        revokeObjectURL(value) { revokedUrls.push(value); },
    };
    class FakeImage {
        constructor() { this.naturalWidth = previewDimensions[0]; this.naturalHeight = previewDimensions[1]; }
        set src(value) { this._src = value; if (previewFails && typeof this.onerror === 'function') { this.onerror(); } else if (typeof this.onload === 'function') { this.onload(); } }
        get src() { return this._src || ''; }
    }

    const root = new Element({
        'data-api': '/api/',
        'data-max-size': String(10 * 1024 * 1024),
        'data-min-width': '640',
        'data-min-height': '480',
        'data-allowed-types': 'image/jpeg,image/png,image/webp',
        'data-step': 'upload',
    });
    const form = new Element();
    const file = new Element();
    file.required = true;
    const submit = new Element();
    const submitLabel = new Element();
    submitLabel.textContent = 'Create visualization';
    submit.children['[data-viz-submit-label]'] = submitLabel;
    const leadForm = new Element();
    const lead = new Element();
    const image = new Element();
    const dropzone = new Element();
    const retry = new Element();
    const newButton = new Element();
    const removeButton = new Element();
    const state = new Element();
    const stateLabel = new Element();
    const stateDot = new Element();
    state.children['[data-viz-state-label]'] = stateLabel;
    state.children['.rkaiviz-ready-dot'] = stateDot;
    const imageField = new Element({ 'data-field-label': 'Material', 'data-field-type': 'image' });
    const imageChoice = new Element();
    imageChoice.value = 'natural';
    const imageChoiceLabel = new Element();
    imageChoiceLabel.textContent = 'Natural oak';
    const imageChoiceParent = new Element();
    imageChoiceParent.children['.rkaiviz-choice-label'] = imageChoiceLabel;
    imageChoice.parentNode = imageChoiceParent;
    imageField.children['input:checked'] = imageChoice;
    const nodes = {
        '[data-viz-form]': form,
        '[data-viz-leadform]': leadForm,
        '[data-viz-lead]': lead,
        '[data-viz-file]': file,
        '[data-viz-dropzone]': dropzone,
        '[data-viz-img]': image,
        '[data-viz-empty]': new Element(),
        '[data-viz-busy]': new Element(),
        '[data-viz-busy-title]': new Element(),
        '[data-viz-busy-copy]': new Element(),
        '[data-viz-done]': new Element(),
        '[data-viz-error]': new Element(),
        '[data-viz-quota]': new Element(),
        '[data-viz-summary]': new Element(),
        '[data-viz-file-status]': new Element(),
        '[data-viz-upload-actions]': new Element(),
        '[data-viz-orig]': new Element(),
        '[data-viz-submit]': submit,
        '[data-viz-retry]': retry,
        '[data-viz-new]': newButton,
        '[data-viz-remove]': removeButton,
        '[data-viz-view-label]': new Element(),
        '[data-viz-state]': state,
        '[data-viz-state-label]': stateLabel,
        '[data-viz-lead-error]': new Element(),
    };
    root.children = nodes;
    form.children = { '[data-viz-file]': file, '[data-viz-submit]': submit };
    form.lists['[data-viz-field]'] = [imageField];
    form.elements = {};
    form.reportValidity = () => true;
    form.reset = () => { file.value = ''; };
    leadForm.children = { '[data-viz-lead-submit]': new Element() };
    leadForm.formDataValues = { name: 'Test User', email: 'test@example.com' };
    const document = { querySelectorAll: () => [root] };
    let generateCalls = 0;
    let releaseGenerate;
    let rejectNextGenerate = false;
    let nextGenerate = null;
    let nextStatus = null;
    let rawOptions = null;
    let uploadedFile = null;
    const requestLog = [];
    const fetch = (url, options = {}) => {
        requestLog.push({ url, options });
        if (url === '/api/quota') {
            return Promise.resolve(response(true, { available: true, canGenerate: true, message: 'Ready' }));
        }
        if (url === '/api/generate') {
            generateCalls += 1;
            if (options.body) {
                rawOptions = options.body.values && options.body.values.options;
                uploadedFile = options.body.values && options.body.values.image;
            }
            if (rejectNextGenerate) {
                rejectNextGenerate = false;
                return Promise.reject(new Error('offline'));
            }
            const reply = nextGenerate;
            nextGenerate = null;
            if (reply === 'deferred') {
                return new Promise((resolve) => { releaseGenerate = resolve; });
            }
            return Promise.resolve(reply || response(true, {
                status: 'success', imageUrl: 'https://cdn.example.com/default.png',
                quota: { available: true, canGenerate: true, message: 'One remaining' },
            }));
        }
        if (url.startsWith('/api/status?job=')) {
            const reply = nextStatus;
            nextStatus = null;
            return Promise.resolve(reply || response(false, { code: 'rk_viz_no_job' }));
        }
        if (url === '/api/lead') {
            return Promise.resolve(response(true, { available: true, canGenerate: true, message: 'One more visualization unlocked' }));
        }
        throw new Error('Unexpected request: ' + url);
    };
    const window = {
        URL: fakeUrl,
        Image: FakeImage,
        setTimeout(callback) { timers.push(callback); return timers.length; },
    };
    const context = vm.createContext({
        document,
        window,
        FormData: FakeFormData,
        fetch,
        setTimeout,
        Date,
        JSON,
        encodeURIComponent,
    });
    const script = fs.readFileSync(require.resolve('../assets/js/visualizer.js'), 'utf8');
    vm.runInContext(script, context, { filename: 'visualizer.js' });
    await flush();
    form.listeners.change({ target: imageChoice });
    assert.equal(nodes['[data-viz-summary]'].textContent, 'Material: Natural oak', 'image choices should contribute their label to the configuration summary');

    file.files = [{ type: 'image/gif', name: 'room.gif', size: 1000 }];
    form.listeners.change({ target: file });
    assert.equal(nodes['[data-viz-error]'].textContent, 'Choose a JPG, PNG, or WebP image.', 'unsupported upload should show safe, actionable feedback');
    assert.equal(nodes['[data-viz-img]'].hidden, true, 'invalid file must not enter the preview');

    file.files = [{ type: 'image/png', name: 'large.png', size: 11 * 1024 * 1024 }];
    form.listeners.change({ target: file });
    assert.match(nodes['[data-viz-error]'].textContent, /too large/i, 'oversized upload should be rejected before preview');
    previewFails = true;
    file.files = [{ type: 'image/jpeg', name: 'broken.jpg', size: 1000 }];
    form.listeners.change({ target: file });
    previewFails = false;
    assert.match(nodes['[data-viz-error]'].textContent, /could not read/i, 'corrupt image should have a recoverable error');

    const photo = { type: 'image/jpeg', name: 'room.jpg', size: 1024 * 1024 };
    file.files = [photo];
    form.listeners.change({ target: file });
    assert.equal(nodes['[data-viz-img]'].hidden, false, 'valid upload should appear in the preview');
    assert.equal(nodes['[data-viz-file-status]'].textContent, 'Photo ready. Your original image will remain available as a reference.', 'valid photo should show a ready state');
    assert.equal(nodes['[data-viz-upload-actions]'].hidden, false, 'replace/remove actions should appear after upload');
    const firstPreviewUrl = nodes['[data-viz-img]'].src;
    assert.equal(stateLabel.textContent, 'Photo added — choose your design details', 'status updates should target the nested accessible label');
    assert.ok(state.children['.rkaiviz-ready-dot'], 'updating status copy must preserve the decorative status dot');
    removeButton.listeners.click();
    assert.equal(nodes['[data-viz-img]'].hidden, true, 'remove action should clear the preview');
    assert.equal(nodes['[data-viz-empty]'].hidden, false, 'remove action should restore the empty state');
    assert.ok(revokedUrls.includes(firstPreviewUrl), 'remove action should revoke the previous local preview URL');
    file.files = [photo];
    form.listeners.change({ target: file });
    const replacementPreviewUrl = nodes['[data-viz-img]'].src;

    const submitEvent = { preventDefault() {} };
    nextGenerate = 'deferred';
    form.listeners.submit(submitEvent);
    assert.equal(generateCalls, 1, 'first submit should send exactly one request');
    assert.equal(submit.disabled, true, 'submit should be disabled while the request is pending');
    assert.equal(uploadedFile, photo, 'the selected photo should be attached to the existing generate request');
    form.listeners.submit(submitEvent);
    assert.equal(generateCalls, 1, 'a second submit while pending must not send another request');
    releaseGenerate(response(true, {
        status: 'success', imageUrl: 'https://cdn.example.com/result.png',
        quota: { available: true, canGenerate: true, message: 'One remaining' },
    }));
    await flush();
    await flush();
    assert.equal(nodes['[data-viz-done]'].hidden, false, 'success state should be displayed');
    assert.equal(nodes['[data-viz-img]'].src, 'https://cdn.example.com/result.png', 'success should replace the preview with the generated result');
    assert.equal(nodes['[data-viz-orig]'].src, replacementPreviewUrl, 'original image reference should be retained');
    assert.equal(submit.disabled, false, 'submit should be restored after success');

    nextGenerate = response(false, { code: 'rk_viz_provider', message: 'Internal provider trace: secret=do-not-show' });
    form.listeners.submit(submitEvent);
    await flush();
    await flush();
    assert.equal(submit.disabled, false, 'submit should be restored after an HTTP error');
    assert.equal(nodes['[data-viz-busy]'].hidden, true, 'loading state should be hidden after an HTTP error');
    assert.equal(nodes['[data-viz-error]'].textContent, 'We could not create that visualization right now. Please try again.', 'known provider errors should use friendly copy');
    assert.equal(nodes['[data-viz-error]'].textContent.includes('secret='), false, 'raw provider details must not be exposed');
    assert.equal(nodes['[data-viz-quota]'].textContent.includes('secret='), false, 'raw provider details must not leak into quota/status copy');

    nextGenerate = response(false, { code: 'rk_viz_timeout', message: 'Raw remote timeout detail' });
    form.listeners.submit(submitEvent);
    await flush();
    await flush();
    assert.equal(nodes['[data-viz-error]'].textContent, 'This is taking longer than expected. Please try again in a moment.', 'timeout errors should be mapped to friendly copy');

    rejectNextGenerate = true;
    form.listeners.submit(submitEvent);
    await flush();
    await flush();
    assert.equal(submit.disabled, false, 'submit should be restored after a network error');
    assert.equal(nodes['[data-viz-busy]'].hidden, true, 'loading state should be hidden after a network error');
    assert.equal(nodes['[data-viz-error]'].textContent, 'We could not send your photo. Check your connection and try again.', 'network failures should offer actionable retry guidance');

    nextGenerate = response(true, { status: 'unavailable' });
    form.listeners.submit(submitEvent);
    await flush();
    await flush();
    assert.equal(nodes['[data-viz-error]'].textContent, 'We could not create that visualization right now. Please try again.', 'missing result payload should recover to a useful error state');

    nextGenerate = response(true, { status: 'pending', job: 'job-123', quota: { available: true, canGenerate: true, message: 'One remaining' } });
    nextStatus = response(true, { status: 'success', imageUrl: 'https://cdn.example.com/polled.png' });
    form.listeners.submit(submitEvent);
    await flush();
    assert.equal(nodes['[data-viz-busy-title]'].textContent, 'The image service is working', 'pending work should have truthful status copy');
    assert.equal(timers.length, 1, 'pending generation should schedule a status poll');
    timers.shift()();
    await flush();
    await flush();
    assert.equal(nodes['[data-viz-img]'].src, 'https://cdn.example.com/polled.png', 'poll completion should display the result');
    assert.equal(nodes['[data-viz-done]'].hidden, false, 'polled result should use the same completion state');

    nextGenerate = response(false, { kind: 'quota_denied', phase: 'needs_lead', canGenerate: false, message: 'internal quota metadata' });
    form.listeners.submit(submitEvent);
    await flush();
    assert.equal(lead.open, true, 'needs_lead quota phase should open the existing lead dialog');
    nextGenerate = response(true, { status: 'success', imageUrl: 'https://cdn.example.com/lead-unlocked.png', quota: { available: true, canGenerate: true, message: 'Unlocked' } });
    leadForm.listeners.submit({ preventDefault() {} });
    await flush();
    await flush();
    await flush();
    assert.equal(lead.open, false, 'successful lead unlock should close the dialog');
    assert.equal(nodes['[data-viz-img]'].src, 'https://cdn.example.com/lead-unlocked.png', 'successful lead unlock should resume the same generation flow');

    newButton.listeners.click();
    assert.equal(nodes['[data-viz-done]'].hidden, true, 'start another should clear the prior result');
    assert.equal(nodes['[data-viz-empty]'].hidden, false, 'start another should restore the empty preview');
    assert.equal(nodes['[data-viz-upload-actions]'].hidden, true, 'start another should clear upload actions');
    assert.ok(revokedUrls.includes(replacementPreviewUrl), 'reset should release the local object URL');

    const droppedPhoto = { type: 'image/webp', name: 'dropped.webp', size: 2048 };
    dropzone.listeners.drop({ preventDefault() {}, dataTransfer: { files: [droppedPhoto] } });
    assert.equal(nodes['[data-viz-img]'].hidden, false, 'drag-and-drop should use the same preview flow as browsing');
    assert.equal(nodes['[data-viz-file-status]'].textContent, 'Photo ready. Your original image will remain available as a reference.', 'drop should produce the normal ready state');
    removeButton.listeners.click();

    previewDimensions = [200, 200];
    file.files = [{ type: 'image/png', name: 'small.png', size: 2000 }];
    form.listeners.change({ target: file });
    assert.equal(nodes['[data-viz-error]'].textContent, 'This photo is too small. Choose an image at least 640 × 480 pixels.', 'small images should be rejected before generation');

    const css = fs.readFileSync(require.resolve('../assets/css/visualizer.css'), 'utf8');
    assert.match(css, /\.rk-ai-visualizer\s+\[hidden\]\s*\{\s*display:\s*none\s*!important/i, 'hidden UI states must be protected from theme display rules');
    assert.match(css, /@media\s*\(max-width:\s*560px\)/, 'public UI must include a narrow-mobile layout');
    assert.match(css, /:focus-visible/, 'keyboard focus should be visibly styled');
    assert.match(css, /\.rk-ai-visualizer \.rkaiviz-preview-panel/, 'public styles should be namespaced under the plugin root');
    console.log('PASS public frontend handles upload, preview, generation, polling, safe errors, lead unlock, and reset states');
}

run().catch((error) => {
    console.error(error.stack || error);
    process.exitCode = 1;
});
