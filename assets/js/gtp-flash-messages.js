/**
 * Auto-dismiss flash success/error banners after a few seconds.
 * Messages with .gtp-persist (e.g. "please fill this out") stay visible.
 */
(function () {
    var DELAY_MS = 4000;
    var FADE_MS = 350;
    var SELECTOR = [
        '.gtp-msg.is-success:not(.gtp-persist)',
        '.gtp-msg.is-error:not(.gtp-persist)',
        '.gtp-checkin-msg.is-success:not(.gtp-persist)',
        '.gtp-checkin-msg.is-error:not(.gtp-persist)',
        '.gtp-resources-msg.is-success:not(.gtp-persist)',
        '.gtp-resources-msg.is-error:not(.gtp-persist)',
        '.gtp-home-success:not(.gtp-persist)',
        '.gtp-flash:not(.gtp-persist)'
    ].join(',');

    function dismiss(el) {
        if (!el || !el.parentNode) {
            return;
        }
        el.classList.add('gtp-flash-out');
        window.setTimeout(function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, FADE_MS);
    }

    function schedule() {
        document.querySelectorAll(SELECTOR).forEach(function (el) {
            if (el.getAttribute('data-gtp-flash-armed')) {
                return;
            }
            el.setAttribute('data-gtp-flash-armed', '1');
            window.setTimeout(function () {
                dismiss(el);
            }, DELAY_MS);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', schedule);
    } else {
        schedule();
    }
})();
