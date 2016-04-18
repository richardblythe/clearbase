<?php
/*
  Plugin Name: Clearbase Flex Slider
  Description: A ClearBase responsive slider
  Version: 1.0.0
  Author: Richard Blythe
  Author URI: http://unity3software.com/richardblythe
 */

define( 'FLEX_SLIDER_VERSION', '1.0.0' );

function Clearbase_FlexSlider_Load() {
    add_image_size('flexslider-default', 1140, 460, true);

    class Clearbase_FlexSlider extends Clearbase_View_Controller {
       
        public function ID() {
            return 'clearbase-flexslider';
        }

        public function Title() {
            return __('Flex Slider', 'clearbase_flexslider');
        } 
        

        public function FolderSettings($folder = null) {
            return array(
                //Specifies if child folders are shown
                'allow_folders'       => false,
                'media_filter' => 'image'
            );
        }

        public function Init() {       
           /** init the FlexSlider widget **/
            add_action( 'widgets_init', array(&$this, 'register_widget'));
            add_filter('clearbase_image_editor_fields', array(&$this, 'image_editor_fields'));
        }
        
        public function register_widget() {
            register_widget( 'clearbase_flexslider_widget' );
        }
        
        public function image_editor_fields($fields = array()) {
            if ($this->IsCurrentController()) {
                //insert the url field before the last field, which is a section close
                array_splice( $fields, count($fields) - 1, 0, array(
                    array(
                        'id'        => 'postmeta.flexslider_url',
                        'title'     => __( "Flexslider Url", 'clearbase_flexslider' ),
                        'desc'      => __( "Specifies a target url for this slide", 'clearbase' ),
                        'type'  => 'text',
                        'css'   => 'min-width:300px;'
                    )
                ));
            }
            return $fields;
        }

        public function Enqueue() {
            $this->register_script('clearbase_flexslider', plugins_url('jquery.flexslider-min.js', __FILE__), array( 'jquery' ), FLEX_SLIDER_VERSION);
            $this->register_style( 'clearbase_flexslider_style', plugins_url('flexslider.css', __FILE__), array(), FLEX_SLIDER_VERSION );
        }

        public function Render($data = null) {
            $this->enqueue_registered();
            $folder = get_post($data);

            if (!isset($folder) || 'clearbase_folder' != $folder->post_type) {
                echo '<p class="error">' . __('FlexSlider: You must specify a valid clearbase folder', 'clearbase_flexslider') . '</p>';
                return false;
            }

            //$folder_settings = clearbase_get_folder_settings($post->ID);
            //$filter = clearbase_get_value('media_filter', 'image', $folder_settings);

            $query = clearbase_query_attachments('image', $folder);
            $settings = clearbase_get_value('postmeta.clearbase_flexslider', null, $folder);

            $classes = apply_filters('clearbase_flexslider_classes', 'flexslider clearbase-flexslider');
            $image_size = clearbase_get_value('image_size', 'flexslider-default', $settings);
            $show_title = clearbase_get_value('show_title', true, $settings);
            $show_caption = clearbase_get_value('show_caption', true, $settings);
            ?>

            <div id="<?php echo "clearbase-flexslider-{$folder->ID}" ?>" class="<?php echo $classes ?>">            
                <ul class="slides" style="visibility: hidden;">
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <li>
                        <div class="aspect-ratio">
                        <div class="content">
                        <?php if ('' != clearbase_get_value('postmeta.flexslider_url', '')) : ?>
                            <a href="<?php echo clearbase_get_value('postmeta.flexslider_url', ''); ?>" rel="bookmark">
                                <?php echo wp_get_attachment_image( get_the_ID(), $image_size); ?>
                            </a>
                        <?php else: ?>
                            <?php echo wp_get_attachment_image( get_the_ID(), $image_size); ?>
                        <?php endif; ?>
                        </div><!-- end .content -->
                        </div><!-- end .aspect-ratio -->
                        <?php if ( $show_title && $show_caption ) : ?>
                            <div class="slide-excerpt slide-<?php the_ID(); ?>">
                            <div class="slide-excerpt-inner">
                            <?php if ( $show_title ) : ?>
                                <h2>
                                    <?php echo esc_html(get_the_title()); ?>
                                </h2>
                            <?php endif; ?>
                            <?php if ( $show_caption ) : ?>
                                <p>
                                    <?php echo esc_html(get_the_excerpt()); ?>
                                </p>
                            <?php endif; ?>

                            </div><!-- end .slide-excerpt-inner  -->
                            </div><!-- end .slide-excerpt  -->
                        <?php endif; ?>
                    </li>
                    <?php endwhile; ?>
                </ul><!-- end ul.slides -->
            </div><!-- end .flexslider -->
            
            <?php

            $output = 
            'jQuery(document).ready(function($) {
                $("#clearbase-flexslider-' . $folder->ID .'").flexslider({
                    animation: "' . esc_js( clearbase_get_value('animation', 'slide', $settings) ) . '",
                    animationDuration: ' . clearbase_get_value('animationDuration', 800, $settings) . ',
                    directionNav: ' . ('yes' == clearbase_get_value('directionNav', 'yes', $settings) ? 'true' : 'false') . ',
                    controlNav: ' . ('yes' == clearbase_get_value('controlNav', 'yes', $settings) ? 'true' : 'false') . ',
                    slideshowSpeed: ' . clearbase_get_value('slideshowSpeed', 4000, $settings) . '
                }).find("ul.slides").css("visibility", "visible");
              });';

            $output = str_replace( array( "\n", "\t", "\r" ), '', $output );
            echo '<script type=\'text/javascript\'>' . $output . '</script>';
        }

        public function EditorFields() {
            $sizes = clearbase_get_image_sizes();
            foreach ( (array) $sizes as $name => $size )
               $sizes[$name] = (esc_html( $name ) . ' (' . absint( $size['width'] ) . 'x' . absint( $size['height'] ) . ')');

            return array( 
                array(
                    'id'        => 'clearbase_flexslider',
                    'type'      => 'sectionstart'
                ),
                array(
                    'id'        => 'postmeta.clearbase_flexslider.image_size', 
                    'title'     => __( "Image Size", 'clearbase_flexslider' ),
                    'desc'      => __( "Specifies the size of the slide image", 'clearbase_flexslider' ),
                    'type'      => 'select',
                    'options'   => $sizes,
                    'default'   => 'flexslider-default'
                ),
                array(
                    'id'        => 'postmeta.clearbase_flexslider.show_title', 
                    'title'     => __( "Show Title", 'clearbase_flexslider' ),
                    'desc'      => __( "Specifies slide titles are shown", 'clearbase_flexslider' ),
                    'type'      => 'checkbox',
                    'default'   => 'yes'
                ),
                array(
                    'id'        => 'postmeta.clearbase_flexslider.show_caption', 
                    'title'     => __( "Show Caption", 'clearbase_flexslider' ),
                    'desc'      => __( "Specifies slide captions are shown", 'clearbase_flexslider' ),
                    'type'      => 'checkbox',
                    'default'   => 'yes'
                ),
                array(
                    'id'        => 'postmeta.clearbase_flexslider.slideshowSpeed', 
                    'title'     => __( "Slide Speed", 'clearbase_flexslider' ),
                    'desc'      => __( "Specifies the time that each slide is shown", 'clearbase_flexslider' ),
                    'type'      => 'number',
                    'default'   => 4000
                ),
                array(
                    'id'        => 'postmeta.clearbase_flexslider.animationDuration', 
                    'title'     => __( "Animation Speed", 'clearbase_flexslider' ),
                    'desc'      => __( "Specifies the slide show animation speed", 'clearbase_flexslider' ),
                    'type'      => 'number',
                    'default'   => 800
                ),
                array(
                    'id'        => 'postmeta.clearbase_flexslider.animation', 
                    'title'     => __( "Effect", 'clearbase_flexslider' ),
                    'desc'      => __( "Specifies the transition effect", 'clearbase_flexslider' ),
                    'type'      => 'select',
                    'options'   => array('slide' => __('Slide', 'clearbase_flexslider'), 'fade' => __('Fade', 'clearbase_flexslider')),
                    'default'   => 'slide'
                ),
                array(
                    'id'        => 'postmeta.clearbase_flexslider.controlNav', 
                    'title'     => __( "Pager", 'clearbase_flexslider' ),
                    'desc'      => __( "Specifies is paging is shown", 'clearbase_flexslider' ),
                    'type'      => 'checkbox',
                    'default'   => 'yes'
                ),
                array(
                    'id'        => 'postmeta.clearbase_flexslider.directionNav', 
                    'title'     => __( "Arrows", 'clearbase_flexslider' ),
                    'desc'      => __( "Specifies if navigation arrows are shown", 'clearbase_flexslider' ),
                    'type'      => 'checkbox',
                    'default'   => 'yes'
                ),
                // array(
                //     'id'        => 'postmeta.clearbase_flexslider.maxWidth', 
                //     'title'     => __( "Max Width", 'clearbase_flexslider' ),
                //     'desc'      => __( "Specifies the maximum width of the slider", 'clearbase_flexslider' ),
                //     'type'      => 'text'
                // ),
                // array(
                //     'id'        => 'postmeta.clearbase_flexslider.maxHeight', 
                //     'title'     => __( "Max Height", 'clearbase_flexslider' ),
                //     'desc'      => __( "Specifies the maximum height of the slider", 'clearbase_flexslider' ),
                //     'type'      => 'text',
                // ),

                array(
                    'id'        => 'clearbase_flexslider',
                    'type'      => 'sectionend'
                )
            );
        }
      
    }
    //run the class code
    new ClearBase_FlexSlider();
}
add_action('clearbase_loaded', 'Clearbase_FlexSlider_Load');
/**
 * Slideshow Widget Class
 */
