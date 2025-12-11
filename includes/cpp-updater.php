<?php
if (!defined('ABSPATH')) exit;

class CPP_Auto_Updater {
    private $plugin_slug;
    private $version;
    private $remote_path;
    private $cache_key;
    private $plugin_file;

    public function __construct($plugin_file, $current_version, $remote_path) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = $current_version;
        $this->remote_path = $remote_path;
        $this->cache_key = 'cpp_update_' . md5($this->plugin_slug);

        // هوک برای بررسی وجود نسخه جدید
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        // هوک برای نمایش اطلاعات افزونه در پاپ‌آپ جزئیات
        add_filter('plugins_api', [$this, 'check_info'], 10, 3);
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // دریافت اطلاعات از سرور شما
        $remote_info = $this->request_info();

        if (
            $remote_info && 
            isset($remote_info->version) && 
            version_compare($this->version, $remote_info->version, '<')
        ) {
            $res = new stdClass();
            $res->slug = $this->plugin_slug;
            $res->plugin = $this->plugin_slug;
            $res->new_version = $remote_info->version;
            $res->package = $remote_info->download_url;
            $res->url = isset($remote_info->author_profile) ? $remote_info->author_profile : '';
            
            $transient->response[$this->plugin_slug] = $res;
        }

        return $transient;
    }

    public function check_info($false, $action, $arg) {
        if ($action !== 'plugin_information') {
            return $false;
        }

        // بررسی اینکه آیا درخواست برای همین افزونه است
        if (isset($arg->slug) && $arg->slug === $this->plugin_slug) {
             // slug در اینجا ممکن است فقط نام پوشه باشد، باید دقیق چک شود
        } 
        
        // راه ساده‌تر: اگر اسلاگ درخواست شده با نام پوشه ما یکی بود
        if (basename(dirname($this->plugin_file)) !== $arg->slug) {
           // return $false; // برخی اوقات وردپرس فقط نام فایل را می‌فرستد، این شرط را منعطف می‌کنیم
        }

        $remote_info = $this->request_info();

        if ($remote_info) {
            $res = new stdClass();
            $res->name = $remote_info->name;
            $res->slug = $this->plugin_slug; // استفاده از اسلاگ کامل
            $res->version = $remote_info->version;
            $res->author = $remote_info->author;
            $res->author_profile = $remote_info->author_profile;
            $res->requires = $remote_info->requires;
            $res->tested = $remote_info->tested;
            $res->requires_php = $remote_info->requires_php;
            $res->last_updated = $remote_info->last_updated;
            $res->sections = (array) $remote_info->sections;
            $res->download_link = $remote_info->download_url;
            $res->banners = (array) ($remote_info->banners ?? []);

            return $res;
        }

        return $false;
    }

    private function request_info() {
        // استفاده از ترنزینت برای کش کردن پاسخ و جلوگیری از کندی پیشخوان
        $remote_info = get_transient($this->cache_key);

        if (false === $remote_info) {
            $request = wp_remote_get($this->remote_path, [
                'timeout' => 10,
                'headers' => ['Accept' => 'application/json']
            ]);

            if (is_wp_error($request) || wp_remote_retrieve_response_code($request) !== 200) {
                return false;
            }

            $body = wp_remote_retrieve_body($request);
            $remote_info = json_decode($body);

            if ($remote_info) {
                // کش کردن اطلاعات برای ۱۲ ساعت
                set_transient($this->cache_key, $remote_info, 12 * HOUR_IN_SECONDS);
            }
        }

        return $remote_info;
    }
}
