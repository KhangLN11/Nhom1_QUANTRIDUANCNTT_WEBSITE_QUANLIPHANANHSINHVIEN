// Progressive navigation only. Forms and PHP workflows do not depend on JavaScript.
(() => {
    'use strict';
    document.querySelectorAll('[data-site-header]').forEach(header => {
        const button = header.querySelector('.nav-toggle');
        const navigation = header.querySelector('[data-site-nav]');
        if (!button || !navigation) return;
        const mobile = window.matchMedia('(max-width: 1000px)');
        const setOpen = open => {
            button.setAttribute('aria-expanded', String(open));
            header.dataset.navOpen = String(open);
        };
        setOpen(false);
        header.dataset.navReady = 'true';
        button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
        header.addEventListener('keydown', event => {
            if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                button.focus();
            }
        });
        document.addEventListener('click', event => {
            if (!header.contains(event.target)) setOpen(false);
        });
        navigation.addEventListener('click', event => {
            const link = event.target.closest('a');
            if (!link || !mobile.matches) return;
            setOpen(false);
            const href = link.getAttribute('href');
            if (href && href.startsWith('#')) {
                const target = document.getElementById(href.slice(1));
                if (target) {
                    if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
                    target.focus({preventScroll: true});
                }
            }
        });
        const onResize = () => { if (!mobile.matches) setOpen(false); };
        if (mobile.addEventListener) mobile.addEventListener('change', onResize);
        else mobile.addListener(onResize);
    });
})();
