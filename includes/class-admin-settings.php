<?php

namespace SEOAIBulk;

defined( 'ABSPATH' ) || exit;

class AdminSettings {

	const OPTION_GROUP = 'seoai_settings';

	public function __construct() {
		add_action( 'admin_menu',    [ $this, 'add_menu' ] );
		add_action( 'admin_init',    [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_settings_assets' ] );
	}

	public function add_menu(): void {
		add_options_page(
			__( 'SEO AI Bulk Settings', 'seo-ai-bulk' ),
			__( 'SEO AI Bulk', 'seo-ai-bulk' ),
			'manage_options',
			'seo-ai-bulk',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_settings_assets( string $hook ): void {
		if ( 'settings_page_seo-ai-bulk' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'seo-ai-bulk-settings',
			SEOAI_URL . 'admin/js/seo-ai-bulk.js',
			[ 'jquery' ],
			SEOAI_VERSION,
			true
		);

		wp_localize_script( 'seo-ai-bulk-settings', 'seoaiBulk', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'seoai_nonce' ),
			'trigger' => false,
			'postIds' => [],
			'i18n'    => [
				'testing'  => __( 'Testing...', 'seo-ai-bulk' ),
				'testOk'   => __( 'Connection successful!', 'seo-ai-bulk' ),
				'testFail' => __( 'Connection failed.', 'seo-ai-bulk' ),
			],
		] );
	}

	public function register_settings(): void {
		register_setting( self::OPTION_GROUP, 'seoai_provider',         [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'groq' ] );
		register_setting( self::OPTION_GROUP, 'seoai_api_key',          [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
		register_setting( self::OPTION_GROUP, 'seoai_model',            [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
		register_setting( self::OPTION_GROUP, 'seoai_ollama_endpoint',  [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'http://localhost:11434/api/generate' ] );
		register_setting( self::OPTION_GROUP, 'seoai_prompt_template',  [ 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$provider        = get_option( 'seoai_provider', 'groq' );
		$api_key         = get_option( 'seoai_api_key', '' );
		$model           = get_option( 'seoai_model', '' );
		$ollama_endpoint = get_option( 'seoai_ollama_endpoint', 'http://localhost:11434/api/generate' );
		$prompt_template = get_option( 'seoai_prompt_template', '' );

		$writer         = new SEOWriter();
		$detected_plugin = $writer->detect_seo_plugin();
		$plugin_labels  = [
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			'aioseo'   => 'AIOSEO',
			'fallback' => __( 'None detected (using fallback meta fields)', 'seo-ai-bulk' ),
		];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SEO AI Bulk Settings', 'seo-ai-bulk' ); ?></h1>

			<?php settings_errors( self::OPTION_GROUP ); ?>

			<?php if ( empty( $api_key ) && $provider !== 'ollama' ) : ?>
			<div class="notice notice-error" style="margin-left:0">
				<p><strong><?php esc_html_e( 'API key is not saved yet.', 'seo-ai-bulk' ); ?></strong>
				<?php esc_html_e( 'Enter your API key and click "Save Settings" before running bulk generation.', 'seo-ai-bulk' ); ?></p>
			</div>
			<?php endif; ?>

			<p>
				<strong><?php esc_html_e( 'Active provider:', 'seo-ai-bulk' ); ?></strong>
				<code><?php echo esc_html( $provider ); ?></code>
				&nbsp;|&nbsp;
				<strong><?php esc_html_e( 'Detected SEO Plugin:', 'seo-ai-bulk' ); ?></strong>
				<?php echo esc_html( $plugin_labels[ $detected_plugin ] ?? $detected_plugin ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="seoai_provider"><?php esc_html_e( 'AI Provider', 'seo-ai-bulk' ); ?></label></th>
						<td>
							<select id="seoai_provider" name="seoai_provider">
								<optgroup label="<?php esc_attr_e( '— Free tier available —', 'seo-ai-bulk' ); ?>">
									<option value="groq"         <?php selected( $provider, 'groq' ); ?>>Groq (Free tier · Llama / Mixtral)</option>
									<option value="openrouter"   <?php selected( $provider, 'openrouter' ); ?>>OpenRouter (Free models available)</option>
									<option value="gemini"       <?php selected( $provider, 'gemini' ); ?>>Google Gemini (Free tier)</option>
									<option value="huggingface"  <?php selected( $provider, 'huggingface' ); ?>>Hugging Face (Free tier)</option>
									<option value="ollama"       <?php selected( $provider, 'ollama' ); ?>>Ollama (Local · 100% free)</option>
								</optgroup>
								<optgroup label="<?php esc_attr_e( '— Paid —', 'seo-ai-bulk' ); ?>">
									<option value="openai"  <?php selected( $provider, 'openai' ); ?>>OpenAI (Paid)</option>
								</optgroup>
							</select>
						</td>
					</tr>
					<tr class="seoai-api-key-row">
						<th scope="row"><label for="seoai_api_key"><?php esc_html_e( 'API Key', 'seo-ai-bulk' ); ?></label></th>
						<td>
							<input type="password" id="seoai_api_key" name="seoai_api_key"
								value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="new-password" />
							<p class="description seoai-key-hint seoai-key-hint-groq">
								<?php esc_html_e( 'Free API key:', 'seo-ai-bulk' ); ?>
								<a href="https://console.groq.com" target="_blank" rel="noopener">console.groq.com</a>
							</p>
							<p class="description seoai-key-hint seoai-key-hint-openrouter">
								<?php esc_html_e( 'Free API key:', 'seo-ai-bulk' ); ?>
								<a href="https://openrouter.ai/keys" target="_blank" rel="noopener">openrouter.ai/keys</a>
								— <?php esc_html_e( 'use a model ending in :free to stay free.', 'seo-ai-bulk' ); ?>
							</p>
							<p class="description seoai-key-hint seoai-key-hint-gemini">
								<?php esc_html_e( 'Free API key:', 'seo-ai-bulk' ); ?>
								<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">aistudio.google.com</a>
							</p>
							<p class="description seoai-key-hint seoai-key-hint-huggingface">
								<?php esc_html_e( 'Free API token:', 'seo-ai-bulk' ); ?>
								<a href="https://huggingface.co/settings/tokens" target="_blank" rel="noopener">huggingface.co/settings/tokens</a>
							</p>
							<p class="description seoai-key-hint seoai-key-hint-openai">
								<?php esc_html_e( 'Paid API key:', 'seo-ai-bulk' ); ?>
								<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com/api-keys</a>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="seoai_model"><?php esc_html_e( 'Model Override', 'seo-ai-bulk' ); ?></label></th>
						<td>
							<input type="text" id="seoai_model" name="seoai_model"
								value="<?php echo esc_attr( $model ); ?>" class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. gpt-4o-mini, gemini-1.5-flash, llama3.2', 'seo-ai-bulk' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to use the default model for the selected provider.', 'seo-ai-bulk' ); ?></p>
						</td>
					</tr>
					<tr class="seoai-ollama-row">
						<th scope="row"><label for="seoai_ollama_endpoint"><?php esc_html_e( 'Ollama Endpoint', 'seo-ai-bulk' ); ?></label></th>
						<td>
							<input type="text" id="seoai_ollama_endpoint" name="seoai_ollama_endpoint"
								value="<?php echo esc_attr( $ollama_endpoint ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="seoai_prompt_template"><?php esc_html_e( 'Custom Prompt Template', 'seo-ai-bulk' ); ?></label></th>
						<td>
							<textarea id="seoai_prompt_template" name="seoai_prompt_template" rows="8" class="large-text"><?php echo esc_textarea( $prompt_template ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Available placeholders: {post_title}, {post_content}. Leave blank to use the default prompt.', 'seo-ai-bulk' ); ?><br>
								<?php esc_html_e( 'The prompt must instruct the AI to respond with a JSON object containing "title", "description", and "keyword" keys.', 'seo-ai-bulk' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p>
					<?php submit_button( __( 'Save Settings', 'seo-ai-bulk' ), 'primary', 'submit', false ); ?>
					&nbsp;
					<button type="button" id="seoai-test-connection" class="button button-secondary">
						<?php esc_html_e( 'Test Connection', 'seo-ai-bulk' ); ?>
					</button>
					<span id="seoai-test-result" style="margin-left:10px;vertical-align:middle;"></span>
				</p>
			</form>
		</div>
		<?php
	}
}
