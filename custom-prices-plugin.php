<?php
/**
 * Plugin Name: Custom Prices & Orders
 * Description: افزونه مدیریت قیمت، سفارش، نمودار و چند نقش کاربری.
 * Version: 3.4.3
 * Author: Mr.NT
 */

if (!defined('ABSPATH')) exit;

global $wpdb;
define('CPP_VERSION', '3.4.3');
define('CPP_PATH', plugin_dir_path(__FILE__));
define('CPP_URL', plugin_dir_url(__FILE__));
define('CPP_TEMPLATES_DIR', CPP_PATH . 'templates/');
define('CPP_ASSETS_URL', CPP_URL . 'assets/');
define('CPP_DB_PRODUCTS', $wpdb->prefix . 'cpp_products');
define('CPP_DB_ORDERS', $wpdb->prefix . 'cpp_orders');
define('CPP_DB_CATEGORIES', $wpdb->prefix . 'cpp_categories');
define('CPP_DB_PRICE_HISTORY', $wpdb->prefix . 'cpp_price_history');

require_once(CPP_PATH . 'includes/cpp-core.php');
require_once(CPP_PATH . 'includes/cpp-admin.php');
require_once(CPP_PATH . 'includes/cpp-settings.php');
if (file_exists(CPP_PATH . 'includes/cpp-email.php')) require_once(CPP_PATH . 'includes/cpp-email.php');
if (file_exists(CPP_PATH . 'includes/cpp-sms.php')) require_once(CPP_PATH . 'includes/cpp-sms.php');

register_activation_hook(__FILE__, 'cpp_activate');
function cpp_activate() {
    CPP_Core::create_db_tables();
    if (get_option('cpp_products_per_page') === false) update_option('cpp_products_per_page', 5);
    if (get_option('cpp_admin_capability') === false) update_option('cpp_admin_capability', ['administrator']);
}

// شورت‌کد لیست (اصلاح شده: مرتب‌سازی صعودی ID)
add_shortcode('cpp_products_list', 'cpp_products_list_shortcode');
function cpp_products_list_shortcode($atts) {
    $atts = shortcode_atts( array( 'cat_id' => '', 'ids' => '', 'status' => '1' ), $atts, 'cpp_products_list' );
    global $wpdb;
    $where_clauses = [];
    $query_params = [];

    if ($atts['status'] !== 'all') {
        $where_clauses[] = 'p.is_active = %d';
        $query_params[] = intval($atts['status']);
    }

    if (!empty($atts['cat_id'])) {
        $cat_ids = array_map('intval', explode(',', $atts['cat_id']));
        if (!empty($cat_ids)) {
            $placeholders = implode(', ', array_fill(0, count($cat_ids), '%d'));
            $where_clauses[] = "p.cat_id IN ({$placeholders})";
            $query_params = array_merge($query_params, $cat_ids);
        }
    }
    if (!empty($atts['ids'])) {
        $product_ids = array_map('intval', explode(',', $atts['ids']));
        if (!empty($product_ids)) {
            $placeholders = implode(', ', array_fill(0, count($product_ids), '%d'));
            $where_clauses[] = "p.id IN ({$placeholders})";
            $query_params = array_merge($query_params, $product_ids);
        }
    }

    $where_sql = !empty($where_clauses) ? ' WHERE ' . implode(' AND ', $where_clauses) : '';
    
    // تغییر ORDER BY به ASC
    $query = "SELECT p.id, p.name, p.product_type, p.unit, p.load_location, p.last_updated_at, p.price, p.min_price, p.max_price, p.image_url, c.name as category_name
              FROM " . CPP_DB_PRODUCTS . " p
              LEFT JOIN " . CPP_DB_CATEGORIES . " c ON p.cat_id = c.id
              {$where_sql}
              ORDER BY p.id ASC";

    if(!empty($query_params)){
        $products = $wpdb->get_results($wpdb->prepare($query, $query_params));
    } else {
        $products = $wpdb->get_results($query);
    }

    if (!$products) { return '<p class="cpp-no-products">' . __('محصولی برای نمایش یافت نشد.', 'cpp-full') . '</p>'; }

    ob_start();
    echo '<div class="cpp-table-responsive-wrapper cpp-products-list-wrapper">';
    include CPP_TEMPLATES_DIR . 'shortcode-list.php'; 
    echo '</div>';
    return ob_get_clean();
}

