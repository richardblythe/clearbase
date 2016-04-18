<?php
require_once (CLEARBASE_DIR . '/views/class-view-collection.php');
class Clearbase_View_Folder_Properties extends Clearbase_View_Collection {

    public function ID() {
        return 'folder-properties';
    }
    
    
    public function Title() {
        global $cb_post;
        return isset($cb_post) ?  "Edit {$cb_post->post_title}" : $this->ID();
    }
    
    public function __construct() {
        global $cb_post;

        require_once (CLEARBASE_DIR . '/views/subviews/class-subview-folder.php');
        $subview_folder = new Clearbase_Subview_Folder();
        $subviews = array($subview_folder->ID() => $subview_folder);

        if ($cb_post->post_parent === 0) {
            require_once (CLEARBASE_DIR . '/views/subviews/class-subview-controller.php');
            require_once (CLEARBASE_DIR . '/views/subviews/class-subview-menu.php');
            $subview_controller = new Clearbase_Subview_Controller();
            $subviews[$subview_controller->ID()] = $subview_controller;
            //
            $subview_menu = new Clearbase_Subview_Menu();
            $subviews[$subview_menu->ID()] = $subview_menu;
        }

        parent::__construct($subviews);
    }

    public function Header($header) {
        global $cb_post;
        if (isset($header['back']) && !isset($_REQUEST['back'])) {
            $header['back'] = '<a class="button-secondary" href="'. 
                clearbase_workspace_url(array('id' => $cb_post->ID )).'">' . 
                __('Back', 'clearbase') . '</a>';
        }
        $header['title'] = '<span class="edit">' . __('Edit', 'clearbase') . '</span>&nbsp' . $cb_post->post_title;
        $header['save'] = '<input name="save-editor" type="submit" class="button-primary" value="'. __('Save Changes', 'clearbase') .'"/>';
        return $header;
    }

    public function SaveEditor() {
        parent::SaveEditor();
        global $cb_post;
        if ('auto-draft' === $cb_post->post_status) {
            wp_update_post(array(
                'ID'          => $cb_post->ID,
                'post_status' => 'publish'
            ));
        }
    }

}
