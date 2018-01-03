<?php
/*
  Plugin Name: Clearbase Recent Images Widget
  Description: A widget that displays the most recent images from a specified folder
  Version: 1.0.2
  Author: Richard Blythe
  Author URI: http://unity3software.com/richardblythe
 */

// Block direct requests
if ( !defined('ABSPATH') )
  die('-1');
  
  
add_action( 'widgets_init', function(){
     register_widget( 'Clearbase_Recent_Images_Widget' );
});

class Clearbase_Recent_Images_Widget extends WP_Widget {

    public function __construct() {
      $widget_ops = array( 'classname' => 'cb-recent-imgs', 'description' => __( 'Displays the most recent Clearbase images inside a widget area', 'cb_recent_imgs' ) );
      $control_ops = array( 'width' => 200, 'height' => 250, 'id_base' => 'cb-recent-imgs-widget' );
      parent::__construct( 'cb-recent-imgs-widget', 
        __( 'Clearbase Recent Images', 'cb_recent_imgs' ), 
        $widget_ops, 
        $control_ops 
      );
    
      add_action( 'current_screen', array(&$this, 'current_screen') );
    }

    function current_screen() {
      $currentScreen = get_current_screen();
      if('widgets' === $currentScreen->id) {
        add_action( 'admin_enqueue_scripts', array(&$this, 'enqueue_admin') );
      }
    }

    function enqueue_admin() {
      wp_enqueue_script('jstree', CLEARBASE_URL . '/includes/assets/jstree/jstree.min.js', array('jquery'));
      wp_enqueue_style( 'jstree', CLEARBASE_URL . '/includes/assets/jstree/themes/default/style.min.css');
    }

    function save_settings( $settings ) {
      $settings['_multiwidget'] = 0;
      update_option( $this->option_name, $settings );
    }

    // display widget
    function widget( $args, $instance ) {
            extract( $args );

            echo $before_widget;

            $title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
            if ( $title )
                echo $before_title . $title . $after_title;

            $folders = isset ( $instance['folders'] ) ? $instance['folders'] : '';
            $all_folders = explode(',', $folders);
            //now we need to examine each folder for nested child folders...
            for ($i = 0, $ni = count($all_folders); $i < $ni; $i++)
              $all_folders = array_merge($all_folders, clearbase_get_children($all_folders[$i], true));

            //loop through all of the folders and enforce folder settings
            for ($i=count($all_folders) - 1; $i > -1; $i--) { 
              $settings = clearbase_get_folder_settings($all_folders[$i]);
              if (!clearbase_get_value('allow_media', true, $settings))
                unset($all_folders[$i]);
            }

            $columns = (int)(isset( $instance['columns'] ) ? $instance['columns'] : 3);
            $rows = (int)(isset( $instance['rows'] ) ? $instance['rows'] : 3);
            $image_size = isset ( $instance['image_size'] ) ? $instance['image_size'] : 'thumbnail';
            $link_to = isset ( $instance['link_to'] ) ? $instance['link_to'] : 'parent';

            //RENDER
            if (0 == count($all_folders)) {
              echo 'There are no recent images to display';
            } else {


                $attachments = clearbase_get_attachments('image', $all_folders, $columns * $rows, array('orderby' => 'modified menu_order', 'order' => 'DESC'));
//                $attachments = get_posts(array(
//                  'post_type'      => 'attachment',
//                  'post_mime_type' => 'image',
//                  'post_status'    => 'any',
//                  'post_parent__in'    => $all_folders,
//                  'orderby'        => 'date',
//                  'order'          => 'DESC',
//                  'posts_per_page'    => $columns * $rows
//              ));
              $IDs = array();
              foreach ($attachments as $a)
                  $IDs[] = $a->ID;

              add_filter( 'gallery_image_caption', '__return_empty_string');
              add_filter( 'wp_get_attachment_image_attributes', array(&$this, 'image_attributes'), 10, 3);
              if ('parent' == $link_to) 
                add_filter( 'attachment_link', array(&$this, 'link_to_parent'), 20, 2);

              echo clearbase_gallery_shortcode(array(
                'ids'       => $IDs,
                'orderby'   => 'modified menu_order',
                'order'     => 'DESC',
                'columns'   => $columns,
                'size'      => $image_size,
                'link'      => $link_to
              ));
              remove_filter( 'gallery_image_caption', '__return_empty_string');
              remove_filter( 'wp_get_attachment_image_attributes', array(&$this, 'image_attributes'), 10, 3);
              if ('parent' == $link_to) 
                remove_filter( 'attachment_link', array(&$this, 'link_to_parent'), 20, 2);
              
            }

            echo $after_widget;
    }

    function inject_gallery_style($style) {
      return '<style type="text/css">.cb-recent-imgs .gallery-caption { display: none; }</style>' . $style;
    }

    function image_attributes($attr, $attachment, $size) {
      if (!empty($attachment->post_excerpt)) 
        $attr['title'] = $attachment->post_excerpt;
      else if (!empty($attachment->post_title))
        $attr['title'] = $attachment->post_title;
      return $attr;
    }

    function link_to_parent($url, $post_id) {
      $post = get_post($post_id);
      $parent = get_post($post->post_parent);
      if ($parent)
        return get_permalink($parent->ID);
      else
        return $url;
    }

    /** Widget options */
    function form( $instance ) {
        $instance = wp_parse_args( (array) $instance);
        $title = $instance['title'];

        $jstree_id = $this->get_field_id('jstree');
        $folders_id = $this->get_field_id( 'folders' );
        $folder_id_base = $this->get_field_id( 'folder' );
    ?>
    <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'cb_recent_imgs' ); ?> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
        </label>
    </p>
        
