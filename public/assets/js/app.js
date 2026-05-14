// Governance Intelligence Portal — app.js

document.addEventListener('DOMContentLoaded', () => {
  initSystemTelemetry();
  initConfirmations();
  initFlashDismiss();
  initCopyButtons();
  initMobileSidebar();
  initSidebarSubmenus();
  initTabs();
  initModals();
});

// ── Confirmation dialogs ──────────────────────────────────────────────────────
function initConfirmations() {
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      const msg = el.getAttribute('data-confirm') || 'Are you sure?';
      if (!confirm(msg)) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  });
}

// ── Flash message auto-dismiss ────────────────────────────────────────────────
function initFlashDismiss() {
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
    const delay = parseInt(alert.getAttribute('data-auto-dismiss') || '5000', 10);
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transition = 'opacity .4s ease';
      setTimeout(() => alert.remove(), 400);
    }, delay);
  });

  document.querySelectorAll('.alert .alert-close').forEach(btn => {
    btn.addEventListener('click', () => {
      const alert = btn.closest('.alert');
      if (alert) {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity .3s';
        setTimeout(() => alert.remove(), 300);
      }
    });
  });
}

// ── Copy to clipboard ─────────────────────────────────────────────────────────
function initCopyButtons() {
  document.querySelectorAll('[data-copy]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target  = btn.getAttribute('data-copy');
      const el      = document.querySelector(target) || document.getElementById(target.replace('#', ''));
      const text    = el ? (el.value || el.textContent || '').trim() : target;
      const original = btn.textContent;

      navigator.clipboard.writeText(text).then(() => {
        btn.textContent = '✓ Copied';
        btn.style.color = '#4caf50';
        setTimeout(() => {
          btn.textContent = original;
          btn.style.color = '';
        }, 2000);
      }).catch(() => {
        // Fallback when clipboard API is unavailable (non-HTTPS, permission denied, or older browsers)
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        btn.textContent = '✓ Copied';
        setTimeout(() => { btn.textContent = original; }, 2000);
      });
    });
  });
}

// ── Mobile sidebar toggle ─────────────────────────────────────────────────────
function initMobileSidebar() {
  const toggle  = document.getElementById('sidebar-toggle');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.getElementById('sidebar-overlay');

  if (!toggle || !sidebar) return;

  toggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('hidden');
  });

  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.add('hidden');
    });
  }
}

// ── Sidebar submenus ───────────────────────────────────────────────────────────
function initSidebarSubmenus() {
  document.querySelectorAll('[data-sidebar-group]').forEach(group => {
    const toggle = group.querySelector('[data-sidebar-toggle]');
    if (!toggle) return;

    toggle.addEventListener('click', () => {
      const isOpen = group.classList.toggle('open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  });
}

// ── Tab switching ─────────────────────────────────────────────────────────────
function initTabs() {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const tabGroup = btn.closest('[data-tabs]');
      if (!tabGroup) return;

      const target = btn.getAttribute('data-tab');

      tabGroup.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      tabGroup.querySelectorAll('.tab-panel').forEach(p => {
        p.classList.toggle('hidden', p.getAttribute('data-panel') !== target);
      });
    });
  });
}

// ── Modal ─────────────────────────────────────────────────────────────────────
function initModals() {
  document.querySelectorAll('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      // Prevent default navigation for anchor elements that open modals
      if (e && typeof e.preventDefault === 'function') {
        e.preventDefault();
        e.stopPropagation();
      }
      const id    = btn.getAttribute('data-modal-open');
      const modal = document.getElementById(id);
      if (modal) modal.classList.remove('hidden');
    });
  });

  document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal-overlay');
      if (modal) modal.classList.add('hidden');
    });
  });

  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) overlay.classList.add('hidden');
    });
  });
}

// ── Format numbers ────────────────────────────────────────────────────────────
window.formatCredits = (n) =>
  parseFloat(n).toLocaleString('en-ZA', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

// ── System telemetry / localStorage logger ───────────────────────────────────
function initSystemTelemetry() {
  const storageKey = 'gi_system_event_log';
  const maxEntries = 1000;
  const context = window.__GI_CONTEXT || {};

  function readLogs() {
    try {
      return JSON.parse(localStorage.getItem(storageKey) || '[]');
    } catch {
      return [];
    }
  }

  function writeLogs(logs) {
    localStorage.setItem(storageKey, JSON.stringify(logs.slice(-maxEntries)));
  }

  function sanitizeFormData(form) {
    const data = {};
    if (!form) return data;
    const fd = new FormData(form);
    fd.forEach((value, key) => {
      const k = String(key).toLowerCase();
      const isSensitive = k.includes('password') || k.includes('cvc') || k.includes('cvv') || k.includes('card_number') || k.includes('token');
      data[key] = isSensitive ? '[REDACTED]' : String(value);
    });
    return data;
  }

  function logEvent(type, payload = {}) {
    const event = {
      id: Math.random().toString(36).slice(2, 12),
      at: new Date().toISOString(),
      type,
      route: window.location.pathname,
      user: context.user || null,
      payload
    };

    const logs = readLogs();
    logs.push(event);
    writeLogs(logs);
    console.log('[GI-LOG]', event);
  }

  window.GI_LOG = logEvent;
  window.GI_LOGS = {
    dump: () => readLogs(),
    clear: () => localStorage.removeItem(storageKey)
  };

  logEvent('app_boot', {
    title: document.title,
    href: window.location.href,
    userAgent: navigator.userAgent
  });

  // Basic route/page view log
  logEvent('page_view', {
    referrer: document.referrer || null
  });

  // Click logging for important UI actions
  document.addEventListener('click', (e) => {
    const el = e.target.closest('a, button, [data-modal-open], [data-modal-close], [data-copy], [data-confirm]');
    if (!el) return;
    logEvent('click', {
      tag: el.tagName,
      id: el.id || null,
      text: (el.textContent || '').trim().slice(0, 120),
      href: el.getAttribute('href') || null,
      classes: el.className || null
    });
  });

  // Form submissions and field payload snapshot
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    logEvent('form_submit', {
      action: form.getAttribute('action') || window.location.pathname,
      method: (form.getAttribute('method') || 'GET').toUpperCase(),
      fields: sanitizeFormData(form)
    });
  });

  // Search input changes (for updates page and others)
  document.addEventListener('input', (e) => {
    const target = e.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (target.type === 'search' || /search/i.test(target.id) || /search/i.test(target.name)) {
      logEvent('search_input', {
        id: target.id || null,
        name: target.name || null,
        value: target.value
      });
    }
  });

  // Client errors
  window.addEventListener('error', (e) => {
    logEvent('js_error', {
      message: e.message,
      source: e.filename,
      line: e.lineno,
      column: e.colno
    });
  });

  window.addEventListener('unhandledrejection', (e) => {
    logEvent('promise_rejection', {
      reason: String(e.reason || 'unknown')
    });
  });
}
