<?php
/**
 * LCCL Fundraiser Dashboard template.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array $fr_events   List of events (each has types[], tables[], pool_stats, blocked_seats).
 * @var array $fr_stats    Stats array (total_events, public_events, total_bookings, total_revenue).
 * @var array $fr_bookings List of all bookings.
 */

defined( 'ABSPATH' ) || exit;

// Encode all event data for JS consumption once, avoiding repeated inline JSON.
$fr_events_json   = wp_json_encode( $fr_events );
$fr_bookings_json = wp_json_encode( $fr_bookings );
?>

<div class="lccl-frx">

	<!-- ============================================================
	     STAT BAR
	============================================================ -->
	<div class="lccl-frx-statbar">
		<div class="lccl-frx-stat">
			<span class="lccl-frx-stat__icon">🗓️</span>
			<div>
				<p class="lccl-frx-stat__val"><?php echo esc_html( $fr_stats['total_events'] ); ?></p>
				<p class="lccl-frx-stat__lbl"><?php esc_html_e( 'Total Events', 'lccl-de' ); ?></p>
			</div>
		</div>
		<div class="lccl-frx-stat">
			<span class="lccl-frx-stat__icon">🌐</span>
			<div>
				<p class="lccl-frx-stat__val"><?php echo esc_html( $fr_stats['public_events'] ); ?></p>
				<p class="lccl-frx-stat__lbl"><?php esc_html_e( 'Public Events', 'lccl-de' ); ?></p>
			</div>
		</div>
		<div class="lccl-frx-stat">
			<span class="lccl-frx-stat__icon">🎟️</span>
			<div>
				<p class="lccl-frx-stat__val"><?php echo esc_html( $fr_stats['total_bookings'] ); ?></p>
				<p class="lccl-frx-stat__lbl"><?php esc_html_e( 'Confirmed Bookings', 'lccl-de' ); ?></p>
			</div>
		</div>
		<div class="lccl-frx-stat">
			<span class="lccl-frx-stat__icon">💰</span>
			<div>
				<p class="lccl-frx-stat__val">LKR <?php echo esc_html( number_format( $fr_stats['total_revenue'], 0 ) ); ?></p>
				<p class="lccl-frx-stat__lbl"><?php esc_html_e( 'Confirmed Revenue', 'lccl-de' ); ?></p>
			</div>
		</div>
	</div>

	<!-- ============================================================
	     TOP NAV (Events Overview / All Bookings / Create Event)
	============================================================ -->
	<nav class="lccl-frx-topnav" id="lccl-frx-topnav">
		<button type="button" class="lccl-frx-topnav__btn is-active" data-panel="overview">
			<?php esc_html_e( 'Events Overview', 'lccl-de' ); ?>
		</button>
		<button type="button" class="lccl-frx-topnav__btn" data-panel="allbookings">
			<?php esc_html_e( 'All Bookings', 'lccl-de' ); ?>
		</button>
		<button type="button" class="lccl-frx-topnav__btn" data-panel="create">
			<span>+</span> <?php esc_html_e( 'Create Event', 'lccl-de' ); ?>
		</button>
	</nav>

	<!-- Toast notification -->
	<div id="lccl-frx-toast" class="lccl-frx-toast" aria-live="polite"></div>

	<!-- ============================================================
	     PANEL: EVENTS OVERVIEW
	============================================================ -->
	<div class="lccl-frx-panel is-active" id="lccl-frx-panel-overview">
		<?php if ( empty( $fr_events ) ) : ?>
			<div class="lccl-frx-empty-state">
				<div class="lccl-frx-empty-state__icon">🗓️</div>
				<p class="lccl-frx-empty-state__title"><?php esc_html_e( 'No events yet', 'lccl-de' ); ?></p>
				<p class="lccl-frx-empty-state__desc"><?php esc_html_e( 'Create your first fundraiser event to get started.', 'lccl-de' ); ?></p>
				<button type="button" class="lccl-frx-btn lccl-frx-btn--primary" id="lccl-frx-goto-create"><?php esc_html_e( '+ Create Event', 'lccl-de' ); ?></button>
			</div>
		<?php else : ?>
		<div class="lccl-frx-workspace">

			<!-- Left: Event Selector -->
			<aside class="lccl-frx-sidebar">
				<div class="lccl-frx-card">
					<div class="lccl-frx-card__head">
						<span class="lccl-frx-card__title"><?php esc_html_e( 'Events', 'lccl-de' ); ?></span>
						<span class="lccl-frx-card__count"><?php echo esc_html( count( $fr_events ) ); ?></span>
					</div>
					<div class="lccl-frx-event-list" id="lccl-frx-event-list">
						<?php foreach ( $fr_events as $idx => $ev ) : ?>
							<a
								href="#"
								class="lccl-frx-event-item<?php echo 0 === $idx ? ' is-selected' : ''; ?>"
								data-event-id="<?php echo esc_attr( $ev['id'] ); ?>"
								id="lccl-frx-evitem-<?php echo esc_attr( $ev['id'] ); ?>"
							>
								<div class="lccl-frx-event-item__top">
									<span class="lccl-frx-event-item__name"><?php echo esc_html( $ev['name'] ); ?></span>
									<span class="lccl-frx-badge lccl-frx-badge--<?php echo esc_attr( $ev['status'] ); ?>">
										<?php echo esc_html( $ev['status'] ); ?>
									</span>
								</div>
								<div class="lccl-frx-event-item__meta">
									<span><?php echo esc_html( $ev['event_date'] ? date( 'M j, Y', strtotime( $ev['event_date'] ) ) : 'Date TBA' ); ?></span>
									<?php if ( $ev['public_booking'] ) : ?>
										<span class="lccl-frx-dot lccl-frx-dot--live" title="Public booking active"></span>
									<?php endif; ?>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			</aside>

			<!-- Right: Event Editor Workspace -->
			<div class="lccl-frx-editor" id="lccl-frx-editor">

				<!-- Event Header Bar -->
				<div class="lccl-frx-editor-header" id="lccl-frx-editor-header">
					<div class="lccl-frx-editor-header__info">
						<h2 class="lccl-frx-editor-header__name" id="frx-hdr-name">—</h2>
						<p class="lccl-frx-editor-header__meta" id="frx-hdr-meta"></p>
					</div>
					<div class="lccl-frx-editor-header__actions">
						<span class="lccl-frx-badge" id="frx-hdr-public-badge"></span>
						<button type="button" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--outline" id="frx-btn-toggle-public"></button>
						<a href="<?php echo esc_url( LCCL_DE_Roles::fundraiser_page_url() ); ?>" target="_blank" rel="noopener noreferrer" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--outline" style="text-decoration:none;display:inline-flex;align-items:center;gap:4px;" title="<?php esc_attr_e( 'Open public seat booking page in a new tab', 'lccl-de' ); ?>">
							<span>↗</span> <?php esc_html_e( 'Booking Page', 'lccl-de' ); ?>
						</a>
						<button type="button" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--danger" id="frx-btn-delete-event"><?php esc_html_e( 'Delete Event', 'lccl-de' ); ?></button>
					</div>
				</div>

				<!-- Sub-tab nav -->
				<nav class="lccl-frx-subnav" id="lccl-frx-subnav">
					<button type="button" class="lccl-frx-subnav__btn is-active" data-sub="details">
						<span class="lccl-frx-subnav__icon">📝</span> <?php esc_html_e( 'Event Details', 'lccl-de' ); ?>
					</button>
					<button type="button" class="lccl-frx-subnav__btn" data-sub="tables">
						<span class="lccl-frx-subnav__icon">🪑</span> <?php esc_html_e( 'Tables & Pools', 'lccl-de' ); ?>
					</button>
					<button type="button" class="lccl-frx-subnav__btn" data-sub="blocked">
						<span class="lccl-frx-subnav__icon">🚫</span> <?php esc_html_e( 'Blocked Seats & Tables', 'lccl-de' ); ?>
					</button>
					<button type="button" class="lccl-frx-subnav__btn" data-sub="evbookings">
						<span class="lccl-frx-subnav__icon">🎟️</span> <?php esc_html_e( 'Event Bookings', 'lccl-de' ); ?>
					</button>
				</nav>

				<!-- ── Sub-panel: Event Details ── -->
				<div class="lccl-frx-subpanel is-active" id="lccl-frx-sub-details">
					<div class="lccl-frx-card">
						<div class="lccl-frx-card__head">
							<span class="lccl-frx-card__title"><?php esc_html_e( 'Edit Event Details', 'lccl-de' ); ?></span>
						</div>
						<div class="lccl-frx-card__body">
							<form id="lccl-frx-edit-form">
								<input type="hidden" name="event_id" id="frx-edit-id">

								<div class="lccl-frx-form-row">
									<label for="frx-edit-name"><?php esc_html_e( 'Event Name', 'lccl-de' ); ?></label>
									<input type="text" id="frx-edit-name" name="name" required>
								</div>

								<div class="lccl-frx-form-grid">
									<div class="lccl-frx-form-row">
										<label for="frx-edit-date"><?php esc_html_e( 'Date', 'lccl-de' ); ?></label>
										<input type="date" id="frx-edit-date" name="event_date">
									</div>
									<div class="lccl-frx-form-row">
										<label for="frx-edit-time"><?php esc_html_e( 'Time', 'lccl-de' ); ?></label>
										<input type="time" id="frx-edit-time" name="event_time">
									</div>
									<div class="lccl-frx-form-row">
										<label for="frx-edit-price"><?php esc_html_e( 'Base Ticket Price (LKR)', 'lccl-de' ); ?></label>
										<input type="number" step="0.01" id="frx-edit-price" name="ticket_price">
									</div>
									<div class="lccl-frx-form-row">
										<label for="frx-edit-status"><?php esc_html_e( 'Status', 'lccl-de' ); ?></label>
										<select id="frx-edit-status" name="status">
											<option value="upcoming"><?php esc_html_e( 'Upcoming', 'lccl-de' ); ?></option>
											<option value="ongoing"><?php esc_html_e( 'Ongoing', 'lccl-de' ); ?></option>
											<option value="completed"><?php esc_html_e( 'Completed', 'lccl-de' ); ?></option>
											<option value="archived"><?php esc_html_e( 'Archived', 'lccl-de' ); ?></option>
										</select>
									</div>
								</div>

								<div class="lccl-frx-form-row">
									<label for="frx-edit-venue"><?php esc_html_e( 'Venue', 'lccl-de' ); ?></label>
									<input type="text" id="frx-edit-venue" name="venue">
								</div>
								<div class="lccl-frx-form-row">
									<label for="frx-edit-desc"><?php esc_html_e( 'Description', 'lccl-de' ); ?></label>
									<textarea id="frx-edit-desc" name="description"></textarea>
								</div>

								<div class="lccl-frx-form-actions">
									<button type="submit" class="lccl-frx-btn lccl-frx-btn--primary">
										<?php esc_html_e( 'Save Changes', 'lccl-de' ); ?>
									</button>
								</div>
							</form>
						</div>
					</div>

					<!-- Add Table Tier -->
					<div class="lccl-frx-card">
						<div class="lccl-frx-card__head">
							<span class="lccl-frx-card__title"><?php esc_html_e( 'Table Tiers', 'lccl-de' ); ?></span>
						</div>
						<div class="lccl-frx-card__body">
							<p class="lccl-frx-help"><?php esc_html_e( 'Define table tiers (e.g. VIP 10-seater @ LKR 80,000). Tiers describe the type of table; actual table inventory is generated in the Tables & Pools workspace.', 'lccl-de' ); ?></p>
							<form id="lccl-frx-add-tier-form" class="lccl-frx-inline-form">
								<div class="lccl-frx-form-row">
									<label><?php esc_html_e( 'Tier Name', 'lccl-de' ); ?></label>
									<input type="text" name="name" placeholder="VIP Table" required>
								</div>
								<div class="lccl-frx-form-row">
									<label><?php esc_html_e( 'Seats / Table', 'lccl-de' ); ?></label>
									<input type="number" name="capacity" value="10" min="1" style="width:80px;">
								</div>
								<div class="lccl-frx-form-row">
									<label><?php esc_html_e( 'Table Price (LKR)', 'lccl-de' ); ?></label>
									<input type="number" name="price" step="0.01" value="0">
								</div>
								<div class="lccl-frx-form-row lccl-frx-form-row--action">
									<label>&nbsp;</label>
									<button type="submit" class="lccl-frx-btn lccl-frx-btn--primary lccl-frx-btn--sm"><?php esc_html_e( '+ Add Tier', 'lccl-de' ); ?></button>
								</div>
							</form>

							<!-- Tier List (rendered by JS) -->
							<div id="lccl-frx-tier-list" class="lccl-frx-tier-list"></div>
						</div>
					</div>
				</div>

				<!-- ── Sub-panel: Tables & Pools ── -->
				<div class="lccl-frx-subpanel" id="lccl-frx-sub-tables">

					<!-- Pool Monitor -->
					<div class="lccl-frx-card">
						<div class="lccl-frx-card__head">
							<span class="lccl-frx-card__title"><?php esc_html_e( 'Pool Monitor', 'lccl-de' ); ?></span>
						</div>
						<div class="lccl-frx-card__body">
							<div class="lccl-frx-pool-grid" id="lccl-frx-pool-grid">
								<!-- Populated by JS -->
								<div class="lccl-frx-pool-gauge" id="frx-gauge-table"></div>
								<div class="lccl-frx-pool-gauge" id="frx-gauge-seat"></div>
							</div>
						</div>
					</div>

					<!-- Generate Tables -->
					<div class="lccl-frx-card">
						<div class="lccl-frx-card__head">
							<span class="lccl-frx-card__title"><?php esc_html_e( 'Generate Table Inventory', 'lccl-de' ); ?></span>
						</div>
						<div class="lccl-frx-card__body">
							<form id="lccl-frx-generate-form" class="lccl-frx-inline-form lccl-frx-generate-row">
								<div class="lccl-frx-form-row">
									<label><?php esc_html_e( 'Tier', 'lccl-de' ); ?></label>
									<select name="type_id" id="frx-gen-tier" style="min-width:160px;"></select>
								</div>
								<div class="lccl-frx-form-row">
									<label><?php esc_html_e( 'Prefix', 'lccl-de' ); ?></label>
									<input type="text" name="prefix" value="T-" style="width:70px;">
								</div>
								<div class="lccl-frx-form-row">
									<label><?php esc_html_e( 'Count', 'lccl-de' ); ?></label>
									<input type="number" name="count" value="10" min="1" max="50" style="width:70px;">
								</div>
								<div class="lccl-frx-form-row lccl-frx-pool-radio-row">
									<label><?php esc_html_e( 'Destination Pool', 'lccl-de' ); ?></label>
									<div class="lccl-frx-pool-radio-group">
										<label class="lccl-frx-pool-radio">
											<input type="radio" name="mode" value="seat" checked>
											<span class="lccl-frx-pool-radio__badge lccl-frx-pool-radio__badge--seat"><?php esc_html_e( 'Seat Pool', 'lccl-de' ); ?></span>
										</label>
										<label class="lccl-frx-pool-radio">
											<input type="radio" name="mode" value="table">
											<span class="lccl-frx-pool-radio__badge lccl-frx-pool-radio__badge--table"><?php esc_html_e( 'Table Pool', 'lccl-de' ); ?></span>
										</label>
									</div>
								</div>
								<div class="lccl-frx-form-row lccl-frx-form-row--action">
									<label>&nbsp;</label>
									<button type="submit" class="lccl-frx-btn lccl-frx-btn--primary lccl-frx-btn--sm"><?php esc_html_e( '⚡ Generate', 'lccl-de' ); ?></button>
								</div>
							</form>
						</div>
					</div>

					<!-- Table Inventory -->
					<div class="lccl-frx-card">
						<div class="lccl-frx-card__head">
							<span class="lccl-frx-card__title"><?php esc_html_e( 'Table Inventory', 'lccl-de' ); ?></span>
							<div class="lccl-frx-filter-pills" id="lccl-frx-inv-filter">
								<button type="button" class="lccl-frx-pill is-active" data-filter="all"><?php esc_html_e( 'All', 'lccl-de' ); ?></button>
								<button type="button" class="lccl-frx-pill" data-filter="table"><?php esc_html_e( 'Table Pool', 'lccl-de' ); ?></button>
								<button type="button" class="lccl-frx-pill" data-filter="seat"><?php esc_html_e( 'Seat Pool', 'lccl-de' ); ?></button>
								<button type="button" class="lccl-frx-pill" data-filter="blocked"><?php esc_html_e( 'Blocked', 'lccl-de' ); ?></button>
							</div>
						</div>
						<div class="lccl-frx-table-wrap" id="lccl-frx-inv-table-wrap">
							<table class="lccl-frx-table" id="lccl-frx-inv-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Table', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Tier', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Capacity', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Pool', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Status', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'lccl-de' ); ?></th>
									</tr>
								</thead>
								<tbody id="lccl-frx-inv-tbody">
									<!-- Populated by JS -->
								</tbody>
							</table>
							<p class="lccl-frx-empty" id="lccl-frx-inv-empty" style="display:none;"><?php esc_html_e( 'No tables match the selected filter.', 'lccl-de' ); ?></p>
						</div>
					</div>
				</div><!-- /sub-tables -->

				<!-- ── Sub-panel: Blocked Seats & Tables ── -->
				<div class="lccl-frx-subpanel" id="lccl-frx-sub-blocked">

					<!-- KPI Summary Row -->
					<div class="lccl-frx-blocked-summary">
						<div class="lccl-frx-blocked-kpi">
							<span class="lccl-frx-blocked-kpi__icon">🚫</span>
							<div>
								<span class="lccl-frx-blocked-kpi__val" id="frx-blk-kpi-tables">0</span>
								<span class="lccl-frx-blocked-kpi__lbl"><?php esc_html_e( 'Blocked Tables', 'lccl-de' ); ?></span>
							</div>
						</div>
						<div class="lccl-frx-blocked-kpi">
							<span class="lccl-frx-blocked-kpi__icon">👥</span>
							<div>
								<span class="lccl-frx-blocked-kpi__val" id="frx-blk-kpi-groups">0</span>
								<span class="lccl-frx-blocked-kpi__lbl"><?php esc_html_e( 'Seat Block Groups', 'lccl-de' ); ?></span>
							</div>
						</div>
						<div class="lccl-frx-blocked-kpi">
							<span class="lccl-frx-blocked-kpi__icon">💺</span>
							<div>
								<span class="lccl-frx-blocked-kpi__val" id="frx-blk-kpi-seats">0</span>
								<span class="lccl-frx-blocked-kpi__lbl"><?php esc_html_e( 'Total Blocked Seats', 'lccl-de' ); ?></span>
							</div>
						</div>
						<div class="lccl-frx-blocked-kpi">
							<span class="lccl-frx-blocked-kpi__icon">🟢</span>
							<div>
								<span class="lccl-frx-blocked-kpi__val" id="frx-blk-kpi-avail">0</span>
								<span class="lccl-frx-blocked-kpi__lbl"><?php esc_html_e( 'Available Seat Pool', 'lccl-de' ); ?></span>
							</div>
						</div>
					</div>

					<!-- Blocked Tables Section (Grouped for Group Blocks) -->
					<div class="lccl-frx-card">
						<div class="lccl-frx-card__head">
							<div>
								<span class="lccl-frx-card__title"><?php esc_html_e( 'Blocked Tables & Connected Sets', 'lccl-de' ); ?></span>
								<p class="lccl-frx-card__subtitle"><?php esc_html_e( 'Browse tables held out of public booking. Tables blocked together are grouped with their shared notes. Inspect details, click chips to locate in floor plan, or re-send them back to their designated pool.', 'lccl-de' ); ?></p>
							</div>
							<span class="lccl-frx-badge lccl-frx-badge--failed" id="frx-blocked-tables-badge">0</span>
						</div>
						<div class="lccl-frx-card__body">
							<div class="lccl-frx-seat-groups-list lccl-frx-table-groups-list" id="lccl-frx-table-groups-list">
								<!-- Populated by JS renderBlockedTableGroupsList() -->
							</div>
							<p class="lccl-frx-empty" id="lccl-frx-no-blocked-tables" style="display:none;">
								<?php esc_html_e( 'No tables are currently blocked for this event.', 'lccl-de' ); ?>
							</p>
							<!-- Pagination for Blocked Table Groups -->
							<div class="lccl-frx-pagination" id="lccl-frx-blocked-tables-pagination" style="display:none;">
								<div class="lccl-frx-pagination__info" id="frx-tbl-pagination-info"></div>
								<div class="lccl-frx-pagination__controls">
									<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--xs" id="frx-tbl-page-prev" disabled>← Previous</button>
									<div class="lccl-frx-pagination__pages" id="frx-tbl-page-numbers"></div>
									<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--xs" id="frx-tbl-page-next">Next →</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Blocked Seat Groups Section -->
					<div class="lccl-frx-card">
						<div class="lccl-frx-card__head">
							<div>
								<span class="lccl-frx-card__title"><?php esc_html_e( 'Blocked Seat Groups & Connected Sets', 'lccl-de' ); ?></span>
								<p class="lccl-frx-card__subtitle"><?php esc_html_e( 'Seats blocked together with attached notes. View details, inspect common notes, or re-send the whole group back to the seat pool.', 'lccl-de' ); ?></p>
							</div>
							<span class="lccl-frx-badge lccl-frx-badge--failed" id="frx-blocked-groups-badge">0</span>
						</div>
						<div class="lccl-frx-card__body">
							<div class="lccl-frx-seat-groups-list" id="lccl-frx-seat-groups-list">
								<!-- Populated by JS -->
							</div>
							<p class="lccl-frx-empty" id="lccl-frx-no-blocked-groups" style="display:none;">
								<?php esc_html_e( 'No individual seats or seat groups are currently blocked for this event.', 'lccl-de' ); ?>
							</p>
							<!-- Pagination for Blocked Seat Groups -->
							<div class="lccl-frx-pagination" id="lccl-frx-seat-groups-pagination" style="display:none;">
								<div class="lccl-frx-pagination__info" id="frx-seat-pagination-info"></div>
								<div class="lccl-frx-pagination__controls">
									<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--xs" id="frx-seat-page-prev" disabled>← Previous</button>
									<div class="lccl-frx-pagination__pages" id="frx-seat-page-numbers"></div>
									<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--xs" id="frx-seat-page-next">Next →</button>
								</div>
							</div>
						</div>
					</div>

					<!-- ── 1. Table Map Grid & Table Block Tool (Table Pool) ── -->
					<div class="lccl-frx-card">
						<div class="lccl-frx-card__head">
							<div>
								<span class="lccl-frx-card__title"><?php esc_html_e( 'Table Map Grid & Table Block Tool (Table Pool)', 'lccl-de' ); ?></span>
								<p class="lccl-frx-card__subtitle"><?php esc_html_e( 'Interactive floor plan for tables sold as complete units. Click available tables to select and block as a group with a common note, or click a blocked table to inspect and re-send to pool.', 'lccl-de' ); ?></p>
							</div>
							<span class="lccl-frx-badge lccl-frx-badge--private" id="frx-tbl-pool-badge">0 tables</span>
						</div>
						<div class="lccl-frx-card__body">

							<!-- Multi-Table Floating / Action Bar -->
							<div class="lccl-frx-selection-bar lccl-frx-selection-bar--table" id="lccl-frx-tbl-selection-bar" style="display:none;">
								<div class="lccl-frx-selection-bar__info">
									<span class="lccl-frx-selection-bar__badge" id="frx-tbl-sel-count">0 tables selected</span>
									<span class="lccl-frx-selection-bar__preview" id="frx-tbl-sel-preview"></span>
								</div>
								<div class="lccl-frx-selection-bar__form">
									<input type="text" id="frx-tbl-group-note-input" class="lccl-frx-selection-bar__input" placeholder="<?php esc_attr_e( 'Attach note for selected tables (e.g. Reserved for Chief Guest, VIP Sponsor Hold)...', 'lccl-de' ); ?>">
									<button type="button" id="frx-btn-block-tables-group" class="lccl-frx-btn lccl-frx-btn--danger lccl-frx-btn--sm">
										<span>🔒</span> <?php esc_html_e( 'Block Selected Tables', 'lccl-de' ); ?>
									</button>
									<button type="button" id="frx-btn-cancel-tbl-selection" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--sm">
										<?php esc_html_e( '✕ Deselect', 'lccl-de' ); ?>
									</button>
								</div>
							</div>

							<!-- Table Pool Legend -->
							<div class="lccl-frx-seat-legend">
								<span class="lccl-frx-seat-legend__item lccl-frx-seat-legend__item--avail">✓ <?php esc_html_e( 'Available Whole Table (click to select)', 'lccl-de' ); ?></span>
								<span class="lccl-frx-seat-legend__item lccl-frx-seat-legend__item--selected">🔘 <?php esc_html_e( 'Selected (pending block)', 'lccl-de' ); ?></span>
								<span class="lccl-frx-seat-legend__item lccl-frx-seat-legend__item--blocked">✖ <?php esc_html_e( 'Blocked (click to inspect / re-send to pool)', 'lccl-de' ); ?></span>
								<span class="lccl-frx-seat-legend__item lccl-frx-seat-legend__item--booked">★ <?php esc_html_e( 'Full Table Booked (paid)', 'lccl-de' ); ?></span>
							</div>

							<!-- Top-down circular table grid for Table Pool -->
							<div class="lccl-map-grid" id="lccl-frx-table-pool-grid">
								<!-- Populated by JS renderTablePoolGrid() -->
							</div>

							<p class="lccl-frx-empty" id="lccl-frx-table-pool-empty" style="display:none;">
								<?php esc_html_e( 'No tables currently assigned to the Table Pool for this event. Switch a table from the Seat Pool in "Tables & Pools".', 'lccl-de' ); ?>
							</p>
						</div>
					</div>

					<!-- ── 2. Seat Map Grid & Multi-Seat Block Tool (Seat Pool) ── -->
					<div class="lccl-frx-card">
						<div class="lccl-frx-card__head">
							<div>
								<span class="lccl-frx-card__title"><?php esc_html_e( 'Seat Map Grid & Multi-Seat Block Tool (Seat Pool)', 'lccl-de' ); ?></span>
								<p class="lccl-frx-card__subtitle"><?php esc_html_e( 'Interactive floor plan showing table separation for the Seat Pool. Individual seats are numbered sequentially across tables. Click multiple seats (or shift-click for a range) to group them, attach a common note, and block them together.', 'lccl-de' ); ?></p>
							</div>
							<span class="lccl-frx-badge lccl-frx-badge--upcoming" id="frx-seat-pool-badge">0 seats</span>
						</div>
						<div class="lccl-frx-card__body">

							<!-- Multi-Select Floating / Action Bar -->
							<div class="lccl-frx-selection-bar" id="lccl-frx-selection-bar" style="display:none;">
								<div class="lccl-frx-selection-bar__info">
									<span class="lccl-frx-selection-bar__badge" id="frx-sel-count">0 seats selected</span>
									<span class="lccl-frx-selection-bar__preview" id="frx-sel-seats-preview"></span>
								</div>
								<div class="lccl-frx-selection-bar__form">
									<input type="text" id="frx-group-note-input" class="lccl-frx-selection-bar__input" placeholder="<?php esc_attr_e( 'Attach note for this group (e.g. VIP Sponsor Guests, Chief Guest Hold)...', 'lccl-de' ); ?>">
									<button type="button" id="frx-btn-block-group" class="lccl-frx-btn lccl-frx-btn--danger lccl-frx-btn--sm">
										<span>🔒</span> <?php esc_html_e( 'Block as Group', 'lccl-de' ); ?>
									</button>
									<button type="button" id="frx-btn-cancel-selection" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--sm">
										<?php esc_html_e( '✕ Deselect', 'lccl-de' ); ?>
									</button>
								</div>
							</div>

							<!-- Controls & Jump -->
							<div class="lccl-frx-seat-controls">
								<label class="lccl-frx-seat-controls__label" for="frx-seat-jump"><?php esc_html_e( 'Jump to seat:', 'lccl-de' ); ?></label>
								<input type="number" id="frx-seat-jump" class="lccl-frx-seat-controls__input" min="1" placeholder="#">
								<button type="button" id="frx-seat-jump-btn" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--outline"><?php esc_html_e( 'Go', 'lccl-de' ); ?></button>
								<button type="button" id="frx-seat-clear-blocks" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--danger-outline"><?php esc_html_e( '✕ Clear All Seat Blocks', 'lccl-de' ); ?></button>
							</div>

							<!-- Seat Pool Legend -->
							<div class="lccl-frx-seat-legend">
								<span class="lccl-frx-seat-legend__item lccl-frx-seat-legend__item--avail">● <?php esc_html_e( 'Available (click to select)', 'lccl-de' ); ?></span>
								<span class="lccl-frx-seat-legend__item lccl-frx-seat-legend__item--selected">🔘 <?php esc_html_e( 'Selected (pending group block)', 'lccl-de' ); ?></span>
								<span class="lccl-frx-seat-legend__item lccl-frx-seat-legend__item--blocked">✖ <?php esc_html_e( 'Blocked (click to inspect / re-send)', 'lccl-de' ); ?></span>
								<span class="lccl-frx-seat-legend__item lccl-frx-seat-legend__item--booked">■ <?php esc_html_e( 'Booked (locked)', 'lccl-de' ); ?></span>
							</div>

							<!-- Top-down circular table grid for Seat Pool -->
							<div class="lccl-map-grid" id="lccl-frx-seat-grid">
								<!-- Populated by JS renderSeatGrid() with separated table cards -->
							</div>

							<p class="lccl-frx-empty" id="lccl-frx-seat-empty" style="display:none;">
								<?php esc_html_e( 'No tables currently assigned to the Seat Pool for this event. Generate tables and assign them to the Seat Pool in "Tables & Pools".', 'lccl-de' ); ?>
							</p>
						</div>
					</div>

				</div><!-- /sub-blocked -->

				<!-- ── Sub-panel: Event Bookings ── -->
				<div class="lccl-frx-subpanel" id="lccl-frx-sub-evbookings">
					<div class="lccl-frx-card">
						<div class="lccl-frx-card__head">
							<span class="lccl-frx-card__title"><?php esc_html_e( 'Event Bookings', 'lccl-de' ); ?></span>
							<span class="lccl-frx-badge" id="frx-evbk-count"></span>
						</div>
						<div class="lccl-frx-table-wrap">
							<table class="lccl-frx-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Reference', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Attendee', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Type', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Seats / Tables', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Notes', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Amount', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Status', 'lccl-de' ); ?></th>
										<th><?php esc_html_e( 'Date', 'lccl-de' ); ?></th>
									</tr>
								</thead>
								<tbody id="lccl-frx-evbk-tbody">
									<!-- Populated by JS -->
								</tbody>
							</table>
						</div>
					</div>
				</div><!-- /sub-evbookings -->

			</div><!-- /lccl-frx-editor -->
		</div><!-- /lccl-frx-workspace -->
		<?php endif; ?>
	</div><!-- /panel-overview -->

	<!-- ============================================================
	     PANEL: ALL BOOKINGS
	============================================================ -->
	<div class="lccl-frx-panel" id="lccl-frx-panel-allbookings">
		<div class="lccl-frx-card">
			<div class="lccl-frx-card__head">
				<span class="lccl-frx-card__title"><?php esc_html_e( 'All Fundraiser Bookings', 'lccl-de' ); ?></span>
			</div>
			<div class="lccl-frx-table-wrap">
				<table class="lccl-frx-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Reference', 'lccl-de' ); ?></th>
							<th><?php esc_html_e( 'Event', 'lccl-de' ); ?></th>
							<th><?php esc_html_e( 'Attendee', 'lccl-de' ); ?></th>
							<th><?php esc_html_e( 'Type', 'lccl-de' ); ?></th>
							<th><?php esc_html_e( 'Qty', 'lccl-de' ); ?></th>
							<th><?php esc_html_e( 'Notes', 'lccl-de' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'lccl-de' ); ?></th>
							<th><?php esc_html_e( 'Status', 'lccl-de' ); ?></th>
							<th><?php esc_html_e( 'Date', 'lccl-de' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $fr_bookings ) ) : ?>
							<tr><td colspan="9" class="lccl-frx-empty"><?php esc_html_e( 'No bookings yet.', 'lccl-de' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $fr_bookings as $bk ) : ?>
								<tr>
									<td><code><?php echo esc_html( $bk['order_ref'] ); ?></code></td>
									<td><?php echo esc_html( $bk['event_name'] ?? '—' ); ?></td>
									<td>
										<strong><?php echo esc_html( $bk['attendee_name'] ); ?></strong><br>
										<small style="color:#888;"><?php echo esc_html( $bk['attendee_email'] ); ?></small>
									</td>
									<td><?php echo esc_html( ucfirst( $bk['booking_type'] ) ); ?></td>
									<td><?php echo esc_html( $bk['qty'] ); ?></td>
									<td>
										<?php if ( ! empty( $bk['notes'] ) ) : ?>
											<span class="lccl-frx-note-chip" title="<?php echo esc_attr( $bk['notes'] ); ?>">
												📝 <?php echo esc_html( wp_trim_words( $bk['notes'], 6, '…' ) ); ?>
											</span>
										<?php else : ?>
											<span style="color:#ccc;">—</span>
										<?php endif; ?>
									</td>
									<td>LKR <?php echo esc_html( number_format( $bk['amount_lkr'], 2 ) ); ?></td>
									<td>
										<span class="lccl-frx-badge lccl-frx-badge--<?php echo 'paid' === $bk['status'] ? 'paid' : 'pending'; ?>">
											<?php echo esc_html( $bk['status'] ); ?>
										</span>
									</td>
									<td><small><?php echo esc_html( date( 'M j, Y H:i', strtotime( $bk['created_at'] ) ) ); ?></small></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div><!-- /panel-allbookings -->

	<!-- ============================================================
	     UNIVERSAL MODAL (Block Note / Inspection & Re-send)
	============================================================ -->
	<div class="lccl-frx-modal-backdrop" id="lccl-frx-modal-backdrop" style="display:none;">
		<div class="lccl-frx-modal" id="lccl-frx-modal">
			<div class="lccl-frx-modal__head">
				<h3 class="lccl-frx-modal__title" id="frx-modal-title"><?php esc_html_e( 'Details', 'lccl-de' ); ?></h3>
				<button type="button" class="lccl-frx-modal__close" id="frx-modal-close">&times;</button>
			</div>
			<div class="lccl-frx-modal__body" id="frx-modal-body">
				<!-- Injected by JS -->
			</div>
			<div class="lccl-frx-modal__foot" id="frx-modal-foot">
				<!-- Injected by JS -->
			</div>
		</div>
	</div>

	<!-- ============================================================
	     PANEL: CREATE EVENT
	============================================================ -->
	<div class="lccl-frx-panel" id="lccl-frx-panel-create">
		<div class="lccl-frx-card" style="max-width:720px;">
			<div class="lccl-frx-card__head">
				<span class="lccl-frx-card__title"><?php esc_html_e( 'Create New Fundraiser Event', 'lccl-de' ); ?></span>
			</div>
			<div class="lccl-frx-card__body">
				<p class="lccl-frx-help"><?php esc_html_e( 'After creating the event, configure table tiers and inventory from the Events Overview → Tables & Pools workspace.', 'lccl-de' ); ?></p>
				<form id="lccl-frx-create-form">
					<div class="lccl-frx-form-row">
						<label for="frx-create-name"><?php esc_html_e( 'Event Name *', 'lccl-de' ); ?></label>
						<input type="text" id="frx-create-name" name="name" required placeholder="<?php esc_attr_e( 'e.g. LCCL Gala Dinner 2026', 'lccl-de' ); ?>">
					</div>
					<div class="lccl-frx-form-grid">
						<div class="lccl-frx-form-row">
							<label for="frx-create-date"><?php esc_html_e( 'Event Date *', 'lccl-de' ); ?></label>
							<input type="date" id="frx-create-date" name="event_date" required>
						</div>
						<div class="lccl-frx-form-row">
							<label for="frx-create-time"><?php esc_html_e( 'Event Time *', 'lccl-de' ); ?></label>
							<input type="time" id="frx-create-time" name="event_time" required>
						</div>
						<div class="lccl-frx-form-row">
							<label for="frx-create-price"><?php esc_html_e( 'Base Ticket Price (LKR)', 'lccl-de' ); ?></label>
							<input type="number" id="frx-create-price" name="ticket_price" step="0.01" value="6500">
						</div>
					</div>
					<div class="lccl-frx-form-row">
						<label for="frx-create-venue"><?php esc_html_e( 'Venue *', 'lccl-de' ); ?></label>
						<input type="text" id="frx-create-venue" name="venue" required placeholder="<?php esc_attr_e( 'e.g. Grand Ballroom, Colombo', 'lccl-de' ); ?>">
					</div>
					<div class="lccl-frx-form-row">
						<label for="frx-create-desc"><?php esc_html_e( 'Description', 'lccl-de' ); ?></label>
						<textarea id="frx-create-desc" name="description"></textarea>
					</div>
					<div class="lccl-frx-form-actions">
						<button type="submit" class="lccl-frx-btn lccl-frx-btn--primary"><?php esc_html_e( '+ Create Event', 'lccl-de' ); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div><!-- /panel-create -->

	<!-- Universal Admin Modal for Details & Actions -->
	<div class="lccl-frx-modal-backdrop" id="lccl-frx-modal-backdrop" style="display:none;" aria-hidden="true">
		<div class="lccl-frx-modal" id="lccl-frx-modal" role="dialog" aria-modal="true" aria-labelledby="lccl-frx-modal-title">
			<div class="lccl-frx-modal__head">
				<h3 class="lccl-frx-modal__title" id="lccl-frx-modal-title">Modal Title</h3>
				<button type="button" class="lccl-frx-modal__close" id="lccl-frx-modal-close" aria-label="<?php esc_attr_e( 'Close', 'lccl-de' ); ?>">&times;</button>
			</div>
			<div class="lccl-frx-modal__body" id="lccl-frx-modal-body"></div>
			<div class="lccl-frx-modal__foot" id="lccl-frx-modal-foot"></div>
		</div>
	</div>

</div><!-- /lccl-frx -->

<!-- Inline data for JS — encoded once on the PHP side -->
<script>
window.lcclFrxEvents   = <?php echo $fr_events_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
window.lcclFrxBookings = <?php echo $fr_bookings_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
</script>