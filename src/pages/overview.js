import { Button, Icon } from '@wordpress/components';
import { check, closeSmall, pause } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

/**
 * How a campaign reads on screen.
 *
 * Paused is its own row rather than folded into "not running": both send nothing, but a paused
 * campaign is one the merchant stopped on purpose and can resume, while a stopped one is where
 * every campaign begins. Telling them apart is the difference between "you turned this off" and
 * "you never turned this on".
 */
function campaignState( status ) {
	if ( status === 'active' ) {
		return {
			tone: 'ok',
			icon: check,
			label: __( 'Running', 'woocommerce-referralcandy' ),
		};
	}

	if ( status === 'paused' ) {
		return {
			tone: 'warn',
			icon: pause,
			label: __( 'Paused', 'woocommerce-referralcandy' ),
		};
	}

	return {
		tone: 'bad',
		icon: closeSmall,
		label: __( 'Not running', 'woocommerce-referralcandy' ),
	};
}

/**
 * A platform-connected store does not push orders — ReferralCandy pulls them with the
 * WooCommerce credentials it was granted — so the connected copy must not promise sending.
 */
function readyText( ready, platformConnected ) {
	if ( ! ready ) {
		return platformConnected
			? __(
					'Your store is connected; a few settings below still need attention.',
					'woocommerce-referralcandy'
			  )
			: __(
					'Your keys are verified; a few settings below still need attention.',
					'woocommerce-referralcandy'
			  );
	}

	return platformConnected
		? __(
				'ReferralCandy reads your orders directly from WooCommerce, so referrals get rewarded automatically.',
				'woocommerce-referralcandy'
		  )
		: __(
				'Orders that reach the configured status are sent to ReferralCandy so referrals get rewarded automatically.',
				'woocommerce-referralcandy'
		  );
}

export default function Overview( {
	status,
	platformConnected,
	campaigns = [],
	links,
	navigate,
} ) {
	const byId = Object.fromEntries( status.map( ( s ) => [ s.id, s ] ) );
	// Platform-connected stores have no key checks in the list at all, so "keys present"
	// is satisfied by the connection itself.
	const hasKeys =
		platformConnected || ( byId.api_id?.ok && byId.secret_key?.ok );
	const verified = byId.api_verified?.ok;
	const ready = status.every( ( s ) => s.ok );
	// Where the hero sends a connected merchant: the connection group is hidden for a
	// platform-connected store, so its first settings page is order tracking.
	const settingsTo = platformConnected
		? '/settings/orders'
		: '/settings/connection';

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
			to: settingsTo,
		};
	} else {
		hero = {
			title: ready
				? __( 'Your store is connected.', 'woocommerce-referralcandy' )
				: __( 'Almost there.', 'woocommerce-referralcandy' ),
			text: readyText( ready, platformConnected ),
			cta: __( 'Open settings', 'woocommerce-referralcandy' ),
			to: settingsTo,
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
				{ campaigns.length > 0 && (
					<>
						<h2 className="rc-page__subtitle">
							{ __(
								'Campaigns',
								'woocommerce-referralcandy'
							) }
						</h2>
						<p className="rc-page__desc">
							{ __(
								'Only a running campaign sends referral emails. Start, pause and edit them in your ReferralCandy dashboard.',
								'woocommerce-referralcandy'
							) }
						</p>
						{ /* Read-only on purpose: activating a campaign involves rewards,
						     emails and themes, and a button here would be a thin wrapper
						     over a deep flow that already has a home. */ }
						<ul className="rc-status">
							{ campaigns.map( ( campaign ) => {
								const state = campaignState( campaign.status );

								return (
									<li key={ campaign.key }>
										<span
											className={ `rc-status__icon rc-status__icon--${ state.tone }` }
										>
											<Icon
												icon={ state.icon }
												size={ 18 }
											/>
										</span>
										<span className="rc-status__label">
											{ campaign.name ||
												__(
													'Untitled campaign',
													'woocommerce-referralcandy'
												) }
										</span>
										<span className="rc-status__message">
											{ state.label }
										</span>
									</li>
								);
							} ) }
						</ul>
					</>
				) }
			</div>

			<aside className="rc-tips">
				<h4>{ __( 'Get started', 'woocommerce-referralcandy' ) }</h4>
				{ /* The key-based checklist is meaningless once the store is connected
				     through WooCommerce: the account exists and there is nothing to paste. */ }
				<ol className="rc-steps">
					{ platformConnected ? (
						<>
							<li>
								<a
									href={ links.dashboard }
									target="_blank"
									rel="noreferrer"
								>
									{ __(
										'Open your ReferralCandy dashboard',
										'woocommerce-referralcandy'
									) }
								</a>
							</li>
							<li>
								{ __(
									'Set up and launch your referral campaign.',
									'woocommerce-referralcandy'
								) }
							</li>
							<li>
								{ __(
									'Check the order status and checkout options under Settings.',
									'woocommerce-referralcandy'
								) }
							</li>
						</>
					) : (
						<>
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
						</>
					) }
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
