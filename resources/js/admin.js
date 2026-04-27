import './bootstrap';

// Dynamically import admin-specific PWA tour logic so it is code-split
// into a separate chunk and only loaded when needed.
if (document.querySelector('.fi-main-sidebar') || document.querySelector('[href^="/admin/"]')) {
    import('./pwa-tour');
}
