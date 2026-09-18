<?php
/** Front-end chat widget view. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="cw-chat" data-wpaic-chat>
	<section class="cw-panel" data-cw-panel hidden aria-label="<?php echo esc_attr( $support_name ); ?>">
		<header class="cw-header">
			<div class="cw-avatar" aria-hidden="true"><?php echo esc_html( $avatar ); ?></div>
			<div class="cw-info">
				<strong><?php echo esc_html( $support_name ); ?></strong>
				<span><i></i><?php esc_html_e( 'Online · AI knowledge assistant', 'wp-ai-chat-lab' ); ?></span>
			</div>
			<button class="cw-new" data-cw-new type="button" aria-label="<?php esc_attr_e( 'Start new conversation', 'wp-ai-chat-lab' ); ?>" title="<?php esc_attr_e( 'Start new conversation', 'wp-ai-chat-lab' ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
			</button>
			<button class="cw-close" data-cw-close type="button" aria-label="<?php esc_attr_e( 'Close chat', 'wp-ai-chat-lab' ); ?>">×</button>
		</header>

		<div class="cw-messages" data-cw-messages aria-live="polite">
			<div class="cw-message cw-welcome">
				<p><?php esc_html_e( 'Hello! How can we help you today?', 'wp-ai-chat-lab' ); ?></p>
				<small><?php echo esc_html( $support_name ); ?></small>
			</div>
			<div class="cw-quick" data-cw-quick>
				<button type="button">Decking</button>
				<button type="button">Wall Cladding</button>
				<button type="button">Fence</button>
				<button type="button">Get a Quote</button>
				<button type="button">Sample Request</button>
			</div>
		</div>

		<form class="cw-form" data-cw-form>
			<input data-cw-input type="text" maxlength="1000" placeholder="<?php esc_attr_e( 'Type your message...', 'wp-ai-chat-lab' ); ?>" autocomplete="off">
			<button data-cw-send type="submit" aria-label="<?php esc_attr_e( 'Send message', 'wp-ai-chat-lab' ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 3 9.7 14.3M21 3l-7.2 18-4.1-6.7L3 10.2 21 3Z"/></svg>
			</button>
		</form>
	</section>

	<button class="cw-launcher" data-cw-launcher type="button" aria-label="<?php esc_attr_e( 'Open chat', 'wp-ai-chat-lab' ); ?>" aria-expanded="false">
		<svg viewBox="0 0 24 24" aria-hidden="true">
			<path d="M7.5 18.5 4 20l1.2-3.8A7.7 7.7 0 0 1 4 12.1C4 7.6 7.6 4 12 4s8 3.6 8 8-3.6 8-8 8c-1.6 0-3.2-.5-4.5-1.5Z"/>
			<path d="M8.5 12h.01M12 12h.01M15.5 12h.01"/>
		</svg>
	</button>
</div>
