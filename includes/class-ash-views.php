<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ash_Events_Views {

	public static function init() {
		add_shortcode( 'ashford_events', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest_routes' ) );
	}

	public static function register_assets() {
		wp_register_style(
			'ash-events-fonts',
			'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap',
			array(),
			null
		);
		wp_register_style( 'ash-events', ASH_EVENTS_URL . 'assets/css/calendar.css', array( 'ash-events-fonts' ), ASH_EVENTS_VERSION );
		wp_register_script( 'ash-events', ASH_EVENTS_URL . 'assets/js/calendar.js', array(), ASH_EVENTS_VERSION, true );
	}

	public static function rest_routes() {
		register_rest_route( 'ash-events/v1', '/calendar', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => array(
				'month'    => array( 'required' => true ),
				'view'     => array( 'default' => 'month' ),
				'category' => array( 'default' => '' ),
				'months'   => array( 'default' => 1 ),
			),
			'callback'            => array( __CLASS__, 'rest_calendar' ),
		) );
	}

	public static function rest_calendar( WP_REST_Request $request ) {
		$month = sanitize_text_field( $request['month'] );
		if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
			$month = current_time( 'Y-m' );
		}
		$view     = 'list' === $request['view'] ? 'list' : 'month';
		$category = sanitize_text_field( $request['category'] );
		$months   = max( 1, absint( $request['months'] ) );

		return rest_ensure_response( array(
			'month' => $month,
			'title' => date_i18n( 'F Y', strtotime( $month . '-01' ) ),
			'html'  => self::render_body( $month, $view, $category, $months ),
		) );
	}

	/**
	 * [ashford_events view="month|list" category="slug" months="1"]
	 */
	public static function shortcode( $atts ) {
		wp_enqueue_style( 'ash-events' );
		wp_enqueue_script( 'ash-events' );

		$atts = shortcode_atts( array(
			'view'     => 'month',
			'category' => '',
			'months'   => 1,
		), $atts, 'ashford_events' );
		$view = 'list' === $atts['view'] ? 'list' : 'month';

		$month = isset( $_GET['ash_month'] ) ? sanitize_text_field( wp_unslash( $_GET['ash_month'] ) ) : '';
		if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
			$month = current_time( 'Y-m' );
		}
		$category = $atts['category'];
		if ( '' === $category && isset( $_GET['ash_cat'] ) ) {
			$category = sanitize_title( wp_unslash( $_GET['ash_cat'] ) );
		}

		ob_start();
		?>
		<div class="ash-cal"
			data-view="<?php echo esc_attr( $view ); ?>"
			data-month="<?php echo esc_attr( $month ); ?>"
			data-current="<?php echo esc_attr( current_time( 'Y-m' ) ); ?>"
			data-category="<?php echo esc_attr( $category ); ?>"
			data-months="<?php echo esc_attr( max( 1, (int) $atts['months'] ) ); ?>"
			data-endpoint="<?php echo esc_url( rest_url( 'ash-events/v1/calendar' ) ); ?>">
			<?php self::render_nav( $month, '' === $atts['category'] ? $category : null ); ?>
			<div class="ash-cal__body" aria-live="polite">
				<?php echo self::render_body( $month, $view, $category, (int) $atts['months'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Body (grid + mobile list, or list). Shared by shortcode and REST.
	 */
	public static function render_body( $month, $view, $category, $months ) {
		$first = $month . '-01';
		$last  = gmdate( 'Y-m-t', strtotime( $first ) );

		ob_start();
		if ( 'list' === $view ) {
			$current_month = current_time( 'Y-m' );
			$today         = current_time( 'Y-m-d' );
			$from          = ( $month === $current_month ) ? $today : $first;
			$to            = gmdate( 'Y-m-t', strtotime( $first . ' +' . ( $months - 1 ) . ' months' ) );
			$events        = Ash_Events_Query::between( $from, $to, $category );
			self::render_list( $events );
		} else {
			$today         = current_time( 'Y-m-d' );
			$current_month = current_time( 'Y-m' );
			// Current month: start the grid on today so upcoming events lead.
			// Other months: classic Sunday-aligned month grid.
			if ( $month === $current_month ) {
				$start_of_week = (int) gmdate( 'w', strtotime( $today ) );
				$grid_start    = $today;
			} else {
				$start_of_week = 0; // Sunday
				$grid_start    = self::grid_start( $first, $start_of_week );
			}
			$grid_end = gmdate( 'Y-m-d', strtotime( $grid_start . ' +41 days' ) );
			$events   = Ash_Events_Query::between( $grid_start, $grid_end, $category );
			$by_date  = Ash_Events_Query::group_by_date( $events );

			self::render_month_grid( $month, $grid_start, $by_date, $start_of_week, $month === $current_month );

			$month_events = array();
			foreach ( $events as $e ) {
				$d = get_post_meta( $e->ID, '_ash_start_date', true );
				if ( $d >= $first && $d <= $last ) {
					// On the current month mobile list, skip past days.
					if ( $month === $current_month && $d < $today ) {
						continue;
					}
					$month_events[] = $e;
				}
			}
			echo '<div class="ash-cal__mobile-list">';
			self::render_list( $month_events );
			echo '</div>';
		}
		return ob_get_clean();
	}

	private static function grid_start( $first_of_month, $start_of_week ) {
		$dow    = (int) gmdate( 'w', strtotime( $first_of_month ) );
		$offset = ( $dow - $start_of_week + 7 ) % 7;
		return gmdate( 'Y-m-d', strtotime( $first_of_month . ' -' . $offset . ' days' ) );
	}

	/**
	 * @param string      $month           Y-m
	 * @param string|null $active_category Slug of active category filter, or null to hide the filter
	 *                                     (used when the shortcode locks a category).
	 */
	private static function render_nav( $month, $active_category ) {
		$prev = gmdate( 'Y-m', strtotime( $month . '-01 -1 month' ) );
		$next = gmdate( 'Y-m', strtotime( $month . '-01 +1 month' ) );
		$now  = current_time( 'Y-m' );
		$base = remove_query_arg( array( 'ash_month', 'ash_cat' ) );

		$chev_left  = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>';
		$chev_right = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
		?>
		<div class="ash-cal__nav">
			<div class="ash-cal__nav-left">
				<a class="ash-cal__chev" data-ash-nav="prev" href="<?php echo esc_url( add_query_arg( 'ash_month', $prev, $base ) ); ?>" rel="nofollow" aria-label="<?php esc_attr_e( 'Previous month', 'ashford-events' ); ?>"><?php echo $chev_left; // phpcs:ignore ?></a>
				<a class="ash-cal__chev" data-ash-nav="next" href="<?php echo esc_url( add_query_arg( 'ash_month', $next, $base ) ); ?>" rel="nofollow" aria-label="<?php esc_attr_e( 'Next month', 'ashford-events' ); ?>"><?php echo $chev_right; // phpcs:ignore ?></a>
				<a class="ash-cal__today" data-ash-nav="today" href="<?php echo esc_url( add_query_arg( 'ash_month', $now, $base ) ); ?>" rel="nofollow"><?php esc_html_e( 'This Month', 'ashford-events' ); ?></a>
				<h2 class="ash-cal__title"><?php echo esc_html( date_i18n( 'F Y', strtotime( $month . '-01' ) ) ); ?></h2>
			</div>
			<?php if ( null !== $active_category ) : ?>
				<div class="ash-cal__nav-right">
					<?php self::render_category_filter( $active_category ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_category_filter( $active ) {
		$terms = get_terms( array(
			'taxonomy'   => 'ash_event_cat',
			'hide_empty' => true,
		) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}
		$default = get_option( 'ash_events_default_color', '#C9A353' );

		$active_color = $default;
		foreach ( $terms as $term ) {
			if ( $term->slug === $active ) {
				$c = get_term_meta( $term->term_id, 'ash_color', true );
				$active_color = $c ? $c : $default;
			}
		}
		?>
		<label class="ash-cal__filter">
			<span class="ash-cal__filter-dot" style="background:<?php echo esc_attr( $active_color ); ?>" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Filter by category', 'ashford-events' ); ?></span>
			<select class="ash-cal__filter-select" data-ash-filter>
				<option value="" data-color="<?php echo esc_attr( $default ); ?>"><?php esc_html_e( 'All Events', 'ashford-events' ); ?></option>
				<?php foreach ( $terms as $term ) :
					$c = get_term_meta( $term->term_id, 'ash_color', true );
					$c = $c ? $c : $default;
					?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" data-color="<?php echo esc_attr( $c ); ?>" <?php selected( $active, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php
	}

	private static function render_month_grid( $month, $grid_start, $by_date, $start_of_week, $from_today = false ) {
		$today = current_time( 'Y-m-d' );
		global $wp_locale;

		echo '<div class="ash-cal__grid" role="grid">';

		echo '<div class="ash-cal__weekdays" role="row">';
		for ( $i = 0; $i < 7; $i++ ) {
			$day_index = ( $start_of_week + $i ) % 7;
			echo '<div class="ash-cal__weekday" role="columnheader">' . esc_html( $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $day_index ) ) ) . '</div>';
		}
		echo '</div>';

		for ( $row = 0; $row < 6; $row++ ) {
			echo '<div class="ash-cal__week" role="row">';
			for ( $col = 0; $col < 7; $col++ ) {
				$offset   = $row * 7 + $col;
				$date     = gmdate( 'Y-m-d', strtotime( $grid_start . ' +' . $offset . ' days' ) );
				$in       = $from_today ? true : ( substr( $date, 0, 7 ) === $month );
				$is_today = ( $date === $today );

				$classes = 'ash-cal__day' . ( $in ? '' : ' is-outside' ) . ( $is_today ? ' is-today' : '' );
				echo '<div class="' . esc_attr( $classes ) . '" role="gridcell">';

				// When the rolling "from today" grid crosses into a new month, label the 1st with the month name.
				$day_label = gmdate( 'j', strtotime( $date ) );
				if ( $from_today && '01' === gmdate( 'd', strtotime( $date ) ) ) {
					$day_label = date_i18n( 'F j', strtotime( $date ) );
				}
				echo '<span class="ash-cal__daynum">' . esc_html( $day_label ) . '</span>';

				if ( ! empty( $by_date[ $date ] ) ) {
					echo '<div class="ash-cal__cards">';
					foreach ( $by_date[ $date ] as $event ) {
						self::render_card( $event, false, true );
					}
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	private static function render_card( $event, $show_label = false, $popover = false ) {
		$color = ash_events_color( $event->ID );
		$text  = ash_events_text_color( $event->ID );
		$time  = get_post_meta( $event->ID, '_ash_start_time', true );
		$date  = get_post_meta( $event->ID, '_ash_start_date', true );
		$time_display = $time ? date_i18n( 'g:i a', strtotime( $date . ' ' . $time ) ) : __( 'Time TBD', 'ashford-events' );
		$label = ash_events_label( $event->ID );
		$when  = $date ? date_i18n( 'F j', strtotime( $date ) ) . ' @ ' . $time_display : $time_display;
		?>
		<a class="ash-cal__card" href="<?php echo esc_url( get_permalink( $event ) ); ?>" style="--ash-ev:<?php echo esc_attr( $color ); ?>;--ash-ev-text:<?php echo esc_attr( $text ); ?>">
			<span class="ash-cal__card-time"><?php echo esc_html( $time_display ); ?></span>
			<span class="ash-cal__card-title"><?php echo esc_html( get_the_title( $event ) ); ?></span>
			<?php if ( $show_label && $label ) : ?>
				<span class="ash-cal__card-label"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
			<?php if ( $popover ) : ?>
				<span class="ash-cal__pop" aria-hidden="true">
					<?php if ( has_post_thumbnail( $event ) ) : ?>
						<span class="ash-cal__pop-img"><?php echo get_the_post_thumbnail( $event, 'medium', array( 'loading' => 'lazy' ) ); ?></span>
					<?php endif; ?>
					<span class="ash-cal__pop-when"><?php echo esc_html( $when ); ?></span>
					<span class="ash-cal__pop-title"><?php echo esc_html( get_the_title( $event ) ); ?></span>
					<?php if ( $label ) : ?>
						<span class="ash-cal__pop-label"><?php echo esc_html( $label ); ?></span>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</a>
		<?php
	}

	private static function render_list( $events ) {
		$by_date = Ash_Events_Query::group_by_date( $events );
		if ( empty( $by_date ) ) {
			echo '<p class="ash-cal__empty">' . esc_html__( 'No events scheduled this month. Use the arrows above to browse.', 'ashford-events' ) . '</p>';
			return;
		}
		echo '<div class="ash-cal__list">';
		foreach ( $by_date as $date => $items ) {
			echo '<div class="ash-cal__list-day">';
			echo '<h3 class="ash-cal__list-date">' . esc_html( date_i18n( 'l, F j', strtotime( $date ) ) ) . '</h3>';
			echo '<div class="ash-cal__cards">';
			foreach ( $items as $event ) {
				self::render_card( $event, true );
			}
			echo '</div></div>';
		}
		echo '</div>';
	}
}