    <?php
        $folders = isset ( $instance['folders'] ) ? $instance['folders'] : '';
        $folders = array_fill_keys(explode(',', $folders), array( 
          'state' => array('selected' => true)
        ));

        $columns = (int)(isset( $instance['columns'] ) ? $instance['columns'] : 3);
        $rows = (int)(isset( $instance['rows'] ) ? $instance['rows'] : 3);
        $image_size = isset ( $instance['image_size'] ) ? $instance['image_size'] : 'thumbnail';
        $link_to = isset ( $instance['link_to'] ) ? $instance['link_to'] : 'parent';

        $jstree_data = clearbase_get_jstree_data($folders);
    ?>
    
    <div class="widget-column">
        <p>     
            <label for="<?php echo $jstree_id; ?>"><?php _e( 'Media Folders', 'cb_recent_imgs' ); ?>:</label>
            <input type="hidden" 
                id="<?php echo $folders_id; ?>" 
                name="<?php echo $this->get_field_name( 'folders' ); ?>"
                value="<?php echo $instance['folders'] ?>"
            />
            <div id="<?php echo $jstree_id; ?>"></div>
            <?php

            $folder_icon = CLEARBASE_URL . '/images/folder20x20.png';
            $output = 
            "jQuery(document).ready(function($) {
                $('#{$jstree_id}').on('changed.jstree', function (e, data) {
                    var i, j, id, r = [];
                    for(i = 0, j = data.selected.length; i < j; i++) {
                      id = data.instance.get_node(data.selected[i]).id;
                      r.push(id.substring(id.lastIndexOf('-') + 1));
                    }
                    $('#{$folders_id}').val(r.join(','));
                  }).jstree({ 
                    'plugins' : [ 'checkbox', 'types' ],
                    'types': {
                      'default': {
                        'icon': '{$folder_icon}'
                      }
                    },
                    'checkbox' : {
                      'keep_selected_style' : false,
                      'three_state' : false,
                      'cascade' : 'up'
                    },
                    'core' : {
                    'data' : " . wp_json_encode($jstree_data) . "
                  }});
              });";

            $output = str_replace( array( "\n", "\t", "\r" ), '', $output );
            echo '<script type=\'text/javascript\'>' . $output . '</script>';
            ?>

        </p>
    </div>

    <p>
        <label for="<?php echo $this->get_field_id( 'columns' ); ?>"><?php _e( 'Columns:', 'cb_recent_imgs' ); ?> </label>
         <select id="<?php echo $this->get_field_id( 'columns' ); ?>" name="<?php echo $this->get_field_name( 'columns' ); ?>">
          <?php for ($i = 1; $i<=4; $i++) {
            echo "<option value=\"{$i}\"". selected($i, $columns, false) . ">{$i}</option>";
          }
          ?> 
        </select>
      <label for="<?php echo $this->get_field_id( 'rows' ); ?>"><?php _e( 'Rows:', 'cb_recent_imgs' ); ?> </label>
       <select id="<?php echo $this->get_field_id( 'rows' ); ?>" name="<?php echo $this->get_field_name( 'rows' ); ?>">
        <?php for ($i = 1; $i<=4; $i++) {
          echo "<option value=\"{$i}\"". selected($i, $rows, false) . ">{$i}</option>";
        }
        ?>
      </select> 
    </p>
    <p>
      <label for="<?php echo $this->get_field_id( 'image_size' ); ?>"><?php _e( 'Image Size:', 'cb_recent_imgs' ); ?> </label>
       <select id="<?php echo $this->get_field_id( 'image_size' ); ?>" name="<?php echo $this->get_field_name( 'image_size' ); ?>">
        <?php 
        $sizes = clearbase_get_image_sizes();
        foreach ( (array) $sizes as $name => $size ) : ?>
          <option value="<?php echo $name; ?>" <?php selected($image_size, $name) ?>>
            <?php echo (esc_html( $name ) . ' (' . absint( $size['width'] ) . 'x' . absint( $size['height'] ) . ')'); ?>
          </option>
        <?php endforeach; ?>
      </select> 
    </p>
    <p>
      <label for="<?php echo $this->get_field_id( 'link_to' ); ?>"><?php _e( 'Link To:', 'cb_recent_imgs' ); ?> </label>
       <select id="<?php echo $this->get_field_id( 'link_to' ); ?>" name="<?php echo $this->get_field_name( 'link_to' ); ?>">
        <?php 
        $arrLinkTo = array(
          'none'   => __( 'None', 'cb_recent_imgs' ), 
          'parent' => __( 'Parent Folder', 'cb_recent_imgs'),
          'file'   => __( 'Media File', 'cb_recent_imgs' ),
          'attachment_page' => __( 'Attachment Page', 'cb_recent_imgs' )
        );
        foreach ($arrLinkTo as $name => $display ) : ?>
          <option value="<?php echo $name; ?>" <?php selected($link_to, $name); ?>>
            <?php echo $display; ?>
          </option>
        <?php endforeach; ?>
      </select> 
    </p>

  <?php
  }

  function update( $new_instance, $old_instance ) {
      $instance = $old_instance;
      $new_instance = wp_parse_args($new_instance);
      $instance['title'] = strip_tags( $new_instance['title'] );
      $instance['folders'] = $new_instance['folders'];
      $instance['image_size'] = $new_instance['image_size'];
      $instance['columns'] = $new_instance['columns'];
      $instance['rows'] = $new_instance['rows'];
      $instance['link_to'] = $new_instance['link_to'];
      return $instance;
  }

}