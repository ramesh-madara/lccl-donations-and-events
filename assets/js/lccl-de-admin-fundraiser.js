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
		Object.entries( data ).forEach( ( [ k, v ] ) => body.set( k, v ) );
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
		const wrap = document.getElementById( 'lccl-frx-toast' );
		const el   = document.createElement( 'div' );
		el.className = `lccl-frx-toast__msg is-${type}`;
		el.textContent = msg;
		wrap.appendChild( el );
		setTimeout( () => el.classList.add( 'is-fading' ), 2800 );
		setTimeout( () => el.remove(), 3200 );
	}

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
		if ( name === 'tables'     ) renderTablesPanel();
		if ( name === 'seatgrid'   ) renderSeatGrid();
		if ( name === 'evbookings' ) renderEventBookings();
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
	   Seat Block Grid
	──────────────────────────────────────────────────────────── */
	function renderSeatGrid() {
		const ev = getEvent( currentEventId );
		if ( ! ev ) return;

		const grid  = document.getElementById( 'lccl-frx-seat-grid' );
		const empty = document.getElementById( 'lccl-frx-seat-empty' );
		if ( ! grid ) return;

		// Collect all seat-pool tables
		const seatTables = ( ev.tables || [] ).filter( t => t.booking_mode === 'seat' || ! t.booking_mode );
		const totalCap   = seatTables.reduce( ( sum, t ) => sum + parseInt( t.capacity, 10 ), 0 );

		if ( ! totalCap ) {
			grid.innerHTML   = '';
			empty.style.display = '';
			updateSeatStats( 0, 0, 0, 0 );
			return;
		}
		empty.style.display = 'none';

		const blockedSet  = new Set( ( ev.blocked_seats || [] ).map( Number ) );
		const bookedSet   = new Set( ( ev.booked_seats  || [] ).map( Number ) );

		let nAvail = 0, nBooked = 0, nBlocked = 0;

		const dots = [];
		for ( let i = 1; i <= totalCap; i++ ) {
			let state;
			if      ( bookedSet.has( i ) )  { state = 'booked';  nBooked++;  }
			else if ( blockedSet.has( i ) )  { state = 'blocked'; nBlocked++; }
			else                             { state = 'avail';   nAvail++;   }

			dots.push(
				`<div class="lccl-frx-seat-dot lccl-frx-seat-dot--${state}${state==='booked'?' is-booked':''}"
					data-seat="${i}" data-state="${state}" title="Seat #${i}">${i}</div>`
			);
		}

		grid.innerHTML = dots.join('');
		updateSeatStats( nAvail, nBooked, nBlocked, totalCap );
	}

	function updateSeatStats( avail, booked, blocked, total ) {
		const s = n => n === undefined ? '—' : n;
		document.getElementById( 'frx-ss-avail'   ).textContent = s( avail );
		document.getElementById( 'frx-ss-booked'  ).textContent = s( booked );
		document.getElementById( 'frx-ss-blocked' ).textContent = s( blocked );
		document.getElementById( 'frx-ss-total'   ).textContent = s( total );
	}

	// Seat grid click — toggle block
	const seatGrid = document.getElementById( 'lccl-frx-seat-grid' );
	if ( seatGrid ) {
		seatGrid.addEventListener( 'click', e => {
			const dot = e.target.closest( '.lccl-frx-seat-dot' );
			if ( ! dot || dot.classList.contains( 'is-booked' ) ) return;
			const seatNum = parseInt( dot.dataset.seat, 10 );
			const curState = dot.dataset.state;
			const newAction = curState === 'blocked' ? 'unblock' : 'block';
			toggleSeatBlock( seatNum, newAction );
		} );
	}

	function toggleSeatBlock( seatNum, action ) {
		ajax( 'lccl_de_fr_toggle_seat_block', {
			event_id : currentEventId,
			seat_num : seatNum,
			action_type: action,
		} ).then( res => {
			if ( ! res.success ) { toast( res.data || 'Error', 'error' ); return; }
			// Update in-memory event
			const ev = getEvent( currentEventId );
			if ( ! ev ) return;
			ev.blocked_seats = ev.blocked_seats || [];
			if ( action === 'block' ) {
				if ( ! ev.blocked_seats.includes( seatNum ) ) ev.blocked_seats.push( seatNum );
			} else {
				ev.blocked_seats = ev.blocked_seats.filter( n => n !== seatNum );
			}
			renderSeatGrid();
			toast( action === 'block' ? `Seat #${seatNum} blocked` : `Seat #${seatNum} unblocked` );
		} ).catch( () => toast( 'Request failed', 'error' ) );
	}

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
		if ( ! confirm( 'Clear all admin-blocked seats for this event?' ) ) return;
		ajax( 'lccl_de_fr_clear_seat_blocks', { event_id: currentEventId } )
			.then( res => {
				if ( ! res.success ) { toast( res.data || 'Error', 'error' ); return; }
				const ev = getEvent( currentEventId );
				if ( ev ) ev.blocked_seats = [];
				renderSeatGrid();
				toast( 'All seat blocks cleared' );
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
			tbody.innerHTML = `<tr><td colspan="7" class="lccl-frx-empty">No bookings for this event yet.</td></tr>`;
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

			if ( action === 'block-table' || action === 'unblock-table' ) {
				const blocked = action === 'block-table' ? 1 : 0;
				ajax( 'lccl_de_fr_toggle_table_block', { event_id: currentEventId, table_id: tableId, blocked } )
					.then( res => {
						btn.disabled = false;
						if ( ! res.success ) { toast( res.data || 'Error', 'error' ); return; }
						const ev = getEvent( currentEventId );
						if ( ev ) {
							const tbl = ev.tables.find( t => String(t.id) === String(tableId) );
							if ( tbl ) tbl.is_blocked = blocked;
							recalcPoolStats( ev );
						}
						renderTablesPanel();
						toast( blocked ? 'Table blocked' : 'Table unblocked', 'success' );
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