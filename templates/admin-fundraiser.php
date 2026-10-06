<?php
/**
 * LCCL Fundraiser Dashboard template.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array $fr_events   List of events.
 * @var array $fr_stats    Stats array (events, public, bookings, revenue).
 * @var array $fr_bookings List of all bookings.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="lccl-prog__stage">
	<div class="lccl-fr-dashboard">
		<div class="lccl-fr-stat">
			<p class="lccl-fr-stat__label"><?php esc_html_e( 'Total Events', 'lccl-de' ); ?></p>
			<p class="lccl-fr-stat__value"><?php echo esc_html( $fr_stats['total_events'] ); ?></p>
		</div>
		<div class="lccl-fr-stat">
			<p class="lccl-fr-stat__label"><?php esc_html_e( 'Public Events', 'lccl-de' ); ?></p>
			<p class="lccl-fr-stat__value"><?php echo esc_html( $fr_stats['public_events'] ); ?></p>
		</div>
		<div class="lccl-fr-stat">
			<p class="lccl-fr-stat__label"><?php esc_html_e( 'Total Bookings', 'lccl-de' ); ?></p>
			<p class="lccl-fr-stat__value"><?php echo esc_html( $fr_stats['total_bookings'] ); ?></p>
		</div>
		<div class="lccl-fr-stat">
			<p class="lccl-fr-stat__label"><?php esc_html_e( 'Confirmed Revenue', 'lccl-de' ); ?></p>
			<p class="lccl-fr-stat__value">LKR <?php echo esc_html( number_format( $fr_stats['total_revenue'], 2 ) ); ?></p>
		</div>
	</div>

	<nav class="lccl-fr-nav" id="lccl-fr-nav">
		<button type="button" class="lccl-fr-nav__btn is-active" data-fr-target="overview"><?php esc_html_e( 'Events Overview', 'lccl-de' ); ?></button>
		<button type="button" class="lccl-fr-nav__btn" data-fr-target="bookings"><?php esc_html_e( 'All Bookings', 'lccl-de' ); ?></button>
		<button type="button" class="lccl-fr-nav__btn" data-fr-target="create"><?php esc_html_e( 'Create Event', 'lccl-de' ); ?></button>
	</nav>

	<div id="lccl-fr-msg"></div>

	<!-- TAB: EVENTS OVERVIEW -->
	<div class="lccl-fr-panel is-active" id="lccl-fr-panel-overview">
		<div class="lccl-fr-grid">
			<!-- Event List Column -->
			<div>
				<div class="lccl-fr-card">
					<div class="lccl-fr-card__header">
						<h3 class="lccl-fr-card__title"><?php esc_html_e( 'Select Event', 'lccl-de' ); ?></h3>
					</div>
					<div class="lccl-fr-card__body" style="padding: 0;">
						<?php if ( empty( $fr_events ) ) : ?>
							<p class="lccl-fr-empty"><?php esc_html_e( 'No events found.', 'lccl-de' ); ?></p>
						<?php else : ?>
							<?php foreach ( $fr_events as $idx => $ev ) : ?>
								<a href="#" class="lccl-fr-event-item <?php echo 0 === $idx ? 'is-selected' : ''; ?>" data-fr-event-id="<?php echo esc_attr( $ev['id'] ); ?>" data-fr-event="<?php echo esc_attr( wp_json_encode( $ev ) ); ?>">
									<span class="lccl-fr-event-item__name"><?php echo esc_html( $ev['name'] ); ?></span>
									<span class="lccl-fr-event-item__meta">
										<?php echo esc_html( $ev['event_date'] ? date( 'M j, Y', strtotime( $ev['event_date'] ) ) : 'TBA' ); ?>
										<span class="lccl-fr-badge lccl-fr-badge--<?php echo esc_attr( $ev['status'] ); ?>" style="float: right;"><?php echo esc_html( $ev['status'] ); ?></span>
									</span>
								</a>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<!-- Event Details Column -->
			<div id="lccl-fr-event-editor">
				<?php if ( empty( $fr_events ) ) : ?>
					<div class="lccl-fr-card"><div class="lccl-fr-card__body lccl-fr-empty"><?php esc_html_e( 'Create an event first.', 'lccl-de' ); ?></div></div>
				<?php else : ?>
					<!-- Details populated by JS -->
					<div class="lccl-fr-card">
						<div class="lccl-fr-card__body">
							<div class="lccl-fr-event-detail-header">
								<div>
									<h2 class="lccl-fr-event-detail-title" id="fr-edit-title">Event Name</h2>
									<p class="lccl-fr-event-detail-meta" id="fr-edit-meta">Date - Venue</p>
								</div>
								<div class="lccl-fr-btn-row">
									<button type="button" class="lccl-fr-btn lccl-fr-btn--secondary" id="fr-btn-toggle-public">Toggle Public</button>
									<button type="button" class="lccl-fr-btn lccl-fr-btn--danger" id="fr-btn-delete-event">Delete</button>
								</div>
							</div>
							
							<form id="lccl-fr-edit-form">
								<input type="hidden" name="event_id" id="fr-edit-id">
								<input type="hidden" name="status" id="fr-edit-status">
								
								<div class="lccl-fr-form-row">
									<label><?php esc_html_e( 'Event Name', 'lccl-de' ); ?></label>
									<input type="text" name="name" id="fr-edit-name" required>
								</div>
								<div class="lccl-fr-form-pair">
									<div class="lccl-fr-form-row">
										<label><?php esc_html_e( 'Date', 'lccl-de' ); ?></label>
										<input type="date" name="event_date" id="fr-edit-date">
									</div>
									<div class="lccl-fr-form-row">
										<label><?php esc_html_e( 'Time', 'lccl-de' ); ?></label>
										<input type="time" name="event_time" id="fr-edit-time">
									</div>
									<div class="lccl-fr-form-row">
										<label><?php esc_html_e( 'Base Ticket Price (LKR)', 'lccl-de' ); ?></label>
										<input type="number" step="0.01" name="ticket_price" id="fr-edit-price">
									</div>
									<div class="lccl-fr-form-row">
										<label><?php esc_html_e( 'Status', 'lccl-de' ); ?></label>
										<select id="fr-edit-status-select" onchange="document.getElementById('fr-edit-status').value = this.value;">
											<option value="upcoming">Upcoming</option>
											<option value="ongoing">Ongoing</option>
											<option value="completed">Completed</option>
											<option value="archived">Archived</option>
										</select>
									</div>
								</div>
								<div class="lccl-fr-form-row">
									<label><?php esc_html_e( 'Venue', 'lccl-de' ); ?></label>
									<input type="text" name="venue" id="fr-edit-venue" required>
								</div>
								<div class="lccl-fr-form-row">
									<label><?php esc_html_e( 'Description', 'lccl-de' ); ?></label>
									<textarea name="description" id="fr-edit-desc"></textarea>
								</div>
								
								<div class="lccl-fr-btn-row">
									<button type="submit" class="lccl-fr-btn lccl-fr-btn--primary"><?php esc_html_e( 'Save Changes', 'lccl-de' ); ?></button>
									<span id="fr-edit-status-badge" class="lccl-fr-badge"></span>
								</div>
							</form>
						</div>
					</div>

					<div class="lccl-fr-card">
						<div class="lccl-fr-card__header">
							<h3 class="lccl-fr-card__title"><?php esc_html_e( 'Table Inventory & Pricing', 'lccl-de' ); ?></h3>
						</div>
						<div class="lccl-fr-card__body">
							<div class="lccl-fr-info">
								<?php esc_html_e( 'Define table tiers (e.g. VIP 10-seater) and generate physical table inventory for the event map.', 'lccl-de' ); ?>
							</div>
							
							<!-- Add Table Type Form -->
							<form id="lccl-fr-add-type-form" class="lccl-fr-type-row" style="background: #fafafa; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
								<div>
									<label style="font-size: 11px; text-transform: uppercase; color: #777;">Tier Name</label>
									<input type="text" name="name" placeholder="VIP Table" required style="width: 100%;">
								</div>
								<div>
									<label style="font-size: 11px; text-transform: uppercase; color: #777;">Seats</label>
									<input type="number" name="capacity" value="10" min="1" style="width: 80px;">
								</div>
								<div>
									<label style="font-size: 11px; text-transform: uppercase; color: #777;">Price (LKR)</label>
									<input type="number" name="price" step="0.01" value="0.00" style="width: 120px;">
								</div>
								<div>
									<label style="font-size: 11px; visibility: hidden;">Action</label><br>
									<button type="submit" class="lccl-fr-btn lccl-fr-btn--primary lccl-fr-btn--sm">Add Tier</button>
								</div>
							</form>

							<div id="lccl-fr-types-list">
								<p class="lccl-fr-empty" style="padding: 10px;"><?php esc_html_e( 'Table configuration is loaded dynamically. Save the event to refresh.', 'lccl-de' ); ?></p>
							</div>

							<!-- Generator -->
							<div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee;">
								<h4 style="margin-top: 0; font-size: 13px; text-transform: uppercase;">Generate Tables</h4>
								<form id="lccl-fr-generate-form" style="display: flex; gap: 8px; align-items: flex-end;">
									<div>
										<label style="font-size: 11px; color: #777;">Tier</label>
										<select name="type_id" id="fr-gen-type" style="width: 150px;"></select>
									</div>
									<div>
										<label style="font-size: 11px; color: #777;">Prefix</label>
										<input type="text" name="prefix" value="T" style="width: 60px;">
									</div>
									<div>
										<label style="font-size: 11px; color: #777;">Count</label>
										<input type="number" name="count" value="10" min="1" max="50" style="width: 80px;">
									</div>
									<button type="submit" class="lccl-fr-btn lccl-fr-btn--secondary lccl-fr-btn--sm">Generate</button>
								</form>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- TAB: ALL BOOKINGS -->
	<div class="lccl-fr-panel" id="lccl-fr-panel-bookings">
		<div class="lccl-fr-card">
			<div class="lccl-fr-card__header">
				<h3 class="lccl-fr-card__title"><?php esc_html_e( 'All Fundraiser Bookings', 'lccl-de' ); ?></h3>
			</div>
			<div class="lccl-fr-table-wrap">
				<table class="lccl-fr-table">
					<thead>
						<tr>
							<th>Reference</th>
							<th>Event</th>
							<th>Customer</th>
							<th>Type</th>
							<th>Qty</th>
							<th>Amount</th>
							<th>Status</th>
							<th>Date</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $fr_bookings ) ) : ?>
							<tr><td colspan="8" class="lccl-fr-empty"><?php esc_html_e( 'No bookings yet.', 'lccl-de' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $fr_bookings as $bk ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $bk['order_ref'] ); ?></strong></td>
									<td><?php echo esc_html( $bk['event_name'] ); ?></td>
									<td>
										<?php echo esc_html( $bk['attendee_name'] ); ?><br>
										<small style="color:#777;"><?php echo esc_html( $bk['attendee_email'] ); ?></small>
									</td>
									<td><?php echo esc_html( ucfirst( $bk['booking_type'] ) ); ?></td>
									<td><?php echo esc_html( $bk['qty'] ); ?></td>
									<td><?php echo esc_html( number_format( $bk['amount_lkr'], 2 ) ); ?></td>
									<td>
										<span class="lccl-fr-badge <?php echo 'paid' === $bk['status'] ? 'lccl-fr-badge--ongoing' : 'lccl-fr-badge--draft'; ?>">
											<?php echo esc_html( $bk['status'] ); ?>
										</span>
									</td>
									<td><small><?php echo esc_html( date( 'M j, y H:i', strtotime( $bk['created_at'] ) ) ); ?></small></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- TAB: CREATE EVENT -->
	<div class="lccl-fr-panel" id="lccl-fr-panel-create">
		<div class="lccl-fr-card" style="max-width: 800px;">
			<div class="lccl-fr-card__header">
				<h3 class="lccl-fr-card__title"><?php esc_html_e( 'Create New Fundraiser / Event', 'lccl-de' ); ?></h3>
			</div>
			<div class="lccl-fr-card__body">
				<div class="lccl-fr-info">
					<?php esc_html_e( 'Create an event first. After that, you can configure ticket pricing, table types, and the seating inventory from the Events Overview tab.', 'lccl-de' ); ?>
				</div>
				<form id="lccl-fr-create-form">
					<div class="lccl-fr-form-pair">
						<div class="lccl-fr-form-row" style="grid-column: 1 / -1;">
							<label><?php esc_html_e( 'Event Name *', 'lccl-de' ); ?></label>
							<input type="text" name="name" required placeholder="LCCL Gala 2026">
						</div>
						<div class="lccl-fr-form-row">
							<label><?php esc_html_e( 'Base Ticket Price (LKR)', 'lccl-de' ); ?></label>
							<input type="number" name="ticket_price" step="0.01" value="6500">
						</div>
						<div class="lccl-fr-form-row">
							<label><?php esc_html_e( 'Event Date *', 'lccl-de' ); ?></label>
							<input type="date" name="event_date" required>
						</div>
						<div class="lccl-fr-form-row">
							<label><?php esc_html_e( 'Event Time *', 'lccl-de' ); ?></label>
							<input type="time" name="event_time" required>
						</div>
						<div class="lccl-fr-form-row" style="grid-column: 1 / -1;">
							<label><?php esc_html_e( 'Venue *', 'lccl-de' ); ?></label>
							<input type="text" name="venue" required placeholder="Grand Ballroom">
						</div>
						<div class="lccl-fr-form-row" style="grid-column: 1 / -1;">
							<label><?php esc_html_e( 'Description', 'lccl-de' ); ?></label>
							<textarea name="description"></textarea>
						</div>
					</div>
					<button type="submit" class="lccl-fr-btn lccl-fr-btn--primary"><?php esc_html_e( 'Create Event', 'lccl-de' ); ?></button>
				</form>
			</div>
		</div>
	</div>
</div>