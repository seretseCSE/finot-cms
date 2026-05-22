
const BUILD_INFO = {"timestamp":1779290062114,"hash":"p70vc30o6","assets":["/assets/app-CGRnLp_K.css","/assets/vendor-BYIAvQxD.js","/assets/admin-tour-qw7r0X-M.js","/assets/app-D_IEYnof.js","/assets/financial-charts-BbJYJBRA.js","/assets/admin-B0dRGtE1.js","/assets/app-BWSsXgQj.js"]};
const CACHE_NAME = 'finot-cache-p70vc30o6';
const API_CACHE_NAME = 'finot-api-cache-p70vc30o6';

// Dynamic assets from build
const DYNAMIC_ASSETS = ["/assets/app-CGRnLp_K.css","/assets/vendor-BYIAvQxD.js","/assets/admin-tour-qw7r0X-M.js","/assets/app-D_IEYnof.js","/assets/financial-charts-BbJYJBRA.js","/assets/admin-B0dRGtE1.js","/assets/app-BWSsXgQj.js"];

// Static assets that rarely change
const STATIC_ASSETS = [
    '/',
    '/manifest.json',
    '/offline',
    '/images/logo.png',
];

self.addEventListener('install', (event) => {
    console.log('Installing service worker v' + BUILD_INFO.hash);
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
});

self.addEventListener('activate', (event) => {
    console.log('Activating service worker v' + BUILD_INFO.hash);
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => !name.includes(BUILD_INFO.hash))
                    .map((name) => {
                        console.log('Deleting old cache:', name);
                        return caches.delete(name);
                    })
            );
        }).then(() => self.clients.claim())
    );
});

// Handle skip waiting message
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    if (request.method !== 'GET') {
        return;
    }

    // Network-First for HTML pages (always fresh)
    if (request.mode === 'navigate' || url.pathname.endsWith('.html')) {
        event.respondWith(
            fetch(request).then((networkResponse) => {
                if (networkResponse.ok) {
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, networkResponse.clone());
                    });
                }
                return networkResponse;
            }).catch(() => {
                return caches.match(request).then((cached) => {
                    return cached || caches.match('/offline');
                });
            })
        );
        return;
    }

    // Cache-First for built assets (versioned anyway)
    if (DYNAMIC_ASSETS.some((asset) => url.pathname.includes(asset))) {
        event.respondWith(
            caches.match(request).then((response) => {
                return response || fetch(request).then((fetchResponse) => {
                    if (fetchResponse.ok) {
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, fetchResponse.clone());
                        });
                    }
                    return fetchResponse;
                });
            })
        );
        return;
    }

    // Stale-while-revalidate for static assets
    if (STATIC_ASSETS.some((asset) => url.pathname === asset)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const fetchPromise = fetch(request).then((networkResponse) => {
                    if (networkResponse.ok) {
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, networkResponse.clone());
                        });
                    }
                    return networkResponse;
                });
                return cached || fetchPromise;
            })
        );
        return;
    }

    // Network-First for API calls
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/livewire/')) {
        event.respondWith(fetch(request));
        return;
    }

    // Default: Network-First with cache fallback
    event.respondWith(
        fetch(request).then((networkResponse) => {
            if (networkResponse.ok) {
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(request, networkResponse.clone());
                });
            }
            return networkResponse;
        }).catch(() => caches.match(request))
    );
});

// Background sync for offline functionality
self.addEventListener('sync', (event) => {
    if (event.tag === 'attendance-sync') {
        event.waitUntil(syncAttendance());
    }
});

// Push notifications
self.addEventListener('push', (event) => {
    if (!event.data) return;
    const data = event.data.json();
    const options = {
        body: data.body || 'New notification',
        icon: data.icon || '/images/logo.png',
        badge: data.badge || '/images/logo.png',
        data: data.data || {},
        requireInteraction: true,
    };
    event.waitUntil(
        self.registration.showNotification(data.title || 'FINOTE TSIDIK', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/admin';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url.includes(url) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});

// IndexedDB for offline functionality
async function openIndexedDB() {
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
