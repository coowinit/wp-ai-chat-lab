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
	 * Provider order is significant: earlier providers are fallbacks, later
	 * providers have higher value priority when they expose a non-empty value
	 * for the same knowledge_key.
	 *
	 * @param array<int,object> $providers Providers exposing get_definitions().
	 */
	public function __construct( array $providers ) {
		$this->providers = $providers;
	}

	/**
	 * Resolve candidate definitions for a post.
	 *
	 * Definitions are intentionally NOT collapsed by knowledge_key here. The
	 * Structured Source Enhancer evaluates values in provider order, allowing:
	 *
	 * Legacy non-empty -> fallback value
	 * WEM non-empty    -> overrides Legacy
	 * WEM empty        -> Legacy remains
	 *
	 * This keeps schema resolution separate from value fallback.
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

				$resolved[] = array(
					'knowledge_key' => $knowledge_key,
					'meta_key'      => $meta_key,
					'label'         => $label,
					'type'          => $type,
					'provider'      => $provider_name,
				);
			}
		}

		/**
		 * Filter normalized structured field candidates after provider discovery.
		 * Candidate order is meaningful because later non-empty candidates can
		 * override earlier fallback values for the same knowledge_key.
		 *
		 * @param array<int,array<string,string>> $resolved Definitions.
		 * @param WP_Post                        $post     Source post.
		 */
		$filtered = apply_filters( 'wpaic_structured_field_definitions', $resolved, $post );

		return is_array( $filtered ) ? $filtered : $resolved;
	}
}
