<?php
/**
 * Testimonials Carousel Template.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="lccl-testimonials-wrapper">
	<div class="lccl-testimonials-carousel" data-lccl-testimonials>
		<div class="lccl-testimonials-track">
			<?php foreach ( $active_testimonials as $t ) : ?>
				<div class="lccl-testimonial-slide">
					<div class="lccl-testimonial-card-inner">
						<div class="lccl-t-header-block"></div>
						
						<div class="lccl-t-avatar-wrapper">
							<?php if ( ! empty( $t['photo_url'] ) ) : ?>
								<img src="<?php echo esc_url( $t['photo_url'] ); ?>" alt="<?php echo esc_attr( $t['name'] ); ?>" class="lccl-t-avatar">
							<?php else : ?>
								<div class="lccl-t-avatar lccl-t-avatar-placeholder">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
										<circle cx="12" cy="7" r="4"></circle>
									</svg>
								</div>
							<?php endif; ?>
						</div>

						<div class="lccl-t-author-info">
							<h4 class="lccl-t-name"><?php echo esc_html( $t['name'] ); ?></h4>
							<?php if ( ! empty( $t['date'] ) ) : ?>
								<span class="lccl-t-date"><?php echo esc_html( $t['date'] ); ?></span>
							<?php endif; ?>
						</div>

						<div class="lccl-t-content">
							<?php if ( ! empty( $t['role'] ) ) : ?>
								<h5 class="lccl-t-role-title"><?php echo esc_html( $t['role'] ); ?></h5>
							<?php endif; ?>
							
							<div class="lccl-t-quote-container">
								<p class="lccl-t-quote-text" data-full-quote="<?php echo esc_attr( $t['quote'] ); ?>">
									<?php echo nl2br( esc_html( $t['quote'] ) ); ?>
								</p>
								<button type="button" class="lccl-t-read-more" style="display: none;"><?php esc_html_e( 'Read more', 'lccl-de' ); ?></button>
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<!-- Testimonial Modal -->
<div class="lccl-t-modal-overlay" style="display: none;">
	<div class="lccl-t-modal">
		<button type="button" class="lccl-t-modal-close">&times;</button>
		<div class="lccl-t-modal-content"></div>
	</div>
</div>
