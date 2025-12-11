<?php
if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', 'cpp_admin_assets');
function cpp_admin_assets($hook) {
    if (!CPP_Core::has_access()) return;

    $allowed = ['custom-prices-products', 'custom-prices-categories', 'custom-prices-orders', 'custom-prices-shortcodes', 'custom-prices-settings', 'custom-prices-product-edit'];
    $is_cpp = false; foreach($allowed as $a) if(strpos($hook, $a)!==false) $is_cpp=true;
    if(!$is_cpp && strpos($hook,'custom-prices')===false) return;

    wp_enqueue_media();
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], null, true);
    wp_enqueue_script('cpp-admin-js', CPP_ASSETS_URL . 'js/admin.js', ['jquery', 'wp-i18n', 'chart-js', 'wp-util'], CPP_VERSION, true);

    if (strpos($hook, 'settings') !== false) {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('cpp-color-picker-init', CPP_ASSETS_URL . 'js/admin-color-picker.js', ['wp-color-picker', 'jquery'], CPP_VERSION, true);
    }

    $logo = get_option('cpp_default_product_image');

    wp_localize_script('cpp-admin-js', 'cpp_admin_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cpp_admin_nonce'),
        'logo_url' => $logo ? esc_url($logo) : '',
        'order_statuses' => ['new_order'=>__('سفارش جدید','cpp-full'), 'negotiating'=>__('در حال مذاکره','cpp-full'), 'cancelled'=>__('کنسل شد','cpp-full'), 'completed'=>__('خرید انجام شد','cpp-full')],
        'product_statuses' => ['1'=>__('فعال','cpp-full'), '0'=>__('غیرفعال','cpp-full')],
        'i18n' => [ 'saving'=>__('ذخیره...','cpp-full'), 'save'=>__('ذخیره','cpp-full'), 'cancel'=>__('لغو','cpp-full'), 'error'=>__('خطا','cpp-full'), 'serverError'=>__('خطای سرور','cpp-full'), 'loadingForm'=>__('بارگذاری...','cpp-full') ]
    ]);

    wp_enqueue_style('cpp-admin-css', CPP_ASSETS_URL . 'css/admin.css', [], CPP_VERSION);
}

add_action('admin_menu', 'cpp_admin_menu');
function cpp_admin_menu() {
    if (!CPP_Core::has_access()) return;
    $cap = 'read'; $slug = 'custom-prices-products';
    add_menu_page( __('مدیریت قیمت', 'cpp-full'), __('مدیریت قیمت', 'cpp-full'), $cap, $slug, 'cpp_products_page', 'dashicons-tag', 30 );
    add_submenu_page($slug, __('محصولات', 'cpp-full'), __('محصولات', 'cpp-full'), $cap, $slug, 'cpp_products_page'); 
    add_submenu_page($slug, __('دسته‌بندی‌ها', 'cpp-full'), __('دسته‌بندی‌ها', 'cpp-full'), $cap, 'custom-prices-categories', 'cpp_categories_page');
    add_submenu_page($slug, __('سفارشات', 'cpp-full'), __('سفارشات', 'cpp-full'), $cap, 'custom-prices-orders', 'cpp_orders_page');
    add_submenu_page($slug, __('شورت‌کدها', 'cpp-full'), __('شورت‌کدها', 'cpp-full'), $cap, 'custom-prices-shortcodes', 'cpp_shortcodes_page');
    add_submenu_page($slug, __('تنظیمات', 'cpp-full'), __('تنظیمات', 'cpp-full'), $cap, 'custom-prices-settings', 'cpp_settings_page');
    add_submenu_page( null, __('ویرایش', 'cpp-full'), __('ویرایش', 'cpp-full'), $cap, 'custom-prices-product-edit', 'cpp_product_edit_page' );
}

