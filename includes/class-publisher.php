<?php
defined( 'ABSPATH' ) || exit;

/**
 * Creates WordPress posts from Nimtara article payloads.
 * Handles category mapping, tag creation, and featured image upload.
 */
class Nimtara_Connect_Publisher {

    /**
     * Create a WP post from a Nimtara article payload.
     *
     * @return array|WP_Error  Post data array on success, WP_Error on failure.
     */
    public function create_post( array $args ): array|WP_Error {
        $category_id = $this->resolve_category( $args['pillar'] );
        $tag_ids     = $this->resolve_tags( $args['tags'] );

        $post_data = [
            'post_title'   => $args['title'],
            'post_content' => $args['content'],
            'post_excerpt' => $args['excerpt'],
            'post_status'  => $args['publish_mode'],
            'post_author'  => $this->resolve_author( $args['source'] ),
            'post_category' => $category_id ? [ $category_id ] : [],
            'tags_input'   => $tag_ids,
            'meta_input'   => [
                '_nimtara_run_id' => $args['run_id'],
                '_nimtara_source' => $args['source'],
                '_nimtara_synced' => time(),
            ],
        ];

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Upload featured image to media library
        if ( ! empty( $args['featured_image']['src'] ) ) {
            $media    = new Nimtara_Connect_Media();
            $image_id = $media->sideload( $args['featured_image']['src'], $post_id, $args['featured_image']['alt'] ?? $args['title'] );
            if ( $image_id && ! is_wp_error( $image_id ) ) {
                set_post_thumbnail( $post_id, $image_id );
            }
        }

        // Fire callback webhook to Nimtara if configured
        $this->maybe_notify_nimtara( $post_id, $args['run_id'], $args['publish_mode'] );

        return [
            'post_id'  => $post_id,
            'status'   => $args['publish_mode'],
            'url'      => get_permalink( $post_id ),
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
        ];
    }

    /**
     * Resolve or create a category from a Nimtara pillar slug.
     * Falls back to the default category if the pillar doesn't exist.
     */
    private function resolve_category( string $pillar ): int {
        if ( empty( $pillar ) ) {
            return (int) get_option( 'default_category', 1 );
        }

        // Check option map first (admin can override pillar → category)
        $map = get_option( 'nimtara_connect_category_map', [] );
        if ( isset( $map[ $pillar ] ) ) {
            return (int) $map[ $pillar ];
        }

        // Try to find by slug
        $term = get_term_by( 'slug', $pillar, 'category' );
        if ( $term ) {
            return $term->term_id;
        }

        // Auto-create if "auto-create categories" is enabled
        if ( get_option( 'nimtara_connect_auto_categories', false ) ) {
            $result = wp_insert_term( ucfirst( str_replace( '-', ' ', $pillar ) ), 'category', [ 'slug' => $pillar ] );
            if ( ! is_wp_error( $result ) ) {
                return $result['term_id'];
            }
        }

        return (int) get_option( 'default_category', 1 );
    }

    /**
     * Resolve or create tags from a tag name array.
     */
    private function resolve_tags( array $tags ): array {
        return array_filter( array_map( function ( $tag ) {
            $term = get_term_by( 'name', $tag, 'post_tag' );
            if ( $term ) return $term->term_id;
            $result = wp_insert_term( $tag, 'post_tag' );
            return is_wp_error( $result ) ? null : $result['term_id'];
        }, $tags ) );
    }

    /**
     * Find a WP user by display name or fall back to the first admin.
     */
    private function resolve_author( string $source ): int {
        $user = get_user_by( 'display_name', $source );
        if ( $user ) return $user->ID;

        $admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
        return $admins ? $admins[0]->ID : 1;
    }

    /**
     * POST back to Nimtara's callback URL if configured.
     */
    private function maybe_notify_nimtara( int $post_id, string $run_id, string $status ): void {
        $callback_url = get_option( 'nimtara_connect_callback_url', '' );
        $callback_key = get_option( 'nimtara_connect_callback_key', '' );

        if ( empty( $callback_url ) || empty( $run_id ) ) return;

        wp_remote_post( $callback_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Nimtara-Key' => $callback_key,
            ],
            'body'    => wp_json_encode( [
                'run_id'  => $run_id,
                'post_id' => $post_id,
                'status'  => $status,
                'url'     => get_permalink( $post_id ),
            ] ),
            'timeout'  => 10,
            'blocking' => false,
        ] );
    }
}
