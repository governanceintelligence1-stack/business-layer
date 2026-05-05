<?php $pageTitle = 'Register'; ?>

<div class="auth-wrapper">
  <nav class="public-nav">
    <div class="public-nav-inner">
      <a href="/" class="nav-logo">
        <div class="nav-logo-icon">GI</div>
        <span>Governance <span>Intelligence</span></span>
      </a>
    </div>
  </nav>

  <div class="auth-container" style="align-items:flex-start;padding-top:3rem;">
    <div class="auth-box" style="max-width:640px;">
      <h1 class="auth-title">Register Your Organisation</h1>
      <p class="auth-sub">Set up your Governance Intelligence Portal account.</p>

      <form method="POST" action="/auth/register">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">

        <div class="section-label" style="margin-bottom:.5rem;">Personal Details</div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">First Name *</label>
            <input type="text" name="first_name" class="form-control" required placeholder="Jane"
                   value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Last Name *</label>
            <input type="text" name="last_name" class="form-control" required placeholder="Smith"
                   value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" required placeholder="jane@company.co.za"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="section-label" style="margin:.75rem 0 .5rem;">Organisation Details</div>
        <div class="form-group">
          <label class="form-label">Organisation Name *</label>
          <input type="text" name="organisation_name" class="form-control" required placeholder="Acme Corporation (Pty) Ltd"
                 value="<?= htmlspecialchars($_POST['organisation_name'] ?? '') ?>">
        </div>

        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Account Type</label>
            <select name="account_type" class="form-control">
              <option value="individual" <?= ($_POST['account_type'] ?? '') === 'individual' ? 'selected' : '' ?>>Individual</option>
              <option value="company"    <?= ($_POST['account_type'] ?? '') === 'company'    ? 'selected' : '' ?>>Company</option>
              <option value="government" <?= ($_POST['account_type'] ?? '') === 'government' ? 'selected' : '' ?>>Government</option>
              <option value="ngo"        <?= ($_POST['account_type'] ?? '') === 'ngo'        ? 'selected' : '' ?>>NGO / Non-Profit</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Country</label>
            <select name="country" class="form-control">
              <option value="ZA" selected>South Africa</option>
              <option value="ZW">Zimbabwe</option>
              <option value="BW">Botswana</option>
              <option value="NA">Namibia</option>
              <option value="MZ">Mozambique</option>
              <option value="OTHER">Other</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-control" placeholder="+27 11 000 0000"
                 value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100 mt-2">
          Create Account
        </button>
      </form>

      <div class="auth-footer">
        Already have an account? <a href="/auth/login">Log in</a>
      </div>
    </div>
  </div>
</div>
