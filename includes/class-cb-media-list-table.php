<?php
/**
 * Clearbase Media Library List Table class.
 * Extends WP_Media_List_Table for showing attachments in a Clearbase folder
 *
 * @package Clearbase
 * @subpackage WP_Media_List_Table
 */
require_once(clearbase_wp_admin_path('/includes/class-wp-media-list-table.php'));
class CB_Media_List_Table extends WP_Media_List_Table {

    /**
   * Constructor.
   *
   * @since 3.1.0
   * @access public
   *
   * @see WP_List_Table::__construct() for more information on default arguments.
   *
   * @param array $args An associative array of arguments.
   */
  public function __construct( $args = array() ) {

      if ( !$this->current_action() && clearbase_empty_default($_GET, '_wp_http_referer', false ) ) {
         wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
         die;
      }

      parent::__construct( $args );
      global $cb_post_id;

      add_filter('clearbase_workspace_action', array(&$this, 'filter_action'));
      add_filter('clearbase_workspace_form_attributes', array(&$this, 'filter_workspace_form'));
      add_filter('clearbase_workspace_url_args', array(&$this, 'filter_workspace_url_args'));

      //Implement the necessary things for attachments in a Clearbase folder
      $screen = get_current_screen();

      $_REQUEST['post_mime_type'] = clearbase_get_value('media_filter', null, clearbase_get_folder_settings());
      $_REQUEST['post_parent'] = $cb_post_id;
      $_REQUEST['orderby'] = 'menu_order';
      $_REQUEST['order'] = clearbase_get_value('postmeta.attachment_order', 'DESC');
      $this->isTrash = isset( $_REQUEST['attachment-filter'] ) && 'trash' == $_REQUEST['attachment-filter'];

      add_filter( 'manage_media_columns', array(&$this, 'manage_media_columns'));
      add_filter( "manage_{$screen->id}_sortable_columns", array(&$this, 'manage_sortable_columns') );
      add_filter( 'months_dropdown_results', array(&$this, 'manage_months_dropdown'), 10, 2);
      add_filter( 'media_row_actions', array(&$this, 'manage_row_actions'), 10, 2);
      add_filter( 'get_edit_post_link', array(&$this, 'edit_post_link'), 10, 3);

      add_action( 'manage_media_custom_column', array(&$this, 'render_column'), 10, 2);
  }

  public function filter_action($args = array()) {
      if ($this->current_action()) {
        $args['action']       = $this->current_action();
        $args['nonce_action'] = 'bulk-media';
        $args['nonce_field']  = '_wpnonce';
        $args['posts']        = $_REQUEST['media'];
      }

      return $args;
  }

  public function filter_workspace_form($attributes = array()) {
      $attributes['method'] = 'get';
      return $attributes;
  }

  public function filter_workspace_url_args($args = array()) {
      return array_merge(array_fill_keys(array(
            'attachment-filter', 'm', 's', 'filter_action', 'paged', 'action', 'action2'
      ), false), $args);
  }

  /** WP_Media_List_Table does not provide a filter for managing get_views() so,
   *  so we have done the next best thing.  Override
   *
   * @global wpdb  $wpdb
   * @global array $post_mime_types
   * @global array $avail_post_mime_types
   * @return array
   */
  public function get_views() {
    global $wpdb, $cb_post_id, $post_mime_types, $avail_post_mime_types;

    $type_links = array();
    // $_num_posts = (array) wp_count_attachments();
    $_num_posts = (array) clearbase_count_attachments($cb_post_id);
    //$_total_posts = array_sum($_num_posts) - $_num_posts['trash'];

    $media_filter = clearbase_get_value('media_filter', '', clearbase_get_folder_settings($cb_post_id));
    if (empty($media_filter))
      $media_filter = array_keys($post_mime_types);

    $matches = wp_match_mime_types($media_filter, array_keys($_num_posts));
    $num_posts = array();
    foreach ( $matches as $type => $reals ) {
      foreach ( $reals as $real ) {
        $num_posts[$type] = ( isset( $num_posts[$type] ) ) ? $num_posts[$type] + $_num_posts[$real] : $_num_posts[$real];
      }
    }

    $selected = empty( $_GET['attachment-filter'] ) ? ' selected="selected"' : '';
    if (count($num_posts) > 1) {
        $_total_posts = array_sum($num_posts) - $_num_posts['trash'];
        $type_links['all'] = "<option value=''$selected>" . sprintf( _nx( 'All (%s)', 'All (%s)', $_total_posts, 'uploaded files' ), number_format_i18n( $_total_posts ) ) . '</option>';
    }

    foreach ( $post_mime_types as $mime_type => $label ) {
      if ( !wp_match_mime_types($mime_type, $avail_post_mime_types) )
        continue;

      $selected = '';
      if ( !empty( $_GET['attachment-filter'] ) && strpos( $_GET['attachment-filter'], 'post_mime_type:' ) === 0 && wp_match_mime_types( $mime_type, str_replace( 'post_mime_type:', '', $_GET['attachment-filter'] ) ) )
        $selected = ' selected="selected"';
      if ( !empty( $num_posts[$mime_type] ) )
        $type_links[$mime_type] = '<option value="post_mime_type:' . esc_attr( $mime_type ) . '"' . $selected . '>' . sprintf( translate_nooped_plural( $label[2], $num_posts[$mime_type] ), number_format_i18n( $num_posts[$mime_type] )) . '</option>';
    }

    // $type_links['detached'] = '<option value="detached"' . ( $this->detached ? ' selected="selected"' : '' ) . '>' . sprintf( _nx( 'Unattached (%s)', 'Unattached (%s)', $total_orphans, 'detached files' ), number_format_i18n( $total_orphans ) ) . '</option>';

    if ( !empty($_num_posts['trash']) )
      $type_links['trash'] = '<option value="trash"' . ( (isset($_GET['attachment-filter']) && $_GET['attachment-filter'] == 'trash' ) ? ' selected="selected"' : '') . '>' . sprintf( _nx( 'Trash (%s)', 'Trash (%s)', $_num_posts['trash'], 'uploaded files' ), number_format_i18n( $_num_posts['trash'] ) ) . '</option>';

    return $type_links;
  }

