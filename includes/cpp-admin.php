<?php
if (!defined('ABSPATH')) exit;

/**
 * مدیریت بخش پیشخوان وردپرس
 */

// ۱. ثبت و بارگذاری اسکریپت‌ها
add_action('admin_enqueue_scripts', 'cpp_admin_assets');
function cpp_admin_assets($hook) {
    // اصلاح: استفاده از تابع جدید has_access برای بررسی نقش
    if (!CPP_Core::has_access()) {
        return; 
    }

    $allowed_hooks = [
        'toplevel_page_custom-prices-products',
        'custom-prices_page_custom-prices-categories',
        'custom-prices_page_custom-prices-orders',
        'custom-prices_page_custom-prices-shortcodes',
        'custom-prices_page_custom-prices-settings',
        'admin_page_custom-prices-product-edit'
    ];
    
    $is_cpp_page = false;
    foreach ($allowed_hooks as $allowed) {
        if (strpos($hook, 'custom-prices') !== false) {
            $is_cpp_page = true; 
            break;
        }
    }

    if (!$is_cpp_page) return;

    wp_enqueue_media();
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], null, true);
    wp_enqueue_script('cpp-admin-js', CPP_ASSETS_URL . 'js/admin.js', ['jquery', 'wp-i18n', 'chart-js', 'wp-util'], CPP_VERSION, true);

    if (strpos($hook, 'settings') !== false) {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('cpp-color-picker-init', CPP_ASSETS_URL . 'js/admin-color-picker.js', ['wp-color-picker', 'jquery'], CPP_VERSION, true);
    }

    $order_statuses = [
        'new_order'     => __('سفارش جدید', 'cpp-full'),
        'negotiating'   => __('در حال مذاکره', 'cpp-full'),
        'cancelled'     => __('کنسل شد', 'cpp-full'),
        'completed'     => __('خرید انجام شد', 'cpp-full'),
    ];
    $status_options = [ '1' => __('فعال', 'cpp-full'), '0' => __('غیرفعال', 'cpp-full') ];

    wp_localize_script('cpp-admin-js', 'cpp_admin_vars', [
        'ajax_url'      => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('cpp_admin_nonce'),
        'order_statuses' => $order_statuses,
        'product_statuses' => $status_options,
        'i18n' => [ 
            'saving' => __('در حال ذخیره...', 'cpp-full'),
            'save' => __('ذخیره', 'cpp-full'),
            'cancel' => __('لغو', 'cpp-full'),
            'error' => __('خطا', 'cpp-full'),
            'serverError' => __('خطای سرور', 'cpp-full'),
            'loadingForm' => __('در حال بارگذاری فرم...', 'cpp-full'),
             'sendTestEmail' => __('ارسال ایمیل تست', 'cpp-full'),
             'sendTestSms' => __('ارسال پیامک تست', 'cpp-full'),
        ]
    ]);

    wp_enqueue_style('cpp-admin-css', CPP_ASSETS_URL . 'css/admin.css', [], CPP_VERSION);

    wp_add_inline_script('cpp-admin-js', '
        window.cpp_init_media_uploader = function() {
            var mediaUploader;
            jQuery("body").off("click.cppuploader", ".cpp-upload-btn").on("click.cppuploader", ".cpp-upload-btn", function(e) {
                e.preventDefault();
                var button = jQuery(this);
                var inputId = button.data("input-id") || button.siblings("input[type=\"text\"]").attr("id");
                 if (!inputId) {
                     input_field = button.prev("input[type=\'text\']");
                 } else {
                     input_field = jQuery("#" + inputId);
                 }
                var preview_img_container = button.closest("td, .cpp-image-uploader-wrapper, .form-table tr").find(".cpp-image-preview");
                if (!input_field.length) return;
                
                mediaUploader = wp.media({ title: "'.__('انتخاب تصویر', 'cpp-full').'", button: { text: "'.__('استفاده', 'cpp-full').'" }, multiple: false });
                (function(target_input, target_preview) {
                    mediaUploader.off("select"); 
                    mediaUploader.on("select", function() {
                        var attachment = mediaUploader.state().get("selection").first().toJSON();
                        target_input.val(attachment.url).trigger("change");
                         if(target_preview.length) {
                            target_preview.html("<img src=\"" + attachment.url + "\" style=\"max-width: 100px; height: auto; margin-top: 10px; border: 1px solid #ddd; padding: 3px;\">");
                         }
                    });
                     mediaUploader.open();
                })(input_field, preview_img_container);
            });
        };
        jQuery(document).ready(function(){ window.cpp_init_media_uploader(); });
    ', 'after');
}

