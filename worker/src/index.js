/**
 * Buddy Modules update Worker.
 *
 * GET /update  Plugin Update Checker metadata (latest GitHub release). Each
 *              request also records the hashed site ID and the plugin, WP and
 *              PHP versions in D1, one row per site.
 * GET /stats   Active installs by version (needs STATS_TOKEN).
 */

/* global caches */
/* eslint-disable no-console -- console.error is the Workers log. */

const CACHE_SECONDS = 600;
const DAY = 86400;
const HASH_RE = /^[a-f0-9]{64}$/;
const VERSION_RE = /^[0-9A-Za-z.+-]{1,24}$/;

export default {
	async fetch( request, env, ctx ) {
		const url = new URL( request.url );

		if ( request.method !== 'GET' && request.method !== 'HEAD' ) {
			return new Response( 'Method not allowed', { status: 405 } );
		}

		if ( url.pathname === '/update' ) {
			return handleUpdate( url, env, ctx );
		}

		if ( url.pathname === '/stats' ) {
			return handleStats( request, url, env );
		}

		return new Response( 'Not found', { status: 404 } );
	},
};

/**
 * Record the check-in and return the update metadata.
 *
 * @param {URL}    url Request URL.
 * @param {Object} env Worker bindings.
 * @param {Object} ctx Execution context (waitUntil).
 * @return {Promise<Response>} JSON response.
 */
async function handleUpdate( url, env, ctx ) {
	const site = url.searchParams.get( 'site' ) || '';

	if ( HASH_RE.test( site ) && env.DB ) {
		ctx.waitUntil(
			recordInstall( env, site, {
				plugin: cleanVersion(
					url.searchParams.get( 'installed_version' )
				),
				wp: cleanVersion( url.searchParams.get( 'wp' ) ),
				php: cleanVersion( url.searchParams.get( 'php' ) ),
			} ).catch( ( error ) => console.error( 'record failed', error ) )
		);
	}

	try {
		const metadata = await getMetadata( env, ctx );
		return Response.json( metadata, {
			headers: { 'Cache-Control': 'no-store' },
		} );
	} catch ( error ) {
		console.error( 'metadata failed', error );
		return Response.json(
			{ error: 'Release information is unavailable.' },
			{ status: 502 }
		);
	}
}

/**
 * Keep a version string only when it looks like a version.
 *
 * @param {string|null} value Raw value.
 * @return {string|null} Clean value or null.
 */
function cleanVersion( value ) {
	return value && VERSION_RE.test( value ) ? value : null;
}

/**
 * Insert or update the row for one site.
 *
 * @param {Object} env      Worker bindings.
 * @param {string} site     SHA-256 site hash.
 * @param {Object} versions Plugin, WP and PHP versions.
 * @return {Promise} D1 result.
 */
function recordInstall( env, site, versions ) {
	const now = Math.floor( Date.now() / 1000 );

	return env.DB.prepare(
		`INSERT INTO installs (site_hash, plugin_version, wp_version, php_version, first_seen, last_seen)
		VALUES (?1, ?2, ?3, ?4, ?5, ?5)
		ON CONFLICT (site_hash) DO UPDATE SET
			plugin_version = COALESCE(excluded.plugin_version, plugin_version),
			wp_version = COALESCE(excluded.wp_version, wp_version),
			php_version = COALESCE(excluded.php_version, php_version),
			last_seen = excluded.last_seen`
	)
		.bind( site, versions.plugin, versions.wp, versions.php, now )
		.run();
}

/**
 * Build the metadata from the latest release, cached for CACHE_SECONDS so
 * GitHub's API rate limit is never reached.
 *
 * @param {Object} env Worker bindings.
 * @param {Object} ctx Execution context (waitUntil).
 * @return {Promise<Object>} Plugin Update Checker metadata.
 */
