import { RawHTML } from '@wordpress/element';
import { FormToggle, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function Control( { id, field, value, onChange } ) {
	const title = field.title || field.label || id;

	if ( field.type === 'checkbox' ) {
		return (
			<FormToggle
				checked={ value === 'yes' }
				onChange={ ( e ) =>
					onChange( e.target.checked ? 'yes' : 'no' )
				}
				aria-label={ title }
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
				label={ title }
				hideLabelFromVision
				value={ value }
				options={ options }
				onChange={ onChange }
				__nextHasNoMarginBottom
			/>
		);
	}

	return (
		<TextControl
			label={ title }
			hideLabelFromVision
			placeholder={ field.placeholder }
			type={ id === 'secret_key' ? 'password' : 'text' }
			value={ value }
			onChange={ onChange }
			__nextHasNoMarginBottom
		/>
	);
}

export default function Settings( { group, fields, values, onChange } ) {
	return (
		<>
			<div className="rc-content">
				<h1 className="rc-page__title">{ group.title }</h1>
				{ group.description && (
					<p className="rc-page__desc">{ group.description }</p>
				) }

				{ group.fields
					.filter( ( key ) => fields[ key ] )
					.filter(
						( key ) =>
							key !== 'popup_campaign_key' ||
							values.popup === 'yes'
					)
					.map( ( key ) => {
						const field = fields[ key ];
						const isToggle = field.type === 'checkbox';
						// Checkbox fields carry their sentence in `label`; use it when there is no description.
						const description =
							field.description ||
							( field.title && field.label ? field.label : '' );
						return (
							<div className="rc-row" key={ key }>
								<div className="rc-row__text">
									<h3 className="rc-row__title">
										{ field.title || field.label || key }
									</h3>
									{ description && (
										<RawHTML className="rc-row__desc">
											{ description }
										</RawHTML>
									) }
								</div>
								<div
									className={ `rc-row__control${
										isToggle
											? ' rc-row__control--toggle'
											: ''
									}` }
								>
									<Control
										id={ key }
										field={ field }
										value={ values[ key ] ?? '' }
										onChange={ ( v ) => onChange( key, v ) }
									/>
								</div>
							</div>
						);
					} ) }
			</div>

			{ group.tips.length > 0 && (
				<aside className="rc-tips">
					<h4>{ __( 'Tips', 'woocommerce-referralcandy' ) }</h4>
					{ group.tips.map( ( tip, i ) => (
						<p key={ i }>{ tip }</p>
					) ) }
				</aside>
			) }
		</>
	);
}
