<?php
/**
 * PHPUnit bootstrap.
 *
 * The classes under test are WP-light; the few core helpers they touch are
 * stubbed here with in-memory backing.
 *
 * @package DragonWebhookManager
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );
defined( 'OBJECT' ) || define( 'OBJECT', 'OBJECT' );
defined( 'DRAGONWEBHOOKMANAGER_VERSION' ) || define( 'DRAGONWEBHOOKMANAGER_VERSION', '9.9.9-test' );
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/FakeWpdb.php';

$GLOBALS['dragonwebhookmanager_test_options']          = array();
$GLOBALS['dragonwebhookmanager_test_readonly_options'] = array();
$GLOBALS['dragonwebhookmanager_test_dbdelta_creates']  = true;
$GLOBALS['dragonwebhookmanager_test_dbdelta_calls']    = array();
$GLOBALS['dragonwebhookmanager_test_transients']       = array();
$GLOBALS['dragonwebhookmanager_test_can']              = true;

$GLOBALS['dragonwebhookmanager_test_filters'] = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
		unset( $hook, $cb, $priority, $args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	// Minimal in-memory filter registry so filter pipelines can be exercised.
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
		unset( $priority, $args );
		$GLOBALS['dragonwebhookmanager_test_filters'][ $hook ][] = $cb;
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['dragonwebhookmanager_test_filters'][ $hook ] ?? array() as $cb ) {
			$value = call_user_func( $cb, $value, ...$args );
		}
		return $value;
	}
}


if ( ! function_exists( 'dragon_test_repair_utf8' ) ) {
	/**
	 * Mirrors wp_check_invalid_utf8( $text, true ) over a whole structure:
	 * invalid byte sequences are stripped rather than causing a failure.
	 *
	 * @param mixed $value Value to repair.
	 * @return mixed
	 */
	function dragon_test_repair_utf8( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$out[ is_string( $key ) ? dragon_test_repair_utf8( $key ) : $key ] = dragon_test_repair_utf8( $item );
			}
			return $out;
		}

		if ( ! is_string( $value ) || '' === $value || 1 === preg_match( '//u', $value ) ) {
			return $value;
		}

		return (string) preg_replace( '/[\x80-\xFF]/', '', $value );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		/*
		 * Core runs every string through wp_check_invalid_utf8() first, which
		 * REPAIRS invalid UTF-8 rather than refusing to encode it. The result can
		 * therefore name different bytes than the input, and the function
		 * succeeds where plain json_encode() would return false. A stub that just
		 * calls json_encode() hides every bug where a repaired value is then used
		 * as if it were the original.
		 */
		return json_encode( dragon_test_repair_utf8( $data ), $options, $depth );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default_value = false ) {
		return array_key_exists( $name, $GLOBALS['dragonwebhookmanager_test_options'] ) ? $GLOBALS['dragonwebhookmanager_test_options'][ $name ] : $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	// Mirrors core: false when unchanged; writes to names listed in the
	// readonly list are dropped to simulate a failed write.
	function update_option( $name, $value, $autoload = null ) {
		unset( $autoload );
		if ( in_array( $name, $GLOBALS['dragonwebhookmanager_test_readonly_options'], true ) ) {
			return false;
		}
		if ( array_key_exists( $name, $GLOBALS['dragonwebhookmanager_test_options'] ) && $GLOBALS['dragonwebhookmanager_test_options'][ $name ] === $value ) {
			return false;
		}
		$GLOBALS['dragonwebhookmanager_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		$GLOBALS['dragonwebhookmanager_test_deleted_options'][] = $name;
		$existed = array_key_exists( $name, $GLOBALS['dragonwebhookmanager_test_options'] );
		unset( $GLOBALS['dragonwebhookmanager_test_options'][ $name ] );
		return $existed;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	// Stored with an absolute expiry so tests can age a transient by editing 'expires'.
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['dragonwebhookmanager_test_transients'][ $key ] = array(
			'value'   => $value,
			'expires' => $expiration ? time() + (int) $expiration : 0,
		);
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		$row = $GLOBALS['dragonwebhookmanager_test_transients'][ $key ] ?? null;
		if ( null === $row ) {
			return false;
		}
		if ( $row['expires'] && $row['expires'] <= time() ) {
			unset( $GLOBALS['dragonwebhookmanager_test_transients'][ $key ] );
			return false;
		}
		return $row['value'];
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['dragonwebhookmanager_test_transients'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		unset( $cap );
		return (bool) $GLOBALS['dragonwebhookmanager_test_can'];
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.WP.I18n
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) { // phpcs:ignore WordPress.WP.I18n
		unset( $domain );
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, abs( (int) $decimals ), '.', ',' );
	}
}

if ( ! function_exists( 'wp_get_list_item_separator' ) ) {
	function wp_get_list_item_separator() {
		return __( ', ' ); // phpcs:ignore WordPress.WP.I18n
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8', false );
	}
}

if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( $from, $to = 0 ) {
		return (string) abs( ( $to ? (int) $to : time() ) - (int) $from ) . ' secs';
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		unset( $hook, $args );
		return false;
	}
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
	function wp_unschedule_event( $timestamp, $hook, $args = array() ) {
		unset( $timestamp, $hook, $args );
		return true;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		unset( $timestamp, $recurrence, $hook, $args );
		return true;
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $queries ) {
		global $wpdb;
		$GLOBALS['dragonwebhookmanager_test_dbdelta_calls'][] = $queries;
		if ( $GLOBALS['dragonwebhookmanager_test_dbdelta_creates'] && preg_match( '/CREATE TABLE (\S+)/', (string) $queries, $m ) ) {
			$wpdb->tables[] = $m[1];
		}
		return array();
	}
}

