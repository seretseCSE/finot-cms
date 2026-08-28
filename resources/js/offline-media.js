const MEDIA_CACHE = 'finot-media-opt-in';

export async function saveOffline(url, title = '') {
    if (!('caches' in window)) {
        throw new Error('Offline cache is not supported');
    }
    const cache = await caches.open(MEDIA_CACHE);
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) {
        throw new Error('Download failed');
    }
    await cache.put(url, response.clone());
    return { url, title, bytes: Number(response.headers.get('content-length') || 0) };
}

export async function removeOffline(url) {
    const cache = await caches.open(MEDIA_CACHE);
    await cache.delete(url);
}

export async function clearOffline() {
    await caches.delete(MEDIA_CACHE);
}

export async function listOffline() {
    if (!('caches' in window)) {
        return [];
    }
    const cache = await caches.open(MEDIA_CACHE);
    const keys = await cache.keys();
    return keys.map((request) => request.url);
}

export async function storageMeter() {
    if (navigator.storage?.estimate) {
        const estimate = await navigator.storage.estimate();
        return {
            usage: estimate.usage || 0,
            quota: estimate.quota || 0,
        };
    }
    return { usage: 0, quota: 0 };
}

export function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let n = bytes;
    while (n >= 1024 && i < units.length - 1) {
        n /= 1024;
        i++;
    }
    return `${n.toFixed(1)} ${units[i]}`;
}

async function refreshMeter() {
    const el = document.getElementById('offline-storage-meter');
    if (!el) return;
    const { usage, quota } = await storageMeter();
    const items = await listOffline();
    el.textContent = items.length
        ? `Offline: ${items.length} item(s) · ${formatBytes(usage)}${quota ? ' / ' + formatBytes(quota) : ''}`
        : 'Offline downloads: none';
}

document.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-offline-url]');
    if (!btn) return;
    event.preventDefault();
    const url = btn.getAttribute('data-offline-url');
    const title = btn.getAttribute('data-offline-title') || url;
    btn.disabled = true;
    try {
        await saveOffline(url, title);
        btn.textContent = 'Saved offline';
        await refreshMeter();
    } catch (e) {
        btn.textContent = 'Retry save';
    } finally {
        btn.disabled = false;
    }
});

document.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-offline-clear]');
    if (!btn) return;
    event.preventDefault();
    await clearOffline();
    await refreshMeter();
});

document.addEventListener('DOMContentLoaded', refreshMeter);

window.finotOffline = { saveOffline, removeOffline, clearOffline, listOffline, storageMeter };
