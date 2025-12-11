<?php
if (!defined('ABSPATH')) exit;

class CPP_Core {

    public static function init_session() {
        if (!session_id() && !headers_sent()) {
            try { @session_start(); } catch (Exception $e) {}
        }
    }

    public static function has_access() {
        $allowed = get_option('cpp_admin_capability', ['administrator']);
        if(is_string($allowed)) $allowed = ['administrator'];
        $u = wp_get_current_user();
        if(!$u) return false;
        foreach($u->roles as $r) if(in_array($r, $allowed)) return true;
        return false;
    }

    public static function create_db_tables() {
        global $wpdb; require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $c = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE ".CPP_DB_CATEGORIES." (id mediumint(9) NOT NULL AUTO_INCREMENT, name varchar(200) NOT NULL, slug varchar(200) NOT NULL, image_url varchar(255) DEFAULT '', created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY (id), UNIQUE KEY slug (slug)) $c;");
        dbDelta("CREATE TABLE ".CPP_DB_PRODUCTS." (id mediumint(9) NOT NULL AUTO_INCREMENT, cat_id mediumint(9) NOT NULL, name varchar(200) NOT NULL, price varchar(50) DEFAULT '', min_price varchar(50) DEFAULT '', max_price varchar(50) DEFAULT '', product_type varchar(100) DEFAULT '', unit varchar(50) DEFAULT '', load_location varchar(200) DEFAULT '', is_active tinyint(1) DEFAULT 1, description text, image_url varchar(255) DEFAULT '', last_updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY (id), KEY cat_id (cat_id)) $c;");
        dbDelta("CREATE TABLE ".CPP_DB_ORDERS." (id mediumint(9) NOT NULL AUTO_INCREMENT, product_id mediumint(9) NOT NULL, product_name varchar(200) NOT NULL, customer_name varchar(200) NOT NULL, phone varchar(50) NOT NULL, qty varchar(50) NOT NULL, unit varchar(50) DEFAULT '', load_location varchar(200) DEFAULT '', note text, admin_note text, status varchar(50) DEFAULT 'new_order', created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY (id), KEY product_id (product_id)) $c;");
        dbDelta("CREATE TABLE ".CPP_DB_PRICE_HISTORY." (id bigint(20) NOT NULL AUTO_INCREMENT, product_id mediumint(9) NOT NULL, price varchar(50) DEFAULT NULL, min_price varchar(50) DEFAULT NULL, max_price varchar(50) DEFAULT NULL, change_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY (id), KEY product_id (product_id)) $c;");
    }

    public static function save_price_history($pid, $val, $field='price') {
        global $wpdb; $pid = intval($pid); if(!$pid) return false;
        $cur = $wpdb->get_row($wpdb->prepare("SELECT price, min_price, max_price FROM ".CPP_DB_PRODUCTS." WHERE id=%d", $pid));
        if(!$cur) return false;
        
        // اگر فیلدی تغییر نکرده، مقدار قبلی را نگهدار تا گراف قطع نشود
        $p = ($field==='price') ? $val : $cur->price;
        $min = ($field==='min_price') ? $val : $cur->min_price;
        $max = ($field==='max_price') ? $val : $cur->max_price;

        $wpdb->insert(CPP_DB_PRICE_HISTORY, ['product_id'=>$pid, 'change_time'=>current_time('mysql',1), 'price'=>$p, 'min_price'=>$min, 'max_price'=>$max]);
        $wpdb->update(CPP_DB_PRODUCTS, ['last_updated_at'=>current_time('mysql',1)], ['id'=>$pid]);
    }

    private static function clean($v) {
        if($v===null||$v==='') return null;
        $v = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], $v);
        $v = preg_replace('/[^0-9.]/', '', $v);
        return ($v==='') ? null : (float)$v;
    }

    public static function get_chart_data($pid) {
        global $wpdb; $pid = intval($pid);
        $L=[]; $P=[]; $Min=[]; $Max=[];
        
        // دریافت کل تاریخچه بدون محدودیت زمانی (فیلتر در JS انجام می‌شود)
        $hist = $wpdb->get_results($wpdb->prepare("SELECT price, min_price, max_price, change_time FROM ".CPP_DB_PRICE_HISTORY." WHERE product_id=%d ORDER BY change_time ASC", $pid));
        
        // اضافه کردن وضعیت فعلی به عنوان آخرین نقطه
        $cur = $wpdb->get_row($wpdb->prepare("SELECT price, min_price, max_price, last_updated_at FROM ".CPP_DB_PRODUCTS." WHERE id=%d", $pid));
        if($cur) {
            $last = !empty($hist) ? end($hist)->change_time : '';
            // اگر تاریخچه خالی است یا رکورد جدیدتری داریم
            if(empty($hist) || $cur->last_updated_at > $last) {
                $d = new stdClass(); $d->change_time = $cur->last_updated_at?:current_time('mysql',1);
                $d->price = $cur->price; $d->min_price = $cur->min_price; $d->max_price = $cur->max_price;
                $hist[] = $d;
            }
        }

        $no_base = get_option('cpp_disable_base_price', 0);
        foreach($hist as $r) {
            $ts = strtotime(get_date_from_gmt($r->change_time)); if(!$ts) $ts = current_time('timestamp');
            $L[] = date_i18n('Y/m/d H:i', $ts);
            
            $b = self::clean($r->price);
            $mn = self::clean($r->min_price);
            $mx = self::clean($r->max_price);

            // پر کردن حفره‌های داده (اگر حداقل/حداکثر خالی بود، برابر پایه قرار بده)
            if ($mn === null && $b !== null) $mn = $b;
            if ($mx === null && $b !== null) $mx = $b;
            if ($mn === null && $mx !== null) $mn = $mx; 

            // محاسبه میانگین اگر قیمت پایه غیرفعال است
            if($no_base) {
                if($mn!==null && $mx!==null) $P[] = ($mn+$mx)/2;
                elseif($mn!==null) $P[] = $mn;
                elseif($mx!==null) $P[] = $mx;
                else $P[] = null;
            } else $P[] = $b;
            
            $Min[] = $mn; $Max[] = $mx;
        }
        return ['labels'=>$L, 'prices'=>$P, 'min_prices'=>$Min, 'max_prices'=>$Max];
    }
    
    public static function get_all_categories() { global $wpdb; return $wpdb->get_results("SELECT * FROM ".CPP_DB_CATEGORIES." ORDER BY id ASC"); }
    public static function get_all_orders() { global $wpdb; return $wpdb->get_results("SELECT * FROM ".CPP_DB_ORDERS." ORDER BY id ASC"); }
}
add_action('init', ['CPP_Core', 'init_session'], 1);

