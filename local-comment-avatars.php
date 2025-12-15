<?php
/*
Plugin Name: Local Comment Avatars
Description: Allows users and guests to upload a Display Picture (DP) while commenting. Overrides Gravatar.
Version: 1.1
Author: Gemini
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

class Local_Comment_Avatars {

    /**
     * Holds the latest uploaded attachment ID for the current request.
     *
     * @var int|null
     */
    protected $uploaded_id = null;

    public function __construct() {
        // 1. Add File Field to Comment Form
        add_action( 'comment_form_logged_in_after', array( $this, 'add_avatar_field' ) );
        add_action( 'comment_form_after_fields', array( $this, 'add_avatar_field' ) );
        add_action( 'comment_form', array( $this, 'ensure_multipart_form' ) );

        // 2. Handle File Upload on Submission
        add_action( 'preprocess_comment', array( $this, 'handle_file_upload' ) );

        // 3. Save Attachment ID to Meta
        add_action( 'comment_post', array( $this, 'save_avatar_meta' ), 10, 2 );

        // 4. Override Gravatar
        add_filter( 'pre_get_avatar_data', array( $this, 'override_gravatar' ), 10, 2 );

        // 5. Enqueue JS/CSS
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Renders the file input field on the comment form.
     */
    public function add_avatar_field() {
        $current_avatar_id  = 0;
        $current_avatar_url = '';

        if ( is_user_logged_in() ) {
            $current_avatar_id = (int) get_user_meta( get_current_user_id(), 'lca_profile_pic', true );
            if ( $current_avatar_id ) {
                $current_avatar_url = wp_get_attachment_image_url( $current_avatar_id, 'thumbnail' );
            }
        }

        $has_avatar = ! empty( $current_avatar_url );

        ?>
        <div class="lca-avatar-wrapper<?php echo $has_avatar ? ' lca-avatar-wrapper--has-avatar' : ''; ?>">
            <div class="lca-heading">
                <p class="lca-title"><?php esc_html_e( 'Add a profile photo', 'lca' ); ?></p>
                <p class="lca-helper"><?php esc_html_e( 'Optional — looks great next to your comment.', 'lca' ); ?></p>
            </div>

            <?php if ( $has_avatar && $current_avatar_url ) : ?>
                <div class="lca-current-avatar" aria-live="polite" aria-atomic="true">
                    <img src="<?php echo esc_url( $current_avatar_url ); ?>" alt="<?php esc_attr_e( 'Your current profile photo', 'lca' ); ?>" />
                    <p class="lca-current-avatar__text"><?php esc_html_e( 'You already have a profile photo. Use the button below if you want to change it.', 'lca' ); ?></p>
                </div>
            <?php endif; ?>

            <label class="lca-input" for="lca-upload">
                <input
                    type="file"
                    name="lca-upload"
                    id="lca-upload"
                    accept="image/png, image/jpeg, image/gif"
                    data-has-avatar="<?php echo $has_avatar ? '1' : '0'; ?>"
                />
                <span class="lca-input__hint"><?php echo $has_avatar ? esc_html__( 'Change your profile photo', 'lca' ) : esc_html__( 'Drag & drop or browse an image', 'lca' ); ?></span>
            </label>

            <div class="lca-preview-row">
                <div
                    id="lca-preview"
                    class="lca-preview"
                    aria-live="polite"
                    aria-atomic="true"
                    data-current-avatar="<?php echo esc_attr( $current_avatar_url ); ?>"
                >
                    <?php if ( $has_avatar && $current_avatar_url ) : ?>
                        <img src="<?php echo esc_url( $current_avatar_url ); ?>" alt="<?php esc_attr_e( 'Current profile photo preview', 'lca' ); ?>" />
                    <?php endif; ?>
                </div>
                <div
                    id="lca-feedback"
                    class="lca-feedback"
                    role="status"
                    aria-live="polite"
                    data-current-message="<?php echo esc_attr__( 'Using your saved profile photo. Upload a new one to replace it.', 'lca' ); ?>"
                >
                    <?php echo $has_avatar ? esc_html__( 'Using your saved profile photo. Upload a new one to replace it.', 'lca' ) : ''; ?>
                </div>
            </div>

            <p class="lca-note"><?php esc_html_e( 'Allowed: JPG, PNG, GIF. Max 2MB. Square images work best.', 'lca' ); ?></p>
            <?php wp_nonce_field( 'lca-upload', 'lca_nonce' ); ?>
        </div>
        <?php
    }

    /**
     * Validates and uploads the file to WP Media Library.
     */
    public function handle_file_upload( $commentdata ) {
        // Reset any previously set attachment for this request.
        $this->uploaded_id = null;

        if ( empty( $_FILES['lca-upload']['name'] ) ) {
            return $commentdata;
        }

        if ( ! isset( $_POST['lca_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lca_nonce'] ) ), 'lca-upload' ) ) {
            // Fail gracefully: skip the upload but still let the comment through.
            unset( $_FILES['lca-upload'] );

            add_filter(
                'comment_post_redirect',
                static function ( $location ) {
                    return add_query_arg( 'lca', 'nonce-failed', $location );
                }
            );

            return $commentdata;
        }

        $file = $_FILES['lca-upload'];

        if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
            $message = __( 'Upload failed. Please try again.', 'lca' );

            if ( isset( $file['error'] ) && UPLOAD_ERR_INI_SIZE === (int) $file['error'] ) {
                $message = __( 'Error: The uploaded file exceeds the maximum size allowed by the server.', 'lca' );
            }

            wp_die( esc_html( $message ) );
        }

        // Security: Validate File Type and Extension
        $allowed = array( 'image/jpeg', 'image/png', 'image/gif' );
        $checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );

        if ( empty( $checked['type'] ) || ! in_array( $checked['type'], $allowed, true ) ) {
            wp_die( esc_html__( 'Error: Only JPG, PNG, and GIF images are allowed.', 'lca' ) );
        }

        // Security: Validate File Size (2MB)
        if ( $file['size'] > 2 * 1024 * 1024 ) {
            wp_die( esc_html__( 'Error: Image size must be less than 2MB.', 'lca' ) );
        }

        // WordPress Media Handling
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'lca-upload', 0, array(), array( 'test_form' => false ) );

        if ( is_wp_error( $attachment_id ) ) {
            wp_die( esc_html( sprintf( __( 'Upload Error: %s', 'lca' ), $attachment_id->get_error_message() ) ) );
        }

        // Store ID for access in the 'comment_post' hook
        $this->uploaded_id = $attachment_id;

        return $commentdata;
    }

    /**
     * Links the uploaded image to the Comment and the User (if logged in).
     */
    public function save_avatar_meta( $comment_id, $comment_approved ) {
        if ( $this->uploaded_id ) {
            // Save to Comment Meta (So this specific comment has the pic)
            add_comment_meta( $comment_id, 'lca_avatar_id', $this->uploaded_id );

            // If user is logged in, save to User Meta (So they keep this pic for future)
            $user_id = get_current_user_id();
            if ( $user_id ) {
                update_user_meta( $user_id, 'lca_profile_pic', $this->uploaded_id );
            }
        }
    }

    /**
     * Replaces the default Gravatar with our local image.
     */
    public function override_gravatar( $args, $id_or_email ) {
        $avatar_id = false;

        // 1. Check if we are viewing a specific comment
        if ( is_object( $id_or_email ) && isset( $id_or_email->comment_ID ) ) {
            // Check if this specific comment has an uploaded avatar
            $comment_avatar = get_comment_meta( $id_or_email->comment_ID, 'lca_avatar_id', true );
            
            if ( $comment_avatar ) {
                $avatar_id = $comment_avatar;
            } elseif ( $id_or_email->user_id ) {
                // If comment has no avatar, check if the author has a saved profile pic
                $avatar_id = get_user_meta( $id_or_email->user_id, 'lca_profile_pic', true );
            }
        }

        // 2. Check if we are viewing a User ID (standard get_avatar calls)
        if ( is_numeric( $id_or_email ) ) {
            $avatar_id = get_user_meta( $id_or_email, 'lca_profile_pic', true );
        }

        // 3. Check if we are viewing by Email
        if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
            $user = get_user_by( 'email', $id_or_email );
            if ( $user ) {
                $avatar_id = get_user_meta( $user->ID, 'lca_profile_pic', true );
            }
        }

        // If we found a local image, set the URL
        if ( $avatar_id ) {
            $img_url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' ); // 'thumbnail' usually 150x150
            if ( $img_url ) {
                $args['url'] = $img_url;
                $args['found_avatar'] = true;
            }
        }

        return $args;
    }

    public function ensure_multipart_form() {
        echo '<script>(function(){var f=document.getElementById("commentform");if(f){f.enctype="multipart/form-data";f.encoding="multipart/form-data";}})();</script>';
    }

    public function enqueue_assets() {
        if ( is_singular() && comments_open() ) {
            wp_enqueue_script( 'lca-js', plugin_dir_url( __FILE__ ) . 'script.js', array( 'jquery' ), '1.1', true );
            wp_enqueue_style( 'lca-css', plugin_dir_url( __FILE__ ) . 'style.css', array(), '1.1' );
        }
    }
}

new Local_Comment_Avatars();
