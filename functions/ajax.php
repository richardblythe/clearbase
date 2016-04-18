<?php

function clearbase_ajax_get_folder_tree() {
    // $tree = clearbase_get_folder_tree();
    // echo '<ul>';
    // for($i=0, $count = count($tree); $i < $count; $i++){
    //     __clearbase_ajax_print_folder($tree[$i]);
    // }
    // echo '</ul>';
    wp_send_json( clearbase_get_jstree_data() );
}

function __clearbase_ajax_print_folder($folder) {
    
    $data = array();
    $data['icon'] = apply_filters('clearbase_folder_icon', '', array('width' => 40, 'height' => 40));
    $data['disabled'] = apply_filters('clearbase_folder_disabled', '');
    $data['opened'] = apply_filters('clearbase_folder_opened', '');

    $jsdata = '';
    foreach ($data as $key => $value) {
        if (!empty($value)) {
            $jsdata .= ((empty($jsdata) ? '' : ', ') . '"' . $key .'":"'. $value .'"'); 
        }
    }

    if (!empty($jsdata))
        $jsdata = 'data-jstree=\'{' . $jsdata . '}\'';

    echo '<li id="jstree-folder-' . $folder->ID .'" class="clearbase-folder" ' . $jsdata . '>';
    echo apply_filters('clearbase_ajax_folder_title', $folder->post_title, $folder);
    $count = count($folder->folders);
    if ($count) {
        echo '<ul>';
        for($i=0; $i < $count; $i++){
            __clearbase_ajax_print_folder($folder->folders[$i]);
        }
        echo '</ul>';
    }
    echo '</li>';
}


function clearbase_ajax_move_to_folder() {
    check_ajax_referer('clearbase_ajax', 'cbnonce');
    //move posts to another folder
    $folder_id = absint($_REQUEST['folderid']);
    $posts = clearbase_empty_default($_REQUEST, 'posts', array());
    $result = clearbase_move_to_folder($folder_id, $posts);

    echo wp_json_encode( array(
            'success' => false == is_wp_error($result),
            'data'    => array(
                'message'  => is_wp_error($result) ? 
                    $result->get_error_message() :
                    __( 'Move to folder complete.', 'clearbase' )
            )
    ) );
    wp_die();
}

function _clearbase_sort_posts() {
    global $wpdb;
    $posts = isset($_REQUEST['attachments']) ? $_REQUEST['attachments'] : $_REQUEST['posts'];
    if (!isset($posts) || !is_array($posts) || 0 == count($posts)) {
        wp_send_json_success();
        exit;
    }

    //sort the posts by the menu_order value
    asort($posts);
    reset($posts);

    //get the first post id
    $post = get_post(key($posts));
    //if the result is not a valid WP_Post 
    if (!($post instanceof WP_Post)) {
        wp_send_json_success();
        exit;
    }

    //get the sorting order
    $order = clearbase_get_value('postmeta.attachment_order', 'DESC', $post->post_parent);
    //ensure a correct order value
    if ('ASC' != $order || 'DESC' != $order)
        $order = 'DESC';
    
    $post__in = implode(',', array_map( 'absint', array_keys($posts) ));
    //get the starting menu order from the database
    $menu_order = $wpdb->get_var($wpdb->prepare("SELECT menu_order FROM $wpdb->posts 
        WHERE ID IN ($post__in) ORDER BY menu_order $order LIMIT 1", $post->post_parent));

    if ('DESC' == $order && $menu_order < count($posts)) {
        //If the menu_order has gotten out of sync.  We need to do a hard reset
        //of all posts in the specified folder to ensure that sorting by DESC will perform correctly.
        $p = clearbase_get_attachments($post->post_parent);
        $posts = array();
        for ($i = 0, $c = count($p); $i < $c; $i++)
            $posts[$p[$i]->ID] = $p[$i]->menu_order;

        $menu_order = count($posts);
    }

    foreach ( $posts as $post_id => $value ) {
        if ( ! $p = get_post( $post_id ) )
            continue;
        wp_update_post( array( 'ID' => $post_id, 'menu_order' =>  $menu_order) );
        $menu_order = max('DESC' == $order ? $menu_order - 1 : $menu_order + 1, 0);
    }

    wp_send_json_success();
}

//Sorts clearbase_folder posts and posts from the attachments table in list mode
function clearbase_ajax_sort_posts() {
    check_ajax_referer('clearbase_ajax', 'cbnonce');

    if ( ! current_user_can( 'edit_posts' ) ) {
        echo wp_json_encode( array(
            'success' => false,
            'data'    => array(
                'message'  => __( "You don't have permission to sort posts." )
            )
        ) );

        wp_die();
    }


    _clearbase_sort_posts();
}



/**
 * Sorts attachments from the WP AttachmentBrowser
 *
 * @since 3.5.0
 */
function clearbase_ajax_save_attachment_order() {
    remove_action('wp-ajax_save-attachment-order', 'wp_ajax_save_attachment_order', 1);
    if ( ! isset( $_REQUEST['post_id'] ) )
        wp_send_json_error();

    if ( ! $post_id = absint( $_REQUEST['post_id'] ) )
        wp_send_json_error();

    if ( empty( $_REQUEST['attachments'] ) )
        wp_send_json_error();

    check_ajax_referer( 'update-post_' . $post_id, 'nonce' );

    _clearbase_sort_posts();
}

add_action( 'wp_ajax_clearbase_get_folder_tree', 'clearbase_ajax_get_folder_tree');
add_action( 'wp_ajax_clearbase_move_to_folder', 'clearbase_ajax_move_to_folder');
add_action( 'wp_ajax_clearbase_sort_posts', 'clearbase_ajax_sort_posts');
//add_action( 'wp_ajax_save-attachment-order', 'clearbase_ajax_save_attachment_order', 0);