<?php

namespace SEOAIBulk;

defined( 'ABSPATH' ) || exit;

class AjaxHandler {

	public function __construct() {
		add_action( 'wp_ajax_seoai_generate', [ $this, 'handle_generate' ] );
		add_action( 'wp_ajax_seoai_save',     [ $this, 'handle_save' ] );
		add_action( 'wp_ajax_seoai_test',     [ $this, 'handle_test' ] );
	}

	public function handle_generate(): void {
		$this->verify_request();

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-ai-bulk' ) ] );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( [ 'message' => __( 'Post not found.', 'seo-ai-bulk' ) ] );
		}

		try {
			$generator = new AIGenerator();
			$result    = $generator->generate( $post );
			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public function handle_save(): void {
		$this->verify_request();

		$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$title       = isset( $_POST['seo_title'] )       ? sanitize_text_field( wp_unslash( $_POST['seo_title'] ) )       : '';
		$description = isset( $_POST['seo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['seo_description'] ) ) : '';
		$keyword     = isset( $_POST['seo_keyword'] )     ? sanitize_text_field( wp_unslash( $_POST['seo_keyword'] ) )     : '';
		$publish     = ! empty( $_POST['publish'] );

		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'seo-ai-bulk' ) ] );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'seo-ai-bulk' ) ] );
		}

		try {
			$writer = new SEOWriter();
			$writer->write( $post_id, $title, $description, $keyword );

			if ( $publish ) {
				wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
			}

			wp_send_json_success( [ 'message' => __( 'SEO data saved.', 'seo-ai-bulk' ) ] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public function handle_test(): void {
		$this->verify_request();

		// Read form values directly — no DB involved, so test works before saving.
		$provider_id = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : get_option( 'seoai_provider', 'groq' );
		$api_key     = isset( $_POST['api_key'] )  ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) )  : '';
		$model       = isset( $_POST['model'] )    ? sanitize_text_field( wp_unslash( $_POST['model'] ) )    : '';
		$endpoint    = isset( $_POST['endpoint'] ) ? sanitize_text_field( wp_unslash( $_POST['endpoint'] ) ) : '';

		try {
			$provider = $this->make_provider( $provider_id, $api_key, $model, $endpoint );
			$provider->generate( 'Say "OK" if you can read this.' );
			wp_send_json_success( [ 'message' => __( 'Connection successful!', 'seo-ai-bulk' ) ] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	private function make_provider( string $provider_id, string $api_key, string $model, string $endpoint ): Providers\ProviderBase {
		switch ( $provider_id ) {
			case 'gemini':
				return new Providers\ProviderGemini( $api_key, $model );
			case 'ollama':
				return new Providers\ProviderOllama( $api_key, $model, $endpoint );
			case 'groq':
				return new Providers\ProviderGroq( $api_key, $model );
			case 'openrouter':
				return new Providers\ProviderOpenRouter( $api_key, $model );
			case 'huggingface':
				return new Providers\ProviderHuggingFace( $api_key, $model );
			case 'openai':
			default:
				return new Providers\ProviderOpenAI( $api_key, $model );
		}
	}

	private function verify_request(): void {
		if ( ! check_ajax_referer( 'seoai_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'seo-ai-bulk' ) ] );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'seo-ai-bulk' ) ] );
		}
	}
}
