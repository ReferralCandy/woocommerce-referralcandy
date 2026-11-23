#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

import {
	VERSIONED_REPO_PATHS,
	changelogHas,
	releaseProblems,
	readVersions,
	versionFromTag,
	writeTested,
	writeVersion,
} from './version.mjs';

const ROOT = dirname( dirname( fileURLToPath( import.meta.url ) ) );

/**
 * Returns git's output with only the trailing newline removed. Not trimmed: `status --porcelain`
 * puts meaning in the first two columns, and the leading space of an unstaged change is part of it.
 */
function git( ...args ) {
	return execFileSync( 'git', args, { cwd: ROOT, encoding: 'utf8' } ).replace( /\n$/, '' );
}

function fail( message ) {
	console.error( `Aborted: ${ message }` );
	process.exit( 1 );
}

function parseArgs( argv ) {
	const rest = [];
	let tested;

	for ( let i = 0; i < argv.length; i++ ) {
		if ( argv[ i ] === '--tested' ) {
			tested = argv[ ++i ];
			continue;
		}

		rest.push( argv[ i ] );
	}

	return { version: versionFromTag( rest[ 0 ] ), tested };
}

function assertNothingUnrelatedPending() {
	const pending = git( 'status', '--porcelain' )
		.split( '\n' )
		.filter( Boolean )
		.map( ( line ) => line.slice( 3 ).trim() )
		.filter( ( path ) => ! VERSIONED_REPO_PATHS.includes( path ) );

	if ( pending.length ) {
		fail(
			`uncommitted changes outside the version files:\n  ${ pending.join(
				'\n  '
			) }\nCommit or stash them first, so the release commit carries only the bump.`
		);
	}
}

function main( argv ) {
	const { version, tested } = parseArgs( argv );

	if ( ! version ) {
		fail( 'no version given. Usage: pnpm run release 3.0.2 [--tested 7.1]' );
	}

	const tag = `v${ version }`;

	if ( git( 'tag', '--list', tag ).trim() ) {
		fail( `tag ${ tag } already exists` );
	}

	assertNothingUnrelatedPending();

	if ( ! changelogHas( version ) ) {
		fail(
			`readme.txt has no "= ${ version } =" changelog entry.\nWrite the entry under == Changelog ==, then run this again.`
		);
	}

	const before = readVersions();

	writeVersion( version );

	if ( tested ) {
		writeTested( tested );
	}

	const remaining = releaseProblems( version );

	if ( remaining.length ) {
		fail( `the tree is still inconsistent after the bump:\n  ${ remaining.join( '\n  ' ) }` );
	}

	git( 'add', '--', ...VERSIONED_REPO_PATHS );

	if ( ! git( 'diff', '--cached', '--name-only' ).trim() ) {
		fail( `nothing to commit — the tree already reads ${ version }` );
	}

	git( 'commit', '-m', `chore: release ${ version }` );
	git( 'tag', '-a', tag, '-m', version );

	console.log( `${ before.header } -> ${ version }` );

	if ( tested ) {
		console.log( `Tested up to ${ before.testedReadme } -> ${ tested }` );
	}

	console.log( `Committed and tagged ${ tag }.\n` );
	console.log( 'Publish to WordPress.org with:' );
	console.log( '  git push origin master --follow-tags' );
}

main( process.argv.slice( 2 ) );
