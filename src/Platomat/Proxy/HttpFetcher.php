<?php

namespace Platomat\Proxy;

/**
 * Outbound HTTP fetch (curl).
 */
final class HttpFetcher {

	public function fetch( FetchRequest $request ): FetchResponse {
		UrlGuard::assertSafe( $request->url() );

		$ch = curl_init( $request->url() );
		if ( $ch === false ) {
			return FetchResponse::fail( 'curl_init failed' );
		}

		$headers = $this->normalizeHeaders( $request->headers() );

		curl_setopt_array( $ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_FOLLOWLOCATION => $request->followRedirects(),
			CURLOPT_MAXREDIRS      => 5,
			CURLOPT_TIMEOUT        => $request->timeout(),
			CURLOPT_CONNECTTIMEOUT => min( 10, $request->timeout() ),
			CURLOPT_SSL_VERIFYPEER => $request->sslVerify(),
			CURLOPT_SSL_VERIFYHOST => $request->sslVerify() ? 2 : 0,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_CUSTOMREQUEST  => $request->method(),
		] );

		$body = $request->body();
		if ( $body !== null && $body !== '' && $request->method() !== 'GET' && $request->method() !== 'HEAD' ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
		}
		if ( $request->method() === 'GET' ) {
			curl_setopt( $ch, CURLOPT_HTTPGET, true );
		}

		$started = microtime( true );
		$raw     = curl_exec( $ch );
		$errno   = curl_errno( $ch );
		$error   = curl_error( $ch );
		$status  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$headerSize = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		$finalUrl = (string) curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );
		curl_close( $ch );

		$durationMs = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( $raw === false ) {
			return FetchResponse::fail( $error !== '' ? $error : ( 'curl error ' . $errno ) );
		}

		$rawHeaders = substr( $raw, 0, $headerSize );
		$bodyOut    = substr( $raw, $headerSize );
		$parsedHeaders = $this->parseHeaderBlock( $rawHeaders );

		return new FetchResponse(
			true,
			$status,
			$parsedHeaders,
			$bodyOut === false ? '' : $bodyOut,
			[
				'cached'      => false,
				'fetched_at'  => gmdate( 'c' ),
				'duration_ms' => $durationMs,
				'final_url'   => $finalUrl,
			]
		);
	}

	/**
	 * @param list<string>|array<string, string> $headers
	 * @return list<string>
	 */
	private function normalizeHeaders( array $headers ): array {
		$out = [];
		foreach ( $headers as $k => $v ) {
			if ( is_int( $k ) ) {
				$line = trim( (string) $v );
				if ( $line !== '' ) {
					$out[] = $line;
				}
				continue;
			}
			$out[] = $k . ': ' . $v;
		}
		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	private function parseHeaderBlock( string $rawHeaders ): array {
		$lines = preg_split( '/\r\n|\n|\r/', trim( $rawHeaders ) ) ?: [];
		$out   = [];
		foreach ( $lines as $line ) {
			if ( $line === '' || str_starts_with( strtoupper( $line ), 'HTTP/' ) ) {
				continue;
			}
			$pos = strpos( $line, ':' );
			if ( $pos === false ) {
				continue;
			}
			$name = strtolower( trim( substr( $line, 0, $pos ) ) );
			$value = trim( substr( $line, $pos + 1 ) );
			if ( isset( $out[ $name ] ) ) {
				$out[ $name ] .= ', ' . $value;
			} else {
				$out[ $name ] = $value;
			}
		}
		return $out;
	}
}
