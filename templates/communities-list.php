<?php require_once __DIR__ . '/_helpers.php'; ?>
<?php // templates/communities-list.php ?>
<section class="vt-section vt-communities">
  <h1 class="vt-heading">Communities</h1>

  <?php
  $circle = $circle ?? 'all';
  $circleLinks = [
      ['key' => 'all', 'label' => 'All'],
      ['key' => 'inner', 'label' => 'Inner'],
      ['key' => 'trusted', 'label' => 'Trusted'],
      ['key' => 'extended', 'label' => 'Extended'],
  ];
  ?>
  <nav class="vt-subnav vt-mb-4">
    <?php foreach ($circleLinks as $link): ?>
      <a class="vt-subnav-link<?= $link['key'] === $circle ? ' is-active' : '' ?>" href="/communities?circle=<?= urlencode($link['key']) ?>">
        <?= e($link['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php
  $card_path = __DIR__ . '/partials/card.php';
  if (!is_file($card_path)) {
      echo '<p class="vt-text-muted">Card partial not found at templates/partials/card.php</p>';
      return;
  }
  ?>

  <?php if (!empty($communities)) : ?>
    <div class="vt-grid vt-communities-grid">
      <?php foreach ($communities as $row):
        $entity = (object)[
          'id'          => $row['id'] ?? null,
          'title'       => $row['title'] ?? '',
          'description' => $row['description'] ?? '',
          'slug'        => $row['slug'] ?? (string)($row['id'] ?? ''),
          'url'         => '/communities/' . ($row['slug'] ?? (string)($row['id'] ?? '')),
        ];
        include __DIR__ . '/partials/card.php';
      endforeach; ?>
    </div>
  <?php else: ?>
    <p class="vt-text-muted">No communities found.</p>
  <?php endif; ?>
</section>
