<?php

namespace Platomat\Proxy;

/**
 * PHP client for the proxy server.
 */
final class Client {

	private string $baseUrl;

	private string $apiKey;

	private int $timeout;

	/**
	 * @param array{base_url?: string, api_key?: string, timeout?: int} $config
	 */
	public function __construct( array $config ) {
		$this->baseUrl = rtrim( (string) ( $config['base_url'] ?? '' ), '/' );
		$this->apiKey  = (string) ( $config['api_key'] ?? '' );
		$this->timeout = max( 1, (int) ( $config['timeout'] ?? 30 ) );

		if ( $this->baseUrl === '' ) {
			throw new \InvalidArgumentException( 'base_url required' );
		}
		if ( $this->apiKey === '' ) {
			throw new \InvalidArgumentException( 'api_key required' );
		}
	}

	/**
	 * Convenience helper for typical grabber use.
	 *
	 * @param list<string>|array<string, string> $headers
	 * @param array<string, mixed>               $meta
	 */
	public function fetch(
		string $url,
		string $method = 'GET',
		array $headers = [],
		?string $body = null,
		array $meta = []
	): FetchResponse {
		$request = new FetchRequest(
			$meta,
			[
				'url'     => $url,
				'method'  => $method,
				'headers' => $headers,
				'body'    => $body,
			]
		);
		return $this->send( $request );
	}

	public function send( FetchRequest $request ): FetchResponse {
		$endpoint = $this->baseUrl;
		$payload  = json_encode( $request->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( $payload === false ) {
			return FetchResponse::fail( 'Failed to encode request JSON' );
		}

		$ch = curl_init( $endpoint );
		if ( $ch === false ) {
			return FetchResponse::fail( 'curl_init failed' );
		}

		curl_setopt_array( $ch, [
			CURLOPT_POST           => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => min( 10, $this->timeout ),
			CURLOPT_HTTPHEADER     => [
				'Content-Type: application/json; charset=utf-8',
				'Accept: application/json',
				'Authorization: Bearer ' . $this->apiKey,
			],
			CURLOPT_POSTFIELDS     => $payload,
		] );

		$raw  = curl_exec( $ch );
		$err  = curl_error( $ch );
		$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( $raw === false ) {
			return FetchResponse::fail( $err !== '' ? $err : 'proxy request failed' );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return FetchResponse::fail( 'Invalid JSON from proxy (HTTP ' . $code . ')' );
		}

		$response = FetchResponse::fromArray( $data );
		if ( ! $response->ok && $response->error === '' && $code >= 400 ) {
			$response->error = 'HTTP ' . $code;
		}
		return $response;
	}
}
