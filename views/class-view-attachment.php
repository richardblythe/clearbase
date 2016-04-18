<?php
require_once (CLEARBASE_DIR . '/views/class-view-collection.php');
class Clearbase_View_Attachment extends Clearbase_View_Collection {

    public function ID() {
        return 'attachment-edit';
    }

    public function Title() {
        global $cb_post;
        return isset($cb_post) ?  "Edit {$cb_post->post_title}" : $this->ID();
    }
    
    public function __construct() {
        global $cb_post_id;
        
        $subviews = array();

        if (wp_attachment_is('image', $cb_post_id)) {
            require_once (CLEARBASE_DIR . '/views/subviews/class-subview-image.php');
            $subview_image = new Clearbase_Subview_Image();
            $subviews[$subview_image->ID()] = $subview_image;
        } else {
            //TODO implement specific subviews for other attachment types
            require_once (CLEARBASE_DIR . '/views/subviews/class-subview-post.php');
            $subview_post = new Clearbase_Subview_Post();
            $subviews[$subview_post->ID()] = $subview_post;
        }

        parent::__construct($subviews);
    }

    public function Header($header) {
        global $cb_post;
        $header['title'] = '<span class="edit">' . __('Edit', 'clearbase') . '</span>&nbsp' . $cb_post->post_title;
        $header['save'] = '<input name="save-editor" type="submit" class="button-primary" value="'. __('Save Changes', 'clearbase') .'"/>';
        return $header;
    }

    public function Save() {
        parent::Save();
        global $cb_post;
        if ('auto-draft' === $cb_post->post_status) {
            wp_update_post(array(
                'ID'          => $cb_post->ID,
                'post_status' => 'publish'
            ));
        }
    }
}
