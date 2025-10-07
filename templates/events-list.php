<?php // templates/events-list.php ?>
<section class="vt-section vt-events">
  <h1 class="vt-heading">Upcoming Events</h1>

  <?php
  $card_path = __DIR__ . '/partials/entity-card.php';
  if (!is_file($card_path)) {
      echo '<p class="vt-text-muted">Card partial not found at templates/partials/entity-card.php</p>';
      return;
  }
  ?>

  <?php if (!empty($events)) : ?>
    <div class="vt-grid vt-events-grid">
      <?php foreach ($events as $row):
        // Normalize DB row -> object the partial can use
        $entity = (object)[
          'id'          => $row['id']          ?? null,
          'title'       => $row['title']       ?? '',
          'description' => $row['description'] ?? '',
          'event_date'  => $row['event_date']  ?? '',
          'slug'        => $row['slug']        ?? (string)($row['id'] ?? ''),
          'host_name'   => $row['host_name']   ?? '',
          'created_at'  => $row['created_at']  ?? ($row['event_date'] ?? null),
        ];

        // Required by the partial
        $entity_type     = 'event';

        // Optional helpers some versions expect
        $url             = '/events/' . ($entity->slug ?: $entity->id);
        $date_formatted  = $entity->event_date ? date('M j, Y', strtotime($entity->event_date)) : '';
        $badges          = $badges  ?? [];   // keep defined
        $stats           = $stats   ?? [];
        $actions         = $actions ?? [];
        $description     = $description ?? null;
        $truncate_length = $truncate_length ?? 15;

        include $card_path;
      endforeach; ?>
    </div>
  <?php else: ?>
    <p class="vt-text-muted">No events found.</p>
  <?php endif; ?>
</section>

