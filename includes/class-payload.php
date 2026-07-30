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

		// Replace double-brace template placeholders.
		return preg_replace_callback(
			'/\{\{(\w+)\}\}/',
			function ( $matches ) use ( $variables ) {
				$key = $matches[1];
				return $variables[ $key ] ?? $matches[0];
			},
			$template
		);
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
	 * Get variable reference for UI
	 */
	public static function get_variable_reference(): array {
		return array(
			'Global'  => array(
				'{{site_url}}'      => 'Site URL',
				'{{site_name}}'     => 'Site name',
				'{{admin_email}}'   => 'Admin email',
				'{{timestamp}}'     => 'Unix timestamp',
				'{{timestamp_iso}}' => 'ISO 8601 timestamp',
			),
			'Post'    => array(
				'{{post_id}}'           => 'Post ID',
				'{{post_title}}'        => 'Post title',
				'{{post_content}}'      => 'Post content',
				'{{post_excerpt}}'      => 'Post excerpt',
				'{{post_url}}'          => 'Post URL',
				'{{post_type}}'         => 'Post type',
				'{{post_status}}'       => 'Post status',
				'{{post_author_name}}'  => 'Author name',
				'{{post_author_email}}' => 'Author email',
			),
			'User'    => array(
				'{{user_id}}'           => 'User ID',
				'{{user_email}}'        => 'User email',
				'{{user_login}}'        => 'Username',
				'{{user_display_name}}' => 'Display name',
				'{{user_role}}'         => 'User role(s)',
			),
			'Comment' => array(
				'{{comment_id}}'         => 'Comment ID',
				'{{comment_author}}'     => 'Author name',
				'{{comment_email}}'      => 'Author email',
				'{{comment_content}}'    => 'Comment content',
				'{{comment_post_title}}' => 'Post title',
				'{{comment_post_url}}'   => 'Post URL',
			),
		);
	}
}
