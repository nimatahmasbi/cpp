<?php
if (!defined('ABSPATH')) exit;

add_action('admin_init', 'cpp_register_settings_and_fields');

function cpp_register_settings_and_fields() {
    register_setting('cpp_general_settings_grp', 'cpp_disable_base_price');
    register_setting('cpp_general_settings_grp', 'cpp_products_per_page');
    register_setting('cpp_general_settings_grp', 'cpp_admin_capability', array('type' => 'array', 'sanitize_callback' => 'cpp_sanitize_roles'));

    register_setting('cpp_shortcode_settings_grp', 'cpp_default_product_image');
    register_setting('cpp_shortcode_settings_grp', 'cpp_grid_with_date_show_image');
    register_setting('cpp_shortcode_settings_grp', 'cpp_grid_no_date_show_image');
    register_setting('cpp_shortcode_settings_grp', 'cpp_grid_with_date_button_color');
    register_setting('cpp_shortcode_settings_grp', 'cpp_grid_no_date_button_color');

    register_setting('cpp_notification_settings_grp', 'cpp_enable_email');
    register_setting('cpp_notification_settings_grp', 'cpp_admin_email');
    register_setting('cpp_notification_settings_grp', 'cpp_email_subject_template');
    register_setting('cpp_notification_settings_grp', 'cpp_email_body_template');

    register_setting('cpp_notification_settings_grp', 'cpp_sms_service');
    register_setting('cpp_notification_settings_grp', 'cpp_sms_api_key');
    register_setting('cpp_notification_settings_grp', 'cpp_sms_sender');
    register_setting('cpp_notification_settings_grp', 'cpp_admin_phone');
    register_setting('cpp_notification_settings_grp', 'cpp_sms_pattern_code');
    register_setting('cpp_notification_settings_grp', 'cpp_sms_customer_enable');
    register_setting('cpp_notification_settings_grp', 'cpp_sms_customer_pattern_code');

    add_settings_section('cpp_general_section', null, null, 'cpp_general_settings_page');
    add_settings_section('cpp_shortcode_section', null, null, 'cpp_shortcode_settings_page');
    add_settings_section('cpp_email_test_section', __('تست ارسال ایمیل', 'cpp-full'), null, 'cpp_notification_settings_page');

    add_settings_field('cpp_disable_base_price', __('غیرفعال کردن قیمت پایه', 'cpp-full'), 'cpp_disable_base_price_callback', 'cpp_general_settings_page', 'cpp_general_section');
    add_settings_field('cpp_products_per_page', __('تعداد محصولات در هر بار بارگذاری', 'cpp-full'), 'cpp_products_per_page_callback', 'cpp_general_settings_page', 'cpp_general_section');
    add_settings_field('cpp_admin_capability', __('نقش‌های مجاز دسترسی', 'cpp-full'), 'cpp_admin_capability_callback', 'cpp_general_settings_page', 'cpp_general_section');

    add_settings_field('cpp_default_product_image', __('لوگوی پیش‌فرض محصولات', 'cpp-full'), 'cpp_default_product_image_callback', 'cpp_shortcode_settings_page', 'cpp_shortcode_section');
    add_settings_field('cpp_grid_with_date_show_image', __('نمایش تصویر (شورت‌کد با تاریخ)', 'cpp-full'), 'cpp_grid_with_date_show_image_callback', 'cpp_shortcode_settings_page', 'cpp_shortcode_section');
    add_settings_field('cpp_grid_no_date_show_image', __('نمایش تصویر (شورت‌کد بدون تاریخ)', 'cpp-full'), 'cpp_grid_no_date_show_image_callback', 'cpp_shortcode_settings_page', 'cpp_shortcode_section');
    add_settings_field('cpp_grid_with_date_button_color', __('رنگ دکمه (شورت‌کد با تاریخ)', 'cpp-full'), 'cpp_grid_with_date_button_color_callback', 'cpp_shortcode_settings_page', 'cpp_shortcode_section');
    add_settings_field('cpp_grid_no_date_button_color', __('رنگ دکمه (شورت‌کد بدون تاریخ)', 'cpp-full'), 'cpp_grid_no_date_button_color_callback', 'cpp_shortcode_settings_page', 'cpp_shortcode_section');

    add_settings_field('cpp_email_test', __('ارسال ایمیل آزمایشی', 'cpp-full'), 'cpp_email_test_callback', 'cpp_notification_settings_page', 'cpp_email_test_section');
}

