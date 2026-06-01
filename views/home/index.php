<?php $pageTitle = 'GI Smartanalytics Portal'; ?>

<!-- Hero -->
<section class="hero">
  <div class="hero-badge">⚖ Enterprise Governance Platform</div>
  <h1>Intelligent Governance<br>for <em>Modern Organisations</em></h1>
  <p class="hero-sub">
    Comprehensive governance analytics, compliance monitoring, and smart decision intelligence — built for organisations that demand precision and accountability.
  </p>
  <div class="hero-cta">
    <a href="/auth/register" class="btn btn-primary btn-lg">Start Free Trial</a>
    <a href="#products" class="btn btn-secondary btn-lg">Explore Products</a>
  </div>
</section>

<div class="divider"></div>

<!-- Products -->
<section class="section" id="products">
  <div class="section-inner">
    <div class="section-header">
      <div class="section-label">Our Products</div>
      <h2 class="section-title">Intelligence Products</h2>
      <p class="section-sub">Purpose-built analytics modules for every aspect of organisational governance.</p>
    </div>

    <?php if (!empty($products)): ?>
    <div class="product-cards">
      <?php foreach ($products as $product): ?>
      <div class="product-card">
        <div class="product-icon">
          <?= htmlspecialchars($product['icon'] ?? '📊') ?>
        </div>
        <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
        <div class="product-desc"><?= htmlspecialchars($product['description'] ?? '') ?></div>
        <?php if (!empty($product['credit_cost'])): ?>
          <div class="product-credit">⚡ <?= number_format((float)$product['credit_cost'], 2) ?> tokens / request</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Demo product cards when DB not yet connected -->
    <div class="product-cards">
      <?php
      $demoProducts = [
        ['icon' => '🏛', 'name' => 'Governance Analytics',       'desc' => 'Board composition analysis, governance scoring, and compliance benchmarking against best practices.'],
        ['icon' => '📋', 'name' => 'Regulatory Intelligence',    'desc' => 'Real-time regulatory monitoring, impact assessments, and automated compliance gap analysis.'],
        ['icon' => '🔍', 'name' => 'Due Diligence Engine',       'desc' => 'Deep-dive corporate intelligence reports covering ownership, directorships, and risk factors.'],
        ['icon' => '📈', 'name' => 'ESG Scoring',               'desc' => 'Environmental, Social and Governance scoring with peer benchmarking and trend analysis.'],
        ['icon' => '⚠',  'name' => 'Risk Intelligence',         'desc' => 'Predictive risk modelling, early warning indicators, and automated risk register management.'],
        ['icon' => '🗂',  'name' => 'Document Intelligence',     'desc' => 'AI-powered document analysis, clause extraction, and regulatory mapping for legal documents.'],
      ];
      foreach ($demoProducts as $p): ?>
      <div class="product-card">
        <div class="product-icon"><?= $p['icon'] ?></div>
        <div class="product-name"><?= $p['name'] ?></div>
        <div class="product-desc"><?= $p['desc'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<div class="divider"></div>

<!-- Pricing Preview -->
<section class="section" id="pricing">
  <div class="section-inner">
    <div class="section-header">
      <div class="section-label">Pricing</div>
      <h2 class="section-title">Transparent Pricing</h2>
      <p class="section-sub">Token-based usage model — pay for what you use. No hidden fees.</p>
    </div>

    <?php if (!empty($plans)): ?>
    <div class="pricing-cards">
      <?php
      $planCount = count($plans);
      $middleIdx = (int) floor(($planCount - 1) / 2);
      foreach ($plans as $i => $plan):
        $featured = ($planCount > 1 && $i === $middleIdx) ? 'featured' : '';
        $features = is_string($plan['features'] ?? null) ? json_decode($plan['features'], true) : ($plan['features'] ?? []);
      ?>
      <div class="pricing-card <?= $featured ?>">
        <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
        <div class="plan-desc"><?= htmlspecialchars($plan['description'] ?? '') ?></div>
        <div class="plan-price">
          <div class="plan-amount">
            <sup>R</sup><?= number_format((float)($plan['price_monthly'] ?? 0), 0) ?>
          </div>
          <div class="plan-period">per month · <?= number_format((int)($plan['credits_monthly'] ?? 0)) ?> tokens included</div>
        </div>
        <?php if (!empty($features)): ?>
        <ul class="plan-features">
          <?php foreach (array_slice($features, 0, 5) as $f): ?>
            <li><?= htmlspecialchars(is_array($f) ? ($f['label'] ?? '') : $f) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <a href="/auth/register" class="btn btn-<?= $featured ? 'primary' : 'secondary' ?> w-100">Get Started</a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Demo pricing when DB not yet connected -->
    <div class="pricing-cards">
      <div class="pricing-card">
        <div class="plan-name">Starter</div>
        <div class="plan-desc">Perfect for small teams getting started.</div>
        <div class="plan-price">
          <div class="plan-amount"><sup>R</sup>1 500</div>
          <div class="plan-period">per month · 5 000 tokens included</div>
        </div>
        <ul class="plan-features">
          <li>Up to 5 users</li><li>3 products</li><li>API access</li><li>Email support</li>
        </ul>
        <a href="/auth/register" class="btn btn-secondary w-100">Get Started</a>
      </div>
      <div class="pricing-card featured">
        <div class="plan-name">Professional</div>
        <div class="plan-desc">For growing organisations needing more power.</div>
        <div class="plan-price">
          <div class="plan-amount"><sup>R</sup>4 500</div>
          <div class="plan-period">per month · 20 000 tokens included</div>
        </div>
        <ul class="plan-features">
          <li>Up to 25 users</li><li>All products</li><li>Full API access</li><li>Priority support</li><li>Advanced analytics</li>
        </ul>
        <a href="/auth/register" class="btn btn-primary w-100">Get Started</a>
      </div>
      <div class="pricing-card">
        <div class="plan-name">Enterprise</div>
        <div class="plan-desc">Custom solutions for large organisations.</div>
        <div class="plan-price">
          <div class="plan-amount"><sup>R</sup>12 000</div>
          <div class="plan-period">per month · 100 000 tokens included</div>
        </div>
        <ul class="plan-features">
          <li>Unlimited users</li><li>All products</li><li>Dedicated API</li><li>SLA support</li><li>Custom integrations</li>
        </ul>
        <a href="/auth/register" class="btn btn-secondary w-100">Get Started</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<div class="divider"></div>

<!-- Security -->
<section class="section">
  <div class="section-inner">
    <div class="section-header">
      <div class="section-label">Security & Compliance</div>
      <h2 class="section-title">Enterprise-Grade Security</h2>
      <p class="section-sub">Your data is protected by industry-leading security standards and practices.</p>
    </div>
    <div class="security-grid">
      <div class="security-item">
        <div class="security-icon">🔐</div>
        <div>
          <h4>OIDC Authentication</h4>
          <p>Powered by Keycloak — enterprise SSO, MFA, and role-based access control.</p>
        </div>
      </div>
      <div class="security-item">
        <div class="security-icon">🔒</div>
        <div>
          <h4>API Key Hashing</h4>
          <p>API keys stored as SHA-256 hashes — your credentials are never stored in plaintext.</p>
        </div>
      </div>
      <div class="security-item">
        <div class="security-icon">📝</div>
        <div>
          <h4>Full Audit Trail</h4>
          <p>Every action logged with timestamps, user identity, and change details.</p>
        </div>
      </div>
      <div class="security-item">
        <div class="security-icon">🛡</div>
        <div>
          <h4>CSRF Protection</h4>
          <p>All forms protected with CSRF tokens. Strict session management enforced.</p>
        </div>
      </div>
      <div class="security-item">
        <div class="security-icon">⚡</div>
        <div>
          <h4>Token Integrity</h4>
          <p>Transactional token operations with row-level locking prevent race conditions.</p>
        </div>
      </div>
      <div class="security-item">
        <div class="security-icon">🌍</div>
        <div>
          <h4>Data Sovereignty</h4>
          <p>All data stored in South Africa. POPIA compliant data processing.</p>
        </div>
      </div>
    </div>
  </div>
</section>
