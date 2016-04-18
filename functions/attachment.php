<?php

function clearbase_get_attachments($type = '', $folder_id = null) {
    $folder = clearbase_load_folder($folder_id);
    if (is_wp_error($folder))
      return $folder;

    return get_posts(apply_filters('clearbase_query_media_args', array(
        'post_type'      => 'attachment',
        'post_mime_type' => $type,
        'post_status'    => 'any',
        'post_parent'    => $folder->ID,
        'orderby'        => 'menu_order',
        'order'          => clearbase_get_value('postmeta.attachment_order', 'DESC', $folder->ID),
        'posts_per_page'    => -1
    )));

}

function clearbase_get_first_attachment($type = '', $folder_id = null) {
    $folder = clearbase_load_folder($folder_id);
    if (is_wp_error($folder))
      return $folder;

    global $wpdb;
    $folder_id = absint($folder->ID);
    $order = clearbase_get_value('postmeta.attachment_order', 'DESC', $folder);
    if ('ASC' == $order || 'DESC' == $order)
      $order = 'DESC'; //force a proper sorting order
    $and_where_mime = wp_post_mime_type_where( $type );
    $attachment = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE post_parent = $folder_id 
      AND post_type='attachment' $and_where_mime ORDER BY menu_order $order LIMIT 1");

    return $attachment ? new WP_Post($attachment) : null;
}


function clearbase_query_attachments($type = '', $folder_id = null) {
    $post = get_post($folder_id);
    if (!isset($post) || 'clearbase_folder' != $post->post_type)
      return new WP_Error('clearbase_invalid_folder', __('You must specify a valid clearbase folder', 'clearbase'));

    return new WP_Query(apply_filters('clearbase_query_media_args', array(
        'post_type'      => 'attachment',
        'post_mime_type' => $type,
        'post_status'    => 'any',
        'post_parent'    => $post->ID,
        'orderby'        => 'menu_order',
        'order'          => clearbase_get_value('postmeta.attachment_order', 'DESC', $post->ID),
        'posts_per_page'    => -1
    )));
}

function clearbase_validate_attachment_filter($filter = '', $context = 'all') {
  
  if (empty($filter) || empty($context) || 'all' == $context)
    return '';

  $arr_context = explode('|', $context);
  if (false != in_array('all', $arr_context))
    return '';

  // $count = count($arr_context);
  // switch ($arr_context[0]) {
  //   case 'image':
  //     for ($i=1; $i < $count; $i++) { 
  //       if (in_array($arr_context))
  //     }
  //     break;
    
  //   default:
  //     # code...
  //     break;
  // }

}


/**
 * Count number of attachments for a clearbase post.
 *
 * If you set the optional mime_type parameter, then an array will still be
 * returned, but will only have the item you are looking for. It does not give
 * you the number of attachments that are children of a post. You can get that
 * by counting the number of children that post has.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb
 *
 * @param int $post_id.  The ID of parent post
 *  
 * @param string|array $mime_type Optional. Array or comma-separated list of
 *                                MIME patterns. Default empty.
 * @return object An object containing the attachment counts by mime type.
 */
function clearbase_count_attachments($post = null, $mime_type = '' ) {
    global $wpdb;

    if (!$post = get_post($post))
        throw new WP_Error('invalid_post', 'You must specify a valid post!');

    $and = wp_post_mime_type_where( $mime_type );
    $count = $wpdb->get_results( $wpdb->prepare("SELECT post_mime_type, COUNT( * ) AS num_posts FROM $wpdb->posts 
        WHERE post_type = 'attachment' AND post_parent = %d 
        AND post_status != 'trash' $and GROUP BY post_mime_type", $post->ID), ARRAY_A );

    $counts = array();
    foreach( (array) $count as $row ) {
        $counts[ $row['post_mime_type'] ] = $row['num_posts'];
    }
    $counts['trash'] = $wpdb->get_var( $wpdb->prepare("SELECT COUNT( * ) FROM $wpdb->posts WHERE post_type = 'attachment' 
        AND post_parent = %d AND post_status = 'trash' $and", $post->ID) );

    /**
     * Modify returned attachment counts by mime type.
     *
     * @since 3.7.0
     *
     * @param object  $counts    An object containing the attachment counts by
     *                          mime type.
     * @param WP_POST $post     The parent post of the attachments.
     * @param string $mime_type The mime type pattern used to filter the attachments
     *                          counted.
     */
    return apply_filters( 'clearbase_count_attachments', (object) $counts, $post, $mime_type );
}


function clearbase_insert_attachment_data($data, $postArr) {
    //if adding a new attachment...
    if (empty( $postArr['ID'] ) ) {
        //...to a clearbase album
        $post_id = isset($data['post_parent']) ? absint($data['post_parent']) : 0;
        $post = $post_id ? get_post($post_id) : false;
        if ($post && 'clearbase_folder' === $post->post_type) {
            //set the menu order in the album
            $max = clearbase_get_max_menu_order($post_id);
            $data['menu_order'] = ++$max;
        }
    }

    return $data;
}

add_filter( 'wp_insert_attachment_data', 'clearbase_insert_attachment_data', 10, 2);


function clearbase_get_image_sizes() {

  $builtin_sizes = array(
    'large'   => array(
      'width'  => get_option( 'large_size_w' ),
      'height' => get_option( 'large_size_h' ),
    ),
    'medium'  => array(
      'width'  => get_option( 'medium_size_w' ),
      'height' => get_option( 'medium_size_h' ),
    ),
    'thumbnail' => array(
      'width'  => get_option( 'thumbnail_size_w' ),
      'height' => get_option( 'thumbnail_size_h' ),
      'crop'   => get_option( 'thumbnail_crop' ),
    ),
  );

  global $_wp_additional_image_sizes;
  $additional_sizes = $_wp_additional_image_sizes ? $_wp_additional_image_sizes : array();

  return array_merge( $builtin_sizes, $additional_sizes );

}