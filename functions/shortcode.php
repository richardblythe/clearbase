<?php

add_shortcode('clearbase', 'clearbase_short_code_handler');

function clearbase_short_code_handler($atts) {
    $folder = clearbase_load_folder($atts['id']);
    if (is_wp_error($folder))
        return;

    ob_start();
    $controller = clearbase_get_controller($folder);
    if ($controller) {
        $controller->Render($folder);
    } else {
        //TODO: clearbase_default_render($folder);
    }

    $output_string = ob_get_contents();
    ob_end_clean();
    return $output_string;
}


add_shortcode('clearbase_gallery', 'clearbase_gallery_shortcode');
/**
 * Builds the Gallery shortcode output.
 *
 * This extends the WP gallery_shortcode function.
 * WordPress images on a post.
 *
 * @since 2.5.0
 *
 * @staticvar int $instance
 *
 * @param array $attr {
 *     Attributes of the gallery shortcode.
 *
 *     @type string       $order      Order of the images in the gallery. Default 'ASC'. Accepts 'ASC', 'DESC'.
 *     @type string       $orderby    The field to use when ordering the images. Default 'menu_order ID'.
 *                                    Accepts any valid SQL ORDERBY statement.
 *     @type int          $id         Post ID.
 *     @type string       $itemtag    HTML tag to use for each image in the gallery.
 *                                    Default 'dl', or 'figure' when the theme registers HTML5 gallery support.
 *     @type string       $icontag    HTML tag to use for each image's icon.
 *                                    Default 'dt', or 'div' when the theme registers HTML5 gallery support.
 *     @type string       $captiontag HTML tag to use for each image's caption.
 *                                    Default 'dd', or 'figcaption' when the theme registers HTML5 gallery support.
 *     @type int          $columns    Number of columns of images to display. Default 3.
 *     @type string|array $size       Size of the images to display. Accepts any valid image size, or an array of width
 *                                    and height values in pixels (in that order). Default 'thumbnail'.
 *     @type string       $ids        A comma-separated list of IDs of attachments to display. Default empty.
 *     @type string       $include    A comma-separated list of IDs of attachments to include. Default empty.
 *     @type string       $exclude    A comma-separated list of IDs of attachments to exclude. Default empty.
 *     @type string       $link       What to link each image to. Default empty (links to the attachment page).
 *                                    Accepts 'file', 'none'.
 * }
 * @return string HTML content to display gallery.
 */
