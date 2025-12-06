<?php
if (!defined('ABSPATH')) exit;

class CPP_Core {

    public static function init_session() {
        if (!session_id() && !headers_sent()) {
            try {
                 @session_start();
            } catch (Exception $e) {
                 error_log('CPP Error: ' . $e->getMessage());
            }
        }
    }

    public static function has_access() {
        $allowed_roles = get_option('cpp_admin_capability');
        if (empty($allowed_roles)) $allowed_roles = ['administrator'];
        elseif (is_string($allowed_roles)) $allowed_roles = ['administrator']; 

        $current_user = wp_get_current_user();
        if (empty($current_user) || empty($current_user->roles)) return false;

        foreach ($current_user->roles as $user_role) {
            if (in_array($user_role, $allowed_roles)) return true;
        }
        return false;
    }

    public static function create_db_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE " . CPP_DB_CATEGORIES . " ( id mediumint(9) NOT NULL AUTO_INCREMENT, name varchar(200) NOT NULL, slug varchar(200) NOT NULL, image_url varchar(255) DEFAULT '', created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (id), UNIQUE KEY slug (slug) ) $charset_collate;"; 
        $sql2 = "CREATE TABLE " . CPP_DB_PRODUCTS . " ( id mediumint(9) NOT NULL AUTO_INCREMENT, cat_id mediumint(9) NOT NULL, name varchar(200) NOT NULL, price varchar(50) DEFAULT '', min_price varchar(50) DEFAULT '', max_price varchar(50) DEFAULT '', product_type varchar(100) DEFAULT '', unit varchar(50) DEFAULT '', load_location varchar(200) DEFAULT '', is_active tinyint(1) DEFAULT 1, description text, image_url varchar(255) DEFAULT '', last_updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (id), KEY cat_id (cat_id) ) $charset_collate;"; 
        $sql3 = "CREATE TABLE " . CPP_DB_ORDERS . " ( id mediumint(9) NOT NULL AUTO_INCREMENT, product_id mediumint(9) NOT NULL, product_name varchar(200) NOT NULL, customer_name varchar(200) NOT NULL, phone varchar(50) NOT NULL, qty varchar(50) NOT NULL, unit varchar(50) DEFAULT '', load_location varchar(200) DEFAULT '', note text, admin_note text, status varchar(50) DEFAULT 'new_order', created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY  (id), KEY product_id (product_id), KEY phone (phone) ) $charset_collate;"; 
        $sql4 = "CREATE TABLE " . CPP_DB_PRICE_HISTORY . " ( id bigint(20) NOT NULL AUTO_INCREMENT, product_id mediumint(9) NOT NULL, price varchar(50) DEFAULT NULL, min_price varchar(50) DEFAULT NULL, max_price varchar(50) DEFAULT NULL, change_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY (id), KEY product_id (product_id) ) $charset_collate;"; 

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);

        // آپدیت جداول قدیمی
        $table_name_history = CPP_DB_PRICE_HISTORY;
        if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name_history)) == $table_name_history) {
            $history_columns = $wpdb->get_col("DESC `{$table_name_history}`");
             if(!in_array('min_price', $history_columns)) { $wpdb->query("ALTER TABLE `{$table_name_history}` ADD `min_price` VARCHAR(50) DEFAULT NULL AFTER `price`"); }
             if(!in_array('max_price', $history_columns)) { $wpdb->query("ALTER TABLE `{$table_name_history}` ADD `max_price` VARCHAR(50) DEFAULT NULL AFTER `min_price`"); }
        }
        $table_name_orders = CPP_DB_ORDERS;
         if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name_orders)) == $table_name_orders) {
             $order_columns = $wpdb->get_col("DESC `{$table_name_orders}`");
             if(!in_array('unit', $order_columns)) { $wpdb->query("ALTER TABLE `{$table_name_orders}` ADD `unit` VARCHAR(50) DEFAULT '' AFTER `qty`"); }
             if(!in_array('load_location', $order_columns)) { $wpdb->query("ALTER TABLE `{$table_name_orders}` ADD `load_location` VARCHAR(200) DEFAULT '' AFTER `unit`"); }
         }
    }

    // ثبت تاریخچه کامل
    public static function save_price_history($product_id, $new_value, $field_name = 'price') {
         global $wpdb;
         $product_id = intval($product_id);
         if (!$product_id) return false;
         
         $current = $wpdb->get_row($wpdb->prepare("SELECT price, min_price, max_price FROM " . CPP_DB_PRODUCTS . " WHERE id = %d", $product_id));
         if (!$current) return false;

         $p = ($field_name === 'price') ? $new_value : $current->price;
         $min = ($field_name === 'min_price') ? $new_value : $current->min_price;
         $max = ($field_name === 'max_price') ? $new_value : $current->max_price;

         $inserted = $wpdb->insert(CPP_DB_PRICE_HISTORY, [
             'product_id' => $product_id,
             'change_time' => current_time('mysql', 1), 
             'price' => sanitize_text_field($p), 
             'min_price' => sanitize_text_field($min), 
             'max_price' => sanitize_text_field($max),
         ]);
         
         if ($inserted) {
              $wpdb->update(CPP_DB_PRODUCTS, ['last_updated_at' => current_time('mysql', 1)], ['id' => $product_id]);
              return true;
         }
          return false;
     }

    private static function clean_price_value($value) {
        if ($value === null || $value === '') return null;
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $value = str_replace($persian, $english, $value);
        $value = str_replace($arabic, $english, $value);
        $value = preg_replace('/[^0-9.]/', '', $value);
        return ($value === '') ? null : (float)$value;
    }

    public static function get_chart_data($product_id, $months = 12) { // پیش‌فرض ۱۲ ماه
        global $wpdb;

        $product_id = intval($product_id);
        
        $labels = []; $prices = []; $min_prices = []; $max_prices = [];

        // دریافت تمام تاریخچه (فیلتر زمانی را به سمت کلاینت می‌سپاریم یا بازه بزرگتری می‌گیریم)
        $history = $wpdb->get_results($wpdb->prepare("SELECT price, min_price, max_price, change_time FROM " . CPP_DB_PRICE_HISTORY . " WHERE product_id = %d ORDER BY change_time ASC", $product_id));

        // اضافه کردن وضعیت فعلی به عنوان آخرین نقطه
        $current = $wpdb->get_row($wpdb->prepare("SELECT price, min_price, max_price, last_updated_at FROM " . CPP_DB_PRODUCTS . " WHERE id = %d", $product_id));
        if ($current) {
            $last_time = !empty($history) ? end($history)->change_time : '';
            if (empty($history) || $current->last_updated_at > $last_time) {
                $dummy = new stdClass();
                $dummy->change_time = $current->last_updated_at ? $current->last_updated_at : current_time('mysql', 1);
                $dummy->price = $current->price;
                $dummy->min_price = $current->min_price;
                $dummy->max_price = $current->max_price;
                $history[] = $dummy;
            }
        }

        $disable_base_price = get_option('cpp_disable_base_price', 0);

        foreach ($history as $row) {
            $ts = strtotime(get_date_from_gmt($row->change_time));
            if (!$ts) $ts = current_time('timestamp');
            $labels[] = date_i18n('Y/m/d H:i', $ts);

            $p_base = self::clean_price_value($row->price);
            $p_min  = self::clean_price_value($row->min_price);
            $p_max  = self::clean_price_value($row->max_price);

            // محاسبه میانگین اگر قیمت پایه غیرفعال است
            if ($disable_base_price) {
                if ($p_min !== null && $p_max !== null) $prices[] = ($p_min + $p_max) / 2;
                elseif ($p_min !== null) $prices[] = $p_min;
                elseif ($p_max !== null) $prices[] = $p_max;
                else $prices[] = null;
            } else {
                $prices[] = $p_base;
            }

            $min_prices[] = $p_min;
            $max_prices[] = $p_max;
        }

        return [ 'labels' => $labels, 'prices' => $prices, 'min_prices' => $min_prices, 'max_prices' => $max_prices ];
    }
    
    public static function get_all_categories() {
        global $wpdb;
        if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", CPP_DB_CATEGORIES)) != CPP_DB_CATEGORIES) return [];
        return $wpdb->get_results("SELECT id, name, slug, image_url, created FROM " . CPP_DB_CATEGORIES . " ORDER BY id ASC");
    }
    
    public static function get_all_orders() {
        global $wpdb;
        if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", CPP_DB_ORDERS)) != CPP_DB_ORDERS) return [];
        return $wpdb->get_results("SELECT * FROM " . CPP_DB_ORDERS . " ORDER BY id ASC");
    }
} 

