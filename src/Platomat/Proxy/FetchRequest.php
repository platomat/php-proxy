<?php

namespace Platomat\Proxy;

/**
 * Client → Server fetch request (meta + payload).
 */
final class FetchRequest {

	/** @var array<string, mixed> */
	public array $meta;

	/** @var array<string, mixed> */
	public array $payload;

	/**
	 * @param array<string, mixed> $meta
	 * @param array<string, mixed> $payload
	 */
	public function __construct( array $meta = [], array $payload = [] ) {
		$this->meta    = $meta;
		$this->payload = $payload;
	}

	public function url(): string {
		return trim( (string) ( $this->payload['url'] ?? '' ) );
	}

	public function method(): string {
		$method = strtoupper( trim( (string) ( $this->payload['method'] ?? 'GET' ) ) );
		return $method !== '' ? $method : 'GET';
	}

	/**
	 * @return list<string>|array<string, string>
	 */
	public function headers(): array {
		$headers = $this->payload['headers'] ?? [];
		return is_array( $headers ) ? $headers : [];
	}

	public function body(): ?string {
		if ( ! array_key_exists( 'body', $this->payload ) || $this->payload['body'] === null ) {
			return null;
		}
		return (string) $this->payload['body'];
	}

	public function cacheTtl(): int {
		$ttl = (int) ( $this->meta['cache_ttl'] ?? 0 );
		return max( 0, $ttl );
	}

	public function timeout(): int {
		$timeout = (int) ( $this->meta['timeout'] ?? 15 );
		return max( 1, min( 120, $timeout ) );
	}

	public function followRedirects(): bool {
		if ( ! array_key_exists( 'follow_redirects', $this->meta ) ) {
			return true;
		}
		return (bool) $this->meta['follow_redirects'];
	}

	public function sslVerify(): bool {
		if ( ! array_key_exists( 'ssl_verify', $this->meta ) ) {
			return true;
		}
		return (bool) $this->meta['ssl_verify'];
	}

	/**
	 * @return array{meta: array<string, mixed>, payload: array<string, mixed>}
	 */
	public function toArray(): array {
		return [
			'meta'    => $this->meta,
			'payload' => $this->payload,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray( array $data ): self {
		$meta    = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : [];
		$payload = isset( $data['payload'] ) && is_array( $data['payload'] ) ? $data['payload'] : [];
		return new self( $meta, $payload );
	}
}
