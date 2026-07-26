<?php
/**
 * Runtime context for rendering a document template (invoice, receipt, …).
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

/**
 * Holds the current document payload while blocks and merge fields render.
 *
 * Labels and static strings go through gettext at render time so output follows
 * the active WordPress locale (site language by default).
 */
class Document_Context {

	/**
	 * Document type slug (invoice, receipt, …).
	 *
	 * @var string
	 */
	private static string $type = '';

	/**
	 * Payload: invoice/payment rows, items, client, project, etc.
	 *
	 * @var array<string, mixed>
	 */
	private static array $data = array();

	/**
	 * Set the context for the current render pass.
	 *
	 * @param string               $type Document type.
	 * @param array<string, mixed> $data Payload.
	 *
	 * @return void
	 */
	public static function set( string $type, array $data ): void {
		self::$type = $type;
		self::$data = $data;
	}

	/**
	 * Clear context after render.
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$type = '';
		self::$data = array();
	}

	/**
	 * Current document type.
	 *
	 * @return string
	 */
	public static function type(): string {
		return self::$type;
	}

	/**
	 * Full payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		return self::$data;
	}

	/**
	 * Read a top-level payload key.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default_value Default.
	 *
	 * @return mixed
	 */
	public static function get( string $key, $default_value = null ) {
		return array_key_exists( $key, self::$data ) ? self::$data[ $key ] : $default_value;
	}

	/**
	 * Whether a document is currently being rendered.
	 *
	 * @return bool
	 */
	public static function is_rendering(): bool {
		return '' !== self::$type;
	}
}
