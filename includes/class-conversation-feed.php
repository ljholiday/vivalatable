<?php
/**
 * VivalaTable Conversation Feed
 * Build filtered conversation feeds based on circles and permission gates
 * Ported from PartyMinder WordPress plugin
 */

class VT_Conversation_Feed {

	/**
	 * Get conversation feed list for a viewer
	 *
	 * @param int $viewer_id The user ID viewing the feed
	 * @param string $circle Circle filter: 'inner', 'trusted', 'extended', or 'all'
	 * @param array $opts Options: page, per_page, include_general, etc.
	 * @return array Feed data with conversations, pagination, and metadata
	 */
	public static function list($viewer_id, $circle = 'all', $opts = array()) {
		$viewer_id = intval($viewer_id);
		if (!$viewer_id) {
			return self::getEmptyFeed('Invalid viewer');
		}

		// Parse options
		$options = array_merge(array(
			'page' => 1,
			'per_page' => 20,
			'include_general' => true,
			'include_event_conversations' => true,
			'filter' => ''
		), $opts);

		$start_time = microtime(true);

		// Get circles data using VT_Circle_Scope
		$circles = self::getCirclesData($viewer_id);

		// Get creator IDs based on circle filter
		$creator_ids = self::getCreatorIdsForCircle($circles, $circle);

		if (empty($creator_ids)) {
			return self::getEmptyFeed('No creators in selected circle');
		}

		// Build and execute the feed query
		$feed_data = self::executeFeedQuery($viewer_id, $creator_ids, $options);

		// Add circle classification to each conversation
		$feed_data['conversations'] = self::addVisibilityMarkers(
			$feed_data['conversations'],
			$circles,
			$viewer_id
		);

		// Add performance metrics
		$calculation_time = microtime(true) - $start_time;
		$feed_data['meta']['performance'] = array(
			'calculation_time' => round($calculation_time * 1000, 2) . 'ms',
			'circle' => $circle,
			'creator_count' => count($creator_ids)
		);

		return $feed_data;
	}

	/**
	 * Get circles data for a viewer (correct implementation based on PartyMinder)
	 */
	private static function getCirclesData($viewer_id) {
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';
		$members_table = $db->prefix . 'community_members';

		// Inner: Communities the user belongs to + all members of those communities
		$inner_communities = $db->getCol(
			$db->prepare(
				"SELECT community_id FROM $members_table
				 WHERE user_id = %d AND status = 'active'",
				$viewer_id
			)
		);
		$inner_creators = array($viewer_id);

		if (!empty($inner_communities)) {
			$community_ids_in = implode(',', array_map('intval', $inner_communities));
			$inner_creators = $db->getCol(
				"SELECT DISTINCT user_id FROM $members_table
				 WHERE community_id IN ($community_ids_in) AND status = 'active'"
			);
		}

		// Trusted: Inner + communities that inner circle members belong to + their members
		$trusted_communities = $inner_communities;
		$trusted_creators = $inner_creators;

		if (!empty($inner_creators)) {
			$user_ids_in = implode(',', array_map('intval', $inner_creators));
			$additional_communities = $db->getCol(
				"SELECT DISTINCT community_id FROM $members_table
				 WHERE user_id IN ($user_ids_in) AND status = 'active'"
			);
			$trusted_communities = array_unique(array_merge($trusted_communities, $additional_communities));

			if (!empty($trusted_communities)) {
				$community_ids_in = implode(',', array_map('intval', $trusted_communities));
				$trusted_creators = $db->getCol(
					"SELECT DISTINCT user_id FROM $members_table
					 WHERE community_id IN ($community_ids_in) AND status = 'active'"
				);
			}
		}

		// Extended: Trusted + communities that trusted circle members belong to + their members
		$extended_communities = $trusted_communities;
		$extended_creators = $trusted_creators;

		if (!empty($trusted_creators)) {
			$user_ids_in = implode(',', array_map('intval', $trusted_creators));
			$additional_communities = $db->getCol(
				"SELECT DISTINCT community_id FROM $members_table
				 WHERE user_id IN ($user_ids_in) AND status = 'active'"
			);
			$extended_communities = array_unique(array_merge($extended_communities, $additional_communities));

			if (!empty($extended_communities)) {
				$community_ids_in = implode(',', array_map('intval', $extended_communities));
				$extended_creators = $db->getCol(
					"SELECT DISTINCT user_id FROM $members_table
					 WHERE community_id IN ($community_ids_in) AND status = 'active'"
				);
			}
		}

		return array(
			'inner' => array(
				'communities' => array_unique(array_map('intval', $inner_communities)),
				'creators' => array_unique(array_map('intval', $inner_creators))
			),
			'trusted' => array(
				'communities' => array_unique(array_map('intval', $trusted_communities)),
				'creators' => array_unique(array_map('intval', $trusted_creators))
			),
			'extended' => array(
				'communities' => array_unique(array_map('intval', $extended_communities)),
				'creators' => array_unique(array_map('intval', $extended_creators))
			)
		);
	}

