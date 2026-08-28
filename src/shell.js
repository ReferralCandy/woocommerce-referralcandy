import { Icon } from '@wordpress/components';
import {
	check,
	chevronLeft,
	chevronRight,
	cog,
	help,
	home,
	wordpress,
} from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

function NavItem( {
	icon,
	label,
	active,
	onClick,
	arrow,
	trailing,
	disabled,
} ) {
	return (
		<button
			type="button"
			className={ `rc-shell__nav-item${
				active ? ' rc-shell__nav-item--active' : ''
			}` }
			onClick={ onClick }
			disabled={ disabled }
		>
			{ icon && <Icon icon={ icon } size={ 24 } /> }
			<span className="rc-shell__nav-label">{ label }</span>
			{ trailing }
			{ arrow && (
				<Icon
					icon={ chevronRight }
					size={ 20 }
					className="rc-shell__nav-arrow"
				/>
			) }
		</button>
	);
}

function SetupNav( { sub, signupStarted, navigate } ) {
	// The last step depends on which way the merchant went. Connecting through WooCommerce
	// ends at ReferralCandy's plan picker and never asks for a key, so promising "Enter API
	// keys" to everyone advertises a chore most merchants will never do — and it is the step
	// left on screen while they decide whether to start at all.
	const enteringKeys = sub === 'keys';
	const steps = [
		{
			key: 'create',
			label: __( 'Create account', 'woocommerce-referralcandy' ),
			active: ! sub,
			done: signupStarted,
			to: '/setup',
		},
		{
			key: 'approve',
			label: __( 'Approve access', 'woocommerce-referralcandy' ),
			active: false,
			done: signupStarted,
		},
		enteringKeys
			? {
					key: 'keys',
					label: __(
						'Enter API keys',
						'woocommerce-referralcandy'
					),
					active: true,
					done: false,
					to: '/setup/keys',
			  }
			: {
					key: 'plan',
					label: __(
						'Choose a plan',
						'woocommerce-referralcandy'
					),
					active: false,
					done: false,
			  },
	];

	return (
		<>
			<h2 className="rc-shell__title">
				{ __( 'Setup', 'woocommerce-referralcandy' ) }
			</h2>
			<p className="rc-shell__desc">
				{ __(
					"Three steps. Your store's own URL is used; nothing to type.",
					'woocommerce-referralcandy'
				) }
			</p>
			<nav className="rc-shell__nav">
				{ steps.map( ( step, i ) => (
					<NavItem
						key={ step.key }
						label={ step.label }
						active={ step.active }
						disabled={ ! step.to }
						onClick={ () => step.to && navigate( step.to ) }
						trailing={
							<span
								className={ `rc-shell__nav-step${
									step.done ? ' rc-shell__nav-step--done' : ''
								}` }
							>
								{ step.done ? (
									<Icon icon={ check } size={ 16 } />
								) : (
									i + 1
								) }
							</span>
						}
					/>
				) ) }
			</nav>
		</>
	);
}

export default function Shell( {
	config,
	section,
	sub,
	groups,
	navigate,
	pageTitle,
	headerAction,
	signupStarted,
	children,
} ) {
	const inSettings = section === 'settings';
	const inSetup = section === 'setup';

	return (
		<div className="rc-shell">
			<aside className="rc-shell__sidebar">
				<a className="rc-shell__back-wp" href={ config.adminUrl }>
					<Icon icon={ wordpress } size={ 28 } />
					<span>
						{ __(
							'Back to WP Admin',
							'woocommerce-referralcandy'
						) }
					</span>
				</a>

				{ inSetup && (
					<SetupNav
						sub={ sub }
						signupStarted={ signupStarted }
						navigate={ navigate }
					/>
				) }

				{ inSettings && (
					<>
						<button
							type="button"
							className="rc-shell__submenu-back"
							onClick={ () => navigate( '/' ) }
						>
							<Icon icon={ chevronLeft } size={ 24 } />
							<span>
								{ __(
									'Settings',
									'woocommerce-referralcandy'
								) }
							</span>
						</button>
						<p className="rc-shell__desc">
							{ __(
								'Configure how the store talks to ReferralCandy.',
								'woocommerce-referralcandy'
							) }
						</p>
						<nav className="rc-shell__nav">
							{ groups.map( ( g ) => (
								<NavItem
									key={ g.key }
									label={ g.title }
									active={ g.key === sub }
									onClick={ () =>
										navigate( `/settings/${ g.key }` )
									}
								/>
							) ) }
						</nav>
					</>
				) }

				{ ! inSetup && ! inSettings && (
					<>
						<h2 className="rc-shell__title">{ config.title }</h2>
						<p className="rc-shell__desc">
							{ __(
								'Referral and affiliate marketing for your WooCommerce store.',
								'woocommerce-referralcandy'
							) }
						</p>
						<nav className="rc-shell__nav">
							<NavItem
								icon={ home }
								label={ __(
									'Overview',
									'woocommerce-referralcandy'
								) }
								active={ section === 'overview' }
								onClick={ () => navigate( '/' ) }
							/>
							<NavItem
								icon={ cog }
								label={ __(
									'Settings',
									'woocommerce-referralcandy'
								) }
								arrow
								onClick={ () => navigate( '/settings' ) }
							/>
							<NavItem
								icon={ help }
								label={ __(
									'Help',
									'woocommerce-referralcandy'
								) }
								active={ section === 'help' }
								onClick={ () => navigate( '/help' ) }
							/>
						</nav>
					</>
				) }

				<footer className="rc-shell__footer">
					<a
						href={ config.links.guide }
						target="_blank"
						rel="noreferrer"
					>
						{ __( 'Docs', 'woocommerce-referralcandy' ) }
					</a>
					<a
						href={ config.links.help }
						target="_blank"
						rel="noreferrer"
					>
						{ __( 'Support', 'woocommerce-referralcandy' ) }
					</a>
					<span>v{ config.version }</span>
				</footer>
			</aside>

			<main className="rc-shell__main">
				<div className="rc-shell__card">
					<header className="rc-shell__header">
						<div className="rc-shell__brand">
							ReferralCandy
							{ pageTitle && (
								<>
									<span className="rc-shell__brand-sep">
										|
									</span>
									<span className="rc-shell__brand-page">
										{ pageTitle }
									</span>
								</>
							) }
						</div>
						<div className="rc-shell__actions">
							{ headerAction }
						</div>
					</header>
					<div className="rc-shell__body">{ children }</div>
				</div>
			</main>
		</div>
	);
}
