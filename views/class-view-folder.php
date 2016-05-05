<?php
/**
 * Clearbase Folder View.
 * A fork of WP_Media_List_Table which includes:
 *
 *  Subfolders
 *  Drag-drop sorting
 *  Clearbase filters
 *  Custom bulk actions
 *
 * @package Clearbase
 * @subpackage Clearbase_View
 * @since 1.0.0
 * @access public
 */
require_once (CLEARBASE_DIR . '/views/class-view.php');
class Clearbase_View_Folder extends Clearbase_View {
    protected $is_trash, $settings, $media_table;

    public function ID() {
        return 'folder';
    }

    public function __construct() {
        parent::__construct();
        global $cb_post, $cb_post_id;
        $this->settings = clearbase_get_folder_settings();

        $this->show_folders = clearbase_get_value('allow_folders', true, $this->settings);
        $this->show_add_folders = !$this->show_folders ? false : apply_filters("clearbase_allow_new_folders", true, $cb_post_id);

        $this->show_media = clearbase_get_value('allow_media', true, $this->settings);
        $this->show_add_media = !$this->show_media ? false : apply_filters("clearbase_allow_new_media", true, $cb_post_id);


        $this->is_trash = false; //TODO
        if (clearbase_is_root(false)) {
          //enforce Clearbase root rules
          $this->show_folders = $this->show_add_folders = true;
          $this->show_media = $this->show_add_media = false;
        }

        $modes = array( 'grid', 'list' );
        if ( isset( $_GET['mode'] ) && in_array( $_GET['mode'], $modes ) ) {
          $this->mode = clearbase_empty_default($_GET, 'mode', '');
          update_user_option( get_current_user_id(), 'clearbase_media_mode', $this->mode );
        } else {
          $this->mode = get_user_option( 'clearbase_media_mode', get_current_user_id() );
          if (!in_array($this->mode, $modes)) 
            $this->mode = 'grid'; //set the default view to grid
        }

        if ($this->show_media && 'list' == $this->mode) {
          require_once (CLEARBASE_DIR . '/includes/class-cb-media-list-table.php');
          $this->media_table = new CB_Media_List_Table();
        }
    }

    public function Header($header = array()) {
      global $cb_post_id;
      
      if (!clearbase_is_root(false) && current_user_can( 'edit_post', $cb_post_id )) {
          $header['edit'] = '<a href="'. clearbase_workspace_url(array(
              'cbaction' => 'edit', 
              'id' => $cb_post_id)) . 
          '" class="button-secondary edit-folder">'. 
          apply_filters('clearbase_edit_folder_label', __('Edit', 'clearbase'), $cb_post_id) .'</a>';
      }

      if ($this->show_add_folders) {
          $header['add-folder'] = '<a href="'. clearbase_workspace_url(array(
              'cbaction' => 'add-folder', 
              'id' => $cb_post_id,
              'cbnonce' => wp_create_nonce('add-folder'))) .'" class="addnew add-new-h2 folders">'. 
              apply_filters('clearbase_folder_add_label', __('Add Folder', 'clearbase'), $cb_post_id) .'</a>';
      }

      if ($this->show_add_media) {
          $header['add-media'] = '<a href="#" class="addnew add-new-h2 media">'. 
              apply_filters('clearbase_media_add_label', __('Add Media', 'clearbase'), $cb_post_id) .'</a>';
      }


      return $header;
    }

    public function Enqueue() {
        global $cb_post_id;
        if ( $this->show_folders  || $this->show_add_folders ||
             $this->show_media  || $this->show_add_media) {
            add_filter('media_view_settings', array(&$this, '_media_view_settings'), 10, 2);
            wp_enqueue_media(array('post' => $cb_post_id));
        }

        if ($this->show_media && 'grid' === $this->mode ) {
          wp_enqueue_script( 'media-grid' );
          wp_enqueue_script( 'media-grid-overrides', CLEARBASE_URL . '/js/media-grid-overrides.js', array('media-grid'));
          wp_enqueue_script( 'clearbase', CLEARBASE_URL . '/js/clearbase.js', array('media-grid', 'media-grid-overrides'));

          add_filter( 'admin_body_class', array(&$this, '_body_class' ));

          //TODO set uploader filter type

          $vars = array(
            'post_parent' => $cb_post_id,
            'orderby'     => 'menuOrder',
            'order'       => clearbase_get_value('postmeta.attachment_order', 'DESC', $cb_post_id)
          );
          $media_filter = clearbase_get_value('media_filter', '', clearbase_get_folder_settings($cb_post_id));
          if (!empty($media_filter))
            $vars['post_mime_type'] = $media_filter;

          $urlparts = parse_url( clearbase_current_url() );
          wp_localize_script( 'media-grid', '_wpMediaGridSettings', array(
            'adminUrl' => clearbase_parse_url(array('path', 'query', 'fragment')),
            'queryVars' => (object)$vars
          ) );
        }
    }

    public function _body_class($classes) {
        return "$classes upload-php";
    }

    public function _media_view_settings($settings = array(), $post = null) {
        global $cb_post_id;
        $media_filter = clearbase_get_value('media_filter', '', clearbase_get_folder_settings($cb_post_id));
        if (!empty($media_filter)) {
            $post_mime_types = get_post_mime_types();
            $matches = wp_match_mime_types($media_filter, array_keys($post_mime_types));
            $settings['mimeTypes'] = wp_list_pluck( array_intersect_key($post_mime_types, $matches), 0 );

            global $wpdb, $wp_locale;
            $post_parent = absint($post->ID);
            $and_where_mime = wp_post_mime_type_where($media_filter);
            $months = $wpdb->get_results("
              SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
              FROM $wpdb->posts
              WHERE post_parent = $post_parent AND post_type = 'attachment' $and_where_mime
              ORDER BY post_date DESC"
            );
            foreach ( $months as $month_year ) {
              $month_year->text = sprintf( __( '%1$s %2$d' ), $wp_locale->get_month( $month_year->month ), $month_year->year );
            }
            $settings['months'] = $months;

        }
        return $settings;
    }

