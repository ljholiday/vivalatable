<?php
/**
 * VivalaTable Event Form Handler
 * Handles validation and processing of event creation forms
 * Ported from PartyMinder WordPress plugin
 */

class VT_Event_Form_Handler {

	/**
	 * Validate event form data
	 *
	 * @param array $post_data The $_POST data
	 * @return array Array of validation errors (empty if valid)
	 */
	public static function validateEventForm($post_data) {
		$form_errors = array();

		// Validate required fields
		if (empty($post_data['event_title'])) {
			$form_errors[] = 'Event title is required.';
		}
		if (empty($post_data['start_date'])) {
			$form_errors[] = 'Start date is required.';
		}
		if (empty($post_data['host_email'])) {
			$form_errors[] = 'Host email is required.';
		}

		// Validate start time if not all-day event
		if (empty($post_data['all_day']) && empty($post_data['start_time'])) {
			$form_errors[] = 'Start time is required for timed events.';
		}

		// Validate end date/time consistency
		if (!empty($post_data['end_date'])) {
			$start_datetime = $post_data['start_date'];
			if (!empty($post_data['start_time']) && empty($post_data['all_day'])) {
				$start_datetime .= ' ' . $post_data['start_time'];
			}

			$end_datetime = $post_data['end_date'];
			if (!empty($post_data['end_time']) && empty($post_data['all_day'])) {
				$end_datetime .= ' ' . $post_data['end_time'];
			}

			if (strtotime($end_datetime) <= strtotime($start_datetime)) {
				$form_errors[] = 'End date/time must be after start date/time.';
			}
		}

		return $form_errors;
	}

	/**
	 * Process event form data into structured event data
	 *
	 * @param array $post_data The $_POST data
	 * @return array Processed event data array
	 */
	public static function processEventFormData($post_data) {
		// Build event datetime from separate fields
		$event_datetime = $post_data['start_date'];
		if (!empty($post_data['start_time']) && empty($post_data['all_day'])) {
			$event_datetime .= ' ' . $post_data['start_time'];
		} else {
			$event_datetime .= ' 00:00:00';
		}

		// Build end datetime if provided
		$end_datetime = null;
		if (!empty($post_data['end_date'])) {
			$end_datetime = $post_data['end_date'];
			if (!empty($post_data['end_time']) && empty($post_data['all_day'])) {
				$end_datetime .= ' ' . $post_data['end_time'];
			} else {
				$end_datetime .= ' 23:59:59';
			}
		}

		$event_data = array(
			'title' => vt_service('validation.sanitizer')->textField($post_data['event_title']),
			'description' => vt_service('validation.sanitizer')->richText($post_data['event_description'] ?? ''),
			'event_date' => vt_service('validation.sanitizer')->textField($event_datetime),
			'venue_info' => vt_service('validation.sanitizer')->textField($post_data['venue_info'] ?? ''),
			'guest_limit' => intval($post_data['guest_limit'] ?? 10),
			'host_email' => vt_service('validation.sanitizer')->email($post_data['host_email']),
			'host_notes' => vt_service('validation.sanitizer')->richText($post_data['host_notes'] ?? ''),
			'author_id' => vt_service('auth.service')->getCurrentUserId(),
			'all_day' => !empty($post_data['all_day']) ? 1 : 0,
			'end_date' => $end_datetime ? vt_service('validation.sanitizer')->textField($end_datetime) : null,
			'recurrence_type' => vt_service('validation.sanitizer')->textField($post_data['recurrence_type'] ?? 'none'),
			'privacy' => vt_service('validation.sanitizer')->textField($post_data['privacy'] ?? 'public'),
		);

		// Add recurrence data if specified
		if (!empty($post_data['recurrence_type']) && $post_data['recurrence_type'] !== 'none') {
			$event_data['recurrence_interval'] = intval($post_data['recurrence_interval'] ?? 1);

			if ($post_data['recurrence_type'] === 'weekly' && !empty($post_data['weekly_days'])) {
				$event_data['recurrence_days'] = implode(',', array_map([vt_service('validation.sanitizer'), 'textField'], $post_data['weekly_days']));
			}

			if ($post_data['recurrence_type'] === 'monthly') {
				$event_data['monthly_type'] = vt_service('validation.sanitizer')->textField($post_data['monthly_type'] ?? 'date');
				if ($post_data['monthly_type'] === 'weekday') {
					$event_data['monthly_week'] = vt_service('validation.sanitizer')->textField($post_data['monthly_week'] ?? '');
					$event_data['monthly_day'] = vt_service('validation.sanitizer')->textField($post_data['monthly_day'] ?? '');
				}
			}

			if ($post_data['recurrence_type'] === 'custom' && !empty($post_data['custom_days'])) {
				$event_data['recurrence_days'] = implode(',', array_map([vt_service('validation.sanitizer'), 'textField'], $post_data['custom_days']));
				$event_data['recurrence_interval'] = intval($post_data['custom_interval'] ?? 1);
			}
		}

		return $event_data;
	}
}