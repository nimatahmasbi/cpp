<?php
if (!defined('ABSPATH')) exit;

$disable_base_price = get_option('cpp_disable_base_price', 0);
$cart_icon_url = CPP_ASSETS_URL . 'images/cart-icon.png';
$chart_icon_url = CPP_ASSETS_URL . 'images/chart-icon.png';
$show_image = get_option('cpp_grid_with_date_show_image', 1);
$default_image = get_option('cpp_default_product_image', CPP_ASSETS_URL . 'images/default-product.png');

// دریافت لوگوی سایت (Site Icon) برای پس‌زمینه
$site_logo_url = get_site_icon_url(512); 
if (!$site_logo_url) $site_logo_url = $default_image;

// منطق فیلتر دسته‌بندی‌ها برای نمایش در تب‌ها
$show_filters = true;
if (!empty($cat_ids)) {
    // اگر دسته‌های خاصی در شورت‌کد خواسته شده، فقط همان‌ها را در تب‌ها نگه دار
    $categories = array_filter($categories, function($cat) use ($cat_ids) {
        return in_array($cat->id, $cat_ids);
    });
    // اگر فقط یک دسته انتخاب شده، کل نوار فیلتر را مخفی کن
    if (count($cat_ids) === 1) {
        $show_filters = false;
    }
}
?>

