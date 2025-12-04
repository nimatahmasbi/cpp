<?php
if (!defined('ABSPATH')) exit;

/**
 * مدیریت بخش پیشخوان وردپرس افزونه
 * شامل ثبت منوها، اسکریپت‌ها، استایل‌ها و مدیریت ایجکس
 */

// ۱. ثبت و بارگذاری اسکریپت‌ها و استایل‌های بخش مدیریت
add_action('admin_enqueue_scripts', 'cpp_admin_assets');
function cpp_admin_assets($hook) {
     // بررسی اینکه آیا در یکی از صفحات افزونه هستیم یا خیر
    $allowed_hooks = [
        'toplevel_page_custom-prices-products',
        'مدیریت-قیمت_page_custom-prices-categories', // Use the localized slug if needed
        'مدیریت-قیمت_page_custom-prices-orders',
        'مدیریت-قیمت_page_custom-prices-shortcodes',
        'مدیریت-قیمت_page_custom-prices-settings',
        'admin_page_custom-prices-product-edit' // Hidden page slug
    ];
     // Also check against non-localized slugs just in case
     $allowed_hooks = array_merge($allowed_hooks, [
         'custom-prices_page_custom-prices-categories',
         'custom-prices_page_custom-prices-orders',
         'custom-prices_page_custom-prices-shortcodes',
         'custom-prices_page_custom-prices-settings',
     ]);


     // A more reliable way to check if it's one of our plugin pages
     $is_cpp_page = in_array($hook, $allowed_hooks);
     // Also check if the page query var exists
     if (!$is_cpp_page && isset($_GET['page']) && strpos($_GET['page'], 'custom-prices-') === 0) {
        $is_cpp_page = true;
     }


    // Allow loading on post edit screens if needed for shortcode helpers (currently not used)
    // if (!$is_cpp_page && $hook !== 'post.php' && $hook !== 'post-new.php') return;
    if (!$is_cpp_page) return; // Only load on our plugin pages


    // کتابخانه‌های عمومی مورد نیاز
    wp_enqueue_media();
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', [], null, true);

    // اسکریپت اصلی مدیریت
    wp_enqueue_script('cpp-admin-js', CPP_ASSETS_URL . 'js/admin.js', ['jquery', 'wp-i18n', 'chart-js', 'wp-util'], CPP_VERSION, true); // Added wp-util dependency

    // افزودن اسکریپت و استایل انتخاب‌گر رنگ فقط برای صفحه تنظیمات
    if ($hook === 'مدیریت-قیمت_page_custom-prices-settings' || $hook === 'custom-prices_page_custom-prices-settings') {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('cpp-color-picker-init', CPP_ASSETS_URL . 'js/admin-color-picker.js', ['wp-color-picker', 'jquery'], CPP_VERSION, true);
    }

    // آماده‌سازی متغیرها برای ارسال به جاوا اسکریپت
    $order_statuses = [
        'new_order'     => __('سفارش جدید', 'cpp-full'),
        'negotiating'   => __('در حال مذاکره', 'cpp-full'),
        'cancelled'     => __('کنسل شد', 'cpp-full'),
        'completed'     => __('خرید انجام شد', 'cpp-full'),
    ];
    // --- Pass status options for product quick edit ---
    $status_options = [ '1' => __('فعال', 'cpp-full'), '0' => __('غیرفعال', 'cpp-full') ];


    wp_localize_script('cpp-admin-js', 'cpp_admin_vars', [
        'ajax_url'      => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('cpp_admin_nonce'),
        'edit_url_base' => admin_url('admin.php?page=custom-prices-product-edit&id='), // This might not be needed if using AJAX modals
        'order_statuses' => $order_statuses,
        'product_statuses' => $status_options, // Pass product statuses
        'i18n' => [ // Pass general admin translations
            'saving' => __('در حال ذخیره...', 'cpp-full'),
            'save' => __('ذخیره', 'cpp-full'),
            'cancel' => __('لغو', 'cpp-full'),
            'error' => __('خطا', 'cpp-full'),
            'serverError' => __('خطای سرور', 'cpp-full'),
            'loadingForm' => __('در حال بارگذاری فرم ویرایش...', 'cpp-full'),
             'confirmDelete' => __('آیا مطمئنید؟', 'cpp-full'),
             'sendingTestEmail' => __('در حال ارسال ایمیل تست...', 'cpp-full'),
             'sendTestEmail' => __('ارسال ایمیل تست', 'cpp-full'),
             'sendingTestSms' => __('در حال ارسال پیامک تست...', 'cpp-full'),
             'sendTestSms' => __('ارسال پیامک تست به مدیر', 'cpp-full'),
        ]
    ]);

    // استایل اصلی بخش مدیریت
    wp_enqueue_style('cpp-admin-css', CPP_ASSETS_URL . 'css/admin.css', [], CPP_VERSION);

    // استایل‌های درون‌خطی برای مودال (پاپ‌آپ) - Removed as they should be in admin.css
    // wp_add_inline_style('cpp-admin-css', $custom_css);

    // اسکریپت درون‌خطی برای مدیریت آپلودر رسانه وردپرس
     // --- Improved Media Uploader Initialization ---
    wp_add_inline_script('cpp-admin-js', '
        window.cpp_init_media_uploader = function() {
            var mediaUploader;
            // Use event delegation on a static parent if modal content is dynamic
            jQuery("body").off("click.cppuploader", ".cpp-upload-btn").on("click.cppuploader", ".cpp-upload-btn", function(e) {
                e.preventDefault();
                var button = jQuery(this);
                var inputId = button.data("input-id") || button.siblings("input[type=\"text\"]").attr("id");
                 if (!inputId) {
                     // Try finding based on relative position if no ID/data-id
                     input_field = button.prev("input[type=\'text\']");
                 } else {
                     input_field = jQuery("#" + inputId);
                 }

                // More robust preview container finding
                var preview_img_container = button.closest("td, .cpp-image-uploader-wrapper, .form-table tr").find(".cpp-image-preview");

                if (!input_field.length) {
                    console.error("CPP Uploader: Could not find target input field.");
                    return;
                }
                 if (!preview_img_container.length) {
                    console.warn("CPP Uploader: Could not find preview container.");
                    // Create one if needed? Or just skip preview.
                 }


                // Reinitialize frame every time
                mediaUploader = wp.media({ title: "'.__('انتخاب یا آپلود تصویر', 'cpp-full').'", button: { text: "'.__('استفاده از این تصویر', 'cpp-full').'" }, multiple: false });

                // Use a closure
                (function(target_input, target_preview) {
                    mediaUploader.off("select"); // Remove previous handlers
                    mediaUploader.on("select", function() {
                        var attachment = mediaUploader.state().get("selection").first().toJSON();
                        target_input.val(attachment.url).trigger("change");
                         if(target_preview.length) { // Check if preview container exists
                            target_preview.html("<img src=\"" + attachment.url + "\" style=\"max-width: 100px; height: auto; margin-top: 10px; border: 1px solid #ddd; padding: 3px;\">");
                         }
                    });
                     mediaUploader.open();
                })(input_field, preview_img_container);

            });
        };
        // Initial call on document ready
        jQuery(document).ready(function(){
             window.cpp_init_media_uploader();
        });
    ', 'after');
}


// ... (کدهای توابع cpp_admin_menu, cpp_add_order_count_bubble, توابع نمایش صفحات, cpp_handle_admin_actions, AJAX محصولات, AJAX دسته‌بندی‌ها, AJAX ویرایش سریع, AJAX نمودار, هوک المنتور, AJAX تست ایمیل بدون تغییر) ...

// ۲. ثبت منوهای افزونه در پیشخوان وردپرس
add_action('admin_menu', 'cpp_admin_menu');
function cpp_admin_menu() {
    $capability = get_option('cpp_admin_capability', 'manage_options');
    $main_slug = 'custom-prices-products';

    add_menu_page( __('مدیریت قیمت‌ها و سفارشات', 'cpp-full'), __('مدیریت قیمت', 'cpp-full'), $capability, $main_slug, 'cpp_products_page', 'dashicons-tag', 30 );
    add_submenu_page($main_slug, __('محصولات', 'cpp-full'), __('محصولات', 'cpp-full'), $capability, $main_slug, 'cpp_products_page'); // Explicit submenu for products
    add_submenu_page($main_slug, __('دسته‌بندی‌ها', 'cpp-full'), __('دسته‌بندی‌ها', 'cpp-full'), $capability, 'custom-prices-categories', 'cpp_categories_page');
    add_submenu_page($main_slug, __('سفارشات', 'cpp-full'), __('سفارشات مشتری', 'cpp-full'), $capability, 'custom-prices-orders', 'cpp_orders_page');
    add_submenu_page($main_slug, __('شورت‌کدها', 'cpp-full'), __('شورت‌کدها', 'cpp-full'), $capability, 'custom-prices-shortcodes', 'cpp_shortcodes_page');
    add_submenu_page($main_slug, __('تنظیمات', 'cpp-full'), __('تنظیمات', 'cpp-full'), $capability, 'custom-prices-settings', 'cpp_settings_page');
    // صفحه مخفی برای ویرایش محصول - Ensure slug is unique if needed or kept null
    add_submenu_page( null, __('ویرایش محصول', 'cpp-full'), __('ویرایش محصول', 'cpp-full'), $capability, 'custom-prices-product-edit', 'cpp_product_edit_page' );
}

// ۳. افزودن نشانگر عددی تعداد سفارشات جدید به منو
add_action('admin_menu', 'cpp_add_order_count_bubble', 99);
function cpp_add_order_count_bubble() {
    global $wpdb, $menu;
    $capability = get_option('cpp_admin_capability', 'manage_options');
    if (!current_user_can($capability)) return;
    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM " . CPP_DB_ORDERS . " WHERE status = %s", 'new_order'));
    if ($count > 0) {
        $menu_slug = 'custom-prices-products'; // Main menu slug
        foreach ($menu as $key => $value) {
            if ($menu[$key][2] == $menu_slug) {
                // Add bubble to main menu item
                $menu[$key][0] .= ' <span class="update-plugins count-' . intval($count) . '"><span class="plugin-count">' . intval($count) . '</span></span>';

                // Optionally add bubble to the Orders submenu item too
                 global $submenu;
                 if (isset($submenu[$menu_slug])) {
                     foreach ($submenu[$menu_slug] as $sub_key => $sub_value) {
                         if ($submenu[$menu_slug][$sub_key][2] == 'custom-prices-orders') {
                             $submenu[$menu_slug][$sub_key][0] .= ' <span class="update-plugins count-' . intval($count) . '"><span class="plugin-count">' . intval($count) . '</span></span>';
                             break; // Found the orders submenu
                         }
                     }
                 }
                return; // Stop after finding main menu
            }
        }
    }
}

// ۴. توابع Callback برای نمایش محتوای هر صفحه از منو
function cpp_products_page() {
    include CPP_TEMPLATES_DIR . 'products.php';
    // Modal HTML is now created by JS if needed
    // echo '<div id="cpp-edit-modal" ... ></div>';
}
function cpp_categories_page() {
    include CPP_TEMPLATES_DIR . 'categories.php';
    // Modal HTML is now created by JS if needed
}
function cpp_orders_page() { include CPP_TEMPLATES_DIR . 'orders.php'; }
function cpp_settings_page() { include CPP_TEMPLATES_DIR . 'settings.php'; }
function cpp_shortcodes_page() { include CPP_TEMPLATES_DIR . 'shortcodes.php'; }
function cpp_product_edit_page() { include CPP_TEMPLATES_DIR . 'product-edit.php'; } // Standalone edit page

// ۵. مدیریت فرم‌های POST (افزودن و حذف)
add_action('admin_init', 'cpp_handle_admin_actions');
function cpp_handle_admin_actions() {
    global $wpdb;
    $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

    // افزودن دسته‌بندی جدید (Non-AJAX) - Keep for direct form submit fallback?
    if (isset($_POST['cpp_add_category']) && $current_page === 'custom-prices-categories') {
        if (!isset($_POST['cpp_add_cat_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['cpp_add_cat_nonce']), 'cpp_add_cat_action')) { wp_die(__('Security check failed.', 'cpp-full')); }
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
        $image_url = isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '';
        if (empty($slug) && !empty($name)) $slug = sanitize_title($name);

        $message = 'category_add_failed';
        if (!empty($name)) {
             $inserted = $wpdb->insert(CPP_DB_CATEGORIES, array('name' => $name,'slug' => $slug,'image_url' => $image_url, 'created' => current_time('mysql', 1)), ['%s', '%s', '%s', '%s']);
             if ($inserted) {
                 $message = 'category_added';
             } else {
                  error_log("CPP DB Error adding category: " . $wpdb->last_error);
             }
        }
        wp_redirect(add_query_arg('cpp_message', $message, admin_url('admin.php?page=custom-prices-categories'))); exit;
    }

    // افزودن محصول جدید (Non-AJAX)
    if (isset($_POST['cpp_add_product']) && $current_page === 'custom-prices-products') {
        if (!isset($_POST['cpp_add_product_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['cpp_add_product_nonce']), 'cpp_add_product_action')) { wp_die(__('Security check failed.', 'cpp-full')); }

        // Sanitize all inputs
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
            'description'  => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '', // Use sanitize_textarea_field
            'image_url'    => isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '',
            'created'      => current_time('mysql', 1), // Use GMT
            'last_updated_at' => current_time('mysql', 1) // Use GMT
        ];

        $message = 'product_add_failed';
        if (!empty($data['name']) && !empty($data['cat_id'])) { // Basic validation
             $inserted = $wpdb->insert(CPP_DB_PRODUCTS, $data);
             if ($inserted) {
                 $product_id = $wpdb->insert_id;
                 // Save initial price history if price was set
                 if (!empty($data['price'])) {
                      CPP_Core::save_price_history($product_id, $data['price'], 'price');
                 }
                  if (!empty($data['min_price'])) {
                     CPP_Core::save_price_history($product_id, $data['min_price'], 'min_price');
                 }
                  if (!empty($data['max_price'])) {
                     CPP_Core::save_price_history($product_id, $data['max_price'], 'max_price');
                 }
                 $message = 'product_added';
             } else {
                  error_log("CPP DB Error adding product: " . $wpdb->last_error);
             }
        }
        wp_redirect(add_query_arg('cpp_message', $message, admin_url('admin.php?page=custom-prices-products'))); exit;
    }


    // حذف آیتم‌ها (محصول، دسته‌بندی، سفارش) - Stays the same as before
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && isset($_GET['_wpnonce'])) {
        $id = intval($_GET['id']);
        $redirect_url = admin_url('admin.php?page=' . $current_page); // Use current page
        $deleted = false;
        $db_table = '';
        $nonce_action = '';
        $message_success = '';
        $message_failed = '';

        if ($current_page == 'custom-prices-categories') {
            $nonce_action = 'cpp_delete_cat_' . $id;
            $db_table = CPP_DB_CATEGORIES;
            $message_success = 'category_deleted';
            $message_failed = 'category_delete_failed';
        } elseif ($current_page == 'custom-prices-products') {
            $nonce_action = 'cpp_delete_product_' . $id;
            $db_table = CPP_DB_PRODUCTS;
            $message_success = 'product_deleted';
            $message_failed = 'product_delete_failed';
        } elseif ($current_page == 'custom-prices-orders') {
            $nonce_action = 'cpp_delete_order_' . $id;
            $db_table = CPP_DB_ORDERS;
            $message_success = 'order_deleted';
            $message_failed = 'order_delete_failed';
        }

        if ($db_table && $nonce_action && wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), $nonce_action)) {
             $deleted = $wpdb->delete($db_table, array('id' => $id), array('%d'));
        } else {
            // Nonce failed or invalid page for delete action
            wp_die(__('Invalid delete request or security check failed.', 'cpp-full'));
        }

        $redirect_url = add_query_arg('cpp_message', $deleted ? $message_success : $message_failed, $redirect_url);
        wp_redirect($redirect_url); exit;
    }
}


// ... (AJAX handlers for fetch/handle edit forms, quick edit, chart data, email test - remain the same as previous correct versions) ...

// ۶. توابع AJAX برای محصولات
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
    include CPP_TEMPLATES_DIR . 'product-edit.php'; // Template uses $_GET['id'] internally
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
     wp_die();
}


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
        'last_updated_at' => current_time('mysql', 1) // Use GMT
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


// ۷. توابع AJAX برای دسته‌بندی‌ها
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
    include CPP_TEMPLATES_DIR . 'category-edit.php'; // Template uses $_GET['id'] internally
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
    wp_die();
}


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
        wp_send_json_success(['message' => __('دسته‌بندی با موفقیت به‌روزرسانی شد.', 'cpp-full')]);
    } else {
        wp_send_json_error(['message' => __('خطا در به‌روزرسانی دسته‌بندی.', 'cpp-full') . ' ' . $wpdb->last_error], 500);
    }
    wp_die();
}


