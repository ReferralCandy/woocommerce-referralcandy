import { createRoot } from '@wordpress/element';
import App from './app';
import './style.scss';

// Config is injected by RC_Admin::enqueue() on the plugin's own screen only.
const config = window.wcReferralCandyAdmin;
const root = config && document.getElementById( config.rootId );

if ( root ) {
	createRoot( root ).render( <App config={ config } /> );
}
