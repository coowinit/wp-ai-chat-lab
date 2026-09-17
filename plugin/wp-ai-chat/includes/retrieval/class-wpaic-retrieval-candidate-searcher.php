<?php
/**
 * Recall active Knowledge Store candidates with transparent SQL LIKE queries.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Retrieval_Candidate_Searcher {

	/** @var wpdb */
	protected $wpdb;

	/** @var string */
	protected $table;

	/** @var array<int,string> */
	protected $fields = array( 'title', 'excerpt', 'taxonomies', 'structured_data', 'content' );

	/**
	 * @param WPAIC_Knowledge_Store_Repository $repository Store repository.
	 */
	public function __construct( WPAIC_Knowledge_Store_Repository $repository ) {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $repository->get_table_name();
	}

	/**
	 * @param array<string,mixed> $query Normalized query representation.
	 * @param int                 $limit Candidate limit.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function search( array $query, $limit = 100 ) {
		$limit = $this->normalize_limit( $limit );

		$needles = array();
		$phrase  = isset( $query['phrase'] ) ? trim( (string) $query['phrase'] ) : '';
		if ( '' !== $phrase ) {
			$needles[] = $phrase;
		}

		$terms = isset( $query['terms'] ) && is_array( $query['terms'] ) ? $query['terms'] : array();
		foreach ( $terms as $term ) {
			$term = trim( (string) $term );
			if ( '' !== $term ) {
				$needles[] = $term;
			}
		}
		$needles = array_values( array_unique( $needles ) );

		if ( empty( $needles ) ) {
			return array();
		}

		$where_groups = array();
		$params       = array( 'active' );

		foreach ( $needles as $needle ) {
			$like        = '%' . $this->wpdb->esc_like( $needle ) . '%';
			$field_parts = array();
			foreach ( $this->fields as $field ) {
				$field_parts[] = "{$field} LIKE %s";
				$params[]      = $like;
			}
			$where_groups[] = '(' . implode( ' OR ', $field_parts ) . ')';
		}

		$params[] = $limit;
		$sql      = "SELECT id, source_id, source_type, object_id, post_type, knowledge_type, title, excerpt, url, taxonomies, structured_data, content, source_hash, source_status, store_status, source_updated_at, updated_at
			FROM {$this->table}
			WHERE store_status = %s
			AND (" . implode( ' OR ', $where_groups ) . ")
			ORDER BY updated_at DESC, id DESC
			LIMIT %d";

		$prepared = $this->wpdb->prepare( $sql, $params );
		$rows     = $this->wpdb->get_results( $prepared, ARRAY_A );

		if ( null === $rows && '' !== trim( (string) $this->wpdb->last_error ) ) {
			return new WP_Error( 'wpaic_retrieval_candidate_query_failed', 'Local Retrieval Candidate 查询失败。' );
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$results = array();
		foreach ( $rows as $row ) {
			$results[] = $this->prepare_candidate( $row, $query );
		}

		return $results;
	}

	/**
	 * @param mixed $limit Candidate limit.
	 * @return int
	 */
	public function normalize_limit( $limit ) {
		$limit = absint( $limit );
		if ( ! $limit ) {
			$limit = 100;
		}
		$limit = (int) apply_filters( 'wpaic_retrieval_candidate_limit', $limit );
		return max( 20, min( 200, $limit ) );
	}

	/**
	 * @param array<string,mixed> $row   Raw row.
	 * @param array<string,mixed> $query Query representation.
	 * @return array<string,mixed>
	 */
	protected function prepare_candidate( array $row, array $query ) {
		$taxonomies      = $this->decode_json_array( isset( $row['taxonomies'] ) ? $row['taxonomies'] : '' );
		$structured_data = $this->decode_json_array( isset( $row['structured_data'] ) ? $row['structured_data'] : '' );

		$field_values = array(
			'title'           => isset( $row['title'] ) ? (string) $row['title'] : '',
			'excerpt'         => isset( $row['excerpt'] ) ? (string) $row['excerpt'] : '',
			'taxonomies'      => $this->json_search_text( $taxonomies ),
			'structured_data' => $this->json_search_text( $structured_data ),
			'content'         => isset( $row['content'] ) ? (string) $row['content'] : '',
		);

		$terms = isset( $query['terms'] ) && is_array( $query['terms'] ) ? $query['terms'] : array();
		if ( empty( $terms ) && ! empty( $query['phrase'] ) ) {
			$terms = array( (string) $query['phrase'] );
		}

		$matched_terms  = array();
		$matched_fields = array();
		foreach ( $field_values as $field => $value ) {
			foreach ( $terms as $term ) {
				if ( $this->contains( $value, (string) $term ) ) {
					$matched_terms[]  = (string) $term;
					$matched_fields[] = $field;
				}
			}
		}

		$matched_terms  = array_values( array_unique( $matched_terms ) );
		$matched_fields = array_values( array_unique( $matched_fields ) );

		return array(
			'id'              => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'source_id'       => isset( $row['source_id'] ) ? (string) $row['source_id'] : '',
			'object_id'       => isset( $row['object_id'] ) ? (int) $row['object_id'] : 0,
			'post_type'       => isset( $row['post_type'] ) ? (string) $row['post_type'] : '',
			'knowledge_type'  => isset( $row['knowledge_type'] ) ? (string) $row['knowledge_type'] : '',
			'title'           => isset( $row['title'] ) ? (string) $row['title'] : '',
			'url'             => isset( $row['url'] ) ? (string) $row['url'] : '',
			'taxonomies'      => $taxonomies,
			'structured_data' => $structured_data,
			'content'         => isset( $row['content'] ) ? (string) $row['content'] : '',
			'matched_terms'   => $matched_terms,
			'matched_fields'  => $matched_fields,
			'snippet'         => $this->build_snippet( $field_values, $matched_fields, $matched_terms ),
		);
	}

	/** @return array<string,mixed> */
	protected function decode_json_array( $value ) {
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/** @return string */
	protected function json_search_text( array $value ) {
		$encoded = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return false === $encoded ? '' : (string) $encoded;
	}

	/** @return bool */
	protected function contains( $haystack, $needle ) {
		$haystack = (string) $haystack;
		$needle   = trim( (string) $needle );
		if ( '' === $needle ) {
			return false;
		}
		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( $haystack, $needle, 0, 'UTF-8' );
		}
		return false !== stripos( $haystack, $needle );
	}

	/** @return string */
	protected function build_snippet( array $field_values, array $matched_fields, array $matched_terms ) {
		$order = array( 'structured_data', 'title', 'taxonomies', 'excerpt', 'content' );
		$text  = '';
		foreach ( $order as $field ) {
			if ( in_array( $field, $matched_fields, true ) && ! empty( $field_values[ $field ] ) ) {
				$text = (string) $field_values[ $field ];
				break;
			}
		}
		if ( '' === $text ) {
			$text = isset( $field_values['content'] ) ? (string) $field_values['content'] : '';
		}

		$text = preg_replace( '/\s+/u', ' ', trim( wp_strip_all_tags( $text ) ) );
		$text = is_string( $text ) ? $text : '';
		if ( '' === $text ) {
			return '';
		}

		$max = 240;
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $text, 'UTF-8' ) > $max ? mb_substr( $text, 0, $max, 'UTF-8' ) . '…' : $text;
		}
		return strlen( $text ) > $max ? substr( $text, 0, $max ) . '…' : $text;
	}
}
