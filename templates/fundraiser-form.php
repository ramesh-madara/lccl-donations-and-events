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
	echo '<div class="lccl-fr-card" style="margin-top:20px; text-align:center;"><p>No active fundraiser event found.</p></div>';
	return;
}

$types = LCCL_DE_Fundraiser::get_table_types( $active_event['id'] );
$tables = LCCL_DE_Fundraiser::get_tables( $active_event['id'] );
$ticket_price = (float) $active_event['ticket_price'];

// Group tables by type ID
$tables_by_type = array();
foreach ( $tables as $tbl ) {
	if ( ! isset( $tables_by_type[ $tbl['type_id'] ] ) ) {
		$tables_by_type[ $tbl['type_id'] ] = array();
	}
	$tables_by_type[ $tbl['type_id'] ][] = $tbl;
}

// Ensure JS gets the data
$js_data = array(
	'ticket_price' => $ticket_price,
	'types'        => $types,
	'tables'       => $tables_by_type,
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
				<?php esc_html_e( 'Book individual seats or reserve a table for this fundraising event.', 'lccl-de' ); ?>
				<?php esc_html_e( 'Your booking will be confirmed after successful payment.', 'lccl-de' ); ?>
			</p>

			<div class="section-title">
				<?php esc_html_e( 'Booking Type', 'lccl-de' ); ?> <span class="required">*</span>
			</div>

			<div class="booking-type">
				<label class="type-option active" id="typeIndividual">
					<input type="radio" name="booking_type" value="individual" checked>
					<span class="type-title"><?php esc_html_e( 'Individual Seats', 'lccl-de' ); ?></span>
					<span class="type-help">
						<?php esc_html_e( 'Book one or more seats. The system will assign available seats automatically.', 'lccl-de' ); ?>
					</span>
				</label>
				<label class="type-option" id="typeTable">
					<input type="radio" name="booking_type" value="table">
					<span class="type-title"><?php esc_html_e( 'Table Booking', 'lccl-de' ); ?></span>
					<span class="type-help">
						<?php esc_html_e( 'Select an available table. All seats assigned to the table will be reserved together.', 'lccl-de' ); ?>
					</span>
				</label>
			</div>

			<!-- INDIVIDUAL SEAT BOOKING -->
			<div id="panelIndividual" class="booking-panel active">
				<div class="section-title"><?php esc_html_e( 'Individual Seat Booking', 'lccl-de' ); ?></div>
				<div class="lccl-fr-row">
					<div>
						<label for="ticketQty"><?php esc_html_e( 'Number of Seats', 'lccl-de' ); ?> <span class="required">*</span></label>
						<input type="number" name="qty" id="ticketQty" min="1" max="10" value="1">
					</div>
					<div>
						<label><?php esc_html_e( 'Ticket Price', 'lccl-de' ); ?></label>
						<input type="text" value="LKR <?php echo esc_attr( number_format( $ticket_price, 2 ) ); ?>" readonly>
					</div>
				</div>
				<div class="note" style="margin-bottom: 12px; font-size: 13px; color: #666; line-height: 1.5;">
					<?php esc_html_e( 'Individual seat bookings do not require you to select a table. The system will allocate available seats automatically, preferably together.', 'lccl-de' ); ?>
				</div>
				<div class="seat-info" style="padding: 12px 15px; background: #f5f5f5; font-size: 13px; line-height: 1.5;">
					<strong><?php esc_html_e( 'Seats to be assigned:', 'lccl-de' ); ?></strong> <span id="indivSeatCount">1</span><br>
					<span class="small" style="color: #777; font-size: 11px;"><?php esc_html_e( 'The system will allocate available seats automatically.', 'lccl-de' ); ?></span>
				</div>
			</div>

			<!-- TABLE BOOKING -->
			<div id="panelTable" class="booking-panel" style="display: none;">
				<div class="section-title"><?php esc_html_e( 'Table Booking', 'lccl-de' ); ?></div>
				<div class="lccl-fr-row">
					<div>
						<label for="tableTypeSelect"><?php esc_html_e( 'Table Type', 'lccl-de' ); ?> <span class="required">*</span></label>
						<select id="tableTypeSelect">
							<option value=""><?php esc_html_e( 'Select table type', 'lccl-de' ); ?></option>
							<?php foreach ( $types as $t ) : ?>
								<option value="<?php echo esc_attr( $t['id'] ); ?>" data-price="<?php echo esc_attr( $t['price'] ); ?>"><?php echo esc_html( $t['name'] ); ?> – LKR <?php echo esc_html( number_format( $t['price'], 2 ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div>
						<label for="tableQty"><?php esc_html_e( 'Number of Tables', 'lccl-de' ); ?> <span class="required">*</span></label>
						<input type="number" id="tableQty" min="1" max="10" value="1" disabled>
					</div>
				</div>
				<div class="note" style="margin-bottom: 12px; font-size: 13px; color: #666; line-height: 1.5;">
					<?php esc_html_e( 'Select the available table(s) you would like to reserve. Booked tables are not available for selection.', 'lccl-de' ); ?>
				</div>
				<div class="table-selector" style="margin-bottom: 15px;">
					<label for="tableSelect"><?php esc_html_e( 'Select Table(s)', 'lccl-de' ); ?> <span class="required">*</span></label>
					<select name="selectedTables[]" id="tableSelect" multiple size="6" disabled style="height: auto; min-height: 120px;">
						<option value=""><?php esc_html_e( 'Select a table type first', 'lccl-de' ); ?></option>
					</select>
					<div class="table-status-note" style="font-size: 11px; color: #777; margin-top: 5px;">
						<?php esc_html_e( 'Hold Ctrl (Windows) or Command (Mac) to select multiple tables.', 'lccl-de' ); ?>
					</div>
				</div>
			</div>

			<!-- ATTENDEE INFORMATION -->
			<div class="section-title"><?php esc_html_e( 'Attendee Information', 'lccl-de' ); ?></div>
			<div class="lccl-fr-row">
				<div style="grid-column: span 2;">
					<label><?php esc_html_e( 'Name on Tickets', 'lccl-de' ); ?> <span class="required">*</span></label>
					<input type="text" name="attendee_name" required>
				</div>
			</div>
			<div class="lccl-fr-row">
				<div>
					<label><?php esc_html_e( 'Email Address', 'lccl-de' ); ?> <span class="required">*</span></label>
					<input type="email" name="attendee_email" required>
				</div>
				<div>
					<label><?php esc_html_e( 'Mobile Number', 'lccl-de' ); ?> <span class="required">*</span></label>
					<input type="tel" name="attendee_phone" required>
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
					<strong id="summarySelection">1</strong>
				</div>
				<div class="total">
					<span><?php esc_html_e( 'TOTAL AMOUNT', 'lccl-de' ); ?></span>
					<span>LKR <span id="lccl-fundraiser-total-display"><?php echo number_format( $ticket_price, 2 ); ?></span></span>
					<input type="hidden" name="total_amount" id="lccl-fundraiser-total-hidden" value="<?php echo esc_attr( $ticket_price ); ?>">
				</div>
			</div>

			<div class="terms">
				<label style="display: flex; align-items: center; font-size: 14px; margin-top: 20px;">
					<input type="checkbox" id="terms_agree" required style="width: auto; height: auto; margin-right: 8px;">
					<span><?php esc_html_e( 'I have read and agree to the', 'lccl-de' ); ?> <a href="#" onclick="return false;"><?php esc_html_e( 'Terms & Conditions', 'lccl-de' ); ?></a>.</span>
				</label>
			</div>

			<button class="lccl-fr-button" type="submit" id="lccl-fundraiser-submit" style="margin-top: 20px;" disabled>
				<?php esc_html_e( 'PROCEED TO PAYMENT', 'lccl-de' ); ?>
			</button>

			<div class="secure">
				<?php esc_html_e( '🔒 Payments are processed securely via Commercial Bank of Ceylon (CBC) PayCenter.', 'lccl-de' ); ?>
			</div>

		</form>

		<!-- EVENT INFORMATION / SEATING MAP -->
		<aside class="event-card">
			<div class="dummy-image">
				<div>
					<h3><?php esc_html_e( 'TOGETHER,', 'lccl-de' ); ?><br><?php esc_html_e( 'WE SERVE', 'lccl-de' ); ?></h3>
					<p><?php esc_html_e( 'Support our fundraising initiatives and help us continue making a meaningful difference in our community.', 'lccl-de' ); ?></p>
				</div>
			</div>
			
			<div class="event-details">
				<h2><?php echo esc_html( $active_event['name'] ); ?></h2>
				<div class="detail">
					<span><?php esc_html_e( 'Date', 'lccl-de' ); ?></span>
					<strong><?php echo esc_html( $active_event['event_date'] ? gmdate( '15 M Y', strtotime( $active_event['event_date'] ) ) : 'TBA' ); ?></strong>
				</div>
				<div class="detail">
					<span><?php esc_html_e( 'Time', 'lccl-de' ); ?></span>
					<strong><?php echo esc_html( $active_event['event_time'] ? gmdate( 'h:i A', strtotime( $active_event['event_time'] ) ) : 'TBA' ); ?></strong>
				</div>
				<div class="detail">
					<span><?php esc_html_e( 'Venue', 'lccl-de' ); ?></span>
					<strong><?php echo esc_html( $active_event['venue'] ?: 'TBA' ); ?></strong>
				</div>
				<div class="detail">
					<span><?php esc_html_e( 'Base Ticket', 'lccl-de' ); ?></span>
					<strong>LKR <?php echo esc_html( number_format( $ticket_price, 2 ) ); ?></strong>
				</div>

				<div class="seat-map">
					<div class="stage"><?php esc_html_e( 'STAGE', 'lccl-de' ); ?></div>
					<div class="seat-map-grid">
						<?php for ( $i = 0; $i < 40; $i++ ) : ?>
							<div class="seat-dot <?php echo ( wp_rand(0, 10) < 3 ) ? 'sold' : ''; ?>"></div>
						<?php endfor; ?>
					</div>
					<div class="legend">
						<span><?php esc_html_e( 'Available', 'lccl-de' ); ?></span>
						<span class="booked"><?php esc_html_e( 'Booked', 'lccl-de' ); ?></span>
					</div>
				</div>
			</div>
		</aside>

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
	
	var totalDisp = document.getElementById('lccl-fundraiser-total-display');
	var totalHid = document.getElementById('lccl-fundraiser-total-hidden');
	
	var sumType = document.getElementById('summaryType');
	var sumQty = document.getElementById('summaryQuantity');
	var sumSel = document.getElementById('summarySelection');

	var submitBtn = document.getElementById('lccl-fundraiser-submit');
	var termsAgree = document.getElementById('terms_agree');
	
	var mode = 'individual';
	
	function formatLKR(val) {
		return val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
	}

	function validateState() {
		var valid = false;
		if (mode === 'individual') {
			var q = parseInt(inputQty.value) || 0;
			valid = q > 0;
		} else {
			var tq = parseInt(inputTableQty.value) || 0;
			var type = selectType.value;
			var selCount = 0;
			for (var i = 0; i < selectTable.options.length; i++) {
				if (selectTable.options[i].selected && selectTable.options[i].value !== '') selCount++;
			}
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
			tot = q * fundData.ticket_price;
			indivSeatCount.textContent = q;
			
			sumType.textContent = 'Individual Seats';
			sumQty.textContent = q + (q === 1 ? ' Seat' : ' Seats');
			sumSel.textContent = q;
		} else {
			var tq = parseInt(inputTableQty.value) || 0;
			var type = selectType.value;
			var selCount = 0;
			for (var i = 0; i < selectTable.options.length; i++) {
				if (selectTable.options[i].selected && selectTable.options[i].value !== '') selCount++;
			}
			
			if (type && type !== '') {
				var opt = selectType.options[selectType.selectedIndex];
				var p = parseFloat(opt.getAttribute('data-price')) || 0;
				tot = tq * p;
			}
			
			sumType.textContent = 'Table Booking';
			sumQty.textContent = tq + (tq === 1 ? ' Table' : ' Tables');
			sumSel.textContent = selCount + ' of ' + tq;
		}
		
		totalDisp.textContent = formatLKR(tot);
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

	typeIndiv.addEventListener('click', function() {
		typeIndiv.querySelector('input').checked = true;
		switchMode('individual');
	});
	
	typeTable.addEventListener('click', function() {
		typeTable.querySelector('input').checked = true;
		switchMode('table');
	});
	
	inputQty.addEventListener('input', updateTotal);
	inputTableQty.addEventListener('input', function() {
		// Prevent user from selecting more tables than available
		var max = 0;
		for (var i = 0; i < selectTable.options.length; i++) {
			if (!selectTable.options[i].disabled && selectTable.options[i].value !== '') max++;
		}
		var v = parseInt(this.value) || 1;
		if (v > max) this.value = max;
		updateTotal();
	});
	
	selectType.addEventListener('change', function() {
		var tid = this.value;
		selectTable.innerHTML = '';
		if (!tid || tid === '') {
			selectTable.innerHTML = '<option value="">Select a table type first</option>';
			selectTable.setAttribute('disabled', 'disabled');
			inputTableQty.setAttribute('disabled', 'disabled');
		} else {
			var avail = fundData.tables[tid] || [];
			var c = 0;
			for (var i = 0; i < avail.length; i++) {
				if (parseInt(avail[i].is_booked) === 0) {
					var opt = document.createElement('option');
					opt.value = avail[i].id;
					opt.textContent = avail[i].table_label;
					selectTable.appendChild(opt);
					c++;
				}
			}
			if (c === 0) {
				selectTable.innerHTML = '<option value="">No available tables for this tier</option>';
				selectTable.setAttribute('disabled', 'disabled');
				inputTableQty.setAttribute('disabled', 'disabled');
			} else {
				selectTable.removeAttribute('disabled');
				inputTableQty.removeAttribute('disabled');
			}
		}
		updateTotal();
	});
	
	selectTable.addEventListener('change', function() {
		var selCount = 0;
		for (var i = 0; i < this.options.length; i++) {
			if (this.options[i].selected && this.options[i].value !== '') selCount++;
		}
		
		var max = parseInt(inputTableQty.value) || 1;
		if (selCount > max) {
			// Deselect the last selected one
			// Browser multiselect natively supports ctrl+click, 
			// if they select too many, we just alert them and revert
			alert('You can only select ' + max + ' table(s). Increase the "Number of Tables" to select more.');
			// Simply validate down
			for (var j = this.options.length - 1; j >= 0; j--) {
				if (this.options[j].selected) {
					this.options[j].selected = false;
					selCount--;
					if (selCount <= max) break;
				}
			}
		}
		
		updateTotal();
	});
	
	termsAgree.addEventListener('change', validateState);
	
	// Init
	updateTotal();
});
</script>