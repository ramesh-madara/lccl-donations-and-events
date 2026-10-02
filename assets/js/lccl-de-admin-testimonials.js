/**
 * Admin Testimonials JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		
		// Setup media uploader
		let mediaFrame;

		$(document).on('click', '#lccl-t-upload-btn', function(e) {
			e.preventDefault();
			
			if (mediaFrame) {
				mediaFrame.open();
				return;
			}
			
			mediaFrame = wp.media({
				title: lcclDePrograms.i18n ? lcclDePrograms.i18n.selectPhoto : 'Select Photo',
				button: {
					text: lcclDePrograms.i18n ? lcclDePrograms.i18n.usePhoto : 'Use this Photo'
				},
				multiple: false
			});
			
			mediaFrame.on('select', function() {
				const attachment = mediaFrame.state().get('selection').first().toJSON();
				$('#lccl-t-photo-id').val(attachment.id);
				$('#lccl-t-photo-url').val(attachment.url);
				
				$('#lccl-t-photo-preview').html('<img src="' + attachment.url + '" alt="">');
				$('#lccl-t-remove-photo-btn').show();
			});
			
			mediaFrame.open();
		});

		$(document).on('click', '#lccl-t-remove-photo-btn', function(e) {
			e.preventDefault();
			$('#lccl-t-photo-id').val('');
			$('#lccl-t-photo-url').val('');
			$('#lccl-t-photo-preview').html('<div class="lccl-de-photo-placeholder"><span class="dashicons dashicons-format-image"></span></div>');
			$(this).hide();
		});

		// Modal Handling
		const $modal = $('#lccl-de-testimonial-modal');
		const $form = $('#lccl-de-testimonial-form');

		function openModal(title, data = null) {
			$('#lccl-de-modal-title').text(title);
			$form[0].reset();
			
			if (data) {
				$('#lccl-t-id').val(data.id);
				$('#lccl-t-name').val(data.name);
				$('#lccl-t-role').val(data.role);
				
				// Handle date splitting
				const dateStr = data.date || '';
				const dateParts = dateStr.split(' ');
				$('#lccl-t-date-day').val('');
				$('#lccl-t-date-month').val('');
				$('#lccl-t-date-year').val('');
				
				if (dateParts.length === 3) {
					$('#lccl-t-date-day').val(dateParts[0]);
					$('#lccl-t-date-month').val(dateParts[1]);
					$('#lccl-t-date-year').val(dateParts[2]);
				} else if (dateParts.length === 2) {
					$('#lccl-t-date-month').val(dateParts[0]);
					$('#lccl-t-date-year').val(dateParts[1]);
				} else if (dateParts.length === 1 && dateParts[0] !== '') {
					$('#lccl-t-date-year').val(dateParts[0]);
				}
				$('#lccl-t-date').val(dateStr);

				$('#lccl-t-quote').val(data.quote);
				
				if (data.photo_url) {
					$('#lccl-t-photo-id').val(data.photo_id);
					$('#lccl-t-photo-url').val(data.photo_url);
					$('#lccl-t-photo-preview').html('<img src="' + data.photo_url + '" alt="">');
					$('#lccl-t-remove-photo-btn').show();
				} else {
					$('#lccl-t-photo-id').val('');
					$('#lccl-t-photo-url').val('');
					$('#lccl-t-photo-preview').html('<div class="lccl-de-photo-placeholder"><span class="dashicons dashicons-format-image"></span></div>');
					$('#lccl-t-remove-photo-btn').hide();
				}
			} else {
				$('#lccl-t-id').val('');
				$('#lccl-t-photo-id').val('');
				$('#lccl-t-photo-url').val('');
				$('#lccl-t-photo-preview').html('<div class="lccl-de-photo-placeholder"><span class="dashicons dashicons-format-image"></span></div>');
				$('#lccl-t-remove-photo-btn').hide();
			}
			
			$modal.fadeIn(200);
		}

		function closeModal() {
			$modal.fadeOut(200);
		}

		$(document).on('click', '#lccl-de-add-testimonial', function(e) {
			e.preventDefault();
			openModal('Add Testimonial');
		});

		$(document).on('click', '.lccl-de-modal-close, #lccl-de-modal-cancel', function(e) {
			e.preventDefault();
			closeModal();
		});

		// Edit button
		$(document).on('click', '.lccl-de-edit-btn', function(e) {
			e.preventDefault();
			const $card = $(this).closest('.lccl-de-t-card');
			const rawData = $card.data('raw');
			openModal('Edit Testimonial', rawData);
		});

		// Save Form
		$(document).on('click', '#lccl-de-modal-save', function(e) {
			e.preventDefault();
			if (!$form[0].checkValidity()) {
				$form[0].reportValidity();
				return;
			}
			
			const $btn = $(this);
			$btn.prop('disabled', true);
			$('#lccl-de-modal-spinner').addClass('is-active');

			// Assemble Date
			const d = $('#lccl-t-date-day').val();
			const m = $('#lccl-t-date-month').val();
			const y = $('#lccl-t-date-year').val();
			
			if ((d || m || y) && (!m || !y)) {
				alert(lcclDePrograms.i18n ? (lcclDePrograms.i18n.dateError || 'Minimum requirement for date is Month and Year.') : 'Minimum requirement for date is Month and Year.');
				$btn.prop('disabled', false);
				$('#lccl-de-modal-spinner').removeClass('is-active');
				return;
			}

			let dateStr = '';
			if (d && m && y) {
				dateStr = d + ' ' + m + ' ' + y;
			} else if (m && y) {
				dateStr = m + ' ' + y;
			}
			$('#lccl-t-date').val(dateStr);

			const data = {
				action: 'lccl_de_testimonial_save',
				nonce: lcclDePrograms.nonce_save || lcclDePrograms.nonce,
				id: $('#lccl-t-id').val(),
				name: $('#lccl-t-name').val(),
				role: $('#lccl-t-role').val(),
				date: dateStr,
				quote: $('#lccl-t-quote').val(),
				photo_id: $('#lccl-t-photo-id').val(),
				photo_url: $('#lccl-t-photo-url').val()
			};

			$.post(lcclDePrograms.ajaxUrl, data, function(response) {
				$btn.prop('disabled', false);
				$('#lccl-de-modal-spinner').removeClass('is-active');
				
				if (response.success) {
					closeModal();
					if (data.id) {
						// Update existing
						$('.lccl-de-t-card[data-id="' + data.id + '"]').replaceWith(response.data.html);
					} else {
						// Add new (always inactive by default)
						$('#lccl-de-inactive-list').prepend(response.data.html);
					}
					updateCounts();
				} else {
					alert(response.data.message || 'Error saving testimonial.');
				}
			}).fail(function() {
				$btn.prop('disabled', false);
				$('#lccl-de-modal-spinner').removeClass('is-active');
				alert('Network error.');
			});
		});

		// Delete
		$(document).on('click', '.lccl-de-delete-btn', function(e) {
			e.preventDefault();
			if (!confirm('Are you sure you want to delete this testimonial?')) {
				return;
			}

			const $card = $(this).closest('.lccl-de-t-card');
			const id = $card.data('id');
			
			$card.css('opacity', '0.5');

			$.post(lcclDePrograms.ajaxUrl, {
				action: 'lccl_de_testimonial_delete',
				nonce: lcclDePrograms.nonce_delete || lcclDePrograms.nonce,
				id: id
			}, function(response) {
				if (response.success) {
					$card.slideUp(300, function() {
						$(this).remove();
						updateCounts();
					});
				} else {
					$card.css('opacity', '1');
					alert(response.data.message || 'Error deleting testimonial.');
				}
			});
		});

		// Toggle Active Status
		$(document).on('change', '.lccl-de-toggle-btn', function() {
			const $checkbox = $(this);
			const $card = $checkbox.closest('.lccl-de-t-card');
			const id = $card.data('id');
			const isActive = $checkbox.prop('checked') ? 1 : 0;
			
			$card.addClass('is-toggling');

			$.post(lcclDePrograms.ajaxUrl, {
				action: 'lccl_de_testimonial_toggle',
				nonce: lcclDePrograms.nonce_toggle || lcclDePrograms.nonce,
				id: id,
				is_active: isActive
			}, function(response) {
				if (response.success) {
					setTimeout(function() {
						if (isActive) {
							$card.prependTo('#lccl-de-active-list');
							$card.attr('draggable', 'true');
							
							// Add drag handle if not exists
							if ($card.find('.lccl-de-t-drag-handle').length === 0) {
								$card.prepend('<div class="lccl-de-t-drag-handle" title="Drag to reorder"><span class="dashicons dashicons-menu"></span></div>');
							}
						} else {
							$card.prependTo('#lccl-de-inactive-list');
							$card.removeAttr('draggable');
							$card.find('.lccl-de-t-drag-handle').remove();
						}
						
						// Update raw data state
						const raw = $card.data('raw');
						raw.is_active = isActive;
						$card.data('raw', raw);
						
						$card.removeClass('is-toggling');
						updateCounts();
						
						if (isActive) {
							saveOrder();
						}
					}, 300);
				} else {
					$checkbox.prop('checked', !isActive);
					$card.removeClass('is-toggling');
					alert(response.data.message || 'Error updating status.');
				}
			}).fail(function() {
				$checkbox.prop('checked', !isActive);
				$card.removeClass('is-toggling');
			});
		});

		// Drag and Drop Ordering using HTML5 API
		let draggedCard = null;

		$(document).on('dragstart', '.lccl-de-t-card[draggable="true"]', function(e) {
			draggedCard = this;
			$(this).addClass('is-dragging');
			e.originalEvent.dataTransfer.effectAllowed = 'move';
			// Firefox requires this to make it draggable
			e.originalEvent.dataTransfer.setData('text/html', this.innerHTML);
		});

		$(document).on('dragover', '.lccl-de-t-card[draggable="true"]', function(e) {
			e.preventDefault();
			e.originalEvent.dataTransfer.dropEffect = 'move';
			
			if (this !== draggedCard) {
				const rect = this.getBoundingClientRect();
				const midPoint = rect.top + rect.height / 2;
				if (e.originalEvent.clientY < midPoint) {
					$(this).addClass('drag-over-top').removeClass('drag-over-bottom');
				} else {
					$(this).addClass('drag-over-bottom').removeClass('drag-over-top');
				}
				$(this).addClass('drag-over');
			}
			return false;
		});

		$(document).on('dragleave', '.lccl-de-t-card[draggable="true"]', function(e) {
			$(this).removeClass('drag-over drag-over-top drag-over-bottom');
		});

		$(document).on('drop', '.lccl-de-t-card[draggable="true"]', function(e) {
			e.stopPropagation();
			$(this).removeClass('drag-over drag-over-top drag-over-bottom');
			
			if (draggedCard && this !== draggedCard) {
				const rect = this.getBoundingClientRect();
				const midPoint = rect.top + rect.height / 2;
				
				if (e.originalEvent.clientY < midPoint) {
					$(this).before(draggedCard);
				} else {
					$(this).after(draggedCard);
				}
				
				saveOrder();
			}
			return false;
		});

		$(document).on('dragend', '.lccl-de-t-card[draggable="true"]', function(e) {
			$(this).removeClass('is-dragging');
			$('.lccl-de-t-card').removeClass('drag-over drag-over-top drag-over-bottom');
			draggedCard = null;
		});

		function saveOrder() {
			const ids = [];
			$('#lccl-de-active-list .lccl-de-t-card').each(function() {
				ids.push($(this).data('id'));
			});

			if (ids.length > 0) {
				$.post(lcclDePrograms.ajaxUrl, {
					action: 'lccl_de_testimonial_reorder',
					nonce: lcclDePrograms.nonce_reorder || lcclDePrograms.nonce,
					ids: ids
				});
			}
		}

		function updateCounts() {
			const activeCount = $('#lccl-de-active-list .lccl-de-t-card').length;
			const inactiveCount = $('#lccl-de-inactive-list .lccl-de-t-card').length;
			
			$('#lccl-de-active-count').text(activeCount);
			$('#lccl-de-inactive-count').text(inactiveCount);
			
			if (activeCount === 0) {
				$('#lccl-de-active-empty').show();
			} else {
				$('#lccl-de-active-empty').hide();
			}
			
			if (inactiveCount === 0) {
				$('#lccl-de-inactive-empty').show();
			} else {
				$('#lccl-de-inactive-empty').hide();
			}
		}

	});

})(jQuery);
