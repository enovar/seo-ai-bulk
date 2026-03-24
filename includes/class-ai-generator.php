<?php

namespace SEOAIBulk;

defined( 'ABSPATH' ) || exit;

class AIGenerator {

	public function generate( \WP_Post $post ): array {
		$provider = $this->get_provider();
		$prompt   = $this->build_prompt( $post );

		$raw    = $provider->generate( $prompt );
		$result = $this->parse_response( $raw );

		// Verify the keyword appears in the slug, title, and description.
		// If not, find the best word that exists in all three.
		if ( ! $this->keyword_in_all_three( $result['keyword'], $post->post_name, $result['title'], $result['description'] ) ) {
			$result['keyword'] = $this->find_common_keyword( $post->post_name, $result['title'], $result['description'] );
		}

		return $result;
	}

	public function test_connection(): void {
		$provider = $this->get_provider();
		$provider->generate( 'Say "OK" if you can read this.' );
	}

	private function get_provider(): Providers\ProviderBase {
		$provider_id = get_option( 'seoai_provider', 'groq' );

		switch ( $provider_id ) {
			case 'gemini':
				return new Providers\ProviderGemini();
			case 'ollama':
				return new Providers\ProviderOllama();
			case 'groq':
				return new Providers\ProviderGroq();
			case 'openrouter':
				return new Providers\ProviderOpenRouter();
			case 'huggingface':
				return new Providers\ProviderHuggingFace();
			case 'openai':
			default:
				return new Providers\ProviderOpenAI();
		}
	}

	private function build_prompt( \WP_Post $post ): string {
		$template = get_option( 'seoai_prompt_template', $this->default_prompt_template() );

		$content_stripped = wp_strip_all_tags( $post->post_content );
		// Limit content length to avoid token limits
		$content_stripped = mb_substr( $content_stripped, 0, 3000 );

		$prompt = str_replace(
			[ '{post_title}', '{post_content}', '{post_slug}' ],
			[ $post->post_title, $content_stripped, $post->post_name ],
			$template
		);

		return $prompt;
	}

	private function default_prompt_template(): string {
		return 'Generate SEO metadata for the following content. Respond ONLY with a JSON object containing exactly these keys: "title" (max 60 characters), "description" (max 160 characters), "keyword" (exactly one single word that appears in all three: the URL slug, the title, and the description — choose it carefully so it is present in all three). Do not include any explanation or markdown.

Title: {post_title}
URL slug: {post_slug}

Content: {post_content}';
	}

	private function parse_response( string $raw ): array {
		// Strip markdown code blocks if present
		$raw = preg_replace( '/^```(?:json)?\s*/m', '', $raw );
		$raw = preg_replace( '/\s*```$/m', '', $raw );
		$raw = trim( $raw );

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: raw AI response */
					__( 'Could not parse AI response as JSON. Raw response: %s', 'seo-ai-bulk' ),
					esc_html( mb_substr( $raw, 0, 200 ) )
				)
			);
		}

		// Enforce single word: take only the first word, lowercase
		$keyword = isset( $data['keyword'] ) ? sanitize_text_field( $data['keyword'] ) : '';
		$keyword = strtolower( strtok( trim( $keyword ), " \t\n\r-_" ) );

		return [
			'title'       => isset( $data['title'] )       ? sanitize_text_field( $data['title'] )           : '',
			'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'keyword'     => $keyword,
		];
	}

	private function keyword_in_all_three( string $keyword, string $slug, string $title, string $description ): bool {
		if ( empty( $keyword ) ) {
			return false;
		}
		$in_slug  = in_array( $keyword, explode( '-', $slug ), true );
		$in_title = in_array( $keyword, $this->extract_words( $title ), true );
		$in_desc  = in_array( $keyword, $this->extract_words( $description ), true );
		return $in_slug && $in_title && $in_desc;
	}

	/**
	 * Find the best single word present in the slug, title, and description.
	 * Falls back to the best slug word if no common word exists.
	 */
	private function find_common_keyword( string $slug, string $title, string $description ): string {
		$stop_words  = [ 'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'it', 'as', 'be', 'do', 'if', 'its', 'are', 'was', 'has', 'can', 'not', 'this', 'that', 'from', 'your' ];
		$slug_words  = array_diff( explode( '-', $slug ), $stop_words );
		$title_words = $this->extract_words( $title );
		$desc_words  = $this->extract_words( $description );

		$common = array_filter(
			$slug_words,
			fn( $w ) => strlen( $w ) > 2
				&& ! in_array( $w, $stop_words, true )
				&& in_array( $w, $title_words, true )
				&& in_array( $w, $desc_words, true )
		);

		if ( ! empty( $common ) ) {
			usort( $common, fn( $a, $b ) => strlen( $b ) - strlen( $a ) );
			return reset( $common );
		}

		// Fallback: best word from slug only
		$slug_words = array_filter( $slug_words, fn( $w ) => strlen( $w ) > 2 );
		if ( ! empty( $slug_words ) ) {
			usort( $slug_words, fn( $a, $b ) => strlen( $b ) - strlen( $a ) );
			return reset( $slug_words );
		}

		return explode( '-', $slug )[0];
	}

	private function extract_words( string $text ): array {
		preg_match_all( '/[a-z]+/i', strtolower( $text ), $matches );
		return $matches[0] ?? [];
	}
}
