/**
 * Samina Rasul — product page.
 *
 * Turns WooCommerce's variation dropdowns into a row of pills, each headed by
 * its attribute name and the chosen value, and each carrying the price step it
 * costs. The <select> is still the input WooCommerce reads and writes; the
 * pills only set its value and fire the change event its own script listens
 * for. With this file absent or broken the dropdowns stay visible and the page
 * works exactly as stock.
 */
(function () {
	'use strict';

	/* ---------------- Save for later ----------------
	 * Browser-local, because there is no wishlist page or account-side list to
	 * write to yet. It persists, which is the honest half of the promise; the
	 * other half arrives with the list itself.
	 */
	var saveBtn = document.querySelector('[data-sr-save]');
	if (saveBtn) {
		var SAVE_KEY = 'srSaved';
		var readSaved = function () {
			try { return JSON.parse(localStorage.getItem(SAVE_KEY)) || []; } catch (e) { return []; }
		};
		var id = saveBtn.getAttribute('data-sr-save');
		var paint = function (saved) {
			var on = saved.indexOf(id) !== -1;
			saveBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
			saveBtn.classList.toggle('is-saved', on);
		};
		paint(readSaved());
		saveBtn.addEventListener('click', function () {
			var saved = readSaved();
			var at = saved.indexOf(id);
			if (at === -1) { saved.push(id); } else { saved.splice(at, 1); }
			try { localStorage.setItem(SAVE_KEY, JSON.stringify(saved)); } catch (e) {}
			paint(saved);
		});
	}


	/* ---------------- Quantity stepper ----------------
	 * WooCommerce ships a bare number input; the spinner it gets from the
	 * browser is tiny and inconsistent across them. Two buttons around the same
	 * input - it stays the field that is submitted, and `change` still fires so
	 * anything listening (cart totals, add-to-cart validation) keeps working.
	 */
	var qtyWrap = document.querySelector('.summary form.cart .quantity');
	var qtyInput = qtyWrap && qtyWrap.querySelector('input.qty');
	if (qtyWrap && qtyInput && !qtyWrap.querySelector('.sr-qty-step')) {
		var step = function (delta) {
			return function () {
				var min = parseFloat(qtyInput.getAttribute('min'));
				var max = parseFloat(qtyInput.getAttribute('max'));
				var by = parseFloat(qtyInput.getAttribute('step')) || 1;
				var next = (parseFloat(qtyInput.value) || 0) + (delta * by);
				if (isFinite(min) && next < min) { next = min; }
				if (isFinite(max) && next > max) { next = max; }
				qtyInput.value = next;
				qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
			};
		};
		var button = function (label, aria, handler) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'sr-qty-step';
			b.textContent = label;
			b.setAttribute('aria-label', aria);
			b.addEventListener('click', handler);
			return b;
		};
		var qtyL10n = (window.srProductL10n && srProductL10n.qty) || {};
		qtyWrap.insertBefore(button('\u2212', qtyL10n.less || 'Decrease quantity', step(-1)), qtyInput);
		qtyWrap.appendChild(button('+', qtyL10n.more || 'Increase quantity', step(1)));
		qtyWrap.classList.add('sr-qty');
	}

	/* ---------------- Live price ----------------
	 * One figure that answers "what does the piece I have configured cost",
	 * kept current as the variation and the add-ons change.
	 *
	 * The fees are applied server-side (samina-core/fabric-addons) and the
	 * variation price comes from WooCommerce, so this only ever re-states
	 * figures the server already owns. It never becomes the number that is
	 * charged - the cart recomputes from the same sources.
	 */
	var priceEl = document.querySelector('[data-sr-price]');
	var addonInputs = document.querySelectorAll('.sr-addons input[data-fee]');
	var variationPrice = null;

	var currencyEl = document.querySelector('.sr-price .woocommerce-Price-currencySymbol');
	var currency = currencyEl ? currencyEl.textContent.trim() : '';
	/* Formatting follows the shop's own separators and decimal count, passed
	 * through from WooCommerce - not toLocaleString(), which would quietly use
	 * the visitor's locale and print a figure the cart will not match. */
	var strings = (window.srProductL10n && srProductL10n.strings) || {};
	var money = (window.srProductL10n && srProductL10n.price) || {};
	var decimals = typeof money.decimals === 'number' ? money.decimals : 0;
	var decimalSep = money.decimalSep || '.';
	var thousandSep = typeof money.thousandSep === 'string' ? money.thousandSep : ',';

	function formatAmount(amount) {
		var parts = Math.abs(amount).toFixed(decimals).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
		return parts.join(decimalSep);
	}

	function addonSum() {
		var sum = 0;
		Array.prototype.forEach.call(addonInputs, function (input) {
			if (input.checked) { sum += parseFloat(input.getAttribute('data-fee')) || 0; }
		});
		return sum;
	}

	function renderPrice() {
		if (!priceEl) { return; }
		var base = variationPrice !== null ? variationPrice : parseFloat(priceEl.getAttribute('data-sr-base'));
		if (!isFinite(base)) { return; }
		// Digits only: the currency symbol is a sibling element and stays put.
		priceEl.textContent = formatAmount(base + addonSum());
	}

	Array.prototype.forEach.call(addonInputs, function (input) {
		input.addEventListener('change', renderPrice);
	});

	var form = document.querySelector('.variations_form');

	/* WooCommerce announces the chosen variation on jQuery events, which is the
	 * only place its price is exposed once the page has loaded. */
	if (form && window.jQuery) {
		window.jQuery(form)
			.on('show_variation', function (event, variation) {
				variationPrice = parseFloat(variation.display_price);
				renderPrice();
			})
			.on('hide_variation reset_data', function () {
				variationPrice = null;
				renderPrice();
			});
	}

	renderPrice();

	if (!form) { return; }

	var selects = form.querySelectorAll('.variations select');
	if (!selects.length) { return; }

	form.classList.add('sr-has-swatches');

	/* ---------------- Price steps ----------------
	 * WooCommerce ships the whole variation matrix on the form. Reading it lets
	 * a pill say what it costs before it is clicked, which is the difference
	 * between "Heavy" and "Heavy, + PKR 40,000".
	 *
	 * The step shown is against the cheapest variation on the product, computed
	 * per option value as the cheapest variation carrying that value. On a
	 * multi-attribute product that is a floor, not an exact total - which is
	 * why it is only ever rendered as "+", never as the final price. The real
	 * figure comes from WooCommerce's own single_variation panel on selection.
	 */
	var variations = [];
	try {
		variations = JSON.parse(form.getAttribute('data-product_variations') || '[]') || [];
	} catch (e) {
		variations = [];
	}


	var basePrice = null;
	variations.forEach(function (variation) {
		var price = parseFloat(variation.display_price);
		if (!isFinite(price)) { return; }
		if (basePrice === null || price < basePrice) { basePrice = price; }
	});

	/* Cheapest variation carrying a given value of a given attribute. */
	function priceFor(attributeName, value) {
		var lowest = null;
		variations.forEach(function (variation) {
			var attributes = variation.attributes || {};
			var held = attributes[attributeName];
			// An empty attribute on a variation means "any value", so it matches.
			if (held && String(held).toLowerCase() !== String(value).toLowerCase()) { return; }
			var price = parseFloat(variation.display_price);
			if (!isFinite(price)) { return; }
			if (lowest === null || price < lowest) { lowest = price; }
		});
		return lowest;
	}

	function formatStep(amount) {
		return "+ " + (currency ? currency + " " : "") + formatAmount(amount);
	}

	/* The size guide lives in the summary so it still works without this file;
	 * once the pills exist it belongs on the size row, beside the choices. */
	var sizeGuide = document.querySelector('.sr-size-chart-row');

	selects.forEach(function (select) {
		var labelEl = form.querySelector('label[for="' + select.id + '"]');
		var labelText = labelEl ? labelEl.textContent.trim().replace(/[:\s]+$/, '') : '';
		var attributeName = select.getAttribute('data-attribute_name') || select.name;

		var group = document.createElement('div');
		group.className = 'sr-swatch-group';

		var head = document.createElement('div');
		head.className = 'sr-swatch-head';
		var heading = document.createElement('span');
		heading.className = 'sr-swatch-head__label';
		head.appendChild(heading);
		group.appendChild(head);

		/* Size is the one attribute with a chart behind it. Matched on the
		 * taxonomy name first, falling back to the visible label so a custom
		 * (non-taxonomy) size attribute is still recognised. */
		var isSize = /(^|_)size$/i.test(attributeName || '') || /size/i.test(labelText);
		// "Select Size", but just "Dupatta Style" - the verb only reads well on
		// the one row that is always a required choice. Through l10n rather than
		// concatenated in English: this string is shown to every customer.
		var headingText = isSize
			? (strings.selectPrefix || 'Select %s').replace('%s', labelText)
			: labelText;

		/* Sizes are a handful of two-character labels and read as a row of
		 * pills. Everything else is a fabric or a made-up combination whose
		 * label runs to a full sentence, and those need a row each - as pills
		 * they wrapped into a ragged staircase of wildly different widths. */
		group.classList.add(isSize ? 'sr-swatch-group--size' : 'sr-swatch-group--option');
		if (isSize && sizeGuide) {
			head.appendChild(sizeGuide);
			sizeGuide = null;
		}

		/* A radiogroup, not a group of toggle buttons. This is a required,
		 * single-choice product option - the control that decides what gets
		 * bought - and as aria-pressed buttons a screen reader announced it as a
		 * set of independent toggles with no indication that picking one clears
		 * the others, or how many choices there were. */
		var pills = document.createElement('div');
		pills.className = 'sr-swatches';
		pills.setAttribute('role', 'radiogroup');
		if (labelText) { pills.setAttribute('aria-label', labelText); }
		group.appendChild(pills);

		/* Built once from the full option list. WooCommerce prunes the <option>
		 * elements as other attributes are chosen, so a pill whose option has
		 * gone is marked unavailable rather than removed - a row of sizes that
		 * reflows on every click is harder to use than one that greys out. */
		var values = [];
		Array.prototype.forEach.call(select.options, function (option) {
			if (!option.value) { return; }   // the "Choose an option" placeholder
			values.push(option.value);

			var pill = document.createElement('button');
			pill.type = 'button';
			pill.className = 'sr-swatch';
			pill.value = option.value;
			pill.setAttribute('role', 'radio');
			pill.setAttribute('aria-checked', 'false');

			var name = document.createElement('span');
			name.className = 'sr-swatch__name';
			name.textContent = option.textContent;
			pill.appendChild(name);

			var optionPrice = basePrice === null ? null : priceFor(attributeName, option.value);
			if (optionPrice !== null && optionPrice > basePrice) {
				var step = document.createElement('span');
				step.className = 'sr-swatch__step';
				step.textContent = formatStep(optionPrice - basePrice);
				pill.appendChild(step);
			}

			pill.addEventListener('click', function () {
				// Clicking the chosen pill again clears it, which is the only
				// way back to "Choose an option" once the select is hidden.
				select.value = select.value === option.value ? '' : option.value;
				select.dispatchEvent(new Event('change', { bubbles: true }));
			});
			pills.appendChild(pill);
		});

		if (!values.length) { return; }

		select.insertAdjacentElement('afterend', group);
		/* The pills are the control now. The select stays as the value the
		 * WooCommerce script reads, but out of the tab order, so nobody lands
		 * on a dropdown they cannot see. */
		select.tabIndex = -1;

		/* Roving tabindex, as a radiogroup requires: one stop for the whole group
		 * in the tab order, arrow keys to move within it. Without this, Tab
		 * walks every size in the row and the group has no keyboard model at
		 * all. */
		pills.addEventListener('keydown', function (e) {
			var keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
			if (!(e.key in keys)) { return; }

			var usable = Array.prototype.filter.call(pills.children, function (p) { return !p.disabled; });
			if (usable.length < 2) { return; }

			e.preventDefault();
			var at = usable.indexOf(document.activeElement);
			var next = usable[(at + keys[e.key] + usable.length) % usable.length];
			next.focus();
			// Arrow keys select in a radiogroup, they do not merely move.
			select.value = next.value;
			select.dispatchEvent(new Event('change', { bubbles: true }));
		});

		function sync() {
			var chosen = '';
			var firstUsable = null;
			var hasChoice = false;

			Array.prototype.forEach.call(pills.children, function (pill) {
				var available = false;
				Array.prototype.forEach.call(select.options, function (option) {
					if (option.value === pill.value) { available = true; }
				});
				var active = select.value === pill.value;
				if (active) {
					chosen = pill.querySelector('.sr-swatch__name').textContent;
					hasChoice = true;
				}
				if (available && !firstUsable) { firstUsable = pill; }
				pill.classList.toggle('is-active', active);
				pill.setAttribute('aria-checked', active ? 'true' : 'false');
				pill.classList.toggle('is-unavailable', !available);
				pill.disabled = !available;
				// The chosen option is the group's tab stop; with none chosen it
				// is the first selectable one, so the group is always reachable.
				pill.tabIndex = active ? 0 : -1;
			});

			if (!hasChoice && firstUsable) { firstUsable.tabIndex = 0; }
			// Two nodes, not one string: the chosen value is set in a heavier
			// weight beside the attribute name.
			heading.textContent = headingText;
			if (chosen) {
				var value = document.createElement('span');
				value.className = 'sr-swatch-head__value';
				value.textContent = ' (' + chosen + ')';
				heading.appendChild(value);
			}
		}

		sync();
		select.addEventListener('change', sync);
		/* WooCommerce rewrites the option list directly rather than firing an
		 * event this file can listen for, so watch the select's children. */
		new MutationObserver(sync).observe(select, { childList: true });
	});
})();
