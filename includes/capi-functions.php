<?php
defined('ABSPATH') || exit;

/**
 * Return configuration from config.json or fallback if missing
 *
 * @param string $key The config key to retrieve, e.g. 'pixel_id' or 'access_token'
 * @return mixed|false
 */
function simple_fb_get_config($key) {
    $config_file = plugin_dir_path(__FILE__) . '../config.json';
    if (!file_exists($config_file)) {
        return false;
    }

    $config = json_decode(file_get_contents($config_file), true);
    return $config[$key] ?? false;
}

/**
 * Build a Facebook CAPI payload
 *
 * @param string $eventName e.g. 'page_view', 'Lead', etc.
 * @param bool   $debug     Whether we want debug logs
 * @param array  $additionalData Additional data like event_source_url or extra user_data
 * @return array The structured payload ready for sending
 */
function simple_fb_build_capi_payload($eventName, $debug = false, $additionalData = []) {
    // Basic user data
    $userData = [
        'fbp'               => $_COOKIE['_fbp'] ?? '',
        'fbc'               => $_COOKIE['_fbc'] ?? '',
        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];

    // If there's user data in additionalData, merge that in
    if (isset($additionalData['user_data'])) {
        $userData = array_merge($userData, $additionalData['user_data']);
        unset($additionalData['user_data']);
    }

    // If there's an event_source_url or anything else relevant, we can handle it
    // We'll just merge the entire array
    $eventData = array_merge([
        'event_name'    => $eventName,
        'event_time'    => time(),
        'action_source' => 'website',
        'event_id'      => uniqid($eventName . '_', true),
        'user_data'     => $userData,
    ], $additionalData);

    $payload = [
        'data' => [
            $eventData
        ],
    ];

    if ($debug) {
        error_log('Building CAPI payload: ' . print_r($payload, true));
    }

    return $payload;
}

/**
 * Send the Facebook CAPI event to Meta
 *
 * @param array $payload  The payload array from build_capi_payload
 * @param bool  $debug    Whether we want debug logs
 * @return array|WP_Error The response
 */
function simple_fb_send_capi_event($payload, $debug = false) {
    // Grab pixel & token
    $pixel_id    = simple_fb_get_config('pixel_id');
    $accessToken = simple_fb_get_config('access_token');

    if (!$pixel_id || !$accessToken) {
        // If missing config, return an error early
        $error = new WP_Error('capi_config_missing', 'Missing Pixel ID or Access Token.');
        if ($debug) {
            error_log('CAPI error: ' . $error->get_error_message());
        }
        return $error;
    }

    // Endpoint
    $url = "https://graph.facebook.com/v12.0/{$pixel_id}/events?access_token={$accessToken}";

    // Make the request
    $response = wp_remote_post($url, [
        'body'    => json_encode($payload),
        'headers' => ['Content-Type' => 'application/json'],
    ]);

    if (is_wp_error($response)) {
        if ($debug) {
            error_log('CAPI request error: ' . $response->get_error_message());
        }
        return $response;
    }

    if ($debug) {
        error_log('CAPI event sent successfully. Response: ' . print_r($response, true));
    }

    return $response;
}