// شورت‌کد گرید با تاریخ (اصلاح شده: مرتب‌سازی صعودی ID)
add_shortcode('cpp_products_grid_view', 'cpp_products_grid_view_shortcode');
function cpp_products_grid_view_shortcode($atts) {
    global $wpdb;
    $categories = CPP_Core::get_all_categories();
    $products_per_page = max(1, (int) get_option('cpp_products_per_page', 5)); 
    
    // تغییر ORDER BY به ASC
    $products = $wpdb->get_results($wpdb->prepare(
        "SELECT id, cat_id, name, product_type, unit, load_location, last_updated_at, price, min_price, max_price, image_url
         FROM " . CPP_DB_PRODUCTS . "
         WHERE is_active = 1
         ORDER BY id ASC LIMIT %d",
        $products_per_page
    ));
    $total_products = $wpdb->get_var("SELECT COUNT(id) FROM " . CPP_DB_PRODUCTS . " WHERE is_active = 1");

    if (!$products) { return '<p class="cpp-no-products">' . __('محصولی برای نمایش یافت نشد.', 'cpp-full') . '</p>'; }

    ob_start();
    echo '<div class="cpp-table-responsive-wrapper cpp-grid-view-date-wrapper">';
    include CPP_TEMPLATES_DIR . 'shortcode-grid-view.php';
    echo '</div>';
    return ob_get_clean();
}

// شورت‌کد گرید بدون تاریخ (اصلاح شده: مرتب‌سازی صعودی ID)
add_shortcode('cpp_products_grid_view_no_date', 'cpp_products_grid_view_no_date_shortcode');
function cpp_products_grid_view_no_date_shortcode($atts) {
    global $wpdb;
    $categories = CPP_Core::get_all_categories();
    $products_per_page = max(1, (int) get_option('cpp_products_per_page', 5)); 
    
    // تغییر ORDER BY به ASC
    $products = $wpdb->get_results($wpdb->prepare(
         "SELECT id, cat_id, name, product_type, unit, load_location, last_updated_at, price, min_price, max_price, image_url
         FROM " . CPP_DB_PRODUCTS . "
         WHERE is_active = 1
         ORDER BY id ASC LIMIT %d",
        $products_per_page
    ));
    $total_products = $wpdb->get_var("SELECT COUNT(id) FROM " . CPP_DB_PRODUCTS . " WHERE is_active = 1");
    $last_updated_time = $wpdb->get_var("SELECT MAX(last_updated_at) FROM " . CPP_DB_PRODUCTS . " WHERE is_active = 1");

    if (!$products) { return '<p class="cpp-no-products">' . __('محصولی برای نمایش یافت نشد.', 'cpp-full') . '</p>'; }

    ob_start();
    echo '<div class="cpp-table-responsive-wrapper cpp-grid-view-nodate-wrapper">';
    include CPP_TEMPLATES_DIR . 'shortcode-grid-view-no-date.php';
    echo '</div>';
    return ob_get_clean();
}

add_action('wp_enqueue_scripts', 'cpp_front_assets');
function cpp_front_assets() {
    global $post;
    $load = false;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'cpp_products_list')) $load = true;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'cpp_products_grid_view')) $load = true;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'cpp_products_grid_view_no_date')) $load = true;
    if (isset($_GET['elementor-preview'])) $load = true;

    if ($load) {
        wp_enqueue_style('cpp-front-css', CPP_ASSETS_URL . 'css/front.css', [], CPP_VERSION);
        wp_enqueue_style('cpp-grid-view-css', CPP_ASSETS_URL . 'css/grid-view.css', [], CPP_VERSION);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], null, true);
        wp_enqueue_script('cpp-front-js', CPP_ASSETS_URL . 'js/front.js', ['jquery', 'chart-js'], CPP_VERSION, true);

        $logo_url = get_option('cpp_default_product_image');

        wp_localize_script('cpp-front-js', 'cpp_front_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cpp_front_nonce'),
            'logo_url' => $logo_url ? esc_url($logo_url) : '',
            'i18n' => [
                'sending' => 'در حال ارسال...',
                'server_error' => 'خطای سرور.',
                'view_more' => 'مشاهده بیشتر',
                'loading' => 'بارگذاری...',
                'no_more_products' => 'محصول دیگری نیست.'
            ]
        ));
    }
}