async function getMetadata( env, ctx ) {
	const cache = caches.default;
	const key = new Request(
		`https://bdm-cache.internal/metadata/${ env.GITHUB_REPO }`
	);
	const cached = await cache.match( key );

	if ( cached ) {
		return cached.json();
	}

	const metadata = await buildMetadata( env );

	ctx.waitUntil(
		cache.put(
			key,
			Response.json( metadata, {
				headers: { 'Cache-Control': `max-age=${ CACHE_SECONDS }` },
			} )
		)
	);

	return metadata;
}

/**
 * Read the latest release and readme.txt from GitHub.
 *
 * @param {Object} env Worker bindings.
 * @return {Promise<Object>} Plugin Update Checker metadata.
 */
async function buildMetadata( env ) {
	const headers = {
		Accept: 'application/vnd.github+json',
		'User-Agent': 'buddy-modules-update-worker',
	};

	if ( env.GITHUB_TOKEN ) {
		headers.Authorization = `Bearer ${ env.GITHUB_TOKEN }`;
	}

	const [ releaseResponse, readmeResponse ] = await Promise.all( [
		fetch(
			`https://api.github.com/repos/${ env.GITHUB_REPO }/releases/latest`,
			{ headers }
		),
		fetch(
			`https://raw.githubusercontent.com/${ env.GITHUB_REPO }/main/readme.txt`,
			{ headers: { 'User-Agent': headers[ 'User-Agent' ] } }
		),
	] );

	if ( ! releaseResponse.ok ) {
		throw new Error( `GitHub release: HTTP ${ releaseResponse.status }` );
	}

	const release = await releaseResponse.json();
	const asset = ( release.assets || [] ).find(
		( item ) => item.name === env.RELEASE_ASSET
	);

	if ( ! asset ) {
		throw new Error(
			`Release ${ release.tag_name } has no ${ env.RELEASE_ASSET }`
		);
	}

	const readme = readmeResponse.ok ? await readmeResponse.text() : '';
	const header = ( name ) => {
		const match = readme.match(
			new RegExp( `^${ name }:\\s*(.+)$`, 'mi' )
		);
		return match ? match[ 1 ].trim() : undefined;
	};

	return {
		name: 'Buddy Modules',
		slug: 'buddy-modules',
		version: release.tag_name.replace( /^v/, '' ),
		download_url: asset.browser_download_url,
		homepage: `https://github.com/${ env.GITHUB_REPO }`,
		author: 'rezwan2024',
		requires: header( 'Requires at least' ),
		tested: header( 'Tested up to' ),
		requires_php: header( 'Requires PHP' ),
		last_updated: release.published_at,
		sections: {
			description: escapeHtml( readmeShortDescription( readme ) ),
			changelog: releaseNotesToHtml( release.body || '' ),
		},
	};
}

/**
 * The one-line summary under the readme.txt header block.
 *
 * @param {string} readme readme.txt contents.
 * @return {string} Summary.
 */
function readmeShortDescription( readme ) {
	const blocks = readme.split( /\n\s*\n/ );
	return blocks.length > 1 ? blocks[ 1 ].trim() : 'Buddy Modules';
}

/**
 * Turn the release notes (simple Markdown: bullets, bold, code) into HTML.
 * The build note after the "---" line is left out.
 *
 * @param {string} body Release body.
 * @return {string} HTML.
 */
