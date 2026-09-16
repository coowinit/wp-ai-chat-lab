<?php
/**
 * Explicit structured-field profiles for legacy WordPress sites.
 *
 * Legacy sites keep their existing functions.php meta boxes and post meta.
 * This provider only describes the small set of fields that WP AI Chat Lab is
 * explicitly allowed to treat as structured AI knowledge.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Legacy_Profile_Provider {

	/**
	 * Return normalized field definitions for one post type.
	 *
	 * v0.3.1 intentionally ships with a tiny Product profile only. Developers
	 * can extend or replace the profiles with the filter below without changing
	 * the Knowledge Core.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<int,array<string,string>>
	 */
	public function get_definitions( $post_type ) {
		$profiles = array(
			'products' => array(
				'fields' => array(
					'product_number' => array(
						'knowledge_key' => 'product_model',
						'label'         => 'Product Model',
						'type'          => 'text',
					),
					'product_size' => array(
						'knowledge_key' => 'product_dimension',
						'label'         => 'Product Dimension',
						'type'          => 'text',
					),
				),
			),
		);

		/**
		 * Filter explicit legacy structured-field profiles.
		 *
		 * This is the intended compatibility escape hatch for older sites with
		 * different meta keys. It is not an automatic post-meta scanner.
		 *
		 * @param array<string,mixed> $profiles Legacy profiles.
		 */
		$profiles = apply_filters( 'wpaic_legacy_structured_profiles', $profiles );

		$post_type = sanitize_key( $post_type );
		if ( '' === $post_type || ! is_array( $profiles ) || empty( $profiles[ $post_type ]['fields'] ) || ! is_array( $profiles[ $post_type ]['fields'] ) ) {
			return array();
		}

		$definitions = array();
		foreach ( $profiles[ $post_type ]['fields'] as $meta_key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$meta_key      = sanitize_key( $meta_key );
			$knowledge_key = isset( $field['knowledge_key'] ) ? sanitize_key( $field['knowledge_key'] ) : '';
			$label         = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';
			$type          = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';

			if ( '' === $meta_key || '' === $knowledge_key || '' === $label ) {
				continue;
			}

			$definitions[] = array(
				'knowledge_key' => $knowledge_key,
				'meta_key'      => $meta_key,
				'label'         => $label,
				'type'          => $type,
				'provider'      => 'legacy',
			);
		}

		return $definitions;
	}
}
