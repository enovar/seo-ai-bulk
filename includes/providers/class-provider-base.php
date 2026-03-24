<?php

namespace SEOAIBulk\Providers;

defined( 'ABSPATH' ) || exit;

abstract class ProviderBase {

	protected string $api_key;
	protected string $model;

	/**
	 * @param string $api_key  Pass directly (e.g. from test form) or leave empty to read from DB.
	 * @param string $model    Pass directly or leave empty to read from DB.
	 */
	public function __construct( string $api_key = '', string $model = '' ) {
		$this->api_key = $api_key !== '' ? $api_key : (string) get_option( 'seoai_api_key', '' );
		$this->model   = $model   !== '' ? $model   : (string) get_option( 'seoai_model', '' );
	}

	/**
	 * Send a prompt to the AI provider and return the raw text response.
	 *
	 * @throws \Exception On API error or network failure.
	 */
	abstract public function generate( string $prompt ): string;

	protected function throw_wp_error( \WP_Error $error ): void {
		throw new \Exception(
			sprintf(
				/* translators: %s: error message */
				__( 'HTTP request failed: %s', 'seo-ai-bulk' ),
				$error->get_error_message()
			)
		);
	}

	protected function throw_api_error( int $status_code, string $body ): void {
		throw new \Exception(
			sprintf(
				/* translators: 1: HTTP status code, 2: response body */
				__( 'API returned HTTP %1$d: %2$s', 'seo-ai-bulk' ),
				$status_code,
				esc_html( mb_substr( $body, 0, 300 ) )
			)
		);
	}

	/**
	 * Send a POST request and automatically retry on HTTP 429 (rate limit).
	 * Reads the wait time from the Retry-After header or the response body.
	 *
	 * @throws \Exception On network failure or when retries are exhausted.
	 */
	protected function request_with_retry( string $url, array $args, int $max_retries = 3 ): array {
		$attempt = 0;
		while ( true ) {
			$response = wp_remote_post( $url, $args );

			if ( is_wp_error( $response ) ) {
				$this->throw_wp_error( $response );
			}

			$status = wp_remote_retrieve_response_code( $response );

			if ( 429 !== $status || $attempt >= $max_retries ) {
				return $response;
			}

			$wait = $this->parse_retry_after( $response );
			sleep( $wait );
			$attempt++;
		}
	}

	private function parse_retry_after( array $response ): int {
		$header = wp_remote_retrieve_header( $response, 'retry-after' );
		if ( $header && is_numeric( $header ) ) {
			return (int) ceil( (float) $header ) + 1;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( preg_match( '/try again in (\d+(?:\.\d+)?)s/i', $body, $m ) ) {
			return (int) ceil( (float) $m[1] ) + 1;
		}
		return 5;
	}
}
