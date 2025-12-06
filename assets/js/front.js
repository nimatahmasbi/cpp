jQuery(document).ready(function($) {
    var frontChartInstance = null;
    var fullFrontChartData = null; // ذخیره داده‌ها برای فیلتر سمت کاربر

    // --- مدیریت کپچا ---
    function refreshCaptcha() {
        var captchaElement = $('.cpp-captcha-code');
        var captchaInput = $('#captcha_input');
        if (!captchaElement.length) return;

        captchaElement.text('...'); 
        captchaInput.val(''); 

        $.post(cpp_front_vars.ajax_url, {
            action: 'cpp_get_captcha', 
            nonce: cpp_front_vars.nonce 
        }, function(response) {
            if (response.success && response.data && response.data.code) {
                captchaElement.text(response.data.code); 
            } else {
                captchaElement.text('خطا');
            }
        }).fail(function(jqXHR) {
            captchaElement.text('خطا');
        });
    }

    // --- باز کردن مودال ثبت سفارش ---
    $(document).on('click', '.cpp-order-btn', function() {
        var button = $(this); 
        var productId = button.data('product-id');
        var productName = button.data('product-name');
        var productUnit = button.data('product-unit');
        var productLocation = button.data('product-location');

        var modal = $('#cpp-order-modal');
        modal.find('#cpp-order-product-id').val(productId);
        modal.find('.cpp-modal-product-name').text(productName);
        modal.find('.cpp-modal-product-location').text(productLocation ? productLocation : ''); 
        modal.find('.cpp-modal-product-unit').text(productUnit ? productUnit : ''); 

        modal.find('.cpp-form-message').remove();
        modal.find('form')[0].reset();
        refreshCaptcha(); 
        modal.show(); // یا css('display', 'flex')
    });

    $(document).on('click', '.cpp-refresh-captcha', refreshCaptcha);

    // --- ارسال فرم سفارش ---
    $('#cpp-order-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var button = form.find('button[type="submit"]');
        var formData = form.serialize(); 
        var originalButtonText = button.text();
        
        button.prop('disabled', true).text(cpp_front_vars.i18n.sending || 'ارسال...');
        form.find('.cpp-form-message').remove();

        $.post(cpp_front_vars.ajax_url, formData + '&action=cpp_submit_order&nonce=' + cpp_front_vars.nonce, function(response) {
            if (response.success) {
                form.before('<div class="cpp-form-message cpp-success">' + response.data.message + '</div>');
                setTimeout(function() {
                    $('#cpp-order-modal').hide();
                    button.prop('disabled', false).text(originalButtonText);
                }, 2000); 
            } else {
                var msg = response.data.message || 'خطا';
                form.before('<div class="cpp-form-message cpp-error">' + msg + '</div>');
                button.prop('disabled', false).text(originalButtonText);
                if (response.data.code === 'captcha_error') {
                    refreshCaptcha();
                }
            }
        }).fail(function() {
            form.before('<div class="cpp-form-message cpp-error">' + cpp_front_vars.i18n.server_error + '</div>');
            button.prop('disabled', false).text(originalButtonText);
        });
    });

    // --- مدیریت نمودار پیشرفته (سمت کاربر) ---
     $(document).on('click', '.cpp-chart-btn', function(e) {
        e.preventDefault();
        var productId = $(this).data('product-id');
        
        // ساخت HTML مودال با ابزارها اگر وجود ندارد
        if ($('#cpp-front-chart-modal').length === 0) {
             var modalHtml = '<div id="cpp-front-chart-modal" class="cpp-modal-overlay" style="display:none;">' +
                 '<div class="cpp-modal-container cpp-chart-container">' +
                 '<button class="cpp-modal-close">&times;</button>' +
                 '<h3>نمودار تغییرات قیمت</h3>' +
                 // نوار ابزار فیلتر و دانلود
                 '<div class="cpp-chart-toolbar" style="margin-bottom:10px; text-align:left; direction:ltr;">' +
                    '<button class="button cpp-chart-filter active" data-range="all">همه</button> ' +
                    '<button class="button cpp-chart-filter" data-range="12">۱ سال</button> ' +
                    '<button class="button cpp-chart-filter" data-range="6">۶ ماه</button> ' +
                    '<button class="button cpp-chart-filter" data-range="3">۳ ماه</button> ' +
                    '<button class="button cpp-chart-filter" data-range="1">۱ ماه</button> ' +
                    '<button class="button cpp-chart-filter" data-range="0.25">۱ هفته</button> ' +
                    '<button class="button button-primary cpp-chart-download" style="margin-left:10px;">دانلود</button>' +
                '</div>' +
                 '<div class="cpp-chart-inner"><div class="cpp-chart-bg"></div><canvas id="cppFrontPriceChart"></canvas></div>' +
                 '</div></div>';
             $('body').append(modalHtml);
        }

        var modal = $('#cpp-front-chart-modal');
        var chartCanvas = modal.find('#cppFrontPriceChart');
        var chartContainer = chartCanvas.parent(); 

        // اعمال لوگو
        if (cpp_front_vars.logo_url) {
            modal.find('.cpp-chart-bg').css({
                'background-image': 'url(' + cpp_front_vars.logo_url + ')',
                'background-repeat': 'no-repeat',
                'background-position': 'center center',
                'background-size': '200px',
                'opacity': '0.1',
                'position': 'absolute', 'top':0, 'left':0, 'width':'100%', 'height':'100%', 'z-index':0
            });
            chartCanvas.css({'position':'relative', 'z-index':1});
        }

        modal.css('display', 'flex'); 
        chartContainer.find('.chart-error, .chart-loading').remove(); 
        chartCanvas.show(); 

        if (frontChartInstance) { frontChartInstance.destroy(); frontChartInstance = null; }
        chartContainer.append('<p class="chart-loading" style="text-align:center;">' + cpp_front_vars.i18n.loading + '</p>');

        $.get(cpp_front_vars.ajax_url, { action: 'cpp_get_chart_data', product_id: productId, nonce: cpp_front_vars.nonce }, function(response) { 
            chartContainer.find('.chart-loading').remove(); 
            if (response.success && response.data && response.data.labels) {
                 fullFrontChartData = response.data;
                 renderFrontChart(fullFrontChartData, chartCanvas[0], 'all');
             } else {
                 chartCanvas.hide();
                 chartContainer.prepend('<p class="chart-error" style="color:red; text-align:center;">داده‌ای یافت نشد.</p>');
             }
        }).fail(function() {
            chartContainer.find('.chart-loading').remove(); 
            chartCanvas.hide();
            chartContainer.prepend('<p class="chart-error">خطای سرور</p>');
        });
    });

    // فیلتر نمودار
    $(document).on('click', '.cpp-chart-filter', function() {
        $('.cpp-chart-filter').removeClass('active');
        $(this).addClass('active');
        var range = $(this).data('range');
        var ctx = $('#cppFrontPriceChart')[0];
        
        if (frontChartInstance) frontChartInstance.destroy();
        renderFrontChart(fullFrontChartData, ctx, range);
    });

    // دانلود نمودار
    $(document).on('click', '.cpp-chart-download', function() {
        var link = document.createElement('a');
        link.href = frontChartInstance.toBase64Image();
        link.download = 'chart-front.png';
        link.click();
    });

    // تابع رسم نمودار
    function renderFrontChart(data, ctx, range) {
        var labels = data.labels;
        var prices = data.prices;
        var min_prices = data.min_prices;
        var max_prices = data.max_prices;

        // فیلتر زمانی
         if (range !== 'all') {
             var totalPoints = labels.length;
             var pointsToShow = Math.floor(parseFloat(range) * 30); 
             if (totalPoints > pointsToShow) {
                 var start = totalPoints - pointsToShow;
                 labels = labels.slice(start);
                 prices = prices.slice(start);
                 min_prices = min_prices.slice(start);
                 max_prices = max_prices.slice(start);
             }
         }

        var datasets = [];
        
        // 1. حداقل
        if (min_prices) {
            datasets.push({ 
                label: 'حداقل', 
                data: min_prices, 
                borderColor: 'rgba(54, 162, 235, 0.8)', 
                borderDash: [5, 5], 
                pointRadius: 0, 
                fill: false 
            });
        }
        
        // 2. پایه (پر کردن تا حداقل با آبی)
        if (prices) {
            datasets.push({ 
                label: 'قیمت پایه', 
                data: prices, 
                borderColor: 'rgb(75, 192, 192)', 
                tension: 0.1, 
                borderWidth: 3,
                fill: {
                    target: 0, 
                    above: 'rgba(54, 162, 235, 0.15)' 
                }
            });
        }
        
        // 3. حداکثر (پر کردن تا پایه با قرمز)
        if (max_prices) {
            datasets.push({ 
                label: 'حداکثر', 
                data: max_prices, 
                borderColor: 'rgba(255, 99, 132, 0.8)', 
                borderDash: [5, 5], 
                pointRadius: 0, 
                fill: {
                    target: 1, 
                    above: 'rgba(255, 99, 132, 0.15)' 
                }
            });
        }

        if(datasets.length === 0){
             $(ctx).parent().prepend('<p class="chart-error">داده‌ای وجود ندارد.</p>');
             $(ctx).hide(); return;
        }

        try {
            frontChartInstance = new Chart(ctx, {
                type: 'line',
                data: { labels: labels, datasets: datasets },
                options: {
                     responsive: true,
                     maintainAspectRatio: false,
                     spanGaps: true,
                     plugins: {
                          legend: { display: true, position: 'top' },
                          tooltip: { mode: 'index', intersect: false },
                          filler: { propagate: false }
                      },
                      scales: { y: { beginAtZero: false } }
                }
             });
        } catch(e) { console.error("Error creating chart:", e); }
    }

    // بستن مودال
    $(document).on('click', '.cpp-modal-close, .cpp-modal-overlay', function(e) {
        if (e.target === this || $(this).hasClass('cpp-modal-close')) {
            $('.cpp-modal-overlay').hide();
            if (frontChartInstance) { frontChartInstance.destroy(); frontChartInstance = null; }
        }
    });
    
    // --- مدیریت دکمه مشاهده بیشتر (Load More) ---
    $(document).on('click', '.cpp-view-more-btn', function() {
        var button = $(this);
        var wrapper = button.closest('.cpp-grid-view-wrapper');
        var currentPage = parseInt(button.data('page'), 10); 
        var nextPage = currentPage + 1;
        var shortcode_type = button.data('shortcode-type'); 
        var original_text = cpp_front_vars.i18n.view_more;

        button.prop('disabled', true).text(cpp_front_vars.i18n.loading);

        $.post(cpp_front_vars.ajax_url, {
            action: 'cpp_load_more_products',
            nonce: cpp_front_vars.nonce,
            page: nextPage, 
            shortcode_type: shortcode_type
        }, function(response) {
            if (response.success && response.data && response.data.html) {
                wrapper.find('.cpp-grid-view-table tbody').append(response.data.html);
                button.data('page', nextPage); 

                if (!response.data.has_more) {
                    button.text(cpp_front_vars.i18n.no_more_products).prop('disabled', true);
                     button.parent().hide(); 
                } else {
                    button.prop('disabled', false).text(original_text);
                }
            } else {
                button.text(cpp_front_vars.i18n.no_more_products).prop('disabled', true);
                 button.parent().hide(); 
            }
        }).fail(function(jqXHR, textStatus) {
            alert(cpp_front_vars.i18n.server_error || 'خطای سرور.');
            button.prop('disabled', false).text(original_text);
        });
    });

     // فیلتر دسته‌بندی (Grid View)
    $('.cpp-grid-view-filters .filter-btn').on('click', function(e){
        e.preventDefault();
        var $this = $(this);
        var catId = $this.data('cat-id');
        var wrapper = $this.closest('.cpp-grid-view-wrapper');
        wrapper.find('.cpp-grid-view-filters .filter-btn').removeClass('active');
        $this.addClass('active');
        if (catId === 'all') {
            wrapper.find('.cpp-grid-view-table .product-row').show();
        } else {
            wrapper.find('.cpp-grid-view-table .product-row').hide();
            wrapper.find('.cpp-grid-view-table .product-row[data-cat-id="' + catId + '"]').show();
        }
        wrapper.find('.cpp-grid-view-footer').toggle(catId === 'all'); 
    });

    // مقداردهی اولیه‌ی متون (اگر تعریف نشده باشند)
     cpp_front_vars.i18n = cpp_front_vars.i18n || {};
     cpp_front_vars.i18n.sending = cpp_front_vars.i18n.sending || 'در حال ارسال...';
     cpp_front_vars.i18n.server_error = cpp_front_vars.i18n.server_error || 'خطای سرور.';
     cpp_front_vars.i18n.view_more = cpp_front_vars.i18n.view_more || 'مشاهده بیشتر';
     cpp_front_vars.i18n.loading = cpp_front_vars.i18n.loading || 'بارگذاری...';
     cpp_front_vars.i18n.no_more_products = cpp_front_vars.i18n.no_more_products || 'محصول دیگری نیست.';

});
