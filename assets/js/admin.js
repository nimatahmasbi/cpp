jQuery(document).ready(function ($) {

    // 1. آکاردئون
    $('.cpp-accordion-header').on('click', function () {
        $(this).toggleClass('active').next('.cpp-accordion-content').slideToggle(300);
    });

    if ($('.cpp-accordion-content').length && !$('.cpp-accordion-content').find('.notice-error, .error').length && !window.location.hash) {
       $('.cpp-accordion-content').hide(); 
       $('.cpp-accordion-header').removeClass('active');
    }
    
    if (window.location.hash) {
        var targetAccordion = $(window.location.hash);
        if (targetAccordion.hasClass('cpp-accordion-content')) {
            targetAccordion.show();
            targetAccordion.prev('.cpp-accordion-header').addClass('active');
        }
    }

    // 2. آپلودر تصویر
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

        if (!input_field.length) return;

        mediaUploader = wp.media({ title: 'انتخاب تصویر', button: { text: 'استفاده' }, multiple: false });

        (function(target_input, target_preview) {
            mediaUploader.off('select'); 
            mediaUploader.on('select', function () {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                target_input.val(attachment.url).trigger('change');
                 if(target_preview.length) {
                    target_preview.html('<img src="' + attachment.url + '" style="max-width: 100px; margin-top: 10px;">');
                 }
            });
            mediaUploader.open();
        })(input_field, preview_img_container);
    });

    // 3. ویرایش سریع
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
             var options_list = (table_type === 'orders') ? cpp_admin_vars.order_statuses : cpp_admin_vars.product_statuses;
             if (!options_list) options_list = {};

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
             var min_input = $('<input type="text">').addClass('cpp-quick-edit-input small-text').val(min_val).attr('data-field', 'min_price');
             var max_input = $('<input type="text">').addClass('cpp-quick-edit-input small-text').val(max_val).attr('data-field', 'max_price');
             
             container.append(min_input).append(' - ').append(max_input);
             input_element = container;
             td.css('width', 'auto'); 

        } else {
            cell.data('original-content', original_html).addClass('editing');
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
             if (field === 'min_price' || field === 'max_price') performSavePriceRange(td, id, table_type);
             else performSave(cell, id, field, table_type);
         });
        cancel_btn.on('click', function () {
             if (field === 'min_price' || field === 'max_price') td.removeClass('editing-td').html(td.data('original-content'));
             else cell.removeClass('editing').html(cell.data('original-content'));
         });
         $(input_element).find('input, select, textarea').on('keydown', function (e) {
            if (e.key === 'Escape') cancel_btn.click();
            else if (e.key === 'Enter' && !$(this).is('textarea')) { e.preventDefault(); save_btn.click(); }
        });
    });

    function performSavePriceRange(td, id, table_type) {
        var min_input = td.find('input[data-field="min_price"]');
        var max_input = td.find('input[data-field="max_price"]');
        var min_value = min_input.val();
        var max_value = max_input.val();
        var original_html = td.data('original-content');

        td.html(cpp_admin_vars.i18n.saving || 'در حال ذخیره...');

        var p1 = $.post(cpp_admin_vars.ajax_url, { action: 'cpp_quick_update', security: cpp_admin_vars.nonce, id: id, field: 'min_price', value: min_value, table_type: table_type });
        var p2 = $.post(cpp_admin_vars.ajax_url, { action: 'cpp_quick_update', security: cpp_admin_vars.nonce, id: id, field: 'max_price', value: max_value, table_type: table_type });

        $.when(p1, p2).done(function (r1, r2) {
            td.removeClass('editing-td');
            if (r1[0].success && r2[0].success) {
                var s1 = $('<span>').addClass('cpp-quick-edit').attr('data-id', id).attr('data-field', 'min_price').attr('data-table-type', table_type).text(min_value);
                var s2 = $('<span>').addClass('cpp-quick-edit').attr('data-id', id).attr('data-field', 'max_price').attr('data-table-type', table_type).text(max_value);
                td.html('').append(s1).append(' - ').append(s2);
                if (r1[0].data.new_time) td.closest('tr').find('.cpp-last-update').text(r1[0].data.new_time);
            } else { alert('خطا در ذخیره'); td.html(original_html); }
        }).fail(function () { td.removeClass('editing-td'); alert(cpp_admin_vars.i18n.serverError); td.html(original_html); });
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
                var display_val = response.data.display_value || new_value; 
                if (cell.hasClass('cpp-quick-edit-select')) {
                     var opts = (table_type === 'orders') ? cpp_admin_vars.order_statuses : cpp_admin_vars.product_statuses;
                     display_val = opts[new_value] || new_value;
                }
                cell.data('current', new_value).html(display_val);
                if (response.data.new_time) cell.closest('tr').find('.cpp-last-update').text(response.data.new_time);
            } else { alert('خطا: ' + (response.data.message || 'Error')); cell.html(original_html); }
        }).fail(function () { cell.removeClass('editing'); alert(cpp_admin_vars.i18n.serverError); cell.html(original_html); });
    }

    // 4. پاپ‌آپ‌ها
    function openEditModal(ajax_data) {
        if ($('#cpp-edit-modal').length === 0) $('body').append('<div id="cpp-edit-modal" class="cpp-modal-overlay" style="display: none;"><div class="cpp-modal-container"><span class="cpp-close-modal">×</span><div class="cpp-edit-modal-content"></div></div></div>');
        var modal = $('#cpp-edit-modal');
        modal.css('display', 'flex').addClass('loading').find('.cpp-edit-modal-content').html('<p style="text-align:center;padding:20px;">' + (cpp_admin_vars.i18n.loadingForm || 'بارگذاری...') + '</p>');

        $.get(cpp_admin_vars.ajax_url, ajax_data).done(function (res) {
            modal.removeClass('loading');
            if (res.success) {
                modal.find('.cpp-edit-modal-content').html(res.data.html);
                if (typeof window.cpp_init_media_uploader === 'function') window.cpp_init_media_uploader();
                if (modal.find('.cpp-color-picker').length) modal.find('.cpp-color-picker').wpColorPicker();
            } else modal.find('.cpp-edit-modal-content').html('<p style="color:red;text-align:center;">' + (res.data.message || 'خطا') + '</p>');
        }).fail(function () { modal.removeClass('loading').find('.cpp-edit-modal-content').html('<p style="color:red;text-align:center;">خطای سرور</p>'); });
    }

    $(document).on('click', '.cpp-close-modal, .cpp-modal-overlay', function (e) {
        if (e.target === this || $(this).hasClass('cpp-close-modal')) {
            $('.cpp-modal-overlay').hide();
            if (typeof chartInstance !== 'undefined' && chartInstance) { chartInstance.destroy(); chartInstance = null; }
        }
    });

    $(document).on('click', '.cpp-edit-button, .cpp-edit-cat-button', function (e) {
        e.preventDefault();
        var btn = $(this);
        var data = { security: cpp_admin_vars.nonce };
        if (btn.hasClass('cpp-edit-button')) { data.action = 'cpp_fetch_product_edit_form'; data.id = btn.data('product-id'); }
        else { data.action = 'cpp_fetch_category_edit_form'; data.id = btn.data('cat-id'); }
        openEditModal(data);
    });

    // -----------------------------------------------------------
    // 5. رسم نمودار (با قابلیت‌های جدید: زوم، دانلود، فیلتر)
    // -----------------------------------------------------------
    var chartInstance = null;
    var fullChartData = null; // ذخیره کل داده‌ها برای فیلتر کردن

    $(document).on('click', '.cpp-show-chart', function (e) {
        e.preventDefault();
        var pid = $(this).data('product-id');
        
        // افزودن ابزارها به مودال
        if ($('#cpp-chart-modal').length === 0) {
             var modalHtml = '<div id="cpp-chart-modal" class="cpp-modal-overlay" style="display:none;">' +
                '<div class="cpp-modal-container cpp-chart-background">' +
                '<span class="cpp-close-modal">×</span>' +
                '<h2>نمودار قیمت</h2>' +
                '<div class="cpp-chart-toolbar" style="margin-bottom:10px; text-align:left; direction:ltr;">' +
                    '<button class="button cpp-chart-filter active" data-range="all">همه</button> ' +
                    '<button class="button cpp-chart-filter" data-range="12">۱ سال</button> ' +
                    '<button class="button cpp-chart-filter" data-range="6">۶ ماه</button> ' +
                    '<button class="button cpp-chart-filter" data-range="3">۳ ماه</button> ' +
                    '<button class="button cpp-chart-filter" data-range="1">۱ ماه</button> ' +
                    '<button class="button cpp-chart-filter" data-range="0.25">۱ هفته</button> ' +
                    '<button class="button button-primary cpp-chart-download" style="margin-left:10px;">دانلود نمودار</button>' +
                '</div>' +
                '<div class="cpp-chart-modal-content">' +
                    '<div class="cpp-chart-bg"></div><canvas id="cppPriceChart"></canvas>' +
                '</div>' +
                '</div></div>';
             $('body').append(modalHtml);
        }
        
        var modal = $('#cpp-chart-modal');
        var ctx = modal.find('#cppPriceChart');
        
        if (cpp_admin_vars.logo_url) {
            modal.find('.cpp-chart-bg').css({ 'background-image': 'url(' + cpp_admin_vars.logo_url + ')', 'background-repeat': 'no-repeat', 'background-position': 'center center', 'background-size': '150px', 'opacity': '0.1' });
        }

        modal.css('display', 'flex');
        if (chartInstance) { chartInstance.destroy(); chartInstance = null; }
        
        $.get(cpp_admin_vars.ajax_url, { action: 'cpp_get_chart_data', product_id: pid, security: cpp_admin_vars.nonce }).done(function (res) {
            if (res.success && res.data.labels) {
                 fullChartData = res.data; // ذخیره داده اصلی
                 renderChart(fullChartData, ctx, 'all'); // نمایش پیش‌فرض
            } else { alert(res.data.message || 'داده‌ای یافت نشد'); modal.hide(); }
        }).fail(function () { alert('خطای دریافت داده'); modal.hide(); });
    });

    // فیلتر کردن داده‌ها
    $(document).on('click', '.cpp-chart-filter', function() {
        $('.cpp-chart-filter').removeClass('active');
        $(this).addClass('active');
        var range = $(this).data('range');
        var ctx = $('#cppPriceChart');
        if (chartInstance) chartInstance.destroy();
        renderChart(fullChartData, ctx, range);
    });

    // دانلود نمودار
    $(document).on('click', '.cpp-chart-download', function() {
        var link = document.createElement('a');
        link.href = chartInstance.toBase64Image();
        link.download = 'chart.png';
        link.click();
    });

    function renderChart(data, ctx, range) {
         var labels = data.labels;
         var prices = data.prices;
         var min_prices = data.min_prices;
         var max_prices = data.max_prices;

         // منطق فیلتر زمانی
         if (range !== 'all') {
             var totalPoints = labels.length;
             var pointsToShow = Math.floor(parseFloat(range) * 30); // 1 ماه = 30 نقطه
             if (totalPoints > pointsToShow) {
                 var start = totalPoints - pointsToShow;
                 labels = labels.slice(start);
                 prices = prices.slice(start);
                 min_prices = min_prices.slice(start);
                 max_prices = max_prices.slice(start);
             }
         }

         var ds = [];
         if (min_prices) ds.push({ label: 'حداقل', data: min_prices, borderColor: 'rgba(54, 162, 235, 0.8)', borderDash: [5,5], pointRadius: 0, fill: false });
         if (prices) ds.push({ label: 'قیمت پایه', data: prices, borderColor: 'rgb(75, 192, 192)', tension: 0.1, borderWidth: 3, fill: { target: 0, above: 'rgba(54, 162, 235, 0.2)' } });
         if (max_prices) ds.push({ label: 'حداکثر', data: max_prices, borderColor: 'rgba(255, 99, 132, 0.8)', borderDash: [5,5], pointRadius: 0, fill: { target: 1, above: 'rgba(255, 99, 132, 0.2)' } });
         
         chartInstance = new Chart(ctx, { 
             type: 'line', 
             data: { labels: labels, datasets: ds }, 
             options: { 
                 responsive: true, 
                 maintainAspectRatio: false,
                 interaction: { mode: 'index', intersect: false },
                 plugins: { filler: { propagate: false } },
                 scales: { y: { beginAtZero: false } }
             } 
         });
    }

    // ارسال فرم‌ها و تست‌ها
    $(document).on('submit', '#cpp-edit-product-form, #cpp-edit-category-form', function (e) {
        e.preventDefault(); var form = $(this); var action = form.attr('id') === 'cpp-edit-product-form' ? 'cpp_handle_edit_product_ajax' : 'cpp_handle_edit_category_ajax'; var btn = form.find('input[type="submit"]'); btn.prop('disabled', true).val(cpp_admin_vars.i18n.saving);
        $.post(cpp_admin_vars.ajax_url, form.serialize() + '&action=' + action, function(res){ if(res.success) { alert('ذخیره شد'); $('#cpp-edit-modal').hide(); window.location.reload(); } else { alert(res.data.message || 'خطا'); btn.prop('disabled', false).val(cpp_admin_vars.i18n.save); } }).fail(function(){ alert(cpp_admin_vars.i18n.serverError); btn.prop('disabled', false).val(cpp_admin_vars.i18n.save); });
    });

    $('#cpp-load-email-template').click(function(){ if(confirm('مطمئنید؟')) { var t = $('#cpp-email-template-html').html(); if(typeof tinymce!='undefined' && tinymce.get('cpp_email_body_template')) tinymce.get('cpp_email_body_template').setContent(t); else $('#cpp_email_body_template').val(t); } });
    $('#cpp-test-email-btn, #cpp-test-sms-btn').click(function(){ var btn = $(this), log = btn.siblings('textarea'), act = (btn.attr('id')==='cpp-test-email-btn')?'cpp_test_email':'cpp_test_sms'; btn.prop('disabled', true); log.val('ارسال...'); $.post(cpp_admin_vars.ajax_url, { action: act, security: cpp_admin_vars.nonce }, function(res){ log.val(res.data.log || JSON.stringify(res)); btn.prop('disabled', false); }).fail(function(x){ log.val('خطا: '+x.responseText); btn.prop('disabled', false); }); });
});