function clearbase_gallery_shortcode( $attr ) {
    $post = get_post();

    static $instance = 0;
    $instance++;

    if ( ! empty( $attr['ids'] ) ) {
        // 'ids' is explicitly ordered, unless you specify otherwise.
        if ( empty( $attr['orderby'] ) ) {
            $attr['orderby'] = 'post__in';
        }
        $attr['include'] = $attr['ids'];
    }

    /**
     * Filter the default gallery shortcode output.
     *
     * If the filtered output isn't empty, it will be used instead of generating
     * the default gallery template.
     *
     * @since 2.5.0
     * @since 4.2.0 The `$instance` parameter was added.
     *
     * @see gallery_shortcode()
     *
     * @param string $output   The gallery output. Default empty.
     * @param array  $attr     Attributes of the gallery shortcode.
     * @param int    $instance Unique numeric ID of this gallery shortcode instance.
     */
    $output = apply_filters( 'post_gallery', '', $attr, $instance );
    if ( $output != '' ) {
        return $output;
    }

    $html5 = current_theme_supports( 'html5', 'gallery' );
    $atts = shortcode_atts( array(
        'order'       => 'ASC',
        'orderby'     => 'menu_order ID',
        'id'          => $post ? $post->ID : 0,
        //ADDED
        'class'       => '',
        'captiontext' => '', //if link is set to parent, defaults to the parent post title
        //
        'itemtag'     => $html5 ? 'figure'     : 'dl',
        'icontag'     => $html5 ? 'div'        : 'dt',
        'captiontag'  => $html5 ? 'figcaption' : 'dd',
        'columns'     => 3,
        'size'        => 'thumbnail',
        'include'     => '',
        'exclude'     => '',
        'link'        => ''
    ), $attr, 'gallery' );

    $id = intval( $atts['id'] );

    if ( ! empty( $atts['include'] ) ) {
        $_attachments = get_posts( array( 'include' => $atts['include'], 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $atts['order'], 'orderby' => $atts['orderby'] ) );

        $attachments = array();
        foreach ( $_attachments as $key => $val ) {
            $attachments[$val->ID] = $_attachments[$key];
        }
    } elseif ( ! empty( $atts['exclude'] ) ) {
        $attachments = get_children( array( 'post_parent' => $id, 'exclude' => $atts['exclude'], 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $atts['order'], 'orderby' => $atts['orderby'] ) );
    } else {
        $attachments = get_children( array( 'post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $atts['order'], 'orderby' => $atts['orderby'] ) );
    }

    if ( empty( $attachments ) ) {
        return '';
    }

    if ( is_feed() ) {
        $output = "\n";
        foreach ( $attachments as $att_id => $attachment ) {
            $output .= wp_get_attachment_link( $att_id, $atts['size'], true ) . "\n";
        }
        return $output;
    }

    $itemtag = tag_escape( $atts['itemtag'] );
    $captiontag = tag_escape( $atts['captiontag'] );
    $icontag = tag_escape( $atts['icontag'] );
    $valid_tags = wp_kses_allowed_html( 'post' );
    if ( ! isset( $valid_tags[ $itemtag ] ) ) {
        $itemtag = 'dl';
    }
    if ( ! isset( $valid_tags[ $captiontag ] ) ) {
        $captiontag = 'dd';
    }
    if ( ! isset( $valid_tags[ $icontag ] ) ) {
        $icontag = 'dt';
    }

    $columns = intval( $atts['columns'] );
    $itemwidth = $columns > 0 ? floor(100/$columns) : 100;
    $float = is_rtl() ? 'right' : 'left';

    $selector = "gallery-{$instance}";

    $gallery_style = '';

    /**
     * Filter whether to print default gallery styles.
     *
     * @since 3.1.0
     *
     * @param bool $print Whether to print default gallery styles.
     *                    Defaults to false if the theme supports HTML5 galleries.
     *                    Otherwise, defaults to true.
     */
    if ( apply_filters( 'use_default_gallery_style', ! $html5 ) ) {
        $gallery_style = "
        <style type='text/css'>
            #{$selector} {
                margin: auto;
            }
            #{$selector} .gallery-item {
                float: {$float};
                margin-top: 10px;
                text-align: center;
                width: {$itemwidth}%;
            }
            #{$selector} img {
                border: 2px solid #cfcfcf;
            }
            #{$selector} .gallery-caption {
                margin-left: 0;
            }
            /* see gallery_shortcode() in wp-includes/media.php */
        </style>\n\t\t";
    }

    /**
     * Creates a javascript style of CSS media queries.  This allows the gallery
     * to dynamically resize itself based on the client window.innerWidth
     *
     * @since 3.1.0
     *
     * @param array $columns An key/value array. key is the window max width, and value is the column count.
     *                       Defaults to null.
     */

    $gallery_script = '';
    if ( $arr_columns = apply_filters( 'clearbase_gallery_js_columns', null, $attr ) ) {

        $if_statements = '';
        $if_var = 'if';
        $arr_columns[10000] = $columns;//ensure the default column settings will be enforced as the last fallback
        ksort($arr_columns);
        foreach ($arr_columns as $width => $cols) {
            $if_statements .= "
                {$if_var} ( window.innerWidth < {$width} )
                    columns = $cols;
            ";
            if ('if' == $if_var)
                $if_var = 'else if';
        }

        $gallery_script = "
        <script type=\"text/javascript\">
            jQuery(document).ready(function($) {
                var gallery = $('#{$selector}');
                var update_columns = function() {
                    var columns = 0;
                    {$if_statements} 
                    if ( 0 != columns && !gallery.hasClass('gallery-columns-' + columns) ) {
                        gallery.removeClass (function (index, css) {
                            return (css.match (/\bgallery-columns-\S+/g) || []).join(' ');
                        }).addClass('gallery-columns-' + columns);
                    }
                }
                update_columns();
                $(window).resize(update_columns);
            }); //end jQuery(document).ready();
        </script>";
    }

    $size_class = sanitize_html_class( $atts['size'] );

    //ADDED
    $gallery_class = "gallery galleryid-{$id} gallery-columns-{$columns} gallery-size-{$size_class}";
    if (is_array($atts['class'])) {
        $gallery_class .= (' ' . implode(' ', $atts['class'] ));
    }
    else if (!empty($atts['class'])) {
        $gallery_class .= (' ' . $atts['class'] );
    }
    //END ADDED

    $gallery_div = "<div id='$selector' class='{$gallery_class}'>";

    $output = apply_filters( 'clearbase_gallery_start', array(
        'script' => $gallery_script,
        'style' => $gallery_style,
        'gallery' => $gallery_div
    ), $atts);
    //convert the array into a string
    $output = implode(' ', $output);

    $i = 0;
    foreach ( $attachments as $id => $attachment ) {

        //ADDED
        //if the link is set to parent and a parent exists, set the image caption to the parent post title
        if ( ! empty( $atts['link'] ) && 'parent' === $atts['link'] && $parent = get_post($attachment->post_parent) ) {
            $image_caption = $parent->post_title;
        } else {
            $image_caption = $attachment->post_excerpt;
        }

        $image_caption = apply_filters('gallery_image_caption', $image_caption , $attachment);
        //END ADDED

        $attr = ( trim( $image_caption ) ) ? array( 'aria-describedby' => "$selector-$id" ) : '';
        if ( ! empty( $atts['link'] ) && 'file' === $atts['link'] ) {
            $image_output = wp_get_attachment_link( $id, $atts['size'], false, false, false, $attr );
            //ADDED
        } elseif ( ! empty( $atts['link'] ) && 'parent' === $atts['link'] ) {
            $image_output = clearbase_get_attachment_parent_link( $id, $atts['size'], $attr );
            //END ADDED
        } elseif ( ! empty( $atts['link'] ) && 'none' === $atts['link'] ) {
            $image_output = wp_get_attachment_image( $id, $atts['size'], false, $attr );
        } else {
            $image_output = wp_get_attachment_link( $id, $atts['size'], true, false, false, $attr );
        }
        $image_meta  = wp_get_attachment_metadata( $id );

        $orientation = '';
        if ( isset( $image_meta['height'], $image_meta['width'] ) ) {
            $orientation = ( $image_meta['height'] > $image_meta['width'] ) ? 'portrait' : 'landscape';
        }
        $output .= "<{$itemtag} class='gallery-item'>";
        $output .= "
            <{$icontag} class='gallery-icon {$orientation}'>
                $image_output
            </{$icontag}>";
        if ( $captiontag && trim($image_caption) ) {
            $output .= "
                <{$captiontag} class='wp-caption-text gallery-caption' id='$selector-$id'>
                " . wptexturize($image_caption) . "
                </{$captiontag}>";
        }
        $output .= "</{$itemtag}>";
        if ( ! $html5 && $columns > 0 && ++$i % $columns == 0 ) {
            $output .= '<br style="clear: both" />';
        }
    }

    if ( ! $html5 && $columns > 0 && $i % $columns !== 0 ) {
        $output .= "
            <br style='clear: both' />";
    }

    $output .= "
        </div>\n";

    return $output;
}