add_action('wp_ajax_cpp_get_captcha', 'cpp_ajax_get_captcha');
add_action('wp_ajax_nopriv_cpp_get_captcha', 'cpp_ajax_get_captcha');
function cpp_ajax_get_captcha() { check_ajax_referer('cpp_front_nonce','nonce'); CPP_Core::init_session(); $c=rand(1000,9999); $_SESSION['cpp_captcha_code']=(string)$c; wp_send_json_success(['code'=>(string)$c]); }

add_action('wp_ajax_cpp_get_chart_data', 'cpp_ajax_get_chart_data');
add_action('wp_ajax_nopriv_cpp_get_chart_data', 'cpp_ajax_get_chart_data');
function cpp_ajax_get_chart_data() {
    $ok=false; if(isset($_REQUEST['security']) && wp_verify_nonce($_REQUEST['security'],'cpp_admin_nonce')) $ok=true;
    elseif(isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'],'cpp_front_nonce')) $ok=true;
    if(!$ok) wp_send_json_error(['message'=>'عدم دسترسی'], 403);
    
    $d = CPP_Core::get_chart_data(intval($_GET['product_id']));
    $has=false; foreach(['prices','min_prices','max_prices'] as $k) if(count(array_filter($d[$k], function($v){return $v!==null;}))>0) $has=true;
    
    if(!$has) wp_send_json_error(['message'=>'داده‌ای نیست'], 404); else wp_send_json_success($d);
}

add_action('wp_ajax_cpp_submit_order', 'cpp_submit_order');
add_action('wp_ajax_nopriv_cpp_submit_order', 'cpp_submit_order');
function cpp_submit_order() {
    check_ajax_referer('cpp_front_nonce','nonce'); CPP_Core::init_session();
    if(empty($_POST['captcha_input']) || $_POST['captcha_input'] !== $_SESSION['cpp_captcha_code']) wp_send_json_error(['message'=>'کد امنیتی اشتباه است', 'code'=>'captcha_error'], 400);
    global $wpdb; $pid = intval($_POST['product_id']);
    $p = $wpdb->get_row($wpdb->prepare("SELECT name, unit, load_location FROM ".CPP_DB_PRODUCTS." WHERE id=%d", $pid));
    if(!$p) wp_send_json_error(['message'=>'محصول یافت نشد'], 404);
    
    $wpdb->insert(CPP_DB_ORDERS, [
        'product_id'=>$pid, 'product_name'=>$p->name, 'customer_name'=>sanitize_text_field($_POST['customer_name']),
        'phone'=>sanitize_text_field($_POST['phone']), 'qty'=>sanitize_text_field($_POST['qty']), 'unit'=>$p->unit,
        'load_location'=>$p->load_location, 'note'=>sanitize_textarea_field($_POST['note']), 'status'=>'new_order', 'created'=>current_time('mysql',1)
    ]);
    
    $vars = ['{product_name}'=>$p->name, '{customer_name}'=>$_POST['customer_name'], '{phone}'=>$_POST['phone'], '{qty}'=>$_POST['qty'], '{unit}'=>$p->unit, '{load_location}'=>$p->load_location, '{note}'=>$_POST['note']];
    if(get_option('cpp_enable_email') && class_exists('CPP_Full_Email')) CPP_Full_Email::send_notification($vars);
    if(get_option('cpp_sms_service')==='ippanel' && class_exists('CPP_Full_SMS')) {
        CPP_Full_SMS::send_notification($vars);
        if(get_option('cpp_sms_customer_enable')) {
            $c_vars=[]; foreach(['customer_name','product_name','unit','load_location','qty'] as $k) $c_vars[$k]=$vars['{'.$k.'}'];
            CPP_Full_SMS::ippanel_send_pattern(get_option('cpp_sms_api_key'), get_option('cpp_sms_sender'), $_POST['phone'], get_option('cpp_sms_customer_pattern_code'), $c_vars);
        }
    }
    wp_send_json_success(['message'=>'درخواست ثبت شد.']);
}
?>