// ۲. ثبت منوهای افزونه
add_action('admin_menu', 'cpp_admin_menu');
function cpp_admin_menu() {
    // اصلاح: بررسی دسترسی چندگانه
    if (!CPP_Core::has_access()) {
        return; 
    }

    $capability = 'read'; // چون دسترسی قبلاً با has_access چک شده، اینجا read کافیست
    $main_slug = 'custom-prices-products';

    add_menu_page( __('مدیریت قیمت‌ها', 'cpp-full'), __('مدیریت قیمت', 'cpp-full'), $capability, $main_slug, 'cpp_products_page', 'dashicons-tag', 30 );
    add_submenu_page($main_slug, __('محصولات', 'cpp-full'), __('محصولات', 'cpp-full'), $capability, $main_slug, 'cpp_products_page'); 
    add_submenu_page($main_slug, __('دسته‌بندی‌ها', 'cpp-full'), __('دسته‌بندی‌ها', 'cpp-full'), $capability, 'custom-prices-categories', 'cpp_categories_page');
    add_submenu_page($main_slug, __('سفارشات', 'cpp-full'), __('سفارشات مشتری', 'cpp-full'), $capability, 'custom-prices-orders', 'cpp_orders_page');
    add_submenu_page($main_slug, __('شورت‌کدها', 'cpp-full'), __('شورت‌کدها', 'cpp-full'), $capability, 'custom-prices-shortcodes', 'cpp_shortcodes_page');
    add_submenu_page($main_slug, __('تنظیمات', 'cpp-full'), __('تنظیمات', 'cpp-full'), $capability, 'custom-prices-settings', 'cpp_settings_page');
    add_submenu_page( null, __('ویرایش محصول', 'cpp-full'), __('ویرایش محصول', 'cpp-full'), $capability, 'custom-prices-product-edit', 'cpp_product_edit_page' );
}

// ۳. افزودن حباب اعلان سفارشات
add_action('admin_menu', 'cpp_add_order_count_bubble', 99);
function cpp_add_order_count_bubble() {
    global $wpdb, $menu;
    if (!CPP_Core::has_access()) return;

    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM " . CPP_DB_ORDERS . " WHERE status = %s", 'new_order'));
    if ($count > 0) {
        $menu_slug = 'custom-prices-products';
        foreach ($menu as $key => $value) {
            if ($menu[$key][2] == $menu_slug) {
                $menu[$key][0] .= ' <span class="update-plugins count-' . intval($count) . '"><span class="plugin-count">' . intval($count) . '</span></span>';
                global $submenu;
                 if (isset($submenu[$menu_slug])) {
                     foreach ($submenu[$menu_slug] as $sub_key => $sub_value) {
                         if ($submenu[$menu_slug][$sub_key][2] == 'custom-prices-orders') {
                             $submenu[$menu_slug][$sub_key][0] .= ' <span class="update-plugins count-' . intval($count) . '"><span class="plugin-count">' . intval($count) . '</span></span>';
                             break; 
                         }
                     }
                 }
                return; 
            }
        }
    }
}

// ۴. توابع نمایش صفحات
function cpp_products_page() { include CPP_TEMPLATES_DIR . 'products.php'; }
function cpp_categories_page() { include CPP_TEMPLATES_DIR . 'categories.php'; }
function cpp_orders_page() { include CPP_TEMPLATES_DIR . 'orders.php'; }
function cpp_settings_page() { include CPP_TEMPLATES_DIR . 'settings.php'; }
function cpp_shortcodes_page() { include CPP_TEMPLATES_DIR . 'shortcodes.php'; }
function cpp_product_edit_page() { include CPP_TEMPLATES_DIR . 'product-edit.php'; }

