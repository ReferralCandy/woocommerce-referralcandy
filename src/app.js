import { useCallback, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import Shell from './shell';
import { GROUPS, groupsFor } from './groups';
import Overview from './pages/overview';
import Settings from './pages/settings';
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

export default function App( { config } ) {
	const [ route, navigate ] = useRoute();
	const [ data, setData ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ saving, setSaving ] = useState( false );

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

	const dirty =
		data &&
		draft &&
		JSON.stringify( draft ) !== JSON.stringify( data.values );

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: config.restPath, method: 'POST', data: draft } )
			.then( ( response ) => {
				setData( response );
				setDraft( response.values );
				setNotice( {
					status: 'success',
					message: __(
						'Settings saved.',
						'woocommerce-referralcandy'
					),
				} );
			} )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			)
			.finally( () => setSaving( false ) );
	};

	const [ section = 'overview', sub ] = route;
	const groups = data ? groupsFor( data.fields ) : GROUPS;

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
	} else if ( section === 'settings' ) {
		const group = groups.find( ( g ) => g.key === sub ) || groups[ 0 ];
		pageTitle = group.title;
		headerAction = (
			<Button
				variant="primary"
				isBusy={ saving }
				disabled={ saving || ! dirty }
				onClick={ save }
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
				links={ config.links }
				navigate={ navigate }
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
