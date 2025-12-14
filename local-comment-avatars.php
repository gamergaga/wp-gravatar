<?php
/*
Plugin Name: Local Comment Avatars
Description: Allows users and guests to upload a Display Picture (DP) while commenting. Overrides Gravatar.
Version: 1.0
Author: Gemini
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

class Local_Comment_Avatars {

    public function __construct() {
        // 1. Add File Field to Comment Form
        add_action( 'comment_form_logged_in_after', array( $this, 'add_avatar_field' ) );
        add_action( 'comment_form_after_fields', array( $this, 'add_avatar_field' ) );

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
        ?>
        <div class="lca-avatar-wrapper">
            <label for="lca-upload"><?php _e( 'Upload a Photo (Optional)', 'lca' ); ?></label>
            <input type="file" name="lca-upload" id="lca-upload" accept="image/png, image/jpeg, image/gif" />
            <div id="lca-preview"></div>
            <p class="lca-note">Allowed: JPG, PNG, GIF. Max 2MB.</p>
        </div>
        <?php
    }

    /**
     * Validates and uploads the file to WP Media Library.
     */
    public function handle_file_upload( $commentdata ) {
        if ( ! isset( $_FILES['lca-upload'] ) || empty( $_FILES['lca-upload']['name'] ) ) {
            return $commentdata;
        }

        $file = $_FILES['lca-upload'];

        // Security: Validate File Type
        $allowed = array( 'image/jpeg', 'image/png', 'image/gif' );
        if ( ! in_array( $file['type'], $allowed ) ) {
            wp_die( 'Error: Only JPG, PNG, and GIF images are allowed.' );
        }

        // Security: Validate File Size (2MB)
        if ( $file['size'] > 2 * 1024 * 1024 ) {
            wp_die( 'Error: Image size must be less than 2MB.' );
        }

        // WordPress Media Handling
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $attachment_id = media_handle_upload( 'lca-upload', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            wp_die( 'Upload Error: ' . $attachment_id->get_error_message() );
        }

        // Store ID in a global variable to access it in the 'comment_post' hook
        global $lca_uploaded_id;
        $lca_uploaded_id = $attachment_id;

        return $commentdata;
    }

    /**
     * Links the uploaded image to the Comment and the User (if logged in).
     */
    public function save_avatar_meta( $comment_id, $comment_approved ) {
        global $lca_uploaded_id;

        if ( isset( $lca_uploaded_id ) && $lca_uploaded_id ) {
            // Save to Comment Meta (So this specific comment has the pic)
            add_comment_meta( $comment_id, 'lca_avatar_id', $lca_uploaded_id );

            // If user is logged in, save to User Meta (So they keep this pic for future)
            $user_id = get_current_user_id();
            if ( $user_id ) {
                update_user_meta( $user_id, 'lca_profile_pic', $lca_uploaded_id );
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

    public function enqueue_assets() {
        if ( is_singular() && comments_open() ) {
            wp_enqueue_script( 'lca-js', plugin_dir_url( __FILE__ ) . 'script.js', array( 'jquery' ), '1.0', true );
            wp_enqueue_style( 'lca-css', plugin_dir_url( __FILE__ ) . 'style.css' );
        }
    }
}

new Local_Comment_Avatars();
