/**
 * The settings navigation is the one place the plugin decides what a merchant is even shown,
 * and a wc-auth store must not be offered credentials it will never have.
 */
import { GROUPS, groupsFor } from '../groups';

const FIELDS = {
	api_id: {},
	app_id: {},
	secret_key: {},
	order_status: {},
	tracking_page: {},
	enable_marketing_checkbox: {},
	accepts_marketing_label: {},
	popup: {},
	popup_campaign_key: {},
	popup_quickfix: {},
};

const fieldsIn = ( groups ) => groups.flatMap( ( group ) => group.fields );

describe( 'groupsFor', () => {
	it.each( [
		[ 'a key-based store', false ],
		[ 'a connected store', true ],
	] )( 'shows no credential form to %s', ( _label, platformConnected ) => {
		// v3 sets a store up one way. The values stay stored, validated and used — there is
		// simply no form asking a merchant to copy three strings out of a dashboard.
		const shown = fieldsIn( groupsFor( FIELDS, platformConnected ) );

		expect( shown ).not.toContain( 'api_id' );
		expect( shown ).not.toContain( 'secret_key' );
		expect( shown ).not.toContain( 'app_id' );
	} );

	it( 'has no connection group left to navigate to', () => {
		expect( groupsFor( FIELDS ).map( ( g ) => g.key ) ).not.toContain(
			'connection'
		);
	} );

	it( 'hides the order status from a store whose orders are read, not sent', () => {
		// submit_purchase() returns early for these stores, so the setting is wired to
		// nothing; showing it implies the merchant can control ingestion from here.
		expect( fieldsIn( groupsFor( FIELDS, true ) ) ).not.toContain(
			'order_status'
		);
	} );

	it( 'keeps the order status for a store that pushes its own orders', () => {
		expect( fieldsIn( groupsFor( FIELDS ) ) ).toContain( 'order_status' );
	} );

	it( 'renames the order group once nothing about sending is configurable', () => {
		const connected = groupsFor( FIELDS, true ).find(
			( group ) => group.key === 'orders'
		);

		expect( connected.title ).toBe( 'Tracking' );
		expect( connected.fields ).toEqual( [ 'tracking_page' ] );
	} );

	it( 'does not spill hidden keys into the "Other" catch-all', () => {
		const other = groupsFor( FIELDS, true ).find(
			( group ) => group.key === 'other'
		);

		expect( other ).toBeUndefined();
	} );

	it( 'still collects genuinely unknown fields, so a new PHP field cannot vanish', () => {
		const other = groupsFor(
			{ ...FIELDS, brand_new_field: {} },
			true
		).find( ( group ) => group.key === 'other' );

		expect( other?.fields ).toEqual( [ 'brand_new_field' ] );
	} );

	it( 'leaves the group list untouched for a key-based store', () => {
		expect( groupsFor( FIELDS ) ).toEqual( GROUPS );
	} );

	it( 'never mutates the shared GROUPS constant', () => {
		const before = JSON.stringify( GROUPS );
		groupsFor( FIELDS, true );

		expect( JSON.stringify( GROUPS ) ).toBe( before );
	} );
} );