  public function manage_media_columns($columns) {
      //remove the "Uploaded To" column.  All media items in our table are
      //uploaded to the current folder
      unset($columns['parent']);
      //if the attachment-filter is not set...
      if (!clearbase_empty_default($_REQUEST, 'attachment-filter', false)) {
        //add the dragsort column at the first position
        $columns = array('dragsort' => '') + $columns;
      }
      //return the updated columns
      return $columns;
  }

  public function manage_sortable_columns() {
      return array();
  }

  public function manage_months_dropdown($months, $post_type) {
      global $wpdb, $cb_post_id;
      return $wpdb->get_results( $wpdb->prepare( "
          SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
          FROM $wpdb->posts
          WHERE post_type = %s
          AND post_parent = %d
          ORDER BY post_date DESC
      ", $post_type, $cb_post_id ) );
  }

  public function edit_post_link($link, $post_id, $context) {
      return clearbase_workspace_url(array('id' => $post_id, 'cbaction' => 'edit'));
  }

  public function manage_row_actions($actions, $post) {
      if ( isset($actions['edit']))
          $actions['edit'] = '<a href="' . clearbase_workspace_url(array('id' => $post->ID, 'cbaction' => 'edit')) . '">' . __( 'Edit' ) . '</a>';
      if ( isset($actions['trash']) )
          $actions['trash'] = "<a class='submitdelete' href='" . clearbase_workspace_url(array('id' => $post->ID, 'cbaction' => 'trash', 'cbnonce' => wp_create_nonce('trash'))) . "'>" . __( 'Trash' ) . "</a>";
      if ( isset($actions['untrash']))
          $actions['untrash'] = "<a class='submitdelete' href='" . clearbase_workspace_url(array('id' => $post->ID, 'cbaction' => 'untrash', 'cbnonce' => wp_create_nonce('untrash'))) . "'>" . __( 'Restore' ) . "</a>";
      if ( isset($actions['delete']))
          //this action has js code embedded by WP_Media_List_Table.  So we just replace the url
          $actions['delete'] = preg_replace("/(?<=href=(\"|'))[^\"']+(?=(\"|'))/", clearbase_workspace_url(array('id' => $post->ID, 'cbaction' => 'delete', 'cbnonce' => wp_create_nonce('delete'))), $actions['delete']);

      unset($actions['view']);
      if (!$this->isTrash)
        $actions['move'] = '<a class="move-post" href="' . clearbase_workspace_url(array('id' => $post->ID, 'cbaction' => 'move', 'cbnonce' => wp_create_nonce('move'))) . '">' . __( 'Move', 'clearbase' ) . '</a>';
    
      return $actions;
  }


  /**
   *
   * @return array
   */
  public function get_bulk_actions() {
    $actions = array();
    if ( MEDIA_TRASH ) {
      if ( $this->is_trash ) {
        $actions['untrash'] = __( 'Restore' );
        $actions['delete'] = __( 'Delete Permanently' );
      } else {
        $actions['trash'] = __( 'Trash' );
      }
    } else {
      $actions['delete'] = __( 'Delete Permanently' );
    }

    // if ( $this->detached )
    //   $actions['attach'] = __( 'Attach to a post' );

    $actions['move'] = __('Move', 'clearbase');

    return $actions;
  }


  public function render_column($column_name, $post_id) {
      if ('dragsort' == $column_name) {
          echo '<span class="ui-draggable-handle"></span>';
      }
  }


}