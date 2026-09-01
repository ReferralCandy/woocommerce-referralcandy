import { Button, Icon, Spinner } from '@wordpress/components';
import { check, closeSmall } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';


function Check( { ok, label, message } ) {
	const state = ok === null ? 'wait' : ok ? 'ok' : 'bad';
	return (
		<li>
			<span className={ `rc-status__icon rc-status__icon--${ state }` }>
				{ ok === null ? (
					<span>·</span>
				) : (
					<Icon icon={ ok ? check : closeSmall } size={ 18 } />
				) }
			</span>
			<span className="rc-status__label">{ label }</span>
			{ message && (
				<span className="rc-status__message">{ message }</span>
			) }
		</li>
	);
}

function host( url ) {
	try {
		return new URL( url ).host;
	} catch {
		return url;
	}
}

function CreateAccount( { onboarding, links, starting, onStart, onSkip } ) {
	const storeHost = host( onboarding.storeUrl );
	const canStart = onboarding.https && onboarding.canAuthorize && ! starting;

	return (
		<>
			<div className="rc-content">
				{ /* The sidebar owns the step count. A second one in the page body drifts
				     out of step with it — the third step is not always the same step. */ }
				<p className="rc-hero__eyebrow">
					{ __( 'Get started', 'woocommerce-referralcandy' ) }
				</p>
				<h1 className="rc-page__title rc-page__title--large">
					{ __(
						'Create your ReferralCandy account',
						'woocommerce-referralcandy'
					) }
				</h1>
				<p className="rc-page__desc rc-page__desc--wide">
					{ sprintf(
						/* translators: %s: store host name */
						__(
							"You'll approve access to %s in WooCommerce, choose a password, and pick a plan on ReferralCandy. You are brought back here automatically when that is done.",
							'woocommerce-referralcandy'
						),
						storeHost
					) }
				</p>

				<ResumeBanner
					minutes={ startedMinutesAgo( onboarding ) }
					links={ links }
				/>

				<ul className="rc-status rc-status--compact">
					<Check
						ok={ onboarding.https }
						label={ __(
							'Store is served over HTTPS',
							'woocommerce-referralcandy'
						) }
						message={
							onboarding.https
								? ''
								: __(
										'WooCommerce only shares API access over a secure connection. Ask your host to enable HTTPS for this store, then reload this page.',
										'woocommerce-referralcandy'
								  )
						}
					/>
					<Check
						ok={ onboarding.canAuthorize }
						label={ __(
							'You can manage WooCommerce on this site',
							'woocommerce-referralcandy'
						) }
					/>
					<Check
						ok={ onboarding.storeExists === null ? null : true }
						label={
							onboarding.storeExists === null
								? __(
										'Could not check for an existing account',
										'woocommerce-referralcandy'
								  )
								: __(
										'No ReferralCandy account found for this store',
										'woocommerce-referralcandy'
								  )
						}
					/>
				</ul>

				<div className="rc-actions">
					<Button
						variant="primary"
						isBusy={ starting }
						disabled={ ! canStart }
						onClick={ onStart }
					>
						{ __(
							'Create account & connect store',
							'woocommerce-referralcandy'
						) }
					</Button>
					<Button variant="link" onClick={ onSkip }>
						{ __( 'Skip for now', 'woocommerce-referralcandy' ) }
					</Button>
				</div>
			</div>

			<aside className="rc-tips">
				<h4>
					{ __( 'What happens next', 'woocommerce-referralcandy' ) }
				</h4>
				<ol className="rc-steps">
					<li>
						{ __(
							'WooCommerce asks you to approve ReferralCandy — one click, no key copying.',
							'woocommerce-referralcandy'
						) }
					</li>
					<li>
						{ __(
							'Set a password and pick a plan on ReferralCandy.',
							'woocommerce-referralcandy'
						) }
					</li>
					<li>
						{ __(
							"You're sent back here to finish.",
							'woocommerce-referralcandy'
						) }
					</li>
				</ol>
				<hr />
				<h4>{ __( 'Requirements', 'woocommerce-referralcandy' ) }</h4>
				<p>
					{ __(
						'A public HTTPS store and a WordPress administrator with WooCommerce access.',
						'woocommerce-referralcandy'
					) }
				</p>
				<p>
					<a href={ links.signup } target="_blank" rel="noreferrer">
						{ __(
							'Sign up without connecting this store →',
							'woocommerce-referralcandy'
						) }
					</a>
					<br />
					{ __(
						'You would then have to connect this store yourself afterwards. Kept for stores ReferralCandy cannot match by address.',
						'woocommerce-referralcandy'
					) }
				</p>
			</aside>
		</>
	);
}

