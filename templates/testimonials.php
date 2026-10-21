<?php
/**
 * Testimonials Carousel Template.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $design ) ) {
	$design = get_option('lccl_de_testimonial_design', 'design-1');
}
if ( ! isset( $scroll ) ) {
	$scroll = get_option('lccl_de_testimonial_scroll', 'scroll-snap');
}
if ( ! isset( $title ) ) {
	$title = __( 'TESTIMONIALS', 'lccl-de' );
}
if ( ! isset( $subtitle ) ) {
	$subtitle = __( 'Words of Appreciation and Recognition', 'lccl-de' );
}
?>

<div class="lccl-testimonials-wrapper lccl-<?php echo esc_attr( $design ); ?> lccl-<?php echo esc_attr( $scroll ); ?>">
	<?php if ( ! empty( $title ) || ! empty( $subtitle ) ) : ?>
		<div class="lccl-testimonials-header">
			<?php if ( ! empty( $title ) ) : ?>
				<h2 class="lccl-t-section-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( ! empty( $subtitle ) ) : ?>
				<p class="lccl-t-section-subtitle"><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="lccl-testimonials-carousel" data-lccl-testimonials>
		<div class="lccl-testimonials-track">
			<?php foreach ( $active_testimonials as $index => $t ) : ?>
				<?php
				$formatted_date = '';
				if ( ! empty( $t['date'] ) ) {
					$time = strtotime( $t['date'] );
					$formatted_date = $time ? ( function_exists( 'wp_date' ) ? wp_date( 'jS F Y', $time ) : date( 'jS F Y', $time ) ) : $t['date'];
				}
				?>
				<div class="lccl-testimonial-slide" data-index="<?php echo esc_attr( $index ); ?>">
					<div class="lccl-testimonial-card-inner">
						<?php if ( $design === 'design-1' ) : ?>
							<!-- Design 1: Gold Header & Centered -->
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
								<?php if ( ! empty( $t['role'] ) ) : ?>
									<h5 class="lccl-t-role-title"><?php echo esc_html( $t['role'] ); ?></h5>
								<?php endif; ?>
							</div>

							<div class="lccl-t-content">
								<?php if ( ! empty( $formatted_date ) ) : ?>
									<div class="lccl-t-date"><?php echo esc_html( $formatted_date ); ?></div>
								<?php endif; ?>
								<div class="lccl-t-quote-container" data-author-name="<?php echo esc_attr( $t['name'] ); ?>" data-author-role="<?php echo esc_attr( $t['role'] ?? '' ); ?>" data-author-date="<?php echo esc_attr( $formatted_date ); ?>">
									<p class="lccl-t-quote-text" data-full-quote="<?php echo esc_attr( $t['quote'] ); ?>">
										<?php echo nl2br( esc_html( $t['quote'] ) ); ?>
									</p>
									<button type="button" class="lccl-t-read-more" style="display: none;"><?php esc_html_e( 'Read more', 'lccl-de' ); ?></button>
								</div>
							</div>
							
						<?php else : ?>
							<!-- Design 2: Minimal Left Aligned -->
							<div class="lccl-t-quote-icon">“</div>
							
							<div class="lccl-t-content">
								<?php if ( ! empty( $formatted_date ) ) : ?>
									<div class="lccl-t-date"><?php echo esc_html( $formatted_date ); ?></div>
								<?php endif; ?>
								<div class="lccl-t-quote-container" data-author-name="<?php echo esc_attr( $t['name'] ); ?>" data-author-role="<?php echo esc_attr( $t['role'] ?? '' ); ?>" data-author-date="<?php echo esc_attr( $formatted_date ); ?>">
									<p class="lccl-t-quote-text" data-full-quote="<?php echo esc_attr( $t['quote'] ); ?>">
										<?php echo nl2br( esc_html( $t['quote'] ) ); ?>
									</p>
									<button type="button" class="lccl-t-read-more" style="display: none;"><?php esc_html_e( 'Read more', 'lccl-de' ); ?></button>
								</div>
							</div>
							
							<div class="lccl-t-author-block-bottom">
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
									<?php if ( ! empty( $t['role'] ) ) : ?>
										<h5 class="lccl-t-role-title"><?php echo esc_html( $t['role'] ); ?></h5>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		
		<button type="button" class="lccl-t-nav lccl-t-nav-prev" aria-label="Previous testimonial">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
		</button>
		<button type="button" class="lccl-t-nav lccl-t-nav-next" aria-label="Next testimonial">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
		</button>
	</div>

	<?php if ( ! empty( $active_testimonials ) && count( $active_testimonials ) > 1 ) : ?>
		<div class="lccl-testimonials-dots" role="tablist" aria-label="<?php esc_attr_e( 'Testimonials pagination', 'lccl-de' ); ?>">
			<?php foreach ( $active_testimonials as $index => $t ) : ?>
				<button type="button" class="lccl-t-dot<?php echo $index === 0 ? ' is-active' : ''; ?>" data-slide-index="<?php echo esc_attr( $index ); ?>" role="tab" aria-label="<?php echo esc_attr( sprintf( __( 'Testimonial %d', 'lccl-de' ), $index + 1 ) ); ?>"></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<!-- Testimonial Modal -->
<div class="lccl-t-modal-overlay" style="display: none;">
	<div class="lccl-t-modal">
		<button type="button" class="lccl-t-modal-close">&times;</button>
		<div class="lccl-t-modal-content"></div>
	</div>
</div>