// ۵. مدیریت فرم‌های POST (افزودن و حذف)
add_action('admin_init', 'cpp_handle_admin_actions');
function cpp_handle_admin_actions() {
    global $wpdb;
    $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

    // افزودن دسته‌بندی
    if (isset($_POST['cpp_add_category']) && $current_page === 'custom-prices-categories') {
        if (!isset($_POST['cpp_add_cat_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['cpp_add_cat_nonce']), 'cpp_add_cat_action')) { wp_die('Security Check Failed'); }
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
        $image_url = isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '';
        if (empty($slug) && !empty($name)) $slug = sanitize_title($name);

        if (!empty($name)) {
             $wpdb->insert(CPP_DB_CATEGORIES, array('name' => $name,'slug' => $slug,'image_url' => $image_url, 'created' => current_time('mysql', 1)), ['%s', '%s', '%s', '%s']);
        }
        wp_redirect(add_query_arg('cpp_message', 'category_added', admin_url('admin.php?page=custom-prices-categories'))); exit;
    }

    // افزودن محصول
    if (isset($_POST['cpp_add_product']) && $current_page === 'custom-prices-products') {
        if (!isset($_POST['cpp_add_product_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['cpp_add_product_nonce']), 'cpp_add_product_action')) { wp_die('Security Check Failed'); }

        $data = [
            'cat_id'       => isset($_POST['cat_id']) ? intval($_POST['cat_id']) : 0,
            'name'         => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'price'        => isset($_POST['price']) ? sanitize_text_field(wp_unslash($_POST['price'])) : '',
            'min_price'    => isset($_POST['min_price']) ? sanitize_text_field(wp_unslash($_POST['min_price'])) : '',
            'max_price'    => isset($_POST['max_price']) ? sanitize_text_field(wp_unslash($_POST['max_price'])) : '',
            'product_type' => isset($_POST['product_type']) ? sanitize_text_field(wp_unslash($_POST['product_type'])) : '',
            'unit'         => isset($_POST['unit']) ? sanitize_text_field(wp_unslash($_POST['unit'])) : '',
            'load_location'=> isset($_POST['load_location']) ? sanitize_text_field(wp_unslash($_POST['load_location'])) : '',
            'is_active'    => isset($_POST['is_active']) ? intval($_POST['is_active']) : 0,
            'description'  => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '', 
            'image_url'    => isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '',
            'created'      => current_time('mysql', 1), 
            'last_updated_at' => current_time('mysql', 1) 
        ];

        if (!empty($data['name']) && !empty($data['cat_id'])) {
             $inserted = $wpdb->insert(CPP_DB_PRODUCTS, $data);
             if ($inserted) {
                 $product_id = $wpdb->insert_id;
                 if (!empty($data['price'])) CPP_Core::save_price_history($product_id, $data['price'], 'price');
                 if (!empty($data['min_price'])) CPP_Core::save_price_history($product_id, $data['min_price'], 'min_price');
                 if (!empty($data['max_price'])) CPP_Core::save_price_history($product_id, $data['max_price'], 'max_price');
             }
        }
        wp_redirect(add_query_arg('cpp_message', 'product_added', admin_url('admin.php?page=custom-prices-products'))); exit;
    }

    // حذف آیتم‌ها
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
        $id = intval($_GET['id']);
        $redirect_url = admin_url('admin.php?page=' . $current_page);
        
        $action_map = [
            'custom-prices-categories' => ['table' => CPP_DB_CATEGORIES, 'nonce' => 'cpp_delete_cat_'.$id, 'msg' => 'category_deleted'],
            'custom-prices-products' => ['table' => CPP_DB_PRODUCTS, 'nonce' => 'cpp_delete_product_'.$id, 'msg' => 'product_deleted'],
            'custom-prices-orders' => ['table' => CPP_DB_ORDERS, 'nonce' => 'cpp_delete_order_'.$id, 'msg' => 'order_deleted']
        ];

        if (isset($action_map[$current_page]) && wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), $action_map[$current_page]['nonce'])) {
             $wpdb->delete($action_map[$current_page]['table'], array('id' => $id), array('%d'));
             $redirect_url = add_query_arg('cpp_message', $action_map[$current_page]['msg'], $redirect_url);
        } else {
            wp_die('Invalid request');
        }
        wp_redirect($redirect_url); exit;
    }
}

// ۶. توابع AJAX مدیریت (ویرایش‌ها)

// دریافت فرم ویرایش محصول
add_action('wp_ajax_cpp_fetch_product_edit_form', 'cpp_fetch_product_edit_form');
function cpp_fetch_product_edit_form() {
    if (!isset($_GET['security']) || !wp_verify_nonce(sanitize_text_field($_GET['security']), 'cpp_admin_nonce')) {
        wp_send_json_error(['message' => __('بررسی امنیتی ناموفق بود.', 'cpp-full')], 403);
    }

    $product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$product_id) { wp_send_json_error(['message' => __('شناسه محصول نامعتبر است.', 'cpp-full')], 400); }

     global $wpdb;
     $product_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . CPP_DB_PRODUCTS . " WHERE id = %d", $product_id));
     if(!$product_exists){
         wp_send_json_error(['message' => __('محصول یافت نشد.', 'cpp-full')], 404);
     }

    ob_start();
    include CPP_TEMPLATES_DIR . 'product-edit.php'; 
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
     wp_die();
}

