/**
 * Samina Rasul — interaction layer.
 * Lenis smooth scroll + GSAP/ScrollTrigger reveals + micro-interactions.
 * Everything motion-related is gated behind prefers-reduced-motion;
 * pointer effects behind (pointer: fine).
 */
(function () {
	'use strict';

	if (typeof gsap === 'undefined') { return; }

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
	gsap.registerPlugin(ScrollTrigger);

	var docEl = document.documentElement;
	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var finePointer = window.matchMedia('(pointer: fine)').matches;

	docEl.classList.add('sr-js');

	/* ---------------- Lenis smooth scroll ---------------- */
	var lenis = null;
	/* Native scrolling is the most stable baseline on shared hosting. Enable the
	 * Lenis layer only when the page explicitly opts in with data-sr-smooth. */
	var smoothScrollOptIn = docEl.getAttribute('data-sr-smooth') === 'true';
	if (!reduceMotion && smoothScrollOptIn && finePointer && typeof Lenis !== 'undefined') {
		lenis = new Lenis({
			duration: 0.75,
			easing: function (t) { return Math.min(1, 1.001 - Math.pow(2, -10 * t)); }
		});
		lenis.on('scroll', ScrollTrigger.update);
		gsap.ticker.add(function (time) { lenis.raf(time * 1000); });
		gsap.ticker.lagSmoothing(500, 16);
	}

	/* ---------------- Sticky header: shrink + hide on scroll down ---------------- */
	var header = document.querySelector('.site-header');
	if (header) {
		ScrollTrigger.create({
			start: 24,
			end: 'max',
			onUpdate: function (self) {
				docEl.classList.toggle('sr-header-hidden', self.direction === 1 && self.scroll() > 320);
			},
			onToggle: function (self) {
				docEl.classList.toggle('sr-scrolled', self.isActive);
			}
		});
	}

	/* ---------------- Reduced motion: show everything, stop here ---------------- */
	if (reduceMotion) {
		docEl.classList.add('sr-motion-off');
		return;
	}

	/* ---------------- Preloader (first visit per session) ---------------- */
	var preloader = document.querySelector('.sr-preloader');
	var heroDelay = 0;
	if (preloader && docEl.classList.contains('sr-preload')) {
		heroDelay = 0.9;
		try { sessionStorage.setItem('srSeen', '1'); } catch (e) {}
		gsap.timeline()
			.to('.sr-preloader__word span', { yPercent: 0, y: 0, autoAlpha: 1, stagger: 0.045, duration: 0.5, ease: 'power3.out' })
			.to('.sr-preloader__word span', { autoAlpha: 0, duration: 0.3, delay: 0.25, ease: 'power1.in' })
			.to(preloader, {
				yPercent: -100, duration: 0.6, ease: 'expo.inOut',
				onComplete: function () { docEl.classList.remove('sr-preload'); preloader.remove(); }
			});
	} else if (preloader) {
		preloader.remove();
	}

	/* ---------------- Curtain page transitions ---------------- */
	var curtain = document.querySelector('.sr-curtain');
	if (curtain) {
		// Arriving through a curtain exit → sweep it away.
		if (docEl.classList.contains('sr-curtain-in')) {
			gsap.to(curtain, {
				scaleY: 0, transformOrigin: '50% 0%', duration: 0.65, ease: 'expo.inOut', delay: 0.05,
				onComplete: function () { docEl.classList.remove('sr-curtain-in'); }
			});
			try { sessionStorage.removeItem('srCurtain'); } catch (e) {}
		}
		document.addEventListener('click', function (e) {
			if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
			var a = e.target.closest('a[href]');
			if (!a || a.target === '_blank' || a.hasAttribute('download')) { return; }
			var href = a.getAttribute('href');
			if (!href || href.charAt(0) === '#') { return; }
			var url;
			try { url = new URL(a.href, window.location.href); } catch (err) { return; }
			if (url.origin !== window.location.origin) { return; }
			if (url.pathname === window.location.pathname && url.hash) { return; }
			if (a.classList.contains('add_to_cart_button') || a.closest('.wc-block-components-button, .sr-whatsapp-inquire, .sr-whatsapp-float')) { return; }
			e.preventDefault();
			try { sessionStorage.setItem('srCurtain', '1'); } catch (err) {}
			gsap.timeline()
				.set(curtain, { transformOrigin: '50% 100%' })
				.to(curtain, { scaleY: 1, duration: 0.5, ease: 'expo.inOut' })
				.add(function () { window.location.href = url.href; });
		});
		// bfcache restore: make sure the curtain is gone.
		window.addEventListener('pageshow', function (e) {
			if (e.persisted) { gsap.set(curtain, { scaleY: 0 }); docEl.classList.remove('sr-curtain-in'); }
		});
	}

	/* ---------------- Hero entrance ---------------- */
	var hero = document.querySelector('.sr-hero');
	if (hero) {
		var heroDots = hero.querySelectorAll('.sr-divider .dot');
		var heroTl = gsap.timeline({ delay: heroDelay });
		heroTl
			.to('.sr-hero .sr-line-inner', { yPercent: 0, y: 0, duration: 1.05, stagger: 0.12, ease: 'power4.out' }, 0)
			.fromTo('.sr-hero .sr-eyebrow', { autoAlpha: 0, letterSpacing: '0.5em' }, { autoAlpha: 1, letterSpacing: '0.22em', duration: 1.1, ease: 'power2.out' }, 0.15)
			.fromTo('.sr-hero p', { autoAlpha: 0, y: 22 }, { autoAlpha: 1, y: 0, duration: 0.8, ease: 'power2.out' }, 0.55)
			.fromTo('.sr-hero .sr-hero-actions > *', { autoAlpha: 0, y: 18 }, { autoAlpha: 1, y: 0, duration: 0.6, stagger: 0.08, ease: 'power2.out' }, 0.7);
		if (heroDots.length) {
			heroTl.fromTo(heroDots, { scale: 0 }, { scale: 1, duration: 0.4, stagger: { each: 0.05, from: 'center' }, ease: 'back.out(2)' }, 0.9);
		}
	}

	// Ambient ornament rotation (hero + craft medallions).
	gsap.utils.toArray('.sr-ornament').forEach(function (el) {
		gsap.to(el, { rotation: 360, duration: 120, ease: 'none', repeat: -1, transformOrigin: '50% 50%' });
	});

	/* ---------------- Scroll reveals ---------------- */
	// Tag product cards for the shared reveal system.
	document.querySelectorAll('ul.products > li.product').forEach(function (li) {
		li.setAttribute('data-sr-reveal', '');
	});

	var revealEls = gsap.utils.toArray('[data-sr-reveal]');
	if (revealEls.length) {
		gsap.set(revealEls, { autoAlpha: 0, y: 30 });
		ScrollTrigger.batch(revealEls, {
			start: 'top 88%',
			once: true,
			onEnter: function (batch) {
				gsap.to(batch, { autoAlpha: 1, y: 0, duration: 0.9, stagger: 0.09, ease: 'power3.out', overwrite: true });
			}
		});
	}

	// Scroll-triggered masked-line reveals (manifesto and any [data-sr-lines] block).
	gsap.utils.toArray('[data-sr-lines]').forEach(function (section) {
		var inners = section.querySelectorAll('.sr-line-inner');
		if (!inners.length) { return; }
		gsap.to(inners, {
			yPercent: 0, y: 0, duration: 1.05, stagger: 0.14, ease: 'power4.out',
			scrollTrigger: { trigger: section, start: 'top 78%', once: true }
		});
	});

	// Drifting display words (values section) — opposing directions on scrub.
	gsap.utils.toArray('[data-sr-drift]').forEach(function (el) {
		var amount = parseFloat(el.getAttribute('data-sr-drift')) || 12;
		gsap.fromTo(el, { yPercent: amount * 2.2 }, {
			yPercent: amount * -2.2, ease: 'none',
			scrollTrigger: {
				trigger: el.closest('section') || el.parentElement,
				start: 'top bottom', end: 'bottom top', scrub: 1.2
			}
		});
	});

	// Mukesh-dot dividers scale in on scroll.
	gsap.utils.toArray('.sr-divider').forEach(function (divider) {
		if (divider.closest('.sr-hero')) { return; }
		var dots = divider.querySelectorAll('.dot');
		if (!dots.length) { return; }
		gsap.fromTo(dots, { scale: 0 }, {
			scale: 1, duration: 0.45, stagger: { each: 0.06, from: 'center' }, ease: 'back.out(2)',
			scrollTrigger: { trigger: divider, start: 'top 90%', once: true }
		});
	});

	/* ---------------- Process timeline: progress line + alternating cards ---------------- */
	var timeline = document.querySelector('.sr-timeline');
	if (timeline) {
		gsap.fromTo(timeline, { '--sr-progress': 0 }, {
			'--sr-progress': 1, ease: 'none',
			scrollTrigger: { trigger: timeline, start: 'top 72%', end: 'bottom 55%', scrub: 0.6 }
		});
		timeline.querySelectorAll('.sr-timeline__step').forEach(function (step, i) {
			var card = step.querySelector('.sr-timeline__card');
			var mobile = window.matchMedia('(max-width: 760px)').matches;
			var fromX = mobile ? 32 : ( i % 2 === 0 ? -48 : 48 );
			var details = card.querySelectorAll('.sr-timeline__heading, .sr-timeline__motif, h3, p');
			gsap.set(card, { autoAlpha: 0, x: fromX });
			gsap.set(details, { autoAlpha: 0, y: 18 });
			var chapter = gsap.timeline({ paused: true })
				.to(card, { autoAlpha: 1, x: 0, duration: 0.65, ease: 'power3.out' })
				.to(details, { autoAlpha: 1, y: 0, duration: 0.58, stagger: 0.08, ease: 'power3.out' }, '-=0.28');
			ScrollTrigger.create({
				trigger: step,
				start: 'top 62%',
				onEnter: function () {
					step.classList.add('is-lit', 'is-revealed');
					chapter.restart();
				},
				onLeaveBack: function () {
					step.classList.remove('is-lit', 'is-revealed');
					chapter.reverse();
				}
			});
		});
	}

	/* ---------------- Atelier rail: intentional horizontal browse controls ---------------- */
	var productRail = document.querySelector('[data-sr-product-rail]');
	if (productRail) {
		document.querySelectorAll('[data-sr-product-scroll]').forEach(function (control) {
			control.addEventListener('click', function () {
				var direction = control.getAttribute('data-sr-product-scroll') === 'next' ? 1 : -1;
				productRail.scrollBy({ left: direction * Math.max(productRail.clientWidth * 0.72, 320), behavior: 'smooth' });
			});
		});
	}

	/* ---------------- Marquee (scroll-velocity aware) ---------------- */
	var marqueeTrack = document.querySelector('.sr-marquee__track');
	if (marqueeTrack) {
		var marqueeTween = gsap.to(marqueeTrack, { xPercent: -50, ease: 'none', duration: 28, repeat: -1 });
		var speedProxy = { v: 1 };
		ScrollTrigger.create({
			start: 0,
			end: 'max',
			onUpdate: function (self) {
				var boost = 1 + Math.min(Math.abs(self.getVelocity()) / 900, 3);
				gsap.to(speedProxy, {
					v: boost, duration: 0.4, ease: 'power1.out', overwrite: true,
					onUpdate: function () { marqueeTween.timeScale(speedProxy.v); }
				});
			}
		});
	}

	/* ---------------- Gentle parallax ---------------- */
	gsap.utils.toArray('[data-sr-parallax]').forEach(function (el) {
		var amount = parseFloat(el.getAttribute('data-sr-parallax')) || 8;
		gsap.fromTo(el, { yPercent: amount }, {
			yPercent: -amount, ease: 'none',
			scrollTrigger: { trigger: el.parentElement, start: 'top bottom', end: 'bottom top', scrub: 1 }
		});
	});

	/* ---------------- Footer wordmark sweep ---------------- */
	var wordmark = document.querySelector('.sr-footer-wordmark span');
	if (wordmark) {
		gsap.fromTo(wordmark, { xPercent: 4 }, {
			xPercent: -4, ease: 'none',
			scrollTrigger: { trigger: '.sr-footer-wordmark', start: 'top bottom', end: 'bottom top', scrub: 1 }
		});
	}

	/* ---------------- Pointer-only micro-interactions ---------------- */
	if (!finePointer) { return; }

	// Custom cursor: trailing ring + dot with contextual labels.
	var ring = document.querySelector('.sr-cursor-ring');
	var dot = document.querySelector('.sr-cursor-dot');
	if (ring && dot) {
		docEl.classList.add('sr-cursor-on', 'sr-cursor-gone'); // hidden until the pointer first moves
		var ringX = gsap.quickTo(ring, 'x', { duration: 0.35, ease: 'power3.out' });
		var ringY = gsap.quickTo(ring, 'y', { duration: 0.35, ease: 'power3.out' });
		var dotX = gsap.quickTo(dot, 'x', { duration: 0.12, ease: 'power2.out' });
		var dotY = gsap.quickTo(dot, 'y', { duration: 0.12, ease: 'power2.out' });
		window.addEventListener('mousemove', function (e) {
			docEl.classList.remove('sr-cursor-gone');
			ringX(e.clientX); ringY(e.clientY); dotX(e.clientX); dotY(e.clientY);
		}, { passive: true });

		var hoverSelector = 'a, button, input[type="submit"], .sr-tile, li.product, [role="button"], select, label.sr-addon-option';
		document.addEventListener('mouseover', function (e) {
			var t = e.target.closest(hoverSelector);
			if (!t) { return; }
			var label = '';
			var card = e.target.closest('li.product');
			if (card) { label = card.querySelector('.sr-whatsapp-inquire') ? 'Inquire' : 'View'; }
			ring.setAttribute('data-label', label);
			docEl.classList.add('sr-cursor-hover');
			docEl.classList.toggle('sr-cursor-label', !!label);
		});
		document.addEventListener('mouseout', function (e) {
			if (e.target.closest(hoverSelector)) {
				docEl.classList.remove('sr-cursor-hover', 'sr-cursor-label');
			}
		});
		document.addEventListener('mouseleave', function () { docEl.classList.add('sr-cursor-gone'); });
		document.addEventListener('mouseenter', function () { docEl.classList.remove('sr-cursor-gone'); });
	}

	// Magnetic buttons.
	gsap.utils.toArray('.sr-hero .button, .sr-newsletter .button, .site-header .button').forEach(function (btn) {
		var xTo = gsap.quickTo(btn, 'x', { duration: 0.4, ease: 'power3.out' });
		var yTo = gsap.quickTo(btn, 'y', { duration: 0.4, ease: 'power3.out' });
		btn.addEventListener('mousemove', function (e) {
			var r = btn.getBoundingClientRect();
			xTo((e.clientX - (r.left + r.width / 2)) * 0.18);
			yTo((e.clientY - (r.top + r.height / 2)) * 0.28);
		});
		btn.addEventListener('mouseleave', function () { xTo(0); yTo(0); });
	});
	}
})();
