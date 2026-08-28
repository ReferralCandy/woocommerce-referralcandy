#!/usr/bin/env node
/**
 * Packages the plugin into dist/<folder>-<version>.zip for manual upload.
 *
 *   node scripts/package.mjs production   -> dist/woocommerce-referralcandy-3.0.0.zip
 *   node scripts/package.mjs staging      -> dist/woocommerce-referralcandy-3.0.0-staging.zip
 *                                            (plugin folder inside: woocommerce-referralcandy-staging)
 *
 * Run `pnpm run build` first; build/ must exist.
 *
 * Staging hosts are not in the repo (public). Every `define('WC_REFERRALCANDY_<X>_BASE', ...)`
 * in the main file gets its staging value from env WC_REFERRALCANDY_STAGING_<X>_BASE — put them
 * in a gitignored .env at the repo root (see .env.example) or export them in the shell.
 *
 * The staging flavor is the same code with (a) the three flavor defines at the top of the main
 * plugin file swapped, and (b) PHP class/function/constant names suffixed so it can be active
 * next to the production plugin on one store. The plugin folder differs for the same reason.
 * Everything that is user-visible or stored (integration id, option key, REST namespace, admin
 * path, checkout field ids, labels) derives from those defines at runtime, not from this script.
 */

import AdmZip from 'adm-zip';
import {
	existsSync,
	mkdirSync,
	readdirSync,
	readFileSync,
	statSync,
} from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = dirname( dirname( fileURLToPath( import.meta.url ) ) );

// .env is optional; shell environment wins over it.
try {
	process.loadEnvFile( join( ROOT, '.env' ) );
} catch {
	// no .env file
}
const MAIN = 'woocommerce-referralcandy.php';
const mainSource = readFileSync( join( ROOT, MAIN ), 'utf8' );

/**
 * One replacement per *_BASE define, value from the environment. Fails loudly if any is missing:
 * a staging zip that silently talks to production is the worst outcome.
 */
