<?php
/**
 * Plugin Name: Custom Prices & Orders
 * Description: افزونه مستقل برای مدیریت بازه قیمت‌ها، دسته‌بندی محصولات و ثبت سفارش‌های درخواست شده (بدون ووکامرس). شامل شورت‌کدهای نمایش، پاپ‌آپ سفارش با کپچا، اعلان پیامکی (مدیر و مشتری) با الگوی IPPanel، صفحه تنظیمات و بهبودهای ریسپانسیو.
 * Version: 3.3.0
 * Author: Mr.NT
 */

if (!defined('ABSPATH')) exit;

global $wpdb;
// --- شروع تغییر: افزایش شماره نسخه ---
define('CPP_VERSION', '3.3.0');
// --- پایان تغییر ---
define('CPP_PATH', plugin_dir_path(__FILE__));
define('CPP_URL', plugin_dir_url(__FILE__));
define('CPP_TEMPLATES_DIR', CPP_PATH . 'templates/');
define('CPP_ASSETS_URL', CPP_URL . 'assets/');
define('CPP_DB_PRODUCTS', $wpdb->prefix . 'cpp_products');
define('CPP_DB_ORDERS', $wpdb->prefix . 'cpp_orders');
define('CPP_DB_CATEGORIES', $wpdb->prefix . 'cpp_categories');
define('CPP_DB_PRICE_HISTORY', $wpdb->prefix . 'cpp_price_history');
define('CPP_PLUGIN_SLUG','custom-prices');

// بارگذاری فایل‌های ضروری افزونه
require_once(CPP_PATH . 'includes/cpp-core.php');
require_once(CPP_PATH . 'includes/cpp-admin.php');
require_once(CPP_PATH . 'includes/cpp-settings.php');
if (file_exists(CPP_PATH . 'includes/cpp-email.php')) require_once(CPP_PATH . 'includes/cpp-email.php');
if (file_exists(CPP_PATH . 'includes/cpp-sms.php')) require_once(CPP_PATH . 'includes/cpp-sms.php');

// تابع فعال‌سازی افزونه: ایجاد جداول و ثبت مقادیر پیش‌فرض برای تنظیمات
register_activation_hook(__FILE__, 'cpp_activate');
function cpp_activate() {
    CPP_Core::create_db_tables();
    // تنظیمات پیش‌فرض برای ایمیل (با متغیرهای جدید)
    if (get_option('cpp_email_subject_template') === false) {
        update_option('cpp_email_subject_template', 'سفارش جدید: {product_name}');
        update_option('cpp_email_body_template', '<p style="direction:rtl; text-align:right;">سفارش جدیدی از طریق وب‌سایت ثبت شده است:<br><br><strong>محصول:</strong> {product_name} - {load_location}<br><strong>نام مشتری:</strong> {customer_name}<br><strong>شماره تماس:</strong> {phone}<br><strong>تعداد/مقدار:</strong> {qty} ({unit})<br><strong>توضیحات مشتری:</strong> {note}<br></p>');
    }
    // ... (سایر تنظیمات پیش‌فرض بدون تغییر) ...
    if (get_option('cpp_products_per_page') === false) update_option('cpp_products_per_page', 5);
    if (get_option('cpp_grid_with_date_button_color') === false) update_option('cpp_grid_with_date_button_color', '#ffc107');
    if (get_option('cpp_grid_no_date_button_color') === false) update_option('cpp_grid_no_date_button_color', '#0073aa');
    if (get_option('cpp_grid_with_date_show_image') === false) update_option('cpp_grid_with_date_show_image', 1);
    if (get_option('cpp_grid_no_date_show_image') === false) update_option('cpp_grid_no_date_show_image', 1);
    if (get_option('cpp_admin_capability') === false) update_option('cpp_admin_capability', 'manage_options');
    if (get_option('cpp_sms_service') === false) update_option('cpp_sms_service', '');
    if (get_option('cpp_sms_customer_enable') === false) update_option('cpp_sms_customer_enable', 0);
}

