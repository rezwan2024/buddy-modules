/**
 * Build the distributable zip, respecting .distignore.
 *
 * Usage: node scripts/zip.js
 * Output: buddy-modules.zip in the plugin root.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { execSync } = require( 'child_process' );

const root = path.resolve( __dirname, '..' );
const zipName = 'buddy-modules.zip';
const tmpDir = path.join( require( 'os' ).tmpdir(), 'buddy-modules-dist' );

// Read .distignore patterns.
const distignore = fs
	.readFileSync( path.join( root, '.distignore' ), 'utf8' )
	.split( '\n' )
	.map( ( l ) => l.trim() )
	.filter( ( l ) => l && ! l.startsWith( '#' ) );

const excludes = distignore
	.map( ( p ) => `--exclude="${ p }"` )
	.join( ' ' );

execSync( `rm -rf "${ tmpDir }"` );
execSync( `mkdir -p "${ tmpDir }/buddy-modules"` );
execSync(
	`rsync -a ${ excludes } "${ root }/" "${ tmpDir }/buddy-modules/"`,
	{ stdio: 'inherit' }
);

const out = path.join( root, zipName );
execSync( `cd "${ tmpDir }" && zip -r "${ out }" buddy-modules`, {
	stdio: 'inherit',
} );
execSync( `rm -rf "${ tmpDir }"` );

console.log( `Built: ${ zipName }` );
