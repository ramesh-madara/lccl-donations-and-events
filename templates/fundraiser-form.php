<?php
/**
 * Fundraiser Seat Booking form.
 *
 * @package LCCL_Donations_And_Events
 */

defined( 'ABSPATH' ) || exit;

$gateway_configured = LCCL_DE_Paycenter_Client::is_configured();
?>
<div class="lccl-bdf lccl-bdf--membership lccl-bdf--fundraiser">
	<div class="lccl-membership-layout">
		<form
			class="lccl-bdf__form lccl-membership__form"
			onsubmit="return false;"
			id="lccl-fundraiser-form"
			novalidate
		>
			<?php wp_nonce_field( LCCL_DE_Fundraiser_Form::NONCE_ACTION, LCCL_DE_Fundraiser_Form::NONCE_FIELD ); ?>

			<header class="lccl-bdf__header">
				<h2 class="lccl-bdf__title"><?php esc_html_e( 'LCCL FUNDRAISER', 'lccl-de' ); ?></h2>
				<p class="lccl-bdf__intro"><?php esc_html_e( 'Important: This payment is specifically for the Colombo Leads Fundraiser.', 'lccl-de' ); ?></p>
				<p class="lccl-bdf__required-note">
					<?php
					printf(
						/* translators: %s: required field asterisk. */
						esc_html__( 'Fields marked with an %s are required', 'lccl-de' ),
						'<span class="lccl-bdf__req">*</span>'
					);
					?>
				</p>
			</header>

			<div class="lccl-mf">
				<div class="lccl-mf__section">
				<div class="lccl-df__pair">
					<div class="lccl-bdf__field lccl-mf__field--first">
						<label class="lccl-bdf__label"><?php esc_html_e( 'Ticket Price (LKR)', 'lccl-de' ); ?></label>
						<input class="lccl-bdf__input" type="number" value="6500" readonly>
					</div>
					<div class="lccl-bdf__field lccl-mf__field--first">
						<label class="lccl-bdf__label"><?php esc_html_e( 'Table Price (LKR)', 'lccl-de' ); ?></label>
						<input class="lccl-bdf__input" type="number" value="78000" readonly>
					</div>
				</div>

				<div class="lccl-df__pair">
					<div class="lccl-bdf__field">
						<label class="lccl-bdf__label" for="lccl-fundraiser-tickets"><?php esc_html_e( 'Number of Tickets', 'lccl-de' ); ?></label>
						<input class="lccl-bdf__input" type="number" id="lccl-fundraiser-tickets" name="num_tickets" min="1">
					</div>
					<div class="lccl-bdf__field">
						<label class="lccl-bdf__label" for="lccl-fundraiser-tables"><?php esc_html_e( 'Number of Tables', 'lccl-de' ); ?></label>
						<input class="lccl-bdf__input" type="number" id="lccl-fundraiser-tables" name="num_tables" min="1">
					</div>
				</div>

				<input type="hidden" name="ticketCount" id="lccl-fundraiser-hidden-tickets">

				<div class="lccl-bdf__field" id="lccl-fundraiser-tables-container" style="margin-top: 24px;">
					<label class="lccl-bdf__label"><?php esc_html_e( 'Select Tables', 'lccl-de' ); ?></label>
					<div class="lccl-fundraiser__table-grid" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
						<?php for ( $i = 1; $i <= 30; $i++ ) : ?>
							<label class="lccl-fundraiser__table-label" style="display: flex; align-items: center; gap: 5px; width: calc(20% - 10px); padding: 5px; background: var(--lccl-bdf-primary-soft); border-radius: 4px; cursor: pointer;">
								<input type="checkbox" name="selectedTables[]" value="T<?php echo $i; ?>" class="lccl-fundraiser__table-cb">
								<span style="font-size: 14px; font-weight: 600; color: var(--lccl-bdf-heading);">T<?php echo $i; ?></span>
							</label>
						<?php endfor; ?>
					</div>
				</div>
			</div>

			<div class="lccl-mf__section">
				<h3 class="lccl-mf__section-title"><?php esc_html_e( 'Attendee Information', 'lccl-de' ); ?></h3>
				<div class="lccl-bdf__field lccl-mf__field--first">
					<label class="lccl-bdf__label" for="lccl-fundraiser-name"><?php esc_html_e( 'Name on Tickets', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span></label>
					<input class="lccl-bdf__input" type="text" id="lccl-fundraiser-name" name="attendee_name" required>
				</div>
				<div class="lccl-df__pair">
					<div class="lccl-bdf__field">
						<label class="lccl-bdf__label" for="lccl-fundraiser-email"><?php esc_html_e( 'Email Address', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span></label>
						<input class="lccl-bdf__input" type="email" id="lccl-fundraiser-email" name="attendee_email" required>
					</div>
					<div class="lccl-bdf__field">
						<label class="lccl-bdf__label" for="lccl-fundraiser-phone"><?php esc_html_e( 'Mobile Number', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span></label>
						<input class="lccl-bdf__input" type="tel" id="lccl-fundraiser-phone" name="attendee_phone" required>
					</div>
				</div>
			</div>

			<div class="lccl-df__summary">
				<div class="lccl-df__summary-row lccl-df__summary-row--total">
					<span class="lccl-df__summary-label"><?php esc_html_e( 'Total Amount', 'lccl-de' ); ?></span>
					<span class="lccl-df__summary-value"><span class="lccl-df__currency-code">LKR</span> <span id="lccl-fundraiser-total-display">0.00</span></span>
					<input type="hidden" name="total_amount" id="lccl-fundraiser-total-hidden" value="0">
				</div>
			</div>

			<div class="lccl-bdf__terms">
				<label class="lccl-bdf__terms-label">
					<input class="lccl-bdf__checkbox" type="checkbox" name="terms" id="lccl-fundraiser-terms" required>
					<span>
						<?php
						printf(
							/* translators: %s: link to terms and conditions */
							esc_html__( 'I agree to the %s.', 'lccl-de' ),
							'<a href="' . esc_url( get_privacy_policy_url() ) . '" target="_blank">' . esc_html__( 'Terms and Conditions', 'lccl-de' ) . '</a>'
						);
						?>
					</span>
				</label>
			</div>

			<button
				class="lccl-bdf__submit lccl-df__submit lccl-mf__pay-btn"
				type="button"
				id="lccl-fundraiser-submit-btn"
				disabled aria-disabled="true"
				onclick="alert('This is a concept UI. Payments and database connections are disabled.');"
			>
				<span class="lccl-mf__pay-label"><?php esc_html_e( 'Proceed to Payment', 'lccl-de' ); ?></span>
			</button>

				<p class="lccl-mf__secure-note">
					<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
					<?php esc_html_e( 'Payments are processed securely via Commercial Bank of Ceylon (CBC) Paycenter.', 'lccl-de' ); ?>
				</p>
			</div>
		</form>

		<div class="lccl-membership__image-panel">
			<img
				class="lccl-membership__image"
				src="<?php echo esc_url( function_exists( 'content_url' ) ? content_url( '/uploads/2026/09/membership-1.jpg' ) : 'https://www.colomboleads.org/wp-content/uploads/2026/09/membership-1.jpg' ); ?>"
				alt="<?php esc_attr_e( 'Fundraiser - Lions Club of Colombo LEADS', 'lccl-de' ); ?>"
				loading="lazy"
				style="border-radius: 8px;"
			>
		</div>
	</div>

	<div class="lccl-fundraiser__map-container" style="margin-top: 40px; text-align: center;">
		<h3 class="lccl-bdf__title" style="margin-bottom: 20px; font-size: 1.5rem; text-align: center;"><?php esc_html_e( 'Seating Map', 'lccl-de' ); ?></h3>
		<img src="https://i.imgur.com/gFRICVl.jpeg" alt="Seating Map" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const form = document.getElementById('lccl-fundraiser-form');
	if (!form) return;

	const numTickets = document.getElementById('lccl-fundraiser-tickets');
	const numTables = document.getElementById('lccl-fundraiser-tables');
	const hiddenTickets = document.getElementById('lccl-fundraiser-hidden-tickets');
	const tablesContainer = document.getElementById('lccl-fundraiser-tables-container');
	const checkboxes = document.querySelectorAll('.lccl-fundraiser__table-cb');
	
	const totalDisplay = document.getElementById('lccl-fundraiser-total-display');
	const totalHidden = document.getElementById('lccl-fundraiser-total-hidden');
	const submitBtn = document.getElementById('lccl-fundraiser-submit-btn');
	const termsCb = document.getElementById('lccl-fundraiser-terms');

	const ticketPrice = 6500;
	const tablePrice = 78000;
	
	let requestedTablesCount = 0;

	function calculateTotal() {
		let tickets = parseInt(numTickets.value, 10) || 0;
		let tables = parseInt(numTables.value, 10) || 0;
		requestedTablesCount = tables;

		let total = (tickets * ticketPrice) + (tables * tablePrice);
		
		totalDisplay.textContent = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
		totalHidden.value = total;

		checkFormValidity();
	}

	function checkFormValidity() {
		const isValid = termsCb.checked && parseInt(totalHidden.value, 10) > 0;
		if (isValid) {
			submitBtn.removeAttribute('disabled');
			submitBtn.removeAttribute('aria-disabled');
		} else {
			submitBtn.setAttribute('disabled', 'disabled');
			submitBtn.setAttribute('aria-disabled', 'true');
		}
	}

	numTickets.addEventListener('input', calculateTotal);
	numTables.addEventListener('input', calculateTotal);
	termsCb.addEventListener('change', checkFormValidity);

	checkboxes.forEach(cb => {
		cb.addEventListener('change', function() {
			let checkedCount = document.querySelectorAll('.lccl-fundraiser__table-cb:checked').length;
			if (checkedCount > requestedTablesCount) {
				alert(`You can only select ${requestedTablesCount} tables.`);
				this.checked = false;
			}
		});
	});
});
</script>
