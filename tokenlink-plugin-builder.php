<?php
/*
Plugin Name: Tokenlink Plugin Builder
Plugin URI: https://www.mailborder.com/tokenlink-plugin-builder
Description: Instantly create new WordPress plugins from your dashboard. Clean, secure, and built for developers who prefer efficiency over bloat.
Version: 1.0.6
Author: Mailborder Systems (Jerry Benton)
Author URI: https://www.mailborder.com
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

# ---------------------------------------------------------------------------
# Add admin page
# ---------------------------------------------------------------------------
add_action('admin_menu', function() {
    add_submenu_page(
        'plugins.php',
        'Create Plugin',
        'Create Plugin',
        'edit_plugins',
        'mb-plugin-builder',
        'mb_plugin_builder_page'
    );
});

# ---------------------------------------------------------------------------
# Render admin page
# ---------------------------------------------------------------------------
function mb_plugin_builder_page() {
    if (!current_user_can('edit_plugins')) {
        wp_die(__('You do not have permission to create plugins.'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('mb_plugin_builder_nonce')) {
        mb_plugin_builder_create();
    }

    ?>
    <div class="wrap">
        <h1>Create a New Plugin</h1>
        <form method="post">
            <?php wp_nonce_field('mb_plugin_builder_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th>Plugin Name *</th>
                    <td><input type="text" name="name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th>Description</th>
                    <td><input type="text" name="description" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Version</th>
                    <td><input type="text" name="version" value="1.0.1" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Author</th>
                    <td><input type="text" name="author" class="regular-text"
                        value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>"></td>
                </tr>
                <tr>
                    <th>License</th>
                    <td><input type="text" name="license" class="regular-text" 
                        value="GPL v3 or later"></td>
                </tr>
                <tr>
                    <th>License URI</th>
                    <td><input type="text" name="license_uri" class="regular-text" 
                        value="https://www.gnu.org/licenses/gpl-3.0.html"></td>
                </tr>
            </table>
            <?php submit_button('Create Plugin'); ?>
        </form>
    </div>

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
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-');
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
function mb_plugin_builder_create() {
    $name     = sanitize_text_field($_POST['name']);
    $slug     = sanitize_title($name);
    $desc     = sanitize_text_field($_POST['description']);
    $ver      = sanitize_text_field($_POST['version']);
    $auth     = sanitize_text_field($_POST['author']);
    $license  = sanitize_text_field($_POST['license']);
    $license_uri = sanitize_text_field($_POST['license_uri']);

    $plugin_dir  = WP_PLUGIN_DIR . "/{$slug}";
    $plugin_file = "{$plugin_dir}/{$slug}.php";

    if (file_exists($plugin_dir)) {
        echo '<div class="notice notice-error"><p>That plugin slug already exists.</p></div>';
        return;
    }

    if (!wp_mkdir_p($plugin_dir)) {
        echo '<div class="notice notice-error"><p>Unable to create plugin directory.</p></div>';
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
        echo '<div class="notice notice-error"><p>Failed to write plugin file.</p></div>';
        return;
    }

    # Create a default readme.txt file
    $readme  = "=== {$name} ===\n";
    $readme .= "Contributors: your-wordpress-username-here\n";
    $readme .= "Tags: custom, plugin\n";
    $readme .= "Requires at least: 5.0\n";
    
    global $wp_version;
    preg_match('/^\d+\.\d+/', $wp_version, $match);
    $tested_up_to = $match[0] ?? $wp_version;
    $readme .= "Tested up to: {$tested_up_to}\n";

    $readme .= "Tested up to: {$tested_up_to}\n";
    $readme .= "Stable tag: {$ver}\n";
    $readme .= "License: {$license}\n";
    $readme .= "License URI: {$license_uri}\n\n";
    
    $readme .= "== Description ==\n";
    $readme .= (!empty($desc) ? "{$desc}\n\n" : "Describe your plugin here.\n\n");

    $readme .= "== Installation ==\n";
    $readme .= "1. Upload to `/wp-content/plugins/`\n";
    $readme .= "2. Activate via WordPress admin\n\n";
    $readme .= "== Changelog ==\n";
    $readme .= "= {$ver} =\n";
    $readme .= "- Initial plugin stub generated using Tokenlink Plugin Builder\n";

    file_put_contents("{$plugin_dir}/readme.txt", $readme);

    // Clear plugin cache before activation attempt
    wp_clean_plugins_cache();

    // Attempt activation quietly
    $result = activate_plugin("{$slug}/{$slug}.php", '', false, true);

    if (is_wp_error($result)) {
        // Ignore common transient cache errors
        $error_code = $result->get_error_code();
        if ($error_code === 'invalid_plugin' || $error_code === 'plugin_not_found') {
            echo '<div class="notice notice-success"><p>Plugin created successfully. Please refresh your Plugins page to activate it.</p></div>';
            return;
        }
        echo '<div class="notice notice-error"><p>Plugin created but could not be activated: ' . esc_html($result->get_error_message()) . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>Plugin created and activated successfully.</p></div>';
    }
}