	/**
	 * Get creator IDs for the specified circle
	 */
	private static function getCreatorIdsForCircle($circles, $circle) {
		switch ($circle) {
			case 'inner':
				return $circles['inner']['creators'];
			case 'trusted':
				return array_unique(array_merge(
					$circles['inner']['creators'],
					$circles['trusted']['creators']
				));
			case 'extended':
				return array_unique(array_merge(
					$circles['inner']['creators'],
					$circles['trusted']['creators'],
					$circles['extended']['creators']
				));
			case 'all':
			default:
				return array_unique(array_merge(
					$circles['inner']['creators'],
					$circles['trusted']['creators'],
					$circles['extended']['creators']
				));
		}
	}

	/**
	 * Execute the main feed query with permission gates
	 */
	private static function executeFeedQuery($viewer_id, $creator_ids, $options) {
		$db = VT_Database::getInstance();

		$conversations_table = $db->prefix . 'conversations';
		$communities_table = $db->prefix . 'communities';
		$members_table = $db->prefix . 'community_members';
		$events_table = $db->prefix . 'events';

		if (empty($creator_ids)) {
			return array(
				'conversations' => array(),
				'meta' => array(
					'page' => $options['page'],
					'per_page' => $options['per_page'],
					'total' => 0,
					'total_pages' => 0,
					'has_more' => false
				)
			);
		}

		// Build creator filter
		$creator_placeholders = implode(',', array_fill(0, count($creator_ids), '%d'));

		// Build content type filter based on options
		$content_type_filter = '';
		if ($options['filter'] === 'events') {
			$content_type_filter = 'AND conv.event_id IS NOT NULL AND conv.event_id > 0';
		} elseif ($options['filter'] === 'communities') {
			$content_type_filter = 'AND conv.community_id IS NOT NULL AND conv.community_id > 0';
		}

		// Simplified main query
		$query = "
			SELECT
				conv.*,
				com.name as community_name,
				com.slug as community_slug,
				com.visibility as community_visibility,
				com.creator_id as community_creator_id,
				com.personal_owner_user_id,
				ev.title as event_title,
				ev.slug as event_slug,
				conv.last_reply_date as latest_activity
			FROM $conversations_table conv
			LEFT JOIN $communities_table com ON conv.community_id = com.id
			LEFT JOIN $events_table ev ON conv.event_id = ev.id
			WHERE
				-- Filter by creator circles
				(
					(com.creator_id IN ($creator_placeholders))
					OR
					-- Include general conversations from creators in circles
					(conv.community_id IS NULL AND conv.author_id IN ($creator_placeholders))
				)
				AND
				-- Apply permission gates
				(
					-- Public communities
					(com.visibility = 'public')
					OR
					-- User is a member of the community
					EXISTS (
						SELECT 1 FROM $members_table mem
						WHERE mem.community_id = com.id
						AND mem.user_id = %d
						AND mem.status = 'active'
					)
					OR
					-- General conversations (no community)
					(conv.community_id IS NULL)
				)
				$content_type_filter
			ORDER BY COALESCE(conv.last_reply_date, conv.created_at) DESC
			LIMIT %d OFFSET %d
		";

		// Calculate offset
		$offset = ($options['page'] - 1) * $options['per_page'];

		// Prepare parameters
		$params = array_merge(
			$creator_ids, // For community creators
			$creator_ids, // For general conversation authors
			array($viewer_id), // For permission gates
			array($options['per_page'], $offset) // For pagination
		);

		// Execute query
		$conversations = $db->getResults($db->prepare($query, ...$params));

		// Get total count for pagination
		$count_query = "
			SELECT COUNT(DISTINCT conv.id)
			FROM $conversations_table conv
			LEFT JOIN $communities_table com ON conv.community_id = com.id
			WHERE
				(
					(com.creator_id IN ($creator_placeholders))
					OR
					(conv.community_id IS NULL AND conv.author_id IN ($creator_placeholders))
				)
				AND
				(
					(com.visibility = 'public')
					OR
					EXISTS (
						SELECT 1 FROM $members_table mem
						WHERE mem.community_id = com.id
						AND mem.user_id = %d
						AND mem.status = 'active'
					)
					OR
					(conv.community_id IS NULL)
				)
				$content_type_filter
		";

		$total_count = $db->getVar($db->prepare(
			$count_query,
			array_merge($creator_ids, $creator_ids, array($viewer_id))
		));

		return array(
			'conversations' => $conversations ?: array(),
			'meta' => array(
				'page' => $options['page'],
				'per_page' => $options['per_page'],
				'total' => intval($total_count),
				'total_pages' => ceil($total_count / $options['per_page']),
				'has_more' => ($options['page'] * $options['per_page']) < $total_count
			)
		);
	}

