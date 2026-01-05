<?php
/**
 * Plugin Name: Podify Podcast Importer
 * Description: Production-ready podcast importer with RSS sync, admin management, list-style UI, and extensible architecture.
 * Version: 1.0.0
 * Author: Podify
 * Text Domain: podify-podcast-importer
 */

defined('ABSPATH') || exit;

define('PODIFY_PODCAST_VERSION', '1.0.0');
define('PODIFY_PODCAST_PATH', plugin_dir_path(__FILE__));
define('PODIFY_PODCAST_URL', plugin_dir_url(__FILE__));

require_once PODIFY_PODCAST_PATH . 'includes/class-loader.php';

register_activation_hook(__FILE__, ['PodifyPodcast\\Core\\Loader', 'activate']);
register_deactivation_hook(__FILE__, ['PodifyPodcast\\Core\\Loader', 'deactivate']);

add_action('init', ['PodifyPodcast\\Core\\Loader', 'init']);
