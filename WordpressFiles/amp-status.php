<?php
/*
Plugin Name: AMP Server Status
Description: Displays status from AMP instances using a secure relay, with WP fallback cache.
Version: 1.6
Author: ChatGPT/bburd
Author URI: https://github.com/bburd
*/

add_shortcode('amp_server_status', 'amp_status_shortcode');

// Admin settings
add_action('admin_menu', function() {
    add_options_page('AMP Server Status', 'AMP Server Status', 'manage_options', 'amp_server_status', function() {
        ?>
        <div class="wrap">
            <h1>AMP Server Status Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('amp_status_settings_group');
                do_settings_sections('amp_server_status');
                submit_button();
                ?>
            </form>
            <hr>
            <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <input type="hidden" name="action" value="amp_status_manual_refresh">
                <?php submit_button('Refresh Now'); ?>
            </form>
        </div>
        <?php
    });
});

add_action('admin_init', function() {
    register_setting('amp_status_settings_group', 'amp_status_relay_key');
    register_setting('amp_status_settings_group', 'amp_status_relay_url');

    add_settings_section('amp_status_main_section', '', null, 'amp_server_status');

    add_settings_field('amp_status_relay_key_field', 'Relay Key', function() {
        $value = get_option('amp_status_relay_key', '');
        echo '<input type="text" name="amp_status_relay_key" value="' . esc_attr($value) . '" size="50">';
    }, 'amp_server_status', 'amp_status_main_section');

    add_settings_field('amp_status_relay_url_field', 'Relay URL', function() {
        $value = get_option('amp_status_relay_url', '');
        echo '<input type="text" name="amp_status_relay_url" value="' . esc_attr($value) . '" size="50">';
    }, 'amp_server_status', 'amp_status_main_section');
});

// Refresh now AJAX
add_action('wp_ajax_amp_status_manual_refresh', function() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    delete_transient('amp_status_cache_wp');
    update_option('amp_status_last_update', 0);
    wp_redirect(admin_url('options-general.php?page=amp_server_status&refreshed=1'));
    exit;
});

// Frontend UI
function amp_status_shortcode() {
    ob_start(); ?>
    <style>
        .amp-status-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem 2rem;
        }
        .amp-status-card {
            background: #1e1e1e;
            color: #fff;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            font-family: 'Figtree', sans-serif;
        }
        .amp-status-card h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            font-family: 'Poppins', sans-serif;
        }
        .amp-status-card span {
            display: block;
            margin-top: 0.25rem;
            font-family: 'Figtree', sans-serif;
        }
        .amp-progress-bar {
            background-color: #374151;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 4px;
            height: 8px;
            width: 100%;
        }
        .amp-progress-fill {
            height: 100%;
            background: linear-gradient(to right, #F59E0B, #D9D9D9);
            transition: width 0.5s ease;
        }
    </style>
    <div class="amp-status-container" id="amp-status-block">Loading...</div>
    <script>
        function fetchAmpStatus() {
            fetch('<?php echo esc_url(admin_url('admin-ajax.php?action=fetch_amp_cache')); ?>')
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById('amp-status-block');
                container.innerHTML = '';
                for (const [label, server] of Object.entries(data)) {
                    const title = label.replace(':', ':<br>');

                    if (server.error) {
                        container.innerHTML += `
                            <div class='amp-status-card'>
                                <h4>${title}</h4>
                                <span style="color: #F59E0B;"><strong>⚠️ Offline or unreachable</strong></span>
                            </div>`;
                        continue;
                    }

                    const metrics = server.Metrics || {};
                    const uptime = server.Uptime || 'N/A';
                    let appStatus = '';

                    if (server.AppRunning === true) {
                        appStatus = `<span style="color: #22c55e;"><strong>🟢 Application Running</strong></span>`;
                    } else if (server.AppRunning === false) {
                        appStatus = `<span style="color: #ef4444;"><strong>🔴 Application Stopped</strong></span>`;
                    }

                    let card = `<div class='amp-status-card'>
                        <h4>${title}</h4>
                        ${appStatus}
                        <span><strong>Uptime:</strong> ${uptime}</span>`;

                    const allowedKeys = {
                        'CPU Usage': 'CPU',
                        'Memory Usage': 'RAM'
                    };

                    for (const [key, metric] of Object.entries(metrics)) {
                        if (!allowedKeys[key]) continue;

                        let bar = '';
                        if (typeof metric.Percent !== 'undefined') {
                            bar = `
                                <div class="amp-progress-bar">
                                    <div class="amp-progress-fill" style="width: ${metric.Percent}%;"></div>
                                </div>`;
                        }

                        let value = metric.RawValue;
                        let units = metric.Units;

                        if (allowedKeys[key] === 'RAM' && units === 'MB') {
                            value = (value / 1024).toFixed(1);
                            units = 'GB';
                        }

                        card += `<span style="color: #F59E0B;">
                            <strong>${allowedKeys[key]}:</strong> ${value} ${units}${bar}</span>`;
                    }

                    card += `</div>`;
                    container.innerHTML += card;
                }
            })
            .catch(() => {
                document.getElementById('amp-status-block').innerText = 'Error fetching AMP status.';
            });
        }

        fetchAmpStatus(); // initial load
        setInterval(fetchAmpStatus, 900000); // refresh every 15 minutes
    </script>
    <?php
    return ob_get_clean();
}

// AJAX handler
add_action('wp_ajax_nopriv_fetch_amp_cache', 'amp_status_cache_handler');
add_action('wp_ajax_fetch_amp_cache', 'amp_status_cache_handler');

function amp_status_cache_handler() {
    $cached = get_transient('amp_status_cache_wp');
    if ($cached) {
        wp_send_json($cached);
    } else {
        wp_send_json(['error' => 'No cached AMP data available']);
    }
}

// Background update every page load (rate limited)
add_action('wp_loaded', 'amp_background_cache_check');

function amp_background_cache_check() {
    $last_update = get_option('amp_status_last_update', 0);
    if (time() - $last_update < 900) return;

    $relay_key = get_option('amp_status_relay_key', '');
    $relay_url = get_option('amp_status_relay_url', '');
    if (!$relay_key || !$relay_url) return;

    $request_url = $relay_url . '?key=' . urlencode($relay_key);
    $response = wp_remote_get($request_url);

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($data)) {
            set_transient('amp_status_cache_wp', $data, 900);
            update_option('amp_status_last_update', time());
        }
    }
}
