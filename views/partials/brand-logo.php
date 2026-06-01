<?php
/** @var int $logoSize Optional width/height in px (default 40) */
$logoSize = isset($logoSize) ? (int) $logoSize : 40;
?>
<img
  src="/assets/images/gi-logo.png"
  alt="Governance Intelligence"
  class="nav-logo-icon"
  width="<?= $logoSize ?>"
  height="<?= $logoSize ?>"
  decoding="async"
/>