add_action('admin_menu', 'cpp_add_order_count_bubble', 99);
function cpp_add_order_count_bubble() {
    global $wpdb, $menu; if (!CPP_Core::has_access()) return;
    $count = $wpdb->get_var("SELECT COUNT(id) FROM " . CPP_DB_ORDERS . " WHERE status = 'new_order'");
    if ($count > 0) {
        foreach ($menu as $key => $value) {
            if ($menu[$key][2] == 'custom-prices-products') {
                $menu[$key][0] .= ' <span class="update-plugins count-' . intval($count) . '"><span class="plugin-count">' . intval($count) . '</span></span>';
                return;
            }
        }
    }
}

function cpp_products_page() { include CPP_TEMPLATES_DIR . 'products.php'; }
function cpp_categories_page() { include CPP_TEMPLATES_DIR . 'categories.php'; }
function cpp_orders_page() { include CPP_TEMPLATES_DIR . 'orders.php'; }
function cpp_settings_page() { include CPP_TEMPLATES_DIR . 'settings.php'; }
function cpp_shortcodes_page() { include CPP_TEMPLATES_DIR . 'shortcodes.php'; }
function cpp_product_edit_page() { include CPP_TEMPLATES_DIR . 'product-edit.php'; }

add_action('admin_init', 'cpp_handle_admin_actions');
function cpp_handle_admin_actions() {
    global $wpdb; $page = isset($_GET['page']) ? $_GET['page'] : '';

    if (isset($_POST['cpp_add_category']) && $page === 'custom-prices-categories') {
        check_admin_referer('cpp_add_cat_action', 'cpp_add_cat_nonce');
        $wpdb->insert(CPP_DB_CATEGORIES, ['name'=>$_POST['name'], 'slug'=>sanitize_title($_POST['slug']?:$_POST['name']), 'image_url'=>$_POST['image_url'], 'created'=>current_time('mysql',1)]);
        wp_redirect(add_query_arg('cpp_message', 'category_added', admin_url('admin.php?page=custom-prices-categories'))); exit;
    }

    if (isset($_POST['cpp_add_product']) && $page === 'custom-prices-products') {
        check_admin_referer('cpp_add_product_action', 'cpp_add_product_nonce');
        $data = ['cat_id'=>$_POST['cat_id'], 'name'=>$_POST['name'], 'price'=>$_POST['price'], 'min_price'=>$_POST['min_price'], 'max_price'=>$_POST['max_price'], 'product_type'=>$_POST['product_type'], 'unit'=>$_POST['unit'], 'load_location'=>$_POST['load_location'], 'is_active'=>$_POST['is_active'], 'description'=>$_POST['description'], 'image_url'=>$_POST['image_url'], 'created'=>current_time('mysql',1), 'last_updated_at'=>current_time('mysql',1)];
        $wpdb->insert(CPP_DB_PRODUCTS, $data);
        $pid = $wpdb->insert_id;
        CPP_Core::save_price_history($pid, $data['price'], 'price');
        wp_redirect(add_query_arg('cpp_message', 'product_added', admin_url('admin.php?page=custom-prices-products'))); exit;
    }

    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $map = ['custom-prices-categories'=>[CPP_DB_CATEGORIES,'cpp_delete_cat_'.$id], 'custom-prices-products'=>[CPP_DB_PRODUCTS,'cpp_delete_product_'.$id], 'custom-prices-orders'=>[CPP_DB_ORDERS,'cpp_delete_order_'.$id]];
        if(isset($map[$page]) && check_admin_referer($map[$page][1])) {
            $wpdb->delete($map[$page][0], ['id'=>$id]);
            wp_redirect(add_query_arg('cpp_message', 'deleted', admin_url('admin.php?page='.$page))); exit;
        }
    }
}

add_action('wp_ajax_cpp_fetch_product_edit_form', 'cpp_fetch_product_edit_form');
function cpp_fetch_product_edit_form() {
    check_ajax_referer('cpp_admin_nonce', 'security');
    if (!CPP_Core::has_access()) wp_send_json_error(['message'=>'عدم دسترسی'], 403);
    $product_id = intval($_GET['id']);
    ob_start(); include CPP_TEMPLATES_DIR . 'product-edit.php'; wp_send_json_success(['html'=>ob_get_clean()]);
}