function ExistingAccount( { onboarding, links, starting, onStart, onSkip } ) {
	// The same approval that creates an account also proves this store to ReferralCandy, so
	// a store already connected through WooCommerce can be recognised by running it again —
	// it creates nothing the second time, and the plugin comes back knowing it is connected.
	const canConnect = onboarding.https && onboarding.canAuthorize && ! starting;
	return (
		<>
			<div className="rc-content">
				<p className="rc-hero__eyebrow">
					{ __( 'Account found', 'woocommerce-referralcandy' ) }
				</p>
				<h1 className="rc-page__title rc-page__title--large">
					{ sprintf(
						/* translators: %s: store host name */
						__(
							'%s already has a ReferralCandy account',
							'woocommerce-referralcandy'
						),
						host( onboarding.storeUrl )
					) }
				</h1>
				<p className="rc-page__desc rc-page__desc--wide">
					{ __(
						'Approve access once and this store is linked to that account. Nothing new is created, and there are no keys to copy.',
						'woocommerce-referralcandy'
					) }
				</p>

				<ResumeBanner
					minutes={ startedMinutesAgo( onboarding ) }
					links={ links }
				/>

				<div className="rc-panel">
					<h5>
						{ __(
							'Already connected in ReferralCandy?',
							'woocommerce-referralcandy'
						) }
					</h5>
					<p>
						{ __(
							'Confirm the connection by approving access once more. Nothing is created, and no API keys are needed.',
							'woocommerce-referralcandy'
						) }
					</p>
					{ ! canConnect && ! starting && (
						<ul className="rc-status rc-status--compact">
							<Check
								ok={ onboarding.https }
								label={ __(
									'Store is served over HTTPS',
									'woocommerce-referralcandy'
								) }
							/>
							<Check
								ok={ onboarding.canAuthorize }
								label={ __(
									'You can manage WooCommerce on this site',
									'woocommerce-referralcandy'
								) }
							/>
						</ul>
					) }
					<div className="rc-actions">
						<Button
							variant="primary"
							isBusy={ starting }
							disabled={ ! canConnect }
							onClick={ onStart }
						>
							{ __(
								'Confirm connection',
								'woocommerce-referralcandy'
							) }
						</Button>
						<Button variant="link" onClick={ onSkip }>
							{ __(
								'Skip for now',
								'woocommerce-referralcandy'
							) }
						</Button>
					</div>
				</div>

				<div className="rc-panel">
					<h5>
						{ __(
							'Not your account?',
							'woocommerce-referralcandy'
						) }
					</h5>
					<p>
						{ __(
							'A store can belong to one ReferralCandy account only. If this store was registered by mistake, contact support.',
							'woocommerce-referralcandy'
						) }
					</p>
				</div>
			</div>

			<aside className="rc-tips">
				<h4>
					{ __( 'Why no sign up here', 'woocommerce-referralcandy' ) }
				</h4>
				<p>
					{ __(
						'Each store URL maps to a single ReferralCandy account, so creating another one would fail after the approval step.',
						'woocommerce-referralcandy'
					) }
				</p>
				<hr />
				<h4>{ __( 'Resources', 'woocommerce-referralcandy' ) }</h4>
				<p>
					<a href={ links.help } target="_blank" rel="noreferrer">
						{ __( 'Help center →', 'woocommerce-referralcandy' ) }
					</a>
				</p>
			</aside>
		</>
	);
}

/** Minutes since this store began a signup, or null if it never did. */
function startedMinutesAgo( onboarding ) {
	return onboarding?.signupStartedAt
		? Math.max(
				1,
				Math.round(
					( Date.now() / 1000 - onboarding.signupStartedAt ) / 60
				)
		  )
		: null;
}

/**
 * Tells a merchant who wandered off mid-signup where they were.
 *
 * Without it, someone who bailed at the plan picker and came back the next day is shown a
 * pristine "Create your ReferralCandy account", which invites them to start a second signup
 * for a store that is already half way through one.
 */
function ResumeBanner( { minutes, links } ) {
	if ( minutes === null ) return null;

	return (
		<div className="rc-banner">
			<div>
				<strong>
					{ __( 'Welcome back.', 'woocommerce-referralcandy' ) }
				</strong>{ ' ' }
				{ sprintf(
					/* translators: %d: minutes */
					__(
						'You started connecting this store %d min ago. Carry on below, or finish in ReferralCandy —',
						'woocommerce-referralcandy'
					),
					minutes
				) }{ ' ' }
				<a href={ links.dashboard } target="_blank" rel="noreferrer">
					{ __( 'open it ↗', 'woocommerce-referralcandy' ) }
				</a>
			</div>
		</div>
	);
}

/**
 * The store is connected to an account that has not chosen a plan.
 *
 * Everything technical is done; nothing will happen until it is paid for. Persisted server-side,
 * so this survives the reload that a dismissible notice does not.
 */
function FinishSetup( { onboarding, links } ) {
	return (
		<>
			<div className="rc-content">
				<p className="rc-hero__eyebrow">
					{ __( 'Almost there', 'woocommerce-referralcandy' ) }
				</p>
				<h1 className="rc-page__title rc-page__title--large">
					{ __(
						'Your ReferralCandy account needs a plan',
						'woocommerce-referralcandy'
					) }
				</h1>
				<p className="rc-page__desc rc-page__desc--wide">
					{ sprintf(
						/* translators: %s: store host name */
						__(
							'%s is connected and your account exists. Referrals start once a plan is chosen — nothing here needs redoing.',
							'woocommerce-referralcandy'
						),
						host( onboarding.storeUrl )
					) }
				</p>

				<div className="rc-actions">
					<Button
						variant="primary"
						href={ links.plans }
						target="_blank"
						rel="noreferrer"
					>
						{ __(
							'Choose a plan ↗',
							'woocommerce-referralcandy'
						) }
					</Button>
				</div>

				<div className="rc-panel">
					<h5>
						{ __(
							'Already chose one?',
							'woocommerce-referralcandy'
						) }
					</h5>
					<p>
						{ __(
							'Reload this page. The plugin re-checks with ReferralCandy each time it opens, and will show your store as connected.',
							'woocommerce-referralcandy'
						) }
					</p>
				</div>
			</div>
		</>
	);
}

export default function Setup( props ) {
	const { onboarding } = props;

	if ( ! onboarding ) {
		return (
			<div className="rc-content">
				<Spinner />
			</div>
		);
	}

	// Linked and unpaid is its own state. Offering "Confirm connection" here asks the merchant
	// to redo the step that worked, and hides the one that did not.
	if ( props.pendingSetup ) {
		return <FinishSetup { ...props } />;
	}

	return onboarding.storeExists === true ? (
		<ExistingAccount { ...props } />
	) : (
		<CreateAccount { ...props } />
	);
}
