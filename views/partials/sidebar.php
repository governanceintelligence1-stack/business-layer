  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="nav-logo-icon">GI</div>
      <span><span>SmartAnalytics</span></span>
    </div>

    <nav class="sidebar-nav">
      <div class="sidebar-section sidebar-section-one-line">Dashboard &amp; Core Management</div>
      <a href="/dashboard" class="sidebar-link sidebar-link-dashboard <?= isActiveLink('/dashboard', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>
      <a href="/organisation" class="sidebar-link <?= isActiveLink('/organisation', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M9 21V7l6-4v18M9 7H3v14"/></svg>
        Organisation
      </a>

      <div class="sidebar-section">Products & Plans</div>
      <div class="sidebar-group <?= $isProductsOpen ? 'open' : '' ?>" data-sidebar-group>
        <button type="button"
                class="sidebar-link sidebar-link-toggle <?= isActiveLink('/products', $currentUri) ?>"
                data-sidebar-toggle
                aria-expanded="<?= $isProductsOpen ? 'true' : 'false' ?>">
          <span class="sidebar-link-content">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Products
          </span>
          <svg class="sidebar-chevron" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="9 6 15 12 9 18"></polyline>
          </svg>
        </button>
        <div class="sidebar-submenu">
          <a href="http://upload.gismartanalytics.com" class="sidebar-sub-link">Upload Forensic Image</a>
          <a href="http://ocr.gismartanalytics.com" class="sidebar-sub-link">OCR</a>
          <a href="http://transcription.gismartanalytics.com" class="sidebar-sub-link">Transcription</a>
          <a href="http://bank.gismartanalytics.com" class="sidebar-sub-link">Bank Statements</a>
          <a href="http://file.gismartanalytics.com" class="sidebar-sub-link">File Comparison</a>
          <a href="http://mobile.gismartanalytics.com" class="sidebar-sub-link">Mobile Forensics</a>

        </div>
      </div>
      <a href="/plans" class="sidebar-link <?= isActiveLink('/plans', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Plans
      </a>
      <a href="/subscriptions" class="sidebar-link <?= isActiveLink('/subscriptions', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Subscriptions
      </a>

      <div class="sidebar-section">Usage &amp; Billing</div>
      <a href="/credits" class="sidebar-link <?= isActiveLink('/credits', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        Credits
      </a>
      <a href="/api-keys" class="sidebar-link <?= isActiveLink('/api-keys', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        API Keys
      </a>
      <a href="/billing" class="sidebar-link <?= isActiveLink('/billing', $currentUri) ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        Billing
      </a>
    </nav>

    <div class="sidebar-footer">
      <a href="/profile" class="sidebar-user sidebar-user-link">
        <div class="sidebar-avatar"><?= htmlspecialchars($userInitials) ?></div>
        <div>
          <div class="sidebar-user-name"><?= htmlspecialchars($userFullName) ?></div>
          <div class="sidebar-user-role"><?= htmlspecialchars($userRole) ?></div>
        </div>
      </a>
      <a href="/auth/logout" class="btn btn-sm w-100 sidebar-logout-btn"
         data-confirm="Are you sure you want to log out?">
        Log Out
      </a>
    </div>
  </aside>
