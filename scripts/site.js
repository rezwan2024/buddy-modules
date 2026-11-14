/**
 * Build the GitHub Pages landing page into _site/.
 *
 * Usage: node scripts/site.js   (npm run site)
 * Copies site/ and docs/screenshots/ (as images/), and fills {{VERSION}},
 * {{WP}} and {{PHP}} from the readme.txt headers.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const out = path.join( root, '_site' );
const readme = fs.readFileSync( path.join( root, 'readme.txt' ), 'utf8' );

const header = ( name ) => {
	const match = readme.match( new RegExp( `^${ name }:\\s*(.+)$`, 'mi' ) );
	if ( ! match ) {
		throw new Error( `readme.txt has no "${ name }" header.` );
	}
	return match[ 1 ].trim();
};

const values = {
	VERSION: header( 'Stable tag' ),
	WP: header( 'Requires at least' ),
	PHP: header( 'Requires PHP' ),
};

fs.rmSync( out, { recursive: true, force: true } );
fs.cpSync( path.join( root, 'site' ), out, { recursive: true } );
fs.cpSync(
	path.join( root, 'docs', 'screenshots' ),
	path.join( out, 'images' ),
	{ recursive: true }
);

const index = path.join( out, 'index.html' );
const html = fs
	.readFileSync( index, 'utf8' )
	.replace( /\{\{(VERSION|WP|PHP)\}\}/g, ( match, key ) => values[ key ] );
fs.writeFileSync( index, html );

// eslint-disable-next-line no-console -- CLI build script output.
console.log( `Built _site/ (version ${ values.VERSION })` );