// ذخیره فرم ویرایش محصول (AJAX)
add_action('wp_ajax_cpp_handle_edit_product_ajax', 'cpp_handle_edit_product_ajax');
function cpp_handle_edit_product_ajax() {
    global $wpdb;
    if (!isset($_POST['cpp_edit_product_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['cpp_edit_product_nonce']), 'cpp_edit_product_action')) {
         wp_send_json_error(['message' => __('بررسی امنیتی ناموفق بود.', 'cpp-full')], 403);
    }

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) { wp_send_json_error(['message' => __('شناسه محصول نامعتبر است.', 'cpp-full')], 400); }

    $data = [
        'cat_id'       => isset($_POST['cat_id']) ? intval($_POST['cat_id']) : 0,
        'name'         => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
        'price'        => isset($_POST['price']) ? sanitize_text_field(wp_unslash($_POST['price'])) : '',
        'min_price'    => isset($_POST['min_price']) ? sanitize_text_field(wp_unslash($_POST['min_price'])) : '',
        'max_price'    => isset($_POST['max_price']) ? sanitize_text_field(wp_unslash($_POST['max_price'])) : '',
        'product_type' => isset($_POST['product_type']) ? sanitize_text_field(wp_unslash($_POST['product_type'])) : '',
        'unit'         => isset($_POST['unit']) ? sanitize_text_field(wp_unslash($_POST['unit'])) : '',
        'load_location'=> isset($_POST['load_location']) ? sanitize_text_field(wp_unslash($_POST['load_location'])) : '',
        'is_active'    => isset($_POST['is_active']) ? intval($_POST['is_active']) : 0,
        'description'  => isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '',
        'image_url'    => isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '',
        'last_updated_at' => current_time('mysql', 1) 
    ];

    if (empty($data['name']) || empty($data['cat_id'])) {
         wp_send_json_error(['message' => __('نام محصول و دسته‌بندی الزامی است.', 'cpp-full')], 400);
    }

    $old_data = $wpdb->get_row($wpdb->prepare("SELECT price, min_price, max_price FROM " . CPP_DB_PRODUCTS . " WHERE id = %d", $product_id));

    $updated = $wpdb->update(CPP_DB_PRODUCTS, $data, ['id' => $product_id]);

    if ($updated !== false) {
        if ($old_data) {
             if ($old_data->price != $data['price']) CPP_Core::save_price_history($product_id, $data['price'], 'price');
             if ($old_data->min_price != $data['min_price']) CPP_Core::save_price_history($product_id, $data['min_price'], 'min_price');
             if ($old_data->max_price != $data['max_price']) CPP_Core::save_price_history($product_id, $data['max_price'], 'max_price');
        }
        wp_send_json_success(['message' => __('محصول با موفقیت به‌روزرسانی شد.', 'cpp-full')]);
    } else {
         wp_send_json_error(['message' => __('خطا در به‌روزرسانی محصول در دیتابیس.', 'cpp-full') . ' ' . $wpdb->last_error], 500);
    }
     wp_die();
}

// دریافت فرم ویرایش دسته‌بندی
add_action('wp_ajax_cpp_fetch_category_edit_form', 'cpp_fetch_category_edit_form');
function cpp_fetch_category_edit_form() {
    if (!isset($_GET['security']) || !wp_verify_nonce(sanitize_text_field($_GET['security']), 'cpp_admin_nonce')) {
        wp_send_json_error(['message' => __('بررسی امنیتی ناموفق بود.', 'cpp-full')], 403);
    }

    $cat_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$cat_id) { wp_send_json_error(['message' => __('شناسه دسته‌بندی نامعتبر است.', 'cpp-full')], 400); }

     global $wpdb;
     $cat_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . CPP_DB_CATEGORIES . " WHERE id = %d", $cat_id));
     if(!$cat_exists){
         wp_send_json_error(['message' => __('دسته‌بندی یافت نشد.', 'cpp-full')], 404);
     }

    ob_start();
    include CPP_TEMPLATES_DIR . 'category-edit.php'; 
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
    wp_die();
}

