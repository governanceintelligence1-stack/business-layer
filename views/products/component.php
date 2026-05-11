<?php $pageTitle = $componentTitle ?? 'Product Component'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= htmlspecialchars($componentTitle ?? 'Product Component') ?></h1>
    <p class="page-subtitle"><?= htmlspecialchars($componentDescription ?? 'Component workspace.') ?></p>
  </div>
  <a href="/products" class="btn btn-ghost">Back to Products</a>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Workspace</h3>
    <span class="badge badge-pending">Preview Mode</span>
  </div>

  <p class="text-muted">
    This page is ready for UI styling and workflow design. Connect backend processing when your product flow is finalized.
  </p>

  <div class="form-row form-row-2 mt-3">
    <div class="form-group">
      <label class="form-label">Source File</label>
      <input class="form-control" type="text" placeholder="Choose or drag file..." />
    </div>
    <div class="form-group">
      <label class="form-label">Case Reference</label>
      <input class="form-control" type="text" placeholder="e.g. GI-CASE-2026-001" />
    </div>
  </div>

  <div class="d-flex gap-2 mt-2">
    <button type="button" class="btn btn-primary"><?= htmlspecialchars($componentCta ?? 'Run') ?></button>
    <button type="button" class="btn btn-secondary">Save Draft</button>
  </div>
</div>
