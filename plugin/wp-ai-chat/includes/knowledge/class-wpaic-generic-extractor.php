<?php
/**
 * Generic WordPress post -> Unified Knowledge Source extractor.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Generic_Extractor {

	/** @var WPAIC_Content_Normalizer */
	protected $normalizer;

	/**
	 * @param WPAIC_Content_Normalizer $normalizer Content normalizer.
	 */
	public function __construct( WPAIC_Content_Normalizer $normalizer ) {
		$this->normalizer = $normalizer;
	}

	/**
	 * Extract one WordPress post on demand.
	 *
	 * @param int  $post_id           Post ID.
	 * @param bool $allow_unpublished Allow admin preview of non-published content.
	 * @return array<string,mixed>|WP_Error
	 */
	public function extract( $post_id, $allow_unpublished = false ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'wpaic_knowledge_post_not_found', '未找到对应的 WordPress 内容。' );
		}

		if ( 'revision' === $post->post_type || 'auto-draft' === $post->post_status || 'trash' === $post->post_status ) {
			return new WP_Error( 'wpaic_knowledge_invalid_post', '该内容不能作为 Knowledge Source。' );
		}

		if ( ! $allow_unpublished && 'publish' !== $post->post_status ) {
			return new WP_Error( 'wpaic_knowledge_not_published', '只有已发布内容才能进入正式 Knowledge Source。' );
		}

		$is_manual = WPAIC_Manual_Knowledge::POST_TYPE === $post->post_type;
		$title     = get_the_title( $post_id );
		$excerpt   = $this->normalizer->normalize( $post->post_excerpt );
		$content   = $this->normalizer->normalize( $post->post_content );
		$url       = '';

		if ( ! $is_manual ) {
			$permalink = get_permalink( $post_id );
			$url       = is_string( $permalink ) ? $permalink : '';
		}

		$source = WPAIC_Knowledge_Source::prepare(
			array(
				'source_id'       => ( $is_manual ? 'manual_' : 'wordpress_post_' ) . $post_id,
				'source_type'     => $is_manual ? 'manual' : 'wordpress_post',
				'object_id'       => $post_id,
				'post_type'       => $post->post_type,
				'knowledge_type'  => $post->post_type,
				'title'           => is_string( $title ) ? $title : '',
				'excerpt'         => $excerpt,
				'url'             => $url,
				'taxonomies'      => $this->get_taxonomies( $post ),
				'structured_data' => $this->get_allowed_meta( $post ),
				'content'         => $content,
				'updated_at'      => $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified,
				'status'          => $post->post_status,
			)
		);

		/**
		 * Filter the final unified source. This is an escape hatch for developers,
		 * not a field-mapping UI.
		 *
		 * @param array<string,mixed> $source  Unified source.
		 * @param WP_Post             $post    Source post.
		 */
		$filtered = apply_filters( 'wpaic_knowledge_source', $source, $post );

		if ( is_array( $filtered ) ) {
			$source = WPAIC_Knowledge_Source::prepare( $filtered );
		}

		return $source;
	}

	/**
	 * Generic extraction never reads arbitrary post meta by default. Developers
	 * can explicitly allow business keys with a filter until dedicated
	 * structured extractors are introduced.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string,mixed>
	 */
	protected function get_allowed_meta( WP_Post $post ) {
		/**
		 * @param array<int,string> $keys      Explicitly allowed keys. Default empty.
		 * @param string            $post_type Current post type.
		 * @param int               $post_id   Current post ID.
		 */
		$keys = apply_filters( 'wpaic_allowed_meta_keys_for_post_type', array(), $post->post_type, $post->ID );

		if ( ! is_array( $keys ) || empty( $keys ) ) {
			return array();
		}

		$data = array();
		foreach ( array_unique( array_map( 'sanitize_key', $keys ) ) as $key ) {
			if ( '' === $key ) {
				continue;
			}

			$value = get_post_meta( $post->ID, $key, true );
			if ( '' === $value || null === $value ) {
				continue;
			}

			if ( is_scalar( $value ) ) {
				$data[ $key ] = (string) $value;
			} elseif ( is_array( $value ) ) {
				$data[ $key ] = $value;
			}
		}

		return $data;
	}

	/**
	 * Read public taxonomies as structured context. They are intentionally kept
	 * separate from content so they do not pollute the normalized body text.
	 * Manual Knowledge includes its internal knowledge category as well.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string,array<int,string>>
	 */
	protected function get_taxonomies( WP_Post $post ) {
		$objects   = get_object_taxonomies( $post->post_type, 'objects' );
		$taxonomies = array();
		$is_manual = WPAIC_Manual_Knowledge::POST_TYPE === $post->post_type;

		foreach ( $objects as $taxonomy ) {
			if ( ! $taxonomy instanceof WP_Taxonomy ) {
				continue;
			}

			$is_manual_category = $is_manual && WPAIC_Manual_Knowledge::TAXONOMY === $taxonomy->name;
			if ( ! $taxonomy->public && ! $is_manual_category ) {
				continue;
			}

			$terms = get_the_terms( $post, $taxonomy->name );
			if ( ! is_array( $terms ) || empty( $terms ) ) {
				continue;
			}

			$names = array();
			foreach ( $terms as $term ) {
				if ( $term instanceof WP_Term ) {
					$names[] = $term->name;
				}
			}

			$names = array_values( array_unique( array_filter( $names ) ) );
			sort( $names, SORT_NATURAL | SORT_FLAG_CASE );

			if ( ! empty( $names ) ) {
				$taxonomies[ $taxonomy->name ] = $names;
			}
		}

		ksort( $taxonomies );
		return $taxonomies;
	}
}
