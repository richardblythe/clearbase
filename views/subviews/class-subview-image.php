<?php
require_once (CLEARBASE_DIR . '/views/subviews/class-subview.php');
class Clearbase_Subview_Image extends Clearbase_Subview {
    public function ID() {
        return 'image';
    }
    
    public function Title() {
        return __('Properties', 'clearbase');
    }
    
    public function __construct($fields = array()) {

        parent::__construct(array_merge(array( 
                array(
                    'id'        => 'image',
                    'type'      => 'sectionstart'
                ),
                array(
                    'title'     => __( "Image", 'clearbase' ),
                    'type'      => 'html',
                    'render'    => array(&$this, RenderImage)
                ),
                array(
                    'id'        => 'post.post_title',
                    'type'      => 'post_title'
                ),
                array(
                    'id'        => 'post.post_excerpt',
                    'title'     => __( "Caption", 'clearbase' ),
                    'desc'      => __( "Specifies the image caption", 'clearbase' ),
                    'type'  => 'textarea',
                    'css'   => 'min-width:300px;'
                ),
                array(
                    'id'        => 'image',
                    'type'      => 'sectionend'
                )
            ),
            $fields)
        );
    }

    public function RenderImage() {
        global $cb_post_id;
        $thumb_url = wp_get_attachment_image_src( $cb_post_id, array( 900, 450 ), true );
    ?>
        <div class="wp_attachment_holder">
            <div class="imgedit-response" id="imgedit-response-<?php echo $cb_post_id ?>"></div>
            <div class="wp_attachment_image" id="media-head-<?php echo $cb_post_id ?>">
                <p id="thumbnail-head-<?php echo $cb_post_id ?>">
                    <img class="thumbnail" src="<?php echo set_url_scheme( $thumb_url[0] ); ?>" style="max-width:100%" alt="">
                </p>
                <?php /*
                <p>
                    <input id="imgedit-open-btn-<?php echo $cb_post_id ?>" 
                    onclick="imageEdit.open(<?php echo $cb_post_id ?>, &quot;bfcc5f781b&quot; )" 
                    class="button"
                    value="<?php echo __('Edit Image', 'clearbase') ?>" 
                    type="button"> <span class="spinner"></span>
                </p>
                */?>
            </div>
            <div style="display:none" class="image-editor" id="image-editor-<?php echo $cb_post_id ?>"></div>
        </div>

    <?php
    }
}