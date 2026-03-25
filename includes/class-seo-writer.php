<?php

namespace SEOAIBulk;

defined( 'ABSPATH' ) || exit;

class SEOWriter {

	public function write( int $post_id, string $title, string $description, string $keyword ): void {
		$plugin = $this->detect_seo_plugin();

		switch ( $plugin ) {
			case 'yoast':
				$this->write_yoast( $post_id, $title, $description, $keyword );
				break;
			case 'rankmath':
				$this->write_rankmath( $post_id, $title, $description, $keyword );
				break;
			case 'aioseo':
				$this->write_aioseo( $post_id, $title, $description, $keyword );
				break;
			default:
				$this->write_fallback( $post_id, $title, $description, $keyword );
				break;
		}
	}

	public function detect_seo_plugin(): string {
		if ( class_exists( 'WPSEO_Options' ) ) {
			return 'yoast';
		}
		if ( class_exists( 'RankMath' ) ) {
			return 'rankmath';
		}
		if ( class_exists( 'AIOSEO\Plugin\AIOSEO' ) || class_exists( 'AIOSEO' ) ) {
			return 'aioseo';
		}
		return 'fallback';
	}

	private function write_yoast( int $post_id, string $title, string $description, string $keyword ): void {
		update_post_meta( $post_id, '_yoast_wpseo_title',    $title );
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', $description );
		update_post_meta( $post_id, '_yoast_wpseo_focuskw',  $keyword );
	}

	private function write_rankmath( int $post_id, string $title, string $description, string $keyword ): void {
		update_post_meta( $post_id, 'rank_math_title',          $title );
		update_post_meta( $post_id, 'rank_math_description',    $description );
		update_post_meta( $post_id, 'rank_math_focus_keyword',  $keyword );
		// RankMath score is calculated by the block editor's JS engine.
		// Delete the stale score so it shows as unscored rather than a wrong low value.
		// It will be recalculated correctly the next time the post is opened/saved in the editor.
		delete_post_meta( $post_id, 'rank_math_seo_score' );
	}

	private function write_aioseo( int $post_id, string $title, string $description, string $keyword ): void {
		update_post_meta( $post_id, '_aioseo_title',       $title );
		update_post_meta( $post_id, '_aioseo_description', $description );
		// AIOSEO stores keyphrases as a JSON array
		update_post_meta( $post_id, '_aioseo_keyphrases', wp_json_encode( [ [ 'keyphrase' => $keyword ] ] ) );
	}

	private function write_fallback( int $post_id, string $title, string $description, string $keyword ): void {
		update_post_meta( $post_id, '_seoai_title',         $title );
		update_post_meta( $post_id, '_seoai_description',   $description );
		update_post_meta( $post_id, '_seoai_focus_keyword', $keyword );
	}
}
