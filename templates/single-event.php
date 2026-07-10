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
	$ash_text    = ash_events_text_color( $ash_id );
	$ash_label   = ash_events_label( $ash_id );
	$ash_date    = get_post_meta( $ash_id, '_ash_start_date', true );
	$ash_website = get_post_meta( $ash_id, '_ash_website', true );
	$ash_terms   = get_the_terms( $ash_id, 'ash_event_cat' );
	$ash_ics     = add_query_arg( array( 'ash_ical' => '1', 'event' => $ash_id ), home_url( '/' ) );
	$ash_google  = Ash_Events_Single::google_link( $ash_id );
	$ash_related = Ash_Events_Single::related( $ash_id );
	$ash_has_img = has_post_thumbnail();
	$ash_content = trim( (string) get_the_content() );

	// Avoid repeating the category name as both a chip and body text.
	$ash_term_names = ( $ash_terms && ! is_wp_error( $ash_terms ) )
		? wp_list_pluck( $ash_terms, 'name' )
		: array();
	$ash_show_label = $ash_label && ! in_array( $ash_label, $ash_term_names, true );
	?>
	<main class="ash-single-page<?php echo $ash_has_img ? ' has-image' : ''; ?>" style="--ash-ev:<?php echo esc_attr( $ash_color ); ?>;--ash-ev-text:<?php echo esc_attr( $ash_text ); ?>">
		<div class="ash-single-page__inner">

			<div class="ash-single-page__layout">
				<div class="ash-single-page__main">
					<a class="ash-single-page__back" href="<?php echo esc_url( Ash_Events_Single::calendar_url() ); ?>">&laquo; <?php esc_html_e( 'All Events', 'ashford-events' ); ?></a>

					<?php if ( ! empty( $ash_term_names ) ) : ?>
						<div class="ash-single-page__chips">
							<span class="ash-single-page__chip"><?php echo esc_html( $ash_term_names[0] ); ?></span>
						</div>
					<?php endif; ?>

					<h1 class="ash-single-page__title"><?php the_title(); ?></h1>

					<?php echo Ash_Events_Single::render_amenities( $ash_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

					<?php if ( $ash_has_img ) : ?>
						<figure class="ash-single-page__image ash-single-page__image--mobile">
							<?php the_post_thumbnail( 'large' ); ?>
						</figure>
					<?php endif; ?>

					<?php if ( $ash_content ) : ?>
						<div class="ash-single-page__content"><?php the_content(); ?></div>
					<?php elseif ( $ash_show_label ) : ?>
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
				</div>

				<?php if ( $ash_has_img ) : ?>
					<aside class="ash-single-page__aside" aria-hidden="true">
						<figure class="ash-single-page__image ash-single-page__image--desktop">
							<?php the_post_thumbnail( 'large' ); ?>
						</figure>
					</aside>
				<?php endif; ?>
			</div>

			<?php if ( $ash_related ) : ?>
				<section class="ash-single-page__related">
					<h2 class="ash-single-page__related-heading"><?php esc_html_e( 'Related Events', 'ashford-events' ); ?></h2>
					<div class="ash-single-page__related-grid">
						<?php foreach ( $ash_related as $ash_rel ) :
							$rel_date = get_post_meta( $ash_rel->ID, '_ash_start_date', true );
							$rel_time = get_post_meta( $ash_rel->ID, '_ash_start_time', true );
							?>
							<a class="ash-single-page__related-card" href="<?php echo esc_url( get_permalink( $ash_rel ) ); ?>">
								<span class="ash-single-page__related-img"><?php echo get_the_post_thumbnail( $ash_rel, 'medium_large', array( 'loading' => 'lazy' ) ); ?></span>
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
