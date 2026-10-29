<?php
/**
 * Fundraiser Seat Booking form.
 *
 * @package LCCL_Donations_And_Events
 * 
 * @var array $active_event
 */

defined( 'ABSPATH' ) || exit;

$gateway_configured = LCCL_DE_Paycenter_Client::is_configured();

if ( ! $active_event ) {
	echo '<div class="lccl-fr-card" style="margin-top:20px; text-align:center;"><p>' . esc_html__( 'No active fundraiser event found.', 'lccl-de' ) . '</p></div>';
	return;
}

$types         = LCCL_DE_Fundraiser::get_table_types( $active_event['id'] );
// Exclude development/testing tiers from public view
$types         = array_values( array_filter( $types, function( $t ) {
	return ! preg_match( '/\b(test|testing)\b/i', $t['name'] );
} ) );
$tables        = LCCL_DE_Fundraiser::get_tables( $active_event['id'] );
$ticket_price  = (float) $active_event['ticket_price'];
$currency      = ! empty( $active_event['currency'] ) ? esc_html( $active_event['currency'] ) : 'LKR';
$avail_seats   = LCCL_DE_Fundraiser::get_available_seat_numbers( $active_event['id'] );
$available_seats_count = count( $avail_seats );
$pool_stats    = LCCL_DE_Fundraiser::get_pool_stats( $active_event['id'] );
$blocked_seats = LCCL_DE_Fundraiser::get_blocked_seat_numbers( $active_event['id'] );
$booked_seats  = LCCL_DE_Fundraiser::get_booked_seat_numbers( $active_event['id'] );

// Check for VIP Pass unlock context
$is_vip       = ! empty( $vip_validation['valid'] );
$vip_details  = $is_vip ? $vip_validation['item_details'] : null;
$vip_pass_str = $is_vip ? ( $vip_validation['token'] ?? '' ) : '';

if ( $is_vip && ! empty( $vip_details ) ) {
	if ( 'table' === $vip_details['booking_mode'] && ! empty( $vip_details['table_ids'] ) ) {
		foreach ( $tables as &$tbl_item ) {
			if ( in_array( (int) $tbl_item['id'], $vip_details['table_ids'], true ) ) {
				$tbl_item['is_blocked']     = 0;
				$tbl_item['booking_mode']   = 'table';
				$tbl_item['is_vip_unlocked'] = 1;
			}
		}
		unset( $tbl_item );
	} elseif ( 'individual' === $vip_details['booking_mode'] && ! empty( $vip_details['seats'] ) ) {
		$available_seats_count = max( $available_seats_count, count( $vip_details['seats'] ) );
	}
}

// Group tables by type ID and calculate tier pool metrics
$tables_by_type = array();
$type_stats     = array();
foreach ( $types as $t ) {
	$tid = (int) $t['id'];
	$tables_by_type[ $tid ] = array();
	$tbl_pool_free    = 0;
	$tbl_pool_blocked = 0;
	$tbl_pool_booked  = 0;
	$seat_pool_count  = 0;

	foreach ( $tables as $tbl ) {
		if ( (int) $tbl['type_id'] === $tid ) {
			$tables_by_type[ $tid ][] = $tbl;
			if ( 'table' === $tbl['booking_mode'] ) {
				if ( (int) $tbl['is_booked'] === 1 ) {
					$tbl_pool_booked++;
				} elseif ( (int) $tbl['is_blocked'] === 1 ) {
					$tbl_pool_blocked++;
				} else {
					$tbl_pool_free++;
				}
			} else {
				$seat_pool_count++;
			}
		}
	}

	$type_stats[ $tid ] = array(
		'table_pool_free'    => $tbl_pool_free,
		'table_pool_blocked' => $tbl_pool_blocked,
		'table_pool_booked'  => $tbl_pool_booked,
		'seat_pool_count'    => $seat_pool_count,
		'total'              => count( $tables_by_type[ $tid ] ),
	);
}

$total_table_pool_free = (int) ( $pool_stats['table_pool_free'] ?? 0 );
if ( $is_vip && 'table' === ( $vip_details['booking_mode'] ?? '' ) ) {
	$total_table_pool_free = 0;
	foreach ( $type_stats as $ts ) {
		$total_table_pool_free += (int) $ts['table_pool_free'];
	}
}

// Ensure JS gets the data
$js_data = array(
	'event_id'              => (int) $active_event['id'],
	'ajax_url'              => admin_url( 'admin-ajax.php' ),
	'preview_nonce'         => wp_create_nonce( 'lccl_fundraiser_preview_seats' ),
	'ticket_price'          => $ticket_price,
	'currency'              => $currency,
	'types'                 => $types,
	'type_stats'            => $type_stats,
	'tables'                => $tables_by_type,
	'available_seats_count' => $available_seats_count,
	'total_table_pool_free' => $total_table_pool_free,
	'vip'                   => $is_vip ? array(
		'active'       => true,
		'token'        => $vip_pass_str,
		'mode'         => $vip_details['booking_mode'],
		'table_ids'    => $vip_details['table_ids'],
		'table_labels' => $vip_details['table_labels'],
		'seats'        => $vip_details['seats'],
		'seat_count'   => $vip_details['seat_count'],
		'type_id'      => $vip_details['type_id'],
		'recipient'    => $vip_details['recipient_label'],
	) : array( 'active' => false ),
);

