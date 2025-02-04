<?php
/*
Plugin Name: Simple FB Pixel and CAPI
Description: A simple plugin to add the Facebook Pixel code and Meta CAPI to your WordPress site.
Version: 2.0
Author: George M
*/

defined('ABSPATH') || exit;

// Set a debug mode constant: toggle to true/false
define('SIMPLE_PIXEL_DEBUG', false);

// Require our CAPI functions (payload building & sending)
require_once plugin_dir_path(__FILE__) . 'includes/capi-functions.php';

// Hook to insert the pixel code in the header
add_action('wp_head', 'simple_fb_pixel_inject_code');
function simple_fb_pixel_inject_code() {
    $pixel_id = simple_fb_get_config('pixel_id');
    if (!$pixel_id) {
        if (SIMPLE_PIXEL_DEBUG) {
            error_log('FB Pixel not injected: no pixel ID found.');
        }
        return;
    }
    ?>
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
      fbq('init', '<?php echo esc_js($pixel_id); ?>'); 
      fbq('track', 'PageView');
    </script>
    <noscript>
      <img height='1' width='1' style='display:none'
      src='https://www.facebook.com/tr?id=<?php echo esc_attr($pixel_id); ?>&ev=PageView&noscript=1'/>
    </noscript>
    <!-- End Facebook Meta Pixel Code -->
    <?php
}

// Hook page view CAPI on template_redirect
add_action('template_redirect', 'simple_fb_pixel_send_pageview_event');
function simple_fb_pixel_send_pageview_event() {
    // Build the payload
    $payload = simple_fb_build_capi_payload('page_view', SIMPLE_PIXEL_DEBUG, [
        'event_source_url' => home_url($_SERVER['REQUEST_URI']),
    ]);

    // Send it
    simple_fb_send_capi_event($payload, SIMPLE_PIXEL_DEBUG);
}

// Register/Enqueue the JS
add_action('wp_enqueue_scripts', 'simple_fb_pixel_enqueue_scripts');
function simple_fb_pixel_enqueue_scripts() {
    // Enqueue your JS file
    wp_enqueue_script(
        'simple_pixel-hubspot-tracking',
        plugin_dir_url(__FILE__) . 'js/hubspotTracking.js',
        [],
        '1.0',
        false
    );

    // Pass admin-ajax URL to JS
    wp_localize_script(
        'simple_pixel-hubspot-tracking',
        'simplePixelData',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]
    );
}

// AJAX actions for sending a Lead event
add_action('wp_ajax_send_lead_capi_event', 'simple_fb_pixel_lead_capi_event');
add_action('wp_ajax_nopriv_send_lead_capi_event', 'simple_fb_pixel_lead_capi_event');
function simple_fb_pixel_lead_capi_event() {
    $payload = simple_fb_build_capi_payload('Lead', SIMPLE_PIXEL_DEBUG);
    $response = simple_fb_send_capi_event($payload, SIMPLE_PIXEL_DEBUG);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    } else {
        wp_send_json_success('Lead event sent via CAPI!');
    }
}