// شورت‌کد [cpp_products_list] برای نمایش جدولی ساده
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
    $query = "SELECT p.id, p.name, p.product_type, p.unit, p.load_location, p.last_updated_at, p.price, p.min_price, p.max_price, p.image_url, c.name as category_name
              FROM " . CPP_DB_PRODUCTS . " p
              LEFT JOIN " . CPP_DB_CATEGORIES . " c ON p.cat_id = c.id
              {$where_sql}
              ORDER BY p.id DESC";

    if(!empty($query_params)){
        $products = $wpdb->get_results($wpdb->prepare($query, $query_params));
    } else {
        $products = $wpdb->get_results($query);
    }

    if (!$products) { return '<p class="cpp-no-products">' . __('محصولی برای نمایش یافت نشد.', 'cpp-full') . '</p>'; }

    ob_start();
    // --- افزودن کلاس برای هدف‌گیری CSS ریسپانسیو ---
    echo '<div class="cpp-table-responsive-wrapper cpp-products-list-wrapper">';
    include CPP_TEMPLATES_DIR . 'shortcode-list.php';
    echo '</div>';
    return ob_get_clean();
}

// شورت‌کد [cpp_products_grid_view] با ستون تاریخ
add_shortcode('cpp_products_grid_view', 'cpp_products_grid_view_shortcode');
function cpp_products_grid_view_shortcode($atts) {
    global $wpdb;
    $categories = CPP_Core::get_all_categories();
    $products_per_page = max(1, (int) get_option('cpp_products_per_page', 5)); // Ensure >= 1
    $products = $wpdb->get_results($wpdb->prepare(
        "SELECT id, cat_id, name, product_type, unit, load_location, last_updated_at, price, min_price, max_price, image_url
         FROM " . CPP_DB_PRODUCTS . "
         WHERE is_active = 1
         ORDER BY id DESC LIMIT %d",
        $products_per_page
    ));
    $total_products = $wpdb->get_var("SELECT COUNT(id) FROM " . CPP_DB_PRODUCTS . " WHERE is_active = 1");

    if (!$products) { return '<p class="cpp-no-products">' . __('محصولی برای نمایش یافت نشد.', 'cpp-full') . '</p>'; }

    ob_start();
     // --- افزودن کلاس برای هدف‌گیری CSS ریسپانسیو ---
    echo '<div class="cpp-table-responsive-wrapper cpp-grid-view-date-wrapper">';
    include CPP_TEMPLATES_DIR . 'shortcode-grid-view.php';
    echo '</div>';
    return ob_get_clean();
}

// شورت‌کد [cpp_products_grid_view_no_date] بدون ستون تاریخ
add_shortcode('cpp_products_grid_view_no_date', 'cpp_products_grid_view_no_date_shortcode');
function cpp_products_grid_view_no_date_shortcode($atts) {
    global $wpdb;
    $categories = CPP_Core::get_all_categories();
    $products_per_page = max(1, (int) get_option('cpp_products_per_page', 5)); // Ensure >= 1
    $products = $wpdb->get_results($wpdb->prepare(
         "SELECT id, cat_id, name, product_type, unit, load_location, last_updated_at, price, min_price, max_price, image_url
         FROM " . CPP_DB_PRODUCTS . "
         WHERE is_active = 1
         ORDER BY id DESC LIMIT %d",
        $products_per_page
    ));
    $total_products = $wpdb->get_var("SELECT COUNT(id) FROM " . CPP_DB_PRODUCTS . " WHERE is_active = 1");
    $last_updated_time = $wpdb->get_var("SELECT MAX(last_updated_at) FROM " . CPP_DB_PRODUCTS . " WHERE is_active = 1");

    if (!$products) { return '<p class="cpp-no-products">' . __('محصولی برای نمایش یافت نشد.', 'cpp-full') . '</p>'; }

    ob_start();
     // --- افزودن کلاس برای هدف‌گیری CSS ریسپانسیو ---
    echo '<div class="cpp-table-responsive-wrapper cpp-grid-view-nodate-wrapper">';
    include CPP_TEMPLATES_DIR . 'shortcode-grid-view-no-date.php';
    echo '</div>';
    return ob_get_clean();
}


