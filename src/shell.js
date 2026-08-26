import { Icon } from '@wordpress/components';
import {
	chevronLeft,
	chevronRight,
	cog,
	help,
	home,
	wordpress,
} from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

function NavItem( { icon, label, active, onClick, arrow } ) {
	return (
		<button
			type="button"
			className={ `rc-shell__nav-item${
				active ? ' rc-shell__nav-item--active' : ''
			}` }
			onClick={ onClick }
		>
			{ icon && <Icon icon={ icon } size={ 24 } /> }
			<span className="rc-shell__nav-label">{ label }</span>
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

export default function Shell( {
	config,
	section,
	sub,
	groups,
	navigate,
	pageTitle,
	headerAction,
	children,
} ) {
	const inSettings = section === 'settings';

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

				{ inSettings ? (
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
				) : (
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
