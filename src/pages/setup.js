import { Button, Icon, Spinner } from '@wordpress/components';
import { check, closeSmall } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';

import { SettingsRow } from '../fields';

const KEY_FIELDS = [ 'api_id', 'app_id', 'secret_key' ];

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

function CreateAccount( { onboarding, links, starting, onStart, navigate } ) {
	const storeHost = host( onboarding.storeUrl );
	const canStart = onboarding.https && onboarding.canAuthorize && ! starting;

	return (
		<>
			<div className="rc-content">
				<p className="rc-hero__eyebrow">
					{ __( 'Step 1 of 3', 'woocommerce-referralcandy' ) }
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
							"You'll approve access to %s in WooCommerce, choose a password, and pick a plan on ReferralCandy. Then come back here to finish.",
							'woocommerce-referralcandy'
						),
						storeHost
					) }
				</p>

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
					<Button
						variant="link"
						onClick={ () => navigate( '/setup/keys' ) }
					>
						{ __(
							'I already have an account',
							'woocommerce-referralcandy'
						) }
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
							'Sign up on ReferralCandy instead →',
							'woocommerce-referralcandy'
						) }
					</a>
				</p>
			</aside>
		</>
	);
}

function ExistingAccount( { onboarding, links, starting, onStart, navigate } ) {
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
						'If you already connected this store in ReferralCandy, confirm it below. Otherwise, log in to get the API keys and enter them here.',
						'woocommerce-referralcandy'
					) }
				</p>

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
					</div>
				</div>

				<div className="rc-panel">
					<h5>
						{ __(
							'Connected with API keys instead?',
							'woocommerce-referralcandy'
						) }
					</h5>
					<p>
						{ __(
							'In ReferralCandy go to Integrations → WooCommerce and copy the API Access ID, App ID and Secret Key.',
							'woocommerce-referralcandy'
						) }
					</p>
					<div className="rc-actions">
						<Button
							variant="secondary"
							href={ links.integrations }
							target="_blank"
							rel="noreferrer"
						>
							{ __(
								'Open ReferralCandy ↗',
								'woocommerce-referralcandy'
							) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => navigate( '/setup/keys' ) }
						>
							{ __(
								'Enter API keys',
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

function EnterKeys( { onboarding, fields, values, onChange, links, onSkip } ) {
	const startedMinutes = onboarding?.signupStartedAt
		? Math.max(
				1,
				Math.round(
					( Date.now() / 1000 - onboarding.signupStartedAt ) / 60
				)
		  )
		: null;

	return (
		<>
			<div className="rc-content">
				<p className="rc-hero__eyebrow">
					{ __( 'Step 3 of 3', 'woocommerce-referralcandy' ) }
				</p>
				<h1 className="rc-page__title rc-page__title--large">
					{ __(
						'Finish: enter your API keys',
						'woocommerce-referralcandy'
					) }
				</h1>
				<p className="rc-page__desc rc-page__desc--wide">
					{ __(
						'Copy the three values from Integrations → WooCommerce in ReferralCandy. The plugin uses them to send orders and load the tracking script.',
						'woocommerce-referralcandy'
					) }
				</p>

				{ startedMinutes !== null && (
					<div className="rc-banner">
						<div>
							<strong>
								{ __(
									'Welcome back.',
									'woocommerce-referralcandy'
								) }
							</strong>{ ' ' }
							{ sprintf(
								/* translators: %d: minutes */
								__(
									'You started signup from this store %d min ago. Your keys are on the Integrations page —',
									'woocommerce-referralcandy'
								),
								startedMinutes
							) }{ ' ' }
							<a
								href={ links.integrations }
								target="_blank"
								rel="noreferrer"
							>
								{ __(
									'open it ↗',
									'woocommerce-referralcandy'
								) }
							</a>
						</div>
					</div>
				) }

				{ KEY_FIELDS.filter( ( key ) => fields[ key ] ).map(
					( key ) => (
						<SettingsRow
							key={ key }
							id={ key }
							field={ fields[ key ] }
							value={ values[ key ] ?? '' }
							onChange={ ( v ) => onChange( key, v ) }
						/>
					)
				) }

				<div className="rc-actions">
					<Button variant="link" onClick={ onSkip }>
						{ __( 'Skip for now', 'woocommerce-referralcandy' ) }
					</Button>
				</div>
			</div>

			<aside className="rc-tips">
				<h4>{ __( 'Verify', 'woocommerce-referralcandy' ) }</h4>
				<p>
					{ __(
						'Save & verify calls ReferralCandy with your keys and confirms they are accepted before showing the dashboard.',
						'woocommerce-referralcandy'
					) }
				</p>
				<p>
					{ __(
						'If a key is rejected you will see why, and the dashboard keeps flagging it until fixed.',
						'woocommerce-referralcandy'
					) }
				</p>
				<hr />
				<h4>
					{ __( 'Where are my keys?', 'woocommerce-referralcandy' ) }
				</h4>
				<p>
					<a
						href={ links.integrations }
						target="_blank"
						rel="noreferrer"
					>
						{ __(
							'Integrations → WooCommerce ↗',
							'woocommerce-referralcandy'
						) }
					</a>
				</p>
			</aside>
		</>
	);
}

export default function Setup( props ) {
	const { step, onboarding } = props;

	if ( step === 'keys' ) {
		return <EnterKeys { ...props } />;
	}

	if ( ! onboarding ) {
		return (
			<div className="rc-content">
				<Spinner />
			</div>
		);
	}

	return onboarding.storeExists === true ? (
		<ExistingAccount { ...props } />
	) : (
		<CreateAccount { ...props } />
	);
}
