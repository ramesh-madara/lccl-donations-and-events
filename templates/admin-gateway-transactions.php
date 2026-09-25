<?php
/**
 * Payment Transactions list table & detailed response viewer.
 *
 * Rendered inside the 'Payment Transactions' subtab of Payment Gateway.
 * Displays all payments configured across the site:
 *  - Online Donations
 *  - Membership Fee Payments (Single & Family)
 *  - Project Sponsorships
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$payments_table     = LCCL_DE_Schema::payments_table();
$sponsorships_table = LCCL_DE_Schema::sponsorships_table();
$sponsorships_exist = LCCL_DE_Schema::sponsorships_exist();

// ------------------------------------------------------------------
// Build Unified SQL Query across all payments
// ------------------------------------------------------------------
$union_sql = "
	(
		SELECT
			id,
			order_ref,
			member_first_name AS first_name,
			member_last_name AS last_name,
			member_email AS email,
			member_phone AS phone,
			membership_type AS tx_type,
			family_count,
			'' AS project,
			'' AS project_label,
			'' AS extra_message,
			amount_lkr,
			currency,
			status,
			session_id,
			gateway_receipt,
			gateway_response,
			ip_address,
			created_at,
			paid_at,
			'payments' AS source_table
		FROM `{$payments_table}`
	)
";

if ( $sponsorships_exist ) {
	$union_sql .= "
		UNION ALL
		(
			SELECT
				id,
				order_ref,
				first_name,
				last_name,
				email,
				phone,
				'sponsorship' AS tx_type,
				0 AS family_count,
				project,
				project_label,
				message AS extra_message,
				amount_lkr,
				currency,
				status,
				session_id,
				gateway_receipt,
				gateway_response,
				ip_address,
				created_at,
				paid_at,
				'sponsorships' AS source_table
			FROM `{$sponsorships_table}`
		)
	";
}

// Filter & search parameters from GET.
$status_filter = isset( $_GET['tx_status'] ) ? sanitize_key( wp_unslash( $_GET['tx_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$type_filter   = isset( $_GET['tx_type'] ) ? sanitize_key( wp_unslash( $_GET['tx_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$search_query  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged         = max( 1, (int) ( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$per_page      = 20;
$offset        = ( $paged - 1 ) * $per_page;

// ------------------------------------------------------------------
// KPI Summary Calculations (All Payments Combined)
// ------------------------------------------------------------------
$stats = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	"SELECT
		COUNT(*) as total_count,
		COALESCE(SUM(CASE WHEN status = 'paid' AND (currency = 'LKR' OR currency IS NULL OR currency = '') THEN amount_lkr ELSE 0 END), 0) as total_paid_lkr,
		COALESCE(SUM(CASE WHEN status = 'paid' AND currency = 'USD' THEN amount_lkr ELSE 0 END), 0) as total_paid_usd,
		COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
		COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count,
		COUNT(CASE WHEN status IN ('pending', 'cancelled') THEN 1 END) as other_count
	FROM ({$union_sql}) AS all_tx", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	ARRAY_A
);

$total_tx_all     = isset( $stats['total_count'] ) ? (int) $stats['total_count'] : 0;
$total_paid_lkr   = isset( $stats['total_paid_lkr'] ) ? (float) $stats['total_paid_lkr'] : 0.00;
$total_paid_usd   = isset( $stats['total_paid_usd'] ) ? (float) $stats['total_paid_usd'] : 0.00;
$paid_count_all   = isset( $stats['paid_count'] ) ? (int) $stats['paid_count'] : 0;
$failed_count_all = isset( $stats['failed_count'] ) ? (int) $stats['failed_count'] : 0;
$other_count_all  = isset( $stats['other_count'] ) ? (int) $stats['other_count'] : 0;

// ------------------------------------------------------------------
// Build Filter SQL Query
// ------------------------------------------------------------------
$where_clauses = array( '1=1' );
$params        = array();

if ( '' !== $status_filter ) {
	$where_clauses[] = 'status = %s';
	$params[]        = $status_filter;
}

if ( '' !== $type_filter ) {
	$where_clauses[] = 'tx_type = %s';
	$params[]        = $type_filter;
}

if ( '' !== $search_query ) {
	$like            = '%' . $wpdb->esc_like( $search_query ) . '%';
	$where_clauses[] = '(order_ref LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR gateway_receipt LIKE %s OR project_label LIKE %s)';
	$params[]        = $like;
	$params[]        = $like;
	$params[]        = $like;
	$params[]        = $like;
	$params[]        = $like;
	$params[]        = $like;
	$params[]        = $like;
}

$where_sql = implode( ' AND ', $where_clauses );

// Count matching rows.
if ( ! empty( $params ) ) {
	$total_items = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT COUNT(*) FROM ({$union_sql}) AS all_tx WHERE {$where_sql}", $params ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
} else {
	$total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ({$union_sql}) AS all_tx WHERE 1=1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );

// Fetch items for current page.
$query_params   = $params;
$query_params[] = $per_page;
$query_params[] = $offset;

$items = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT * FROM ({$union_sql}) AS all_tx WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
		$query_params
	),
	ARRAY_A
);
?>

<div class="lccl-tx-container">

	<div class="lccl-gw-panel__header" style="margin-bottom: 20px;">
		<div class="lccl-gw-panel__title-wrap">
			<h3 class="lccl-gw-panel__title"><?php esc_html_e( 'Payment Transactions', 'lccl-de' ); ?></h3>
			<p class="lccl-gw-panel__desc">
				<?php esc_html_e( 'Live unified record of all payments (Donations, Project Sponsorships, and Memberships) processed via Commercial Bank of Ceylon (CBC) Paycenter Web 4.0.', 'lccl-de' ); ?>
			</p>
		</div>
	</div>

	<!-- KPI Metric Cards -->
	<div class="lccl-tx-stats">
		<div class="lccl-tx-card">
			<span class="lccl-tx-card__label"><?php esc_html_e( 'Total Transactions', 'lccl-de' ); ?></span>
			<span class="lccl-tx-card__value"><?php echo esc_html( number_format_i18n( $total_tx_all ) ); ?></span>
			<span class="lccl-tx-card__sub"><?php esc_html_e( 'All configured payments', 'lccl-de' ); ?></span>
		</div>
		<div class="lccl-tx-card lccl-tx-card--success">
			<span class="lccl-tx-card__label"><?php esc_html_e( 'Total Collected', 'lccl-de' ); ?></span>
			<span class="lccl-tx-card__value">
				<?php
				$collected_parts = array();
				if ( $total_paid_lkr > 0 || 0.0 === (float) $total_paid_usd ) {
					$collected_parts[] = 'LKR ' . number_format( $total_paid_lkr, 2 );
				}
				if ( $total_paid_usd > 0 ) {
					$collected_parts[] = 'USD ' . number_format( $total_paid_usd, 2 );
				}
				echo esc_html( implode( ' + ', $collected_parts ) );
				?>
			</span>
			<span class="lccl-tx-card__sub"><?php printf( esc_html__( '%s completed payments', 'lccl-de' ), number_format_i18n( $paid_count_all ) ); ?></span>
		</div>
		<div class="lccl-tx-card lccl-tx-card--danger">
			<span class="lccl-tx-card__label"><?php esc_html_e( 'Declined / Failed', 'lccl-de' ); ?></span>
			<span class="lccl-tx-card__value"><?php echo esc_html( number_format_i18n( $failed_count_all ) ); ?></span>
			<span class="lccl-tx-card__sub"><?php esc_html_e( 'Declined by bank or timeout', 'lccl-de' ); ?></span>
		</div>
		<div class="lccl-tx-card">
			<span class="lccl-tx-card__label"><?php esc_html_e( 'Pending / Cancelled', 'lccl-de' ); ?></span>
			<span class="lccl-tx-card__value"><?php echo esc_html( number_format_i18n( $other_count_all ) ); ?></span>
			<span class="lccl-tx-card__sub"><?php esc_html_e( 'Initiated or abandoned checkout', 'lccl-de' ); ?></span>
		</div>
	</div>

	<!-- Filter & Search Bar -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="lccl-tx-filter-bar">
		<input type="hidden" name="page" value="<?php echo esc_attr( LCCL_DE_Admin_Programs::PAGE ); ?>">
		<input type="hidden" name="tab" value="<?php echo esc_attr( LCCL_DE_Admin_Programs::TAB_GATEWAY ); ?>">
		<input type="hidden" name="subtab" value="transactions">

		<div class="lccl-tx-filter-group">
			<label for="lccl-tx-search" class="screen-reader-text"><?php esc_html_e( 'Search Transactions', 'lccl-de' ); ?></label>
			<input
				type="search"
				id="lccl-tx-search"
				name="s"
				value="<?php echo esc_attr( $search_query ); ?>"
				placeholder="<?php esc_attr_e( 'Search Ref, Name, Email, Project...', 'lccl-de' ); ?>"
				class="regular-text"
				style="width: 280px;"
			>

			<select name="tx_status" id="lccl-tx-status" aria-label="<?php esc_attr_e( 'Filter by status', 'lccl-de' ); ?>">
				<option value=""><?php esc_html_e( 'All Statuses', 'lccl-de' ); ?></option>
				<option value="paid" <?php selected( $status_filter, 'paid' ); ?>><?php esc_html_e( 'Paid (Approved)', 'lccl-de' ); ?></option>
				<option value="failed" <?php selected( $status_filter, 'failed' ); ?>><?php esc_html_e( 'Declined / Failed', 'lccl-de' ); ?></option>
				<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending Checkout', 'lccl-de' ); ?></option>
				<option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'lccl-de' ); ?></option>
			</select>

			<select name="tx_type" id="lccl-tx-type" aria-label="<?php esc_attr_e( 'Filter by payment type', 'lccl-de' ); ?>">
				<option value=""><?php esc_html_e( 'All Payment Types', 'lccl-de' ); ?></option>
				<option value="donation" <?php selected( $type_filter, 'donation' ); ?>><?php esc_html_e( 'Donations', 'lccl-de' ); ?></option>
				<option value="sponsorship" <?php selected( $type_filter, 'sponsorship' ); ?>><?php esc_html_e( 'Project Sponsorships', 'lccl-de' ); ?></option>
				<option value="member" <?php selected( $type_filter, 'member' ); ?>><?php esc_html_e( 'Annual Membership (Single)', 'lccl-de' ); ?></option>
				<option value="family" <?php selected( $type_filter, 'family' ); ?>><?php esc_html_e( 'Annual Membership (Family)', 'lccl-de' ); ?></option>
			</select>

			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Filter', 'lccl-de' ); ?></button>

			<?php if ( '' !== $search_query || '' !== $status_filter || '' !== $type_filter ) : ?>
				<a href="<?php echo esc_url( LCCL_DE_Admin_Programs::gateway_url( array( 'subtab' => 'transactions' ) ) ); ?>" class="button">
					<?php esc_html_e( 'Reset Filters', 'lccl-de' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<div class="lccl-tx-count-label">
			<?php printf( esc_html__( '%s transactions found', 'lccl-de' ), '<strong>' . esc_html( number_format_i18n( $total_items ) ) . '</strong>' ); ?>
		</div>
	</form>

	<!-- Transactions Table -->
	<div class="lccl-tx-table-wrap">
		<table class="wp-list-table widefat fixed striped table-view-list lccl-tx-table">
			<thead>
				<tr>
					<th scope="col" style="width: 130px;"><?php esc_html_e( 'Date / Time', 'lccl-de' ); ?></th>
					<th scope="col" style="width: 180px;"><?php esc_html_e( 'Order Reference', 'lccl-de' ); ?></th>
					<th scope="col" style="width: 140px;"><?php esc_html_e( 'Payment Type', 'lccl-de' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Payer / Donor / Sponsor', 'lccl-de' ); ?></th>
					<th scope="col" style="width: 120px;"><?php esc_html_e( 'Amount', 'lccl-de' ); ?></th>
					<th scope="col" style="width: 100px;"><?php esc_html_e( 'Status', 'lccl-de' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Bank Receipt / Gateway Response', 'lccl-de' ); ?></th>
					<th scope="col" style="width: 80px; text-align: center;"><?php esc_html_e( 'Details', 'lccl-de' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr>
						<td colspan="8" style="text-align: center; padding: 28px 0; color: #646970;">
							<?php esc_html_e( 'No transactions found matching your criteria.', 'lccl-de' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $items as $row ) : ?>
						<?php
						$row_id       = (int) $row['id'];
						$source_table = (string) $row['source_table'];
						$row_key      = $source_table . '-' . $row_id;
						$status       = (string) $row['status'];
						$type         = (string) $row['tx_type'];
						$name         = trim( $row['first_name'] . ' ' . $row['last_name'] );
						$email        = (string) $row['email'];
						$phone        = (string) $row['phone'];
						$order_ref    = (string) $row['order_ref'];
						$receipt      = (string) $row['gateway_receipt'];
						$currency     = ! empty( $row['currency'] ) ? strtoupper( $row['currency'] ) : 'LKR';
						$amount_fmt   = number_format( (float) $row['amount_lkr'], 2 );
						$date_str     = mysql2date( 'd M Y, H:i', $row['created_at'] );
						$gw_raw       = (string) $row['gateway_response'];
						$gw_json      = json_decode( $gw_raw, true );

						// Determine bank response code & text
						$resp_code = '';
						$resp_text = '';
						if ( is_array( $gw_json ) ) {
							if ( isset( $gw_json['responseCode'] ) ) {
								$resp_code = (string) $gw_json['responseCode'];
							}
							if ( isset( $gw_json['responseText'] ) ) {
								$resp_text = (string) $gw_json['responseText'];
							}
						}

						// Badge styling for status
						$status_class = 'lccl-badge--grey';
						$status_label = ucfirst( $status );
						if ( 'paid' === $status ) {
							$status_class = 'lccl-badge--green';
							$status_label = __( 'Paid', 'lccl-de' );
						} elseif ( 'failed' === $status ) {
							$status_class = 'lccl-badge--red';
							$status_label = __( 'Declined', 'lccl-de' );
						} elseif ( 'pending' === $status ) {
							$status_class = 'lccl-badge--yellow';
							$status_label = __( 'Pending', 'lccl-de' );
						}

						// Badge styling for payment type
						$type_class = 'lccl-type--other';
						$type_label = __( 'Payment', 'lccl-de' );
						if ( 'donation' === $type ) {
							$type_class = 'lccl-type--donation';
							$type_label = __( 'Donation', 'lccl-de' );
						} elseif ( 'sponsorship' === $type ) {
							$type_class = 'lccl-type--sponsorship';
							$type_label = __( 'Sponsorship', 'lccl-de' );
						} elseif ( 'member' === $type ) {
							$type_class = 'lccl-type--member';
							$type_label = __( 'Member', 'lccl-de' );
						} elseif ( 'family' === $type ) {
							$type_class = 'lccl-type--family';
							$type_label = sprintf( __( 'Family (%d)', 'lccl-de' ), (int) $row['family_count'] );
						}
						?>
						<tr id="tx-row-<?php echo esc_attr( $row_key ); ?>">
							<td>
								<span style="font-size: 13px; font-weight: 500; color: #2c3338;"><?php echo esc_html( $date_str ); ?></span>
								<?php if ( ! empty( $row['paid_at'] ) && 'paid' === $status ) : ?>
									<br><small style="color: #007017;"><?php printf( esc_html__( 'Paid: %s', 'lccl-de' ), esc_html( mysql2date( 'H:i', $row['paid_at'] ) ) ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<code style="font-size: 12px; font-weight: 600;"><?php echo esc_html( $order_ref ); ?></code>
							</td>
							<td>
								<span class="lccl-type-badge <?php echo esc_attr( $type_class ); ?>">
									<?php echo esc_html( $type_label ); ?>
								</span>
								<?php if ( 'sponsorship' === $type && ! empty( $row['project_label'] ) ) : ?>
									<div style="font-size: 11px; color: #646970; margin-top: 3px; max-width: 140px; line-height: 1.25;">
										<?php echo esc_html( $row['project_label'] ); ?>
									</div>
								<?php endif; ?>
							</td>
							<td>
								<strong><?php echo esc_html( $name ?: '—' ); ?></strong>
								<?php if ( $email ) : ?>
									<br><a href="mailto:<?php echo esc_attr( $email ); ?>" style="color: #2271b1; text-decoration: none; font-size: 12px;"><?php echo esc_html( $email ); ?></a>
								<?php endif; ?>
								<?php if ( $phone ) : ?>
									<br><small style="color: #646970;"><?php echo esc_html( $phone ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<strong style="font-size: 13px; color: #1d2327;"><?php echo esc_html( $currency . ' ' . $amount_fmt ); ?></strong>
							</td>
							<td>
								<span class="lccl-badge <?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
							</td>
							<td>
								<?php if ( $receipt ) : ?>
									<div style="font-size: 12px; margin-bottom: 3px;">
										<span style="color: #646970;"><?php esc_html_e( 'Receipt:', 'lccl-de' ); ?></span>
										<code style="font-weight: 700; color: #007017;"><?php echo esc_html( $receipt ); ?></code>
									</div>
								<?php endif; ?>

								<?php if ( '' !== $resp_code || '' !== $resp_text ) : ?>
									<div class="lccl-resp-tag <?php echo ( '00' === $resp_code ) ? 'lccl-resp-tag--ok' : 'lccl-resp-tag--err'; ?>">
										<strong><?php echo esc_html( $resp_code ?: 'ERR' ); ?>:</strong>
										<span><?php echo esc_html( $resp_text ?: __( 'No response message', 'lccl-de' ) ); ?></span>
									</div>
								<?php elseif ( '' !== $gw_raw ) : ?>
									<div class="lccl-resp-tag lccl-resp-tag--warn" title="<?php echo esc_attr( $gw_raw ); ?>">
										<span><?php echo esc_html( wp_trim_words( $gw_raw, 8, '...' ) ); ?></span>
									</div>
								<?php else : ?>
									<span style="color: #8c8f94; font-size: 12px;">—</span>
								<?php endif; ?>
							</td>
							<td style="text-align: center;">
								<button
									type="button"
									class="button button-small lccl-tx-toggle-btn"
									data-target="tx-detail-<?php echo esc_attr( $row_key ); ?>"
									aria-expanded="false"
									title="<?php esc_attr_e( 'View full technical details', 'lccl-de' ); ?>"
								>
									<?php esc_html_e( 'View', 'lccl-de' ); ?>
								</button>
							</td>
						</tr>

						<!-- Expandable Details Row -->
						<tr id="tx-detail-<?php echo esc_attr( $row_key ); ?>" class="lccl-tx-detail-row" hidden>
							<td colspan="8">
								<div class="lccl-tx-detail-box">
									<div class="lccl-tx-detail-grid">
										<div>
											<h4><?php esc_html_e( 'Transaction Metadata', 'lccl-de' ); ?></h4>
											<ul>
												<li><strong><?php esc_html_e( 'Record Source:', 'lccl-de' ); ?></strong> <?php echo esc_html( 'sponsorships' === $source_table ? __( 'Project Sponsorship', 'lccl-de' ) : ( 'donation' === $type ? __( 'Online Donation', 'lccl-de' ) : __( 'Membership Registration', 'lccl-de' ) ) ); ?> (#<?php echo esc_html( $row_id ); ?>)</li>
												<li><strong><?php esc_html_e( 'Order Reference:', 'lccl-de' ); ?></strong> <code><?php echo esc_html( $order_ref ); ?></code></li>
												<li><strong><?php esc_html_e( 'Payer / Sponsor:', 'lccl-de' ); ?></strong> <?php echo esc_html( $name ); ?></li>
												<li><strong><?php esc_html_e( 'Email:', 'lccl-de' ); ?></strong> <?php echo esc_html( $email ?: '—' ); ?></li>
												<li><strong><?php esc_html_e( 'Phone:', 'lccl-de' ); ?></strong> <?php echo esc_html( $phone ?: '—' ); ?></li>
												<li><strong><?php esc_html_e( 'Client IP:', 'lccl-de' ); ?></strong> <?php echo esc_html( $row['ip_address'] ?: '—' ); ?></li>
												<li><strong><?php esc_html_e( 'Session / ReqID:', 'lccl-de' ); ?></strong> <code><?php echo esc_html( $row['session_id'] ?: '—' ); ?></code></li>
											</ul>
										</div>

										<div>
											<h4><?php esc_html_e( 'Payment & Bank Details', 'lccl-de' ); ?></h4>
											<ul>
												<li><strong><?php esc_html_e( 'Amount:', 'lccl-de' ); ?></strong> <?php echo esc_html( $currency . ' ' . $amount_fmt ); ?></li>
												<li><strong><?php esc_html_e( 'Status:', 'lccl-de' ); ?></strong> <?php echo esc_html( strtoupper( $status ) ); ?></li>
												<li><strong><?php esc_html_e( 'Receipt:', 'lccl-de' ); ?></strong> <?php echo esc_html( $receipt ?: 'None' ); ?></li>
												<li><strong><?php esc_html_e( 'Bank Code:', 'lccl-de' ); ?></strong> <code><?php echo esc_html( $resp_code ?: '—' ); ?></code></li>
												<li><strong><?php esc_html_e( 'Bank Description:', 'lccl-de' ); ?></strong> <?php echo esc_html( $resp_text ?: '—' ); ?></li>
												<li><strong><?php esc_html_e( 'Created At:', 'lccl-de' ); ?></strong> <?php echo esc_html( $row['created_at'] ); ?></li>
												<li><strong><?php esc_html_e( 'Paid At:', 'lccl-de' ); ?></strong> <?php echo esc_html( $row['paid_at'] ?: '—' ); ?></li>
											</ul>
										</div>
									</div>

									<?php if ( 'sponsorship' === $type ) : ?>
										<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
											<h4><?php esc_html_e( 'Project Sponsorship Details', 'lccl-de' ); ?></h4>
											<p><strong><?php esc_html_e( 'Project:', 'lccl-de' ); ?></strong> <?php echo esc_html( $row['project_label'] ?: $row['project'] ); ?></p>
											<?php if ( ! empty( $row['extra_message'] ) ) : ?>
												<p><strong><?php esc_html_e( 'Sponsor Message:', 'lccl-de' ); ?></strong> <em>"<?php echo esc_html( $row['extra_message'] ); ?>"</em></p>
											<?php endif; ?>
										</div>
									<?php elseif ( is_array( $gw_json ) && ( ! empty( $gw_json['causes'] ) || ! empty( $gw_json['message'] ) ) ) : ?>
										<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
											<h4><?php esc_html_e( 'Donation Details', 'lccl-de' ); ?></h4>
											<?php if ( ! empty( $gw_json['causes'] ) ) : ?>
												<p><strong><?php esc_html_e( 'Selected Causes:', 'lccl-de' ); ?></strong> <?php echo esc_html( implode( ', ', (array) $gw_json['causes'] ) ); ?></p>
											<?php endif; ?>
											<?php if ( ! empty( $gw_json['message'] ) ) : ?>
												<p><strong><?php esc_html_e( 'Donor Message:', 'lccl-de' ); ?></strong> <em>"<?php echo esc_html( $gw_json['message'] ); ?>"</em></p>
											<?php endif; ?>
										</div>
									<?php elseif ( is_array( $gw_json ) && ! empty( $gw_json['breakdown'] ) ) : ?>
										<?php $bk = $gw_json['breakdown']; ?>
										<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
											<h4><?php esc_html_e( 'Membership Fee Breakdown (Saved at Checkout)', 'lccl-de' ); ?></h4>
											<div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px 14px; margin-top: 6px; font-size: 12px; max-width: 580px;">
												<div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
													<span><strong><?php esc_html_e( 'Membership Type:', 'lccl-de' ); ?></strong> <?php echo esc_html( ucfirst( ! empty( $bk['membership_type'] ) ? $bk['membership_type'] : $type ) ); ?> (<?php echo (int) ( ! empty( $bk['members_count'] ) ? $bk['members_count'] : 1 ); ?> <?php esc_html_e( 'member(s)', 'lccl-de' ); ?>)</span>
													<?php if ( ! empty( $bk['rates']['exchange_rate'] ) ) : ?>
														<span style="color: #64748b;"><?php printf( esc_html__( 'Rate: 1 USD = LKR %s', 'lccl-de' ), esc_html( number_format( (float) $bk['rates']['exchange_rate'], 2 ) ) ); ?></span>
													<?php endif; ?>
												</div>
												<ul style="margin: 0; padding: 0; list-style: none; line-height: 1.7; color: #475569;">
													<li style="display: flex; justify-content: space-between;">
														<span><?php esc_html_e( 'International Household Fee (USD):', 'lccl-de' ); ?></span>
														<strong style="color: #0f172a; font-family: monospace;">LKR <?php echo esc_html( number_format( (float) ( isset( $bk['international_main_lkr'] ) ? $bk['international_main_lkr'] : 0 ), 2 ) ); ?></strong>
													</li>
													<?php if ( ! empty( $bk['additional_members'] ) && (int) $bk['additional_members'] > 0 ) : ?>
														<li style="display: flex; justify-content: space-between;">
															<span><?php printf( esc_html__( 'Family Members (%d × USD fee):', 'lccl-de' ), (int) $bk['additional_members'] ); ?></span>
															<strong style="color: #0f172a; font-family: monospace;">LKR <?php echo esc_html( number_format( (float) ( isset( $bk['family_fee_lkr'] ) ? $bk['family_fee_lkr'] : 0 ), 2 ) ); ?></strong>
														</li>
													<?php endif; ?>
													<li style="display: flex; justify-content: space-between;">
														<span><?php printf( esc_html__( 'District 306 D6 Dues (%d × member):', 'lccl-de' ), (int) ( ! empty( $bk['members_count'] ) ? $bk['members_count'] : 1 ) ); ?></span>
														<strong style="color: #0f172a; font-family: monospace;">LKR <?php echo esc_html( number_format( (float) ( isset( $bk['district_total_lkr'] ) ? $bk['district_total_lkr'] : 0 ), 2 ) ); ?></strong>
													</li>
													<li style="display: flex; justify-content: space-between;">
														<span><?php esc_html_e( 'Club Administration Payment:', 'lccl-de' ); ?></span>
														<strong style="color: #0f172a; font-family: monospace;">LKR <?php echo esc_html( number_format( (float) ( isset( $bk['club_fee_lkr'] ) ? $bk['club_fee_lkr'] : 0 ), 2 ) ); ?></strong>
													</li>
													<li style="display: flex; justify-content: space-between; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #cbd5e1; font-size: 13px;">
														<span style="font-weight: 700; color: #002b49;"><?php esc_html_e( 'Calculated Total:', 'lccl-de' ); ?></span>
														<strong style="color: #002b49; font-size: 13px; font-family: monospace;">LKR <?php echo esc_html( number_format( (float) ( isset( $bk['total_lkr'] ) ? $bk['total_lkr'] : 0 ), 2 ) ); ?></strong>
													</li>
												</ul>
											</div>
										</div>
									<?php endif; ?>

									<?php if ( ! empty( $gw_raw ) ) : ?>
										<div style="margin-top: 14px;">
											<h4><?php esc_html_e( 'Raw Gateway Response (JSON / Debug)', 'lccl-de' ); ?></h4>
											<pre style="background: #0f172a; color: #38bdf8; padding: 12px 14px; border-radius: 6px; font-size: 11px; overflow-x: auto; max-height: 240px; margin: 4px 0 0;"><?php
												if ( is_array( $gw_json ) ) {
													echo esc_html( wp_json_encode( $gw_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
												} else {
													echo esc_html( $gw_raw );
												}
											?></pre>
										</div>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom" style="margin-top: 16px;">
			<div class="tablenav-pages">
				<span class="displaying-num"><?php printf( esc_html__( '%s items', 'lccl-de' ), number_format_i18n( $total_items ) ); ?></span>
				<span class="pagination-links">
					<?php
					$base_page_url = LCCL_DE_Admin_Programs::gateway_url(
						array(
							'subtab'    => 'transactions',
							's'         => $search_query,
							'tx_status' => $status_filter,
							'tx_type'   => $type_filter,
						)
					);

					if ( $paged > 1 ) {
						printf(
							'<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">&lsaquo;</span></a>',
							esc_url( add_query_arg( 'paged', $paged - 1, $base_page_url ) ),
							esc_html__( 'Previous page', 'lccl-de' )
						);
					}

					printf(
						'<span class="paging-input"><span class="tablenav-paging-text">%s</span></span>',
						sprintf(
							/* translators: 1: current page, 2: total pages */
							esc_html__( '%1$s of %2$s', 'lccl-de' ),
							'<span class="current-page">' . esc_html( $paged ) . '</span>',
							'<span class="total-pages">' . esc_html( $total_pages ) . '</span>'
						)
					);

					if ( $paged < $total_pages ) {
						printf(
							'<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">&rsaquo;</span></a>',
							esc_url( add_query_arg( 'paged', $paged + 1, $base_page_url ) ),
							esc_html__( 'Next page', 'lccl-de' )
						);
					}
					?>
				</span>
			</div>
		</div>
	<?php endif; ?>

