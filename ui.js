(() => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.addEventListener('DOMContentLoaded', () => {
        document.body.classList.add('ui-ready');

        const header = document.querySelector('.site-header');
        const syncHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 12);
        syncHeader();
        window.addEventListener('scroll', syncHeader, { passive: true });

        const animated = document.querySelectorAll(
            '.producto, .service-strip > div, .stat-card, .order-card, .checkout-card, .admin-panel, .profile-card, .side-card, .auth-card, .auth-intro'
        );

        if (reduceMotion || !('IntersectionObserver' in window)) {
            animated.forEach(el => el.classList.add('reveal-visible'));
        } else {
            animated.forEach(el => el.classList.add('reveal-item'));
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('reveal-visible');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

            animated.forEach((el, index) => {
                el.style.setProperty('--reveal-delay', `${Math.min(index % 6, 5) * 55}ms`);
                observer.observe(el);
            });
        }

        document.querySelectorAll('.alert').forEach(alert => {
            if (!alert.classList.contains('alert-error')) {
                setTimeout(() => alert.classList.add('alert-soft-hide'), 5500);
            }
        });

        if (document.querySelector('.payment-result-ok') && !reduceMotion) {
            createConfetti();
        }
    });

    function createConfetti() {
        const target = document.querySelector('.payment-result-ok');
        if (!target) return;

        const layer = document.createElement('div');
        layer.className = 'confetti-layer';
        layer.setAttribute('aria-hidden', 'true');

        const glyphs = ['●', '◆', '■', '▲'];
        for (let i = 0; i < 28; i++) {
            const piece = document.createElement('span');
            piece.textContent = glyphs[i % glyphs.length];
            piece.style.left = `${5 + Math.random() * 90}%`;
            piece.style.animationDelay = `${Math.random() * 0.8}s`;
            piece.style.animationDuration = `${1.8 + Math.random() * 1.6}s`;
            piece.style.setProperty('--drift', `${-70 + Math.random() * 140}px`);
            layer.appendChild(piece);
        }

        target.appendChild(layer);
        setTimeout(() => layer.remove(), 4300);
    }
})();
