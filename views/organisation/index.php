<?php $pageTitle = 'Organisation'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Organisation</h1>
    <p class="page-subtitle">Manage your organisation details and members.</p>
  </div>
</div>

<?php $tab = $tab ?? 'details'; ?>

<div data-tabs>
  <div class="tabs">
    <button class="tab-btn <?= $tab === 'details' ? 'active' : '' ?>" data-tab="details">Details</button>
    <button class="tab-btn <?= $tab === 'members' ? 'active' : '' ?>" data-tab="members">Members</button>
  </div>

  <!-- Details Tab -->
  <div data-panel="details" class="tab-panel <?= $tab !== 'details' ? 'hidden' : '' ?>">
    <div class="card" style="max-width:640px;">
      <div class="card-header">
        <h3 class="card-title">Organisation Details</h3>
      </div>
      <form method="POST" action="/organisation">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">

        <div class="form-group">
          <label class="form-label">Organisation Name</label>
          <input type="text" name="name" class="form-control"
                 value="<?= htmlspecialchars($org['name'] ?? '') ?>" required>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Billing email</label>
            <input type="email" name="billing_email" class="form-control"
                   value="<?= htmlspecialchars($org['billing_email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Tax number</label>
            <input type="text" name="tax_number" class="form-control"
                   value="<?= htmlspecialchars($org['tax_number'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Country</label>
            <select name="country" class="form-control">
              <?php
              $countries = ['ZA' => 'South Africa', 'ZW' => 'Zimbabwe', 'BW' => 'Botswana', 'NA' => 'Namibia', 'MZ' => 'Mozambique', 'OTHER' => 'Other'];
              foreach ($countries as $code => $name):
              ?>
              <option value="<?= $code ?>" <?= ($org['country'] ?? '') === $code ? 'selected' : '' ?>>
                <?= $name ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php if (!empty($org['slug'])): ?>
        <div class="form-group">
          <label class="form-label">Slug</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($org['slug']) ?>" disabled>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </form>
    </div>
  </div>

  <!-- Members Tab -->
  <div data-panel="members" class="tab-panel <?= $tab !== 'members' ? 'hidden' : '' ?>">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Team Members</h3>
      </div>
      <?php if (!empty($members)): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
            <tr>
              <td><?= htmlspecialchars(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?></td>
              <td style="color:var(--text-muted);"><?= htmlspecialchars($m['email'] ?? '') ?></td>
              <td><span class="badge badge-gold"><?= htmlspecialchars(ucfirst($m['role'] ?? '')) ?></span></td>
              <td><span class="badge badge-<?= ($m['status'] ?? '') === 'active' ? 'active' : 'pending' ?>">
                <?= htmlspecialchars(ucfirst($m['status'] ?? '')) ?>
              </span></td>
              <td style="color:var(--text-muted);font-size:.85rem;"><?= htmlspecialchars(substr($m['created_at'] ?? '', 0, 10)) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">👥</div>
        <h3>No members yet</h3>
        <p>Members who join your organisation will appear here.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
