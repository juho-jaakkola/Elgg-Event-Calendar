<?php
/**
 * Register the EventCalendar class for the object/event_calendar subtype
 */

if (get_subtype_id('object', 'event_calendar')) {
	update_subtype('object', 'event_calendar', 'EventCalendar');
} else {
	add_subtype('object', 'event_calendar', 'EventCalendar');
}
