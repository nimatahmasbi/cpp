jQuery(document).ready(function ($) {

    // 1. آکاردئون
    $('.cpp-accordion-header').on('click', function () { $(this).toggleClass('active').next('.cpp-accordion-content').slideToggle(300); });
    if ($('.cpp-accordion-content').length && !$('.cpp-accordion-content').find('.notice-error, .error').length && !window.location.hash) { $('.cpp-accordion-content').hide(); $('.cpp-accordion-header').removeClass('active'); }
    if (window.location.hash) { var target = $(window.location.hash); if (target.hasClass('cpp-accordion-content')) { target.show(); target.prev('.cpp-accordion-header').addClass('active'); } }

    // 2. آپلودر
    var mediaUploader;
    $(document).on('click', '.cpp-upload-btn', function (e) {
        e.preventDefault(); var button = $(this); var inputId = button.data("input-id"); var input_field = inputId ? jQuery("#" + inputId) : button.siblings('input[type="text"]');
        if (!input_field.length) input_field = button.closest('td').find('input[type="text"]');
        if (!input_field.length) return;
        mediaUploader = wp.media({ title: 'انتخاب تصویر', button: { text: 'استفاده' }, multiple: false });
        (function(target_input, target_preview) {
            mediaUploader.off('select'); mediaUploader.on('select', function () {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                target_input.val(attachment.url).trigger('change');
                 if(target_preview.length) target_preview.html('<img src="' + attachment.url + '" style="max-width: 100px; margin-top: 10px;">');
            }); mediaUploader.open();
        })(input_field, button.closest('td').find(".cpp-image-preview"));
    });

    // 3. ویرایش سریع
    $(document).on('dblclick', '.cpp-quick-edit, .cpp-quick-edit-select', function () {
        var cell = $(this); if (cell.hasClass('editing') || cell.closest('td').hasClass('editing-td')) return;
        var id = cell.data('id'), field = cell.data('field'), table = cell.data('table-type');
        var val = cell.text().trim(); var input;

        if (cell.hasClass('cpp-quick-edit-select')) {
            cell.addClass('editing'); input = $('<select>').addClass('cpp-quick-edit-input');
            var opts = (table === 'orders') ? cpp_admin_vars.order_statuses : cpp_admin_vars.product_statuses;
            $.each(opts, function(k, v){ $('<option>').val(k).text(v).prop('selected', k == cell.data('current')).appendTo(input); });
        } else if (field === 'min_price' || field === 'max_price') {
             var td = cell.closest('td'); td.addClass('editing-td');
             var minv = td.find('[data-field="min_price"]').text().trim(); var maxv = td.find('[data-field="max_price"]').text().trim();
             input = $('<div>').append($('<input>').addClass('cpp-quick-edit-input small-text').val(minv).attr('data-field','min_price')).append(' - ').append($('<input>').addClass('cpp-quick-edit-input small-text').val(maxv).attr('data-field','max_price'));
             cell = td;
        } else {
            cell.addClass('editing');
            input = (field === 'admin_note' || field === 'description') ? $('<textarea>').addClass('cpp-quick-edit-input').val(val) : $('<input>').addClass('cpp-quick-edit-input').val(val);
        }

        var save = $('<button>').addClass('button button-primary button-small').text('ذخیره').click(function(){
            if(field==='min_price'||field==='max_price') saveRange(cell, id, table); else saveSingle(cell, id, field, table);
        });
        var cancel = $('<button>').addClass('button button-small').text('لغو').click(function(){ window.location.reload(); });
        
        cell.html(input).append($('<div>').addClass('cpp-quick-edit-buttons').append(save).append(cancel));
        input.find('input,textarea,select').first().focus();
    });

    function saveRange(td, id, table) {
        var min = td.find('input[data-field="min_price"]').val(), max = td.find('input[data-field="max_price"]').val();
        td.text('ذخیره...');
        $.when(
            $.post(cpp_admin_vars.ajax_url, { action: 'cpp_quick_update', security: cpp_admin_vars.nonce, id: id, field: 'min_price', value: min, table_type: table }),
            $.post(cpp_admin_vars.ajax_url, { action: 'cpp_quick_update', security: cpp_admin_vars.nonce, id: id, field: 'max_price', value: max, table_type: table })
        ).done(function(){ window.location.reload(); }).fail(function(){ alert('خطا'); });
    }

    function saveSingle(cell, id, field, table) {
        var val = cell.find('.cpp-quick-edit-input').val(); cell.text('ذخیره...');
        $.post(cpp_admin_vars.ajax_url, { action: 'cpp_quick_update', security: cpp_admin_vars.nonce, id: id, field: field, value: val, table_type: table }, function(res){
            if(res.success) window.location.reload(); else alert('خطا');
        });
    }

    // 4. نمودار با امکانات (فیلتر، دانلود، زوم، لوگو)
    var chartInstance = null;
    var fullData = null;

    $(document).on('click', '.cpp-show-chart', function (e) {
        e.preventDefault();
        var pid = $(this).data('product-id');
        
        if ($('#cpp-chart-modal').length === 0) {
             var html = '<div id="cpp-chart-modal" class="cpp-modal-overlay" style="display:none;">' +
                '<div class="cpp-modal-container cpp-chart-background">' +
                '<span class="cpp-close-modal">×</span>' +
                '<h2>نمودار قیمت</h2>' +
                '<div class="cpp-chart-toolbar" style="margin-bottom:10px; direction:ltr; text-align:left;">' +
                    '<button class="button cpp-filter active" data-r="all">همه</button> ' +
                    '<button class="button cpp-filter" data-r="12">1 سال</button> ' +
                    '<button class="button cpp-filter" data-r="6">6 ماه</button> ' +
                    '<button class="button cpp-filter" data-r="3">3 ماه</button> ' +
                    '<button class="button cpp-filter" data-r="1">1 ماه</button> ' +
                    '<button class="button cpp-filter" data-r="0.25">1 هفته</button> ' +
                    '<button class="button button-primary cpp-dl" style="margin-left:10px;">دانلود</button>' +
                '</div>' +
                '<div class="cpp-chart-modal-content"><div class="cpp-chart-bg"></div><canvas id="cppPriceChart"></canvas></div>' +
                '</div></div>';
             $('body').append(html);
        }
        
        var modal = $('#cpp-chart-modal');
        var ctx = modal.find('#cppPriceChart');
        if(cpp_admin_vars.logo_url) modal.find('.cpp-chart-bg').css({'background-image':'url('+cpp_admin_vars.logo_url+')', 'opacity':'0.1'});
        
        modal.css('display', 'flex');
        if(chartInstance) chartInstance.destroy();

        $.get(cpp_admin_vars.ajax_url, { action: 'cpp_get_chart_data', product_id: pid, security: cpp_admin_vars.nonce }).done(function (res) {
            if (res.success) {
                fullData = res.data;
                renderChart(ctx, fullData, 'all');
            } else { alert(res.data.message); modal.hide(); }
        });
    });

    $(document).on('click', '.cpp-filter', function(){
        $('.cpp-filter').removeClass('active'); $(this).addClass('active');
        var r = $(this).data('r');
        if(chartInstance) chartInstance.destroy();
        renderChart($('#cppPriceChart'), fullData, r);
    });

    $(document).on('click', '.cpp-dl', function(){
        var a = document.createElement('a');
        a.href = chartInstance.toBase64Image(); a.download = 'chart.png'; a.click();
    });

    function renderChart(ctx, data, range) {
        var L=data.labels, P=data.prices, Min=data.min_prices, Max=data.max_prices;

        if(range !== 'all') {
            var count = Math.floor(parseFloat(range) * 30); // تخمین ۳۰ روز در ماه
            if(L.length > count) {
                var start = L.length - count;
                L=L.slice(start); P=P.slice(start); Min=Min.slice(start); Max=Max.slice(start);
            }
        }

        var ds = [];
        // ترتیب مهم: Min -> Base -> Max برای fill صحیح
        ds.push({ label: 'حداقل', data: Min, borderColor: 'rgba(54, 162, 235, 0.8)', borderDash:[5,5], pointRadius:0, fill: false });
        ds.push({ 
            label: 'قیمت پایه', data: P, borderColor: 'rgb(75, 192, 192)', tension: 0.1, borderWidth: 3,
            fill: { target: 0, above: 'rgba(54, 162, 235, 0.15)' } // پر کردن تا حداقل (آبی)
        });
        ds.push({ 
            label: 'حداکثر', data: Max, borderColor: 'rgba(255, 99, 132, 0.8)', borderDash:[5,5], pointRadius:0, 
            fill: { target: 1, above: 'rgba(255, 99, 132, 0.15)' } // پر کردن تا پایه (قرمز)
        });

        chartInstance = new Chart(ctx, {
            type: 'line', data: { labels: L, datasets: ds },
            options: {
                responsive: true, maintainAspectRatio: false, spanGaps: true,
                plugins: { filler: { propagate: false } },
                interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: false } }
            }
        });
    }

    // سایر بخش‌ها
    $(document).on('click', '.cpp-close-modal, .cpp-modal-overlay', function(e){ if(e.target==this || $(this).hasClass('cpp-close-modal')) $('.cpp-modal-overlay').hide(); });
    $(document).on('click', '.cpp-edit-button, .cpp-edit-cat-button', function (e) {
        e.preventDefault(); var btn=$(this); var data={security:cpp_admin_vars.nonce};
        if(btn.hasClass('cpp-edit-button')){ data.action='cpp_fetch_product_edit_form'; data.id=btn.data('product-id'); }
        else{ data.action='cpp_fetch_category_edit_form'; data.id=btn.data('cat-id'); }
        
        if($('#cpp-edit-modal').length===0) $('body').append('<div id="cpp-edit-modal" class="cpp-modal-overlay" style="display:none;"><div class="cpp-modal-container"><span class="cpp-close-modal">×</span><div class="cpp-edit-modal-content"></div></div></div>');
        var m=$('#cpp-edit-modal'); m.css('display','flex').find('.cpp-edit-modal-content').html('بارگذاری...');
        $.get(cpp_admin_vars.ajax_url, data).done(function(res){ m.find('.cpp-edit-modal-content').html(res.data.html); if(window.cpp_init_media_uploader) window.cpp_init_media_uploader(); });
    });
    
    $(document).on('submit', '#cpp-edit-product-form, #cpp-edit-category-form', function(e){
        e.preventDefault(); var form=$(this); 
        var act = form.attr('id')==='cpp-edit-product-form'?'cpp_handle_edit_product_ajax':'cpp_handle_edit_category_ajax';
        $.post(cpp_admin_vars.ajax_url, form.serialize()+'&action='+act, function(res){ if(res.success){alert('ذخیره شد'); window.location.reload();}else alert('خطا'); });
    });
    
    $('#cpp-test-email-btn, #cpp-test-sms-btn').click(function(){
        var btn=$(this); btn.prop('disabled',true);
        var act = (btn.attr('id')==='cpp-test-email-btn')?'cpp_test_email':'cpp_test_sms';
        $.post(cpp_admin_vars.ajax_url, {action:act, security:cpp_admin_vars.nonce}, function(res){ btn.siblings('textarea').val(res.data.log); btn.prop('disabled',false); });
    });
});
