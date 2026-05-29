import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const progressBar = document.getElementById('scroll-progress');
    const nav = document.getElementById('ss-nav');
    const snapContainer = document.querySelector('.snap-container');
    const hTracks = document.querySelectorAll('.h-track');

    const getScrollTop = () => snapContainer ? snapContainer.scrollTop : window.scrollY;
    const getScrollHeight = () => snapContainer
        ? snapContainer.scrollHeight - snapContainer.clientHeight
        : document.documentElement.scrollHeight - window.innerHeight;

    // ─── Scroll Progress Bar ───
    const updateProgress = () => {
        const scrollTop = getScrollTop();
        const scrollHeight = getScrollHeight();
        const progress = scrollHeight > 0 ? (scrollTop / scrollHeight) * 100 : 0;
        if (progressBar) progressBar.style.width = `${Math.min(progress, 100)}%`;
    };

    // ─── Nav Visibility ───
    const handleNav = () => {
        const scrollTop = getScrollTop();
        const vh = window.innerHeight;
        if (scrollTop > vh * 0.7) {
            nav?.classList.add('visible');
            if (scrollTop > vh * 1.5) {
                nav?.classList.add('scrolled');
            } else {
                nav?.classList.remove('scrolled');
            }
        } else {
            nav?.classList.remove('visible', 'scrolled');
        }
    };

    const scrollHandler = () => {
        updateProgress();
        handleNav();
    };

    if (snapContainer) {
        snapContainer.addEventListener('scroll', scrollHandler, { passive: true });
    } else {
        window.addEventListener('scroll', scrollHandler, { passive: true });
    }

    // ─── Section Reveal Animations ───
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const delay = parseInt(entry.target.dataset.delay) || 0;
                setTimeout(() => entry.target.classList.add('in'), delay);
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    document.querySelectorAll('.ss-reveal, .ss-reveal-left, .ss-reveal-right').forEach((el, i) => {
        if (!el.dataset.delay) {
            const parent = el.parentElement;
            const siblings = parent ? [...parent.querySelectorAll('.ss-reveal, .ss-reveal-left, .ss-reveal-right')] : [el];
            el.dataset.delay = siblings.indexOf(el) * 100;
        }
        revealObserver.observe(el);
    });

    // ─── Horizontal Track Dots ───
    hTracks.forEach(track => {
        const panels = track.querySelectorAll('.h-panel');
        const dotsContainer = track.parentElement?.querySelector('.h-dots');
        if (!dotsContainer || panels.length === 0) return;

        const dots = dotsContainer.querySelectorAll('.h-dot');

        const updateDots = () => {
            const trackRect = track.getBoundingClientRect();
            const trackMid = trackRect.left + trackRect.width / 2;
            let activeIndex = 0;
            panels.forEach((panel, i) => {
                const panelRect = panel.getBoundingClientRect();
                const panelMid = panelRect.left + panelRect.width / 2;
                if (Math.abs(panelMid - trackMid) < panelRect.width / 2) {
                    activeIndex = i;
                }
            });
            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === activeIndex);
            });
        };

        track.addEventListener('scroll', updateDots, { passive: true });

        dots.forEach((dot, i) => {
            dot.addEventListener('click', () => {
                panels[i].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
            });
        });

        requestAnimationFrame(() => {
            setTimeout(updateDots, 150);
        });
    });

    // ─── Counter Animation ───
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counters = entry.target.querySelectorAll('[data-count]');
                counters.forEach(el => {
                    if (el.dataset.done) return;
                    el.dataset.done = '1';
                    const target = parseFloat(el.dataset.count);
                    const hasSuffix = !!el.dataset.suffix;
                    const duration = 2000;
                    const start = performance.now();

                    const animate = (now) => {
                        const elapsed = now - start;
                        const progress = Math.min(elapsed / duration, 1);
                        const eased = 1 - Math.pow(1 - progress, 3);
                        const current = target * eased;
                        const display = target % 1 !== 0
                            ? current.toFixed(1)
                            : Math.round(current).toLocaleString();
                        el.textContent = hasSuffix ? display : display;
                        if (progress < 1) requestAnimationFrame(animate);
                    };
                    requestAnimationFrame(animate);
                });
                counterObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });

    document.querySelectorAll('[data-counter-section]').forEach(el => {
        counterObserver.observe(el);
    });

    // ─── Smooth Scroll for Nav Links ───
    nav?.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('href');
            if (!targetId) return;
            const target = document.querySelector(targetId);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
            const navLinks = document.querySelector('.nav-links');
            navLinks?.classList.remove('open');
        });
    });

    // ─── Hamburger Menu ───
    const hamburger = document.querySelector('.nav-hamburger');
    const navLinks = document.querySelector('.nav-links');
    hamburger?.addEventListener('click', () => {
        navLinks?.classList.toggle('open');
    });

    // ─── Keyboard: Escape closes mobile nav ───
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            navLinks?.classList.remove('open');
        }
    });

    // ─── Initial nav check ───
    handleNav();
});
