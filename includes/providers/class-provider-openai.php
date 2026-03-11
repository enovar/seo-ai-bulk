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

		$response = wp_remote_post( self::API_ENDPOINT, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'    => $body,
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			$this->throw_wp_error( $response );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );

		if ( 200 !== $status ) {
			$this->throw_api_error( $status, $raw );
		}

		$data = json_decode( $raw, true );

		return $data['choices'][0]['message']['content'] ?? '';
	}
}
