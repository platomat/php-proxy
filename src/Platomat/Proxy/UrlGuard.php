<?php

namespace Platomat\Proxy;

/**
 * Reject dangerous / non-public targets.
 */
final class UrlGuard {

	public static function assertSafe( string $url ): void {
		$parts = parse_url( $url );
		if ( $parts === false || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			throw new \InvalidArgumentException( 'Invalid URL' );
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			throw new \InvalidArgumentException( 'Only http/https allowed' );
		}

		$host = strtolower( (string) $parts['host'] );
		if ( $host === 'localhost' || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.localhost' ) ) {
			throw new \InvalidArgumentException( 'Local hostnames are not allowed' );
		}

		$ips = [];
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$ips[] = $host;
		} else {
			$resolved = gethostbynamel( $host );
			if ( is_array( $resolved ) ) {
				$ips = $resolved;
			}
		}

		foreach ( $ips as $ip ) {
			if ( self::isPrivateIp( $ip ) ) {
				throw new \InvalidArgumentException( 'Private / reserved IPs are not allowed' );
			}
		}
	}

	private static function isPrivateIp( string $ip ): bool {
		return filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		) === false;
	}
}
