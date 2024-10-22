<?php

/**
 * Plugin Name: Multisite URL Fixer
 * Plugin URI: https://github.com/noticiesinfo/multisite-url-fixer
 * Description: Fixes WordPress issues with home and site URL on multisite when using Bedrock
 * Version: 1.0.0
 * Author: factoria.lu
 * Author URI: https://factoria.lu
 * License: GPL v2+
 */

class_exists('Noticiesinfo\Utils\URLFixer') || require_once __DIR__ . '/vendor/autoload.php';

use Noticiesinfo\Utils\URLFixer;

if (is_multisite()) {
    add_action('plugins_loaded', function () {
        (new URLFixer())->addFilters();
    });
}
