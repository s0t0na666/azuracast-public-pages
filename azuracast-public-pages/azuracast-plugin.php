<?php
/*
Plugin Name: Azuracast Public Pages Shortcodes
Description: Integrates AzuraCast with WordPress to display live radio data and display public elements via short-codes.
Version: 1
Author: s0t0na
*/

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Add settings menu
add_action('admin_menu', 'azuracast_plugin_menu');

function azuracast_plugin_menu() {
    add_menu_page('AzuraCast Settings', 'AzuraCast', 'manage_options', 'azuracast-settings', 'azuracast_settings_page', 'dashicons-megaphone');
    add_submenu_page('azuracast-settings', 'Error Logs', 'Error Logs', 'manage_options', 'azuracast-error-logs', 'azuracast_error_logs_page');
}

// Register settings
add_action('admin_init', 'azuracast_register_settings');

function azuracast_register_settings() {
    register_setting('azuracast-settings-group', 'azuracast_url');
    register_setting('azuracast-settings-group', 'azuracast_station_name');
    register_setting('azuracast-settings-group', 'azuracast_api_key');
    register_setting('azuracast-settings-group', 'azuracast_custom_css');
    register_setting('azuracast-settings-group', 'azuracast_live_status_text');
    register_setting('azuracast-settings-group', 'azuracast_theme', ['default' => 'dark']);
}

// Error Logging Function
function azuracast_log_error($message) {
    $log_file = plugin_dir_path(__FILE__) . 'logs/azuracast-error.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND);
}

// Error Log Viewer in Admin
function azuracast_error_logs_page() {
    $log_file = plugin_dir_path(__FILE__) . 'logs/azuracast-error.log';
    echo '<h1>AzuraCast Error Logs</h1>';
    if (file_exists($log_file)) {
        echo '<pre style="background: #f7f7f7; padding: 20px; border: 1px solid #ccc;">' . esc_html(file_get_contents($log_file)) . '</pre>';
    } else {
        echo '<p>No error logs available.</p>';
    }
}

// Settings Page
if (!function_exists('azuracast_settings_page')) {
    function azuracast_settings_page() {
        ?>
        <div class="wrap">
            <h1>AzuraCast Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('azuracast-settings-group');
                do_settings_sections('azuracast-settings-group');
                ?>
                <table class="form-table">
                    <tr>
                        <th>AzuraCast Server URL</th>
                        <td><input type="text" name="azuracast_url" value="<?php echo esc_attr(get_option('azuracast_url')); ?>" style="width: 100%;" /></td>
                    </tr>
                    <tr>
                        <th>Station Name</th>
                        <td><input type="text" name="azuracast_station_name" value="<?php echo esc_attr(get_option('azuracast_station_name')); ?>" style="width: 100%;" /></td>
                    </tr>
                    <tr>
                        <th>API Key (Optional)</th>
                        <td><input type="text" name="azuracast_api_key" value="<?php echo esc_attr(get_option('azuracast_api_key')); ?>" style="width: 100%;" /></td>
                    </tr>
                    <tr>
                        <th>Theme</th>
                        <td>
                            <select name="azuracast_theme">
                                <option value="dark" <?php selected(get_option('azuracast_theme'), 'dark'); ?>>Dark ðŸŒ™</option>
                                <option value="light" <?php selected(get_option('azuracast_theme'), 'light'); ?>>Light ðŸŒž</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Custom CSS</th>
                        <td><textarea name="azuracast_custom_css" style="width: 100%; height: 150px;"><?php echo esc_textarea(get_option('azuracast_custom_css')); ?></textarea></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Shortcodes for AzuraCast Embeds
function azuracast_generate_iframe($type, $min_height) {
    $url = rtrim(esc_url(get_option('azuracast_url')), '/');
    $station = esc_attr(get_option('azuracast_station_name'));
    $theme = esc_attr(get_option('azuracast_theme', 'dark'));

    if (empty($url) || empty($station)) {
        return '<p>Please configure the AzuraCast URL and Station Name in the plugin settings.</p>';
    }

    $src = "{$url}/public/{$station}/{$type}?theme={$theme}";
    if ($type === 'podcasts') {
        $src .= '&embed=true';
    }

    return "<iframe src='{$src}' frameborder='0' allowtransparency='true' style='width: 100%; min-height: {$min_height}px; border: 0;'></iframe>";
}

add_shortcode('azura_player', function() { return azuracast_generate_iframe('embed', 150); });
add_shortcode('azura_history', function() { return azuracast_generate_iframe('history', 300); });
add_shortcode('azura_podcasts', function() { return azuracast_generate_iframe('podcasts', 400); });
add_shortcode('azura_schedule', function() { return azuracast_generate_iframe('schedule/embed', 800); });

// Shortcode for Playing Next
add_shortcode('azura_playing_next', function() {
    $url = esc_url(get_option('azuracast_url'));
    $station = esc_attr(get_option('azuracast_station_name'));

    if (empty($url) || empty($station)) {
        return '<p>Please configure the AzuraCast URL and Station Name in the plugin settings.</p>';
    }

    // Output container for the next track info
    $output = "
    <div id='azura-playing-next'>Loading next track...</div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            fetch('{$url}/api/nowplaying/{$station}')
                .then(response => response.json())
                .then(data => {
                    const nextSong = data.playing_next.song;
                    if (nextSong && nextSong.title) {
                        const artist = nextSong.artist || 'Unknown Artist';
                        const title = nextSong.title;
                        const artwork = nextSong.art || '';

                        let output = `<strong>🎵 Next Track:</strong> ${artist} - ${title}`;
                        if (artwork) {
                            output = `<img src='${artwork}' alt='Album Art' style='max-width:100px; height:auto; display:block; margin-bottom:10px;'>` + output;
                        }

                        document.getElementById('azura-playing-next').innerHTML = output;
                    } else {
                        document.getElementById('azura-playing-next').innerText = 'No upcoming track.';
                    }
                })
                .catch(error => {
                    console.error('Error fetching next track:', error);
                    document.getElementById('azura-playing-next').innerText = 'Unable to fetch next track information.';
                });
        });
    </script>";

    return $output;
});


