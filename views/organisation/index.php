<?php
$pageTitle = 'Organisation';
$tab = $tab ?? 'details';
$canManageTeam = (bool) ($canManageTeam ?? false);
$invitations = $invitations ?? [];
$members = $members ?? [];

$roleLabel = static fn (string $role): string => ucfirst(str_replace('_', ' ', $role));
$statusBadge = static function (string $status): string {
    return match (strtolower($status)) {
        'active', 'accepted' => 'active',
        'cancelled', 'removed', 'suspended' => 'revoked',
        default => 'pending',
    };
};
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Organisation</h1>
    <p class="page-subtitle">Manage organisation details, members, and invitations.</p>
  </div>
</div>

<div data-tabs>
  <div class="tabs">
    <button class="tab-btn <?= $tab === 'details' ? 'active' : '' ?>" data-tab="details">Details</button>
    <button class="tab-btn <?= $tab === 'members' ? 'active' : '' ?>" data-tab="members">Members</button>
    <button class="tab-btn <?= $tab === 'invitations' ? 'active' : '' ?>" data-tab="invitations">Invitations</button>
  </div>

  <div data-panel="details" class="tab-panel <?= $tab !== 'details' ? 'hidden' : '' ?>">
    <div class="card" style="max-width:720px;">
      <div class="card-header">
        <h3 class="card-title">Organisation Details</h3>
      </div>
      <form method="POST" action="/organisation">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">

        <div class="form-group">
          <label class="form-label">Organisation Name</label>
          <input type="text" name="name" class="form-control"
                 value="<?= htmlspecialchars($org['name'] ?? $org['organisation_name'] ?? '') ?>" required>
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
          <?php if (!empty($org['slug'] ?? $org['organisation_slug'] ?? '')): ?>
          <div class="form-group">
            <label class="form-label">Slug</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($org['slug'] ?? $org['organisation_slug']) ?>" disabled>
          </div>
          <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </form>
    </div>
  </div>

  <div data-panel="members" class="tab-panel <?= $tab !== 'members' ? 'hidden' : '' ?>">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Team Members</h3>
        <?php if ($canManageTeam): ?>
          <a href="/organisation/invitations" class="btn btn-primary btn-sm">Invite Member</a>
        <?php endif; ?>
      </div>
      <?php if (!empty($members)): ?>
      <?php if ($canManageTeam): ?>
      <form id="members-role-update-form" method="POST" action="/organisation/members/update">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
      </form>
      <?php endif; ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Joined</th>
              <?php if ($canManageTeam): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
            <?php
              $membershipId = (string) ($m['membership_id'] ?? $m['id'] ?? '');
              $name = trim((string) (($m['display_name'] ?? '') ?: (($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))));
              $status = (string) ($m['membership_status'] ?? $m['status'] ?? 'active');
              $role = (string) ($m['membership_role'] ?? $m['role'] ?? 'viewer');
            ?>
            <tr>
              <td><?= htmlspecialchars($name !== '' ? $name : 'Pending profile') ?></td>
              <td style="color:var(--text-muted);"><?= htmlspecialchars($m['email'] ?? '') ?></td>
              <td><span class="badge badge-gold"><?= htmlspecialchars($roleLabel($role)) ?></span></td>
              <td><span class="badge badge-<?= $statusBadge($status) ?>"><?= htmlspecialchars($roleLabel($status)) ?></span></td>
              <td style="color:var(--text-muted);font-size:.85rem;"><?= htmlspecialchars(substr((string) ($m['created_at'] ?? $m['membership_created_at'] ?? ''), 0, 10)) ?></td>
              <?php if ($canManageTeam): ?>
              <td>
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:nowrap;white-space:nowrap;">
                  <?php if ($role === 'owner'): ?>
                    <span class="badge badge-gold">Owner role locked</span>
                  <?php else: ?>
                    <select name="roles[<?= htmlspecialchars($membershipId, ENT_QUOTES, 'UTF-8') ?>]"
                            form="members-role-update-form"
                            class="form-control"
                            style="min-width:120px;">
                      <?php foreach (['admin', 'billing_admin', 'developer', 'analyst', 'member', 'viewer'] as $option): ?>
                      <option value="<?= $option ?>" <?= $role === $option ? 'selected' : '' ?>><?= htmlspecialchars($roleLabel($option)) ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                  <?php if ($role !== 'owner'): ?>
                  <form method="POST"
                        action="/organisation/members/<?= rawurlencode($membershipId) ?>/remove"
                        style="margin:0;"
                        onsubmit="return confirm('Remove this member from the organisation?');">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
                    <button class="btn btn-ghost btn-sm" type="submit">Remove</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($canManageTeam): ?>
      <div style="display:flex;justify-content:flex-end;margin-top:1rem;">
        <button class="btn btn-primary" type="submit" form="members-role-update-form">Update Roles</button>
      </div>
      <?php endif; ?>
      <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">TEAM</div>
        <h3>No members yet</h3>
        <p>Members who join your organisation will appear here.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div data-panel="invitations" class="tab-panel <?= $tab !== 'invitations' ? 'hidden' : '' ?>">
    <?php if ($canManageTeam): ?>
    <div class="card" style="max-width:760px;margin-bottom:1rem;">
      <div class="card-header">
        <h3 class="card-title">Invite Member</h3>
      </div>
      <form method="POST" action="/organisation/invitations">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" placeholder="name@example.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Role</label>
            <select class="form-control" name="role">
              <option value="member">Member</option>
              <option value="admin">Admin</option>
              <option value="viewer">Viewer</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Send Invitation</button>
      </form>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Invitations</h3>
      </div>
      <?php if (!empty($invitations)): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Expires</th>
              <th>Accepted</th>
              <?php if ($canManageTeam): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($invitations as $invite): ?>
            <?php
              $inviteId = (string) ($invite['invite_id'] ?? $invite['id'] ?? '');
              $status = (string) ($invite['status'] ?? 'pending');
              $token = (string) ($invite['invite_token'] ?? $invite['token'] ?? '');
              $inviteLink = $token !== '' ? rtrim((string) ($_ENV['APP_URL'] ?? ''), '/') . '/invite/' . rawurlencode($token) : '';
            ?>
            <tr>
              <td><?= htmlspecialchars($invite['invited_email'] ?? $invite['email'] ?? '') ?></td>
              <td><span class="badge badge-gold"><?= htmlspecialchars($roleLabel((string) ($invite['invited_role'] ?? $invite['role'] ?? 'viewer'))) ?></span></td>
              <td><span class="badge badge-<?= $statusBadge($status) ?>"><?= htmlspecialchars($roleLabel($status)) ?></span></td>
              <td style="color:var(--text-muted);font-size:.85rem;"><?= htmlspecialchars(substr((string) ($invite['expires_at'] ?? ''), 0, 19)) ?></td>
              <td style="color:var(--text-muted);font-size:.85rem;"><?= htmlspecialchars(substr((string) ($invite['accepted_at'] ?? ''), 0, 19)) ?></td>
              <?php if ($canManageTeam): ?>
              <td>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                  <?php if ($inviteLink !== ''): ?>
                    <input class="form-control" style="max-width:280px;" value="<?= htmlspecialchars($inviteLink) ?>" readonly>
                  <?php endif; ?>
                  <?php if ($status === 'pending' && $inviteId !== ''): ?>
                  <form method="POST" action="/organisation/invitations/<?= rawurlencode($inviteId) ?>/cancel">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
                    <button class="btn btn-ghost btn-sm" type="submit">Cancel</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">MAIL</div>
        <h3>No invitations yet</h3>
        <p>Pending and past invitations will appear here.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
