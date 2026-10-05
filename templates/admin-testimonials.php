<?php
/**
 * Admin Testimonials Tab
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_media();
?>

<div class="lccl-de-workspace lccl-de-testimonials-workspace">
	<div class="lccl-de-workspace-header">
		<h2 class="lccl-de-workspace-title"><?php esc_html_e( 'Testimonials', 'lccl-de' ); ?></h2>
		<p class="lccl-de-workspace-lede">
			<?php esc_html_e( 'Manage testimonials to display on the website.', 'lccl-de' ); ?>
		</p>
	</div>

	<div class="lccl-de-toolbar">
		<div class="lccl-de-design-selector" style="display: flex; align-items: center; gap: 10px;">
			<label for="lccl-de-testimonial-design" style="font-weight: 600;"><?php esc_html_e( 'Card Design:', 'lccl-de' ); ?></label>
			<select id="lccl-de-testimonial-design" name="lccl_de_testimonial_design">
				<?php $current_design = get_option('lccl_de_testimonial_design', 'design-1'); ?>
				<option value="design-1" <?php selected($current_design, 'design-1'); ?>><?php esc_html_e('Design 1 (Gold Header)', 'lccl-de'); ?></option>
				<option value="design-2" <?php selected($current_design, 'design-2'); ?>><?php esc_html_e('Design 2 (Minimal Left)', 'lccl-de'); ?></option>
			</select>
			<button type="button" class="button" id="lccl-de-save-design" data-nonce="<?php echo esc_attr( wp_create_nonce('lccl_de_testimonial_design') ); ?>"><?php esc_html_e( 'Save Design', 'lccl-de' ); ?></button>
			<span class="spinner" id="lccl-de-design-spinner" style="float:none; margin:0;"></span>
		</div>
	</div>

	<div class="lccl-de-design-preview-container" style="background: #fdfbf7; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
		<h3 style="margin-top:0;"><?php esc_html_e('Design Preview', 'lccl-de'); ?></h3>
		<div id="lccl-de-design-preview" style="width: 100%; max-width: 400px; margin: 0 auto;">
			<!-- Preview is populated by JS -->
		</div>
	</div>

	<div style="margin-bottom: 20px;">
		<button type="button" class="lccl-de-btn lccl-de-btn--primary" id="lccl-de-add-testimonial">
			<span class="dashicons dashicons-plus"></span>
			<?php esc_html_e( 'Add Testimonial', 'lccl-de' ); ?>
		</button>
	</div>

	<div class="lccl-de-testimonials-grid">
		
		<!-- Active Column -->
		<div class="lccl-de-column lccl-de-column--active">
			<div class="lccl-de-column-header">
				<h3>
					<?php esc_html_e( 'Active Testimonials', 'lccl-de' ); ?>
					<span class="lccl-de-badge lccl-de-badge--count" id="lccl-de-active-count"><?php echo esc_html( count( $active ) ); ?></span>
				</h3>
				<p class="description"><?php esc_html_e( 'Drag to reorder. These are shown on the website.', 'lccl-de' ); ?></p>
			</div>
			
			<div class="lccl-de-testimonials-list lccl-de-testimonials-list--active" id="lccl-de-active-list">
				<?php if ( empty( $active ) ) : ?>
					<div class="lccl-de-empty-state" id="lccl-de-active-empty">
						<p><?php esc_html_e( 'No active testimonials.', 'lccl-de' ); ?></p>
					</div>
				<?php endif; ?>

				<?php foreach ( $active as $t ) : ?>
					<?php include LCCL_DE_PATH . 'templates/admin-testimonial-card.php'; ?>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Inactive Column -->
		<div class="lccl-de-column lccl-de-column--inactive">
			<div class="lccl-de-column-header">
				<h3>
					<?php esc_html_e( 'Inactive Testimonials', 'lccl-de' ); ?>
					<span class="lccl-de-badge lccl-de-badge--count lccl-de-badge--neutral" id="lccl-de-inactive-count"><?php echo esc_html( count( $inactive ) ); ?></span>
				</h3>
				<p class="description"><?php esc_html_e( 'Hidden from the website.', 'lccl-de' ); ?></p>
			</div>
			
			<div class="lccl-de-testimonials-list lccl-de-testimonials-list--inactive" id="lccl-de-inactive-list">
				<?php if ( empty( $inactive ) ) : ?>
					<div class="lccl-de-empty-state" id="lccl-de-inactive-empty">
						<p><?php esc_html_e( 'No inactive testimonials.', 'lccl-de' ); ?></p>
					</div>
				<?php endif; ?>

				<?php foreach ( $inactive as $t ) : ?>
					<?php include LCCL_DE_PATH . 'templates/admin-testimonial-card.php'; ?>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>

<!-- Modal -->
<div id="lccl-de-testimonial-modal" class="lccl-de-modal" style="display: none;">
	<div class="lccl-de-modal-overlay"></div>
	<div class="lccl-de-modal-content">
		<div class="lccl-de-modal-header">
			<h3 id="lccl-de-modal-title"><?php esc_html_e( 'Add Testimonial', 'lccl-de' ); ?></h3>
			<button type="button" class="lccl-de-modal-close" aria-label="<?php esc_attr_e( 'Close', 'lccl-de' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
		</div>
		<div class="lccl-de-modal-body">
			<form id="lccl-de-testimonial-form">
				<input type="hidden" name="id" id="lccl-t-id" value="">
				<input type="hidden" name="photo_id" id="lccl-t-photo-id" value="">
				<input type="hidden" name="photo_url" id="lccl-t-photo-url" value="">
				
				<div class="lccl-de-form-row lccl-de-form-row--photo">
					<div class="lccl-de-photo-preview" id="lccl-t-photo-preview">
						<div class="lccl-de-photo-placeholder"><span class="dashicons dashicons-format-image"></span></div>
					</div>
					<div class="lccl-de-photo-actions">
						<button type="button" class="button" id="lccl-t-upload-btn"><?php esc_html_e( 'Select Photo', 'lccl-de' ); ?></button>
						<button type="button" class="button lccl-de-text-danger" id="lccl-t-remove-photo-btn" style="display: none;"><?php esc_html_e( 'Remove', 'lccl-de' ); ?></button>
						<p class="description"><?php esc_html_e( 'Upload a square image for best results.', 'lccl-de' ); ?></p>
					</div>
				</div>

				<div class="lccl-de-form-row">
					<label for="lccl-t-name"><?php esc_html_e( 'Name', 'lccl-de' ); ?> <span class="required">*</span></label>
					<input type="text" name="name" id="lccl-t-name" class="regular-text" required>
				</div>
				
				<div class="lccl-de-form-row">
					<label for="lccl-t-role"><?php esc_html_e( 'Position / Role', 'lccl-de' ); ?></label>
					<input type="text" name="role" id="lccl-t-role" class="regular-text">
				</div>

				<div class="lccl-de-form-row">
					<label><?php esc_html_e( 'Date (Optional)', 'lccl-de' ); ?></label>
					<div style="display: flex; gap: 8px;">
						<input type="number" id="lccl-t-date-day" class="small-text" placeholder="DD" min="1" max="31" style="width: 60px;">
						<select id="lccl-t-date-month">
							<option value=""><?php esc_html_e( 'Month', 'lccl-de' ); ?></option>
							<option value="January">January</option>
							<option value="February">February</option>
							<option value="March">March</option>
							<option value="April">April</option>
							<option value="May">May</option>
							<option value="June">June</option>
							<option value="July">July</option>
							<option value="August">August</option>
							<option value="September">September</option>
							<option value="October">October</option>
							<option value="November">November</option>
							<option value="December">December</option>
						</select>
						<select id="lccl-t-date-year">
							<option value=""><?php esc_html_e( 'Year', 'lccl-de' ); ?></option>
							<?php
							$current_year = (int) wp_date( 'Y' );
							for ( $y = $current_year; $y >= $current_year - 30; $y-- ) {
								echo '<option value="' . esc_attr( $y ) . '">' . esc_html( $y ) . '</option>';
							}
							?>
						</select>
					</div>
					<input type="hidden" name="date" id="lccl-t-date">
				</div>

				<div class="lccl-de-form-row">
					<label for="lccl-t-quote"><?php esc_html_e( 'Testimonial', 'lccl-de' ); ?> <span class="required">*</span></label>
					<textarea name="quote" id="lccl-t-quote" rows="4" class="large-text" required></textarea>
				</div>
			</form>
		</div>
		<div class="lccl-de-modal-footer">
			<button type="button" class="button" id="lccl-de-modal-cancel"><?php esc_html_e( 'Cancel', 'lccl-de' ); ?></button>
			<button type="button" class="button button-primary" id="lccl-de-modal-save"><?php esc_html_e( 'Save Testimonial', 'lccl-de' ); ?></button>
			<span class="spinner" id="lccl-de-modal-spinner"></span>
		</div>
	</div>
</div>
