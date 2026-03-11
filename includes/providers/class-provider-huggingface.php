<?php

namespace SEOAIBulk\Providers;

defined( 'ABSPATH' ) || exit;

class ProviderHuggingFace extends ProviderBase {

	const DEFAULT_MODEL = 'mistralai/Mistral-7B-Instruct-v0.3';
	const API_BASE      = 'https://api-inference.huggingface.co/models/';

	public function generate( string $prompt ): string {
		if ( empty( $this->api_key ) ) {
			throw new \Exception( __( 'Hugging Face API token is not configured. Get a free token at huggingface.co/settings/tokens', 'seo-ai-bulk' ) );
		}

		$model    = $this->model ?: self::DEFAULT_MODEL;
		$endpoint = self::API_BASE . ltrim( $model, '/' ) . '/v1/chat/completions';

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
			'max_tokens'  => 300,
		] );

		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'    => $body,
			'timeout' => 90,
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
