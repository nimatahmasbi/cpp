jQuery(document).ready(function($) {
    var frontChartInstance = null;
    var fullFrontChartData = null; // ذخیره داده کامل برای فیلتر

    // --- کپچا و سفارش (بدون تغییر) ---
    function refreshCaptcha() {
        var captchaElement = $('.cpp-captcha-code');
        var captchaInput = $('#captcha_input');
        if (!captchaElement.length) return;
        captchaElement.text('...'); captchaInput.val(''); 
        $.post(cpp_front_vars.ajax_url, { action: 'cpp_get_captcha', nonce: cpp_front_vars.nonce }, function(response) {
            if (response.success) captchaElement.text(response.data.code); else captchaElement.text('خطا');
        });
    }

    $(document).on('click', '.cpp-order-btn', function() {
        var btn = $(this); var modal = $('#cpp-order-modal');
        modal.find('#cpp-order-product-id').val(btn.data('product-id'));
        modal.find('.cpp-modal-product-name').text(btn.data('product-name'));
        modal.find('.cpp-modal-product-location').text(btn.data('product-location')||''); 
        modal.find('.cpp-modal-product-unit').text(btn.data('product-unit')||''); 
        modal.find('.cpp-form-message').remove(); modal.find('form')[0].reset(); refreshCaptcha(); modal.show();
    });

    $(document).on('click', '.cpp-refresh-captcha', refreshCaptcha);
    $('#cpp-order-form').on('submit', function(e) {
        e.preventDefault(); var form = $(this); var btn = form.find('button[type="submit"]');
        btn.prop('disabled', true).text('ارسال...'); form.find('.cpp-form-message').remove();
        $.post(cpp_front_vars.ajax_url, form.serialize()+'&action=cpp_submit_order&nonce='+cpp_front_vars.nonce, function(res) {
            if (res.success) { form.before('<div class="cpp-form-message cpp-success">'+res.data.message+'</div>'); setTimeout(function(){$('#cpp-order-modal').hide(); btn.prop('disabled',false).text('ثبت');}, 2000); } 
            else { form.before('<div class="cpp-form-message cpp-error">'+(res.data.message||'خطا')+'</div>'); btn.prop('disabled',false).text('ثبت'); if(res.data.code==='captcha_error') refreshCaptcha(); }
        });
    });

    // --- مدیریت نمودار پیشرفته ---
     $(document).on('click', '.cpp-chart-btn', function(e) {
        e.preventDefault();
        var pid = $(this).data('product-id');
        
        // ساختار مودال با تولبار و دکمه‌ها (دقیقاً مثل ادمین)
        if ($('#cpp-front-chart-modal').length === 0) {
             var html = '<div id="cpp-front-chart-modal" class="cpp-modal-overlay" style="display:none;">' +
                 '<div class="cpp-modal-container cpp-chart-container">' +
                 '<button class="cpp-modal-close">&times;</button>' +
                 '<h3>نمودار تغییرات قیمت</h3>' +
                 // تولبار فیلترها
                 '<div class="cpp-chart-toolbar">' +
                    '<button class="cpp-btn-filter active" data-r="all">همه</button>' +
                    '<button class="cpp-btn-filter" data-r="12">۱ سال</button>' +
                    '<button class="cpp-btn-filter" data-r="6">۶ ماه</button>' +
                    '<button class="cpp-btn-filter" data-r="3">۳ ماه</button>' +
                    '<button class="cpp-btn-filter" data-r="1">۱ ماه</button>' +
                    '<button class="cpp-btn-filter" data-r="0.25">۱ هفته</button>' +
                    '<button class="cpp-btn-dl">دانلود</button>' +
                '</div>' +
                 '<div class="cpp-chart-inner"><div class="cpp-chart-bg"></div><canvas id="cppFrontPriceChart"></canvas></div>' +
                 '</div></div>';
             $('body').append(html);
        }

        var modal = $('#cpp-front-chart-modal');
        var chartCanvas = modal.find('#cppFrontPriceChart');
        var chartContainer = chartCanvas.parent(); 

        // تنظیم لوگو
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

        $.get(cpp_front_vars.ajax_url, { action: 'cpp_get_chart_data', product_id: pid, nonce: cpp_front_vars.nonce }, function(res) { 
            chartContainer.find('.chart-loading').remove(); 
            if (res.success && res.data && res.data.labels) {
                 fullFrontChartData = res.data; // ذخیره داده اصلی
                 renderFrontChart(fullFrontChartData, chartCanvas[0], 'all');
             } else {
                 chartCanvas.hide();
                 chartContainer.prepend('<p class="chart-error" style="color:red; text-align:center;">داده‌ای یافت نشد.</p>');
             }
        }).fail(function() { chartContainer.find('.chart-loading').remove(); chartContainer.prepend('<p class="chart-error">خطای سرور</p>'); });
    });

    // رویداد دکمه‌های فیلتر
    $(document).on('click', '.cpp-btn-filter', function() {
        $('.cpp-btn-filter').removeClass('active');
        $(this).addClass('active');
        var range = $(this).data('r');
        var ctx = $('#cppFrontPriceChart')[0];
        if (frontChartInstance) frontChartInstance.destroy();
        renderFrontChart(fullFrontChartData, ctx, range);
    });

    // رویداد دکمه دانلود
    $(document).on('click', '.cpp-btn-dl', function() {
        var a = document.createElement('a');
        a.href = frontChartInstance.toBase64Image();
        a.download = 'chart-price.png';
        a.click();
    });

    function renderFrontChart(data, ctx, range) {
        var L=data.labels, P=data.prices, Min=data.min_prices, Max=data.max_prices;

        // منطق فیلتر زمانی
         if (range !== 'all') {
             var totalPoints = L.length;
             var pointsToShow = Math.floor(parseFloat(range) * 30); 
             if (totalPoints > pointsToShow) {
                 var start = totalPoints - pointsToShow;
                 L=L.slice(start); P=P.slice(start); Min=Min.slice(start); Max=Max.slice(start);
             }
         }

         // تشخیص تک‌قیمتی
         var isSinglePrice = true;
         if (Min && Max) {
             for(var i=0; i<Min.length; i++) {
                 if (Min[i] !== Max[i]) { isSinglePrice = false; break; }
             }
         } else isSinglePrice = false;

        var ds = [];
        
        if (isSinglePrice) {
             ds.push({ 
                 label: 'قیمت', data: P, borderColor: 'rgb(75, 192, 192)', tension: 0.1, borderWidth: 3, fill: false 
             });
        } else {
            if (Min) ds.push({ label: 'حداقل', data: Min, borderColor: 'rgba(54, 162, 235, 0.8)', borderDash:[5,5], pointRadius:0, fill: false });
            if (P) ds.push({ label: 'قیمت پایه', data: P, borderColor: 'rgb(75, 192, 192)', tension: 0.1, borderWidth: 3, fill: { target: 0, above: 'rgba(54, 162, 235, 0.15)' } });
            if (Max) ds.push({ label: 'حداکثر', data: Max, borderColor: 'rgba(255, 99, 132, 0.8)', borderDash:[5,5], pointRadius:0, fill: { target: 1, above: 'rgba(255, 99, 132, 0.15)' } });
        }

        try {
            frontChartInstance = new Chart(ctx, {
                type: 'line',
                data: { labels: L, datasets: ds },
                options: {
                     responsive: true, maintainAspectRatio: false, spanGaps: true,
                     plugins: { legend: { position: 'top' }, tooltip: { mode: 'index', intersect: false }, filler: { propagate: false } },
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
    
    // --- Load More (بدون تغییر) ---
    $('.cpp-view-more-btn').on('click', function() {
        var btn = $(this); var wrapper = btn.closest('.cpp-grid-view-wrapper'); var next = parseInt(btn.data('page'), 10) + 1;
        btn.prop('disabled', true).text(cpp_front_vars.i18n.loading);
        $.post(cpp_front_vars.ajax_url, { action: 'cpp_load_more_products', nonce: cpp_front_vars.nonce, page: next, shortcode_type: btn.data('shortcode-type') }, function(res) {
            if (res.success && res.data.html) { wrapper.find('tbody').append(res.data.html); btn.data('page', next).prop('disabled', false).text(cpp_front_vars.i18n.view_more); if(!res.data.has_more) btn.hide(); } 
            else btn.hide();
        });
    });

    $('.cpp-grid-view-filters .filter-btn').on('click', function(e){
        e.preventDefault(); var $this = $(this);
        $this.closest('.cpp-grid-view-wrapper').find('.product-row').hide().filter($this.data('cat-id') === 'all' ? '*' : '[data-cat-id="' + $this.data('cat-id') + '"]').show();
        $this.siblings().removeClass('active'); $this.addClass('active');
        $this.closest('.cpp-grid-view-wrapper').find('.cpp-grid-view-footer').toggle($this.data('cat-id') === 'all');
    });
});