// ۸. تابع AJAX برای ویرایش سریع (Quick Edit)
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
    $response_data = ['message' => __('با موفقیت به‌روزرسانی شد.', 'cpp-full')]; // Standardized message key

    if ($table_type === 'products') {
        $data_to_update['last_updated_at'] = current_time('mysql', 1); // Use GMT
        $old_data = $wpdb->get_row($wpdb->prepare("SELECT price, min_price, max_price FROM " . CPP_DB_PRODUCTS . " WHERE id = %d", $id));
        if ($old_data && in_array($field, ['price', 'min_price', 'max_price'])) {
            if ($old_data->$field != $value) {
                CPP_Core::save_price_history($id, $value, $field); // Pass field name
            }
        }
        $response_data['new_time'] = date_i18n('Y/m/d H:i:s', current_time('timestamp')); // Local time for display
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


// ۹. هوک برای سازگاری با المنتور
add_action('elementor/frontend/after_register_styles', 'cpp_enqueue_styles_elementor');
add_action('elementor/preview/enqueue_styles', 'cpp_enqueue_styles_elementor'); // Also load in preview
function cpp_enqueue_styles_elementor() {
     // Check if assets are already enqueued to prevent double loading
     if (!wp_style_is('cpp-front-css', 'enqueued')) {
         cpp_front_assets();
     }
}


// ۱۰. تابع AJAX برای تست ارسال ایمیل
add_action('wp_ajax_cpp_test_email', 'cpp_ajax_test_email');
function cpp_ajax_test_email() {
    check_ajax_referer('cpp_admin_nonce', 'security');

    $capability = get_option('cpp_admin_capability', 'manage_options');
    if (!current_user_can($capability)) wp_send_json_error(['log' => 'Error: You do not have permission.'], 403);

    $log = "--- Starting Email Test ---\nTime: " . current_time('mysql', 1) . "\n";
    $to = get_option('cpp_admin_email', get_option('admin_email'));
    if (empty($to) || !is_email($to)) {
        $log .= "Error: Invalid or empty admin email address in CPP settings.\n";
        wp_send_json_error(['log' => $log]); return;
    }

    $log .= "Attempting to send a test email to: " . esc_html($to) . "\n";
    $subject = 'ایمیل آزمایشی از افزونه مدیریت قیمت (CPP)';
    $message = '<p style="direction:rtl; text-align:right;">این یک ایمیل آزمایشی برای بررسی صحت عملکرد سیستم ارسال ایمیل وب‌سایت شما از طریق افزونه CPP است.</p>';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $mail_error = null;
    $mail_failed_hook = function ($wp_error) use (&$mail_error) { $mail_error = $wp_error; };
    add_action('wp_mail_failed', $mail_failed_hook, 10, 1);
    $sent = wp_mail($to, $subject, $message, $headers);
    remove_action('wp_mail_failed', $mail_failed_hook, 10);

    if ($sent) {
        $log .= "Success: wp_mail() executed successfully.\nCheck inbox/spam at " . esc_html($to) . ".\n";
        wp_send_json_success(['log' => $log]);
    } else {
        $log .= "Error: wp_mail() failed.\n";
        if ($mail_error instanceof WP_Error) $log .= "Error Details: " . esc_html(implode(', ', $mail_error->get_error_messages())) . "\n";
        else $log .= "No specific WP_Error captured. Check server mail logs or use an SMTP plugin.\n";
        wp_send_json_error(['log' => $log]);
    }
     wp_die();
}


// ۱۱. تابع AJAX برای تست پیامک (فقط الگوی IPPanel)
add_action('wp_ajax_cpp_test_sms', 'cpp_ajax_test_sms');
function cpp_ajax_test_sms() {
    check_ajax_referer('cpp_admin_nonce', 'security');

    $capability = get_option('cpp_admin_capability', 'manage_options');
    if (!current_user_can($capability)) wp_send_json_error(['log' => 'Error: You do not have permission.'], 403);

    $log = "--- Starting IPPanel SMS Pattern Test ---\nTime: " . current_time('mysql', 1) . "\n";
    $service = get_option('cpp_sms_service');
    $apiKey = get_option('cpp_sms_api_key');
    $sender = get_option('cpp_sms_sender');
    $adminPhone = get_option('cpp_admin_phone');
    $pattern_code = get_option('cpp_sms_pattern_code'); // Admin pattern code for test

    if (empty($service) || $service !== 'ippanel') { $log .= "Error: IPPanel SMS Service not enabled.\n"; wp_send_json_error(['log' => $log]); return; }
    if (empty($apiKey)) { $log .= "Error: API Key missing.\n"; wp_send_json_error(['log' => $log]); return; }
    if (empty($sender)) { $log .= "Error: Sender Number missing.\n"; wp_send_json_error(['log' => $log]); return; }
    if (empty($adminPhone)) { $log .= "Error: Admin Phone missing.\n"; wp_send_json_error(['log' => $log]); return; }
    if (empty($pattern_code)) { $log .= "Error: Admin Pattern Code missing.\n"; wp_send_json_error(['log' => $log]); return; }

    $log .= "Attempting test SMS...\nTo: " . esc_html($adminPhone) . "\nFrom: " . esc_html($sender) . "\nPattern: " . esc_html($pattern_code) . "\n";

    $url = 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send';
    $test_variables = [
        'product_name' => 'پیامک تستی', 'customer_name' => 'مدیر سایت',
        'phone' => $adminPhone, 'qty' => '1', 'unit' => 'عدد', // Add all expected vars
        'load_location' => 'دفتر مرکزی', 'note' => 'تست افزونه CPP'
    ];
    $log .= "Variables: " . json_encode($test_variables, JSON_UNESCAPED_UNICODE) . "\n";

    $data = ['code' => $pattern_code, 'sender' => $sender, 'recipient' => $adminPhone, 'variable' => $test_variables];
    $body = json_encode($data);
    $headers = ['Content-Type' => 'application/json', 'apikey' => $apiKey];
    $args = ['body' => $body, 'headers' => $headers, 'method' => 'POST', 'timeout' => 25];

    $log .= "Sending POST request...\n";
    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        $log .= "WP HTTP Error: " . esc_html($error_message) . "\n";
        wp_send_json_error(['log' => $log], 500);
    } else {
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $safe_body = htmlspecialchars($response_body, ENT_QUOTES, 'UTF-8');
        $log .= "HTTP Status: " . intval($http_code) . "\nResponse: " . $safe_body . "\n";

        if ($http_code >= 200 && $http_code < 300) {
            $result = json_decode($response_body);
            if ($result && isset($result->status->code) && $result->status->code == 0 && isset($result->data->message_id)) {
                $log .= "Success! Message ID: " . esc_html($result->data->message_id) . "\nCheck inbox at " . esc_html($adminPhone) . ".\n";
                wp_send_json_success(['log' => $log]);
            } else {
                 $api_error = ($result && isset($result->status->message)) ? $result->status->message : 'Unknown API Logic Error';
                 $log .= "API Error: " . esc_html($api_error) . "\nCheck pattern code/variables.\n";
                 wp_send_json_error(['log' => $log]);
            }
        } else {
             $error_detail = $safe_body;
             $result = json_decode($response_body);
             if ($result && isset($result->status->message)) $error_detail = $result->status->message;
             elseif ($result && isset($result->errorMessage)) $error_detail = $result->errorMessage;
             elseif ($result && isset($result->message)) $error_detail = $result->message;
             $log .= "HTTP Error.\nDetail: " . esc_html($error_detail) . "\nCheck API Key/Sender/Pattern.\n";
             wp_send_json_error(['log' => $log], $http_code);
        }
    }
     wp_die();
}

?>
