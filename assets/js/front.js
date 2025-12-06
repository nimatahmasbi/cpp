jQuery(document).ready(function($) {
    var frontChartInstance = null;

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
        }).fail(function() { captchaElement.text('خطا'); });
    }

    // باز کردن مودال ثبت سفارش
    $(document).on('click', '.cpp-order-btn', function(e) {
        e.preventDefault();
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
        modal.show(); // یا css('display', 'flex') اگر استایل فلکس دارید
    });

    $(document).on('click', '.cpp-refresh-captcha', refreshCaptcha);

    $('#cpp-order-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var button = form.find('button[type="submit"]');
        var formData = form.serialize(); 
        
        button.prop('disabled', true).text('ارسال...');
        form.find('.cpp-form-message').remove();

        $.post(cpp_front_vars.ajax_url, formData + '&action=cpp_submit_order&nonce=' + cpp_front_vars.nonce, function(response) {
            if (response.success) {
                form.before('<div class="cpp-form-message cpp-success">' + response.data.message + '</div>');
                setTimeout(function() { $('#cpp-order-modal').hide(); button.prop('disabled', false).text('ثبت درخواست'); }, 2000);
            } else {
                form.before('<div class="cpp-form-message cpp-error">' + (response.data.message || 'خطا') + '</div>');
                button.prop('disabled', false).text('ثبت درخواست');
                if (response.data.code === 'captcha_error') refreshCaptcha();
            }
        }).fail(function() {
            form.before('<div class="cpp-form-message cpp-error">خطای سرور</div>');
            button.prop('disabled', false).text('ثبت درخواست');
        });
    });

    // باز کردن مودال نمودار
     $(document).on('click', '.cpp-chart-btn', function(e) {
        e.preventDefault();
        var productId = $(this).data('product-id');
        
        if ($('#cpp-front-chart-modal').length === 0) {
             $('body').append('<div id="cpp-front-chart-modal" class="cpp-modal-overlay" style="display:none;"><div class="cpp-modal-container cpp-chart-container"><button class="cpp-modal-close">&times;</button><h3>نمودار تغییرات قیمت</h3><div class="cpp-chart-inner"><div class="cpp-chart-bg"></div><canvas id="cppFrontPriceChart"></canvas></div></div></div>');
        }
        var modal = $('#cpp-front-chart-modal');
        var chartCanvas = modal.find('#cppFrontPriceChart');
        var chartContainer = chartCanvas.parent(); 

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

        modal.css('display', 'flex'); // برای وسط چین
        chartContainer.find('.chart-error, .chart-loading').remove(); 
        chartCanvas.show(); 

        if (frontChartInstance) { frontChartInstance.destroy(); frontChartInstance = null; }
        chartContainer.append('<p class="chart-loading" style="text-align:center;">بارگذاری...</p>');

        $.get(cpp_front_vars.ajax_url, { action: 'cpp_get_chart_data', product_id: productId, nonce: cpp_front_vars.nonce }, function(response) { 
            chartContainer.find('.chart-loading').remove(); 
            if (response.success && response.data && response.data.labels) {
                 renderFrontChart(response.data, chartCanvas[0]);
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

    function renderFrontChart(chartData, ctx) {
        var datasets = [];
        if (chartData.min_prices) {
            datasets.push({ label: 'حداقل', data: chartData.min_prices, borderColor: 'rgba(54, 162, 235, 0.8)', borderDash: [5, 5], pointRadius: 0, fill: false });
        }
        if (chartData.prices) {
            datasets.push({ 
                label: 'قیمت پایه', data: chartData.prices, borderColor: 'rgb(75, 192, 192)', tension: 0.1, borderWidth: 3,
                fill: { target: 0, above: 'rgba(54, 162, 235, 0.15)' } // پر کردن فاصله تا حداقل
            });
        }
        if (chartData.max_prices) {
            datasets.push({ 
                label: 'حداکثر', data: chartData.max_prices, borderColor: 'rgba(255, 99, 132, 0.8)', borderDash: [5, 5], pointRadius: 0, 
                fill: { target: 1, above: 'rgba(255, 99, 132, 0.15)' } // پر کردن فاصله تا پایه
            });
        }

        if(datasets.length === 0){
             $(ctx).parent().prepend('<p class="chart-error">داده‌ای نیست.</p>'); $(ctx).hide(); return;
        }

        try {
            frontChartInstance = new Chart(ctx, {
                type: 'line',
                data: { labels: chartData.labels, datasets: datasets },
                options: {
                     responsive: true, maintainAspectRatio: false, spanGaps: true,
                     plugins: { filler: { propagate: false } },
                     scales: { y: { beginAtZero: false } }
                }
             });
        } catch(e) { console.error(e); }
    }

    $(document).on('click', '.cpp-modal-close, .cpp-modal-overlay', function(e) {
        if (e.target === this || $(this).hasClass('cpp-modal-close')) {
            $('.cpp-modal-overlay').hide();
            if (frontChartInstance) { frontChartInstance.destroy(); frontChartInstance = null; }
        }
    });
});
