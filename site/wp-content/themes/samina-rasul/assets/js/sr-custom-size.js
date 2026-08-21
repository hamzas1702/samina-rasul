/**
 * Custom size — the made-to-measure route behind the "Customized" size pill.
 *
 * Progressive enhancement over markup that already works without it: the choice
 * row and the two dialogs are printed by samina-core/custom-size.php, the values
 * live in hidden inputs inside the real cart form, and this file only decides
 * when the row is shown and moves values into those inputs. With JavaScript off
 * nothing is revealed, the plain size still posts, and the server-side validator
 * refuses it with a message rather than taking an order nobody can cut.
 *
 * The size control itself is WooCommerce's own <select>, which sr-product.js
 * mirrors into pills; this listens to the select rather than the pills, so it
 * cannot disagree with what is actually being submitted.
 */
( function () {
	'use strict';

	var CUSTOM = 'customized';

	var form = document.querySelector( 'form.cart' );
	var panel = document.querySelector( '[data-sr-custom-size]' );

	if ( ! form || ! panel ) {
		return;
	}

	var select = form.querySelector( 'select#pa_size, select[name="attribute_pa_size"]' );

	if ( ! select ) {
		return;
	}

	var modeInput = panel.querySelector( '[data-sr-cs-mode]' );
	var summary = panel.querySelector( '[data-sr-cs-summary]' );
	var stores = {};

	panel.querySelectorAll( '[data-sr-cs-store]' ).forEach( function ( input ) {
		stores[ input.getAttribute( 'data-sr-cs-store' ) ] = input;
	} );

	var dialogs = {};
	document.querySelectorAll( '[data-sr-cs-dialog]' ).forEach( function ( node ) {
		dialogs[ node.getAttribute( 'data-sr-cs-dialog' ) ] = node;
	} );

	var strings = ( window.srCustomSizeL10n || {} );

	function t( key, fallback ) {
		return strings[ key ] || fallback;
	}

	/* --- Reveal ---------------------------------------------------------- */

	function isCustom() {
		return String( select.value || '' ).toLowerCase() === CUSTOM;
	}

	function syncVisibility() {
		var on = isCustom();
		panel.hidden = ! on;

		// Clearing on the way out matters: without it, a customer who filled the
		// sheet, changed their mind and chose "M" would still post fifteen
		// measurements alongside a standard size.
		if ( ! on ) {
			clearAll();
		}

		syncCartState();
	}

	function clearAll() {
		modeInput.value = '';
		Object.keys( stores ).forEach( function ( key ) {
			stores[ key ].value = '';
		} );
		summary.hidden = true;
		summary.textContent = '';
	}

	/**
	 * Hold the cart shut while "Customized" is chosen and nothing has been
	 * given.
	 *
	 * The server already refuses this (samina-core/custom-size.php) — the point
	 * here is that the customer finds out before a page load, and is told which
	 * of the two buttons above to press. `data-sr-block` is the contract
	 * sr-product.js reads to turn a click into a notice instead of a submit;
	 * `aria-disabled` rather than `disabled` because the button must stay
	 * focusable to be able to announce why it will not fire.
	 */
	function syncCartState() {
		var blocked = isCustom() && '' === modeInput.value;

		if ( blocked ) {
			form.setAttribute( 'data-sr-block', t( 'chooseRoute', 'Tell us how to take your measurements — request a call back, or enter them yourself.' ) );
		} else {
			form.removeAttribute( 'data-sr-block' );
		}

		var button = form.querySelector( '.single_add_to_cart_button' );
		if ( button ) {
			button.classList.toggle( 'sr-cart-held', blocked );
			button.setAttribute( 'aria-disabled', blocked ? 'true' : 'false' );
		}
	}

	select.addEventListener( 'change', syncVisibility );
	syncVisibility();

	/* --- Dialogs --------------------------------------------------------- */

	function openDialog( name ) {
		var dialog = dialogs[ name ];
		if ( ! dialog ) {
			return;
		}

		// Re-seed the dialog's own fields from what has already been stored, so
		// reopening it shows the values rather than an empty sheet.
		dialog.querySelectorAll( '[data-sr-cs-input]' ).forEach( function ( input ) {
			var key = input.getAttribute( 'data-sr-cs-input' );
			if ( stores[ key ] ) {
				input.value = stores[ key ].value;
			}
		} );

		showStep( dialog, 'front' );
		hideError( dialog );

		if ( typeof dialog.showModal === 'function' ) {
			dialog.showModal();
		} else {
			dialog.setAttribute( 'open', '' );
		}

		var first = dialog.querySelector( '[data-sr-cs-input]' );
		if ( first ) {
			first.focus();
		}
	}

	function showStep( dialog, step ) {
		var steps = dialog.querySelectorAll( '[data-sr-cs-step]' );
		if ( ! steps.length ) {
			return;
		}

		steps.forEach( function ( node ) {
			node.hidden = node.getAttribute( 'data-sr-cs-step' ) !== step;
		} );

		dialog.querySelectorAll( '[data-sr-cs-step-dot]' ).forEach( function ( dot ) {
			dot.classList.toggle( 'is-current', dot.getAttribute( 'data-sr-cs-step-dot' ) === step );
		} );
	}

	function showError( dialog, message ) {
		var box = dialog.querySelector( '[data-sr-cs-step]:not([hidden]) [data-sr-cs-error]' )
			|| dialog.querySelector( '[data-sr-cs-error]' );
		if ( ! box ) {
			return;
		}
		box.textContent = message;
		box.hidden = false;
	}

	function hideError( dialog ) {
		dialog.querySelectorAll( '[data-sr-cs-error]' ).forEach( function ( box ) {
			box.hidden = true;
			box.textContent = '';
		} );
	}

	/**
	 * Every visible, required input in the current step (or the whole dialog
	 * when it has no steps), reported as valid or not.
	 *
	 * Mirrors the server's rule rather than trusting the browser's: `required`
	 * and `min`/`max` are not enforced on inputs inside a <dialog> that is not
	 * being submitted as a form.
	 */
	function collect( dialog, scope ) {
		var inputs = ( scope || dialog ).querySelectorAll( '[data-sr-cs-input]' );
		var values = {};
		var missing = [];

		inputs.forEach( function ( input ) {
			var key = input.getAttribute( 'data-sr-cs-input' );
			var raw = String( input.value || '' ).trim();
			var label = input.getAttribute( 'placeholder' ) || key;
			var optional = ! input.hasAttribute( 'required' );

			input.classList.remove( 'is-invalid' );

			if ( '' === raw ) {
				if ( ! optional ) {
					missing.push( label );
					input.classList.add( 'is-invalid' );
				}
				values[ key ] = '';
				return;
			}

			if ( 'number' === input.type ) {
				var num = parseFloat( raw );
				var min = parseFloat( input.getAttribute( 'min' ) );
				var max = parseFloat( input.getAttribute( 'max' ) );

				if ( isNaN( num ) || num < min || num > max ) {
					missing.push( label );
					input.classList.add( 'is-invalid' );
					values[ key ] = '';
					return;
				}
				values[ key ] = String( num );
				return;
			}

			values[ key ] = raw;
		} );

		return { ok: 0 === missing.length, values: values, missing: missing };
	}

	function store( values ) {
		Object.keys( values ).forEach( function ( key ) {
			if ( stores[ key ] ) {
				stores[ key ].value = values[ key ];
			}
		} );
	}

	function closeDialog( dialog ) {
		if ( typeof dialog.close === 'function' && dialog.open ) {
			dialog.close();
		} else {
			dialog.removeAttribute( 'open' );
		}
	}

	/* --- Wiring ---------------------------------------------------------- */

	panel.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-sr-cs-open]' );
		if ( ! trigger ) {
			return;
		}
		event.preventDefault();
		openDialog( trigger.getAttribute( 'data-sr-cs-open' ) );
	} );

	Object.keys( dialogs ).forEach( function ( name ) {
		var dialog = dialogs[ name ];

		dialog.addEventListener( 'click', function ( event ) {
			var goTo = event.target.closest( '[data-sr-cs-goto]' );
			var commit = event.target.closest( '[data-sr-cs-commit]' );

			if ( goTo ) {
				event.preventDefault();
				var target = goTo.getAttribute( 'data-sr-cs-goto' );

				// Moving forward validates what is on screen; moving back never
				// does, or a half-filled first step becomes a trap.
				if ( 'back' === target ) {
					var current = dialog.querySelector( '[data-sr-cs-step="front"]' );
					var checked = collect( dialog, current );
					if ( ! checked.ok ) {
						showError( dialog, t( 'incomplete', 'Please complete every field: ' ) + checked.missing.join( ', ' ) );
						return;
					}
					store( checked.values );
				}

				hideError( dialog );
				showStep( dialog, target );
				return;
			}

			if ( commit ) {
				event.preventDefault();
				var mode = commit.getAttribute( 'data-sr-cs-commit' );
				var result = collect( dialog );

				if ( ! result.ok ) {
					showError( dialog, t( 'incomplete', 'Please complete every field: ' ) + result.missing.join( ', ' ) );
					return;
				}

				store( result.values );
				modeInput.value = mode;
				syncCartState();

				summary.textContent = 'manual' === mode
					? t( 'savedMeasurements', 'Measurements saved. They travel with this piece to the atelier.' )
					: t( 'savedCallback', 'Call back requested. We will telephone you to take your measurements.' );
				summary.hidden = false;

				closeDialog( dialog );
			}
		} );

		// Esc and the close button both fire `close`; neither should leave a
		// half-entered sheet looking as though it had been accepted.
		dialog.addEventListener( 'close', function () {
			if ( '' === modeInput.value ) {
				summary.hidden = true;
				summary.textContent = '';
			}
		} );
	} );
} )();
