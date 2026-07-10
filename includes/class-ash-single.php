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
		$text    = ash_events_text_color( $post_id );
		$details = '<div class="ash-single-page ash-single-page--embed" style="--ash-ev:' . esc_attr( $color ) . ';--ash-ev-text:' . esc_attr( $text ) . '">'
			. self::render_details( $post_id )
			. '</div>';
		return $details . $content;
	}

	/**
	 * The event details block (label, when, amenities, CTA, .ics link).
	 * Shared by the content-injection fallback.
	 */
	public static function render_details( $post_id, $include_label = true ) {
		$date    = get_post_meta( $post_id, '_ash_start_date', true );
		$time    = get_post_meta( $post_id, '_ash_start_time', true );
		$end     = get_post_meta( $post_id, '_ash_end_time', true );
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

		echo self::render_amenities( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $website ) {
			echo '<p><a class="ash-single-page__cta" href="' . esc_url( $website ) . '" target="_blank" rel="noopener">' . esc_html__( 'Event Details & Registration', 'ashford-events' ) . '</a></p>';
		}

		if ( $date ) {
			$ics = add_query_arg( array( 'ash_ical' => '1', 'event' => $post_id ), home_url( '/' ) );
			echo '<p class="ash-single-page__ics"><a href="' . esc_url( $ics ) . '" rel="nofollow">' . esc_html__( '+ Add to calendar (.ics)', 'ashford-events' ) . '</a></p>';
		}

		return ob_get_clean();
	}

	/**
	 * Airbnb-style amenity rows for date, time, category, buy-in, etc.
	 */
	public static function render_amenities( $post_id ) {
		$date    = get_post_meta( $post_id, '_ash_start_date', true );
		$time    = get_post_meta( $post_id, '_ash_start_time', true );
		$buyin   = get_post_meta( $post_id, '_ash_buyin', true );
		$rebuys  = get_post_meta( $post_id, '_ash_rebuys', true );
		$gtd     = get_post_meta( $post_id, '_ash_guarantee', true );
		$website = get_post_meta( $post_id, '_ash_website', true );
		$terms   = get_the_terms( $post_id, 'ash_event_cat' );

		$rows = array();

		if ( $date ) {
			$format = ( gmdate( 'Y', strtotime( $date ) ) === current_time( 'Y' ) ) ? 'F j' : 'F j, Y';
			$rows[] = array(
				'icon'  => 'calendar',
				'label' => __( 'Date', 'ashford-events' ),
				'value' => date_i18n( $format, strtotime( $date ) ),
			);
		}

		$rows[] = array(
			'icon'  => 'clock',
			'label' => __( 'Time', 'ashford-events' ),
			'value' => $time
				? date_i18n( 'g:i a', strtotime( ( $date ? $date . ' ' : '' ) . $time ) )
				: __( 'TBD', 'ashford-events' ),
		);

		if ( $terms && ! is_wp_error( $terms ) ) {
			$rows[] = array(
				'icon'  => 'tag',
				'label' => __( 'Category', 'ashford-events' ),
				'value' => implode( ', ', wp_list_pluck( $terms, 'name' ) ),
			);
		}

		if ( $buyin ) {
			$rows[] = array(
				'icon'  => 'chips',
				'label' => __( 'Buy-In / Stack', 'ashford-events' ),
				'value' => $buyin,
			);
		}

		if ( $gtd ) {
			$rows[] = array(
				'icon'  => 'trophy',
				'label' => __( 'Guarantee', 'ashford-events' ),
				'value' => $gtd,
			);
		}

		if ( $rebuys ) {
			$rows[] = array(
				'icon'  => 'layers',
				'label' => __( 'Rebuys / Add-Ons / Levels', 'ashford-events' ),
				'value' => $rebuys,
			);
		}

		if ( $website ) {
			$host = wp_parse_url( $website, PHP_URL_HOST );
			$rows[] = array(
				'icon'  => 'link',
				'label' => __( 'Website', 'ashford-events' ),
				'value' => '<a href="' . esc_url( $website ) . '" target="_blank" rel="noopener">' . esc_html( $host ? $host : $website ) . '</a>',
				'html'  => true,
			);
		}

		if ( empty( $rows ) ) {
			return '';
		}

		ob_start();
		?>
		<section class="ash-single-page__amenities">
			<h2 class="ash-single-page__amenities-heading"><?php esc_html_e( 'What this event includes', 'ashford-events' ); ?></h2>
			<ul class="ash-single-page__amenity-list">
				<?php foreach ( $rows as $row ) : ?>
					<li class="ash-single-page__amenity">
						<span class="ash-single-page__amenity-icon" aria-hidden="true"><?php echo self::amenity_icon( $row['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="ash-single-page__amenity-body">
							<span class="ash-single-page__amenity-label"><?php echo esc_html( $row['label'] ); ?></span>
							<span class="ash-single-page__amenity-value">
								<?php
								if ( ! empty( $row['html'] ) ) {
									echo $row['value']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with esc_url/esc_html
								} else {
									echo esc_html( $row['value'] );
								}
								?>
							</span>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
		return ob_get_clean();
	}

	/** Inline SVG icons for amenity rows. */
	public static function amenity_icon( $name ) {
		$icons = array(
			'calendar' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg>',
			'clock'    => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
			'tag'      => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 12 22l-9-9V3h10z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>',
			'chips'    => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M6.3 6.3l2.1 2.1M15.6 15.6l2.1 2.1M17.7 6.3l-2.1 2.1M8.4 15.6l-2.1 2.1"/></svg>',
			'trophy'   => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4z"/><path d="M7 6H5a2 2 0 0 0 2 4M17 6h2a2 2 0 0 1-2 4"/></svg>',
			'layers'   => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 9 5-9 5-9-5 9-5z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/></svg>',
			'link'     => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.07 0l2.12-2.12a5 5 0 0 0-7.07-7.07L10.7 5.24"/><path d="M14 11a5 5 0 0 0-7.07 0L4.8 13.12a5 5 0 0 0 7.07 7.07L13.3 18.76"/></svg>',
		);
		return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
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
