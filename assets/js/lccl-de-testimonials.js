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

			function nextSlide() {
				// Calculate width dynamically in case of resize
				const slideWidth = $slides.first().outerWidth();
				
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
			
			// Handle window resize to prevent visual glitches
			$(window).on('resize', function() {
				$track.css({
					'transition': 'none',
					'transform': 'translateX(0)'
				});
			});
		});
	});
})(jQuery);
