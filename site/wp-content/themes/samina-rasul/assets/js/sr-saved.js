/**
 * Samina Rasul — saved items.
 *
 * Reads the ids the product-page heart wrote to localStorage and asks the
 * search endpoint for those products. Same key, same store, same card shape as
 * the search panel, so there is one definition of "a product in a list".
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-sr-saved]');
	if (!root) { return; }

	var SAVE_KEY = 'srSaved';
	var endpoint = root.getAttribute('data-sr-endpoint');
	var resultsEl = root.querySelector('[data-sr-saved-results]');
	var emptyEl = root.querySelector('[data-sr-saved-empty]');
	var l10n = window.srSavedL10n || {};
	/* Static markup, no interpolation - the same pattern the other bundles use
	 * for inline icons. Every value that comes off the wire goes in via
	 * textContent below. */
	var REMOVE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';

	function readSaved() {
		try {
			var saved = JSON.parse(localStorage.getItem(SAVE_KEY));
			// Ids only: anything else in there is not ours to render.
			return Array.isArray(saved) ? saved.filter(function (id) { return /^\d+$/.test(String(id)); }) : [];
		} catch (e) {
			return [];
		}
	}

	function writeSaved(ids) {
		try { localStorage.setItem(SAVE_KEY, JSON.stringify(ids)); } catch (e) {}
	}

	function showEmpty(message) {
		resultsEl.textContent = '';
		emptyEl.hidden = false;
		if (message) { emptyEl.textContent = message; }
	}

	/* Built with DOM APIs: every value here comes off the wire, and textContent
	 * cannot be talked into executing anything. */
	function card(item) {
		var wrap = document.createElement('div');
		wrap.className = 'sr-saved-card';

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

		var price = document.createElement('span');
		price.className = 'sr-search-card__price';
		price.textContent = item.price;
		body.appendChild(price);

		link.appendChild(body);
		wrap.appendChild(link);

		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'sr-saved-remove';
		remove.setAttribute('aria-label', (l10n.remove || 'Remove from saved') + ': ' + item.title);
		remove.insertAdjacentHTML('afterbegin', REMOVE_ICON);
		remove.addEventListener('click', function () {
			var ids = readSaved().filter(function (id) { return String(id) !== String(item.id); });
			writeSaved(ids);
			wrap.remove();
			if (!ids.length) { showEmpty(); }
		});
		wrap.appendChild(remove);

		return wrap;
	}

	var ids = readSaved();
	if (!ids.length) { showEmpty(); return; }

	emptyEl.hidden = true;

	var query = ids.map(function (id) { return 'ids[]=' + encodeURIComponent(id); }).join('&');
	fetch(endpoint + '?' + query, {
		credentials: 'same-origin',
		headers: { Accept: 'application/json' }
	})
		.then(function (response) {
			if (!response.ok) { throw new Error('HTTP ' + response.status); }
			return response.json();
		})
		.then(function (data) {
			if (!data.results.length) { showEmpty(); return; }

			var frag = document.createDocumentFragment();
			data.results.forEach(function (item) { frag.appendChild(card(item)); });
			resultsEl.appendChild(frag);

			/* Products that have since been unpublished or deleted come back
			 * missing. Drop them so the list stops asking for them. */
			var live = data.results.map(function (item) { return String(item.id); });
			var pruned = ids.filter(function (id) { return live.indexOf(String(id)) !== -1; });
			if (pruned.length !== ids.length) { writeSaved(pruned); }
		})
		.catch(function () {
			showEmpty(l10n.error || 'Your saved pieces could not be loaded.');
		});
})();
