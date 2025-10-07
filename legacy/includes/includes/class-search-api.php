<?php
/**
 * VivalaTable Search API
 * Handles search functionality for events, communities, conversations, and members
 * Ported from PartyMinder WordPress plugin
 */

class VT_Search_API {

	/**
	 * Search all content types
	 */
	public static function searchContent($params = array()) {
		$query = isset($_GET['q']) ? trim($_GET['q']) : '';
		$types = isset($_GET['types']) ? $_GET['types'] : array();
		$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
		if (empty($query)) {
			return array('items' => array());
		}

		// Parse entity types
		$allowed_types = array('event', 'community', 'conversation', 'member');
		$entity_types = array();

		if ($types) {
			if (is_string($types)) {
				$types = array_map('trim', explode(',', $types));
			}
			$entity_types = array_filter($types, function($type) use ($allowed_types) {
				return in_array($type, $allowed_types);
			});
		}

		if (empty($entity_types)) {
			$entity_types = $allowed_types;
		}

		// Perform search
		$results = self::performSearch($query, $entity_types, $limit);

		// Format results
		$formatted_results = array();
		foreach ($results as $result) {
			$formatted_results[] = array(
				'entity_type' => $result->entity_type,
				'entity_id' => (int) $result->entity_id,
				'title' => $result->title,
				'snippet' => self::createSnippet($result->content, $query),
				'url' => $result->url,
				'score' => (float) $result->match_score,
			);
		}

		return array('items' => $formatted_results);
	}

	/**
	 * Perform the actual search query
	 */
	private static function performSearch($query, $entity_types = array(), $limit = 20) {
		$db = VT_Database::getInstance();
		$search_table = $db->prefix . 'search';
		$current_user_id = vt_service('auth.service')->getCurrentUserId();

		// Create search terms
		$search_terms = self::prepareSearchTerms($query);

		// Base query - using LIKE for broader compatibility
		$sql = "SELECT entity_type, entity_id, title, content, url,
		               (CASE
		                 WHEN title LIKE %s THEN 100
		                 WHEN content LIKE %s THEN 50
		                 ELSE 10
		               END) as match_score,
		               last_activity_at
		        FROM $search_table
		        WHERE (title LIKE %s OR content LIKE %s)";

		$search_pattern = '%' . $db->escLike($query) . '%';
		$params = array($search_pattern, $search_pattern, $search_pattern, $search_pattern);

		// Add entity type filter
		if (!empty($entity_types)) {
			$placeholders = implode(',', array_fill(0, count($entity_types), '%s'));
			$sql .= " AND entity_type IN ($placeholders)";
			$params = array_merge($params, $entity_types);
		}

		// Add basic visibility filter
		if (!$current_user_id) {
			$sql .= " AND visibility_scope = 'public'";
		} else {
			$sql .= " AND (visibility_scope IN ('public', 'site') OR owner_user_id = %d)";
			$params[] = $current_user_id;
		}

		$sql .= " ORDER BY match_score DESC, last_activity_at DESC LIMIT %d";
		$params[] = $limit;

		$results = $db->getResults($db->prepare($sql, $params));

		return $results ?: array();
	}

	/**
	 * Prepare search terms for better matching
	 */
	private static function prepareSearchTerms($query) {
		// Split into words and remove very short ones
		$words = array_filter(
			explode(' ', strtolower(trim($query))),
			function($word) {
				return strlen($word) >= 2;
			}
		);

		return $words;
	}

	/**
	 * Create snippet from content with highlighted query terms
	 */
	private static function createSnippet($content, $query, $length = 150) {
		$content = strip_tags($content);
		$query_lower = strtolower($query);
		$content_lower = strtolower($content);

		// Find the position of the query in the content
		$pos = strpos($content_lower, $query_lower);

		if ($pos !== false) {
			// Extract around the match
			$start = max(0, $pos - 50);
			$snippet = substr($content, $start, $length);

			// Add ellipsis if truncated
			if ($start > 0) {
				$snippet = '...' . $snippet;
			}
			if (strlen($content) > $start + $length) {
				$snippet .= '...';
			}
		} else {
			// Just take the first part
			$snippet = substr($content, 0, $length);
			if (strlen($content) > $length) {
				$snippet .= '...';
			}
		}

		return trim($snippet);
	}

