<style>
  body { overflow: hidden; }
  .auth-container {
    min-height: calc(100vh - 76px);
    padding: .75rem .75rem !important;
    align-items: center !important;
  }
  .auth-box {
    max-height: calc(100vh - 108px);
    overflow: hidden;
    padding: 1.5rem;
  }
  .auth-title { margin-bottom: .25rem; }
  .auth-sub { margin-bottom: .9rem; }
  .register-steps {
    display: flex;
    justify-content: center;
    gap: .5rem;
    margin: 0 0 .8rem;
  }
  .register-step-pill {
    border: 1px solid var(--border);
    color: var(--text-muted);
    font-size: .72rem;
    padding: .25rem .55rem;
    border-radius: 999px;
  }
  .register-step-pill.is-active {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(0, 113, 227, .08);
  }
  .register-step { display: none; }
  .register-step.is-active { display: block; }
  .register-actions {
    display: flex;
    gap: .5rem;
    margin-top: .65rem;
  }
  .register-actions .btn { flex: 1; }
  .register-actions.step-one { align-items: center; }
  .register-actions.step-one .btn {
    text-align: center;
    justify-content: center;
    flex: 0 0 42%;
    max-width: 42%;
    margin-left: auto;
    margin-bottom: 1rem;
  }
  .register-inline-login {
    flex: 1;
    text-align: left;
    font-size: .85rem;
    color: var(--text-muted);
    white-space: nowrap;
  }
  .register-step > div + div { margin-top: .75rem; }
  .register-step[data-step="1"] .form-group:last-of-type { margin-bottom: 1rem; }
  .register-step .form-group { margin-bottom: .65rem; }
  .register-step .form-label { margin-bottom: .25rem; }
</style>

<div class="register-steps" aria-label="Registration progress">
  <span class="register-step-pill is-active" data-pill="1">Part 1: Personal</span>
  <span class="register-step-pill" data-pill="2">Part 2: Organisation</span>
</div>

<form method="POST" action="/auth/register" id="registerForm">
  <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
  <input type="hidden" name="_register_step" id="registerStepInput" value="1">

  <section class="register-step is-active" data-step="1">
    <div class="section-label" style="margin-bottom:.45rem;">Personal Details</div>
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

    <div class="register-actions step-one">
      <div class="register-inline-login">
          Already have an account? <a href="/auth/login">Log in</a>
      </div>
      <button type="button" class="btn btn-primary w-50" id="nextStepBtn">Continue to Organisation</button>
      
    </div>
  </section>

  <section class="register-step" data-step="2">
    <div class="section-label" style="margin:.25rem 0 .45rem;">Organisation Details</div>
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
          <option value="company" <?= ($_POST['account_type'] ?? '') === 'company' ? 'selected' : '' ?>>Company</option>
          <option value="government" <?= ($_POST['account_type'] ?? '') === 'government' ? 'selected' : '' ?>>Government</option>
          <option value="ngo" <?= ($_POST['account_type'] ?? '') === 'ngo' ? 'selected' : '' ?>>NGO / Non-Profit</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Country</label>
        <select name="country" class="form-control">
          <option value="ZA" <?= ($_POST['country'] ?? 'ZA') === 'ZA' ? 'selected' : '' ?>>South Africa</option>
          <option value="ZW" <?= ($_POST['country'] ?? '') === 'ZW' ? 'selected' : '' ?>>Zimbabwe</option>
          <option value="BW" <?= ($_POST['country'] ?? '') === 'BW' ? 'selected' : '' ?>>Botswana</option>
          <option value="NA" <?= ($_POST['country'] ?? '') === 'NA' ? 'selected' : '' ?>>Namibia</option>
          <option value="MZ" <?= ($_POST['country'] ?? '') === 'MZ' ? 'selected' : '' ?>>Mozambique</option>
          <option value="OTHER" <?= ($_POST['country'] ?? '') === 'OTHER' ? 'selected' : '' ?>>Other</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Phone Number</label>
      <input type="tel" name="phone" class="form-control" placeholder="+27 11 000 0000"
             value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
    </div>

    <div class="register-actions">
      <button type="button" class="btn btn-ghost w-100" id="prevStepBtn">Back</button>
      <button type="submit" class="btn btn-primary w-100">Create Account</button>
    </div>
  </section>
</form>

<script>
  (function () {
    var form = document.getElementById('registerForm');
    if (!form) return;

    var stepInput = document.getElementById('registerStepInput');
    var stepOne = form.querySelector('[data-step="1"]');
    var stepTwo = form.querySelector('[data-step="2"]');
    var nextBtn = document.getElementById('nextStepBtn');
    var prevBtn = document.getElementById('prevStepBtn');
    var pills = document.querySelectorAll('[data-pill]');

    function setStep(step) {
      var isStepOne = step === 1;
      stepOne.classList.toggle('is-active', isStepOne);
      stepTwo.classList.toggle('is-active', !isStepOne);
      stepInput.value = String(step);
      pills.forEach(function (pill) {
        pill.classList.toggle('is-active', Number(pill.getAttribute('data-pill')) === step);
      });
    }

    nextBtn.addEventListener('click', function () {
      var stepOneFields = stepOne.querySelectorAll('input, select, textarea');
      for (var i = 0; i < stepOneFields.length; i++) {
        if (!stepOneFields[i].checkValidity()) {
          stepOneFields[i].reportValidity();
          return;
        }
      }
      setStep(2);
    });

    prevBtn.addEventListener('click', function () {
      setStep(1);
    });
  })();
</script>
