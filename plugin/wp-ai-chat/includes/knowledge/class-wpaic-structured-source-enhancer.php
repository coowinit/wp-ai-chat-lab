<?php
/**
 * Enhance a Generic Knowledge Source with explicit structured business data.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Structured_Source_Enhancer {

	/** @var WPAIC_Structured_Field_Resolver */
	protected $resolver;

	/** @var WPAIC_Structured_Value_Normalizer */
	protected $normalizer;

	/**
	 * @param WPAIC_Structured_Field_Resolver   $resolver   Field resolver.
	 * @param WPAIC_Structured_Value_Normalizer $normalizer Structured value normalizer.
	 */
	public function __construct( WPAIC_Structured_Field_Resolver $resolver, WPAIC_Structured_Value_Normalizer $normalizer ) {
		$this->resolver   = $resolver;
		$this->normalizer = $normalizer;

		add_filter( 'wpaic_knowledge_source', array( $this, 'enhance' ), 20, 2 );
	}

	/**
	 * Add allowlisted structured data to a unified source.
	 *
	 * @param array<string,mixed> $source Unified source prepared by Generic Extractor.
	 * @param WP_Post             $post   Source post.
	 * @return array<string,mixed>
	 */
	public function enhance( $source, $post ) {
		if ( ! is_array( $source ) || ! $post instanceof WP_Post ) {
			return $source;
		}

		$definitions = $this->resolver->resolve( $post );
		if ( empty( $definitions ) ) {
			return $source;
		}

		$structured_data = isset( $source['structured_data'] ) && is_array( $source['structured_data'] ) ? $source['structured_data'] : array();

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$knowledge_key = isset( $definition['knowledge_key'] ) ? sanitize_key( $definition['knowledge_key'] ) : '';
			$meta_key      = isset( $definition['meta_key'] ) ? sanitize_key( $definition['meta_key'] ) : '';
			$label         = isset( $definition['label'] ) ? trim( (string) $definition['label'] ) : '';
			$type          = isset( $definition['type'] ) ? sanitize_key( $definition['type'] ) : 'text';

			if ( '' === $knowledge_key || '' === $meta_key || '' === $label ) {
				continue;
			}

			$value = get_post_meta( $post->ID, $meta_key, true );
			$value = $this->normalizer->normalize( $value, $type );
			if ( null === $value ) {
				continue;
			}

			$structured_data[ $knowledge_key ] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		/**
		 * Filter final AI-visible structured data before Source Hash is rebuilt.
		 *
		 * @param array<string,mixed> $structured_data Structured data.
		 * @param WP_Post             $post            Source post.
		 * @param array<string,mixed> $source          Current unified source.
		 */
		$filtered = apply_filters( 'wpaic_structured_data', $structured_data, $post, $source );
		$source['structured_data'] = is_array( $filtered ) ? $filtered : $structured_data;

		return $source;
	}
}
