import { addFilter } from '@wordpress/hooks';
import { RawHTML, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function Field( { id, field, value, onChange } ) {
	const help = field.description ? (
		<RawHTML>{ field.description }</RawHTML>
	) : undefined;

	if ( field.type === 'checkbox' ) {
		return (
			<ToggleControl
				label={ field.title || field.label }
				help={ help }
				checked={ value === 'yes' }
				onChange={ ( checked ) => onChange( checked ? 'yes' : 'no' ) }
				__nextHasNoMarginBottom
			/>
		);
	}

	if ( field.type === 'select' ) {
		const options = Object.entries( field.options || {} ).map(
			( [ v, label ] ) => ( { value: v, label } )
		);
		// Keep a stored value that is no longer an option selectable so the form can still save.
		if ( value && ! field.options?.[ value ] ) {
			options.unshift( {
				value,
				label: `${ value } (${ __(
					'no longer available',
					'woocommerce-referralcandy'
				) })`,
			} );
		}
		return (
			<SelectControl
				label={ field.title }
				help={ help }
				value={ value }
				options={ options }
				onChange={ onChange }
				__nextHasNoMarginBottom
			/>
		);
	}

	return (
		<TextControl
			label={ field.title }
			help={ help }
			placeholder={ field.placeholder }
			type={ id === 'secret_key' ? 'password' : 'text' }
			value={ value }
			onChange={ onChange }
			__nextHasNoMarginBottom
		/>
	);
}

function Settings( { restPath } ) {
	const [ data, setData ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		apiFetch( { path: restPath } )
			.then( setData )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			);
	}, [ restPath ] );

	if ( ! data ) {
		return notice ? (
			<Notice status={ notice.status } isDismissible={ false }>
				{ notice.message }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const setValue = ( key, value ) =>
		setData( { ...data, values: { ...data.values, [ key ]: value } } );

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: restPath, method: 'POST', data: data.values } )
			.then( ( response ) => {
				setData( { ...data, values: response.values } );
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

	return (
		<div
			className="wc-referralcandy-settings"
			style={ { maxWidth: 760, padding: 24 } }
		>
			<RawHTML>{ data.intro }</RawHTML>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ Object.entries( data.fields ).map( ( [ key, field ] ) => {
				if (
					key === 'popup_campaign_key' &&
					data.values.popup !== 'yes'
				) {
					return null;
				}
				return (
					<div key={ key } style={ { marginBottom: 20 } }>
						<Field
							id={ key }
							field={ field }
							value={ data.values[ key ] ?? '' }
							onChange={ ( v ) => setValue( key, v ) }
						/>
					</div>
				);
			} ) }

			<Button
				variant="primary"
				isBusy={ saving }
				disabled={ saving }
				onClick={ save }
			>
				{ __( 'Save changes', 'woocommerce-referralcandy' ) }
			</Button>
		</div>
	);
}

// One page per config pushed by PHP (RC_Admin::enqueue). Production and a staging copy of the
// plugin share this bundle, so each registers its own { id, title, path, restPath }.
const configs = window.wcReferralCandyPages || [];

addFilter( 'woocommerce_admin_pages_list', 'referralcandy', ( pages ) => {
	configs.forEach( ( cfg ) => {
		pages.push( {
			container: () => <Settings restPath={ cfg.restPath } />,
			path: cfg.path,
			breadcrumbs: [ cfg.title ],
			navArgs: { id: cfg.id },
			capability: 'manage_woocommerce',
		} );
	} );
	return pages;
} );
