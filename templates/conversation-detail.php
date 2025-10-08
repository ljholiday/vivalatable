<?php require_once __DIR__ . '/_helpers.php'; ?>
<section class="vt-section vt-conversation-detail">
  <?php if (empty($conversation)): ?>
    <h1 class="vt-heading">Conversation not found</h1>
    <p class="vt-text-muted">We couldn’t find that conversation.</p>
  <?php else: $c = (object)$conversation; ?>
    <h1 class="vt-heading"><?= e($c->title ?? '') ?></h1>
    <div class="vt-sub">
      <?php
      $bits = [];
      if (!empty($c->author_name)) {
          $bits[] = 'Started by ' . $c->author_name;
      }
      if (!empty($c->created_at)) {
          $bits[] = date_fmt($c->created_at);
      }
      echo e(implode(' · ', $bits));
      ?>
    </div>
    <?php if (!empty($c->content)): ?>
      <div class="vt-body vt-mt-4">
        <?= nl2br(e($c->content)) ?>
      </div>
    <?php endif; ?>
    <div class="vt-card-meta vt-mt-4">
      <span><?= e((string)($c->reply_count ?? 0)) ?> replies</span>
      <?php if (!empty($c->last_reply_date)): ?>
        <span>Last reply <?= e(date_fmt($c->last_reply_date)) ?></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
