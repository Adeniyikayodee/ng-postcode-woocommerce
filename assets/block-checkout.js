/*
 * The block checkout also checks postcodes in the browser, and for Nigeria it still
 * expects the old six-digit code, so it rejects every digital postcode before the
 * server sees it. This clears that one error when the code is a well-formed digital
 * postcode. The server checks the code again either way.
 */
( function () {
	'use strict';

	const data = window.wp && window.wp.data;
	if ( ! data ) {
		return;
	}
	const STORE = 'wc/store/validation';
	const CODE = /^[A-Z]{2}(0[1-9]|[1-9][0-9])[A-Z0-9]{3}[A-Z]{2}(0[1-9]|[1-9][0-9])$/;

	data.subscribe( () => {
		const errors = data.select( STORE );
		if ( ! errors ) {
			return;
		}
		[ 'billing', 'shipping' ].forEach( ( address ) => {
			const input = document.getElementById( `${ address }-postcode` );
			const country = document.getElementById( `${ address }-country` );
			if (
				input &&
				country &&
				country.value === 'NG' &&
				errors.getValidationError( `${ address }_postcode` ) &&
				CODE.test( input.value.replace( /[ -]/g, '' ).toUpperCase() )
			) {
				data.dispatch( STORE ).clearValidationError( `${ address }_postcode` );
			}
		} );
	} );
} )();
