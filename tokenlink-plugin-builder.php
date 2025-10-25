<?php
/*
Plugin Name: Tokenlink - Plugin Builder
Plugin URI: https://www.mailborder.com/tokenlink-plugin-builder
Description: Instantly create new WordPress plugins from your dashboard. Clean, secure, and built for developers who prefer efficiency over bloat.
Version: 1.0.1
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
                <tr><th>Plugin Name *</th><td><input type="text" name="name" class="regular-text" required></td></tr>
                <tr><th>Slug</th><td><input type="text" name="slug" class="regular-text"></td></tr>
                <tr><th>Description</th><td><input type="text" name="description" class="regular-text"></td></tr>
                <tr><th>Version</th><td><input type="text" name="version" value="1.0" class="regular-text"></td></tr>
                <tr><th>Author</th><td><input type="text" name="author" class="regular-text" value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>"></td></tr>
            </table>
            <?php submit_button('Create Plugin'); ?>
        </form>
    </div>
    <?php
}

# ---------------------------------------------------------------------------
# Create plugin
# ---------------------------------------------------------------------------
function mb_plugin_builder_create() {
    $name = sanitize_text_field($_POST['name']);
    $slug = sanitize_title($_POST['slug'] ?: $name);
    $desc = sanitize_text_field($_POST['description']);
    $ver  = sanitize_text_field($_POST['version']);
    $auth = sanitize_text_field($_POST['author']);

    $plugin_dir = WP_PLUGIN_DIR . "/{$slug}";
    $plugin_file = "{$plugin_dir}/{$slug}.php";

    if (file_exists($plugin_dir)) {
        echo '<div class="notice notice-error"><p>That plugin slug already exists.</p></div>';
        return;
    }

    if (!wp_mkdir_p($plugin_dir)) {
        echo '<div class="notice notice-error"><p>Unable to create plugin directory.</p></div>';
        return;
    }

    $header = <<<PHP
<?php
/*
Plugin Name: {$name}
Description: {$desc}
Version: {$ver}
Author: {$auth}
*/
PHP;

    if (file_put_contents($plugin_file, $header) === false) {
        echo '<div class="notice notice-error"><p>Failed to write plugin file.</p></div>';
        return;
    }

    $result = activate_plugin("{$slug}/{$slug}.php");

    if (is_wp_error($result)) {
        echo '<div class="notice notice-error"><p>Plugin created but could not be activated.</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>Plugin created and activated successfully.</p></div>';
    }
}