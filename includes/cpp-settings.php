<?php
if (!defined('ABSPATH')) exit;

/**
 * مدیریت و ثبت تنظیمات افزونه
 */
add_action('admin_init', 'cpp_register_settings_and_fields');

function cpp_register_settings_and_fields() {
    // === ثبت گروه‌های تنظیمات برای هر تب ===
    register_setting('cpp_general_settings_grp', 'cpp_disable_base_price');
    register_setting('cpp_general_settings_grp', 'cpp_products_per_page');
    register_setting('cpp_general_settings_grp', 'cpp_admin_capability'); // دسترسی کاربران

    register_setting('cpp_shortcode_settings_grp', 'cpp_default_product_image'); // لوگوی پیش‌فرض
    register_setting('cpp_shortcode_settings_grp', 'cpp_grid_with_date_show_image');
    register_setting('cpp_shortcode_settings_grp', 'cpp_grid_no_date_show_image');
    register_setting('cpp_shortcode_settings_grp', 'cpp_grid_with_date_button_color');
    register_setting('cpp_shortcode_settings_grp', 'cpp_grid_no_date_button_color');

    // تنظیمات اعلان ایمیل
    register_setting('cpp_notification_settings_grp', 'cpp_enable_email');
    register_setting('cpp_notification_settings_grp', 'cpp_admin_email');
    register_setting('cpp_notification_settings_grp', 'cpp_email_subject_template');
    register_setting('cpp_notification_settings_grp', 'cpp_email_body_template');

    // تنظیمات اعلان پیامک مدیر (IPPanel Pattern)
    register_setting('cpp_notification_settings_grp', 'cpp_sms_service'); // برای فعال/غیرفعال کردن
    register_setting('cpp_notification_settings_grp', 'cpp_sms_api_key');
    register_setting('cpp_notification_settings_grp', 'cpp_sms_sender');
    register_setting('cpp_notification_settings_grp', 'cpp_admin_phone');
    register_setting('cpp_notification_settings_grp', 'cpp_sms_pattern_code');

    // --- شروع تغییر: ثبت تنظیمات پیامک مشتری ---
    register_setting('cpp_notification_settings_grp', 'cpp_sms_customer_enable');
    register_setting('cpp_notification_settings_grp', 'cpp_sms_customer_pattern_code');
    // --- پایان تغییر ---

    // === تعریف بخش‌ها (Sections) برای هر تب ===
    add_settings_section('cpp_general_section', null, null, 'cpp_general_settings_page');
    add_settings_section('cpp_shortcode_section', null, null, 'cpp_shortcode_settings_page');
    add_settings_section('cpp_email_test_section', __('تست ارسال ایمیل', 'cpp-full'), null, 'cpp_notification_settings_page');

    // === تعریف فیلدها و اتصال آن‌ها به بخش‌ها ===
    // --- تب عمومی ---
    add_settings_field('cpp_disable_base_price', __('غیرفعال کردن قیمت پایه', 'cpp-full'), 'cpp_disable_base_price_callback', 'cpp_general_settings_page', 'cpp_general_section');
    add_settings_field('cpp_products_per_page', __('تعداد محصولات در هر بار بارگذاری', 'cpp-full'), 'cpp_products_per_page_callback', 'cpp_general_settings_page', 'cpp_general_section');
    add_settings_field('cpp_admin_capability', __('سطح دسترسی به افزونه', 'cpp-full'), 'cpp_admin_capability_callback', 'cpp_general_settings_page', 'cpp_general_section');

    // --- تب شورت‌کدها ---
    add_settings_field('cpp_default_product_image', __('لوگوی پیش‌فرض محصولات', 'cpp-full'), 'cpp_default_product_image_callback', 'cpp_shortcode_settings_page', 'cpp_shortcode_section');
    add_settings_field('cpp_grid_with_date_show_image', __('نمایش تصویر (شورت‌کد با تاریخ)', 'cpp-full'), 'cpp_grid_with_date_show_image_callback', 'cpp_shortcode_settings_page', 'cpp_shortcode_section');
    add_settings_field('cpp_grid_no_date_show_image', __('نمایش تصویر (شورت‌کد بدون تاریخ)', 'cpp-full'), 'cpp_grid_no_date_show_image_callback', 'cpp_shortcode_settings_page', 'cpp_shortcode_section');
    add_settings_field('cpp_grid_with_date_button_color', __('رنگ دکمه (شورت‌کد با تاریخ)', 'cpp-full'), 'cpp_grid_with_date_button_color_callback', 'cpp_shortcode_settings_page', 'cpp_shortcode_section');
    add_settings_field('cpp_grid_no_date_button_color', __('رنگ دکمه (شورت‌کد بدون تاریخ)', 'cpp-full'), 'cpp_grid_no_date_button_color_callback', 'cpp_shortcode_settings_page', 'cpp_shortcode_section');

    // --- تب اعلان‌ها ---
    add_settings_field('cpp_email_test', __('ارسال ایمیل آزمایشی', 'cpp-full'), 'cpp_email_test_callback', 'cpp_notification_settings_page', 'cpp_email_test_section');
}


