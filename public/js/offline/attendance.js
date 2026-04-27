class OfflineAttendance {
    constructor() {
        this.db = null;
        this.deviceId = this.getOrCreateDeviceId();
        this.initDB();
        this.setupOfflineDetection();
    }

    async initDB() {
        this.db = await new Promise((resolve, reject) => {
            const request = indexedDB.open('FinotOffline', 1);
            request.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains('pending_attendance')) {
                    const store = db.createObjectStore('pending_attendance', { keyPath: ['student_id', 'session_id'] });
                    store.createIndex('session_id', 'session_id');
                }
                if (!db.objectStoreNames.contains('cached_sessions')) {
                    db.createObjectStore('cached_sessions', { keyPath: 'session_id' });
                }
                if (!db.objectStoreNames.contains('sync_queue')) {
                    const queue = db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
                    queue.createIndex('endpoint', 'endpoint');
                }
                if (!db.objectStoreNames.contains('sync_errors')) {
                    db.createObjectStore('sync_errors', { keyPath: 'id', autoIncrement: true });
                }
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    getOrCreateDeviceId() {
        let id = localStorage.getItem('finot-device-id');
        if (!id) {
            id = 'device-' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('finot-device-id', id);
        }
        return id;
    }

    setupOfflineDetection() {
        window.addEventListener('online', () => {
            this.hideOfflineBanner();
            this.syncPendingAttendance();
        });

        window.addEventListener('offline', () => {
            this.showOfflineBanner();
        });

        if (!navigator.onLine) {
            this.showOfflineBanner();
        }
    }

    showOfflineBanner() {
        let banner = document.getElementById('offline-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'offline-banner';
            banner.className = 'fixed top-0 left-0 right-0 bg-yellow-500 text-white text-center py-2 z-50';
            banner.textContent = '📵 Offline Mode - Changes will sync when you are back online';
            document.body.prepend(banner);
        }
    }

    hideOfflineBanner() {
        const banner = document.getElementById('offline-banner');
        if (banner) banner.remove();
    }

    async saveAttendanceOffline(studentId, sessionId, status) {
        if (!this.db) return;

        const tx = this.db.transaction(['pending_attendance'], 'readwrite');
        const store = tx.objectStore('pending_attendance');
        await store.put({
            student_id: studentId,
            session_id: sessionId,
            status,
            marked_by: window.authUserId || null,
            marked_at: new Date().toISOString(),
            synced: false,
            device_id: this.deviceId,
        });

        this.showToast('Attendance saved locally (offline)');
    }

    async syncPendingAttendance() {
        if (!navigator.onLine || !this.db) return;

        const pending = await this.getAllPending();
        if (!pending.length) return;

        this.showToast('Syncing attendance...');

        try {
            const payload = pending.map(p => ({
                student_id: p.student_id,
                session_id: p.session_id,
                status: p.status,
                marked_at: p.marked_at,
                local_device_id: p.device_id,
            }));

            const response = await fetch('/api/v1/attendance/sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ attendance: payload }),
            });

            if (!response.ok) throw new Error('Sync failed');

            const result = await response.json();

            for (const item of pending) {
                const key = [item.student_id, item.session_id];
                await this.deletePending(key);
            }

            for (const conflict of result.conflicts || []) {
                await this.logSyncError({ type: 'conflict', data: conflict });
            }

            for (const error of result.errors || []) {
                await this.logSyncError({ type: 'error', data: error });
            }

            this.showToast(`Synced ${result.synced?.length || 0} records`);
        } catch (err) {
            console.error('Sync error:', err);
            this.showToast('Sync failed, will retry later');
        }
    }

    async getAllPending() {
        return new Promise((resolve) => {
            const tx = this.db.transaction(['pending_attendance'], 'readonly');
            const store = tx.objectStore('pending_attendance');
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
        });
    }

    async deletePending(key) {
        return new Promise((resolve) => {
            const tx = this.db.transaction(['pending_attendance'], 'readwrite');
            const store = tx.objectStore('pending_attendance');
            store.delete(key);
            tx.oncomplete = () => resolve();
        });
    }

    async logSyncError(error) {
        const tx = this.db.transaction(['sync_errors'], 'readwrite');
        const store = tx.objectStore('sync_errors');
        await store.add({ ...error, timestamp: new Date().toISOString() });
    }

    showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded shadow-lg z-50';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    async triggerBackgroundSync() {
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            const registration = await navigator.serviceWorker.ready;
            await registration.sync.register('attendance-sync');
        }
    }
}

window.OfflineAttendance = OfflineAttendance;

// Auto-initialize
document.addEventListener('DOMContentLoaded', () => {
    window.offlineAttendance = new OfflineAttendance();
});
