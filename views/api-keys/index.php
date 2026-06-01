<?php $pageTitle = 'API Keys'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">API Keys</h1>
    <p class="page-subtitle">Manage API keys for programmatic access to GI products.</p>
  </div>
  <button class="btn btn-primary" data-modal-open="create-key-modal">+ Create API Key</button>
</div>

<?php if (!empty($apiKeys)): ?>
<div class="card mb-4">
  <div class="card-header">
    <h3 class="card-title">Your API Keys</h3>
    <span style="font-size:.85rem;color:var(--text-muted);"><?= count($apiKeys) ?> key<?= count($apiKeys) !== 1 ? 's' : '' ?></span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Prefix</th>
          <th>Product</th>
          <th>Status</th>
          <th>Created</th>
          <th>Last Used</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($apiKeys as $key): ?>
        <tr>
          <td style="font-weight:500;"><?= htmlspecialchars($key['name']) ?></td>
          <td>
            <code style="background:rgba(200,168,75,.1);color:var(--accent);padding:.2rem .5rem;border-radius:4px;font-size:.85rem;">
              <?= htmlspecialchars($key['key_prefix']) ?>…
            </code>
          </td>
          <td style="color:var(--text-muted);"><?= htmlspecialchars($key['product_name'] ?? 'All Products') ?></td>
          <td>
            <span class="badge badge-<?= $key['status'] === 'active' ? 'active' : 'revoked' ?>">
              <?= htmlspecialchars(ucfirst($key['status'])) ?>
            </span>
          </td>
          <td style="font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars(substr($key['created_at'], 0, 10)) ?></td>
          <td style="font-size:.8rem;color:var(--text-muted);"><?= $key['last_used_at'] ? htmlspecialchars(substr($key['last_used_at'], 0, 10)) : '—' ?></td>
          <td>
            <?php if ($key['status'] === 'active'): ?>
            <form method="POST" action="/api-keys/revoke/<?= htmlspecialchars($key['id']) ?>" style="display:inline;">
              <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      data-confirm="Revoke API key '<?= htmlspecialchars($key['name']) ?>'? This cannot be undone.">
                Revoke
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="empty-state">
    <div class="empty-state-icon">🔑</div>
    <h3>No API keys yet</h3>
    <p>Create your first API key to start making programmatic requests.</p>
    <button class="btn btn-primary mt-2" data-modal-open="create-key-modal">Create API Key</button>
  </div>
</div>
<?php endif; ?>

<!-- API Docs snippet -->
<div class="card mt-4">
  <div class="card-header">
    <h3 class="card-title">API Usage Example</h3>
  </div>
  <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem;">
    Include your API key in the <code style="color:var(--accent);">X-API-Key</code> header with every request.
    Reserve tokens before a job starts; capture on success or release on failure.
  </div>
  <div class="code-block" id="api-example"># 1) Reserve estimated tokens (locks balance until job finishes)
curl -X POST https://my.gismartanalytics.com/api/v1/reserve \
  -H "X-API-Key: gi_your_key_here" \
  -H "Content-Type: application/json" \
  -d '{"org_id":"your-org-id","product_slug":"governance-analytics","job_id":"550e8400-e29b-41d4-a716-446655440000","estimated_tokens":250}'

# 2a) On success — capture actual usage
curl -X POST https://my.gismartanalytics.com/api/v1/capture \
  -H "X-API-Key: gi_your_key_here" \
  -H "Content-Type: application/json" \
  -d '{"job_id":"550e8400-e29b-41d4-a716-446655440000","amount":240}'

# 2b) On failure — release the hold
curl -X POST https://my.gismartanalytics.com/api/v1/release \
  -H "X-API-Key: gi_your_key_here" \
  -H "Content-Type: application/json" \
  -d '{"job_id":"550e8400-e29b-41d4-a716-446655440000"}'</div>
  <button class="btn btn-ghost btn-sm mt-2" data-copy="#api-example">Copy Example</button>
</div>

<!-- Create Key Modal -->
<div id="create-key-modal" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3>Create API Key</h3>
      <button class="modal-close">&times;</button>
    </div>
    <form method="POST" action="/api-keys/create">
      <input type="hidden" name="_token" value="<?= htmlspecialchars(\GI\Core\Session::getCsrfToken()) ?>">
      <div class="form-group">
        <label class="form-label">Key Name *</label>
        <input type="text" name="name" class="form-control" required placeholder="e.g. Production API Key">
        <div style="font-size:.8rem;color:var(--text-muted);margin-top:.3rem;">A descriptive name to identify this key.</div>
      </div>
      <div class="form-group">
        <label class="form-label">Restrict to Product (optional)</label>
        <select name="product_id" class="form-control">
          <option value="">All Products</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= htmlspecialchars($p['id']) ?>"><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="alert alert-warning" style="font-size:.8rem;">
        ⚠ The API key will only be shown once after creation. Store it securely.
      </div>
      <div style="display:flex;gap:.75rem;">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary w-100">Generate Key</button>
      </div>
    </form>
  </div>
</div>
