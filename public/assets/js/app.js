// Governance Intelligence Portal — app.js

document.addEventListener('DOMContentLoaded', () => {
  initConfirmations();
  initFlashDismiss();
  initCopyButtons();
  initMobileSidebar();
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
    btn.addEventListener('click', () => {
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
