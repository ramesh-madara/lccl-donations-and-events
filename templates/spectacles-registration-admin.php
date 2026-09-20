<?php
/**
 * Blood donation admin shell: login + dashboard chrome.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var bool    $can_view     Whether the current user may see registrations.
 * @var WP_User $user         Current user.
 * @var string  $current_dash blood|projects|spectacles
 */

defined( 'ABSPATH' ) || exit;

$current_dash = isset( $current_dash ) ? $current_dash : 'spectacles';
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
			<h2 class="lccl-bda__title"><?php esc_html_e( 'Spectacles Registration Admin', 'lccl-de' ); ?></h2>
			<p class="lccl-bda__lede"><?php esc_html_e( 'Sign in to manage free spectacles registrations.', 'lccl-de' ); ?></p>

			<p class="lccl-bda__banner lccl-bda__banner--error" data-login-error hidden></p>
			<p class="lccl-bda__banner lccl-bda__banner--success" data-login-notice hidden></p>

			<form class="lccl-bda__form" data-form="login" novalidate>
				<div class="lccl-bda__field">
					<label class="lccl-bda__label" id="lccl-bda-username-label" for="lccl-bda-username"><?php esc_html_e( 'Username or email', 'lccl-de' ); ?></label>
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
			<p class="lccl-bda__lede" data-lccl-forgot-lede><?php esc_html_e( 'Enter your username or email. We will send a reset link if the account exists.', 'lccl-de' ); ?></p>

			<p class="lccl-bda__banner lccl-bda__banner--error" data-forgot-error hidden></p>
			<p class="lccl-bda__banner lccl-bda__banner--success" data-forgot-notice hidden></p>

			<form class="lccl-bda__form" data-form="forgot" novalidate>
				<div class="lccl-bda__field">
					<label class="lccl-bda__label" id="lccl-bda-forgot-user-label" for="lccl-bda-forgot-user"><?php esc_html_e( 'Username or email', 'lccl-de' ); ?></label>
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
				<h2 class="lccl-bda__title lccl-bda__title--inline">
					<?php esc_html_e( 'Spectacles registrations', 'lccl-de' ); ?>
					<span class="lccl-bda__loader" data-dash-loader hidden aria-hidden="true"></span>
				</h2>
				<?php include LCCL_DE_PATH . 'templates/review-dash-nav.php'; ?>
			</div>
			<div class="lccl-bda__who">
				<span class="lccl-bda__identity">
					<span class="lccl-bda__avatar" aria-hidden="true">
						<svg class="lccl-bda__avatar-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="12" cy="8" r="3.6" stroke="currentColor" stroke-width="1.8"/>
							<path d="M5 19.2c.7-3.5 3.5-5.3 7-5.3s6.3 1.8 7 5.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
						</svg>
					</span>
					<span data-display-name><?php echo esc_html( $display ); ?></span>
				</span>
				<button class="lccl-bda__text-button" type="button" data-action="logout">
					<?php esc_html_e( 'Sign out', 'lccl-de' ); ?>
				</button>
			</div>
		</header>

		<p class="lccl-bda__banner lccl-bda__banner--error" data-dash-error hidden></p>

		<form class="lccl-bda__filters" data-form="filters">
			<div class="lccl-bda__field lccl-bda__field--grow">
				<label class="lccl-bda__label" for="lccl-bda-search"><?php esc_html_e( 'Search', 'lccl-de' ); ?></label>
				<input class="lccl-bda__input" type="search" id="lccl-bda-search" name="search" placeholder="<?php esc_attr_e( 'Search by child, guardian, phone, email, school, or district.', 'lccl-de' ); ?>">
			</div>
			<div class="lccl-bda__field">
				<label class="lccl-bda__label" for="lccl-bda-district"><?php esc_html_e( 'District', 'lccl-de' ); ?></label>
				<select class="lccl-bda__select" id="lccl-bda-district" name="district">
					<option value=""><?php esc_html_e( 'All districts', 'lccl-de' ); ?></option>
				</select>
			</div>
			<div class="lccl-bda__field">
				<label class="lccl-bda__label" for="lccl-sra-gender"><?php esc_html_e( 'Gender', 'lccl-de' ); ?></label>
				<select class="lccl-bda__select" id="lccl-sra-gender" name="gender">
					<option value=""><?php esc_html_e( 'All genders', 'lccl-de' ); ?></option>
				</select>
			</div>
			<div class="lccl-bda__field">
				<label class="lccl-bda__label" for="lccl-sra-grade"><?php esc_html_e( 'Grade', 'lccl-de' ); ?></label>
				<select class="lccl-bda__select" id="lccl-sra-grade" name="grade">
					<option value=""><?php esc_html_e( 'All grades', 'lccl-de' ); ?></option>
				</select>
			</div>
			<div class="lccl-bda__field">
				<label class="lccl-bda__label" for="lccl-sra-letter"><?php esc_html_e( 'School letter', 'lccl-de' ); ?></label>
				<select class="lccl-bda__select" id="lccl-sra-letter" name="school_letter">
					<option value=""><?php esc_html_e( 'All letters', 'lccl-de' ); ?></option>
				</select>
			</div>
			<div class="lccl-bda__field lccl-bda__field--clear">
				<span class="lccl-bda__label" aria-hidden="true">&nbsp;</span>
				<button class="lccl-bda__clear" type="button" data-action="clear-filters">
					<?php esc_html_e( 'Clear Filters', 'lccl-de' ); ?>
				</button>
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
								<th><?php esc_html_e( 'Child', 'lccl-de' ); ?></th>
								<th><?php esc_html_e( 'Guardian', 'lccl-de' ); ?></th>
								<th><?php esc_html_e( 'Phone', 'lccl-de' ); ?></th>
								<th class="lccl-bda__col-email"><?php esc_html_e( 'Email', 'lccl-de' ); ?></th>
								<th class="lccl-bda__col-district"><?php esc_html_e( 'District', 'lccl-de' ); ?></th>
								<th class="lccl-bda__col-bank"><?php esc_html_e( 'School', 'lccl-de' ); ?></th>
								<th><?php esc_html_e( 'Grade', 'lccl-de' ); ?></th>
								<th><?php esc_html_e( 'Registered', 'lccl-de' ); ?></th>
								<th class="lccl-bda__col-expand"><span class="screen-reader-text"><?php esc_html_e( 'Details', 'lccl-de' ); ?></span></th>
							</tr>
						</thead>
						<tbody data-donor-rows>
							<tr class="lccl-bda__empty">
								<td colspan="9"><?php esc_html_e( 'Loading registrations…', 'lccl-de' ); ?></td>
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
		</div>
	</section>

	<div class="lccl-bda__dialog lccl-bda__dialog--letter" data-letter-dialog hidden>
		<div class="lccl-bda__dialog-backdrop" data-letter-close></div>
		<div class="lccl-bda__dialog-card" role="dialog" aria-modal="true" aria-labelledby="lccl-bda-letter-title">
			<div class="lccl-bda__letter-head">
				<h3 class="lccl-bda__dialog-title" id="lccl-bda-letter-title" data-letter-title><?php esc_html_e( 'School letter', 'lccl-de' ); ?></h3>
				<button class="lccl-bda__action" type="button" data-letter-close><?php esc_html_e( 'Close', 'lccl-de' ); ?></button>
			</div>
			<p class="lccl-bda__banner lccl-bda__banner--error" data-letter-error hidden></p>
			<div class="lccl-bda__letter-stage">
				<iframe class="lccl-bda__letter-frame" data-letter-frame title="<?php esc_attr_e( 'School letter preview', 'lccl-de' ); ?>" hidden></iframe>
				<img class="lccl-bda__letter-image" data-letter-image alt="<?php esc_attr_e( 'School letter preview', 'lccl-de' ); ?>" hidden>
			</div>
		</div>
	</div>
	<div class="lccl-bda__dialog" data-delete-dialog hidden>
		<div class="lccl-bda__dialog-backdrop" data-delete-cancel></div>
		<div class="lccl-bda__dialog-card" role="dialog" aria-modal="true" aria-labelledby="lccl-bda-delete-title">
			<h3 class="lccl-bda__dialog-title" id="lccl-bda-delete-title"><?php esc_html_e( 'Delete this registration?', 'lccl-de' ); ?></h3>
			<p class="lccl-bda__dialog-copy" data-delete-message><?php esc_html_e( 'Are you sure you want to delete this spectacles registration? This cannot be undone.', 'lccl-de' ); ?></p>
			<p class="lccl-bda__banner lccl-bda__banner--error" data-delete-error hidden></p>
			<div class="lccl-bda__dialog-actions">
				<button class="lccl-bda__dialog-no" type="button" data-delete-cancel><?php esc_html_e( 'No', 'lccl-de' ); ?></button>
				<button class="lccl-bda__dialog-yes" type="button" data-delete-confirm><?php esc_html_e( 'Yes', 'lccl-de' ); ?></button>
			</div>
		</div>
	</div>
	<div class="lccl-bda__toasts" data-toasts></div>
</div>
