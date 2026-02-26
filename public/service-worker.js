const CACHE_NAME = 'finot-cache-v1';
const API_CACHE_NAME = 'finot-api-cache-v1';

const STATIC_ASSETS = [
    '/',
    '/manifest.json',
    '/storage/logo.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // CacheFirst for static assets
    if (STATIC_ASSETS.some((asset) => url.pathname === asset)) {
        event.respondWith(
            caches.match(request).then((response) => {
                return response || fetch(request).then((fetchResponse) => {
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, fetchResponse.clone()));
                    return fetchResponse;
                });
            })
        );
        return;
    }

    // NetworkFirst for attendance API endpoints
    if (url.pathname.startsWith('/api/v1/attendance')) {
        event.respondWith(
            caches.open(API_CACHE_NAME).then((cache) => {
                return cache.match(request).then((cached) => {
                    const fetchPromise = fetch(request).then((networkResponse) => {
                        if (networkResponse.ok) {
                            cache.put(request, networkResponse.clone());
                        }
                        return networkResponse;
                    }).catch(() => cached);
                    return cached || fetchPromise;
                });
            })
        );
        return;
    }

    event.respondWith(fetch(request));
});

self.addEventListener('sync', (event) => {
    if (event.tag === 'attendance-sync') {
        event.waitUntil(syncAttendance());
    }
});

async function syncAttendance() {
    try {
        const db = await openIndexedDB();
        const pending = await getAllPending(db);
        if (!pending.length) return;

        const payload = pending.map((p) => ({
            student_id: p.student_id,
            session_id: p.session_id,
            status: p.status,
            marked_at: p.marked_at,
            local_device_id: p.device_id || 'unknown',
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
            await deletePending(db, key);
        }

        for (const conflict of result.conflicts || []) {
            await logSyncError(db, { type: 'conflict', data: conflict });
        }

        for (const error of result.errors || []) {
            await logSyncError(db, { type: 'error', data: error });
        }

        self.registration.showNotification(`Synced ${result.synced?.length || 0} attendance records`);
    } catch (err) {
        console.error('Sync failed:', err);
        self.registration.showNotification('Sync failed, will retry later');
    }
}

function openIndexedDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('FinotOffline', 1);
        request.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('pending_attendance')) {
                const store = db.createObjectStore('pending_attendance', { keyPath: ['student_id', 'session_id'] });
                store.createIndex('session_id', 'session_id');
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

function getAllPending(db) {
    return new Promise((resolve) => {
        const tx = db.transaction(['pending_attendance'], 'readonly');
        const store = tx.objectStore('pending_attendance');
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
    });
}

function deletePending(db, key) {
    return new Promise((resolve) => {
        const tx = db.transaction(['pending_attendance'], 'readwrite');
        const store = tx.objectStore('pending_attendance');
        store.delete(key);
        tx.oncomplete = () => resolve();
    });
}

function logSyncError(db, error) {
    return new Promise((resolve) => {
        const tx = db.transaction(['sync_errors'], 'readwrite');
        const store = tx.objectStore('sync_errors');
        store.add({ ...error, timestamp: new Date().toISOString() });
        tx.oncomplete = () => resolve();
    });
}
