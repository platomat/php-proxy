<?php

/**
 * Proxy server front controller.
 * Point the vhost / php-fpm root here (e.g. https://raspi.example:8443/).
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Platomat\Proxy\Server;

$configFile = __DIR__ . '/config/config.php';
if ( ! is_readable( $configFile ) ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo json_encode( [
		'ok'    => false,
		'error' => 'Missing config/config.php — copy config.sample.php',
	] );
	exit;
}

/** @var array<string, mixed> $config */
$config = require $configFile;

$server = new Server( $config );
$server->handleRequest();
