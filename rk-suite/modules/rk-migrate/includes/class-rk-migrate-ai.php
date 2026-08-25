<?php
/**
 * RK_Migrate_AI — optional AI content rewrite on import. Pluggable provider
 * (any OpenAI-compatible chat-completions endpoint). Rewrites placeholder copy
 * for the target client's industry / location / tone.
 *
 * Disabled unless an API key is configured in Settings. Network calls are the
 * site owner's own provider account — no traffic flows through RK Migrate.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RK_Migrate_AI {

	/** Is AI swap usable (key present)? */
	public static function available() {
		$s = RK_Migrate_Settings::instance();
		return $s->can_use( 'ai' ) && '' !== trim( (string) $s->get( 'ai_api_key' ) );
	}

	/**
	 * Rewrite a batch of text strings. Returns map original=>rewritten.
	 * Keeps it conservative: only swaps strings it gets back 1:1.
	 */
	public static function rewrite_batch( array $texts, $context ) {
		if ( ! self::available() || empty( $texts ) ) { return array(); }
		$s = RK_Migrate_Settings::instance();
		$sys = "You localize website copy. Rewrite each numbered line for this business context: {$context}. "
		     . "Keep length and tone similar. Return the SAME numbered list, one rewrite per line, nothing else.";
		$user = '';
		$keys = array_values( $texts );
		foreach ( $keys as $i => $t ) { $user .= ( $i + 1 ) . '. ' . str_replace( "\n", ' ', $t ) . "\n"; }

		$resp = wp_remote_post( $s->get( 'ai_endpoint' ), array(
			'timeout' => 45,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $s->get( 'ai_api_key' ),
			),
			'body' => wp_json_encode( array(
				'model'    => $s->get( 'ai_model' ),
				'messages' => array(
					array( 'role' => 'system', 'content' => $sys ),
					array( 'role' => 'user', 'content' => $user ),
				),
				'temperature' => 0.7,
			) ),
		) );
		if ( is_wp_error( $resp ) ) { return array(); }
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		$content = isset( $body['choices'][0]['message']['content'] ) ? $body['choices'][0]['message']['content'] : '';
		if ( ! $content ) { return array(); }

		$out = array();
		foreach ( explode( "\n", trim( $content ) ) as $line ) {
			if ( preg_match( '/^\s*(\d+)\.\s*(.+)$/', $line, $mm ) ) {
				$idx = (int) $mm[1] - 1;
				if ( isset( $keys[ $idx ] ) ) { $out[ $keys[ $idx ] ] = trim( $mm[2] ); }
			}
		}
		return $out;
	}

	/**
	 * Build an RK_Migrate_Replace from AI rewrites of the visible text in a bundle
	 * directory's pages. Returns a Replace instance (may be empty).
	 */
	/** Walk an Elementor data tree collecting human-visible text strings. */
	private static function collect_texts( $node, array &$out, int $limit ): void {
		if ( count( $out ) >= $limit ) { return; }
		$keys = array( 'title', 'editor', 'text', 'description', 'heading_title', 'caption',
			'title_text', 'description_text', 'sub_heading', 'button_text', 'cta_text', 'tab_title', 'before_text', 'after_text', 'highlighted_text', 'rotating_text' );
		if ( is_array( $node ) ) {
			foreach ( $node as $k => $v ) {
				if ( is_string( $v ) && is_string( $k ) && in_array( $k, $keys, true ) ) {
					$t = trim( wp_strip_all_tags( $v ) );
					if ( mb_strlen( $t ) >= 8 && mb_strlen( $t ) <= 200 && ! isset( $out[ $t ] ) ) {
						$out[ $t ] = $t;
						if ( count( $out ) >= $limit ) { return; }
					}
				} elseif ( is_array( $v ) ) {
					self::collect_texts( $v, $out, $limit );
					if ( count( $out ) >= $limit ) { return; }
				}
			}
		}
	}

	public static function build_replace_for_bundle( $base_dir, $context, $limit = 60 ) {
		$rep = new RK_Migrate_Replace( array() );
		if ( ! self::available() ) { return $rep; }
		$texts = array();
		foreach ( glob( trailingslashit( $base_dir ) . '*.json' ) as $f ) {
			if ( basename( $f ) === 'manifest.json' ) { continue; }
			$data = json_decode( (string) file_get_contents( $f ), true );
			if ( ! is_array( $data ) ) { continue; }
			self::collect_texts( $data, $texts, $limit );
			if ( count( $texts ) >= $limit ) { break; }
		}
		$map = self::rewrite_batch( $texts, $context );
		foreach ( $map as $from => $to ) { if ( $to && $to !== $from ) { $rep->add( $from, $to ); } }
		return $rep;
	}
}
