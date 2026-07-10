<?php
/**
 * Plugin Name:       Ashford Events
 * Plugin URI:        https://ashfordcreative.com
 * Description:       Lightweight events calendar with month/list views, per-event colors and labels, single event pages, CSV import, iCal feeds, and one-click migration from The Events Calendar.
 * Version:           1.3.6
 * Author:            Ashford Creative
 * License:           GPL-2.0-or-later
 * Text Domain:       ashford-events
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASH_EVENTS_VERSION', '1.3.6' );
define( 'ASH_EVENTS_FILE', __FILE__ );
define( 'ASH_EVENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASH_EVENTS_URL', plugin_dir_url( __FILE__ ) );

require_once ASH_EVENTS_DIR . 'includes/class-ash-cpt.php';
require_once ASH_EVENTS_DIR . 'includes/class-ash-meta.php';
require_once ASH_EVENTS_DIR . 'includes/class-ash-query.php';
require_once ASH_EVENTS_DIR . 'includes/class-ash-views.php';
require_once ASH_EVENTS_DIR . 'includes/class-ash-single.php';
require_once ASH_EVENTS_DIR . 'includes/class-ash-ical.php';
require_once ASH_EVENTS_DIR . 'includes/class-ash-importer.php';
require_once ASH_EVENTS_DIR . 'includes/class-ash-migrate.php';

Ash_Events_CPT::init();
Ash_Events_Meta::init();
Ash_Events_Views::init();
Ash_Events_Single::init();
Ash_Events_Ical::init();
Ash_Events_Importer::init();
Ash_Events_Migrate::init();

/*
 * GitHub-powered updates.
 *
 * Sites check the GitHub repo for new releases and surface them as normal
 * WordPress plugin updates (visible in wp-admin and ManageWP).
 *
 * Set the repo below (or define ASH_EVENTS_GITHUB_REPO in wp-config.php).
 * For a private repo, define ASH_EVENTS_GITHUB_TOKEN with a read-only
 * fine-grained personal access token.
 */
if ( file_exists( ASH_EVENTS_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once ASH_EVENTS_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

	$ash_events_repo = defined( 'ASH_EVENTS_GITHUB_REPO' )
		? ASH_EVENTS_GITHUB_REPO
		: 'https://github.com/ashfordcreative/ashfordevents/';

	$ash_events_updates = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		$ash_events_repo,
		ASH_EVENTS_FILE,
		'ashford-events'
	);
	$ash_events_updates->getVcsApi()->enableReleaseAssets();

	if ( defined( 'ASH_EVENTS_GITHUB_TOKEN' ) && ASH_EVENTS_GITHUB_TOKEN ) {
		$ash_events_updates->setAuthentication( ASH_EVENTS_GITHUB_TOKEN );
	}
}

/**
 * Resolve the accent color for an event.
 * Priority: event meta -> first category term meta -> plugin default.
 */
function ash_events_color( $post_id ) {
	$color = get_post_meta( $post_id, '_ash_color', true );
	if ( $color ) {
		return $color;
	}
	$terms = get_the_terms( $post_id, 'ash_event_cat' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$term_color = get_term_meta( $term->term_id, 'ash_color', true );
			if ( $term_color ) {
				return $term_color;
			}
		}
	}
	return get_option( 'ash_events_default_color', '#C9A353' );
}

/**
 * Resolve the display label for an event (e.g. "Daily Tournament").
 * Priority: event meta -> first category name.
 */
function ash_events_label( $post_id ) {
	$label = get_post_meta( $post_id, '_ash_label', true );
	if ( $label ) {
		return $label;
	}
	$terms = get_the_terms( $post_id, 'ash_event_cat' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		return $terms[0]->name;
	}
	return '';
}

/**
 * Pick a readable text color (near-black or white) for a given background hex.
 * Used on filled event cards so dark category colors stay legible.
 */
function ash_events_text_on( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return '#1B1B1B';
	}
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );
	$luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
	return $luminance > 0.55 ? '#1B1B1B' : '#FFFFFF';
}

/**
 * Resolve text color for an event card / CTA.
 * Priority: first category ash_text_color -> auto contrast from background.
 */
function ash_events_text_color( $post_id ) {
	$terms = get_the_terms( $post_id, 'ash_event_cat' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$text = get_term_meta( $term->term_id, 'ash_text_color', true );
			if ( $text ) {
				return $text;
			}
		}
	}
	return ash_events_text_on( ash_events_color( $post_id ) );
}

register_activation_hook( __FILE__, function () {
	Ash_Events_CPT::register();
	flush_rewrite_rules();
	update_option( 'ash_events_rewrite_version', ASH_EVENTS_VERSION );
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/**
 * Re-flush rewrite rules after plugin updates.
 * Zip/GitHub updates often skip the activation hook, which leaves /event/ URLs 404ing.
 */
add_action( 'init', function () {
	if ( get_option( 'ash_events_rewrite_version' ) === ASH_EVENTS_VERSION ) {
		return;
	}
	Ash_Events_CPT::register();
	flush_rewrite_rules( false );
	update_option( 'ash_events_rewrite_version', ASH_EVENTS_VERSION );
}, 20 );
