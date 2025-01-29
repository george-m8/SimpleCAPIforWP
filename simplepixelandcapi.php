<?php
/*
Plugin Name: Simple FB Pixel and CAPI
Description: A simple plugin to add the Facebook Pixel code and Meta CAPI to your WordPress site. Currently only functioning with PageView/page_view event.
Version: 1.1
Author: George M
*/

defined('ABSPATH') || exit; // Exit if accessed directly

// Hook into wp_head to inject Pixel code
add_action('wp_head', 'inject_facebook_pixel_code');

// Hook into template_redirect to send CAPI events
add_action('template_redirect', 'send_capi_pageview_event');

/**
 * Function to inject the Facebook Pixel code
 */
function inject_facebook_pixel_code() {
    $pixel_id = get_facebook_pixel_id();

    // If no Pixel ID is set, do nothing
    if (!$pixel_id) {
        return;
    }

    echo "
    <!-- Facebook Meta Pixel Code -->
    <script>
      !function(f,b,e,v,n,t,s)
      {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
      n.queue=[];t=b.createElement(e);t.async=!0;
      t.src=v;s=b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t,s)}(window, document,'script',
      'https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', '{$pixel_id}'); 
      fbq('track', 'PageView');
    </script>
    <noscript>
      <img height='1' width='1' style='display:none'
      src='https://www.facebook.com/tr?id={$pixel_id}&ev=PageView&noscript=1'/>
    </noscript>
    <!-- End Facebook Meta Pixel Code -->
    ";
}

/**
 * Function to send a PageView event via Facebook CAPI
 */
function send_capi_pageview_event() {
    // Load the Pixel ID and Access Token
    $pixel_id = get_facebook_pixel_id();
    $access_token = get_facebook_access_token();
    

    // If no Pixel ID or Access Token is set, do nothing
    if (!$pixel_id || !$access_token) {
        error_log('Facebook CAPI: Missing Pixel ID or Access Token.');
        return;
    }

    // Prepare the payload for the PageView event
    $payload = [
        'data' => [
            [
                'event_name' => 'page_view',
                'event_time' => time(),
                'action_source' => 'website',
                'event_id' => uniqid('event_', true),
                'event_source_url' => home_url($_SERVER['REQUEST_URI']),
                'user_data' => [
                    'fbp' => $_COOKIE['_fbp'] ?? '',
                    'fbc' => $_GET['fbc'] ?? '',
                    'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            ]
        ]
    ];

    // Send the payload to Facebook CAPI
    $url = "https://graph.facebook.com/v12.0/{$pixel_id}/events?access_token={$access_token}";
    $response = wp_remote_post($url, [
        'body'    => json_encode($payload),
        'headers' => ['Content-Type' => 'application/json']
    ]);

    // Debug log the payload and response
    //error_log('Facebook CAPI Payload: ' . print_r($payload, true));

    // Log errors for debugging
    if (is_wp_error($response)) {
        error_log('Facebook CAPI Error: ' . $response->get_error_message());
    } else {
        error_log('Facebook CAPI PageView Sent: ' . print_r($response, true));
    }
}

/**
 * Function to fetch the Facebook Pixel ID
 */
function get_facebook_pixel_id() {
    $config_file = plugin_dir_path(__FILE__) . 'config.json';

    if (!file_exists($config_file)) {
        return false;
    }

    $config = json_decode(file_get_contents($config_file), true);

    return $config['pixel_id'] ?? false;
}

/**
 * Function to fetch the Facebook Access Token
 */
function get_facebook_access_token() {
    $config_file = plugin_dir_path(__FILE__) . 'config.json';

    if (!file_exists($config_file)) {
        return false;
    }

    $config = json_decode(file_get_contents($config_file), true);

    return $config['access_token'] ?? false;
}