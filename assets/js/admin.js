jQuery(document).ready(function ($) {

    // =======================================================
    // ۱. مدیریت آکاردئون (اصلاح شده: پیش‌فرض بسته)
    // =======================================================
    $('.cpp-accordion-header').on('click', function () {
        $(this).toggleClass('active').next('.cpp-accordion-content').slideToggle(300);
    });

    // بستن تمام پنل‌ها در ابتدا (مگر اینکه خطایی رخ داده باشد)
    if ($('.cpp-accordion-content').length && !$('.cpp-accordion-content').find('.notice-error, .error').length && !window.location.hash) {
       $('.cpp-accordion-content').hide(); 
       $('.cpp-accordion-header').removeClass('active');
    }
    
    // باز کردن پنل اگر لینک هش (Hash) وجود دارد
    if (window.location.hash) {
        var targetAccordion = $(window.location.hash);
        if (targetAccordion.hasClass('cpp-accordion-content')) {
            targetAccordion.show();
            targetAccordion.prev('.cpp-accordion-header').addClass('active');
        }
    }


    // =======================================================
    // ۲. مدیریت آپلود عکس
    // =======================================================
    var mediaUploader;
    $(document).on('click', '.cpp-upload-btn', function (e) {
        e.preventDefault();
        var button = $(this);
        var inputId = button.data("input-id");
        var input_field = inputId ? jQuery("#" + inputId) : button.siblings('input[type="text"]');
        
        // تلاش برای یافتن فیلد ورودی در ساختارهای مختلف
        if (!input_field.length) {
             input_field = button.closest('td').find('input[type="text"]');
        }

        var preview_img_container = button.closest('td, .cpp-image-uploader-wrapper, .form-table tr').find(".cpp-image-preview"); 

        if (!input_field.length) {
            console.error("CPP Uploader: Could not find target input field.");
            return;
        }

        // باز کردن مدیا آپلودر وردپرس
        mediaUploader = wp.media({
            title: 'انتخاب یا آپلود تصویر',
            button: { text: 'استفاده از این تصویر' },
            multiple: false
        });

        (function(target_input, target_preview) {
            mediaUploader.off('select'); 
            mediaUploader.on('select', function () {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                target_input.val(attachment.url).trigger('change');
                 if(target_preview.length) {
                    target_preview.html('<img src="' + attachment.url + '" style="max-width: 100px; height: auto; margin-top: 10px; border: 1px solid #ddd; padding: 3px;">');
                 }
            });
            mediaUploader.open();
        })(input_field, preview_img_container);
    });


    // =======================================================
    // ۳. ویرایش سریع (Quick Edit) - اصلاح شده
    // =======================================================
    $(document).on('dblclick', '.cpp-quick-edit, .cpp-quick-edit-select', function () {
        var cell = $(this);
        // جلوگیری از باز شدن مجدد اگر در حال ویرایش است
        if (cell.hasClass('editing') || cell.closest('td').hasClass('editing-td')) return;

        var id = cell.data('id'), field = cell.data('field'), table_type = cell.data('table-type');
        var original_html = cell.html(); 
        var original_text_content = cell.clone().children().remove().end().text().trim();

        var input_element;
        var target_element = cell;

        // حالت ۱: فیلدهای انتخابی (Select) مثل وضعیت
        if (cell.hasClass('cpp-quick-edit-select')) {
             cell.data('original-content', original_html).addClass('editing');
            var current_value = cell.data('current');
            input_element = $('<select>').addClass('cpp-quick-edit-input');
             var options_list = {};
             
             if (table_type === 'orders') {
                 options_list = cpp_admin_vars.order_statuses || {};
             } else if (table_type === 'products' && field === 'is_active') {
                  options_list = cpp_admin_vars.product_statuses || {};
             } else {
                 // برای سایر موارد فعلاً تکست باکس ساده
                 input_element = $('<input type="text">').addClass('cpp-quick-edit-input').val(original_text_content);
             }

            // پر کردن گزینه‌های Select
            if (input_element.is('select')) {
                $.each(options_list, function (val, text) {
                    $('<option>').val(val).text(text).prop('selected', val == current_value).appendTo(input_element);
                });
            }

        // حالت ۲: بازه قیمت (دو فیلد در یک سلول)
        } else if (field === 'min_price' || field === 'max_price') {
             var td = cell.closest('td');
             if (td.hasClass('editing-td')) return;
             td.addClass('editing-td');
             target_element = td;

             var min_span = td.find('[data-field="min_price"]');
             var max_span = td.find('[data-field="max_price"]');
             td.data('original-content', td.html());

             var min_val = min_span.text().trim();
             var max_val = max_span.text().trim();

             var container = $('<div>');
             // استفاده از attr برای دیتا فیلد تا در تابع ذخیره قابل شناسایی باشد
             var min_input = $('<input type="text">').addClass('cpp-quick-edit-input small-text').val(min_val).attr('data-field', 'min_price');
             var max_input = $('<input type="text">').addClass('cpp-quick-edit-input small-text').val(max_val).attr('data-field', 'max_price');
             
             container.append(min_input).append(' - ').append(max_input);
             input_element = container;
             td.css('width', 'auto'); 

        // حالت ۳: متن ساده یا متن چند خطی
        } else {
            cell.data('original-content', original_html).addClass('editing');
            
            if (field === 'admin_note' || field === 'description') {
                input_element = $('<textarea>').addClass('cpp-quick-edit-input').val(original_text_content);
            } else {
                input_element = $('<input>').attr('type', 'text').addClass('cpp-quick-edit-input').val(original_text_content);
            }
        }

        // دکمه‌های ذخیره و لغو
        var save_btn = $('<button>').addClass('button button-primary button-small').text(cpp_admin_vars.i18n.save || 'ذخیره');
        var cancel_btn = $('<button>').addClass('button button-secondary button-small').text(cpp_admin_vars.i18n.cancel || 'لغو').css('margin-right', '5px');
        var buttons = $('<div>').addClass('cpp-quick-edit-buttons').css('margin-top', '5px').append(save_btn).append(cancel_btn);

        target_element.html('').append(input_element).append(buttons);
        input_element.find('input, select, textarea').first().focus();

        // رویداد کلیک ذخیره
        save_btn.on('click', function () {
             if (field === 'min_price' || field === 'max_price') {
                 performSavePriceRange(td, id, table_type);
             } else {
                 performSave(cell, id, field, table_type);
             }
         });

        // رویداد کلیک لغو
        cancel_btn.on('click', function () {
             if (field === 'min_price' || field === 'max_price') {
                 td.removeClass('editing-td').html(td.data('original-content'));
             } else {
                 cell.removeClass('editing').html(cell.data('original-content'));
             }
         });

        // ذخیره با اینتر (به جز textarea)
         $(input_element).find('input, select, textarea').on('keydown', function (e) {
            if (e.key === 'Escape') {
                cancel_btn.click();
            } else if (e.key === 'Enter' && !$(this).is('textarea')) {
                 e.preventDefault();
                 save_btn.click();
             }
        });
    });

    // تابع ذخیره بازه قیمت (رفع باگ خطای سرور)
    function performSavePriceRange(td, id, table_type) {
        var min_input = td.find('input[data-field="min_price"]');
        var max_input = td.find('input[data-field="max_price"]');
        
        var min_value = min_input.val();
        var max_value = max_input.val();
        var original_html = td.data('original-content');

        td.html(cpp_admin_vars.i18n.saving || 'در حال ذخیره...');

        var promise1 = $.post(cpp_admin_vars.ajax_url, {
            action: 'cpp_quick_update', security: cpp_admin_vars.nonce, id: id, field: 'min_price', value: min_value, table_type: table_type
        });
        var promise2 = $.post(cpp_admin_vars.ajax_url, {
            action: 'cpp_quick_update', security: cpp_admin_vars.nonce, id: id, field: 'max_price', value: max_value, table_type: table_type
        });

        $.when(promise1, promise2).done(function (res1, res2) {
            td.removeClass('editing-td');
            var response1 = res1[0];
            var response2 = res2[0];

            if (response1.success && response2.success) {
                var new_min_span = $('<span>').addClass('cpp-quick-edit').attr('data-id', id).attr('data-field', 'min_price').attr('data-table-type', table_type).text(min_value);
                var new_max_span = $('<span>').addClass('cpp-quick-edit').attr('data-id', id).attr('data-field', 'max_price').attr('data-table-type', table_type).text(max_value);
                td.html('').append(new_min_span).append(' - ').append(new_max_span);

                if (response1.data.new_time || response2.data.new_time) {
                    td.closest('tr').find('.cpp-last-update').text(response1.data.new_time || response2.data.new_time);
                }
            } else {
                 var errorMsg = (cpp_admin_vars.i18n.error || 'خطا') + ':';
                 if (!response1.success && response1.data) errorMsg += ' ' + (response1.data.message || '');
                 if (!response2.success && response2.data) errorMsg += ' ' + (response2.data.message || '');
                 alert(errorMsg);
                 td.html(original_html);
            }
        }).fail(function (jqXHR) {
             td.removeClass('editing-td');
             alert(cpp_admin_vars.i18n.serverError || 'خطای سرور');
             console.error("Price Range Save Error:", jqXHR);
             td.html(original_html);
        });
    }

    // تابع ذخیره فیلد تکی
    function performSave(cell, id, field, table_type) {
        var inputField = cell.find('.cpp-quick-edit-input');
        var new_value = inputField.val();
        var original_html = cell.data('original-content');

        cell.html(cpp_admin_vars.i18n.saving || 'در حال ذخیره...');
        $.post(cpp_admin_vars.ajax_url, {
            action: 'cpp_quick_update', security: cpp_admin_vars.nonce, id: id, field: field, value: new_value, table_type: table_type
        }, function (response) {
            cell.removeClass('editing');
            if (response.success) {
                var display_html_or_text;
                if (cell.hasClass('cpp-quick-edit-select')) {
                     var options_list = {};
                     if (table_type === 'orders') options_list = cpp_admin_vars.order_statuses || {};
                     else if (table_type === 'products' && field === 'is_active') options_list = cpp_admin_vars.product_statuses || {};
                     display_html_or_text = options_list[new_value] || new_value;
                     cell.data('current', new_value);
                     cell.html(display_html_or_text); 
                } else {
                     display_html_or_text = new_value.replace(/\n/g, '<br>');
                     cell.html(display_html_or_text); 
                }

                if (response.data && response.data.new_time) { 
                    cell.closest('tr').find('.cpp-last-update').text(response.data.new_time);
                }
            } else {
                 var errorMsg = (response.data && response.data.message) ? response.data.message : (cpp_admin_vars.i18n.error || 'خطا');
                 alert(errorMsg);
                 cell.html(original_html);
            }
        }).fail(function (jqXHR) {
             cell.removeClass('editing');
             alert((cpp_admin_vars.i18n.serverError || 'خطای سرور'));
             cell.html(original_html);
        });
    }


    // =======================================================
    // ۴. مدیریت پاپ‌آپ‌ها (Modals)
    // =======================================================
    
    // تابع کمکی برای باز کردن مدال
    function openEditModal(ajax_data) {
        if ($('#cpp-edit-modal').length === 0) {
            $('body').append('<div id="cpp-edit-modal" class="cpp-modal-overlay" style="display: none;"><div class="cpp-modal-container"><span class="cpp-close-modal">×</span><div class="cpp-edit-modal-content"></div></div></div>');
        }
        var modal = $('#cpp-edit-modal');
        var modalContent = modal.find('.cpp-edit-modal-content');

        // استفاده از display:flex برای وسط‌چین شدن صحیح (با کمک CSS جدید)
        modal.css('display', 'flex').addClass('loading');
        modalContent.html('<p style="text-align:center; padding: 20px;">' + (cpp_admin_vars.i18n.loadingForm || 'در حال بارگذاری...') + '</p>');

        $.get(cpp_admin_vars.ajax_url, ajax_data)
            .done(function (response) {
                modal.removeClass('loading');
                if (response.success && response.data && response.data.html) {
                    modalContent.html(response.data.html);
                    // فعال‌سازی مجدد آپلودر و رنگ‌گزین در محتوای جدید
                    if (typeof window.cpp_init_media_uploader === 'function') window.cpp_init_media_uploader();
                    if (modalContent.find('.cpp-color-picker').length > 0) modalContent.find('.cpp-color-picker').wpColorPicker();
                } else {
                     var errorMessage = (response.data && (response.data.message || response.data.html || response.data)) || 'خطا در بارگذاری';
                     modalContent.html('<p style="color:red; text-align:center; padding: 20px;">' + errorMessage + '</p>');
                }
            })
            .fail(function (jqXHR, textStatus) {
                 modal.removeClass('loading');
                 modalContent.html('<p style="color:red; text-align:center; padding: 20px;">' + (cpp_admin_vars.i18n.serverError || 'خطای سرور') + ': ' + textStatus + '</p>');
            });
    }

    // بستن مدال با دکمه بستن
    $(document).on('click', '.cpp-close-modal', function () { 
        $(this).closest('.cpp-modal-overlay').hide(); 
        if (typeof chartInstance !== 'undefined' && chartInstance) { chartInstance.destroy(); chartInstance = null; }
    });
    
    // بستن مدال با کلیک بیرون کادر
    $(document).on('click', '.cpp-modal-overlay', function(e) { 
        if ($(e.target).is('.cpp-modal-overlay')) { 
            $(this).hide(); 
            if (typeof chartInstance !== 'undefined' && chartInstance) { chartInstance.destroy(); chartInstance = null; }
        } 
    });

    // دکمه‌های ویرایش
    $(document).on('click', '.cpp-edit-button, .cpp-edit-cat-button', function () {
        var button = $(this);
        var ajax_data = { security: cpp_admin_vars.nonce };
        if (button.hasClass('cpp-edit-button')) {
            ajax_data.action = 'cpp_fetch_product_edit_form';
            ajax_data.id = button.data('product-id');
        } else {
            ajax_data.action = 'cpp_fetch_category_edit_form';
            ajax_data.id = button.data('cat-id');
        }
        openEditModal(ajax_data);
    });

    // نمایش نمودار
    var chartInstance = null;
    $(document).on('click', '.cpp-show-chart', function (e) {
        e.preventDefault();
        var productId = $(this).data('product-id');
        
        if ($('#cpp-chart-modal').length === 0) {
             $('body').append('<div id="cpp-chart-modal" class="cpp-modal-overlay" style="display:none;"><div class="cpp-modal-container"><span class="cpp-close-modal">×</span><h2>نمودار تغییرات قیمت</h2><div class="cpp-chart-modal-content"><canvas id="cppPriceChart" width="400" height="150"></canvas></div></div></div>');
        }
        var modal = $('#cpp-chart-modal');
        var chartCanvas = modal.find('#cppPriceChart');
        var modalContent = modal.find('.cpp-chart-modal-content');

        modal.css('display', 'flex'); // Flex برای وسط‌چین شدن
        modalContent.find('.chart-error, .chart-loading').remove();
        chartCanvas.show();
        
        if (chartInstance) { chartInstance.destroy(); chartInstance = null; }
        modalContent.append('<p class="chart-loading" style="text-align:center;">' + (cpp_admin_vars.i18n.loading || 'در حال بارگذاری...') + '</p>');

        $.get(cpp_admin_vars.ajax_url, { action: 'cpp_get_chart_data', product_id: productId, security: cpp_admin_vars.nonce }, function (response) {
            modalContent.find('.chart-loading').remove();
            if (response.success && response.data && response.data.labels && response.data.labels.length > 0) {
                 renderChart(response.data, chartCanvas[0]);
             } else {
                 chartCanvas.hide();
                 var errorMsg = (response.data && typeof response.data === 'string') ? response.data : 'تاریخچه قیمت یافت نشد.';
                 modalContent.prepend('<p class="chart-error" style="color:red; text-align:center;">' + errorMsg + '</p>');
             }
        }).fail(function (jqXHR, textStatus) {
             modalContent.find('.chart-loading').remove();
             chartCanvas.hide();
             modalContent.prepend('<p class="chart-error" style="color:red; text-align:center;">' + (cpp_admin_vars.i18n.serverError || 'خطای سرور') + ': ' + textStatus + '</p>');
        });
    });

    function renderChart(chartData, ctx) { 
         var datasets = [];
         if (chartData.prices && chartData.prices.filter(p => p !== null).length > 0) {
             datasets.push({ label: 'قیمت پایه', data: chartData.prices, borderColor: 'rgb(75, 192, 192)', backgroundColor: 'rgba(75, 192, 192, 0.2)', tension: 0.3, fill: false, borderWidth: 2 });
         }
         if (chartData.min_prices && chartData.min_prices.filter(p => p !== null).length > 0) {
             datasets.push({ label: 'حداقل قیمت', data: chartData.min_prices, borderColor: 'rgba(255, 99, 132, 0.7)', backgroundColor: 'rgba(255, 99, 132, 0.1)', tension: 0, borderDash: [5, 5], fill: '+1', pointRadius: 0, borderWidth: 1 });
         }
         if (chartData.max_prices && chartData.max_prices.filter(p => p !== null).length > 0) {
             datasets.push({ label: 'حداکثر قیمت', data: chartData.max_prices, borderColor: 'rgba(54, 162, 235, 0.7)', backgroundColor: 'rgba(54, 162, 235, 0.1)', tension: 0, borderDash: [5, 5], fill: false, pointRadius: 0, borderWidth: 1 });
         }
         
         if(datasets.length === 0){ 
             $(ctx).parent().prepend('<p class="chart-error" style="color:red; text-align:center;">داده‌ای برای نمایش در نمودار وجود ندارد.</p>'); 
             $(ctx).hide(); return; 
         }
         
         try {
             chartInstance = new Chart(ctx, { type: 'line', data: { labels: chartData.labels, datasets: datasets }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false } }, spanGaps: true } });
         } catch (e) { console.error("Chart Error:", e); }
     }


    // =======================================================
    // ۵. ارسال فرم‌های ویرایش (داخل مدال)
    // =======================================================
    $(document).on('submit', '#cpp-edit-product-form, #cpp-edit-category-form', function (e) {
        e.preventDefault();
        var form = $(this);
        var action = form.attr('id') === 'cpp-edit-product-form' ? 'cpp_handle_edit_product_ajax' : 'cpp_handle_edit_category_ajax';
        var submit_button = form.find('input[type="submit"]');
        var originalButtonText = submit_button.val();

        submit_button.prop('disabled', true).val(cpp_admin_vars.i18n.saving || 'در حال ذخیره...');
        form.find('.cpp-form-message').remove();

        var form_data = form.serializeArray();
        form_data.push({ name: 'action', value: action });

        $.ajax({
            url: cpp_admin_vars.ajax_url, type: 'POST', data: $.param(form_data),
            success: function (response) {
                if (response.success) {
                    var successMsg = (response.data && response.data.message) ? response.data.message : 'با موفقیت ذخیره شد.';
                    form.prepend('<div class="cpp-form-message notice notice-success is-dismissible"><p>' + successMsg + '</p></div>');
                    setTimeout(function () { $('#cpp-edit-modal').hide(); window.location.reload(); }, 1500);
                } else {
                    submit_button.prop('disabled', false).val(originalButtonText);
                    var errorMessage = (response.data && response.data.message) ? response.data.message : 'خطا در ذخیره';
                    form.prepend('<div class="cpp-form-message notice notice-error is-dismissible"><p>' + errorMessage + '</p></div>');
                }
            },
            error: function (jqXHR, textStatus) {
                 submit_button.prop('disabled', false).val(originalButtonText);
                 form.prepend('<div class="cpp-form-message notice notice-error is-dismissible"><p>خطای سرور: ' + textStatus + '</p></div>');
            }
        });
    });


    // =======================================================
    // ۶. ابزارهای تنظیمات (تست ایمیل و پیامک)
    // =======================================================
    
    // دکمه بارگذاری قالب ایمیل
    $('#cpp-load-email-template').on('click', function () {
        if (confirm('آیا مطمئنید؟ محتوای فعلی فیلد قالب ایمیل با قالب پیش‌فرض جایگزین خواهد شد.')) {
            var templateHtml = $('#cpp-email-template-html').html();
            if (typeof tinymce !== 'undefined' && tinymce.get('cpp_email_body_template')) {
                tinymce.get('cpp_email_body_template').setContent(templateHtml.trim());
            } else {
                $('#cpp_email_body_template').val(templateHtml.trim());
            }
        }
    });

    // دکمه تست ایمیل
    $('#cpp-test-email-btn').on('click', function () {
        var button = $(this);
        var logBox = $('#cpp-email-log');
        var originalButtonText = cpp_admin_vars.i18n.sendTestEmail || 'ارسال ایمیل تست';

        button.prop('disabled', true).text('در حال ارسال...');
        logBox.val('در حال ارسال...');
        logBox.css('color', 'black');

        $.post(cpp_admin_vars.ajax_url, { action: 'cpp_test_email', security: cpp_admin_vars.nonce }, function (response) {
            if (response.success) {
                logBox.val(response.data.log);
                logBox.css('color', 'green');
            } else {
                 logBox.val('خطا: \n' + JSON.stringify(response));
                 logBox.css('color', 'red');
            }
            button.prop('disabled', false).text(originalButtonText);
        }).fail(function (jqXHR) {
            logBox.val('خطای سرور: ' + jqXHR.responseText);
            logBox.css('color', 'red');
            button.prop('disabled', false).text(originalButtonText);
        });
    });

    // دکمه تست پیامک
    $('#cpp-test-sms-btn').on('click', function () {
        var button = $(this);
        var logBox = $('#cpp-sms-log');
        var originalButtonText = cpp_admin_vars.i18n.sendTestSms || 'ارسال پیامک تست';

        button.prop('disabled', true).text('در حال ارسال...');
        logBox.val('در حال ارسال...');
        logBox.css('color', 'black');

        $.post(cpp_admin_vars.ajax_url, { action: 'cpp_test_sms', security: cpp_admin_vars.nonce }, function (response) {
            if (response.success) {
                logBox.val(response.data.log);
                logBox.css('color', 'green');
            } else {
                 logBox.val('خطا: \n' + JSON.stringify(response));
                 logBox.css('color', 'red');
            }
            button.prop('disabled', false).text(originalButtonText);
        }).fail(function (jqXHR) {
            logBox.val('خطای سرور: ' + jqXHR.responseText);
            logBox.css('color', 'red');
            button.prop('disabled', false).text(originalButtonText);
        });
    });

});
