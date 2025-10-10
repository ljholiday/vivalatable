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

    <section class="vt-section vt-mt-6">
      <h2 class="vt-heading-sm">Replies</h2>
      <?php if (!empty($replies)): ?>
        <div class="vt-stack">
          <?php foreach ($replies as $reply): $r = (object)$reply; ?>
            <article class="vt-card">
              <div class="vt-card-sub">
                <?= e($r->author_name ?? 'Unknown') ?><?php if (!empty($r->created_at)): ?> · <?= e(date_fmt($r->created_at)) ?><?php endif; ?>
              </div>
              <p class="vt-card-desc"><?= nl2br(e($r->content ?? '')) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="vt-text-muted">No replies yet.</p>
      <?php endif; ?>
    </section>

    <section class="vt-section vt-mt-6">
      <h2 class="vt-heading-sm">Add Reply</h2>
      <?php if (!empty($reply_errors)): ?>
        <div class="vt-alert vt-alert-error vt-mb-4">
          <ul>
            <?php foreach ($reply_errors as $message): ?>
              <li><?= e($message) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <form method="post" action="/conversations/<?= e($c->slug ?? '') ?>/reply" class="vt-form vt-stack">
        <?php if (function_exists('vt_service')): ?>
          <?php echo vt_service('security.service')->nonceField('vt_conversation_reply', 'reply_nonce'); ?>
        <?php endif; ?>
        <div class="vt-field">
          <label class="vt-label" for="reply-content">Reply</label>
          <textarea class="vt-textarea<?= isset($reply_errors['content']) ? ' is-invalid' : '' ?>" id="reply-content" name="content" rows="4" required><?= e($reply_input['content'] ?? '') ?></textarea>
        </div>
        <div class="vt-actions">
          <button type="submit" class="vt-btn vt-btn-primary">Post Reply</button>
        </div>
      </form>
    </section>
  <?php endif; ?>
</section>
