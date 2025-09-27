<?php
/**
 * VivalaTable Dashboard Content Template
 * Your VivalaTable home with conversations and navigation
 * Ported from PartyMinder WordPress plugin
 */

// Prevent direct access
if (!defined('VT_VERSION')) {
    exit;
}

// Load required classes
require_once VT_INCLUDES_DIR . '/class-event-manager.php';
require_once VT_INCLUDES_DIR . '/class-guest-manager.php';
require_once VT_INCLUDES_DIR . '/class-profile-manager.php';
require_once VT_INCLUDES_DIR . '/class-conversation-manager.php';

$event_manager = new VT_Event_Manager();
$guest_manager = new VT_Guest_Manager();
$conversation_manager = new VT_Conversation_Manager();

// Get current user info
$current_user = VT_Auth::getCurrentUser();
$user_logged_in = VT_Auth::isLoggedIn();

// Get user profile data if logged in
$profile_data = null;
if ($user_logged_in) {
    $profile_data = VT_Profile_Manager::getUserProfile($current_user->id);
}

// Get user's recent activity
$recent_events = array();
if ($user_logged_in) {
    $db = VT_Database::getInstance();
    $events_table = $db->prefix . 'events';

    // Get user's 3 most recent events (created or RSVP'd)
    $recent_events = $db->getResults(
        $db->prepare(
            "SELECT DISTINCT e.*, 'created' as relationship_type FROM $events_table e
         WHERE e.author_id = %d AND e.event_status = 'active'
         UNION
         SELECT DISTINCT e.*, 'rsvpd' as relationship_type FROM $events_table e
         INNER JOIN {$db->prefix}guests g ON e.id = g.event_id
         WHERE g.email = %s AND e.event_status = 'active'
         ORDER BY event_date DESC
         LIMIT 3",
            $current_user->id,
            $current_user->email
        )
    );
}

// Get recent conversations from user's close circle for dashboard
$recent_conversations = array();
if ($user_logged_in) {
    $recent_conversations = $conversation_manager->getRecentConversations(5);
}

// Set up template variables
$page_title = 'Dashboard';
$page_description = 'Your social event hub';

?>

<!-- Secondary Menu Bar -->
<div class="vt-section vt-mb-4">
    <div class="vt-flex vt-gap-4 vt-flex-wrap">
        <?php if ($user_logged_in): ?>
            <a href="/conversations/create" class="vt-btn">
                Start Conversation
            </a>
            <a href="/events/create" class="vt-btn">
                Create Event
            </a>
        <?php endif; ?>
        <a href="/events" class="vt-btn vt-btn-secondary">
            Browse Events
        </a>
        <a href="/communities" class="vt-btn vt-btn-secondary">
            Communities
        </a>
    </div>
</div>

