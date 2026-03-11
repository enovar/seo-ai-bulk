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
}
