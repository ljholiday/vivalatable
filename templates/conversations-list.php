<?php require_once __DIR__ . '/_helpers.php'; ?>
<?php // templates/conversations-list.php ?>
<section class="vt-section vt-conversations">
  <h1 class="vt-heading">Conversations</h1>

  <?php
  $circle = $circle ?? 'all';
  $pagination = $pagination ?? ['page' => 1, 'per_page' => 20, 'has_more' => false, 'next_page' => null];
  $circleLinks = [
      ['key' => 'all', 'label' => 'All'],
      ['key' => 'inner', 'label' => 'Inner'],
      ['key' => 'trusted', 'label' => 'Trusted'],
      ['key' => 'extended', 'label' => 'Extended'],
  ];
  ?>
  <nav class="vt-subnav vt-mb-4">
    <?php foreach ($circleLinks as $link): ?>
      <a class="vt-subnav-link<?= $link['key'] === $circle ? ' is-active' : '' ?>" href="/conversations?circle=<?= urlencode($link['key']) ?>">
        <?= e($link['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if (!empty($conversations)): ?>
    <div class="vt-stack">
      <?php foreach ($conversations as $row):
        $item = (object)$row;
        ?>
        <article class="vt-card">
          <h3 class="vt-card-title">
            <a class="vt-link" href="/conversations/<?= e($item->slug ?? (string)($item->id ?? '')) ?>">
              <?= e($item->title ?? '') ?>
            </a>
          </h3>
          <?php if (!empty($item->author_name)): ?>
            <div class="vt-card-sub">Started by <?= e($item->author_name) ?><?php if (!empty($item->created_at)): ?> · <?= e(date_fmt($item->created_at)) ?><?php endif; ?></div>
          <?php endif; ?>
          <?php if (!empty($item->content)): ?>
            <p class="vt-card-desc"><?= e(substr(strip_tags((string)$item->content), 0, 160)) ?><?= strlen(strip_tags((string)$item->content)) > 160 ? '…' : '' ?></p>
          <?php endif; ?>
          <div class="vt-card-meta">
            <span><?= e((string)($item->reply_count ?? 0)) ?> replies</span>
            <?php if (!empty($item->last_reply_date)): ?>
              <span>Updated <?= e(date_fmt($item->last_reply_date)) ?></span>
            <?php endif; ?>
          </div>
          <?php if (!empty($item->community_name)): ?>
            <div class="vt-card-meta">In <?= e($item->community_name) ?></div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="vt-text-muted">No conversations found.</p>
  <?php endif; ?>

  <?php if (!empty($pagination['has_more'])): ?>
    <div class="vt-mt-4">
      <a class="vt-btn" href="/conversations?circle=<?= urlencode($circle) ?>&page=<?= (int)($pagination['next_page'] ?? (($pagination['page'] ?? 1) + 1)) ?>">Older Conversations</a>
    </div>
  <?php endif; ?>
</section>
