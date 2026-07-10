<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ash_Events_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'manage_ash_event_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_ash_event_posts_custom_column', array( __CLASS__, 'admin_column_content' ), 10, 2 );
		add_filter( 'manage_edit-ash_event_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort_admin_list' ) );
	}

	public static function register() {
		register_post_type( 'ash_event', array(
			'labels' => array(
				'name'          => __( 'Events', 'ashford-events' ),
				'singular_name' => __( 'Event', 'ashford-events' ),
				'add_new_item'  => __( 'Add New Event', 'ashford-events' ),
				'edit_item'     => __( 'Edit Event', 'ashford-events' ),
				'all_items'     => __( 'All Events', 'ashford-events' ),
			),
			'public'       => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'menu_position'=> 21,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'event', 'with_front' => false ),
			'show_in_rest' => true,
		) );

		register_taxonomy( 'ash_event_cat', 'ash_event', array(
			'labels' => array(
				'name'          => __( 'Event Categories', 'ashford-events' ),
				'singular_name' => __( 'Event Category', 'ashford-events' ),
			),
			'hierarchical' => true,
			'public'       => true,
			'rewrite'      => array( 'slug' => 'event-category', 'with_front' => false ),
			'show_in_rest' => true,
		) );
	}

	public static function admin_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['ash_when']  = __( 'When', 'ashford-events' );
				$new['ash_label'] = __( 'Label', 'ashford-events' );
				$new['ash_color'] = __( 'Color', 'ashford-events' );
			}
		}
		return $new;
	}

	public static function admin_column_content( $column, $post_id ) {
		if ( 'ash_when' === $column ) {
			$date = get_post_meta( $post_id, '_ash_start_date', true );
			$time = get_post_meta( $post_id, '_ash_start_time', true );
			if ( $date ) {
				$ts = strtotime( $date );
				echo esc_html( date_i18n( 'D, M j, Y', $ts ) );
				echo $time ? ' @ ' . esc_html( date_i18n( 'g:i a', strtotime( $date . ' ' . $time ) ) ) : ' <em>(' . esc_html__( 'time TBD', 'ashford-events' ) . ')</em>';
			} else {
				echo '—';
			}
		}
		if ( 'ash_label' === $column ) {
			echo esc_html( ash_events_label( $post_id ) );
		}
		if ( 'ash_color' === $column ) {
			$color = ash_events_color( $post_id );
			echo '<span style="display:inline-block;width:16px;height:16px;border-radius:50%;vertical-align:middle;background:' . esc_attr( $color ) . '"></span> <code>' . esc_html( $color ) . '</code>';
		}
	}

	public static function sortable_columns( $columns ) {
		$columns['ash_when'] = 'ash_when';
		return $columns;
	}

	public static function sort_admin_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'ash_event' !== $query->get( 'post_type' ) ) {
			return;
		}
		$orderby = $query->get( 'orderby' );
		if ( 'ash_when' === $orderby || '' === $orderby ) {
			$query->set( 'meta_key', '_ash_start_date' );
			$query->set( 'orderby', 'meta_value' );
			if ( '' === $orderby ) {
				$query->set( 'order', 'ASC' );
				// Only show upcoming first by default ordering; keep it simple: date asc.
			}
		}
	}
}
