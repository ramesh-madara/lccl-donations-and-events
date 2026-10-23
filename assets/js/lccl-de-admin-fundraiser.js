/**
 * LCCL Fundraiser Admin — Dashboard JS
 *
 * Zero-full-reload architecture. All event data is seeded in
 * window.lcclFrxEvents (PHP JSON). AJAX actions mutate the in-memory
 * state and re-render the affected sub-panel only.
 *
 * Global: lcclFrxAjax  { ajaxurl, nonce }  (wp_localize_script)
 * Global: lcclFrxEvents  (admin-fundraiser.php inline <script>)
 * Global: lcclFrxBookings
 */
( function () {
	'use strict';

	/* ────────────────────────────────────────────────────────────
	   State
	──────────────────────────────────────────────────────────── */
	let events   = window.lcclFrxEvents   || [];
	let bookings = window.lcclFrxBookings || [];
	let currentEventId = events.length ? parseInt( events[0].id, 10 ) : null;

	let selectedSeats    = new Set();
	let lastSelectedSeat = null;
	let selectedTables   = new Set();
	let lastSelectedTable = null;

	let blockedTablesPage = 1;
	let blockedSeatsPage  = 1;
	const BLOCKED_TABLES_PER_PAGE = 5;
	const BLOCKED_SEATS_PER_PAGE  = 5;

	function getEvent( id ) {
		return events.find( e => parseInt( e.id, 10 ) === parseInt( id, 10 ) ) || null;
	}

	/* ────────────────────────────────────────────────────────────
	   AJAX helper
	──────────────────────────────────────────────────────────── */
	function ajax( action, data, method ) {
		const body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', lcclFrxAjax.nonce );
		Object.entries( data ).forEach( ( [ k, v ] ) => {
			if ( Array.isArray( v ) ) {
				v.forEach( item => body.append( `${k}[]`, item ) );
			} else {
				body.set( k, v );
			}
		} );
		return fetch( lcclFrxAjax.ajaxurl, {
			method : method || 'POST',
			body,
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		} ).then( r => r.json() );
	}

	/* ────────────────────────────────────────────────────────────
	   Toast
	──────────────────────────────────────────────────────────── */
	function toast( msg, type = 'success' ) {
		const text = ( typeof msg === 'object' && msg !== null ) ? ( msg.message || JSON.stringify( msg ) ) : String( msg );
		const wrap = document.getElementById( 'lccl-frx-toast' );
		if ( ! wrap ) return;
		const el   = document.createElement( 'div' );
		el.className = `lccl-frx-toast__msg is-${type}`;
		el.textContent = text;
		wrap.appendChild( el );
		setTimeout( () => el.classList.add( 'is-fading' ), 2800 );
		setTimeout( () => el.remove(), 3200 );
	}

	/* ────────────────────────────────────────────────────────────
	   Universal Modal Helpers
	──────────────────────────────────────────────────────────── */
	const modalBackdrop = document.getElementById( 'lccl-frx-modal-backdrop' );
	const modalTitle    = document.getElementById( 'lccl-frx-modal-title' );
	const modalBody     = document.getElementById( 'lccl-frx-modal-body' );
	const modalFoot     = document.getElementById( 'lccl-frx-modal-foot' );
	const modalCloseBtn = document.getElementById( 'lccl-frx-modal-close' );

	function openModal( title, bodyHtml, footHtml = '' ) {
		if ( ! modalBackdrop ) return;
		if ( modalTitle ) modalTitle.innerHTML = title;
		if ( modalBody )  modalBody.innerHTML  = bodyHtml;
		if ( modalFoot ) {
			modalFoot.innerHTML = footHtml;
			modalFoot.style.display = footHtml ? 'flex' : 'none';
		}
		modalBackdrop.style.display = 'flex';
		modalBackdrop.setAttribute( 'aria-hidden', 'false' );
	}

	function closeModal() {
		if ( ! modalBackdrop ) return;
		modalBackdrop.style.display = 'none';
		modalBackdrop.setAttribute( 'aria-hidden', 'true' );
		if ( modalBody ) modalBody.innerHTML = '';
		if ( modalFoot ) modalFoot.innerHTML = '';
	}

	if ( modalCloseBtn ) modalCloseBtn.addEventListener( 'click', closeModal );
	if ( modalBackdrop ) {
		modalBackdrop.addEventListener( 'click', e => {
			if ( e.target === modalBackdrop ) closeModal();
		} );
	}
	document.addEventListener( 'keydown', e => {
		if ( e.key === 'Escape' && modalBackdrop && modalBackdrop.style.display !== 'none' ) {
			closeModal();
		}
	} );

	/* ────────────────────────────────────────────────────────────
	   Top-level panel switching
	──────────────────────────────────────────────────────────── */
	const topNav   = document.getElementById( 'lccl-frx-topnav' );
	const topBtns  = topNav ? topNav.querySelectorAll( '[data-panel]' ) : [];
	const topPanels = document.querySelectorAll( '.lccl-frx-panel' );

	function switchTopPanel( name ) {
		topBtns.forEach( b => b.classList.toggle( 'is-active', b.dataset.panel === name ) );
		topPanels.forEach( p => p.classList.toggle( 'is-active', p.id === `lccl-frx-panel-${name}` ) );
	}

	topBtns.forEach( btn => btn.addEventListener( 'click', () => switchTopPanel( btn.dataset.panel ) ) );

	// "Create Event" shortcut from empty state
	const gotoCreate = document.getElementById( 'lccl-frx-goto-create' );
	if ( gotoCreate ) gotoCreate.addEventListener( 'click', () => switchTopPanel( 'create' ) );

	/* ────────────────────────────────────────────────────────────
	   Sub-panel switching
	──────────────────────────────────────────────────────────── */
	const subNav  = document.getElementById( 'lccl-frx-subnav' );
	const subBtns = subNav ? subNav.querySelectorAll( '[data-sub]' ) : [];

	function switchSubPanel( name ) {
		subBtns.forEach( b => b.classList.toggle( 'is-active', b.dataset.sub === name ) );
		document.querySelectorAll( '.lccl-frx-subpanel' ).forEach( p => {
			p.classList.toggle( 'is-active', p.id === `lccl-frx-sub-${name}` );
		} );
		if ( name === 'tables'                  ) renderTablesPanel();
		if ( name === 'blocked' || name === 'seatgrid' ) renderBlockedPanel();
		if ( name === 'evbookings'              ) renderEventBookings();
	}

	subBtns.forEach( btn => btn.addEventListener( 'click', () => switchSubPanel( btn.dataset.sub ) ) );

	/* ────────────────────────────────────────────────────────────
	   Event selector (sidebar)
	──────────────────────────────────────────────────────────── */
	const eventList = document.getElementById( 'lccl-frx-event-list' );
	if ( eventList ) {
		eventList.addEventListener( 'click', e => {
			const item = e.target.closest( '[data-event-id]' );
			if ( ! item ) return;
			e.preventDefault();
			const id = parseInt( item.dataset.eventId, 10 );
			if ( id === currentEventId ) return;
			currentEventId = id;
			eventList.querySelectorAll( '.lccl-frx-event-item' )
				.forEach( el => el.classList.toggle( 'is-selected', el === item ) );
			renderEditorForEvent( id );
		} );
	}

	/* ────────────────────────────────────────────────────────────
	   Render editor (header + sub-panels) for a chosen event
	──────────────────────────────────────────────────────────── */
	function renderEditorForEvent( id ) {
		const ev = getEvent( id );
		if ( ! ev ) return;

		// Header
		document.getElementById( 'frx-hdr-name' ).textContent = ev.name;
		document.getElementById( 'frx-hdr-meta' ).textContent =
			( ev.event_date ? formatDate( ev.event_date ) : 'Date TBA' ) +
			( ev.venue ? ' · ' + ev.venue : '' );

		const publicBadge = document.getElementById( 'frx-hdr-public-badge' );
		const publicBtn   = document.getElementById( 'frx-btn-toggle-public' );
		if ( +ev.public_booking ) {
			publicBadge.textContent = 'LIVE';
			publicBadge.className   = 'lccl-frx-badge lccl-frx-badge--public';
			publicBtn.textContent   = '✕ Take Offline';
		} else {
			publicBadge.textContent = 'PRIVATE';
			publicBadge.className   = 'lccl-frx-badge lccl-frx-badge--private';
			publicBtn.textContent   = '🌐 Make Public';
		}

		// Populate edit form
		document.getElementById( 'frx-edit-id'    ).value = ev.id;
		document.getElementById( 'frx-edit-name'  ).value = ev.name;
		document.getElementById( 'frx-edit-date'  ).value = ev.event_date  || '';
		document.getElementById( 'frx-edit-time'  ).value = ev.event_time  || '';
		document.getElementById( 'frx-edit-price' ).value = ev.ticket_price || '';
		document.getElementById( 'frx-edit-venue' ).value = ev.venue        || '';
		document.getElementById( 'frx-edit-desc'  ).value = ev.description  || '';
		const statusEl = document.getElementById( 'frx-edit-status' );
		if ( statusEl ) statusEl.value = ev.status || 'upcoming';

		// Clear seat and table selection and reset pagination
		selectedSeats.clear();
		lastSelectedSeat = null;
		updateSelectionBar();
		selectedTables.clear();
		lastSelectedTable = null;
		updateTableSelectionBar();
		blockedTablesPage = 1;
		blockedSeatsPage  = 1;

		// Render tier list
		renderTierList( ev );

		// Reset to details sub-panel
		switchSubPanel( 'details' );
	}

	/* ────────────────────────────────────────────────────────────
	   Tier List
	──────────────────────────────────────────────────────────── */
	function renderTierList( ev ) {
		const wrap = document.getElementById( 'lccl-frx-tier-list' );
		if ( ! wrap ) return;
		const tiers = ev.types || [];
		if ( ! tiers.length ) {
			wrap.innerHTML = '<p class="lccl-frx-empty">No tiers yet. Add one above.</p>';
			return;
		}
		wrap.innerHTML = tiers.map( t => `
			<div class="lccl-frx-tier-item">
				<div class="lccl-frx-tier-item__info">
					<span class="lccl-frx-tier-item__name">${esc(t.name)}</span>
					<span class="lccl-frx-tier-item__meta">${esc(t.capacity)} seats/table</span>
					<span class="lccl-frx-tier-item__meta">LKR ${fmt(t.price)}</span>
				</div>
				<button type="button" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--danger-outline"
					data-action="delete-tier" data-tier-id="${esc(t.id)}">✕ Remove</button>
			</div>
		` ).join('');
	}

	/* ────────────────────────────────────────────────────────────
	   Tables & Pools sub-panel
	──────────────────────────────────────────────────────────── */
	let invFilter = 'all'; // 'all' | 'table' | 'seat' | 'blocked'

	function renderTablesPanel() {
		const ev = getEvent( currentEventId );
		if ( ! ev ) return;

		renderPoolGauges( ev );
		renderGenTierSelect( ev );
		renderInventoryTable( ev );
	}

	function renderPoolGauges( ev ) {
		const stats = ev.pool_stats || {};
		// Table pool
		const tFree   = stats.table_pool_free  || 0;
		const tBooked = stats.table_pool_booked || 0;
		const tBlock  = stats.table_pool_blocked || 0;
		const tTotal  = tFree + tBooked + tBlock;
		const tPct    = tTotal ? Math.round( ( tBooked / tTotal ) * 100 ) : 0;

		document.getElementById( 'frx-gauge-table' ).innerHTML = `
			<div class="lccl-frx-gauge-header">
				<span class="lccl-frx-gauge-title">🟣 Table Pool</span>
				<span class="lccl-frx-badge lccl-frx-badge--private">${tTotal} tables</span>
			</div>
			<div class="lccl-frx-gauge-nums">${tFree}<small style="font-size:14px;font-weight:400;color:#6b7280"> free</small></div>
			<div class="lccl-frx-gauge-track"><div class="lccl-frx-gauge-fill" style="width:${tPct}%"></div></div>
			<div class="lccl-frx-gauge-meta">
				<span>Booked: <strong>${tBooked}</strong></span>
				<span>Blocked: <strong>${tBlock}</strong></span>
				<span>${tPct}% booked</span>
			</div>
		`;

		// Seat pool
		const sFree   = stats.seat_pool_free    || 0;
		const sBooked = stats.seat_pool_booked   || 0;
		const sBlock  = stats.seat_pool_blocked  || 0;
		const sTotal  = sFree + sBooked + sBlock;
		const sPct    = sTotal ? Math.round( ( sBooked / sTotal ) * 100 ) : 0;

		document.getElementById( 'frx-gauge-seat' ).innerHTML = `
			<div class="lccl-frx-gauge-header">
				<span class="lccl-frx-gauge-title">🔵 Seat Pool</span>
				<span class="lccl-frx-badge lccl-frx-badge--upcoming">${sTotal} seats</span>
			</div>
			<div class="lccl-frx-gauge-nums">${sFree}<small style="font-size:14px;font-weight:400;color:#6b7280"> free</small></div>
			<div class="lccl-frx-gauge-track"><div class="lccl-frx-gauge-fill" style="width:${sPct}%"></div></div>
			<div class="lccl-frx-gauge-meta">
				<span>Booked: <strong>${sBooked}</strong></span>
				<span>Blocked: <strong>${sBlock}</strong></span>
				<span>${sPct}% booked</span>
			</div>
		`;
	}

	function renderGenTierSelect( ev ) {
		const sel = document.getElementById( 'frx-gen-tier' );
		if ( ! sel ) return;
		const tiers = ev.types || [];
		sel.innerHTML = tiers.length
			? tiers.map( t => `<option value="${esc(t.id)}">${esc(t.name)} (${esc(t.capacity)} seats)</option>` ).join('')
			: '<option value="">No tiers — add one first</option>';
	}

	function renderInventoryTable( ev ) {
		const tbody = document.getElementById( 'lccl-frx-inv-tbody' );
		const empty = document.getElementById( 'lccl-frx-inv-empty' );
		if ( ! tbody ) return;

		const tables = ( ev.tables || [] ).filter( t => {
			if ( invFilter === 'all' )     return true;
			if ( invFilter === 'blocked' ) return +t.is_blocked;
			return t.booking_mode === invFilter && ! +t.is_blocked;
		} );

		if ( ! tables.length ) {
			tbody.innerHTML = '';
			empty.style.display = '';
			return;
		}
		empty.style.display = 'none';

		tbody.innerHTML = tables.map( t => {
			const isBooked  = +t.is_booked;
			const isBlocked = +t.is_blocked;
			const mode      = t.booking_mode || 'seat'; // 'table' | 'seat'

			const poolBadge = isBlocked
				? `<span class="lccl-frx-pool-badge lccl-frx-pool-badge--blocked">✕ Blocked</span>`
				: mode === 'table'
					? `<span class="lccl-frx-pool-badge lccl-frx-pool-badge--table">🟣 Table Pool</span>`
					: `<span class="lccl-frx-pool-badge lccl-frx-pool-badge--seat">🔵 Seat Pool</span>`;

			const statusBadge = isBooked
				? `<span class="lccl-frx-badge lccl-frx-badge--paid">Booked</span>`
				: `<span class="lccl-frx-badge lccl-frx-badge--upcoming">Free</span>`;

			const moveToOther = ! isBooked && ! isBlocked && ( mode === 'table'
				? `<button type="button" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--pool-seat"
					data-action="move-to-seat" data-table-id="${t.id}">→ Seat</button>`
				: `<button type="button" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--pool-table"
					data-action="move-to-table" data-table-id="${t.id}">→ Table</button>` );

			const blockBtn = ! isBooked
				? isBlocked
					? `<button type="button" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--outline"
						data-action="unblock-table" data-table-id="${t.id}">✓ Unblock</button>`
					: `<button type="button" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--danger-outline"
						data-action="block-table" data-table-id="${t.id}">✕ Block</button>`
				: '';

			return `<tr>
				<td><strong>${esc(t.table_label)}</strong></td>
				<td>${esc(t.type_name)}</td>
				<td>${esc(t.capacity)}</td>
				<td>${poolBadge}</td>
				<td>${statusBadge}</td>
				<td>
					<div class="lccl-frx-table-actions">
						${moveToOther || ''}
						${blockBtn}
					</div>
				</td>
			</tr>`;
		} ).join('');
	}

	// Filter pill clicks
	const filterRow = document.getElementById( 'lccl-frx-inv-filter' );
	if ( filterRow ) {
		filterRow.addEventListener( 'click', e => {
			const pill = e.target.closest( '.lccl-frx-pill' );
			if ( ! pill ) return;
			invFilter = pill.dataset.filter;
			filterRow.querySelectorAll( '.lccl-frx-pill' ).forEach( p => p.classList.toggle( 'is-active', p === pill ) );
			renderInventoryTable( getEvent( currentEventId ) );
		} );
	}

	/* ────────────────────────────────────────────────────────────
	   Blocked Seats & Tables Workspace
	──────────────────────────────────────────────────────────── */
	function renderBlockedPanel() {
		const ev = getEvent( currentEventId );
		if ( ! ev ) return;

		renderBlockedKPIs( ev );
		renderBlockedTableGroupsList( ev );
		renderBlockedSeatGroupsList( ev );
		renderTablePoolGrid( ev );
		renderSeatGrid();
	}

	function renderBlockedKPIs( ev ) {
		const blkTables   = ( ev.tables || [] ).filter( t => +t.is_blocked );
		const tableGroups = getBlockedTableGroups( ev );
		const seatGroups  = ev.blocked_seat_groups || [];
		const blkSeats    = ev.blocked_seats || [];

		// Available seat pool capacity calculation: unblocked seat-pool tables
		const seatTables = ( ev.tables || [] ).filter( t => ( t.booking_mode === 'seat' || ! t.booking_mode ) && ! +t.is_blocked );
		const totalCap   = seatTables.reduce( ( sum, t ) => sum + parseInt( t.capacity, 10 ), 0 );
		const bookedCount = ( ev.booked_seats || [] ).length;
		const blkCount    = blkSeats.length;
		const availSeats  = Math.max( 0, totalCap - bookedCount - blkCount );

		const setTxt = ( id, val ) => {
			const el = document.getElementById( id );
			if ( el ) el.textContent = val;
		};

		setTxt( 'frx-blk-kpi-tables', blkTables.length );
		setTxt( 'frx-blk-kpi-groups', seatGroups.length );
		setTxt( 'frx-blk-kpi-seats', blkSeats.length );
		setTxt( 'frx-blk-kpi-avail', availSeats );

		setTxt( 'frx-blocked-tables-badge', `${tableGroups.length} group${tableGroups.length !== 1 ? 's' : ''} (${blkTables.length} tables)` );
		setTxt( 'frx-blocked-groups-badge', `${seatGroups.length} group${seatGroups.length !== 1 ? 's' : ''} (${blkSeats.length} seats)` );
	}

	// Helper to organize blocked tables into connected groups
	function getBlockedTableGroups( ev ) {
		const blkTables = ( ev?.tables || [] ).filter( t => +t.is_blocked );
		const groupsMap = new Map();

		blkTables.forEach( tbl => {
			let gid = tbl.block_group_id;
			if ( ! gid ) {
				if ( tbl.block_note && tbl.blocked_at ) {
					gid = `legacy_${tbl.blocked_at}_${tbl.block_note}`;
				} else {
					gid = `single_tbl_${tbl.id}`;
				}
			}

			if ( ! groupsMap.has( gid ) ) {
				groupsMap.set( gid, {
					group_id       : gid,
					is_group       : false,
					note           : tbl.block_note || '',
					blocked_at     : tbl.blocked_at || '',
					blocked_by     : tbl.blocked_by || 0,
					blocked_by_name: tbl.blocked_by_name || 'Admin',
					tables         : [],
					total_capacity : 0,
				} );
			}

			const grp = groupsMap.get( gid );
			grp.tables.push( tbl );
			grp.total_capacity += ( parseInt( tbl.capacity, 10 ) || 0 );
		} );

		const groups = Array.from( groupsMap.values() );
		groups.forEach( g => {
			g.is_group = g.tables.length > 1 || ( g.group_id && g.group_id.startsWith( 'tbl_grp_' ) );
			g.tables.sort( ( a, b ) => ( a.sort_order || 0 ) - ( b.sort_order || 0 ) );
		} );

		return groups;
	}

	// Reusable Pagination Renderer
	function renderPagination( containerId, currentPage, totalItems, pageSize, label = 'items' ) {
		const container = document.getElementById( containerId );
		if ( ! container ) return;

		const totalPages = Math.max( 1, Math.ceil( totalItems / pageSize ) );

		if ( totalItems <= pageSize ) {
			container.style.display = 'none';
			return;
		}

		container.style.display = 'flex';
		const startItem = ( currentPage - 1 ) * pageSize + 1;
		const endItem   = Math.min( currentPage * pageSize, totalItems );

		const infoEl = container.querySelector( '.lccl-frx-pagination__info' );
		if ( infoEl ) {
			infoEl.innerHTML = `Showing <strong>${startItem}–${endItem}</strong> of <strong>${totalItems}</strong> ${label}`;
		}

		const prevBtn = container.querySelector( '[id$="-prev"]' );
		if ( prevBtn ) {
			prevBtn.disabled = ( currentPage <= 1 );
		}

		const nextBtn = container.querySelector( '[id$="-next"]' );
		if ( nextBtn ) {
			nextBtn.disabled = ( currentPage >= totalPages );
		}

		const pagesWrap = container.querySelector( '.lccl-frx-pagination__pages' );
		if ( pagesWrap ) {
			let pagesHtml = '';
			for ( let p = 1; p <= totalPages; p++ ) {
				pagesHtml += `<button type="button" class="lccl-frx-page-btn ${p === currentPage ? 'is-active' : ''}" data-page="${p}">${p}</button>`;
			}
			pagesWrap.innerHTML = pagesHtml;
		}
	}

	function renderBlockedTableGroupsList( ev ) {
		const wrap  = document.getElementById( 'lccl-frx-table-groups-list' );
		const empty = document.getElementById( 'lccl-frx-no-blocked-tables' );
		if ( ! wrap ) return;

		const groups = getBlockedTableGroups( ev );
		const totalGroups = groups.length;

		if ( ! totalGroups ) {
			wrap.innerHTML = '';
			if ( empty ) empty.style.display = '';
			renderPagination( 'lccl-frx-blocked-tables-pagination', 1, 0, BLOCKED_TABLES_PER_PAGE, 'groups' );
			return;
		}
		if ( empty ) empty.style.display = 'none';

		const totalPages = Math.max( 1, Math.ceil( totalGroups / BLOCKED_TABLES_PER_PAGE ) );
		if ( blockedTablesPage > totalPages ) blockedTablesPage = totalPages;
		if ( blockedTablesPage < 1 ) blockedTablesPage = 1;

		const slice = groups.slice( ( blockedTablesPage - 1 ) * BLOCKED_TABLES_PER_PAGE, blockedTablesPage * BLOCKED_TABLES_PER_PAGE );

		wrap.innerHTML = slice.map( grp => {
			const tables = grp.tables || [];
			const isGroup = tables.length > 1;
			const groupLabel = isGroup ? 'Table Group' : ( tables[0]?.table_label || 'Single Table Block' );
			const metaStr = ( grp.blocked_at ? formatDateTime( grp.blocked_at ) : '' ) +
				( grp.blocked_by_name ? ` · by ${esc(grp.blocked_by_name)}` : '' );

			const tableChips = tables.map( t => {
				const poolText = t.booking_mode === 'table' ? '🟣 Table' : '🔵 Seat';
				return `<span class="lccl-frx-seat-chip lccl-frx-table-chip" data-table-id="${t.id}" title="Click to locate ${esc(t.table_label)} in floor plan">🏷️ ${esc(t.table_label)} <small style="opacity:0.85;margin-left:3px;">(${esc(t.type_name)}, ${t.capacity}s • ${poolText})</small></span>`;
			} ).join('');

			return `
				<div class="lccl-frx-seat-group-card" data-table-group-id="${esc(grp.group_id)}">
					<div class="lccl-frx-seat-group-card__head">
						<div class="lccl-frx-seat-group-card__title">
							<span>🏢 ${esc(groupLabel)}</span>
							<span class="lccl-frx-badge lccl-frx-badge--failed">${tables.length} table${isGroup ? 's' : ''}</span>
							<span class="lccl-frx-badge lccl-frx-badge--private">${grp.total_capacity} seats total</span>
						</div>
						<div class="lccl-frx-seat-group-card__meta">${metaStr}</div>
					</div>
					<div class="lccl-frx-seat-chips-wrap">
						${tableChips}
					</div>
					<div class="lccl-frx-group-note">
						<span>📝</span>
						<div>
							<strong>Note:</strong> ${grp.note ? esc(grp.note) : '<em style="color:#a16207;">No note attached</em>'}
						</div>
					</div>
					<div class="lccl-frx-seat-group-card__actions">
						<button type="button" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--outline"
							data-action="view-table-group" data-group-id="${esc(grp.group_id)}">👁️ Details</button>
						<button type="button" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--success-outline"
							data-action="resend-table-group" data-group-id="${esc(grp.group_id)}">↩ Re-send to Pool</button>
					</div>
				</div>
			`;
		} ).join('');

		renderPagination( 'lccl-frx-blocked-tables-pagination', blockedTablesPage, totalGroups, BLOCKED_TABLES_PER_PAGE, 'groups' );
	}

	function renderBlockedSeatGroupsList( ev ) {
		const wrap  = document.getElementById( 'lccl-frx-seat-groups-list' );
		const empty = document.getElementById( 'lccl-frx-no-blocked-groups' );
		if ( ! wrap ) return;

		const groups = ev.blocked_seat_groups || [];
		const totalGroups = groups.length;

		if ( ! totalGroups ) {
			wrap.innerHTML = '';
			if ( empty ) empty.style.display = '';
			renderPagination( 'lccl-frx-seat-groups-pagination', 1, 0, BLOCKED_SEATS_PER_PAGE, 'groups' );
			return;
		}
		if ( empty ) empty.style.display = 'none';

		const totalPages = Math.max( 1, Math.ceil( totalGroups / BLOCKED_SEATS_PER_PAGE ) );
		if ( blockedSeatsPage > totalPages ) blockedSeatsPage = totalPages;
		if ( blockedSeatsPage < 1 ) blockedSeatsPage = 1;

		const slice = groups.slice( ( blockedSeatsPage - 1 ) * BLOCKED_SEATS_PER_PAGE, blockedSeatsPage * BLOCKED_SEATS_PER_PAGE );

		wrap.innerHTML = slice.map( grp => {
			const seats = grp.seats || [];
			const seatChips = seats.map( s => `<span class="lccl-frx-seat-chip" data-seat="${s}" title="Click to locate Seat #${s}">#${s}</span>` ).join('');
			const metaStr = ( grp.blocked_at ? formatDateTime( grp.blocked_at ) : '' ) +
				( grp.blocked_by_name ? ` · by ${esc(grp.blocked_by_name)}` : '' );

			const groupLabel = grp.group_id
				? ( grp.group_id.startsWith('grp_') ? 'Seat Group' : grp.group_id )
				: 'Individual / Single Block';

			return `
				<div class="lccl-frx-seat-group-card" data-group-id="${esc(grp.group_id)}">
					<div class="lccl-frx-seat-group-card__head">
						<div class="lccl-frx-seat-group-card__title">
							<span>👥 ${esc(groupLabel)}</span>
							<span class="lccl-frx-badge lccl-frx-badge--failed">${seats.length} seat${seats.length > 1 ? 's' : ''}</span>
						</div>
						<div class="lccl-frx-seat-group-card__meta">${metaStr}</div>
					</div>
					<div class="lccl-frx-seat-chips-wrap">
						${seatChips}
					</div>
					<div class="lccl-frx-group-note">
						<span>📝</span>
						<div>
							<strong>Note:</strong> ${grp.note ? esc(grp.note) : '<em style="color:#a16207;">No note attached</em>'}
						</div>
					</div>
					<div class="lccl-frx-seat-group-card__actions">
						<button type="button" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--outline"
							data-action="view-seat-group" data-group-id="${esc(grp.group_id)}">👁️ Details</button>
						<button type="button" class="lccl-frx-btn lccl-frx-btn--sm lccl-frx-btn--success-outline"
							data-action="resend-seat-group" data-group-id="${esc(grp.group_id)}">↩ Re-send to Seat Pool</button>
					</div>
				</div>
			`;
		} ).join('');

		renderPagination( 'lccl-frx-seat-groups-pagination', blockedSeatsPage, totalGroups, BLOCKED_SEATS_PER_PAGE, 'groups' );
	}

	/* ────────────────────────────────────────────────────────────
	   Table Map Grid & Table Block Tool (Table Pool)
	──────────────────────────────────────────────────────────── */
	function renderTablePoolGrid( ev ) {
		const grid  = document.getElementById( 'lccl-frx-table-pool-grid' );
		const empty = document.getElementById( 'lccl-frx-table-pool-empty' );
		const badge = document.getElementById( 'frx-tbl-pool-badge' );
		if ( ! grid ) return;

		const tblPoolTables = ( ev.tables || [] ).filter( t => t.booking_mode === 'table' );

		if ( badge ) {
			const freeCount = tblPoolTables.filter( t => ! +t.is_blocked && ! +t.is_booked ).length;
			badge.textContent = `${tblPoolTables.length} tables (${freeCount} available)`;
		}

		if ( ! tblPoolTables.length ) {
			grid.innerHTML = '';
			if ( empty ) empty.style.display = '';
			selectedTables.clear();
			lastSelectedTable = null;
			updateTableSelectionBar();
			return;
		}
		if ( empty ) empty.style.display = 'none';

		const cardsHtml = tblPoolTables.map( tbl => {
			const tableId    = parseInt( tbl.id, 10 );
			const isBooked   = +tbl.is_booked;
			const isBlocked  = +tbl.is_blocked;
			const isAvail    = ! isBooked && ! isBlocked;
			const isSelected = selectedTables.has( tableId );
			const cap        = parseInt( tbl.capacity, 10 ) || 10;

			let cardClass  = 'lccl-map-card';
			let badgeClass = 'lccl-map-pill';
			let badgeText  = '';

			if ( isBooked ) {
				cardClass += ' lccl-map-card--full-booked';
				badgeClass += ' lccl-map-pill--full-booked';
				badgeText   = '★ Full Table Booked';
			} else if ( isBlocked ) {
				cardClass += ' lccl-map-card--blocked lccl-map-card--clickable';
				badgeClass += ' lccl-map-pill--blocked';
				badgeText   = '🚫 Blocked';
			} else {
				cardClass += ' lccl-map-card--avail-table lccl-map-card--clickable';
				if ( isSelected ) {
					cardClass += ' is-selected';
					badgeClass += ' lccl-map-pill--seat-pool';
					badgeText   = '🔘 Selected';
				} else {
					badgeClass += ' lccl-map-pill--avail-table';
					badgeText   = '✓ Available Table';
				}
			}

			// Circular chairs around table surface
			let chairsHtml = '';
			for ( let sIdx = 0; sIdx < cap; sIdx++ ) {
				const angle = ( sIdx / cap ) * 2 * Math.PI - ( Math.PI / 2 );
				const lPct  = ( 50 + 38 * Math.cos( angle ) ).toFixed( 2 );
				const tPct  = ( 50 + 38 * Math.sin( angle ) ).toFixed( 2 );

				let chairClass = 'lccl-map-chair';
				if ( isBooked ) {
					chairClass += ' lccl-map-chair--full-booked is-booked';
				} else if ( isBlocked ) {
					chairClass += ' lccl-map-chair--blocked';
				} else {
					chairClass += ' lccl-map-chair--avail-table';
				}
				chairsHtml += `<div class="${chairClass}" style="left:${lPct}%; top:${tPct}%;"><span>${sIdx + 1}</span></div>`;
			}

			const state = isBooked ? 'booked' : ( isBlocked ? 'blocked' : 'avail' );

			const footerNote = isBlocked
				? ( tbl.block_note ? `<span class="lccl-frx-note-chip" title="${esc(tbl.block_note)}">📝 ${esc(tbl.block_note.length > 20 ? tbl.block_note.slice(0, 20) + '…' : tbl.block_note)}</span>` : '<span style="color:#ef4444;font-size:10px;font-weight:700;">Click to Inspect</span>' )
				: ( isBooked
					? '<span style="color:#b45309;font-size:10px;font-weight:700;">Full Unit Sold</span>'
					: ( isSelected
						? '<span style="color:#4f46e5;font-weight:700;font-size:11px;">✓ Selected</span>'
						: '<span style="color:#059669;font-size:11px;">Click to Select</span>' ) );

			return `
				<div class="${cardClass}" data-table-id="${tableId}" data-state="${state}">
					<div class="lccl-map-card__head">
						<div class="lccl-map-card__title-wrap">
							<h4 class="lccl-map-card__title">${esc(tbl.table_label)}</h4>
							<span class="lccl-map-card__tier">${esc(tbl.type_name)} • ${cap} Seats</span>
						</div>
						<span class="${badgeClass}">${badgeText}</span>
					</div>
					<div class="lccl-map-stage">
						<div class="lccl-map-surface">
							<span class="lccl-map-surface__lbl">${esc(tbl.table_label)}</span>
							<span class="lccl-map-surface__cap">${cap} Seats</span>
							${isBlocked
								? '<span class="lccl-map-surface__ratio" style="background:#fee2e2;color:#dc2626;">BLOCKED</span>'
								: ( isBooked
									? '<span class="lccl-map-surface__ratio" style="background:#fef3c7;color:#b45309;">BOOKED</span>'
									: '<span class="lccl-map-surface__ratio" style="background:#dcfce7;color:#15803d;">WHOLE TABLE</span>' )}
						</div>
						${chairsHtml}
					</div>
					<div class="lccl-map-card__footer">
						<div class="lccl-map-card__footer-flex">
							<span class="lccl-map-card__price">LKR ${fmt(tbl.price || 0)}</span>
							${footerNote}
						</div>
					</div>
				</div>
			`;
		} ).join('');

		grid.innerHTML = cardsHtml;
		updateTableSelectionBar();
	}

	function updateTableSelectionBar() {
		const bar     = document.getElementById( 'lccl-frx-tbl-selection-bar' );
		const countEl = document.getElementById( 'frx-tbl-sel-count' );
		const prevEl  = document.getElementById( 'frx-tbl-sel-preview' );
		const noteEl  = document.getElementById( 'frx-tbl-group-note-input' );
		if ( ! bar ) return;

		if ( ! selectedTables.size ) {
			bar.style.display = 'none';
			if ( noteEl ) noteEl.value = '';
			return;
		}

		bar.style.display = 'flex';
		const ev = getEvent( currentEventId );
		const labels = [];
		selectedTables.forEach( id => {
			const tbl = ( ev?.tables || [] ).find( t => parseInt( t.id, 10 ) === id );
			labels.push( tbl ? tbl.table_label : `#${id}` );
		} );
		if ( countEl ) countEl.textContent = `${selectedTables.size} table${selectedTables.size > 1 ? 's' : ''} selected`;
		if ( prevEl )  prevEl.textContent  = `Tables: ${labels.join( ', ' )}`;
	}

	// Table Selection Bar: Cancel / Deselect
	document.getElementById( 'frx-btn-cancel-tbl-selection' )?.addEventListener( 'click', () => {
		selectedTables.clear();
		lastSelectedTable = null;
		document.getElementById( 'lccl-frx-table-pool-grid' )
			?.querySelectorAll( '.lccl-map-card.is-selected' )
			.forEach( c => c.classList.remove( 'is-selected' ) );
		updateTableSelectionBar();
	} );

	// Table Selection Bar: Block Selected Tables
	document.getElementById( 'frx-btn-block-tables-group' )?.addEventListener( 'click', () => {
		if ( ! selectedTables.size ) return;
		const btn      = document.getElementById( 'frx-btn-block-tables-group' );
		const note     = document.getElementById( 'frx-tbl-group-note-input' )?.value || '';
		const tableIds = Array.from( selectedTables );
		btn.disabled   = true;
		btn.innerHTML  = '<span>⏳</span> Blocking…';

		ajax( 'lccl_de_fr_toggle_table_block', {
			event_id : currentEventId,
			table_ids: tableIds,
			blocked  : 1,
			note     : note,
		} ).then( res => {
			btn.disabled  = false;
			btn.innerHTML = '<span>🔒</span> Block Selected Tables';
			if ( ! res.success ) {
				toast( res.data?.message || res.data || 'Failed to block tables', 'error' );
				return;
			}
			const ev = getEvent( currentEventId );
			if ( ev ) {
				tableIds.forEach( id => {
					const tbl = ( ev.tables || [] ).find( t => parseInt( t.id, 10 ) === id );
					if ( tbl ) {
						tbl.is_blocked      = 1;
						tbl.block_group_id  = res.data.block_group_id || null;
						tbl.block_note      = res.data.block_note || note;
						tbl.blocked_at      = res.data.blocked_at || '';
						tbl.blocked_by      = res.data.blocked_by || 0;
						tbl.blocked_by_name = res.data.blocked_by_name || '';
					}
				} );
				recalcPoolStats( ev );
			}
			selectedTables.clear();
			lastSelectedTable = null;
			updateTableSelectionBar();
			renderBlockedPanel();
			toast( `Blocked ${tableIds.length} tables as group`, 'success' );
		} ).catch( () => {
			btn.disabled  = false;
			btn.innerHTML = '<span>🔒</span> Block Selected Tables';
			toast( 'Request failed', 'error' );
		} );
	} );

	// Table Pool Card Click Delegation: select / shift+click or inspect blocked
	document.getElementById( 'lccl-frx-table-pool-grid' )?.addEventListener( 'click', e => {
		const card = e.target.closest( '.lccl-map-card' );
		if ( ! card ) return;
		const tableId = parseInt( card.dataset.tableId, 10 );
		const state   = card.dataset.state;

		if ( state === 'blocked' ) {
			viewBlockedTableDetails( tableId );
			return;
		}
		if ( state === 'booked' ) return;

		if ( state === 'avail' ) {
			const ev = getEvent( currentEventId );
			const availTables = ( ev?.tables || [] ).filter( t => t.booking_mode === 'table' && ! +t.is_blocked && ! +t.is_booked );
			const availIds = availTables.map( t => parseInt( t.id, 10 ) );

			if ( e.shiftKey && lastSelectedTable !== null && availIds.includes( lastSelectedTable ) && availIds.includes( tableId ) ) {
				const idx1  = availIds.indexOf( lastSelectedTable );
				const idx2  = availIds.indexOf( tableId );
				const start = Math.min( idx1, idx2 );
				const end   = Math.max( idx1, idx2 );
				for ( let i = start; i <= end; i++ ) {
					selectedTables.add( availIds[i] );
					const c = document.querySelector( `#lccl-frx-table-pool-grid .lccl-map-card[data-table-id="${availIds[i]}"]` );
					if ( c ) c.classList.add( 'is-selected' );
				}
			} else {
				if ( selectedTables.has( tableId ) ) {
					selectedTables.delete( tableId );
					card.classList.remove( 'is-selected' );
					if ( lastSelectedTable === tableId ) lastSelectedTable = null;
				} else {
					selectedTables.add( tableId );
					card.classList.add( 'is-selected' );
					lastSelectedTable = tableId;
				}
			}
			updateTableSelectionBar();
		}
	} );

	/* ────────────────────────────────────────────────────────────
	   Seat Map Grid & Multi-Seat Block Tool (Seat Pool)
	──────────────────────────────────────────────────────────── */
	function renderSeatGrid() {
		const ev = getEvent( currentEventId );
		if ( ! ev ) return;

		const grid  = document.getElementById( 'lccl-frx-seat-grid' );
		const empty = document.getElementById( 'lccl-frx-seat-empty' );
		const badge = document.getElementById( 'frx-seat-pool-badge' );
		if ( ! grid ) return;

		// Collect all seat-pool tables
		const seatTables = ( ev.tables || [] ).filter( t => ( t.booking_mode === 'seat' || ! t.booking_mode ) );

		// Number seats sequentially across all seat-pool tables
		let seatCounter = 1;
		const tableMeta = [];
		seatTables.forEach( t => {
			const cap   = parseInt( t.capacity, 10 ) || 0;
			const start = seatCounter;
			const end   = seatCounter + cap - 1;
			seatCounter += cap;
			tableMeta.push( { table: t, startSeat: start, endSeat: end, capacity: cap } );
		} );

		const totalSeats = seatCounter - 1;

		if ( badge ) {
			badge.textContent = `${totalSeats} seats (${seatTables.length} tables)`;
		}

		if ( ! totalSeats || ! seatTables.length ) {
			grid.innerHTML = '';
			if ( empty ) empty.style.display = '';
			selectedSeats.clear();
			lastSelectedSeat = null;
			updateSelectionBar();
			return;
		}
		if ( empty ) empty.style.display = 'none';

		const blockedSet = new Set( ( ev.blocked_seats || [] ).map( Number ) );
		const bookedSet  = new Set( ( ev.booked_seats  || [] ).map( Number ) );

		// Build lookup for seat block notes and group membership
		const detailMap = new Map();
		( ev.blocked_seat_details || [] ).forEach( d => detailMap.set( parseInt( d.seat_number, 10 ), d ) );
		( ev.blocked_seat_groups || [] ).forEach( g => {
			( g.seats || [] ).forEach( s => {
				if ( ! detailMap.has( s ) ) {
					detailMap.set( s, {
						seat_number: s,
						group_id: g.group_id,
						note: g.note,
						blocked_at: g.blocked_at,
						blocked_by_name: g.blocked_by_name,
					} );
				}
			} );
		} );

		const cardsHtml = tableMeta.map( meta => {
			const tbl            = meta.table;
			const cap            = meta.capacity;
			const startSeat      = meta.startSeat;
			const endSeat        = meta.endSeat;
			const isTableBlocked = +tbl.is_blocked;

			let tblBookedCount  = 0;
			let tblBlockedCount = 0;
			let tblAvailCount   = 0;

			// Generate circular chairs
			let chairsHtml = '';
			for ( let sIdx = 0; sIdx < cap; sIdx++ ) {
				const seatNum = startSeat + sIdx;
				const angle   = ( sIdx / cap ) * 2 * Math.PI - ( Math.PI / 2 );
				const lPct    = ( 50 + 38 * Math.cos( angle ) ).toFixed( 2 );
				const tPct    = ( 50 + 38 * Math.sin( angle ) ).toFixed( 2 );

				let state, chairClass, title;

				if ( bookedSet.has( seatNum ) ) {
					state = 'booked';
					tblBookedCount++;
					chairClass = 'lccl-map-chair lccl-frx-seat-dot lccl-map-chair--seat-booked lccl-frx-seat-dot--booked is-booked';
					title = `Seat #${seatNum} (Booked / Paid)`;
				} else if ( isTableBlocked ) {
					state = 'table-blocked';
					tblBlockedCount++;
					chairClass = 'lccl-map-chair lccl-frx-seat-dot lccl-map-chair--blocked lccl-frx-seat-dot--blocked';
					title = `Seat #${seatNum} (Table Blocked${tbl.block_note ? ': ' + tbl.block_note : ''}) — Click to view table`;
				} else if ( blockedSet.has( seatNum ) ) {
					state = 'blocked';
					tblBlockedCount++;
					const dt = detailMap.get( seatNum );
					const notePart = dt?.note ? ` — Note: ${dt.note}` : '';
					chairClass = 'lccl-map-chair lccl-frx-seat-dot lccl-map-chair--blocked lccl-frx-seat-dot--blocked';
					title = `Seat #${seatNum} (Blocked${notePart}) — Click to view details / re-send to pool`;
				} else {
					state = 'avail';
					tblAvailCount++;
					const isSelected = selectedSeats.has( seatNum );
					chairClass = 'lccl-map-chair lccl-frx-seat-dot lccl-map-chair--seat-free lccl-frx-seat-dot--avail' + ( isSelected ? ' is-selected' : '' );
					title = `Seat #${seatNum} (Available) — Click to select (Shift+Click for range)`;
				}

				chairsHtml += `<div class="${chairClass}" style="left:${lPct}%; top:${tPct}%;" data-seat="${seatNum}" data-table-id="${tbl.id}" data-state="${state}" title="${esc(title)}"><span>${seatNum}</span></div>`;
			}

			// Table card classes & badge
			let cardClass  = 'lccl-map-card lccl-map-card--seat-pool';
			let badgeClass = 'lccl-map-pill';
			let badgeText  = '';

			if ( isTableBlocked ) {
				cardClass += ' lccl-map-card--blocked';
				badgeClass += ' lccl-map-pill--blocked';
				badgeText   = '🚫 Table Blocked';
			} else if ( tblBookedCount === cap ) {
				cardClass += ' lccl-map-card--full-booked';
				badgeClass += ' lccl-map-pill--full-booked';
				badgeText   = '★ All Booked';
			} else {
				badgeClass += ' lccl-map-pill--seat-pool';
				badgeText   = `${tblBookedCount} / ${cap} Booked`;
			}

			// Quick Action in footer
			let footerAction = '';
			if ( isTableBlocked ) {
				footerAction = `<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--xs" data-action="view-blocked-table" data-table-id="${tbl.id}">👁️ Inspect Table Block</button>`;
			} else if ( tblAvailCount > 0 ) {
				let allAvailSelected = true;
				for ( let s = startSeat; s <= endSeat; s++ ) {
					if ( ! bookedSet.has( s ) && ! blockedSet.has( s ) && ! selectedSeats.has( s ) ) {
						allAvailSelected = false;
						break;
					}
				}
				if ( allAvailSelected ) {
					footerAction = `<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--xs" data-action="deselect-table-seats" data-start="${startSeat}" data-end="${endSeat}">✕ Deselect Table</button>`;
				} else {
					footerAction = `<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--xs" data-action="select-table-seats" data-start="${startSeat}" data-end="${endSeat}">+ Select All (${tblAvailCount})</button>`;
				}
			} else {
				footerAction = `<span style="color:#aaa;font-size:11px;">No seats available</span>`;
			}

			return `
				<div class="${cardClass}" data-table-id="${tbl.id}">
					<div class="lccl-map-card__head">
						<div class="lccl-map-card__title-wrap">
							<h4 class="lccl-map-card__title">${esc(tbl.table_label)}</h4>
							<span class="lccl-map-card__tier">${esc(tbl.type_name)} • Seats #${startSeat}–#${endSeat}</span>
						</div>
						<span class="${badgeClass}">${badgeText}</span>
					</div>
					<div class="lccl-map-stage">
						<div class="lccl-map-surface">
							<span class="lccl-map-surface__lbl">${esc(tbl.table_label)}</span>
							<span class="lccl-map-surface__cap">${cap} Seats</span>
							${isTableBlocked
								? '<span class="lccl-map-surface__ratio" style="background:#fee2e2;color:#dc2626;">BLOCKED</span>'
								: ( tblBlockedCount > 0
									? `<span class="lccl-map-surface__ratio" style="background:#fee2e2;color:#dc2626;">${tblBlockedCount} Blocked</span>`
									: `<span class="lccl-map-surface__ratio">${tblBookedCount} / ${cap} Booked</span>` )}
						</div>
						${chairsHtml}
					</div>
					<div class="lccl-map-card__footer">
						<div class="lccl-map-card__footer-flex">
							<span style="font-size:11px;font-weight:600;color:#64748b;">#${startSeat}–#${endSeat}</span>
							${footerAction}
						</div>
					</div>
				</div>
			`;
		} ).join('');

		grid.innerHTML = cardsHtml;
		updateSelectionBar();
	}

	function updateSelectionBar() {
		const bar     = document.getElementById( 'lccl-frx-selection-bar' );
		const countEl = document.getElementById( 'frx-sel-count' );
		const prevEl  = document.getElementById( 'frx-sel-seats-preview' );
		const noteEl  = document.getElementById( 'frx-group-note-input' );
		if ( ! bar ) return;

		if ( ! selectedSeats.size ) {
			bar.style.display = 'none';
			if ( noteEl ) noteEl.value = '';
			return;
		}

		bar.style.display = 'flex';
		const arr = Array.from( selectedSeats ).sort( ( a, b ) => a - b );
		if ( countEl ) countEl.textContent = `${arr.length} seat${arr.length > 1 ? 's' : ''} selected`;
		if ( prevEl )  prevEl.textContent  = `Seats: #${arr.join( ', #' )}`;
	}

	// Selection Bar: Cancel / Deselect
	document.getElementById( 'frx-btn-cancel-selection' )?.addEventListener( 'click', () => {
		selectedSeats.clear();
		lastSelectedSeat = null;
		document.getElementById( 'lccl-frx-seat-grid' )
			?.querySelectorAll( '.lccl-frx-seat-dot.is-selected' )
			.forEach( d => d.classList.remove( 'is-selected' ) );
		updateSelectionBar();
		renderSeatGrid();
	} );

	// Selection Bar: Block as Group
	document.getElementById( 'frx-btn-block-group' )?.addEventListener( 'click', () => {
		if ( ! selectedSeats.size ) return;
		const btn  = document.getElementById( 'frx-btn-block-group' );
		const note = document.getElementById( 'frx-group-note-input' )?.value || '';
		btn.disabled  = true;
		btn.innerHTML = '<span>⏳</span> Blocking…';

		ajax( 'lccl_de_fr_block_seats_group', {
			event_id    : currentEventId,
			seat_numbers: Array.from( selectedSeats ),
			note        : note,
		} ).then( res => {
			btn.disabled  = false;
			btn.innerHTML = '<span>🔒</span> Block as Group';
			if ( ! res.success ) {
				toast( res.data?.message || res.data || 'Failed to block seats', 'error' );
				return;
			}
			const ev = getEvent( currentEventId );
			if ( ev ) {
				ev.blocked_seat_groups = res.data.groups || [];
				ev.blocked_seats       = res.data.blocked_seats || [];
				// Refresh local details
				if ( res.data.groups ) {
					const details = [];
					res.data.groups.forEach( g => {
						( g.seats || [] ).forEach( s => {
							details.push( {
								seat_number: s,
								group_id: g.group_id,
								note: g.note,
								blocked_at: g.blocked_at,
								blocked_by: g.blocked_by,
								blocked_by_name: g.blocked_by_name,
							} );
						} );
					} );
					ev.blocked_seat_details = details;
				}
			}
			selectedSeats.clear();
			lastSelectedSeat = null;
			updateSelectionBar();
			renderBlockedPanel();
			toast( `Blocked ${res.data.seat_numbers?.length || 'selected'} seats as group`, 'success' );
		} ).catch( () => {
			btn.disabled  = false;
			btn.innerHTML = '<span>🔒</span> Block as Group';
			toast( 'Request failed', 'error' );
		} );
	} );

	// Seat Grid Click Delegation: multi-select, table action, or inspect blocked
	document.getElementById( 'lccl-frx-seat-grid' )?.addEventListener( 'click', e => {
		// Table footer action: Select / Deselect all available seats in a table
		const actionBtn = e.target.closest( '[data-action]' );
		if ( actionBtn ) {
			const action = actionBtn.dataset.action;
			if ( action === 'view-blocked-table' ) {
				viewBlockedTableDetails( actionBtn.dataset.tableId );
				return;
			}
			if ( action === 'select-table-seats' || action === 'deselect-table-seats' ) {
				const start = parseInt( actionBtn.dataset.start, 10 );
				const end   = parseInt( actionBtn.dataset.end, 10 );
				const ev    = getEvent( currentEventId );
				const blockedSet = new Set( ( ev?.blocked_seats || [] ).map( Number ) );
				const bookedSet  = new Set( ( ev?.booked_seats  || [] ).map( Number ) );

				for ( let s = start; s <= end; s++ ) {
					if ( ! blockedSet.has( s ) && ! bookedSet.has( s ) ) {
						const dot = document.querySelector( `.lccl-frx-seat-dot[data-seat="${s}"]` );
						if ( action === 'select-table-seats' ) {
							selectedSeats.add( s );
							if ( dot ) dot.classList.add( 'is-selected' );
						} else {
							selectedSeats.delete( s );
							if ( dot ) dot.classList.remove( 'is-selected' );
						}
					}
				}
				updateSelectionBar();
				renderSeatGrid();
				return;
			}
		}

		// Chair Dot click
		const dot = e.target.closest( '.lccl-frx-seat-dot' );
		if ( ! dot || dot.classList.contains( 'is-booked' ) ) return;

		const seatNum = parseInt( dot.dataset.seat, 10 );
		const state   = dot.dataset.state;

		if ( state === 'table-blocked' ) {
			viewBlockedTableDetails( dot.dataset.tableId );
			return;
		}

		if ( state === 'blocked' ) {
			inspectBlockedSeat( seatNum );
			return;
		}

		if ( state === 'avail' ) {
			const ev = getEvent( currentEventId );
			const blockedSet = new Set( ( ev?.blocked_seats || [] ).map( Number ) );
			const bookedSet  = new Set( ( ev?.booked_seats  || [] ).map( Number ) );

			if ( e.shiftKey && lastSelectedSeat !== null ) {
				const start = Math.min( lastSelectedSeat, seatNum );
				const end   = Math.max( lastSelectedSeat, seatNum );
				for ( let s = start; s <= end; s++ ) {
					if ( ! blockedSet.has( s ) && ! bookedSet.has( s ) ) {
						selectedSeats.add( s );
						const el = document.querySelector( `.lccl-frx-seat-dot[data-seat="${s}"]` );
						if ( el ) el.classList.add( 'is-selected' );
					}
				}
			} else {
				if ( selectedSeats.has( seatNum ) ) {
					selectedSeats.delete( seatNum );
					dot.classList.remove( 'is-selected' );
					if ( lastSelectedSeat === seatNum ) lastSelectedSeat = null;
				} else {
					selectedSeats.add( seatNum );
					dot.classList.add( 'is-selected' );
					lastSelectedSeat = seatNum;
				}
			}
			updateSelectionBar();
		}
	} );

	// Inspect Blocked Seat Modal
	function inspectBlockedSeat( seatNum ) {
		const ev = getEvent( currentEventId );
		if ( ! ev ) return;

		const detail = ( ev.blocked_seat_details || [] ).find( d => parseInt( d.seat_number, 10 ) === seatNum ) || {};
		const grp = ( ev.blocked_seat_groups || [] ).find( g => ( g.seats || [] ).includes( seatNum ) );

		const isGroup = grp && grp.seats && grp.seats.length > 1;
		const groupName = grp?.group_id ? ( grp.group_id.startsWith('grp_') ? 'Seat Group' : grp.group_id ) : 'Individual Block';
		const noteText = detail.note || grp?.note || '';
		const blockedTime = detail.blocked_at || grp?.blocked_at || '';
		const blockedBy = detail.blocked_by_name || grp?.blocked_by_name || 'Admin';

		const bodyHtml = `
			<div class="lccl-frx-modal-dl">
				<div class="lccl-frx-modal-dt">Seat:</div>
				<div class="lccl-frx-modal-dd"><strong>#${seatNum}</strong></div>
				<div class="lccl-frx-modal-dt">Block Type:</div>
				<div class="lccl-frx-modal-dd">${isGroup ? `Connected in Group: <strong>${esc(groupName)}</strong> (${grp.seats.length} seats)` : 'Single / Individual Block'}</div>
				<div class="lccl-frx-modal-dt">Blocked At:</div>
				<div class="lccl-frx-modal-dd">${blockedTime ? formatDateTime(blockedTime) : '—'}</div>
				<div class="lccl-frx-modal-dt">Blocked By:</div>
				<div class="lccl-frx-modal-dd">${esc(blockedBy)}</div>
			</div>
			${isGroup ? `
				<div style="margin-bottom:12px;">
					<label style="display:block;font-weight:600;font-size:12px;color:#6b7280;margin-bottom:6px;">All seats in this group:</label>
					<div class="lccl-frx-seat-chips-wrap">
						${grp.seats.map( s => `<span class="lccl-frx-seat-chip" data-seat="${s}" style="${s===seatNum?'background:#fee2e2;border-color:#fca5a5;color:#dc2626;font-weight:700;':''}">#${s}</span>` ).join('')}
					</div>
				</div>
			` : ''}
			<div class="lccl-frx-modal-note-box">
				<strong>Attached Note / Reason:</strong><br>
				${noteText ? esc(noteText) : '<em>No note attached</em>'}
			</div>
		`;

		let footHtml = '';
		if ( isGroup ) {
			footHtml = `
				<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--sm" id="frx-btn-modal-close-seat">Close</button>
				<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--sm" id="frx-btn-modal-resend-single">↩ Re-send Only Seat #${seatNum}</button>
				<button type="button" class="lccl-frx-btn lccl-frx-btn--success-outline lccl-frx-btn--sm" id="frx-btn-modal-resend-all-grp">↩ Re-send Whole Group (${grp.seats.length} seats)</button>
			`;
		} else {
			footHtml = `
				<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--sm" id="frx-btn-modal-close-seat">Close</button>
				<button type="button" class="lccl-frx-btn lccl-frx-btn--success-outline lccl-frx-btn--sm" id="frx-btn-modal-resend-single">↩ Re-send to Seat Pool</button>
			`;
		}

		openModal( `Blocked Seat #${seatNum}`, bodyHtml, footHtml );

		document.getElementById( 'frx-btn-modal-close-seat' )?.addEventListener( 'click', closeModal );
		document.getElementById( 'frx-btn-modal-resend-single' )?.addEventListener( 'click', () => {
			closeModal();
			resendSingleSeat( seatNum );
		} );
		document.getElementById( 'frx-btn-modal-resend-all-grp' )?.addEventListener( 'click', () => {
			closeModal();
			resendSeatGroup( grp.group_id );
		} );
	}

	// Re-send Single Seat to Pool
	function resendSingleSeat( seatNum ) {
		const ev = getEvent( currentEventId );
		if ( ! confirm( `Re-send Seat #${seatNum} back to the available seat pool?` ) ) return;

		ajax( 'lccl_de_fr_toggle_seat_block', {
			event_id   : currentEventId,
			seat_num   : seatNum,
			action_type: 'unblock',
		} ).then( res => {
			if ( ! res.success ) {
				toast( res.data?.message || res.data || 'Error', 'error' );
				return;
			}
			if ( ev ) {
				ev.blocked_seat_groups  = res.data.groups || [];
				ev.blocked_seats        = res.data.blocked_seats || [];
				ev.blocked_seat_details = ( ev.blocked_seat_details || [] ).filter( d => parseInt( d.seat_number, 10 ) !== seatNum );
			}
			renderBlockedPanel();
			toast( `Seat #${seatNum} re-sent back to seat pool`, 'success' );
		} ).catch( () => toast( 'Request failed', 'error' ) );
	}

	// Re-send Whole Seat Group to Pool
	function resendSeatGroup( groupId ) {
		const ev = getEvent( currentEventId );
		const grp = ( ev?.blocked_seat_groups || [] ).find( g => g.group_id === groupId );
		const count = grp ? grp.seats.length : 'selected';
		if ( ! confirm( `Re-send this group (${count} seats) back to the available seat pool?` ) ) return;

		ajax( 'lccl_de_fr_unblock_seat_group', {
			event_id: currentEventId,
			group_id: groupId,
		} ).then( res => {
			if ( ! res.success ) {
				toast( res.data?.message || res.data || 'Error', 'error' );
				return;
			}
			if ( ev ) {
				ev.blocked_seat_groups  = res.data.groups || [];
				ev.blocked_seats        = res.data.blocked_seats || [];
				ev.blocked_seat_details = ( ev.blocked_seat_details || [] ).filter( d => d.group_id !== groupId );
			}
			renderBlockedPanel();
			toast( `Seat group (${count} seats) re-sent back to available seat pool`, 'success' );
		} ).catch( () => toast( 'Request failed', 'error' ) );
	}

	// View Seat Group Details Modal
	function viewSeatGroupDetails( groupId ) {
		const ev = getEvent( currentEventId );
		const grp = ( ev?.blocked_seat_groups || [] ).find( g => g.group_id === groupId );
		if ( ! grp ) return;

		const seats = grp.seats || [];
		const chipsHtml = seats.map( s => `<span class="lccl-frx-seat-chip" data-seat="${s}" title="Click to locate Seat #${s}">#${s}</span>` ).join('');

		const bodyHtml = `
			<div class="lccl-frx-modal-dl">
				<div class="lccl-frx-modal-dt">Group ID:</div>
				<div class="lccl-frx-modal-dd"><code>${esc(grp.group_id || '—')}</code></div>
				<div class="lccl-frx-modal-dt">Total Seats:</div>
				<div class="lccl-frx-modal-dd"><strong>${seats.length} seats</strong></div>
				<div class="lccl-frx-modal-dt">Blocked At:</div>
				<div class="lccl-frx-modal-dd">${grp.blocked_at ? formatDateTime(grp.blocked_at) : '—'}</div>
				<div class="lccl-frx-modal-dt">Blocked By:</div>
				<div class="lccl-frx-modal-dd">${esc(grp.blocked_by_name || 'Admin')}</div>
			</div>
			<div style="margin-bottom:14px;">
				<label style="display:block;font-weight:600;font-size:12px;color:#6b7280;margin-bottom:6px;">Connected Seats in this Group (click chip to locate in grid):</label>
				<div class="lccl-frx-seat-chips-wrap">${chipsHtml}</div>
			</div>
			<div class="lccl-frx-modal-note-box">
				<strong>Attached Group Note:</strong><br>
				${grp.note ? esc(grp.note) : '<em>No note attached</em>'}
			</div>
		`;
		const footHtml = `
			<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--sm" id="frx-btn-modal-close-grp">Close</button>
			<button type="button" class="lccl-frx-btn lccl-frx-btn--success-outline lccl-frx-btn--sm" id="frx-btn-modal-resend-grp">↩ Re-send Group to Seat Pool</button>
		`;
		openModal( `Seat Group Details (${seats.length} seats)`, bodyHtml, footHtml );
		document.getElementById( 'frx-btn-modal-close-grp' )?.addEventListener( 'click', closeModal );
		document.getElementById( 'frx-btn-modal-resend-grp' )?.addEventListener( 'click', () => {
			closeModal();
			resendSeatGroup( grp.group_id );
		} );
	}

	// Re-send Blocked Table to Pool
	function resendTableToPool( tableId ) {
		const ev = getEvent( currentEventId );
		const tbl = ( ev?.tables || [] ).find( t => String(t.id) === String(tableId) );
		const label = tbl ? tbl.table_label : `Table #${tableId}`;
		const targetPool = tbl?.booking_mode === 'table' ? 'Table Pool' : 'Seat Pool';

		if ( ! confirm( `Re-send "${label}" back to ${targetPool}? It will immediately become available for booking.` ) ) return;

		ajax( 'lccl_de_fr_toggle_table_block', {
			event_id: currentEventId,
			table_id: tableId,
			blocked : 0,
		} ).then( res => {
			if ( ! res.success ) {
				toast( res.data?.message || res.data || 'Error', 'error' );
				return;
			}
			if ( ev && tbl ) {
				tbl.is_blocked      = 0;
				tbl.block_group_id  = null;
				tbl.block_note      = '';
				tbl.blocked_at      = '';
				tbl.blocked_by      = null;
				tbl.blocked_by_name = '';
				recalcPoolStats( ev );
			}
			renderBlockedPanel();
			toast( `Table "${label}" re-sent back to ${targetPool}`, 'success' );
		} ).catch( () => toast( 'Request failed', 'error' ) );
	}

	// Re-send Whole Table Group to Pool
	function resendTableGroup( groupId ) {
		const ev = getEvent( currentEventId );
		const groups = getBlockedTableGroups( ev );
		const grp = groups.find( g => g.group_id === groupId );
		if ( ! grp ) return;

		const count = grp.tables.length;
		const tableIds = grp.tables.map( t => parseInt( t.id, 10 ) );
		if ( ! confirm( `Re-send this table group (${count} table${count > 1 ? 's' : ''}) back to their designated pools? They will immediately become available for booking.` ) ) return;

		ajax( 'lccl_de_fr_unblock_table_group', {
			event_id : currentEventId,
			group_id : ( grp.group_id && grp.group_id.startsWith( 'tbl_grp_' ) ) ? grp.group_id : '',
			table_ids: tableIds,
		} ).then( res => {
			if ( ! res.success ) {
				toast( res.data?.message || res.data || 'Error', 'error' );
				return;
			}
			if ( ev ) {
				tableIds.forEach( id => {
					const tbl = ( ev.tables || [] ).find( t => parseInt( t.id, 10 ) === id );
					if ( tbl ) {
						tbl.is_blocked      = 0;
						tbl.block_group_id  = null;
						tbl.block_note      = '';
						tbl.blocked_at      = '';
						tbl.blocked_by      = null;
						tbl.blocked_by_name = '';
					}
				} );
				recalcPoolStats( ev );
			}
			renderBlockedPanel();
			toast( `Table group (${count} table${count > 1 ? 's' : ''}) re-sent back to pool`, 'success' );
		} ).catch( () => toast( 'Request failed', 'error' ) );
	}

	// View Table Group Details Modal
	function viewTableGroupDetails( groupId ) {
		const ev = getEvent( currentEventId );
		const groups = getBlockedTableGroups( ev );
		const grp = groups.find( g => g.group_id === groupId );
		if ( ! grp ) return;

		const tables = grp.tables || [];
		const isGroup = tables.length > 1;
		const title = isGroup ? `Table Group Details (${tables.length} tables)` : `Blocked Table: ${tables[0]?.table_label || ''}`;

		const bodyHtml = `
			<div class="lccl-frx-modal-dl">
				<div class="lccl-frx-modal-dt">Group:</div>
				<div class="lccl-frx-modal-dd">${isGroup ? `<code>${esc(grp.group_id)}</code>` : 'Single Table Block'}</div>
				<div class="lccl-frx-modal-dt">Total Tables:</div>
				<div class="lccl-frx-modal-dd"><strong>${tables.length} table${isGroup ? 's' : ''}</strong> (${grp.total_capacity} seats total)</div>
				<div class="lccl-frx-modal-dt">Blocked At:</div>
				<div class="lccl-frx-modal-dd">${grp.blocked_at ? formatDateTime(grp.blocked_at) : '—'}</div>
				<div class="lccl-frx-modal-dt">Blocked By:</div>
				<div class="lccl-frx-modal-dd">${esc(grp.blocked_by_name || 'Admin')}</div>
			</div>
			<div style="margin-bottom:14px;">
				<label style="display:block;font-weight:600;font-size:12px;color:#6b7280;margin-bottom:6px;">Connected Tables in this Group:</label>
				<div style="display:flex;flex-direction:column;gap:6px;max-height:220px;overflow-y:auto;">
					${tables.map( t => `
						<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">
							<div>
								<strong>🏷️ ${esc(t.table_label)}</strong>
								<span style="font-size:11px;color:#64748b;margin-left:8px;">${esc(t.type_name)} • ${t.capacity} seats • ${t.booking_mode === 'table' ? '🟣 Table Pool' : '🔵 Seat Pool'}</span>
							</div>
							${isGroup ? `<button type="button" class="lccl-frx-btn lccl-frx-btn--xs lccl-frx-btn--outline" data-modal-action="resend-single-table" data-table-id="${t.id}" title="Re-send only this table">↩ Re-send</button>` : ''}
						</div>
					` ).join('')}
				</div>
			</div>
			<div class="lccl-frx-modal-note-box">
				<strong>Attached Group Note:</strong><br>
				${grp.note ? esc(grp.note) : '<em>No note attached</em>'}
			</div>
		`;

		const footHtml = `
			<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--sm" id="frx-btn-modal-close-tbl-grp">Close</button>
			<button type="button" class="lccl-frx-btn lccl-frx-btn--success-outline lccl-frx-btn--sm" id="frx-btn-modal-resend-all-tbl-grp">↩ Re-send ${isGroup ? `Whole Group (${tables.length} tables)` : 'Table'} to Pool</button>
		`;

		openModal( title, bodyHtml, footHtml );
		document.getElementById( 'frx-btn-modal-close-tbl-grp' )?.addEventListener( 'click', closeModal );
		document.getElementById( 'frx-btn-modal-resend-all-tbl-grp' )?.addEventListener( 'click', () => {
			closeModal();
			resendTableGroup( grp.group_id );
		} );
	}

	// View Individual Blocked Table Details Modal
	function viewBlockedTableDetails( tableId ) {
		const ev = getEvent( currentEventId );
		const tbl = ( ev?.tables || [] ).find( t => String(t.id) === String(tableId) );
		if ( ! tbl ) return;

		const groups = getBlockedTableGroups( ev );
		const grp = groups.find( g => ( g.tables || [] ).some( t => String(t.id) === String(tableId) ) );
		const isGroup = grp && grp.tables && grp.tables.length > 1;

		const poolBadge = tbl.booking_mode === 'table' ? '🟣 Table Pool' : '🔵 Seat Pool';
		const bodyHtml = `
			<div class="lccl-frx-modal-dl">
				<div class="lccl-frx-modal-dt">Table:</div>
				<div class="lccl-frx-modal-dd"><strong>${esc(tbl.table_label)}</strong></div>
				<div class="lccl-frx-modal-dt">Tier:</div>
				<div class="lccl-frx-modal-dd">${esc(tbl.type_name)}</div>
				<div class="lccl-frx-modal-dt">Capacity:</div>
				<div class="lccl-frx-modal-dd">${esc(tbl.capacity)} seats</div>
				<div class="lccl-frx-modal-dt">Designated Pool:</div>
				<div class="lccl-frx-modal-dd">${poolBadge}</div>
				<div class="lccl-frx-modal-dt">Block Type:</div>
				<div class="lccl-frx-modal-dd">${isGroup ? `Connected in Group: <strong>${esc(grp.group_id)}</strong> (${grp.tables.length} tables)` : 'Single Table Block'}</div>
				<div class="lccl-frx-modal-dt">Blocked At:</div>
				<div class="lccl-frx-modal-dd">${tbl.blocked_at ? formatDateTime(tbl.blocked_at) : '—'}</div>
				<div class="lccl-frx-modal-dt">Blocked By:</div>
				<div class="lccl-frx-modal-dd">${esc(tbl.blocked_by_name || 'Admin')}</div>
			</div>
			${isGroup ? `
				<div style="margin-bottom:12px;">
					<label style="display:block;font-weight:600;font-size:12px;color:#6b7280;margin-bottom:6px;">All tables in this group (click to locate):</label>
					<div class="lccl-frx-seat-chips-wrap">
						${grp.tables.map( t => `<span class="lccl-frx-seat-chip lccl-frx-table-chip" data-table-id="${t.id}" style="${String(t.id)===String(tableId)?'background:#fee2e2;border-color:#fca5a5;color:#dc2626;font-weight:700;':''}">🏷️ ${esc(t.table_label)}</span>` ).join('')}
					</div>
				</div>
			` : ''}
			<div class="lccl-frx-modal-note-box">
				<strong>Attached Note / Reason:</strong><br>
				${tbl.block_note ? esc(tbl.block_note) : '<em>No note attached</em>'}
			</div>
		`;
		let footHtml = '';
		if ( isGroup ) {
			footHtml = `
				<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--sm" id="frx-btn-modal-close-tbl">Close</button>
				<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--sm" id="frx-btn-modal-resend-single-tbl">↩ Re-send Only This Table</button>
				<button type="button" class="lccl-frx-btn lccl-frx-btn--success-outline lccl-frx-btn--sm" id="frx-btn-modal-resend-all-grp-tbl">↩ Re-send Whole Group (${grp.tables.length} tables)</button>
			`;
		} else {
			footHtml = `
				<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--sm" id="frx-btn-modal-close-tbl">Close</button>
				<button type="button" class="lccl-frx-btn lccl-frx-btn--success-outline lccl-frx-btn--sm" id="frx-btn-modal-resend-single-tbl">↩ Re-send to Pool</button>
			`;
		}

		openModal( `Blocked Table: ${esc(tbl.table_label)}`, bodyHtml, footHtml );
		document.getElementById( 'frx-btn-modal-close-tbl' )?.addEventListener( 'click', closeModal );
		document.getElementById( 'frx-btn-modal-resend-single-tbl' )?.addEventListener( 'click', () => {
			closeModal();
			resendTableToPool( tbl.id );
		} );
		document.getElementById( 'frx-btn-modal-resend-all-grp-tbl' )?.addEventListener( 'click', () => {
			closeModal();
			resendTableGroup( grp.group_id );
		} );
	}

	// Action delegation for Blocked Table Groups list
	document.getElementById( 'lccl-frx-table-groups-list' )?.addEventListener( 'click', e => {
		const chip = e.target.closest( '.lccl-frx-table-chip' );
		if ( chip ) {
			const tid = parseInt( chip.dataset.tableId, 10 );
			const card = document.querySelector( `.lccl-map-card[data-table-id="${tid}"]` );
			if ( card ) {
				card.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				card.classList.add( 'is-highlighted' );
				setTimeout( () => card.classList.remove( 'is-highlighted' ), 2000 );
			}
			return;
		}

		const btn = e.target.closest( '[data-action]' );
		if ( ! btn ) return;
		const action = btn.dataset.action;
		const groupId = btn.dataset.groupId;

		if ( action === 'view-table-group' ) {
			viewTableGroupDetails( groupId );
		} else if ( action === 'resend-table-group' ) {
			resendTableGroup( groupId );
		}
	} );

	// Pagination delegation for Blocked Table Groups
	document.getElementById( 'lccl-frx-blocked-tables-pagination' )?.addEventListener( 'click', e => {
		const ev = getEvent( currentEventId );
		if ( ! ev ) return;
		const totalGroups = getBlockedTableGroups( ev ).length;
		const totalPages  = Math.max( 1, Math.ceil( totalGroups / BLOCKED_TABLES_PER_PAGE ) );

		const pageBtn = e.target.closest( '[data-page]' );
		if ( pageBtn ) {
			blockedTablesPage = parseInt( pageBtn.dataset.page, 10 );
			renderBlockedTableGroupsList( ev );
			return;
		}
		if ( e.target.closest( '#frx-tbl-page-prev' ) ) {
			if ( blockedTablesPage > 1 ) {
				blockedTablesPage--;
				renderBlockedTableGroupsList( ev );
			}
			return;
		}
		if ( e.target.closest( '#frx-tbl-page-next' ) ) {
			if ( blockedTablesPage < totalPages ) {
				blockedTablesPage++;
				renderBlockedTableGroupsList( ev );
			}
			return;
		}
	} );

	// Action delegation for Blocked Seat Groups list
	document.getElementById( 'lccl-frx-seat-groups-list' )?.addEventListener( 'click', e => {
		const chip = e.target.closest( '.lccl-frx-seat-chip' );
		if ( chip ) {
			const s = parseInt( chip.dataset.seat, 10 );
			const dot = document.querySelector( `.lccl-frx-seat-dot[data-seat="${s}"]` );
			if ( dot ) {
				dot.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				dot.classList.add( 'is-highlighted' );
				setTimeout( () => dot.classList.remove( 'is-highlighted' ), 2000 );
			}
			return;
		}

		const btn = e.target.closest( '[data-action]' );
		if ( ! btn ) return;
		const action = btn.dataset.action;
		const groupId = btn.dataset.groupId;

		if ( action === 'view-seat-group' ) {
			viewSeatGroupDetails( groupId );
		} else if ( action === 'resend-seat-group' ) {
			resendSeatGroup( groupId );
		}
	} );

	// Pagination delegation for Blocked Seat Groups
	document.getElementById( 'lccl-frx-seat-groups-pagination' )?.addEventListener( 'click', e => {
		const ev = getEvent( currentEventId );
		if ( ! ev ) return;
		const totalGroups = ( ev.blocked_seat_groups || [] ).length;
		const totalPages  = Math.max( 1, Math.ceil( totalGroups / BLOCKED_SEATS_PER_PAGE ) );

		const pageBtn = e.target.closest( '[data-page]' );
		if ( pageBtn ) {
			blockedSeatsPage = parseInt( pageBtn.dataset.page, 10 );
			renderBlockedSeatGroupsList( ev );
			return;
		}
		if ( e.target.closest( '#frx-seat-page-prev' ) ) {
			if ( blockedSeatsPage > 1 ) {
				blockedSeatsPage--;
				renderBlockedSeatGroupsList( ev );
			}
			return;
		}
		if ( e.target.closest( '#frx-seat-page-next' ) ) {
			if ( blockedSeatsPage < totalPages ) {
				blockedSeatsPage++;
				renderBlockedSeatGroupsList( ev );
			}
			return;
		}
	} );

	// Modal seat-chip, table-chip, and single table resend delegation
	document.getElementById( 'lccl-frx-modal-body' )?.addEventListener( 'click', e => {
		const seatChip = e.target.closest( '.lccl-frx-seat-chip:not(.lccl-frx-table-chip)' );
		if ( seatChip ) {
			const s = parseInt( seatChip.dataset.seat, 10 );
			closeModal();
			const dot = document.querySelector( `.lccl-frx-seat-dot[data-seat="${s}"]` );
			if ( dot ) {
				dot.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				dot.classList.add( 'is-highlighted' );
				setTimeout( () => dot.classList.remove( 'is-highlighted' ), 2000 );
			}
			return;
		}

		const tblChip = e.target.closest( '.lccl-frx-table-chip' );
		if ( tblChip ) {
			const tid = parseInt( tblChip.dataset.tableId, 10 );
			closeModal();
			const card = document.querySelector( `.lccl-map-card[data-table-id="${tid}"]` );
			if ( card ) {
				card.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				card.classList.add( 'is-highlighted' );
				setTimeout( () => card.classList.remove( 'is-highlighted' ), 2000 );
			}
			return;
		}

		const resendBtn = e.target.closest( '[data-modal-action="resend-single-table"]' );
		if ( resendBtn ) {
			const tid = parseInt( resendBtn.dataset.tableId, 10 );
			closeModal();
			resendTableToPool( tid );
			return;
		}
	} );

	// Jump to seat
	document.getElementById( 'frx-seat-jump-btn' )?.addEventListener( 'click', () => {
		const n   = parseInt( document.getElementById( 'frx-seat-jump' ).value, 10 );
		const dot = document.querySelector( `.lccl-frx-seat-dot[data-seat="${n}"]` );
		if ( ! dot ) { toast( `Seat #${n} not found`, 'warning' ); return; }
		dot.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		dot.classList.add( 'is-highlighted' );
		setTimeout( () => dot.classList.remove( 'is-highlighted' ), 2000 );
	} );

	// Clear all blocks
	document.getElementById( 'frx-seat-clear-blocks' )?.addEventListener( 'click', () => {
		if ( ! confirm( 'Clear all admin-blocked seats for this event? All seats will immediately return to the seat pool.' ) ) return;
		ajax( 'lccl_de_fr_clear_seat_blocks', { event_id: currentEventId } )
			.then( res => {
				if ( ! res.success ) { toast( res.data?.message || res.data || 'Error', 'error' ); return; }
				const ev = getEvent( currentEventId );
				if ( ev ) {
					ev.blocked_seats        = [];
					ev.blocked_seat_groups  = [];
					ev.blocked_seat_details = [];
				}
				selectedSeats.clear();
				lastSelectedSeat = null;
				updateSelectionBar();
				renderBlockedPanel();
				toast( 'All seat blocks cleared and returned to pool' );
			} ).catch( () => toast( 'Request failed', 'error' ) );
	} );

	/* ────────────────────────────────────────────────────────────
	   Event Bookings sub-panel
	──────────────────────────────────────────────────────────── */
	function renderEventBookings() {
		const tbody = document.getElementById( 'lccl-frx-evbk-tbody' );
		const countBadge = document.getElementById( 'frx-evbk-count' );
		if ( ! tbody ) return;

		const evBk = bookings.filter( b => parseInt( b.event_id, 10 ) === currentEventId );

		if ( countBadge ) {
			countBadge.textContent = evBk.length + ' bookings';
			countBadge.className   = 'lccl-frx-badge lccl-frx-badge--upcoming';
		}

		if ( ! evBk.length ) {
			tbody.innerHTML = `<tr><td colspan="8" class="lccl-frx-empty">No bookings for this event yet.</td></tr>`;
			return;
		}

		tbody.innerHTML = evBk.map( bk => `
			<tr>
				<td><code>${esc(bk.order_ref)}</code></td>
				<td>
					<strong>${esc(bk.attendee_name)}</strong><br>
					<small style="color:#888;">${esc(bk.attendee_email)}</small>
				</td>
				<td>${esc(ucfirst(bk.booking_type))}</td>
				<td>${seatTableInfo(bk)}</td>
				<td>${bk.notes ? `<span class="lccl-frx-note-chip" title="${esc(bk.notes)}">📝 ${esc(bk.notes.length > 25 ? bk.notes.slice(0, 25) + '…' : bk.notes)}</span>` : '<span style="color:#ccc;">—</span>'}</td>
				<td>LKR ${fmt(bk.amount_lkr)}</td>
				<td><span class="lccl-frx-badge lccl-frx-badge--${'paid'===bk.status?'paid':'pending'}">${esc(bk.status)}</span></td>
				<td><small>${formatDateTime(bk.created_at)}</small></td>
			</tr>
		` ).join('');
	}

	function seatTableInfo( bk ) {
		if ( bk.seat_numbers ) return `Seats: <strong>${esc(bk.seat_numbers)}</strong>`;
		if ( bk.table_ids    ) return `Tables: <strong>${esc(bk.table_ids)}</strong>`;
		return `Qty: <strong>${esc(bk.qty)}</strong>`;
	}

	/* ────────────────────────────────────────────────────────────
	   EDIT EVENT form submit
	──────────────────────────────────────────────────────────── */
	const editForm = document.getElementById( 'lccl-frx-edit-form' );
	if ( editForm ) {
		editForm.addEventListener( 'submit', e => {
			e.preventDefault();
			const data = formData( editForm );
			const btn  = editForm.querySelector( '[type=submit]' );
			btn.disabled = true;
			ajax( 'lccl_de_fundraiser_update_event', data )
				.then( res => {
					btn.disabled = false;
					if ( ! res.success ) { toast( res.data || 'Save failed', 'error' ); return; }
					// Merge updated fields into in-memory event
					const ev = getEvent( currentEventId );
					if ( ev ) Object.assign( ev, data, { id: currentEventId } );
					// Update sidebar label
					const listItem = document.getElementById( `lccl-frx-evitem-${currentEventId}` );
					if ( listItem ) {
						listItem.querySelector( '.lccl-frx-event-item__name' ).textContent = data.name;
					}
					renderEditorForEvent( currentEventId );
					toast( 'Event saved', 'success' );
				} )
				.catch( () => { btn.disabled = false; toast( 'Request failed', 'error' ); } );
		} );
	}

	/* ────────────────────────────────────────────────────────────
	   ADD TIER form
	──────────────────────────────────────────────────────────── */
	const addTierForm = document.getElementById( 'lccl-frx-add-tier-form' );
	if ( addTierForm ) {
		addTierForm.addEventListener( 'submit', e => {
			e.preventDefault();
			const data   = formData( addTierForm );
			data.event_id = currentEventId;
			const btn    = addTierForm.querySelector( '[type=submit]' );
			btn.disabled = true;
			ajax( 'lccl_de_fundraiser_add_table_type', data )
				.then( res => {
					btn.disabled = false;
					if ( ! res.success ) { toast( res.data || 'Error', 'error' ); return; }
					const ev = getEvent( currentEventId );
					if ( ev ) {
						ev.types = ev.types || [];
						ev.types.push( res.data.type );
					}
					renderTierList( getEvent( currentEventId ) );
					addTierForm.reset();
					toast( 'Tier added', 'success' );
				} )
				.catch( () => { btn.disabled = false; toast( 'Request failed', 'error' ); } );
		} );
	}

	// Tier delete — delegated
	document.getElementById( 'lccl-frx-tier-list' )?.addEventListener( 'click', e => {
		const btn = e.target.closest( '[data-action="delete-tier"]' );
		if ( ! btn ) return;
		if ( ! confirm( 'Remove this tier? This will also remove its tables.' ) ) return;
		const tierId = btn.dataset.tierId;
		btn.disabled = true;
		ajax( 'lccl_de_fundraiser_delete_table_type', { event_id: currentEventId, type_id: tierId } )
			.then( res => {
				if ( ! res.success ) { toast( res.data || 'Error', 'error' ); btn.disabled = false; return; }
				const ev = getEvent( currentEventId );
				if ( ev ) {
					ev.types  = ( ev.types  || [] ).filter( t => String(t.id) !== String(tierId) );
					ev.tables = ( ev.tables || [] ).filter( t => String(t.type_id) !== String(tierId) );
					recalcPoolStats( ev );
				}
				renderTierList( getEvent( currentEventId ) );
				toast( 'Tier removed', 'success' );
			} )
			.catch( () => { btn.disabled = false; toast( 'Request failed', 'error' ); } );
	} );

	/* ────────────────────────────────────────────────────────────
	   GENERATE INVENTORY form
	──────────────────────────────────────────────────────────── */
	const genForm = document.getElementById( 'lccl-frx-generate-form' );
	if ( genForm ) {
		genForm.addEventListener( 'submit', e => {
			e.preventDefault();
			const data    = formData( genForm );
			data.event_id = currentEventId;
			const btn     = genForm.querySelector( '[type=submit]' );
			btn.disabled  = true;
			ajax( 'lccl_de_fundraiser_generate_inventory', data )
				.then( res => {
					btn.disabled = false;
					if ( ! res.success ) { toast( res.data || 'Error', 'error' ); return; }
					const ev = getEvent( currentEventId );
					if ( ev && res.data.tables ) {
						ev.tables = ev.tables || [];
						ev.tables.push( ...res.data.tables );
						recalcPoolStats( ev );
					}
					renderTablesPanel();
					toast( `${res.data.count} tables generated`, 'success' );
				} )
				.catch( () => { btn.disabled = false; toast( 'Request failed', 'error' ); } );
		} );
	}

	/* ────────────────────────────────────────────────────────────
	   INVENTORY TABLE: move + block/unblock delegation
	──────────────────────────────────────────────────────────── */
	const invTableWrap = document.getElementById( 'lccl-frx-inv-table-wrap' );
	if ( invTableWrap ) {
		invTableWrap.addEventListener( 'click', e => {
			const btn = e.target.closest( '[data-action]' );
			if ( ! btn ) return;
			const action  = btn.dataset.action;
			const tableId = btn.dataset.tableId;
			btn.disabled  = true;

			if ( action === 'move-to-table' || action === 'move-to-seat' ) {
				const newMode = action === 'move-to-table' ? 'table' : 'seat';
				ajax( 'lccl_de_fr_move_table_pool', { event_id: currentEventId, table_id: tableId, mode: newMode } )
					.then( res => {
						btn.disabled = false;
						if ( ! res.success ) { toast( res.data || 'Error', 'error' ); return; }
						const ev = getEvent( currentEventId );
						if ( ev ) {
							const tbl = ev.tables.find( t => String(t.id) === String(tableId) );
							if ( tbl ) tbl.booking_mode = newMode;
							recalcPoolStats( ev );
						}
						renderTablesPanel();
						toast( `Table moved to ${newMode === 'table' ? 'Table' : 'Seat'} Pool`, 'success' );
					} )
					.catch( () => { btn.disabled = false; toast( 'Request failed', 'error' ); } );
			}

			if ( action === 'block-table' ) {
				const ev = getEvent( currentEventId );
				const tbl = ( ev?.tables || [] ).find( t => String(t.id) === String(tableId) );
				const label = tbl ? tbl.table_label : `Table #${tableId}`;
				const bodyHtml = `
					<p style="margin-bottom:12px;">You are about to block <strong>${esc(label)}</strong> (${tbl ? esc(tbl.type_name) + ', ' + tbl.capacity + ' seats' : ''}) from public booking.</p>
					<label for="frx-tbl-block-note" style="display:block;font-weight:600;font-size:12px;margin-bottom:4px;color:#374151;">Reason / Note for blocking (Optional):</label>
					<textarea id="frx-tbl-block-note" class="lccl-frx-modal-textarea" placeholder="e.g. Reserved for Chief Guest / VIP offline hold..."></textarea>
				`;
				const footHtml = `
					<button type="button" class="lccl-frx-btn lccl-frx-btn--outline lccl-frx-btn--sm" id="frx-btn-modal-cancel-tbl">Cancel</button>
					<button type="button" class="lccl-frx-btn lccl-frx-btn--danger lccl-frx-btn--sm" id="frx-btn-modal-confirm-block-tbl">🔒 Confirm Block Table</button>
				`;
				openModal( `Block Table: ${esc(label)}`, bodyHtml, footHtml );
				document.getElementById( 'frx-btn-modal-cancel-tbl' )?.addEventListener( 'click', () => {
					closeModal();
					btn.disabled = false;
				} );
				document.getElementById( 'frx-btn-modal-confirm-block-tbl' )?.addEventListener( 'click', () => {
					const note = document.getElementById( 'frx-tbl-block-note' )?.value || '';
					closeModal();
					ajax( 'lccl_de_fr_toggle_table_block', { event_id: currentEventId, table_id: tableId, blocked: 1, note } )
						.then( res => {
							btn.disabled = false;
							if ( ! res.success ) { toast( res.data?.message || res.data || 'Error', 'error' ); return; }
							if ( ev && tbl ) {
								tbl.is_blocked      = 1;
								tbl.block_note      = res.data.block_note || note;
								tbl.blocked_at      = res.data.blocked_at || '';
								tbl.blocked_by      = res.data.blocked_by || 0;
								tbl.blocked_by_name = res.data.blocked_by_name || '';
								recalcPoolStats( ev );
							}
							renderTablesPanel();
							toast( `Table "${label}" blocked`, 'success' );
						} )
						.catch( () => { btn.disabled = false; toast( 'Request failed', 'error' ); } );
				} );
				return;
			}

			if ( action === 'unblock-table' ) {
				ajax( 'lccl_de_fr_toggle_table_block', { event_id: currentEventId, table_id: tableId, blocked: 0 } )
					.then( res => {
						btn.disabled = false;
						if ( ! res.success ) { toast( res.data?.message || res.data || 'Error', 'error' ); return; }
						const ev = getEvent( currentEventId );
						if ( ev ) {
							const tbl = ev.tables.find( t => String(t.id) === String(tableId) );
							if ( tbl ) {
								tbl.is_blocked      = 0;
								tbl.block_note      = '';
								tbl.blocked_at      = '';
								tbl.blocked_by      = null;
								tbl.blocked_by_name = '';
							}
							recalcPoolStats( ev );
						}
						renderTablesPanel();
						toast( 'Table unblocked and returned to pool', 'success' );
					} )
					.catch( () => { btn.disabled = false; toast( 'Request failed', 'error' ); } );
			}
		} );
	}

	/* ────────────────────────────────────────────────────────────
	   TOGGLE PUBLIC button
	──────────────────────────────────────────────────────────── */
	document.getElementById( 'frx-btn-toggle-public' )?.addEventListener( 'click', () => {
		const ev = getEvent( currentEventId );
		if ( ! ev ) return;
		ajax( 'lccl_de_fundraiser_toggle_public', { event_id: currentEventId } )
			.then( res => {
				if ( ! res.success ) { toast( res.data || 'Error', 'error' ); return; }
				ev.public_booking = +ev.public_booking ? 0 : 1;
				renderEditorForEvent( currentEventId );
				// Update sidebar live dot
				const item = document.getElementById( `lccl-frx-evitem-${currentEventId}` );
				if ( item ) {
					let dot = item.querySelector( '.lccl-frx-dot' );
					if ( ev.public_booking ) {
						if ( ! dot ) {
							dot = document.createElement( 'span' );
							dot.className = 'lccl-frx-dot lccl-frx-dot--live';
							item.querySelector( '.lccl-frx-event-item__meta' ).appendChild( dot );
						}
					} else {
						dot?.remove();
					}
				}
				toast( ev.public_booking ? 'Booking is now LIVE' : 'Booking taken offline', 'success' );
			} )
			.catch( () => toast( 'Request failed', 'error' ) );
	} );

	/* ────────────────────────────────────────────────────────────
	   DELETE EVENT button
	──────────────────────────────────────────────────────────── */
	document.getElementById( 'frx-btn-delete-event' )?.addEventListener( 'click', () => {
		const ev = getEvent( currentEventId );
		if ( ! confirm( `Delete "${ev?.name}"? This cannot be undone.` ) ) return;
		ajax( 'lccl_de_fundraiser_delete_event', { event_id: currentEventId } )
			.then( res => {
				if ( ! res.success ) { toast( res.data || 'Error', 'error' ); return; }
				events   = events.filter( e => parseInt(e.id,10) !== currentEventId );
				bookings = bookings.filter( b => parseInt(b.event_id,10) !== currentEventId );
				const item = document.getElementById( `lccl-frx-evitem-${currentEventId}` );
				item?.remove();
				currentEventId = events.length ? parseInt( events[0].id, 10 ) : null;
				if ( currentEventId ) {
					document.querySelector('.lccl-frx-event-item')?.classList.add('is-selected');
					renderEditorForEvent( currentEventId );
				}
				toast( 'Event deleted', 'success' );
			} )
			.catch( () => toast( 'Request failed', 'error' ) );
	} );

	/* ────────────────────────────────────────────────────────────
	   CREATE EVENT form
	──────────────────────────────────────────────────────────── */
	const createForm = document.getElementById( 'lccl-frx-create-form' );
	if ( createForm ) {
		createForm.addEventListener( 'submit', e => {
			e.preventDefault();
			const data = formData( createForm );
			const btn  = createForm.querySelector( '[type=submit]' );
			btn.disabled = true;
			ajax( 'lccl_de_fundraiser_create_event', data )
				.then( res => {
					btn.disabled = false;
					if ( ! res.success ) { toast( res.data || 'Error', 'error' ); return; }
					// Full page reload to get fresh sidebar list with the new event
					toast( 'Event created — reloading…', 'success' );
					setTimeout( () => window.location.reload(), 1000 );
				} )
				.catch( () => { btn.disabled = false; toast( 'Request failed', 'error' ); } );
		} );
	}

	/* ────────────────────────────────────────────────────────────
	   Recalc in-memory pool stats (client-side approximation)
	──────────────────────────────────────────────────────────── */
	function recalcPoolStats( ev ) {
		const tables = ev.tables || [];
		const stats  = {
			table_pool_free: 0, table_pool_booked: 0, table_pool_blocked: 0,
			seat_pool_free:  0, seat_pool_booked:  0, seat_pool_blocked:  0,
		};
		tables.forEach( t => {
			const mode    = t.booking_mode || 'seat';
			const blocked = +t.is_blocked;
			const booked  = +t.is_booked;
			if ( mode === 'table' ) {
				if      ( blocked ) stats.table_pool_blocked++;
				else if ( booked  ) stats.table_pool_booked++;
				else                stats.table_pool_free++;
			} else {
				const cap = parseInt( t.capacity, 10 ) || 0;
				if      ( blocked ) stats.seat_pool_blocked += cap;
				else if ( booked  ) stats.seat_pool_booked  += cap;
				else                stats.seat_pool_free    += cap;
			}
		} );
		ev.pool_stats = stats;
	}

	/* ────────────────────────────────────────────────────────────
	   Utilities
	──────────────────────────────────────────────────────────── */
	function formData( form ) {
		const out = {};
		new FormData( form ).forEach( ( v, k ) => out[k] = v );
		return out;
	}

	function esc( str ) {
		if ( str === null || str === undefined ) return '';
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;'  )
			.replace( />/g, '&gt;'  )
			.replace( /"/g, '&quot;' );
	}

	function fmt( num ) {
		return Number( num ).toLocaleString( 'en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
	}

	function ucfirst( s ) {
		if ( ! s ) return '';
		return s.charAt(0).toUpperCase() + s.slice(1);
	}

	function formatDate( d ) {
		if ( ! d ) return '';
		const dt = new Date( d + 'T00:00:00' );
		return dt.toLocaleDateString( 'en-LK', { year: 'numeric', month: 'short', day: 'numeric' } );
	}

	function formatDateTime( d ) {
		if ( ! d ) return '';
		const dt = new Date( d.replace( ' ', 'T' ) );
		return dt.toLocaleDateString( 'en-LK', { year: 'numeric', month: 'short', day: 'numeric' } )
			+ ' ' + dt.toLocaleTimeString( 'en-LK', { hour: '2-digit', minute: '2-digit' } );
	}

	/* ────────────────────────────────────────────────────────────
	   Boot
	──────────────────────────────────────────────────────────── */
	if ( currentEventId ) {
		renderEditorForEvent( currentEventId );
	}

} )();