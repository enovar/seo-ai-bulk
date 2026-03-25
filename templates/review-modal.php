<?php defined( 'ABSPATH' ) || exit; ?>

<div id="seoai-modal-overlay" class="seoai-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="seoai-modal-title">
	<div class="seoai-modal">
		<div class="seoai-modal-header">
			<h2 id="seoai-modal-title"><?php esc_html_e( 'Generate SEO with AI', 'seo-ai-bulk' ); ?></h2>
			<button type="button" class="seoai-close" id="seoai-modal-close" aria-label="<?php esc_attr_e( 'Close', 'seo-ai-bulk' ); ?>">&times;</button>
		</div>

		<div class="seoai-modal-body">
			<div id="seoai-progress-bar-wrap">
				<div id="seoai-progress-bar"><div id="seoai-progress-fill"></div></div>
				<span id="seoai-progress-label"></span>
			</div>

			<div class="seoai-table-wrap">
				<table class="widefat seoai-table" id="seoai-review-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Post', 'seo-ai-bulk' ); ?></th>
							<th><?php esc_html_e( 'SEO Title', 'seo-ai-bulk' ); ?> <small><?php esc_html_e( '(max 60)', 'seo-ai-bulk' ); ?></small></th>
							<th><?php esc_html_e( 'Meta Description', 'seo-ai-bulk' ); ?> <small><?php esc_html_e( '(max 160)', 'seo-ai-bulk' ); ?></small></th>
							<th><?php esc_html_e( 'Focus Keyword', 'seo-ai-bulk' ); ?></th>
							<th><?php esc_html_e( 'Status', 'seo-ai-bulk' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'seo-ai-bulk' ); ?></th>
						</tr>
					</thead>
					<tbody id="seoai-review-tbody">
						<!-- Rows injected by JS -->
					</tbody>
				</table>
			</div>
		</div>

		<div class="seoai-modal-footer">
			<label class="seoai-publish-label">
				<input type="checkbox" id="seoai-publish-on-save" value="1" />
				<?php esc_html_e( 'Publish posts after saving', 'seo-ai-bulk' ); ?>
			</label>
			<button type="button" id="seoai-apply-all" class="button button-primary">
				<?php esc_html_e( 'Apply All', 'seo-ai-bulk' ); ?>
			</button>
			<button type="button" id="seoai-close-modal" class="button button-secondary">
				<?php esc_html_e( 'Close', 'seo-ai-bulk' ); ?>
			</button>
			<div id="seoai-apply-progress-wrap" style="display:none;">
				<div id="seoai-apply-progress-bar"><div id="seoai-apply-progress-fill"></div></div>
				<span id="seoai-apply-progress-label"></span>
			</div>
			<span id="seoai-apply-all-status"></span>
		</div>
	</div>
</div>