<!-- Main content starts here -->
<?php if ($user_logged_in): ?>
    <!-- Welcome Section -->
    <div class="vt-section vt-mb">
        <div class="vt-card">
            <div class="vt-card-header">
                <h2 class="vt-heading">Welcome back, <?php echo VT_Sanitize::escHtml($profile_data['display_name'] ?: $current_user->display_name); ?>!</h2>
            </div>
            <div class="vt-card-body">
                <p class="vt-text-muted">Here's what's happening in your social circle</p>
            </div>
        </div>
    </div>

    <!-- Recent Events Section -->
    <div class="vt-section vt-mb">
        <div class="vt-card">
            <div class="vt-card-header">
                <h3 class="vt-heading">Your Recent Events</h3>
            </div>
            <div class="vt-card-body">
                <?php if (!empty($recent_events)): ?>
                    <div class="vt-grid vt-gap-4">
                        <?php foreach ($recent_events as $event): ?>
                            <div class="vt-card">
                                <div class="vt-card-body">
                                    <h4 class="vt-heading">
                                        <a href="/events/<?php echo $event->id; ?>" class="vt-link">
                                            <?php echo VT_Sanitize::escHtml($event->title); ?>
                                        </a>
                                    </h4>
                                    <p class="vt-text-muted">
                                        <?php echo date('M j, Y g:i A', strtotime($event->event_date)); ?>
                                    </p>
                                    <div class="vt-badge vt-badge-secondary">
                                        <?php echo ucfirst($event->relationship_type); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="vt-text-center vt-p-4">
                        <h4 class="vt-heading">No Recent Events</h4>
                        <p class="vt-text-muted">Create an event or RSVP to events to see them here.</p>
                    </div>
                <?php endif; ?>
                <div class="vt-text-center vt-mt-4">
                    <a href="/events" class="vt-btn">
                        Browse All Events
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Conversations Section -->
    <div class="vt-section vt-mb">
        <div class="vt-card">
            <div class="vt-card-header">
                <h3 class="vt-heading">Recent Conversations</h3>
            </div>
            <div class="vt-card-body">
                <?php if (!empty($recent_conversations)): ?>
                    <div class="vt-grid vt-gap-4">
                        <?php foreach ($recent_conversations as $conversation): ?>
                            <div class="vt-card">
                                <div class="vt-card-body">
                                    <h4 class="vt-heading">
                                        <a href="/conversations/<?php echo $conversation->id; ?>" class="vt-link">
                                            <?php echo VT_Sanitize::escHtml($conversation->title); ?>
                                        </a>
                                    </h4>
                                    <p class="vt-text-muted">
                                        <?php echo VT_Sanitize::escHtml(substr($conversation->content, 0, 100)) . '...'; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="vt-text-center vt-p-4">
                        <h4 class="vt-heading">No Recent Conversations</h4>
                        <p class="vt-text-muted">Start a conversation to connect with the community.</p>
                    </div>
                <?php endif; ?>
                <div class="vt-text-center vt-mt-4">
                    <a href="/conversations" class="vt-btn">
                        View All Conversations
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Login Section for Non-Logged-In Users -->
    <div class="vt-section vt-mb">
        <div class="vt-card">
            <div class="vt-card-header">
                <h2 class="vt-heading">Sign In to Get Started</h2>
                <p class="vt-text-muted">Log in to create events, join conversations, and connect with the community</p>
            </div>
            <div class="vt-card-body">
                <div class="vt-text-center vt-p-4">
                    <h3 class="vt-heading vt-mb">Welcome to VivalaTable!</h3>
                    <p class="vt-text-muted vt-mb">Your social event hub for connecting, planning, and celebrating together.</p>
                    <div class="vt-flex vt-gap-4 vt-justify-center">
                        <a href="/login" class="vt-btn vt-btn-lg">
                            Sign In
                        </a>
                        <a href="/register" class="vt-btn vt-btn-lg">
                            Create Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Section for Non-Logged-In Users -->
    <div class="vt-section vt-mb">
        <div class="vt-card">
            <div class="vt-card-header">
                <h2 class="vt-heading">What You Can Do</h2>
                <p class="vt-text-muted">Discover all the features waiting for you</p>
            </div>
            <div class="vt-card-body">
                <div class="vt-grid vt-gap-4">
                    <div class="vt-flex vt-gap-4 vt-p-4">
                        <div class="vt-flex-1">
                            <h4 class="vt-heading">Create & Host Events</h4>
                            <p class="vt-text-muted">Plan dinner parties, game nights, and social gatherings</p>
                        </div>
                    </div>
                    <div class="vt-flex vt-gap-4 vt-p-4">
                        <div class="vt-flex-1">
                            <h4 class="vt-heading">Join Communities</h4>
                            <p class="vt-text-muted">Connect with like-minded people in your area</p>
                        </div>
                    </div>
                    <div class="vt-flex vt-gap-4 vt-p-4">
                        <div class="vt-flex-1">
                            <h4 class="vt-heading">Start Conversations</h4>
                            <p class="vt-text-muted">Share ideas and connect through meaningful discussions</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>