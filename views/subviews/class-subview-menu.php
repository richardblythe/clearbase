<?php
require_once (CLEARBASE_DIR . '/views/subviews/class-subview.php');
class Clearbase_Subview_Menu extends Clearbase_Subview {
    public function ID() {
        return 'menu';
    }
    
    public function Title() {
        return __('Menu', 'clearbase');
    }
    
    public function __construct($fields = array()) {
        parent::__construct(array_merge(array( 
                array(
                    'id'        => 'menu',
                    'type'      => 'sectionstart',
                    'title'     => __("Menu Settings")
                ),
                array(
                    'id'        => 'postmeta.menu.show', 
                    'title'     => __( "Show Menu", 'clearbase' ),
                    'desc'      => __( "If checked, creates a custom Wordpress Admin menu", 'clearbase' ),
                    'type' 	=> 'checkbox',
                    'default'   => 'no'
                ),
                array(
                    'id'        => 'postmeta.menu.title',
                    'title'     => __( "Menu Title", 'clearbase' ),
                    'desc'      => __( "Specifies the title for the menu", 'clearbase' ),
                    'type' 	=> 'text',
                    'css' 	=> 'min-width:300px;',
                ),
                array(
                    'id'        => 'postmeta.menu.page_title',
                    'title'     => __( "Page Title", 'clearbase' ),
                    'desc'      => __( "Specifies the page title for the editing screen", 'clearbase' ),
                    'type' 	=> 'text',
                    'css' 	=> 'min-width:300px;',
                ),
                array(
                    'id'        => 'postmeta.menu.position',
                    'title'     => __( "Menu Position", 'clearbase' ),
                    'desc'      => __( "Specifies the menu position", 'clearbase' ),
                    'type' 	=> 'text',
                    'css' 	=> 'min-width:300px;',
                    'default'   => '21'
                ),
                array(
                    'id'        => 'postmeta.menu.icon',
                    'title'     => __( "Icon", 'clearbase' ),
                    'desc'      => __( "Specifies the menu icon.  Accepts urls or dashicons", 'clearbase' ),
                    'type' 	=> 'text',
                    'css' 	=> 'min-width:300px;',
                ),
                array(
                    'id'        => 'postmeta.menu.capability',
                    'title'     => __( "Capability", 'clearbase' ),
                    'desc'      => __( "Specifies the user capability", 'clearbase' ),
                    'type' 	=> 'text',
                    'css' 	=> 'min-width:300px;',
                    'default'   => 'edit_posts'
                ),
                array(
                    'id'        => 'menu',
                    'type'      => 'sectionend'
                )
            ), 
            $fields)
        );
    }
}

