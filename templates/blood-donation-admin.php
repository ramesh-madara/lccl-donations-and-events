<?php
/**
 * Blood donation admin shell: login + dashboard chrome.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var bool $can_view Whether the current user may see donor data.
 * @var WP_User $user Current user.
 */

defined( 'ABSPATH' ) || exit;

$display = '';
if ( $can_view && $user instanceof WP_User ) {
	$display = trim( $user->first_name . ' ' . $user->last_name );
	if ( '' === $display ) {
		$display = $user->display_name;
	}
}
?>
<div class="lccl-bda" data-logged-in="<?php echo $can_view ? '1' : '0'; ?>">

	<section class="lccl-bda__login" data-panel="login" <?php echo $can_view ? 'hidden' : ''; ?>>
		<div class="lccl-bda__login-card">
			<div class="lccl-bda__modal" data-login-modal hidden>
				<span class="lccl-bda__loader lccl-bda__loader--lg" aria-hidden="true"></span>
				<span class="lccl-bda__modal-label"><?php esc_html_e( 'Signing in…', 'lccl-de' ); ?></span>
			</div>
			<p class="lccl-bda__brand">LCCL</p>
			<h2 class="lccl-bda__title"><?php esc_html_e( 'Blood Donation Admin', 'lccl-de' ); ?></h2>
			<p class="lccl-bda__lede"><?php esc_html_e( 'Sign in to review donor registrations.', 'lccl-de' ); ?></p>

			<p class="lccl-bda__banner lccl-bda__banner--error" data-login-error hidden></p>
			<p class="lccl-bda__banner lccl-bda__banner--success" data-login-notice hidden></p>

			<form class="lccl-bda__form" data-form="login" novalidate>
				<div class="lccl-bda__field">
					<label class="lccl-bda__label" for="lccl-bda-username"><?php esc_html_e( 'Username or email', 'lccl-de' ); ?></label>
					<input class="lccl-bda__input" type="text" id="lccl-bda-username" name="username" autocomplete="username" required>
				</div>
				<div class="lccl-bda__field">
					<label class="lccl-bda__label" for="lccl-bda-password"><?php esc_html_e( 'Password', 'lccl-de' ); ?></label>
					<input class="lccl-bda__input" type="password" id="lccl-bda-password" name="password" autocomplete="current-password" required>
				</div>
				<div class="lccl-bda__hp" aria-hidden="true">
					<label for="lccl-bda-hp"><?php esc_html_e( 'Website', 'lccl-de' ); ?></label>
					<input type="text" id="lccl-bda-hp" name="lccl_de_hp" value="" tabindex="-1" autocomplete="off">
				</div>
				<button class="lccl-bda__button" type="submit"><?php esc_html_e( 'Sign in', 'lccl-de' ); ?></button>
			</form>

			<p class="lccl-bda__switch">
				<button class="lccl-bda__text-button" type="button" data-show="forgot">
					<?php esc_html_e( 'Forgot password?', 'lccl-de' ); ?>
				</button>
			</p>
		</div>
	</section>

	<section class="lccl-bda__login" data-panel="forgot" hidden>
		<div class="lccl-bda__login-card">
			<div class="lccl-bda__modal" data-forgot-modal hidden>
				<span class="lccl-bda__loader lccl-bda__loader--lg" aria-hidden="true"></span>
				<span class="lccl-bda__modal-label"><?php esc_html_e( 'Sending reset link…', 'lccl-de' ); ?></span>
			</div>
			<p class="lccl-bda__brand">LCCL</p>
			<h2 class="lccl-bda__title"><?php esc_html_e( 'Reset password', 'lccl-de' ); ?></h2>
			<p class="lccl-bda__lede"><?php esc_html_e( 'Enter your username or email. We will send a reset link if the account exists.', 'lccl-de' ); ?></p>

			<p class="lccl-bda__banner lccl-bda__banner--error" data-forgot-error hidden></p>
			<p class="lccl-bda__banner lccl-bda__banner--success" data-forgot-notice hidden></p>

			<form class="lccl-bda__form" data-form="forgot" novalidate>
				<div class="lccl-bda__field">
					<label class="lccl-bda__label" for="lccl-bda-forgot-user"><?php esc_html_e( 'Username or email', 'lccl-de' ); ?></label>
					<input class="lccl-bda__input" type="text" id="lccl-bda-forgot-user" name="username" autocomplete="username" required>
				</div>
				<div class="lccl-bda__hp" aria-hidden="true">
					<label for="lccl-bda-forgot-hp"><?php esc_html_e( 'Website', 'lccl-de' ); ?></label>
					<input type="text" id="lccl-bda-forgot-hp" name="lccl_de_hp" value="" tabindex="-1" autocomplete="off">
				</div>
				<button class="lccl-bda__button" type="submit"><?php esc_html_e( 'Send reset link', 'lccl-de' ); ?></button>
			</form>

			<p class="lccl-bda__switch">
				<button class="lccl-bda__text-button" type="button" data-show="login">
					<?php esc_html_e( 'Back to sign in', 'lccl-de' ); ?>
				</button>
			</p>
		</div>
	</section>

	<section class="lccl-bda__dash" data-panel="dash" <?php echo $can_view ? '' : 'hidden'; ?>>
		<header class="lccl-bda__header">
			<div>
				<p class="lccl-bda__brand">LCCL</p>
				<h2 class="lccl-bda__title lccl-bda__title--inline">
					<span data-dash-title><?php esc_html_e( 'Donor registrations', 'lccl-de' ); ?></span>
					<span class="lccl-bda__loader" data-dash-loader hidden aria-hidden="true"></span>
				</h2>
				<nav class="lccl-bda__nav" aria-label="<?php esc_attr_e( 'Admin sections', 'lccl-de' ); ?>">
					<button class="lccl-bda__nav-btn is-current" type="button" data-view="registrations">
						<?php esc_html_e( 'Registrations', 'lccl-de' ); ?>
					</button>
					<button class="lccl-bda__nav-btn" type="button" data-view="notifications">
						<?php esc_html_e( 'Notifications', 'lccl-de' ); ?>
					</button>
				</nav>
			</div>
			<div class="lccl-bda__who">
				<span data-display-name><?php echo esc_html( $display ); ?></span>
				<button class="lccl-bda__text-button" type="button" data-action="logout">
					<?php esc_html_e( 'Sign out', 'lccl-de' ); ?>
				</button>
			</div>
		</header>

		<p class="lccl-bda__banner lccl-bda__banner--error" data-dash-error hidden></p>

		<div data-view-panel="registrations">
		<form class="lccl-bda__filters" data-form="filters">
			<div class="lccl-bda__field lccl-bda__field--grow">
				<label class="lccl-bda__label" for="lccl-bda-search"><?php esc_html_e( 'Search', 'lccl-de' ); ?></label>
				<input class="lccl-bda__input" type="search" id="lccl-bda-search" name="search" placeholder="<?php esc_attr_e( 'Name, phone, or email', 'lccl-de' ); ?>">
			</div>
			<div class="lccl-bda__field">
				<label class="lccl-bda__label" for="lccl-bda-district"><?php esc_html_e( 'District', 'lccl-de' ); ?></label>
				<select class="lccl-bda__select" id="lccl-bda-district" name="district">
					<option value=""><?php esc_html_e( 'All districts', 'lccl-de' ); ?></option>
				</select>
			</div>
			<div class="lccl-bda__field">
				<label class="lccl-bda__label" for="lccl-bda-notify"><?php esc_html_e( 'Campaigns', 'lccl-de' ); ?></label>
				<select class="lccl-bda__select" id="lccl-bda-notify" name="notify_campaigns">
					<option value=""><?php esc_html_e( 'All', 'lccl-de' ); ?></option>
					<option value="1"><?php esc_html_e( 'Notify: yes', 'lccl-de' ); ?></option>
					<option value="0"><?php esc_html_e( 'Notify: no', 'lccl-de' ); ?></option>
				</select>
			</div>
		</form>

		<div class="lccl-bda__layout">
			<div class="lccl-bda__table-wrap">
				<div class="lccl-bda__table-scroll">
					<div class="lccl-bda__modal" data-table-modal hidden>
						<span class="lccl-bda__loader lccl-bda__loader--lg" aria-hidden="true"></span>
						<span class="lccl-bda__modal-label"><?php esc_html_e( 'Loading…', 'lccl-de' ); ?></span>
					</div>
					<table class="lccl-bda__table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'lccl-de' ); ?></th>
								<th><?php esc_html_e( 'Phone', 'lccl-de' ); ?></th>
								<th><?php esc_html_e( 'District', 'lccl-de' ); ?></th>
								<th><?php esc_html_e( 'Blood bank', 'lccl-de' ); ?></th>
								<th><?php esc_html_e( 'Notify', 'lccl-de' ); ?></th>
								<th><?php esc_html_e( 'Registered', 'lccl-de' ); ?></th>
							</tr>
						</thead>
						<tbody data-donor-rows>
							<tr class="lccl-bda__empty">
								<td colspan="6"><?php esc_html_e( 'Loading registrations…', 'lccl-de' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="lccl-bda__pager" data-pager-bar>
					<p class="lccl-bda__page-status" data-page-status></p>
					<div class="lccl-bda__pager-end">
						<nav class="lccl-bda__page-nav" data-pager aria-label="<?php esc_attr_e( 'Pages', 'lccl-de' ); ?>"></nav>
						<label class="lccl-bda__per-page" for="lccl-bda-per-page">
							<span><?php esc_html_e( 'Per page', 'lccl-de' ); ?></span>
							<select class="lccl-bda__select" id="lccl-bda-per-page" name="per_page">
								<option value="10">10</option>
								<option value="20" selected>20</option>
								<option value="50">50</option>
							</select>
						</label>
					</div>
				</div>
			</div>

			<aside class="lccl-bda__detail" data-detail hidden>
				<div class="lccl-bda__modal" data-detail-modal hidden>
					<span class="lccl-bda__loader lccl-bda__loader--lg" aria-hidden="true"></span>
					<span class="lccl-bda__modal-label"><?php esc_html_e( 'Loading…', 'lccl-de' ); ?></span>
				</div>
				<button class="lccl-bda__text-button lccl-bda__detail-close" type="button" data-action="close-detail">
					<?php esc_html_e( 'Close', 'lccl-de' ); ?>
				</button>
				<h3 class="lccl-bda__detail-title" data-detail-name></h3>
				<dl class="lccl-bda__dl" data-detail-body></dl>
			</aside>
		</div>
		</div>

		<section class="lccl-bda__notify" data-view-panel="notifications" hidden>
			<div class="lccl-bda__notify-card">
				<div class="lccl-bda__modal" data-notify-modal hidden>
					<span class="lccl-bda__loader lccl-bda__loader--lg" aria-hidden="true"></span>
					<span class="lccl-bda__modal-label"><?php esc_html_e( 'Saving…', 'lccl-de' ); ?></span>
				</div>
				<p class="lccl-bda__lede">
					<?php esc_html_e( 'These go out after someone submits the public registration form. Uncheck a row to stop that message. Saving a registration is never blocked if a send fails.', 'lccl-de' ); ?>
				</p>
				<p class="lccl-bda__banner lccl-bda__banner--error" data-notify-error hidden></p>
				<p class="lccl-bda__banner lccl-bda__banner--success" data-notify-notice hidden></p>
				<form class="lccl-bda__form" data-form="notifications">
					<label class="lccl-bda__check">
						<input type="checkbox" name="donor_sms" value="1">
						<span><?php esc_html_e( 'Send an SMS to the person who registered (Dialog e-SMS).', 'lccl-de' ); ?></span>
					</label>
					<label class="lccl-bda__check">
						<input type="checkbox" name="donor_email" value="1">
						<span><?php esc_html_e( 'Email the person who registered, if they entered an email address.', 'lccl-de' ); ?></span>
					</label>
					<label class="lccl-bda__check">
						<input type="checkbox" name="admin_email" value="1">
						<span><?php esc_html_e( 'Email a staff copy when someone registers.', 'lccl-de' ); ?></span>
					</label>
					<div class="lccl-bda__field">
						<label class="lccl-bda__label" for="lccl-bda-admin-address"><?php esc_html_e( 'Admin email address', 'lccl-de' ); ?></label>
						<input class="lccl-bda__input" type="email" id="lccl-bda-admin-address" name="admin_address" autocomplete="email" required>
					</div>
					<button class="lccl-bda__button lccl-bda__button--inline" type="submit"><?php esc_html_e( 'Save notifications', 'lccl-de' ); ?></button>
				</form>
			</div>
		</section>
	</section>
</div>
