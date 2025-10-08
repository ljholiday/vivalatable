<?php require_once __DIR__ . '/_helpers.php'; ?>
<section class="vt-section vt-community-detail">
  <?php if (empty($community)): ?>
    <h1 class="vt-heading">Community not found</h1>
    <p class="vt-text-muted">We couldn’t find that community.</p>
  <?php else: $c = (object)$community; ?>
    <h1 class="vt-heading"><?= e($c->title ?? '') ?></h1>
    <div class="vt-sub">
      <?php
      $bits = [];
      if (!empty($c->privacy)) {
          $bits[] = ucfirst($c->privacy) . ' community';
      }
      if (!empty($c->created_at)) {
          $bits[] = 'Created ' . date_fmt($c->created_at);
      }
      echo e(implode(' · ', $bits));
      ?>
    </div>
    <?php if (!empty($c->description)): ?>
      <p class="vt-body"><?= e($c->description) ?></p>
    <?php endif; ?>
    <ul class="vt-metadata">
      <?php if (isset($c->member_count)): ?>
        <li><strong><?= e((string)$c->member_count) ?></strong> members</li>
      <?php endif; ?>
      <?php if (isset($c->event_count)): ?>
        <li><strong><?= e((string)$c->event_count) ?></strong> events</li>
      <?php endif; ?>
    </ul>
  <?php endif; ?>
</section>