// بارگذاری اسکریپت‌ها و استایل‌های بخش کاربری
add_action('wp_enqueue_scripts', 'cpp_front_assets');
function cpp_front_assets() {
    global $post;
    $load_assets = false;
    // Check if the post object exists and has content
    if (is_a($post, 'WP_Post') && !empty($post->post_content)) {
         // Check for shortcodes in the content
         if (has_shortcode($post->post_content, 'cpp_products_list') ||
             has_shortcode($post->post_content, 'cpp_products_grid_view') ||
             has_shortcode($post->post_content, 'cpp_products_grid_view_no_date')) {
             $load_assets = true;
         }
    }
    // Add check for Elementor preview
    if ( isset($_GET['elementor-preview']) && $_GET['elementor-preview'] ) {
       $load_assets = true;
    }
     // Force load if needed for theme builders etc. (uncomment if necessary)
     // $load_assets = apply_filters('cpp_force_load_front_assets', $load_assets);


    if ($load_assets) {
        wp_enqueue_style('cpp-front-css', CPP_ASSETS_URL . 'css/front.css', [], CPP_VERSION);
        wp_enqueue_style('cpp-grid-view-css', CPP_ASSETS_URL . 'css/grid-view.css', [], CPP_VERSION);

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], null, true);
        wp_enqueue_script('cpp-front-js', CPP_ASSETS_URL . 'js/front.js', ['jquery', 'chart-js'], CPP_VERSION, true);

        wp_localize_script('cpp-front-js', 'cpp_front_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cpp_front_nonce'),
            'i18n' => [
                'sending' => __('در حال ارسال...', 'cpp-full'),
                'server_error' => __('خطای سرور، لطفا دوباره تلاش کنید.', 'cpp-full'),
                'view_more' => __('مشاهده بیشتر', 'cpp-full'),
                'loading' => __('در حال بارگذاری...', 'cpp-full'),
                'no_more_products' => __('محصول دیگری برای نمایش وجود ندارد.', 'cpp-full'),
            ]
        ));
    }
}


// افزودن مودال‌ها و استایل‌های داینامیک به فوتر سایت
add_action('wp_footer', 'cpp_add_modals_to_footer');
function cpp_add_modals_to_footer() {
    // Only add if assets were loaded
    if (wp_script_is('cpp-front-js', 'enqueued')) {
        $modals_template = CPP_TEMPLATES_DIR . 'modals-frontend.php';
        if (file_exists($modals_template)) { include $modals_template; }

        $color_with_date = get_option('cpp_grid_with_date_button_color', '#ffc107');
        $color_no_date = get_option('cpp_grid_no_date_button_color', '#0073aa');

        $custom_css = "
            /* Active filter button colors */
            .cpp-grid-view-wrapper.with-date-shortcode .cpp-grid-view-filters .filter-btn.active {
                background-color: " . esc_attr($color_with_date) . " !important;
                border-color: " . esc_attr($color_with_date) . " !important;
                color: #fff !important; /* Ensure text is visible */
            }
            .cpp-grid-view-wrapper.no-date-shortcode .cpp-grid-view-filters .filter-btn.active {
                background-color: " . esc_attr($color_no_date) . " !important;
                border-color: " . esc_attr($color_no_date) . " !important;
                color: #fff !important; /* Ensure text is visible */
            }
        ";
        // Use wp_add_inline_style for better practice if cpp-front-css is always enqueued when needed
        // wp_add_inline_style('cpp-front-css', $custom_css);
        // Or echo if cpp-front-css might not be enqueued but modals are
         echo '<style type="text/css" id="cpp-dynamic-styles">' . wp_strip_all_tags($custom_css) . '</style>';
    }
}


