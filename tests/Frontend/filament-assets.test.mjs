import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';

function events() {
    const listeners = new Map();
    return {
        addEventListener(type, callback) {
            if (!listeners.has(type)) listeners.set(type, new Set());
            listeners.get(type).add(callback);
        },
        removeEventListener(type, callback) {
            listeners.get(type)?.delete(callback);
        },
        dispatchEvent(event) {
            for (const callback of listeners.get(event.type) ?? []) callback(event);
            return true;
        },
    };
}

function loadAsset(asset, { redirectBatches = [] } = {}) {
    const components = new Map();
    const stores = new Map();
    const ticks = [];
    const timers = [];
    const document = {
        ...events(),
        documentElement: { classList: { add() {}, remove() {} } },
        querySelector() { return null; },
        createElement() { return { style: {}, ...events() }; },
    };
    const context = {
        ...events(),
        document,
        navigator: { platform: 'Linux', userAgent: '' },
        localStorage: { getItem() { return null; }, setItem() {} },
        sessionStorage: { getItem() { return JSON.stringify(redirectBatches); }, setItem() {}, removeItem() {} },
        getComputedStyle() { return { getPropertyValue() { return '64rem'; } }; },
        matchMedia() { return { matches: false, addEventListener() {}, removeEventListener() {} }; },
        requestAnimationFrame() {},
        setTimeout(callback) { timers.push(callback); return timers.length; },
        clearTimeout() {},
        CustomEvent: class { constructor(type, { detail } = {}) { this.type = type; this.detail = detail; } },
        Livewire: { interceptRequest() {}, interceptMessage() {} },
        Alpine: {
            data(name, factory) { components.set(name, factory); },
            store(name, value) { if (arguments.length > 1) stores.set(name, value); return stores.get(name); },
            plugin() {},
            effect() {},
            $persist(value) { return { as() { return value; } }; },
            nextTick(callback) { ticks.push(callback); },
        },
    };
    context.window = context;
    vm.createContext(context);
    vm.runInContext(readFileSync(new URL(`../../public/js/filament/${asset}`, import.meta.url), 'utf8'), context);
    document.dispatchEvent({ type: 'alpine:init' });
    return {
        components, stores, context, timers,
        flushTicks() { while (ticks.length) ticks.shift()(); },
    };
}

test('mobile initialization preserves the independent collapsed desktop sidebar preference', () => {
    const { stores } = loadAsset('filament/app.js');
    const sidebar = stores.get('sidebar');
    sidebar.isOpen = true;
    sidebar.isOpenDesktop = false;
    sidebar.setUpMediaQuery();
    assert.equal(sidebar.isOpen, false);
    assert.equal(sidebar.isOpenDesktop, false);
    sidebar.desktopMediaQueryListener({ matches: true });
    assert.equal(sidebar.isOpen, false);
});

test('quiet modal close releases trapping and scrolling without dispatching a close event or restoring focus', () => {
    const { components } = loadAsset('support/support.js');
    const modal = components.get('filamentModal')({ id: 'test-modal' });
    let releases = 0;
    modal.isOpen = modal.isTrapActive = modal.isHoldingScrollLock = true;
    modal.releaseScrollLock = () => { modal.isHoldingScrollLock = false; releases++; };
    modal.$dispatch = modal.restorePreviouslyFocusedElement = () => assert.fail('Quiet close must stay quiet.');
    modal.closeQuietly();
    assert.equal(modal.isOpen, false);
    assert.equal(modal.isTrapActive, false);
    assert.equal(modal.isHoldingScrollLock, false);
    assert.equal(releases, 1);
});

test('failed select-all requests clear loading and preserve the previous selection', async () => {
    const { components } = loadAsset('tables/tables.js');
    const table = components.get('filamentTable').call({
        $wire: { getAllSelectableTableRecordKeys: async () => { throw new Error('test failure'); } },
    }, { canTrackDeselectedRecords: false });
    const previous = table.selectedRecords;
    previous.add('existing');
    table.updatedSelectedRecords = () => assert.fail('A failed request cannot update selection.');
    await assert.rejects(table.selectAllRecords(), /test failure/);
    assert.equal(table.isLoading, false);
    assert.equal(table.selectedRecords, previous);
    assert.deepEqual([...table.selectedRecords], ['existing']);
});

test('successful select-all requests still update selection and clear loading', async () => {
    const { components } = loadAsset('tables/tables.js');
    const table = components.get('filamentTable').call({
        $wire: { getAllSelectableTableRecordKeys: async () => ['one', 'two'] },
    }, { canTrackDeselectedRecords: false });
    let updates = 0;
    table.updatedSelectedRecords = () => updates++;
    await table.selectAllRecords();
    assert.deepEqual([...table.selectedRecords], ['one', 'two']);
    assert.equal(table.isLoading, false);
    assert.equal(updates, 1);
});

