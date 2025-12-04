<?php
if (!defined('ABSPATH')) exit;

class CPP_Core {

    // تابع برای شروع Session در صورت نیاز
    public static function init_session() {
        if (!session_id() && !headers_sent()) {
            try {
                 @session_start(); // Suppress errors if session already started elsewhere
            } catch (Exception $e) {
                 error_log('CPP Error starting session: ' . $e->getMessage());
            }
        }
    }


    public static function create_db_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE " . CPP_DB_CATEGORIES . " ( id mediumint(9) NOT NULL AUTO_INCREMENT, name varchar(200) NOT NULL, slug varchar(200) NOT NULL, image_url varchar(255) DEFAULT '', created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (id), UNIQUE KEY slug (slug) ) $charset_collate;"; // Added unique slug, NOT NULL timestamp
        $sql2 = "CREATE TABLE " . CPP_DB_PRODUCTS . " ( id mediumint(9) NOT NULL AUTO_INCREMENT, cat_id mediumint(9) NOT NULL, name varchar(200) NOT NULL, price varchar(50) DEFAULT '', min_price varchar(50) DEFAULT '', max_price varchar(50) DEFAULT '', product_type varchar(100) DEFAULT '', unit varchar(50) DEFAULT '', load_location varchar(200) DEFAULT '', is_active tinyint(1) DEFAULT 1, description text, image_url varchar(255) DEFAULT '', last_updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (id), KEY cat_id (cat_id) ) $charset_collate;"; // NOT NULL timestamps
        $sql3 = "CREATE TABLE " . CPP_DB_ORDERS . " ( id mediumint(9) NOT NULL AUTO_INCREMENT, product_id mediumint(9) NOT NULL, product_name varchar(200) NOT NULL, customer_name varchar(200) NOT NULL, phone varchar(50) NOT NULL, qty varchar(50) NOT NULL, unit varchar(50) DEFAULT '',           -- Added Unit
            load_location varchar(200) DEFAULT '', -- Added Load Location
            note text,                              -- Customer Note (already exists)
            admin_note text, status varchar(50) DEFAULT 'new_order', created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (id), KEY product_id (product_id), KEY phone (phone) ) $charset_collate;"; // NOT NULL timestamp
        $sql4 = "CREATE TABLE " . CPP_DB_PRICE_HISTORY . " ( id bigint(20) NOT NULL AUTO_INCREMENT, product_id mediumint(9) NOT NULL, price varchar(50) DEFAULT NULL, min_price varchar(50) DEFAULT NULL, max_price varchar(50) DEFAULT NULL, change_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY (id), KEY product_id (product_id) ) $charset_collate;"; // NOT NULL timestamp

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);

        // --- Add upgrade logic ---
        $table_name_history = CPP_DB_PRICE_HISTORY;
        if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name_history)) == $table_name_history) {
            $history_columns = $wpdb->get_col("DESC `{$table_name_history}`");
             if(!in_array('min_price', $history_columns)) { $wpdb->query("ALTER TABLE `{$table_name_history}` ADD `min_price` VARCHAR(50) DEFAULT NULL AFTER `price`"); }
             if(!in_array('max_price', $history_columns)) { $wpdb->query("ALTER TABLE `{$table_name_history}` ADD `max_price` VARCHAR(50) DEFAULT NULL AFTER `min_price`"); }
             $wpdb->query("ALTER TABLE `{$table_name_history}` MODIFY `change_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
        $table_name_orders = CPP_DB_ORDERS;
         if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name_orders)) == $table_name_orders) {
             $order_columns = $wpdb->get_col("DESC `{$table_name_orders}`");
             if(!in_array('unit', $order_columns)) { $wpdb->query("ALTER TABLE `{$table_name_orders}` ADD `unit` VARCHAR(50) DEFAULT '' AFTER `qty`"); }
             if(!in_array('load_location', $order_columns)) { $wpdb->query("ALTER TABLE `{$table_name_orders}` ADD `load_location` VARCHAR(200) DEFAULT '' AFTER `unit`"); }
             $wpdb->query("ALTER TABLE `{$table_name_orders}` MODIFY `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
         }
        if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", CPP_DB_CATEGORIES)) == CPP_DB_CATEGORIES) { $wpdb->query("ALTER TABLE `" . CPP_DB_CATEGORIES . "` MODIFY `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP"); }
        if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", CPP_DB_PRODUCTS)) == CPP_DB_PRODUCTS) { $wpdb->query("ALTER TABLE `" . CPP_DB_PRODUCTS . "` MODIFY `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP"); $wpdb->query("ALTER TABLE `" . CPP_DB_PRODUCTS . "` MODIFY `last_updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP"); }

    }

    public static function save_price_history($product_id, $new_value, $field_name = 'price') {
         global $wpdb;
         $product_id = intval($product_id);
         if (!$product_id || !in_array($field_name, ['price', 'min_price', 'max_price'])) {
             return false;
         }
         $data_to_insert = [
             'product_id' => $product_id,
             'change_time' => current_time('mysql', 1), // Use GMT
             'price' => null, 'min_price' => null, 'max_price' => null,
         ];
         $data_to_insert[$field_name] = sanitize_text_field($new_value);
         $inserted = $wpdb->insert(CPP_DB_PRICE_HISTORY, $data_to_insert);
         if ($inserted) {
              $wpdb->update(CPP_DB_PRODUCTS, ['last_updated_at' => current_time('mysql', 1)], ['id' => $product_id]); // Use GMT
              return true;
         }
          return false;
     }

    // ... (get_chart_data, get_all_categories, get_all_orders without change) ...
    public static function get_chart_data($product_id, $months = 6) {
        global $wpdb;

        $product_id = intval($product_id);
        $months = intval($months);
        if ($product_id <= 0 || $months <= 0) {
            return ['labels' => [], 'prices' => [], 'min_prices' => [], 'max_prices' => []];
        }

        $disable_base_price = get_option('cpp_disable_base_price', 0);
        $labels = [];
        $prices = [];
        $min_prices = [];
        $max_prices = [];

         $history = $wpdb->get_results($wpdb->prepare("
            SELECT price, min_price, max_price, change_time
            FROM " . CPP_DB_PRICE_HISTORY . "
            WHERE product_id = %d AND change_time >= DATE_SUB(NOW(), INTERVAL %d MONTH)
            ORDER BY change_time ASC
        ", $product_id, $months));

        if ($history) {
             $last_price = null;
             $last_min = null;
             $last_max = null;
             foreach ($history as $row) {
                 $local_timestamp = strtotime(get_date_from_gmt($row->change_time));
                 $labels[] = date_i18n('Y/m/d H:i', $local_timestamp);
                 if ($row->price !== null) $last_price = (float)str_replace(',', '', $row->price);
                 if ($row->min_price !== null) $last_min = (float)str_replace(',', '', $row->min_price);
                 if ($row->max_price !== null) $last_max = (float)str_replace(',', '', $row->max_price);
                 $prices[] = (!$disable_base_price) ? $last_price : null;
                 $min_prices[] = $last_min;
                 $max_prices[] = $last_max;
             }
         }
        return [ 'labels' => $labels, 'prices' => $prices, 'min_prices' => $min_prices, 'max_prices' => $max_prices ];
    }
    public static function get_all_categories() {
        global $wpdb;
        if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", CPP_DB_CATEGORIES)) != CPP_DB_CATEGORIES) return [];
        return $wpdb->get_results("SELECT id, name, slug, image_url, created FROM " . CPP_DB_CATEGORIES . " ORDER BY name ASC");
    }
    public static function get_all_orders() {
        global $wpdb;
        if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", CPP_DB_ORDERS)) != CPP_DB_ORDERS) return [];
        return $wpdb->get_results("SELECT * FROM " . CPP_DB_ORDERS . " ORDER BY created DESC");
    }

} // End CPP_Core Class


add_action('init', ['CPP_Core', 'init_session'], 1); // Run early

add_action('wp_ajax_cpp_get_captcha', 'cpp_ajax_get_captcha');
add_action('wp_ajax_nopriv_cpp_get_captcha', 'cpp_ajax_get_captcha');
function cpp_ajax_get_captcha() {
    check_ajax_referer('cpp_front_nonce', 'nonce');
    CPP_Core::init_session();
    $captcha_code = rand(1000, 9999);
    $_SESSION['cpp_captcha_code'] = (string) $captcha_code;
    wp_send_json_success(['code' => (string) $captcha_code]);
    wp_die();
}

add_action('wp_ajax_cpp_get_chart_data', 'cpp_ajax_get_chart_data');
add_action('wp_ajax_nopriv_cpp_get_chart_data', 'cpp_ajax_get_chart_data');
function cpp_ajax_get_chart_data() {
     check_ajax_referer('cpp_front_nonce', 'nonce');
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    if (!$product_id) wp_send_json_error(__('Invalid Product ID', 'cpp-full'), 400);
    $data = CPP_Core::get_chart_data($product_id);
    if(empty($data['labels'])) {
        wp_send_json_error(__('No price history found for this product.', 'cpp-full'), 404);
    }
    wp_send_json_success($data);
    wp_die();
}


add_action('wp_ajax_cpp_submit_order', 'cpp_submit_order');
add_action('wp_ajax_nopriv_cpp_submit_order', 'cpp_submit_order');
function cpp_submit_order() {
    check_ajax_referer('cpp_front_nonce','nonce');
    global $wpdb;

    CPP_Core::init_session();
    $user_captcha = isset($_POST['captcha_input']) ? trim(sanitize_text_field($_POST['captcha_input'])) : '';
    $session_captcha = isset($_SESSION['cpp_captcha_code']) ? $_SESSION['cpp_captcha_code'] : '';
    unset($_SESSION['cpp_captcha_code']);
    if (empty($user_captcha) || empty($session_captcha) || $user_captcha !== $session_captcha) {
        wp_send_json_error(['message' => __('کد امنیتی وارد شده صحیح نیست.', 'cpp-full'), 'code' => 'captcha_error'], 400);
        wp_die();
    }

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    // Fetch product details needed for order and notifications
    $product = $wpdb->get_row($wpdb->prepare("SELECT name, unit, load_location FROM " . CPP_DB_PRODUCTS . " WHERE id=%d AND is_active = 1", $product_id));
    if (!$product) {
        wp_send_json_error(['message' => __('محصول انتخاب شده یافت نشد یا فعال نیست.', 'cpp-full')], 404);
        wp_die();
    }

    $customer_name = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $qty = isset($_POST['qty']) ? sanitize_text_field(wp_unslash($_POST['qty'])) : '';
    $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

    if(empty($customer_name) || empty($phone) || empty($qty)){
        wp_send_json_error(['message' => __('لطفا تمام فیلدهای ستاره‌دار (نام، شماره تماس، مقدار) را پر کنید.', 'cpp-full')], 400);
        wp_die();
    }

    $order_data = [
        'product_id'    => $product_id,
        'product_name'  => $product->name,
        'customer_name' => $customer_name,
        'phone'         => $phone,
        'qty'           => $qty,
        'unit'          => $product->unit,
        'load_location' => $product->load_location,
        'note'          => $note,
        'status'        => 'new_order',
        'created'       => current_time('mysql', 1) // Use GMT
    ];
    $order_formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

    $inserted = $wpdb->insert(CPP_DB_ORDERS, $order_data, $order_formats);

    if (!$inserted) {
         wp_send_json_error(['message' => __('خطا در ثبت سفارش در دیتابیس.', 'cpp-full') . ' ' . $wpdb->last_error], 500);
         wp_die();
    }

    $order_id = $wpdb->insert_id;

    // --- ارسال اعلان‌ها ---
    // Placeholders for Admin notifications
    $admin_placeholders = [
        '{product_name}'  => $product->name,
        '{customer_name}' => $customer_name,
        '{phone}'         => $phone,
        '{qty}'           => $qty,
        '{unit}'          => $product->unit ?? '',         // Ensure empty string if null
        '{load_location}' => $product->load_location ?? '', // Ensure empty string if null
        '{note}'          => $note,
    ];

    // ۱. ارسال ایمیل به مدیر
    if (get_option('cpp_enable_email') && class_exists('CPP_Full_Email')) {
        CPP_Full_Email::send_notification($admin_placeholders);
    }

    // ۲. ارسال پیامک به مدیر (با الگو)
    if (get_option('cpp_sms_service') === 'ippanel' && class_exists('CPP_Full_SMS')) {
        CPP_Full_SMS::send_notification($admin_placeholders); // Handles admin SMS
    }

    // ۳. ارسال پیامک به مشتری (با الگو)
    if (get_option('cpp_sms_service') === 'ippanel' && get_option('cpp_sms_customer_enable') && class_exists('CPP_Full_SMS')) {
        $customer_pattern_code = get_option('cpp_sms_customer_pattern_code');
        $api_key = get_option('cpp_sms_api_key');
        $sender = get_option('cpp_sms_sender');

        if ($customer_pattern_code && $api_key && $sender) {
             // --- شروع تغییر: اطمینان از ارسال تمام متغیرهای مورد انتظار الگو ---
             // متغیرهای لازم برای الگوی پیشنهادی مشتری
            $customer_variables_needed = ['customer_name', 'product_name', 'unit', 'load_location', 'qty'];
            $customer_variables = [];
            foreach($customer_variables_needed as $var_name) {
                // Remove {} if they exist in the key from admin_placeholders
                $placeholder_key = '{' . $var_name . '}';
                // Assign value if exists in placeholders, otherwise send an empty string
                $customer_variables[$var_name] = isset($admin_placeholders[$placeholder_key]) ? $admin_placeholders[$placeholder_key] : '';
            }
             // --- پایان تغییر ---

             // فراخوانی تابع ارسال الگو با اطلاعات مشتری
             $customer_sms_sent = CPP_Full_SMS::ippanel_send_pattern($api_key, $sender, $phone, $customer_pattern_code, $customer_variables);
             if ($customer_sms_sent) {
                error_log("CPP Customer SMS SENT successfully for Order ID: ".$order_id." to ".$phone. " using pattern ".$customer_pattern_code);
             } else {
                 error_log("CPP Customer SMS FAILED for Order ID: ".$order_id." to ".$phone. " using pattern ".$customer_pattern_code);
             }
        } else {
             error_log("CPP Customer SMS Error for Order ID ".$order_id.": Customer pattern code, API Key, or Sender is missing/invalid.");
        }
    }

    wp_send_json_success(['message' => __('درخواست شما با موفقیت ثبت شد. همکاران ما به زودی با شما تماس خواهند گرفت.', 'cpp-full')]);
    wp_die();
}

?>
