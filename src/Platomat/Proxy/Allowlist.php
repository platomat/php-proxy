<?php

namespace Platomat\Proxy;

/**
 * Host allowlist (+ optional path prefix rules).
 *
 * File format (one per line, # comments):
 *   example.com
 *   *.example.com
 *   example.com/path/prefix
 */
final class Allowlist {

	/** @var list<string> */
	private array $rules;

	/**
	 * @param list<string> $rules
	 */
	public function __construct( array $rules = [] ) {
		$this->rules = array_values( array_filter( array_map( 'trim', $rules ), static fn( $r ) => $r !== '' && $r[0] !== '#' ) );
	}

	public static function fromFile( string $path ): self {
		if ( ! is_readable( $path ) ) {
			return new self( [] );
		}
		$lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( $lines === false ) {
			return new self( [] );
		}
		return new self( $lines );
	}

	public function isEmpty(): bool {
		return $this->rules === [];
	}

	public function allows( string $url ): bool {
		$host = strtolower( (string) ( parse_url( $url, PHP_URL_HOST ) ?: '' ) );
		$path = (string) ( parse_url( $url, PHP_URL_PATH ) ?: '/' );
		if ( $host === '' ) {
			return false;
		}

		foreach ( $this->rules as $rule ) {
			$rule = strtolower( $rule );
			$ruleHost = $rule;
			$rulePath = '';
			if ( str_contains( $rule, '/' ) ) {
				[ $ruleHost, $rulePath ] = array_pad( explode( '/', $rule, 2 ), 2, '' );
				$rulePath = '/' . $rulePath;
			}

			if ( ! $this->hostMatches( $host, $ruleHost ) ) {
				continue;
			}
			if ( $rulePath === '' || $rulePath === '/' ) {
				return true;
			}
			if ( str_starts_with( $path, $rulePath ) ) {
				return true;
			}
		}

		return false;
	}

	private function hostMatches( string $host, string $ruleHost ): bool {
		if ( $ruleHost === '' ) {
			return false;
		}
		if ( str_starts_with( $ruleHost, '*.' ) ) {
			$suffix = substr( $ruleHost, 1 ); // .example.com
			return $host === substr( $ruleHost, 2 ) || str_ends_with( $host, $suffix );
		}
		return $host === $ruleHost;
	}
}
