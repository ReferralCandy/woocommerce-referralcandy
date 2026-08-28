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

		apiFetch( {
			path: `${ config.onboardingPath }/connection`,
			method: 'POST',
			data: ticket ? { ticket } : { statusToken },
		} )
			.then( ( result ) => {
				if ( ! result.connected ) {
					if ( result.reason === 'setup_incomplete' ) {
						// The store is attached, but its owner stopped before choosing a
						// plan. Point them back at the account they already have.
						setNotice( {
							status: 'warning',
							message: __(
								'Your store is linked, but your ReferralCandy account still needs a plan before referrals can run. Finish setting it up, then reload this page.',
								'woocommerce-referralcandy'
							),
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
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			);
	}, [ config.onboardingPath, config.restPath ] );

	useEffect( () => {
		apiFetch( { path: config.restPath } )
			.then( ( response ) => {
				setData( response );
				setDraft( response.values );
			} )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			);
	}, [ config.restPath ] );

	// Setup gate: without keys the app opens on the wizard; with keys the wizard is gone.
	useEffect( () => {
		if ( ! connected && ! skipSetup && section !== 'setup' ) {
			navigate( '/setup' );
		} else if ( connected && section === 'setup' ) {
			navigate( '/' );
		}
	}, [ connected, skipSetup, section, navigate ] );

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

	if ( ! data ) {
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
			signupStarted={ Boolean( onboarding?.signupStartedAt ) }
		>
			{ notice && data && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					className="rc-notice"
				>
					{ notice.message }
				</Notice>
			) }
			{ page }
		</Shell>
	);
}
