<?php
/**
 * Plugin Name:       SEO AI Bulk
 * Plugin URI:        https://github.com/lalmeida/seo-ai-bulk
 * Description:       Bulk-generate SEO title, meta description, and focus keyword using AI for posts and pages.
 * Version:           1.0.0
 * Author:            SEO AI Bulk
 * Text Domain:       seo-ai-bulk
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'SEOAI_VERSION', '1.0.0' );
define( 'SEOAI_PATH', plugin_dir_path( __FILE__ ) );
define( 'SEOAI_URL', plugin_dir_url( __FILE__ ) );

// Autoload all includes
$includes = [
	'providers/class-provider-base.php',
	'providers/class-provider-openai.php',
	'providers/class-provider-gemini.php',
	'providers/class-provider-ollama.php',
	'providers/class-provider-groq.php',
	'providers/class-provider-openrouter.php',
	'providers/class-provider-huggingface.php',
	'class-ai-generator.php',
	'class-seo-writer.php',
	'class-admin-settings.php',
	'class-bulk-action.php',
	'class-ajax-handler.php',
	'class-plugin.php',
];

foreach ( $includes as $file ) {
	require_once SEOAI_PATH . 'includes/' . $file;
}

// Bootstrap the plugin
SEOAIBulk\Plugin::get_instance();
