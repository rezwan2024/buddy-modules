/**
 * Print the readme.txt changelog entry for one version (release notes).
 *
 * Usage: node scripts/changelog.js 2.0.1
 * Exits 1 when readme.txt has no "= <version> =" entry under "== Changelog ==".
 */

const fs = require( 'fs' );
const path = require( 'path' );

const version = ( process.argv[ 2 ] || '' ).replace( /^v/, '' );
if ( ! version ) {
	// eslint-disable-next-line no-console -- CLI script output.
	console.error( 'Usage: node scripts/changelog.js <version>' );
	process.exit( 1 );
}

const readme = fs.readFileSync(
	path.join( __dirname, '..', 'readme.txt' ),
	'utf8'
);
const changelog = readme.split( /^== Changelog ==\s*$/m )[ 1 ] || '';
const lines = changelog.split( '\n' );
const entry = [];
let inEntry = false;

for ( const line of lines ) {
	if ( /^== .* ==\s*$/.test( line ) ) {
		break; // Next readme section.
	}
	const heading = line.match( /^= (.+) =\s*$/ );
	if ( heading ) {
		if ( inEntry ) {
			break; // Next version.
		}
		inEntry = heading[ 1 ].trim() === version;
		continue;
	}
	if ( inEntry ) {
		entry.push( line );
	}
}

const notes = entry.join( '\n' ).trim();
if ( ! notes ) {
	// eslint-disable-next-line no-console -- CLI script output.
	console.error( `readme.txt has no changelog entry for ${ version }.` );
	process.exit( 1 );
}

// eslint-disable-next-line no-console -- CLI script output.
console.log( notes );
