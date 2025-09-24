<?php
/**
 * VivalaTable Homepage
 */

$page_title = 'VivalaTable - Real-world social networking';
$page_description = 'Plan real events. Connect with real people. Share real life.';

ob_start();
?>

<div class="pm-homepage">
    <!-- Hero Section -->
    <section class="pm-hero">
        <div class="pm-hero-container">
            <div class="pm-hero-content">
                <h1 class="pm-hero-title">Plan Real Events.<br>Connect with Real People.</h1>
                <p class="pm-hero-subtitle">VivalaTable is social networking for the real world. Create events, build communities, and bring people together.</p>

                <div class="pm-hero-actions">
                    <?php if (!is_user_logged_in()): ?>
                        <a href="<?php echo vt_base_url('/register'); ?>" class="pm-btn pm-btn-primary pm-btn-lg">Get Started Free</a>
                        <a href="<?php echo vt_base_url('/login'); ?>" class="pm-btn pm-btn-lg">Sign In</a>
                    <?php else: ?>
                        <a href="<?php echo vt_base_url('/events/create'); ?>" class="pm-btn pm-btn-primary pm-btn-lg">Create Event</a>
                        <a href="<?php echo vt_base_url('/communities'); ?>" class="pm-btn pm-btn-lg">Browse Communities</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pm-hero-visual">
                <div class="pm-hero-feature-cards">
                    <div class="pm-feature-card">
                        <h3>Easy Event Planning</h3>
                        <p>Create events in minutes with AI-powered planning assistance</p>
                    </div>
                    <div class="pm-feature-card">
                        <h3>Guest-Friendly RSVPs</h3>
                        <p>No account required for guests to RSVP to your events</p>
                    </div>
                    <div class="pm-feature-card">
                        <h3>Federated Communities</h3>
                        <p>Connect across platforms with Bluesky integration</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if (is_user_logged_in()): ?>
        <!-- User Dashboard Section -->
        <section class="pm-dashboard-preview">
            <div class="pm-container">
                <h2 class="pm-section-title">Your Activity</h2>

                <div class="pm-dashboard-grid">
                    <!-- Upcoming Events -->
                    <div class="pm-card">
                        <div class="pm-card-header">
                            <h3 class="pm-heading pm-heading-md">Upcoming Events</h3>
                            <a href="<?php echo vt_base_url('/events'); ?>" class="pm-link">View All</a>
                        </div>
                        <div class="pm-card-body">
                            <?php
                            $user = new User();
                            $upcoming_events = $user->get_upcoming_events(get_current_user_id(), 3);
                            ?>

                            <?php if (empty($upcoming_events)): ?>
                                <div class="pm-empty-state">
                                    <p class="pm-text-muted">No upcoming events</p>
                                    <a href="<?php echo vt_base_url('/events/create'); ?>" class="pm-btn pm-btn-sm">Create Event</a>
                                </div>
                            <?php else: ?>
                                <div class="pm-event-list">
                                    <?php foreach ($upcoming_events as $event): ?>
                                        <div class="pm-event-item">
                                            <div class="pm-event-date">
                                                <strong><?php echo vt_format_date($event->event_date, 'M j'); ?></strong>
                                                <small><?php echo vt_format_date($event->event_date, 'g:i A'); ?></small>
                                            </div>
                                            <div class="pm-event-info">
                                                <h4><?php echo vt_escape_html($event->title); ?></h4>
                                                <p class="pm-text-muted">
                                                    <?php if ($event->relationship === 'host'): ?>
                                                        <span class="pm-badge pm-badge-primary">Host</span>
                                                    <?php else: ?>
                                                        <span class="pm-badge pm-badge-success">Attending</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Communities -->
                    <div class="pm-card">
                        <div class="pm-card-header">
                            <h3 class="pm-heading pm-heading-md">Your Communities</h3>
                            <a href="<?php echo vt_base_url('/communities'); ?>" class="pm-link">View All</a>
                        </div>
                        <div class="pm-card-body">
                            <?php
                            $communities = $user->get_communities(get_current_user_id());
                            ?>

                            <?php if (empty($communities)): ?>
                                <div class="pm-empty-state">
                                    <p class="pm-text-muted">No communities joined yet</p>
                                    <a href="<?php echo vt_base_url('/communities'); ?>" class="pm-btn pm-btn-sm">Browse Communities</a>
                                </div>
                            <?php else: ?>
                                <div class="pm-community-list">
                                    <?php foreach (array_slice($communities, 0, 3) as $community): ?>
                                        <div class="pm-community-item">
                                            <div class="pm-community-info">
                                                <h4><?php echo vt_escape_html($community->name); ?></h4>
                                                <p class="pm-text-muted">
                                                    <span class="pm-badge pm-badge-<?php echo $community->role === 'admin' ? 'primary' : 'success'; ?>">
                                                        <?php echo vt_escape_html(ucfirst($community->role)); ?>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="pm-card">
                        <div class="pm-card-header">
                            <h3 class="pm-heading pm-heading-md">Quick Actions</h3>
                        </div>
                        <div class="pm-card-body">
                            <div class="pm-quick-actions">
                                <a href="<?php echo vt_base_url('/events/create'); ?>" class="pm-quick-action">
                                    <span>Create Event</span>
                                </a>
                                <a href="<?php echo vt_base_url('/communities/create'); ?>" class="pm-quick-action">
                                    <span>Create Community</span>
                                </a>
                                <a href="<?php echo vt_base_url('/conversations'); ?>" class="pm-quick-action">
                                    <span>Start Discussion</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    <?php else: ?>
        <!-- Features Section for Non-logged-in Users -->
        <section class="pm-features">
            <div class="pm-container">
                <h2 class="pm-section-title">Everything you need for real-world events</h2>

                <div class="pm-features-grid">
                    <div class="pm-feature">
                        <h3>Guest-Friendly RSVPs</h3>
                        <p>Your guests don't need accounts to RSVP. Send invitation links via email or Bluesky and track responses easily.</p>
                    </div>

                    <div class="pm-feature">
                        <h3>AI Event Planning</h3>
                        <p>Get personalized event suggestions, menu ideas, and activity recommendations powered by AI.</p>
                    </div>

                    <div class="pm-feature">
                        <h3>Federated Communities</h3>
                        <p>Connect with communities across platforms. Import your Bluesky followers and expand your social network.</p>
                    </div>

                    <div class="pm-feature">
                        <h3>Privacy Controls</h3>
                        <p>Full control over who sees your events and communities. Public, private, or community-only visibility options.</p>
                    </div>

                    <div class="pm-feature">
                        <h3>Rich Conversations</h3>
                        <p>Threaded discussions with nested replies. Follow conversations and get notifications for topics you care about.</p>
                    </div>

                    <div class="pm-feature">
                        <h3>Mobile Optimized</h3>
                        <p>Beautiful, responsive design that works perfectly on all devices. Plan events on the go.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="pm-cta">
            <div class="pm-container">
                <div class="pm-cta-content">
                    <h2>Ready to plan your first event?</h2>
                    <p>Join thousands of hosts creating memorable experiences for their communities.</p>
                    <div class="pm-cta-actions">
                        <a href="<?php echo vt_base_url('/register'); ?>" class="pm-btn pm-btn-primary pm-btn-lg">Get Started Free</a>
                        <a href="<?php echo vt_base_url('/events'); ?>" class="pm-btn pm-btn-lg">Browse Events</a>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

// Load page template
vt_load_template('base/page', [
    'page_title' => $page_title,
    'page_description' => $page_description,
    'content' => $content
]);
?>