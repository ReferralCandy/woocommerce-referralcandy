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
	it( 'shows every credential to a store that connects with API keys', () => {
		const shown = fieldsIn( groupsFor( FIELDS ) );

		expect( shown ).toEqual( expect.arrayContaining( [ 'api_id', 'app_id', 'secret_key' ] ) );
	} );

	it( 'hides the keys a platform-connected store will never own', () => {
		const shown = fieldsIn( groupsFor( FIELDS, true ) );

		expect( shown ).not.toContain( 'api_id' );
		expect( shown ).not.toContain( 'secret_key' );
	} );

	it( 'keeps the App ID, which names the tracking script', () => {
		// Hiding it would leave a connected store unable to see or fix the one identifier its
		// thank-you page actually needs.
		expect( fieldsIn( groupsFor( FIELDS, true ) ) ).toContain( 'app_id' );
	} );

	it( 'does not spill hidden keys into the "Other" catch-all', () => {
		const other = groupsFor( FIELDS, true ).find( ( group ) => group.key === 'other' );

		expect( other ).toBeUndefined();
	} );

	it( 'still collects genuinely unknown fields, so a new PHP field cannot vanish', () => {
		const other = groupsFor( { ...FIELDS, brand_new_field: {} }, true ).find(
			( group ) => group.key === 'other'
		);

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
