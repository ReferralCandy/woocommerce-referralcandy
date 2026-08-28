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

	// Read-only rather than absent: the merchant should be able to see the identifier their
	// tracking script is named after, and to notice when it is missing — but a value the
	// connection supplies is not theirs to retype, and a typo here silently breaks tracking.
	if ( field.readonly ) {
		return (
			<TextControl
				label={ title }
				hideLabelFromVision
				value={ value }
				readOnly
				onChange={ () => {} }
				help={ __(
					'Set automatically when your store connected.',
					'woocommerce-referralcandy'
				) }
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

/** One settings row: title + description on the left, the control on the right. */
export function SettingsRow( { id, field, value, onChange } ) {
	const isToggle = field.type === 'checkbox';
	// Checkbox fields carry their sentence in `label`; use it when there is no description.
	const description =
		field.description || ( field.title && field.label ? field.label : '' );

	return (
		<div className="rc-row">
			<div className="rc-row__text">
				<h3 className="rc-row__title">
					{ field.title || field.label || id }
				</h3>
				{ description && (
					<RawHTML className="rc-row__desc">{ description }</RawHTML>
				) }
			</div>
			<div
				className={ `rc-row__control${
					isToggle ? ' rc-row__control--toggle' : ''
				}` }
			>
				<Control
					id={ id }
					field={ field }
					value={ value }
					onChange={ onChange }
				/>
			</div>
		</div>
	);
}