class clearbase_flexslider_widget extends WP_Widget {

    public function __construct() {
        $widget_ops = array( 'classname' => 'clearbase-flexslider', 'description' => __( 'Displays a clearbase slideshow inside a widget area', 'clearbase_flexslider' ) );
        $control_ops = array( 'width' => 200, 'height' => 250, 'id_base' => 'clearbase-flexslider-widget' );
        parent::__construct( 'clearbase-flexslider-widget', 
            __( 'Clearbase Flex Slider', 'clearbase_flexslider' ), 
            $widget_ops, 
            $control_ops 
        );
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

        $folder_id = $instance['folder_id'];
        $controller = (empty($folder_id) || '-1' == $folder_id) ? null : clearbase_get_controller($folder_id);

        if (!$controller)
            echo '<h3>' . __('No FlexSlider Is Selected') . '</h3>';
        else 
            $controller->Render($folder_id);

        echo $after_widget;
    }

    /** Widget options */
    function form( $instance ) {
        $instance = wp_parse_args( (array) $instance);
        $title = $instance['title'];
    ?>
    <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'clearbase_flexslider' ); ?> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
        </label>
    </p>
        
    <?php
        //Get all clearbase galleries
        $posts = clearbase_folders_with_controller('clearbase-flexslider');
        $selected_post = null;
    ?>
    
    <div class="widget-column">
        <p>     
            <label for="<?php echo $this->get_field_id( 'folder_id' ); ?>"><?php _e( 'Flex Sliders', 'clearbase_flexslider' ); ?>:</label>
            <select id="<?php echo $this->get_field_id( 'folder_id' ); ?>" name="<?php echo $this->get_field_name( 'folder_id' ); ?>">
                <option value="-1"><?php echo __('No Slider Specified', 'clearbase_flexslider') ?></option>
                <?php foreach ($posts as $post) {
                    if (!$selected_post && $post->ID == $instance['folder_id']) {
                        $selected_post = $post;
                    }
                ?>
                <option value="<?php echo $post->ID; ?>" <?php selected( $post->ID, $instance['folder_id']); ?>><?php echo esc_html($post->post_title); ?></option>
                <?php } ?>
            </select>
        </p>
    </div>

<?php



    }

    function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $new_instance = wp_parse_args($new_instance);
        $instance['title'] = strip_tags( $new_instance['title'] );
        $instance['folder_id'] = $new_instance['folder_id'];

        return $instance;
    }

}