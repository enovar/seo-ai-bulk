<?php

namespace SEOAIBulk\Providers;

defined( 'ABSPATH' ) || exit;

class ProviderOpenAI extends ProviderBase {

	const DEFAULT_MODEL = 'gpt-3.5-turbo';
	const API_ENDPOINT  = 'https://api.openai.com/v1/chat/completions';

	public function generate( string $prompt ): string {
		if ( empty( $this->api_key ) ) {
			throw new \Exception( __( 'OpenAI API key is not configured.', 'seo-ai-bulk' ) );
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
		] );

		$response = $this->request_with_retry( self::API_ENDPOINT, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'    => $body,
			'timeout' => 60,
		] );

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );

		if ( 200 !== $status ) {
			$this->throw_api_error( $status, $raw );
		}

		$data = json_decode( $raw, true );

		return $data['choices'][0]['message']['content'] ?? '';
	}
}