add_action('wp_ajax_cpp_handle_edit_product_ajax', 'cpp_handle_edit_product_ajax');
function cpp_handle_edit_product_ajax() {
    check_ajax_referer('cpp_edit_product_action', 'cpp_edit_product_nonce');
    if (!CPP_Core::has_access()) wp_send_json_error(['message'=>'عدم دسترسی'], 403);
    global $wpdb; $pid = intval($_POST['product_id']);
    $data = ['cat_id'=>$_POST['cat_id'], 'name'=>$_POST['name'], 'price'=>$_POST['price'], 'min_price'=>$_POST['min_price'], 'max_price'=>$_POST['max_price'], 'product_type'=>$_POST['product_type'], 'unit'=>$_POST['unit'], 'load_location'=>$_POST['load_location'], 'is_active'=>$_POST['is_active'], 'description'=>$_POST['description'], 'image_url'=>$_POST['image_url'], 'last_updated_at'=>current_time('mysql',1)];
    $wpdb->update(CPP_DB_PRODUCTS, $data, ['id'=>$pid]);
    CPP_Core::save_price_history($pid, $data['price'], 'price');
    wp_send_json_success(['message'=>'بروزرسانی شد.']);
}

add_action('wp_ajax_cpp_fetch_category_edit_form', 'cpp_fetch_category_edit_form');
function cpp_fetch_category_edit_form() {
    check_ajax_referer('cpp_admin_nonce', 'security');
    if (!CPP_Core::has_access()) wp_send_json_error(['message'=>'عدم دسترسی'], 403);
    $cat_id = intval($_GET['id']);
    ob_start(); include CPP_TEMPLATES_DIR . 'category-edit.php'; wp_send_json_success(['html'=>ob_get_clean()]);
}

add_action('wp_ajax_cpp_handle_edit_category_ajax', 'cpp_handle_edit_category_ajax');
function cpp_handle_edit_category_ajax() {
    check_ajax_referer('cpp_edit_cat_action', 'cpp_edit_cat_nonce');
    if (!CPP_Core::has_access()) wp_send_json_error(['message'=>'عدم دسترسی'], 403);
    global $wpdb;
    $wpdb->update(CPP_DB_CATEGORIES, ['name'=>$_POST['name'], 'slug'=>sanitize_title($_POST['slug']?:$_POST['name']), 'image_url'=>$_POST['image_url']], ['id'=>intval($_POST['category_id'])]);
    wp_send_json_success(['message'=>'بروزرسانی شد.']);
}

add_action('wp_ajax_cpp_quick_update', 'cpp_quick_update');
function cpp_quick_update() {
    check_ajax_referer('cpp_admin_nonce', 'security');
    if (!CPP_Core::has_access()) wp_send_json_error(['message'=>'عدم دسترسی'], 403);
    global $wpdb; $id = intval($_POST['id']); $field = $_POST['field']; $val = $_POST['value'];
    $table = ($_POST['table_type']=='products') ? CPP_DB_PRODUCTS : (($_POST['table_type']=='orders') ? CPP_DB_ORDERS : CPP_DB_CATEGORIES);
    
    $data = [$field => $val];
    if ($_POST['table_type']=='products') {
        $data['last_updated_at'] = current_time('mysql', 1);
        if(in_array($field, ['price','min_price','max_price'])) CPP_Core::save_price_history($id, $val, $field);
    }
    $wpdb->update($table, $data, ['id'=>$id]);
    wp_send_json_success(['message'=>'بروز شد', 'new_time'=>date_i18n('Y/m/d H:i:s', current_time('timestamp'))]);
}

add_action('wp_ajax_cpp_test_email', 'cpp_ajax_test_email');
function cpp_ajax_test_email() {
    check_ajax_referer('cpp_admin_nonce', 'security');
    if(!CPP_Core::has_access()) wp_send_json_error(['log'=>'عدم دسترسی'], 403);
    $sent = wp_mail(get_option('cpp_admin_email'), 'Test', 'Test Body');
    wp_send_json_success(['log'=>$sent?'Success':'Failed']);
}
?>
