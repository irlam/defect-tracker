(function () {
    'use strict';

    if (window.offlineDefectQueue) return;

    const DB_NAME = 'defect_tracker_field_queue';
    const DB_VERSION = 1;
    const OUTBOX_STORE = 'defect_submissions';
    const META_STORE = 'field_metadata';
    const PREVIEW_CACHE = 'defect-tracker-floor-plans-v1';
    const MAX_ATTEMPTS = 12;
    let dbPromise;
    let syncing = false;

    function requestResult(request) {
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error || new Error('IndexedDB request failed'));
        });
    }

    function transactionDone(transaction) {
        return new Promise((resolve, reject) => {
            transaction.oncomplete = resolve;
            transaction.onerror = () => reject(transaction.error || new Error('IndexedDB transaction failed'));
            transaction.onabort = () => reject(transaction.error || new Error('IndexedDB transaction aborted'));
        });
    }

    function openDatabase() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains(OUTBOX_STORE)) {
                    const outbox = db.createObjectStore(OUTBOX_STORE, { keyPath: 'id' });
                    outbox.createIndex('status', 'status', { unique: false });
                    outbox.createIndex('userId', 'userId', { unique: false });
                    outbox.createIndex('createdAt', 'createdAt', { unique: false });
                }
                if (!db.objectStoreNames.contains(META_STORE)) {
                    db.createObjectStore(META_STORE, { keyPath: 'key' });
                }
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error || new Error('Unable to open offline storage'));
        });
        return dbPromise;
    }

    async function setMetadata(key, value) {
        const db = await openDatabase();
        const tx = db.transaction(META_STORE, 'readwrite');
        tx.objectStore(META_STORE).put({ key, value, updatedAt: Date.now() });
        await transactionDone(tx);
    }

    async function getMetadata(key) {
        const db = await openDatabase();
        const tx = db.transaction(META_STORE, 'readonly');
        const result = await requestResult(tx.objectStore(META_STORE).get(key));
        return result ? result.value : null;
    }

    function makeId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        const random = window.crypto && window.crypto.getRandomValues
            ? Array.from(window.crypto.getRandomValues(new Uint32Array(4)), value => value.toString(16)).join('')
            : Math.random().toString(16).slice(2) + Date.now().toString(16);
        return `field-${Date.now().toString(36)}-${random}`;
    }

    function serialiseFormData(formData) {
        const entries = [];
        for (const [name, value] of formData.entries()) {
            if (value instanceof Blob) {
                entries.push({
                    name,
                    kind: 'file',
                    blob: value,
                    fileName: value.name || 'field-upload.jpg',
                    mimeType: value.type || 'application/octet-stream',
                    lastModified: value.lastModified || Date.now()
                });
            } else {
                entries.push({ name, kind: 'text', value: String(value) });
            }
        }
        return entries;
    }

    function restoreFormData(entries, csrfToken) {
        const formData = new FormData();
        for (const entry of entries) {
            if (entry.name === 'csrf_token') continue;
            if (entry.kind === 'file') {
                const file = typeof File === 'function'
                    ? new File([entry.blob], entry.fileName, { type: entry.mimeType, lastModified: entry.lastModified })
                    : entry.blob;
                formData.append(entry.name, file, entry.fileName);
            } else {
                formData.append(entry.name, entry.value);
            }
        }
        formData.set('csrf_token', csrfToken);
        return formData;
    }

    async function getAllItems() {
        const db = await openDatabase();
        const tx = db.transaction(OUTBOX_STORE, 'readonly');
        return requestResult(tx.objectStore(OUTBOX_STORE).getAll());
    }

    async function getItem(id) {
        const db = await openDatabase();
        const tx = db.transaction(OUTBOX_STORE, 'readonly');
        return requestResult(tx.objectStore(OUTBOX_STORE).get(id));
    }

    async function saveItem(item) {
        const db = await openDatabase();
        const tx = db.transaction(OUTBOX_STORE, 'readwrite');
        tx.objectStore(OUTBOX_STORE).put(item);
        await transactionDone(tx);
    }

    async function removeItem(id) {
        const db = await openDatabase();
        const tx = db.transaction(OUTBOX_STORE, 'readwrite');
        tx.objectStore(OUTBOX_STORE).delete(id);
        await transactionDone(tx);
    }

    async function activeUser() {
        const configured = Number(window.OFFLINE_FIELD_CONFIG && window.OFFLINE_FIELD_CONFIG.userId);
        if (configured > 0) return configured;
        const stored = await getMetadata('activeUser');
        return stored && Number(stored.userId) > 0 ? Number(stored.userId) : null;
    }

    async function activeDeviceId() {
        let deviceId = await getMetadata('deviceId');
        if (typeof deviceId === 'string' && deviceId.length >= 10) return deviceId;
        deviceId = `field-${makeId()}`;
        await setMetadata('deviceId', deviceId);
        return deviceId;
    }

    async function reportTelemetry(event) {
        if (!navigator.onLine) return false;
        const context = await getMetadata('referenceData');
        if (!context || !context.csrfToken) return false;
        const userId = await activeUser();
        const deviceId = await activeDeviceId();
        const items = (await getAllItems()).filter(item => Number(item.userId) === Number(userId));
        const pending = items.filter(item => !['failed', 'syncing'].includes(item.status));
        const failed = items.filter(item => item.status === 'failed');
        const syncingItems = items.filter(item => item.status === 'syncing');
        const oldestPending = items.length ? new Date(Math.min(...items.map(item => item.createdAt))).toISOString() : null;
        const lastErrorItem = [...failed, ...items.filter(item => item.lastError)].sort((a, b) => (b.lastAttemptAt || 0) - (a.lastAttemptAt || 0))[0];
        try {
            const response = await fetch('/api/offline_field_telemetry.php', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': context.csrfToken
                },
                body: JSON.stringify({
                    deviceId,
                    pendingCount: pending.length,
                    failedCount: failed.length,
                    syncingCount: syncingItems.length,
                    oldestPendingAt: oldestPending,
                    status: failed.length ? 'error' : (syncingItems.length ? 'syncing' : 'online'),
                    lastError: lastErrorItem ? lastErrorItem.lastError : null,
                    event: event || null
                })
            });
            return response.ok;
        } catch (error) {
            return false;
        }
    }

    async function pendingItemsForActiveUser() {
        const userId = await activeUser();
        const items = await getAllItems();
        const staleSyncBefore = Date.now() - 120000;
        return items
            .filter(item => item.userId === userId && item.status !== 'synced')
            .filter(item => item.status !== 'syncing' || Number(item.lastAttemptAt || 0) < staleSyncBefore)
            .sort((a, b) => a.createdAt - b.createdAt);
    }

    function dispatchStatus(detail) {
        window.dispatchEvent(new CustomEvent('offline-defect-queue-status', { detail }));
    }

    async function updateBadge(message) {
        const pending = await pendingItemsForActiveUser().catch(() => []);
        const failedCount = pending.filter(item => item.status === 'failed').length;
        let badge = document.getElementById('offlineFieldStatus');
        if (!badge && document.body) {
            badge = document.createElement('button');
            badge.type = 'button';
            badge.id = 'offlineFieldStatus';
            badge.className = 'offline-field-status';
            badge.setAttribute('aria-live', 'polite');
            badge.addEventListener('click', () => {
                const text = failedCount
                    ? `${failedCount} saved field report${failedCount === 1 ? ' needs' : 's need'} attention. Open Field Mode to retry or remove it.`
                    : pending.length
                    ? `${pending.length} field report${pending.length === 1 ? '' : 's'} safely stored on this device. They will upload automatically when you are signed in and online.`
                    : 'All field reports stored on this device have synced.';
                window.alert(text);
            });
            document.body.appendChild(badge);
        }
        if (!badge) return;
        const isOffline = !navigator.onLine;
        const label = message || (failedCount
            ? `${failedCount} saved report${failedCount === 1 ? '' : 's'} need attention`
            : pending.length
            ? `${isOffline ? 'Offline' : 'Waiting'} · ${pending.length} saved`
            : (isOffline ? 'Offline field mode' : 'Field sync ready'));
        badge.textContent = label;
        badge.dataset.state = pending.length ? 'pending' : (isOffline ? 'offline' : 'ready');
        badge.hidden = !pending.length && !isOffline;
        dispatchStatus({ pending: pending.length, online: navigator.onLine, message: label });
    }

    async function fetchContext() {
        const response = await fetch('/api/offline_field_context.php', {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        });
        if (response.status === 401) throw Object.assign(new Error('Sign in required'), { code: 'auth' });
        if (!response.ok) throw new Error(`Unable to refresh field data (${response.status})`);
        const context = await response.json();
        await setMetadata('activeUser', { userId: Number(context.userId), username: context.username });
        await setMetadata('referenceData', context);
        return context;
    }

    async function cacheFloorPlanPreviews(context) {
        if (!('caches' in window) || !navigator.onLine || (navigator.connection && navigator.connection.saveData)) return;
        const paths = Array.from(new Set((context.floorPlans || [])
            .map(plan => plan.imagePath)
            .filter(path => typeof path === 'string' && path.startsWith('/'))));
        const cache = await caches.open(PREVIEW_CACHE);
        for (const path of paths) {
            try {
                const existing = await cache.match(path);
                if (existing) continue;
                const response = await fetch(path, { credentials: 'same-origin' });
                if (response.ok) await cache.put(path, response.clone());
            } catch (error) {
                console.warn('Floor-plan preview was not cached:', path, error);
            }
        }
    }

    async function refreshFieldData() {
        if (!navigator.onLine) return getMetadata('referenceData');
        const context = await fetchContext();
        cacheFloorPlanPreviews(context).catch(error => console.warn('Preview caching failed:', error));
        reportTelemetry().catch(() => false);
        return context;
    }

    async function markForRetry(item, error, status) {
        item.status = status || 'pending';
        item.lastError = error instanceof Error ? error.message : String(error);
        item.lastAttemptAt = Date.now();
        item.attempts = Number(item.attempts || 0) + 1;
        await saveItem(item);
    }

    async function syncItem(item, context) {
        if (Number(item.userId) !== Number(context.userId)) {
            await markForRetry(item, 'Sign in as the user who created this field report.', 'needs_user');
            return { status: 'needs_user' };
        }
        item.status = 'syncing';
        item.lastAttemptAt = Date.now();
        item.deviceId = item.deviceId || await activeDeviceId();
        await saveItem(item);
        reportTelemetry({
            id: `started-${item.id}-${item.attempts || 0}`,
            submissionId: item.id,
            type: 'upload_started',
            status: 'info',
            message: 'Field report upload started.'
        });
        try {
            const response = await fetch('/create_defect.php', {
                method: 'POST',
                credentials: 'same-origin',
                redirect: 'manual',
                headers: {
                    Accept: 'application/json',
                    'X-Offline-Submission': '1',
                    'X-Field-Device-ID': item.deviceId
                },
                body: restoreFormData(item.payload, context.csrfToken)
            });
            let body = {};
            try { body = await response.json(); } catch (error) { body = {}; }
            if (response.ok && body.success) {
                await removeItem(item.id);
                reportTelemetry();
                return { status: 'synced', defectId: body.defectId, duplicate: Boolean(body.duplicate) };
            }
            if (response.status === 401 || response.status === 403 || response.type === 'opaqueredirect') {
                await markForRetry(item, body.message || 'Sign in again to upload this report.', 'needs_login');
                return { status: 'needs_login' };
            }
            if (response.status >= 400 && response.status < 500) {
                await markForRetry(item, body.message || `Report needs attention (${response.status}).`, 'failed');
                reportTelemetry({
                    id: `failed-${item.id}-${item.attempts || 0}`,
                    submissionId: item.id,
                    type: 'upload_failed',
                    status: 'failed',
                    message: body.message || `Report rejected with status ${response.status}.`
                });
                return { status: 'failed', message: body.message };
            }
            throw new Error(body.message || `Server error ${response.status}`);
        } catch (error) {
            const attempts = Number(item.attempts || 0) + 1;
            await markForRetry(item, error, attempts >= MAX_ATTEMPTS ? 'failed' : 'pending');
            reportTelemetry(attempts >= MAX_ATTEMPTS ? {
                id: `failed-${item.id}-${attempts}`,
                submissionId: item.id,
                type: 'upload_failed',
                status: 'failed',
                message: error.message || 'Upload failed after repeated attempts.'
            } : null);
            return { status: attempts >= MAX_ATTEMPTS ? 'failed' : 'pending', error };
        }
    }

    async function syncPending() {
        if (syncing || !navigator.onLine) {
            await updateBadge();
            return { status: navigator.onLine ? 'busy' : 'offline' };
        }
        syncing = true;
        await updateBadge('Syncing field reports…');
        try {
            const context = await fetchContext();
            const items = (await pendingItemsForActiveUser()).filter(item => item.status !== 'failed');
            let lastResult = { status: 'empty' };
            for (const item of items) {
                lastResult = await syncItem(item, context);
                if (['needs_login', 'needs_user', 'failed'].includes(lastResult.status)) break;
            }
            return lastResult;
        } catch (error) {
            if (error.code !== 'auth') console.warn('Offline queue sync paused:', error);
            return { status: error.code === 'auth' ? 'needs_login' : 'pending', error };
        } finally {
            syncing = false;
            await updateBadge();
            reportTelemetry();
        }
    }

    async function enqueue(formData, summary) {
        const userId = await activeUser();
        if (!userId) throw new Error('Open the app online and sign in once before using offline field mode.');
        const id = formData.get('client_submission_id') || makeId();
        formData.set('client_submission_id', id);
        const item = {
            id,
            userId,
            deviceId: await activeDeviceId(),
            createdAt: Date.now(),
            updatedAt: Date.now(),
            attempts: 0,
            status: 'pending',
            lastError: null,
            summary: summary || String(formData.get('title') || 'Field defect'),
            payload: serialiseFormData(formData)
        };
        await saveItem(item);
        if (navigator.storage && typeof navigator.storage.persist === 'function') {
            navigator.storage.persist().catch(() => false);
        }
        if (!navigator.onLine && navigator.serviceWorker) {
            navigator.serviceWorker.ready.then(registration => {
                if (registration.sync) return registration.sync.register('sync-field-reports');
            }).catch(() => undefined);
        }
        await updateBadge();
        reportTelemetry({
            id: `queued-${id}`,
            submissionId: id,
            type: 'report_queued',
            status: 'info',
            message: 'Field report saved to the device outbox.'
        });
        return item;
    }

    async function retryItem(id) {
        const item = await getItem(id);
        if (!item) return { status: 'missing' };
        item.status = 'pending';
        item.attempts = 0;
        item.lastError = null;
        item.updatedAt = Date.now();
        await saveItem(item);
        await updateBadge();
        reportTelemetry({
            id: `retry-${item.id}-${Date.now()}`,
            submissionId: item.id,
            type: 'manual_retry',
            status: 'info',
            message: 'User requested another upload attempt.'
        });
        return syncPending();
    }

    async function discardItem(id) {
        const item = await getItem(id);
        await removeItem(id);
        await updateBadge();
        reportTelemetry({
            id: `discarded-${id}`,
            submissionId: id,
            type: 'report_discarded',
            status: 'info',
            message: item ? 'Saved field report removed from this device.' : 'Outbox item removed.'
        });
    }

    async function initialise() {
        await openDatabase();
        if (navigator.serviceWorker) {
            navigator.serviceWorker.register('/service-worker.js?v=1.2.0').catch(error => {
                console.warn('Offline field worker registration deferred:', error);
            });
        }
        const configured = window.OFFLINE_FIELD_CONFIG;
        if (configured && Number(configured.userId) > 0) {
            await setMetadata('activeUser', { userId: Number(configured.userId), username: configured.username || '' });
        }
        await updateBadge();
        if (navigator.onLine && configured) {
            refreshFieldData().catch(error => console.warn('Field data refresh deferred:', error));
            syncPending();
        }
    }

    window.offlineDefectQueue = {
        enqueue,
        syncPending,
        pendingItems: pendingItemsForActiveUser,
        getItem,
        discard: discardItem,
        retry: retryItem,
        getReferenceData: () => getMetadata('referenceData'),
        refreshFieldData,
        updateBadge,
        reportTelemetry,
        makeId
    };

    window.addEventListener('online', () => {
        updateBadge('Back online · syncing…');
        syncPending();
    });
    window.addEventListener('offline', () => updateBadge());
    if (navigator.serviceWorker) {
        navigator.serviceWorker.addEventListener('message', event => {
            if (!event.data) return;
            if (event.data.type === 'FIELD_SYNC_COMPLETE') updateBadge();
            if (event.data.type === 'FIELD_SYNC_NEEDS_LOGIN') updateBadge('Sign in to upload saved reports');
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
    } else {
        initialise();
    }
})();
