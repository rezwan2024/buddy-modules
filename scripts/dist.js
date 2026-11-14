/**
 * Assemble the distributable plugin, respecting .distignore.
 *
 * Usage: node scripts/dist.js   (run `npm run build` first; `npm run zip` does both)
 * Output:
 *   dist/buddy-modules/       the built plugin (what the release workflow pushes to main)
 *   dist/buddy-modules.zip    the same folder zipped (the release asset)
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const slug = 'buddy-modules';
const root = path.resolve( __dirname, '..' );
const distDir = path.join( root, 'dist' );
const pluginDir = path.join( distDir, slug );
const zipFile = path.join( distDir, `${ slug }.zip` );

// The built admin bundle must exist, or the zip installs a plugin with no UI.
if ( ! fs.existsSync( path.join( root, 'build', 'admin.js' ) ) ) {
	// eslint-disable-next-line no-console -- CLI build script output.
	console.error( 'build/admin.js is missing. Run `npm run build` first.' );
	process.exit( 1 );
}

fs.rmSync( distDir, { recursive: true, force: true } );
fs.mkdirSync( pluginDir, { recursive: true } );

execFileSync(
	'rsync',
	[
		'-a',
		`--exclude-from=${ path.join( root, '.distignore' ) }`,
		`${ root }/`,
		`${ pluginDir }/`,
	],
	{ stdio: 'inherit' }
);

// main and the zip get the user-facing readme; README.md on dev is for development.
// Its <!-- changelog --> marker is filled from the readme.txt changelog.
fs.writeFileSync(
	path.join( pluginDir, 'README.md' ),
	fs
		.readFileSync( path.join( root, 'README.dist.md' ), 'utf8' )
		.replace( '<!-- changelog -->', changelogMarkdown() )
);

// -X drops extra file attributes, so the zip is the same on macOS and Linux.
execFileSync( 'zip', [ '-rqX', zipFile, slug ], {
	cwd: distDir,
	stdio: 'inherit',
} );

/**
 * The readme.txt changelog as Markdown: a heading per version, plain bullets,
 * and the "New:" / "Fix:" / "Improvement:" labels in bold.
 *
 * @return {string} Markdown.
 */
function changelogMarkdown() {
	const readme = fs.readFileSync( path.join( root, 'readme.txt' ), 'utf8' );
	const section = (
		readme.split( /^== Changelog ==\s*$/m )[ 1 ] || ''
	).split( /^== .* ==\s*$/m )[ 0 ];
	const out = [];

	for ( const line of section.split( '\n' ) ) {
		const heading = line.match( /^= (.+) =\s*$/ );
		const bullet = line.match( /^\*\s+(.*)$/ );

		if ( heading ) {
			out.push( '', `### Version ${ heading[ 1 ].trim() }`, '' );
		} else if ( bullet ) {
			out.push( '- ' + bullet[ 1 ].replace( /^(\w+):\s/, '**$1:** ' ) );
		}
	}

	return out.join( '\n' ).trim();
}

// eslint-disable-next-line no-console -- CLI build script output.
console.log( `Built: dist/${ slug }/ and dist/${ slug }.zip` );
