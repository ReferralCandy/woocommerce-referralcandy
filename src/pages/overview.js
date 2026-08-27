import { Button, Icon } from '@wordpress/components';
import { check, closeSmall } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

export default function Overview( { status, links, navigate } ) {
	const byId = Object.fromEntries( status.map( ( s ) => [ s.id, s ] ) );
	const hasKeys = byId.api_id?.ok && byId.secret_key?.ok;
	const verified = byId.api_verified?.ok;
	const ready = status.every( ( s ) => s.ok );

	let hero;
	if ( ! hasKeys ) {
		hero = {
			title: __(
				'Finish connecting your store.',
				'woocommerce-referralcandy'
			),
			text: __(
				'Enter the API keys from your ReferralCandy account to start sending orders and tracking referrals.',
				'woocommerce-referralcandy'
			),
			cta: __( 'Enter API keys', 'woocommerce-referralcandy' ),
			to: '/setup/keys',
		};
	} else if ( verified === false ) {
		hero = {
			title: __(
				'ReferralCandy rejected your API keys.',
				'woocommerce-referralcandy'
			),
			text: __(
				'Orders are not being sent. Re-copy the API Access ID, App ID and Secret Key from Integrations → WooCommerce.',
				'woocommerce-referralcandy'
			),
			cta: __( 'Fix API keys', 'woocommerce-referralcandy' ),
			to: '/settings/connection',
		};
	} else {
		hero = {
			title: ready
				? __( 'Your store is connected.', 'woocommerce-referralcandy' )
				: __( 'Almost there.', 'woocommerce-referralcandy' ),
			text: ready
				? __(
						'Orders that reach the configured status are sent to ReferralCandy so referrals get rewarded automatically.',
						'woocommerce-referralcandy'
				  )
				: __(
						'Your keys are verified; a few settings below still need attention.',
						'woocommerce-referralcandy'
				  ),
			cta: __( 'Open settings', 'woocommerce-referralcandy' ),
			to: '/settings/connection',
		};
	}

	return (
		<>
			<div className="rc-content">
				<section className="rc-hero">
					<p className="rc-hero__eyebrow">
						{ __( 'Overview', 'woocommerce-referralcandy' ) }
					</p>
					<h1>{ hero.title }</h1>
					<p>{ hero.text }</p>
					<div className="rc-hero__actions">
						<Button
							variant="primary"
							onClick={ () => navigate( hero.to ) }
						>
							{ hero.cta }
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
							href={ links.integrations }
							target="_blank"
							rel="noreferrer"
						>
							{ __(
								'Open Integrations → WooCommerce',
								'woocommerce-referralcandy'
							) }
						</a>
					</li>
					<li>
						{ __(
							'Paste the API Access ID, App ID and Secret Key under Settings → API Connection.',
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
