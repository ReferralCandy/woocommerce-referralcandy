import { useCallback, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import Shell from './shell';
import { GROUPS, groupsFor } from './groups';
import Overview from './pages/overview';
import Settings from './pages/settings';
import Setup from './pages/setup';
import Help from './pages/help';

/** Tiny hash router: "#/settings/checkout" -> ["settings", "checkout"]. */
function useRoute() {
	const read = () =>
		window.location.hash
			.replace( /^#\/?/, '' )
			.split( '/' )
			.filter( Boolean );
	const [ route, setRoute ] = useState( read );

	useEffect( () => {
		const onChange = () => setRoute( read() );
		window.addEventListener( 'hashchange', onChange );
		return () => window.removeEventListener( 'hashchange', onChange );
	}, [] );

	const navigate = useCallback( ( path ) => {
		window.location.hash = path;
	}, [] );

	return [ route, navigate ];
}

const hasKeys = ( values ) =>
	Boolean( values && values.api_id && values.secret_key );

export default function App( { config } ) {
	const [ route, navigate ] = useRoute();
	const [ data, setData ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ onboarding, setOnboarding ] = useState( null );
	const [ starting, setStarting ] = useState( false );
	const [ skipSetup, setSkipSetup ] = useState( false );
	/** { proof, failed? } while a returning merchant's ticket is being exchanged. */
	const [ connecting, setConnecting ] = useState( null );

	const [ section = 'overview', sub ] = route;
	// A wc-auth connected store granted ReferralCandy its own WooCommerce credentials and
	// never holds API keys, so it is connected without them.
	const platformConnected = data
		? Boolean( data.platformConnected )
		: Boolean( config.platformConnected );
	const connected =
		platformConnected ||
		( data ? hasKeys( data.values ) : config.hasCredentials );

	/**
	 * ReferralCandy appends the signup ticket to the URL it returns the merchant to. Exchange
	 * it once, server-side, then drop it from the address bar so a reload cannot replay it.
	 *
	 * The ticket is kept in state as well, because stripping the URL means a reload cannot
	 * retry: if the exchange fails, the retry has to come from here.
	 */
	useEffect( () => {
		const params = new URLSearchParams( window.location.search );
		// rc_signup is the signup nonce, handed back by an approval that ended here directly.
		// rc_token is the durable status token, used when the merchant went the long way round
		// through onboarding and payment first, by which time the nonce is long expired.
		const ticket = params.get( 'rc_signup' );
		const statusToken = params.get( 'rc_token' );
		if ( ! ticket && ! statusToken ) return;

		params.delete( 'rc_signup' );
		params.delete( 'rc_token' );
		const query = params.toString();
		window.history.replaceState(
			{},
			'',
			window.location.pathname +
				( query ? `?${ query }` : '' ) +
				window.location.hash
		);

		setConnecting( { proof: ticket ? { ticket } : { statusToken } } );
	}, [] );

	/** Runs the exchange, and again if the merchant retries a failed one. */
	useEffect( () => {
		if ( ! connecting?.proof || connecting.failed ) return;

		apiFetch( {
			path: `${ config.onboardingPath }/connection`,
			method: 'POST',
			data: connecting.proof,
		} )
			.then( ( result ) => {
				if ( ! result.connected ) {
					// Anything other than "you have not paid yet" means the approval did not
					// produce a connection. The merchant just clicked Approve, so silence
					// here reads as the plugin losing their work — but what to offer them
					// depends on why, and retrying a dead ticket never succeeds.
					setConnecting(
						result.reason === 'setup_incomplete'
							? null
							: ( current ) => ( {
									...current,
									failed:
										result.outcome === 'unreachable'
											? 'unreachable'
											: 'rejected',
							  } )
					);

					if ( result.reason === 'setup_incomplete' ) {
						// The store is attached, but its owner stopped before choosing a
						// plan. Send them to the plan picker: telling them to "reload" is
						// telling them to repeat the thing that did not work.
						setNotice( {
							status: 'warning',
							message: __(
								'Your store is linked, but your ReferralCandy account still needs a plan before referrals can run.',
								'woocommerce-referralcandy'
							),
							actions: [
								{
									label: __(
										'Choose a plan',
										'woocommerce-referralcandy'
									),
									url: config.links.plans,
								},
							],
						} );
					}
					return;
				}
				// Re-read rather than patching local state: the status list and the settings
				// groups are both derived server-side from the flag just written.
				return apiFetch( { path: config.restPath } ).then(
					( refreshed ) => {
						setData( refreshed );
						setDraft( refreshed.values );
						setConnecting( null );
						setNotice( {
							status: 'success',
							message: __(
								'Your store is connected to ReferralCandy.',
								'woocommerce-referralcandy'
							),
						} );
					}
				);
			} )
			.catch( () =>
				// The plugin's own REST call failed, so this never reached ReferralCandy.
				// Kept, not cleared: the URL no longer holds the ticket, so this state is the
				// only thing that can offer a retry.
				setConnecting( ( current ) => ( {
					...current,
					failed: 'unreachable',
				} ) )
			);
	}, [ connecting, config.onboardingPath, config.restPath ] );

	useEffect( () => {
		apiFetch( { path: config.restPath } )
			.then( ( response ) => {
				setData( response );
				setDraft( response.values );
				// Paint first, then check. The app mounts once per page load, so this is one
				// call per visit, not one per navigation: the hash router never remounts it.
				refreshConnection();
			} )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			);
	}, [ config.restPath ] );

	// A failed exchange must not trap the merchant. It holds the whole screen, so navigating
	// anywhere is them saying they are done with it — the ticket is dead either way, and the
	// setup screens can still connect the store.
	useEffect( () => {
		if ( connecting?.failed ) setConnecting( null );
		// Only when the route changes, hence the deliberately narrow dependency list.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ section, sub ] );

	// Setup gate: without keys the app opens on the wizard; with keys the wizard is gone.
	// Suspended while a ticket is in flight — the merchant is mid-connection, and bouncing
	// them into the wizard would show setup steps they are in the middle of completing.
	useEffect( () => {
		if ( connecting ) return;
		if ( ! connected && ! skipSetup && section !== 'setup' ) {
			navigate( '/setup' );
		} else if ( connected && section === 'setup' ) {
			navigate( '/' );
		}
	}, [ connected, skipSetup, section, navigate, connecting ] );

	useEffect( () => {
		if ( section === 'setup' && ! onboarding ) {
			apiFetch( { path: config.onboardingPath } )
				.then( setOnboarding )
				.catch( ( e ) =>
					setNotice( { status: 'error', message: e.message } )
				);
		}
	}, [ section, onboarding, config.onboardingPath ] );

	const dirty =
		data &&
		draft &&
		JSON.stringify( draft ) !== JSON.stringify( data.values );

	const save = async () => {
		const response = await apiFetch( {
			path: config.restPath,
			method: 'POST',
			data: draft,
		} );
		setData( response );
		setDraft( response.values );
		return response;
	};

	const saveSettings = () => {
		setSaving( true );
		setNotice( null );
		save()
			.then( () =>
				setNotice( {
					status: 'success',
					message: __(
						'Settings saved.',
						'woocommerce-referralcandy'
					),
				} )
			)
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			)
			.finally( () => setSaving( false ) );
	};

	const saveAndVerify = async () => {
		setSaving( true );
		setNotice( null );
		try {
			await save();
			const result = await apiFetch( {
				path: `${ config.onboardingPath }/verify`,
				method: 'POST',
			} );
			// Status list is computed server-side from the (now refreshed) verification.
			const refreshed = await apiFetch( { path: config.restPath } );
			setData( refreshed );
			setDraft( refreshed.values );
			if ( result.ok ) {
				setNotice( {
					status: 'success',
					message: __(
						'Connected. Your API keys were verified.',
						'woocommerce-referralcandy'
					),
				} );
				navigate( '/' );
			} else {
				setNotice( { status: 'error', message: result.message } );
			}
		} catch ( e ) {
			setNotice( { status: 'error', message: e.message } );
		} finally {
			setSaving( false );
		}
	};

	/**
	 * Re-asks ReferralCandy after the screen has painted, so a change made in the dashboard is
	 * already reflected by the time the merchant looks. Forced, because opening this screen is
	 * itself the asking; silent on failure, because nobody pressed anything and what is already
	 * on screen remains the best answer available.
	 */
	const refreshConnection = () =>
		apiFetch( {
			path: `${ config.onboardingPath }/refresh`,
			method: 'POST',
			data: { force: true },
		} )
			.then( ( response ) => {
				setData( response );
				setDraft( response.values );
			} )
			.catch( () => {} );

	const startSignup = async () => {
		setStarting( true );
		setNotice( null );
		try {
			const result = await apiFetch( {
				path: `${ config.onboardingPath }/start`,
				method: 'POST',
			} );
			window.location.assign( result.redirectUrl );
		} catch ( e ) {
			setNotice( { status: 'error', message: e.message } );
			setStarting( false );
		}
	};

	const groups = data
		? groupsFor( data.fields, platformConnected )
		: GROUPS;

	// "#/settings" lands on the first group.
	useEffect( () => {
		if ( section === 'settings' && ! sub && groups.length ) {
			navigate( `/settings/${ groups[ 0 ].key }` );
		}
	}, [ section, sub, groups, navigate ] );

	let page = null;
	let pageTitle = '';
	let headerAction = null;

	if ( connecting ) {
		// A merchant who just approved access is watching this. Anything else on screen —
		// least of all "enter your API keys", which this kind of store never has — reads as
		// the connection having failed.
		pageTitle = __( 'Connecting', 'woocommerce-referralcandy' );
		page = connecting.failed ? (
			<div className="rc-content">
				<h1 className="rc-page__title">
					{ __(
						'Could not confirm the connection',
						'woocommerce-referralcandy'
					) }
				</h1>
				<p className="rc-page__desc">
					{ connecting.failed === 'unreachable'
						? __(
								'Your store approved access, but ReferralCandy could not be reached to confirm it. Nothing is lost — try again.',
								'woocommerce-referralcandy'
						  )
						: __(
								'This connection link is no longer valid. Links expire a few minutes after they are issued, and each one can be used once. Approving access again takes a moment and creates nothing new.',
								'woocommerce-referralcandy'
						  ) }
				</p>
				<div className="rc-actions">
					{ connecting.failed === 'unreachable' ? (
						<Button
							variant="primary"
							onClick={ () =>
								setConnecting( ( current ) => ( {
									proof: current.proof,
								} ) )
							}
						>
							{ __(
								'Try again',
								'woocommerce-referralcandy'
							) }
						</Button>
					) : (
						<Button
							variant="primary"
							isBusy={ starting }
							disabled={ starting }
							onClick={ () => {
								setConnecting( null );
								startSignup();
							} }
						>
							{ __(
								'Restart connection',
								'woocommerce-referralcandy'
							) }
						</Button>
					) }
				</div>
			</div>
		) : (
			<div className="rc-content">
				<Spinner />
				<p className="rc-page__desc">
					{ __(
						'Connecting your store…',
						'woocommerce-referralcandy'
					) }
				</p>
			</div>
		);
	} else if ( ! data ) {
		page = notice ? (
			<Notice status={ notice.status } isDismissible={ false }>
				{ notice.message }
			</Notice>
		) : (
			<Spinner />
		);
	} else if ( section === 'setup' ) {
		pageTitle = __( 'Setup', 'woocommerce-referralcandy' );
		if ( sub === 'keys' ) {
			headerAction = (
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ saving || ! hasKeys( draft ) }
					onClick={ saveAndVerify }
				>
					{ __( 'Save & verify', 'woocommerce-referralcandy' ) }
				</Button>
			);
		}
		page = (
			<Setup
				step={ sub }
				onboarding={ onboarding }
				links={ config.links }
				navigate={ navigate }
				starting={ starting }
				onStart={ startSignup }
				fields={ data.fields }
				values={ draft }
				onChange={ ( key, value ) =>
					setDraft( { ...draft, [ key ]: value } )
				}
				onSkip={ () => {
					setSkipSetup( true );
					navigate( '/' );
				} }
			/>
		);
	} else if ( section === 'settings' ) {
		const group = groups.find( ( g ) => g.key === sub ) || groups[ 0 ];
		pageTitle = group.title;
		headerAction = (
			<Button
				variant="primary"
				isBusy={ saving }
				disabled={ saving || ! dirty }
				onClick={ saveSettings }
			>
				{ __( 'Save settings', 'woocommerce-referralcandy' ) }
			</Button>
		);
		page = (
			<Settings
				group={ group }
				fields={ data.fields }
				values={ draft }
				onChange={ ( key, value ) =>
					setDraft( { ...draft, [ key ]: value } )
				}
			/>
		);
	} else if ( section === 'help' ) {
		pageTitle = __( 'Help', 'woocommerce-referralcandy' );
		page = <Help links={ config.links } />;
	} else {
		pageTitle = __( 'Overview', 'woocommerce-referralcandy' );
		page = (
			<Overview
				status={ data.status }
				platformConnected={ platformConnected }
				campaigns={ data.campaigns || [] }
				links={ config.links }
				navigate={ ( to ) => {
					if ( to.startsWith( '/setup' ) ) {
						setSkipSetup( false );
					}
					navigate( to );
				} }
			/>
		);
	}

	return (
		<Shell
			config={ config }
			section={ section }
			sub={ sub }
			groups={ groups }
			navigate={ navigate }
			pageTitle={ pageTitle }
			headerAction={ headerAction }
			accountExists={
				connected || onboarding?.storeExists === true
			}
			connected={ connected }
		>
			{ notice && data && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					className="rc-notice"
					{ ...( notice.actions
						? { actions: notice.actions }
						: {} ) }
				>
					{ notice.message }
				</Notice>
			) }
			{ page }
		</Shell>
	);
}
