<?php
/**
 * Membership fee schedule settings and revision history in wp-admin.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array  $fees    Current membership fee configuration.
 * @var array  $history List of past revisions.
 * @var string $message Flash code ('saved'|'error').
 * @var string $error   Flash error message.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $fees ) || ! is_array( $fees ) ) {
	$fees = LCCL_DE_Settings::get_membership_fees();
}

if ( ! isset( $history ) || ! is_array( $history ) ) {
	$history = LCCL_DE_Settings::get_membership_fees_history();
}

$rate          = isset( $fees['exchange_rate'] ) ? (float) $fees['exchange_rate'] : 330.80;
$household_usd = isset( $fees['principal_usd'] ) ? (float) $fees['principal_usd'] : ( isset( $fees['household_fee_usd'] ) ? (float) $fees['household_fee_usd'] : 50.00 );
$family_usd    = isset( $fees['family_usd'] ) ? (float) $fees['family_usd'] : ( isset( $fees['family_fee_usd'] ) ? (float) $fees['family_fee_usd'] : 25.00 );
$district_lkr  = isset( $fees['district_lkr'] ) ? (float) $fees['district_lkr'] : ( isset( $fees['district_fee_lkr'] ) ? (float) $fees['district_fee_lkr'] : 4561.00 );
$club_lkr      = isset( $fees['club_lkr'] ) ? (float) $fees['club_lkr'] : ( isset( $fees['club_fee_lkr'] ) ? (float) $fees['club_fee_lkr'] : 6000.00 );

// Preview calculations.
$single_intl     = $household_usd * $rate;
$single_district = $district_lkr;
$single_club     = $club_lkr;
$single_total    = $single_intl + $single_district + $single_club;

$family2_intl     = $household_usd * $rate;
$family2_addl     = 1 * $family_usd * $rate;
$family2_district = 2 * $district_lkr;
$family2_total    = $family2_intl + $family2_addl + $family2_district + $single_club;
?>
<nav class="lccl-prog__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'lccl-de' ); ?>">
	<span class="lccl-prog__crumbs-current"><?php esc_html_e( 'Membership Fees', 'lccl-de' ); ?></span>
</nav>

<?php if ( 'saved' === $message ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Membership fee schedule saved successfully. Public payment forms now reflect these updated rates.', 'lccl-de' ); ?></p>
	</div>
<?php elseif ( 'error' === $message && ! empty( $error ) ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><?php echo esc_html( $error ); ?></p>
	</div>
<?php endif; ?>

<div class="lccl-prog__section">
	<div style="margin-bottom: 20px;">
		<h2 class="lccl-prog__panel-title" style="margin-bottom: 6px;"><?php esc_html_e( 'Membership Fee Schedule', 'lccl-de' ); ?></h2>
		<p class="lccl-prog__hint" style="margin: 0; max-width: 820px; line-height: 1.5; color: #475569;">
			<?php esc_html_e( 'Configure annual membership dues, USD conversion rates, and district & club components. All adjustments update the public membership fee payment form in real-time and are logged in the revision history below.', 'lccl-de' ); ?>
		</p>
		<?php if ( ! empty( $fees['updated_at'] ) ) : ?>
			<p style="margin: 6px 0 0; font-size: 12px; color: #64748b;">
				<?php
				printf(
					/* translators: 1: date and time, 2: user display name */
					esc_html__( 'Last modified on %1$s by %2$s', 'lccl-de' ),
					esc_html( mysql2date( 'd M Y, H:i', $fees['updated_at'] ) ),
					esc_html( ! empty( $fees['updated_by'] ) ? $fees['updated_by'] : __( 'Administrator', 'lccl-de' ) )
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<div style="display: grid; grid-template-columns: minmax(320px, 1.25fr) minmax(300px, 1fr); gap: 24px; align-items: start; margin-bottom: 28px;">
		<!-- Fee Settings Form -->
		<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 22px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
			<h3 style="margin: 0 0 16px; font-size: 15px; font-weight: 700; color: #002b49; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
				<?php esc_html_e( 'Fee Configuration Rates', 'lccl-de' ); ?>
			</h3>

			<form method="post" action="<?php echo esc_url( LCCL_DE_Admin_Programs::membership_fees_url() ); ?>" id="lccl-fees-form" novalidate>
				<?php wp_nonce_field( 'lccl_de_membership_fees_save', 'lccl_de_membership_fees_nonce' ); ?>
				<input type="hidden" name="lccl_de_membership_fees_save" value="1">

				<table class="form-table" style="margin: 0; width: 100%;">
					<tbody>
						<tr>
							<th scope="row" style="padding: 12px 10px 12px 0; width: 44%;">
								<label for="lccl-rate" style="font-weight: 600; color: #1e293b;">
									<?php esc_html_e( 'Exchange Rate (LKR)', 'lccl-de' ); ?>
								</label>
								<div style="font-size: 11px; font-weight: 400; color: #64748b; margin-top: 2px;">
									<?php esc_html_e( '1 USD in Sri Lankan Rupees', 'lccl-de' ); ?>
								</div>
							</th>
							<td style="padding: 12px 0;">
								<div style="display: flex; align-items: center; gap: 8px;">
									<span style="font-weight: 600; color: #64748b; font-size: 13px;">LKR</span>
									<input
										type="number"
										step="0.01"
										min="1"
										id="lccl-rate"
										name="exchange_rate"
										value="<?php echo esc_attr( number_format( $rate, 2, '.', '' ) ); ?>"
										class="regular-text"
										style="width: 140px; font-weight: 600; font-family: monospace;"
										required
									>
								</div>
							</td>
						</tr>

						<tr>
							<th scope="row" style="padding: 12px 10px 12px 0;">
								<label for="lccl-household-usd" style="font-weight: 600; color: #1e293b;">
									<?php esc_html_e( 'Household Fee (USD)', 'lccl-de' ); ?>
								</label>
								<div style="font-size: 11px; font-weight: 400; color: #64748b; margin-top: 2px;">
									<?php esc_html_e( 'Principal member international dues', 'lccl-de' ); ?>
								</div>
							</th>
							<td style="padding: 12px 0;">
								<div style="display: flex; align-items: center; gap: 8px;">
									<span style="font-weight: 600; color: #64748b; font-size: 13px;">USD</span>
									<input
										type="number"
										step="0.01"
										min="0"
										id="lccl-household-usd"
										name="household_fee_usd"
										value="<?php echo esc_attr( number_format( $household_usd, 2, '.', '' ) ); ?>"
										class="regular-text"
										style="width: 140px; font-weight: 600; font-family: monospace;"
										required
									>
								</div>
							</td>
						</tr>

						<tr>
							<th scope="row" style="padding: 12px 10px 12px 0;">
								<label for="lccl-family-usd" style="font-weight: 600; color: #1e293b;">
									<?php esc_html_e( 'Family Member Fee (USD)', 'lccl-de' ); ?>
								</label>
								<div style="font-size: 11px; font-weight: 400; color: #64748b; margin-top: 2px;">
									<?php esc_html_e( 'Per additional family member', 'lccl-de' ); ?>
								</div>
							</th>
							<td style="padding: 12px 0;">
								<div style="display: flex; align-items: center; gap: 8px;">
									<span style="font-weight: 600; color: #64748b; font-size: 13px;">USD</span>
									<input
										type="number"
										step="0.01"
										min="0"
										id="lccl-family-usd"
										name="family_fee_usd"
										value="<?php echo esc_attr( number_format( $family_usd, 2, '.', '' ) ); ?>"
										class="regular-text"
										style="width: 140px; font-weight: 600; font-family: monospace;"
										required
									>
								</div>
							</td>
						</tr>

						<tr>
							<th scope="row" style="padding: 12px 10px 12px 0;">
								<label for="lccl-district-lkr" style="font-weight: 600; color: #1e293b;">
									<?php esc_html_e( 'District Payment (LKR)', 'lccl-de' ); ?>
								</label>
								<div style="font-size: 11px; font-weight: 400; color: #64748b; margin-top: 2px;">
									<?php esc_html_e( 'Per member district dues', 'lccl-de' ); ?>
								</div>
							</th>
							<td style="padding: 12px 0;">
								<div style="display: flex; align-items: center; gap: 8px;">
									<span style="font-weight: 600; color: #64748b; font-size: 13px;">LKR</span>
									<input
										type="number"
										step="0.01"
										min="0"
										id="lccl-district-lkr"
										name="district_fee_lkr"
										value="<?php echo esc_attr( number_format( $district_lkr, 2, '.', '' ) ); ?>"
										class="regular-text"
										style="width: 140px; font-weight: 600; font-family: monospace;"
										required
									>
								</div>
							</td>
						</tr>

						<tr>
							<th scope="row" style="padding: 12px 10px 12px 0;">
								<label for="lccl-club-lkr" style="font-weight: 600; color: #1e293b;">
									<?php esc_html_e( 'Club Payment (LKR)', 'lccl-de' ); ?>
								</label>
								<div style="font-size: 11px; font-weight: 400; color: #64748b; margin-top: 2px;">
									<?php esc_html_e( 'Fixed club fee per membership', 'lccl-de' ); ?>
								</div>
							</th>
							<td style="padding: 12px 0;">
								<div style="display: flex; align-items: center; gap: 8px;">
									<span style="font-weight: 600; color: #64748b; font-size: 13px;">LKR</span>
									<input
										type="number"
										step="0.01"
										min="0"
										id="lccl-club-lkr"
										name="club_fee_lkr"
										value="<?php echo esc_attr( number_format( $club_lkr, 2, '.', '' ) ); ?>"
										class="regular-text"
										style="width: 140px; font-weight: 600; font-family: monospace;"
										required
									>
								</div>
							</td>
						</tr>

						<tr>
							<th scope="row" style="padding: 12px 10px 12px 0;">
								<label for="lccl-change-note" style="font-weight: 600; color: #1e293b;">
									<?php esc_html_e( 'Change Note (Optional)', 'lccl-de' ); ?>
								</label>
								<div style="font-size: 11px; font-weight: 400; color: #64748b; margin-top: 2px;">
									<?php esc_html_e( 'Reason recorded in revision history', 'lccl-de' ); ?>
								</div>
							</th>
							<td style="padding: 12px 0;">
								<input
									type="text"
									id="lccl-change-note"
									name="change_note"
									placeholder="<?php esc_attr_e( 'e.g. Annual exchange rate adjustment', 'lccl-de' ); ?>"
									class="regular-text"
									style="width: 100%; max-width: 320px;"
								>
							</td>
						</tr>
					</tbody>
				</table>

				<div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
					<button type="submit" class="button button-primary button-large" style="background: #002b49; border-color: #002b49; font-weight: 600; padding: 0 20px; height: 38px; line-height: 36px;">
						<?php esc_html_e( 'Save Fee Schedule', 'lccl-de' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Live Calculation Preview Card -->
		<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 22px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
			<h3 style="margin: 0 0 4px; font-size: 15px; font-weight: 700; color: #002b49;">
				<?php esc_html_e( 'Live Calculation Preview', 'lccl-de' ); ?>
			</h3>
			<p style="margin: 0 0 16px; font-size: 12px; color: #64748b;">
				<?php esc_html_e( 'Real-time breakdown as displayed on public membership forms:', 'lccl-de' ); ?>
			</p>

			<!-- Single Member Preview -->
			<div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 16px; margin-bottom: 14px;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
					<strong style="color: #002b49; font-size: 13px;"><?php esc_html_e( 'Single Member (1 Person)', 'lccl-de' ); ?></strong>
					<span id="preview-single-badge" style="background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px;">
						<?php esc_html_e( 'Principal Member', 'lccl-de' ); ?>
					</span>
				</div>
				<ul style="margin: 0; padding: 0; list-style: none; font-size: 12px; color: #475569; line-height: 1.8;">
					<li style="display: flex; justify-content: space-between;">
						<span><?php esc_html_e( 'International Fee (USD):', 'lccl-de' ); ?></span>
						<strong id="preview-single-intl" style="color: #0f172a; font-family: monospace;">
							LKR <?php echo esc_html( number_format( $single_intl, 2 ) ); ?>
						</strong>
					</li>
					<li style="display: flex; justify-content: space-between;">
						<span><?php esc_html_e( 'District Dues (1 member):', 'lccl-de' ); ?></span>
						<strong id="preview-single-district" style="color: #0f172a; font-family: monospace;">
							LKR <?php echo esc_html( number_format( $single_district, 2 ) ); ?>
						</strong>
					</li>
					<li style="display: flex; justify-content: space-between;">
						<span><?php esc_html_e( 'Club Payment:', 'lccl-de' ); ?></span>
						<strong id="preview-single-club" style="color: #0f172a; font-family: monospace;">
							LKR <?php echo esc_html( number_format( $single_club, 2 ) ); ?>
						</strong>
					</li>
					<li style="display: flex; justify-content: space-between; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #cbd5e1; font-size: 13px;">
						<span style="font-weight: 700; color: #002b49;"><?php esc_html_e( 'Total Payment:', 'lccl-de' ); ?></span>
						<strong id="preview-single-total" style="color: #002b49; font-size: 14px; font-family: monospace;">
							LKR <?php echo esc_html( number_format( $single_total, 2 ) ); ?>
						</strong>
					</li>
				</ul>
			</div>

			<!-- Family Membership (2 Members) Preview -->
			<div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 16px; margin-bottom: 14px;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
					<strong style="color: #002b49; font-size: 13px;"><?php esc_html_e( 'Family Membership (2 Members)', 'lccl-de' ); ?></strong>
					<span id="preview-family-badge" style="background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px;">
						<?php esc_html_e( 'Principal + 1 Family', 'lccl-de' ); ?>
					</span>
				</div>
				<ul style="margin: 0; padding: 0; list-style: none; font-size: 12px; color: #475569; line-height: 1.8;">
					<li style="display: flex; justify-content: space-between;">
						<span><?php esc_html_e( 'Principal Member (USD):', 'lccl-de' ); ?></span>
						<strong id="preview-family-intl" style="color: #0f172a; font-family: monospace;">
							LKR <?php echo esc_html( number_format( $family2_intl, 2 ) ); ?>
						</strong>
					</li>
					<li style="display: flex; justify-content: space-between;">
						<span><?php esc_html_e( '1 Family Member (USD):', 'lccl-de' ); ?></span>
						<strong id="preview-family-addl" style="color: #0f172a; font-family: monospace;">
							LKR <?php echo esc_html( number_format( $family2_addl, 2 ) ); ?>
						</strong>
					</li>
					<li style="display: flex; justify-content: space-between;">
						<span><?php esc_html_e( 'District Dues (2 members):', 'lccl-de' ); ?></span>
						<strong id="preview-family-district" style="color: #0f172a; font-family: monospace;">
							LKR <?php echo esc_html( number_format( $family2_district, 2 ) ); ?>
						</strong>
					</li>
					<li style="display: flex; justify-content: space-between;">
						<span><?php esc_html_e( 'Club Payment:', 'lccl-de' ); ?></span>
						<strong id="preview-family-club" style="color: #0f172a; font-family: monospace;">
							LKR <?php echo esc_html( number_format( $single_club, 2 ) ); ?>
						</strong>
					</li>
					<li style="display: flex; justify-content: space-between; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #cbd5e1; font-size: 13px;">
						<span style="font-weight: 700; color: #002b49;"><?php esc_html_e( 'Total Payment:', 'lccl-de' ); ?></span>
						<strong id="preview-family-total" style="color: #002b49; font-size: 14px; font-family: monospace;">
							LKR <?php echo esc_html( number_format( $family2_total, 2 ) ); ?>
						</strong>
					</li>
				</ul>
			</div>

			<!-- Dynamic Family Scale Summary -->
			<div style="font-size: 11px; color: #64748b; line-height: 1.5; background: #f1f5f9; padding: 8px 12px; border-radius: 4px;">
				<div style="font-weight: 600; color: #334155; margin-bottom: 3px;"><?php esc_html_e( 'Family Member Increments:', 'lccl-de' ); ?></div>
				<div><?php esc_html_e( 'Each additional family member adds:', 'lccl-de' ); ?></div>
				<div id="preview-increment-text" style="font-weight: 600; color: #002b49; margin-top: 2px;">
					USD <?php echo esc_html( number_format( $family_usd, 2 ) ); ?> (LKR <?php echo esc_html( number_format( $family_usd * $rate, 2 ) ); ?>) + District LKR <?php echo esc_html( number_format( $district_lkr, 2 ) ); ?> = LKR <?php echo esc_html( number_format( ( $family_usd * $rate ) + $district_lkr, 2 ) ); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Revision History (Subtle / Non-prominent Collapsible Section) -->
	<details class="lccl-fee-history-details" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; margin-top: 16px;">
		<summary style="cursor: pointer; font-size: 14px; font-weight: 600; color: #475569; user-select: none;">
			<span><?php esc_html_e( 'Fee Revision History', 'lccl-de' ); ?></span>
			<span style="background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 10px; margin-left: 6px;">
				<?php echo (int) count( $history ); ?>
			</span>
			<span style="font-size: 12px; font-weight: 400; color: #94a3b8; margin-left: 8px;">
				<?php esc_html_e( '(click to expand past modifications)', 'lccl-de' ); ?>
			</span>
		</summary>

		<div style="margin-top: 16px; border-top: 1px solid #f1f5f9; padding-top: 14px;">
			<?php if ( empty( $history ) ) : ?>
				<p style="margin: 0; color: #94a3b8; font-size: 13px; font-style: italic;">
					<?php esc_html_e( 'No revision history recorded yet. Future modifications made through this screen will appear here automatically.', 'lccl-de' ); ?>
				</p>
			<?php else : ?>
				<div style="overflow-x: auto;">
					<table class="widefat striped" style="border: 1px solid #e2e8f0; font-size: 12px; margin-top: 4px;">
						<thead>
							<tr style="background: #f8fafc;">
								<th style="padding: 8px 10px; font-weight: 600; color: #334155;"><?php esc_html_e( 'Date & Time', 'lccl-de' ); ?></th>
								<th style="padding: 8px 10px; font-weight: 600; color: #334155;"><?php esc_html_e( 'Modified By', 'lccl-de' ); ?></th>
								<th style="padding: 8px 10px; font-weight: 600; color: #334155;"><?php esc_html_e( 'Exchange Rate', 'lccl-de' ); ?></th>
								<th style="padding: 8px 10px; font-weight: 600; color: #334155;"><?php esc_html_e( 'Household USD', 'lccl-de' ); ?></th>
								<th style="padding: 8px 10px; font-weight: 600; color: #334155;"><?php esc_html_e( 'Family USD', 'lccl-de' ); ?></th>
								<th style="padding: 8px 10px; font-weight: 600; color: #334155;"><?php esc_html_e( 'District LKR', 'lccl-de' ); ?></th>
								<th style="padding: 8px 10px; font-weight: 600; color: #334155;"><?php esc_html_e( 'Club LKR', 'lccl-de' ); ?></th>
								<th style="padding: 8px 10px; font-weight: 600; color: #334155;"><?php esc_html_e( 'Single Member Total', 'lccl-de' ); ?></th>
								<th style="padding: 8px 10px; font-weight: 600; color: #334155;"><?php esc_html_e( 'Note / Reason', 'lccl-de' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $history as $rev ) : ?>
								<?php
								$r_rate  = isset( $rev['exchange_rate'] ) ? (float) $rev['exchange_rate'] : 0.0;
								$r_house = isset( $rev['principal_usd'] ) ? (float) $rev['principal_usd'] : ( isset( $rev['household_fee_usd'] ) ? (float) $rev['household_fee_usd'] : 0.0 );
								$r_fam   = isset( $rev['family_usd'] ) ? (float) $rev['family_usd'] : ( isset( $rev['family_fee_usd'] ) ? (float) $rev['family_fee_usd'] : 0.0 );
								$r_dist  = isset( $rev['district_lkr'] ) ? (float) $rev['district_lkr'] : ( isset( $rev['district_fee_lkr'] ) ? (float) $rev['district_fee_lkr'] : 0.0 );
								$r_club  = isset( $rev['club_lkr'] ) ? (float) $rev['club_lkr'] : ( isset( $rev['club_fee_lkr'] ) ? (float) $rev['club_fee_lkr'] : 0.0 );
								$r_total = ( $r_house * $r_rate ) + $r_dist + $r_club;
								$r_user  = ! empty( $rev['user_name'] ) ? $rev['user_name'] : ( ! empty( $rev['saved_by_name'] ) ? $rev['saved_by_name'] : __( 'Administrator', 'lccl-de' ) );
								$r_date  = ! empty( $rev['timestamp'] ) ? mysql2date( 'd M Y, H:i', $rev['timestamp'] ) : ( ! empty( $rev['saved_at'] ) ? mysql2date( 'd M Y, H:i', $rev['saved_at'] ) : '—' );
								$r_note  = ! empty( $rev['note'] ) ? $rev['note'] : '—';
								?>
								<tr>
									<td style="padding: 8px 10px; white-space: nowrap; color: #475569;"><?php echo esc_html( $r_date ); ?></td>
									<td style="padding: 8px 10px; font-weight: 600; color: #1e293b;"><?php echo esc_html( $r_user ); ?></td>
									<td style="padding: 8px 10px; font-family: monospace;">LKR <?php echo esc_html( number_format( $r_rate, 2 ) ); ?></td>
									<td style="padding: 8px 10px; font-family: monospace;">USD <?php echo esc_html( number_format( $r_house, 2 ) ); ?></td>
									<td style="padding: 8px 10px; font-family: monospace;">USD <?php echo esc_html( number_format( $r_fam, 2 ) ); ?></td>
									<td style="padding: 8px 10px; font-family: monospace;">LKR <?php echo esc_html( number_format( $r_dist, 2 ) ); ?></td>
									<td style="padding: 8px 10px; font-family: monospace;">LKR <?php echo esc_html( number_format( $r_club, 2 ) ); ?></td>
									<td style="padding: 8px 10px; font-family: monospace; font-weight: 700; color: #002b49;">
										LKR <?php echo esc_html( number_format( $r_total, 2 ) ); ?>
									</td>
									<td style="padding: 8px 10px; color: #64748b; font-style: <?php echo ( '—' === $r_note ) ? 'normal' : 'italic'; ?>;">
										<?php echo esc_html( $r_note ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</details>
</div>

<script>
(function() {
	var form = document.getElementById('lccl-fees-form');
	if (!form) return;

	var rateInput     = document.getElementById('lccl-rate');
	var houseInput    = document.getElementById('lccl-household-usd');
	var familyInput   = document.getElementById('lccl-family-usd');
	var distInput     = document.getElementById('lccl-district-lkr');
	var clubInput     = document.getElementById('lccl-club-lkr');

	var singleIntlEl  = document.getElementById('preview-single-intl');
	var singleDistEl  = document.getElementById('preview-single-district');
	var singleClubEl  = document.getElementById('preview-single-club');
	var singleTotEl   = document.getElementById('preview-single-total');

	var familyIntlEl  = document.getElementById('preview-family-intl');
	var familyAddlEl  = document.getElementById('preview-family-addl');
	var familyDistEl  = document.getElementById('preview-family-district');
	var familyClubEl  = document.getElementById('preview-family-club');
	var familyTotEl   = document.getElementById('preview-family-total');
	var incTextEl     = document.getElementById('preview-increment-text');

	function fmt(n) {
		return Number(n).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function num(el, fb) {
		var v = parseFloat(el ? el.value : 0);
		return isNaN(v) ? fb : v;
	}

	function updatePreview() {
		var rate    = num(rateInput, 330.80);
		var house   = num(houseInput, 50.00);
		var fam     = num(familyInput, 25.00);
		var dist    = num(distInput, 4561.00);
		var club    = num(clubInput, 6000.00);

		// Single
		var sIntl = house * rate;
		var sTot  = sIntl + dist + club;
		if (singleIntlEl) singleIntlEl.textContent = 'LKR ' + fmt(sIntl);
		if (singleDistEl) singleDistEl.textContent = 'LKR ' + fmt(dist);
		if (singleClubEl) singleClubEl.textContent = 'LKR ' + fmt(club);
		if (singleTotEl)  singleTotEl.textContent  = 'LKR ' + fmt(sTot);

		// Family 2
		var fIntl = house * rate;
		var fAddl = 1 * fam * rate;
		var fDist = 2 * dist;
		var fTot  = fIntl + fAddl + fDist + club;
		if (familyIntlEl) familyIntlEl.textContent = 'LKR ' + fmt(fIntl);
		if (familyAddlEl) familyAddlEl.textContent = 'LKR ' + fmt(fAddl);
		if (familyDistEl) familyDistEl.textContent = 'LKR ' + fmt(fDist);
		if (familyClubEl) familyClubEl.textContent = 'LKR ' + fmt(club);
		if (familyTotEl)  familyTotEl.textContent  = 'LKR ' + fmt(fTot);

		// Increment
		if (incTextEl) {
			var addlPer = (fam * rate) + dist;
			incTextEl.textContent = 'USD ' + fmt(fam) + ' (LKR ' + fmt(fam * rate) + ') + District LKR ' + fmt(dist) + ' = LKR ' + fmt(addlPer);
		}
	}

	[rateInput, houseInput, familyInput, distInput, clubInput].forEach(function(input) {
		if (input) {
			input.addEventListener('input', updatePreview);
			input.addEventListener('change', updatePreview);
		}
	});
})();
</script>
