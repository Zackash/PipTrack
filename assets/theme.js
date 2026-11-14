(() => {
    'use strict';

    const storageKey = 'piptrack-theme';
    const root = document.documentElement;

    function setTheme(theme) {
        const isDark = theme === 'dark';
        root.dataset.theme = isDark ? 'dark' : 'light';
        const themeColor = document.querySelector('meta[name="theme-color"]');
        if (themeColor) themeColor.content = isDark ? '#111713' : '#101812';
        const button = document.getElementById('themeToggle');
        if (!button) return;
        const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-pressed', String(isDark));
        button.title = label;
    }

    setTheme(window.localStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light');

    document.addEventListener('DOMContentLoaded', () => {
        const button = document.getElementById('themeToggle');
        if (!button) return;
        setTheme(root.dataset.theme);
        button.addEventListener('click', () => {
            const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
            setTheme(theme);
            window.localStorage.setItem(storageKey, theme);
        });
    });
})();
