<?php

namespace Platomat\Proxy;

/**
 * Simple file cache for proxied responses (on the Pi).
 */
final class CacheStore {

	private string $dir;

	public function __construct( string $dir ) {
		$this->dir = rtrim( $dir, DIRECTORY_SEPARATOR );
		if ( ! is_dir( $this->dir ) ) {
			mkdir( $this->dir, 0755, true );
		}
	}

	public function get( string $key, int $ttl ): ?array {
		if ( $ttl <= 0 ) {
			return null;
		}
		$path = $this->path( $key );
		if ( ! is_readable( $path ) ) {
			return null;
		}
		if ( time() - (int) filemtime( $path ) > $ttl ) {
			return null;
		}
		$raw = file_get_contents( $path );
		if ( $raw === false || $raw === '' ) {
			return null;
		}
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function set( string $key, array $data ): void {
		$path = $this->path( $key );
		file_put_contents( $path, json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), LOCK_EX );
	}

	public function keyFor( FetchRequest $request ): string {
		return hash(
			'sha256',
			$request->method() . "\n" . $request->url() . "\n" . (string) $request->body()
		);
	}

	private function path( string $key ): string {
		return $this->dir . DIRECTORY_SEPARATOR . $key . '.json';
	}
}
