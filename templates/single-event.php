<?php
/**
 * Single event template (Ashford Events).
 *
 * Overridable: copy this file to your theme as single-ash_event.php,
 * or disable with add_filter( 'ash_events_use_template', '__return_false' ).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

while ( have_posts() ) :
	the_post();

	$ash_id      = get_the_ID();
	$ash_color   = ash_events_color( $ash_id );
	$ash_text    = ash_events_text_on( $ash_color );
	$ash_label   = ash_events_label( $ash_id );
	$ash_date    = get_post_meta( $ash_id, '_ash_start_date', true );
	$ash_time    = get_post_meta( $ash_id, '_ash_start_time', true );
	$ash_buyin   = get_post_meta( $ash_id, '_ash_buyin', true );
	$ash_rebuys  = get_post_meta( $ash_id, '_ash_rebuys', true );
	$ash_gtd     = get_post_meta( $ash_id, '_ash_guarantee', true );
	$ash_website = get_post_meta( $ash_id, '_ash_website', true );
	$ash_terms   = get_the_terms( $ash_id, 'ash_event_cat' );
	$ash_ics     = add_query_arg( array( 'ash_ical' => '1', 'event' => $ash_id ), home_url( '/' ) );
	$ash_google  = Ash_Events_Single::google_link( $ash_id );
	$ash_related = Ash_Events_Single::related( $ash_id );
	?>
	<main class="ash-single-page" style="--ash-ev:<?php echo esc_attr( $ash_color ); ?>;--ash-ev-text:<?php echo esc_attr( $ash_text ); ?>">
		<div class="ash-single-page__inner">

			<a class="ash-single-page__back" href="<?php echo esc_url( Ash_Events_Single::calendar_url() ); ?>">&laquo; <?php esc_html_e( 'All Events', 'ashford-events' ); ?></a>

			<h1 class="ash-single-page__title"><?php the_title(); ?></h1>

			<?php if ( $ash_date ) : ?>
				<p class="ash-single-page__when"><?php echo esc_html( Ash_Events_Single::format_when_short( $ash_date, $ash_time ) ); ?></p>
			<?php endif; ?>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="ash-single-page__image">
					<?php the_post_thumbnail( 'large' ); ?>
				</figure>
			<?php endif; ?>

			<?php if ( get_the_content() ) : ?>
				<div class="ash-single-page__content"><?php the_content(); ?></div>
			<?php elseif ( $ash_label ) : ?>
				<p class="ash-single-page__content"><?php echo esc_html( $ash_label ); ?></p>
			<?php endif; ?>

			<div class="ash-single-page__actions">
				<?php if ( $ash_website ) : ?>
					<a class="ash-single-page__cta" href="<?php echo esc_url( $ash_website ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Event Details & Registration', 'ashford-events' ); ?></a>
				<?php endif; ?>

				<?php if ( $ash_date ) : ?>
					<details class="ash-single-page__atc">
						<summary>
							<?php esc_html_e( 'Add to Calendar', 'ashford-events' ); ?>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
						</summary>
						<div class="ash-single-page__atc-menu">
							<?php if ( $ash_google ) : ?>
								<a href="<?php echo esc_url( $ash_google ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Google Calendar', 'ashford-events' ); ?></a>
							<?php endif; ?>
							<a href="<?php echo esc_url( $ash_ics ); ?>" rel="nofollow"><?php esc_html_e( 'Apple / Outlook (.ics)', 'ashford-events' ); ?></a>
						</div>
					</details>
				<?php endif; ?>
			</div>

			<hr class="ash-single-page__rule">

			<section class="ash-single-page__details">
				<h2 class="ash-single-page__details-heading"><?php esc_html_e( 'Details', 'ashford-events' ); ?></h2>
				<dl class="ash-single-page__dl">
					<?php if ( $ash_date ) : ?>
						<dt><?php esc_html_e( 'Date:', 'ashford-events' ); ?></dt>
						<dd><?php echo esc_html( date_i18n( gmdate( 'Y', strtotime( $ash_date ) ) === current_time( 'Y' ) ? 'F j' : 'F j, Y', strtotime( $ash_date ) ) ); ?></dd>
					<?php endif; ?>
					<dt><?php esc_html_e( 'Time:', 'ashford-events' ); ?></dt>
					<dd><?php echo $ash_time ? esc_html( date_i18n( 'g:i a', strtotime( $ash_date . ' ' . $ash_time ) ) ) : esc_html__( 'TBD', 'ashford-events' ); ?></dd>
					<?php if ( $ash_terms && ! is_wp_error( $ash_terms ) ) : ?>
						<dt><?php esc_html_e( 'Event Category:', 'ashford-events' ); ?></dt>
						<dd><?php echo esc_html( implode( ', ', wp_list_pluck( $ash_terms, 'name' ) ) ); ?></dd>
					<?php endif; ?>
					<?php if ( $ash_buyin ) : ?>
						<dt><?php esc_html_e( 'Buy-In / Stack:', 'ashford-events' ); ?></dt>
						<dd><?php echo esc_html( $ash_buyin ); ?></dd>
					<?php endif; ?>
					<?php if ( $ash_gtd ) : ?>
						<dt><?php esc_html_e( 'Guarantee:', 'ashford-events' ); ?></dt>
						<dd><?php echo esc_html( $ash_gtd ); ?></dd>
					<?php endif; ?>
					<?php if ( $ash_rebuys ) : ?>
						<dt><?php esc_html_e( 'Rebuys / Add-Ons / Levels:', 'ashford-events' ); ?></dt>
						<dd><?php echo esc_html( $ash_rebuys ); ?></dd>
					<?php endif; ?>
					<?php if ( $ash_website ) : ?>
						<dt><?php esc_html_e( 'Website:', 'ashford-events' ); ?></dt>
						<dd><a href="<?php echo esc_url( $ash_website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $ash_website, PHP_URL_HOST ) ); ?></a></dd>
					<?php endif; ?>
				</dl>
			</section>

			<?php if ( $ash_related ) : ?>
				<hr class="ash-single-page__rule">
				<section class="ash-single-page__related">
					<h2 class="ash-single-page__related-heading"><?php esc_html_e( 'Related Events', 'ashford-events' ); ?></h2>
					<div class="ash-single-page__related-grid">
						<?php foreach ( $ash_related as $ash_rel ) :
							$rel_date = get_post_meta( $ash_rel->ID, '_ash_start_date', true );
							$rel_time = get_post_meta( $ash_rel->ID, '_ash_start_time', true );
							?>
							<a class="ash-single-page__related-card" href="<?php echo esc_url( get_permalink( $ash_rel ) ); ?>">
								<?php if ( has_post_thumbnail( $ash_rel ) ) : ?>
									<span class="ash-single-page__related-img"><?php echo get_the_post_thumbnail( $ash_rel, 'medium_large', array( 'loading' => 'lazy' ) ); ?></span>
								<?php endif; ?>
								<span class="ash-single-page__related-title"><?php echo esc_html( get_the_title( $ash_rel ) ); ?></span>
								<span class="ash-single-page__related-when"><?php echo esc_html( Ash_Events_Single::format_when_short( $rel_date, $rel_time ) ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

		</div>
	</main>
	<?php
endwhile;

get_footer();
