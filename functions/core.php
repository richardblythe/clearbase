<?php
function clearbase_workspace_url($args = array(), $escape_url = true) {
    $original = array_fill_keys(array_keys($_REQUEST), false);
    $original['page'] = $_REQUEST['page'];
    $args = array_merge($original, $args);
    $url = add_query_arg(apply_filters('clearbase_workspace_url_args', $args));
    return $escape_url ? esc_url_raw($url) : $url;
}

function clearbase_current_url($encode = false) {
    $pageURL = 'http';
   if ("on" == clearbase_empty_default($_SERVER, 'HTTPS', '')) {$pageURL .= "s";}
   $pageURL .= "://";
   if ('80' != clearbase_empty_default($_SERVER, 'SERVER_PORT', '')) {
    $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
   } else {
    $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
   }
   return $encode ? urlencode($pageURL) : $pageURL;
}

function clearbase_parse_url($parts = array(), $url = '') {
  if (empty($url))
    $url = clearbase_current_url();
    
  $url = parse_url($url);

  $scheme   = in_array('scheme', $parts) && isset($url['scheme']) ? $url['scheme'] . '://' : '';
  $host     = in_array('host', $parts) && isset($url['host']) ? $url['host'] : '';
  $port     = in_array('port', $parts) && isset($url['port']) ? ':' . $url['port'] : '';
  $user     = in_array('user', $parts) && isset($url['user']) ? $url['user'] : '';
  $pass     = in_array('pass', $parts) && isset($url['pass']) ? ':' . $url['pass']  : '';
  $pass     = ($user || $pass) ? "$pass@" : '';
  $path     = in_array('path', $parts) && isset($url['path']) ? $url['path'] : '';
  $query    = in_array('query', $parts) && isset($url['query']) ? '?' . $url['query'] : '';
  $fragment = in_array('fragment', $parts) && isset($url['fragment']) ? '#' . $url['fragment'] : '';
  return "$scheme$user$pass$host$port$path$query$fragment";
}

function clearbase_update_sorting($args, $wp_error = true) {
    global $wpdb;
    $sql = "UPDATE `{$wpdb->posts}` SET menu_order=%d, post_modified=%s,
        post_modified_gmt=%s WHERE ID=%d";

    foreach ($args as $post_id => $menu_order) {
        if (false === $wpdb->query( $wpdb->prepare( $sql, 
            $menu_order, 
            current_time( 'mysql' ),
            current_time( 'mysql', 1 ),
            $post_id ))) 
        {
            if ( $wp_error ) {
                return new WP_Error('db_update_sorting_error', __('Could not update clearbase post sorting in the database', 'clearbase'), $wpdb->last_error);
            } else {
                return false;
            }
        }
    }

    return true;
}

function clearbase_set_post_modified($post_id) {
    wp_update_post( array('ID' => $post_id, 'post_modified' => current_time('mysql')) );
}

