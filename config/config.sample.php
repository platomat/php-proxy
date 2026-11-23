<?php

/**
 * Copy to config.php (gitignored) and adjust.
 */

$root = dirname( __DIR__ ) . DIRECTORY_SEPARATOR;

return [
	// Shared secret — clients send Authorization: Bearer <api_key>
	'api_key' => 'change-me-to-a-long-random-string',

	// Host allowlist file (one host per line). Empty allowlist = deny all.
	'allowlist_file' => $root . 'data' . DIRECTORY_SEPARATOR . 'allowlist.txt',

	// Or inline list (optional; overrides file if non-empty):
	// 'allowlist' => [
	//   'www.google.com',
	//   '*.example.com',
	// ],

	// Response cache directory on the relay (optional; empty disables)
	'cache_dir' => $root . 'tmp' . DIRECTORY_SEPARATOR . 'cache',

	// Cap client-requested cache_ttl
	'max_cache_ttl' => 3600,

	// Logging: off | error | warning | info | debug
	// warning = auth/allowlist/bad request; info = each fetch + cache hit; debug = fetch start
	'log_level'  => 'info',
	'log_prefix' => 'php-proxy',
	// Dedicated file (recommended). Empty = PHP error_log() only.
	'log_file'   => $root . 'logs' . DIRECTORY_SEPARATOR . 'proxy.log',
];
