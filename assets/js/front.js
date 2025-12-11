jQuery(document).ready(function($) {
    var frontChartInstance = null;
    var fullFrontChartData = null; // برای نگهداری داده‌ها جهت فیلتر بدون درخواست مجدد

    /**
     * =======================================================
     * 1. سیستم کپچا و ثبت سفارش
     * =======================================================
     */
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
        }).fail(function() {
            captchaElement.text('خطا');
        });
    }

    // باز کردن مودال ثبت سفارش با کلیک روی دکمه سبد خرید
    $(document).on('click', '.cpp-order-btn', function(e) {
        e.preventDefault();
        var button = $(this); 
        var modal = $('#cpp-order-modal');
        
        // پر کردن فیلدهای مخفی و نمایشی مودال
        modal.find('#cpp-order-product-id').val(button.data('product-id'));
        modal.find('.cpp-modal-product-name').text(button.data('product-name'));
        modal.find('.cpp-modal-product-location').text(button.data('product-location') || ''); 
        modal.find('.cpp-modal-product-unit').text(button.data('product-unit') || ''); 
        
        modal.find('.cpp-form-message').remove(); // حذف پیام‌های قبلی
        modal.find('form')[0].reset(); // ریست فرم
        
        refreshCaptcha(); 
        modal.show(); // نمایش مودال
    });

    // دکمه رفرش کپچا
    $(document).on('click', '.cpp-refresh-captcha', refreshCaptcha);

    // ارسال فرم سفارش (AJAX)
    $('#cpp-order-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var button = form.find('button[type="submit"]');
        var formData = form.serialize(); 
        var originalText = button.text();
        
        button.prop('disabled', true).text(cpp_front_vars.i18n.sending || 'در حال ارسال...');
        form.find('.cpp-form-message').remove();

        $.post(cpp_front_vars.ajax_url, formData + '&action=cpp_submit_order&nonce=' + cpp_front_vars.nonce, function(response) {
            if (response.success) {
                form.before('<div class="cpp-form-message cpp-success">' + response.data.message + '</div>');
                // بستن خودکار مودال بعد از ۲ ثانیه
                setTimeout(function() {
                    $('#cpp-order-modal').hide();
                    button.prop('disabled', false).text(originalText);
                }, 2000); 
            } else {
                var msg = response.data.message || 'خطا در ثبت سفارش';
                form.before('<div class="cpp-form-message cpp-error">' + msg + '</div>');
                button.prop('disabled', false).text(originalText);
                
                // اگر خطا مربوط به کپچا بود، رفرش کن
                if (response.data.code === 'captcha_error') {
                    refreshCaptcha();
                }
            }
        }).fail(function() {
            form.before('<div class="cpp-form-message cpp-error">' + cpp_front_vars.i18n.server_error + '</div>');
            button.prop('disabled', false).text(originalText);
        });
    });


    /**
     * =======================================================
     * 2. مدیریت نمودار پیشرفته (Front-end)
     * =======================================================
     */
     $(document).on('click', '.cpp-chart-btn', function(e) {
        e.preventDefault();
        var productId = $(this).data('product-id');
        
        // اگر مودال نمودار در صفحه وجود ندارد، آن را بساز (Lazy Load HTML)
        if ($('#cpp-front-chart-modal').length === 0) {
             var modalHtml = 
                '<div id="cpp-front-chart-modal" class="cpp-modal-overlay" style="display:none;">' +
                     '<div class="cpp-modal-container cpp-chart-container">' +
                         '<button class="cpp-modal-close">&times;</button>' +
                         '<h3>نمودار تغییرات قیمت</h3>' +
                         // نوار ابزار: فیلترها و دانلود
                         '<div class="cpp-chart-toolbar">' +
                            '<button class="button cpp-chart-filter active" data-range="all">همه</button> ' +
                            '<button class="button cpp-chart-filter" data-range="12">۱ سال</button> ' +
                            '<button class="button cpp-chart-filter" data-range="6">۶ ماه</button> ' +
                            '<button class="button cpp-chart-filter" data-range="3">۳ ماه</button> ' +
                            '<button class="button cpp-chart-filter" data-range="1">۱ ماه</button> ' +
                            '<button class="button cpp-chart-filter" data-range="0.25">۱ هفته</button> ' +
                            '<button class="button button-primary cpp-chart-download">دانلود</button>' +
                        '</div>' +
                        // کانتینر نمودار
                         '<div class="cpp-chart-inner">' +
                            '<div class="cpp-chart-bg"></div>' + // واترمارک لوگو
                            '<canvas id="cppFrontPriceChart"></canvas>' +
                        '</div>' +
                     '</div>' +
                '</div>';
             $('body').append(modalHtml);
        }

        var modal = $('#cpp-front-chart-modal');
        var chartCanvas = modal.find('#cppFrontPriceChart');
        var chartContainer = chartCanvas.parent(); 

        // تنظیم لوگو در پس‌زمینه (اگر موجود باشد)
        if (cpp_front_vars.logo_url) {
            modal.find('.cpp-chart-bg').css({
                'background-image': 'url(' + cpp_front_vars.logo_url + ')',
                'background-repeat': 'no-repeat',
                'background-position': 'center center',
                'background-size': '200px',
                'opacity': '0.1', // کمرنگ بودن واترمارک
                'position': 'absolute', 'top':0, 'left':0, 'width':'100%', 'height':'100%', 'z-index':0
            });
            chartCanvas.css({'position':'relative', 'z-index':1});
        }

        modal.css('display', 'flex'); // نمایش مودال
        chartContainer.find('.chart-error, .chart-loading').remove(); 
        chartCanvas.show(); 

        // ریست کردن اینستنس قبلی چارت
        if (frontChartInstance) { 
            frontChartInstance.destroy(); 
            frontChartInstance = null; 
        }

        chartContainer.append('<p class="chart-loading" style="text-align:center;">' + cpp_front_vars.i18n.loading + '</p>');

        // دریافت داده‌ها
        $.get(cpp_front_vars.ajax_url, { 
            action: 'cpp_get_chart_data', 
            product_id: productId, 
            nonce: cpp_front_vars.nonce 
        }, function(response) { 
            chartContainer.find('.chart-loading').remove(); 
            
            if (response.success && response.data && response.data.labels && response.data.labels.length > 0) {
                 fullFrontChartData = response.data; // ذخیره
                 renderFrontChart(fullFrontChartData, chartCanvas[0], 'all'); // رسم
             } else {
                 chartCanvas.hide();
                 chartContainer.prepend('<p class="chart-error" style="color:red; text-align:center;">داده‌ای یافت نشد.</p>');
             }
        }).fail(function() {
            chartContainer.find('.chart-loading').remove(); 
            chartCanvas.hide();
            chartContainer.prepend('<p class="chart-error" style="color:red;">خطای سرور در دریافت داده‌ها.</p>');
        });
    });

    // رویداد تغییر فیلتر زمانی
    $(document).on('click', '.cpp-chart-filter', function() {
        $('.cpp-chart-filter').removeClass('active');
        $(this).addClass('active');
        
        var range = $(this).data('range');
        var ctx = $('#cppFrontPriceChart')[0];
        
        if (frontChartInstance) frontChartInstance.destroy();
        renderFrontChart(fullFrontChartData, ctx, range);
    });

    // رویداد دانلود نمودار
    $(document).on('click', '.cpp-chart-download', function() {
        if (!frontChartInstance) return;
        var link = document.createElement('a');
        link.href = frontChartInstance.toBase64Image();
        link.download = 'price-chart.png';
        link.click();
    });

    // تابع رسم نمودار (مشابه ادمین)
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

         // بررسی تک‌قیمتی
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
             datasets.push({ 
                 label: 'قیمت', 
                 data: prices, 
                 borderColor: 'rgb(75, 192, 192)', 
                 tension: 0.1, 
                 borderWidth: 3, 
                 fill: false 
             });
        } else {
            // ترتیب لایه‌ها: 0=حداقل، 1=پایه، 2=حداکثر
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
            if (prices) {
                datasets.push({ 
                    label: 'پایه', 
                    data: prices, 
                    borderColor: 'rgb(75, 192, 192)', // سبزآبی
                    tension: 0.1, 
                    borderWidth: 3, 
                    fill: { target: 0, above: 'rgba(54, 162, 235, 0.15)' } // آبی تا حداقل
                });
            }
            if (max_prices) {
                datasets.push({ 
                    label: 'حداکثر', 
                    data: max_prices, 
                    borderColor: 'rgba(255, 99, 132, 0.8)', // قرمز
                    borderDash: [5, 5], 
                    pointRadius: 0, 
                    fill: { target: 1, above: 'rgba(255, 99, 132, 0.15)' } // قرمز تا پایه
                });
            }
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
        } catch(e) { console.error("Chart Error:", e); }
    }

    // بستن مودال
    $(document).on('click', '.cpp-modal-close, .cpp-modal-overlay', function(e) {
        if (e.target === this || $(this).hasClass('cpp-modal-close')) {
            $('.cpp-modal-overlay').hide();
            if (frontChartInstance) { frontChartInstance.destroy(); frontChartInstance = null; }
        }
    });
    
    // --- دکمه مشاهده بیشتر (Load More) ---
    $(document).on('click', '.cpp-view-more-btn', function() {
        var button = $(this);
        var wrapper = button.closest('.cpp-grid-view-wrapper');
        var nextPage = parseInt(button.data('page'), 10) + 1;
        
        button.prop('disabled', true).text(cpp_front_vars.i18n.loading);

        $.post(cpp_front_vars.ajax_url, {
            action: 'cpp_load_more_products',
            nonce: cpp_front_vars.nonce,
            page: nextPage, 
            shortcode_type: button.data('shortcode-type')
        }, function(response) {
            if (response.success && response.data.html) {
                wrapper.find('.cpp-grid-view-table tbody').append(response.data.html);
                button.data('page', nextPage).prop('disabled', false).text(cpp_front_vars.i18n.view_more);
                if (!response.data.has_more) {
                     button.text(cpp_front_vars.i18n.no_more_products).prop('disabled', true).parent().hide(); 
                }
            } else {
                button.text(cpp_front_vars.i18n.no_more_products).prop('disabled', true).parent().hide();
            }
        }).fail(function() {
            alert(cpp_front_vars.i18n.server_error);
            button.prop('disabled', false).text(cpp_front_vars.i18n.view_more);
        });
    });

    // فیلتر دسته‌بندی گرید
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

});