    public function RenderEditor() {
        global $cb_post_id, $post;
        //-------------------------------------------------------------
        //--------------    Render Subfolders   -----------------------
        //-------------------------------------------------------------
        if ($this->show_folders) :
          $query = clearbase_query_subfolders($cb_post_id);
          $drag_sortable = apply_filters("clearbase_{$this->ID()}_subfolders_drag_sortable", true, $cb_post_id); //TODO user option?
          $classes = array( "folder-{$cb_post_id}", 'folders' );
          if ($drag_sortable)
            $classes[] = 'ui-sortable-container';

          $classes = apply_filters("clearbase_{$this->ID()}_subfolders_container_class", $classes, $cb_post_id);

          if ( $query->have_posts() ) :
            echo '<ul class="' . implode(' ', $classes) . '">'; 
            while ( $query->have_posts() ) : $query->the_post();
              $this->render_subfolder($post);
            endwhile; 
            echo '</ul>';
            wp_reset_postdata();
          else :
              echo apply_filters("clearbase_{$this->ID()}_subfolders_empty", '<p>' . __( 'No child folders.', 'clearbase' ) . '</p>');
          endif;

        endif;
        //-------------------------------------------------------------
        //-------------    Render media   -----------------------
        //-------------------------------------------------------------
        if (!$this->show_media)
          return;

        if ('grid' === $this->mode) {
          ?>
            <div class="wrap" id="wp-media-grid" data-search="">
            <div class="error hide-if-js">
              <p><?php _e( 'The grid view for the Media Library requires JavaScript. <a href="upload.php?mode=list">Switch to the list view</a>.' ); ?></p>
            </div>
          </div>
          <?php
        } else {
          //
          $this->media_table->prepare_items();
          $this->media_table->views();
          $this->media_table->display();
        }

    }


    protected function render_subfolder($post) {
        $url = clearbase_workspace_url(array('id' => $post->ID));
        $classes = apply_filters("clearbase_{$this->ID()}_subfolder_classes", array('clearbase-folder'));

        $settings = clearbase_get_folder_settings($post);
        if ($settings['allow_folders'])
            $classes[] = 'allow-folders';
        $data = apply_filters("clearbase_{$this->ID()}_subfolder_data", array('menu-order' => $post->menu_order));
    ?>
      <li id="post-<?php echo $post->ID; ?>" class="<?php echo implode(' ', $classes) ?>" <?php echo clearbase_html_serialize_data($data) ?> >
        <?php echo $this->render_subfolder_actions($post); ?>
        <div class="clearbase-folder-preview">
          <div class="thumbnail">
              <div class="centered">
                <a href="<?php echo $url ?>">
                  <img class="ui-draggable-handle" src="<?php echo apply_filters('clearbase_folder_icon', 
                     CLEARBASE_URL . '/images/folder150x150.png', array('width' => 150, 'height' => 150), $post); ?>" draggable="false" alt="">
                </a>
              </div>
          </div>
          <div class="title-container">
            <div class="title">
                <a href="<?php echo $url ?>">
                    <?php echo apply_filters('clearbase_folder_title', $post->post_title, $post); ?>
                </a>
            </div>
          </div>
        </div>
      </li>
    <?php
    }


    protected function render_subfolder_actions( $post ) {
      $actions = array();

      if ( current_user_can( 'edit_post', $post->ID ) && !$this->is_trash )
        $actions['edit'] = '<a href="' . clearbase_workspace_url(array(
            'id' => $post->ID, 
            'cbaction' => 'edit',
            'back' => clearbase_current_url(true) )) . '" title="' . __( 'Edit Folder', 'clearbase' ) . '">' . __( 'Edit', 'clearbase' ) . '</a>';
        
        if (clearbase_get_value('child_folders', true, $this->settings)) {
            $actions['move'] = "<a class='move-post' title='" . esc_attr__( 'Move this folder to another folder', 'clearbase' ) . "' href='" . clearbase_workspace_url(array(
                'id'    => $post->ID,
                'cbaction' => 'move', 
                'cbnonce' => wp_create_nonce('move')))  . "'>" . __( 'Move', 'clearbase' ) . "</a>";          
        }

        if ( current_user_can( 'delete_post', $post->ID ) ) {
          $actions['delete'] = "<a class='submitdelete' title='" . esc_attr__( 'Delete this folder permanently' ) . "' href='" . clearbase_workspace_url(array(
              'id'    => $post->ID,
              'cbaction' => 'delete', 
              'cbnonce' => wp_create_nonce('delete')))  . "'>" . __( 'Delete', 'clearbase' ) . "</a>";
      
          //MEDIA LIST VIEW NOT WORKING
      }

      $actions = apply_filters( "clearbase_{$this->ID()}_subfolder_actions", $actions, $post);
      $action_count = count( $actions );
      if ( !$action_count)
        return;
      $i = 0;
      ?>
      <div class="folder-actions-container">
        <div class="folder-actions">
        <?php
          foreach ( $actions as $action => $link ) {
            ( ++$i == $action_count ) ? $sep = '' : $sep = ' | ';
            echo "<span class='$action'>$link$sep</span>";
          }
        ?> 
        </div>
      </div>
      <?php
  }


}

