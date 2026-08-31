<?php
/**
 * Plugin Name: Dev Tunnel SSL
 * Description: Makes the wp-env store behave correctly when served over a public HTTPS tunnel.
 *
 * Two problems this solves:
 *
 * 1. cloudflared terminates TLS and forwards plain HTTP, so is_ssl() is false. WordPress then
 *    emits a redirect loop and WooCommerce REST key auth rejects requests.
 * 2. wp-env always defines WP_HOME/WP_SITEURL as http://localhost:PORT and force-appends its port
 *    even to values set in .wp-env.json, so generated URLs carry a :PORT the tunnel does not
 *    serve. Asset URLs are affected too, which leaves wc-admin React screens blank.
 *
 * Neither can be fixed through wp-env config, so the URLs are rewritten at runtime instead. The
 * stored siteurl option is never touched, so http://localhost:PORT keeps working at the same time
 * and rewrite rules never need flushing.
 *
 * Dev-only. Lives in dev/mu-plugins/ and is mapped into the container by .wp-env.json; it is not
 * part of the shipped plugin.
 */

namespace WooRC\Dev\TunnelSSL;

const HOST_FILE = __DIR__ . '/.tunnel-host';

/**
 * The forwarded headers are only trustworthy when the request actually came through the tunnel.
 * Docker publishes the wp-env port on 0.0.0.0, so anything on the LAN can forge X-Forwarded-Host
 * and poison generated URLs, including password reset links.
 */
function is_trusted_forwarded_request( $host ) {
	// The local origin is not a tunnel host; browsing http://localhost:PORT must stay untouched.
	if ( 'localhost' === $host || '127.0.0.1' === $host || '[::1]' === $host ) {
		return false;
	}

	if ( ! empty( $_SERVER['HTTP_CF_RAY'] ) || ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		return true;
	}

	foreach ( array( '.trycloudflare.com', '.share.zrok.io' ) as $suffix ) {
		if ( substr( $host, -strlen( $suffix ) ) === $suffix ) {
			return true;
		}
	}

	$configured = getenv( 'WP_TUNNEL_HOST' );

	return $configured && strtolower( $configured ) === $host;
}

/**
 * Resolve the public host this site should present itself as.
 *
 * Prefers the live request, and falls back to the file written by scripts/tunnel.mjs so that
 * contexts with no request at all - `wp-env run cli`, the WP-Cron loopback, Action Scheduler -
 * still emit tunnel URLs. Without that fallback, deferred work such as order emails and
 * ReferralCandy API payloads would embed localhost.
 */
function resolve_host() {
	static $host = null;

	if ( null !== $host ) {
		return $host;
	}

	$host = false;

	/*
	 * cloudflared does not send X-Forwarded-Host - it passes the public hostname through as Host -
	 * so the Host header is the primary signal here. X-Forwarded-Host is still honoured first for
	 * proxies that do set it.
	 */
	$candidate = '';

	foreach ( array( 'HTTP_X_FORWARDED_HOST', 'HTTP_HOST' ) as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) {
			continue;
		}

		$candidate = strtolower( trim( $_SERVER[ $header ] ) );

		// A comma separated chain means several proxies; the first entry is the original host.
		if ( false !== strpos( $candidate, ',' ) ) {
			$candidate = trim( strtok( $candidate, ',' ) );
		}

		// Drop any port; the tunnel always serves 443.
		$candidate = preg_replace( '/:\d+$/', '', $candidate );

		if ( $candidate ) {
			break;
		}
	}

	if ( $candidate && is_trusted_forwarded_request( $candidate ) ) {
		$host = $candidate;

		return $host;
	}

	/*
	 * The file is a fallback for contexts with no request to read a host from - WP-CLI, and cron or
	 * Action Scheduler runs invoked through it. It must not apply to real requests: a browser on
	 * http://localhost:PORT would otherwise be handed tunnel URLs while a tunnel happens to be up.
	 */
	if ( 'cli' !== PHP_SAPI && ! empty( $_SERVER['HTTP_HOST'] ) ) {
		return $host;
	}

	if ( is_readable( HOST_FILE ) ) {
		$stored = strtolower( trim( (string) file_get_contents( HOST_FILE ) ) );

		if ( $stored ) {
			$host = $stored;
		}
	}

	return $host;
}

/**
 * Swap the scheme and host of a WordPress generated URL for the tunnel's, dropping wp-env's port.
 */
function normalize_url( $url ) {
	$host = resolve_host();

	if ( ! $host || ! is_string( $url ) || '' === $url ) {
		return $url;
	}

	// Protocol relative and root relative URLs carry no host to replace.
	if ( 0 === strpos( $url, '//' ) || 0 === strpos( $url, '/' ) ) {
		return $url;
	}

	$parts = wp_parse_url( $url );

	if ( empty( $parts['host'] ) ) {
		return $url;
	}

	$rebuilt = 'https://' . $host;

	if ( isset( $parts['path'] ) ) {
		$rebuilt .= $parts['path'];
	}

	if ( isset( $parts['query'] ) ) {
		$rebuilt .= '?' . $parts['query'];
	}

	if ( isset( $parts['fragment'] ) ) {
		$rebuilt .= '#' . $parts['fragment'];
	}

	return $rebuilt;
}

if ( resolve_host() ) {
	if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
		$_SERVER['HTTPS']       = 'on';
		$_SERVER['SERVER_PORT'] = 443;
	}

	if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
		$_SERVER['HTTP_HOST'] = resolve_host();
	}

	/*
	 * home_url/site_url and friends cover page and REST URLs. The plugins_url/content_url/
	 * includes_url/*_loader_src group is separate and just as necessary: WordPress derives
	 * WP_CONTENT_URL and WP_PLUGIN_URL from the port carrying siteurl before mu-plugins load, so
	 * without these the browser is handed :PORT asset URLs it cannot reach over 443.
	 */
	$url_filters = array(
		'home_url',
		'site_url',
		'admin_url',
		'rest_url',
		'option_home',
		'option_siteurl',
		'plugins_url',
		'content_url',
		'includes_url',
		'script_loader_src',
		'style_loader_src',
	);

	foreach ( $url_filters as $filter ) {
		add_filter( $filter, __NAMESPACE__ . '\normalize_url', PHP_INT_MAX );
	}

	// upload_dir hands back an array, so it needs its own pass. WooCommerce builds
	// placeholderImgSrc from it, which otherwise reaches the browser as localhost:PORT.
	add_filter(
		'upload_dir',
		function ( $uploads ) {
			foreach ( array( 'url', 'baseurl' ) as $key ) {
				if ( ! empty( $uploads[ $key ] ) ) {
					$uploads[ $key ] = normalize_url( $uploads[ $key ] );
				}
			}

			return $uploads;
		},
		PHP_INT_MAX
	);
}
