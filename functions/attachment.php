<?php
function clearbase_get_attachments($type = '', $folder = null, $max = -1, $query_args = null) {
    //allow another method to return an array of attachments
	$result = apply_filters('clearbase_get_attachments_pre', false, array($type, $folder, $max, $query_args));
	//if another method has returned an array value, we'll assume that it's an array of attachments
    if (is_array($result))
		return $result;

	$folders = clearbase_get_folders(is_array($folder) ? $folder : array($folder));
	$folder = is_array($folder) ? null : clearbase_load_folder($folder);

    $query_args = wp_parse_args($query_args, array(
	    'post_type'      => 'attachment',
	    'post_mime_type' => $type,
	    'post_status'    => 'any',
	    'post_parent__in'    => array_keys($folders),
	    'orderby'        => array('menu_order', clearbase_get_value('postmeta.attachment_order', 'DESC', $folder)),
	    'posts_per_page'    => $max
    ));

    return get_posts(apply_filters('clearbase_query_media_args', $query_args));

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

function clearbase_get_nth_attachment($type = '', $folder = null, $nth = 1) {
	if ($nth <= 0)
		$nth = 1;


	$attachments = clearbase_get_attachments($type, $folder, $nth);
	$result = (count($attachments) >= $nth) ? $attachments[$nth-1] : null;
	//allow another function to override the result
	return apply_filters('clearbase_get_nth_attachment', $result);
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
        return new WP_Error('invalid_post', 'You must specify a valid post!');

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

/**
 * Retrieve an attachment page link using an image or icon, if possible.
 *
 * @since 2.5.0
 * @since 4.4.0 The `$id` parameter can now accept either a post ID or `WP_Post` object.
 *
 * @param int|WP_Post  $id        Optional. Attachment ID or post object.
 * @param string|array $size      Optional. Image size. Accepts any valid image size, or an array
 *                                of width and height values in pixels (in that order).
 *                                Default 'thumbnail'.
 * @param bool         $permalink Optional, Whether to add permalink to image. Default false.
 * @param bool         $icon      Optional. Whether the attachment is an icon. Default false.
 * @param string|false $text      Optional. Link text to use. Activated by passing a string, false otherwise.
 *                                Default false.
 * @param array|string $attr      Optional. Array or string of attributes. Default empty.
 * @return string HTML content.
 */
function clearbase_get_attachment_parent_link( $id = 0, $size = 'thumbnail', $icon = false, $text = false, $attr = '' ) {
    $_post = get_post( $id );

    if ( empty( $_post ) || ( 'attachment' != $_post->post_type ) || !$post_parent = get_post($_post->post_parent) )
        return __( 'Missing Attachment' );

    $url = get_permalink( $post_parent->ID );

    if ( $text ) {
        $link_text = $text;
    } elseif ( $size && 'none' != $size ) {
        $link_text = wp_get_attachment_image( $_post->ID, $size, $icon, $attr );
    } else {
        $link_text = '';
    }

    if ( trim( $link_text ) == '' )
        $link_text = $post_parent->post_title;

    /**
     * Filter a retrieved attachment parent page link.
     *
     * @since 2.7.0
     *
     * @param string       $link_html The page link HTML output.
     * @param int          $id        Post ID.
     * @param string|array $size      Size of the image. Image size or array of width and height values (in that order).
     *                                Default 'thumbnail'.
     * @param bool         $permalink Whether to add permalink to image. Default false.
     * @param bool         $icon      Whether to include an icon. Default false.
     * @param string|bool  $text      If string, will be link text. Default false.
     */
    return apply_filters( 'clearbase_get_attachment_parent_link', "<a href='$url'>$link_text</a>", $id, $size, $permalink, $icon, $text );
}