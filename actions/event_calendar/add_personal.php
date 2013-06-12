<?php
elgg_load_library('elgg:event_calendar');

$event_guid = get_input('guid', 0);
$event = get_entity($event_guid);

if (elgg_instanceof($event, 'object', 'event_calendar')) {
	$user = elgg_get_logged_in_user_entity();

	if (!$event->isParticipating($user)) {
		if ($event->addParticipant($user)) {
			system_message(elgg_echo('event_calendar:add_to_my_calendar_response'));
		} else {
			register_error(elgg_echo('event_calendar:add_to_my_calendar_error'));
		}
	}
}

forward(REFERER);