function clearbase_get_max_menu_order($post_parent_id) {
    global $wpdb;
    $maxSort = $wpdb->get_var($wpdb->prepare(
        "SELECT menu_order FROM $wpdb->posts
         WHERE post_parent = %d
         ORDER BY menu_order DESC LIMIT 0, 1", $post_parent_id));
    return $maxSort ? (int) $maxSort : 0;
}


/**
 * Count number of posts that are children of the specified.  If an id of zero
 * is specified, a list of type: clearbase_gallery will be returned
 *
 * This function provides an efficient method of finding the amount of post's
 * type a blog has. Another method is to count the amount of items in
 * get_posts(), but that method has a lot of overhead with doing so. Therefore,
 * when developing for 2.5+, use this function instead.
 *
 * The $perm parameter checks for 'readable' value and if the user can read
 * private posts, it will display that for the user that is signed in.
 *
 * @since 2.5.0
 *
 * @global wpdb $wpdb
 *
 * @param int $id.  Specifies the ID of the parent post.  Use zero to return a count of clearbase_gallery
 * @param string $perm Optional. 'readable' or empty. Default empty.
   @param bool $sum_all. If true, returns a count of all posts that are listed in the All view
 * @return mixed object|int. Number of posts for each status or sum of all posts that are listed in the all view.
 */
function clearbase_count_posts( $id = 0, $perm = '', $sum_all = false) {
    global $wpdb;

    //ensure the $id is int
    $id = (int)$id;
    $get_galleries = ($id == 0);
    $cache_key = "clearbase_post_{$id}" . (empty($perm) ? '' : "_{$perm}");

    $counts = wp_cache_get( $cache_key, 'counts' );
    if ( $counts === false ) {
        $query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE " .
            ($get_galleries ? 'post_type = %s' : 'post_parent = %d');

        if ( 'readable' == $perm && is_user_logged_in() ) {
            $post = $get_galleries ? false : get_post($id);
            $post_type_object = get_post_type_object($get_galleries ? 'clearbase_gallery' : $post->post_type);
            if ( ! current_user_can( $post_type_object->cap->read_private_posts ) ) {
                $query .= $wpdb->prepare( " AND (post_status != 'private' OR ( post_author = %d AND post_status = 'private' ))",
                    get_current_user_id()
                );
            }
        }
        $query .= ' GROUP BY post_status';


        $results = (array) $wpdb->get_results( $wpdb->prepare( $query, $get_galleries ? 'clearbase_gallery' : $id ), ARRAY_A );

        foreach ( $results as $row ) {
            $counts[ $row['post_status'] ] = $row['num_posts'];
        }

        $counts = (object) $counts;
        wp_cache_set( $cache_key, $counts, 'counts' );
    }

    /**
     * Allow user to modify the posts count.
     *
     * @since 3.7.0
     *
     * @param object $counts An object containing the current post_type's post
     *                       counts by status.
     * @param string $type   Post type.
     * @param string $perm   The permission to determine if the posts are 'readable'
     *                       by the current user.
     */
    $counts = apply_filters( 'clearbase_count_posts', $counts, $id);

    if ($sum_all) {
        $total_posts = array_sum( (array) $counts );
        // Subtract post types that are not included in the admin all list.
        foreach ( get_post_stati( array('show_in_admin_all_list' => false) ) as $state )
          $total_posts -= $num_posts->$state;

        return $total_posts;
    }
    else
        return $counts;
}

function clearbase_get_array_value($key, $default, $arr = array()) {
    if (!is_array($arr))
        return $default;

    //split the key into tokens that we can proccess
    $tokens = explode('.', $key);
    //if the key contains more than one token, then the user is referencing a nested array
    //example: house.doors.back.color


}

function clearbase_get_value($key, $default = '', $data = null) {
    //split the key into tokens that we can proccess
    $tokens = explode('.', $key);
    $post = $value = null;

    if (in_array($tokens[0], array('post', 'postmeta'))) {
        if (!$post = get_post($data))
            return $default;
    }

    switch ($tokens[0]) {
        case 'post':
            if (count($tokens) > 2)
                return new WP_Error('clearbase_invalid_key', 'A post key cannot contain nested references', $key);
            $post = (array)$post;
            $value = $post[$tokens[1]];
        break;
        case 'postmeta':
        case 'option':
            /*  
            token[0] is keyword, 
            token[1] is the key
            token[2+] references a nested array 
            */
            $current_value = 'postmeta' == $tokens[0] ?
                 get_post_meta($post->ID, $tokens[1], true) :
                 get_option($tokens[1]);
            //if the field id contains more than the keyword: meta and a
            //key, then the user is referencing a nested array
            //example: house.doors.back.color
            if (count($tokens) > 2) {
                 $context = _clearbase_array_traverse(array_slice($tokens, 2, count($tokens) - 3), $current_value); 
                 $current_value = isset($context) ? $context[$tokens[count($tokens) - 1]] : '';
            } 

            $value = $current_value;
        break;
        default:
            $context = 1 == count($tokens) ? $data : _clearbase_array_traverse(array_slice($tokens, 0, count($tokens) - 2), $data); 
            $value = isset($context) ? $context[$tokens[count($tokens) - 1]] : null;
        break;
    }

    //if $value is not set or is an empty string, return the specified default. Else return the value
    return apply_filters('clearbase_get_value', !isset($value) || $value === '' ? $default : $value, $key);
}

function clearbase_set_value($key, $value, $data = null) {
    //split the key into tokens that we can proccess
    $tokens = explode('.', $key);
    $post = $result = null;


    if (in_array($tokens[0], array('post', 'postmeta'))) {
        if (!$post = get_post($data))
            return new WP_Error('invalid_post', 'Could not find a valid post for the specified key', $key);
    }

    switch ($tokens[0]) {
        case 'post':
            if (count($tokens) === 1 && !is_array($value))
                return new WP_Error('clearbase_invalid_key_value', 'For a root post key, you must specify the $value parameter as an array of post field key/values', $value);
            else if (count($tokens) > 2)
                return new WP_Error('clearbase_invalid_key', 'A post key cannot contain nested references', $key);
            //store the post value
            $args = array('ID' => $post->ID, $key => $value);
            if (is_array($value))
                $args = array_merge($args, $value);
            $result = wp_update_post(apply_filters('clearbase_set_value', $args, $key));
            break;
        case 'postmeta':
        case 'option':
            /*  
            token[0] is keyword, 
            token[1] is the key
            token[2+] references a nested array 
            */
            //get a copy of the current value
            $current_value = 'postmeta' == $tokens[0] ?
                 get_post_meta($post->ID, $tokens[1], true) :
                 get_option($tokens[1], array());
            //if the field id contains more than token[0] and token[1],
            //then the user is referencing a nested array
            //example: meta.house.doors.back.color
            if (count($tokens) > 2) {
                if (!is_array($current_value))
                    $current_value = array();
                $context =& _clearbase_array_traverse(array_slice($tokens, 2, count($tokens) - 3), $current_value, true );
                //now that we're at the correct nested array position, assign the specified value
                $context[$tokens[count($tokens) - 1]] = $value;
                //now we need to set $value's reference to the root array
                $value = $current_value;
            } else {
                //since the user may not have supplied all of the values in an array
                //we merge the two arrays to ensure that the prev db values are not lost
                if (is_array($value) && is_array($meta_value))
                    $value = array_merge($meta_value, $value);
            }

            $value = apply_filters('clearbase_set_value', $value, $key);
            //store the meta value
            $result = 'postmeta' == $tokens[0] ? 
                update_post_meta($post->ID, $tokens[1], $value) :
                update_option($tokens[1], $value);
            break;
        default: //TODO check function
            //try to insert a value into a runtime array
            if (!is_array($data))
                return false;

            $context =& _clearbase_array_traverse(array_slice($tokens, 0, count($tokens) - 2), $data, true);
            //now that we're at the correct nested array position, assign the specified value
            $context[$tokens[count($tokens) - 1]] = $value;

            $result = true;
            break;
    }

    return $result;
}

//uses the tokens as references to traverse a nested array. Returns the last token reference.  
//If build is set to true then a nested array structure will be created to match the tokens
function &_clearbase_array_traverse($tokens, &$array, $build = false) {
    if (!is_array($array))
        return null;
    //grab a direct reference to the root array
    $context_array = &$array; 
    for ($i = 0, $c = count($tokens); $i < $c; $i++){
        //ensure that the current token value references an array
        if (!is_array($context_array[$tokens[$i]])) {
            //if the caller wants to ensure a nested array from the tokens..
            if ($build)
                $context_array[$tokens[$i]] = array();
            else
                //nope the nested array does not exist
                return null;
        }
        //set the array context to the current token position
        $context_array = &$context_array[$tokens[$i]];
    }
    //return the array reference.  The caller must also accept by reference: $accept =& _clearbase_array_traverse()
    return $context_array;
}

function clearbase_array_search($needle, $haystack) {
    foreach ($haystack as $k => $v) {
        if (strpos($v, $needle) !== 0)
            return $k;
    }

    return false;
}

/**
 * Gets the WP admin directory.
 *
 * @return string
 */
function clearbase_wp_admin_path($end) {
    // Replace the site base URL with the absolute path to its installation directory. 
    return str_replace( get_bloginfo( 'url' ) . '/', ABSPATH, get_admin_url() ) . $end;
}

function clearbase_empty_default($array, $key, $default) {
    return isset($array) && isset($array[$key]) && !empty($array[$key]) ? $array[$key] : $default;
}

function clearbase_html_serialize_data($data = array()) {
    $data_serialized = '';
    foreach ($data as $key => $value) {
      $data_serialized .= " data-$key=".'"' .$value . '"';
    }

    return $data_serialized;

}


function _clearbase_template_loader( $template ) {
    if ( 'clearbase_folder' == get_post_type()) {
      $template = CLEARBASE_DIR . '/functions/template-loader.php';
    } 

    return $template;
}
add_filter( 'template_include', '_clearbase_template_loader' );


function _clearbase_request_filter( $request ) {
    if (!is_admin())
        return $request;

    //if query is not for attachments or the caller has already specified a post_parent__not_in
    //exit the filter so no conflicts will occur
    if (isset($request['post_type']) && 'attachment' != $request['post_type'])
        return $request;

    $is_wp_media = false;
    if (isset($_REQUEST['action']) && 'query-attachments' == $_REQUEST['action'])
        $is_wp_media = true;
    //
    if (!$is_wp_media) {
        $screen = get_current_screen();
        $is_wp_media = (isset($screen) && 'upload' === $screen->id);
    }

    //if this is a wordpress media request
    if ( $is_wp_media ) {
        global $wpdb;
        //get the post ids that are clearbase parents
        $post_ids = $wpdb->get_col(
            "SELECT ID FROM $wpdb->posts
             WHERE post_type = 'clearbase_folder'");
        //if we have results, filter out the media items that are attached to the post_ids 
        if (isset($post_ids) && count($post_ids) != 0) {
            $request['post_parent__not_in'] = isset($request['post_parent__not_in']) ?
                array_merge($request['post_parent__not_in'], $post_ids) : $post_ids;
        }
    }

    return $request;
}
add_filter( 'request', '_clearbase_request_filter' );
add_filter( 'ajax_query_attachments_args', '_clearbase_request_filter' );

// function _clearbase_plupload_init( $plupload_init ) {
//     $plupload_init['filters']['mime_types'] = array(
//         array('title' => "Image files", 'extensions' => "jpg,gif,png") 
//     );
//     return $plupload_init;
// }
// add_filter( 'plupload_init', '_clearbase_plupload_init');


//*******************************************************************************

function _clearbase_current_screen() {
    //this hack is required to use the class-cb-media-list-table.php
    $screen = get_current_screen(); 
    if (strpos($screen->id, 'clearbase') || strpos($screen->id, 'cbfolder'))
        add_filter( "manage_{$screen->id}_columns", '_clearbase_manage_columns', -1 );
}

function _clearbase_manage_columns() {
    return null;
}

add_action( 'current_screen', '_clearbase_current_screen' );
//********************************************************************************