/**
 * Testimonials Frontend JS
 */
(function($) {
	'use strict';

	window.initLcclTestimonials = function($context) {
		const $target = $context ? $context.find('.lccl-testimonials-carousel') : $('.lccl-testimonials-carousel');
		$target.each(function() {
			const $carousel = $(this);
			const $wrapper = $carousel.closest('.lccl-testimonials-wrapper');
			const $track = $carousel.find('.lccl-testimonials-track');
			let $slides = $track.find('.lccl-testimonial-slide');
			const $dots = $wrapper.find('.lccl-t-dot');
			
			// Don't initialize if empty or already initialized
			if ($slides.length === 0 || $carousel.hasClass('is-initialized')) return;
			$carousel.addClass('is-initialized');

			const scrollMode = $wrapper.attr('class') ? $wrapper.attr('class').match(/lccl-scroll-([a-z-]+)/) : null;
			const mode = scrollMode ? scrollMode[1] : 'snap';
			
			// If constant or manual, we don't resize cards via JS classes.
			if (mode === 'constant') {
				// If not already pre-doubled by the server, double it now as a fallback
				if ($track.children('.lccl-testimonial-slide').length === $slides.length && $slides.length > 0) {
					while ($track.children('.lccl-testimonial-slide').length < 5) {
						$track.append($slides.clone());
					}
					const $all = $track.find('.lccl-testimonial-slide');
					$track.append($all.clone());
				}

				// Pause marquee when reading testimonial modal, resume when closed
				$carousel.on('pauseCarousel', function() {
					$track.css('animation-play-state', 'paused');
				});
				$carousel.on('resumeCarousel', function() {
					$track.css('animation-play-state', '');
				});

				return; // Handled completely by CSS keyframes
			}

			// If manual, just setup buttons and native scroll
			if (mode === 'manual') {
				let isDown = false;
				let startX;
				let scrollLeft;
				
				$track[0].addEventListener('mousedown', (e) => {
					isDown = true;
					startX = e.pageX - $track[0].offsetLeft;
					scrollLeft = $track[0].scrollLeft;
				});
				$track[0].addEventListener('mouseleave', () => { isDown = false; });
				$track[0].addEventListener('mouseup', () => { isDown = false; });
				$track[0].addEventListener('mousemove', (e) => {
					if (!isDown) return;
					e.preventDefault();
					const x = e.pageX - $track[0].offsetLeft;
					const walk = (x - startX) * 2;
					$track[0].scrollLeft = scrollLeft - walk;
				});

				$carousel.find('.lccl-t-nav-next').on('click', function() {
					$track.animate({ scrollLeft: '+=' + $slides.first().outerWidth() }, 300);
				});
				$carousel.find('.lccl-t-nav-prev').on('click', function() {
					$track.animate({ scrollLeft: '-=' + $slides.first().outerWidth() }, 300);
				});

				$dots.on('click', function(e) {
					e.preventDefault();
					const targetIdx = parseInt($(this).attr('data-slide-index'), 10);
					const $targetSlide = $track.find('.lccl-testimonial-slide[data-index="' + targetIdx + '"]').first();
					if ($targetSlide.length) {
						const offset = $targetSlide.position().left + $track.scrollLeft() - ($track.width() - $targetSlide.outerWidth()) / 2;
						$track.animate({ scrollLeft: Math.max(0, offset) }, 300);
						$dots.removeClass('is-active');
						$(this).addClass('is-active');
					}
				});
				return; // No auto-scroll in manual
			}

			// Auto-scroll (snap mode):
			// Ensure we have enough slides for smooth looping and jumps (at least 8 slides in track)
			const originalTotal = $dots.length || $slides.length;
			while ($track.children('.lccl-testimonial-slide').length < 8) {
				$track.append($slides.clone());
			}
			$slides = $track.find('.lccl-testimonial-slide');

			// On desktop (3 items visible), prepend the last slide so testimonial 0 starts in the prominent center
			if ($(window).width() >= 1024) {
				$track.prepend($track.children('.lccl-testimonial-slide').last());
				$slides = $track.find('.lccl-testimonial-slide');
			}

			let interval;
			let isTransitioning = false;
			const delay = 3500;
			const transitionSpeed = 600;

			function updateCenterClass(isTransitioningNext = false, forcedActiveIndex = null) {
				$slides.removeClass('is-center is-left is-right');
				// During transition, the 'next' slides will become the visible ones.
				const offset = isTransitioningNext ? 1 : 0;
				if ($(window).width() >= 1024) {
					$slides.eq(0 + offset).addClass('is-left');
					$slides.eq(1 + offset).addClass('is-center');
					$slides.eq(2 + offset).addClass('is-right');
				} else if ($(window).width() >= 768) {
					$slides.eq(offset).addClass('is-center');
					$slides.eq(1 + offset).addClass('is-center');
				} else {
					$slides.eq(offset).addClass('is-center');
				}

				// Update active pagination dot
				let activeIndex = forcedActiveIndex;
				if (activeIndex === null || activeIndex === undefined) {
					const centerIdx = $(window).width() >= 1024 ? (1 + offset) : offset;
					const $centerSlide = $slides.eq(centerIdx);
					activeIndex = $centerSlide.attr('data-index');
				}

				if (activeIndex !== undefined && activeIndex !== null) {
					$dots.removeClass('is-active');
					$dots.filter('[data-slide-index="' + activeIndex + '"]').addClass('is-active');
				}
			}

			// Initial set
			updateCenterClass();

			function nextSlide() {
				if (isTransitioning) return;
				isTransitioning = true;

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
					isTransitioning = false;
					
					// Re-apply center class immediately without transition offset
					updateCenterClass();
				}, transitionSpeed);
			}

			function goToSlide(targetIdx) {
				if (isTransitioning) return;

				const centerPos = $(window).width() >= 1024 ? 1 : 0;
				const currentIdx = parseInt($slides.eq(centerPos).attr('data-index'), 10);

				if (isNaN(currentIdx) || targetIdx === currentIdx) return;

				const totalUnique = originalTotal || 1;
				const forwardDist = (targetIdx - currentIdx + totalUnique) % totalUnique;
				const backwardDist = (currentIdx - targetIdx + totalUnique) % totalUnique;

				stopCarousel();
				const slideWidth = $slides.first().outerWidth();
				isTransitioning = true;

				if (forwardDist <= backwardDist) {
					// Forward transition
					updateCenterClass(false, targetIdx);
					$slides.removeClass('is-center is-left is-right');
					if ($(window).width() >= 1024) {
						$slides.eq(0 + forwardDist).addClass('is-left');
						$slides.eq(1 + forwardDist).addClass('is-center');
						$slides.eq(2 + forwardDist).addClass('is-right');
					} else {
						$slides.eq(forwardDist).addClass('is-center');
					}

					$track.css({
						'transition': 'transform ' + transitionSpeed + 'ms ease-in-out',
						'transform': 'translateX(-' + (forwardDist * slideWidth) + 'px)'
					});

					setTimeout(function() {
						$track.css({
							'transition': 'none',
							'transform': 'translateX(0)'
						});
						for (let i = 0; i < forwardDist; i++) {
							$track.append($track.children('.lccl-testimonial-slide').first());
						}
						$slides = $track.find('.lccl-testimonial-slide');
						isTransitioning = false;
						updateCenterClass();
						startCarousel();
					}, transitionSpeed);
				} else {
					// Backward transition
					for (let i = 0; i < backwardDist; i++) {
						$track.prepend($track.children('.lccl-testimonial-slide').last());
					}
					$slides = $track.find('.lccl-testimonial-slide');

					$track.css({
						'transition': 'none',
						'transform': 'translateX(-' + (backwardDist * slideWidth) + 'px)'
					});
					// Force reflow
					$track[0].offsetHeight;

					updateCenterClass(false, targetIdx);

					$track.css({
						'transition': 'transform ' + transitionSpeed + 'ms ease-in-out',
						'transform': 'translateX(0)'
					});

					setTimeout(function() {
						$track.css({
							'transition': 'none'
						});
						isTransitioning = false;
						updateCenterClass();
						startCarousel();
					}, transitionSpeed);
				}
			}

			// Dot click handler
			$dots.on('click', function(e) {
				e.preventDefault();
				const targetIdx = parseInt($(this).attr('data-slide-index'), 10);
				goToSlide(targetIdx);
			});

			let isModalOpen = false;

			function startCarousel() {
				if (isModalOpen) return;
				clearInterval(interval);
				interval = setInterval(nextSlide, delay);
			}

			function stopCarousel() {
				clearInterval(interval);
			}

			// Start the auto-scroll
			startCarousel();

			// Pause on hover for better UX (both carousel and dots)
			$carousel.on('mouseenter', stopCarousel);
			$carousel.on('mouseleave', startCarousel);
			$dots.on('mouseenter', stopCarousel);
			$dots.on('mouseleave', startCarousel);
			
			// Custom events for modal
			$carousel.on('pauseCarousel', function() {
				isModalOpen = true;
				stopCarousel();
			});
			$carousel.on('resumeCarousel', function() {
				isModalOpen = false;
				startCarousel();
			});
			
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

		function formatQuote(text) {
			if (!text) return '';
			let escaped = $('<div>').text(text).html();
			escaped = escaped.replace(/\[\s*\{?([^{}()\[\]\r\n]+?)\}?\s*(?:\]\s*\(|\()\s*\{?([^\s{}()\[\]\r\n]+?)\}?\s*\)\s*\]?/g, function(match, label, url) {
				let cleanUrl = url.trim().replace(/&amp;/g, '&');
				if (/^www\./i.test(cleanUrl)) {
					cleanUrl = 'https://' + cleanUrl;
				} else if (!/^(?:https?:\/\/|mailto:|tel:|\/)/i.test(cleanUrl) && /^[a-z0-9.-]+\.[a-z]{2,}/i.test(cleanUrl)) {
					cleanUrl = 'https://' + cleanUrl;
				}
				if (!/^(?:https?:\/\/|mailto:|tel:|\/)/i.test(cleanUrl)) {
					return label;
				}
				cleanUrl = cleanUrl.replace(/"/g, '&quot;');
				return '<a href="' + cleanUrl + '" target="_blank" rel="noopener noreferrer" class="lccl-t-link">' + label + '</a>';
			});
			return escaped.replace(/\n/g, '<br>');
		}

		// Modal logic
		$(document).off('click.lcclTestimonials', '.lccl-t-read-more').on('click.lcclTestimonials', '.lccl-t-read-more', function(e) {
			e.preventDefault();
			const $cardInner = $(this).closest('.lccl-testimonial-card-inner');
			const $container = $(this).closest('.lccl-t-quote-container');
			const name = $container.data('author-name') || '';
			const role = $container.data('author-role') || '';
			const date = $container.data('author-date') || '';
			const fullQuote = $(this).siblings('.lccl-t-quote-text').data('full-quote');
			const formattedQuote = formatQuote(fullQuote);
			const avatarHtml = $cardInner.find('.lccl-t-avatar-wrapper').html();
			
			let modalHtml = '<div style="width: 88px; height: 88px; margin: 0 auto 15px auto;">' + avatarHtml + '</div>';
			modalHtml += '<h4 class="lccl-t-name" style="margin-top:0; text-align:center;">' + name + '</h4>';
			if (role) {
				modalHtml += '<h5 class="lccl-t-role-title" style="font-size:12.35px; font-weight:normal; margin-bottom:4px; color:#666; text-align:center;">' + role + '</h5>';
			}
			if (date) {
				modalHtml += '<div class="lccl-t-date lccl-t-modal-date" style="font-size:12.35px; color:#666; text-align:center;">' + date + '</div>';
			}
			modalHtml += '<div class="lccl-t-modal-quote">' + formattedQuote + '</div>';
			
			$('.lccl-t-modal-content').html(modalHtml);
			$('.lccl-t-modal-overlay').fadeIn(200);
			
			// Pause all carousels
			$('.lccl-testimonials-carousel').trigger('pauseCarousel');
		});

		function closeTestimonialsModal() {
			$('.lccl-t-modal-overlay').fadeOut(200);
			$('.lccl-testimonials-carousel').trigger('resumeCarousel');
		}

		$(document).off('click.lcclTestimonials', '.lccl-t-modal-close, .lccl-t-modal-overlay').on('click.lcclTestimonials', '.lccl-t-modal-close, .lccl-t-modal-overlay', function(e) {
			if (e.target === this) {
				closeTestimonialsModal();
			}
		});

		$(document).off('keydown.lcclTestimonials').on('keydown.lcclTestimonials', function(e) {
			if (e.key === 'Escape' && $('.lccl-t-modal-overlay:visible').length) {
				closeTestimonialsModal();
			}
		});

	};

	$(document).ready(function() {
		window.initLcclTestimonials();
	});

})(jQuery);
