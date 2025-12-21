<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// Clean up user metadata
delete_metadata( 'user', 0, 'cacp_profile_pic', '', true );
delete_metadata( 'user', 0, 'cacp_user_label', '', true );