add_action('wp_footer', 'cpp_add_modals_to_footer');
function cpp_add_modals_to_footer() {
    if (wp_script_is('cpp-front-js', 'enqueued')) {
        include CPP_TEMPLATES_DIR . 'modals-frontend.php';
        
        $c1 = get_option('cpp_grid_with_date_button_color', '#ffc107');
        $c2 = get_option('cpp_grid_no_date_button_color', '#0073aa');
        echo "<style>
            .cpp-grid-view-wrapper.with-date-shortcode .filter-btn.active { background-color: $c1 !important; border-color: $c1 !important; color: #fff !important; }
            .cpp-grid-view-wrapper.no-date-shortcode .filter-btn.active { background-color: $c2 !important; border-color: $c2 !important; color: #fff !important; }
        </style>";
    }
}

// لود بیشتر (AJAX) - (اصلاح شده: مرتب‌سازی صعودی ID)
add_action('wp_ajax_cpp_load_more_products', 'cpp_load_more_products');
add_action('wp_ajax_nopriv_cpp_load_more_products', 'cpp_load_more_products');
function cpp_load_more_products() {
    check_ajax_referer('cpp_front_nonce', 'nonce');
    global $wpdb;
    $page = max(1, intval($_POST['page']));
    $per_page = max(1, (int) get_option('cpp_products_per_page', 5));
    $offset = ($page - 1) * $per_page;
    
    // تغییر ORDER BY به ASC
    $products = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . CPP_DB_PRODUCTS . " WHERE is_active=1 ORDER BY id ASC LIMIT %d OFFSET %d", $per_page, $offset));
    
    if ($products) {
        ob_start();
        $disable_base_price = get_option('cpp_disable_base_price', 0);
        $show_image = ($_POST['shortcode_type'] === 'with_date') ? get_option('cpp_grid_with_date_show_image', 1) : get_option('cpp_grid_no_date_show_image', 1);
        $show_date = ($_POST['shortcode_type'] === 'with_date');
        $cart_icon = CPP_ASSETS_URL . 'images/cart-icon.png';
        $chart_icon = CPP_ASSETS_URL . 'images/chart-icon.png';
        $def_img = get_option('cpp_default_product_image', CPP_ASSETS_URL . 'images/default-product.png');

        foreach ($products as $p) {
            $img = $p->image_url ?: $def_img;
            $min_clean = str_replace(',', '', $p->min_price);
            $max_clean = str_replace(',', '', $p->max_price);
            $show_single = ($min_clean == $max_clean && is_numeric($min_clean));
            ?>
            <tr class="product-row" data-cat-id="<?php echo $p->cat_id; ?>">
                <td class="col-product-name" data-colname="محصول">
                    <?php if($show_image): ?><img src="<?php echo esc_url($img); ?>"><?php endif; ?>
                    <span><?php echo esc_html($p->name); ?></span>
                </td>
                <td data-colname="نوع"><?php echo esc_html($p->product_type); ?></td>
                <td data-colname="واحد"><?php echo esc_html($p->unit); ?></td>
                <td data-colname="محل"><?php echo esc_html($p->load_location); ?></td>
                <?php if($show_date): ?><td data-colname="تاریخ"><?php echo date_i18n('Y/m/d H:i', strtotime($p->last_updated_at)); ?></td><?php endif; ?>
                <?php if(!$disable_base_price): ?><td class="col-price" data-colname="قیمت"><?php echo number_format((float)$p->price); ?></td><?php endif; ?>
                <td class="col-price-range" data-colname="بازه">
                    <?php if ($show_single): ?>
                        <?php echo number_format((float)$min_clean); ?>
                    <?php else: ?>
                        <?php echo ($p->min_price && $p->max_price) ? number_format((float)$p->min_price) . ' - ' . number_format((float)$p->max_price) : 'تماس بگیرید'; ?>
                    <?php endif; ?>
                </td>
                <td class="col-actions">
                    <button class="cpp-icon-btn cpp-order-btn" data-product-id="<?php echo $p->id; ?>" data-product-name="<?php echo $p->name; ?>" data-product-unit="<?php echo $p->unit; ?>" data-product-location="<?php echo $p->load_location; ?>"><img src="<?php echo $cart_icon; ?>"></button>
                    <button class="cpp-icon-btn cpp-chart-btn" data-product-id="<?php echo $p->id; ?>"><img src="<?php echo $chart_icon; ?>"></button>
                </td>
            </tr>
            <?php
        }
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html, 'has_more' => true]);
    } else {
        wp_send_json_success(['html' => '', 'has_more' => false]);
    }
    wp_die();
}
