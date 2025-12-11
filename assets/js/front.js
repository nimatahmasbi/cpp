jQuery(document).ready(function($) {
    var frontChartInstance = null;
    var fullFrontChartData = null;

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
        }).fail(function() {
            captchaElement.text('خطا');
        });
    }

    // --- باز کردن مودال ثبت سفارش ---
    $(document).on('click', '.cpp-order-btn', function(e) {
        e.preventDefault();
        var button = $(this); 
        var modal = $('#cpp-order-modal');
        modal.find('#cpp-order-product-id').val(button.data('product-id'));
        modal.find('.cpp-modal-product-name').text(button.data('product-name'));
        modal.find('.cpp-modal-product-location').text(button.data('product-location') || ''); 
        modal.find('.cpp-modal-product-unit').text(button.data('product-unit') || ''); 

        modal.find('.cpp-form-message').remove();
        modal.find('form')[0].reset();
        refreshCaptcha(); 
        modal.fadeIn(200).css('display', 'flex');
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
                    $('#cpp-order-modal').fadeOut(200);
                    button.prop('disabled', false).text(originalButtonText);
                }, 2000); 
            } else {
                var msg = response.data.message || 'خطا';
                form.before('<div class="cpp-form-message cpp-error">' + msg + '</div>');
                button.prop('disabled', false).text(originalButtonText);
                if (response.data.code === 'captcha_error') { refreshCaptcha(); }
            }
        }).fail(function() {
            form.before('<div class="cpp-form-message cpp-error">' + cpp_front_vars.i18n.server_error + '</div>');
            button.prop('disabled', false).text(originalButtonText);
        });
    });

    // --- مدیریت نمودار پیشرفته ---
     $(document).on('click', '.cpp-chart-btn', function(e) {
        e.preventDefault();
        var productId = $(this).data('product-id');
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

        modal.fadeIn(200).css('display', 'flex');
        chartContainer.find('.chart-error, .chart-loading').remove(); 
        chartCanvas.show(); 

        if (frontChartInstance) { frontChartInstance.destroy(); frontChartInstance = null; }
        chartContainer.append('<p class="chart-loading" style="text-align:center; padding:20px;">' + cpp_front_vars.i18n.loading + '</p>');

        $.get(cpp_front_vars.ajax_url, { action: 'cpp_get_chart_data', product_id: productId, nonce: cpp_front_vars.nonce }, function(response) { 
            chartContainer.find('.chart-loading').remove(); 
            if (response.success && response.data && response.data.labels) {
                 fullFrontChartData = response.data;
                 // ریست کردن دکمه‌های فیلتر به "همه"
                 modal.find('.cpp-chart-filter').removeClass('active');
                 modal.find('.cpp-chart-filter[data-range="all"]').addClass('active');
                 renderFrontChart(fullFrontChartData, chartCanvas[0], 'all');
             } else {
                 chartCanvas.hide();
                 chartContainer.prepend('<p class="chart-error" style="color:red; text-align:center; padding:20px;">داده‌ای یافت نشد.</p>');
             }
        }).fail(function() {
            chartContainer.find('.chart-loading').remove(); 
            chartCanvas.hide();
            chartContainer.prepend('<p class="chart-error" style="color:red; text-align:center; padding:20px;">خطای سرور</p>');
        });
    });

    // فیلتر زمانی نمودار
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
        if (!frontChartInstance) return;
        var link = document.createElement('a');
        link.href = frontChartInstance.toBase64Image();
        link.download = 'chart-' + new Date().toISOString().slice(0,10) + '.png';
        link.click();
    });

    // تابع رسم نمودار
    function renderFrontChart(data, ctx, range) {
        var labels = data.labels; var prices = data.prices; var min_prices = data.min_prices; var max_prices = data.max_prices;

         if (range !== 'all') {
             var totalPoints = labels.length;
             var pointsToShow = Math.floor(parseFloat(range) * 30); 
             if (totalPoints > pointsToShow) {
                 var start = totalPoints - pointsToShow;
                 labels = labels.slice(start); prices = prices.slice(start); min_prices = min_prices.slice(start); max_prices = max_prices.slice(start);
             }
         }

         var isSinglePrice = true;
         if (min_prices && max_prices) {
             for(var i=0; i<min_prices.length; i++) {
                 if (min_prices[i] !== max_prices[i] && min_prices[i] !== null) { isSinglePrice = false; break; }
             }
         } else isSinglePrice = false;

        var datasets = [];
        if (isSinglePrice) {
             datasets.push({ 
                 label: 'قیمت', data: prices, 
                 borderColor: 'rgb(75, 192, 192)', tension: 0.1, borderWidth: 3, fill: false 
             });
        } else {
            if (min_prices) datasets.push({ label: 'حداقل', data: min_prices, borderColor: 'rgba(54, 162, 235, 0.8)', borderDash: [5, 5], pointRadius: 0, fill: false });
            if (prices) datasets.push({ label: 'پایه', data: prices, borderColor: 'rgb(75, 192, 192)', tension: 0.1, borderWidth: 3, fill: { target: 0, above: 'rgba(54, 162, 235, 0.15)' } });
            if (max_prices) datasets.push({ label: 'حداکثر', data: max_prices, borderColor: 'rgba(255, 99, 132, 0.8)', borderDash: [5, 5], pointRadius: 0, fill: { target: 1, above: 'rgba(255, 99, 132, 0.15)' } });
        }

        try {
            frontChartInstance = new Chart(ctx, {
                type: 'line',
                data: { labels: labels, datasets: datasets },
                options: {
                     responsive: true, maintainAspectRatio: false, spanGaps: true,
                     plugins: { legend: { position: 'top' }, tooltip: { mode: 'index', intersect: false }, filler: { propagate: false } },
                     scales: { y: { beginAtZero: false } }
                }
             });
        } catch(e) { console.error("Chart Error:", e); }
    }

    // بستن مودال
    $(document).on('click', '.cpp-modal-close, .cpp-modal-overlay', function(e) {
        if (e.target === this || $(this).hasClass('cpp-modal-close')) {
            $('.cpp-modal-overlay').fadeOut(200);
            if (frontChartInstance) { frontChartInstance.destroy(); frontChartInstance = null; }
        }
    });
    
    // --- دکمه Load More ---
    $(document).on('click', '.cpp-view-more-btn', function() {
        var button = $(this);
        var wrapper = button.closest('.cpp-grid-view-wrapper');
        var nextPage = parseInt(button.data('page'), 10) + 1;
        
        button.prop('disabled', true).text(cpp_front_vars.i18n.loading);

        $.post(cpp_front_vars.ajax_url, {
            action: 'cpp_load_more_products', nonce: cpp_front_vars.nonce, page: nextPage, shortcode_type: button.data('shortcode-type')
        }, function(response) {
            if (response.success && response.data.html) {
                wrapper.find('.cpp-grid-view-table tbody').append(response.data.html);
                button.data('page', nextPage).prop('disabled', false).text(cpp_front_vars.i18n.view_more);
                if (!response.data.has_more) button.hide();
            } else {
                button.hide();
            }
        }).fail(function() {
            alert(cpp_front_vars.i18n.server_error);
            button.prop('disabled', false).text(cpp_front_vars.i18n.view_more);
        });
    });

    // فیلتر گرید
    $('.cpp-grid-view-filters .filter-btn').on('click', function(e){
        e.preventDefault();
        var $this = $(this);
        $this.closest('.cpp-grid-view-wrapper').find('.product-row').hide().filter($this.data('cat-id') === 'all' ? '*' : '[data-cat-id="' + $this.data('cat-id') + '"]').show();
        $this.siblings().removeClass('active'); $this.addClass('active');
        $this.closest('.cpp-grid-view-wrapper').find('.cpp-grid-view-footer').toggle($this.data('cat-id') === 'all');
    });
});
