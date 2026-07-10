<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ash_Events_Single {

	/** True while the plugin's own template is rendering the page. */
	public static $using_plugin_template = false;

	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_filter( 'the_content', array( __CLASS__, 'inject_details' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		if ( is_singular( 'ash_event' ) ) {
			wp_enqueue_style( 'ash-events' );
		}
	}

	/**
	 * Take over the single event template. Runs at priority 99 so it wins over
	 * page-builder theme templates that would otherwise mangle the layout.
	 *
	 * Opt-outs: a theme can provide its own single-ash_event.php, or code can
	 * add_filter( 'ash_events_use_template', '__return_false' ) to fall back to
	 * the_content injection inside the theme's own single template.
	 */
	public static function template( $template ) {
		if ( ! is_singular( 'ash_event' ) ) {
			return $template;
		}
		if ( ! apply_filters( 'ash_events_use_template', true ) ) {
			return $template;
		}

		$theme_template = locate_template( 'single-ash_event.php' );
		if ( $theme_template ) {
			return $theme_template;
		}

		self::$using_plugin_template = true;
		return ASH_EVENTS_DIR . 'templates/single-event.php';
	}

	/**
	 * Fallback: when a theme renders the event through its own single template,
	 * prepend the event details to the content. Skipped when the plugin
	 * template is active (it renders everything itself).
	 */
	public static function inject_details( $content ) {
		if ( self::$using_plugin_template ) {
			return $content;
		}
		if ( ! is_singular( 'ash_event' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		$color   = ash_events_color( $post_id );
		$text    = ash_events_text_on( $color );
		$details = '<div class="ash-single-page ash-single-page--embed" style="--ash-ev:' . esc_attr( $color ) . ';--ash-ev-text:' . esc_attr( $text ) . '">'
			. self::render_details( $post_id )
			. '</div>';
		return $details . $content;
	}

	/**
	 * The event details block (label, when, facts, CTA, .ics link).
	 * Shared by the plugin template and the content-injection fallback.
	 */
	public static function render_details( $post_id, $include_label = true ) {
		$date    = get_post_meta( $post_id, '_ash_start_date', true );
		$time    = get_post_meta( $post_id, '_ash_start_time', true );
		$end     = get_post_meta( $post_id, '_ash_end_time', true );
		$buyin   = get_post_meta( $post_id, '_ash_buyin', true );
		$rebuys  = get_post_meta( $post_id, '_ash_rebuys', true );
		$gtd     = get_post_meta( $post_id, '_ash_guarantee', true );
		$website = get_post_meta( $post_id, '_ash_website', true );
		$label   = ash_events_label( $post_id );

		ob_start();

		if ( $include_label && $label ) {
			echo '<span class="ash-single-page__label">' . esc_html( $label ) . '</span>';
		}

		$when = self::format_when( $date, $time, $end );
		if ( $when ) {
			echo '<p class="ash-single-page__when">' . esc_html( $when ) . '</p>';
		}

		if ( $buyin || $rebuys || $gtd ) {
			echo '<dl class="ash-single-page__facts">';
			if ( $buyin ) {
				echo '<div class="ash-single-page__fact"><dt>' . esc_html__( 'Buy-In / Stack', 'ashford-events' ) . '</dt><dd>' . esc_html( $buyin ) . '</dd></div>';
			}
			if ( $gtd ) {
				echo '<div class="ash-single-page__fact"><dt>' . esc_html__( 'Guarantee', 'ashford-events' ) . '</dt><dd>' . esc_html( $gtd ) . '</dd></div>';
			}
			if ( $rebuys ) {
				echo '<div class="ash-single-page__fact"><dt>' . esc_html__( 'Rebuys / Add-Ons / Levels', 'ashford-events' ) . '</dt><dd>' . esc_html( $rebuys ) . '</dd></div>';
			}
			echo '</dl>';
		}

		if ( $website ) {
			echo '<p><a class="ash-single-page__cta" href="' . esc_url( $website ) . '" target="_blank" rel="noopener">' . esc_html__( 'Event Details & Registration', 'ashford-events' ) . '</a></p>';
		}

		if ( $date ) {
			$ics = add_query_arg( array( 'ash_ical' => '1', 'event' => $post_id ), home_url( '/' ) );
			echo '<p class="ash-single-page__ics"><a href="' . esc_url( $ics ) . '" rel="nofollow">' . esc_html__( '+ Add to calendar (.ics)', 'ashford-events' ) . '</a></p>';
		}

		return ob_get_clean();
	}

	public static function format_when( $date, $time, $end ) {
		if ( ! $date ) {
			return '';
		}
		$when = date_i18n( 'l, F j, Y', strtotime( $date ) );
		if ( $time ) {
			$when .= ' @ ' . date_i18n( 'g:i a', strtotime( $date . ' ' . $time ) );
			if ( $end ) {
				$when .= ' – ' . date_i18n( 'g:i a', strtotime( $date . ' ' . $end ) );
			}
		} else {
			$when .= ' — ' . __( 'Time TBD', 'ashford-events' );
		}
		return $when;
	}

	/** Short "July 10 @ 9:00 pm" line for the single page header (year only if not current). */
	public static function format_when_short( $date, $time ) {
		if ( ! $date ) {
			return '';
		}
		$format = ( gmdate( 'Y', strtotime( $date ) ) === current_time( 'Y' ) ) ? 'F j' : 'F j, Y';
		$when   = date_i18n( $format, strtotime( $date ) );
		$when  .= $time
			? ' @ ' . date_i18n( 'g:i a', strtotime( $date . ' ' . $time ) )
			: ' — ' . __( 'Time TBD', 'ashford-events' );
		return $when;
	}

	/** URL of the main calendar page ("« All Events" target). */
	public static function calendar_url() {
		$url = get_option( 'ash_events_calendar_url', '' );
		if ( $url ) {
			return $url;
		}
		$archive = get_post_type_archive_link( 'ash_event' );
		return $archive ? $archive : home_url( '/' );
	}

	/** Google Calendar "add event" template link. */
	public static function google_link( $post_id ) {
		$date = get_post_meta( $post_id, '_ash_start_date', true );
		if ( ! $date ) {
			return '';
		}
		$time = get_post_meta( $post_id, '_ash_start_time', true );
		$end  = get_post_meta( $post_id, '_ash_end_time', true );

		if ( $time ) {
			$start_ts = strtotime( $date . ' ' . $time );
			$end_ts   = $end ? strtotime( $date . ' ' . $end ) : $start_ts + 3 * HOUR_IN_SECONDS;
			$dates    = gmdate( 'Ymd\THis', $start_ts ) . '/' . gmdate( 'Ymd\THis', $end_ts );
		} else {
			$dates = gmdate( 'Ymd', strtotime( $date ) ) . '/' . gmdate( 'Ymd', strtotime( $date . ' +1 day' ) );
		}

		return add_query_arg( array(
			'action'  => 'TEMPLATE',
			'text'    => rawurlencode( get_the_title( $post_id ) ),
			'dates'   => $dates,
			'ctz'     => rawurlencode( wp_timezone_string() ),
			'details' => rawurlencode( get_permalink( $post_id ) ),
		), 'https://www.google.com/calendar/render' );
	}

	/** Upcoming events in the same category (fallback: any upcoming), excluding the current one. */
	public static function related( $post_id, $limit = 3 ) {
		$today = current_time( 'Y-m-d' );
		$args  = array(
			'post_type'      => 'ash_event',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'post__not_in'   => array( $post_id ),
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => '_ash_start_date',
					'value'   => $today,
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
			'meta_key' => '_ash_start_date',
			'orderby'  => 'meta_value',
			'order'    => 'ASC',
		);

		$terms = get_the_terms( $post_id, 'ash_event_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'ash_event_cat',
					'field'    => 'term_id',
					'terms'    => wp_list_pluck( $terms, 'term_id' ),
				),
			);
		}

		$related = get_posts( $args );
		if ( empty( $related ) && isset( $args['tax_query'] ) ) {
			unset( $args['tax_query'] );
			$related = get_posts( $args );
		}
		return $related;
	}
}
