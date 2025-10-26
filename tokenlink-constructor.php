<?php
/*
Plugin Name: TokenLink Constructor
Plugin URI: https://www.mailborder.com/tokenlink-constructor
Description: Instantly create new WordPress plugins from your dashboard. Clean, secure, and built for developers who prefer efficiency over bloat.
Version: 1.0.8
Author: Mailborder Systems (Jerry Benton)
Author URI: https://www.mailborder.com/tokenlink-constructor
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: tokenlink-constructor
*/

defined('ABSPATH') || exit;

# ---------------------------------------------------------------------------
# Add admin page
# ---------------------------------------------------------------------------
add_action('admin_menu', function() {
    add_submenu_page(
        'plugins.php',
        esc_html__('Create Plugin', 'tokenlink-constructor'),
        esc_html__('Create Plugin', 'tokenlink-constructor'),
        'edit_plugins',
        'tokenlink-constructor',
        'mb_tokenlink_constructor_page'
    );
});

# ---------------------------------------------------------------------------
# Render admin page
# ---------------------------------------------------------------------------
function mb_tokenlink_constructor_page() {
    if (!current_user_can('edit_plugins')) {
        wp_die(esc_html__('You do not have permission to create plugins.', 'tokenlink-constructor'));
    }

    if (
        isset($_SERVER['REQUEST_METHOD']) &&
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        check_admin_referer('tokenlink_constructor_nonce')
    ) {
        mb_tokenlink_constructor_create();
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Create a New Plugin', 'tokenlink-constructor'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('tokenlink_constructor_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><?php echo esc_html__('Name *', 'tokenlink-constructor'); ?></th>
                    <td><input type="text" name="name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Description', 'tokenlink-constructor'); ?></th>
                    <td><input type="text" name="description" class="regular-text"></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Version', 'tokenlink-constructor'); ?></th>
                    <td><input type="text" name="version" value="1.0.1" class="regular-text"></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Author', 'tokenlink-constructor'); ?></th>
                    <td><input type="text" name="author" class="regular-text"
                        value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>"></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('License', 'tokenlink-constructor'); ?></th>
                    <td><input type="text" name="license" class="regular-text"
                        value="GPL v3 or later"></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('License URI', 'tokenlink-constructor'); ?></th>
                    <td><input type="text" name="license_uri" class="regular-text"
                        value="https://www.gnu.org/licenses/gpl-3.0.html"></td>
                </tr>
            </table>
            <?php submit_button(esc_html__('Create Plugin', 'tokenlink-constructor')); ?>
        </form>
    </div>
    <?php


    ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const nameField = document.querySelector('input[name="name"]');
        if (!nameField) return;
        const preview = document.createElement('p');
        preview.style.color = '#777';
        preview.style.fontStyle = 'italic';
        nameField.parentNode.appendChild(preview);
        const update = () => {
            let slug = nameField.value
                .toLowerCase()
                .replace(/_/g, '-')
                .replace(/[^a-z0-9-]+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
            preview.textContent = slug ? 'Slug: ' + slug : '';
        };
        nameField.addEventListener('input', update);
    });
    </script>
    <?php
}

# ---------------------------------------------------------------------------
# Create plugin
# ---------------------------------------------------------------------------
function mb_tokenlink_constructor_create() {

    // ✅ Verify nonce again to satisfy plugin check and tighten security.
    if (
        !isset($_POST['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'tokenlink_constructor_nonce')
    ) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed. Please reload the page and try again.', 'tokenlink-constructor') . '</p></div>';
        return;
    }

    // ✅ Safely extract and sanitize all POST fields
    $name        = isset($_POST['name'])        ? sanitize_text_field(wp_unslash($_POST['name']))        : '';
    $slug        = sanitize_title($name);
    $desc        = isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '';
    $ver         = isset($_POST['version'])     ? sanitize_text_field(wp_unslash($_POST['version']))     : '';
    $auth        = isset($_POST['author'])      ? sanitize_text_field(wp_unslash($_POST['author']))      : '';
    $license     = isset($_POST['license'])     ? sanitize_text_field(wp_unslash($_POST['license']))     : '';
    $license_uri = isset($_POST['license_uri']) ? sanitize_text_field(wp_unslash($_POST['license_uri'])) : '';

    $plugin_dir  = WP_PLUGIN_DIR . "/{$slug}";
    $plugin_file = "{$plugin_dir}/{$slug}.php";

    if (file_exists($plugin_dir)) {
        echo '<div class="notice notice-error"><p>' . esc_html__('That plugin slug already exists.', 'tokenlink-constructor') . '</p></div>';
        return;
    }

    if (!wp_mkdir_p($plugin_dir)) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Unable to create the plugin directory.', 'tokenlink-constructor') . '</p></div>';
        return;
    }

    # Build plugin header
    $header  = "<?php\n";
    $header .= "/*\n";
    $header .= "Plugin Name: {$name}\n";
    $header .= "Description: {$desc}\n";
    $header .= "Version: {$ver}\n";
    $header .= "Author: {$auth}\n";
    $header .= "License: {$license}\n";
    $header .= "License URI: {$license_uri}\n";
    $header .= "*/\n";

    # Write to file
    if (file_put_contents($plugin_file, $header) === false) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Failed to write plugin file.', 'tokenlink-constructor') . '</p></div>';
        return;
    }

    # Create a default readme.txt file
    global $wp_version;
    preg_match('/^\d+\.\d+/', $wp_version, $match);
    $tested_up_to = $match[0] ?? $wp_version;

    $readme  = "=== {$name} ===\n";
    $readme .= "Contributors: your-wordpress-username-here\n";
    $readme .= "Tags: custom plugin, extension\n";
    $readme .= "Requires at least: 5.0\n";
    $readme .= "Tested up to: {$tested_up_to}\n";
    $readme .= "Stable tag: {$ver}\n";
    $readme .= "License: {$license}\n";
    $readme .= "License URI: {$license_uri}\n\n";
    $readme .= (!empty($desc) ? "{$desc}\n\n" : "Put a short description of your plugin here.\n\n");
    $readme .= "== Description ==\n";
    $readme .= (!empty($desc) ? "{$desc}\n\n" : "Describe your plugin at length here.\n\n");
    $readme .= "== Installation ==\n";
    $readme .= "1. Upload to `/wp-content/plugins/`\n";
    $readme .= "2. Activate via WordPress admin\n\n";
    $readme .= "== Changelog ==\n";
    $readme .= "= {$ver} =\n";
    $readme .= "- Initial plugin stub generated using Tokenlink Constructor\n";

    file_put_contents("{$plugin_dir}/readme.txt", $readme);

    // Clear plugin cache before activation attempt
    wp_clean_plugins_cache();

    // Attempt activation quietly
    $result = activate_plugin("{$slug}/{$slug}.php", '', false, true);

    if (is_wp_error($result)) {
        $error_code = $result->get_error_code();
        if ($error_code === 'invalid_plugin' || $error_code === 'plugin_not_found') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Plugin created successfully. Please refresh your Plugins page to activate it.', 'tokenlink-constructor') . '</p></div>';
            return;
        }
        echo '<div class="notice notice-error"><p>' . esc_html__('Plugin created but could not be activated: ', 'tokenlink-constructor') . esc_html($result->get_error_message()) . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>' . esc_html__('Plugin created and activated successfully.', 'tokenlink-constructor') . '</p></div>';
    }
}