	/**
	 * Search events specifically
	 */
	public static function searchEvents($query, $limit = 20) {
		$db = VT_Database::getInstance();
		$events_table = $db->prefix . 'events';
		$search_pattern = '%' . $db->escLike($query) . '%';

		$sql = "SELECT id, title, description, event_date, venue_info, privacy
		        FROM $events_table
		        WHERE event_status = 'active'
		        AND (title LIKE %s OR description LIKE %s OR venue_info LIKE %s)";

		$params = array($search_pattern, $search_pattern, $search_pattern);

		// Add privacy filter for non-logged users
		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		if (!$current_user_id) {
			$sql .= " AND privacy = 'public'";
		}

		$sql .= " ORDER BY
		          (CASE WHEN title LIKE %s THEN 100 ELSE 50 END) DESC,
		          event_date ASC
		          LIMIT %d";

		$params[] = $search_pattern;
		$params[] = $limit;

		return $db->getResults($db->prepare($sql, $params));
	}

	/**
	 * Search communities specifically
	 */
	public static function searchCommunities($query, $limit = 20) {
		$db = VT_Database::getInstance();
		$communities_table = $db->prefix . 'communities';
		$search_pattern = '%' . $db->escLike($query) . '%';

		$sql = "SELECT id, name, description, privacy, member_count
		        FROM $communities_table
		        WHERE is_active = 1
		        AND (name LIKE %s OR description LIKE %s)";

		$params = array($search_pattern, $search_pattern);

		// Add privacy filter for non-logged users
		$current_user_id = vt_service('auth.service')->getCurrentUserId();
		if (!$current_user_id) {
			$sql .= " AND privacy = 'public'";
		}

		$sql .= " ORDER BY
		          (CASE WHEN name LIKE %s THEN 100 ELSE 50 END) DESC,
		          member_count DESC
		          LIMIT %d";

		$params[] = $search_pattern;
		$params[] = $limit;

		return $db->getResults($db->prepare($sql, $params));
	}

	/**
	 * Search conversations specifically
	 */
	public static function searchConversations($query, $limit = 20) {
		$db = VT_Database::getInstance();
		$conversations_table = $db->prefix . 'conversations';
		$search_pattern = '%' . $db->escLike($query) . '%';

		$sql = "SELECT id, title, content, event_id, community_id, reply_count
		        FROM $conversations_table
		        WHERE is_active = 1
		        AND (title LIKE %s OR content LIKE %s)
		        ORDER BY
		          (CASE WHEN title LIKE %s THEN 100 ELSE 50 END) DESC,
		          reply_count DESC
		        LIMIT %d";

		$params = array($search_pattern, $search_pattern, $search_pattern, $limit);

		return $db->getResults($db->prepare($sql, $params));
	}

	/**
	 * Search members/profiles specifically
	 */
	public static function searchMembers($query, $limit = 20) {
		$db = VT_Database::getInstance();
		$profiles_table = $db->prefix . 'user_profiles';
		$search_pattern = '%' . $db->escLike($query) . '%';

		$sql = "SELECT user_id, display_name, bio, location, profile_image
		        FROM $profiles_table
		        WHERE is_active = 1
		        AND JSON_EXTRACT(privacy_settings, '$.profile_visibility') = 'public'
		        AND (display_name LIKE %s OR bio LIKE %s OR location LIKE %s)
		        ORDER BY
		          (CASE WHEN display_name LIKE %s THEN 100 ELSE 50 END) DESC,
		          events_hosted DESC
		        LIMIT %d";

		$params = array($search_pattern, $search_pattern, $search_pattern, $search_pattern, $limit);

		return $db->getResults($db->prepare($sql, $params));
	}

	/**
	 * Get search suggestions for autocomplete
	 */
	public static function getSuggestions($query, $limit = 10) {
		if (strlen($query) < 2) {
			return array();
		}

		$suggestions = array();

		// Get event suggestions
		$events = self::searchEvents($query, 3);
		foreach ($events as $event) {
			$suggestions[] = array(
				'type' => 'event',
				'title' => $event->title,
				'url' => '/events/' . $event->id,
			);
		}

		// Get community suggestions
		$communities = self::searchCommunities($query, 3);
		foreach ($communities as $community) {
			$suggestions[] = array(
				'type' => 'community',
				'title' => $community->name,
				'url' => '/communities/' . $community->id,
			);
		}

		// Get member suggestions
		$members = self::searchMembers($query, 3);
		foreach ($members as $member) {
			$suggestions[] = array(
				'type' => 'member',
				'title' => $member->display_name,
				'url' => '/profile/' . $member->user_id,
			);
		}

		return array_slice($suggestions, 0, $limit);
	}
}