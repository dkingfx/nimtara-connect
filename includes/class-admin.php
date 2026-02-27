<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page for Nimtara Connect.
 * Settings → Nimtara Connect
 */
class Nimtara_Connect_Admin {

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_menu(): void {
        add_options_page(
            'Nimtara Connect',
            'Nimtara Connect',
            'manage_options',
            'nimtara-connect',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'nimtara_connect', 'nimtara_connect_api_key' );
        register_setting( 'nimtara_connect', 'nimtara_connect_callback_url' );
        register_setting( 'nimtara_connect', 'nimtara_connect_callback_key' );
        register_setting( 'nimtara_connect', 'nimtara_connect_auto_categories', [
            'type'    => 'boolean',
            'default' => false,
        ] );
    }

    public function render_page(): void {
        $api_key      = get_option( 'nimtara_connect_api_key', '' );
        $endpoint     = get_site_url() . '/wp-json/nimtara/v1/submit';
        $status_url   = get_site_url() . '/wp-json/nimtara/v1/status';
        $callback_url = get_option( 'nimtara_connect_callback_url', '' );
        $callback_key = get_option( 'nimtara_connect_callback_key', '' );
        $auto_cats    = get_option( 'nimtara_connect_auto_categories', false );

        // Handle API key regeneration
        if ( isset( $_POST['regenerate_key'] ) && check_admin_referer( 'nimtara_regenerate_key' ) ) {
            $api_key = wp_generate_password( 40, false );
            update_option( 'nimtara_connect_api_key', $api_key );
            echo '<div class="notice notice-success"><p>API key regenerated.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>Nimtara Connect</h1>
            <p>Connect your WordPress site to the <strong>Nimtara</strong> AI content platform.</p>

            <h2>Connection Details</h2>
            <p>Add these to your Nimtara publish connection:</p>
            <table class="form-table">
                <tr>
                    <th>Submit Endpoint</th>
                    <td><code><?php echo esc_html( $endpoint ); ?></code></td>
                </tr>
                <tr>
                    <th>Status Endpoint</th>
                    <td><code><?php echo esc_html( $status_url ); ?></code></td>
                </tr>
                <tr>
                    <th>API Key</th>
                    <td>
                        <input type="text" value="<?php echo esc_attr( $api_key ); ?>" readonly style="width:360px;font-family:monospace;" />
                        <form method="post" style="display:inline-block;margin-left:8px;">
                            <?php wp_nonce_field( 'nimtara_regenerate_key' ); ?>
                            <input type="hidden" name="regenerate_key" value="1" />
                            <button class="button" onclick="return confirm('Regenerate API key? The old key will stop working immediately.')">Regenerate</button>
                        </form>
                    </td>
                </tr>
            </table>

            <hr />

            <form method="post" action="options.php">
                <?php settings_fields( 'nimtara_connect' ); ?>
                <h2>Settings</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="callback_url">Callback URL</label></th>
                        <td>
                            <input type="url" id="callback_url" name="nimtara_connect_callback_url"
                                   value="<?php echo esc_attr( $callback_url ); ?>" style="width:360px;" />
                            <p class="description">Nimtara webhook URL to notify when a post is published or updated. Leave blank to disable.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="callback_key">Callback Key</label></th>
                        <td>
                            <input type="text" id="callback_key" name="nimtara_connect_callback_key"
                                   value="<?php echo esc_attr( $callback_key ); ?>" style="width:360px;font-family:monospace;" />
                            <p class="description">Sent as <code>X-Nimtara-Key</code> header in callback requests.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Auto-create Categories</th>
                        <td>
                            <label>
                                <input type="checkbox" name="nimtara_connect_auto_categories" value="1"
                                       <?php checked( $auto_cats, true ); ?> />
                                Automatically create a category when Nimtara submits an unknown pillar slug
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr />

            <h2>Recent Submissions</h2>
            <?php $this->render_recent_posts(); ?>
        </div>
        <?php
    }

    private function render_recent_posts(): void {
        $posts = get_posts( [
            'post_type'   => 'post',
            'post_status' => [ 'publish', 'draft', 'pending' ],
            'meta_key'    => '_nimtara_run_id',
            'numberposts' => 10,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ] );

        if ( empty( $posts ) ) {
            echo '<p>No Nimtara submissions yet.</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Title</th><th>Status</th><th>Run ID</th><th>Date</th><th></th>';
        echo '</tr></thead><tbody>';

        foreach ( $posts as $post ) {
            $run_id = get_post_meta( $post->ID, '_nimtara_run_id', true );
            printf(
                '<tr><td><strong>%s</strong></td><td><code>%s</code></td><td><small>%s</small></td><td>%s</td><td><a href="%s">Edit</a></td></tr>',
                esc_html( $post->post_title ),
                esc_html( $post->post_status ),
                esc_html( $run_id ),
                esc_html( get_the_date( 'Y-m-d H:i', $post ) ),
                esc_url( get_edit_post_link( $post->ID ) )
            );
        }

        echo '</tbody></table>';
    }
}
