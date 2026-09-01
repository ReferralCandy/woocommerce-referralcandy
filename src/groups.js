import { __ } from '@wordpress/i18n';

/**
 * Settings sub-pages. Field keys come from WC_Referralcandy_Integration::init_form_fields();
 * any key not listed here lands in an "Other" group so new PHP fields still show up.
 */
export const GROUPS = [
	{
		key: 'orders',
		title: __( 'Order Tracking', 'woocommerce-referralcandy' ),
		description: __(
			'When orders are sent to ReferralCandy and where the tracking code renders.',
			'woocommerce-referralcandy'
		),
		fields: [ 'order_status', 'tracking_page' ],
		tips: [
			__(
				'Orders are sent once when they reach the selected status. "Completed" is the safest default; use "Processing" to reward referrals sooner.',
				'woocommerce-referralcandy'
			),
			__(
				'The tracking code always renders on the order-received page; the selected page is an extra location.',
				'woocommerce-referralcandy'
			),
		],
	},
	{
		key: 'checkout',
		title: __( 'Checkout', 'woocommerce-referralcandy' ),
		description: __(
			'The accepts-marketing checkbox shown to customers at checkout.',
			'woocommerce-referralcandy'
		),
		fields: [ 'enable_marketing_checkbox', 'accepts_marketing_label' ],
		tips: [
			__(
				'Turning the checkbox off marks every customer as unsubscribed from referral emails by default.',
				'woocommerce-referralcandy'
			),
			__(
				'Works with both the classic shortcode checkout and the block checkout.',
				'woocommerce-referralcandy'
			),
		],
	},
	{
		key: 'popup',
		title: __( 'Post-purchase Popup', 'woocommerce-referralcandy' ),
		description: __(
			'Show the ReferralCandy popup on the thank-you page.',
			'woocommerce-referralcandy'
		),
		fields: [ 'popup', 'popup_campaign_key', 'popup_quickfix' ],
		tips: [
			__(
				'The campaign decides which offer the popup shows. Connect the store and its campaigns are listed here by name.',
				'woocommerce-referralcandy'
			),
			__(
				'Enable the quickfix only if the popup breaks the layout of your thank-you page.',
				'woocommerce-referralcandy'
			),
		],
	},
];

/**
 * Never shown in v3, in any store shape — but still stored, still validated, still used.
 *
 * A connected store owns none of them: ReferralCandy holds the store's own WooCommerce
 * credentials and supplies the App ID itself. A store still running on v2 keys does own them,
 * and they keep working untouched — but v3 has one way to set a store up, and it is not a form
 * that asks a merchant to copy three strings out of a dashboard. Their repair is Connect.
 *
 * Kept out of the "Other" catch-all too, or removing the group would simply move them.
 */
const CONNECTION_FIELDS = new Set( [ 'api_id', 'app_id', 'secret_key' ] );

/**
 * Settings that only matter when the plugin is the one sending orders.
 *
 * A platform-connected store has ReferralCandy read its orders directly, and
 * `RC_Order::submit_purchase()` returns early, so the order status is wired to nothing.
 * Leaving it on screen tells a merchant they can control ingestion from here. They cannot.
 */
const PUSH_ONLY_FIELDS = new Set( [ 'order_status' ] );

/**
 * @param {Object}  fields            Field schema from PHP.
 * @param {boolean} platformConnected Store connected through wc-auth, which grants
 *                                    ReferralCandy the store's own WooCommerce credentials.
 *                                    Such a store also has its orders read rather than pushed,
 *                                    so the order status goes away with them.
 */
export function groupsFor( fields, platformConnected = false ) {
	const groups = platformConnected
		? GROUPS.map( ( group ) =>
				group.key === 'orders'
					? {
							...group,
							title: __(
								'Tracking',
								'woocommerce-referralcandy'
							),
							description: __(
								'Where the ReferralCandy tracking code renders. ReferralCandy reads your orders directly, so there is nothing to configure about sending them.',
								'woocommerce-referralcandy'
							),
							fields: group.fields.filter(
								( key ) => ! PUSH_ONLY_FIELDS.has( key )
							),
							tips: [
								__(
									'The tracking code always renders on the order-received page; the selected page is an extra location.',
									'woocommerce-referralcandy'
								),
							],
					  }
					: group
		  )
		: GROUPS;
	const known = new Set( groups.flatMap( ( g ) => g.fields ) );
	const hidden = ( key ) =>
		CONNECTION_FIELDS.has( key ) ||
		( platformConnected && PUSH_ONLY_FIELDS.has( key ) );
	const other = Object.keys( fields ).filter(
		( k ) => ! known.has( k ) && ! hidden( k )
	);

	return other.length
		? [
				...groups,
				{
					key: 'other',
					title: __( 'Other', 'woocommerce-referralcandy' ),
					description: '',
					fields: other,
					tips: [],
				},
		  ]
		: groups;
}
