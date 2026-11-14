/**
 * Publish the assembled dist folder as one commit on the main branch and
 * point the release tag at it. Used by .github/workflows/release.yml.
 *
 * The commit is created through the GitHub API (not `git push`), so GitHub
 * signs it and it shows as Verified. Main always holds exactly the built
 * plugin; the previous main commit stays the parent, so release history is kept.
 *
 * Usage: node scripts/publish-dist.js <dist dir> <tag> <source sha>
 * Env:   GITHUB_TOKEN (contents: write), GITHUB_REPOSITORY (owner/repo),
 *        GITHUB_OUTPUT (optional, receives commit=<sha>)
 */

const fs = require( 'fs' );
const path = require( 'path' );

const [ distDir, tag, sourceSha ] = process.argv.slice( 2 );
const { GITHUB_TOKEN: token, GITHUB_REPOSITORY: repo } = process.env;
const branch = 'main';

if ( ! distDir || ! tag || ! sourceSha || ! token || ! repo ) {
	// eslint-disable-next-line no-console -- CLI script output.
	console.error(
		'Usage: GITHUB_TOKEN=… GITHUB_REPOSITORY=owner/repo node scripts/publish-dist.js <dist dir> <tag> <source sha>'
	);
	process.exit( 1 );
}

/**
 * Call the GitHub REST API.
 *
 * @param {string}  method   HTTP method.
 * @param {string}  endpoint Path below /repos/<repo>.
 * @param {Object=} body     JSON body.
 * @return {Promise<Object|null>} Parsed response, or null on 404 for GET.
 */
async function api( method, endpoint, body ) {
	for ( let attempt = 1; ; attempt++ ) {
		const res = await fetch(
			`https://api.github.com/repos/${ repo }${ endpoint }`,
			{
				method,
				headers: {
					Authorization: `Bearer ${ token }`,
					Accept: 'application/vnd.github+json',
					'X-GitHub-Api-Version': '2022-11-28',
					'Content-Type': 'application/json',
				},
				body: body ? JSON.stringify( body ) : undefined,
			}
		);
		if ( 404 === res.status && 'GET' === method ) {
			return null;
		}
		if ( res.ok ) {
			return res.json();
		}
		// Retry server errors and secondary rate limits a few times.
		if ( attempt < 4 && ( res.status >= 500 || 403 === res.status ) ) {
			await new Promise( ( r ) => setTimeout( r, attempt * 2000 ) );
			continue;
		}
		throw new Error(
			`${ method } ${ endpoint } → ${ res.status } ${ await res.text() }`
		);
	}
}

/**
 * List every file below a directory (relative paths, forward slashes).
 *
 * @param {string} dir  Directory to walk.
 * @param {string} base Path prefix for the results.
 * @return {string[]} Relative file paths.
 */
function walk( dir, base = '' ) {
	return fs
		.readdirSync( dir, { withFileTypes: true } )
		.flatMap( ( entry ) => {
			const rel = base ? `${ base }/${ entry.name }` : entry.name;
			if ( entry.isDirectory() ) {
				return walk( path.join( dir, entry.name ), rel );
			}
			return entry.isFile() ? [ rel ] : [];
		} );
}

/**
 * Run async jobs with limited concurrency.
 *
 * @param {Array}    items Items.
 * @param {number}   limit Parallel jobs.
 * @param {Function} job   Async job per item.
 * @return {Promise<Array>} Results in item order.
 */
async function pool( items, limit, job ) {
	const results = new Array( items.length );
	let next = 0;
	const workers = Array.from( { length: limit }, async () => {
		while ( next < items.length ) {
			const i = next++;
			results[ i ] = await job( items[ i ] );
		}
	} );
	await Promise.all( workers );
	return results;
}

( async () => {
	const files = walk( distDir );
	if ( ! files.includes( 'buddy-modules.php' ) ) {
		throw new Error( `${ distDir } does not contain buddy-modules.php` );
	}

	const tree = await pool( files, 8, async ( rel ) => {
		const full = path.join( distDir, rel );
		const blob = await api( 'POST', '/git/blobs', {
			content: fs.readFileSync( full ).toString( 'base64' ),
			encoding: 'base64',
		} );
		// eslint-disable-next-line no-bitwise -- file mode check.
		const executable = fs.statSync( full ).mode & 0o111;
		return {
			path: rel,
			mode: executable ? '100755' : '100644',
			type: 'blob',
			sha: blob.sha,
		};
	} );

	const newTree = await api( 'POST', '/git/trees', { tree } );
	const ref = await api( 'GET', `/git/ref/heads/${ branch }` );
	const parent = ref
		? await api( 'GET', `/git/commits/${ ref.object.sha }` )
		: null;

	let commitSha;
	if ( parent && parent.tree.sha === newTree.sha ) {
		// Re-run with identical files: keep the existing commit.
		commitSha = parent.sha;
	} else {
		const commit = await api( 'POST', '/git/commits', {
			message: `Release ${ tag }\n\nBuilt from ${ sourceSha } on dev.`,
			tree: newTree.sha,
			parents: parent ? [ parent.sha ] : [],
		} );
		commitSha = commit.sha;
	}

	if ( ref ) {
		await api( 'PATCH', `/git/refs/heads/${ branch }`, {
			sha: commitSha,
			force: true,
		} );
	} else {
		await api( 'POST', '/git/refs', {
			ref: `refs/heads/${ branch }`,
			sha: commitSha,
		} );
	}

	// The release tag points at the built files, so the release's
	// "Source code" downloads and the update checker's readme.txt are the plugin.
	const tagRef = await api( 'GET', `/git/ref/tags/${ tag }` );
	if ( tagRef ) {
		await api( 'PATCH', `/git/refs/tags/${ tag }`, {
			sha: commitSha,
			force: true,
		} );
	} else {
		await api( 'POST', '/git/refs', {
			ref: `refs/tags/${ tag }`,
			sha: commitSha,
		} );
	}

	/* eslint-disable no-console -- CLI script output. */
	console.log(
		`${ files.length } files → ${ branch } @ ${ commitSha } (tag ${ tag })`
	);
	/* eslint-enable no-console */
	if ( process.env.GITHUB_OUTPUT ) {
		fs.appendFileSync(
			process.env.GITHUB_OUTPUT,
			`commit=${ commitSha }\n`
		);
	}
} )().catch( ( error ) => {
	// eslint-disable-next-line no-console -- CLI script output.
	console.error( error.message );
	process.exit( 1 );
} );
