// A shopper in Ado Ekiti finds their postcode and places an order, in both checkouts.
// NIPOST is replaced by tests/integration/stub.php, and the browser's location is set here.
const { test, expect } = require( '@playwright/test' );

const HERE = { latitude: 7.6211, longitude: 5.2214 };

for ( const kind of [ 'block', 'classic' ] ) {
	test( `${ kind } checkout: find my postcode, then place the order`, async ( { browser, request } ) => {
		const store = await ( await request.get( `/ng-setup.php?checkout=${ kind }` ) ).json();
		const context = await browser.newContext( { geolocation: HERE, permissions: [ 'geolocation' ] } );
		const page = await context.newPage();
		const field = ( name ) => page.locator( kind === 'block' ? `#billing-${ name }` : `#billing_${ name }` );
		// The classic checkout hides its selects behind a widget, so set them directly.
		const choose = ( name, value ) =>
			kind === 'block'
				? field( name ).selectOption( value )
				: field( name ).evaluate( ( select, picked ) => {
						select.value = picked;
						select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				  }, value );

		await page.goto( `/?add-to-cart=${ store.product }` );
		await page.goto( '/checkout/' );
		const button = page.getByRole( 'button', { name: 'Find my postcode' } );
		await expect( button ).toBeVisible();

		await choose( 'country', 'GH' );
		await expect( button ).toBeHidden();
		await choose( 'country', 'NG' );
		await expect( button ).toBeVisible();

		await button.click();
		await expect( field( 'postcode' ) ).toHaveValue( 'EK-01-A29-KR-36' );
		await expect( page.locator( '.ng-postcode-locate [role=status]' ) ).toHaveText(
			'Nearest building: EK-01-A29-KR-36, about 16 m away. Check that it is yours.'
		);

		await page.locator( kind === 'block' ? '#email' : '#billing_email' ).fill( 'ada@example.com' );
		await field( 'first_name' ).fill( 'Ada' );
		await field( 'last_name' ).fill( 'Obi' );
		await field( 'address_1' ).fill( '1 NTA Road' );
		await field( 'city' ).fill( 'Ado Ekiti' );
		await choose( 'state', 'EK' );
		await field( 'phone' ).fill( '08000000000' );
		// The redrawn form must still hold the code the button filled in.
		await expect( field( 'postcode' ) ).toHaveValue( 'EK-01-A29-KR-36' );
		await page.getByRole( 'button', { name: /place order/i } ).click();

		await page.waitForURL( /order-received\/(\d+)/ );
		const order = page.url().match( /order-received\/(\d+)/ )[ 1 ];
		const stored = await ( await request.get( `/ng-probe.php?order=${ order }` ) ).json();
		expect( stored.postcode ).toBe( 'EK-01-A29-KR-36' );
		await context.close();
	} );
}

test( 'block checkout: a typed code is accepted and stored in canonical form', async ( { page, request } ) => {
	const store = await ( await request.get( '/ng-setup.php?checkout=block' ) ).json();
	await page.goto( `/?add-to-cart=${ store.product }` );
	await page.goto( '/checkout/' );
	await page.locator( '#billing-country' ).selectOption( 'NG' );
	await page.locator( '#billing-postcode' ).fill( 'EK-00-A03-FK-01' );
	await page.locator( '#billing-city' ).click();
	await expect( page.getByText( 'Please enter a valid postcode' ) ).toBeVisible();

	await page.locator( '#billing-postcode' ).fill( 'ek 01 a03 fk 01' );
	await page.locator( '#billing-city' ).fill( 'Ado Ekiti' );
	await expect( page.getByText( 'Please enter a valid postcode' ) ).toBeHidden();
	await page.locator( '#email' ).fill( 'ada@example.com' );
	await page.locator( '#billing-first_name' ).fill( 'Ada' );
	await page.locator( '#billing-last_name' ).fill( 'Obi' );
	await page.locator( '#billing-address_1' ).fill( '1 NTA Road' );
	await page.locator( '#billing-state' ).selectOption( 'EK' );
	await page.locator( '#billing-phone' ).fill( '08000000000' );
	await page.getByRole( 'button', { name: /place order/i } ).click();

	await page.waitForURL( /order-received\/(\d+)/ );
	const order = page.url().match( /order-received\/(\d+)/ )[ 1 ];
	const stored = await ( await request.get( `/ng-probe.php?order=${ order }` ) ).json();
	expect( stored.postcode ).toBe( 'EK-01-A03-FK-01' );
} );
