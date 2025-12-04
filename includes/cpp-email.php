<?php
if (!defined('ABSPATH')) exit;

class CPP_Full_Email {
    /**
     * ارسال ایمیل اعلان سفارش جدید به مدیر با جایگزینی متغیرها در قالب‌ها
     * @param array $placeholders شامل: {product_name}, {customer_name}, {phone}, {qty}, {note}
     */
    public static function send_notification($placeholders) {
        if (!get_option('cpp_enable_email')) return false;
        
        $admin_email = get_option('cpp_admin_email') ? get_option('cpp_admin_email') : get_option('admin_email');
        if (!$admin_email) return false;
        
        $default_body = "محصول: {product_name}\nنام مشتری: {customer_name}\nشماره: {phone}\nتوضیحات: {note}";
        $body_template    = get_option('cpp_email_body_template', $default_body);
        $subject_template = get_option('cpp_email_subject_template', "سفارش جدید: {product_name}");

        // --- جایگزینی متغیرها ---
        $keys = array_keys($placeholders);
        $values = array_values($placeholders);

        $final_subject = str_replace($keys, $values, $subject_template);
        $final_message = str_replace($keys, $values, $body_template);
        
        // اطمینان از اینکه ایمیل به صورت HTML ارسال می‌شود
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // برای اینکه قالب‌های HTML که از ویرایشگر وردپرس می‌آیند درست کار کنند
        // wpautop پاراگراف‌های خودکار اضافه می‌کند
        return wp_mail($admin_email, $final_subject, wpautop($final_message), $headers);
    }
}
?>
