<?php
/**
 * Payload template variable parsing
 */

namespace DragonWebhookManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Payload {

	/**
	 * Parse template variables in payload
	 */
	public function parse( string $template, array $context = array() ): string {
		// Get all available variables
		$variables = $this->get_variables( $context );

		// Replace double-brace template placeholders. Values are JSON-escaped so
		// that user-controlled content — a comment body or author name, which an
		// anonymous visitor controls — cannot break out of its surrounding quotes
		// and inject structure into the JSON payload delivered to the endpoint.
		return preg_replace_callback(
			'/\{\{(\w+)\}\}/',
			function ( $matches ) use ( $variables, $context ) {
				$key = $matches[1];

				if ( array_key_exists( $key, $variables ) ) {
					$value = $variables[ $key ];
				} else {
					$value = $this->resolve_extra_variable( $key, $context );
					if ( null === $value ) {
						return $matches[0];
					}
				}

				// Numbers carry no JSON metacharacters and are usually written
				// unquoted in the template, so pass them through unchanged.
				if ( is_int( $value ) || is_float( $value ) ) {
					return (string) $value;
				}

				// Escape the string's contents for a double-quoted JSON context.
				// wp_json_encode wraps it in quotes; the template already supplies
				// them, so strip the outer pair.
				$encoded = wp_json_encode( (string) $value );

				return is_string( $encoded ) ? substr( $encoded, 1, -1 ) : '';
			},
			$template
		);
	}

	/**
	 * Resolve a placeholder that is not a built-in variable through add-ons.
	 *
	 * A context entry holding an object (a WP_Post, WP_User, WC_Order...) is
	 * never offered to the filter: serialising it would publish every field,
	 * including a user's password hash, to the endpoint.
	 *
	 * @param string $key     Placeholder name without braces.
	 * @param array  $context Trigger context.
	 * @return string|int|float|null Null leaves the placeholder as written.
	 */
	private function resolve_extra_variable( string $key, array $context ) {
		if ( isset( $context[ $key ] ) && is_object( $context[ $key ] ) ) {
			return null;
		}

		/**
		 * Filters the value of a placeholder that is not a built-in variable.
		 *
		 * Return null to leave the placeholder unreplaced. Strings are
		 * JSON-escaped for a double-quoted position; ints and floats are
		 * inserted as written. Any other type leaves the placeholder unreplaced.
		 *
		 * @param mixed  $value   Null, or a value from an earlier callback.
		 * @param string $key     Placeholder name without braces.
		 * @param array  $context Trigger context.
		 */
		$value = apply_filters( 'dragonwebhookmanager_parse_variable', null, $key, $context );

		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}

