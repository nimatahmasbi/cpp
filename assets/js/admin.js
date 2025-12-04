jQuery(document).ready(function ($) {

    // آکاردئون
    $('.cpp-accordion-header').on('click', function () {
        $(this).toggleClass('active').next('.cpp-accordion-content').slideToggle(300);
    });

    // بستن آکاردئون‌ها در بارگذاری اولیه صفحه (مگر اینکه خطایی داخلشان باشد)
    // --- اصلاح: کدهای زیر از حالت کامنت خارج شدند ---
    if ($('.cpp-accordion-content').length && !$('.cpp-accordion-content').find('.error').length && !$('.cpp-accordion-content').is(':visible')) {
       $('.cpp-accordion-content').hide(); 
       $('.cpp-accordion-header').removeClass('active');
    }
    
    // If hash exists, open corresponding accordion (useful for redirects with errors)
    if (window.location.hash) {
        var targetAccordion = $(window.location.hash);
        if (targetAccordion.hasClass('cpp-accordion-content')) {
            targetAccordion.show();
            targetAccordion.prev('.cpp-accordion-header').addClass('active');
        }
    }


    // مدیریت آپلود عکس
    var mediaUploader;
    $(document).on('click', '.cpp-upload-btn', function (e) {
        e.preventDefault();
        var button = $(this);
        var inputId = button.data("input-id");
        var input_field = inputId ? jQuery("#" + inputId) : button.siblings('input[type="text"]');
        if (!input_field.length) {
             input_field = button.closest('td').find('input[type="text"]');
        }

        var preview_img_container = button.closest('td, .cpp-image-uploader-wrapper, .form-table tr').find(".cpp-image-preview"); 

        if (!input_field.length) {
            console.error("CPP Uploader: Could not find target input field.");
            return;
        }

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


    // ویرایش سریع با دبل کلیک
    $(document).on('dblclick', '.cpp-quick-edit, .cpp-quick-edit-select', function () {
        var cell = $(this);
        if (cell.hasClass('editing') || cell.closest('td').hasClass('editing-td')) return;

        var id = cell.data('id'), field = cell.data('field'), table_type = cell.data('table-type');
        var original_html = cell.html(); 
        var original_text_content = cell.clone().children().remove().end().text().trim();

        var input_element;
        var target_element = cell;

        if (cell.hasClass('cpp-quick-edit-select')) {
             cell.data('original-content', original_html).addClass('editing');
            var current_value = cell.data('current');
            input_element = $('<select>').addClass('cpp-quick-edit-input');
             var options_list = {};
             if (table_type === 'orders') {
                 options_list = cpp_admin_vars.order_statuses || {};
             } else if (table_type === 'products' && field === 'is_active') {
                  options_list = cpp_admin_vars.product_statuses || {};
             } else if (table_type === 'products' && field === 'cat_id') {
                   console.warn("Category quick edit not fully implemented yet.");
                    input_element = $('<input type="text">').addClass('cpp-quick-edit-input').val(original_text_content);

             }

            $.each(options_list, function (val, text) {
                $('<option>').val(val).text(text).prop('selected', val == current_value).appendTo(input_element);
            });
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
             
             // --- اصلاح: استفاده از attr به جای data برای شناسایی توسط سلکتور در تابع ذخیره ---
             var min_input = $('<input type="text">').addClass('cpp-quick-edit-input small-text').val(min_val).attr('data-field', 'min_price');
             var max_input = $('<input type="text">').addClass('cpp-quick-edit-input small-text').val(max_val).attr('data-field', 'max_price');
             
             container.append(min_input).append(' - ').append(max_input);
             input_element = container;

             td.css('width', 'auto'); 


        } else {
            cell.data('original-content', original_html).addClass('editing');
            
            // --- اصلاح: ایجاد صحیح المنت input یا textarea ---
            if (field === 'admin_note' || field === 'description') {
                input_element = $('<textarea>').addClass('cpp-quick-edit-input').val(original_text_content);
            } else {
                input_element = $('<input>').attr('type', 'text').addClass('cpp-quick-edit-input').val(original_text_content);
            }
        }

        var save_btn = $('<button>').addClass('button button-primary button-small').text(cpp_admin_vars.i18n.save || 'ذخیره');
        var cancel_btn = $('<button>').addClass('button button-secondary button-small').text(cpp_admin_vars.i18n.cancel || 'لغو').css('margin-right', '5px');
        var buttons = $('<div>').addClass('cpp-quick-edit-buttons').css('margin-top', '5px').append(save_btn).append(cancel_btn);

        target_element.html('').append(input_element).append(buttons);
        input_element.find('input, select, textarea').first().focus();

        save_btn.on('click', function () {
             if (field === 'min_price' || field === 'max_price') {
                 performSavePriceRange(td, id, table_type);
             } else {
                 performSave(cell, id, field, table_type);
             }
         });
        cancel_btn.on('click', function () {
             if (field === 'min_price' || field === 'max_price') {
                 td.removeClass('editing-td').html(td.data('original-content'));
             } else {
                 cell.removeClass('editing').html(cell.data('original-content'));
             }
         });
         $(input_element).find('input, select, textarea').on('keydown', function (e) {
            if (e.key === 'Escape') {
                cancel_btn.click();
            } else if (e.key === 'Enter' && !$(this).is('textarea')) {
                 e.preventDefault();
                 save_btn.click();
             }
        });
    });

    function performSavePriceRange(td, id, table_type) {
        // --- چون از attr استفاده کردیم، حالا این سلکتور درست کار می‌کند ---
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
                 if (!response1.success && response1.data && response1.data.message) errorMsg += '\nMin: ' + response1.data.message;
                 if (!response2.success && response2.data && response2.data.message) errorMsg += '\nMax: ' + response2.data.message;
                 alert(errorMsg);
                 td.html(original_html);
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
             td.removeClass('editing-td');
             alert(cpp_admin_vars.i18n.serverError || 'خطای سرور');
             console.error("Price Range Save Error:", textStatus, errorThrown, jqXHR);
             td.html(original_html);
        });
    }


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
        }).fail(function (jqXHR, textStatus, errorThrown) {
             cell.removeClass('editing');
             alert((cpp_admin_vars.i18n.serverError || 'خطای سرور') + ': ' + textStatus);
             cell.html(original_html);
        });
    }


    // منطق پاپ‌آپ ویرایش
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

   function openEditModal(ajax_data) {
        if ($('#cpp-edit-modal').length === 0) {
            $('body').append('<div id="cpp-edit-modal" class="cpp-modal-overlay" style="display: none;"><div class="cpp-modal-container"><span class="cpp-close-modal">×</span><div class="cpp-edit-modal-content"></div></div></div>');
        }
        var modal = $('#cpp-edit-modal');
        var modalContent = modal.find('.cpp-edit-modal-content');

        modal.addClass('loading').show();
        modalContent.html('<p style="text-align:center; padding: 20px;">' + (cpp_admin_vars.i18n.loadingForm || 'در حال بارگذاری...') + '</p>');

        $.get(cpp_admin_vars.ajax_url, ajax_data)
            .done(function (response) {
                modal.removeClass('loading');
                if (response.success && response.data && response.data.html) {
                    modalContent.html(response.data.html);
                    if (typeof window.cpp_init_media_uploader === 'function') window.cpp_init_media_uploader();
                    if (modalContent.find('.cpp-color-picker').length > 0) modalContent.find('.cpp-color-picker').wpColorPicker();
                } else {
                     var errorMessage = (response.data && (response.data.message || response.data.html || response.data)) || (cpp_admin_vars.i18n.error || 'خطا');
                     modalContent.html('<p style="color:red; text-align:center; padding: 20px;">' + errorMessage + '</p>');
                     console.error("Error loading edit form:", response);
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                 modal.removeClass('loading');
                 modalContent.html('<p style="color:red; text-align:center; padding: 20px;">' + (cpp_admin_vars.i18n.serverError || 'خطای سرور') + ': ' + textStatus + '</p>');
                 console.error("AJAX error loading edit form:", textStatus, errorThrown, jqXHR);
            });
    }

    $(document).on('click', '#cpp-edit-modal .cpp-close-modal', function () { $('#cpp-edit-modal').hide(); });
    $(document).on('click', '#cpp-edit-modal', function(e) { if ($(e.target).is('#cpp-edit-modal')) $(this).hide(); });


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

        modal.show();
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
                 console.error("Chart data error:", response);
             }
        }).fail(function (jqXHR, textStatus, errorThrown) {
             modalContent.find('.chart-loading').remove();
             chartCanvas.hide();
             modalContent.prepend('<p class="chart-error" style="color:red; text-align:center;">' + (cpp_admin_vars.i18n.serverError || 'خطای سرور') + ': ' + textStatus + '</p>');
        });
    });

    $(document).on('click', '#cpp-chart-modal .cpp-close-modal', function () { $('#cpp-chart-modal').hide(); if (chartInstance) { chartInstance.destroy(); chartInstance = null; } });
    $(document).on('click', '#cpp-chart-modal', function(e) { if ($(e.target).is('#cpp-chart-modal')) { $(this).hide(); if (chartInstance) { chartInstance.destroy(); chartInstance = null; } } });

    function renderChart(chartData, ctx) { 
         var datasets = [];
         if (chartData.prices && chartData.prices.filter(p => p !== null).length > 0) {
             datasets.push({ label: 'قیمت پایه', data: chartData.prices, borderColor: 'rgb(75, 192, 192)', backgroundColor: 'rgba(75, 192, 192, 0.2)', tension: 0.3, fill: false, borderWidth: 2 });
         }
         if (chartData.min_prices && chartData.min_prices.filter(p => p !== null).length > 0) {
             datasets.push({ label: 'حداقل قیمت', data: chartData.min_prices, borderColor: 'rgba(255, 99, 132, 0.7)', backgroundColor: 'rgba(255, 99, 132, 0.1)', tension: 0, borderDash: [5, 5], fill: '+1', pointRadius: 0, borderWidth: 1 });
         }
         if (chartData.max_prices && chartData.max_prices.filter(p => p !== null).length > 0) {
             var fillTarget = false;
             datasets.push({ label: 'حداکثر قیمت', data: chartData.max_prices, borderColor: 'rgba(54, 162, 235, 0.7)', backgroundColor: 'rgba(54, 162, 235, 0.1)', tension: 0, borderDash: [5, 5], fill: fillTarget, pointRadius: 0, borderWidth: 1 });
         }
         if(datasets.length === 0){ $(ctx).parent().prepend('<p class="chart-error" style="color:red; text-align:center;">داده‌ای برای نمایش در نمودار وجود ندارد.</p>'); $(ctx).hide(); return; }
         if (!ctx || typeof ctx.getContext !== 'function') { console.error("Invalid canvas context"); return; }
         try {
             chartInstance = new Chart(ctx, { type: 'line', data: { labels: chartData.labels, datasets: datasets }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false, title: { display: true, text: 'قیمت' } }, x: { title: { display: true, text: 'تاریخ' } } }, plugins: { tooltip: { mode: 'index', intersect: false }, legend: { position: 'top' } }, hover: { mode: 'nearest', intersect: true }, spanGaps: true } });
         } catch (e) { console.error("Error creating chart:", e); $(ctx).parent().prepend('<p class="chart-error" style="color:red;">خطا در رسم نمودار.</p>'); $(ctx).hide(); }
     }


    // مدیریت ذخیره فرم‌های پاپ آپ ویرایش (محصول و دسته بندی)
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
                if (response && typeof response.success !== 'undefined') {
                    if (response.success) {
                        var successMsg = (response.data && response.data.message) ? response.data.message : (response.data || 'با موفقیت ذخیره شد.');
                        form.prepend('<div class="cpp-form-message notice notice-success is-dismissible"><p>' + successMsg + '</p></div>');
                        setTimeout(function () { $('#cpp-edit-modal').hide(); window.location.reload(); }, 1500);
                    } else {
                        submit_button.prop('disabled', false).val(originalButtonText);
                        var errorMessage = (response.data && response.data.message) ? response.data.message : (response.data || (cpp_admin_vars.i18n.error || 'خطا'));
                        form.prepend('<div class="cpp-form-message notice notice-error is-dismissible"><p>' + errorMessage + '</p></div>');
                    }
                 } else {
                     submit_button.prop('disabled', false).val(originalButtonText);
                     form.prepend('<div class="cpp-form-message notice notice-error is-dismissible"><p>پاسخ غیرمنتظره از سرور.</p></div>');
                     console.error("Unexpected success response format:", response);
                 }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                 submit_button.prop('disabled', false).val(originalButtonText);
                 var errorDetail = jqXHR.responseText || errorThrown;
                 form.prepend('<div class="cpp-form-message notice notice-error is-dismissible"><p>' + (cpp_admin_vars.i18n.serverError || 'خطای سرور') + ': ' + textStatus + '<br><small>' + errorDetail.substring(0, 200) + '</small></p></div>');
                 console.error("AJAX Error saving form:", textStatus, errorThrown, jqXHR);
            }
        });
    });


    // منطق بارگذاری قالب ایمیل در صفحه تنظیمات
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

    // منطق تست ارسال ایمیل
    $('#cpp-test-email-btn').on('click', function () {
        var button = $(this);
        var logBox = $('#cpp-email-log');
        var originalButtonText = cpp_admin_vars.i18n.sendTestEmail || 'ارسال ایمیل تست';

        button.prop('disabled', true).text(cpp_admin_vars.i18n.sendingTestEmail || 'در حال ارسال...');
        logBox.val('در حال آماده‌سازی برای ارسال ایمیل آزمایشی...');
        logBox.css('color', 'black');

        $.post(cpp_admin_vars.ajax_url, { action: 'cpp_test_email', security: cpp_admin_vars.nonce }, function (response) {
            if (response && typeof response.success !== 'undefined' && response.data && typeof response.data.log !== 'undefined') {
                logBox.val(response.data.log);
                logBox.css('color', response.success ? 'green' : 'red');
            } else {
                 logBox.val('پاسخ نامعتبر از سرور.\nResponse:\n' + JSON.stringify(response, null, 2));
                 logBox.css('color', 'red'); console.error("Invalid email test response:", response);
            }
            button.prop('disabled', false).text(originalButtonText);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            logBox.val('AJAX Error: ' + textStatus + ' (' + jqXHR.status + ')\n' + jqXHR.responseText.substring(0, 500));
            logBox.css('color', 'red'); button.prop('disabled', false).text(originalButtonText); console.error("AJAX Error email test:", jqXHR);
        });
    });

    // منطق تست ارسال پیامک (نهایی)
    $('#cpp-test-sms-btn').on('click', function () {
        var button = $(this);
        var logBox = $('#cpp-sms-log');
        var originalButtonText = cpp_admin_vars.i18n.sendTestSms || 'ارسال پیامک تست به مدیر';

        button.prop('disabled', true).text(cpp_admin_vars.i18n.sendingTestSms || 'در حال ارسال...');
        logBox.val('در حال آماده‌سازی برای ارسال پیامک آزمایشی...');
        logBox.css('color', 'black');
        console.log('Sending AJAX request for cpp_test_sms...');

        $.post(cpp_admin_vars.ajax_url, { action: 'cpp_test_sms', security: cpp_admin_vars.nonce }, function (response) {
             console.log('AJAX Success (SMS):', response);
            if (response && typeof response.success !== 'undefined' && response.data && typeof response.data.log !== 'undefined') {
                logBox.val(response.data.log);
                logBox.css('color', response.success ? 'green' : 'red');
            } else {
                 logBox.val('پاسخ نامعتبر از سرور (JSON?).\nResponse:\n' + JSON.stringify(response, null, 2));
                 logBox.css('color', 'red'); console.error("Invalid SMS test response:", response);
            }
            button.prop('disabled', false).text(originalButtonText);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX Fail (SMS):', jqXHR, textStatus, errorThrown);
             var errorDetails = jqXHR.responseText;
             var readableError = 'AJAX Error: ' + textStatus + ' (' + jqXHR.status + ') - ' + errorThrown;
             try {
                var errorObj = JSON.parse(jqXHR.responseText);
                if(errorObj && errorObj.data && errorObj.data.log) readableError += '\n--- Server Log ---\n' + errorObj.data.log;
                else if (errorObj && errorObj.data) readableError += '\n--- Server Data ---\n' + JSON.stringify(errorObj.data, null, 2);
                else readableError += '\n--- Raw Response ---\n' + jqXHR.responseText;
             } catch(e) { readableError += '\n--- Raw Response (first 500 chars) ---\n' + (jqXHR.responseText ? jqXHR.responseText.substring(0, 500) : 'Empty'); }
             readableError += '\n\nCheck PHP error logs & IPPanel settings.';
             logBox.val(readableError);
             logBox.css('color', 'red'); button.prop('disabled', false).text(originalButtonText); console.error("AJAX Error SMS test full:", jqXHR);
        });
    });

});
