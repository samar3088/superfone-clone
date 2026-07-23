/* Superfone Admin UI — shared behaviors (no dependencies) */

/* Theme: honor saved choice, else OS preference; toggle persists. */
const root = document.documentElement;
const savedTheme = localStorage.getItem('sf-theme');
if (savedTheme) root.dataset.theme = savedTheme;

function syncThemeIcon() {
  const isDark =
    root.dataset.theme === 'dark' ||
    (!root.dataset.theme && matchMedia('(prefers-color-scheme: dark)').matches);
  document.querySelectorAll('[data-theme-toggle]').forEach((b) => {
    b.textContent = isDark ? '☀️' : '🌙';
    b.setAttribute('aria-label', isDark ? 'Switch to light theme' : 'Switch to dark theme');
  });
}

document.addEventListener('click', (e) => {
  /* theme toggle */
  const themeBtn = e.target.closest('[data-theme-toggle]');
  if (themeBtn) {
    const isDark =
      root.dataset.theme === 'dark' ||
      (!root.dataset.theme && matchMedia('(prefers-color-scheme: dark)').matches);
    root.dataset.theme = isDark ? 'light' : 'dark';
    localStorage.setItem('sf-theme', root.dataset.theme);
    syncThemeIcon();
    return;
  }

  /* sidebar drawer (mobile) */
  if (e.target.closest('[data-drawer-open]')) {
    document.querySelector('.sidebar')?.classList.add('open');
    document.querySelector('.side-scrim')?.classList.add('open');
    return;
  }
  if (e.target.closest('[data-drawer-close]') || e.target.classList.contains('side-scrim')) {
    document.querySelector('.sidebar')?.classList.remove('open');
    document.querySelector('.side-scrim')?.classList.remove('open');
  }

  /* tabs: [data-tab="x"] shows [data-panel="x"] within the same [data-tabset] */
  const tab = e.target.closest('[data-tab]');
  if (tab) {
    const scope = tab.closest('[data-tabset]') || document;
    scope.querySelectorAll('[data-tab]').forEach((t) => t.classList.toggle('active', t === tab));
    scope.querySelectorAll('[data-panel]').forEach((p) =>
      p.classList.toggle('active', p.dataset.panel === tab.dataset.tab)
    );
    return;
  }

  /* modals: [data-open="#id"] / [data-close] / click on scrim */
  const opener = e.target.closest('[data-open]');
  if (opener) {
    document.querySelector(opener.dataset.open)?.classList.add('open');
    return;
  }
  if (e.target.closest('[data-close]') || e.target.classList.contains('scrim')) {
    e.target.closest('.scrim')?.classList.remove('open');
    if (e.target.classList.contains('scrim')) e.target.classList.remove('open');
    return;
  }

  /* switches */
  const toggle = e.target.closest('.toggle');
  if (toggle) {
    toggle.setAttribute('aria-checked', toggle.getAttribute('aria-checked') !== 'true');
    return;
  }

  /* steppers (+ live total in the addon modal) */
  const step = e.target.closest('.stepper button');
  if (step) {
    const out = step.parentElement.querySelector('output');
    const delta = step.dataset.step === '-' ? -1 : 1;
    out.value = Math.max(0, (parseInt(out.value || '0', 10) || 0) + delta);
    out.textContent = out.value;
    updateAddonTotal();
  }
});

function updateAddonTotal() {
  const totalEl = document.querySelector('[data-addon-total]');
  if (!totalEl) return;
  let total = 0;
  document.querySelectorAll('[data-price]').forEach((row) => {
    const qty = parseInt(row.querySelector('output')?.value || '0', 10) || 0;
    total += qty * Number(row.dataset.price);
  });
  totalEl.textContent = '₹' + total.toLocaleString('en-IN');
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape')
    document.querySelectorAll('.scrim.open').forEach((s) => s.classList.remove('open'));
});

syncThemeIcon();
