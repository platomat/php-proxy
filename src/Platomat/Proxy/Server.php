<?php

namespace Platomat\Proxy;

/**
 * HTTP proxy server endpoint.
 *
 * Expects POST JSON: { meta: {...}, payload: { url, method, headers, body } }
 * Auth: Authorization: Bearer <apiKey>
 */
final class Server {

	/** @var array<string, mixed> */
	private array $config;

	private Allowlist $allowlist;

	private HttpFetcher $fetcher;

	private ?CacheStore $cache;

	/**
	 * @param array<string, mixed> $config
	 */
	public function __construct( array $config ) {
		$this->config    = $config;
		$this->allowlist = $this->buildAllowlist( $config );
		$this->fetcher   = new HttpFetcher();

		$cacheDir = (string) ( $config['cache_dir'] ?? '' );
		$this->cache = $cacheDir !== '' ? new CacheStore( $cacheDir ) : null;
	}

	public function handleRequest(): void {
		try {
			$this->authenticate();
			if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
				$this->json( 405, FetchResponse::fail( 'Method not allowed', 405 )->toArray() );
				return;
			}

			$raw = file_get_contents( 'php://input' );
			if ( $raw === false || $raw === '' ) {
				$this->json( 400, FetchResponse::fail( 'Empty body', 400 )->toArray() );
				return;
			}

			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) ) {
				$this->json( 400, FetchResponse::fail( 'Invalid JSON', 400 )->toArray() );
				return;
			}

			$request = FetchRequest::fromArray( $data );
			if ( $request->url() === '' ) {
				$this->json( 400, FetchResponse::fail( 'payload.url required', 400 )->toArray() );
				return;
			}

			if ( $this->allowlist->isEmpty() ) {
				$this->json( 503, FetchResponse::fail( 'Allowlist is empty — refusing all fetches', 503 )->toArray() );
				return;
			}
			if ( ! $this->allowlist->allows( $request->url() ) ) {
				$this->json( 403, FetchResponse::fail( 'Host not allowlisted', 403 )->toArray() );
				return;
			}

			$maxTtl = (int) ( $this->config['max_cache_ttl'] ?? 3600 );
			$ttl    = min( $request->cacheTtl(), max( 0, $maxTtl ) );

			if ( $this->cache && $ttl > 0 ) {
				$cached = $this->cache->get( $this->cache->keyFor( $request ), $ttl );
				if ( is_array( $cached ) ) {
					$resp = FetchResponse::fromArray( $cached );
					$resp->meta['cached'] = true;
					$this->json( 200, $resp->toArray() );
					return;
				}
			}

			$response = $this->fetcher->fetch( $request );

			if ( $response->ok && $this->cache && $ttl > 0 ) {
				$this->cache->set( $this->cache->keyFor( $request ), $response->toArray() );
			}

			$httpStatus = $response->ok ? 200 : 502;
			$this->json( $httpStatus, $response->toArray() );
		} catch ( \InvalidArgumentException $e ) {
			$this->json( 400, FetchResponse::fail( $e->getMessage(), 400 )->toArray() );
		} catch ( \Throwable $e ) {
			$this->json( 500, FetchResponse::fail( 'Server error: ' . $e->getMessage(), 500 )->toArray() );
		}
	}

	private function authenticate(): void {
		$expected = (string) ( $this->config['api_key'] ?? '' );
		if ( $expected === '' ) {
			throw new \RuntimeException( 'Server api_key is not configured' );
		}

		$auth = (string) ( $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '' );
		if ( $auth === '' && function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
			foreach ( $headers ?: [] as $k => $v ) {
				if ( strtolower( (string) $k ) === 'authorization' ) {
					$auth = (string) $v;
					break;
				}
			}
		}

		$token = '';
		if ( preg_match( '/^Bearer\s+(.+)$/i', trim( $auth ), $m ) ) {
			$token = trim( $m[1] );
		}
		if ( $token === '' || ! hash_equals( $expected, $token ) ) {
			$this->json( 401, FetchResponse::fail( 'Unauthorized', 401 )->toArray() );
			exit;
		}
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function buildAllowlist( array $config ): Allowlist {
		if ( ! empty( $config['allowlist'] ) && is_array( $config['allowlist'] ) ) {
			return new Allowlist( $config['allowlist'] );
		}
		$file = (string) ( $config['allowlist_file'] ?? '' );
		if ( $file !== '' ) {
			return Allowlist::fromFile( $file );
		}
		return new Allowlist( [] );
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function json( int $status, array $payload ): void {
		http_response_code( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}
