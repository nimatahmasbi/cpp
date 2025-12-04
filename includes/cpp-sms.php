<?php
if (!defined('ABSPATH')) exit;

class CPP_Full_SMS {
    /**
     * ارسال پیامک اعلان سفارش جدید به مدیر با جایگزینی متغیرها در الگو
     * @param array $placeholders شامل: {product_name}, {customer_name}, {phone}, {qty}, {note} ...
     */
    public static function send_notification($placeholders){
        $service    = get_option('cpp_sms_service');
        $apiKey     = get_option('cpp_sms_api_key');
        $sender     = get_option('cpp_sms_sender');
        $adminPhone = get_option('cpp_admin_phone');
        $pattern_code = get_option('cpp_sms_pattern_code'); // کد الگوی مدیر

        // بررسی می‌کنیم که سرویس IPPanel فعال باشد و همه مقادیر لازم مدیر پر شده باشند
        if (!$service || $service !== 'ippanel' || !$apiKey || !$adminPhone || !$sender || !$pattern_code) {
            // Log error if admin SMS cannot be sent due to settings
            if ($service === 'ippanel') { // Only log if ippanel was intended
                 error_log("CPP Admin SMS Error: Cannot send notification. Missing IPPanel settings (API Key, Sender, Admin Phone, or Admin Pattern Code).");
            }
            return false;
        }

        // --- تبدیل متغیرهای افزونه ({key}) به متغیرهای الگو (key) ---
        // اطمینان از ارسال تمام متغیرهای مورد انتظار الگوی مدیر
        $admin_variables_needed = ['product_name', 'customer_name', 'phone', 'qty', 'unit', 'load_location', 'note'];
        $variables = [];
        foreach($admin_variables_needed as $var_name) {
            $placeholder_key = '{' . $var_name . '}';
            // Assign value if exists in placeholders, otherwise send an empty string or placeholder text
            $variables[$var_name] = isset($placeholders[$placeholder_key]) ? ($placeholders[$placeholder_key] ?: '-') : '-'; // Use '-' for empty values?
        }


        // --- فقط تابع ارسال الگوی IPPanel فراخوانی می‌شود ---
        // error_log("Attempting to send ADMIN SMS with vars: ".print_r($variables, true)); // Debug log
        $sent = self::ippanel_send_pattern($apiKey, $sender, $adminPhone, $pattern_code, $variables);
        if (!$sent) {
             error_log("CPP Admin SMS FAILED to send to ".$adminPhone." using pattern ".$pattern_code);
        }
        return $sent; // Return true/false based on attempt result
    }

    /**
     * تابع ارسال پیامک با الگوی IPPanel با استفاده از wp_remote_post
     * @param string $apiKey
     * @param string $sender
     * @param string $to
     * @param string $pattern_code
     * @param array $variables آرایه‌ای از متغیرهای الگو به شکل ['var_name' => 'value']
     * @return bool True on success, False on failure
     */
    // تابع عمومی شده تا برای مشتری هم استفاده شود
    public static function ippanel_send_pattern($apiKey, $sender, $to, $pattern_code, $variables){

        // Validate essential parameters before proceeding
        if (empty($apiKey) || empty($sender) || empty($to) || empty($pattern_code) || !is_array($variables)) {
            error_log("CPP ippanel_send_pattern Error: Invalid parameters provided.");
            return false;
        }


        $url = 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send';
        $data = [
            'code'      => $pattern_code,
            'sender'    => $sender,
            'recipient' => $to, // گیرنده باید فقط یک شماره باشد
            'variable'  => $variables,
        ];

        // اطمینان از اینکه recipient آرایه نباشد و پاکسازی شماره
        if (is_array($data['recipient'])) {
             $data['recipient'] = $data['recipient'][0];
        }
        // Basic cleanup for phone number, adjust regex if international numbers are expected differently
        $data['recipient'] = preg_replace('/[^\d]/', '', $data['recipient']); // Remove non-digits
        // Optional: Add + or country code prefix check if needed


        $body = json_encode($data);
        // Check for JSON encoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
             error_log('CPP ippanel_send_pattern Error: Failed to encode JSON body. Error: ' . json_last_error_msg());
             return false;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'apikey'       => $apiKey
        ];

        $args = [
            'body'        => $body,
            'headers'     => $headers,
            'method'      => 'POST',
            'data_format' => 'body',
            'timeout'     => 20, // Increased timeout
        ];

        // error_log("Sending IPPanel Request to $url with body: $body"); // Debug log request body

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('CPP IPPanel WP HTTP Error (Pattern Send to '.$to.'): ' . $error_message);
            return false;
        } else {
            $http_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            // error_log('IPPanel Response (Pattern Send to '.$to.') - Code: '.$http_code.' | Body: '.$response_body); // Debug log response

            if ($http_code >= 200 && $http_code < 300) {
                $result = json_decode($response_body);
                // بررسی دقیق‌تر پاسخ موفق IPPanel
                if ($result && isset($result->status->code) && $result->status->code == 0 && isset($result->data->message_id)) {
                    // Log success minimally unless debugging needed
                    // error_log('CPP IPPanel SMS Sent Successfully to '.$to.'. Message ID: '.$result->data->message_id);
                    return true; // ارسال موفقیت آمیز بود
                } else {
                     $api_error = 'Unknown API Logic Error or unexpected success structure.';
                     if ($result && isset($result->status->message)) {
                        $api_error = $result->status->message;
                     } elseif ($result && isset($result->errorMessage)) {
                         $api_error = $result->errorMessage;
                     }
                     error_log('CPP IPPanel API Logic Error (Pattern Send to '.$to.'): ' . $api_error . ' | Response: ' . $response_body);
                     return false;
                }
            } else {
                 // Try to get a meaningful error from the body
                 $error_detail = $response_body;
                 $result = json_decode($response_body);
                 if ($result && isset($result->status->message)) {
                     $error_detail = $result->status->message;
                 } elseif ($result && isset($result->errorMessage)) {
                     $error_detail = $result->errorMessage;
                 } elseif ($result && isset($result->message)) {
                     $error_detail = $result->message;
                 }
                error_log('CPP IPPanel HTTP Error (Pattern Send to '.$to.'): Code ' . $http_code . ' | Detail: ' . $error_detail);
                return false;
            }
        }
    } // End ippanel_send_pattern

} // End Class
?>
