<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1><?php _e('راهنمای شورت‌کدهای افزونه', 'cpp-full'); ?></h1>

    <div class="card">
        <h2><?php _e('لیست محصولات (گرید با تاریخ)', 'cpp-full'); ?></h2>
        <p><?php _e('این شورت‌کد یک جدول گرید پیشرفته از محصولات فعال را نمایش می‌دهد که شامل فیلتر دسته‌بندی، ستون تاریخ بروزرسانی و دکمه "مشاهده بیشتر" (Load More) است.', 'cpp-full'); ?></p>
        <code>[cpp_products_grid_view]</code>
    </div>

    <div class="card">
        <h2><?php _e('لیست محصولات (گرید بدون تاریخ)', 'cpp-full'); ?></h2>
        <p><?php _e('این شورت‌کد یک جدول گرید مشابه بالایی نمایش می‌دهد، اما ستون تاریخ بروزرسانی را حذف می‌کند و در بالای جدول، "آخرین زمان بروزرسانی" کلی را نمایش می‌دهد.', 'cpp-full'); ?></p>
        <code>[cpp_products_grid_view_no_date]</code>
    </div>

    <div class="card">
        <h2><?php _e('لیست محصولات (ساده)', 'cpp-full'); ?></h2>
        <p><?php _e('این شورت‌کد یک لیست جدولی ساده‌تر از محصولات را نمایش می‌دهد. این شورت‌کد از دکمه "مشاهده بیشتر" پشتیبانی نمی‌کند اما قابلیت فیلتر بر اساس شناسه یا دسته‌بندی را دارد.', 'cpp-full'); ?></p>
        <code>[cpp_products_list]</code>
        
        <h3><?php _e('پارامترهای قابل قبول:', 'cpp-full'); ?></h3>
        <ul>
            <li><strong>cat_id:</strong> <?php _e('برای نمایش محصولات یک یا چند دسته‌بندی خاص (جدا شده با کاما). مثال:', 'cpp-full'); ?> <code>[cpp_products_list cat_id="1,5"]</code></li>
            <li><strong>ids:</strong> <?php _e('برای نمایش محصولات خاص بر اساس شناسه (جدا شده با کاما). مثال:', 'cpp-full'); ?> <code>[cpp_products_list ids="10,15"]</code></li>
            <li><strong>status:</strong> <?php _e('برای نمایش محصولات فعال (1)، غیرفعال (0) یا همه (all). پیش‌فرض 1 است. مثال:', 'cpp-full'); ?> <code>[cpp_products_list status="all"]</code></li>
        </ul>
    </div>

    <style>
        .wrap .card {
            background: #fff;
            border: 1px solid #e5e5e5;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 15px 20px;
            margin-top: 20px;
            direction: rtl;
            text-align: right;
        }
        .wrap .card h2 {
            margin-top: 0;
            font-size: 1.2em;
        }
        .wrap .card code {
            display: block;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
            direction: ltr;
            text-align: left;
            user-select: all;
        }
         .wrap .card ul {
             list-style-type: disc;
             margin-right: 20px;
         }
         .wrap .card ul li {
             margin-bottom: 5px;
         }