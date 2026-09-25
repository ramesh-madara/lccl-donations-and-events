<?php
/**
 * Projects configuration in wp-admin.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array  $projects Current projects.
 * @var string $message Flash code ('saved'|'error').
 * @var string $error   Flash error message.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $projects ) || ! is_array( $projects ) ) {
	$projects = LCCL_DE_Settings::get_projects();
}
?>
<nav class="lccl-prog__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'lccl-de' ); ?>">
	<span class="lccl-prog__crumbs-current"><?php esc_html_e( 'Projects', 'lccl-de' ); ?></span>
</nav>

<?php if ( 'saved' === $message ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Projects saved successfully. The project list on the form has been updated.', 'lccl-de' ); ?></p>
	</div>
<?php elseif ( 'error' === $message && ! empty( $error ) ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><?php echo esc_html( $error ); ?></p>
	</div>
<?php endif; ?>

<div class="lccl-prog__section">
	<div style="margin-bottom: 20px;">
		<h2 class="lccl-prog__panel-title" style="margin-bottom: 6px;"><?php esc_html_e( 'Manage Projects', 'lccl-de' ); ?></h2>
		<p class="lccl-prog__hint" style="margin: 0; max-width: 820px; line-height: 1.5; color: #475569;">
			<?php esc_html_e( 'Add, edit, or remove projects. Set a financial goal and toggle their visibility on the donation form. Projects that meet their financial goal will automatically display as Completed on the form.', 'lccl-de' ); ?>
		</p>
	</div>

	<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 22px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
		<form method="post" action="<?php echo esc_url( LCCL_DE_Admin_Programs::projects_tab_url() ); ?>" id="lccl-projects-form">
			<?php wp_nonce_field( 'lccl_de_projects_save', 'lccl_de_projects_nonce' ); ?>
			<input type="hidden" name="lccl_de_projects_save" value="1">

			<div style="overflow-x: auto;">
				<table class="widefat striped" style="margin-bottom: 20px; border: 1px solid #e2e8f0;">
					<thead>
						<tr style="background: #f8fafc;">
							<th style="padding: 10px; font-weight: 600; width: 15%;"><?php esc_html_e( 'Project ID', 'lccl-de' ); ?></th>
							<th style="padding: 10px; font-weight: 600; width: 35%;"><?php esc_html_e( 'Title / Name', 'lccl-de' ); ?></th>
							<th style="padding: 10px; font-weight: 600; width: 15%;"><?php esc_html_e( 'Goal (LKR)', 'lccl-de' ); ?></th>
							<th style="padding: 10px; font-weight: 600; width: 15%;"><?php esc_html_e( 'Raised (LKR)', 'lccl-de' ); ?></th>
							<th style="padding: 10px; font-weight: 600; width: 10%; text-align: center;"><?php esc_html_e( 'Enabled', 'lccl-de' ); ?></th>
							<th style="padding: 10px; font-weight: 600; width: 10%; text-align: center;"><?php esc_html_e( 'Actions', 'lccl-de' ); ?></th>
						</tr>
					</thead>
					<tbody id="lccl-projects-list">
						<?php
						global $wpdb;
						$table = LCCL_DE_Schema::sponsorships_table();
						foreach ( $projects as $id => $p ) :
							// Calculate raised amount for this project
							$raised = 0.0;
							if ( $table ) {
								$raised = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
									$wpdb->prepare(
										"SELECT SUM(amount_lkr) FROM `{$table}` WHERE project = %s AND status = 'paid'",
										$id
									)
								);
							}
							
							$goal = isset( $p['financial_goal'] ) ? (float) $p['financial_goal'] : 0.0;
							$progress = $goal > 0 ? min( 100, round( ( $raised / $goal ) * 100, 1 ) ) : 0;
						?>
							<tr>
								<td style="padding: 10px;">
									<input type="text" name="projects[<?php echo esc_attr( $id ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" class="regular-text" style="width: 100%; font-family: monospace; font-size: 12px; background: #f8fafc;" readonly>
								</td>
								<td style="padding: 10px;">
									<input type="text" name="projects[<?php echo esc_attr( $id ); ?>][title]" value="<?php echo esc_attr( $p['title'] ); ?>" class="regular-text" style="width: 100%;" required>
								</td>
								<td style="padding: 10px;">
									<input type="number" step="0.01" min="0" name="projects[<?php echo esc_attr( $id ); ?>][financial_goal]" value="<?php echo esc_attr( $goal ); ?>" class="regular-text" style="width: 100%;" required>
								</td>
								<td style="padding: 10px; font-family: monospace;">
									<?php echo esc_html( number_format( $raised, 2 ) ); ?>
									<div style="width: 100%; background-color: #e2e8f0; height: 4px; margin-top: 6px; border-radius: 2px; overflow: hidden;">
										<div style="width: <?php echo esc_attr( $progress ); ?>%; background-color: <?php echo $progress >= 100 ? '#10b981' : '#3b82f6'; ?>; height: 100%;"></div>
									</div>
									<div style="font-size: 10px; color: #64748b; margin-top: 2px; text-align: right;">
										<?php echo esc_html( $progress ); ?>%
									</div>
								</td>
								<td style="padding: 10px; text-align: center;">
									<input type="checkbox" name="projects[<?php echo esc_attr( $id ); ?>][enabled]" value="1" <?php checked( 1, isset( $p['enabled'] ) ? $p['enabled'] : 1 ); ?>>
								</td>
								<td style="padding: 10px; text-align: center;">
									<button type="button" class="button lccl-remove-project" style="color: #dc2626; border-color: #dc2626;"><?php esc_html_e( 'Remove', 'lccl-de' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div style="display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid #f1f5f9;">
				<button type="button" id="lccl-add-project" class="button button-secondary">
					<?php esc_html_e( '+ Add New Project', 'lccl-de' ); ?>
				</button>
				<button type="submit" class="button button-primary button-large" style="background: #002b49; border-color: #002b49; font-weight: 600; padding: 0 20px;">
					<?php esc_html_e( 'Save Projects', 'lccl-de' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>

<script>
(function() {
	var list = document.getElementById('lccl-projects-list');
	var addBtn = document.getElementById('lccl-add-project');
	if (!list || !addBtn) return;

	addBtn.addEventListener('click', function(e) {
		e.preventDefault();
		var id = 'proj_' + Math.random().toString(36).substring(2, 8);
		var tr = document.createElement('tr');
		tr.innerHTML = `
			<td style="padding: 10px;">
				<input type="text" name="projects[${id}][id]" value="${id}" class="regular-text" style="width: 100%; font-family: monospace; font-size: 12px; background: #f8fafc;" readonly>
			</td>
			<td style="padding: 10px;">
				<input type="text" name="projects[${id}][title]" value="" class="regular-text" style="width: 100%;" placeholder="New Project Name" required>
			</td>
			<td style="padding: 10px;">
				<input type="number" step="0.01" min="0" name="projects[${id}][financial_goal]" value="0.00" class="regular-text" style="width: 100%;" required>
			</td>
			<td style="padding: 10px; font-family: monospace; color: #94a3b8;">
				0.00
			</td>
			<td style="padding: 10px; text-align: center;">
				<input type="checkbox" name="projects[${id}][enabled]" value="1" checked>
			</td>
			<td style="padding: 10px; text-align: center;">
				<button type="button" class="button lccl-remove-project" style="color: #dc2626; border-color: #dc2626;">Remove</button>
			</td>
		`;
		list.appendChild(tr);
	});

	list.addEventListener('click', function(e) {
		if (e.target.classList.contains('lccl-remove-project')) {
			e.preventDefault();
			if (confirm('<?php echo esc_js( __( 'Are you sure you want to remove this project?', 'lccl-de' ) ); ?>')) {
				var tr = e.target.closest('tr');
				if (tr) tr.remove();
			}
		}
	});
})();
</script>
