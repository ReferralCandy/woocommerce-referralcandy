import { Button, Icon } from '@wordpress/components';
import { check, closeSmall } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

export default function Overview( { status, links, navigate } ) {
	const failed = status.filter( ( s ) => ! s.ok );
	const ready = failed.length === 0;

	return (
		<>
			<div className="rc-content">
				<section className="rc-hero">
					<p className="rc-hero__eyebrow">
						{ __( 'Overview', 'woocommerce-referralcandy' ) }
					</p>
					<h1>
						{ ready
							? __(
									'Your store is connected.',
									'woocommerce-referralcandy'
							  )
							: __(
									'Finish connecting your store.',
									'woocommerce-referralcandy'
							  ) }
					</h1>
					<p>
						{ ready
							? __(
									'Orders that reach the configured status are sent to ReferralCandy so referrals get rewarded automatically.',
									'woocommerce-referralcandy'
							  )
							: __(
									'Paste your API credentials from the ReferralCandy dashboard to start tracking referrals.',
									'woocommerce-referralcandy'
							  ) }
					</p>
					<div className="rc-hero__actions">
						<Button
							variant="primary"
							onClick={ () => navigate( '/settings/connection' ) }
						>
							{ __(
								'Open settings',
								'woocommerce-referralcandy'
							) }
						</Button>
						<Button
							variant="link"
							href={ links.dashboard }
							target="_blank"
							rel="noreferrer"
						>
							{ __(
								'ReferralCandy dashboard ›',
								'woocommerce-referralcandy'
							) }
						</Button>
					</div>
				</section>

				<h2 className="rc-page__subtitle">
					{ __( 'Integration status', 'woocommerce-referralcandy' ) }
				</h2>
				<p className="rc-page__desc">
					{ __(
						'Everything the plugin needs to send orders to ReferralCandy.',
						'woocommerce-referralcandy'
					) }
				</p>
				<ul className="rc-status">
					{ status.map( ( s ) => (
						<li key={ s.id }>
							<span
								className={ `rc-status__icon rc-status__icon--${
									s.ok ? 'ok' : 'bad'
								}` }
							>
								<Icon
									icon={ s.ok ? check : closeSmall }
									size={ 18 }
								/>
							</span>
							<span className="rc-status__label">
								{ s.label }
							</span>
							{ ! s.ok && (
								<span className="rc-status__message">
									{ s.message }
								</span>
							) }
						</li>
					) ) }
				</ul>
			</div>

			<aside className="rc-tips">
				<h4>{ __( 'Get started', 'woocommerce-referralcandy' ) }</h4>
				<ol className="rc-steps">
					<li>
						<a
							href={ links.signup }
							target="_blank"
							rel="noreferrer"
						>
							{ __(
								'Start your free trial',
								'woocommerce-referralcandy'
							) }
						</a>
					</li>
					<li>
						<a
							href={ links.integration }
							target="_blank"
							rel="noreferrer"
						>
							{ __(
								'Open Integrations > WooCommerce',
								'woocommerce-referralcandy'
							) }
						</a>
					</li>
					<li>
						{ __(
							'Paste the API Access ID, App ID and Secret Key under Settings > API Connection.',
							'woocommerce-referralcandy'
						) }
					</li>
				</ol>
				<hr />
				<h4>{ __( 'Resources', 'woocommerce-referralcandy' ) }</h4>
				<p>
					<a href={ links.guide } target="_blank" rel="noreferrer">
						{ __( 'Setup guide →', 'woocommerce-referralcandy' ) }
					</a>
				</p>
				<p>
					<a href={ links.help } target="_blank" rel="noreferrer">
						{ __( 'Help center →', 'woocommerce-referralcandy' ) }
					</a>
				</p>
			</aside>
		</>
	);
}
