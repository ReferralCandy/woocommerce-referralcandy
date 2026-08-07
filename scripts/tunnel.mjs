#!/usr/bin/env node
/**
 * Exposes the running wp-env store over a public HTTPS tunnel with cloudflared.
 *
 * Two modes:
 *   - Named tunnel, when WP_TUNNEL_HOST and WP_TUNNEL_NAME are set. The hostname is known up
 *     front, so nothing needs discovering.
 *   - Quick tunnel otherwise. cloudflared assigns a random *.trycloudflare.com hostname, which we
 *     read from its metrics endpoint.
 *
 * The resolved hostname is written to dev/mu-plugins/.tunnel-host so that 00-tunnel-ssl.php can
 * emit correct URLs from contexts with no HTTP request, such as WP-Cron and `wp-env run cli`.
 *
 * This script is convenience only. Running cloudflared by hand works just as well; the mu-plugin
 * picks the host up from the forwarded headers.
 */

import { spawn } from 'node:child_process';
import { existsSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:net';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = dirname( dirname( fileURLToPath( import.meta.url ) ) );
const HOST_FILE = join( ROOT, 'dev', 'mu-plugins', '.tunnel-host' );
const DISCOVERY_TIMEOUT_MS = 30000;
const DISCOVERY_INTERVAL_MS = 500;

function readJson( path ) {
	if ( ! existsSync( path ) ) {
		return {};
	}

	try {
		return JSON.parse( readFileSync( path, 'utf8' ) );
	} catch ( error ) {
		console.warn( `Ignoring unreadable ${ path }: ${ error.message }` );
		return {};
	}
}

/** Mirrors how wp-env resolves its port: override file beats config file, env var beats both. */
function resolvePort() {
	if ( process.env.WP_ENV_PORT ) {
		return process.env.WP_ENV_PORT;
	}

	const override = readJson( join( ROOT, '.wp-env.override.json' ) );
	const config = readJson( join( ROOT, '.wp-env.json' ) );

	return override.port ?? config.port ?? 8888;
}

function sleep( ms ) {
	return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
}

/**
 * cloudflared defaults its metrics server to 127.0.0.1:20241 and exits if that is taken, which it
 * will be whenever another tunnel is already running on this machine. Hand it a free port instead.
 */
function findFreePort() {
	return new Promise( ( resolve, reject ) => {
		const probe = createServer();

		probe.on( 'error', reject );
		probe.listen( 0, '127.0.0.1', () => {
			const { port } = probe.address();

			probe.close( () => resolve( port ) );
		} );
	} );
}

/**
 * cloudflared prints the quick tunnel URL to stderr inside a decorative banner, which is brittle to
 * scrape. The metrics server exposes the same value as JSON, so use that instead.
 */
async function discoverQuickTunnelHost( child, metricsAddress ) {
	const deadline = Date.now() + DISCOVERY_TIMEOUT_MS;

	while ( Date.now() < deadline ) {
		if ( child.exitCode !== null ) {
			throw new Error( `cloudflared exited with code ${ child.exitCode } before a tunnel was assigned.` );
		}

		try {
			const response = await fetch( `http://${ metricsAddress }/quicktunnel` );

			if ( response.ok ) {
				const { hostname } = await response.json();

				if ( hostname ) {
					return hostname;
				}
			}
		} catch {
			// Metrics server is not listening yet.
		}

		await sleep( DISCOVERY_INTERVAL_MS );
	}

	throw new Error( `cloudflared did not report a hostname within ${ DISCOVERY_TIMEOUT_MS / 1000 }s.` );
}

function cleanUp() {
	if ( existsSync( HOST_FILE ) ) {
		rmSync( HOST_FILE, { force: true } );
	}
}

async function main() {
	const port = resolvePort();
	const origin = `http://localhost:${ port }`;
	const namedHost = process.env.WP_TUNNEL_HOST;
	const namedTunnel = process.env.WP_TUNNEL_NAME;

	if ( namedHost && ! namedTunnel ) {
		throw new Error(
			'WP_TUNNEL_HOST is set but WP_TUNNEL_NAME is not. Set WP_TUNNEL_NAME to the cloudflared ' +
				'tunnel name or UUID that serves that hostname, or unset WP_TUNNEL_HOST to use a quick tunnel.'
		);
	}

	const metricsAddress = `127.0.0.1:${ await findFreePort() }`;

	/*
	 * Named tunnels deliberately get no --url: that flag replaces the whole ingress from
	 * ~/.cloudflared/config.yml, so it would take every other hostname on the tunnel offline. The
	 * ingress entry for WP_TUNNEL_HOST is expected to point at the wp-env port already.
	 */
	const args = namedTunnel
		? [ 'tunnel', '--metrics', metricsAddress, 'run', namedTunnel ]
		: [ 'tunnel', '--url', origin, '--metrics', metricsAddress ];

	const child = spawn( 'cloudflared', args, { stdio: [ 'ignore', 'inherit', 'inherit' ] } );

	child.on( 'error', ( error ) => {
		if ( error.code === 'ENOENT' ) {
			console.error( 'cloudflared not found on PATH. Install it: https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/' );
		} else {
			console.error( error.message );
		}

		cleanUp();
		process.exit( 1 );
	} );

	const stop = ( signal ) => {
		cleanUp();

		if ( child.exitCode === null ) {
			child.kill( signal === 'SIGTERM' ? 'SIGTERM' : 'SIGINT' );
		}
	};

	process.on( 'SIGINT', () => stop( 'SIGINT' ) );
	process.on( 'SIGTERM', () => stop( 'SIGTERM' ) );
	process.on( 'exit', cleanUp );

	child.on( 'exit', ( code ) => {
		cleanUp();
		process.exit( code ?? 0 );
	} );

	let host;

	try {
		host = namedHost ?? ( await discoverQuickTunnelHost( child, metricsAddress ) );
	} catch ( error ) {
		console.error( error.message );
		stop( 'SIGTERM' );
		process.exit( 1 );
	}

	writeFileSync( HOST_FILE, host, 'utf8' );

	console.log( `\nStore is public at https://${ host } (proxying ${ origin })` );
	console.log( 'Press Ctrl-C to close the tunnel.\n' );
}

main().catch( ( error ) => {
	console.error( error.message );
	cleanUp();
	process.exit( 1 );
} );