$initial_preview        = null;
$initial_allocated_text = '';
$initial_allocated_html = '';
$initial_badge_text     = '';
if ( $available_seats_count > 0 ) {
	$init_qty = 1;
	if ( $is_vip && 'individual' === ( $vip_details['booking_mode'] ?? '' ) ) {
		$init_qty = (int) ( $vip_details['seat_count'] ?? 1 );
	}
	$initial_preview = LCCL_DE_Fundraiser::get_party_seat_preview(
		(int) $active_event['id'],
		$init_qty,
		$is_vip ? $vip_pass_str : ''
	);
	if ( $initial_preview && ! is_wp_error( $initial_preview ) ) {
		$initial_allocated_text = $initial_preview['formatted'] ?? '';
		$initial_allocated_html = $initial_preview['formatted_html'] ?? ( ! empty( $initial_preview['formatted'] ) ? esc_html( $initial_preview['formatted'] ) : '' );
		if ( ! empty( $initial_preview['is_vip'] ) ) {
			$initial_badge_text = __( 'VIP Reserved', 'lccl-de' );
		} elseif ( ! empty( $initial_preview['is_split'] ) || ( ! empty( $initial_preview['grouped'] ) && count( $initial_preview['grouped'] ) > 1 ) ) {
			$initial_badge_text = __( 'Adjacent Tables', 'lccl-de' );
		} else {
			$initial_badge_text = __( 'Seating Together', 'lccl-de' );
		}
	}
}
?>
<div class="lccl-fr-wrapper">
	<div class="lccl-fr-layout">

		<!-- BOOKING FORM -->
		<form method="POST" action="" id="lccl-fundraiser-form" class="lccl-fr-card event-card" novalidate style="margin: 0; display: block;">
			<?php wp_nonce_field( LCCL_DE_Fundraiser_Form::NONCE_ACTION, LCCL_DE_Fundraiser_Form::NONCE_FIELD ); ?>
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $active_event['id'] ); ?>">
			<?php if ( $is_vip ) : ?>
				<input type="hidden" name="vip_pass" value="<?php echo esc_attr( $vip_pass_str ); ?>">
			<?php endif; ?>

			<?php if ( $is_vip && ! empty( $vip_details ) ) : ?>
				<div class="lccl-fr-vip-banner">
					<div class="lccl-fr-vip-banner__icon">⭐</div>
					<div class="lccl-fr-vip-banner__body">
						<div class="lccl-fr-vip-banner__tag"><?php esc_html_e( 'VIP Reservation Pass Unlocked', 'lccl-de' ); ?></div>
						<h3 class="lccl-fr-vip-banner__title">
							<?php
							if ( ! empty( $vip_details['recipient_label'] ) ) {
								printf( esc_html__( 'Special Reservation for %s', 'lccl-de' ), esc_html( $vip_details['recipient_label'] ) );
							} else {
								esc_html_e( 'Exclusive Reservation Unlocked', 'lccl-de' );
							}
							?>
						</h3>
						<p class="lccl-fr-vip-banner__desc">
							<?php
							if ( 'table' === $vip_details['booking_mode'] ) {
								printf(
									esc_html__( 'Your exclusive private reservation for Table(s) %s (%d seats total) has been unlocked. Complete your booking details below.', 'lccl-de' ),
									'<strong>' . esc_html( implode( ', ', $vip_details['table_labels'] ) ) . '</strong>',
									(int) $vip_details['total_capacity']
								);
							} else {
								printf(
									esc_html__( 'Your exclusive private reservation for %d seat(s) (Seats #%s) has been unlocked. Complete your booking details below.', 'lccl-de' ),
									(int) $vip_details['seat_count'],
									esc_html( implode( ', #', $vip_details['seats'] ) )
								);
							}
							?>
						</p>
					</div>
				</div>
			<?php endif; ?>

			<h1><?php echo esc_html( $active_event['name'] ); ?></h1>
			<p class="intro">
				<?php esc_html_e( 'Please review the event details and select your preferred booking option below.', 'lccl-de' ); ?>
			</p>

			<div class="section-title">
				<?php esc_html_e( 'Booking Type', 'lccl-de' ); ?> <span class="required">*</span>
			</div>

			<div class="booking-type">
				<label class="type-option <?php echo $available_seats_count > 0 ? 'active' : 'is-disabled'; ?>" id="typeIndividual">
					<input type="radio" name="booking_type" value="individual" style="position:absolute; opacity:0; pointer-events:none; width:0; height:0; margin:0;" <?php echo $available_seats_count > 0 ? 'checked' : 'disabled'; ?>>
					<div class="type-header-row">
						<span class="type-title"><?php esc_html_e( 'Individual Seat Booking', 'lccl-de' ); ?></span>
						<?php if ( $available_seats_count > 0 ) : ?>
							<span class="lccl-fr-badge lccl-fr-badge--green"><?php echo esc_html( $available_seats_count ); ?> <?php esc_html_e( 'seats available', 'lccl-de' ); ?></span>
						<?php else : ?>
							<span class="lccl-fr-badge lccl-fr-badge--red"><?php esc_html_e( 'Sold Out', 'lccl-de' ); ?></span>
						<?php endif; ?>
					</div>
					<span class="type-help">
						<?php esc_html_e( 'Book individual seats. Seats are automatically allocated based on availability.', 'lccl-de' ); ?>
					</span>
				</label>
				<label class="type-option <?php echo ( $available_seats_count === 0 && $total_table_pool_free > 0 ) ? 'active' : ''; ?> <?php echo $total_table_pool_free === 0 ? 'is-disabled' : ''; ?>" id="typeTable">
					<input type="radio" name="booking_type" value="table" style="position:absolute; opacity:0; pointer-events:none; width:0; height:0; margin:0;" <?php echo ( $available_seats_count === 0 && $total_table_pool_free > 0 ) ? 'checked' : ''; ?> <?php echo $total_table_pool_free === 0 ? 'disabled' : ''; ?>>
					<div class="type-header-row">
						<span class="type-title"><?php esc_html_e( 'Table Booking', 'lccl-de' ); ?></span>
						<?php if ( $total_table_pool_free > 0 ) : ?>
							<span class="lccl-fr-badge lccl-fr-badge--gold"><?php echo esc_html( $total_table_pool_free ); ?> <?php esc_html_e( 'tables available', 'lccl-de' ); ?></span>
						<?php else : ?>
							<span class="lccl-fr-badge lccl-fr-badge--muted"><?php esc_html_e( 'Sold Out', 'lccl-de' ); ?></span>
						<?php endif; ?>
					</div>
					<span class="type-help">
						<?php esc_html_e( 'Reserve an entire table. Choose from the table options available for this event.', 'lccl-de' ); ?>
					</span>
				</label>
			</div>

			<!-- INDIVIDUAL SEAT BOOKING -->
			<div id="panelIndividual" class="booking-panel <?php echo $available_seats_count > 0 ? 'active' : ''; ?>" style="<?php echo $available_seats_count > 0 ? '' : 'display:none;'; ?>">
				<div class="section-title"><?php esc_html_e( 'Individual Seat Booking', 'lccl-de' ); ?></div>
				<div class="lccl-fr-row">
					<div>
						<label for="ticketQty"><?php esc_html_e( 'Number of Seats', 'lccl-de' ); ?> <span class="required">*</span></label>
						<input type="number" name="qty" id="ticketQty" min="<?php echo $available_seats_count > 0 ? '1' : '0'; ?>" max="<?php echo esc_attr( min( 10, $available_seats_count ) ); ?>" value="<?php echo $available_seats_count > 0 ? '1' : '0'; ?>" <?php echo $available_seats_count === 0 ? 'disabled' : ''; ?>>
					</div>
					<div>
						<label><?php esc_html_e( 'Price per Ticket', 'lccl-de' ); ?></label>
						<input type="text" value="<?php echo esc_attr( $currency ); ?> <?php echo esc_attr( number_format( $ticket_price, 2 ) ); ?>" readonly>
					</div>
				</div>

				<?php if ( $available_seats_count === 0 ) : ?>
					<div class="note" style="margin-bottom: 12px; font-size: 13px; color: #dc2626; font-weight: 600; background: #fef2f2; border-left: 4px solid #dc2626; border-radius: 3px;">
						<?php esc_html_e( 'Notice: All individual seats for this event are currently booked or reserved.', 'lccl-de' ); ?>
					</div>
				<?php else : ?>
					<div class="note" style="margin-bottom: 12px; font-size: 13px; color: #4b5563; line-height: 1.5; border-radius: 3px;">
						<strong><?php esc_html_e( 'Seat Allocation:', 'lccl-de' ); ?></strong> <?php esc_html_e( 'Seats are automatically allocated consecutively, based on availability.', 'lccl-de' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $is_vip && 'individual' === ( $vip_details['booking_mode'] ?? '' ) ) : ?>
					<div class="seat-info" id="indivSeatInfoCard" style="padding: 12px 16px; background: #fefce8; border: 1px solid #fde047; border-left: 4px solid #d97706; font-size: 13px; line-height: 1.5; border-radius: 3px; color: #92400e;">
						<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 6px;">
							<div style="font-size: 14px;">
								<strong style="color: #78350f;"><?php esc_html_e( 'Seats to be allocated:', 'lccl-de' ); ?></strong> <span id="indivSeatCount" style="color: #78350f; font-weight: 700;"><?php echo (int) ( $vip_details['seat_count'] ?? 1 ); ?></span>
								<span style="color: #b45309; font-weight: 700; margin-left: 8px;">(⭐ <?php esc_html_e( 'VIP Reserved', 'lccl-de' ); ?>)</span>
							</div>
							<span id="allocatedSeatsBadge" class="lccl-fr-gold-pill" style="<?php echo empty( $initial_badge_text ) ? 'display: none;' : ''; ?>">
								<?php echo esc_html( $initial_badge_text ?: __( 'VIP Reserved', 'lccl-de' ) ); ?>
							</span>
						</div>
						<div style="margin-bottom: 4px; color: #92400e;">
							<?php esc_html_e( 'These seats have been exclusively held for you and will be confirmed under your name upon payment.', 'lccl-de' ); ?>
						</div>
						<div id="allocatedSeatsLine" style="display: <?php echo empty( $initial_allocated_html ) ? 'none' : 'flex'; ?>; flex-wrap: wrap; align-items: flex-start; gap: 2px 6px; color: #78350f;">
							<div style="flex: 0 0 auto; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;">
								<span><?php esc_html_e( 'Your allocated seats:', 'lccl-de' ); ?></span>
								<span id="allocatedSeatsSpinner" style="display: none; line-height: 1;">
									<svg class="lccl-fr-spin" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: #d97706;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
								</span>
							</div>
							<div id="allocatedSeatsText" data-loaded="<?php echo ! empty( $initial_allocated_html ) ? 'true' : 'false'; ?>" style="flex: 1; min-width: 180px; color: #78350f; font-weight: 600;">
								<?php echo $initial_allocated_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					</div>
				<?php else : ?>
					<div class="seat-info" id="indivSeatInfoCard" style="padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 13px; line-height: 1.5; border-radius: 4px; color: #475569;">
						<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 6px;">
							<div style="font-size: 14px;">
								<strong style="color: #0f172a;"><?php esc_html_e( 'Seats to be allocated:', 'lccl-de' ); ?></strong> <span id="indivSeatCount" style="color: #0f172a; font-weight: 700;"><?php echo $available_seats_count > 0 ? '1' : '0'; ?></span>
								<span style="color: #047857; font-weight: 600; margin-left: 8px;">(<?php echo esc_html( $available_seats_count ); ?> <?php esc_html_e( 'seats available', 'lccl-de' ); ?>)</span>
							</div>
							<span id="allocatedSeatsBadge" class="lccl-fr-gold-pill" style="<?php echo empty( $initial_badge_text ) ? 'display: none;' : ''; ?>">
								<?php echo esc_html( $initial_badge_text ); ?>
							</span>
						</div>
						<div style="margin-bottom: 4px; color: #475569;">
							<?php esc_html_e( 'Your consecutive seat numbers will be sent to your registered email address and mobile number once your payment is successful.', 'lccl-de' ); ?>
						</div>
						<div id="allocatedSeatsLine" style="display: <?php echo empty( $initial_allocated_html ) ? 'none' : 'flex'; ?>; flex-wrap: wrap; align-items: flex-start; gap: 2px 6px; color: #475569;">
							<div style="flex: 0 0 auto; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;">
								<span><?php esc_html_e( 'Your allocated seats:', 'lccl-de' ); ?></span>
								<span id="allocatedSeatsSpinner" style="display: none; line-height: 1;">
									<svg class="lccl-fr-spin" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: #2563eb;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
								</span>
							</div>
							<div id="allocatedSeatsText" data-loaded="<?php echo ! empty( $initial_allocated_html ) ? 'true' : 'false'; ?>" style="flex: 1; min-width: 180px; color: #334155; font-weight: 500;">
								<?php echo $initial_allocated_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- TABLE BOOKING -->
			<div id="panelTable" class="booking-panel <?php echo ( $available_seats_count === 0 && $total_table_pool_free > 0 ) ? 'active' : ''; ?>" style="<?php echo ( $available_seats_count === 0 && $total_table_pool_free > 0 ) ? '' : 'display:none;'; ?>">
				<div class="section-title"><?php esc_html_e( 'Table Booking', 'lccl-de' ); ?></div>
				<div class="lccl-fr-row">
					<div>
						<label for="tableTypeSelect"><?php esc_html_e( 'Table Type', 'lccl-de' ); ?> <span class="required">*</span></label>
						<select id="tableTypeSelect">
							<option value=""><?php esc_html_e( 'Select a table type', 'lccl-de' ); ?></option>
							<?php foreach ( $types as $t ) : 
								$s = $type_stats[ $t['id'] ] ?? array( 'table_pool_free' => 0, 'total' => 0, 'table_pool_blocked' => 0, 'table_pool_booked' => 0 );
								$has_free = $s['table_pool_free'] > 0;
								if ( ! $has_free ) {
									continue;
								}
								$label_extra = '';
								if ( $has_free ) {
									$label_extra = sprintf( __( '— %d available', 'lccl-de' ), $s['table_pool_free'] );
								} else {
									$label_extra = __( '— Sold out', 'lccl-de' );
								}
							?>
								<option 
									value="<?php echo esc_attr( $t['id'] ); ?>" 
									data-price="<?php echo esc_attr( $t['price'] ); ?>"
									data-capacity="<?php echo esc_attr( $t['capacity'] ); ?>"
									data-available="<?php echo esc_attr( $s['table_pool_free'] ); ?>"
									<?php echo ! $has_free ? 'disabled' : ''; ?>
								>
									<?php echo esc_html( $t['name'] ); ?> – <?php echo esc_html( $currency ); ?> <?php echo esc_html( number_format( $t['price'], 2 ) ); ?> (<?php echo esc_html( $t['capacity'] ); ?> Seats) <?php echo esc_html( $label_extra ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label for="tableQty"><?php esc_html_e( 'Number of Tables Selected', 'lccl-de' ); ?> <span class="required">*</span></label>
						<input type="number" id="tableQty" min="0" value="0" readonly>
					</div>
				</div>

				<div class="note" style="margin-bottom: 12px; font-size: 13px; color: #4b5563; line-height: 1.5; border-radius: 3px;">
					<?php esc_html_e( 'Only tables available for table booking can be selected. Tables reserved for individual seat bookings or marked unavailable cannot be booked.', 'lccl-de' ); ?>
				</div>

				<!-- Visual Table Grid Selector -->
				<div class="table-selector" style="margin-bottom: 18px;">
					<label><?php esc_html_e( 'Select Table(s)', 'lccl-de' ); ?> <span class="required">*</span></label>
					
					<!-- Container where interactive chips render -->
					<div id="tableChipsContainer" class="lccl-fr-table-grid" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Available tables', 'lccl-de' ); ?>">
						<div style="grid-column: 1 / -1; padding: 18px; text-align: center; color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 3px;">
							<?php esc_html_e( 'Select a table type to view the available tables.', 'lccl-de' ); ?>
						</div>
					</div>

					<div class="lccl-fr-table-legend">
						<span class="lccl-fr-legend-item">
							<span class="lccl-fr-legend-swatch lccl-fr-legend-swatch--available"></span>
							<strong style="color:#854d0e;"><?php esc_html_e( 'Available', 'lccl-de' ); ?></strong>
						</span>
						<span class="lccl-fr-legend-item">
							<span class="lccl-fr-legend-swatch lccl-fr-legend-swatch--selected"></span>
							<strong style="color:#b45309;"><?php esc_html_e( 'Selected', 'lccl-de' ); ?></strong>
						</span>
					</div>

					<!-- Hidden native multi-select for standard form submission compatibility -->
					<select name="selectedTables[]" id="tableSelect" multiple hidden></select>
				</div>
			</div>

			<!-- BOOKING CONTACT DETAILS -->
			<div class="section-title"><?php esc_html_e( 'Booking Contact Details', 'lccl-de' ); ?></div>
			<div class="lccl-fr-row">
				<div style="grid-column: span 2;">
					<label for="attendee_name"><?php esc_html_e( 'Full Name', 'lccl-de' ); ?> <span class="required">*</span></label>
					<input type="text" id="attendee_name" name="attendee_name" required placeholder="<?php esc_attr_e( 'Enter your full name', 'lccl-de' ); ?>">
				</div>
			</div>
			<div class="lccl-fr-row">
				<div>
					<label for="attendee_email"><?php esc_html_e( 'Email Address', 'lccl-de' ); ?> <span class="required">*</span></label>
					<input type="email" id="attendee_email" name="attendee_email" required placeholder="name@example.com">
				</div>
				<div>
<div class="lccl-bdf lccl-bdf--donation lccl-fr-payment-control">
<?php
$selected_country_code = '+94';
$country_options = LCCL_DE_Countries::get_all();
$initial_country_code = 'LK';
$initial_country_name = __( 'Sri Lanka (+94)', 'lccl-de' );
$initial_flag = LCCL_DE_Countries::flag_emoji( 'LK' );
?>
						<label class="lccl-bdf__label" for="attendee_phone">
							<?php esc_html_e( 'Mobile / WhatsApp Number', 'lccl-de' ); ?>
						</label>
						<div class="lccl-df__phone-row">
							<div class="lccl-df__phone-code-wrap" id="lccl-fr-phone-code-wrap"
								data-flags-url="<?php echo esc_url( LCCL_DE_URL . 'assets/img/w20/' ); ?>">
								<button type="button" class="lccl-df__phone-code-display"
									id="lccl-fr-phone-code-display" aria-haspopup="listbox" aria-expanded="false"
									aria-controls="lccl-fr-phone-dropdown"
									aria-label="<?php esc_attr_e( 'Select country calling code', 'lccl-de' ); ?>"
									title="<?php echo esc_attr( $initial_country_name ); ?>">
									<span class="lccl-df__phone-flag" id="lccl-fr-phone-flag">
										<?php if ( 2 === strlen( $initial_country_code ) && '+' !== $initial_country_code ) : ?>
											<img class="lccl-df__flag-img"
												src="<?php echo esc_url( LCCL_DE_Countries::flag_url( $initial_country_code ) ); ?>"
												width="20" height="14"
												alt="<?php echo esc_attr( $initial_country_code ); ?>"
												onerror="this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.style.display='inline';">
											<span class="lccl-df__flag-emoji"
												style="display:none;"><?php echo esc_html( $initial_flag ); ?></span>
										<?php else : ?>
											<span
												class="lccl-df__flag-emoji"><?php echo esc_html( $initial_flag ); ?></span>
										<?php endif; ?>
									</span>
									<span
										class="lccl-df__phone-code-text"><?php echo esc_html( '' !== $selected_country_code ? $selected_country_code : '+94' ); ?></span>
									<svg class="lccl-df__phone-code-arrow" viewBox="0 0 20 20" fill="currentColor"
										width="10" height="10" aria-hidden="true" focusable="false">
										<path fill-rule="evenodd"
											d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
											clip-rule="evenodd" />
									</svg>
								</button>
								<div class="lccl-df__phone-dropdown" id="lccl-fr-phone-dropdown" role="listbox"
									aria-label="<?php esc_attr_e( 'Country calling codes', 'lccl-de' ); ?>" hidden>
									<div class="lccl-df__phone-dropdown-search">
										<input type="text" class="lccl-df__phone-search-input"
											id="lccl-fr-phone-search"
											placeholder="<?php esc_attr_e( 'Search country or code...', 'lccl-de' ); ?>"
											autocomplete="off">
									</div>
									<ul class="lccl-df__phone-dropdown-list" id="lccl-fr-phone-list">
										<?php foreach ( $country_options as $c ) :
											$c_code     = $c['code'];
											$c_dial     = $c['dial'];
											$c_flag_url = isset( $c['flag_url'] ) ? $c['flag_url'] : LCCL_DE_Countries::flag_url( $c_code );
											$c_emoji    = isset( $c['flag'] ) ? $c['flag'] : LCCL_DE_Countries::flag_emoji( $c_code );
											$c_clean    = isset( $c['clean_name'] ) ? $c['clean_name'] : trim( (string) preg_replace( '/\s*\(\+[^)]*\)$/', '', $c['name'] ) );
											$is_selected = $c_dial === $selected_country_code;
										?>
											<li role="option"
												class="lccl-df__phone-option<?php echo $is_selected ? ' is-selected' : ''; ?>"
												data-value="<?php echo esc_attr( $c_dial ); ?>"
												data-country="<?php echo esc_attr( $c_code ); ?>"
												data-name="<?php echo esc_attr( $c_clean ); ?>"
												data-flag-url="<?php echo esc_url( $c_flag_url ); ?>"
												data-flag-emoji="<?php echo esc_attr( $c_emoji ); ?>"
												aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>">
												<?php if ( $c_flag_url ) : ?>
													<img class="lccl-df__flag-img"
														src="<?php echo esc_url( $c_flag_url ); ?>" width="20" height="14"
														alt="<?php echo esc_attr( $c_code ); ?>" loading="lazy"
														onerror="this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.style.display='inline';">
													<span class="lccl-df__flag-emoji"
														style="display:none;"><?php echo esc_html( $c_emoji ); ?></span>
												<?php else : ?>
													<span
														class="lccl-df__flag-emoji"><?php echo esc_html( $c_emoji ); ?></span>
												<?php endif; ?>
												<?php if ( '' !== $c_dial ) : ?>
													<span
														class="lccl-df__phone-option-code">(<?php echo esc_html( $c_dial ); ?>)</span>
												<?php endif; ?>
												<span
													class="lccl-df__phone-option-name"><?php echo esc_html( $c_clean ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
								<select name="phone_country_code" id="lccl-fr-phone-code"
									class="lccl-df__phone-code-select"
									aria-label="<?php esc_attr_e( 'Country calling code', 'lccl-de' ); ?>"
									style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;"
									tabindex="-1">
									<?php foreach ( $country_options as $c ) : ?>
										<option value="<?php echo esc_attr( $c['dial'] ); ?>"
											data-country="<?php echo esc_attr( $c['code'] ); ?>"
											data-clean-name="<?php echo esc_attr( isset( $c['clean_name'] ) ? $c['clean_name'] : $c['name'] ); ?>"
											data-flag="<?php echo esc_attr( isset( $c['flag'] ) ? $c['flag'] : LCCL_DE_Countries::flag_emoji( $c['code'] ) ); ?>"
											<?php selected( $selected_country_code, $c['dial'] ); ?>>
											<?php echo esc_html( isset( $c['option_label'] ) ? $c['option_label'] : $c['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<input
								class="lccl-bdf__input lccl-df__phone-number"
								type="tel" required
								id="attendee_phone"
								name="attendee_phone"
								value=""
								placeholder="<?php esc_attr_e( '712345678', 'lccl-de' ); ?>"
								data-placeholder-default="<?php esc_attr_e( '712345678', 'lccl-de' ); ?>"
								inputmode="tel"
								autocomplete="tel-national"
								maxlength="20"
								data-lccl-validate="phone"
								data-invalid-message="<?php echo esc_attr( __( 'Please enter a valid phone number.', 'lccl-de' ) ); ?>"
							>
						</div>
</div>
				</div>
			</div>
			<div class="lccl-fr-row" style="margin-top: 10px;">
				<div style="grid-column: span 2;">
					<label for="booking_notes"><?php esc_html_e( 'Special Requests (Optional)', 'lccl-de' ); ?></label>
					<textarea id="booking_notes" name="notes" rows="2" style="width: 100%; padding: 10px; border: 1px solid #ccc; font-size: 14px; min-height: 60px; box-sizing: border-box; border-radius: 3px;" placeholder="<?php esc_attr_e( 'Enter any special requests here', 'lccl-de' ); ?>"></textarea>
				</div>
			</div>

			<!-- SUMMARY -->
			<div class="summary" style="margin-top: 25px; border-top: none; padding-top: 0;">
				<div class="summary-line" style="display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 10px 0; font-size: 15px;">
					<span><?php esc_html_e( 'Booking Type', 'lccl-de' ); ?></span>
					<strong id="summaryType">
						<?php
						if ( $is_vip && 'table' === ( $vip_details['booking_mode'] ?? '' ) ) {
							esc_html_e( 'Table Booking (VIP)', 'lccl-de' );
						} elseif ( $is_vip ) {
							esc_html_e( 'Individual Seats (VIP)', 'lccl-de' );
						} else {
							esc_html_e( 'Individual Seats', 'lccl-de' );
						}
						?>
					</strong>
				</div>
				<div class="summary-line" style="display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 10px 0; font-size: 15px;">
					<span><?php esc_html_e( 'Seats / Tables', 'lccl-de' ); ?></span>
					<strong id="summaryQuantity">
						<?php
						if ( $is_vip && 'individual' === ( $vip_details['booking_mode'] ?? '' ) ) {
							$cnt = (int) ( $vip_details['seat_count'] ?? 1 );
							echo esc_html( $cnt . ( $cnt === 1 ? ' Seat' : ' Seats' ) );
						} elseif ( $is_vip && 'table' === ( $vip_details['booking_mode'] ?? '' ) ) {
							$cnt = count( $vip_details['table_ids'] ?? array() );
							echo esc_html( $cnt . ( $cnt === 1 ? ' Table' : ' Tables' ) );
						} else {
							esc_html_e( '1 Seat', 'lccl-de' );
						}
						?>
					</strong>
				</div>
				<div class="summary-line" style="display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 10px 0; font-size: 15px;">
					<span><?php esc_html_e( 'Assigned / Selected', 'lccl-de' ); ?></span>
					<strong id="summarySelection">
						<?php
						if ( $is_vip && 'individual' === ( $vip_details['booking_mode'] ?? '' ) ) {
							$cnt = (int) ( $vip_details['seat_count'] ?? 1 );
							$seats_str = ! empty( $vip_details['seats'] ) ? ': Seats #' . implode( ', #', $vip_details['seats'] ) : '';
							echo esc_html( $cnt . ' (VIP reserved' . $seats_str . ')' );
						} elseif ( $is_vip && 'table' === ( $vip_details['booking_mode'] ?? '' ) ) {
							$tbl_str = ! empty( $vip_details['table_labels'] ) ? implode( ', ', $vip_details['table_labels'] ) : 'VIP Table';
							echo esc_html( $tbl_str . ' (VIP reserved)' );
						} else {
							if ( ! empty( $initial_allocated_html ) ) {
								echo $initial_allocated_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} else {
								esc_html_e( '1 (Sequential auto-allocation)', 'lccl-de' );
							}
						}
						?>
					</strong>
				</div>
				<div class="total">
					<span><?php esc_html_e( 'TOTAL AMOUNT', 'lccl-de' ); ?></span>
					<span><?php echo esc_html( $currency ); ?> <span id="lccl-fundraiser-total-display"><?php echo number_format( $ticket_price, 2 ); ?></span></span>
					<input type="hidden" name="total_amount" id="lccl-fundraiser-total-hidden" value="<?php echo esc_attr( $ticket_price ); ?>">
				</div>
			</div>

<div class="lccl-bdf lccl-bdf--donation lccl-fr-payment-control">
				<div class="lccl-bdf__field lccl-terms-checkbox" style="margin-top: 24px;">
					<label class="lccl-bdf__checkbox" style="align-items: flex-start;">
						<input type="checkbox" name="accept_terms" id="terms_agree" class="lccl-accept-terms" required>
						<span class="lccl-bdf__check" aria-hidden="true"></span>
						<span style="font-size: 14px; color: #475569;">
							<?php
							printf(
								/* translators: %s is the link to terms */
								__( 'I have read and agree to the <a href="%s" target="_blank" class="lccl-terms-link">Terms & Conditions</a>.', 'lccl-de' ),
								'https://www.colomboleads.org/online-payment-terms-conditions/'
							);
							?>
						</span>
					</label>
				</div>
</div>

			<button class="lccl-fr-button" type="submit" id="lccl-fundraiser-submit" style="margin-top: 20px;" disabled>
				<?php esc_html_e( 'Proceed to Payment', 'lccl-de' ); ?>
			</button>
			<div id="lcclSubmitSpinner" style="display:none; text-align:center; margin-top:12px; font-size:14px; color:#64748b;">
				⏳ <?php esc_html_e( 'Processing your booking — please wait…', 'lccl-de' ); ?>
			</div>

			<div class="lccl-bdf lccl-bdf--donation lccl-fr-payment-control">
				<p class="lccl-mf__secure-note">
					<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
					<?php esc_html_e( 'Payments are processed securely via Commercial Bank of Ceylon (CBC) Paycenter.', 'lccl-de' ); ?>
				</p>

				<div class="lccl-df__pay-icons">
					<img
						src="<?php echo esc_url( LCCL_DE_URL . 'assets/img/pay-icons.png' ); ?>"
						alt="<?php esc_attr_e( 'Accepted Payment Methods: Visa, Mastercard, American Express, UnionPay, LankaPay', 'lccl-de' ); ?>"
						class="lccl-df__pay-icons-img"
						loading="lazy"
						width="720"
						height="120"
					>
				</div>
			</div>

		</form>

		<!-- EVENT INFORMATION / SEATING MAP ASIDE -->
		<aside class="event-card">
			<div class="dummy-image">
				<div>
					<h3><?php esc_html_e( 'TOGETHER,', 'lccl-de' ); ?><br><?php esc_html_e( 'WE SERVE', 'lccl-de' ); ?></h3>
					<p style="color: #ffffff;"><?php esc_html_e( 'Support our community projects and help us create sustainable impact across our society.', 'lccl-de' ); ?></p>
				</div>
			</div>
			
			<div class="event-details">
				<h2><?php echo esc_html( $active_event['name'] ); ?></h2>
				<div class="detail">
					<span><?php esc_html_e( 'Date', 'lccl-de' ); ?></span>
					<strong><?php echo esc_html( $active_event['event_date'] ? date( 'j F Y', strtotime( $active_event['event_date'] ) ) : 'TBA' ); ?></strong>
				</div>
				<div class="detail">
					<span><?php esc_html_e( 'Time', 'lccl-de' ); ?></span>
					<strong><?php echo esc_html( $active_event['event_time'] ? date( 'h:i A', strtotime( $active_event['event_time'] ) ) : 'TBA' ); ?></strong>
				</div>
				<div class="detail">
					<span><?php esc_html_e( 'Venue', 'lccl-de' ); ?></span>
					<strong><?php echo esc_html( $active_event['venue'] ?: 'TBA' ); ?></strong>
				</div>
				<div class="detail">
					<span><?php esc_html_e( 'Price per Ticket (for individual tickets)', 'lccl-de' ); ?></span>
					<strong><?php echo esc_html( $currency ); ?> <?php echo esc_html( number_format( $ticket_price, 2 ) ); ?></strong>
				</div>

				<div class="detail">
					<span><?php esc_html_e( 'Individual Seats Available', 'lccl-de' ); ?></span>
					<strong style="color: #166534; font-weight:600;"><?php echo esc_html( $available_seats_count ); ?> <?php esc_html_e( 'Seats', 'lccl-de' ); ?></strong>
				</div>
				<div class="detail">
					<span><?php esc_html_e( 'Tables Available', 'lccl-de' ); ?></span>
					<strong style="color: #854d0e; font-weight:600;"><?php echo esc_html( $total_table_pool_free ); ?> <?php esc_html_e( 'Tables', 'lccl-de' ); ?></strong>
				</div>

				<!-- Visual Seating Map Layout -->
				<div class="seat-map">
					<?php
					$table_pool_tables = array_filter( $tables, function( $t ) { return 'table' === $t['booking_mode']; } );
					$table_pool_booked_total = max( 0, count( $table_pool_tables ) - $total_table_pool_free );
					if ( ! empty( $table_pool_tables ) ) :
					?>
					<!-- Table Pool Visual Mini-Grid (mirrors table selector IDs & statuses) -->
					<div class="seat-map-tables">
						<div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; color: #64748b; margin-bottom: 8px; font-weight: 600;"><?php esc_html_e( 'Table Pool', 'lccl-de' ); ?></div>
						<div class="seat-map-table-grid">
							<?php foreach ( $table_pool_tables as $tpt ) :
								$tpt_booked  = (int) $tpt['is_booked'] === 1;
								$tpt_blocked = (int) $tpt['is_blocked'] === 1;
								$tpt_cls     = 'map-tbl-avail';
								$tpt_title   = $tpt['table_label'] . ' — ' . __( 'Available', 'lccl-de' );
								if ( $tpt_booked || $tpt_blocked ) {
									$tpt_cls   = 'map-tbl-booked';
									$tpt_title = $tpt['table_label'] . ' — ' . __( 'Booked', 'lccl-de' );
								}
							?>
								<div class="seat-map-tbl <?php echo esc_attr( $tpt_cls ); ?>" title="<?php echo esc_attr( $tpt_title ); ?>" data-table-id="<?php echo esc_attr( $tpt['id'] ); ?>">
									<?php echo esc_html( $tpt['table_label'] ); ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<?php endif; ?>

					<div class="legend">
						<span class="legend-table"><?php esc_html_e( 'Tables Available', 'lccl-de' ); ?> (<?php echo esc_html( $total_table_pool_free ); ?>)</span>
						<span class="legend-booked"><?php esc_html_e( 'Booked', 'lccl-de' ); ?> (<?php echo esc_html( $table_pool_booked_total ); ?>)</span>
					</div>
				</div>

				<?php
				// Event-specific seating plan image (fallback to Grand Ballroom default)
				$sp_image_file = 'Grand_Ballroom_Gala_Seating_Plan.png';
				$sp_image_url  = LCCL_DE_URL . 'assets/img/' . $sp_image_file;
				$sp_title      = sprintf(
					/* translators: %s: event name */
					__( '%s — Seating Plan', 'lccl-de' ),
					$active_event['name']
				);
				?>
				<!-- Event Seating Plan Image -->
				<div class="seating-plan-image-wrap">
					<h4><?php echo esc_html( $sp_title ); ?></h4>
					<div class="seating-plan-trigger" id="openSeatingPlanTrigger" role="button" tabindex="0" title="<?php esc_attr_e( 'Click to view full size seating plan layout', 'lccl-de' ); ?>" aria-label="<?php esc_attr_e( 'Click to view full size seating plan layout', 'lccl-de' ); ?>">
						<img src="<?php echo esc_url( $sp_image_url ); ?>" alt="<?php echo esc_attr( $sp_title ); ?>" loading="lazy">
						<span class="seating-plan-hover-badge">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<circle cx="11" cy="11" r="8"></circle>
								<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
								<line x1="11" y1="8" x2="11" y2="14"></line>
								<line x1="8" y1="11" x2="14" y2="11"></line>
							</svg>
							<span><?php esc_html_e( 'Click to Enlarge Full Plan', 'lccl-de' ); ?></span>
						</span>
					</div>
					<span class="seating-plan-hint"><?php esc_html_e( '🔍 Click seating plan to open in full size modal (Esc to close)', 'lccl-de' ); ?></span>
				</div>
			</div>
		</aside>

	</div>
</div>

<!-- Full Size Seating Plan Lightbox Modal -->
<div id="lcclSeatingPlanModal" class="lccl-sp-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $sp_title ); ?>" style="display: none;">
	<div class="lccl-sp-modal__overlay" data-close-sp-modal></div>
	<button type="button" class="lccl-sp-modal__close-btn" data-close-sp-modal aria-label="<?php esc_attr_e( 'Close seating plan (Esc)', 'lccl-de' ); ?>" title="<?php esc_attr_e( 'Close (Esc)', 'lccl-de' ); ?>">
		&times;
	</button>
	<div class="lccl-sp-modal__content" data-close-sp-modal>
		<img src="<?php echo esc_url( $sp_image_url ); ?>" alt="<?php echo esc_attr( $sp_title ); ?>" class="lccl-sp-modal__img" onclick="event.stopPropagation();">
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var fundData = <?php echo wp_json_encode( $js_data ); ?>;
	var form = document.getElementById('lccl-fundraiser-form');
	if (!form) return;

	var typeIndiv = document.getElementById('typeIndividual');
	var typeTable = document.getElementById('typeTable');
	var panelIndiv = document.getElementById('panelIndividual');
	var panelTable = document.getElementById('panelTable');
	
	var inputQty = document.getElementById('ticketQty');
	var indivSeatCount = document.getElementById('indivSeatCount');
	var inputTableQty = document.getElementById('tableQty');
	var selectType = document.getElementById('tableTypeSelect');
	var selectTable = document.getElementById('tableSelect');
	var chipsContainer = document.getElementById('tableChipsContainer');
	
	var totalDisp = document.getElementById('lccl-fundraiser-total-display');
	var totalHid = document.getElementById('lccl-fundraiser-total-hidden');
	
	var sumType = document.getElementById('summaryType');
	var sumQty = document.getElementById('summaryQuantity');
	var sumSel = document.getElementById('summarySelection');

	var submitBtn = document.getElementById('lccl-fundraiser-submit');
	var termsAgree = document.getElementById('terms_agree');
	var inputName = document.getElementById('attendee_name');
	var inputEmail = document.getElementById('attendee_email');
	var inputPhone = document.getElementById('attendee_phone');
	
	var mode = fundData.available_seats_count > 0 ? 'individual' : 'table';
	
	function formatCurrency(val) {
		return Number(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
	}

	function getSelectedTableIds() {
		var ids = [];
		for (var i = 0; i < selectTable.options.length; i++) {
			if (selectTable.options[i].selected && selectTable.options[i].value !== '') {
				ids.push(parseInt(selectTable.options[i].value));
			}
		}
		return ids;
	}

	function getSelectedTableLabels() {
		var labels = [];
		for (var i = 0; i < selectTable.options.length; i++) {
			if (selectTable.options[i].selected && selectTable.options[i].value !== '') {
				labels.push(selectTable.options[i].textContent.trim());
			}
		}
		return labels;
	}

	function validateState() {
		var valid = false;
		if (mode === 'individual') {
			var q = parseInt(inputQty.value) || 0;
			var maxSeats = fundData.available_seats_count || 0;
			valid = (q > 0 && q <= maxSeats);
		} else {
			var tq = parseInt(inputTableQty.value) || 0;
			var type = selectType.value;
			var selCount = getSelectedTableIds().length;
			valid = (type !== '' && tq > 0 && selCount === tq);
		}

		// Also require contact details to be filled
		var nameOk = inputName && inputName.value.trim() !== '';
		var emailOk = inputEmail && inputEmail.value.trim() !== '' && inputEmail.value.indexOf('@') > 0;
		var phoneOk = inputPhone && inputPhone.value.trim() !== '';
		var contactOk = nameOk && emailOk && phoneOk;
		
		if (valid && contactOk && termsAgree.checked) {
			submitBtn.removeAttribute('disabled');
		} else {
			submitBtn.setAttribute('disabled', 'disabled');
		}
	}
	
	var allocatedSeatsLine = document.getElementById('allocatedSeatsLine');
	var allocatedSeatsText = document.getElementById('allocatedSeatsText');
	var allocatedSeatsSpinner = document.getElementById('allocatedSeatsSpinner');
	var allocatedSeatsBadge = document.getElementById('allocatedSeatsBadge');
	var previewTimer = null;
	var lastFetchedQty = <?php echo ( $initial_preview && ! is_wp_error( $initial_preview ) ) ? (int) $initial_preview['qty'] : 'null'; ?>;

	function fetchSeatAllocationPreview(qty) {
		if (!allocatedSeatsText || !allocatedSeatsLine) return;

		var q = parseInt(qty) || 0;
		if (q < 1) {
			if (allocatedSeatsSpinner) allocatedSeatsSpinner.style.display = 'none';
			if (allocatedSeatsBadge) allocatedSeatsBadge.style.display = 'none';
			allocatedSeatsLine.style.display = 'none';
			allocatedSeatsText.innerHTML = '';
			return;
		}

		if (q === lastFetchedQty && allocatedSeatsText.innerHTML && allocatedSeatsText.getAttribute('data-loaded') === 'true') {
			if (allocatedSeatsSpinner) allocatedSeatsSpinner.style.display = 'none';
			if (allocatedSeatsBadge && allocatedSeatsBadge.textContent.trim()) {
				allocatedSeatsBadge.style.display = 'inline-flex';
			}
			allocatedSeatsText.style.opacity = '1';
			allocatedSeatsLine.style.display = 'flex';
			return;
		}

		allocatedSeatsLine.style.display = 'flex';
		if (allocatedSeatsSpinner) {
			allocatedSeatsSpinner.style.display = 'inline-flex';
		}
		allocatedSeatsText.style.opacity = '0.4';

		var formData = new FormData();
		formData.append('action', 'lccl_de_fundraiser_preview_seats');
		formData.append('event_id', fundData.event_id || 0);
		formData.append('qty', q);
		formData.append('nonce', fundData.preview_nonce || '');
		if (fundData.vip && fundData.vip.active && fundData.vip.token) {
			formData.append('vip_token', fundData.vip.token);
		}

		fetch(fundData.ajax_url, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
		.then(function(res) { return res.json(); })
		.then(function(data) {
			if (allocatedSeatsSpinner) allocatedSeatsSpinner.style.display = 'none';
			allocatedSeatsText.style.opacity = '1';
			allocatedSeatsText.setAttribute('data-loaded', 'true');
			if (data && data.success && data.data) {
				lastFetchedQty = q;
				renderSeatAllocation(data.data);
			} else {
				if (allocatedSeatsBadge) allocatedSeatsBadge.style.display = 'none';
				var msg = (data && data.data && data.data.message) ? data.data.message : '<?php echo esc_js( __( 'Unable to allocate consecutive seats for this quantity.', 'lccl-de' ) ); ?>';
				allocatedSeatsText.innerHTML = '<span style="color:#dc2626; font-weight:600;">' + msg + '</span>';
			}
		})
		.catch(function() {
			if (allocatedSeatsSpinner) allocatedSeatsSpinner.style.display = 'none';
			if (allocatedSeatsBadge) allocatedSeatsBadge.style.display = 'none';
			allocatedSeatsText.style.opacity = '1';
			allocatedSeatsText.innerHTML = '<span style="color:#dc2626; font-weight:600;">' + '<?php echo esc_js( __( 'Error checking seat allocation.', 'lccl-de' ) ); ?>' + '</span>';
		});
	}

	function renderSeatAllocation(data) {
		if (!allocatedSeatsText || !allocatedSeatsLine) return;

		var groups = data.grouped || [];
		if (groups.length === 0) {
			if (allocatedSeatsBadge) allocatedSeatsBadge.style.display = 'none';
			allocatedSeatsLine.style.display = 'none';
			return;
		}

		var html = '';
		for (var i = 0; i < groups.length; i++) {
			var grp = groups[i];
			var seatWord = grp.seat_numbers.length === 1 ? 'Seat' : 'Seats';
			var seatStr = seatWord + ' ' + grp.seat_numbers.join(', ');
			var lbl = grp.table_label || '';
			if (lbl === 'General') {
				html += '<div class="lccl-fr-allocated-row"><span class="lccl-fr-allocated-seats">' + seatStr + '</span></div>';
			} else {
				var tblPrefix = (lbl.toLowerCase().indexOf('table') === 0) ? lbl : ('Table ' + lbl);
				html += '<div class="lccl-fr-allocated-row"><span class="lccl-fr-allocated-tbl">' + tblPrefix + ' —</span><span class="lccl-fr-allocated-seats">' + seatStr + '</span></div>';
			}
		}

		allocatedSeatsText.innerHTML = html;
		allocatedSeatsLine.style.display = 'flex';

		// Update gold pill status badge
		if (allocatedSeatsBadge) {
			var isSplit = !!data.is_split || groups.length > 1;
			if (data.is_vip) {
				allocatedSeatsBadge.textContent = '<?php echo esc_js( __( 'VIP Reserved', 'lccl-de' ) ); ?>';
				allocatedSeatsBadge.style.display = 'inline-flex';
			} else if (isSplit) {
				allocatedSeatsBadge.textContent = '<?php echo esc_js( __( 'Adjacent Tables', 'lccl-de' ) ); ?>';
				allocatedSeatsBadge.style.display = 'inline-flex';
			} else {
				allocatedSeatsBadge.textContent = '<?php echo esc_js( __( 'Seating Together', 'lccl-de' ) ); ?>';
				allocatedSeatsBadge.style.display = 'inline-flex';
			}
		}

		if (sumSel && mode === 'individual') {
			sumSel.innerHTML = html;
		}
	}

	function queueSeatAllocationPreview(qty) {
		clearTimeout(previewTimer);
		if (allocatedSeatsSpinner) {
			allocatedSeatsSpinner.style.display = 'inline-flex';
		}
		if (allocatedSeatsText) {
			allocatedSeatsText.style.opacity = '0.4';
		}
		previewTimer = setTimeout(function() {
			fetchSeatAllocationPreview(qty);
		}, 150);
	}

	function updateTotal() {
		var tot = 0;
		if (mode === 'individual') {
			var q = parseInt(inputQty.value) || 0;
			var maxSeats = fundData.available_seats_count || 0;
			if (q > maxSeats) {
				q = maxSeats;
				inputQty.value = q;
			}
			tot = q * fundData.ticket_price;
			if (indivSeatCount) {
				indivSeatCount.textContent = q;
			}
			
			sumType.textContent = (fundData.vip && fundData.vip.active) ? 'Individual Seats (VIP)' : 'Individual Seats';
			sumQty.textContent = q + (q === 1 ? ' Seat' : ' Seats');
			if (fundData.vip && fundData.vip.active && fundData.vip.mode === 'individual') {
				var seatDetail = (fundData.vip.seats && fundData.vip.seats.length > 0) ? ': Seats #' + fundData.vip.seats.join(', #') : '';
				sumSel.textContent = q > 0 ? (q + ' (VIP reserved' + seatDetail + ')') : 'None';
			} else {
				sumSel.textContent = q > 0 ? (q + ' (Sequential auto-allocation)') : 'None';
			}

			// Dynamically fetch allocated seats for selected quantity
			if (q > 0) {
				queueSeatAllocationPreview(q);
			} else if (allocatedSeatsLine) {
				if (allocatedSeatsSpinner) {
					allocatedSeatsSpinner.style.display = 'none';
				}
				if (allocatedSeatsBadge) {
					allocatedSeatsBadge.style.display = 'none';
				}
				allocatedSeatsLine.style.display = 'none';
				if (allocatedSeatsText) {
					allocatedSeatsText.innerHTML = '';
				}
			}
		} else {
			var type = selectType.value;
			var selIds = getSelectedTableIds();
			var selLabels = getSelectedTableLabels();
			var selCount = selIds.length;
			
			if (type && type !== '') {
				var opt = selectType.options[selectType.selectedIndex];
				var p = parseFloat(opt.getAttribute('data-price')) || 0;
				tot = selCount * p;
			}
			
			sumType.textContent = (fundData.vip && fundData.vip.active) ? 'Table Booking (VIP)' : 'Table Booking';
			sumQty.textContent = selCount + (selCount === 1 ? ' Table' : ' Tables');
			if (fundData.vip && fundData.vip.active && fundData.vip.mode === 'table') {
				sumSel.textContent = selLabels.length > 0 ? (selLabels.join(', ') + ' (VIP reserved)') : 'None selected';
			} else {
				sumSel.textContent = selLabels.length > 0 ? selLabels.join(', ') : 'None selected';
			}
		}
		
		totalDisp.textContent = formatCurrency(tot);
		totalHid.value = tot;
		validateState();
	}
	
	function switchMode(m) {
		mode = m;
		if (m === 'individual') {
			typeIndiv.classList.add('active');
			typeTable.classList.remove('active');
			panelIndiv.style.display = 'block';
			panelTable.style.display = 'none';
		} else {
			typeTable.classList.add('active');
			typeIndiv.classList.remove('active');
			panelTable.style.display = 'block';
			panelIndiv.style.display = 'none';
		}
		updateTotal();
	}

	if (typeIndiv && fundData.available_seats_count > 0) {
		typeIndiv.addEventListener('click', function(e) {
			var radio = typeIndiv.querySelector('input');
			if (radio && !radio.disabled) {
				radio.checked = true;
				switchMode('individual');
			}
		});
	}
	
	if (typeTable && fundData.total_table_pool_free > 0) {
		typeTable.addEventListener('click', function(e) {
			var radio = typeTable.querySelector('input');
			if (radio && !radio.disabled) {
				radio.checked = true;
				switchMode('table');
			}
		});
	}
	
	inputQty.addEventListener('input', updateTotal);
	inputQty.addEventListener('change', updateTotal);

	var tablePulseTimer = null;
	function triggerTableQtyPulse() {
		if (!inputTableQty) return;
		inputTableQty.classList.remove('lccl-fr-border-pulse');
		clearTimeout(tablePulseTimer);
		void inputTableQty.offsetWidth;
		inputTableQty.classList.add('lccl-fr-border-pulse');
		tablePulseTimer = setTimeout(function() {
			inputTableQty.classList.remove('lccl-fr-border-pulse');
		}, 950);
	}

	function renderTableChips(tid) {
		chipsContainer.innerHTML = '';
		chipsContainer.scrollTop = 0;
		selectTable.innerHTML = '';

		if (!tid || tid === '') {
			chipsContainer.innerHTML = '<div style="grid-column: 1 / -1; padding: 18px; text-align: center; color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 3px;">Select a table type to view the available tables.</div>';
			inputTableQty.value = 0;
			updateTotal();
			return;
		}

		var tierTables = (fundData.tables[tid] || []).filter(function(tbl) {
			var vipUnlocked = parseInt(tbl.is_vip_unlocked) === 1 ||
				(fundData.vip && fundData.vip.active && fundData.vip.table_ids && fundData.vip.table_ids.indexOf(parseInt(tbl.id)) !== -1);
			return parseInt(tbl.is_booked) !== 1 &&
				(vipUnlocked || (tbl.booking_mode === 'table' && parseInt(tbl.is_blocked) !== 1));
		});
		if (tierTables.length === 0) {
			chipsContainer.innerHTML = '<div style="grid-column: 1 / -1; padding: 18px; text-align: center; color: #dc2626; background: #fef2f2; border: 1px dashed #fca5a5; border-radius: 3px;">No tables are currently available for this table type.</div>';
			inputTableQty.value = 0;
			updateTotal();
			return;
		}

		var availCount = 0;

		tierTables.forEach(function(tbl) {
			var isTableMode   = (tbl.booking_mode === 'table');
			var isBlocked     = (parseInt(tbl.is_blocked) === 1);
			var isBooked      = (parseInt(tbl.is_booked) === 1);
			var isVipUnlocked = (parseInt(tbl.is_vip_unlocked) === 1) || (fundData.vip && fundData.vip.active && fundData.vip.table_ids && fundData.vip.table_ids.indexOf(parseInt(tbl.id)) !== -1);
			if (isVipUnlocked) {
				isTableMode = true;
				isBlocked   = false;
			}
			var isAvailable   = (isTableMode && !isBlocked && !isBooked);

			var chip = document.createElement('div');
			chip.className = 'lccl-fr-table-chip';
			chip.setAttribute('data-id', tbl.id);
			chip.setAttribute('data-label', tbl.table_label);

			var numSpan = document.createElement('span');
			numSpan.className = 'chip-num';
			numSpan.textContent = tbl.table_label;
			chip.appendChild(numSpan);

			var metaSpan = document.createElement('span');
			metaSpan.className = 'chip-meta';
			metaSpan.textContent = tbl.capacity + ' Seats';
			chip.appendChild(metaSpan);

			var statusSpan = document.createElement('span');
			statusSpan.className = 'chip-status';

			if (isVipUnlocked) {
				chip.classList.add('is-available', 'is-vip');
				chip.setAttribute('title', 'Exclusive VIP table reserved for you');
				statusSpan.textContent = '⭐ VIP Reserved';
				availCount++;

				var opt = document.createElement('option');
				opt.value = tbl.id;
				opt.textContent = tbl.table_label;
				selectTable.appendChild(opt);

				chip.addEventListener('click', function() {
					if (fundData.vip && fundData.vip.active && fundData.vip.mode === 'table') {
						return; // Locked for VIP
					}
					var isCurrentlySelected = chip.classList.contains('is-selected');

					if (isCurrentlySelected) {
						chip.classList.remove('is-selected');
						for (var j = 0; j < selectTable.options.length; j++) {
							if (selectTable.options[j].value === String(tbl.id)) {
								selectTable.options[j].selected = false;
								break;
							}
						}
					} else {
						chip.classList.add('is-selected');
						for (var k = 0; k < selectTable.options.length; k++) {
							if (selectTable.options[k].value === String(tbl.id)) {
								selectTable.options[k].selected = true;
								break;
							}
						}
					}

					var selCount = chipsContainer.querySelectorAll('.lccl-fr-table-chip.is-selected').length;
					inputTableQty.value = selCount;
					triggerTableQtyPulse();
					updateTotal();
				});
			} else if (isBooked) {
				chip.classList.add('is-booked');
				chip.setAttribute('title', 'Table already booked');
				statusSpan.textContent = 'Booked';
			} else if (isBlocked) {
				chip.classList.add('is-blocked');
				chip.setAttribute('title', 'Table reserved');
				statusSpan.textContent = 'Reserved';
			} else {
				chip.classList.add('is-available');
				chip.setAttribute('title', 'Available for booking. Click to select.');
				statusSpan.textContent = 'Available';
				availCount++;

				// Also add to hidden multi-select
				var opt = document.createElement('option');
				opt.value = tbl.id;
				opt.textContent = tbl.table_label;
				selectTable.appendChild(opt);

				// Interactive click listener
				chip.addEventListener('click', function() {
					var isCurrentlySelected = chip.classList.contains('is-selected');

					if (isCurrentlySelected) {
						chip.classList.remove('is-selected');
						for (var j = 0; j < selectTable.options.length; j++) {
							if (selectTable.options[j].value === String(tbl.id)) {
								selectTable.options[j].selected = false;
								break;
							}
						}
					} else {
						chip.classList.add('is-selected');
						for (var k = 0; k < selectTable.options.length; k++) {
							if (selectTable.options[k].value === String(tbl.id)) {
								selectTable.options[k].selected = true;
								break;
							}
						}
					}

					var selCount = chipsContainer.querySelectorAll('.lccl-fr-table-chip.is-selected').length;
					inputTableQty.value = selCount;
					triggerTableQtyPulse();
					updateTotal();
				});
			}

			chip.appendChild(statusSpan);
			chipsContainer.appendChild(chip);
		});

		if (availCount === 0) {
			inputTableQty.value = 0;
			var notice = document.createElement('div');
			notice.style.gridColumn = '1 / -1';
			notice.style.padding = '12px 16px';
			notice.style.marginTop = '10px';
			notice.style.background = '#fef2f2';
			notice.style.color = '#991b1b';
			notice.style.fontSize = '13px';
			notice.style.borderRadius = '3px';
			notice.textContent = 'All tables of this type are currently reserved, booked, or unavailable.';
			chipsContainer.appendChild(notice);
		} else {
			inputTableQty.max = availCount;
			inputTableQty.value = 0;

			// Auto-select the first available table for user convenience
			var firstAvailChip = chipsContainer.querySelector('.lccl-fr-table-chip.is-available');
			if (firstAvailChip) {
				firstAvailChip.click();
			}
		}
	}
	
	selectType.addEventListener('change', function() {
		renderTableChips(this.value);
		updateTotal();
	});
	
	termsAgree.addEventListener('change', validateState);

	// Re-validate when contact fields change
	if (inputName) inputName.addEventListener('input', validateState);
	if (inputEmail) inputEmail.addEventListener('input', validateState);
	if (inputPhone) inputPhone.addEventListener('input', validateState);
	
	// Initial state setup
	if (mode === 'individual') {
		switchMode('individual');
	} else {
		switchMode('table');
	}
	updateTotal();

	// Full-Size Seating Plan Modal Handlers
	var spModal   = document.getElementById('lcclSeatingPlanModal');
	var spTrigger = document.getElementById('openSeatingPlanTrigger');

	function openSeatingPlanModal() {
		if (!spModal) return;
		spModal.style.display = 'flex';
		spModal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('lccl-modal-prevent-scroll');
		setTimeout(function() {
			spModal.classList.add('is-open');
			var closeBtn = spModal.querySelector('.lccl-sp-modal__close-btn');
			if (closeBtn) closeBtn.focus();
		}, 15);
	}

	function closeSeatingPlanModal() {
		if (!spModal) return;
		spModal.classList.remove('is-open');
		spModal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('lccl-modal-prevent-scroll');
		setTimeout(function() {
			if (!spModal.classList.contains('is-open')) {
				spModal.style.display = 'none';
			}
		}, 260);
	}

	if (spTrigger) {
		spTrigger.addEventListener('click', function(e) {
			e.preventDefault();
			openSeatingPlanModal();
		});
		spTrigger.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				openSeatingPlanModal();
			}
		});
	}

	if (spModal) {
		var closeElements = spModal.querySelectorAll('[data-close-sp-modal]');
		for (var c = 0; c < closeElements.length; c++) {
			closeElements[c].addEventListener('click', function(e) {
				e.preventDefault();
				closeSeatingPlanModal();
			});
		}
	}

	// Close on Escape key
	document.addEventListener('keydown', function(e) {
		if ((e.key === 'Escape' || e.key === 'Esc') && spModal && spModal.classList.contains('is-open')) {
			e.preventDefault();
			closeSeatingPlanModal();
		}
	});

	// Double-submit prevention: disable button and show spinner on form submit
	var isSubmitting = false;
	var submitSpinner = document.getElementById('lcclSubmitSpinner');
	form.addEventListener('submit', function(e) {
		if (isSubmitting) {
			e.preventDefault();
			return false;
		}
		isSubmitting = true;
		submitBtn.setAttribute('disabled', 'disabled');
		submitBtn.textContent = 'Processing…';
		if (submitSpinner) submitSpinner.style.display = 'block';
	});

	// Sync seating plan table mini-grid highlights with table chip selection
	function syncMapTableHighlights() {
		var mapTables = document.querySelectorAll('.seat-map-tbl');
		if (!mapTables.length) return;
		var selIds = getSelectedTableIds();
		for (var m = 0; m < mapTables.length; m++) {
			var tableId = parseInt(mapTables[m].getAttribute('data-table-id'));
			if (selIds.indexOf(tableId) !== -1) {
				mapTables[m].classList.add('map-tbl-selected');
			} else {
				mapTables[m].classList.remove('map-tbl-selected');
			}
		}
	}

	// Patch updateTotal to also sync map highlights
	var origUpdateTotal = updateTotal;
	updateTotal = function() {
		origUpdateTotal();
		syncMapTableHighlights();
	};

	// Initialize VIP Pass state if present
	if (fundData.vip && fundData.vip.active) {
		if (fundData.vip.recipient && inputName && !inputName.value) {
			inputName.value = fundData.vip.recipient;
		}

		if (fundData.vip.mode === 'table') {
			switchMode('table');
			if (typeIndiv) {
				typeIndiv.style.opacity = '0.5';
				typeIndiv.style.pointerEvents = 'none';
				var rIndiv = typeIndiv.querySelector('input');
				if (rIndiv) rIndiv.disabled = true;
			}
			if (selectType && fundData.vip.type_id) {
				selectType.value = fundData.vip.type_id;
				renderTableChips(fundData.vip.type_id);
			}
			var targetTids = fundData.vip.table_ids || [];
			if (inputTableQty) {
				inputTableQty.value = targetTids.length;
				inputTableQty.readOnly = true;
			}
			// Pre-select the VIP tables
			targetTids.forEach(function(tid) {
				var chip = chipsContainer.querySelector('.lccl-fr-table-chip[data-id="' + tid + '"]');
				if (chip) chip.classList.add('is-selected');
				for (var o = 0; o < selectTable.options.length; o++) {
					if (parseInt(selectTable.options[o].value) === parseInt(tid)) {
						selectTable.options[o].selected = true;
					}
				}
			});
			updateTotal();
		} else if (fundData.vip.mode === 'individual') {
			switchMode('individual');
			if (typeTable) {
				typeTable.style.opacity = '0.5';
				typeTable.style.pointerEvents = 'none';
				var rTbl = typeTable.querySelector('input');
				if (rTbl) rTbl.disabled = true;
			}
			if (inputQty) {
				inputQty.value = fundData.vip.seat_count || (fundData.vip.seats ? fundData.vip.seats.length : 1);
				inputQty.readOnly = true;
			}
			updateTotal();
		}
	}
});
</script>
