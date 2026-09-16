<?php
/**
 * wp-admin Blood Donation Users screens.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var string        $action  list|add|edit
 * @var string        $message Flash code
 * @var string        $error   Flash error
 * @var WP_User|null  $edit    User being edited
 */

defined( 'ABSPATH' ) || exit;

$notices = array(
	'created'      => __( 'Reviewer created.', 'lccl-de' ),
	'updated'      => __( 'Reviewer updated.', 'lccl-de' ),
	'deleted'      => __( 'Reviewer deleted.', 'lccl-de' ),
	'activated'    => __( 'Reviewer activated.', 'lccl-de' ),
	'deactivated'  => __( 'Reviewer deactivated.', 'lccl-de' ),
);
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Blood Donation Users', 'lccl-de' ); ?></h1>
	<?php if ( 'add' !== $action && 'edit' !== $action ) : ?>
		<a class="page-title-action" href="<?php echo esc_url( LCCL_DE_Admin_Users::url( array( 'action' => 'add' ) ) ); ?>">
			<?php esc_html_e( 'Add reviewer', 'lccl-de' ); ?>
		</a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<p class="description">
		<?php esc_html_e( 'These accounts can open the blood donation dashboard on the site. They cannot reach wp-admin.', 'lccl-de' ); ?>
		<a href="<?php echo esc_url( LCCL_DE_Roles::dashboard_url() ); ?>">
			<?php esc_html_e( 'Open dashboard', 'lccl-de' ); ?>
		</a>
	</p>

	<?php if ( isset( $notices[ $message ] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notices[ $message ] ); ?></p></div>
	<?php endif; ?>

	<?php if ( 'error' === $message && '' !== $error ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<?php if ( 'add' === $action || 'edit' === $action ) : ?>
		<?php
		$is_edit  = 'edit' === $action && $edit instanceof WP_User;
		$disabled = $is_edit && ! LCCL_DE_Roles::is_active( $edit->ID );
		?>
		<form method="post" action="<?php echo esc_url( LCCL_DE_Admin_Users::url() ); ?>" style="max-width: 520px;">
			<?php wp_nonce_field( LCCL_DE_Admin_Users::NONCE ); ?>
			<input type="hidden" name="lccl_de_user_action" value="<?php echo $is_edit ? 'update' : 'create'; ?>">
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $edit->ID ); ?>">
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lccl-de-user-login"><?php esc_html_e( 'Username', 'lccl-de' ); ?></label></th>
					<td>
						<?php if ( $is_edit ) : ?>
							<input type="text" class="regular-text" id="lccl-de-user-login" value="<?php echo esc_attr( $edit->user_login ); ?>" disabled>
							<p class="description"><?php esc_html_e( 'Username cannot be changed.', 'lccl-de' ); ?></p>
						<?php else : ?>
							<input type="text" class="regular-text" id="lccl-de-user-login" name="user_login" required autocomplete="off">
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lccl-de-user-email"><?php esc_html_e( 'Email', 'lccl-de' ); ?></label></th>
					<td>
						<input type="email" class="regular-text" id="lccl-de-user-email" name="user_email" required value="<?php echo $is_edit ? esc_attr( $edit->user_email ) : ''; ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lccl-de-first-name"><?php esc_html_e( 'First name', 'lccl-de' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="lccl-de-first-name" name="first_name" required value="<?php echo $is_edit ? esc_attr( $edit->first_name ) : ''; ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lccl-de-last-name"><?php esc_html_e( 'Last name', 'lccl-de' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="lccl-de-last-name" name="last_name" required value="<?php echo $is_edit ? esc_attr( $edit->last_name ) : ''; ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="lccl-de-user-pass"><?php esc_html_e( 'Password', 'lccl-de' ); ?></label></th>
					<td>
						<input type="password" class="regular-text" id="lccl-de-user-pass" name="user_pass" <?php echo $is_edit ? '' : 'required'; ?> autocomplete="new-password">
						<p class="description">
							<?php
							echo $is_edit
								? esc_html__( 'Leave blank to keep the current password. Minimum 8 characters if changing.', 'lccl-de' )
								: esc_html__( 'Minimum 8 characters.', 'lccl-de' );
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'lccl-de' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="disabled" value="1" <?php checked( $disabled ); ?>>
							<?php esc_html_e( 'Inactive — cannot sign in', 'lccl-de' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php echo $is_edit ? esc_html__( 'Save reviewer', 'lccl-de' ) : esc_html__( 'Create reviewer', 'lccl-de' ); ?>
				</button>
				<a class="button" href="<?php echo esc_url( LCCL_DE_Admin_Users::url() ); ?>"><?php esc_html_e( 'Cancel', 'lccl-de' ); ?></a>
			</p>
		</form>
	<?php else : ?>
		<?php $users = LCCL_DE_Admin_Users::get_reviewers(); ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'lccl-de' ); ?></th>
					<th><?php esc_html_e( 'Username', 'lccl-de' ); ?></th>
					<th><?php esc_html_e( 'Email', 'lccl-de' ); ?></th>
					<th><?php esc_html_e( 'Status', 'lccl-de' ); ?></th>
					<th><?php esc_html_e( 'Registered', 'lccl-de' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'lccl-de' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $users ) ) : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'No reviewer accounts yet.', 'lccl-de' ); ?></td>
					</tr>
				<?php endif; ?>
				<?php foreach ( $users as $user ) : ?>
					<?php $active = LCCL_DE_Roles::is_active( $user->ID ); ?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( LCCL_DE_Admin_Users::url( array( 'action' => 'edit', 'user_id' => $user->ID ) ) ); ?>">
									<?php echo esc_html( trim( $user->first_name . ' ' . $user->last_name ) ? trim( $user->first_name . ' ' . $user->last_name ) : $user->display_name ); ?>
								</a>
							</strong>
						</td>
						<td><?php echo esc_html( $user->user_login ); ?></td>
						<td><?php echo esc_html( $user->user_email ); ?></td>
						<td><?php echo $active ? esc_html__( 'Active', 'lccl-de' ) : esc_html__( 'Inactive', 'lccl-de' ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $user->user_registered ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( LCCL_DE_Admin_Users::url( array( 'action' => 'edit', 'user_id' => $user->ID ) ) ); ?>">
								<?php esc_html_e( 'Edit', 'lccl-de' ); ?>
							</a>
							|
							<form method="post" action="<?php echo esc_url( LCCL_DE_Admin_Users::url() ); ?>" style="display:inline;">
								<?php wp_nonce_field( LCCL_DE_Admin_Users::NONCE ); ?>
								<input type="hidden" name="lccl_de_user_action" value="toggle">
								<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user->ID ); ?>">
								<button type="submit" class="button-link">
									<?php echo $active ? esc_html__( 'Deactivate', 'lccl-de' ) : esc_html__( 'Activate', 'lccl-de' ); ?>
								</button>
							</form>
							|
							<form method="post" action="<?php echo esc_url( LCCL_DE_Admin_Users::url() ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this reviewer permanently?', 'lccl-de' ) ); ?>');">
								<?php wp_nonce_field( LCCL_DE_Admin_Users::NONCE ); ?>
								<input type="hidden" name="lccl_de_user_action" value="delete">
								<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user->ID ); ?>">
								<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'lccl-de' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
