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
$tables        = LCCL_DE_Fundraiser::get_tables( $active_event['id'] );
$ticket_price  = (float) $active_event['ticket_price'];
$currency      = ! empty( $active_event['currency'] ) ? esc_html( $active_event['currency'] ) : 'LKR';
$avail_seats   = LCCL_DE_Fundraiser::get_available_seat_numbers( $active_event['id'] );
$available_seats_count = count( $avail_seats );
$pool_stats    = LCCL_DE_Fundraiser::get_pool_stats( $active_event['id'] );
$blocked_seats = LCCL_DE_Fundraiser::get_blocked_seat_numbers( $active_event['id'] );
$booked_seats  = LCCL_DE_Fundraiser::get_booked_seat_numbers( $active_event['id'] );

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

// Ensure JS gets the data
$js_data = array(
	'ticket_price'          => $ticket_price,
	'currency'              => $currency,
	'types'                 => $types,
	'type_stats'            => $type_stats,
	'tables'                => $tables_by_type,
	'available_seats_count' => $available_seats_count,
	'total_table_pool_free' => $total_table_pool_free,
);
?>
<div class="lccl-fr-wrapper">
	<div class="lccl-fr-layout">

		<!-- BOOKING FORM -->
		<form method="POST" action="" id="lccl-fundraiser-form" class="lccl-fr-card event-card" novalidate style="margin: 0; display: block;">
			<?php wp_nonce_field( LCCL_DE_Fundraiser_Form::NONCE_ACTION, LCCL_DE_Fundraiser_Form::NONCE_FIELD ); ?>
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $active_event['id'] ); ?>">

			<h1><?php echo esc_html( $active_event['name'] ); ?></h1>
			<p class="intro">
				<?php
				if ( ! empty( $active_event['description'] ) ) {
					echo esc_html( $active_event['description'] );
				} else {
					esc_html_e( 'Book individual seats or reserve whole tables for this fundraising event. Your booking will be confirmed after successful payment.', 'lccl-de' );
				}
				?>
			</p>

			<div class="section-title">
				<?php esc_html_e( 'Booking Type', 'lccl-de' ); ?> <span class="required">*</span>
			</div>

			<div class="booking-type">
				<label class="type-option <?php echo $available_seats_count > 0 ? 'active' : 'is-disabled'; ?>" id="typeIndividual">
					<input type="radio" name="booking_type" value="individual" <?php echo $available_seats_count > 0 ? 'checked' : 'disabled'; ?>>
					<div class="type-header-row">
						<span class="type-title"><?php esc_html_e( 'Individual Seats', 'lccl-de' ); ?></span>
						<?php if ( $available_seats_count > 0 ) : ?>
							<span class="lccl-fr-badge lccl-fr-badge--green"><?php echo esc_html( $available_seats_count ); ?> <?php esc_html_e( 'Seats Available', 'lccl-de' ); ?></span>
						<?php else : ?>
							<span class="lccl-fr-badge lccl-fr-badge--red"><?php esc_html_e( 'Sold Out', 'lccl-de' ); ?></span>
						<?php endif; ?>
					</div>
					<span class="type-help">
						<?php esc_html_e( 'Book individual tickets. Available seats are automatically allocated sequentially in ascending sequence from the stage.', 'lccl-de' ); ?>
					</span>
				</label>
				<label class="type-option <?php echo ( $available_seats_count === 0 && $total_table_pool_free > 0 ) ? 'active' : ''; ?> <?php echo $total_table_pool_free === 0 ? 'is-disabled' : ''; ?>" id="typeTable">
					<input type="radio" name="booking_type" value="table" <?php echo ( $available_seats_count === 0 && $total_table_pool_free > 0 ) ? 'checked' : ''; ?> <?php echo $total_table_pool_free === 0 ? 'disabled' : ''; ?>>
					<div class="type-header-row">
						<span class="type-title"><?php esc_html_e( 'Table Booking', 'lccl-de' ); ?></span>
						<?php if ( $total_table_pool_free > 0 ) : ?>
							<span class="lccl-fr-badge lccl-fr-badge--gold"><?php echo esc_html( $total_table_pool_free ); ?> <?php esc_html_e( 'Table(s) in Pool', 'lccl-de' ); ?></span>
						<?php else : ?>
							<span class="lccl-fr-badge lccl-fr-badge--muted"><?php esc_html_e( 'No Tables in Pool', 'lccl-de' ); ?></span>
						<?php endif; ?>
					</div>
					<span class="type-help">
						<?php esc_html_e( 'Select and reserve entire tables from the dedicated Table Pool. Reserved or blocked tables are locked.', 'lccl-de' ); ?>
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
						<label><?php esc_html_e( 'Ticket Price', 'lccl-de' ); ?></label>
						<input type="text" value="<?php echo esc_attr( $currency ); ?> <?php echo esc_attr( number_format( $ticket_price, 2 ) ); ?>" readonly>
					</div>
				</div>

				<?php if ( $available_seats_count === 0 ) : ?>
					<div class="note" style="margin-bottom: 12px; font-size: 13px; color: #dc2626; font-weight: 600; background: #fef2f2; border-left: 4px solid #dc2626;">
						<?php esc_html_e( 'Notice: All individual seats for this event are currently booked or reserved by administration.', 'lccl-de' ); ?>
					</div>
				<?php else : ?>
					<div class="note" style="margin-bottom: 12px; font-size: 13px; color: #4b5563; line-height: 1.5;">
						<?php esc_html_e( 'Seat Allocation Policy: Seats are automatically allocated sequentially in ascending order (#1, #2, #3...) from unblocked tables in the Seat Pool. Any table or seat reserved by event administration is automatically bypassed.', 'lccl-de' ); ?>
					</div>
				<?php endif; ?>

				<div class="seat-info" style="padding: 12px 15px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 13px; line-height: 1.5; border-radius: 4px;">
					<strong><?php esc_html_e( 'Seats to be assigned:', 'lccl-de' ); ?></strong> <span id="indivSeatCount"><?php echo $available_seats_count > 0 ? '1' : '0'; ?></span>
					<span style="color:#059669; font-weight:600; margin-left:8px;">(<?php echo esc_html( $available_seats_count ); ?> <?php esc_html_e( 'seats available in pool', 'lccl-de' ); ?>)</span>
					<?php if ( ! empty( $pool_stats['seat_pool_blocked'] ) ) : ?>
						<span style="color:#d97706; margin-left:8px;">• <?php echo esc_html( $pool_stats['seat_pool_blocked'] ); ?> <?php esc_html_e( 'seat(s) admin-blocked', 'lccl-de' ); ?></span>
					<?php endif; ?>
					<br>
					<span class="small" style="color: #64748b; font-size: 12px;"><?php esc_html_e( 'Your allocated seat numbers will appear immediately in your confirmation email upon payment completion.', 'lccl-de' ); ?></span>
				</div>
			</div>

			<!-- TABLE BOOKING -->
			<div id="panelTable" class="booking-panel <?php echo ( $available_seats_count === 0 && $total_table_pool_free > 0 ) ? 'active' : ''; ?>" style="<?php echo ( $available_seats_count === 0 && $total_table_pool_free > 0 ) ? '' : 'display:none;'; ?>">
				<div class="section-title"><?php esc_html_e( 'Table Booking', 'lccl-de' ); ?></div>
				<div class="lccl-fr-row">
					<div>
						<label for="tableTypeSelect"><?php esc_html_e( 'Table Tier / Type', 'lccl-de' ); ?> <span class="required">*</span></label>
						<select id="tableTypeSelect">
							<option value=""><?php esc_html_e( 'Select table type', 'lccl-de' ); ?></option>
							<?php foreach ( $types as $t ) : 
								$s = $type_stats[ $t['id'] ] ?? array( 'table_pool_free' => 0, 'total' => 0, 'table_pool_blocked' => 0, 'table_pool_booked' => 0 );
								$has_free = $s['table_pool_free'] > 0;
								$label_extra = '';
								if ( $has_free ) {
									$label_extra = sprintf( __( '— %d available in Table Pool', 'lccl-de' ), $s['table_pool_free'] );
								} elseif ( $s['total'] === 0 ) {
									$label_extra = __( '— No tables generated', 'lccl-de' );
								} elseif ( $s['table_pool_free'] === 0 && ( $s['table_pool_blocked'] > 0 || $s['table_pool_booked'] > 0 ) ) {
									$label_extra = $s['table_pool_blocked'] > 0 ? __( '— Reserved / Blocked', 'lccl-de' ) : __( '— Sold Out', 'lccl-de' );
								} else {
									$label_extra = __( '— Allocated to Seat Pool', 'lccl-de' );
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
						<label for="tableQty"><?php esc_html_e( 'Number of Tables', 'lccl-de' ); ?> <span class="required">*</span></label>
						<input type="number" id="tableQty" min="1" max="10" value="1" disabled>
					</div>
				</div>

				<div class="note" style="margin-bottom: 12px; font-size: 13px; color: #4b5563; line-height: 1.5;">
					<?php esc_html_e( 'Table Pool Policy: Only tables in the dedicated Table Pool are bookable as whole tables. Tables allocated to Individual Seats or marked Blocked/Reserved by administration cannot be booked.', 'lccl-de' ); ?>
				</div>

				<!-- Visual Table Grid Selector -->
				<div class="table-selector" style="margin-bottom: 18px;">
					<label><?php esc_html_e( 'Choose Table(s)', 'lccl-de' ); ?> <span class="required">*</span></label>
					
					<!-- Container where interactive chips render -->
					<div id="tableChipsContainer" class="lccl-fr-table-grid">
						<div style="grid-column: 1 / -1; padding: 18px; text-align: center; color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px;">
							<?php esc_html_e( 'Please select a table type above to view and choose available tables.', 'lccl-de' ); ?>
						</div>
					</div>

					<div class="lccl-fr-table-legend">
						<span><strong style="color:#854d0e;">🟢 Available</strong> (Click to select)</span>
						<span><strong style="color:#9a3412;">🔒 Reserved</strong> (Admin blocked)</span>
						<span><strong style="color:#475569;">⚫ Booked</strong> (Already taken)</span>
						<span><strong style="color:#64748b;">🟦 Seat Pool</strong> (Individual seats only)</span>
					</div>

					<!-- Hidden native multi-select for standard form submission compatibility -->
					<select name="selectedTables[]" id="tableSelect" multiple hidden></select>
				</div>
			</div>

			<!-- ATTENDEE INFORMATION -->
			<div class="section-title"><?php esc_html_e( 'Attendee Information', 'lccl-de' ); ?></div>
			<div class="lccl-fr-row">
				<div style="grid-column: span 2;">
					<label for="attendee_name"><?php esc_html_e( 'Name on Tickets / Reservation', 'lccl-de' ); ?> <span class="required">*</span></label>
					<input type="text" id="attendee_name" name="attendee_name" required placeholder="<?php esc_attr_e( 'Full Name', 'lccl-de' ); ?>">
				</div>
			</div>
			<div class="lccl-fr-row">
				<div>
					<label for="attendee_email"><?php esc_html_e( 'Email Address', 'lccl-de' ); ?> <span class="required">*</span></label>
					<input type="email" id="attendee_email" name="attendee_email" required placeholder="name@example.com">
				</div>
				<div>
					<label for="attendee_phone"><?php esc_html_e( 'Mobile Number', 'lccl-de' ); ?> <span class="required">*</span></label>
					<input type="tel" id="attendee_phone" name="attendee_phone" required placeholder="07X XXXXXXX">
				</div>
			</div>
			<div class="lccl-fr-row" style="margin-top: 10px;">
				<div style="grid-column: span 2;">
					<label for="booking_notes"><?php esc_html_e( 'Booking Notes / Special Requests (Optional)', 'lccl-de' ); ?></label>
					<textarea id="booking_notes" name="notes" rows="2" style="width: 100%; padding: 10px; border: 1px solid #ccc; font-size: 14px; min-height: 60px; box-sizing: border-box;" placeholder="<?php esc_attr_e( 'e.g. Dietary requirements, guest names for badges, table placement preferences...', 'lccl-de' ); ?>"></textarea>
				</div>
			</div>

			<!-- SUMMARY -->
			<div class="summary" style="margin-top: 25px;">
				<div class="summary-line" style="display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 10px 0; font-size: 15px;">
					<span><?php esc_html_e( 'Booking Type', 'lccl-de' ); ?></span>
					<strong id="summaryType"><?php esc_html_e( 'Individual Seats', 'lccl-de' ); ?></strong>
				</div>
				<div class="summary-line" style="display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 10px 0; font-size: 15px;">
					<span><?php esc_html_e( 'Seats / Tables', 'lccl-de' ); ?></span>
					<strong id="summaryQuantity"><?php esc_html_e( '1 Seat', 'lccl-de' ); ?></strong>
				</div>
				<div class="summary-line" style="display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 10px 0; font-size: 15px;">
					<span><?php esc_html_e( 'Assigned / Selected', 'lccl-de' ); ?></span>
					<strong id="summarySelection"><?php esc_html_e( '1 (Sequential auto-allocation)', 'lccl-de' ); ?></strong>
				</div>
				<div class="total">
					<span><?php esc_html_e( 'TOTAL AMOUNT', 'lccl-de' ); ?></span>
					<span><?php echo esc_html( $currency ); ?> <span id="lccl-fundraiser-total-display"><?php echo number_format( $ticket_price, 2 ); ?></span></span>
					<input type="hidden" name="total_amount" id="lccl-fundraiser-total-hidden" value="<?php echo esc_attr( $ticket_price ); ?>">
				</div>
			</div>

			<div class="terms">
				<label style="display: flex; align-items: center; font-size: 14px; margin-top: 20px;">
					<input type="checkbox" id="terms_agree" required style="width: auto; height: auto; margin-right: 8px;">
					<span><?php esc_html_e( 'I have read and agree to the event terms & conditions.', 'lccl-de' ); ?></span>
				</label>
			</div>

			<button class="lccl-fr-button" type="submit" id="lccl-fundraiser-submit" style="margin-top: 20px;" disabled>
				<?php esc_html_e( 'PROCEED TO PAYMENT', 'lccl-de' ); ?>
			</button>

			<div class="secure">
				<?php esc_html_e( '🔒 Payments are processed securely via Commercial Bank of Ceylon (CBC) PayCenter.', 'lccl-de' ); ?>
			</div>

		</form>

		<!-- EVENT INFORMATION / SEATING MAP ASIDE -->
		<aside class="event-card">
			<div class="dummy-image">
				<div>
					<h3><?php esc_html_e( 'TOGETHER,', 'lccl-de' ); ?><br><?php esc_html_e( 'WE SERVE', 'lccl-de' ); ?></h3>
					<p><?php esc_html_e( 'Support our community projects and help us create sustainable impact across our society.', 'lccl-de' ); ?></p>
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
					<span><?php esc_html_e( 'Base Ticket', 'lccl-de' ); ?></span>
					<strong><?php echo esc_html( $currency ); ?> <?php echo esc_html( number_format( $ticket_price, 2 ) ); ?></strong>
				</div>

				<div class="detail" style="border-top: 1px solid #e2e8f0; margin-top: 12px; padding-top: 12px;">
					<span><?php esc_html_e( 'Seat Pool (Available)', 'lccl-de' ); ?></span>
					<strong style="color: #166534; font-weight:600;"><?php echo esc_html( $available_seats_count ); ?> <?php esc_html_e( 'Seats', 'lccl-de' ); ?></strong>
				</div>
				<div class="detail">
					<span><?php esc_html_e( 'Table Pool (Available)', 'lccl-de' ); ?></span>
					<strong style="color: #854d0e; font-weight:600;"><?php echo esc_html( $total_table_pool_free ); ?> <?php esc_html_e( 'Whole Tables', 'lccl-de' ); ?></strong>
				</div>

				<!-- Visual Seating Map Layout -->
				<div class="seat-map">
					<div class="stage"><?php esc_html_e( 'STAGE', 'lccl-de' ); ?></div>
					<div class="seat-map-grid">
						<?php
						$seat_total   = max( 1, (int) ( $pool_stats['seats_total'] ?? 0 ) );
						$seat_booked  = (int) ( $pool_stats['seats_booked'] ?? 0 );
						$seat_blocked = (int) ( $pool_stats['seat_pool_blocked'] ?? 0 );
						$dots_count   = min( 60, $seat_total );
						for ( $i = 0; $i < $dots_count; $i++ ) :
							$ratio         = $i / (float) $dots_count;
							$booked_ratio  = $seat_total > 0 ? ( $seat_booked / $seat_total ) : 0;
							$blocked_ratio = $seat_total > 0 ? ( ( $seat_booked + $seat_blocked ) / $seat_total ) : 0;
							$dot_cls       = 'available';
							$dot_title     = __( 'Available seat slot', 'lccl-de' );
							if ( $ratio < $booked_ratio ) {
								$dot_cls   = 'sold';
								$dot_title = __( 'Booked seat', 'lccl-de' );
							} elseif ( $ratio < $blocked_ratio ) {
								$dot_cls   = 'blocked';
								$dot_title = __( 'Admin-reserved seat', 'lccl-de' );
							}
						?>
							<div class="seat-dot <?php echo esc_attr( $dot_cls ); ?>" title="<?php echo esc_attr( $dot_title ); ?>"></div>
						<?php endfor; ?>
					</div>
					<div class="legend">
						<span class="legend-avail"><?php esc_html_e( 'Available Seats', 'lccl-de' ); ?> (<?php echo esc_html( $available_seats_count ); ?>)</span>
						<span class="legend-blocked"><?php esc_html_e( 'Reserved / Blocked', 'lccl-de' ); ?> (<?php echo esc_html( $seat_blocked ); ?>)</span>
						<span class="legend-booked"><?php esc_html_e( 'Booked', 'lccl-de' ); ?> (<?php echo esc_html( $seat_booked ); ?>)</span>
						<span class="legend-table"><?php esc_html_e( 'Table Pool', 'lccl-de' ); ?> (<?php echo esc_html( $total_table_pool_free ); ?>)</span>
					</div>
				</div>

				<!-- Grand Ballroom Gala Seating Plan Image -->
				<div class="seating-plan-image-wrap">
					<h4><?php esc_html_e( 'Grand Ballroom Gala Seating Plan', 'lccl-de' ); ?></h4>
					<div class="seating-plan-trigger" id="openSeatingPlanTrigger" role="button" tabindex="0" title="<?php esc_attr_e( 'Click to view full size seating plan layout', 'lccl-de' ); ?>" aria-label="<?php esc_attr_e( 'Click to view full size seating plan layout', 'lccl-de' ); ?>">
						<img src="<?php echo esc_url( LCCL_DE_URL . 'assets/img/Grand_Ballroom_Gala_Seating_Plan.png' ); ?>" alt="<?php esc_attr_e( 'Grand Ballroom Gala Seating Plan', 'lccl-de' ); ?>" loading="lazy">
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
<div id="lcclSeatingPlanModal" class="lccl-sp-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Grand Ballroom Seating Plan', 'lccl-de' ); ?>" style="display: none;">
	<div class="lccl-sp-modal__overlay" data-close-sp-modal></div>
	<button type="button" class="lccl-sp-modal__close-btn" data-close-sp-modal aria-label="<?php esc_attr_e( 'Close seating plan (Esc)', 'lccl-de' ); ?>" title="<?php esc_attr_e( 'Close (Esc)', 'lccl-de' ); ?>">
		&times;
	</button>
	<div class="lccl-sp-modal__content" data-close-sp-modal>
		<img src="<?php echo esc_url( LCCL_DE_URL . 'assets/img/Grand_Ballroom_Gala_Seating_Plan.png' ); ?>" alt="<?php esc_attr_e( 'Grand Ballroom Gala Seating Plan', 'lccl-de' ); ?>" class="lccl-sp-modal__img" onclick="event.stopPropagation();">
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
		
		if (valid && termsAgree.checked) {
			submitBtn.removeAttribute('disabled');
		} else {
			submitBtn.setAttribute('disabled', 'disabled');
		}
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
			
			sumType.textContent = 'Individual Seats';
			sumQty.textContent = q + (q === 1 ? ' Seat' : ' Seats');
			sumSel.textContent = q > 0 ? (q + ' (Sequential auto-allocation)') : 'None';
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
			
			sumType.textContent = 'Table Booking';
			sumQty.textContent = selCount + (selCount === 1 ? ' Table' : ' Tables');
			sumSel.textContent = selLabels.length > 0 ? selLabels.join(', ') : 'None selected';
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

	inputTableQty.addEventListener('input', function() {
		var max = parseInt(this.max) || 1;
		var val = parseInt(this.value) || 1;
		if (val > max) {
			val = max;
			this.value = max;
		}
		if (val < 1) {
			val = 1;
			this.value = 1;
		}

		// Adjust selected chips if user decreased count below currently selected
		var selChips = chipsContainer.querySelectorAll('.lccl-fr-table-chip.is-selected');
		if (selChips.length > val) {
			for (var i = selChips.length - 1; i >= val; i--) {
				var tid = selChips[i].getAttribute('data-id');
				selChips[i].classList.remove('is-selected');
				for (var j = 0; j < selectTable.options.length; j++) {
					if (selectTable.options[j].value === tid) {
						selectTable.options[j].selected = false;
						break;
					}
				}
			}
		}
		updateTotal();
	});

	function renderTableChips(tid) {
		chipsContainer.innerHTML = '';
		selectTable.innerHTML = '';

		if (!tid || tid === '') {
			chipsContainer.innerHTML = '<div style="grid-column: 1 / -1; padding: 18px; text-align: center; color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px;">Please select a table tier above to view tables.</div>';
			inputTableQty.setAttribute('disabled', 'disabled');
			return;
		}

		var tierTables = fundData.tables[tid] || [];
		if (tierTables.length === 0) {
			chipsContainer.innerHTML = '<div style="grid-column: 1 / -1; padding: 18px; text-align: center; color: #dc2626; background: #fef2f2; border: 1px dashed #fca5a5; border-radius: 6px;">No tables have been generated for this tier yet.</div>';
			inputTableQty.setAttribute('disabled', 'disabled');
			return;
		}

		var availCount = 0;

		tierTables.forEach(function(tbl) {
			var isTableMode = (tbl.booking_mode === 'table');
			var isBlocked   = (parseInt(tbl.is_blocked) === 1);
			var isBooked    = (parseInt(tbl.is_booked) === 1);
			var isAvailable = (isTableMode && !isBlocked && !isBooked);

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

			if (!isTableMode) {
				chip.classList.add('in-seat-pool');
				chip.setAttribute('title', 'Table allocated to individual seats pool');
				statusSpan.textContent = 'Seat Pool';
			} else if (isBooked) {
				chip.classList.add('is-booked');
				chip.setAttribute('title', 'Table already booked and paid');
				statusSpan.textContent = 'Booked';
			} else if (isBlocked) {
				chip.classList.add('is-blocked');
				chip.setAttribute('title', 'Table reserved or blocked by administration');
				statusSpan.textContent = '🔒 Reserved';
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
					var maxQty = parseInt(inputTableQty.value) || 1;
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
						var selectedChips = chipsContainer.querySelectorAll('.lccl-fr-table-chip.is-selected');
						if (selectedChips.length >= maxQty) {
							// If space allows in available tables, automatically increment quantity for seamless UX
							var maxAvail = parseInt(inputTableQty.max) || availCount;
							if (maxQty < maxAvail) {
								maxQty++;
								inputTableQty.value = maxQty;
							} else {
								alert('You can only select ' + maxQty + ' table(s). Increase "Number of Tables" to select more.');
								return;
							}
						}

						chip.classList.add('is-selected');
						for (var k = 0; k < selectTable.options.length; k++) {
							if (selectTable.options[k].value === String(tbl.id)) {
								selectTable.options[k].selected = true;
								break;
							}
						}
					}

					updateTotal();
				});
			}

			chip.appendChild(statusSpan);
			chipsContainer.appendChild(chip);
		});

		if (availCount === 0) {
			inputTableQty.setAttribute('disabled', 'disabled');
			var notice = document.createElement('div');
			notice.style.gridColumn = '1 / -1';
			notice.style.padding = '12px 16px';
			notice.style.marginTop = '10px';
			notice.style.background = '#fef2f2';
			notice.style.color = '#991b1b';
			notice.style.fontSize = '13px';
			notice.style.borderRadius = '4px';
			notice.textContent = 'All tables in this tier are currently reserved, booked, or allocated to individual seats.';
			chipsContainer.appendChild(notice);
		} else {
			inputTableQty.removeAttribute('disabled');
			inputTableQty.max = availCount;
			inputTableQty.value = 1;

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
	
	// Initial state setup
	if (mode === 'individual') {
		switchMode('individual');
	} else {
		switchMode('table');
	}
	updateTotal();

	// Full-Size Grand Ballroom Seating Plan Modal Handlers
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
});
</script>