$GLOBALS['dragonwebhookmanager_test_actions']         = array();
$GLOBALS['dragonwebhookmanager_test_deleted_options'] = array();

if ( ! class_exists( 'Dragon_Test_Json_Response' ) ) {
	/**
	 * Thrown by the wp_send_json_* stubs: core ends the request there, so a
	 * handler must never carry on past one.
	 */
	class Dragon_Test_Json_Response extends Exception {
		public bool $success;
		public $data;

		public function __construct( bool $success, $data ) {
			parent::__construct( $success ? 'success' : 'error' );
			$this->success = $success;
			$this->data    = $data;
		}
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null, $status_code = null, $flags = 0 ) {
		unset( $status_code, $flags );
		throw new Dragon_Test_Json_Response( true, $data );
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, $status_code = null, $flags = 0 ) {
		unset( $status_code, $flags );
		throw new Dragon_Test_Json_Response( false, $data );
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action = -1, $query_arg = false, $stop = true ) {
		unset( $action, $query_arg, $stop );
		return 1;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	// Records fired actions (no listeners run: add_action is a no-op here).
	function do_action( $hook_name, ...$args ) {
		$GLOBALS['dragonwebhookmanager_test_actions'][] = array_merge( array( $hook_name ), $args );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
	}
}

if ( ! function_exists( 'wp_slash' ) ) {
	// As core: strings are addslashes()'d, arrays walked, anything else kept.
	function wp_slash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_slash', $value );
		}
		return is_string( $value ) ? addslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	// Mirrors core: lowercases, then keeps only a-z, 0-9, underscore and dash.
	function sanitize_key( $key ) {
		if ( ! is_scalar( $key ) ) {
			return '';
		}
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'dragon_test_sanitize_text' ) ) {
	// Mirrors core _sanitize_text_fields(): strips tags, drops percent-encoded
	// octets, folds whitespace (keeping newlines for textareas) and trims.
	function dragon_test_sanitize_text( $str, $keep_newlines ) {
		if ( is_object( $str ) || is_array( $str ) ) {
			return '';
		}
		$str = (string) $str;
		if ( 1 !== preg_match( '//u', $str ) ) {
			return '';
		}
		if ( str_contains( $str, '<' ) ) {
			$str = preg_replace_callback(
				'%<[^>]*?((?=<)|>|$)%',
				static function ( $m ) {
					return str_contains( $m[0], '>' ) ? $m[0] : str_replace( '<', '&lt;', $m[0] );
				},
				$str
			);
			$str = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $str );
			$str = strip_tags( $str );
			$str = str_replace( "<\n", "&lt;\n", $str );
		}
		if ( ! $keep_newlines ) {
			$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
		}
		$str   = trim( $str );
		$found = false;
		while ( preg_match( '/%[a-f0-9]{2}/i', $str, $match ) ) {
			$str   = str_replace( $match[0], '', $str );
			$found = true;
		}
		if ( $found ) {
			$str = trim( preg_replace( '/ +/', ' ', $str ) );
		}
		return $str;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return dragon_test_sanitize_text( $str, false );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return dragon_test_sanitize_text( $str, true );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	// Enough of core for well-formed http(s) URLs: returns them unchanged, and
	// '' for a disallowed scheme.
	function esc_url_raw( $url, $protocols = null ) {
		unset( $protocols );
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( preg_match( '#^([a-z][a-z0-9+.-]*):#i', $url, $m ) && ! in_array( strtolower( $m[1] ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		return str_replace( ' ', '%20', $url );
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	// Mirrors core: timezone_string when set, else the gmt_offset as +HH:MM.
	function wp_timezone() {
		$tz = (string) get_option( 'timezone_string', '' );
		if ( '' !== $tz ) {
			return new DateTimeZone( $tz );
		}
		$offset  = (float) get_option( 'gmt_offset', 0 );
		$hours   = (int) $offset;
		$minutes = abs( ( $offset - $hours ) * 60 );
		return new DateTimeZone( sprintf( '%s%02d:%02d', $offset < 0 ? '-' : '+', abs( $hours ), $minutes ) );
	}
}

require_once __DIR__ . '/../includes/class-webhook.php';
require_once __DIR__ . '/../includes/class-plugin.php';
require_once __DIR__ . '/../includes/class-admin.php';
require_once __DIR__ . '/../includes/class-logger.php';
require_once __DIR__ . '/../includes/class-payload.php';
require_once __DIR__ . '/../includes/class-ajax.php';
require_once __DIR__ . '/../includes/class-integration.php';

require_once __DIR__ . '/../includes/class-pro-pointer.php';
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

require_once __DIR__ . '/wp-objects.php';
require_once __DIR__ . '/doubles.php';
