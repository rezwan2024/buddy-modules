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

// -X drops extra file attributes, so the zip is the same on macOS and Linux.
execFileSync( 'zip', [ '-rqX', zipFile, slug ], {
	cwd: distDir,
	stdio: 'inherit',
} );

// eslint-disable-next-line no-console -- CLI build script output.
console.log( `Built: dist/${ slug }/ and dist/${ slug }.zip` );
