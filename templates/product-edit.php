<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$product_id) {
    wp_die(__('شناسه محصول نامعتبر است.', 'cpp-full'));
}

// واکشی محصول فعلی
$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . CPP_DB_PRODUCTS . " WHERE id = %d", $product_id));
if (!$product) {
    wp_die(__('محصول مورد نظر یافت نشد.', 'cpp-full'));
}

// دریافت دسته‌بندی‌ها
$categories = CPP_Core::get_all_categories(); 
$default_image_url = get_site_icon_url(100) ? get_site_icon_url(100) : CPP_ASSETS_URL . 'images/default-product.png';
$img_src = esc_url($product->image_url) ? esc_url($product->image_url) : $default_image_url;

// --- شروع تغییر: واکشی تنظیمات قیمت پایه ---
$disable_base_price = get_option('cpp_disable_base_price', 0);
// --- پایان تغییر ---
?>

<div class="wrap">
    <h1><?php _e('ویرایش محصول: ', 'cpp-full'); echo esc_html($product->name); ?></h1>

    <?php 
    // نمایش پیام‌های پس از عملیات ذخیره
    if (isset($_GET['cpp_message']) && $_GET['cpp_message'] == 'product_updated') {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('محصول با موفقیت به‌روزرسانی شد.', 'cpp-full') . '</p></div>';
    }
    if (isset($_GET['cpp_message']) && $_GET['cpp_message'] == 'product_update_failed') {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('خطا در به‌روزرسانی محصول. لطفاً دوباره امتحان کنید.', 'cpp-full') . '</p></div>';
    }
    ?>

    <p><a href="<?php echo admin_url('admin.php?page=custom-prices-products'); ?>" class="button button-secondary"><?php _e('بازگشت به لیست محصولات', 'cpp-full'); ?></a></p>

    <form method="post" id="cpp-edit-product-form">
        <?php wp_nonce_field('cpp_edit_product_action', 'cpp_edit_product_nonce'); ?>
        <input type="hidden" name="product_id" value="<?php echo $product->id; ?>">
        <input type="hidden" name="cpp_edit_product" value="1">

        <table class="form-table">
            <tr>
                <th><?php _e('نام محصول', 'cpp-full'); ?></th>
                <td><input type="text" name="name" required class="regular-text" value="<?php echo esc_attr($product->name); ?>"></td>
                <th><?php _e('دسته‌بندی', 'cpp-full'); ?></th>
                <td>
                    <select name="cat_id" required>
                        <option value=""><?php _e('انتخاب کنید', 'cpp-full'); ?></option>
                        <?php foreach ($categories as $cat) : ?>
                            <option value="<?php echo $cat->id; ?>" <?php selected($product->cat_id, $cat->id); ?>><?php echo $cat->name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php _e('نوع', 'cpp-full'); ?></th>
                <td><input type="text" name="product_type" class="regular-text" value="<?php echo esc_attr($product->product_type); ?>"></td>
                <th><?php _e('واحد', 'cpp-full'); ?></th>
                <td><input type="text" name="unit" class="regular-text" value="<?php echo esc_attr($product->unit); ?>"></td>
            </tr>
            <tr>
                <th><?php _e('محل بارگیری', 'cpp-full'); ?></th>
                <td><input type="text" name="load_location" class="regular-text" value="<?php echo esc_attr($product->load_location); ?>"></td>
                <th><?php _e('وضعیت', 'cpp-full'); ?></th>
                <td>
                    <select name="is_active">
                        <option value="1" <?php selected($product->is_active, 1); ?>><?php _e('فعال', 'cpp-full'); ?></option>
                        <option value="0" <?php selected($product->is_active, 0); ?>><?php _e('غیرفعال', 'cpp-full'); ?></option>
                    </select>
                </td>
            </tr>

            <?php if (!$disable_base_price) : ?>
            <tr>
                <th><?php _e('قیمت پایه/استاندارد', 'cpp-full'); ?></th>
                <td><input type="text" name="price" required class="regular-text" value="<?php echo esc_attr($product->price); ?>"></td>
                <th><?php _e('بازه قیمت (حداقل - حداکثر)', 'cpp-full'); ?></th>
                <td>
                    <input type="text" name="min_price" class="small-text" value="<?php echo esc_attr($product->min_price); ?>">
                    -
                    <input type="text" name="max_price" class="small-text" value="<?php echo esc_attr($product->max_price); ?>">
                </td>
            </tr>
            <?php else: ?>
            <input type="hidden" name="price" value="<?php echo esc_attr($product->price); ?>">
             <tr>
                <th><?php _e('بازه قیمت (حداقل - حداکثر)', 'cpp-full'); ?></th>
                <td colspan="3">
                    <input type="text" name="min_price" class="small-text" value="<?php echo esc_attr($product->min_price); ?>">
                    -
                    <input type="text" name="max_price" class="small-text" value="<?php echo esc_attr($product->max_price); ?>">
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><?php _e('عکس محصول', 'cpp-full'); ?></th>
                <td colspan="3">
                    <input type="text" name="image_url" id="product_image_url" class="regular-text" value="<?php echo esc_url($product->image_url); ?>">
                    <button type="button" class="button cpp-upload-btn"><?php _e('انتخاب تصویر', 'cpp-full'); ?></button>
                    <div class="cpp-image-preview">
                        <img src="<?php echo $img_src; ?>" style="max-width: 100px; height: auto; margin-top: 10px;">
                    </div>
                </td>
            </tr>
            <tr>
                <th><?php _e('توضیحات', 'cpp-full'); ?></th>
                <td colspan="3"><textarea name="description" rows="5" class="large-text"><?php echo esc_textarea($product->description); ?></textarea></td>
            </tr>
        </table>
        <p class="submit"><input type="submit" name="cpp_update_product" id="submit" class="button button-primary" value="<?php _e('ذخیره تغییرات', 'cpp-full'); ?>"></p>
    </form>
</div>
