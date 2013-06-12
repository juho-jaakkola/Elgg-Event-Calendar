<?php
elgg_load_library('elgg:event_calendar');

$event_guid = get_input('guid',0);
$event = get_entity($event_guid);
if (elgg_instanceof($event,'object','event_calendar')) {
	$user_guid = elgg_get_logged_in_user_entity();
	$event->removeParticipant($user);
	system_message(elgg_echo('event_calendar:remove_from_my_calendar_response'));
}

forward(REFERER);
