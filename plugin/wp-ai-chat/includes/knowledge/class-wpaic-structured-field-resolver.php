<?php
/**
 * Resolve structured field definitions from one or more lightweight providers.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Structured_Field_Resolver {

	/** @var array<int,object> */
	protected $providers = array();

	/**
	 * @param array<int,object> $providers Providers exposing get_definitions().
	 */
	public function __construct( array $providers ) {
		$this->providers = $providers;
	}

	/**
	 * Resolve definitions for a post.
	 *
	 * Later providers win when two providers expose the same knowledge_key.
	 * This leaves room for a future WEM provider to override a legacy fallback
	 * without introducing a formal adapter framework in v0.3.1.
	 *
	 * @param WP_Post $post Source post.
	 * @return array<int,array<string,string>>
	 */
	public function resolve( WP_Post $post ) {
		$resolved = array();

		foreach ( $this->providers as $provider ) {
			if ( ! is_object( $provider ) || ! is_callable( array( $provider, 'get_definitions' ) ) ) {
				continue;
			}

			$definitions = $provider->get_definitions( $post->post_type );
			if ( ! is_array( $definitions ) ) {
				continue;
			}

			foreach ( $definitions as $definition ) {
				if ( ! is_array( $definition ) ) {
					continue;
				}

				$knowledge_key = isset( $definition['knowledge_key'] ) ? sanitize_key( $definition['knowledge_key'] ) : '';
				$meta_key      = isset( $definition['meta_key'] ) ? sanitize_key( $definition['meta_key'] ) : '';
				$label         = isset( $definition['label'] ) ? trim( (string) $definition['label'] ) : '';
				$type          = isset( $definition['type'] ) ? sanitize_key( $definition['type'] ) : 'text';
				$provider_name = isset( $definition['provider'] ) ? sanitize_key( $definition['provider'] ) : '';

				if ( '' === $knowledge_key || '' === $meta_key || '' === $label ) {
					continue;
				}

				$resolved[ $knowledge_key ] = array(
					'knowledge_key' => $knowledge_key,
					'meta_key'      => $meta_key,
					'label'         => $label,
					'type'          => $type,
					'provider'      => $provider_name,
				);
			}
		}

		$definitions = array_values( $resolved );

		/**
		 * Filter normalized structured field definitions after provider merging.
		 *
		 * @param array<int,array<string,string>> $definitions Definitions.
		 * @param WP_Post                        $post        Source post.
		 */
		$filtered = apply_filters( 'wpaic_structured_field_definitions', $definitions, $post );

		return is_array( $filtered ) ? $filtered : $definitions;
	}
}
