<?php
/**
 * Orchestrate one grounded, single-turn answer.
 *
 * v0.7.0 Stage 2 connects the verified Usage Guard to the real Provider
 * boundary. Retrieval and Grounding run first; Evidence and Prompt are built
 * only for allow_answer; Usage Guard reserves quota immediately before the
 * AI Manager call; successful Provider usage is then added to the counters.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Grounded_Answer_Service {

	/** @var WPAIC_Local_Retriever */
	protected $retriever;

	/** @var WPAIC_Grounding_Gate */
	protected $gate;

	/** @var WPAIC_Evidence_Pack_Builder */
	protected $evidence_builder;

	/** @var WPAIC_Grounded_Prompt_Builder */
	protected $prompt_builder;

	/** @var WPAIC_AI_Manager */
	protected $ai_manager;

	/** @var WPAIC_Usage_Guard */
	protected $usage_guard;

	public function __construct( WPAIC_Local_Retriever $retriever, WPAIC_Grounding_Gate $gate, WPAIC_Evidence_Pack_Builder $evidence_builder, WPAIC_Grounded_Prompt_Builder $prompt_builder, WPAIC_AI_Manager $ai_manager, WPAIC_Usage_Guard $usage_guard ) {
		$this->retriever         = $retriever;
		$this->gate              = $gate;
		$this->evidence_builder  = $evidence_builder;
		$this->prompt_builder    = $prompt_builder;
		$this->ai_manager        = $ai_manager;
		$this->usage_guard       = $usage_guard;
	}

	/**
	 * Run one single-turn grounded answer request.
	 *
	 * @param string              $question User question.
	 * @param array<string,mixed> $options  Retrieval / answer options.
	 * @return array<string,mixed>|WP_Error
	 */
	public function answer( $question, array $options = array() ) {
		$started_at = microtime( true );
		$question   = trim( (string) $question );

		if ( '' === $question ) {
			return new WP_Error( 'wpaic_grounded_empty_question', 'Grounded Answer 需要有效的问题。' );
		}

		$candidate_limit = isset( $options['candidate_limit'] ) ? absint( $options['candidate_limit'] ) : 100;
		$top_k           = isset( $options['top_k'] ) ? absint( $options['top_k'] ) : 5;
		$retrieval       = $this->retriever->retrieve(
			$question,
			array(
				'candidate_limit' => $candidate_limit,
				'top_k'           => $top_k,
			)
		);

		if ( is_wp_error( $retrieval ) ) {
			return $retrieval;
		}

		$gate = $this->gate->evaluate( $retrieval );
		$base = array(
			'stage'       => 'grounded_answer',
			'question'    => $question,
			'retrieval'   => $retrieval,
			'gate'        => $gate,
			'evidence'    => $this->skipped_evidence(),
			'prompt'      => $this->skipped_prompt(),
			'answer'      => '',
			'answer_type' => 'deterministic',
			'source_trace'=> array(),
			'provider'    => '',
			'model'       => '',
			'usage'       => $this->empty_usage(),
			'usage_guard' => $this->skipped_usage_guard(),
			'finish_reason' => '',
			'ai_called'   => false,
			'token_usage' => 0,
			'provider_elapsed_ms' => 0,
			'elapsed_ms'  => 0,
		);

		if ( empty( $gate['allow_ai'] ) || 'allow_answer' !== ( isset( $gate['decision'] ) ? (string) $gate['decision'] : '' ) ) {
			$base['answer']     = isset( $gate['reason_message'] ) ? (string) $gate['reason_message'] : '当前本地证据不足，未调用 AI。';
			$base['elapsed_ms'] = $this->elapsed_ms( $started_at );
			return $base;
		}

		$evidence = $this->evidence_builder->build( $retrieval );
		if ( is_wp_error( $evidence ) ) {
			return $evidence;
		}
		$base['evidence']     = $evidence;
		$base['source_trace'] = $this->build_source_trace( $evidence );

		$prompt = $this->prompt_builder->build( $question, $evidence );
		if ( is_wp_error( $prompt ) ) {
			return $prompt;
		}
		$prompt['status'] = 'built';
		$base['prompt']   = $prompt;

		$provider = $this->ai_manager->get_current_provider();
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}
		if ( ! $provider->is_configured() ) {
			return new WP_Error( 'wpaic_grounded_ai_not_configured', 'Grounding Gate 已允许回答，但当前 AI Provider 尚未配置 API Key。请先到“AI 设置”完成配置。' );
		}

		// v0.7.0 Stage 2: Usage Guard is the final application-layer boundary
		// immediately before the real Provider call. Missing enabled-scope
		// context fails closed; blocked reservations consume zero Provider calls.
		$usage_context = $this->build_usage_context( $options );
		$reservation   = $this->usage_guard->reserve( $usage_context );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		$reservation['status'] = 'evaluated';
		$base['usage_guard']    = $reservation;

		if ( empty( $reservation['reserved'] ) || 'allow' !== ( isset( $reservation['decision'] ) ? (string) $reservation['decision'] : '' ) ) {
			$base['answer']      = $this->usage_block_message( isset( $reservation['reason_code'] ) ? (string) $reservation['reason_code'] : 'usage_guard_unavailable' );
			$base['answer_type'] = 'usage_block';
			$base['elapsed_ms']  = $this->elapsed_ms( $started_at );
			return $base;
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => (string) $prompt['system_prompt'],
			),
			array(
				'role'    => 'user',
				'content' => (string) $prompt['user_prompt'],
			),
		);

		$ai_options = array(
			'max_tokens'  => 640,
			'temperature' => 0.1,
		);
		$ai_options = apply_filters( 'wpaic_grounded_answer_ai_options', $ai_options, $question, $evidence, $retrieval );
		$ai_options = is_array( $ai_options ) ? $ai_options : array();

		// From this exact point onward the request is allowed to cross the
		// Provider boundary. Denied Gate paths return above and never reach here.
		$base['ai_called'] = true;
		$response          = $this->ai_manager->chat( $messages, $ai_options );
		if ( is_wp_error( $response ) ) {
			$error_data = $response->get_error_data();
			$wrapped    = array(
				'phase'     => 'provider_call',
				'ai_called' => true,
				'original'  => $error_data,
			);
			if ( is_array( $error_data ) && ! empty( $error_data['status_code'] ) ) {
				$wrapped['status_code'] = (int) $error_data['status_code'];
			}
			return new WP_Error(
				$response->get_error_code(),
				$response->get_error_message(),
				$wrapped
			);
		}

		$usage = isset( $response['usage'] ) && is_array( $response['usage'] ) ? $response['usage'] : $this->empty_usage();

		// Provider Call count was reserved before the request. Only trusted usage
		// returned by a successful Provider response is added to token counters.
		$usage_states = $this->usage_guard->record_usage( $usage_context, $usage );
		if ( is_wp_error( $usage_states ) ) {
			$base['usage_guard']['accounting_warning'] = $usage_states->get_error_code();
		} else {
			$base['usage_guard']['scopes'] = $usage_states;
		}

		$base['answer']              = isset( $response['content'] ) ? trim( (string) $response['content'] ) : '';
		$base['answer_type']         = 'ai';
		$base['provider']            = isset( $response['provider'] ) ? (string) $response['provider'] : '';
		$base['model']               = isset( $response['response_model'] ) && '' !== (string) $response['response_model'] ? (string) $response['response_model'] : ( isset( $response['request_model'] ) ? (string) $response['request_model'] : '' );
		$base['usage']               = $usage;
		$base['finish_reason']       = isset( $response['finish_reason'] ) ? (string) $response['finish_reason'] : '';
		$base['provider_elapsed_ms'] = isset( $response['elapsed_ms'] ) ? (int) $response['elapsed_ms'] : 0;
		$base['token_usage']         = isset( $usage['total_tokens'] ) ? (int) $usage['total_tokens'] : 0;
		$base['elapsed_ms']          = $this->elapsed_ms( $started_at );

		if ( '' === $base['answer'] ) {
			return new WP_Error( 'wpaic_grounded_empty_answer', 'AI Provider 已返回，但 Grounded Answer 为空。' );
		}

		return $base;
	}

	/**
	 * Build Usage Context from caller-owned stable keys.
	 *
	 * @param array<string,mixed> $options Request options.
	 * @return WPAIC_Usage_Context
	 */
	protected function build_usage_context( array $options ) {
		$raw = isset( $options['usage_context'] ) && is_array( $options['usage_context'] ) ? $options['usage_context'] : array();
		return new WPAIC_Usage_Context( $raw );
	}

	/** @return array<string,mixed> */
	protected function skipped_usage_guard() {
		return array(
			'status'        => 'skipped',
			'decision'      => 'skipped',
			'reserved'      => false,
			'reason_code'   => 'grounding_gate_not_allowed',
			'blocked_scope' => '',
			'scopes'        => array(),
		);
	}

	/** @return string */
	protected function usage_block_message( $reason_code ) {
		switch ( (string) $reason_code ) {
			case 'conversation_limit_reached':
				return '当前会话的 AI 调用额度已用完。';
			case 'visitor_daily_limit_reached':
				return '当前访客今日的 AI 调用额度已用完，请稍后再试。';
			case 'site_daily_limit_reached':
				return '本站今日的 AI 调用额度已用完，请稍后再试。';
			case 'missing_usage_context':
				return 'Usage Guard 缺少必要的调用上下文，已阻止 AI 调用。';
			default:
				return 'Usage Guard 当前无法允许 AI 调用。';
		}
	}

	/** @return array<string,mixed> */
	protected function skipped_evidence() {
		return array(
			'status'       => 'skipped',
			'source_count' => 0,
			'total_chars'  => 0,
			'limits'       => $this->evidence_builder->get_limits(),
			'sources'      => array(),
			'reason'       => 'gate_not_allowed',
		);
	}

	/** @return array<string,mixed> */
	protected function skipped_prompt() {
		return array(
			'status'        => 'skipped',
			'system_prompt' => '',
			'user_prompt'   => '',
			'evidence_ids'  => array(),
			'char_count'    => 0,
		);
	}

	/** @return array<string,mixed> */
	protected function empty_usage() {
		return array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
			'raw'               => array(),
		);
	}

	/**
	 * Source Trace is application-owned. The model may cite [S1], but it never
	 * gets to invent the canonical Source ID / title / URL displayed by the UI.
	 *
	 * @param array<string,mixed> $evidence Evidence Pack.
	 * @return array<int,array<string,mixed>>
	 */
	protected function build_source_trace( array $evidence ) {
		$sources = isset( $evidence['sources'] ) && is_array( $evidence['sources'] ) ? $evidence['sources'] : array();
		$trace   = array();
		foreach ( $sources as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$trace[] = array(
				'evidence_id' => isset( $source['evidence_id'] ) ? (string) $source['evidence_id'] : '',
				'source_id'   => isset( $source['source_id'] ) ? (string) $source['source_id'] : '',
				'source_hash' => isset( $source['source_hash'] ) ? (string) $source['source_hash'] : '',
				'title'       => isset( $source['title'] ) ? (string) $source['title'] : '',
				'url'         => isset( $source['url'] ) ? (string) $source['url'] : '',
				'post_type'   => isset( $source['post_type'] ) ? (string) $source['post_type'] : '',
				'knowledge_type' => isset( $source['knowledge_type'] ) ? (string) $source['knowledge_type'] : '',
				'score'       => isset( $source['score'] ) ? (int) $source['score'] : 0,
				'coverage'    => isset( $source['coverage'] ) ? (float) $source['coverage'] : 0,
			);
		}
		return $trace;
	}

	/** @param float $started_at Start time. @return int */
	protected function elapsed_ms( $started_at ) {
		return (int) round( ( microtime( true ) - (float) $started_at ) * 1000 );
	}
}
