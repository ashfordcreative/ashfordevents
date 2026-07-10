<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ash_Events_Migrate {

	public static function init() {
		add_action( 'admin_post_ash_migrate_tec', array( __CLASS__, 'handle' ) );
	}

	public static function render_section() {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'tribe_events' AND post_status IN ('publish','future','draft')"
		);
		?>
		<h2><?php esc_html_e( 'Migrate from The Events Calendar', 'ashford-events' ); ?></h2>
		<?php if ( $count ) : ?>
			<p>
				<?php
				printf(
					/* translators: %d: event count */
					esc_html__( 'Found %d events from The Events Calendar. Migration copies titles, dates, images, categories, and event URLs into Ashford Events — original slugs are preserved so /event/ links keep working after you deactivate the old plugin. Existing TEC events are left untouched.', 'ashford-events' ),
					(int) $count
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ash_migrate_tec' ); ?>
				<input type="hidden" name="action" value="ash_migrate_tec">
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Migrate Events', 'ashford-events' ); ?></button></p>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'No events from The Events Calendar were found on this site.', 'ashford-events' ); ?></p>
		<?php endif; ?>
		<?php
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		check_admin_referer( 'ash_migrate_tec' );

		$tec_events = get_posts( array(
			'post_type'      => 'tribe_events',
			'post_status'    => array( 'publish', 'future', 'draft' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );

		if ( empty( $tec_events ) ) {
			wp_safe_redirect( self::back_url( array( 'ash_notice' => 'migrate_none' ) ) );
			exit;
		}

		$migrated = 0;
		$skipped  = 0;

		foreach ( $tec_events as $tec ) {
			// Skip if we've already migrated this slug.
			$existing = get_posts( array(
				'post_type'      => 'ash_event',
				'name'           => $tec->post_name,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			) );
			if ( $existing ) {
				$skipped++;
				continue;
			}

			$new_id = wp_insert_post( array(
				'post_type'    => 'ash_event',
				'post_status'  => $tec->post_status,
				'post_title'   => $tec->post_title,
				'post_name'    => $tec->post_name,
				'post_content' => $tec->post_content,
				'post_excerpt' => $tec->post_excerpt,
				'post_date'    => $tec->post_date,
			), true );

			if ( is_wp_error( $new_id ) || ! $new_id ) {
				$skipped++;
				continue;
			}

			// Dates: TEC stores 'Y-m-d H:i:s'.
			$start = get_post_meta( $tec->ID, '_EventStartDate', true );
			$end   = get_post_meta( $tec->ID, '_EventEndDate', true );
			$all_day = get_post_meta( $tec->ID, '_EventAllDay', true );

			if ( $start ) {
				update_post_meta( $new_id, '_ash_start_date', gmdate( 'Y-m-d', strtotime( $start ) ) );
				update_post_meta( $new_id, '_ash_start_time', $all_day ? '' : gmdate( 'H:i', strtotime( $start ) ) );
			}
			if ( $end && ! $all_day && $start && gmdate( 'Y-m-d', strtotime( $end ) ) === gmdate( 'Y-m-d', strtotime( $start ) ) ) {
				update_post_meta( $new_id, '_ash_end_time', gmdate( 'H:i', strtotime( $end ) ) );
			}

			$url = get_post_meta( $tec->ID, '_EventURL', true );
			if ( $url ) {
				update_post_meta( $new_id, '_ash_website', esc_url_raw( $url ) );
			}

			// Featured image.
			$thumb = get_post_thumbnail_id( $tec->ID );
			if ( $thumb ) {
				set_post_thumbnail( $new_id, $thumb );
			}

			// Categories (by name, created if missing).
			$terms = get_the_terms( $tec->ID, 'tribe_events_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$term_ids = array();
				foreach ( $terms as $term ) {
					$exists = term_exists( $term->name, 'ash_event_cat' );
					if ( ! $exists ) {
						$exists = wp_insert_term( $term->name, 'ash_event_cat' );
					}
					if ( ! is_wp_error( $exists ) ) {
						$term_ids[] = is_array( $exists ) ? (int) $exists['term_id'] : (int) $exists;
					}
				}
				if ( $term_ids ) {
					wp_set_object_terms( $new_id, $term_ids, 'ash_event_cat' );
				}
			}

			$migrated++;
		}

		wp_safe_redirect( self::back_url( array(
			'ash_notice' => 'migrate_done',
			'migrated'   => $migrated,
			'skipped'    => $skipped,
		) ) );
		exit;
	}

	private static function back_url( $args = array() ) {
		return add_query_arg( $args, admin_url( 'edit.php?post_type=ash_event&page=ash-import' ) );
	}
}
