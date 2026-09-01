import { __ } from '@wordpress/i18n';

export default function Help( { links } ) {
	const items = [
		[
			links.guide,
			__( 'Setup guide', 'woocommerce-referralcandy' ),
			__(
				'Step-by-step WooCommerce integration walkthrough and tips.',
				'woocommerce-referralcandy'
			),
		],
		[
			links.integration,
			__(
				'Integration settings in ReferralCandy',
				'woocommerce-referralcandy'
			),
			__(
				'Where the API Access ID, App ID and Secret Key live.',
				'woocommerce-referralcandy'
			),
		],
		[
			links.help,
			__( 'Help center', 'woocommerce-referralcandy' ),
			__( 'FAQs and support articles.', 'woocommerce-referralcandy' ),
		],
		[
			links.changelog,
			__( 'Plugin changelog', 'woocommerce-referralcandy' ),
			__( 'What changed in each release.', 'woocommerce-referralcandy' ),
		],
	];

	return (
		<div className="rc-content">
			<h1 className="rc-page__title">
				{ __( 'Help', 'woocommerce-referralcandy' ) }
			</h1>
			<p className="rc-page__desc">
				{ __(
					'Guides and support for the ReferralCandy integration.',
					'woocommerce-referralcandy'
				) }
			</p>
			<div className="rc-cards">
				{ items.map( ( [ href, title, desc ] ) => (
					<a
						key={ href }
						className="rc-card"
						href={ href }
						target="_blank"
						rel="noreferrer"
					>
						<h3>{ title } →</h3>
						<p>{ desc }</p>
					</a>
				) ) }
			</div>
		</div>
	);
}