	/**
	 * Add "why visible" markers for each conversation
	 */
	private static function addVisibilityMarkers($conversations, $circles, $viewer_id) {
		foreach ($conversations as &$conversation) {
			$conversation->why_visible = self::determineVisibilityReason(
				$conversation,
				$circles,
				$viewer_id
			);
		}
		return $conversations;
	}

	/**
	 * Determine why a conversation is visible to the viewer
	 */
	private static function determineVisibilityReason($conversation, $circles, $viewer_id) {
		// Check if it's the viewer's own content
		if ($conversation->author_id == $viewer_id) {
			return array(
				'reason' => 'own_content',
				'circle' => null,
				'description' => 'Your own conversation'
			);
		}

		// Check community visibility
		if ($conversation->community_id) {
			// Determine which circle the community creator belongs to
			$community_creator = intval($conversation->community_creator_id);
			$circle = null;

			if (in_array($community_creator, $circles['inner']['creators'])) {
				$circle = 'inner';
			} elseif (in_array($community_creator, $circles['trusted']['creators'])) {
				$circle = 'trusted';
			} elseif (in_array($community_creator, $circles['extended']['creators'])) {
				$circle = 'extended';
			}

			if ($conversation->community_visibility === 'public') {
				return array(
					'reason' => 'public_community',
					'circle' => $circle,
					'description' => $circle ? "Public community from {$circle} circle" : 'Public community'
				);
			} else {
				return array(
					'reason' => 'member_access',
					'circle' => $circle,
					'description' => $circle ? "Member of {$circle} circle community" : 'Community member'
				);
			}
		} else {
			// General conversation - determine author's circle
			$author_id = intval($conversation->author_id);
			$circle = null;

			if (in_array($author_id, $circles['inner']['creators'])) {
				$circle = 'inner';
			} elseif (in_array($author_id, $circles['trusted']['creators'])) {
				$circle = 'trusted';
			} elseif (in_array($author_id, $circles['extended']['creators'])) {
				$circle = 'extended';
			}

			return array(
				'reason' => 'general_conversation',
				'circle' => $circle,
				'description' => $circle ? "General conversation from {$circle} circle" : 'General conversation'
			);
		}
	}

	/**
	 * Get empty feed structure
	 */
	private static function getEmptyFeed($reason = '') {
		return array(
			'conversations' => array(),
			'meta' => array(
				'page' => 1,
				'per_page' => 20,
				'total' => 0,
				'total_pages' => 0,
				'has_more' => false,
				'empty_reason' => $reason
			)
		);
	}
}