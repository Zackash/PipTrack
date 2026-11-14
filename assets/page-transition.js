(() => {
    'use strict';

    const overlay = document.getElementById('pageTransition');
    const countdown = document.getElementById('pageTransitionCountdown');
    let activeTransition = null;

    function start() {
        if (!overlay || !countdown) return Promise.resolve();
        if (activeTransition) return activeTransition;

        let seconds = 3;
        countdown.textContent = String(seconds);
        overlay.classList.remove('hidden');
        overlay.setAttribute('aria-hidden', 'false');

        activeTransition = new Promise((resolve) => {
            const timer = window.setInterval(() => {
                seconds -= 1;
                if (seconds > 0) {
                    countdown.textContent = String(seconds);
                    return;
                }

                window.clearInterval(timer);
                overlay.classList.add('hidden');
                overlay.setAttribute('aria-hidden', 'true');
                activeTransition = null;
                resolve();
            }, 1000);
        });
        return activeTransition;
    }

    window.PipTrackPageTransition = { start };
})();
