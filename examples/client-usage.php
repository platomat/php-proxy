<?php

/**
 * Minimal client smoke test against a running proxy server.
 *
 *   php examples/client-usage.php
 */

declare(strict_types=1);

require dirname( __DIR__ ) . '/vendor/autoload.php';

use Platomat\Proxy\Client;

$baseUrl = getenv( 'PROXY_BASE_URL' ) ?: 'https://127.0.0.1:8443';
$apiKey  = getenv( 'PROXY_API_KEY' ) ?: 'change-me-to-a-long-random-string';
$target  = getenv( 'PROXY_TARGET_URL' ) ?: 'https://example.com/';

$client = new Client( [
	'base_url' => $baseUrl,
	'api_key'  => $apiKey,
	'timeout'  => 30,
] );

$response = $client->fetch(
	$target,
	'GET',
	[
		'User-Agent: PlatomatPhpProxyExample/1.0',
		'Accept: text/html,application/json;q=0.9,*/*;q=0.8',
	],
	null,
	[
		'cache_ttl'        => 60,
		'timeout'          => 15,
		'follow_redirects' => true,
		'ssl_verify'       => true,
	]
);

fwrite( STDOUT, json_encode( [
	'ok'     => $response->ok,
	'status' => $response->status,
	'error'  => $response->error,
	'meta'   => $response->meta,
	'body_preview' => substr( $response->body, 0, 200 ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . PHP_EOL );

exit( $response->ok ? 0 : 1 );
