<?php

namespace SEOAIBulk\Providers;

defined( 'ABSPATH' ) || exit;

class ProviderOpenRouter extends ProviderBase {

	const DEFAULT_MODEL = 'meta-llama/llama-3.2-3b-instruct:free';
	const API_ENDPOINT  = 'https://openrouter.ai/api/v1/chat/completions';
	public function generate( string $prompt ): string {
		if ( empty( $this->api_key ) ) {
			throw new \Exception( __( 'OpenRouter API key is not configured. Get a free key at openrouter.ai', 'seo-ai-bulk' ) );
		}

		$model = $this->model ?: self::DEFAULT_MODEL;

		$body = wp_json_encode( [
			'model'    => $model,
			'messages' => [
				[
					'role'    => 'system',
					'content' => 'You are an SEO expert. Always respond with valid JSON only, no markdown or explanation.',
				],
				[
					'role'    => 'user',
					'content' => $prompt,
				],
			],
			'temperature' => 0.3,
			'provider'    => [
				'allow_fallbacks' => true,
			],
		] );

		$args = [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
				'HTTP-Referer'  => home_url(),
				'X-Title'       => get_bloginfo( 'name' ),
			],
			'body'    => $body,
			'timeout' => 90,
		];

		$response = $this->request_with_retry( self::API_ENDPOINT, $args );

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );

		if ( 200 !== $status ) {
			$this->throw_api_error( $status, $raw );
		}

		$data = json_decode( $raw, true );

		return $data['choices'][0]['message']['content'] ?? '';
	}
}
