/**
 * Samina Rasul — search panel.
 *
 * The header search icon opens a full-width panel that answers as the visitor
 * types. Standalone and dependency-free on purpose: the animation bundle is
 * deliberately not loaded on cart/checkout/account, and search has to work
 * there too. With JavaScript off, none of this runs and the header form
 * submits to the normal results page, which is why the form is left intact.
 */
(function () {
	'use strict';

	var panel = document.getElementById('sr-search');
	if (!panel) { return; }

	var endpoint = panel.getAttribute('data-sr-search-endpoint');
	var input = panel.querySelector('#sr-search-field');
	var form = panel.querySelector('.sr-search__form');
	var resultsEl = panel.querySelector('[data-sr-search-results]');
	var statusEl = panel.querySelector('[data-sr-search-status]');
	var docEl = document.documentElement;
	var l10n = window.srSearchL10n || {};

	if (!endpoint || !input || !resultsEl || !statusEl) { return; }

	/* Only now is it safe for CSS to collapse the header's own search field to
	 * an icon: without this script that field is the only way to search. */
	docEl.classList.add('sr-search-js');

	var lastTrigger = null;
	var pending = null;   /* in-flight AbortController */
	var timer = 0;
	var primed = false;   /* the empty-query row is fetched once per page */

	/* ---------------- rendering ---------------- */

	function setStatus(message) {
		statusEl.textContent = message || '';
		statusEl.hidden = !message;
	}

	/* Built with DOM APIs rather than an HTML string: every value here comes
	 * off the wire, and textContent cannot be talked into executing anything. */
	function card(item) {
		var link = document.createElement('a');
		link.className = 'sr-search-card';
		link.href = item.url;

		var figure = document.createElement('span');
		figure.className = 'sr-search-card__media';
		if (item.image) {
			var img = document.createElement('img');
			img.src = item.image;
			img.alt = '';
			img.loading = 'lazy';
			img.decoding = 'async';
			figure.appendChild(img);
		}
		link.appendChild(figure);

		var body = document.createElement('span');
		body.className = 'sr-search-card__body';

		var title = document.createElement('span');
		title.className = 'sr-search-card__title';
		title.textContent = item.title;
		body.appendChild(title);

		if (item.meta) {
			var meta = document.createElement('span');
			meta.className = 'sr-search-card__meta';
			meta.textContent = item.meta;
			body.appendChild(meta);
		}

		if (item.blurb) {
			var blurb = document.createElement('span');
			blurb.className = 'sr-search-card__blurb';
			blurb.textContent = item.blurb;
			body.appendChild(blurb);
		}

		var price = document.createElement('span');
		price.className = 'sr-search-card__price';
		price.textContent = item.price;
		body.appendChild(price);

		link.appendChild(body);
		return link;
	}

	function render(data) {
		resultsEl.textContent = '';

		if (!data.results.length) {
			setStatus(l10n.empty || 'No matches.');
			return;
		}
		setStatus('');

		var frag = document.createDocumentFragment();
		data.results.forEach(function (item) { frag.appendChild(card(item)); });
		resultsEl.appendChild(frag);

		/* A query with results also gets a way through to the full results
		 * page, which is paginated and filterable; the panel is only a preview. */
		if (data.query) {
			var all = document.createElement('a');
			all.className = 'sr-search__all';
			all.href = form.action + '?s=' + encodeURIComponent(data.query) + '&post_type=product';
			all.textContent = l10n.viewAll || 'View all results';
			resultsEl.appendChild(all);
		}
	}

	function fetchResults(query) {
		if (pending) { pending.abort(); }
		pending = new AbortController();
		resultsEl.setAttribute('aria-busy', 'true');

		fetch(endpoint + '?q=' + encodeURIComponent(query), {
			signal: pending.signal,
			credentials: 'same-origin',
			headers: { Accept: 'application/json' }
		})
			.then(function (response) {
				if (!response.ok) { throw new Error('HTTP ' + response.status); }
				return response.json();
			})
			.then(function (data) {
				/* A slow response for an abandoned query must not overwrite a
				 * newer one; abort covers most of it, this covers the rest. */
				if (data.query === input.value.trim()) { render(data); }
			})
			.catch(function (err) {
				if (err.name === 'AbortError') { return; }
				resultsEl.textContent = '';
				setStatus(l10n.error || 'Search is unavailable right now.');
			})
			.finally(function () {
				resultsEl.setAttribute('aria-busy', 'false');
			});
	}

	/* ---------------- open / close ---------------- */

	function focusables() {
		return Array.prototype.filter.call(
			panel.querySelectorAll('a[href], button, input, [tabindex]:not([tabindex="-1"])'),
			function (el) { return el.offsetParent !== null; }
		);
	}

	function open(trigger) {
		if (!panel.hidden) { return; }
		lastTrigger = trigger || null;
		panel.hidden = false;
		docEl.classList.add('sr-search-open');
		/* sr-ui.js pauses Lenis on this, so the page behind cannot glide away
		 * under the panel. Harmless when the animation layer is not loaded. */
		document.dispatchEvent(new CustomEvent('sr:lock'));
		/* rAF so the panel is painted (and focusable) before focus moves. */
		requestAnimationFrame(function () { input.focus(); });

		if (!primed) {
			primed = true;
			fetchResults('');
		}
	}

	function close() {
		if (panel.hidden) { return; }
		if (pending) { pending.abort(); pending = null; }
		panel.hidden = true;
		docEl.classList.remove('sr-search-open');
		document.dispatchEvent(new CustomEvent('sr:unlock'));
		if (lastTrigger && document.contains(lastTrigger)) { lastTrigger.focus(); }
		lastTrigger = null;
	}

	/* The header form's submit button becomes the panel trigger. Delegated,
	 * because WooCommerce's cart fragments can replace parts of the header. */
	document.addEventListener('click', function (e) {
		var trigger = e.target.closest('.site-header .site-search button[type="submit"], [data-sr-search-open]');
		if (trigger) {
			e.preventDefault();
			open(trigger);
			return;
		}
		if (e.target.closest('[data-sr-search-close]')) {
			e.preventDefault();
			close();
		}
	});

	input.addEventListener('input', function () {
		var query = input.value.trim();
		clearTimeout(timer);
		/* Debounced: one request per pause in typing, not one per keystroke. */
		timer = setTimeout(function () { fetchResults(query); }, 220);
	});

	document.addEventListener('keydown', function (e) {
		if (panel.hidden) { return; }
		if (e.key === 'Escape') {
			e.preventDefault();
			close();
			return;
		}
		if (e.key !== 'Tab') { return; }

		/* Keep Tab inside the dialog: aria-modal is a promise to assistive tech
		 * that the rest of the page is inert, and focus has to honour it. */
		var items = focusables();
		if (!items.length) { return; }
		var first = items[0];
		var last = items[items.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	});
})();
