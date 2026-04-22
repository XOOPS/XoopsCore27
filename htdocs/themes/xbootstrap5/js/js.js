// JavaScript Document

function initBootstrapCarousels(options) {
	jQuery(function ($) {
		$('.carousel').each(function () {
			if (globalThis.bootstrap?.Carousel) {
				globalThis.bootstrap.Carousel.getOrCreateInstance(this, options);
			} else if (typeof $.fn.carousel === 'function') {
				$(this).carousel(options);
			} else {
				console.warn('Bootstrap carousel API not available.');
			}
		});
	});
}

/* Scroll Top */
jQuery(function ($) {
	if (typeof $.scrollUp === 'function') {
		$.scrollUp({
			scrollName: 'scrollUp',           // Element ID
			topDistance: '300',               // Distance from top before showing element (px)
			topSpeed: 300,                    // Speed back to top (ms)
			animation: 'fade',                // Fade, slide, none
			animationInSpeed: 200,            // Animation in speed (ms)
			animationOutSpeed: 200,           // Animation out speed (ms)
			scrollText: 'Scroll to top',      // Text for element
			activeOverlay: false,             // '#00FFFF' for visible overlay
			scrollImg: true                   // Use image instead of text
		});
	} else {
		console.warn("scrollUp plugin not available.");
	}
});

/* Bootstrap Carousel */
initBootstrapCarousels({
	interval: 5000,
	pause: 'hover',
	wrap: true
});

/* Masonry Grid */
jQuery(function ($) {
	var $container = $('#xoopsgrid').masonry();
	$container.imagesLoaded(function () {
		$container.masonry();
	});
});

/* Newbb styling adjustments */
jQuery(function ($) {
	$(".xoopsform").find('form').addClass("form-inline");
	$(".xoopsform").find('select').addClass("form-control");
	$(".xoopsform").find('input[type="submit"]').addClass("btn btn-primary");
	$(".newbb-links").find('span').removeClass('forum_icon forum_button');
	$('.newbb-thread-attachment').find('br, hr').remove();
});

/* Slider init */
function initSlider() {
	initBootstrapCarousels({
		interval: 5000,
		wrap: true
	});
}

// Load when browser is idle
if ('requestIdleCallback' in globalThis) {
	globalThis.requestIdleCallback(initSlider);
} else {
	setTimeout(initSlider, 200); // fallback
}
