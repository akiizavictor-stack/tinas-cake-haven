/**
 * Tina's Cake Haven – hero image slider.
 *
 * Vanilla, dependency-free carousel for the homepage hero:
 *  - smooth crossfade between slides
 *  - subtle Ken-Burns zoom on the active slide
 *  - auto-advance (~5.5s) with pause-on-hover/focus/offscreen/tab-hidden
 *  - prev/next arrows, dot indicators and a pause/play toggle
 *  - keyboard (Arrow Left/Right) and touch-swipe support
 *  - full respect for prefers-reduced-motion (no auto-advance, no zoom)
 *
 * Loaded only on the front page (see functions.php).
 */
(function () {
	'use strict';

	var slider = document.querySelector('.tch-hero-slider');
	if (!slider) {
		return;
	}

	var slides = Array.prototype.slice.call(slider.querySelectorAll('.tch-hero-slider__slide'));
	var dots   = Array.prototype.slice.call(slider.querySelectorAll('.tch-hero-slider__dot'));
	var prev   = slider.querySelector('.tch-hero-slider__prev');
	var next   = slider.querySelector('.tch-hero-slider__next');
	var pause  = slider.querySelector('.tch-hero-slider__pause');
	var count  = slides.length;
	if (!count) {
		return;
	}

	var INTERVAL = 5500; // ms between automatic transitions (5.5s)
	var SWIPE    = 40;   // px threshold for a swipe to count
	var reduce   = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var current  = 0;
	var timer    = null;
	var paused   = false;
	var visible  = true;

	/** Activate slide i and sync the indicators. */
	function setSlide(i) {
		current = (i % count + count) % count;
		slides.forEach(function (s, j) {
			var active = j === current;
			s.classList.toggle('is-active', active);
			s.setAttribute('aria-hidden', active ? 'false' : 'true');
		});
		dots.forEach(function (d, j) {
			var active = j === current;
			d.classList.toggle('is-active', active);
			d.setAttribute('aria-pressed', active ? 'true' : 'false');
		});
	}

	/** Move to slide i and restart the auto timer. */
	function go(i) {
		setSlide(i);
		restart();
	}

	function start() {
		if (reduce || paused || !visible || document.hidden) {
			return;
		}
		stop();
		timer = window.setInterval(function () { setSlide(current + 1); }, INTERVAL);
	}

	function stop() {
		if (timer) {
			window.clearInterval(timer);
			timer = null;
		}
	}

	function restart() {
		stop();
		start();
	}

	// --- Controls -----------------------------------------------------------
	if (prev) {
		prev.addEventListener('click', function () { go(current - 1); });
	}
	if (next) {
		next.addEventListener('click', function () { go(current + 1); });
	}
	dots.forEach(function (d, i) {
		d.addEventListener('click', function () { go(i); });
	});

	// --- Pause / play toggle ------------------------------------------------
	if (pause) {
		pause.addEventListener('click', function () {
			paused = !paused;
			pause.classList.toggle('is-paused', paused);
			pause.setAttribute('aria-pressed', paused ? 'true' : 'false');
			pause.setAttribute('aria-label', paused ? 'Play slideshow' : 'Pause slideshow');
			if (paused) { stop(); } else { start(); }
		});
	}

	// --- Pause on hover / keyboard focus -----------------------------------
	slider.addEventListener('mouseenter', function () { if (!paused) { stop(); } });
	slider.addEventListener('mouseleave', function () { if (!paused) { start(); } });
	slider.addEventListener('focusin', function () { if (!paused) { stop(); } });
	slider.addEventListener('focusout', function () { if (!paused) { start(); } });

	// --- Keyboard arrows ----------------------------------------------------
	slider.addEventListener('keydown', function (e) {
		if (e.key === 'ArrowLeft') {
			go(current - 1);
			e.preventDefault();
		} else if (e.key === 'ArrowRight') {
			go(current + 1);
			e.preventDefault();
		}
	});

	// --- Touch swipe --------------------------------------------------------
	var startX = null;
	var dx = 0;
	var viewport = slider.querySelector('.tch-hero-slider__viewport');
	function tStart(e) { startX = e.touches ? e.touches[0].clientX : e.clientX; dx = 0; }
	function tMove(e) { if (startX === null) { return; } dx = (e.touches ? e.touches[0].clientX : e.clientX) - startX; }
	function tEnd() {
		if (Math.abs(dx) > SWIPE) {
			go(dx < 0 ? current + 1 : current - 1);
		}
		startX = null;
		dx = 0;
	}
	if (viewport) {
		viewport.addEventListener('touchstart', tStart, { passive: true });
		viewport.addEventListener('touchmove', tMove, { passive: true });
		viewport.addEventListener('touchend', tEnd);
	}

	// --- Pause when the slider scrolls out of view --------------------------
	if ('IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				visible = en.isIntersecting;
				if (!visible) { stop(); } else { start(); }
			});
		}, { threshold: 0.05 });
		io.observe(slider);
	}

	// --- Pause when the tab is hidden --------------------------------------
	document.addEventListener('visibilitychange', function () {
		if (document.hidden) { stop(); } else { start(); }
	});

	// --- Boot ----------------------------------------------------------------
	setSlide(0);
	start();
})();
