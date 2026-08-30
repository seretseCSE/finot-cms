import './bootstrap-admin';

// Dynamically import product tour system
if (document.getElementById('product-tour-root')) {
    import('./tours/filament-init.js');
}

// Member form tab navigation
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on a Member create or edit page
    if (window.location.pathname.includes('/members/create') || window.location.pathname.includes('/members/')) {
        setupMemberTabNavigation();
    }
});

function setupMemberTabNavigation() {
    const previousButton = document.querySelector('.member-tab-previous button');
    const nextButton = document.querySelector('.member-tab-next button');

    if (!previousButton || !nextButton) return;

    // Find all tab buttons
    function getTabButtons() {
        return document.querySelectorAll('[role="tab"][data-tabs-target]');
    }

    function getActiveTabIndex() {
        const tabButtons = getTabButtons();
        for (let i = 0; i < tabButtons.length; i++) {
            if (tabButtons[i].getAttribute('aria-selected') === 'true') {
                return i;
            }
        }
        return 0;
    }

    function updateButtonStates() {
        const activeIndex = getActiveTabIndex();
        const totalTabs = getTabButtons().length;

        // Disable Previous button on first tab
        if (activeIndex === 0) {
            previousButton.disabled = true;
            previousButton.classList.add('fi-disabled');
        } else {
            previousButton.disabled = false;
            previousButton.classList.remove('fi-disabled');
        }

        // Disable Next button on last tab
        if (activeIndex === totalTabs - 1) {
            nextButton.disabled = true;
            nextButton.classList.add('fi-disabled');
        } else {
            nextButton.disabled = false;
            nextButton.classList.remove('fi-disabled');
        }
    }

    function switchTab(direction) {
        const tabButtons = getTabButtons();
        const activeIndex = getActiveTabIndex();
        const newIndex = direction === 'next' ? activeIndex + 1 : activeIndex - 1;

        if (newIndex >= 0 && newIndex < tabButtons.length) {
            tabButtons[newIndex].click();
            updateButtonStates();
        }
    }

    // Initial button state
    setTimeout(updateButtonStates, 100);

    // Listen for tab changes (when user clicks tabs directly)
    const tabContainer = document.querySelector('[role="tablist"]');
    if (tabContainer) {
        const observer = new MutationObserver(updateButtonStates);
        observer.observe(tabContainer, {
            attributes: true,
            subtree: true,
            attributeFilter: ['aria-selected']
        });
    }

    // Previous button click handler
    previousButton.addEventListener('click', function(e) {
        e.preventDefault();
        switchTab('previous');
    });

    // Next button click handler
    nextButton.addEventListener('click', function(e) {
        e.preventDefault();
        switchTab('next');
    });
}
