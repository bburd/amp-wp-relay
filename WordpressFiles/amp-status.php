<?php
/*
Plugin Name: AMP Server Status
Description: Displays status from AMP instances using a secure relay, with WP fallback cache.
Version: 1.7
Author: ChatGPT/bburd
Author URI: https://github.com/bburd
*/

define('AMP_CACHE_DURATION', 900);

add_shortcode('amp_status', function($atts) {
    $alias = isset($atts['alias']) ? sanitize_title($atts['alias']) : null;
    return amp_render_card($alias);
});

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
    register_setting('amp_status_settings_group', 'amp_status_refresh_interval');
    register_setting('amp_status_settings_group', 'amp_status_ui_font');
    register_setting('amp_status_settings_group', 'amp_status_ui_text_color');
    register_setting('amp_status_settings_group', 'amp_status_ui_bg_color');
    register_setting('amp_status_settings_group', 'amp_status_ui_border_radius');

    add_settings_section('amp_status_main_section', '', null, 'amp_server_status');

    add_settings_field('amp_status_relay_key_field', 'Relay Key', function() {
        $value = get_option('amp_status_relay_key', '');
        echo '<input type="text" name="amp_status_relay_key" value="' . esc_attr($value) . '" size="50">';
        echo '<p class="description">Secure key used to authenticate AMP relay access. Rotate regularly.</p>';
    }, 'amp_server_status', 'amp_status_main_section');

    add_settings_field('amp_status_relay_url_field', 'Relay URL', function() {
        $value = get_option('amp_status_relay_url', '');
        echo '<input type="text" name="amp_status_relay_url" value="' . esc_attr($value) . '" size="50">';
        echo '<p class="description">Endpoint URL to your AMP relay script (e.g., https://example.com/amp-status-relay.php).</p>';
    }, 'amp_server_status', 'amp_status_main_section');

    add_settings_field('amp_status_refresh_interval_field', 'Refresh Interval (seconds)', function() {
        $value = get_option('amp_status_refresh_interval', AMP_CACHE_DURATION);
        echo '<input type="number" name="amp_status_refresh_interval" value="' . esc_attr($value) . '" min="60">';
        echo '<p class="description">Interval for refreshing status data. Default is 900 seconds (15 min).</p>';
    }, 'amp_server_status', 'amp_status_main_section');

    add_settings_field('amp_status_ui_font_field', 'Font Family', function() {
        $value = get_option('amp_status_ui_font', 'Figtree');
        echo '<input type="text" name="amp_status_ui_font" value="' . esc_attr($value) . '" placeholder="e.g. Roboto, Arial">';
        echo '<p class="description">Use fonts from your theme or Google Fonts. Inherits from site styles if unset.</p>';
    }, 'amp_server_status', 'amp_status_main_section');

    add_settings_field('amp_status_ui_text_color_field', 'Text Color', function() {
        $value = get_option('amp_status_ui_text_color', '#ffffff');
        echo '<input type="text" name="amp_status_ui_text_color" id="amp_text_color" value="' . esc_attr($value) . '" maxlength="9" style="width:100px;">';
        echo '<input type="color" id="amp_text_color_picker" value="' . esc_attr(substr($value, 0, 7)) . '">';
        echo '<p class="description">Text color for the server cards. Accepts hex format.</p>';
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const hexInput = document.getElementById("amp_text_color");
            const colorInput = document.getElementById("amp_text_color_picker");
            colorInput.addEventListener("input", () => hexInput.value = colorInput.value);
        });
        </script>';
    }, 'amp_server_status', 'amp_status_main_section');

    add_settings_field('amp_status_ui_bg_color_field', 'Background Color', function() {
        $value = get_option('amp_status_ui_bg_color', '#1e1e1e');
        $alpha = strlen($value) === 9 ? hexdec(substr($value, 7, 2)) / 255 : 1;
        echo '<input type="text" name="amp_status_ui_bg_color" id="amp_bg_color" value="' . esc_attr($value) . '" maxlength="9" style="width:100px;">';
        echo '<input type="color" id="amp_bg_color_picker" value="' . esc_attr(substr($value, 0, 7)) . '">';
        echo '<input type="range" id="amp_bg_color_alpha" min="0" max="1" step="0.01" value="' . $alpha . '">';
        echo '<p class="description">Card background color. Append 2 hex digits (00-FF) for transparency.</p>';
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const hexInput = document.getElementById("amp_bg_color");
            const colorInput = document.getElementById("amp_bg_color_picker");
            const alphaSlider = document.getElementById("amp_bg_color_alpha");
            const updateHex = () => {
                const alpha = Math.round(parseFloat(alphaSlider.value) * 255).toString(16).padStart(2, "0");
                hexInput.value = colorInput.value + alpha;
            };
            colorInput.addEventListener("input", updateHex);
            alphaSlider.addEventListener("input", updateHex);
        });
        </script>';
    }, 'amp_server_status', 'amp_status_main_section');

    add_settings_field('amp_status_ui_border_radius_field', 'Card Border Radius (px)', function() {
        $value = max(0, min(100, (int) get_option('amp_status_ui_border_radius', 8)));
        echo '<input type="number" name="amp_status_ui_border_radius" value="' . esc_attr($value) . '" min="0" max="100" step="1">';
        echo '<p class="description">Adjusts roundness of card corners. Use 0 for sharp edges.</p>';
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

// Dynamic shortcode registration from cache
function amp_register_alias_shortcodes($data) {
    foreach ($data as $label => $server) {
        if (!empty($server['Alias'])) {
            $alias = sanitize_title($server['Alias']);
            add_shortcode("amp_status_{$alias}", function() use ($label) {
                return amp_render_card($label);
            });
        }
    }
}

add_action('init', function() {
    $cached = get_transient('amp_status_cache_wp');
    if (is_array($cached)) {
        amp_register_alias_shortcodes($cached);
    }
});

// Frontend UI
function amp_render_card($alias_filter = null) {
    $unique_id = 'amp-status-block-' . ($alias_filter ? sanitize_title($alias_filter) : uniqid());
    $interval = (int) get_option('amp_status_refresh_interval', 900) * 1000;
    $font_family = esc_attr(get_option('amp_status_ui_font', 'Figtree'));
    $text_color = esc_attr(get_option('amp_status_ui_text_color', '#ffffff'));
    $bg_color = esc_attr(get_option('amp_status_ui_bg_color', '#1e1e1e'));
    $border_radius = esc_attr(get_option('amp_status_ui_border_radius', 8));

    ob_start(); ?>
    <style>
        .amp-status-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem 2rem;
        }
        .amp-status-card {
            background: <?php echo $bg_color; ?>;
            color: <?php echo $text_color; ?>;
            border-radius: <?php echo $border_radius; ?>px;
            padding: 1rem;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            font-family: <?php echo $font_family; ?>, sans-serif;
        }
        .amp-status-card h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            font-family: <?php echo $font_family; ?>, sans-serif;
        }
        .amp-status-card span {
            display: block;
            margin-top: 0.25rem;
        }
        .amp-status-card a {
            color: #38bdf8;
            word-break: break-word;
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
    <div class="amp-status-container" id="<?php echo esc_attr($unique_id); ?>">Loading...</div>
    <script>
        (function() {
            const container = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!container) return;

            function fetchAmpStatus() {
                fetch('<?php echo esc_url(admin_url('admin-ajax.php?action=fetch_amp_cache')); ?>')
                .then(res => res.json())
                .then(data => {
                    container.innerHTML = '';
                    for (const [label, server] of Object.entries(data)) {
                        if ('<?php echo esc_js($alias_filter); ?>' && server.Alias !== '<?php echo esc_js($alias_filter); ?>') continue;

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
                        const appStatus = server.AppRunning
                            ? `<span style="color: #22c55e;"><strong>🟢 Application Running</strong></span>`
                            : `<span style="color: #ef4444;"><strong>🔴 Application Stopped</strong></span>`;

                        let card = `<div class='amp-status-card'>
                            <h4>${title}</h4>
                            ${appStatus}
                            <span><strong>Uptime:</strong> ${uptime}</span>`;

                        if (server.IP_Port) {
                            card += `<span><strong>IP:</strong> ${server.IP_Port}</span>`;
                        }

                        if (server.Connect) {
                            card += `<span><a href="${server.Connect}" target="_blank" rel="noopener"><strong>🔗 Connect</strong></a></span>`;
                        }

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
                    container.innerText = 'Error fetching AMP status.';
                });
            }

            fetchAmpStatus();
            setInterval(fetchAmpStatus, <?php echo $interval; ?>);
        })();
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
            amp_register_alias_shortcodes($data);
        }
    }
}
