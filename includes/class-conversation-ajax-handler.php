<?php
/**
 * VT Conversation AJAX Handler
 * Handles AJAX requests for conversations with Circles of Trust filtering
 */

class VT_Conversation_Ajax_Handler {

	/**
	 * Get conversations filtered by circle
	 */
	public static function ajaxGetConversations() {
		// Verify nonce
		$nonce = $_POST['nonce'] ?? '';
		if (!vt_service('security.service')->verifyNonce($nonce, 'vt_nonce')) {
			VT_Ajax::sendError('Security verification failed');
			return;
		}

		// Get current user
		$auth = vt_service('auth.service');
		$user_id = $auth->getCurrentUserId();

		if (!$user_id) {
			VT_Ajax::sendError('User not authenticated');
			return;
		}

		if (!$user_id) {
			VT_Ajax::sendError('User not authenticated');
			return;
		}

		// Get and validate circle parameter
		$circle = $_POST['circle'] ?? 'inner';
		$allowed_circles = array('inner', 'trusted', 'extended', 'all');
		if (!in_array($circle, $allowed_circles, true)) {
			$circle = 'inner';
		}

		// Get filter parameter
		$filter = $_POST['filter'] ?? '';
		$allowed_filters = array('', 'events', 'communities');
		if (!in_array($filter, $allowed_filters, true)) {
			$filter = '';
		}

		// Get pagination
		$page = max(1, intval($_POST['page'] ?? 1));
		$per_page = 20;

		// Build options for feed
		$options = array(
			'page' => $page,
			'per_page' => $per_page,
			'filter' => $filter
		);

		// Get conversations using Circles of Trust feed
		$feed_result = VT_Conversation_Feed::list($user_id, $circle, $options);

		// Check for errors
		if (is_vt_error($feed_result)) {
			VT_Ajax::sendError($feed_result['message'] ?? 'Failed to load conversations');
			return;
		}

		$conversations = $feed_result['conversations'] ?? array();
		$conversation_manager = new VT_Conversation_Manager();

		// Render HTML
		ob_start();
		if (empty($conversations)) {
			?>
			<div class="vt-text-center vt-p-4">
				<h3 class="vt-heading vt-heading-sm vt-mb-4">No Conversations Found</h3>
				<p class="vt-text-muted">There are no conversations in this circle.</p>
			</div>
			<?php
		} else {
			foreach ($conversations as $conversation) {
				?>
				<div class="vt-section">
					<div class="vt-flex vt-flex-between vt-mb-4">
						<h3 class="vt-heading vt-heading-sm">
							<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-text-primary">
								<?php echo htmlspecialchars($conversation_manager->getDisplayTitle($conversation)); ?>
							</a>
						</h3>
					</div>

					<div class="vt-mb-4">
						<div class="vt-flex vt-gap vt-mb-4">
							<span class="vt-text-muted">
								<?php
								if ($conversation->event_id) {
									echo 'Event Discussion';
								} elseif ($conversation->community_id) {
									echo 'Community Discussion';
								} else {
									echo 'General Discussion';
								}
								?>
							</span>
							<span class="vt-badge vt-badge-<?php echo $conversation->privacy === 'private' ? 'secondary' : 'success'; ?>">
								<?php echo htmlspecialchars(ucfirst($conversation->privacy)); ?>
							</span>
						</div>
					</div>

					<?php if ($conversation->content) : ?>
					<div class="vt-mb-4">
						<p class="vt-text-muted"><?php echo htmlspecialchars(VT_Text::truncateWords($conversation->content, 15)); ?></p>
					</div>
					<?php endif; ?>

					<div class="vt-flex vt-flex-between">
						<div class="vt-stat">
							<div class="vt-stat-number vt-text-primary"><?php echo intval($conversation->reply_count); ?></div>
							<div class="vt-stat-label">Replies</div>
						</div>
						<a href="/conversations/<?php echo htmlspecialchars($conversation->slug); ?>" class="vt-btn">
							View Details
						</a>
					</div>
				</div>
				<?php
			}
		}
		$html = ob_get_clean();

		// Send response
		VT_Ajax::sendSuccess(array(
			'html' => $html,
			'meta' => array(
				'count' => $feed_result['meta']['total'] ?? 0,
				'page' => $page,
				'has_more' => $feed_result['meta']['has_more'] ?? false,
				'circle' => $circle,
				'filter' => $filter
			)
		));
	}
}