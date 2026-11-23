<?php

namespace Platomat\Proxy;

/**
 * Level-filtered logger.
 *
 * Default destination: project logs/proxy.log (via config log_file).
 * Falls back to error_log() when log_file is empty.
 *
 * Levels (ascending verbosity): off < error < warning < info < debug
 */
final class Logger {

	public const OFF     = 0;
	public const ERROR   = 1;
	public const WARNING = 2;
	public const INFO    = 3;
	public const DEBUG   = 4;

	private int $level;

	private string $prefix;

	private string $file;

	public function __construct( string $level = 'warning', string $prefix = 'php-proxy', string $file = '' ) {
		$this->level  = self::parseLevel( $level );
		$this->prefix = $prefix;
		$this->file   = $file;
	}

	public static function fromConfig( array $config ): self {
		$level  = (string) ( $config['log_level'] ?? 'warning' );
		$prefix = (string) ( $config['log_prefix'] ?? 'php-proxy' );
		$file   = (string) ( $config['log_file'] ?? '' );
		return new self( $level, $prefix !== '' ? $prefix : 'php-proxy', $file );
	}

	public function error( string $message, array $context = [] ): void {
		$this->log( self::ERROR, 'ERROR', $message, $context );
	}

	public function warning( string $message, array $context = [] ): void {
		$this->log( self::WARNING, 'WARN', $message, $context );
	}

	public function info( string $message, array $context = [] ): void {
		$this->log( self::INFO, 'INFO', $message, $context );
	}

	public function debug( string $message, array $context = [] ): void {
		$this->log( self::DEBUG, 'DEBUG', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function log( int $minLevel, string $label, string $message, array $context ): void {
		if ( $this->level < $minLevel || $this->level === self::OFF ) {
			return;
		}
		$line = date( 'c' ) . ' [' . $this->prefix . '][' . $label . '] ' . $message;
		if ( $context !== [] ) {
			$encoded = json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( $encoded !== false ) {
				$line .= ' ' . $encoded;
			}
		}
		$line .= "\n";

		if ( $this->file !== '' ) {
			$dir = dirname( $this->file );
			if ( ! is_dir( $dir ) ) {
				@mkdir( $dir, 0755, true );
			}
			if ( @file_put_contents( $this->file, $line, FILE_APPEND | LOCK_EX ) !== false ) {
				return;
			}
			// Fall through to error_log if file write fails
		}

		error_log( rtrim( $line ) );
	}

	public static function parseLevel( string $level ): int {
		return match ( strtolower( trim( $level ) ) ) {
			'off', 'none', 'silent', '0' => self::OFF,
			'error', 'err', '1' => self::ERROR,
			'warning', 'warn', '2' => self::WARNING,
			'info', '3' => self::INFO,
			'debug', '4' => self::DEBUG,
			default => self::WARNING,
		};
	}
}
