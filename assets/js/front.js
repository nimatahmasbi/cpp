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
        }).fail(function(jqXHR) {
            captchaElement.text('خطا');
        });
    }

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
        modal.show();
    });

    $(document).on('click', '.cpp-refresh-captcha', refreshCaptcha);

    $('#cpp-order-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var button = form.find('button[type="submit"]');
        var formData = form.serialize(); 
        var originalButtonText = button.text();

        button.prop('disabled', true).text(cpp_front_vars.i18n.sending || 'در حال ارسال...'); 
        form.find('.cpp-form-message').remove();

        $.post(cpp_front_vars.ajax_url, formData + '&action=cpp_submit_order&nonce=' + cpp_front_vars.nonce, function(response) {
            if (response.success && response.data && response.data.message) {
                form.before('<div class="cpp-form-message cpp-success">' + response.data.message + '</div>');
                setTimeout(function() {
                    $('#cpp-order-modal').hide();
                    button.prop('disabled', false).text(originalButtonText);
                }, 3000); 
            } else {
                var errorMsg = (response.data && response.data.message) ? response.data.message : (cpp_front_vars.i18n.server_error || 'خطا.');
                form.before('<div class="cpp-form-message cpp-error">' + errorMsg + '</div>');
                button.prop('disabled', false).text(originalButtonText);
                if (response.data && response.data.code === 'captcha_error') {
                    refreshCaptcha();
                }
            }
        }).fail(function(jqXHR, textStatus) {
            form.before('<div class="cpp-form-message cpp-error">' + (cpp_front_vars.i18n.server_error || 'خطای سرور.') + '</div>');
            button.prop('disabled', false).text(originalButtonText);
            refreshCaptcha(); 
        });
    });

    // --- مدیریت نمودار (اصلاح شده) ---
     $(document).on('click', '.cpp-chart-btn', function() {
        var productId = $(this).data('product-id');
        
        // اضافه کردن div برای پس‌زمینه در صورتی که نباشد
        if ($('#cpp-front-chart-modal').length === 0) {
             $('body').append('<div id="cpp-front-chart-modal" class="cpp-modal-overlay" style="display:none;"><div class="cpp-modal-container cpp-chart-container"><button class="cpp-modal-close">&times;</button><h3>نمودار تغییرات قیمت</h3><div class="cpp-chart-inner"><div class="cpp-chart-bg"></div><canvas id="cppFrontPriceChart"></canvas></div></div></div>');
        }
        var modal = $('#cpp-front-chart-modal');
        var chartCanvas = modal.find('#cppFrontPriceChart');
        var chartContainer = chartCanvas.parent(); 

        // اعمال تصویر پس‌زمینه
        if (cpp_front_vars.logo_url) {
            modal.find('.cpp-chart-bg').css({
                'background-image': 'url(' + cpp_front_vars.logo_url + ')',
                'background-repeat': 'no-repeat',
                'background-position': 'center center',
                'background-size': '200px', // سایز لوگو
                'opacity': '0.1' 
            });
        }

        modal.css('display', 'flex'); // برای وسط‌چین شدن
        chartContainer.find('.chart-error, .chart-loading').remove(); 
        chartCanvas.show(); 

        if (frontChartInstance) { frontChartInstance.destroy(); frontChartInstance = null; }
        chartContainer.append('<p class="chart-loading" style="text-align:center;">' + cpp_front_vars.i18n.loading + '</p>');

        $.get(cpp_front_vars.ajax_url, { action: 'cpp_get_chart_data', product_id: productId, nonce: cpp_front_vars.nonce }, function(response) { 
            chartContainer.find('.chart-loading').remove(); 
            if (response.success && response.data && response.data.labels && response.data.labels.length > 0) {
                 renderFrontChart(response.data, chartCanvas[0]);
             }
            else {
                 var errorMsg = (response.data && typeof response.data === 'string') ? response.data : 'تاریخچه قیمت برای این محصول در دسترس نیست.';
                 chartCanvas.hide();
                 chartContainer.prepend('<p class="chart-error" style="color:red; text-align:center;">'+errorMsg+'</p>');
             }
        }).fail(function(jqXHR, textStatus) {
            chartContainer.find('.chart-loading').remove(); 
            chartCanvas.hide();
            chartContainer.prepend('<p class="chart-error" style="color:red; text-align:center;">خطا در بارگذاری داده‌های نمودار.</p>');
        });
    });

    function renderFrontChart(chartData, ctx) {
        var datasets = [];
        
        // 1. حداقل قیمت (هاشور آبی)
        if (chartData.min_prices) {
            datasets.push({ 
                label: 'حداقل', 
                data: chartData.min_prices, 
                borderColor: 'rgba(54, 162, 235, 0.8)', 
                borderDash: [5, 5], 
                pointRadius: 0, 
                fill: false 
            });
        }
        
        // 2. قیمت پایه (وسط)
        if (chartData.prices) {
            datasets.push({ 
                label: 'قیمت پایه', 
                data: chartData.prices, 
                borderColor: 'rgb(75, 192, 192)', 
                tension: 0.1, 
                borderWidth: 3,
                fill: {
                    target: 0, // پر کردن تا حداقل (ایندکس 0)
                    above: 'rgba(54, 162, 235, 0.15)' 
                }
            });
        }
        
        // 3. حداکثر قیمت (هاشور قرمز)
        if (chartData.max_prices) {
            datasets.push({ 
                label: 'حداکثر', 
                data: chartData.max_prices, 
                borderColor: 'rgba(255, 99, 132, 0.8)', 
                borderDash: [5, 5], 
                pointRadius: 0, 
                fill: {
                    target: 1, // پر کردن تا پایه (ایندکس 1)
                    above: 'rgba(255, 99, 132, 0.15)' 
                }
            });
        }

        if(datasets.length === 0){
             $(ctx).parent().prepend('<p class="chart-error" style="color:red; text-align:center;">داده‌ای وجود ندارد.</p>');
             $(ctx).hide(); return;
        }

        try {
            frontChartInstance = new Chart(ctx, {
                type: 'line',
                data: { labels: chartData.labels, datasets: datasets },
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
        } catch(e) { console.error("Error creating front chart:", e); }
    }

    $(document).on('click', '.cpp-modal-close', function() {
        var modal = $(this).closest('.cpp-modal-overlay');
        modal.hide();
         if (modal.is('#cpp-front-chart-modal') && frontChartInstance) {
            frontChartInstance.destroy(); frontChartInstance = null;
        }
    });
    $(document).on('click', '.cpp-modal-overlay', function(e) {
        if ($(e.target).is('.cpp-modal-overlay')) {
             var modal = $(this);
             modal.hide();
             if (modal.is('#cpp-front-chart-modal') && frontChartInstance) {
                frontChartInstance.destroy(); frontChartInstance = null;
            }
        }
    });

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

     cpp_front_vars.i18n = cpp_front_vars.i18n || {};
     cpp_front_vars.i18n.sending = cpp_front_vars.i18n.sending || 'در حال ارسال...';
     cpp_front_vars.i18n.server_error = cpp_front_vars.i18n.server_error || 'خطای سرور.';
     cpp_front_vars.i18n.view_more = cpp_front_vars.i18n.view_more || 'مشاهده بیشتر';
     cpp_front_vars.i18n.loading = cpp_front_vars.i18n.loading || 'بارگذاری...';
     cpp_front_vars.i18n.no_more_products = cpp_front_vars.i18n.no_more_products || 'محصول دیگری نیست.';

});
