import { __ } from '@wordpress/i18n';

/**
 * Settings sub-pages. Field keys come from WC_Referralcandy_Integration::init_form_fields();
 * any key not listed here lands in an "Other" group so new PHP fields still show up.
 */
export const GROUPS = [
	{
		key: 'connection',
		title: __( 'API Connection', 'woocommerce-referralcandy' ),
		description: __(
			'Credentials that connect this store to your ReferralCandy account.',
			'woocommerce-referralcandy'
		),
		fields: [ 'api_id', 'app_id', 'secret_key' ],
		tips: [
			__(
				'Find these under Integrations > WooCommerce in your ReferralCandy dashboard.',
				'woocommerce-referralcandy'
			),
			__(
				'A purchase is required to confirm the integration is working.',
				'woocommerce-referralcandy'
			),
		],
	},
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
				'The campaign key selects which campaign the popup shows. Find it under Campaigns > (campaign) > Widgets > Post-purchase Popup > WooCommerce integration.',
				'woocommerce-referralcandy'
			),
			__(
				'Enable the quickfix only if the popup breaks the layout of your thank-you page.',
				'woocommerce-referralcandy'
			),
		],
	},
];

export function groupsFor( fields ) {
	const known = new Set( GROUPS.flatMap( ( g ) => g.fields ) );
	const other = Object.keys( fields ).filter( ( k ) => ! known.has( k ) );

	return other.length
		? [
				...GROUPS,
				{
					key: 'other',
					title: __( 'Other', 'woocommerce-referralcandy' ),
					description: '',
					fields: other,
					tips: [],
				},
		  ]
		: GROUPS;
}