// ذخیره ویرایش دسته‌بندی (AJAX)
add_action('wp_ajax_cpp_handle_edit_category_ajax', 'cpp_handle_edit_category_ajax');
function cpp_handle_edit_category_ajax() {
    global $wpdb;
    if (!isset($_POST['cpp_edit_cat_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['cpp_edit_cat_nonce']), 'cpp_edit_cat_action')) {
        wp_send_json_error(['message' => __('بررسی امنیتی ناموفق بود.', 'cpp-full')], 403);
    }

    $cat_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    if (!$cat_id) { wp_send_json_error(['message' => __('شناسه دسته‌بندی نامعتبر است.', 'cpp-full')], 400); }

    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
    $image_url = isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '';

    if (empty($name)) { wp_send_json_error(['message' => __('نام دسته‌بندی الزامی است.', 'cpp-full')], 400); }
    if (empty($slug)) $slug = sanitize_title($name);

    $data = [ 'name' => $name, 'slug' => $slug, 'image_url' => $image_url ];

    $updated = $wpdb->update(CPP_DB_CATEGORIES, $data, ['id' => $cat_id]);

    if ($updated !== false) {
        wp_send_json_error(['message' => __('دسته‌بندی با موفقیت به‌روزرسانی شد.', 'cpp-full')]);
    } else {
        wp_send_json_error(['message' => __('خطا در به‌روزرسانی دسته‌بندی.', 'cpp-full') . ' ' . $wpdb->last_error], 500);
    }
    wp_die();
}

// ویرایش سریع (Quick Update)
add_action('wp_ajax_cpp_quick_update', 'cpp_quick_update');
function cpp_quick_update() {
    check_ajax_referer('cpp_admin_nonce', 'security');
    global $wpdb;

    $id    = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $field = isset($_POST['field']) ? sanitize_key($_POST['field']) : '';
    $table_type = isset($_POST['table_type']) ? sanitize_key($_POST['table_type']) : '';
    $value_raw = isset($_POST['value']) ? wp_unslash($_POST['value']) : null;

    if (!$id || !$field || !$table_type || $value_raw === null) {
        wp_send_json_error(['message' => 'اطلاعات ارسالی ناقص است.'], 400);
    }

    $value = null;
    if ($field === 'admin_note' || $field === 'description') $value = wp_kses_post($value_raw);
    elseif ($field === 'is_active') $value = intval($value_raw);
    elseif (in_array($field, ['price', 'min_price', 'max_price'])) $value = sanitize_text_field($value_raw);
    else $value = sanitize_text_field($value_raw);

    $table = '';
    $allowed_fields = [];

    if ($table_type === 'products') { $table = CPP_DB_PRODUCTS; $allowed_fields = ['name', 'price', 'min_price', 'max_price', 'product_type', 'unit', 'load_location', 'is_active', 'description', 'image_url', 'cat_id']; }
    elseif ($table_type === 'orders') { $table = CPP_DB_ORDERS; $allowed_fields = ['admin_note', 'status']; }
    elseif ($table_type === 'categories') { $table = CPP_DB_CATEGORIES; $allowed_fields = ['name', 'slug', 'image_url']; }
    else { wp_send_json_error(['message' => 'نوع جدول نامعتبر است.'], 400); }

    if (!in_array($field, $allowed_fields)) { wp_send_json_error(['message' => 'فیلد مورد نظر برای ویرایش نامعتبر است: ' . esc_html($field)], 400); }

    $data_to_update = [$field => $value];
    $response_data = ['message' => __('با موفقیت به‌روزرسانی شد.', 'cpp-full')]; 

    if ($table_type === 'products') {
        $data_to_update['last_updated_at'] = current_time('mysql', 1); 
        $old_data = $wpdb->get_row($wpdb->prepare("SELECT price, min_price, max_price FROM " . CPP_DB_PRODUCTS . " WHERE id = %d", $id));
        if ($old_data && in_array($field, ['price', 'min_price', 'max_price'])) {
            if ($old_data->$field != $value) {
                CPP_Core::save_price_history($id, $value, $field); 
            }
        }
        $response_data['new_time'] = date_i18n('Y/m/d H:i:s', current_time('timestamp')); 
    }
    if ($table_type === 'orders' && $field === 'status') {
         $allowed_statuses = ['new_order', 'negotiating', 'cancelled', 'completed'];
         if (!in_array($value, $allowed_statuses)) wp_send_json_error(['message' => 'وضعیت سفارش نامعتبر است.'], 400);
    }
     if ($table_type === 'categories' && $field === 'slug') {
         $value = sanitize_title($value);
         if(empty($value)){
            $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM " . CPP_DB_CATEGORIES . " WHERE id = %d", $id));
            if($name) $value = sanitize_title($name);
         }
         $data_to_update['slug'] = $value;
     }

    $updated = $wpdb->update($table, $data_to_update, ['id' => $id]);

    if ($updated === false) {
        wp_send_json_error(['message' => __('خطا در به‌روزرسانی دیتابیس:', 'cpp-full') . ' ' . $wpdb->last_error], 500);
    }
    wp_send_json_success($response_data);
    wp_die();
}

// هوک المنتور
add_action('elementor/frontend/after_register_styles', 'cpp_enqueue_styles_elementor');
add_action('elementor/preview/enqueue_styles', 'cpp_enqueue_styles_elementor'); 
function cpp_enqueue_styles_elementor() {
     if (!wp_style_is('cpp-front-css', 'enqueued')) {
         cpp_front_assets();
     }
}

// تست ارسال ایمیل (AJAX)
add_action('wp_ajax_cpp_test_email', 'cpp_ajax_test_email');
function cpp_ajax_test_email() {
    check_ajax_referer('cpp_admin_nonce', 'security');
    if (!CPP_Core::has_access()) wp_send_json_error(['log' => 'Error: You do not have permission.'], 403);

    $log = "--- Starting Email Test ---\nTime: " . current_time('mysql', 1) . "\n";
    $to = get_option('cpp_admin_email', get_option('admin_email'));
    if (empty($to) || !is_email($to)) {
        wp_send_json_error(['log' => $log . "Error: Invalid admin email.\n"]); return;
    }

    $sent = wp_mail($to, 'Test Email from CPP', 'This is a test email.', ['Content-Type: text/html; charset=UTF-8']);
    wp_send_json_success(['log' => $log . ($sent ? "Success!" : "Failed.")]);
    wp_die();
}

// تست پیامک (AJAX)
add_action('wp_ajax_cpp_test_sms', 'cpp_ajax_test_sms');
function cpp_ajax_test_sms() {
    check_ajax_referer('cpp_admin_nonce', 'security');
    if (!CPP_Core::has_access()) wp_send_json_error(['log' => 'Error: You do not have permission.'], 403);
    
    wp_send_json_success(['log' => 'SMS Test Logic Initiated...']); 
    wp_die();
}
?>
