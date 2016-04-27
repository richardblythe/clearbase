<?php
/*
    Plugin Name: Clearbase
    Plugin URI: http://www.unity3software.com/clearbase
    Description: A powerfully easy framework for adding/editing Wordpress media folders.
    Version: 1.8.7
    Author: Richard Blythe
    Author URI: http://unity3software.com/richard-blythe
    GitHub Plugin URI: https://github.com/richardblythe/clearbase
 */

function _clearbase_init() {
    $wp = wp_upload_dir();
    define( 'CLEARBASE_VERSION', '1.7.22');
    define( 'CLEARBASE_DIR', untrailingslashit(plugin_dir_path( __FILE__ ))); //dirname gets us the file location without the trailing slash
    define( 'CLEARBASE_URL', untrailingslashit(plugins_url( '/', __FILE__ )));
    define( 'CLEARBASE_UPLOADS_DIR', $wp['basedir'] . '/clearbase');
    define( 'CLEARBASE_UPLOADS_URL', $wp['baseurl'] . '/clearbase'); 

    if ( ! defined( 'CLEARBASE_LANGUAGES_DIR' ) )
        define( 'CLEARBASE_LANGUAGES_DIR', get_template_directory() . '/includes/languages' );

    load_theme_textdomain( 'clearbase', CLEARBASE_LANGUAGES_DIR );


    /* Register the clearbase_folder post type */
    register_post_type( 'clearbase_folder', array(
        'description'     => __( 'Stores a collection of subfolders/attachments', 'clearbase' ), // string
        'public'          => true,
        'show_ui'         => false,
        'hierarchical'    => true,
        'rewrite'         => array(
            //the slug is removed in the '..functions/core.php' file
            'slug'          => 'media',
            'with_front'    => true
        ),
        'labels' => array(
            'name'               => __( 'Folders',                    'clearbase' ),
            'singular_name'      => __( 'Folder',                     'clearbase' ),
            'menu_name'          => __( 'Folder',                     'clearbase' ),
            'add_new'            => __( 'Add New',                    'clearbase' ),
            'add_new_item'       => __( 'Add New Folder',             'clearbase' ),
            'edit_item'          => __( 'Edit Folder',                'clearbase' ),
            'new_item'           => __( 'New Folder',                 'clearbase' ),
            'view_item'          => __( 'View Folder',                'clearbase' ),
            'search_items'       => __( 'Search Folder',              'clearbase' ),
            'not_found'          => __( 'No folders found',           'clearbase' ),
            'not_found_in_trash' => __( 'No folders found in trash',  'clearbase' ),
            'all_items'          => __( 'All Folder',                 'clearbase' ),
        )
    ));

    require_once (CLEARBASE_DIR . '/functions/core.php');
    require_once (CLEARBASE_DIR . '/functions/folder.php');
    require_once (CLEARBASE_DIR . '/functions/attachment.php');
    
    require_once (CLEARBASE_DIR . '/functions/shortcode.php');
    require_once (CLEARBASE_DIR . '/functions/controller.php');    
    
    //load the core file required for all clearbase controllers
    require_once (CLEARBASE_DIR . '/views/class-view-controller.php');
    
    //** Load bundled **
    require_once (CLEARBASE_DIR . '/includes/flexslider/flexslider.php');
    require_once (CLEARBASE_DIR . '/includes/recent-images-widget/recent-images-widget.php');

    if (defined('DOING_AJAX') && DOING_AJAX) {
        require_once (CLEARBASE_DIR .'/functions/ajax.php');
    }
    else if (is_admin()) {
        require_once (CLEARBASE_DIR .'/functions/admin.php');
    }    

    do_action( 'clearbase_loaded' );
}

add_action( 'init', '_clearbase_init' , 0 );