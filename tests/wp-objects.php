<?php
/**
 * Minimal stand-ins for the core object classes and lookups Payload reads.
 *
 * WP_Post and WP_Comment copy every property of the object passed to their
 * constructor, as core does. WP_User keeps unknown fields in $data and reads
 * them back through __get(), returning '' for a missing field the way core's
 * get_user_meta( ..., true ) fallback does.
 *
 * @package DragonWebhookManager
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, WordPress.NamingConventions

$GLOBALS['dragonwebhookmanager_test_users'] = array();
$GLOBALS['dragonwebhookmanager_test_posts'] = array();

// Registered post types: name => object with public, publicly_queryable and _builtin.
$GLOBALS['dragonwebhookmanager_test_post_types'] = array(
	'post'       => (object) array( 'name' => 'post', 'public' => true, 'publicly_queryable' => true, '_builtin' => true ),
	'page'       => (object) array( 'name' => 'page', 'public' => true, 'publicly_queryable' => false, '_builtin' => true ),
	'attachment' => (object) array( 'name' => 'attachment', 'public' => true, 'publicly_queryable' => true, '_builtin' => true ),
	'revision'   => (object) array( 'name' => 'revision', 'public' => false, 'publicly_queryable' => false, '_builtin' => true ),
);

if ( ! class_exists( 'WP_Post' ) ) {
	final class WP_Post {
		public $ID                = 0;
		public $post_author       = '0';
		public $post_date         = '0000-00-00 00:00:00';
		public $post_content      = '';
		public $post_title        = '';
		public $post_excerpt      = '';
		public $post_status       = 'publish';
		public $post_name         = '';
		public $post_parent       = 0;
		public $post_modified     = '0000-00-00 00:00:00';
		public $post_type         = 'post';
		public $filter;

		public function __construct( $post ) {
			foreach ( get_object_vars( $post ) as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_Comment' ) ) {
	final class WP_Comment {
		public $comment_ID           = '';
		public $comment_post_ID      = '0';
		public $comment_author       = '';
		public $comment_author_email = '';
		public $comment_author_url   = '';
		public $comment_date         = '0000-00-00 00:00:00';
		public $comment_content      = '';
		public $comment_approved     = '1';
		public $comment_type         = 'comment';

		public function __construct( $comment ) {
			foreach ( get_object_vars( $comment ) as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_User' ) ) {
	class WP_User {
		public $data;
		public $ID    = 0;
		public $caps  = array();
		public $roles = array();

		public function __construct( $id = 0 ) {
			unset( $id );
			$this->data = new stdClass();
		}

		public function __get( $key ) {
			if ( 'id' === $key ) {
				return $this->ID;
			}
			return $this->data->$key ?? '';
		}

		public function __set( $key, $value ) {
			if ( 'id' === $key ) {
				$this->ID = $value;
				return;
			}
			$this->data->$key = $value;
		}

		public function __isset( $key ) {
			return isset( $this->data->$key );
		}

		public function exists() {
			return ! empty( $this->ID );
		}
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . ( $path ? '/' . ltrim( (string) $path, '/' ) : '' );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'name' === $show ? (string) get_option( 'blogname', '' ) : '';
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		return $GLOBALS['dragonwebhookmanager_test_users'][ (int) $user_id ] ?? false;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		return $GLOBALS['dragonwebhookmanager_test_posts'][ (int) $post ] ?? null;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) {
		$post = get_post( $post );
		return $post ? home_url( '?p=' . $post->ID ) : false;
	}
}

if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $num_words = 55, $more = null ) {
		$more  = null === $more ? '&hellip;' : $more;
		$words = preg_split( '/[\n\r\t ]+/', trim( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );
		if ( count( $words ) > $num_words ) {
			return implode( ' ', array_slice( $words, 0, $num_words ) ) . $more;
		}
		return implode( ' ', $words );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	// Mirrors core: local time is UTC shifted by the gmt_offset option.
	function current_time( $type, $gmt = 0 ) {
		$ts = time() + ( $gmt ? 0 : (int) round( (float) get_option( 'gmt_offset', 0 ) * 3600 ) );
		return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s', $ts ) : $ts;
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	// Names of the registered types, keyed by name (core's default output).
	function get_post_types( $args = array(), $output = 'names' ) {
		unset( $args, $output );
		$names = array_keys( $GLOBALS['dragonwebhookmanager_test_post_types'] );
		return array_combine( $names, $names );
	}
}

if ( ! function_exists( 'get_post_type_object' ) ) {
	function get_post_type_object( $post_type ) {
		return is_scalar( $post_type ) ? ( $GLOBALS['dragonwebhookmanager_test_post_types'][ (string) $post_type ] ?? null ) : null;
	}
}

if ( ! function_exists( 'is_post_type_viewable' ) ) {
	// As core: publicly queryable, or built in and public; then filtered.
	function is_post_type_viewable( $post_type ) {
		if ( is_scalar( $post_type ) ) {
			$post_type = get_post_type_object( $post_type );
			if ( ! $post_type ) {
				return false;
			}
		}
		if ( ! is_object( $post_type ) ) {
			return false;
		}
		$is_viewable = $post_type->publicly_queryable || ( $post_type->_builtin && $post_type->public );
		return true === apply_filters( 'is_post_type_viewable', $is_viewable, $post_type );
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	// As core: the parent ID for a revision, false otherwise.
	function wp_is_post_revision( $post ) {
		$post = get_post( $post );
		if ( ! $post || 'revision' !== $post->post_type ) {
			return false;
		}
		return (int) $post->post_parent;
	}
}

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	// As core: the parent ID for an autosave revision, false otherwise.
	function wp_is_post_autosave( $post ) {
		$post = get_post( $post );
		if ( ! $post || 'revision' !== $post->post_type ) {
			return false;
		}
		if ( str_contains( (string) $post->post_name, "{$post->post_parent}-autosave" ) ) {
			return (int) $post->post_parent;
		}
		return false;
	}
}