test('failed column changes restore confirmed visibility without logging the request error', async () => {
    for (const isLive of [true, false]) {
        const { components, context } = loadAsset('tables/tables.js');
        context.console = { error() { assert.fail('Request errors must not be logged.'); } };
        const confirmed = [{ name: 'customer_notes', isToggled: true }];
        const manager = components.get('filamentTableColumnManager')({ columns: confirmed, isLive });
        manager.$wire = { call: async () => { throw new Error('test failure'); } };
        manager.deferredColumns = [{ name: 'customer_notes', isToggled: false }];
        assert.equal(await manager.applyTableColumnManager(), false);
        assert.deepEqual(manager.columns, confirmed);
        assert.equal(manager.isLoading, false);
        assert.equal(manager.error, 'Failed to update column visibility');
    }
});

test('successful column changes retain confirmed visibility and deferred mode closes the dropdown', async () => {
    const { components } = loadAsset('tables/tables.js');
    const manager = components.get('filamentTableColumnManager')({
        columns: [{ name: 'customer_notes', isToggled: true }], isLive: false,
    });
    const updated = [{ name: 'customer_notes', isToggled: false }];
    manager.deferredColumns = updated;
    let sent;
    let closed = false;
    manager.$wire = { call: async (method, columns) => { sent = { method, columns }; } };
    manager.closeDropdown = () => { closed = true; };
    assert.equal(await manager.applyTableColumnManager(), true);
    assert.equal(JSON.stringify(manager.columns), JSON.stringify(updated));
    assert.equal(sent.method, 'applyTableColumnManager');
    assert.equal(JSON.stringify(sent.columns), JSON.stringify(updated));
    assert.equal(manager.isLoading, false);
    assert.equal(closed, true);
});

test('Escape closes filters even when the event target is inside the filter control', () => {
    const source = readFileSync(new URL('../../public/js/filament/tables/tables.js', import.meta.url), 'utf8');
    const start = source.indexOf('let h=d=>');
    const end = source.indexOf('document.addEventListener("keydown",h)', start);
    let cleanups = 0;
    const state = { areFiltersOpen: true, cleanUpFiltersDropdown() { cleanups++; } };
    // Exercise the shipped Escape callback without requiring Floating UI's browser geometry.
    const handler = new Function('f', `${source.slice(start, end)} return h;`).call(state, () => {});
    handler({ key: 'Escape', target: { insideFilter: true } });
    assert.equal(state.areFiltersOpen, false);
    assert.equal(state.cleanUpFiltersDropdown, null);
    assert.equal(cleanups, 1);
});

function notifications(outcomes) {
    const batchId = 'a'.repeat(40);
    const harness = loadAsset('notifications/notifications.js', { redirectBatches: [batchId] });
    const batches = [];
    harness.context.document.dispatchEvent({ type: 'livewire:init' });
    const component = harness.components.get('notificationsComponent')();
    Object.assign(component, {
        $el: { isConnected: true, dataset: { filamentNotifications: 'mounted' } },
        $wire: {
            pullNotifications(ids) {
                batches.push([...ids]);
                return outcomes.shift()();
            },
        },
    });
    component.init();
    return {
        ...harness, batches, component, batchId,
        async settle() {
            harness.flushTicks();
            await Promise.resolve();
            harness.flushTicks();
            await Promise.resolve();
        },
    };
}

test('notification pulls retain failed batches and acknowledge only after a successful retry', async () => {
    const h = notifications([
        () => Promise.reject(new Error('test failure')),
        () => Promise.resolve(),
    ]);
    await h.settle();
    assert.deepEqual(h.batches, [[h.batchId]]);
    assert.equal(h.timers.length, 1);
    h.timers.shift()();
    await h.settle();
    assert.deepEqual(h.batches, [[h.batchId], [h.batchId]]);
    assert.equal(h.timers.length, 0);
});

test('notification retries stop after three failed attempts without acknowledging the batch', async () => {
    const h = notifications([
        ...Array.from({ length: 3 }, () => () => Promise.reject(new Error('test failure'))),
        () => Promise.resolve(),
    ]);
    await h.settle();
    for (let retry = 0; retry < 2; retry++) {
        h.timers.shift()();
        await h.settle();
    }
    assert.deepEqual(h.batches, [[h.batchId], [h.batchId], [h.batchId]]);
    assert.equal(h.timers.length, 0);
    h.component.destroy();
    h.component.init();
    await h.settle();
    assert.deepEqual(h.batches, Array.from({ length: 4 }, () => [h.batchId]));
});

test('a replacement notification host retains a batch whose previous pull has not finished', async () => {
    let completePreviousPull;
    const h = notifications([
        () => new Promise(resolve => { completePreviousPull = resolve; }),
        () => Promise.resolve(),
    ]);
    await h.settle();
    h.component.destroy();
    h.component.init();
    await h.settle();
    completePreviousPull();
    await h.settle();
    h.timers.shift()();
    await h.settle();
    assert.deepEqual(h.batches, [[h.batchId], [h.batchId]]);
});

test('synchronous notification request failures also retain the batch for retry', async () => {
    const h = notifications([
        () => { throw new Error('test failure'); },
        () => Promise.resolve(),
    ]);
    await h.settle();
    h.timers.shift()();
    await h.settle();
    assert.deepEqual(h.batches, [[h.batchId], [h.batchId]]);
});