// === توابع Callback برای نمایش HTML هر فیلد ===
// ... (توابع callback دیگر بدون تغییر باقی می‌مانند) ...
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
    $current_capability = get_option('cpp_admin_capability', 'manage_options');
    echo '<select name="cpp_admin_capability">';
    $capabilities = [
        'manage_options' => 'مدیرکل (Administrator)',
        'edit_others_pages' => 'ویرایشگر (Editor)',
        'publish_posts' => 'نویسنده (Author)',
        'edit_posts' => 'مشارکت کننده (Contributor)'
    ];
    foreach ($capabilities as $cap => $name) {
         echo '<option value="' . esc_attr($cap) . '" ' . selected($current_capability, $cap, false) . '>' . esc_html($name) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . __('حداقل نقش کاربری که می‌تواند به منوهای مدیریت این افزونه دسترسی داشته باشد را انتخاب کنید.', 'cpp-full') . '</p>';
}

function cpp_default_product_image_callback() {
    $image_url = get_option('cpp_default_product_image', '');
    echo '<div class="cpp-image-uploader-wrapper">';
    echo '<input type="text" name="cpp_default_product_image" value="' . esc_url($image_url) . '" class="regular-text" id="cpp-default-image-url"/>';
    echo '<button type="button" class="button cpp-upload-btn" data-input-id="cpp-default-image-url">' . __('انتخاب تصویر', 'cpp-full') . '</button>';
    echo '<div class="cpp-image-preview">';
    if ($image_url) {
        echo '<img src="' . esc_url($image_url) . '" style="max-width: 100px; height: auto; margin-top: 10px;">';
    }
    echo '</div></div>';
    echo '<p class="description">' . __('یک تصویر یا لوگو انتخاب کنید تا برای محصولاتی که تصویر ندارند، نمایش داده شود.', 'cpp-full') . '</p>';
}

function cpp_grid_with_date_show_image_callback() {
    echo '<input type="checkbox" name="cpp_grid_with_date_show_image" value="1" ' . checked(1, get_option('cpp_grid_with_date_show_image', 1), false) . ' />';
    echo '<p class="description">' . __('تصویر محصول را کنار نام آن در شورت‌کد <code>[cpp_products_grid_view]</code> نمایش بده.', 'cpp-full') . '</p>';
}

function cpp_grid_no_date_show_image_callback() {
    echo '<input type="checkbox" name="cpp_grid_no_date_show_image" value="1" ' . checked(1, get_option('cpp_grid_no_date_show_image', 1), false) . ' />';
    echo '<p class="description">' . __('تصویر محصول را کنار نام آن در شورت‌کد <code>[cpp_products_grid_view_no_date]</code> نمایش بده.', 'cpp-full') . '</p>';
}

function cpp_grid_with_date_button_color_callback() {
    echo '<input type="text" name="cpp_grid_with_date_button_color" value="' . esc_attr(get_option('cpp_grid_with_date_button_color', '#ffc107')) . '" class="cpp-color-picker" />';
    echo '<p class="description">' . __('رنگ دکمه فعال در شورت‌کد <code>[cpp_products_grid_view]</code>.', 'cpp-full') . '</p>';
}

function cpp_grid_no_date_button_color_callback() {
    echo '<input type="text" name="cpp_grid_no_date_button_color" value="' . esc_attr(get_option('cpp_grid_no_date_button_color', '#0073aa')) . '" class="cpp-color-picker" />';
    echo '<p class="description">' . __('رنگ دکمه فعال در شورت‌کد <code>[cpp_products_grid_view_no_date]</code>.', 'cpp-full') . '</p>';
}

function cpp_email_test_callback() {
    echo '<button type="button" class="button button-secondary" id="cpp-test-email-btn">' . __('ارسال ایمیل تست', 'cpp-full') . '</button>';
    echo '<p class="description">' . __('یک ایمیل آزمایشی به ایمیل مدیر ارسال می‌کند.', 'cpp-full') . '</p>';
    echo '<textarea id="cpp-email-log" readonly style="width: 100%; height: 150px; margin-top: 10px; background-color: #f0f0f0; font-family: monospace; direction: ltr; text-align: left;"></textarea>';
}
