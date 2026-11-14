-- Buddy Modules update stats: one row per site (hashed site ID, never the URL).
CREATE TABLE IF NOT EXISTS installs (
	site_hash TEXT PRIMARY KEY,
	plugin_version TEXT,
	wp_version TEXT,
	php_version TEXT,
	first_seen INTEGER NOT NULL,
	last_seen INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS installs_last_seen ON installs (last_seen);
