<?php
/**
 * Plugin Name: Wedding RSVP
 * Description: Form xác nhận tham dự đám cưới. Mỗi khách một link riêng, dùng để dự trù tiệc và xe đưa đón.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Wedding RSVP
 * Text Domain: wedding-rsvp
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WEDDING_RSVP_VERSION', '1.0.0');
define('WEDDING_RSVP_DB_VERSION', '1.0.0');
define('WEDDING_RSVP_FILE', __FILE__);
define('WEDDING_RSVP_DIR', plugin_dir_path(__FILE__));
define('WEDDING_RSVP_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    $map = array(
        'Wedding_RSVP_Activator' => 'includes/class-activator.php',
        'Wedding_RSVP_Settings' => 'includes/class-settings.php',
        'Wedding_RSVP_Text' => 'includes/class-text.php',
        'Wedding_RSVP_Codes' => 'includes/class-codes.php',
        'Wedding_RSVP_Guest_Repo' => 'includes/class-guest-repo.php',
        'Wedding_RSVP_Response_Repo' => 'includes/class-response-repo.php',
        'Wedding_RSVP_Pickup_Repo' => 'includes/class-pickup-repo.php',
        'Wedding_RSVP_Tabular_Parser' => 'includes/class-tabular-parser.php',
        'Wedding_RSVP_Importer' => 'includes/class-importer.php',
        'Wedding_RSVP_Exporter' => 'includes/class-exporter.php',
        'Wedding_Stats' => 'includes/class-stats.php',
        'Wedding_RSVP_Templates' => 'includes/class-templates.php',
        'Wedding_RSVP_REST' => 'includes/class-rest.php',
        'Wedding_RSVP_Frontend' => 'includes/class-frontend.php',
        'Wedding_RSVP_Admin' => 'admin/class-admin.php',
        'Wedding_RSVP_Parse_Exception' => 'includes/class-exceptions.php',
        'Wedding_RSVP_Import_Exception' => 'includes/class-exceptions.php',
        'Wedding_RSVP_Validation_Exception' => 'includes/class-exceptions.php',
    );
    if (isset($map[$class])) {
        require_once WEDDING_RSVP_DIR . $map[$class];
    }
});

function wedding_rsvp_asset_version($relative) {
    $path = WEDDING_RSVP_DIR . ltrim($relative, '/');
    return file_exists($path) ? (string) filemtime($path) : WEDDING_RSVP_VERSION;
}

add_action('plugins_loaded', function () {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if (preg_match('#/wedding(/|$|\?)#', $uri) && !defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    Wedding_RSVP_Activator::maybe_upgrade();
}, 0);

register_activation_hook(__FILE__, array('Wedding_RSVP_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Wedding_RSVP_Activator', 'deactivate'));

add_action('plugins_loaded', function () {
    new Wedding_RSVP_Frontend();
    new Wedding_RSVP_REST();
    if (is_admin()) {
        new Wedding_RSVP_Admin();
    }
});
