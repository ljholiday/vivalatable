<?php // templates/events-list.php ?>
<section class="vt-section vt-events">
  <h1 class="vt-heading">Upcoming Events</h1>

  <?php if (!empty($events)) : ?>
    <div class="vt-grid vt-events-grid">
      <?php foreach ($events as $e):
        // Map DB row -> the structure your card needs
        $event_data = [
          'title'       => $e['title']        ?? '',
          'description' => $e['description']  ?? '',
          'event_date'  => $e['event_date']   ?? '',
          'slug'        => $e['slug']         ?? (string)($e['id'] ?? ''),
          'host_name'   => $e['host_name']    ?? '',
        ];

        // Render the card partial
        include __DIR__ . '/partials/entity-card.php';
      endforeach; ?>
    </div>
  <?php else: ?>
    <p class="vt-text-muted">No events found.</p>
  <?php endif; ?>
</section>

