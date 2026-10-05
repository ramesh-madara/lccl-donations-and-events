/**
 * Testimonials Frontend JS
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		$('.lccl-testimonials-carousel').each(function() {
			const $carousel = $(this);
			const $track = $carousel.find('.lccl-testimonials-track');
			let $slides = $track.find('.lccl-testimonial-slide');
			
			// Don't initialize if empty
			if ($slides.length === 0) return;

			// If we have 3 or fewer slides, duplicate them so the infinite loop works seamlessly on desktop
			if ($slides.length <= 3) {
				$track.append($slides.clone());
				$slides = $track.find('.lccl-testimonial-slide');
			}

			let interval;
			const delay = 3000;
			const transitionSpeed = 600;

			function updateCenterClass(isTransitioning = false) {
				$slides.removeClass('is-center');
				// During transition, the 'next' slides will become the visible ones.
				const offset = isTransitioning ? 1 : 0;
				if ($(window).width() >= 1024) {
					$slides.eq(1 + offset).addClass('is-center');
				} else if ($(window).width() >= 768) {
					$slides.eq(offset).addClass('is-center');
					$slides.eq(1 + offset).addClass('is-center');
				} else {
					$slides.eq(offset).addClass('is-center');
				}
			}

			// Initial set
			updateCenterClass();

			function nextSlide() {
				// Calculate width dynamically in case of resize
				const slideWidth = $slides.first().outerWidth();
				
				// Pre-apply center class for the incoming center slide to trigger CSS transition
				updateCenterClass(true);

				$track.css({
					'transition': 'transform ' + transitionSpeed + 'ms ease-in-out',
					'transform': 'translateX(-' + slideWidth + 'px)'
				});

				// Wait for transition to finish, then snap back and move DOM element
				setTimeout(function() {
					$track.css({
						'transition': 'none',
						'transform': 'translateX(0)'
					});
					
					// Move first slide to the end of the track
					$track.append($track.children('.lccl-testimonial-slide').first());
					
					// Re-cache slides
					$slides = $track.find('.lccl-testimonial-slide');
					
					// Re-apply center class immediately without transition offset
					updateCenterClass();
				}, transitionSpeed);
			}

			function startCarousel() {
				interval = setInterval(nextSlide, delay);
			}

			function stopCarousel() {
				clearInterval(interval);
			}

			// Start the auto-scroll
			startCarousel();

			// Pause on hover for better UX
			$carousel.on('mouseenter', stopCarousel);
			$carousel.on('mouseleave', startCarousel);
			
			// Custom events for modal
			$carousel.on('pauseCarousel', stopCarousel);
			$carousel.on('resumeCarousel', startCarousel);
			
			// Handle window resize to prevent visual glitches
			$(window).on('resize', function() {
				$track.css({
					'transition': 'none',
					'transform': 'translateX(0)'
				});
				updateCenterClass();
			});
		});

		// Truncation check for "Read more..." button
		function checkTruncation() {
			$('.lccl-t-quote-text').each(function() {
				// We give a small 2px buffer for rounding errors
				if (this.scrollHeight > this.clientHeight + 2) {
					$(this).siblings('.lccl-t-read-more').show();
				} else {
					$(this).siblings('.lccl-t-read-more').hide();
				}
			});
		}

		// Use a slight timeout to ensure fonts and layout are fully rendered
		setTimeout(checkTruncation, 100);
		$(window).on('resize', checkTruncation);

		// Modal logic
		$(document).on('click', '.lccl-t-read-more', function(e) {
			e.preventDefault();
			const $cardInner = $(this).closest('.lccl-testimonial-card-inner');
			const $container = $(this).closest('.lccl-t-quote-container');
			const name = $container.data('author-name') || '';
			const role = $container.data('author-role') || '';
			const fullQuote = $(this).siblings('.lccl-t-quote-text').data('full-quote');
			const formattedQuote = fullQuote.replace(/\n/g, '<br>');
			const avatarHtml = $cardInner.find('.lccl-t-avatar-wrapper').html();
			
			let modalHtml = '<div style="width: 80px; height: 80px; margin: 0 auto 15px auto;">' + avatarHtml + '</div>';
			modalHtml += '<h4 class="lccl-t-name" style="margin-top:0; text-align:center;">' + name + '</h4>';
			if (role) {
				modalHtml += '<h5 class="lccl-t-role-title" style="font-size:13px; font-weight:normal; margin-bottom:20px; color:#666; text-align:center;">' + role + '</h5>';
			}
			modalHtml += '<div class="lccl-t-modal-quote" style="margin-top: 15px; text-align:center;">' + formattedQuote + '</div>';
			
			$('.lccl-t-modal-content').html(modalHtml);
			$('.lccl-t-modal-overlay').fadeIn(200);
			
			// Pause all carousels
			$('.lccl-testimonials-carousel').trigger('pauseCarousel');
		});

		$(document).on('click', '.lccl-t-modal-close, .lccl-t-modal-overlay', function(e) {
			if (e.target === this) {
				$('.lccl-t-modal-overlay').fadeOut(200);
				// Resume all carousels
				$('.lccl-testimonials-carousel').trigger('resumeCarousel');
			}
		});

	});
})(jQuery);
