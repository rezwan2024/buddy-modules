/**
 * Print the download count of every GitHub release asset.
 *
 * Usage: npm run stats   (needs the GitHub CLI, `gh`; the repo is public,
 * so any logged-in account works)
 */

const { execFileSync } = require( 'child_process' );

const repo = 'rezwan2024/buddy-modules';

let releases;
try {
	const out = execFileSync(
		'gh',
		[
			'api',
			'--paginate',
			'--slurp',
			`repos/${ repo }/releases?per_page=100`,
		],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'inherit' ] }
	);
	releases = JSON.parse( out ).flat();
} catch ( error ) {
	// eslint-disable-next-line no-console -- CLI script output.
	console.error(
		`Could not read releases from ${ repo }: ${ error.message }`
	);
	process.exit( 1 );
}

const rows = [];
let total = 0;

for ( const release of releases ) {
	const published = ( release.published_at || '' ).slice( 0, 10 );
	const flags = [ release.draft && 'draft', release.prerelease && 'pre' ]
		.filter( Boolean )
		.join( ',' );
	if ( ! release.assets.length ) {
		rows.push( [ release.tag_name, published, flags, '(no assets)', 0 ] );
	}
	for ( const asset of release.assets ) {
		rows.push( [
			release.tag_name,
			published,
			flags,
			asset.name,
			asset.download_count,
		] );
		total += asset.download_count;
	}
}

const header = [ 'Release', 'Published', 'Flags', 'Asset', 'Downloads' ];
const table = [ header, ...rows ].map( ( row ) => row.map( String ) );
const widths = header.map( ( _, i ) =>
	Math.max( ...table.map( ( row ) => row[ i ].length ) )
);
const format = ( row ) =>
	row
		.map( ( cell, i ) =>
			i === row.length - 1
				? cell.padStart( widths[ i ] )
				: cell.padEnd( widths[ i ] )
		)
		.join( '  ' );

/* eslint-disable no-console -- CLI script output. */
console.log( format( table[ 0 ] ) );
console.log( widths.map( ( w ) => '-'.repeat( w ) ).join( '  ' ) );
table.slice( 1 ).forEach( ( row ) => console.log( format( row ) ) );
console.log(
	`\n${ releases.length } release(s), ${ total } download(s) in total.`
);
/* eslint-enable no-console */
