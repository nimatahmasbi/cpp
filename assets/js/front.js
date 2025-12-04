jQuery(document).ready(function($) {
    var frontChartInstance = null;

    // --- تابع درخواست کد کپچا جدید ---
    function refreshCaptcha() {
        var captchaElement = $('.cpp-captcha-code');
        var captchaInput = $('#captcha_input');
        if (!captchaElement.length) return; // Exit if captcha element not found

        captchaElement.text('...'); // Show loading
        captchaInput.val(''); // Clear input

        console.log('Requesting new CAPTCHA...'); // Debug log

        $.post(cpp_front_vars.ajax_url, {
            action: 'cpp_get_captcha', // New AJAX action
            nonce: cpp_front_vars.nonce // Send nonce
        }, function(response) {
            console.log('CAPTCHA Response:', response); // Debug log
            if (response.success && response.data && response.data.code) {
                captchaElement.text(response.data.code); // Display the code
            } else {
                captchaElement.text('خطا');
                console.error("Error fetching CAPTCHA:", response.data ? response.data : 'Unknown error');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            captchaElement.text('خطا');
            console.error("AJAX Error fetching CAPTCHA:", textStatus, errorThrown, jqXHR.responseText);
        });
    }

    // --- ۱. مدیریت پاپ‌آپ سفارش ---
    $(document).on('click', '.cpp-order-btn', function() {
        var button = $(this); // Store button reference
        var productId = button.data('product-id');
        var productName = button.data('product-name');
        // --- خواندن دیتا اتریبیوت‌های جدید ---
        var productUnit = button.data('product-unit');
        var productLocation = button.data('product-location');

        var modal = $('#cpp-order-modal');
        modal.find('#cpp-order-product-id').val(productId);
        modal.find('.cpp-modal-product-name').text(productName);
        // --- آپدیت محل بارگیری و واحد ---
        modal.find('.cpp-modal-product-location').text(productLocation ? productLocation : ''); // Display location or empty
        modal.find('.cpp-modal-product-unit').text(productUnit ? productUnit : ''); // Display unit or empty

        modal.find('.cpp-form-message').remove();
        modal.find('form')[0].reset();
        refreshCaptcha(); // Load captcha when modal opens
        modal.show();
    });

    // --- دکمه رفرش کپچا ---
    $(document).on('click', '.cpp-refresh-captcha', refreshCaptcha);

    // --- ۲. ارسال فرم سفارش با AJAX ---
    $('#cpp-order-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var button = form.find('button[type="submit"]');
        var formData = form.serialize(); // Includes captcha_input
        var originalButtonText = button.text();

        button.prop('disabled', true).text(cpp_front_vars.i18n.sending || 'در حال ارسال...'); // Use localized text
        form.find('.cpp-form-message').remove();
        console.log('Submitting order form data:', formData); // Debug log

        $.post(cpp_front_vars.ajax_url, formData + '&action=cpp_submit_order&nonce=' + cpp_front_vars.nonce, function(response) {
            console.log('Order submit response:', response); // Debug log
            if (response.success && response.data && response.data.message) {
                form.before('<div class="cpp-form-message cpp-success">' + response.data.message + '</div>');
                setTimeout(function() {
                    $('#cpp-order-modal').hide();
                    button.prop('disabled', false).text(originalButtonText);
                }, 3000); // Longer delay to read message
            } else {
                // Display specific error message from server
                var errorMsg = (response.data && response.data.message) ? response.data.message : (cpp_front_vars.i18n.server_error || 'خطای ناشناخته.');
                form.before('<div class="cpp-form-message cpp-error">' + errorMsg + '</div>');
                button.prop('disabled', false).text(originalButtonText);
                // Refresh captcha on error, especially if it's a captcha error
                if (response.data && response.data.code === 'captcha_error') {
                    refreshCaptcha();
                }
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            form.before('<div class="cpp-form-message cpp-error">' + (cpp_front_vars.i18n.server_error || 'خطای سرور، لطفا دوباره تلاش کنید.') + ' (' + textStatus + ')</div>');
            button.prop('disabled', false).text(originalButtonText);
            console.error("AJAX Error submitting order:", textStatus, errorThrown, jqXHR.responseText);
            refreshCaptcha(); // Refresh captcha on server error too
        });
    });

    // --- ۳. مدیریت پاپ‌آپ نمودار ---
    // ... (کد نمودار بدون تغییر) ...
     $(document).on('click', '.cpp-chart-btn', function() {
        var productId = $(this).data('product-id');
        // Ensure modal exists
        if ($('#cpp-front-chart-modal').length === 0) {
             $('body').append('<div id="cpp-front-chart-modal" class="cpp-modal-overlay" style="display:none;"><div class="cpp-modal-container cpp-chart-container"><button class="cpp-modal-close">&times;</button><h3>نمودار تغییرات قیمت</h3><div class="cpp-chart-inner"><canvas id="cppFrontPriceChart"></canvas></div></div></div>');
        }
        var modal = $('#cpp-front-chart-modal');
        var chartCanvas = modal.find('#cppFrontPriceChart');
        var chartContainer = chartCanvas.parent(); // Get the container

        modal.show();
        chartContainer.find('.chart-error, .chart-loading').remove(); // Clear previous states
        chartCanvas.show(); // Ensure canvas is visible

        if (frontChartInstance) { frontChartInstance.destroy(); frontChartInstance = null; }
         // Add loading indicator
        chartContainer.append('<p class="chart-loading" style="text-align:center;">در حال بارگذاری داده...</p>');

        $.get(cpp_front_vars.ajax_url, { action: 'cpp_get_chart_data', product_id: productId, nonce: cpp_front_vars.nonce }, function(response) { // Added nonce
            chartContainer.find('.chart-loading').remove(); // Remove loading
            if (response.success && response.data && response.data.labels && response.data.labels.length > 0) {
                 renderFrontChart(response.data, chartCanvas[0]);
             }
            else {
                 var errorMsg = (response.data && typeof response.data === 'string') ? response.data : 'تاریخچه قیمت برای این محصول در دسترس نیست.';
                 chartCanvas.hide();
                 chartContainer.prepend('<p class="chart-error" style="color:red; text-align:center;">'+errorMsg+'</p>');
                 console.error("Chart data error:", response);
             }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            chartContainer.find('.chart-loading').remove(); // Remove loading
            chartCanvas.hide();
            chartContainer.prepend('<p class="chart-error" style="color:red; text-align:center;">خطا در بارگذاری داده‌های نمودار: '+textStatus+'</p>');
             console.error("AJAX Error loading chart data:", textStatus, errorThrown, jqXHR.responseText);
        });
    });

    function renderFrontChart(chartData, ctx) {
        var datasets = [];
        if (chartData.prices && chartData.prices.filter(p => p !== null).length > 0) { // Check if there's actual data
            datasets.push({ label: 'قیمت پایه', data: chartData.prices, borderColor: 'rgb(75, 192, 192)', tension: 0.1, fill: false, borderWidth: 2 });
        }
        if (chartData.min_prices && chartData.min_prices.filter(p => p !== null).length > 0) {
            datasets.push({ label: 'حداقل قیمت', data: chartData.min_prices, borderColor: 'rgba(255, 99, 132, 0.7)', borderDash: [5, 5], fill: '+1', pointRadius: 0, borderWidth: 1 });
        }
        if (chartData.max_prices && chartData.max_prices.filter(p => p !== null).length > 0) {
            datasets.push({ label: 'حداکثر قیمت', data: chartData.max_prices, borderColor: 'rgba(54, 162, 235, 0.7)', borderDash: [5, 5], fill: false, pointRadius: 0, borderWidth: 1 });
        }

        if(datasets.length === 0){
             $(ctx).parent().prepend('<p class="chart-error" style="color:red; text-align:center;">داده‌ای برای نمایش در نمودار وجود ندارد.</p>');
             $(ctx).hide();
             return;
        }


        if (!ctx || typeof ctx.getContext !== 'function') {
            console.error("Invalid canvas context for front chart.");
             $(ctx).parent().prepend('<p class="chart-error" style="color:red; text-align:center;">خطا در آماده‌سازی نمودار.</p>');
            return;
        }
        try {
            frontChartInstance = new Chart(ctx, {
                type: 'line',
                data: { labels: chartData.labels, datasets: datasets },
                options: {
                     responsive: true,
                     maintainAspectRatio: false,
                      plugins: {
                          legend: { display: true, position: 'top' },
                          tooltip: { mode: 'index', intersect: false }
                      },
                      scales: {
                          y: { beginAtZero: false, title: { display: true, text: 'قیمت'} },
                          x: { title: { display: true, text: 'تاریخ'} }
                       },
                      spanGaps: true // Connect lines over null data points
                }
             });
        } catch(e) {
            console.error("Error creating front chart:", e);
            $(ctx).parent().prepend('<p class="chart-error" style="color:red; text-align:center;">خطا در رسم نمودار.</p>');
        }
    }


    // --- ۴. منطق بستن پاپ‌آپ‌ها ---
    $(document).on('click', '.cpp-modal-close', function() {
        var modal = $(this).closest('.cpp-modal-overlay');
        modal.hide();
         // Destroy chart if closing chart modal
         if (modal.is('#cpp-front-chart-modal') && frontChartInstance) {
            frontChartInstance.destroy();
            frontChartInstance = null;
        }
    });
    $(document).on('click', '.cpp-modal-overlay', function(e) {
        if ($(e.target).is('.cpp-modal-overlay')) {
             var modal = $(this);
             modal.hide();
            // Destroy chart if closing chart modal
             if (modal.is('#cpp-front-chart-modal') && frontChartInstance) {
                frontChartInstance.destroy();
                frontChartInstance = null;
            }
        }
    });

    // --- ۵. فیلتر دسته‌بندی‌ها برای شورت‌کدهای گرید ---
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
        wrapper.find('.cpp-grid-view-footer').toggle(catId === 'all'); // Show/hide 'View More' only for 'All'
    });

    // --- ۶. منطق بارگذاری بیشتر محصولات (مشاهده بیشتر) ---
    $(document).on('click', '.cpp-view-more-btn', function() {
        var button = $(this);
        var wrapper = button.closest('.cpp-grid-view-wrapper');
        var currentPage = parseInt(button.data('page'), 10); // Current page is 0-indexed initially
        var nextPage = currentPage + 1;
        var shortcode_type = button.data('shortcode-type'); // Should be 'with_date' or 'no_date'
        var original_text = cpp_front_vars.i18n.view_more;

        button.prop('disabled', true).text(cpp_front_vars.i18n.loading);

        $.post(cpp_front_vars.ajax_url, {
            action: 'cpp_load_more_products',
            nonce: cpp_front_vars.nonce,
            page: nextPage + 1, // Send next page number (1-based for PHP offset)
            shortcode_type: shortcode_type
        }, function(response) {
            if (response.success && response.data && response.data.html) {
                wrapper.find('.cpp-grid-view-table tbody').append(response.data.html);
                button.data('page', nextPage); // Update current page index on button

                // Check if there are more products reported by the server
                if (!response.data.has_more) {
                    button.text(cpp_front_vars.i18n.no_more_products).prop('disabled', true);
                     button.parent().hide(); // Hide footer completely
                } else {
                    button.prop('disabled', false).text(original_text);
                }

            } else {
                 // Handle case where success is false or html is missing, means no more products
                button.text(cpp_front_vars.i18n.no_more_products).prop('disabled', true);
                 button.parent().hide(); // Hide footer completely
                 console.log("Load more response error or no more products:", response);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            alert(cpp_front_vars.i18n.server_error || 'خطای سرور.');
            button.prop('disabled', false).text(original_text);
             console.error("AJAX Error loading more products:", textStatus, errorThrown, jqXHR.responseText);
        });
    });

     // Initialize localization strings if not already present
     cpp_front_vars.i18n = cpp_front_vars.i18n || {};
     cpp_front_vars.i18n.sending = cpp_front_vars.i18n.sending || 'در حال ارسال...';
     cpp_front_vars.i18n.server_error = cpp_front_vars.i18n.server_error || 'خطای سرور، لطفا دوباره تلاش کنید.';
     cpp_front_vars.i18n.view_more = cpp_front_vars.i18n.view_more || 'مشاهده بیشتر';
     cpp_front_vars.i18n.loading = cpp_front_vars.i18n.loading || 'در حال بارگذاری...';
     cpp_front_vars.i18n.no_more_products = cpp_front_vars.i18n.no_more_products || 'محصول دیگری برای نمایش وجود ندارد.';

}); // End jQuery ready
