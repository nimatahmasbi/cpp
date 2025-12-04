<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$categories = CPP_Core::get_all_categories(); 
?>

<div class="wrap">
    <h1><?php _e('مدیریت دسته‌بندی‌ها', 'cpp-full'); ?></h1>

    <?php 
    if (isset($_GET['cpp_message'])) {
        $message_key = sanitize_key($_GET['cpp_message']);
        $messages = [
            'category_added' => [ 'type' => 'success', 'text' => __('دسته‌بندی جدید با موفقیت اضافه شد.', 'cpp-full') ],
            'category_add_failed' => [ 'type' => 'error', 'text' => __('خطا در اضافه کردن دسته‌بندی.', 'cpp-full') ],
            'category_deleted' => [ 'type' => 'success', 'text' => __('دسته‌بندی با موفقیت حذف شد.', 'cpp-full') ],
            'category_delete_failed' => [ 'type' => 'error', 'text' => __('خطا در حذف دسته‌بندی.', 'cpp-full') ],
        ];
        if (isset($messages[$message_key])) {
            echo '<div class="notice notice-' . $messages[$message_key]['type'] . ' is-dismissible"><p>' . $messages[$message_key]['text'] . '</p></div>';
        }
    }
    ?>
    
    <div class="notice notice-info">
        <p><?php _e('برای ویرایش سریع نام، روی سلول مورد نظر **دوبار کلیک** کنید یا از دکمه **ویرایش** برای باز کردن فرم کامل استفاده نمایید.', 'cpp-full'); ?></p>
    </div>

    <div class="cpp-accordion-wrap">
        <h2 class="cpp-accordion-header"><?php _e('➕ افزودن دسته‌بندی جدید', 'cpp-full'); ?></h2>
        <div class="cpp-accordion-content" style="display: none;">
            <form method="post" id="cpp-add-category-form">
                <?php wp_nonce_field('cpp_add_cat_action', 'cpp_add_cat_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><?php _e('نام دسته‌بندی', 'cpp-full'); ?></th>
                        <td><input type="text" name="name" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><?php _e('اسلاگ (Slug)', 'cpp-full'); ?></th>
                        <td><input type="text" name="slug" class="regular-text" placeholder="<?php _e('اختیاری', 'cpp-full'); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php _e('عکس دسته‌بندی', 'cpp-full'); ?></th>
                        <td>
                            <input type="text" name="image_url" id="category_image_url" class="regular-text">
                            <button type="button" class="button cpp-upload-btn"><?php _e('انتخاب تصویر', 'cpp-full'); ?></button>
                            <div class="cpp-image-preview"></div>
                        </td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" name="cpp_add_category" id="submit" class="button button-primary" value="<?php _e('افزودن دسته‌بندی', 'cpp-full'); ?>"></p>
            </form>
        </div>
    </div>

    <h2 class="title"><?php _e('لیست دسته‌بندی‌ها', 'cpp-full'); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col"><?php _e('عکس', 'cpp-full'); ?></th>
                <th scope="col"><?php _e('نام (دبل کلیک)', 'cpp-full'); ?></th>
                <th scope="col"><?php _e('اسلاگ (دبل کلیک)', 'cpp-full'); ?></th>
                <th scope="col"><?php _e('تاریخ ایجاد', 'cpp-full'); ?></th> 
                <th scope="col"><?php _e('عملیات', 'cpp-full'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ($categories) : foreach ($categories as $cat) : ?>
            <tr>
                <td><?php echo $cat->id; ?></td>
                <td><img src="<?php echo esc_url($cat->image_url); ?>" style="max-width: 50px; height: auto;"></td>
                <td class="cpp-quick-edit" data-id="<?php echo $cat->id; ?>" data-field="name" data-table-type="categories"><?php echo esc_html($cat->name); ?></td>
                <td class="cpp-quick-edit" data-id="<?php echo $cat->id; ?>" data-field="slug" data-table-type="categories"><?php echo esc_html($cat->slug); ?></td>
                <td><?php echo date_i18n('Y/m/d H:i:s', strtotime($cat->created)); ?></td>
                <td>
                    <button type="button" class="button button-primary button-small cpp-edit-cat-button" data-cat-id="<?php echo $cat->id; ?>"><?php _e('ویرایش', 'cpp-full'); ?></button>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=custom-prices-categories&action=delete&id=' . $cat->id), 'cpp_delete_cat_' . $cat->id); ?>" class="button button-small" onclick="return confirm('<?php _e('آیا مطمئنید؟', 'cpp-full'); ?>')"><?php _e('حذف', 'cpp-full'); ?></a>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="6"><?php _e('دسته‌بندی یافت نشد.', 'cpp-full'); ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
