<?php

namespace SEOAIBulk\Providers;

defined( 'ABSPATH' ) || exit;

class ProviderGemini extends ProviderBase {

	const DEFAULT_MODEL = 'gemini-1.5-flash';
	const API_BASE      = 'https://generativelanguage.googleapis.com/v1beta/models/';

	public function generate( string $prompt ): string {
		if ( empty( $this->api_key ) ) {
			throw new \Exception( __( 'Gemini API key is not configured. Get a free key at aistudio.google.com', 'seo-ai-bulk' ) );
		}

		$model    = $this->model ?: self::DEFAULT_MODEL;
		$endpoint = self::API_BASE . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $this->api_key );

		$body = wp_json_encode( [
			'contents' => [
				[
					'parts' => [
						[ 'text' => $prompt ],
					],
				],
			],
			'generationConfig' => [
				'temperature'      => 0.3,
				'responseMimeType' => 'application/json',
			],
			'systemInstruction' => [
				'parts' => [
					[ 'text' => 'You are an SEO expert. Always respond with valid JSON only, no markdown or explanation.' ],
				],
			],
		] );

		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Content-Type' => 'application/json',
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

		return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
	}
}
