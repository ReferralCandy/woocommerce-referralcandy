import { __ } from '@wordpress/i18n';
import { SettingsRow } from '../fields';

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
					.map( ( key ) => (
						<SettingsRow
							key={ key }
							id={ key }
							field={ fields[ key ] }
							value={ values[ key ] ?? '' }
							onChange={ ( v ) => onChange( key, v ) }
						/>
					) ) }
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
