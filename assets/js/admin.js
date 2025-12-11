jQuery(document).ready(function ($) {

    /**
     * =======================================================
     * 1. مدیریت آکاردئون (باز و بسته کردن پنل‌ها)
     * =======================================================
     */
    $('.cpp-accordion-header').on('click', function () {
        $(this).toggleClass('active').next('.cpp-accordion-content').slideToggle(300);
    });

    // بستن پیش‌فرض پنل‌ها اگر خطایی وجود نداشته باشد
    if ($('.cpp-accordion-content').length && !$('.cpp-accordion-content').find('.notice-error, .error').length && !window.location.hash) {
       $('.cpp-accordion-content').hide(); 
       $('.cpp-accordion-header').removeClass('active');
    }
    
    // باز کردن پنل اگر لینک مستقیم (Hash) وجود دارد
    if (window.location.hash) {
        var targetAccordion = $(window.location.hash);
        if (targetAccordion.hasClass('cpp-accordion-content')) {
            targetAccordion.show();
            targetAccordion.prev('.cpp-accordion-header').addClass('active');
        }
    }


    /**
     * =======================================================
     * 2. مدیریت آپلودر رسانه وردپرس
     * =======================================================
     */
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

        // اگر آپلودر قبلاً ساخته شده، آن را باز کن
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: 'انتخاب تصویر محصول/دسته‌بندی',
            button: { text: 'استفاده از این تصویر' },
            multiple: false
        });

        mediaUploader.on('select', function () {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            input_field.val(attachment.url).trigger('change');
            if(preview_img_container.length) {
                preview_img_container.html('<img src="' + attachment.url + '" style="max-width: 100px; margin-top: 10px; border:1px solid #ddd; padding:2px;">');
            }
        });
        mediaUploader.open();
    });


    /**
     * =======================================================
     * 3. ویرایش سریع (Quick Edit) در جدول محصولات
     * =======================================================
     */
    $(document).on('dblclick', '.cpp-quick-edit, .cpp-quick-edit-select', function () {
        var cell = $(this);
        if (cell.hasClass('editing') || cell.closest('td').hasClass('editing-td')) return;

        var id = cell.data('id');
        var field = cell.data('field');
        var table_type = cell.data('table-type');
        var original_content = cell.html(); 
        var original_text = cell.text().trim();
        var input_element;

        // حالت ۱: فیلد انتخابی (مثل وضعیت فعال/غیرفعال)
        if (cell.hasClass('cpp-quick-edit-select')) {
             cell.addClass('editing');
             input_element = $('<select>').addClass('cpp-quick-edit-input');
             var options = (table_type === 'orders') ? cpp_admin_vars.order_statuses : cpp_admin_vars.product_statuses;
             
             $.each(options, function (val, text) {
                var isSelected = (val == cell.data('current')) ? 'selected' : '';
                input_element.append('<option value="' + val + '" ' + isSelected + '>' + text + '</option>');
             });

        // حالت ۲: ویرایش بازه قیمت (حداقل و حداکثر)
        } else if (field === 'min_price' || field === 'max_price') {
             var td = cell.closest('td');
             td.addClass('editing-td');
             
             var min_val = td.find('[data-field="min_price"]').text().trim();
             var max_val = td.find('[data-field="max_price"]').text().trim();

             var container = $('<div>').css({display:'flex', gap:'5px', alignItems:'center'});
             var input_min = $('<input>').attr({type:'text', class:'cpp-quick-edit-input small-text', 'data-field':'min_price', value:min_val, placeholder:'حداقل'});
             var input_max = $('<input>').attr({type:'text', class:'cpp-quick-edit-input small-text', 'data-field':'max_price', value:max_val, placeholder:'حداکثر'});
             
             container.append(input_min).append('<span>-</span>').append(input_max);
             input_element = container;
             
             // تغییر سلول هدف به کل TD
             cell = td; 
             original_content = td.html(); // ذخیره محتوای اصلی برای لغو

        // حالت ۳: متن ساده (نام، قیمت پایه، توضیحات و...)
        } else {
            cell.addClass('editing');
            if (field === 'admin_note' || field === 'description') {
                input_element = $('<textarea>').addClass('cpp-quick-edit-input').val(original_text).css('min-height','60px');
            } else {
                input_element = $('<input>').attr('type', 'text').addClass('cpp-quick-edit-input').val(original_text);
            }
        }

        // دکمه‌های ذخیره و لغو
        var btn_save = $('<button>').addClass('button button-primary button-small').text(cpp_admin_vars.i18n.save).css('margin-right','5px');
        var btn_cancel = $('<button>').addClass('button button-secondary button-small').text(cpp_admin_vars.i18n.cancel);
        var btn_wrap = $('<div>').addClass('cpp-quick-edit-buttons').css('margin-top','5px').append(btn_save).append(btn_cancel);

        cell.empty().append(input_element).append(btn_wrap);
        cell.find('input, select, textarea').first().focus();

        // عملیات ذخیره
        btn_save.on('click', function () {
             if (field === 'min_price' || field === 'max_price') {
                 // ذخیره دو فیلد همزمان
                 var new_min = cell.find('input[data-field="min_price"]').val();
                 var new_max = cell.find('input[data-field="max_price"]').val();
                 
                 cell.text(cpp_admin_vars.i18n.saving);
                 
                 $.when(
                     $.post(cpp_admin_vars.ajax_url, { action: 'cpp_quick_update', security: cpp_admin_vars.nonce, id: id, field: 'min_price', value: new_min, table_type: table_type }),
                     $.post(cpp_admin_vars.ajax_url, { action: 'cpp_quick_update', security: cpp_admin_vars.nonce, id: id, field: 'max_price', value: new_max, table_type: table_type })
                 ).done(function() {
                     window.location.reload();
                 }).fail(function() {
                     alert(cpp_admin_vars.i18n.serverError);
                     window.location.reload();
                 });
             } else {
                 // ذخیره فیلد تکی
                 var new_val = cell.find('.cpp-quick-edit-input').val();
                 cell.text(cpp_admin_vars.i18n.saving);
                 $.post(cpp_admin_vars.ajax_url, {
                    action: 'cpp_quick_update', security: cpp_admin_vars.nonce, id: id, field: field, value: new_val, table_type: table_type
                 }, function (res) {
                    if (res.success) {
                        window.location.reload();
                    } else {
                        alert(res.data.message || cpp_admin_vars.i18n.error);
                        cell.html(original_content);
                        cell.removeClass('editing editing-td');
                    }
                 }).fail(function () {
                     alert(cpp_admin_vars.i18n.serverError);
                     cell.html(original_content);
                     cell.removeClass('editing editing-td');
                 });
             }
         });

        // عملیات لغو
        btn_cancel.on('click', function () {
             cell.html(original_content);
             cell.removeClass('editing editing-td');
         });
    });


    /**
     * =======================================================
     * 4. مدیریت نمودار پیشرفته (Chart.js)
     * شامل: فیلتر زمانی، دانلود، زوم، لوگو، هاشور رنگی
     * =======================================================
     */
    var chartInstance = null;
    var fullChartData = null; // نگهداری کل داده‌ها برای فیلتر سمت کلاینت

    $(document).on('click', '.cpp-show-chart', function (e) {
        e.preventDefault();
        var product_id = $(this).data('product-id');
        
        // ساخت ساختار مودال اگر وجود ندارد (یکبار ساخته می‌شود)
        if ($('#cpp-chart-modal').length === 0) {
             var modalHTML = 
                '<div id="cpp-chart-modal" class="cpp-modal-overlay" style="display:none;">' +
                    '<div class="cpp-modal-container cpp-chart-background">' +
                        '<span class="cpp-close-modal">×</span>' +
                        '<h2>نمودار تغییرات قیمت</h2>' +
                        // نوار ابزار: فیلترها و دانلود
                        '<div class="cpp-chart-toolbar">' +
                            '<button class="button cpp-chart-filter active" data-range="all">همه</button> ' +
                            '<button class="button cpp-chart-filter" data-range="12">۱ سال</button> ' +
                            '<button class="button cpp-chart-filter" data-range="6">۶ ماه</button> ' +
                            '<button class="button cpp-chart-filter" data-range="3">۳ ماه</button> ' +
                            '<button class="button cpp-chart-filter" data-range="1">۱ ماه</button> ' +
                            '<button class="button cpp-chart-filter" data-range="0.25">۱ هفته</button> ' +
                            '<button class="button button-primary cpp-chart-download">دانلود نمودار</button>' +
                        '</div>' +
                        // کانتینر نمودار
                        '<div class="cpp-chart-modal-content">' +
                            '<div class="cpp-chart-bg"></div>' + // محل قرارگیری لوگو
                            '<canvas id="cppPriceChart"></canvas>' +
                        '</div>' +
                    '</div>' +
                '</div>';
             $('body').append(modalHTML);
        }
        
        var modal = $('#cpp-chart-modal');
        var canvas = modal.find('#cppPriceChart');
        var bg_layer = modal.find('.cpp-chart-bg');

        // تنظیم لوگو در پس‌زمینه (اگر در تنظیمات ست شده باشد)
        if (cpp_admin_vars.logo_url) {
            bg_layer.css({
                'background-image': 'url(' + cpp_admin_vars.logo_url + ')',
                'background-repeat': 'no-repeat',
                'background-position': 'center center',
                'background-size': '200px',
                'opacity': '0.1', // شفافیت لوگو
                'position': 'absolute', 'top':0, 'left':0, 'width':'100%', 'height':'100%', 'z-index':0
            });
            canvas.css({'position':'relative', 'z-index':1});
        }

        modal.css('display', 'flex'); // نمایش مودال

        // ریست کردن نمودار قبلی
        if (chartInstance) { 
            chartInstance.destroy(); 
            chartInstance = null; 
        }
        
        // دریافت داده‌ها از سرور
        $.get(cpp_admin_vars.ajax_url, { 
            action: 'cpp_get_chart_data', 
            product_id: product_id, 
            security: cpp_admin_vars.nonce 
        }).done(function (response) {
            if (response.success && response.data.labels && response.data.labels.length > 0) {
                 fullChartData = response.data; // ذخیره داده خام
                 renderChart(fullChartData, canvas, 'all'); // رسم اولیه (همه داده‌ها)
            } else {
                 alert(response.data.message || 'داده‌ای برای نمایش وجود ندارد.');
                 modal.hide();
            }
        }).fail(function () {
             alert('خطا در دریافت اطلاعات نمودار.');
             modal.hide();
        });
    });

    // رویداد کلیک روی دکمه‌های فیلتر زمانی
    $(document).on('click', '.cpp-chart-filter', function() {
        $('.cpp-chart-filter').removeClass('active');
        $(this).addClass('active');
        
        var range = $(this).data('range');
        var canvas = $('#cppPriceChart');
        
        if (chartInstance) chartInstance.destroy();
        renderChart(fullChartData, canvas, range);
    });

    // رویداد کلیک دکمه دانلود
    $(document).on('click', '.cpp-chart-download', function() {
        if (!chartInstance) return;
        var link = document.createElement('a');
        link.href = chartInstance.toBase64Image();
        link.download = 'price-history-chart.png';
        link.click();
    });

    // تابع اصلی رسم نمودار
    function renderChart(data, ctx, range) {
         var labels = data.labels;
         var prices = data.prices;
         var min_prices = data.min_prices;
         var max_prices = data.max_prices;

         // فیلتر کردن داده‌ها بر اساس بازه زمانی (برش آرایه)
         if (range !== 'all') {
             var totalPoints = labels.length;
             // فرض: هر ماه حدود ۳۰ نقطه داده دارد
             var pointsToShow = Math.floor(parseFloat(range) * 30); 
             if (totalPoints > pointsToShow) {
                 var start = totalPoints - pointsToShow;
                 labels = labels.slice(start);
                 prices = prices.slice(start);
                 min_prices = min_prices.slice(start);
                 max_prices = max_prices.slice(start);
             }
         }

         // بررسی اینکه آیا نمودار باید "تک خطی" باشد یا "بازه ای"
         // اگر در تمام نقاط، حداقل و حداکثر برابر باشند، یعنی محصول تک قیمتی است.
         var isSinglePrice = true;
         if (min_prices && max_prices && min_prices.length > 0) {
             for(var i=0; i<min_prices.length; i++) {
                 if (min_prices[i] !== max_prices[i] && min_prices[i] !== null && max_prices[i] !== null) {
                     isSinglePrice = false;
                     break;
                 }
             }
         } else {
             isSinglePrice = false;
         }

         var datasets = [];
         
         if (isSinglePrice) {
             // حالت تک خطی
             datasets.push({ 
                 label: 'قیمت', 
                 data: prices, 
                 borderColor: 'rgb(75, 192, 192)', 
                 tension: 0.1, 
                 borderWidth: 3, 
                 fill: false 
             });
         } else {
             // حالت سه خطی (با هاشور و رنگ‌بندی)
             
             // 1. خط حداقل (پایین)
             if (min_prices) {
                 datasets.push({ 
                     label: 'حداقل', 
                     data: min_prices, 
                     borderColor: 'rgba(54, 162, 235, 0.8)', // آبی
                     borderDash: [5, 5], 
                     pointRadius: 0, 
                     fill: false 
                 });
             }
             
             // 2. خط پایه (وسط) -> فاصله تا حداقل را آبی پر می‌کند
             if (prices) {
                 datasets.push({ 
                     label: 'قیمت پایه', 
                     data: prices, 
                     borderColor: 'rgb(75, 192, 192)', // سبزآبی
                     tension: 0.1, 
                     borderWidth: 3, 
                     fill: {
                         target: 0, // ایندکس دیتاست حداقل
                         above: 'rgba(54, 162, 235, 0.15)' // رنگ آبی کمرنگ
                     }
                 });
             }
             
             // 3. خط حداکثر (بالا) -> فاصله تا پایه را قرمز پر می‌کند
             if (max_prices) {
                 datasets.push({ 
                     label: 'حداکثر', 
                     data: max_prices, 
                     borderColor: 'rgba(255, 99, 132, 0.8)', // قرمز
                     borderDash: [5, 5], 
                     pointRadius: 0, 
                     fill: {
                         target: 1, // ایندکس دیتاست پایه
                         above: 'rgba(255, 99, 132, 0.15)' // رنگ قرمز کمرنگ
                     }
                 });
             }
         }
         
         // ساخت آبجکت نمودار
         chartInstance = new Chart(ctx, { 
             type: 'line', 
             data: { labels: labels, datasets: datasets }, 
             options: { 
                 responsive: true, 
                 maintainAspectRatio: false,
                 interaction: {
                     mode: 'index',
                     intersect: false,
                 },
                 plugins: {
                     legend: { display: true, position: 'top' },
                     filler: { propagate: false } // جلوگیری از تداخل رنگ‌ها
                 },
                 scales: { 
                     y: { beginAtZero: false } // محور Y از صفر شروع نشود (زوم بهتر روی تغییرات)
                 }
             } 
         });
    }


    /**
     * =======================================================
     * 5. سایر پاپ‌آپ‌ها و دکمه‌ها
     * =======================================================
     */
    
    // بستن مودال
    $(document).on('click', '.cpp-close-modal, .cpp-modal-overlay', function (e) {
        if (e.target === this || $(this).hasClass('cpp-close-modal')) {
            $('.cpp-modal-overlay').hide();
            if (chartInstance) { chartInstance.destroy(); chartInstance = null; }
        }
    });

    // دکمه "ویرایش کامل" (فرم مودال)
    $(document).on('click', '.cpp-edit-button, .cpp-edit-cat-button', function (e) {
        e.preventDefault();
        var btn = $(this);
        var ajax_data = { security: cpp_admin_vars.nonce };

        if (btn.hasClass('cpp-edit-button')) {
            ajax_data.action = 'cpp_fetch_product_edit_form';
            ajax_data.id = btn.data('product-id');
        } else {
            ajax_data.action = 'cpp_fetch_category_edit_form';
            ajax_data.id = btn.data('cat-id');
        }
        
        // ساخت مودال ویرایش اگر نیست
        if ($('#cpp-edit-modal').length === 0) {
            $('body').append('<div id="cpp-edit-modal" class="cpp-modal-overlay" style="display: none;"><div class="cpp-modal-container"><span class="cpp-close-modal">×</span><div class="cpp-edit-modal-content"></div></div></div>');
        }
        var modal = $('#cpp-edit-modal');
        modal.css('display', 'flex').addClass('loading').find('.cpp-edit-modal-content').html('<p style="text-align:center;padding:20px;">' + cpp_admin_vars.i18n.loadingForm + '</p>');

        $.get(cpp_admin_vars.ajax_url, ajax_data).done(function (res) {
            modal.removeClass('loading');
            if (res.success) {
                modal.find('.cpp-edit-modal-content').html(res.data.html);
                // فعال‌سازی مجدد آپلودر و کالرپیکر برای محتوای جدید
                if (window.cpp_init_media_uploader) window.cpp_init_media_uploader();
                if (modal.find('.cpp-color-picker').length) modal.find('.cpp-color-picker').wpColorPicker();
            } else {
                 modal.find('.cpp-edit-modal-content').html('<p style="color:red;text-align:center;">' + (res.data.message || 'خطا') + '</p>');
            }
        });
    });

    // ارسال فرم ویرایش (محصول/دسته‌بندی)
    $(document).on('submit', '#cpp-edit-product-form, #cpp-edit-category-form', function (e) {
        e.preventDefault();
        var form = $(this);
        var action_name = (form.attr('id') === 'cpp-edit-product-form') ? 'cpp_handle_edit_product_ajax' : 'cpp_handle_edit_category_ajax';
        var btn = form.find('input[type="submit"]');

        btn.prop('disabled', true).val(cpp_admin_vars.i18n.saving);
        
        $.post(cpp_admin_vars.ajax_url, form.serialize() + '&action=' + action_name, function(res){
            if(res.success) {
                alert('تغییرات با موفقیت ذخیره شد.');
                $('#cpp-edit-modal').hide();
                window.location.reload();
            } else {
                alert(res.data.message || cpp_admin_vars.i18n.error);
                btn.prop('disabled', false).val(cpp_admin_vars.i18n.save);
            }
        }).fail(function(){
            alert(cpp_admin_vars.i18n.serverError);
            btn.prop('disabled', false).val(cpp_admin_vars.i18n.save);
        });
    });

    // دکمه‌های تست ایمیل و پیامک (در تنظیمات)
    $('#cpp-test-email-btn, #cpp-test-sms-btn').click(function(){
        var btn = $(this);
        var log_area = btn.siblings('textarea');
        var action_name = (btn.attr('id') === 'cpp-test-email-btn') ? 'cpp_test_email' : 'cpp_test_sms';
        
        btn.prop('disabled', true).text('در حال ارسال...');
        
        $.post(cpp_admin_vars.ajax_url, { 
            action: action_name, 
            security: cpp_admin_vars.nonce 
        }, function(res){
            log_area.val(res.data.log || JSON.stringify(res));
            btn.prop('disabled', false).text('ارسال تست مجدد');
        }).fail(function(x){
            log_area.val('خطا: ' + x.responseText);
            btn.prop('disabled', false).text('ارسال تست مجدد');
        });
    });

});
