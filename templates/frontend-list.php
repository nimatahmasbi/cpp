<?php
// frontend list template used by shortcode
?>
<div class="cpp-front">
    <?php if(!empty($a['title'])) echo '<h3>'.esc_html($a['title']).'</h3>'; ?>
    <table class="cpp-table">
        <thead><tr><th><?php _e('نام','cpp'); ?></th><th><?php _e('دسته','cpp'); ?></th><th><?php _e('قیمت','cpp'); ?></th><th><?php _e('آخرین بروزرسانی','cpp'); ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach($products as $p): ?>
            <tr data-id="<?php echo $p->id; ?>">
                <td><?php echo esc_html($p->product_name); ?></td>
                <td><?php echo esc_html($p->category_name); ?></td>
                <td><?php echo $p->min_price ? esc_html($p->min_price).' – '.esc_html($p->max_price) : __('تماس بگیرید','cpp'); ?></td>
                <td><?php echo $p->last_modified ? cpp_format_date_local($p->last_modified) : '-'; ?></td>
                <td><button class="cpp-btn cpp-chart-btn" data-id="<?php echo $p->id; ?>"><?php _e('نمودار قیمت','cpp'); ?></button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
