'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
    constructor() {
        this.listeners = {};
        this.children = {};
        this.dataset = {};
        this.hidden = false;
        this.disabled = false;
        this.textContent = '';
        this.elements = {};
        this.files = [];
    }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    querySelector(selector) { return this.children[selector] || null; }
    querySelectorAll() { return []; }
    getAttribute(name) { return name === 'data-api' ? '/api/' : null; }
    showModal() { this.open = true; }
    close() { this.open = false; }
    set src(value) {
        this._src = value;
        if (typeof this.onload === 'function') { this.onload(); }
    }
    get src() { return this._src || ''; }
}

class FakeFormData {
    constructor() { this.values = {}; }
    set(key, value) { this.values[key] = value; }
    forEach(callback) {
        Object.keys(this.values).forEach((key) => callback(this.values[key], key));
    }
}

function response(ok, data) {
    return { ok, json: () => Promise.resolve(data) };
}

async function flush() {
    await new Promise((resolve) => setImmediate(resolve));
}

async function run() {
    const root = new Element();
    const form = new Element();
    const file = new Element();
    const submit = new Element();
    const leadForm = new Element();
    const lead = new Element();
    const image = new Element();
    const nodes = {
        '[data-viz-form]': form,
        '[data-viz-leadform]': leadForm,
        '[data-viz-lead]': lead,
        '[data-viz-img]': image,
        '[data-viz-empty]': new Element(),
        '[data-viz-busy]': new Element(),
        '[data-viz-done]': new Element(),
        '[data-viz-error]': new Element(),
        '[data-viz-quota]': new Element(),
        '[data-viz-orig]': new Element(),
    };
    root.children = nodes;
    form.children = { 'input[type=file]': file, '[data-viz-submit]': submit };
    leadForm.children = { 'button[type=submit]': new Element() };
    const document = { querySelectorAll: () => [root] };
    let generateCalls = 0;
    let releaseGenerate;
    let rejectNextGenerate = false;
    const fetch = (url) => {
        if (url === '/api/quota') {
            return Promise.resolve(response(true, { available: true, canGenerate: true, message: 'Ready' }));
        }
        if (url === '/api/generate') {
            generateCalls += 1;
            if (rejectNextGenerate) {
                rejectNextGenerate = false;
                return Promise.reject(new Error('offline'));
            }
            return new Promise((resolve) => { releaseGenerate = resolve; });
        }
        throw new Error('Unexpected request: ' + url);
    };
    const context = vm.createContext({
        document,
        FormData: FakeFormData,
        fetch,
        URL,
        setTimeout,
        Date,
        JSON,
        encodeURIComponent,
    });
    const script = fs.readFileSync(require.resolve('../assets/js/visualizer.js'), 'utf8');
    vm.runInContext(script, context, { filename: 'visualizer.js' });
    await flush();

    const submitEvent = { preventDefault() {} };
    form.listeners.submit(submitEvent);
    assert.equal(generateCalls, 1, 'first submit should send exactly one request');
    assert.equal(submit.disabled, true, 'submit should be disabled while the request is pending');
    form.listeners.submit(submitEvent);
    assert.equal(generateCalls, 1, 'a second submit while pending must not send another request');

    releaseGenerate(response(true, {
        status: 'success',
        imageUrl: 'https://cdn.example.com/result.png',
        quota: { available: true, canGenerate: true, message: 'One remaining' },
    }));
    await flush();
    await flush();
    assert.equal(nodes['[data-viz-done]'].hidden, false, 'success state should be displayed');
    assert.equal(submit.disabled, false, 'submit should be restored after success');

    form.listeners.submit(submitEvent);
    assert.equal(submit.disabled, true, 'submit should lock during a later request');
    releaseGenerate(response(false, { message: 'Provider unavailable' }));
    await flush();
    await flush();
    assert.equal(submit.disabled, false, 'submit should be restored after an HTTP error');
    assert.equal(nodes['[data-viz-busy]'].hidden, true, 'loading state should be hidden after an HTTP error');
    assert.equal(nodes['[data-viz-error]'].textContent, 'Provider unavailable', 'HTTP error should be rendered as text');

    rejectNextGenerate = true;
    form.listeners.submit(submitEvent);
    await flush();
    await flush();
    assert.equal(submit.disabled, false, 'submit should be restored after a network error');
    assert.equal(nodes['[data-viz-busy]'].hidden, true, 'loading state should be hidden after a network error');
    assert.equal(nodes['[data-viz-error]'].hidden, false, 'network failure should show a recoverable error');
    console.log('PASS frontend locks duplicate submits and restores success/error states');
}

run().catch((error) => {
    console.error(error.stack || error);
    process.exitCode = 1;
});