add_action('init', ['CPP_Core', 'init_session'], 1);

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
    $is_valid_nonce = false;
    if (isset($_REQUEST['security']) && wp_verify_nonce($_REQUEST['security'], 'cpp_admin_nonce')) $is_valid_nonce = true;
    elseif (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'cpp_front_nonce')) $is_valid_nonce = true;

    if (!$is_valid_nonce) {
        wp_send_json_error(['message' => __('مجوز دسترسی نامعتبر است.', 'cpp-full')], 403);
        wp_die();
    }

    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    if (!$product_id) wp_send_json_error(['message' => __('شناسه محصول نامعتبر است.', 'cpp-full')], 400);
    
    $data = CPP_Core::get_chart_data($product_id);
    
    $has_any_data = false;
    foreach(['prices', 'min_prices', 'max_prices'] as $key) {
        if (!empty($data[$key])) {
            foreach($data[$key] as $val) { if ($val !== null) { $has_any_data = true; break 2; } }
        }
    }

    if(!$has_any_data) {
        wp_send_json_error(['message' => __('هیچ داده قیمتی برای نمایش وجود ندارد.', 'cpp-full')], 404);
    } else {
        wp_send_json_success($data);
    }
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
        wp_send_json_error(['message' => __('کد امنیتی صحیح نیست.', 'cpp-full'), 'code' => 'captcha_error'], 400);
        wp_die();
    }
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $product = $wpdb->get_row($wpdb->prepare("SELECT name, unit, load_location FROM " . CPP_DB_PRODUCTS . " WHERE id=%d", $product_id));
    if (!$product) { wp_send_json_error(['message' => 'محصول یافت نشد.'], 404); wp_die(); }

    $customer_name = sanitize_text_field($_POST['customer_name']);
    $phone = sanitize_text_field($_POST['phone']);
    $qty = sanitize_text_field($_POST['qty']);
    $note = sanitize_textarea_field($_POST['note']);

    if(empty($customer_name) || empty($phone) || empty($qty)){
        wp_send_json_error(['message' => 'فیلدهای الزامی را پر کنید.'], 400);
        wp_die();
    }

    $inserted = $wpdb->insert(CPP_DB_ORDERS, [
        'product_id' => $product_id,
        'product_name' => $product->name,
        'customer_name' => $customer_name,
        'phone' => $phone,
        'qty' => $qty,
        'unit' => $product->unit,
        'load_location' => $product->load_location,
        'note' => $note,
        'status' => 'new_order',
        'created' => current_time('mysql', 1)
    ]);

    if (!$inserted) { wp_send_json_error(['message' => 'خطا در ثبت.'], 500); wp_die(); }

    // ارسال اعلان‌ها (ایمیل و پیامک)
    $placeholders = ['{product_name}' => $product->name, '{customer_name}' => $customer_name, '{phone}' => $phone, '{qty}' => $qty, '{unit}' => $product->unit, '{load_location}' => $product->load_location, '{note}' => $note];
    if (get_option('cpp_enable_email') && class_exists('CPP_Full_Email')) CPP_Full_Email::send_notification($placeholders);
    if (get_option('cpp_sms_service') === 'ippanel' && class_exists('CPP_Full_SMS')) {
        CPP_Full_SMS::send_notification($placeholders);
        if (get_option('cpp_sms_customer_enable')) {
            $p_code = get_option('cpp_sms_customer_pattern_code');
            $api = get_option('cpp_sms_api_key');
            $sender = get_option('cpp_sms_sender');
            if ($p_code && $api && $sender) {
                $vars = []; foreach(['customer_name','product_name','unit','load_location','qty'] as $k) $vars[$k] = isset($placeholders['{'.$k.'}']) ? $placeholders['{'.$k.'}'] : '';
                CPP_Full_SMS::ippanel_send_pattern($api, $sender, $phone, $p_code, $vars);
            }
        }
    }

    wp_send_json_success(['message' => 'درخواست ثبت شد.']);
    wp_die();
}
?>
