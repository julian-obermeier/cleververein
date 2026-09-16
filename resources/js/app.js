document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const close = () => {
        sidebar?.classList.add('-translate-x-full');
        backdrop?.classList.add('hidden');
        toggle?.setAttribute('aria-expanded', 'false');
    };
    toggle?.addEventListener('click', () => {
        const opening = sidebar?.classList.contains('-translate-x-full');
        sidebar?.classList.toggle('-translate-x-full');
        backdrop?.classList.toggle('hidden');
        toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
    backdrop?.addEventListener('click', close);
    document.addEventListener('keydown', event => event.key === 'Escape' && close());
});
