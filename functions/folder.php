<?php

  function clearbase_add_folder($var) {
    global $cb_post_obj;

    if (!is_array($var)) {
      if (!is_numeric($var))
        return new WP_Error('clearbase_invalid_var', 'variable must be a parent id or array');
      //convert the single variable into an array
      $var = array('post_parent' => absint($var));
    }

    if (!isset($var['post_parent']))
        return new WP_Error( 'post_parent_empty', __( 'You must specify a post parent for the folder.' ) );

    $max_menu_order = clearbase_get_max_menu_order(absint($var['post_parent']));
    $var['menu_order'] = ++$max_menu_order;
    $var['post_type'] = 'clearbase_folder';
    $var['post_title'] = $cb_post_obj->labels->new_item; 
    $var['post_status'] = 'auto-draft';
    $var['post_title']  = __('New Folder', 'clearbase');

    $var = apply_filters('clearbase_add_folder', $var);

    return wp_insert_post( $var );
  }

 /**
 * Gets settings for the specified folder.  If no rules are found, but a parent folder
 * has settings, then the parent folder settings will be returned
 *
 * @param int    $folder_id The folder ID.
 * @return mixed|array|null The folder settings.
 */
  function clearbase_get_folder_settings($folder_id = null) {
      if (!$post = get_post($folder_id)) {
          $post = $GLOBALS['cb_post'];
      }

      $arrpost = isset($post) ? (array)$post : null;
      if (!isset($arrpost) || 'clearbase_folder' != $arrpost['post_type'])
        return null;

      $controller = clearbase_get_controller($post);
      return apply_filters('clearbase_folder_settings', !empty($controller) ? $controller->FolderSettings($post) : null, $post->ID);
  }


  function clearbase_folder_set_global($post_id = 0) {
      global $post, $cb_folder_root, $cb_post_id, $cb_post, $cb_post_type_obj;

      $cb_post_id = absint($post_id);
      
      //if the post_id and folder_root are not the same, then make sure that 
      //the requested folder is a child of the global $cb_folder_root. 
      if ($cb_post_id != $cb_folder_root && !clearbase_is_child_of($cb_folder_root, $cb_post_id)) {
          $cb_post_id = $cb_folder_root;
      }

      $post = $cb_post = $cb_post_id === 0 ? (object)array( 'ID' => 0, 'post_type' => 'clearbase_folder') : get_post($cb_post_id);
      $cb_post_type_obj = get_post_type_object($cb_post->post_type);

      return $cb_post;
  }

  function clearbase_move_to_folder($folder_id = null, $post_ids = array()) {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return new WP_Error('cannot-edit-posts', __('You do not have permission to move the items.', 'clearbase'));
    }

    $folder = get_post($folder_id);
    if (!isset($folder) || 'clearbase_folder' !== $folder->post_type ) {
        return new WP_Error('invalid-folder', __('The specified folder id is invalid: ' . $folder_id, 'clearbase'));
    }


    if (is_numeric($post_ids))
      $post_ids = array(absint($post_ids));

    $maxsort = clearbase_get_max_menu_order($folder_id);
    foreach($post_ids as $key => $postID) {
       wp_update_post( array( 'ID' => $postID, 'post_parent' => $folder_id, 'menu_order' => ++$maxsort) );
    }

    return true;
  }

  function get_clearbase_folder_id() {
    return $GLOBALS[$cb_post_id];
  }

  function clearbase_folder_id() {
    echo get_clearbase_folder_id();
  }


  function clearbase_folders_with_controller($controller_id = '') {
      return empty($controller_id) ? array() : get_posts(array(
          'post_type'     => 'clearbase_folder',
          'numberposts'   => -1,
          'meta_key' => 'clearbase_controller',
          'meta_value' => $controller_id
      ));
  }

  function clearbase_query_subfolders($folder_id = null) {
      $folder = (null == $folder_id || 0 == $folder_id ? null : clearbase_load_folder($folder_id));
      if (is_wp_error($folder))
        return $folder;

      return new WP_Query(apply_filters('clearbase_query_subfolders_args', array(
          'post_type' => 'clearbase_folder',
          'post_parent' => null == $folder ? 0 : $folder->ID,
          'orderby'    => 'menu_order',
          'order'      => 'DESC',
          'posts_per_page'=> -1
      )));
  }

  function clearbase_query_all_folders() {
      return new WP_Query(apply_filters('clearbase_query_all_folders_args', array(
        'post_type' => 'clearbase_folder',
        'posts_per_page' => -1
      )));
  }

  function clearbase_subfolder_count($folder_id = null) {
      $query = clearbase_query_subfolders($folder_id);
      return ($query instanceof WP_Query ? $query->found_posts : 0);
  }

 /**
 * Loads a Clearbase folder.
 *
 * @param mixed $data WP data to load the folder.  If $data is the default: null, the global $post var will be evalutated as the data.  If the evalutated post is an attachment, then it's parent folder will be returned 
 * @return mixed|array|WP_Error The folder or a WP error.
 */
  function clearbase_load_folder($data = null) {
    $post = get_post($data);
    
    if (isset($post)) {
      if ('clearbase_folder' == $post->post_type)
        return $post;
      //try to load the post parent as a clearbase folder
      else if ( 0 !== $post->post_parent ) {
        $post = get_post($post->post_parent);
        if ('clearbase_folder' == $post->post_type)
          return $post;
      }
    }

    return new WP_Error('clearbase_invalid_folder', __('You must specify valid data to retrieve a clearbaser folder', 'clearbase'));
  } 

  function clearbase_default_folder_image_src($folder_id = null, $image_size = 'thumbnail') {
      $folder = clearbase_load_folder($folder_id);
      if (is_wp_error($folder))
        return $folder;

      if ('full' == $image_size)
        $image_src = array(CLEARBASE_URL . '/images/folder150x150.png', 150, 150);
      else
        $image_src = array(CLEARBASE_URL . '/images/folder40x40.png', 40, 40);

      if ($attachment = clearbase_get_first_attachment('image', $folder))
          $image_src = wp_get_attachment_image_src( $attachment->ID, $image_size );
      
      return apply_filters('clearbase_default_folder_image_src', $image_src, $folder, $image_size);
  }

  function clearbase_is_root($relative = true) {
      global $cb_folder_root, $cb_post_id;

      return $cb_post_id == ($relative ? $cb_folder_root : 0);
  }


  function clearbase_get_folder_tree($folder = 0) {
    $folder = clearbase_load_folder($folder);
      if (is_wp_error($folder))
        return $folder;

    global $cb_folder_tree;
    if (!isset($cb_folder_tree)) {
      $cb_folder_tree = array();
      $references = array();
      $query = clearbase_query_all_folders();
      $posts = array();
      foreach ($query->get_posts() as $post) 
        $posts[$post->ID] = array('parent' => $post->post_parent);

      /* Credits: http://www.tommylacroix.com/2008/09/10/php-design-pattern-building-a-tree/ */
      foreach ($posts as $id=>&$node) {
        if ($node['parent'] === 0) { // root node
          $cb_folder_tree[$id] = &$node;
        } else { // sub node
          if (!isset($posts[$node['parent']]['folders'])) 
            $posts[$node['parent']]['folders'] = array();
          $posts[$node['parent']]['folders'][$id] = &$node;
        }
      }
    }

    return 0 == $folder->ID ? $cb_folder_tree : __clearbase_folder_tree_find($folder->ID, $cb_folder_tree);
}

  function __clearbase_folder_tree_find($folder_id, $folder_tree) {
      $result = null;
      foreach ($folder_tree as $id => $node) {
          if ($id == $folder_id)
            $result = array($id => $node); //return the standard tree format array[key] = node
          else if (isset($node['folders']))
            $result = __clearbase_folder_tree_find($folder_id, $node['folders']);
          if ($result)
            break; 
      }
      return $result;
  }

  function clearbase_is_child_of($parent_folder_id, $post_id) {
      //a post id of zero will never be a child of anything
      if (0 == $post_id)
        return false;

      //find out if the $post_id is a folder
      $post = get_post($post_id);
      if (is_wp_error($post))
        return $post;
      $is_child = $post->post_parent === $parent_folder_id; 
    
      //search deeper into the tree
      if (!$is_child) {
        $folder_id = ('clearbase_folder' === $post->post_type ? $post_id : $post->post_parent);

        //search the master tree for the parent_folder_id
        $parent_tree = clearbase_get_folder_tree($parent_folder_id);
        //if we have found the parent folder tree
        if ($parent_tree) {
            //look in the parent_tree for the folder_id.  If the return is false
            //then the folder_id is not a child of parent_folder tree
            $is_child = (null !== __clearbase_folder_tree_find($folder_id, $parent_tree));
        }
      }
      //return the result
      return $is_child;
  }

  function clearbase_get_children($folder, $flatten = false) {
     $folder = clearbase_load_folder($folder);
      if (is_wp_error($folder))
        return array();
     $tree = clearbase_get_folder_tree($folder);
     if ($flatten) {
        $flattened_array = __clearbase_flatten_tree($tree);
        unset($flattened_array[0]); //first position contains the $folder->ID.  We only want the children
        return $flattened_array;
     }
     else
      return $tree;
  }

  function __clearbase_flatten_tree($tree) {
    $keys = array();
    foreach ($tree as $id => $value) {
      if (0 != $id)
        $keys[] = $id;

      if (isset($value['folders']))
        $keys = array_merge($keys, __clearbase_flatten_tree($value['folders']));
    }

    return $keys;
  }

  function clearbase_get_jstree_data($folder_data = array(), $defaults = array()) {
    static $instance = 0;
    $instance++;

    //convert the query into a json array
    $arrFolders = array();
    $arrKeys = array_keys($folder_data);
    global $post;
    //Get all clearbase folders
    $query = clearbase_query_all_folders();
    while ($query->have_posts()) : $query->the_post();
      $arrFolders[] = (object)array_merge($defaults, 
        isset($folder_data[$post->ID]) ? $folder_data[$post->ID] : array(), array(
        'id'       => "jstree{$instance}-folder-{$post->ID}",
        'text'     => get_the_title(),
        'parent'   => 0 == $post->post_parent ? '#' : "jstree{$instance}-folder-{$post->post_parent}",
        )
      ); 
    endwhile;
    return apply_filters('clearbase_jstree_data', $arrFolders);
  }

  function _clearbase_append_folder_to_page( $content ) {
    if (is_page()) {

      $query = new WP_Query(array(
        'post_type' => 'clearbase_folder',
        'post_status'  => 'publish',
        'meta_query' => array(
          array(
            'key'     => 'page_append',
            'value'   => get_the_ID(),
            'type'    => 'numeric',
            'compare' => '='
          )
        )
      ));

      while ( $query->have_posts() ) : $query->the_post();
        $content .= clearbase_short_code_handler(array('id' => get_the_ID()));
      endwhile;
      wp_reset_postdata();

    }
    return $content;
  }
  add_action( 'the_content', '_clearbase_append_folder_to_page' );