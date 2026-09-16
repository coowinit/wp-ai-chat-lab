<?php
/**
 * Structured-field definitions provided by WEM Content Fields.
 *
 * This provider reads WEM's stored/runtime field definitions, but only exposes
 * fields that WP AI Chat Lab explicitly allowlists for AI knowledge. It never
 * scans arbitrary post meta and it does not require WEM to be installed.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_WEM_Field_Registry_Provider {

	/**
	 * Return normalized WEM field definitions for one post type.
	 *
	 * v0.3.1 Stage 2 intentionally validates only two Product knowledge keys.
	 * The WEM field key is not automatically trusted just because it exists;
	 * it must also be present in the explicit AI allowlist below.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<int,array<string,string>>
	 */
	public function get_definitions( $post_type ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		$post_type = sanitize_key( $post_type );
		if ( '' === $post_type ) {
			return array();
		}

		$allowlist = array(
			'products' => array(
				'product_model' => array(
					'knowledge_key' => 'product_model',
				),
				'product_dimension' => array(
					'knowledge_key' => 'product_dimension',
				),
			),
		);

		/**
		 * Filter WEM fields explicitly allowed to become structured AI knowledge.
		 *
		 * Shape:
		 * [
		 *   'products' => [
		 *     'wem_field_key' => [ 'knowledge_key' => 'stable_key' ],
		 *   ],
		 * ]
		 *
		 * This is an allowlist, not an automatic WEM field scanner.
		 *
		 * @param array<string,mixed> $allowlist WEM AI allowlist.
		 */
		$allowlist = apply_filters( 'wpaic_wem_structured_allowlist', $allowlist );

		if ( ! is_array( $allowlist ) || empty( $allowlist[ $post_type ] ) || ! is_array( $allowlist[ $post_type ] ) ) {
			return array();
		}

		$groups = get_option( 'wem_cf_field_groups', array() );
		$groups = is_array( $groups ) ? $groups : array();

		// Respect WEM's documented runtime-definition extension point as well.
		$groups = apply_filters( 'wem_cf_runtime_field_groups', $groups );
		if ( ! is_array( $groups ) ) {
			return array();
		}

		$definitions = array();

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$post_types = isset( $group['post_types'] ) && is_array( $group['post_types'] ) ? array_map( 'sanitize_key', $group['post_types'] ) : array();
			if ( ! in_array( $post_type, $post_types, true ) ) {
				continue;
			}

			$fields = isset( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : array();
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$field_key = isset( $field['key'] ) ? sanitize_key( $field['key'] ) : '';
				if ( '' === $field_key || empty( $allowlist[ $post_type ][ $field_key ] ) || ! is_array( $allowlist[ $post_type ][ $field_key ] ) ) {
					continue;
				}

				$mapping       = $allowlist[ $post_type ][ $field_key ];
				$knowledge_key = isset( $mapping['knowledge_key'] ) ? sanitize_key( $mapping['knowledge_key'] ) : '';
				$meta_key      = isset( $field['meta_key'] ) ? sanitize_key( $field['meta_key'] ) : '';
				$label         = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';
				$type          = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';

				if ( '' === $knowledge_key || '' === $meta_key || '' === $label ) {
					continue;
				}

				$definitions[ $knowledge_key ] = array(
					'knowledge_key' => $knowledge_key,
					'meta_key'      => $meta_key,
					'label'         => $label,
					'type'          => $type,
					'provider'      => 'wem',
				);
			}
		}

		return array_values( $definitions );
	}

	/**
	 * WEM definitions are only authoritative while the WEM plugin is active.
	 * Its option may remain in the database after deactivation, so option
	 * existence alone is intentionally not treated as availability.
	 *
	 * @return bool
	 */
	protected function is_available() {
		return defined( 'WEM_CF_VERSION' );
	}
}
