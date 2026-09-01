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
	legacy,
	pendingSetup,
	campaigns = [],
	links,
	navigate,
} ) {
	const ready = status.every( ( s ) => s.ok );
	// Linked but unpaid is still linked: ReferralCandy holds this store's WooCommerce
	// credentials either way, and its orders are read rather than pushed. Only the hero treats
	// the two apart, because only such a store still owes a plan.
	const linked = platformConnected || pendingSetup;

	// A freshly connected store usually fails exactly one check, and no settings page can fix
	// it: the campaign has to be started at ReferralCandy. Sending them to Settings here is
	// the moment they decide whether this plugin works, so it has to point at the real
	// next step.
	const failing = status.filter( ( s ) => ! s.ok );
	const onlyNeedsCampaign =
		failing.length === 1 && failing[ 0 ].id === 'campaign_active';

	let hero;
	if ( pendingSetup ) {
		// Approved already, and waiting on a plan. This has to outrank the legacy offer below:
		// telling a merchant who just approved access to approve it again hides the one step
		// that is actually outstanding, and it is not a step this plugin can complete.
		hero = {
			title: __(
				'Your ReferralCandy account needs a plan.',
				'woocommerce-referralcandy'
			),
			text: __(
				'This store is linked and nothing here needs redoing. Referrals start once a plan is chosen.',
				'woocommerce-referralcandy'
			),
			cta: __( 'Choose a plan ↗', 'woocommerce-referralcandy' ),
			href: links.plans,
		};
	} else if ( onlyNeedsCampaign ) {
		hero = {
			title: __(
				'Connected. Now launch a campaign.',
				'woocommerce-referralcandy'
			),
			text: __(
				'Your store is talking to ReferralCandy, but no campaign is running yet, so nothing is being sent to your customers.',
				'woocommerce-referralcandy'
			),
			cta: __( 'Launch a campaign', 'woocommerce-referralcandy' ),
			href: links.dashboard,
		};
	} else if ( legacy ) {
		// Working, on the older arrangement. Not an error and not nagged as one — but connecting
		// is the only thing left to do here, so it is the one button offered.
		hero = {
			title: __(
				'Connect this store to ReferralCandy.',
				'woocommerce-referralcandy'
			),
			text: __(
				'Your store is running on API keys from an earlier setup. They keep working — connecting through WooCommerce replaces them, and there is nothing to copy.',
				'woocommerce-referralcandy'
			),
			cta: __(
				'Connect through WooCommerce',
				'woocommerce-referralcandy'
			),
			to: '/setup',
		};
	} else if ( ! platformConnected ) {
		hero = {
			title: __(
				'Finish connecting your store.',
				'woocommerce-referralcandy'
			),
			text: __(
				'Approve access in WooCommerce and ReferralCandy takes it from there — no keys to copy.',
				'woocommerce-referralcandy'
			),
			cta: __( 'Connect your store', 'woocommerce-referralcandy' ),
			to: '/setup',
		};
	} else {
		// Connected and nothing to fix. The dashboard is where the work happens; settings hold
		// only checkout and popup details, and the sidebar already leads there.
		hero = {
			title: ready
				? __( 'Your store is connected.', 'woocommerce-referralcandy' )
				: __( 'Almost there.', 'woocommerce-referralcandy' ),
			text: readyText( ready, platformConnected ),
			cta: __(
				'Open your ReferralCandy dashboard',
				'woocommerce-referralcandy'
			),
			href: links.dashboard,
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
						{ hero.href ? (
							<Button
								variant="primary"
								href={ hero.href }
								target="_blank"
								rel="noreferrer"
							>
								{ hero.cta }
							</Button>
						) : (
							<Button
								variant="primary"
								onClick={ () => navigate( hero.to ) }
							>
								{ hero.cta }
							</Button>
						) }
						{ hero.href !== links.dashboard && (
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
						) }
					</div>
				</section>

				<h2 className="rc-page__subtitle">
					{ __( 'Integration status', 'woocommerce-referralcandy' ) }
				</h2>
				<p className="rc-page__desc">
					{ /* Two calls, not a ternary inside __(): a computed string cannot be
					     extracted for translation. */ }
					{ linked
						? __(
								'Everything ReferralCandy needs to read this store and reward referrals.',
								'woocommerce-referralcandy'
						  )
						: __(
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
							{ __( 'Campaigns', 'woocommerce-referralcandy' ) }
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
				{ /* Two checklists, because the next three things to do genuinely differ. The
				     unconnected one no longer mentions keys: there is no page to paste them
				     into, and approving access is what sets a store up in v3. A store waiting
				     on a plan gets the linked list — it has already done the first two. */ }
				<ol className="rc-steps">
					{ linked ? (
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
								{ __(
									'Press Connect through WooCommerce above.',
									'woocommerce-referralcandy'
								) }
							</li>
							<li>
								{ __(
									'Approve access in WooCommerce — one click, no keys to copy.',
									'woocommerce-referralcandy'
								) }
							</li>
							<li>
								{ __(
									'Pick a plan on ReferralCandy, and you are brought back here.',
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
