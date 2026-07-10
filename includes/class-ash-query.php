<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ash_Events_Query {

	/**
	 * Get events between two dates (inclusive), ordered by date then time.
	 *
	 * @param string $from     Y-m-d
	 * @param string $to       Y-m-d
	 * @param string $category Optional category slug.
	 * @return WP_Post[]
	 */
	public static function between( $from, $to, $category = '' ) {
		$args = array(
			'post_type'      => 'ash_event',
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => '_ash_start_date',
					'value'   => array( $from, $to ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
			'meta_key' => '_ash_start_date',
			'orderby'  => 'meta_value',
			'order'    => 'ASC',
		);
		if ( $category ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'ash_event_cat',
					'field'    => 'slug',
					'terms'    => array_map( 'trim', explode( ',', $category ) ),
				),
			);
		}
		$posts = get_posts( $args );

		// Secondary sort by start time (empty/TBD times sort last within a day).
		usort( $posts, function ( $a, $b ) {
			$ad = get_post_meta( $a->ID, '_ash_start_date', true );
			$bd = get_post_meta( $b->ID, '_ash_start_date', true );
			if ( $ad !== $bd ) {
				return strcmp( $ad, $bd );
			}
			$at = get_post_meta( $a->ID, '_ash_start_time', true );
			$bt = get_post_meta( $b->ID, '_ash_start_time', true );
			if ( $at === $bt ) {
				return 0;
			}
			if ( '' === $at ) return 1;
			if ( '' === $bt ) return -1;
			return strcmp( $at, $bt );
		} );

		return $posts;
	}

	/**
	 * Group events by their Y-m-d start date.
	 *
	 * @param WP_Post[] $posts
	 * @return array<string, WP_Post[]>
	 */
	public static function group_by_date( $posts ) {
		$grouped = array();
		foreach ( $posts as $post ) {
			$date = get_post_meta( $post->ID, '_ash_start_date', true );
			if ( ! $date ) {
				continue;
			}
			$grouped[ $date ][] = $post;
		}
		return $grouped;
	}
}