function releaseNotesToHtml( body ) {
	const notes = body.split( /\n-{3,}\n/ )[ 0 ];
	const inline = ( text ) =>
		escapeHtml( text )
			.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
			.replace( /`(.+?)`/g, '<code>$1</code>' );

	let html = '';
	let inList = false;

	for ( const raw of notes.split( '\n' ) ) {
		const line = raw.trim();
		const bullet = line.match( /^[*-]\s+(.*)$/ );

		if ( bullet ) {
			html +=
				( inList ? '' : '<ul>' ) +
				`<li>${ inline( bullet[ 1 ] ) }</li>`;
			inList = true;
			continue;
		}

		if ( inList ) {
			html += '</ul>';
			inList = false;
		}

		const heading = line.match( /^#+\s+(.*)$/ );
		if ( heading ) {
			html += `<h4>${ inline( heading[ 1 ] ) }</h4>`;
		} else if ( line ) {
			html += `<p>${ inline( line ) }</p>`;
		}
	}

	return html + ( inList ? '</ul>' : '' );
}

/**
 * Escape text for HTML.
 *
 * @param {string} text Raw text.
 * @return {string} Escaped text.
 */
function escapeHtml( text ) {
	return String( text )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&#39;' );
}

/**
 * Stats page. `?token=` sets a cookie and redirects, so the token does not
 * stay in the address bar.
 *
 * @param {Request} request Incoming request.
 * @param {URL}     url     Request URL.
 * @param {Object}  env     Worker bindings.
 * @return {Promise<Response>} HTML or JSON response.
 */
async function handleStats( request, url, env ) {
	if ( ! env.STATS_TOKEN ) {
		return new Response( 'STATS_TOKEN is not set.', { status: 503 } );
	}

	const queryToken = url.searchParams.get( 'token' );
	if ( queryToken !== null ) {
		if ( ! safeEqual( queryToken, env.STATS_TOKEN ) ) {
			return new Response( 'Forbidden', { status: 403 } );
		}
		const next = new URL( url );
		next.searchParams.delete( 'token' );
		return new Response( null, {
			status: 303,
			headers: {
				Location: next.pathname + next.search,
				'Set-Cookie': `bdm_stats=${ encodeURIComponent(
					queryToken
				) }; Path=/stats; Max-Age=31536000; HttpOnly; Secure; SameSite=Strict`,
			},
		} );
	}

	const cookie = ( request.headers.get( 'Cookie' ) || '' )
		.split( ';' )
		.map( ( part ) => part.trim() )
		.find( ( part ) => part.startsWith( 'bdm_stats=' ) );
	const cookieToken = cookie
		? decodeURIComponent( cookie.slice( 'bdm_stats='.length ) )
		: '';

	if ( ! safeEqual( cookieToken, env.STATS_TOKEN ) ) {
		return new Response(
			'Forbidden. Open /stats?token=<STATS_TOKEN> once.',
			{
				status: 403,
			}
		);
	}

	const stats = await readStats( env );

	if ( url.searchParams.get( 'format' ) === 'json' ) {
		return Response.json( stats, {
			headers: { 'Cache-Control': 'no-store' },
		} );
	}

	return new Response( renderStats( stats ), {
		headers: {
			'Content-Type': 'text/html; charset=utf-8',
			'Cache-Control': 'no-store',
			'X-Robots-Tag': 'noindex',
		},
	} );
}

/**
 * Length-independent string comparison.
 *
 * @param {string} a First value.
 * @param {string} b Second value.
 * @return {boolean} True when equal.
 */
function safeEqual( a, b ) {
	const left = new TextEncoder().encode( a );
	const right = new TextEncoder().encode( b );
	let same = left.length === right.length;
	for ( let i = 0; i < right.length; i++ ) {
		same = left[ i ] === right[ i ] && same;
	}
	return same;
}

/**
 * Query the counts.
 *
 * @param {Object} env Worker bindings.
 * @return {Promise<Object>} Stats.
 */
async function readStats( env ) {
	const days = parseInt( env.ACTIVE_DAYS, 10 ) || 7;
	const now = Math.floor( Date.now() / 1000 );
	const since = now - days * DAY;

	const group = ( column ) =>
		env.DB.prepare(
			`SELECT COALESCE(${ column }, 'unknown') AS version, COUNT(*) AS count
			FROM installs WHERE last_seen >= ?1
			GROUP BY 1 ORDER BY count DESC, version DESC`
		).bind( since );

	const [ totals, plugin, wp, php ] = await env.DB.batch( [
		env.DB.prepare(
			`SELECT
				SUM(last_seen >= ?1) AS active,
				SUM(last_seen >= ?2) AS last_day,
				SUM(first_seen >= ?1) AS new_sites,
				COUNT(*) AS ever
			FROM installs`
		).bind( since, now - DAY ),
		group( 'plugin_version' ),
		group( 'wp_version' ),
		group( 'php_version' ),
	] );

	const row = totals.results[ 0 ] || {};

	return {
		active_days: days,
		active: row.active || 0,
		last_day: row.last_day || 0,
		new_sites: row.new_sites || 0,
		ever: row.ever || 0,
		by_plugin: plugin.results,
		by_wp: wp.results,
		by_php: php.results,
		generated: new Date( now * 1000 ).toISOString(),
	};
}

/**
 * Render one bar table.
 *
 * @param {string}        title Section title.
 * @param {Array<Object>} rows  Version + count rows.
 * @param {number}        total Total for the percentages.
 * @return {string} HTML.
 */
function renderBars( title, rows, total ) {
	const body = rows.length
		? rows
				.map( ( row ) => {
					const percent = total
						? Math.round( ( row.count / total ) * 100 )
						: 0;
					return `<div class="row"><span class="label">${ escapeHtml(
						row.version
					) }</span><span class="track"><span class="bar" style="width:${ percent }%"></span></span><span class="num">${
						row.count
					} <small>${ percent }%</small></span></div>`;
				} )
				.join( '' )
		: '<p class="empty">No active installs yet.</p>';

	return `<section class="card"><h2>${ title }</h2>${ body }</section>`;
}

/**
 * Render the stats page.
 *
 * @param {Object} stats Stats from readStats().
 * @return {string} HTML document.
 */
function renderStats( stats ) {
	const tile = ( label, value ) =>
		`<div class="tile"><div class="value">${ value }</div><div class="tile-label">${ label }</div></div>`;

	return `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Buddy Modules installs</title>
<style>
:root{--bg:#f6f7f7;--card:#fff;--text:#1d2327;--muted:#646970;--line:#dcdcde;--track:#f0f0f1;--accent:#2271b1}
@media (prefers-color-scheme:dark){:root{--bg:#101214;--card:#1b1e21;--text:#e6e8ea;--muted:#9aa1a7;--line:#2c3136;--track:#262a2e;--accent:#72aee6}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
main{max-width:860px;margin:0 auto;padding:24px 16px 48px}h1{font-size:22px;margin:0 0 4px}.sub{color:var(--muted);margin:0 0 20px}
.tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px}
.tile,.card{background:var(--card);border:1px solid var(--line);border-radius:8px;padding:16px}
.value{font-size:30px;font-weight:600;font-variant-numeric:tabular-nums}.tile-label{color:var(--muted);font-size:13px}
.tile:first-child .value{color:var(--accent)}
.card{margin-bottom:12px}h2{font-size:15px;margin:0 0 12px}
.row{display:grid;grid-template-columns:90px 1fr 80px;gap:10px;align-items:center;margin:6px 0}
.label{font-variant-numeric:tabular-nums;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.track{height:12px;background:var(--track);border-radius:6px;overflow:hidden}.bar{display:block;height:100%;background:var(--accent);border-radius:6px}
.num{text-align:right;font-variant-numeric:tabular-nums}.num small{color:var(--muted)}.empty{color:var(--muted);margin:0}
footer{color:var(--muted);font-size:12px;margin-top:16px}
</style></head><body><main>
<h1>Buddy Modules — active installs</h1>
<p class="sub">Active = a site checked for updates in the last ${
		stats.active_days
	} days.</p>
<div class="tiles">
${ tile( 'Active installs', stats.active ) }
${ tile( 'Checked in today', stats.last_day ) }
${ tile( `New in ${ stats.active_days } days`, stats.new_sites ) }
${ tile( 'Sites ever seen', stats.ever ) }
</div>
${ renderBars( 'By plugin version', stats.by_plugin, stats.active ) }
${ renderBars( 'By WordPress version', stats.by_wp, stats.active ) }
${ renderBars( 'By PHP version', stats.by_php, stats.active ) }
<footer>Generated ${ escapeHtml(
		stats.generated
	) } · JSON: <code>?format=json</code></footer>
</main></body></html>`;
}
