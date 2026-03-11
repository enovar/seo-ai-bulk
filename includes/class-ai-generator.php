<?php

namespace SEOAIBulk;

defined( 'ABSPATH' ) || exit;

class AIGenerator {

	public function generate( \WP_Post $post ): array {
		$provider = $this->get_provider();
		$prompt   = $this->build_prompt( $post );

		$raw = $provider->generate( $prompt );

		return $this->parse_response( $raw );
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
			[ '{post_title}', '{post_content}' ],
			[ $post->post_title, $content_stripped ],
			$template
		);

		return $prompt;
	}

	private function default_prompt_template(): string {
		return 'Generate SEO metadata for the following content. Respond ONLY with a JSON object containing exactly these keys: "title" (max 60 characters), "description" (max 160 characters), "keyword" (single focus keyword or short phrase). Do not include any explanation or markdown.

Title: {post_title}

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

		return [
			'title'       => isset( $data['title'] )       ? sanitize_text_field( $data['title'] )       : '',
			'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'keyword'     => isset( $data['keyword'] )     ? sanitize_text_field( $data['keyword'] )     : '',
		];
	}
}