<div class="cpp-grid-view-wrapper with-date-shortcode" style="--cpp-bg-logo: url('<?php echo esc_url($site_logo_url); ?>');">
    
    <?php if ($show_filters && !empty($categories)) : ?>
        <div class="cpp-grid-view-filters">
            <?php if(empty($cat_ids)): // فقط اگر فیلتری نیست گزینه "همه" را نشان بده ?>
                <a href="#" class="filter-btn active" data-cat-id="all"><?php _e('همه دسته‌ها', 'cpp-full'); ?></a>
            <?php endif; ?>
            
            <?php 
            $first = true;
            foreach ($categories as $cat) : 
                // اگر دسته‌های خاصی انتخاب شده، اولین تب را فعال کن
                $active_class = (!empty($cat_ids) && $first) ? 'active' : '';
                if(!empty($cat_ids)) $first = false;
            ?>
                <a href="#" class="filter-btn <?php echo $active_class; ?>" data-cat-id="<?php echo esc_attr($cat->id); ?>"><?php echo esc_html($cat->name); ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <table class="cpp-grid-view-table">
        <thead>
            <tr>
                <th><?php _e('محصول', 'cpp-full'); ?></th>
                <th><?php _e('نوع', 'cpp-full'); ?></th>
                <th><?php _e('واحد', 'cpp-full'); ?></th>
                <th><?php _e('محل بارگیری', 'cpp-full'); ?></th>
                <th><?php _e('آخرین بروزرسانی', 'cpp-full'); ?></th>
                <?php if (!$disable_base_price) : ?><th><?php _e('قیمت پایه', 'cpp-full'); ?></th><?php endif; ?>
                <th><?php _e('بازه قیمت', 'cpp-full'); ?></th>
                <th><?php _e('عملیات', 'cpp-full'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($products)) : foreach ($products as $product) :
                $product_image_url = !empty($product->image_url) ? esc_url($product->image_url) : esc_url($default_image);
                $min_clean = str_replace(',', '', $product->min_price);
                $max_clean = str_replace(',', '', $product->max_price);
                $show_single = ($min_clean == $max_clean && is_numeric($min_clean));
            ?>
                <tr class="product-row" data-cat-id="<?php echo esc_attr($product->cat_id); ?>">
                    <td class="col-product-name" data-colname="<?php esc_attr_e('محصول', 'cpp-full'); ?>">
                        <?php if ($show_image) : ?>
                            <img src="<?php echo $product_image_url; ?>" alt="<?php echo esc_attr($product->name); ?>">
                        <?php endif; ?>
                        <span><?php echo esc_html($product->name); ?></span>
                    </td>
                    <td data-colname="<?php esc_attr_e('نوع', 'cpp-full'); ?>"><?php echo esc_html($product->product_type); ?></td>
                    <td data-colname="<?php esc_attr_e('واحد', 'cpp-full'); ?>"><?php echo esc_html($product->unit); ?></td>
                    <td data-colname="<?php esc_attr_e('محل بارگیری', 'cpp-full'); ?>"><?php echo esc_html($product->load_location); ?></td>
                    <td data-colname="<?php esc_attr_e('آخرین بروزرسانی', 'cpp-full'); ?>"><?php echo esc_html(date_i18n('Y/m/d H:i', strtotime(get_date_from_gmt($product->last_updated_at)))); ?></td>

                    <?php if (!$disable_base_price) : ?>
                    <td class="col-price" data-colname="<?php esc_attr_e('قیمت پایه', 'cpp-full'); ?>">
                        <?php
                            $price_cleaned = str_replace(',', '', $product->price);
                            echo is_numeric($price_cleaned) ? esc_html(number_format_i18n((float)$price_cleaned)) : esc_html($product->price);
                         ?>
                    </td>
                    <?php endif; ?>

                     <td class="col-price-range" data-colname="<?php esc_attr_e('بازه قیمت', 'cpp-full'); ?>">
                        <?php if (!empty($product->min_price) && !empty($product->max_price)) : ?>
                            <?php if ($show_single) : ?>
                                <?php echo esc_html(number_format_i18n((float)$min_clean)); ?>
                            <?php else : ?>
                                <?php echo is_numeric($min_clean) ? esc_html(number_format_i18n((float)$min_clean)) : esc_html($product->min_price); ?> - <?php echo is_numeric($max_clean) ? esc_html(number_format_i18n((float)$max_clean)) : esc_html($product->max_price); ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="cpp-price-not-set"><?php _e('تماس بگیرید', 'cpp-full'); ?></span>
                        <?php endif; ?>
                    </td>

                    <td class="col-actions" data-colname="<?php esc_attr_e('عملیات', 'cpp-full'); ?>">
                        <button class="cpp-icon-btn cpp-order-btn"
                                data-product-id="<?php echo esc_attr($product->id); ?>"
                                data-product-name="<?php echo esc_attr($product->name); ?>"
                                data-product-unit="<?php echo esc_attr($product->unit); ?>"
                                data-product-location="<?php echo esc_attr($product->load_location); ?>"
                                title="<?php esc_attr_e('خرید', 'cpp-full'); ?>">
                            <img src="<?php echo esc_url($cart_icon_url); ?>" alt="<?php esc_attr_e('خرید', 'cpp-full'); ?>">
                        </button>
                        <button class="cpp-icon-btn cpp-chart-btn" data-product-id="<?php echo esc_attr($product->id); ?>" title="<?php esc_attr_e('نمودار', 'cpp-full'); ?>">
                            <img src="<?php echo esc_url($chart_icon_url); ?>" alt="<?php esc_attr_e('نمودار', 'cpp-full'); ?>">
                        </button>
                    </td>
                </tr>
            <?php endforeach; else: ?>
             <tr><td colspan="<?php echo $disable_base_price ? 7 : 8; ?>"><?php _e('محصولی یافت نشد.', 'cpp-full'); ?></td></tr>
            <?php endif; ?>
        </tbody>
         <tfoot>
             <tr>
                 <th><?php _e('محصول', 'cpp-full'); ?></th>
                 <th><?php _e('نوع', 'cpp-full'); ?></th>
                 <th><?php _e('واحد', 'cpp-full'); ?></th>
                 <th><?php _e('محل بارگیری', 'cpp-full'); ?></th>
                 <th><?php _e('آخرین بروزرسانی', 'cpp-full'); ?></th>
                 <?php if (!$disable_base_price) : ?><th><?php _e('قیمت پایه', 'cpp-full'); ?></th><?php endif; ?>
                 <th><?php _e('بازه قیمت', 'cpp-full'); ?></th>
                 <th><?php _e('عملیات', 'cpp-full'); ?></th>
             </tr>
         </tfoot>
    </table>

    <?php if ($total_products > $products_per_page) : ?>
    <div class="cpp-grid-view-footer">
        <button class="cpp-view-more-btn" data-page="1" data-shortcode-type="with_date"><?php _e('مشاهده بیشتر', 'cpp-full'); ?></button>
    </div>
    <?php endif; ?>
</div>
