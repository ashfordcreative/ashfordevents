<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ash_Events_Importer {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_ash_import_upload', array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_ash_import_commit', array( __CLASS__, 'handle_commit' ) );
		add_action( 'admin_post_ash_save_settings', array( __CLASS__, 'handle_settings' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=ash_event',
			__( 'Import & Tools', 'ashford-events' ),
			__( 'Import & Tools', 'ashford-events' ),
			self::CAP,
			'ash-import',
			array( __CLASS__, 'render_page' )
		);
	}

	private static function transient_key() {
		return 'ash_import_' . get_current_user_id();
	}

	/* ------------------------------------------------------------------ */
	/*  Page                                                               */
	/* ------------------------------------------------------------------ */

	public static function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ashford-events' ) );
		}
		$preview = get_transient( self::transient_key() );
		?>
		<div class="wrap ash-import">
			<h1><?php esc_html_e( 'Events — Import & Tools', 'ashford-events' ); ?></h1>

			<?php self::render_notices(); ?>

			<?php if ( $preview ) : ?>
				<?php self::render_preview( $preview ); ?>
			<?php else : ?>
				<?php self::render_upload_form(); ?>
			<?php endif; ?>

			<hr>
			<?php self::render_settings(); ?>

			<hr>
			<?php Ash_Events_Migrate::render_section(); ?>
		</div>
		<?php
	}

	private static function render_notices() {
		if ( ! isset( $_GET['ash_notice'] ) ) {
			return;
		}
		$notice = sanitize_key( wp_unslash( $_GET['ash_notice'] ) );
		$msgs   = array(
			'import_done'   => sprintf(
				/* translators: 1: created count, 2: updated count, 3: skipped count */
				__( 'Import complete: %1$d created, %2$d updated, %3$d skipped.', 'ashford-events' ),
				isset( $_GET['created'] ) ? absint( $_GET['created'] ) : 0,
				isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0,
				isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0
			),
			'upload_error'  => __( 'The file could not be read. Please upload a valid CSV.', 'ashford-events' ),
			'no_rows'       => __( 'No importable rows were found in that file.', 'ashford-events' ),
			'settings'      => __( 'Settings saved.', 'ashford-events' ),
			'migrate_done'  => sprintf(
				/* translators: 1: newly migrated count, 2: updated count, 3: skipped count */
				__( 'Migration complete: %1$d created, %2$d updated, %3$d skipped.', 'ashford-events' ),
				isset( $_GET['migrated'] ) ? absint( $_GET['migrated'] ) : 0,
				isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0,
				isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0
			),
			'migrate_none'  => __( 'No events from The Events Calendar were found.', 'ashford-events' ),
		);
		if ( isset( $msgs[ $notice ] ) ) {
			$class = in_array( $notice, array( 'upload_error', 'no_rows', 'migrate_none' ), true ) ? 'notice-warning' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $msgs[ $notice ] ) . '</p></div>';
		}
	}

	private static function render_upload_form() {
		?>
		<h2><?php esc_html_e( 'Import events from CSV', 'ashford-events' ); ?></h2>
		<p><?php esc_html_e( 'Upload a CSV to preview before anything is created. Recognized columns:', 'ashford-events' ); ?>
			<code>Event Name</code>, <code>Event Start Date</code>, <code>Event Start Time</code>, <code>Event End Time</code>,
			<code>Event Category</code>, <code>Event Description</code> (<?php esc_html_e( 'used as the display label', 'ashford-events' ); ?>),
			<code>Event Label</code>, <code>Event Color</code>, <code>Event Website</code>, <code>Event Featured Image</code>,
			<code>Buy-In/Starting Stack</code>, <code>Rebuys/Add-Ons/Levels</code>, <code>Guarantee</code>.
		</p>
		<p><?php esc_html_e( 'Rows matching an existing event (same name and date) update it instead of creating a duplicate — including filling in times on TBD events. Reimporting a corrected sheet is safe. A start time of "TBD" imports as an all-day event.', 'ashford-events' ); ?></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ash_import_upload' ); ?>
			<input type="hidden" name="action" value="ash_import_upload">
			<p><input type="file" name="ash_csv" accept=".csv,text/csv" required></p>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Upload & Preview', 'ashford-events' ); ?></button></p>
		</form>
		<?php
	}

	private static function render_preview( $rows ) {
		$counts = array( 'new' => 0, 'update' => 0, 'error' => 0 );
		foreach ( $rows as $row ) {
			$counts[ $row['status'] ]++;
		}
		?>
		<h2><?php esc_html_e( 'Preview import', 'ashford-events' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: new count, 2: update count, 3: error count */
				esc_html__( '%1$d new · %2$d will update existing events · %3$d have errors and will be skipped.', 'ashford-events' ),
				(int) $counts['new'],
				(int) $counts['update'],
				(int) $counts['error']
			);
			?>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Status', 'ashford-events' ); ?></th>
					<th><?php esc_html_e( 'Event', 'ashford-events' ); ?></th>
					<th><?php esc_html_e( 'Date', 'ashford-events' ); ?></th>
					<th><?php esc_html_e( 'Time', 'ashford-events' ); ?></th>
					<th><?php esc_html_e( 'Category', 'ashford-events' ); ?></th>
					<th><?php esc_html_e( 'Label', 'ashford-events' ); ?></th>
					<th><?php esc_html_e( 'Buy-In', 'ashford-events' ); ?></th>
					<th><?php esc_html_e( 'Note', 'ashford-events' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td>
							<?php if ( 'new' === $row['status'] ) : ?>
								<span style="color:#00742e;font-weight:600"><?php esc_html_e( 'New', 'ashford-events' ); ?></span>
							<?php elseif ( 'update' === $row['status'] ) : ?>
								<span style="color:#996800;font-weight:600"><?php esc_html_e( 'Update', 'ashford-events' ); ?></span>
							<?php else : ?>
								<span style="color:#b32d2e;font-weight:600"><?php esc_html_e( 'Error', 'ashford-events' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $row['title'] ); ?></td>
						<td><?php echo esc_html( $row['date'] ? date_i18n( 'D, M j, Y', strtotime( $row['date'] ) ) : '—' ); ?></td>
						<td><?php echo $row['time'] ? esc_html( date_i18n( 'g:i a', strtotime( '2000-01-01 ' . $row['time'] ) ) ) : '<em>' . esc_html__( 'TBD', 'ashford-events' ) . '</em>'; ?></td>
						<td><?php echo esc_html( $row['category'] ); ?></td>
						<td><?php echo esc_html( $row['label'] ); ?></td>
						<td><?php echo esc_html( $row['buyin'] ); ?></td>
						<td><?php echo esc_html( $row['note'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
			<?php wp_nonce_field( 'ash_import_commit' ); ?>
			<input type="hidden" name="action" value="ash_import_commit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Run Import', 'ashford-events' ); ?></button>
			<button type="submit" name="cancel" value="1" class="button"><?php esc_html_e( 'Cancel', 'ashford-events' ); ?></button>
		</form>
		<?php
	}

	private static function render_settings() {
		$color   = get_option( 'ash_events_default_color', '#C9A353' );
		$cal_url = get_option( 'ash_events_calendar_url', '' );
		?>
		<h2><?php esc_html_e( 'Settings', 'ashford-events' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ash_save_settings' ); ?>
			<input type="hidden" name="action" value="ash_save_settings">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="ash_default_color"><?php esc_html_e( 'Default event color', 'ashford-events' ); ?></label></th>
					<td>
						<input type="text" id="ash_default_color" name="ash_default_color" class="ash-color-field" value="<?php echo esc_attr( $color ); ?>">
						<p class="description"><?php esc_html_e( 'Used when an event and its category have no color set.', 'ashford-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ash_calendar_url"><?php esc_html_e( 'All Events URL', 'ashford-events' ); ?></label></th>
					<td>
						<input type="url" id="ash_calendar_url" name="ash_calendar_url" class="regular-text" value="<?php echo esc_attr( $cal_url ); ?>" placeholder="<?php echo esc_attr( home_url( '/events/' ) ); ?>">
						<p class="description"><?php esc_html_e( 'URL for the "« All Events" back link on single event pages. Usually the page with the [ashford_events] shortcode.', 'ashford-events' ); ?></p>
					</td>
				</tr>
			</table>
			<p><button type="submit" class="button"><?php esc_html_e( 'Save Settings', 'ashford-events' ); ?></button></p>
		</form>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/*  Handlers                                                           */
	/* ------------------------------------------------------------------ */

	public static function handle_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die();
		}
		check_admin_referer( 'ash_save_settings' );
		$color = isset( $_POST['ash_default_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['ash_default_color'] ) ) : '';
		update_option( 'ash_events_default_color', $color ? $color : '#C9A353' );
		$cal_url = isset( $_POST['ash_calendar_url'] ) ? esc_url_raw( wp_unslash( $_POST['ash_calendar_url'] ) ) : '';
		update_option( 'ash_events_calendar_url', $cal_url );
		wp_safe_redirect( self::page_url( array( 'ash_notice' => 'settings' ) ) );
		exit;
	}

	public static function handle_upload() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die();
		}
		check_admin_referer( 'ash_import_upload' );

		if ( empty( $_FILES['ash_csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['ash_csv']['tmp_name'] ) ) {
			wp_safe_redirect( self::page_url( array( 'ash_notice' => 'upload_error' ) ) );
			exit;
		}

		$rows = self::parse_csv( $_FILES['ash_csv']['tmp_name'] );
		if ( empty( $rows ) ) {
			wp_safe_redirect( self::page_url( array( 'ash_notice' => 'no_rows' ) ) );
			exit;
		}

		set_transient( self::transient_key(), $rows, HOUR_IN_SECONDS );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	public static function handle_commit() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die();
		}
		check_admin_referer( 'ash_import_commit' );

		$rows = get_transient( self::transient_key() );
		delete_transient( self::transient_key() );

		if ( isset( $_POST['cancel'] ) || empty( $rows ) ) {
			wp_safe_redirect( self::page_url() );
			exit;
		}

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$image_cache = array();

		foreach ( $rows as $row ) {
			if ( 'error' === $row['status'] ) {
				$skipped++;
				continue;
			}
			$result = self::import_row( $row, $image_cache );
			if ( 'created' === $result ) {
				$created++;
			} elseif ( 'updated' === $result ) {
				$updated++;
			} else {
				$skipped++;
			}
		}

		wp_safe_redirect( self::page_url( array(
			'ash_notice' => 'import_done',
			'created'    => $created,
			'updated'    => $updated,
			'skipped'    => $skipped,
		) ) );
		exit;
	}

	private static function page_url( $args = array() ) {
		return add_query_arg( $args, admin_url( 'edit.php?post_type=ash_event&page=ash-import' ) );
	}

	/* ------------------------------------------------------------------ */
	/*  Parsing                                                            */
	/* ------------------------------------------------------------------ */

	private static function parse_csv( $path ) {
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) {
			return array();
		}

		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle );
			return array();
		}

		// Strip UTF-8 BOM from the first header cell, normalize headers.
		$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
		$header    = array_map( function ( $h ) {
			return strtolower( trim( (string) $h ) );
		}, $header );

		$aliases = array(
			'event name'            => 'title',
			'event description'     => 'description',
			'event start date'      => 'date',
			'event start time'      => 'time',
			'event end time'        => 'end_time',
			'event category'        => 'category',
			'event website'         => 'website',
			'event featured image'  => 'image',
			'buy-in/starting stack' => 'buyin',
			'rebuys/add-ons/levels' => 'rebuys',
			'guarantee'             => 'guarantee',
			'event label'           => 'label',
			'event color'           => 'color',
		);

		$col_map = array();
		foreach ( $header as $index => $name ) {
			if ( isset( $aliases[ $name ] ) ) {
				$col_map[ $aliases[ $name ] ] = $index;
			}
		}

		$rows = array();
		while ( ( $raw = fgetcsv( $handle ) ) !== false ) {
			if ( count( array_filter( $raw, 'strlen' ) ) === 0 ) {
				continue; // Skip blank lines.
			}
			$get = function ( $key ) use ( $col_map, $raw ) {
				if ( ! isset( $col_map[ $key ] ) || ! isset( $raw[ $col_map[ $key ] ] ) ) {
					return '';
				}
				return trim( (string) $raw[ $col_map[ $key ] ] );
			};

			$title = $get( 'title' );
			$date  = self::parse_date( $get( 'date' ) );
			$time  = self::parse_time( $get( 'time' ) );
			$label = $get( 'label' );
			if ( '' === $label ) {
				$label = $get( 'description' );
			}
			$color = sanitize_hex_color( $get( 'color' ) );

			$row = array(
				'title'     => $title,
				'date'      => $date,
				'time'      => $time,
				'end_time'  => self::parse_time( $get( 'end_time' ) ),
				'category'  => $get( 'category' ),
				'website'   => esc_url_raw( $get( 'website' ) ),
				'image'     => esc_url_raw( $get( 'image' ) ),
				'buyin'     => $get( 'buyin' ),
				'rebuys'    => $get( 'rebuys' ),
				'guarantee' => $get( 'guarantee' ),
				'label'     => $label,
				'color'     => $color ? $color : '',
				'status'    => 'new',
				'note'      => '',
			);

			if ( '' === $title ) {
				$row['status'] = 'error';
				$row['note']   = __( 'Missing event name.', 'ashford-events' );
			} elseif ( '' === $date ) {
				$row['status'] = 'error';
				$row['note']   = __( 'Unrecognized or missing start date.', 'ashford-events' );
			} else {
				$existing = self::find_existing( $title, $date, $time );
				if ( $existing ) {
					$row['status']      = 'update';
					$row['existing_id'] = $existing;
					$old_time = get_post_meta( $existing, '_ash_start_time', true );
					if ( $time && $old_time !== $time ) {
						$row['note'] = $old_time
							? __( 'Will update the existing event (including start time).', 'ashford-events' )
							: __( 'Will fill in the start time on the existing TBD event.', 'ashford-events' );
					}
				}
				if ( '' === $time && 'error' !== $row['status'] && '' === $row['note'] ) {
					$row['note'] = __( 'No start time — will import as time TBD.', 'ashford-events' );
				}
			}

			$rows[] = $row;
		}
		fclose( $handle );
		return $rows;
	}

	/** Parse messy date strings ("Saturday, August 1, 2026", "8/1/2026", "2026-08-01") to Y-m-d. */
	public static function parse_date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$formats = array( 'l, F j, Y', 'D, F j, Y', 'F j, Y', 'F j Y', 'n/j/Y', 'n/j/y', 'Y-m-d', 'm-d-Y', 'j F Y' );
		foreach ( $formats as $format ) {
			$dt = DateTime::createFromFormat( '!' . $format, $value );
			if ( $dt instanceof DateTime ) {
				return $dt->format( 'Y-m-d' );
			}
		}
		$ts = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d', $ts ) : '';
	}

	/** Parse "12:00 PM", "7pm", "19:00" to H:i. "TBD"/empty returns ''. */
	public static function parse_time( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || 0 === strcasecmp( 'tbd', $value ) ) {
			return '';
		}
		$formats = array( 'g:i A', 'g:iA', 'g A', 'gA', 'H:i', 'H:i:s' );
		foreach ( $formats as $format ) {
			$dt = DateTime::createFromFormat( '!' . $format, strtoupper( $value ) );
			if ( $dt instanceof DateTime ) {
				return $dt->format( 'H:i' );
			}
		}
		$ts = strtotime( $value );
		return $ts ? gmdate( 'H:i', $ts ) : '';
	}

	/**
	 * Find an existing event by title + start date.
	 * Prefers an exact start-time match, then a TBD (empty time) event so a
	 * corrected CSV can fill times without creating duplicates.
	 * Returns post ID or 0.
	 */
	private static function find_existing( $title, $date, $time ) {
		$query = new WP_Query( array(
			'post_type'      => 'ash_event',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending' ),
			'title'          => $title,
			'posts_per_page' => 20,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => '_ash_start_date', 'value' => $date ),
			),
		) );
		if ( empty( $query->posts ) ) {
			return 0;
		}

		$tbd_id = 0;
		foreach ( $query->posts as $post_id ) {
			$existing_time = (string) get_post_meta( $post_id, '_ash_start_time', true );
			if ( $existing_time === (string) $time ) {
				return $post_id;
			}
			if ( '' === $existing_time && ! $tbd_id ) {
				$tbd_id = $post_id;
			}
		}

		// CSV has a real time and the only/best match is a TBD event — update it.
		if ( $time && $tbd_id ) {
			return $tbd_id;
		}

		// Single same-name/same-day event: allow time corrections either way.
		if ( 1 === count( $query->posts ) ) {
			return (int) $query->posts[0];
		}

		return 0;
	}

	/* ------------------------------------------------------------------ */
	/*  Committing                                                         */
	/* ------------------------------------------------------------------ */

	private static function import_row( $row, &$image_cache ) {
		$postarr = array(
			'post_type'   => 'ash_event',
			'post_status' => 'publish',
			'post_title'  => $row['title'],
		);

		$is_update = ! empty( $row['existing_id'] );
		if ( $is_update ) {
			$postarr['ID'] = $row['existing_id'];
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 'skipped';
		}

		update_post_meta( $post_id, '_ash_start_date', $row['date'] );
		update_post_meta( $post_id, '_ash_start_time', $row['time'] );
		update_post_meta( $post_id, '_ash_end_time', $row['end_time'] );
		update_post_meta( $post_id, '_ash_buyin', sanitize_text_field( $row['buyin'] ) );
		update_post_meta( $post_id, '_ash_rebuys', sanitize_text_field( $row['rebuys'] ) );
		update_post_meta( $post_id, '_ash_guarantee', sanitize_text_field( $row['guarantee'] ) );
		update_post_meta( $post_id, '_ash_website', $row['website'] );
		update_post_meta( $post_id, '_ash_label', sanitize_text_field( $row['label'] ) );
		if ( $row['color'] ) {
			update_post_meta( $post_id, '_ash_color', $row['color'] );
		}

		if ( $row['category'] ) {
			$term = term_exists( $row['category'], 'ash_event_cat' );
			if ( ! $term ) {
				$term = wp_insert_term( $row['category'], 'ash_event_cat' );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				wp_set_object_terms( $post_id, array( $term_id ), 'ash_event_cat' );
			}
		}

		if ( $row['image'] ) {
			$attachment_id = self::resolve_image( $row['image'], $post_id, $image_cache );
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		return $is_update ? 'updated' : 'created';
	}

	/** Match an image URL to an existing attachment; sideload only if it's not already in the library. */
	private static function resolve_image( $url, $post_id, &$cache ) {
		if ( isset( $cache[ $url ] ) ) {
			return $cache[ $url ];
		}

		$attachment_id = attachment_url_to_postid( $url );

		// attachment_url_to_postid misses scaled/edited sizes; try stripping dimensions suffix.
		if ( ! $attachment_id && preg_match( '/^(.+)-\d+x\d+(\.[a-z]{3,4})$/i', $url, $m ) ) {
			$attachment_id = attachment_url_to_postid( $m[1] . $m[2] );
		}

		if ( ! $attachment_id ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$sideloaded = media_sideload_image( $url, $post_id, null, 'id' );
			$attachment_id = is_wp_error( $sideloaded ) ? 0 : (int) $sideloaded;
		}

		$cache[ $url ] = $attachment_id;
		return $attachment_id;
	}
}