		return ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) ? $value : null;
	}

	/**
	 * Get all available variables for context
	 */
	public function get_variables( array $context = array() ): array {
		$variables = $this->get_global_variables();

		if ( isset( $context['post'] ) && $context['post'] instanceof \WP_Post ) {
			$variables = array_merge( $variables, $this->get_post_variables( $context['post'] ) );
		}

		if ( isset( $context['user'] ) && $context['user'] instanceof \WP_User ) {
			$variables = array_merge( $variables, $this->get_user_variables( $context['user'] ) );
		}

		if ( isset( $context['comment'] ) && $context['comment'] instanceof \WP_Comment ) {
			$variables = array_merge( $variables, $this->get_comment_variables( $context['comment'] ) );
		}

		return $variables;
	}

	/**
	 * Global variables
	 */
	private function get_global_variables(): array {
		return array(
			'site_url'      => home_url(),
			'site_name'     => get_bloginfo( 'name' ),
			'admin_email'   => get_option( 'admin_email' ),
			'timestamp'     => time(),
			'timestamp_iso' => gmdate( 'c' ),
		);
	}

	/**
	 * Post variables
	 */
	private function get_post_variables( \WP_Post $post ): array {
		$author = get_userdata( $post->post_author );

		return array(
			'post_id'           => $post->ID,
			'post_title'        => $post->post_title,
			'post_content'      => $post->post_content,
			'post_excerpt'      => $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 55 ),
			'post_url'          => get_permalink( $post ),
			'post_type'         => $post->post_type,
			'post_status'       => $post->post_status,
			'post_date'         => $post->post_date,
			'post_modified'     => $post->post_modified,
			'post_author_id'    => $post->post_author,
			'post_author_name'  => $author ? $author->display_name : '',
			'post_author_email' => $author ? $author->user_email : '',
		);
	}

	/**
	 * User variables
	 */
	private function get_user_variables( \WP_User $user ): array {
		return array(
			'user_id'           => $user->ID,
			'user_email'        => $user->user_email,
			'user_login'        => $user->user_login,
			'user_display_name' => $user->display_name,
			'user_first_name'   => $user->first_name,
			'user_last_name'    => $user->last_name,
			'user_role'         => implode( ', ', $user->roles ),
			'user_registered'   => $user->user_registered,
		);
	}

	/**
	 * Comment variables
	 */
	private function get_comment_variables( \WP_Comment $comment ): array {
		$post = get_post( $comment->comment_post_ID );

		return array(
			'comment_id'         => $comment->comment_ID,
			'comment_author'     => $comment->comment_author,
			'comment_email'      => $comment->comment_author_email,
			'comment_url'        => $comment->comment_author_url,
			'comment_content'    => $comment->comment_content,
			'comment_date'       => $comment->comment_date,
			'comment_post_id'    => $comment->comment_post_ID,
			'comment_post_title' => $post ? $post->post_title : '',
			'comment_post_url'   => $post ? get_permalink( $post ) : '',
			'comment_status'     => $comment->comment_approved,
		);
	}

	/**
	 * Get variable reference for UI.
	 *
	 * Labels are translated on each call, so call it at render time only.
	 *
	 * @return array<string, array<string, string>> Group label => placeholder => description.
	 */
	public static function get_variable_reference(): array {
		$reference = array(
			__( 'Global', 'dragon-webhook-manager' )  => array(
				'{{site_url}}'      => __( 'Site URL', 'dragon-webhook-manager' ),
				'{{site_name}}'     => __( 'Site name', 'dragon-webhook-manager' ),
				'{{admin_email}}'   => __( 'Admin email', 'dragon-webhook-manager' ),
				'{{timestamp}}'     => __( 'Unix timestamp', 'dragon-webhook-manager' ),
				'{{timestamp_iso}}' => __( 'ISO 8601 timestamp', 'dragon-webhook-manager' ),
			),
			__( 'Post', 'dragon-webhook-manager' )    => array(
				'{{post_id}}'           => __( 'Post ID', 'dragon-webhook-manager' ),
				'{{post_title}}'        => __( 'Post title', 'dragon-webhook-manager' ),
				'{{post_content}}'      => __( 'Post content', 'dragon-webhook-manager' ),
				'{{post_excerpt}}'      => __( 'Post excerpt', 'dragon-webhook-manager' ),
				'{{post_url}}'          => __( 'Post URL', 'dragon-webhook-manager' ),
				'{{post_type}}'         => __( 'Post type', 'dragon-webhook-manager' ),
				'{{post_status}}'       => __( 'Post status', 'dragon-webhook-manager' ),
				'{{post_author_name}}'  => __( 'Author name', 'dragon-webhook-manager' ),
				'{{post_author_email}}' => __( 'Author email', 'dragon-webhook-manager' ),
			),
			__( 'User', 'dragon-webhook-manager' )    => array(
				'{{user_id}}'           => __( 'User ID', 'dragon-webhook-manager' ),
				'{{user_email}}'        => __( 'User email', 'dragon-webhook-manager' ),
				'{{user_login}}'        => __( 'Username', 'dragon-webhook-manager' ),
				'{{user_display_name}}' => __( 'Display name', 'dragon-webhook-manager' ),
				'{{user_role}}'         => __( 'User roles', 'dragon-webhook-manager' ),
			),
			__( 'Comment', 'dragon-webhook-manager' ) => array(
				'{{comment_id}}'         => __( 'Comment ID', 'dragon-webhook-manager' ),
				'{{comment_author}}'     => __( 'Author name', 'dragon-webhook-manager' ),
				'{{comment_email}}'      => __( 'Author email', 'dragon-webhook-manager' ),
				'{{comment_content}}'    => __( 'Comment content', 'dragon-webhook-manager' ),
				'{{comment_post_title}}' => __( 'Post title', 'dragon-webhook-manager' ),
				'{{comment_post_url}}'   => __( 'Post URL', 'dragon-webhook-manager' ),
			),
		);

		/**
		 * Filters the variable reference shown under the payload template.
		 *
		 * Add a group as label => array( 'variable_name' => 'Description' ).
		 * Names may be given with or without their surrounding braces.
		 *
		 * @param array $reference Group label => placeholder => description.
		 */
		$filtered = apply_filters( 'dragonwebhookmanager_template_variables', $reference );
		if ( ! is_array( $filtered ) ) {
			return $reference;
		}

		$clean = array();
		foreach ( $filtered as $group => $vars ) {
			if ( ! is_string( $group ) || ! is_array( $vars ) ) {
				continue;
			}
			foreach ( $vars as $name => $description ) {
				if ( ! is_string( $name ) || ! is_string( $description ) ) {
					continue;
				}
				$bare = preg_replace( '/^\{\{(\w+)\}\}$/', '$1', $name );
				if ( ! is_string( $bare ) || 1 !== preg_match( '/^\w+$/', $bare ) ) {
					continue;
				}
				$clean[ $group ][ '{{' . $bare . '}}' ] = $description;
			}
		}

		return $clean;
	}

	/**
	 * Build sample trigger context for a test delivery.
	 *
	 * The objects are real core classes filled with sample values, so parse()
	 * treats them exactly like the objects a live trigger passes. The sample
	 * post has no author and the sample comment no parent post, so no real
	 * account or post ends up in a test delivery.
	 *
	 * @param string $trigger_event Trigger key.
	 * @return array Context in the shape the trigger itself dispatches.
	 */
	public static function sample_context( string $trigger_event ): array {
		$now = current_time( 'mysql' );

		if ( in_array( $trigger_event, array( 'post_published', 'post_updated', 'post_trashed' ), true ) ) {
			return array(
				'post' => new \WP_Post(
					(object) array(
						'ID'            => 123,
						'post_author'   => '0',
						'post_title'    => __( 'Sample Post Title', 'dragon-webhook-manager' ),
						'post_content'  => __( 'This is sample post content for testing webhooks.', 'dragon-webhook-manager' ),
						'post_excerpt'  => __( 'Sample excerpt', 'dragon-webhook-manager' ),
						'post_type'     => 'post',
						'post_status'   => 'publish',
						'post_date'     => $now,
						'post_modified' => $now,
						'filter'        => 'raw',
					)
				),
			);
		}

		if ( in_array( $trigger_event, array( 'user_registered', 'user_login' ), true ) ) {
			// Built empty and filled field by field: constructing a WP_User from
			// an ID would load that account's capabilities from the database.
			$user                  = new \WP_User();
			$user->ID              = 1;
			$user->user_email      = 'test@example.com';
			$user->user_login      = 'testuser';
			$user->display_name    = __( 'Test User', 'dragon-webhook-manager' );
			$user->first_name      = __( 'Test', 'dragon-webhook-manager' );
			$user->last_name       = __( 'User', 'dragon-webhook-manager' );
			$user->user_registered = $now;
			$user->roles           = array( 'subscriber' );

			return array( 'user' => $user );
		}

		if ( in_array( $trigger_event, array( 'comment_submitted', 'comment_approved' ), true ) ) {
			return array(
				'comment' => new \WP_Comment(
					(object) array(
						'comment_ID'           => '456',
						'comment_post_ID'      => '0',
						'comment_author'       => __( 'Commenter Name', 'dragon-webhook-manager' ),
						'comment_author_email' => 'commenter@example.com',
						'comment_author_url'   => 'https://example.com',
						'comment_content'      => __( 'This is a sample comment.', 'dragon-webhook-manager' ),
						'comment_date'         => $now,
						'comment_approved'     => '1',
					)
				),
			);
		}

		return array();
	}
}
