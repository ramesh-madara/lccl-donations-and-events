/**
 * Fundraiser Admin UI logic.
 */
jQuery(document).ready(function($) {
	if (!$('.lccl-fr-dashboard').length) return;

	var $navBtns = $('.lccl-fr-nav__btn');
	var $panels = $('.lccl-fr-panel');
	var $events = $('.lccl-fr-event-item');
	var $msg = $('#lccl-fr-msg');
	var currentEventId = null;
	var currentTab = 'overview';

	function updateUrl() {
		var url = new URL(window.location.href);
		if (currentTab) url.searchParams.set('fr_tab', currentTab);
		if (currentEventId) url.searchParams.set('fr_event', currentEventId);
		window.history.replaceState({}, '', url);
	}

	// Tabs
	$navBtns.on('click', function(e) {
		if (e) e.preventDefault();
		$navBtns.removeClass('is-active');
		$(this).addClass('is-active');
		$panels.removeClass('is-active');
		var target = $(this).data('fr-target');
		$('#lccl-fr-panel-' + target).addClass('is-active');
		currentTab = target;
		updateUrl();
	});

	function showMsg(text, type) {
		$msg.html('<div class="lccl-fr-flash lccl-fr-flash--' + type + '">' + text + '</div>');
		setTimeout(function() { $msg.empty(); }, 5000);
	}
	
	function showLoader() {
		$msg.html('<div style="text-align:center; padding:10px;"><span class="spinner is-active" style="float:none;"></span> Processing...</div>');
	}

	// Select Event
	$events.on('click', function(e) {
		if (e) e.preventDefault();
		$events.removeClass('is-selected');
		$(this).addClass('is-selected');
		
		var data = $(this).data('fr-event');
		currentEventId = data.id;
		updateUrl();

		// Populate Editor
		$('#fr-edit-title').text(data.name);
		var meta = data.event_date ? new Date(data.event_date).toDateString() : 'TBA';
		if (data.venue) meta += ' - ' + data.venue;
		$('#fr-edit-meta').text(meta);
		
		$('#fr-edit-id').val(data.id);
		$('#fr-edit-status').val(data.status);
		$('#fr-edit-name').val(data.name);
		$('#fr-edit-date').val(data.event_date);
		$('#fr-edit-time').val(data.event_time);
		$('#fr-edit-price').val(data.ticket_price);
		$('#fr-edit-status-select').val(data.status);
		$('#fr-edit-venue').val(data.venue);
		$('#fr-edit-desc').val(data.description);
		
		var isPub = parseInt(data.public_booking) === 1;
		$('#fr-edit-status-badge').text(isPub ? 'PUBLIC BOOKING ACTIVE' : 'PRIVATE')
			.removeClass('lccl-fr-badge--public lccl-fr-badge--draft')
			.addClass(isPub ? 'lccl-fr-badge--public' : 'lccl-fr-badge--draft');
			
		$('#fr-btn-toggle-public').text(isPub ? 'Disable Public Booking' : 'Enable Public Booking');

		// Set type form event id
		$('#lccl-fr-add-type-form').find('input[name="event_id"]').remove();
		$('#lccl-fr-add-type-form').append('<input type="hidden" name="event_id" value="' + data.id + '">');
		$('#lccl-fr-generate-form').find('input[name="event_id"]').remove();
		$('#lccl-fr-generate-form').append('<input type="hidden" name="event_id" value="' + data.id + '">');

		// Populate Table Types
		var $tl = $('#lccl-fr-types-list');
		var $sel = $('#fr-gen-type');
		$tl.empty();
		$sel.empty();

		if (!data.types || data.types.length === 0) {
			$tl.html('<p class="lccl-fr-empty" style="padding: 10px;">No table tiers defined.</p>');
		} else {
			data.types.forEach(function(type) {
				// Count generated tables
				var generated = 0;
				var booked = 0;
				if (data.tables) {
					data.tables.forEach(function(t) {
						if (parseInt(t.type_id) === parseInt(type.id)) {
							generated++;
							if (parseInt(t.is_booked) === 1) booked++;
						}
					});
				}

				var row = '<div class="lccl-fr-type-row" style="background:#fff; border:1px solid #eee; padding:12px; margin-bottom:8px; border-radius:4px;">' +
					'<div><strong style="display:block; font-size:14px; margin-bottom:4px;">' + type.name + '</strong><span class="lccl-fr-badge">' + type.capacity + ' Seats</span></div>' +
					'<div><strong style="color:#28723a;">LKR ' + parseFloat(type.price).toLocaleString() + '</strong></div>' +
					'<div><span style="font-size:12px; color:#666;">Generated: <strong>' + generated + '</strong></span></div>' +
					'<div>' +
						'<div class="lccl-fr-inventory-bar">' +
							'<span style="width: ' + (generated > 0 ? (booked/generated)*100 : 0) + '%"></span>' +
						'</div>' +
						'<div style="font-size:10px; color:#888; text-align:center; margin-top:3px;">' + booked + ' Booked</div>' +
					'</div>' +
					'<div>' +
						'<button type="button" class="lccl-fr-btn lccl-fr-btn--danger lccl-fr-btn--sm js-del-type" data-id="' + type.id + '">Delete</button> ' +
						(generated > 0 ? '<button type="button" class="lccl-fr-btn lccl-fr-btn--secondary lccl-fr-btn--sm js-del-inv" data-id="' + type.id + '">Clear Inv.</button>' : '') +
					'</div>' +
				'</div>';
				$tl.append(row);
				$sel.append('<option value="' + type.id + '">' + type.name + '</option>');
			});
		}

		// Populate Bookings for this event
		var $tb = $('#lccl-fr-bookings-tbody');
		$tb.empty();
		var hasBk = false;
		if (window.lcclFrBookings) {
			window.lcclFrBookings.forEach(function(bk) {
				if (parseInt(bk.event_id) === currentEventId) {
					hasBk = true;
					var tr = '<tr>' +
						'<td><strong>' + bk.order_ref + '</strong></td>' +
						'<td>' + bk.attendee_name + '</td>' +
						'<td>' + bk.attendee_email + '<br><small>' + bk.attendee_phone + '</small></td>' +
						'<td>' + (bk.booking_type === 'individual' ? bk.qty + ' Seats' : 'Tables') + '</td>' +
						'<td>LKR ' + parseFloat(bk.amount_lkr).toLocaleString() + '</td>' +
						'<td>' + bk.status + '</td>' +
						'<td>' + bk.created_at + '</td>' +
					'</tr>';
					$tb.append(tr);
				}
			});
		}
		if (!hasBk) {
			$tb.html('<tr><td colspan="7">No bookings found for this event.</td></tr>');
		}

		$('#lccl-fr-editor').show();
	});

	// Create Event
	$('#lccl-fr-create-form').on('submit', function(e) {
		e.preventDefault();
		showLoader();
		var d = $(this).serialize() + '&action=lccl_de_fundraiser_create_event&nonce=' + lcclFundraiser.nonce;
		$.post(lcclFundraiser.ajaxUrl, d, function(res) {
			if (res.success) {
				showMsg('Event created!', 'success');
				currentEventId = res.data.id;
				updateUrl();
				setTimeout(function(){ location.reload(); }, 1000);
			} else {
				showMsg(res.data.message || 'Error', 'error');
			}
		});
	});

	// Update Event
	$('#lccl-fr-update-form').on('submit', function(e) {
		e.preventDefault();
		showLoader();
		var d = $(this).serialize() + '&action=lccl_de_fundraiser_update_event&nonce=' + lcclFundraiser.nonce;
		$.post(lcclFundraiser.ajaxUrl, d, function(res) {
			if (res.success) {
				showMsg('Event updated!', 'success');
				setTimeout(function(){ location.reload(); }, 1000);
			} else {
				showMsg(res.data.message || 'Error', 'error');
			}
		});
	});

	// Toggle Public
	$('#fr-btn-toggle-public').on('click', function() {
		if (!currentEventId) return;
		showLoader();
		var $badge = $('#fr-edit-status-badge');
		var isPub = $badge.hasClass('lccl-fr-badge--public');
		$.post(lcclFundraiser.ajaxUrl, {
			action: 'lccl_de_fundraiser_toggle_public',
			nonce: lcclFundraiser.nonce,
			event_id: currentEventId,
			is_public: isPub ? 0 : 1
		}, function(res) {
			if (res.success) {
				showMsg('Status updated.', 'success');
				setTimeout(function(){ location.reload(); }, 500);
			}
		});
	});

	// Add Type
	$('#lccl-fr-add-type-form').on('submit', function(e) {
		e.preventDefault();
		showLoader();
		var d = $(this).serialize() + '&action=lccl_de_fundraiser_add_table_type&nonce=' + lcclFundraiser.nonce;
		$.post(lcclFundraiser.ajaxUrl, d, function(res) {
			if (res.success) {
				showMsg('Table tier added.', 'success');
				setTimeout(function(){ location.reload(); }, 500);
			}
		});
	});

	// Generate Inv
	$('#lccl-fr-generate-form').on('submit', function(e) {
		e.preventDefault();
		showLoader();
		var d = $(this).serialize() + '&action=lccl_de_fundraiser_generate_inventory&nonce=' + lcclFundraiser.nonce;
		$.post(lcclFundraiser.ajaxUrl, d, function(res) {
			if (res.success) {
				showMsg(res.data.generated + ' tables generated.', 'success');
				setTimeout(function(){ location.reload(); }, 1000);
			}
		});
	});

	// Del Type
	$(document).on('click', '.js-del-type', function() {
		if (!confirm('Are you sure? This deletes the tier and all its tables.')) return;
		showLoader();
		$.post(lcclFundraiser.ajaxUrl, {
			action: 'lccl_de_fundraiser_delete_table_type',
			nonce: lcclFundraiser.nonce,
			type_id: $(this).data('id')
		}, function(res) {
			if (res.success) {
				showMsg('Tier deleted.', 'success');
				setTimeout(function(){ location.reload(); }, 500);
			}
		});
	});

	// Clear Inv
	$(document).on('click', '.js-del-inv', function() {
		if (!confirm('Are you sure? This deletes all tables for this tier.')) return;
		showLoader();
		$.post(lcclFundraiser.ajaxUrl, {
			action: 'lccl_de_fundraiser_delete_inventory',
			nonce: lcclFundraiser.nonce,
			event_id: currentEventId,
			type_id: $(this).data('id')
		}, function(res) {
			if (res.success) {
				showMsg('Inventory cleared.', 'success');
				setTimeout(function(){ location.reload(); }, 500);
			}
		});
	});

	// Init State from URL
	var urlParams = new URLSearchParams(window.location.search);
	if (urlParams.has('fr_tab')) {
		var t = urlParams.get('fr_tab');
		$navBtns.filter('[data-fr-target="' + t + '"]').trigger('click');
	} else {
		$navBtns.first().trigger('click');
	}

	if (urlParams.has('fr_event')) {
		var eid = urlParams.get('fr_event');
		$events.filter(function(){ return $(this).data('fr-event').id == eid; }).trigger('click');
	} else {
		$events.first().trigger('click');
	}

});