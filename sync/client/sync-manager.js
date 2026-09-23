(function () {
    'use strict';

    /**
     * Compatibility adapter for pages that used the original experimental
     * sync manager. New defect submissions use the durable field outbox in
     * /js/offline-defect-queue.js.
     */
    class SyncManager {
        constructor(config) {
            this.config = config || window.SYNC_CONFIG || {};
        }

        async synchronize() {
            if (!window.offlineDefectQueue) {
                return { status: 'unavailable' };
            }
            return window.offlineDefectQueue.syncPending();
        }

        async checkServerConnection() {
            try {
                const response = await fetch('/api/offline_field_context.php', {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' }
                });
                return response.ok;
            } catch (error) {
                return false;
            }
        }

        queueChange() {
            throw new Error('Generic legacy sync is retired. Use the offline field report outbox.');
        }
    }

    window.SyncManager = SyncManager;
    window.addEventListener('DOMContentLoaded', () => {
        window.syncManager = new SyncManager(window.SYNC_CONFIG || {});
    }, { once: true });
})();
