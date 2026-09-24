(() => {
  const toggle = document.querySelector('.nav-toggle');
  const sidebar = document.querySelector('.sidebar');
  const backdrop = document.querySelector('.sidebar-backdrop');
  const mobile = window.matchMedia('(max-width: 980px)');
  let returnFocus = null;
  const setOpen = (open, restoreFocus = false) => {
    const shouldOpen = Boolean(open && mobile.matches);
    document.body.classList.toggle('sidebar-open', shouldOpen);
    toggle?.setAttribute('aria-expanded', String(shouldOpen));
    const label = toggle?.querySelector('.nav-toggle-label');
    if (label) label.textContent = shouldOpen ? 'Tutup menu' : 'Buka menu';
    if (sidebar) sidebar.setAttribute('aria-hidden', String(mobile.matches && !shouldOpen));
    if (shouldOpen) {
      returnFocus = document.activeElement;
      sidebar?.querySelector('a, button')?.focus();
    } else if (restoreFocus && returnFocus instanceof HTMLElement) {
      returnFocus.focus();
    }
  };
  toggle?.addEventListener('click', () => setOpen(!document.body.classList.contains('sidebar-open'), true));
  backdrop?.addEventListener('click', () => setOpen(false, true));
  sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) setOpen(false, true);
  });
  const syncViewport = () => setOpen(false);
  mobile.addEventListener?.('change', syncViewport);
  syncViewport();
  document.querySelectorAll('.alert-close').forEach((button) => button.addEventListener('click', () => button.closest('.alert')?.remove()));
  document.querySelectorAll('[data-confirm]').forEach((element) => element.addEventListener('click', (event) => {
    if (!window.confirm(element.dataset.confirm || 'Teruskan?')) event.preventDefault();
  }));
  document.querySelectorAll('[data-auto-submit]').forEach((input) => input.addEventListener('change', () => input.form?.submit()));
  document.querySelectorAll('[data-print]').forEach((button) => button.addEventListener('click', () => window.print()));
})();
