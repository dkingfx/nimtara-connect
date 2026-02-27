<?php
defined( 'ABSPATH' ) || exit;

/**
 * Downloads a remote image and uploads it to the WordPress Media Library.
 */
class Nimtara_Connect_Media {

    /**
     * Sideload an image URL into the WP media library and attach it to a post.
     *
     * @param string $url      Remote image URL.
     * @param int    $post_id  Post to attach the image to.
     * @param string $alt      Alt text / title for the attachment.
     * @return int|WP_Error    Attachment ID on success.
     */
    public function sideload( string $url, int $post_id, string $alt = '' ): int|WP_Error {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download to temp file
        $tmp = download_url( $url, 30 );
        if ( is_wp_error( $tmp ) ) {
            return $tmp;
        }

        $file_array = [
            'name'     => $this->filename_from_url( $url ),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload( $file_array, $post_id, $alt );

        // Clean up temp file on error
        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp );
            return $attachment_id;
        }

        // Store alt text
        if ( $alt ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
        }

        return $attachment_id;
    }

    private function filename_from_url( string $url ): string {
        $path = parse_url( $url, PHP_URL_PATH );
        $name = basename( $path ?? '' );
        // Ensure there's a sensible extension
        if ( ! preg_match( '/\.(jpe?g|png|gif|webp)$/i', $name ) ) {
            $name = 'nimtara-image-' . time() . '.jpg';
        }
        return $name;
    }
}
