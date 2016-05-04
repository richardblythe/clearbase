<?php
require_once (CLEARBASE_DIR . '/views/subviews/class-subview.php');
class Clearbase_Subview_Folder extends Clearbase_Subview {
    public function ID() {
        return 'folder-properties';
    }
    
    public function Title() {
        return __('Properties', 'clearbase');
    }
    
    public function __construct($fields = array()) {
        global $cb_post, $cb_post_type_obj;
        $labels = $cb_post_type_obj->labels;
        
        $pages = get_pages();
        $opt_pages = array(0 => 'Select a page');
        foreach ($pages as $page) {
            $opt_pages[$page->ID] = $page->post_title;
        }

        parent::__construct(array_merge(array( 
                array(
                    'id'        => 'folder',
                    'type'      => 'sectionstart'
                ),
                array(
                    'id'        => 'post.post_title',
                    'type'      => 'post_title'
                ),
                array(
                    'id'        => 'post.post_content',
                    'title'     => __( "Description", 'clearbase' ),
                    'desc'      => __( "Specifies the {$labels->singular_name} description", 'clearbase' ),
                    'type' 	=> 'textarea',
                    'css' 	=> 'min-width:300px;'
                ),
                array(
                    'id'            => 'postmeta.page_append',
                    'title'         => __( "Append To Page", 'clearbase' ),
                    'desc'          => __( "Specifies a page to append the {$labels->singular_name}", 'clearbase' ),
                    'type'          => 'select',
                    'options'       => $opt_pages,
                    'default'       => '0',
                    'capability'    => 'manage_options'
                ),
                array(
                    'id'        => 'folder',
                    'type'      => 'sectionend'
                )
            ),
            $fields)
        );
    }
}