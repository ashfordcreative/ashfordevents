<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ash_Events_Ical {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_output' ) );
	}

	public static function maybe_output() {
		if ( ! isset( $_GET['ash_ical'] ) ) {
			return;
		}

		$single_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : 0;

		if ( $single_id ) {
			$post = get_post( $single_id );
			if ( ! $post || 'ash_event' !== $post->post_type || 'publish' !== $post->post_status ) {
				status_header( 404 );
				exit;
			}
			$events = array( $post );
		} else {
			$from   = current_time( 'Y-m-d' );
			$to     = gmdate( 'Y-m-d', strtotime( $from . ' +180 days' ) );
			$events = Ash_Events_Query::between( $from, $to );
		}

		$site = get_bloginfo( 'name' );
		$tz   = wp_timezone_string();

		$lines   = array();
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//Ashford Events//' . self::esc( $site ) . '//EN';
		$lines[] = 'CALSCALE:GREGORIAN';
		$lines[] = 'METHOD:PUBLISH';
		$lines[] = 'X-WR-CALNAME:' . self::esc( $site . ' Events' );
		$lines[] = 'X-WR-TIMEZONE:' . self::esc( $tz );

		foreach ( $events as $event ) {
			$date = get_post_meta( $event->ID, '_ash_start_date', true );
			if ( ! $date ) {
				continue;
			}
			$time = get_post_meta( $event->ID, '_ash_start_time', true );
			$end  = get_post_meta( $event->ID, '_ash_end_time', true );

			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:ash-event-' . $event->ID . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
			$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z', strtotime( $event->post_modified_gmt . ' UTC' ) );

			if ( $time ) {
				$lines[] = 'DTSTART;TZID=' . $tz . ':' . gmdate( 'Ymd\THis', strtotime( $date . ' ' . $time ) );
				$end_time = $end ? $end : gmdate( 'H:i', strtotime( $time . ' +3 hours' ) );
				$lines[]  = 'DTEND;TZID=' . $tz . ':' . gmdate( 'Ymd\THis', strtotime( $date . ' ' . $end_time ) );
			} else {
				// Time TBD -> all-day event.
				$lines[] = 'DTSTART;VALUE=DATE:' . gmdate( 'Ymd', strtotime( $date ) );
				$lines[] = 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', strtotime( $date . ' +1 day' ) );
			}

			$lines[] = 'SUMMARY:' . self::esc( get_the_title( $event ) );
			$lines[] = 'URL:' . self::esc( get_permalink( $event ) );

			$desc_parts = array();
			$label      = ash_events_label( $event->ID );
			$buyin      = get_post_meta( $event->ID, '_ash_buyin', true );
			$gtd        = get_post_meta( $event->ID, '_ash_guarantee', true );
			if ( $label ) { $desc_parts[] = $label; }
			if ( $buyin ) { $desc_parts[] = 'Buy-In: ' . $buyin; }
			if ( $gtd )   { $desc_parts[] = 'Guarantee: ' . $gtd; }
			if ( $desc_parts ) {
				$lines[] = 'DESCRIPTION:' . self::esc( implode( ' | ', $desc_parts ) );
			}
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="events.ics"' );
		echo implode( "\r\n", array_map( array( __CLASS__, 'fold' ), $lines ) ) . "\r\n";
		exit;
	}

	/** Escape text per RFC 5545. */
	private static function esc( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		return str_replace(
			array( '\\', ';', ',', "\n", "\r" ),
			array( '\\\\', '\;', '\,', '\n', '' ),
			$text
		);
	}

	/** Fold lines longer than 75 octets per RFC 5545. */
	private static function fold( $line ) {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}
		$out = '';
		while ( strlen( $line ) > 75 ) {
			$out .= substr( $line, 0, 75 ) . "\r\n ";
			$line = substr( $line, 75 );
		}
		return $out . $line;
	}
}