</div>

<!-- Styles for Transactions Log -->
<style>
.lccl-tx-container {
	margin-top: 8px;
}
.lccl-tx-stats {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 16px;
	margin-bottom: 24px;
}
.lccl-tx-card {
	background: #ffffff;
	border: 1px solid #c3c4c7;
	border-radius: 6px;
	padding: 16px 18px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.lccl-tx-card--success {
	border-left: 4px solid #00a32a;
}
.lccl-tx-card--danger {
	border-left: 4px solid #d63638;
}
.lccl-tx-card__label {
	display: block;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: #646970;
	margin-bottom: 6px;
}
.lccl-tx-card__value {
	display: block;
	font-size: 24px;
	font-weight: 700;
	color: #1d2327;
	line-height: 1.2;
}
.lccl-tx-card__sub {
	display: block;
	font-size: 11px;
	color: #8c8f94;
	margin-top: 4px;
}

.lccl-tx-filter-bar {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	background: #ffffff;
	border: 1px solid #c3c4c7;
	border-radius: 6px;
	padding: 12px 16px;
	margin-bottom: 16px;
}
.lccl-tx-filter-group {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}
.lccl-tx-count-label {
	font-size: 13px;
	color: #50575e;
}

.lccl-tx-table-wrap {
	border-radius: 6px;
	overflow: hidden;
	border: 1px solid #c3c4c7;
	box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.lccl-tx-table th {
	font-weight: 600;
	color: #2c3338;
	padding: 10px 12px;
}
.lccl-tx-table td {
	padding: 10px 12px;
	vertical-align: middle;
}

/* Badges */
.lccl-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 4px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}
.lccl-badge--green {
	background: #edfaef;
	color: #007017;
	border: 1px solid #b6e9bb;
}
.lccl-badge--red {
	background: #fcf0f1;
	color: #b32d2e;
	border: 1px solid #f6c8ca;
}
.lccl-badge--yellow {
	background: #fff8e5;
	color: #996800;
	border: 1px solid #fae29f;
}
.lccl-badge--grey {
	background: #f0f0f1;
	color: #50575e;
	border: 1px solid #dcdcde;
}

.lccl-type-badge {
	display: inline-block;
	padding: 2px 7px;
	border-radius: 4px;
	font-size: 11px;
	font-weight: 600;
}
.lccl-type--donation {
	background: #e8f4fc;
	color: #135e96;
}
.lccl-type--sponsorship {
	background: #fffbeb;
	color: #92400e;
	border: 1px solid #fde68a;
}
.lccl-type--member {
	background: #f4e8fc;
	color: #721396;
}
.lccl-type--family {
	background: #e8fcf6;
	color: #0d8363;
}
.lccl-type--other {
	background: #f0f0f1;
	color: #50575e;
}

/* Gateway Response Tag */
.lccl-resp-tag {
	display: inline-block;
	padding: 3px 7px;
	border-radius: 4px;
	font-size: 11px;
	line-height: 1.4;
}
.lccl-resp-tag--ok {
	background: #edfaef;
	color: #007017;
	border: 1px solid #c2ebc6;
}
.lccl-resp-tag--err {
	background: #fcf0f1;
	color: #b32d2e;
	border: 1px solid #f6c8ca;
}
.lccl-resp-tag--warn {
	background: #fef9e7;
	color: #8a6d3b;
	border: 1px solid #faebcc;
}

/* Expandable Details Box */
.lccl-tx-detail-box {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 6px;
	padding: 16px 20px;
	margin: 6px 0;
}
.lccl-tx-detail-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
	gap: 20px;
}
.lccl-tx-detail-box h4 {
	margin: 0 0 10px;
	font-size: 12px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: #334155;
}
.lccl-tx-detail-box ul {
	margin: 0;
	padding: 0;
	list-style: none;
}
.lccl-tx-detail-box li {
	margin-bottom: 6px;
	font-size: 13px;
	color: #475569;
}
</style>

<!-- Toggle detail drawer script -->
<script>
( function() {
	document.addEventListener( 'click', function( event ) {
		var btn = event.target && event.target.closest ? event.target.closest( '.lccl-tx-toggle-btn' ) : null;
		if ( ! btn ) {
			return;
		}
		event.preventDefault();
		var targetId = btn.getAttribute( 'data-target' );
		var targetRow = document.getElementById( targetId );
		if ( ! targetRow ) {
			return;
		}
		var isHidden = targetRow.hasAttribute( 'hidden' );
		if ( isHidden ) {
			targetRow.removeAttribute( 'hidden' );
			btn.textContent = '<?php echo esc_js( __( 'Close', 'lccl-de' ) ); ?>';
			btn.setAttribute( 'aria-expanded', 'true' );
		} else {
			targetRow.setAttribute( 'hidden', 'hidden' );
			btn.textContent = '<?php echo esc_js( __( 'View', 'lccl-de' ) ); ?>';
			btn.setAttribute( 'aria-expanded', 'false' );
		}
	} );
} )();
</script>
