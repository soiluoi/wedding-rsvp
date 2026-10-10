<?php

class Wedding_RSVP_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('admin_post_wedding_rsvp_save_settings', array($this, 'save_settings'));
        add_action('admin_post_wedding_rsvp_export', array($this, 'export_csv'));
    }

    public function menu() {
        add_menu_page('Đám cưới', 'Wedding', 'manage_options', 'wedding-rsvp', array($this, 'page_dashboard'), 'dashicons-heart', 26);
        add_submenu_page('wedding-rsvp', 'Tổng quan', 'Tổng quan', 'manage_options', 'wedding-rsvp', array($this, 'page_dashboard'));
        add_submenu_page('wedding-rsvp', 'Danh sách khách', 'Danh sách khách', 'manage_options', 'wedding-rsvp-guests', array($this, 'page_guests'));
        add_submenu_page('wedding-rsvp', 'Import', 'Import', 'manage_options', 'wedding-rsvp-import', array($this, 'page_import'));
        add_submenu_page('wedding-rsvp', 'Export', 'Export', 'manage_options', 'wedding-rsvp-export', array($this, 'page_export'));
        add_submenu_page('wedding-rsvp', 'Điểm đón', 'Điểm đón', 'manage_options', 'wedding-rsvp-pickups', array($this, 'page_pickups'));
        add_submenu_page('wedding-rsvp', 'Cài đặt', 'Cài đặt', 'manage_options', 'wedding-rsvp-settings', array($this, 'page_settings'));
    }

    public function enqueue($hook) {
        if (strpos((string) $hook, 'wedding-rsvp') === false) {
            return;
        }
        wp_enqueue_style('wedding-rsvp-admin', WEDDING_RSVP_URL . 'assets/css/admin.css', array(), wedding_rsvp_asset_version('assets/css/admin.css'));
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $script = '';
        if ($page === 'wedding-rsvp') {
            $script = 'dashboard';
        } elseif ($page === 'wedding-rsvp-guests') {
            $script = 'guests';
        } elseif ($page === 'wedding-rsvp-import') {
            $script = 'import';
        } elseif ($page === 'wedding-rsvp-pickups') {
            $script = 'pickups';
        }
        if ($script === '') {
            return;
        }
        wp_enqueue_script('wedding-admin-api', WEDDING_RSVP_URL . 'assets/js/admin-api.js', array(), wedding_rsvp_asset_version('assets/js/admin-api.js'), true);
        $settings = Wedding_RSVP_Settings::get();
        wp_localize_script('wedding-admin-api', 'WeddingRSVP', array(
            'restUrl' => esc_url_raw(rest_url('wedding/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'exportUrl' => admin_url('admin.php?page=wedding-rsvp-export'),
            'presets' => Wedding_RSVP_Exporter::presets(),
            'settings' => array(
                'bride_name' => $settings['bride_name'],
                'groom_name' => $settings['groom_name'],
                'wedding_date' => Wedding_RSVP_Settings::format_datetime($settings['wedding_date']),
                'place' => Wedding_RSVP_Settings::place_label($settings),
                'deadline' => Wedding_RSVP_Settings::format_datetime($settings['deadline']),
                'invite_message_template' => $settings['invite_message_template'],
                'pickup_message_template' => $settings['pickup_message_template'],
                'child_takes_bus_seat' => $settings['child_takes_bus_seat'] ? 1 : 0,
                'child_counts_for_table' => $settings['child_counts_for_table'] ? 1 : 0,
            ),
        ));
        if ($script === 'dashboard' || $script === 'guests') {
            wp_enqueue_script('wedding-clipboard', WEDDING_RSVP_URL . 'assets/js/wedding-clipboard.js', array(), wedding_rsvp_asset_version('assets/js/wedding-clipboard.js'), true);
        }
        $deps = array('wedding-admin-api');
        if ($script === 'dashboard' || $script === 'guests') {
            $deps[] = 'wedding-clipboard';
        }
        wp_enqueue_script(
            'wedding-admin-' . $script,
            WEDDING_RSVP_URL . 'assets/js/admin-' . $script . '.js',
            $deps,
            wedding_rsvp_asset_version('assets/js/admin-' . $script . '.js'),
            true
        );
    }

    public function page_dashboard() {
        $this->guard();
        include WEDDING_RSVP_DIR . 'admin/views/dashboard.php';
    }

    public function page_guests() {
        $this->guard();
        include WEDDING_RSVP_DIR . 'admin/views/guests.php';
    }

    public function page_import() {
        $this->guard();
        include WEDDING_RSVP_DIR . 'admin/views/import.php';
    }

    public function page_export() {
        $this->guard();
        include WEDDING_RSVP_DIR . 'admin/views/export.php';
    }

    public function page_pickups() {
        $this->guard();
        include WEDDING_RSVP_DIR . 'admin/views/pickups.php';
    }

    public function page_settings() {
        $this->guard();
        include WEDDING_RSVP_DIR . 'admin/views/settings.php';
    }

    public function save_settings() {
        $this->guard();
        check_admin_referer('wedding_rsvp_settings');
        Wedding_RSVP_Settings::update(wp_unslash($_POST));
        Wedding_RSVP_Frontend::register_rewrites();
        flush_rewrite_rules(false);
        wp_safe_redirect(admin_url('admin.php?page=wedding-rsvp-settings&updated=1'));
        exit;
    }

    public function export_csv() {
        $this->guard();
        check_admin_referer('wedding_rsvp_export');
        $preset = isset($_POST['preset']) ? sanitize_key(wp_unslash($_POST['preset'])) : 'full';
        if (!isset(Wedding_RSVP_Exporter::presets()[$preset])) {
            $preset = 'full';
        }
        $guests = (new Wedding_RSVP_Guest_Repo())->all_detailed();
        if (empty($_POST['include_inactive'])) {
            $guests = array_values(array_filter($guests, function ($guest) {
                return $guest['status'] === 'active';
            }));
        }
        $csv = Wedding_RSVP_Exporter::to_csv(Wedding_RSVP_Exporter::matrix($guests, $preset, Wedding_RSVP_Settings::get()));
        $stamp = function_exists('wp_date') ? wp_date('Ymd-Hi') : gmdate('Ymd-Hi');
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="wedding-guests-' . $stamp . '.csv"');
        echo $csv;
        exit;
    }

    private function guard() {
        if (!current_user_can('manage_options')) {
            wp_die('Bạn không có quyền xem trang này.');
        }
    }
}
