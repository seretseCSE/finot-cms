import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

(function () {
    'use strict';

    const mr = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (mr.matches) return;

    const isMobile = window.innerWidth < 768;
    const isDark = document.documentElement.classList.contains('dark');

    // Scroll Progress
    const progressBar = document.getElementById('scroll-progress');
    if (progressBar) {
        gsap.to(progressBar, {
            scaleX: 1,
            ease: 'none',
            scrollTrigger: {
                trigger: document.body,
                start: 'top top',
                end: 'bottom bottom',
                scrub: 0.1,
                onUpdate: (self) => { progressBar.style.transform = `scaleX(${self.progress})`; }
            }
        });
    }

    // Typewriter
    document.querySelectorAll('[data-typewriter]').forEach((el) => {
        const text = el.textContent;
        el.textContent = '';
        el.style.visibility = 'visible';
        const chars = text.split('');
        let i = 0;
        const tl = gsap.timeline({ delay: parseFloat(el.dataset.typewriterDelay || 0.8) });
        chars.forEach((char) => { tl.call(() => { el.textContent += char; }, [], '+=0.03'); });
        tl.call(() => {
            el.classList.add('typewriter-cursor');
            setTimeout(() => el.classList.remove('typewriter-cursor'), 3000);
        });
    });

    // Floating elements
    document.querySelectorAll('[data-float]').forEach((el) => {
        const d = parseFloat(el.dataset.floatDuration || 4);
        const dy = parseFloat(el.dataset.floatY || 15);
        gsap.to(el, {
            y: dy, rotation: parseFloat(el.dataset.floatRotate || 3),
            duration: d, repeat: -1, yoyo: true, ease: 'sine.inOut',
            delay: Math.random() * 2
        });
    });

    // Counters
    document.querySelectorAll('[data-counter]').forEach((el) => {
        const target = parseInt(el.dataset.counter, 10);
        if (isNaN(target)) return;
        ScrollTrigger.create({
            trigger: el,
            start: 'top 85%',
            onEnter: () => {
                if (el.dataset.done) return;
                el.dataset.done = '1';
                const dur = parseFloat(el.dataset.counterDuration || 2);
                const obj = { val: 0 };
                gsap.to(obj, {
                    val: target, duration: dur, ease: 'power3.out',
                    onUpdate: () => { el.textContent = Math.round(obj.val).toLocaleString(); }
                });
            }
        });
    });

    // 3D Card Tilt
    document.querySelectorAll('.tilt-card').forEach((card) => {
        const glow = card.querySelector('.tilt-glow');
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width;
            const y = (e.clientY - rect.top) / rect.height;
            card.style.setProperty('--rot-x', `${(y - 0.5) * -12}deg`);
            card.style.setProperty('--rot-y', `${(x - 0.5) * 12}deg`);
            if (glow) {
                card.style.setProperty('--glow-x', `${x * 100}%`);
                card.style.setProperty('--glow-y', `${y * 100}%`);
            }
        });
        card.addEventListener('mouseleave', () => {
            card.style.setProperty('--rot-x', '0deg');
            card.style.setProperty('--rot-y', '0deg');
        });
    });

    // Magnetic buttons
    document.querySelectorAll('.magnetic-wrap').forEach((btn) => {
        btn.addEventListener('mousemove', (e) => {
            const rect = btn.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            const dist = Math.sqrt(x * x + y * y);
            const strength = Math.min(dist / 150, 1) * 8;
            const angle = Math.atan2(y, x);
            gsap.to(btn, {
                x: Math.cos(angle) * strength, y: Math.sin(angle) * strength,
                duration: 0.3, ease: 'power2.out'
            });
        });
        btn.addEventListener('mouseleave', () => {
            gsap.to(btn, { x: 0, y: 0, duration: 0.4, ease: 'elastic.out(1, 0.3)' });
        });
    });

    // Cursor glow
    if (!isMobile) {
        const cursorGlow = document.createElement('div');
        cursorGlow.className = 'fixed pointer-events-none z-[9999] rounded-full';
        cursorGlow.style.cssText = `width:300px;height:300px;background:radial-gradient(circle,rgba(26,68,247,0.08),transparent 70%);transform:translate(-50%,-50%);transition:opacity 0.3s ease;opacity:0;`;
        document.body.appendChild(cursorGlow);
        let cursorTimer;
        document.addEventListener('mousemove', (e) => {
            cursorGlow.style.left = e.clientX + 'px';
            cursorGlow.style.top = e.clientY + 'px';
            cursorGlow.style.opacity = '1';
            clearTimeout(cursorTimer);
            cursorTimer = setTimeout(() => { cursorGlow.style.opacity = '0'; }, 2000);
        });
        document.addEventListener('mouseleave', () => { cursorGlow.style.opacity = '0'; });
    }

    // Particle canvas
    if (!isMobile) {
        const canvas = document.getElementById('particle-canvas');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            let particles = [];
            const count = 40;
            let animId;

            function resize() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
            resize();
            window.addEventListener('resize', resize);

            class Particle {
                constructor() { this.reset(); }
                reset() {
                    this.x = Math.random() * canvas.width;
                    this.y = Math.random() * canvas.height;
                    this.size = Math.random() * 2 + 0.5;
                    this.speedX = (Math.random() - 0.5) * 0.3;
                    this.speedY = (Math.random() - 0.5) * 0.3;
                    this.opacity = Math.random() * 0.5 + 0.1;
                }
                update() {
                    this.x += this.speedX;
                    this.y += this.speedY;
                    if (this.x < 0 || this.x > canvas.width) this.speedX *= -1;
                    if (this.y < 0 || this.y > canvas.height) this.speedY *= -1;
                }
                draw() {
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fillStyle = isDark ? `rgba(92,128,255,${this.opacity})` : `rgba(26,68,247,${this.opacity})`;
                    ctx.fill();
                }
            }

            for (let i = 0; i < count; i++) particles.push(new Particle());

            function drawLines() {
                for (let i = 0; i < particles.length; i++) {
                    for (let j = i + 1; j < particles.length; j++) {
                        const dx = particles[i].x - particles[j].x;
                        const dy = particles[i].y - particles[j].y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        if (dist < 150) {
                            ctx.beginPath();
                            ctx.moveTo(particles[i].x, particles[i].y);
                            ctx.lineTo(particles[j].x, particles[j].y);
                            const alpha = (1 - dist / 150) * 0.15;
                            ctx.strokeStyle = isDark ? `rgba(92,128,255,${alpha})` : `rgba(26,68,247,${alpha})`;
                            ctx.lineWidth = 0.5;
                            ctx.stroke();
                        }
                    }
                }
            }

            function animate() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                particles.forEach((p) => { p.update(); p.draw(); });
                drawLines();
                animId = requestAnimationFrame(animate);
            }
            animate();

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) cancelAnimationFrame(animId);
                else animate();
            });
        }
    }

    // Parallax sections
    document.querySelectorAll('[data-parallax]').forEach((el) => {
        const speed = parseFloat(el.dataset.parallax || 0.3);
        gsap.to(el, {
            y: () => window.innerHeight * speed * 0.5,
            ease: 'none',
            scrollTrigger: { trigger: el.parentElement || el, start: 'top bottom', end: 'bottom top', scrub: true }
        });
    });

    // Section reveals
    document.querySelectorAll('.section-reveal').forEach((section) => {
        const children = section.querySelectorAll('.reveal-item');
        if (children.length > 0) {
            gsap.timeline({
                scrollTrigger: { trigger: section, start: 'top 80%', toggleActions: 'play none none none' }
            }).from(children, {
                y: 50, opacity: 0, rotationX: isMobile ? 0 : 5, stagger: 0.08, duration: 0.7, ease: 'power3.out'
            });
        }
    });

    // Image reveal
    document.querySelectorAll('.img-reveal').forEach((el) => {
        ScrollTrigger.create({
            trigger: el, start: 'top 85%',
            onEnter: () => { el.classList.add('revealed'); }
        });
    });

    // Hero parallax
    const hero = document.getElementById('hero');
    if (hero) {
        const heroContent = hero.querySelector('.hero-content');
        const heroBg = hero.querySelector('.hero-bg-layer');
        if (heroContent) {
            gsap.to(heroContent, {
                y: 30, ease: 'none',
                scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: 1 }
            });
        }
        if (heroBg) {
            gsap.to(heroBg, {
                y: -30, scale: 1.05, ease: 'none',
                scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: 1 }
            });
        }
        hero.querySelectorAll('.hero-shape').forEach((shape, i) => {
            gsap.to(shape, {
                y: i % 2 === 0 ? -20 : 20, x: i % 3 === 0 ? 10 : -10, rotation: i % 2 === 0 ? 5 : -5,
                duration: 3 + i * 0.5, repeat: -1, yoyo: true, ease: 'sine.inOut', delay: i * 0.5
            });
        });
    }

    // Progress bar animations
    document.querySelectorAll('.prog-fill[data-width]').forEach((el) => {
        const targetWidth = parseFloat(el.dataset.width);
        ScrollTrigger.create({
            trigger: el.closest('.card, .futuristic-card, div') || el.parentElement,
            start: 'top 85%',
            onEnter: () => {
                gsap.to(el, { width: `${targetWidth}%`, duration: 1.5, ease: 'power3.out', delay: 0.2 });
            }
        });
    });

    // Marquee gallery
    const galleryMarquee = document.querySelector('.gallery-marquee');
    if (galleryMarquee && !isMobile) {
        const wrapper = document.createElement('div');
        wrapper.style.cssText = 'display:flex;gap:1rem;animation:marquee-scroll 30s linear infinite;';
        wrapper.innerHTML = galleryMarquee.innerHTML + galleryMarquee.innerHTML;
        galleryMarquee.innerHTML = '';
        galleryMarquee.appendChild(wrapper);
    }

    console.log('Futuristic animations initialized');
})();
