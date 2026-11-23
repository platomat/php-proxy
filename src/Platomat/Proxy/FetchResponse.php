<?php

namespace Platomat\Proxy;

/**
 * Server → Client fetch response.
 */
final class FetchResponse {

	public bool $ok;

	public int $status;

	/** @var array<string, string> */
	public array $headers;

	public string $body;

	/** @var array<string, mixed> */
	public array $meta;

	public string $error;

	/**
	 * @param array<string, string> $headers
	 * @param array<string, mixed>  $meta
	 */
	public function __construct(
		bool $ok,
		int $status = 0,
		array $headers = [],
		string $body = '',
		array $meta = [],
		string $error = ''
	) {
		$this->ok      = $ok;
		$this->status  = $status;
		$this->headers = $headers;
		$this->body    = $body;
		$this->meta    = $meta;
		$this->error   = $error;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		$out = [
			'ok'     => $this->ok,
			'status' => $this->status,
			'headers'=> $this->headers,
			'body'   => $this->body,
			'meta'   => $this->meta,
		];
		if ( $this->error !== '' ) {
			$out['error'] = $this->error;
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray( array $data ): self {
		$headers = [];
		if ( isset( $data['headers'] ) && is_array( $data['headers'] ) ) {
			foreach ( $data['headers'] as $k => $v ) {
				$headers[ (string) $k ] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
			}
		}
		$meta = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : [];

		return new self(
			(bool) ( $data['ok'] ?? false ),
			(int) ( $data['status'] ?? 0 ),
			$headers,
			(string) ( $data['body'] ?? '' ),
			$meta,
			(string) ( $data['error'] ?? '' )
		);
	}

	public static function fail( string $error, int $status = 0 ): self {
		return new self( false, $status, [], '', [], $error );
	}
}
