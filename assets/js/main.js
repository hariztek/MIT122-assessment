/*
 * assets/js/main.js
 *
 * Shared client-side behaviour, loaded on every page by
 * includes/footer.php. Purely presentational: nothing here is required
 * for any page to work, and nothing here is trusted by the server.
 *
 * Landing page motion (progressive enhancement):
 * - Scroll reveal: elements fade/slide in as they enter the viewport.
 *   The "js-motion" class is only added when JS runs and the user has
 *   not asked for reduced motion, so without JS everything is visible.
 * - Count-up: numbers marked data-count animate from 0 when revealed.
 * - Hero tilt: the mock cards follow the pointer slightly.
 */
(function () {
    'use strict';

    /* Mobile navigation ------------------------------------------------ */
    // Full-screen menu below the desktop breakpoint. Runs regardless of
    // motion preference (the CSS drops the animation for reduced motion).

    var navToggle = document.querySelector('.nav-toggle');
    var siteNav = document.getElementById('site-nav');
    var desktopNav = window.matchMedia('(min-width: 900px)');

    function setNavOpen(open) {
        if (!navToggle || !siteNav) {
            return;
        }
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        navToggle.querySelector('.nav-toggle__label').textContent = open ? 'Close menu' : 'Menu';
        document.documentElement.classList.toggle('nav-open', open);
        if (open) {
            var first = siteNav.querySelector('a');
            if (first) {
                first.focus();
            }
        }
    }

    if (navToggle && siteNav) {
        navToggle.addEventListener('click', function () {
            setNavOpen(navToggle.getAttribute('aria-expanded') !== 'true');
        });

        // Close on Escape and return focus to the button.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.documentElement.classList.contains('nav-open')) {
                setNavOpen(false);
                navToggle.focus();
            }
        });

        // Keep Tab focus inside the open menu (toggle + links).
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || !document.documentElement.classList.contains('nav-open')) {
                return;
            }
            var focusables = [navToggle].concat(Array.prototype.slice.call(siteNav.querySelectorAll('a')));
            var firstEl = focusables[0];
            var lastEl = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === firstEl) {
                e.preventDefault();
                lastEl.focus();
            } else if (!e.shiftKey && document.activeElement === lastEl) {
                e.preventDefault();
                firstEl.focus();
            }
        });

        // Clicking a link (e.g. an in-page anchor) closes the menu.
        siteNav.addEventListener('click', function (e) {
            if (e.target.closest('a')) {
                setNavOpen(false);
            }
        });

        // Resizing up to desktop resets the menu.
        desktopNav.addEventListener('change', function (e) {
            if (e.matches) {
                setNavOpen(false);
            }
        });
    }

    /* Account menu (avatar dropdown) ---------------------------------- */
    // Desktop only in practice: in the full-screen mobile menu the panel
    // is shown open by CSS. Without JS the panel's links render inline.

    var accountTrigger = document.querySelector('.account-menu__trigger');
    var accountPanel = document.getElementById('account-menu-panel');

    function setAccountOpen(open, focusFirst) {
        accountTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        accountTrigger.parentElement.classList.toggle('is-open', open);
        if (open && focusFirst) {
            var first = accountPanel.querySelector('a');
            if (first) {
                first.focus();
            }
        }
    }

    if (accountTrigger && accountPanel) {
        accountTrigger.addEventListener('click', function () {
            setAccountOpen(accountTrigger.getAttribute('aria-expanded') !== 'true', false);
        });

        accountTrigger.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setAccountOpen(true, true);
            }
        });

        accountPanel.addEventListener('keydown', function (e) {
            var items = Array.prototype.slice.call(accountPanel.querySelectorAll('a'));
            var i = items.indexOf(document.activeElement);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                items[(i + 1) % items.length].focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                items[(i - 1 + items.length) % items.length].focus();
            } else if (e.key === 'Escape') {
                setAccountOpen(false, false);
                accountTrigger.focus();
            }
        });

        document.addEventListener('click', function (e) {
            if (!accountTrigger.parentElement.contains(e.target)) {
                setAccountOpen(false, false);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && accountTrigger.getAttribute('aria-expanded') === 'true') {
                setAccountOpen(false, false);
                accountTrigger.focus();
            }
        });

        // Tabbing out of the menu closes it.
        accountTrigger.parentElement.addEventListener('focusout', function (e) {
            if (e.relatedTarget && !accountTrigger.parentElement.contains(e.relatedTarget)) {
                setAccountOpen(false, false);
            }
        });
    }

    /* Search filters -------------------------------------------------- */
    // Dropdowns marked data-autosubmit re-run the search when changed.
    // The Search button still works without JS.

    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) {
                select.form.submit();
            }
        });
    });

    /* Confirm destructive actions ------------------------------------- */
    // Buttons with data-confirm ask before submitting (e.g. suspending an
    // account in admin.php). The server still validates every request.

    document.addEventListener('submit', function (e) {
        var button = e.submitter;
        if (button && button.hasAttribute('data-confirm') &&
            !window.confirm(button.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });

    /* Motion ----------------------------------------------------------- */

    var reduceMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion || !('IntersectionObserver' in window)) {
        return;
    }

    document.documentElement.classList.add('js-motion');

    /* Scroll reveal ---------------------------------------------------- */

    // Groups whose children should stagger in one after another.
    var staggerGroups = [
        '.feature-grid',
        '.advantages__grid',
        '.categories__list',
        '.split__visual--stack'
    ];

    var revealTargets = document.querySelectorAll(
        '.landing-section-title, .advantages__intro, .categories > .headline-lg,' +
        '.categories > .landing-muted, .band, .split__copy, .split__visual,' +
        '.final-cta, .feature-card, .advantage, .categories__list > li,' +
        '.mock-row--float'
    );

    staggerGroups.forEach(function (selector) {
        document.querySelectorAll(selector).forEach(function (group) {
            Array.prototype.forEach.call(group.children, function (child, i) {
                child.style.setProperty('--reveal-delay', (i * 90) + 'ms');
            });
        });
    });

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) {
                return;
            }
            entry.target.classList.add('is-visible');
            entry.target.querySelectorAll('[data-count]').forEach(countUp);
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    revealTargets.forEach(function (el) {
        el.classList.add('reveal');
        observer.observe(el);
    });

    /* Count-up --------------------------------------------------------- */

    function countUp(el) {
        var target = parseInt(el.getAttribute('data-count'), 10);
        if (isNaN(target) || target <= 0) {
            return;
        }
        var duration = 900;
        var start = null;

        function step(ts) {
            if (start === null) {
                start = ts;
            }
            var progress = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            el.textContent = Math.round(target * eased);
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }
        el.textContent = '0';
        requestAnimationFrame(step);
    }

    /* Hero pointer tilt ------------------------------------------------ */

    var hero = document.querySelector('.landing-hero');
    var heroVisual = document.querySelector('.landing-hero__visual');

    if (hero && heroVisual && window.matchMedia('(hover: hover)').matches) {
        hero.addEventListener('pointermove', function (e) {
            var rect = hero.getBoundingClientRect();
            var x = (e.clientX - rect.left) / rect.width - 0.5;
            var y = (e.clientY - rect.top) / rect.height - 0.5;
            heroVisual.style.setProperty('--tilt-x', (x * 12).toFixed(2) + 'px');
            heroVisual.style.setProperty('--tilt-y', (y * 12).toFixed(2) + 'px');
        });
        hero.addEventListener('pointerleave', function () {
            heroVisual.style.setProperty('--tilt-x', '0px');
            heroVisual.style.setProperty('--tilt-y', '0px');
        });
    }
})();
