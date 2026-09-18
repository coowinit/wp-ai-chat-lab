<?php
/**
 * Deterministic Lead Trigger Policy for v0.9.0.
 *
 * This class decides whether the UI may offer an Inquiry CTA. It does not
 * persist inquiries, call AI providers, or render front-end forms.
 *
 * @package WP_AI_Chat_Lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Lead_Trigger_Policy {

	const TRIGGER_MANUAL            = 'manual';
	const TRIGGER_COMMERCIAL_INTENT = 'commercial_intent';
	const TRIGGER_NO_ANSWER         = 'no_answer';
	const TRIGGER_USAGE_BLOCKED     = 'usage_blocked';

	/**
	 * Default commercial-intent phrases. These are only an initial baseline.
	 * Once the option exists, an intentionally empty list must remain empty.
	 *
	 * @return array<int,string>
	 */
	public static function get_default_keywords() {
		return array(
			'quote',
			'quotation',
			'price',
			'pricing',
			'sample',
			'order',
			'MOQ',
			'minimum order',
			'bulk',
			'custom',
			'customized',
			'OEM',
			'ODM',
			'distributor',
			'dealer',
			'shipping',
			'delivery',
			'contact sales',
		);
	}

	/**
	 * Read the administrator-controlled keyword list.
	 *
	 * @return array<int,string>
	 */
	public function get_keywords() {
		$stored = get_option( WPAIC_OPTION_LEAD_COMMERCIAL_KEYWORDS, false );
		if ( false === $stored ) {
			return self::get_default_keywords();
		}

		return $this->normalize_keywords( $stored );
	}

	/**
	 * Normalize textarea / array input into a stable ordered keyword list.
	 * Empty input intentionally remains empty and disables keyword matching.
	 *
	 * @param mixed $input Raw input.
	 * @return array<int,string>
	 */
	public function normalize_keywords( $input ) {
		$items = array();

		if ( is_string( $input ) ) {
			$parts = preg_split( '/[\r\n,，;；]+/u', $input );
			$items = is_array( $parts ) ? $parts : array();
		} elseif ( is_array( $input ) ) {
			foreach ( $input as $value ) {
				if ( ! is_scalar( $value ) ) {
					continue;
				}
				$parts = preg_split( '/[\r\n,，;；]+/u', (string) $value );
				if ( is_array( $parts ) ) {
					$items = array_merge( $items, $parts );
				}
			}
		}

		$clean = array();
		$seen  = array();

		foreach ( $items as $item ) {
			$item = sanitize_text_field( wp_unslash( (string) $item ) );
			$item = preg_replace( '/\s+/u', ' ', trim( $item ) );
			$item = is_string( $item ) ? $item : '';

			if ( '' === $item ) {
				continue;
			}

			if ( $this->string_length( $item ) > 120 ) {
				$item = $this->string_substr( $item, 0, 120 );
			}

			$key = $this->lower( $item );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$clean[]      = $item;

			if ( count( $clean ) >= 200 ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Evaluate a potential lead offer.
	 *
	 * Expected context keys:
	 * - manual: bool
	 * - question: string
	 * - response_type: answer|clarify|no_answer|blocked|error
	 * - block_reason: conversation_limit_reached|visitor_daily_limit_reached|site_daily_limit_reached
	 *
	 * @param array<string,mixed> $context Evaluation context.
	 * @return array<string,mixed>
	 */
	public function evaluate( array $context ) {
		$manual        = ! empty( $context['manual'] );
		$question      = isset( $context['question'] ) ? trim( (string) $context['question'] ) : '';
		$response_type = isset( $context['response_type'] ) ? sanitize_key( (string) $context['response_type'] ) : 'answer';
		$block_reason  = isset( $context['block_reason'] ) ? sanitize_key( (string) $context['block_reason'] ) : '';

		if ( $manual ) {
			return $this->offer(
				self::TRIGGER_MANUAL,
				'Contact Sales',
				'primary',
				'用户主动请求联系团队，因此直接提供询盘入口。'
			);
		}

		// Clarification and temporary/system errors should never be converted into
		// automatic lead capture just because the question contains a keyword.
		if ( in_array( $response_type, array( 'clarify', 'error' ), true ) ) {
			return $this->no_offer( '当前状态应优先澄清问题或恢复服务，不自动邀请提交询盘。' );
		}

		$matched_keyword = $this->match_commercial_keyword( $question );
		if ( '' !== $matched_keyword ) {
			return $this->offer(
				self::TRIGGER_COMMERCIAL_INTENT,
				'Request a Quote',
				'primary',
				'问题命中了管理员配置的商业需求关键词。',
				$matched_keyword
			);
		}

		if ( 'no_answer' === $response_type ) {
			return $this->offer(
				self::TRIGGER_NO_ANSWER,
				'Leave a Message',
				'primary',
				'当前知识不足以可靠回答，可自然邀请访客把需求留给团队。'
			);
		}

		if ( 'blocked' === $response_type ) {
			if ( 'conversation_limit_reached' === $block_reason ) {
				return $this->offer(
					self::TRIGGER_USAGE_BLOCKED,
					'Contact Us',
					'secondary',
					'当前 Conversation 已达到额度；主操作仍应是开始新会话，询盘只作为次级入口。'
				);
			}

			if ( in_array( $block_reason, array( 'visitor_daily_limit_reached', 'site_daily_limit_reached' ), true ) ) {
				return $this->offer(
					self::TRIGGER_USAGE_BLOCKED,
					'Leave Your Requirement',
					'primary',
					'AI 今日暂时无法继续服务，询盘是自然的后续入口。'
				);
			}
		}

		return $this->no_offer( '当前回答不属于已定义的 Lead Trigger 场景。' );
	}

	/**
	 * Return the first matching configured keyword, or an empty string.
	 *
	 * ASCII word/phrase matching uses Unicode-aware token boundaries to avoid
	 * matching "order" inside "border". Non-ASCII keywords use substring
	 * matching so Chinese and other scripts work naturally inside sentences.
	 *
	 * @param string $question User question.
	 * @return string
	 */
	public function match_commercial_keyword( $question ) {
		$text = $this->normalize_text( $question );
		if ( '' === $text ) {
			return '';
		}

		foreach ( $this->get_keywords() as $keyword ) {
			$needle = $this->normalize_text( $keyword );
			if ( '' === $needle ) {
				continue;
			}

			if ( preg_match( '/[^\x00-\x7F]/', $needle ) ) {
				if ( false !== $this->string_pos( $text, $needle ) ) {
					return $keyword;
				}
				continue;
			}

			$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $needle, '/' ) . '(?![\p{L}\p{N}])/iu';
			if ( 1 === preg_match( $pattern, $text ) ) {
				return $keyword;
			}
		}

		return '';
	}

	/** @return array<string,mixed> */
	protected function offer( $trigger_type, $cta_label, $placement, $reason, $matched_keyword = '' ) {
		return array(
			'offer'           => true,
			'trigger_type'    => (string) $trigger_type,
			'cta_label'       => (string) $cta_label,
			'placement'       => (string) $placement,
			'matched_keyword' => (string) $matched_keyword,
			'reason'          => (string) $reason,
		);
	}

	/** @return array<string,mixed> */
	protected function no_offer( $reason ) {
		return array(
			'offer'           => false,
			'trigger_type'    => '',
			'cta_label'       => '',
			'placement'       => '',
			'matched_keyword' => '',
			'reason'          => (string) $reason,
		);
	}

	/** @return string */
	protected function normalize_text( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = preg_replace( '/\s+/u', ' ', trim( $value ) );
		$value = is_string( $value ) ? $value : '';
		return $this->lower( $value );
	}

	/** @return string */
	protected function lower( $value ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $value, 'UTF-8' ) : strtolower( (string) $value );
	}

	/** @return int */
	protected function string_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value, 'UTF-8' ) : strlen( (string) $value );
	}

	/** @return string */
	protected function string_substr( $value, $start, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $value, $start, $length, 'UTF-8' ) : substr( (string) $value, $start, $length );
	}

	/** @return int|false */
	protected function string_pos( $haystack, $needle ) {
		return function_exists( 'mb_strpos' ) ? mb_strpos( (string) $haystack, (string) $needle, 0, 'UTF-8' ) : strpos( (string) $haystack, (string) $needle );
	}
}