function cpp_sanitize_roles($input) {
    if (is_array($input)) return array_map('sanitize_text_field', $input);
    return [];
}

function cpp_disable_base_price_callback() {
    echo '<input type="checkbox" name="cpp_disable_base_price" value="1" ' . checked(1, get_option('cpp_disable_base_price'), false) . ' />';
    echo '<p class="description">' . __('با فعال کردن این گزینه، فیلد "قیمت پایه" در تمام بخش‌های افزونه مخفی می‌شود.', 'cpp-full') . '</p>';
}

function cpp_products_per_page_callback() {
    echo '<input type="number" name="cpp_products_per_page" value="' . esc_attr(get_option('cpp_products_per_page', 5)) . '" class="small-text" min="1" />';
    echo '<p class="description">' . __('این تعداد محصول در شورت‌کد گرید در ابتدا نمایش داده می‌شود.', 'cpp-full') . '</p>';
}

function cpp_admin_capability_callback() {
    $roles = get_editable_roles();
    $saved_roles = get_option('cpp_admin_capability');
    if (empty($saved_roles)) $saved_roles = ['administrator'];
    elseif (is_string($saved_roles)) $saved_roles = ['administrator']; 

    echo '<fieldset><legend class="screen-reader-text"><span>نقش‌های مجاز</span></legend>';
    foreach ($roles as $role_slug => $role_info) {
        $checked = in_array($role_slug, $saved_roles) ? 'checked="checked"' : '';
        echo '<label style="display:inline-block; margin-left: 15px; margin-bottom: 5px;">';
        echo '<input type="checkbox" name="cpp_admin_capability[]" value="' . esc_attr($role_slug) . '" ' . $checked . ' /> ';
        echo esc_html($role_info['name']);
        echo '</label><br>';
    }
    echo '</fieldset>';
    echo '<p class="description">' . __('نقش‌هایی که اجازه دسترسی به منوهای مدیریت این افزونه را دارند انتخاب کنید.', 'cpp-full') . '</p>';
}

function cpp_default_product_image_callback() {
    $image_url = get_option('cpp_default_product_image', '');
    echo '<div class="cpp-image-uploader-wrapper">';
    echo '<input type="text" name="cpp_default_product_image" value="' . esc_url($image_url) . '" class="regular-text" id="cpp-default-image-url"/>';
    echo '<button type="button" class="button cpp-upload-btn" data-input-id="cpp-default-image-url">' . __('انتخاب تصویر', 'cpp-full') . '</button>';
    echo '<div class="cpp-image-preview">';
    if ($image_url) echo '<img src="' . esc_url($image_url) . '" style="max-width: 100px; height: auto; margin-top: 10px;">';
    echo '</div></div>';
}

function cpp_grid_with_date_show_image_callback() {
    echo '<input type="checkbox" name="cpp_grid_with_date_show_image" value="1" ' . checked(1, get_option('cpp_grid_with_date_show_image', 1), false) . ' />';
}

function cpp_grid_no_date_show_image_callback() {
    echo '<input type="checkbox" name="cpp_grid_no_date_show_image" value="1" ' . checked(1, get_option('cpp_grid_no_date_show_image', 1), false) . ' />';
}

function cpp_grid_with_date_button_color_callback() {
    echo '<input type="text" name="cpp_grid_with_date_button_color" value="' . esc_attr(get_option('cpp_grid_with_date_button_color', '#ffc107')) . '" class="cpp-color-picker" />';
}

function cpp_grid_no_date_button_color_callback() {
    echo '<input type="text" name="cpp_grid_no_date_button_color" value="' . esc_attr(get_option('cpp_grid_no_date_button_color', '#0073aa')) . '" class="cpp-color-picker" />';
}

function cpp_email_test_callback() {
    echo '<button type="button" class="button button-secondary" id="cpp-test-email-btn">' . __('ارسال ایمیل تست', 'cpp-full') . '</button>';
    echo '<p class="description">' . __('یک ایمیل آزمایشی به ایمیل مدیر ارسال می‌کند.', 'cpp-full') . '</p>';
    echo '<textarea id="cpp-email-log" readonly style="width: 100%; height: 150px; margin-top: 10px; background-color: #f0f0f0; font-family: monospace; direction: ltr; text-align: left;"></textarea>';
}
?>
