<?php
/**
 * Payment Dashboard frontend shell: Login UI + Tabbed Dashboard.
 *
 * Hosted on /payment-dashboard via [lccl_payment_dashboard].
 *
 * @package LCCL_Donations_And_Events
 *
 * @var bool    $can_view Whether current user can access the dashboard.
 * @var WP_User $user     Current user.
 */

defined( 'ABSPATH' ) || exit;

$display = '';
if ( $can_view && $user instanceof WP_User ) {
	$display = LCCL_DE_Payment_Dashboard::display_name( $user );
}
?>
<div class="lccl-paydash" data-logged-in="<?php echo $can_view ? '1' : '0'; ?>">

	<!-- 1. Dedicated Login Section -->
	<section class="lccl-paydash__login lccl-bda__login" data-panel="login" <?php echo $can_view ? 'hidden' : ''; ?>>
		<div class="lccl-bda__login-card">
			<div class="lccl-bda__modal" data-login-modal hidden style="display:none;">
				<span class="lccl-bda__loader lccl-bda__loader--lg" aria-hidden="true"></span>
				<span class="lccl-bda__modal-label"><?php esc_html_e( 'Signing in…', 'lccl-de' ); ?></span>
			</div>

			<div class="lccl-paydash__login-badge">
				<span class="lccl-paydash__shield-icon" aria-hidden="true">🔒</span>
				<span><?php esc_html_e( 'Authorized Staff Only', 'lccl-de' ); ?></span>
			</div>

			<h2 class="lccl-bda__title"><?php esc_html_e( 'Payment Dashboard', 'lccl-de' ); ?></h2>
			<p class="lccl-bda__lede"><?php esc_html_e( 'Sign in with your administrative account to monitor and review online payments.', 'lccl-de' ); ?></p>

			<p class="lccl-bda__banner lccl-bda__banner--error" data-login-error hidden></p>
			<p class="lccl-bda__banner lccl-bda__banner--success" data-login-notice hidden></p>

			<form class="lccl-bda__form" data-form="login" novalidate>
				<div class="lccl-bda__field">
					<label class="lccl-bda__label" id="lccl-paydash-username-label" for="lccl-paydash-username"><?php esc_html_e( 'Username or email', 'lccl-de' ); ?></label>
					<input class="lccl-bda__input" type="text" id="lccl-paydash-username" name="username" autocomplete="username" required>
				</div>
				<div class="lccl-bda__field">
					<label class="lccl-bda__label" for="lccl-paydash-password"><?php esc_html_e( 'Password', 'lccl-de' ); ?></label>
					<input class="lccl-bda__input" type="password" id="lccl-paydash-password" name="password" autocomplete="current-password" required>
				</div>
				<div class="lccl-bda__hp" aria-hidden="true" style="display:none;">
					<label for="lccl-paydash-hp"><?php esc_html_e( 'Website', 'lccl-de' ); ?></label>
					<input type="text" id="lccl-paydash-hp" name="lccl_de_hp" value="" tabindex="-1" autocomplete="off">
				</div>
				<button class="lccl-bda__button" type="submit"><?php esc_html_e( 'Sign in to Dashboard', 'lccl-de' ); ?></button>
			</form>

			<p class="lccl-bda__switch">
				<button class="lccl-bda__text-button" type="button" data-show="forgot">
					<?php esc_html_e( 'Forgot password?', 'lccl-de' ); ?>
				</button>
			</p>
		</div>
	</section>

	<!-- 2. Forgot Password Section -->
	<section class="lccl-paydash__login lccl-bda__login" data-panel="forgot" hidden>
		<div class="lccl-bda__login-card">
			<div class="lccl-bda__modal" data-forgot-modal hidden style="display:none;">
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
					<label class="lccl-bda__label" id="lccl-paydash-forgot-user-label" for="lccl-paydash-forgot-user"><?php esc_html_e( 'Username or email', 'lccl-de' ); ?></label>
					<input class="lccl-bda__input" type="text" id="lccl-paydash-forgot-user" name="username" autocomplete="username" required>
				</div>
				<div class="lccl-bda__hp" aria-hidden="true" style="display:none;">
					<label for="lccl-paydash-forgot-hp"><?php esc_html_e( 'Website', 'lccl-de' ); ?></label>
					<input type="text" id="lccl-paydash-forgot-hp" name="lccl_de_hp" value="" tabindex="-1" autocomplete="off">
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

	<!-- 3. Authenticated Payment Monitoring Dashboard -->
	<section class="lccl-paydash__dash lccl-bda__dash" data-panel="dash" <?php echo $can_view ? '' : 'hidden'; ?>>

		<!-- Dashboard Top Bar -->
		<header class="lccl-paydash__header lccl-bda__header">
			<div>
				<h2 class="lccl-paydash__title lccl-bda__title lccl-bda__title--inline">
					<?php esc_html_e( 'Payment Transactions Dashboard', 'lccl-de' ); ?>
					<span class="lccl-bda__loader" data-dash-loader hidden aria-hidden="true" style="display:none;"></span>
				</h2>
				<p class="lccl-paydash__subtitle">
					<?php esc_html_e( 'Live unified record of all payments across configured routes (CBC Paycenter Web 4.0)', 'lccl-de' ); ?>
				</p>
			</div>

			<div class="lccl-bda__who">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=lccl-de-programs&tab=gateway&subtab=transactions' ) ); ?>" class="lccl-paydash__admin-link" target="_blank" rel="noopener">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<rect x="3" y="3" width="7" height="7"></rect>
						<rect x="14" y="3" width="7" height="7"></rect>
						<rect x="14" y="14" width="7" height="7"></rect>
						<rect x="3" y="14" width="7" height="7"></rect>
					</svg>
					<?php esc_html_e( 'WP Admin', 'lccl-de' ); ?>
				</a>
				<span class="lccl-bda__identity">
					<span class="lccl-bda__avatar" aria-hidden="true">
						<svg class="lccl-bda__avatar-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="12" cy="8" r="4"></circle>
							<path d="M4 20c0-4 4-6 8-6s8 2 8 6"></path>
						</svg>
					</span>
					<span data-display-name><?php echo esc_html( $display ); ?></span>
				</span>
				<button class="lccl-bda__text-button lccl-paydash__logout-btn" type="button" data-action="logout">
					<?php esc_html_e( 'Sign out', 'lccl-de' ); ?>
				</button>
			</div>
		</header>

		<p class="lccl-bda__banner lccl-bda__banner--error" data-dash-error hidden></p>

		<!-- KPI Metric Summary Cards -->
		<div class="lccl-paydash__kpis">
			<div class="lccl-paydash__kpi-card">
				<span class="lccl-paydash__kpi-label"><?php esc_html_e( 'Total Transactions', 'lccl-de' ); ?></span>
				<span class="lccl-paydash__kpi-value" data-kpi="total_count">0</span>
				<span class="lccl-paydash__kpi-sub"><?php esc_html_e( 'Across all routes', 'lccl-de' ); ?></span>
			</div>
			<div class="lccl-paydash__kpi-card lccl-paydash__kpi-card--success">
				<span class="lccl-paydash__kpi-label"><?php esc_html_e( 'Total Collected', 'lccl-de' ); ?></span>
				<span class="lccl-paydash__kpi-value" data-kpi="total_paid_lkr">LKR 0.00</span>
				<span class="lccl-paydash__kpi-sub" data-kpi="paid_count_sub"><?php esc_html_e( '0 completed payments', 'lccl-de' ); ?></span>
			</div>
			<div class="lccl-paydash__kpi-card lccl-paydash__kpi-card--danger">
				<span class="lccl-paydash__kpi-label"><?php esc_html_e( 'Declined / Failed', 'lccl-de' ); ?></span>
				<span class="lccl-paydash__kpi-value" data-kpi="failed_count">0</span>
				<span class="lccl-paydash__kpi-sub"><?php esc_html_e( 'Declined by bank or error', 'lccl-de' ); ?></span>
			</div>
			<div class="lccl-paydash__kpi-card">
				<span class="lccl-paydash__kpi-label"><?php esc_html_e( 'Pending / Cancelled', 'lccl-de' ); ?></span>
				<span class="lccl-paydash__kpi-value" data-kpi="other_count">0</span>
				<span class="lccl-paydash__kpi-sub"><?php esc_html_e( 'Unfinished checkout', 'lccl-de' ); ?></span>
			</div>
		</div>

		<!-- Payment Routes Navigation Tabs -->
		<nav class="lccl-paydash__nav-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Payment Routes', 'lccl-de' ); ?>">
			<button class="lccl-paydash__tab is-active" type="button" role="tab" data-tab="all" aria-selected="true">
				<span class="lccl-paydash__tab-icon" aria-hidden="true">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="3" width="7" height="7"></rect>
						<rect x="14" y="3" width="7" height="7"></rect>
						<rect x="14" y="14" width="7" height="7"></rect>
						<rect x="3" y="14" width="7" height="7"></rect>
					</svg>
				</span>
				<span class="lccl-paydash__tab-text"><?php esc_html_e( 'All Payments', 'lccl-de' ); ?></span>
			</button>
			<button class="lccl-paydash__tab" type="button" role="tab" data-tab="donation" aria-selected="false">
				<span class="lccl-paydash__tab-icon" aria-hidden="true">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
					</svg>
				</span>
				<span class="lccl-paydash__tab-text"><?php esc_html_e( 'Online Donations', 'lccl-de' ); ?></span>
			</button>
			<button class="lccl-paydash__tab" type="button" role="tab" data-tab="sponsorship" aria-selected="false">
				<span class="lccl-paydash__tab-icon" aria-hidden="true">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M12 2L2 7l10 5 10-5-10-5z"></path>
						<path d="M2 17l10 5 10-5"></path>
						<path d="M2 12l10 5 10-5"></path>
					</svg>
				</span>
				<span class="lccl-paydash__tab-text"><?php esc_html_e( 'Project Sponsorships', 'lccl-de' ); ?></span>
			</button>
			<button class="lccl-paydash__tab" type="button" role="tab" data-tab="membership" aria-selected="false">
				<span class="lccl-paydash__tab-icon" aria-hidden="true">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
						<circle cx="9" cy="7" r="4"></circle>
						<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
						<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
					</svg>
				</span>
				<span class="lccl-paydash__tab-text"><?php esc_html_e( 'Membership Fees', 'lccl-de' ); ?></span>
			</button>
		</nav>

		<!-- Filters and Search Bar -->
		<form class="lccl-paydash__filter-bar" data-form="filters" onsubmit="return false;">
			<div class="lccl-paydash__search-wrap">
				<label class="screen-reader-text" for="lccl-paydash-search"><?php esc_html_e( 'Search Transactions', 'lccl-de' ); ?></label>
				<input class="lccl-bda__input lccl-paydash__search" type="search" id="lccl-paydash-search" name="search" placeholder="<?php esc_attr_e( 'Search by Ref, Name, Email, Phone, Project, Receipt…', 'lccl-de' ); ?>">
			</div>

			<div class="lccl-paydash__filter-item">
				<label class="screen-reader-text" for="lccl-paydash-status"><?php esc_html_e( 'Filter by status', 'lccl-de' ); ?></label>
				<select class="lccl-bda__select" id="lccl-paydash-status" name="status">
					<option value=""><?php esc_html_e( 'All Statuses', 'lccl-de' ); ?></option>
					<option value="paid" selected><?php esc_html_e( 'Paid (Approved)', 'lccl-de' ); ?></option>
					<option value="failed"><?php esc_html_e( 'Declined / Failed', 'lccl-de' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending Checkout', 'lccl-de' ); ?></option>
					<option value="cancelled"><?php esc_html_e( 'Cancelled', 'lccl-de' ); ?></option>
				</select>
			</div>

			<div class="lccl-paydash__filter-item">
				<label class="screen-reader-text" for="lccl-paydash-per-page"><?php esc_html_e( 'Items per page', 'lccl-de' ); ?></label>
				<select class="lccl-bda__select" id="lccl-paydash-per-page" name="per_page">
					<option value="10"><?php esc_html_e( '10 per page', 'lccl-de' ); ?></option>
					<option value="20" selected><?php esc_html_e( '20 per page', 'lccl-de' ); ?></option>
					<option value="50"><?php esc_html_e( '50 per page', 'lccl-de' ); ?></option>
				</select>
			</div>

			<button class="lccl-bda__clear lccl-paydash__clear-btn" type="button" data-action="clear-filters">
				<?php esc_html_e( 'Clear', 'lccl-de' ); ?>
			</button>

			<div class="lccl-paydash__count-indicator" data-count-label>
				<!-- Populated via JS -->
			</div>
		</form>

		<!-- Data Table Container -->
		<div class="lccl-paydash__table-card">
			<div class="lccl-paydash__modal" data-table-modal hidden style="display:none;">
				<span class="lccl-bda__loader lccl-bda__loader--lg" aria-hidden="true"></span>
				<span class="lccl-bda__modal-label"><?php esc_html_e( 'Loading payments…', 'lccl-de' ); ?></span>
			</div>

			<div class="lccl-paydash__table-scroll">
				<table class="lccl-paydash__table" aria-label="<?php esc_attr_e( 'Payment Transactions', 'lccl-de' ); ?>">
					<thead>
						<tr>
							<th scope="col" style="width: 140px;"><?php esc_html_e( 'Date / Time', 'lccl-de' ); ?></th>
							<th scope="col" style="width: 190px;"><?php esc_html_e( 'Order Ref', 'lccl-de' ); ?></th>
							<th scope="col" style="width: 160px;"><?php esc_html_e( 'Route / Type', 'lccl-de' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Payer / Sponsor', 'lccl-de' ); ?></th>
							<th scope="col" style="width: 120px;"><?php esc_html_e( 'Amount', 'lccl-de' ); ?></th>
							<th scope="col" style="width: 100px;"><?php esc_html_e( 'Status', 'lccl-de' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Receipt / Response', 'lccl-de' ); ?></th>
							<th scope="col" style="width: 90px; text-align: center;"><?php esc_html_e( 'Details', 'lccl-de' ); ?></th>
						</tr>
					</thead>
					<tbody data-tx-rows>
						<!-- Populated via JavaScript -->
					</tbody>
				</table>
			</div>

			<!-- Pagination Footer -->
			<div class="lccl-paydash__pager-bar" data-pager-bar hidden>
				<span class="lccl-paydash__page-status" data-page-status></span>
				<div class="lccl-paydash__pager" data-pager></div>
			</div>
		</div>

	</section>

</div>
