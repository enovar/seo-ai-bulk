<?php

namespace SEOAIBulk\Providers;

defined( 'ABSPATH' ) || exit;

class ProviderOllama extends ProviderBase {

	const DEFAULT_MODEL    = 'llama3.2';
	const DEFAULT_ENDPOINT = 'http://localhost:11434/api/generate';

	private string $endpoint;

	public function __construct( string $api_key = '', string $model = '', string $endpoint = '' ) {
		parent::__construct( $api_key, $model );
		$this->endpoint = $endpoint !== '' ? $endpoint : (string) get_option( 'seoai_ollama_endpoint', self::DEFAULT_ENDPOINT );
	}

	public function generate( string $prompt ): string {
		$model = $this->model ?: self::DEFAULT_MODEL;

		$body = wp_json_encode( [
			'model'  => $model,
			'prompt' => $prompt,
			'stream' => false,
			'system' => 'You are an SEO expert. Always respond with valid JSON only, no markdown or explanation.',
			'format' => 'json',
		] );

		$response = $this->request_with_retry( $this->endpoint, [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => $body,
			'timeout' => 120,
		] );

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );

		if ( 200 !== $status ) {
			$this->throw_api_error( $status, $raw );
		}

		$data = json_decode( $raw, true );

		return $data['response'] ?? '';
	}
}
