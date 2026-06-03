<?php
/**
 * @var list<array<string, mixed>> $subscriptions
 * @var bool $compact
 */
$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$compact = (bool) ($compact ?? false);

$formatDate = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }

    return substr($value, 0, 10);
};

$statusBadgeClass = static function (string $status): string {
    return match (strtolower($status)) {
        'active' => 'active',
        'cancelled', 'canceled', 'expired', 'ended', 'revoked' => 'revoked',
        default => 'pending',
    };
};
?>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Plan</th>
        <th>Billing</th>
        <th>Status</th>
        <th>Started</th>
        <th>Ended</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($subscriptions === []): ?>
        <tr><td colspan="5" style="text-align:center;padding:1rem;">No subscriptions found.</td></tr>
      <?php else: ?>
        <?php foreach ($subscriptions as $sub): ?>
          <?php
            $status = strtolower((string) ($sub['status'] ?? ''));
            $started = (string) ($sub['started_at'] ?? $sub['created_at'] ?? '');
            $ended = (string) ($sub['ended_at'] ?? $sub['cancelled_at'] ?? '');
          ?>
          <tr>
            <td><?= htmlspecialchars((string) ($sub['plan_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars(ucfirst((string) ($sub['billing_cycle'] ?? '—')), ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <span class="badge badge-<?= $statusBadgeClass($status) ?>">
                <?= htmlspecialchars($status !== '' ? ucfirst($status) : '—', ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
            <td style="font-size:.85rem;color:var(--muted-foreground);"><?= htmlspecialchars($formatDate($started), ENT_QUOTES, 'UTF-8') ?></td>
            <td style="font-size:.85rem;color:var(--muted-foreground);"><?= htmlspecialchars($formatDate($ended), ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
