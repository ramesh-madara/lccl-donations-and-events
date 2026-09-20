<?php
/**
 * Program reviewer accounts.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var string        $action  list|add|edit
 * @var string        $message Flash code
 * @var string        $error   Flash error
 * @var WP_User|null  $edit    User being edited
 */

defined( 'ABSPATH' ) || exit;

$action = isset( $action ) ? $action : 'list';
?>
<nav class="lccl-prog__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'lccl-de' ); ?>">
	<?php if ( 'add' === $action || 'edit' === $action ) : ?>
		<a href="<?php echo esc_url( LCCL_DE_Admin_Programs::users_url() ); ?>" data-tab="users"><?php esc_html_e( 'User Management', 'lccl-de' ); ?></a>
		<span class="lccl-prog__crumbs-sep" aria-hidden="true">/</span>
		<span class="lccl-prog__crumbs-current"><?php echo 'edit' === $action ? esc_html__( 'Edit reviewer', 'lccl-de' ) : esc_html__( 'Add reviewer', 'lccl-de' ); ?></span>
	<?php else : ?>
		<span class="lccl-prog__crumbs-current"><?php esc_html_e( 'User Management', 'lccl-de' ); ?></span>
	<?php endif; ?>
</nav>
<?php
$notices = array(
	'created'     => __( 'Reviewer created.', 'lccl-de' ),
	'updated'     => __( 'Reviewer updated.', 'lccl-de' ),
	'deleted'     => __( 'Reviewer deleted.', 'lccl-de' ),
	'activated'   => __( 'Reviewer activated.', 'lccl-de' ),
	'deactivated' => __( 'Reviewer deactivated.', 'lccl-de' ),
);
?>
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
	<div class="lccl-prog__toolbar">
		<p><?php echo $is_edit ? esc_html__( 'Update this reviewer account.', 'lccl-de' ) : esc_html__( 'Create an account that can open every program dashboard. It cannot reach wp-admin.', 'lccl-de' ); ?></p>
	</div>

	<form method="post" action="<?php echo esc_url( LCCL_DE_Admin_Programs::users_url() ); ?>" data-lccl-user-form novalidate>
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
						<input type="text" id="lccl-de-user-login" value="<?php echo esc_attr( $edit->user_login ); ?>" disabled>
						<p class="description"><?php esc_html_e( 'Username cannot be changed.', 'lccl-de' ); ?></p>
					<?php else : ?>
						<input type="text" id="lccl-de-user-login" name="user_login" required autocomplete="off" maxlength="60" pattern="[A-Za-z0-9._@-]+">
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lccl-de-user-email"><?php esc_html_e( 'Email', 'lccl-de' ); ?></label></th>
				<td>
					<input type="email" id="lccl-de-user-email" name="user_email" required maxlength="191" autocomplete="email" value="<?php echo $is_edit ? esc_attr( $edit->user_email ) : ''; ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lccl-de-first-name"><?php esc_html_e( 'First name', 'lccl-de' ); ?></label></th>
				<td>
					<input type="text" id="lccl-de-first-name" name="first_name" required value="<?php echo $is_edit ? esc_attr( $edit->first_name ) : ''; ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lccl-de-last-name"><?php esc_html_e( 'Last name', 'lccl-de' ); ?></label></th>
				<td>
					<input type="text" id="lccl-de-last-name" name="last_name" required value="<?php echo $is_edit ? esc_attr( $edit->last_name ) : ''; ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="lccl-de-user-pass"><?php esc_html_e( 'Password', 'lccl-de' ); ?></label></th>
				<td>
					<input type="password" id="lccl-de-user-pass" name="user_pass" <?php echo $is_edit ? '' : 'required'; ?> minlength="8" autocomplete="new-password">
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
					<label class="lccl-prog__check">
						<input type="checkbox" name="disabled" value="1" <?php checked( $disabled ); ?>>
						<span><?php esc_html_e( 'Inactive — cannot sign in', 'lccl-de' ); ?></span>
					</label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php echo $is_edit ? esc_html__( 'Save reviewer', 'lccl-de' ) : esc_html__( 'Create reviewer', 'lccl-de' ); ?>
			</button>
			<a class="button" href="<?php echo esc_url( LCCL_DE_Admin_Programs::users_url() ); ?>"><?php esc_html_e( 'Cancel', 'lccl-de' ); ?></a>
		</p>
	</form>
<?php else : ?>
	<?php $users = LCCL_DE_Admin_Users::get_reviewers(); ?>
	<div class="lccl-prog__toolbar">
		<p><?php esc_html_e( 'Add, edit, or deactivate reviewer accounts.', 'lccl-de' ); ?></p>
		<a class="button button-primary" href="<?php echo esc_url( LCCL_DE_Admin_Programs::users_url( array( 'action' => 'add' ) ) ); ?>">
			<?php esc_html_e( 'Add reviewer', 'lccl-de' ); ?>
		</a>
	</div>

	<table class="lccl-prog__table">
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
							<a href="<?php echo esc_url( LCCL_DE_Admin_Programs::users_url( array( 'action' => 'edit', 'user_id' => $user->ID ) ) ); ?>">
								<?php echo esc_html( trim( $user->first_name . ' ' . $user->last_name ) ? trim( $user->first_name . ' ' . $user->last_name ) : $user->display_name ); ?>
							</a>
						</strong>
					</td>
					<td><?php echo esc_html( $user->user_login ); ?></td>
					<td><?php echo esc_html( $user->user_email ); ?></td>
					<td>
						<span class="lccl-prog__pill<?php echo $active ? ' lccl-prog__pill--yes' : ''; ?>">
							<?php echo $active ? esc_html__( 'Active', 'lccl-de' ) : esc_html__( 'Inactive', 'lccl-de' ); ?>
						</span>
					</td>
					<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $user->user_registered ) ); ?></td>
					<td>
						<div class="lccl-prog__actions">
							<a class="lccl-prog__link-btn" href="<?php echo esc_url( LCCL_DE_Admin_Programs::users_url( array( 'action' => 'edit', 'user_id' => $user->ID ) ) ); ?>">
								<?php esc_html_e( 'Edit', 'lccl-de' ); ?>
							</a>
							<form method="post" action="<?php echo esc_url( LCCL_DE_Admin_Programs::users_url() ); ?>">
								<?php wp_nonce_field( LCCL_DE_Admin_Users::NONCE ); ?>
								<input type="hidden" name="lccl_de_user_action" value="toggle">
								<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user->ID ); ?>">
								<button type="submit" class="lccl-prog__link-btn">
									<?php echo $active ? esc_html__( 'Deactivate', 'lccl-de' ) : esc_html__( 'Activate', 'lccl-de' ); ?>
								</button>
							</form>
							<form method="post" action="<?php echo esc_url( LCCL_DE_Admin_Programs::users_url() ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this reviewer permanently?', 'lccl-de' ) ); ?>');">
								<?php wp_nonce_field( LCCL_DE_Admin_Users::NONCE ); ?>
								<input type="hidden" name="lccl_de_user_action" value="delete">
								<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user->ID ); ?>">
								<button type="submit" class="lccl-prog__link-btn lccl-prog__link-btn--danger"><?php esc_html_e( 'Delete', 'lccl-de' ); ?></button>
							</form>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
