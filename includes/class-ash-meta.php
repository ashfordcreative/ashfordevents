<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ash_Events_Meta {

	const FIELDS = array(
		'_ash_start_date', '_ash_start_time', '_ash_end_time',
		'_ash_buyin', '_ash_rebuys', '_ash_guarantee',
		'_ash_website', '_ash_label', '_ash_color',
	);

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_boxes' ) );
		add_action( 'save_post_ash_event', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );

		// Category color (term meta).
		add_action( 'ash_event_cat_add_form_fields', array( __CLASS__, 'term_add_field' ) );
		add_action( 'ash_event_cat_edit_form_fields', array( __CLASS__, 'term_edit_field' ) );
		add_action( 'created_ash_event_cat', array( __CLASS__, 'term_save' ) );
		add_action( 'edited_ash_event_cat', array( __CLASS__, 'term_save' ) );
	}

	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		$needs  = $screen && ( 'ash_event' === $screen->post_type );
		if ( ! $needs ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'ash-events-admin', ASH_EVENTS_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), ASH_EVENTS_VERSION, true );
	}

	public static function add_boxes() {
		add_meta_box( 'ash_event_details', __( 'Event Details', 'ashford-events' ), array( __CLASS__, 'render_details' ), 'ash_event', 'normal', 'high' );
		add_meta_box( 'ash_event_display', __( 'Display', 'ashford-events' ), array( __CLASS__, 'render_display' ), 'ash_event', 'side', 'default' );
	}

	public static function render_details( $post ) {
		wp_nonce_field( 'ash_event_meta', 'ash_event_meta_nonce' );
		$v = array();
		foreach ( self::FIELDS as $f ) {
			$v[ $f ] = get_post_meta( $post->ID, $f, true );
		}
		?>
		<table class="form-table ash-meta-table">
			<tr>
				<th><label for="ash_start_date"><?php esc_html_e( 'Start date', 'ashford-events' ); ?></label></th>
				<td><input type="date" id="ash_start_date" name="ash_start_date" value="<?php echo esc_attr( $v['_ash_start_date'] ); ?>" required></td>
			</tr>
			<tr>
				<th><label for="ash_start_time"><?php esc_html_e( 'Start time', 'ashford-events' ); ?></label></th>
				<td>
					<input type="time" id="ash_start_time" name="ash_start_time" value="<?php echo esc_attr( $v['_ash_start_time'] ); ?>">
					<p class="description"><?php esc_html_e( 'Leave empty for "Time TBD".', 'ashford-events' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ash_end_time"><?php esc_html_e( 'End time (optional)', 'ashford-events' ); ?></label></th>
				<td><input type="time" id="ash_end_time" name="ash_end_time" value="<?php echo esc_attr( $v['_ash_end_time'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ash_buyin"><?php esc_html_e( 'Buy-In / Starting Stack', 'ashford-events' ); ?></label></th>
				<td><input type="text" class="regular-text" id="ash_buyin" name="ash_buyin" value="<?php echo esc_attr( $v['_ash_buyin'] ); ?>" placeholder="$400/50K"></td>
			</tr>
			<tr>
				<th><label for="ash_rebuys"><?php esc_html_e( 'Rebuys / Add-Ons / Levels', 'ashford-events' ); ?></label></th>
				<td><input type="text" class="regular-text" id="ash_rebuys" name="ash_rebuys" value="<?php echo esc_attr( $v['_ash_rebuys'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ash_guarantee"><?php esc_html_e( 'Guarantee', 'ashford-events' ); ?></label></th>
				<td><input type="text" class="regular-text" id="ash_guarantee" name="ash_guarantee" value="<?php echo esc_attr( $v['_ash_guarantee'] ); ?>" placeholder="$100,000"></td>
			</tr>
			<tr>
				<th><label for="ash_website"><?php esc_html_e( 'Registration / event URL', 'ashford-events' ); ?></label></th>
				<td><input type="url" class="large-text" id="ash_website" name="ash_website" value="<?php echo esc_attr( $v['_ash_website'] ); ?>" placeholder="https://"></td>
			</tr>
		</table>
		<?php
	}

	public static function render_display( $post ) {
		$label = get_post_meta( $post->ID, '_ash_label', true );
		$color = get_post_meta( $post->ID, '_ash_color', true );
		?>
		<p>
			<label for="ash_label"><strong><?php esc_html_e( 'Display label', 'ashford-events' ); ?></strong></label><br>
			<input type="text" id="ash_label" name="ash_label" value="<?php echo esc_attr( $label ); ?>" style="width:100%" placeholder="<?php esc_attr_e( 'Daily Tournament', 'ashford-events' ); ?>">
			<span class="description"><?php esc_html_e( 'Shown under the title on the calendar. Falls back to the category name.', 'ashford-events' ); ?></span>
		</p>
		<p>
			<label for="ash_color"><strong><?php esc_html_e( 'Accent color', 'ashford-events' ); ?></strong></label><br>
			<input type="text" id="ash_color" name="ash_color" class="ash-color-field" value="<?php echo esc_attr( $color ); ?>">
			<span class="description"><?php esc_html_e( 'Overrides the category color. Leave empty to inherit.', 'ashford-events' ); ?></span>
		</p>
		<?php
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['ash_event_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ash_event_meta_nonce'], 'ash_event_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$map = array(
			'_ash_start_date' => 'ash_start_date',
			'_ash_start_time' => 'ash_start_time',
			'_ash_end_time'   => 'ash_end_time',
			'_ash_buyin'      => 'ash_buyin',
			'_ash_rebuys'     => 'ash_rebuys',
			'_ash_guarantee'  => 'ash_guarantee',
			'_ash_label'      => 'ash_label',
		);
		foreach ( $map as $meta => $field ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			update_post_meta( $post_id, $meta, $value );
		}
		$url = isset( $_POST['ash_website'] ) ? esc_url_raw( wp_unslash( $_POST['ash_website'] ) ) : '';
		update_post_meta( $post_id, '_ash_website', $url );

		$color = isset( $_POST['ash_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['ash_color'] ) ) : '';
		update_post_meta( $post_id, '_ash_color', $color ? $color : '' );
	}

	/* ---- Category color term meta ---- */

	public static function term_add_field() {
		wp_nonce_field( 'ash_term_meta', 'ash_term_meta_nonce' );
		?>
		<div class="form-field">
			<label for="ash_term_color"><?php esc_html_e( 'Category color', 'ashford-events' ); ?></label>
			<input type="text" id="ash_term_color" name="ash_term_color" class="ash-color-field" value="">
			<p><?php esc_html_e( 'Default accent color for events in this category.', 'ashford-events' ); ?></p>
		</div>
		<?php
	}

	public static function term_edit_field( $term ) {
		wp_nonce_field( 'ash_term_meta', 'ash_term_meta_nonce' );
		$color = get_term_meta( $term->term_id, 'ash_color', true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="ash_term_color"><?php esc_html_e( 'Category color', 'ashford-events' ); ?></label></th>
			<td>
				<input type="text" id="ash_term_color" name="ash_term_color" class="ash-color-field" value="<?php echo esc_attr( $color ); ?>">
				<p class="description"><?php esc_html_e( 'Default accent color for events in this category.', 'ashford-events' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function term_save( $term_id ) {
		if ( ! isset( $_POST['ash_term_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ash_term_meta_nonce'], 'ash_term_meta' ) ) {
			return;
		}
		$color = isset( $_POST['ash_term_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['ash_term_color'] ) ) : '';
		if ( $color ) {
			update_term_meta( $term_id, 'ash_color', $color );
		} else {
			delete_term_meta( $term_id, 'ash_color' );
		}
	}
}
