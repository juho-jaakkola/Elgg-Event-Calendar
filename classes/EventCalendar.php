<?php
/**
 * Class for event_calendar objects
 *
 * @property string $status The published status of the event (published, draft)
 * @property int    $spots  The total amount of spots in the event
 */
class EventCalendar extends ElggObject {
	const SUBTYPE = 'event_calendar';

	/**
	 * Set subtype to event.
	 */
	protected function initializeAttributes() {
		parent::initializeAttributes();

		$this->attributes['subtype'] = $this::SUBTYPE;
	}

	/**
	 * Check if the event is in user's personal calendar.
	 *
	 * @param ElggUser $user
	 * @return boolean True is in calendar otherwise false
	 */
	public function isParticipating($user) {
		// Check new implementation
		if (check_entity_relationship($user->guid, 'personal_event', $this->guid)) {
			return true;
		} else {
			// Check also legacy implementation
			$result = elgg_get_annotations(array(
				'guid' => $this->guid,
				'annotation_name' => 'personal_event',
				'annotation_value' => $user->guid,
				'count' => true
			));

			if ($result) {
				return true;
			} else {
				return false;
			}
		}
	}

	/**
	 * Add event to user's personal calendar
	 *
	 * @param ElggUser $user
	 * @return boolean True on success, false if something prevented action
	 */
	public function addParticipant($user) {
		// Check that user hasn't already added the event
		// TODO Maybe it's not necessary to check this here?
		$has_event = $this->isParticipating($user);

		// Check that there is no collision with another event
		$has_collision = event_calendar_has_collision($this->guid, $user->guid);

		if (!$has_event && !$has_collision) {
			if (!$this->isFull()) {
				return add_entity_relationship($user->guid, 'personal_event', $this->guid);
			}
		}

		return false;
	}

	/**
	 * Remove event from user's personal calendar
	 *
	 * @param ElggUser $user
	 * @return boolean  True on success, false if something prevented action
	 */
	public function removeParticipant($user) {
		remove_entity_relationship($user->guid, 'personal_event', $this->guid);

		// Also use the old method for now
		// TODO Remove once old data has been converted to the new model
		$annotations = get_annotations(
			$this->guid,
			'object',
			'event_calendar',
			'personal_event',
			(int) $user->guid,
			$user->guid
		);

		if ($annotations) {
			foreach ($annotations as $annotation) {
				$annotation->delete();
			}
		}
	}

	/**
	 * Check if all the available spots have been reserver.
	 *
	 * @return boolean True if the event is full, otherwise false
	 */
	public function isFull() {
		$spots_display = elgg_get_plugin_setting('spots_display', 'event_calendar');

		if ($event_calendar_spots_display == 'yes') {
			$count = $this->getParticipants(array('count' => true));

			if ($count >= $this->spots) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Get users who take part in this event
	 *
	 * @param array $options Array of options
	 * @return int|ElggUser[] Count or array of users
	 */
	public function getParticipants($options = array()) {
		$defaults = array(
			'type' => 'user',
			'relationship' => 'personal_event',
			'relationship_guid' => $this->guid,
			'inverse_relationship' => TRUE,
			'count' => false,
			'offset' => 0,
			'limit' => 10,
		);

		$options = array_merge($defaults, $options);

		if ($options['count']) {
			$count_old_way = elgg_get_annotations(array(
				'guid' => $this->guid,
				'type' => "object",
				'subtype' => "event_calendar",
				'annotation_name' => "personal_event",
				'count' => TRUE)
			);
			$options ['count'] = TRUE;

			$count_new_way = elgg_get_entities_from_relationship($options);
			return $count_old_way + $count_new_way;
		} else {
			$users_old_way = array();

			$annotations = elgg_get_annotations(array(
				'guid' => $this->guid,
				'type' => 'object',
				'subtype' => 'event_calendar',
				'annotation_name' => 'personal_event',
				'limit' => false,
			));

			if ($annotations) {
				foreach($annotations as $annotation) {
					if (($user = get_entity($annotation->value)) && ($user instanceOf ElggUser)) {
						$users_old_way[] = $user;
					}
				}
			}

			$users_new_way = elgg_get_entities_from_relationship($options);

			return array_merge($users_old_way,$users_new_way);
		}
	}

	/**
	 * Get region that the event is held in.
	 *
	 * @return string $region
	 */
	public function getRegion() {
		$region_list_handles = elgg_get_plugin_setting('region_list_handles', 'event_calendar');

		$region = trim($this->region);

		if ($region_list_handles == 'yes') {
			$region = elgg_echo("event_calendar:region:$region");
		}

		return htmlspecialchars($region);
	}

	/**
	 * Get the name of the event type
	 *
	 * @return string $type The eveny type
	 */
	public function getEventType() {
		$type_list_handles = elgg_get_plugin_setting('type_list_handles', 'event_calendar');
		$type = trim($this->event_type);

		if ($type) {
			if ($type_list_handles == 'yes') {
				$type = elgg_echo("event_calendar:type:$type");
			}

			return htmlspecialchars($type);
		} else {
			return $type;
		}
	}

	/**
	 * Return whether the event is private, open or closed for the given user.
	 *
	 * @todo Find out why this function returns string instead of boolean.
	 *
	 * @return string $status 'private', 'open' or 'closed'
	 */
	public function canManage($user = null) {
		if ($user == null) {
			$user = elgg_get_logged_in_user_entity();
		}

		$status = 'private';
		$personal_manage = elgg_get_plugin_setting('personal_manage', 'event_calendar');

		if (!$personal_manage
			|| $personal_manage == 'open'
			|| $personal_manage == 'yes'
			|| (($personal_manage == 'by_event' && (!$this->personal_manage || ($this->personal_manage == 'open'))))) {
			$status = 'open';
		} else {
			// in this case only admins or event owners can manage events on their personal calendars
			if (elgg_is_admin_logged_in()) {
				$status = 'open';
			} else if ($event && ($this->owner_guid == $user_id)) {
				$status = 'open';
			} else if (($personal_manage == 'closed')
				|| ($personal_manage == 'no')
				|| (($personal_manage == 'by_event') && ($this->personal_manage == 'closed'))) {
				$status = 'closed';
			}
		}

		return $status;
	}
}
