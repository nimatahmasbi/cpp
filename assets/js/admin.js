/* استایل برای بخش آکاردئونی */
.cpp-accordion-header {
    background-color: #f1f1f1; color: #444; cursor: pointer;
    padding: 15px 18px; 
    width: 100%; border: none; text-align: right; outline: none;
    font-size: 14px; 
    transition: background-color 0.3s;
    border-radius: 4px; 
    margin-top: 10px; font-weight: bold; box-sizing: border-box;
}
.cpp-accordion-header:hover { background-color: #e0e0e0; }
.cpp-accordion-header.active { background-color: #ddd; }
.cpp-accordion-content {
    padding: 15px 18px; background-color: white; overflow: hidden;
    border: 1px solid #ddd; border-top: none;
    border-radius: 0 0 4px 4px; margin-bottom: 20px;
}

/* استایل برای ویرایش سریع */
.cpp-quick-edit, .cpp-quick-edit-select { cursor: pointer; padding: 8px !important; }
.cpp-quick-edit:hover, .cpp-quick-edit-select:hover { background-color: #fcf8e3; }
.cpp-quick-edit.editing, .cpp-quick-edit-select.editing,
td.editing-td 
{ padding: 2px !important; } 

.cpp-quick-edit-input {
    box-sizing: border-box; width: 100%;
    border: 1px solid #8cc1e9; 
    padding: 6px 8px; margin: 0; line-height: 1.4; min-height: 30px;
    font-size: inherit; 
    box-shadow: 0 0 0 1px #8cc1e9;
}
.cpp-quick-edit textarea.cpp-quick-edit-input { min-height: 80px; }
.cpp-quick-edit-buttons { margin-top: 5px; }
.cpp-quick-edit-buttons button { margin-left: 5px; }
td.editing-td input.small-text { width: calc(50% - 10px); display: inline-block; }

/* استایل پیش نمایش تصویر */
.cpp-image-preview img { border: 1px solid #ddd; padding: 3px; max-height: 50px; width: auto; }

/* استایل‌های صفحه تنظیمات */
.cpp-settings-wrap .nav-tab-wrapper { margin-bottom: 0; }
.cpp-settings-wrap form { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; }
.cpp-settings-wrap .form-table th { width: 220px; }
.cpp-settings-wrap .form-table code { background: #eee; padding: 2px 5px; border-radius: 3px; font-size: 0.9em; }
.cpp-settings-wrap .form-table .description.clear { clear: both; padding-top: 5px; }

/* --- استایل‌های جدید برای پاپ‌آپ (Modal) در مدیریت --- */
.cpp-modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.6); 
    display: none; 
    align-items: center; justify-content: center;
    z-index: 99999; /* Ensure it's on top of WP admin menu */
    direction: rtl; 
}

.cpp-modal-container {
    background-color: #fff; padding: 25px; border-radius: 8px; 
    width: 90%; max-width: 600px;
    position: relative; box-shadow: 0 5px 20px rgba(0,0,0,0.3); 
    margin: auto; text-align: right; box-sizing: border-box;
    max-height: 90vh; overflow-y: auto;
}

.cpp-close-modal {
    position: absolute; top: 10px; left: 15px; 
    font-size: 28px; cursor: pointer; color: #888; line-height: 1;
}
.cpp-close-modal:hover { color: #d00; }

.cpp-modal-container h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; }

/* رفع تداخل با استایل‌های وردپرس در داخل مدال */
.cpp-modal-container .form-table th { width: auto; min-width: 120px; }
.cpp-modal-container input[type="text"], 
.cpp-modal-container select, 
.cpp-modal-container textarea { width: 100%; max-width: 100%; }

/* --- استایل‌های جدول سفارشات ریسپانسیو --- */
.cpp-orders-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.wp-list-table.cpp-orders-table {
    border: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-radius: 4px;
    margin-top: 1em;
}
.wp-list-table.cpp-orders-table th,
.wp-list-table.cpp-orders-table td {
    border: none;
    padding: 10px 12px;
    vertical-align: middle;
}
.wp-list-table.cpp-orders-table tbody tr {
    background-color: #fff;
    border-bottom: 1px solid #f0f0f0;
}
.wp-list-table.cpp-orders-table tbody tr:last-child {
    border-bottom: none;
}
.column-note span[title],
.column-admin_note span[title] {
    cursor: help;
    border-bottom: 1px dotted #999;
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media screen and (max-width: 782px) {
    .wp-list-table.cpp-orders-table thead,
    .wp-list-table.cpp-orders-table tfoot {
        display: none;
    }
    .wp-list-table.cpp-orders-table tr {
        display: block;
        margin-bottom: 15px;
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    .wp-list-table.cpp-orders-table td {
        display: block;
        text-align: right !important;
        padding-left: 50%;
        position: relative;
        border-bottom: 1px solid #f0f0f0;
        white-space: normal;
        min-height: 20px;
    }
    .wp-list-table.cpp-orders-table td:last-child {
        border-bottom: none;
         padding-bottom: 15px;
    }
    .wp-list-table.cpp-orders-table td::before {
        content: attr(data-colname);
        position: absolute;
        left: auto;
        right: 10px;
        width: calc(50% - 20px);
        font-weight: bold;
        color: #555;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .wp-list-table.cpp-orders-table td.column-id {
        font-weight: bold;
        background-color: #f9f9f9;
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
    }
    .wp-list-table.cpp-orders-table td.column-actions {
        text-align: center !important;
         padding-left: 10px;
    }
     .wp-list-table.cpp-orders-table td.column-actions::before {
        display: none;
    }
     .wp-list-table.cpp-orders-table td.cpp-quick-edit,
     .wp-list-table.cpp-orders-table td.cpp-quick-edit-select {
        cursor: pointer;
     }
     .wp-list-table.cpp-orders-table td.editing .cpp-quick-edit-input,
     .wp-list-table.cpp-orders-table td.editing-td .cpp-quick-edit-input {
         width: 100%;
         font-size: 1em;
     }
     .wp-list-table.cpp-orders-table td.editing-td input.small-text {
         width: calc(50% - 10px);
     }
}