function stagingBaseReplacements() {
	const defines = [
		...mainSource.matchAll(
			/^define\('WC_REFERRALCANDY_([A-Z_]+_BASE)', '[^']*'\);$/gm
		),
	];
	const missing = [];
	const pairs = [];

	for ( const match of defines ) {
		const envName = `WC_REFERRALCANDY_STAGING_${ match[ 1 ] }`;
		const value = process.env[ envName ];
		// http is only tolerated for a local rc-main - loopback, or the gateway the
		// container reaches the host through. Anything remote must still be https.
		const isLocal =
			/^http:\/\/(localhost|127\.0\.0\.1|host\.docker\.internal)(:\d+)?(\/[^'\s]*)?$/.test(
				value || ''
			);
		if ( ! /^https:\/\/[^'\s]+$/.test( value || '' ) && ! isLocal ) {
			missing.push( envName );
			continue;
		}
		pairs.push( [
			match[ 0 ],
			`define('WC_REFERRALCANDY_${ match[ 1 ] }', '${ value }');`,
		] );
	}

	if ( missing.length ) {
		console.error(
			`Missing or not an https URL: ${ missing.join(
				', '
			) }. Add them to .env (see .env.example) or export them.`
		);
		process.exit( 1 );
	}

	return pairs;
}

// Files that ship. Keep in sync with .distignore (the WordPress.org deploy path).
const SHIPPED = [ MAIN, 'readme.txt', 'includes', 'build', 'languages' ];

const FLAVORS = {
	production: {
		folder: 'woocommerce-referralcandy',
		replacements: [],
	},
	staging: {
		folder: 'woocommerce-referralcandy-staging',
		replacements: [
			// Flavor defines (main file).
			[
				"define('WC_REFERRALCANDY_SUFFIX', '');",
				"define('WC_REFERRALCANDY_SUFFIX', '_staging');",
			],
			[
				"define('WC_REFERRALCANDY_LABEL', 'ReferralCandy');",
				"define('WC_REFERRALCANDY_LABEL', 'ReferralCandy (Staging)');",
			],
			[
				/^ \* Plugin Name: .*$/m,
				' * Plugin Name: ReferralCandy for WooCommerce (STAGING)',
			],
			// Coexistence renames: PHP symbols are global, so both plugins cannot declare the same ones.
			[
				/\bWC_Referralcandy_Integration\b/g,
				'WC_Referralcandy_Integration_Staging',
			],
			[ /\bWC_Referralcandy\b/g, 'WC_Referralcandy_Staging' ],
			[ /\bRC_Order\b/g, 'RC_Order_Staging' ],
			[ /\bRC_Admin\b/g, 'RC_Admin_Staging' ],
			[ /\bRC_Api\b/g, 'RC_Api_Staging' ],
			[ /\bwc_referralcandy_/g, 'wc_referralcandy_staging_' ],
			[ /\bWC_REFERRALCANDY_/g, 'WC_REFERRALCANDY_STAGING_' ],
			[ /\brc_plugin_links\b/g, 'rc_staging_plugin_links' ],
		],
	},
};

const flavorName = process.argv[ 2 ] || 'production';
const flavor = FLAVORS[ flavorName ];

if ( ! flavor ) {
	console.error(
		`Unknown flavor "${ flavorName }". Use one of: ${ Object.keys(
			FLAVORS
		).join( ', ' ) }`
	);
	process.exit( 1 );
}

if (
	! existsSync( join( ROOT, 'build', 'index.js' ) ) ||
	! existsSync( join( ROOT, 'build', 'index.asset.php' ) )
) {
	console.error( 'build/ is missing. Run `pnpm run build` first.' );
	process.exit( 1 );
}

const version = mainSource.match( /^ \* Version: (.+)$/m )[ 1 ].trim();

// *_BASE defines are swapped per environment; the rest of the map is static.
const replacements =
	flavorName === 'staging'
		? [ ...stagingBaseReplacements(), ...flavor.replacements ]
		: flavor.replacements;

function walk( path ) {
	return statSync( path ).isDirectory()
		? readdirSync( path ).flatMap( ( name ) => walk( join( path, name ) ) )
		: [ path ];
}

function transform( relPath, contents ) {
	if ( ! relPath.endsWith( '.php' ) ) {
		return contents;
	}

	let text = contents.toString( 'utf8' );

	for ( const [ from, to ] of replacements ) {
		if ( typeof from === 'string' && ! text.includes( from ) ) {
			continue;
		}
		text = text.replace( from, to );
	}

	return Buffer.from( text, 'utf8' );
}

const zip = new AdmZip();
const files = SHIPPED.flatMap( ( entry ) => walk( join( ROOT, entry ) ) );

for ( const file of files ) {
	const rel = relative( ROOT, file );
	zip.addFile(
		`${ flavor.folder }/${ rel }`,
		transform( rel, readFileSync( file ) )
	);
}

// Every flavor define must have been rewritten, otherwise the staging build is silently production.
if ( flavorName !== 'production' ) {
	const main = zip
		.getEntry( `${ flavor.folder }/${ MAIN }` )
		.getData()
		.toString( 'utf8' );
	for ( const [ from ] of replacements ) {
		if ( typeof from === 'string' && main.includes( from ) ) {
			console.error( `Replacement did not apply: ${ from }` );
			process.exit( 1 );
		}
	}
	// A missed rename is silent: the class_exists guards make the staging plugin reuse the
	// production class, so it talks to production hosts. Catch it here instead.
	for ( const entry of zip.getEntries() ) {
		if ( ! entry.entryName.endsWith( '.php' ) ) {
			continue;
		}

		const declarations = entry
			.getData()
			.toString( 'utf8' )
			.matchAll( /^\s*(?:class|function)\s+(RC_\w+|WC_Referralcandy\w*|wc_referralcandy_\w+)/gim );

		for ( const [ , symbol ] of declarations ) {
			if ( ! /_staging/i.test( symbol ) ) {
				console.error(
					`Unsuffixed global symbol in ${ entry.entryName }: ${ symbol }`
				);
				process.exit( 1 );
			}
		}
	}
}

mkdirSync( join( ROOT, 'dist' ), { recursive: true } );
// Zip name carries the flavor; the folder inside stays flavor-specific for coexistence.
const suffix = flavorName === 'production' ? '' : `-${ flavorName }`;
const out = join(
	ROOT,
	'dist',
	`woocommerce-referralcandy-${ version }${ suffix }.zip`
);
zip.writeZip( out );

console.log(
	`${ flavorName }: ${ relative( ROOT, out ) } (${ files.length } files)`
);
