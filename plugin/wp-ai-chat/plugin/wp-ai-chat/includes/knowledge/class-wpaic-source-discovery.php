<?php
/**
 * Discover WordPress post types that are reasonable candidates for AI knowledge.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Source_Discovery {

	/**
	 * Post types that should never be offered as generic knowledge sources.
	 *
	 * @var array<int,string>
	 */
	protected $excluded = array(
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_block',
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		'wp_navigation',
		'elementor_library',
		'wpaic_knowledge',
	);

	/**
	 * Return discoverable post type objects keyed by post type name.
	 *
	 * @return array<string,WP_Post_Type>
	 */
	public function get_post_types() {
		$objects = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		foreach ( $this->excluded as $post_type ) {
			unset( $objects[ $post_type ] );
		}

		/**
		 * Filter the post types shown on the Knowledge Sources screen.
		 *
		 * @param array<string,WP_Post_Type> $objects Discoverable post type objects.
		 */
		$objects = apply_filters( 'wpaic_discoverable_post_types', $objects );

		if ( ! is_array( $objects ) ) {
			return array();
		}

		$clean = array();
		foreach ( $objects as $key => $object ) {
			if ( ! $object instanceof WP_Post_Type ) {
				continue;
			}

			if ( ! $object->public || ! $object->show_ui ) {
				continue;
			}

			if ( in_array( $object->name, $this->excluded, true ) ) {
				continue;
			}

			$clean[ $object->name ] = $object;
		}

		uasort(
			$clean,
			function ( $a, $b ) {
				return strcasecmp( $a->labels->singular_name, $b->labels->singular_name );
			}
		);

		return $clean;
	}

	/**
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public function is_discoverable( $post_type ) {
		$post_type = sanitize_key( $post_type );
		$types     = $this->get_post_types();

		return isset( $types[ $post_type ] );
	}

	/**
	 * @param string $post_type Post type name.
	 * @return int
	 */
	public function get_published_count( $post_type ) {
		$counts = wp_count_posts( $post_type );

		if ( ! $counts || ! isset( $counts->publish ) ) {
			return 0;
		}

		return (int) $counts->publish;
	}
}
