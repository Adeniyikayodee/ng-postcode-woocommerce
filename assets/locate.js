/* "Find my postcode": a button under WooCommerce's postcode field for Nigerian addresses. */
( function () {
	'use strict';

	const config = window.ngPostcodeLocate;
	if ( ! config || ! navigator.geolocation ) {
		return;
	}

	// The classic checkout uses underscores in its ids, the block checkout hyphens.
	const FIELDS = [ 'billing_postcode', 'shipping_postcode', 'billing-postcode', 'shipping-postcode' ];
	const controls = new Map();

	function countryOf( input ) {
		const country = document.getElementById( input.id.replace( 'postcode', 'country' ) );
		return country ? country.value : config.country;
	}

	function fill( input, value ) {
		// The block checkout is React, which ignores a plain assignment: the native
		// setter and an input event are what it listens to.
		Object.getOwnPropertyDescriptor( HTMLInputElement.prototype, 'value' ).set.call( input, value );
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	async function ask( input, note, position ) {
		try {
			const response = await fetch( config.url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { lat: position.coords.latitude, lng: position.coords.longitude } ),
			} );
			const answer = await response.json();
			if ( answer.found ) {
				fill( input, answer.postcode );
				// A phone's fix can land on the building next door, so the customer confirms.
				note.textContent = config.text.found
					.replace( '%1$s', answer.postcode )
					.replace( '%2$s', Math.round( answer.distance_m || 0 ) );
			} else {
				note.textContent = response.ok ? config.text.none : answer.message || config.text.failed;
			}
		} catch ( error ) {
			note.textContent = config.text.failed;
		}
	}

	function control( input ) {
		const box = document.createElement( 'div' );
		const button = document.createElement( 'button' );
		const note = document.createElement( 'p' );
		box.className = 'ng-postcode-locate';
		box.style.cssText = 'flex-basis:100%;grid-column:1/-1;margin:0 0 1em';
		button.type = 'button';
		button.className = 'button';
		button.textContent = config.text.button;
		note.setAttribute( 'role', 'status' );
		note.style.cssText = 'margin:.5em 0 0;font-size:.875em';
		button.addEventListener( 'click', () => {
			note.textContent = config.text.locating;
			navigator.geolocation.getCurrentPosition(
				( position ) => ask( input, note, position ),
				() => ( note.textContent = config.text.denied ),
				{ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
			);
		} );
		box.append( button, note );
		return box;
	}

	function sync() {
		// The block checkout redraws its fields, so drop controls whose field is gone.
		controls.forEach( ( box, input ) => {
			if ( ! input.isConnected ) {
				box.remove();
				controls.delete( input );
			}
		} );
		FIELDS.forEach( ( id ) => {
			const input = document.getElementById( id );
			if ( ! input ) {
				return;
			}
			if ( ! controls.has( input ) ) {
				controls.set( input, control( input ) );
			}
			const box = controls.get( input );
			if ( ! box.isConnected ) {
				( input.closest( '.wc-block-components-text-input, .form-row' ) || input ).after( box );
			}
			box.hidden = countryOf( input ) !== 'NG';
		} );
	}

	let queued = false;
	function queue() {
		if ( ! queued ) {
			queued = true;
			window.requestAnimationFrame( () => {
				queued = false;
				sync();
			} );
		}
	}

	new MutationObserver( queue ).observe( document.body, { childList: true, subtree: true } );
	document.addEventListener( 'change', queue );
	sync();
} )();
