<?php
/**
 * Incremental Knowledge Store synchronization for normal WordPress content
 * lifecycle events.
 *
 * Stage 4 keeps day-to-day maintenance local to the single source that was
 * actually changed. Full Sync remains available for initial population and
 * reconciliation, but ordinary saves must never trigger a site-wide scan.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Knowledge_Incremental_Sync {

	/** @var WPAIC_Source_Discovery */
	protected $discovery;

	/** @var WPAIC_Knowledge_Store_Repository */
	protected $repository;

	/** @var WPAIC_Knowledge_Lifecycle_Manager */
	protected $lifecycle;

	/** @var array<int,bool> Re-entrancy guard keyed by WordPress object ID. */
	protected $processing = array();

	/**
	 * @param WPAIC_Source_Discovery            $discovery Source discovery.
	 * @param WPAIC_Knowledge_Store_Repository $repository Store repository.
	 * @param WPAIC_Knowledge_Lifecycle_Manager $lifecycle Lifecycle manager.
	 */
	public function __construct( WPAIC_Source_Discovery $discovery, WPAIC_Knowledge_Store_Repository $repository, WPAIC_Knowledge_Lifecycle_Manager $lifecycle ) {
		$this->discovery  = $discovery;
		$this->repository = $repository;
		$this->lifecycle  = $lifecycle;

		// wp_after_insert_post runs after the normal save_post hooks, so legacy
		// meta boxes and WEM Content Fields have already persisted their values
		// before we rebuild the Unified Knowledge Source.
		add_action( 'wp_after_insert_post', array( $this, 'handle_after_insert_post' ), 20, 4 );

		// Permanent deletion needs a post-delete hook so get_post( $post_id ) is
		// genuinely missing and Lifecycle can mark the historical Snapshot as
		// source_deleted rather than merely not_published.
		add_action( 'deleted_post', array( $this, 'handle_deleted_post' ), 20, 2 );
	}

	/**
	 * Incrementally sync one changed source after WordPress has completed the
	 * normal save pipeline.
	 *
	 * @param int          $post_id     Post ID.
	 * @param WP_Post      $post        Current post.
	 * @param bool         $update      Whether this was an update.
	 * @param WP_Post|null $post_before Previous post object when available.
	 * @return void
	 */
	public function handle_after_insert_post( $post_id, $post, $update, $post_before ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$post_id = absint( $post_id );

		if ( ! $post_id || ! $post instanceof WP_Post ) {
			return;
		}

		if ( $this->is_revision_or_autosave( $post_id, $post ) ) {
			return;
		}

		$current = $this->repository->find_by_object_id( $post_id );

		if ( ! $this->should_sync_saved_post( $post, $current ) ) {
			return;
		}

		$this->run_sync( $post_id, 'save', $post->post_type );
	}

	/**
	 * Incrementally deactivate a persisted Snapshot after permanent deletion.
	 *
	 * @param int     $post_id Deleted post ID.
	 * @param WP_Post $post    Deleted post object provided by WordPress.
	 * @return void
	 */
	public function handle_deleted_post( $post_id, $post ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return;
		}

		$current = $this->repository->find_by_object_id( $post_id );
		if ( ! $current ) {
			return;
		}

		$post_type = $post instanceof WP_Post ? $post->post_type : ( isset( $current['post_type'] ) ? $current['post_type'] : '' );
		$this->run_sync( $post_id, 'delete', $post_type );
	}

	/**
	 * Decide whether a normal post save is relevant to the Knowledge Store.
	 *
	 * Existing Store rows must always be revisited because a save can change
	 * eligibility (publish → draft, disabled source type, etc.). New rows are
	 * only created for currently published + enabled knowledge sources.
	 *
	 * @param WP_Post                  $post    Post object.
	 * @param array<string,mixed>|null $current Existing Store row.
	 * @return bool
	 */
	protected function should_sync_saved_post( WP_Post $post, $current ) {
		if ( is_array( $current ) ) {
			return true;
		}

		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		if ( WPAIC_Manual_Knowledge::POST_TYPE === $post->post_type ) {
			return true;
		}

		if ( ! $this->discovery->is_discoverable( $post->post_type ) ) {
			return false;
		}

		$enabled = get_option( WPAIC_OPTION_KNOWLEDGE_SOURCES, array() );
		$enabled = is_array( $enabled ) ? array_values( array_unique( array_map( 'sanitize_key', $enabled ) ) ) : array();

		return in_array( $post->post_type, $enabled, true );
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return bool
	 */
	protected function is_revision_or_autosave( $post_id, WP_Post $post ) {
		if ( 'revision' === $post->post_type || 'auto-draft' === $post->post_status ) {
			return true;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Run the existing single-source lifecycle pipeline with a small re-entrancy
	 * guard. Store writes do not update WordPress posts, but the guard makes the
	 * integration safe for future filters/extensions that might do so.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $trigger Trigger name.
	 * @param string $post_type Post type for diagnostics.
	 * @return void
	 */
	protected function run_sync( $post_id, $trigger, $post_type ) {
		$post_id = absint( $post_id );
		if ( isset( $this->processing[ $post_id ] ) ) {
			return;
		}

		$this->processing[ $post_id ] = true;
		$result                       = $this->lifecycle->sync_post( $post_id );
		unset( $this->processing[ $post_id ] );

		$this->record_last_incremental_sync( $post_id, $post_type, $trigger, $result );
	}

	/**
	 * Store only the latest incremental diagnostic event. This is deliberately
	 * not an event log; v0.4.0 keeps one Knowledge Store table and avoids adding
	 * a second log/audit table before a real need exists.
	 *
	 * @param int                          $post_id Post ID.
	 * @param string                       $post_type Post type.
	 * @param string                       $trigger Trigger.
	 * @param array<string,mixed>|WP_Error $result Lifecycle result.
	 * @return void
	 */
	protected function record_last_incremental_sync( $post_id, $post_type, $trigger, $result ) {
		$payload = array(
			'completed_at'    => gmdate( 'Y-m-d H:i:s' ),
			'trigger'         => sanitize_key( $trigger ),
			'object_id'       => absint( $post_id ),
			'post_type'       => sanitize_key( $post_type ),
			'action'          => '',
			'source_id'       => '',
			'store_status'    => '',
			'inactive_reason' => '',
			'error_code'      => '',
			'error_message'   => '',
		);

		if ( is_wp_error( $result ) ) {
			$payload['action']        = 'error';
			$payload['error_code']    = sanitize_key( $result->get_error_code() );
			$payload['error_message'] = sanitize_text_field( $result->get_error_message() );
		} else {
			$payload['action']          = isset( $result['action'] ) ? sanitize_key( $result['action'] ) : '';
			$payload['source_id']       = isset( $result['source_id'] ) ? sanitize_text_field( $result['source_id'] ) : '';
			$payload['store_status']    = isset( $result['store_status'] ) ? sanitize_key( $result['store_status'] ) : '';
			$payload['inactive_reason'] = isset( $result['inactive_reason'] ) ? sanitize_key( $result['inactive_reason'] ) : '';
		}

		update_option( WPAIC_OPTION_LAST_INCREMENTAL_SYNC, $payload, false );
	}
}
