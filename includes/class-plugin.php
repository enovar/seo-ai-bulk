<?php

namespace SEOAIBulk;

defined( 'ABSPATH' ) || exit;

class Plugin {

	private static ?Plugin $instance = null;

	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new AdminSettings();
		new BulkAction();
		new AjaxHandler();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_footer', [ $this, 'inject_modal' ] );
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'edit.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'seo-ai-bulk',
			SEOAI_URL . 'admin/css/seo-ai-bulk.css',
			[],
			SEOAI_VERSION
		);

		wp_enqueue_script(
			'seo-ai-bulk',
			SEOAI_URL . 'admin/js/seo-ai-bulk.js',
			[ 'jquery' ],
			SEOAI_VERSION,
			true
		);

		$post_ids = [];
		$transient_key = 'seoai_post_ids_' . get_current_user_id();
		if ( isset( $_GET['seoai_trigger'] ) && '1' === $_GET['seoai_trigger'] ) {
			$post_ids = get_transient( $transient_key ) ?: [];
			delete_transient( $transient_key );
		}

		wp_localize_script( 'seo-ai-bulk', 'seoaiBulk', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'seoai_nonce' ),
			'trigger'   => isset( $_GET['seoai_trigger'] ) && '1' === $_GET['seoai_trigger'],
			'postIds'   => array_map( 'intval', $post_ids ),
			'i18n'      => [
				'generating'  => __( 'Generating...', 'seo-ai-bulk' ),
				'done'        => __( 'Done', 'seo-ai-bulk' ),
				'error'       => __( 'Error', 'seo-ai-bulk' ),
				'saved'       => __( 'Saved', 'seo-ai-bulk' ),
				'skipped'     => __( 'Skipped', 'seo-ai-bulk' ),
				'apply'       => __( 'Apply', 'seo-ai-bulk' ),
				'skip'        => __( 'Skip', 'seo-ai-bulk' ),
				'regenerate'  => __( 'Regenerate', 'seo-ai-bulk' ),
				'applyAll'    => __( 'Apply All', 'seo-ai-bulk' ),
				'close'       => __( 'Close', 'seo-ai-bulk' ),
			],
		] );
	}

	public function inject_modal(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}
		include SEOAI_PATH . 'templates/review-modal.php';
	}

	private function get_supported_post_types(): array {
		return apply_filters( 'seoai_supported_post_types', [ 'post', 'page' ] );
	}
}
