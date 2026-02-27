<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the Nimtara REST API endpoints.
 *
 * POST /wp-json/nimtara/v1/submit  — receive and create a post
 * GET  /wp-json/nimtara/v1/status  — connection health check
 */
class Nimtara_Connect_API {

    const NAMESPACE = 'nimtara/v1';

    public function register_routes() {
        add_action( 'rest_api_init', function () {
            register_rest_route( self::NAMESPACE, '/submit', [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_submit' ],
                'permission_callback' => [ $this, 'authenticate' ],
            ] );

            register_rest_route( self::NAMESPACE, '/status', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_status' ],
                'permission_callback' => [ $this, 'authenticate' ],
            ] );

            register_rest_route( self::NAMESPACE, '/posts/(?P<id>\d+)', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_get_post' ],
                'permission_callback' => [ $this, 'authenticate' ],
            ] );
        } );
    }

    /**
     * Validate X-Nimtara-Key header against stored API key.
     */
    public function authenticate( WP_REST_Request $request ): bool {
        $stored_key = get_option( 'nimtara_connect_api_key', '' );
        $provided   = $request->get_header( 'X-Nimtara-Key' );
        return hash_equals( $stored_key, (string) $provided );
    }

    /**
     * POST /nimtara/v1/submit
     *
     * Expected body (JSON):
     * {
     *   title:          string,
     *   content:        string (HTML or markdown),
     *   excerpt:        string,
     *   pillar:         string  (maps to category slug),
     *   tags:           string[],
     *   featured_image: { src: string, alt: string },
     *   author:         string,
     *   publish_mode:   "draft" | "pending" | "publish",
     *   source:         string,
     *   nimtara_run_id: string  (stored as post meta for traceability)
     * }
     */
    public function handle_submit( WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_json_params();

        $title        = sanitize_text_field( $params['title'] ?? '' );
        $content      = wp_kses_post( $params['content'] ?? '' );
        $excerpt      = sanitize_textarea_field( $params['excerpt'] ?? '' );
        $pillar       = sanitize_key( $params['pillar'] ?? '' );
        $tags         = array_map( 'sanitize_text_field', (array) ( $params['tags'] ?? [] ) );
        $publish_mode = in_array( $params['publish_mode'] ?? '', [ 'draft', 'pending', 'publish' ], true )
                        ? $params['publish_mode']
                        : 'draft';
        $run_id       = sanitize_text_field( $params['nimtara_run_id'] ?? '' );
        $source       = sanitize_text_field( $params['source'] ?? 'Nimtara' );

        if ( empty( $title ) || empty( $content ) ) {
            return new WP_REST_Response( [ 'error' => 'title and content are required' ], 400 );
        }

        $publisher = new Nimtara_Connect_Publisher();
        $result    = $publisher->create_post( [
            'title'        => $title,
            'content'      => $content,
            'excerpt'      => $excerpt,
            'pillar'       => $pillar,
            'tags'         => $tags,
            'publish_mode' => $publish_mode,
            'run_id'       => $run_id,
            'source'       => $source,
            'featured_image' => $params['featured_image'] ?? null,
        ] );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }

        return new WP_REST_Response( $result, 201 );
    }

    /**
     * GET /nimtara/v1/status
     */
    public function handle_status( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( [
            'status'     => 'connected',
            'site'       => get_bloginfo( 'name' ),
            'url'        => get_site_url(),
            'wp_version' => get_bloginfo( 'version' ),
            'plugin_version' => NIMTARA_CONNECT_VERSION,
        ] );
    }

    /**
     * GET /nimtara/v1/posts/:id
     */
    public function handle_get_post( WP_REST_Request $request ): WP_REST_Response {
        $post_id = (int) $request->get_param( 'id' );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return new WP_REST_Response( [ 'error' => 'Post not found' ], 404 );
        }

        return new WP_REST_Response( [
            'post_id'  => $post->ID,
            'status'   => $post->post_status,
            'url'      => get_permalink( $post->ID ),
            'title'    => $post->post_title,
            'modified' => $post->post_modified,
        ] );
    }
}