// تابع ایجکس برای بارگذاری محصولات بیشتر ("مشاهده بیشتر")
add_action('wp_ajax_cpp_load_more_products', 'cpp_load_more_products');
add_action('wp_ajax_nopriv_cpp_load_more_products', 'cpp_load_more_products');
function cpp_load_more_products() {
    check_ajax_referer('cpp_front_nonce', 'nonce');
    global $wpdb;

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1; // Page number starts from 1 sent by JS
    if ($page <= 1) $page = 1;

    $products_per_page = max(1, (int) get_option('cpp_products_per_page', 5));
    // Offset is calculated based on previous pages (0-indexed)
    $offset = ($page - 1) * $products_per_page;
    $shortcode_type = isset($_POST['shortcode_type']) ? sanitize_key($_POST['shortcode_type']) : 'with_date';

    if ($shortcode_type === 'with_date') {
        $show_image = get_option('cpp_grid_with_date_show_image', 1);
        $show_date_column = true;
    } else { // 'no_date' or default
        $show_image = get_option('cpp_grid_no_date_show_image', 1);
        $show_date_column = false;
    }
     $disable_base_price = get_option('cpp_disable_base_price', 0);

    $products = $wpdb->get_results($wpdb->prepare(
        "SELECT id, cat_id, name, product_type, unit, load_location, last_updated_at, price, min_price, max_price, image_url
         FROM " . CPP_DB_PRODUCTS . "
         WHERE is_active = 1
         ORDER BY id DESC LIMIT %d OFFSET %d",
        $products_per_page,
        $offset
    ));

    $html = '';
    $has_more = false;

    if ($products) {
        ob_start();
        $default_image = get_option('cpp_default_product_image', CPP_ASSETS_URL . 'images/default-product.png');
        $cart_icon_url = CPP_ASSETS_URL . 'images/cart-icon.png';
        $chart_icon_url = CPP_ASSETS_URL . 'images/chart-icon.png';

        foreach ($products as $product) {
            $product_image_url = !empty($product->image_url) ? esc_url($product->image_url) : esc_url($default_image);
            ?>
            <tr class="product-row" data-cat-id="<?php echo esc_attr($product->cat_id); ?>">
                <td class="col-product-name" data-colname="<?php esc_attr_e('محصول', 'cpp-full'); ?>">
                    <?php if ($show_image) : ?>
                        <img src="<?php echo $product_image_url; ?>" alt="<?php echo esc_attr($product->name); ?>">
                    <?php endif; ?>
                    <span><?php echo esc_html($product->name); ?></span>
                </td>
                <td data-colname="<?php esc_attr_e('نوع', 'cpp-full'); ?>"><?php echo esc_html($product->product_type); ?></td>
                <td data-colname="<?php esc_attr_e('واحد', 'cpp-full'); ?>"><?php echo esc_html($product->unit); ?></td>
                <td data-colname="<?php esc_attr_e('محل بارگیری', 'cpp-full'); ?>"><?php echo esc_html($product->load_location); ?></td>

                <?php if ($show_date_column): ?>
                <td data-colname="<?php esc_attr_e('آخرین بروزرسانی', 'cpp-full'); ?>"><?php echo esc_html(date_i18n('Y/m/d H:i', strtotime(get_date_from_gmt($product->last_updated_at)))); ?></td>
                <?php endif; ?>

                <?php if (!$disable_base_price) : ?>
                <td class="col-price" data-colname="<?php esc_attr_e('قیمت پایه', 'cpp-full'); ?>">
                    <?php
                        $price_cleaned = str_replace(',', '', $product->price);
                        echo is_numeric($price_cleaned) ? esc_html(number_format_i18n((float)$price_cleaned)) : esc_html($product->price);
                    ?>
                </td>
                <?php endif; ?>

                 <td class="col-price-range" data-colname="<?php esc_attr_e('بازه قیمت', 'cpp-full'); ?>">
                    <?php if (!empty($product->min_price) && !empty($product->max_price)) :
                         $min_cleaned = str_replace(',', '', $product->min_price);
                         $max_cleaned = str_replace(',', '', $product->max_price);
                    ?>
                         <?php echo is_numeric($min_cleaned) ? esc_html(number_format_i18n((float)$min_cleaned)) : esc_html($product->min_price); ?> - <?php echo is_numeric($max_cleaned) ? esc_html(number_format_i18n((float)$max_cleaned)) : esc_html($product->max_price); ?>
                    <?php else: ?>
                        <span class="cpp-price-not-set"><?php _e('تماس بگیرید', 'cpp-full'); ?></span>
                    <?php endif; ?>
                </td>

                <td class="col-actions" data-colname="<?php esc_attr_e('عملیات', 'cpp-full'); ?>">
                    <button class="cpp-icon-btn cpp-order-btn"
                            data-product-id="<?php echo esc_attr($product->id); ?>"
                            data-product-name="<?php echo esc_attr($product->name); ?>"
                            data-product-unit="<?php echo esc_attr($product->unit); ?>"
                            data-product-location="<?php echo esc_attr($product->load_location); ?>"
                            title="<?php esc_attr_e('خرید', 'cpp-full'); ?>">
                        <img src="<?php echo esc_url($cart_icon_url); ?>" alt="<?php esc_attr_e('خرید', 'cpp-full'); ?>">
                    </button>
                    <button class="cpp-icon-btn cpp-chart-btn" data-product-id="<?php echo esc_attr($product->id); ?>" title="<?php esc_attr_e('نمودار', 'cpp-full'); ?>">
                        <img src="<?php echo esc_url($chart_icon_url); ?>" alt="<?php esc_attr_e('نمودار', 'cpp-full'); ?>">
                    </button>
                </td>
            </tr>
            <?php
        }
        $html = ob_get_clean();

        // Check if there might be more products on the next page more accurately
        $total_products = $wpdb->get_var("SELECT COUNT(id) FROM " . CPP_DB_PRODUCTS . " WHERE is_active = 1");
        $current_total_shown = $page * $products_per_page; // Total potentially shown up to this page
        $has_more = $current_total_shown < $total_products;


        wp_send_json_success(['html' => $html, 'has_more' => $has_more]);

    } else {
        // No products found for this page means no more products
        wp_send_json_success(['html' => '', 'has_more' => false]);
    }
     wp_die();
}

?>
