#!/usr/bin/env node
/**
 * Single source of truth for the plugin version.
 *
 * Four places carry it and they have drifted before: the plugin header, package.json, the readme's
 * Stable tag, and the git tag that triggers the deploy. This module reads and writes all of them
 * from one place, and `node scripts/version.mjs verify <tag>` is the CI guard that refuses to
 * publish when they disagree.
 *
 * "Tested up to" is checked too. It lives in both the plugin header and the readme, and only the
 * readme's copy reaches the WordPress.org listing, so a half-done bump is invisible until a
 * merchant notices the plugin claims to be untested on their WordPress.
 */

import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = dirname( dirname( fileURLToPath( import.meta.url ) ) );

export const PLUGIN_FILE = join( ROOT, 'woocommerce-referralcandy.php' );
export const README_FILE = join( ROOT, 'readme.txt' );
export const PACKAGE_FILE = join( ROOT, 'package.json' );

/** The files this module rewrites, relative to the repo root, for `git add`. */
export const VERSIONED_FILES = [ 'woocommerce-referralcandy.php', 'readme.txt', 'package.json' ];

const SEMVER = /^\d+\.\d+\.\d+$/;

/** " * Version: 3.0.1" in the plugin header. */
const HEADER_VERSION = /^(\s*\*\s*Version:[ \t]*)(\S+)([ \t]*)$/m;
/** " * Tested up to: 7.1" in the plugin header. */
const HEADER_TESTED = /^(\s*\*\s*Tested up to:[ \t]*)(\S+)([ \t]*)$/m;
/** "Stable tag: 3.0.1" in readme.txt. */
const README_STABLE = /^(Stable tag:[ \t]*)(\S+)([ \t]*)$/m;
/** "Tested up to: 7.1" in readme.txt. */
const README_TESTED = /^(Tested up to:[ \t]*)(\S+)([ \t]*)$/m;
/** The version line in package.json. Rewritten by regex so the file's own formatting survives. */
const PACKAGE_VERSION = /^([ \t]*"version":[ \t]*")([^"]+)(")/m;

function read( file ) {
	return readFileSync( file, 'utf8' );
}

function capture( text, pattern, label ) {
	const match = text.match( pattern );

	if ( ! match ) {
		throw new Error( `Could not find ${ label }` );
	}

	return match[ 2 ];
}

function replace( file, pattern, value, label ) {
	const text = read( file );

	if ( ! pattern.test( text ) ) {
		throw new Error( `Could not find ${ label }` );
	}

	writeFileSync( file, text.replace( pattern, `$1${ value }$3` ) );
}

/** Every version-ish value the release has to keep in step. */
export function readVersions() {
	const plugin = read( PLUGIN_FILE );
	const readme = read( README_FILE );

	return {
		header: capture( plugin, HEADER_VERSION, 'Version in the plugin header' ),
		stable: capture( readme, README_STABLE, 'Stable tag in readme.txt' ),
		package: capture( read( PACKAGE_FILE ), PACKAGE_VERSION, 'version in package.json' ),
		testedHeader: capture( plugin, HEADER_TESTED, 'Tested up to in the plugin header' ),
		testedReadme: capture( readme, README_TESTED, 'Tested up to in readme.txt' ),
	};
}

/** The readme changelog is hand-written, so all we can check is that the entry is there at all. */
export function changelogHas( version ) {
	const escaped = version.replace( /\./g, '\\.' );

	return new RegExp( `^=\\s*${ escaped }\\s*=\\s*$`, 'm' ).test( read( README_FILE ) );
}

export function writeVersion( version ) {
	replace( PLUGIN_FILE, HEADER_VERSION, version, 'Version in the plugin header' );
	replace( README_FILE, README_STABLE, version, 'Stable tag in readme.txt' );
	replace( PACKAGE_FILE, PACKAGE_VERSION, version, 'version in package.json' );
}

export function writeTested( tested ) {
	replace( PLUGIN_FILE, HEADER_TESTED, tested, 'Tested up to in the plugin header' );
	replace( README_FILE, README_TESTED, tested, 'Tested up to in readme.txt' );
}

/** Everything wrong with releasing `version` from the current tree. Empty means good to go. */
export function problems( version ) {
	const found = [];

	if ( ! SEMVER.test( version ) ) {
		found.push( `"${ version }" is not an X.Y.Z version` );
	}

	const versions = readVersions();

	if ( versions.header !== version ) {
		found.push( `plugin header Version is ${ versions.header }, expected ${ version }` );
	}

	if ( versions.package !== version ) {
		found.push( `package.json version is ${ versions.package }, expected ${ version }` );
	}

	if ( versions.stable !== version ) {
		found.push( `readme.txt Stable tag is ${ versions.stable }, expected ${ version }` );
	}

	if ( versions.testedHeader !== versions.testedReadme ) {
		found.push(
			`"Tested up to" disagrees: plugin header says ${ versions.testedHeader }, readme.txt says ${ versions.testedReadme }`
		);
	}

	if ( ! changelogHas( version ) ) {
		found.push( `readme.txt has no "= ${ version } =" changelog entry` );
	}

	return found;
}

/** Tags are written vX.Y.Z; every other file carries the bare version. */
export function versionFromTag( tag ) {
	return String( tag ?? '' ).trim().replace( /^v/, '' );
}

function main( argv ) {
	if ( argv[ 0 ] !== 'verify' ) {
		console.error( 'Usage: node scripts/version.mjs verify <tag>' );
		process.exit( 2 );
	}

	const version = versionFromTag( argv[ 1 ] );
	const found = problems( version );

	if ( found.length ) {
		for ( const problem of found ) {
			// The ::error:: prefix surfaces these on the workflow summary, not just the log.
			console.error( `::error::${ problem }` );
		}

		process.exit( 1 );
	}

	console.log( `${ version } agrees across the plugin header, package.json, readme.txt and the tag.` );
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	main( process.argv.slice( 2 ) );
}
