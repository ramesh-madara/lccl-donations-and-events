<?php
/**
 * Single Testimonial Card Template
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

$t_id       = (int) $t['id'];
$is_active  = (int) $t['is_active'];
$photo_url  = ! empty( $t['photo_url'] ) ? esc_url( $t['photo_url'] ) : '';
$t_name     = esc_html( $t['name'] );
$t_role     = ! empty( $t['role'] ) ? esc_html( $t['role'] ) : '';
$t_date     = ! empty( $t['date'] ) ? esc_html( $t['date'] ) : '';
$t_quote    = esc_html( wp_trim_words( $t['quote'], 20 ) );

// We need raw data for the JS to populate the edit modal
$raw_data = array(
	'id'        => $t_id,
	'name'      => $t['name'],
	'role'      => $t['role'],
	'date'      => $t['date'],
	'quote'     => $t['quote'],
	'photo_url' => $t['photo_url'],
	'photo_id'  => $t['photo_id'],
	'is_active' => $is_active,
);
?>
<div class="lccl-de-t-card" data-id="<?php echo esc_attr( $t_id ); ?>" data-raw="<?php echo esc_attr( wp_json_encode( $raw_data ) ); ?>" <?php echo ( 1 === $is_active ) ? 'draggable="true"' : ''; ?>>
	
	<?php if ( 1 === $is_active ) : ?>
		<div class="lccl-de-t-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'lccl-de' ); ?>">
			<span class="dashicons dashicons-menu"></span>
		</div>
	<?php endif; ?>

	<div class="lccl-de-t-card-main">
		<div class="lccl-de-t-header">
			<div class="lccl-de-t-avatar">
				<?php if ( $photo_url ) : ?>
					<img src="<?php echo $photo_url; ?>" alt="">
				<?php else : ?>
					<span class="dashicons dashicons-admin-users"></span>
				<?php endif; ?>
			</div>
			<div class="lccl-de-t-info">
				<div class="lccl-de-t-name"><?php echo $t_name; ?></div>
				<?php if ( $t_role ) : ?>
					<div class="lccl-de-t-role"><?php echo $t_role; ?></div>
				<?php endif; ?>
				<?php if ( $t_date ) : ?>
					<div class="lccl-de-t-date"><?php echo $t_date; ?></div>
				<?php endif; ?>
			</div>
			
			<div class="lccl-de-t-toggle">
				<label class="lccl-de-switch" title="<?php esc_attr_e( 'Toggle Active/Inactive', 'lccl-de' ); ?>">
					<input type="checkbox" class="lccl-de-toggle-btn" value="1" <?php checked( $is_active, 1 ); ?>>
					<span class="lccl-de-slider"></span>
				</label>
			</div>
		</div>

		<div class="lccl-de-t-quote">
			"<?php echo $t_quote; ?>"
		</div>

		<div class="lccl-de-t-actions">
			<button type="button" class="button button-small lccl-de-edit-btn">
				<span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'lccl-de' ); ?>
			</button>
			<button type="button" class="button button-small button-link-delete lccl-de-delete-btn">
				<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete', 'lccl-de' ); ?>
			</button>
		</div>
	</div>
</div>
