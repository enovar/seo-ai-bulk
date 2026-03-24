<?php

namespace SEOAIBulk;

defined( 'ABSPATH' ) || exit;

class BulkAction {

	public function __construct() {
		add_action( 'init', [ $this, 'register_hooks' ], 20 );
		add_action( 'admin_notices', [ $this, 'maybe_show_trigger_notice' ] );
	}

	public function register_hooks(): void {
		foreach ( Plugin::get_supported_post_types() as $post_type ) {
			add_filter( "bulk_actions-edit-{$post_type}", [ $this, 'register_bulk_action' ] );
			add_filter( "handle_bulk_actions-edit-{$post_type}", [ $this, 'handle_bulk_action' ], 10, 3 );
		}
	}

	public function register_bulk_action( array $actions ): array {
		$actions['seoai_generate'] = __( 'Generate SEO with AI', 'seo-ai-bulk' );
		return $actions;
	}

	public function handle_bulk_action( string $redirect_url, string $action, array $post_ids ): string {
		if ( 'seoai_generate' !== $action ) {
			return $redirect_url;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return $redirect_url;
		}

		$transient_key = 'seoai_post_ids_' . get_current_user_id();
		set_transient( $transient_key, array_map( 'intval', $post_ids ), 5 * MINUTE_IN_SECONDS );

		$redirect_url = add_query_arg( 'seoai_trigger', '1', $redirect_url );

		return $redirect_url;
	}

	public function maybe_show_trigger_notice(): void {
		// JS handles the modal opening; this hook is reserved for fallback notices if needed.
	}
}
