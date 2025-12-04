<div class="wrap cpp-settings-wrap">
    <h1><?php echo __('تنظیمات افزونه مدیریت قیمت‌ها','cpp-full'); ?></h1>

    <?php $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general'; ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=custom-prices-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('عمومی', 'cpp-full'); ?></a>
        <a href="?page=custom-prices-settings&tab=shortcodes" class="nav-tab <?php echo $active_tab == 'shortcodes' ? 'nav-tab-active' : ''; ?>"><?php _e('نمایش شورت‌کدها', 'cpp-full'); ?></a>
        <a href="?page=custom-prices-settings&tab=notifications" class="nav-tab <?php echo $active_tab == 'notifications' ? 'nav-tab-active' : ''; ?>"><?php _e('اعلان‌ها', 'cpp-full'); ?></a>
    </h2>

    <form method="post" action="options.php">
        <?php
        if ($active_tab == 'general') {
            settings_fields('cpp_general_settings_grp');
            do_settings_sections('cpp_general_settings_page');
        } elseif ($active_tab == 'shortcodes') {
            settings_fields('cpp_shortcode_settings_grp');
            do_settings_sections('cpp_shortcode_settings_page');
        } else { // Tab Notifications
            settings_fields('cpp_notification_settings_grp');
            ?>
            <h3><?php _e('تنظیمات ایمیل', 'cpp-full'); ?></h3>
            <table class="form-table">
                 <tr valign="top">
                    <th scope="row"><?php _e('فعال‌سازی ارسال ایمیل','cpp-full'); ?></th>
                    <td>
                        <input type="checkbox" name="cpp_enable_email" value="1" <?php checked(get_option('cpp_enable_email'), 1); ?> />
                        <p class="description"><?php _e('ارسال ایمیل اعلان سفارش جدید به مدیر را فعال می‌کند.','cpp-full'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('ایمیل مدیر','cpp-full'); ?></th>
                    <td>
                        <input type="email" name="cpp_admin_email" value="<?php echo esc_attr(get_option('cpp_admin_email', get_option('admin_email'))); ?>" class="regular-text" />
                        <p class="description"><?php _e('ایمیل گیرنده اعلان‌های سفارش.','cpp-full'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('عنوان ایمیل سفارش مدیر','cpp-full'); ?></th>
                    <td><input type="text" name="cpp_email_subject_template" value="<?php echo esc_attr(get_option('cpp_email_subject_template', 'سفارش جدید: {product_name}')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('قالب ایمیل سفارش مدیر','cpp-full'); ?></th>
                    <td>
                        <?php
                            $content = get_option('cpp_email_body_template', '<p style="direction:rtl; text-align:right;">سفارش جدیدی از طریق وب‌سایت ثبت شده است:<br><br><strong>محصول:</strong> {product_name} - {load_location}<br><strong>نام مشتری:</strong> {customer_name}<br><strong>شماره تماس:</strong> {phone}<br><strong>تعداد/مقدار:</strong> {qty} ({unit})<br><strong>توضیحات مشتری:</strong> {note}<br></p>'); // Updated default
                            $editor_id = 'cpp_email_body_template';
                            wp_editor(wp_kses_post($content), $editor_id, ['textarea_name' => 'cpp_email_body_template', 'media_buttons' => false, 'textarea_rows' => 15]);
                        ?>
                        <p class="description"><strong><?php _e('متغیرهای مجاز:', 'cpp-full'); ?></strong> <code>{product_name}</code>, <code>{load_location}</code>, <code>{customer_name}</code>, <code>{phone}</code>, <code>{qty}</code>, <code>{unit}</code>, <code>{note}</code></p>
                        <button type="button" id="cpp-load-email-template" class="button" style="margin-top:10px;"><?php _e('بارگذاری قالب پیش‌فرض زیبا', 'cpp-full'); ?></button>
                    </td>
                </tr>
            </table>

            <hr>
            <?php do_settings_sections('cpp_notification_settings_page'); // این بخش تست ایمیل را نمایش می‌دهد ?>

            <hr>
            <h3><?php _e('تنظیمات پیامک (SMS) با IPPanel (الگو)','cpp-full'); ?></h3>
             <p class="description" style="margin-bottom: 20px;">
                <?php _e('برای ارسال پیامک به مدیر و مشتری، باید از روش "ارسال الگو" (Pattern) در IPPanel استفاده کنید.', 'cpp-full'); ?><br/>
                 <?php _e('لطفاً الگوهای مورد نیاز را در پنل IPPanel خود بسازید.', 'cpp-full'); ?>
            </p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('فعال‌سازی سرویس IPPanel','cpp-full'); ?></th>
                    <td>
                         <select name="cpp_sms_service">
                            <option value="" <?php selected(get_option('cpp_sms_service'), ''); ?>><?php _e('غیرفعال', 'cpp-full'); ?></option>
                            <option value="ippanel" <?php selected(get_option('cpp_sms_service'), 'ippanel'); ?>>IPPanel (فعال)</option>
                        </select>
                         <p class="description"><?php _e('برای فعال شدن ارسال پیامک، این گزینه را روی IPPanel قرار دهید.', 'cpp-full'); ?></p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><?php _e('کلید API','cpp-full'); ?></th>
                    <td><input type="text" name="cpp_sms_api_key" value="<?php echo esc_attr( get_option('cpp_sms_api_key') ); ?>" class="regular-text ltr" style="direction: ltr; text-align: left;"/></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('شماره فرستنده','cpp-full'); ?></th>
                    <td><input type="text" name="cpp_sms_sender" value="<?php echo esc_attr( get_option('cpp_sms_sender') ); ?>" class="regular-text ltr" style="direction: ltr; text-align: left;"/></td>
                </tr>

                <tr valign="top"> <td colspan="2"><hr><h4><?php _e('تنظیمات پیامک مدیر', 'cpp-full'); ?></h4></td> </tr>

                <tr valign="top">
                    <th scope="row"><?php _e('کد الگوی مدیر','cpp-full'); ?></th>
                    <td>
                        <input type="text" name="cpp_sms_pattern_code" value="<?php echo esc_attr( get_option('cpp_sms_pattern_code') ); ?>" class="regular-text ltr" style="direction: ltr; text-align: left; width: 200px; display: inline-block; vertical-align: middle;"/>
                        <div style="display: inline-block; vertical-align: middle; margin-right: 15px; padding: 10px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9em;">
                            <strong><?php _e('متغیرهای الگو:', 'cpp-full'); ?></strong><br>
                            <code>product_name</code><br>
                            <code>customer_name</code><br>
                            <code>phone</code><br>
                            <code>qty</code><br>
                            <code>unit</code><br>
                            <code>load_location</code><br>
                            <code>note</code>
                        </div>
                         <p class="description clear" style="clear: both; padding-top: 5px;"><?php _e('کد الگوی اعلان سفارش به مدیر را وارد کنید. الگو باید شامل متغیرهای مورد نیاز شما از لیست بالا باشد.','cpp-full'); ?></p>
                    </td>
                </tr>


                 <tr valign="top">
                    <th scope="row"><?php _e('شماره موبایل مدیر','cpp-full'); ?></th>
                    <td><input type="text" name="cpp_admin_phone" value="<?php echo esc_attr( get_option('cpp_admin_phone') ); ?>" class="regular-text ltr" style="direction: ltr; text-align: left;"/>
                         <p class="description"><?php _e('این شماره، گیرنده پیامک‌های اعلان سفارش و تست می‌باشد.', 'cpp-full'); ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php _e('تست پیامک مدیر', 'cpp-full'); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="cpp-test-sms-btn"><?php _e('ارسال پیامک تست به مدیر', 'cpp-full'); ?></button>
                        <p class="description"><?php _e('یک پیامک آزمایشی با الگوی مدیر به شماره مدیر ارسال می‌کند.', 'cpp-full'); ?></p>
                        <textarea id="cpp-sms-log" readonly style="width: 100%; height: 100px; margin-top: 10px; background-color: #f0f0f0; font-family: monospace; direction: ltr; text-align: left;"></textarea>
                    </td>
                </tr>

                <tr valign="top"> <td colspan="2"><hr><h4><?php _e('تنظیمات پیامک مشتری', 'cpp-full'); ?></h4></td> </tr>

                <tr valign="top">
                    <th scope="row"><?php _e('فعال‌سازی پیامک مشتری','cpp-full'); ?></th>
                    <td>
                        <input type="checkbox" name="cpp_sms_customer_enable" value="1" <?php checked(get_option('cpp_sms_customer_enable'), 1); ?> />
                        <p class="description"><?php _e('ارسال پیامک تایید ثبت سفارش به مشتری را فعال می‌کند.','cpp-full'); ?></p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><?php _e('کد الگوی مشتری','cpp-full'); ?></th>
                    <td>
                        <input type="text" name="cpp_sms_customer_pattern_code" value="<?php echo esc_attr( get_option('cpp_sms_customer_pattern_code') ); ?>" class="regular-text ltr" style="direction: ltr; text-align: left; width: 200px; display: inline-block; vertical-align: middle;"/>
                         <div style="display: inline-block; vertical-align: middle; margin-right: 15px; padding: 10px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9em;">
                             <strong><?php _e('متغیرهای پیشنهادی:', 'cpp-full'); ?></strong><br>
                            <code>customer_name</code><br>
                            <code>product_name</code><br>
                            <code>qty</code><br>
                            <code>unit</code><br>
                            <code>load_location</code><br>
                         </div>
                        <p class="description clear" style="clear: both; padding-top: 5px;">
                            <?php _e('کد الگوی پیامک تایید سفارش برای مشتری را وارد کنید.','cpp-full'); ?><br/>
                        </p>
                    </td>
                </tr>
            </table>
            <?php
        }
        submit_button();
        ?>
    </form>
</div>

<template id="cpp-email-template-html">
    <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0;">
        <div style="max-width: 600px; margin: 20px auto; padding: 20px; background-color: #ffffff; border: 1px solid #dddddd; border-radius: 5px;">
            <div style="text-align: center; border-bottom: 1px solid #dddddd; padding-bottom: 10px; margin-bottom: 20px;">
                <h2 style="color: #0056b3;">اطلاع‌رسانی سفارش جدید</h2>
            </div>
            <div style="direction: rtl; text-align: right;">
                <p>سلام،</p>
                <p>یک سفارش جدید از طریق وب‌سایت ثبت شده است. جزئیات به شرح زیر است:</p>
                <table style="width: 100%; border-collapse: collapse; margin-top: 20px; margin-bottom: 20px;">
                    <tbody>
                        <tr style="background-color: #f9f9f9;">
                            <td style="padding: 10px; border: 1px solid #dddddd; font-weight: bold; width: 120px;">محصول:</td>
                            <td style="padding: 10px; border: 1px solid #dddddd;">{product_name} - {load_location}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #dddddd; font-weight: bold;">نام مشتری:</td>
                            <td style="padding: 10px; border: 1px solid #dddddd;">{customer_name}</td>
                        </tr>
                        <tr style="background-color: #f9f9f9;">
                            <td style="padding: 10px; border: 1px solid #dddddd; font-weight: bold;">شماره تماس:</td>
                            <td style="padding: 10px; border: 1px solid #dddddd;">{phone}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #dddddd; font-weight: bold;">مقدار/تعداد:</td>
                            <td style="padding: 10px; border: 1px solid #dddddd;">{qty} ({unit})</td>
                        </tr>
                        <tr style="background-color: #f9f9f9;">
                            <td style="padding: 10px; border: 1px solid #dddddd; font-weight: bold;">توضیحات:</td>
                            <td style="padding: 10px; border: 1px solid #dddddd;">{note}</td>
                        </tr>
                    </tbody>
                </table>
                <p>لطفاً در اسرع وقت جهت پیگیری با مشتری تماس بگیرید.</p>
            </div>
            <div style="text-align: center; font-size: 12px; color: #777777; border-top: 1px solid #dddddd; padding-top: 10px; margin-top: 20px;">
                <p>این ایمیل به صورت خودکار ارسال شده است.</p>
            </div>
        </div>
    </div>
</template>
