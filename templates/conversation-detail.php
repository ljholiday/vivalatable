<section class="vt-section vt-conversation-detail">
  <?php if (empty($conversation)): ?>
    <h1 class="vt-heading">Conversation not found</h1>
    <p class="vt-text-muted">We couldn’t find that conversation.</p>
  <?php else: $c = (object)$conversation; ?>
    <h1 class="vt-heading"><?= e($c->title ?? '') ?></h1>
    <div class="vt-sub vt-flex vt-gap">
      <?php
      if (!empty($c->author_username) || !empty($c->author_display_name)) {
          echo '<span>Started by</span>';
          $user = (object)[
              'id' => $c->author_id ?? null,
              'username' => $c->author_username ?? null,
              'display_name' => $c->author_display_name ?? $c->author_name ?? 'Unknown',
              'email' => $c->author_email ?? null,
              'avatar_url' => $c->author_avatar_url ?? null
          ];
          $args = ['avatar_size' => 24, 'class' => 'vt-member-display-inline'];
          include __DIR__ . '/partials/member-display.php';
      }
      if (!empty($c->created_at)) {
          echo '<span> · ' . e(date_fmt($c->created_at)) . '</span>';
      }
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
          <?php
          $conversationService = function_exists('vt_service') ? vt_service('conversation.service') : null;
          foreach ($replies as $reply):
            $r = (object)$reply;
            $content = e($r->content ?? '');
            // Process embeds if service available
            if ($conversationService && method_exists($conversationService, 'processContentEmbeds')) {
              $content = $conversationService->processContentEmbeds($content);
            } else {
              $content = nl2br($content);
            }
          ?>
            <article class="vt-card">
              <div class="vt-card-sub vt-flex vt-gap">
                <?php
                $user = (object)[
                    'id' => $r->author_id ?? null,
                    'username' => $r->author_username ?? null,
                    'display_name' => $r->author_display_name ?? $r->author_name ?? 'Unknown',
                    'email' => $r->author_email ?? null,
                    'avatar_url' => $r->author_avatar_url ?? null
                ];
                $args = ['avatar_size' => 32, 'class' => 'vt-member-display-inline'];
                include __DIR__ . '/partials/member-display.php';
                ?>
                <?php if (!empty($r->created_at)): ?>
                  <span class="vt-text-muted"> · <?= e(date_fmt($r->created_at)) ?></span>
                <?php endif; ?>
              </div>
              <div class="vt-card-body">
                <?php if (!empty($r->image_url)): ?>
                  <div class="vt-reply-image vt-mb-3">
                    <img src="<?= e($r->image_url) ?>" alt="<?= e($r->image_alt ?? '') ?>" class="vt-img" loading="lazy">
                  </div>
                <?php endif; ?>
                <div class="vt-card-desc"><?= $content ?></div>
              </div>
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
      <form method="post" action="/conversations/<?= e($c->slug ?? '') ?>/reply" class="vt-form vt-stack" enctype="multipart/form-data">
        <?php if (function_exists('vt_service')): ?>
          <?php echo vt_service('security.service')->nonceField('vt_conversation_reply', 'reply_nonce'); ?>
        <?php endif; ?>
        <div class="vt-field">
          <label class="vt-label" for="reply-content">Reply</label>
          <textarea class="vt-textarea<?= isset($reply_errors['content']) ? ' is-invalid' : '' ?>" id="reply-content" name="content" rows="4" required><?= e($reply_input['content'] ?? '') ?></textarea>
        </div>
        <div class="vt-field">
          <label class="vt-label" for="reply-image">Image (optional)</label>
          <input type="file" class="vt-input<?= isset($reply_errors['image_alt']) ? ' is-invalid' : '' ?>" id="reply-image" name="reply_image" accept="image/jpeg,image/png,image/gif,image/webp">
          <small class="vt-help-text">Maximum 10MB. JPEG, PNG, GIF, or WebP format.</small>
        </div>
        <div class="vt-field">
          <label class="vt-label" for="image-alt">Image description</label>
          <input type="text" class="vt-input<?= isset($reply_errors['image_alt']) ? ' is-invalid' : '' ?>" id="image-alt" name="image_alt" placeholder="Describe the image for accessibility" value="<?= e($reply_input['image_alt'] ?? '') ?>">
          <small class="vt-help-text">Required if uploading an image. Describe what's in the image for screen reader users.</small>
          <?php if (isset($reply_errors['image_alt'])): ?>
            <div class="vt-field-error"><?= e($reply_errors['image_alt']) ?></div>
          <?php endif; ?>
        </div>
        <div class="vt-actions">
          <button type="submit" class="vt-btn vt-btn-primary">Post Reply</button>
        </div>
      </form>
    </section>
  <?php endif; ?>
</section>
