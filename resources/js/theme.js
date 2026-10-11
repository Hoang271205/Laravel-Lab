const preference = window.matchMedia('(prefers-color-scheme: dark)');
let theme = window.theme || 'auto';
function applyTheme() {
    const dark = theme === 'theme-dark' || (theme === 'auto' && preference.matches);
    document.documentElement.classList.toggle('theme-dark', dark);
    document.documentElement.classList.toggle('theme-light', !dark);
    document.body.classList.toggle('bg-background', dark);
    for (const name of ['bg-gradient-to-r', 'from-rose-100', 'to-teal-100']) {
        document.body.classList.toggle(name, !dark);
    }
}
applyTheme();
window.addEventListener('theme-changed', event => {
    theme = event.detail.theme;
    applyTheme();
});
preference.addEventListener('change', applyTheme);
