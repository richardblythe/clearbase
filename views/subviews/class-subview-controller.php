<?php
require_once (CLEARBASE_DIR . '/views/subviews/class-subview.php');
class Clearbase_Subview_Controller extends Clearbase_Subview {
    public function ID() {
        return 'controller';
    }
    
    public function Title() {
        return __('Controller', 'clearbase');
    }
    
    public function __construct() {

        $controllers = clearbase_get_controllers();

        $select_options = array();
        foreach ( $controllers as $key => $controller ) {
          $select_options[$key] = $controller->Title();
        }
        asort($select_options);
        $select_options = array('-1' => __('No controller specified', 'clearbase')) + $select_options;

        parent::__construct(array( 
            array(
                'id'        => 'controller',
                'type'      => 'sectionstart'
            ),
            array(
                'id'        => 'postmeta.clearbase_controller',
                'class'     => 'clearbase-controller-switcher', 
                'title'     => __( "Controller", 'clearbase' ),
                'desc'      => __( "Specifies a controller for this folder", 'clearbase' ),
                'type'      => 'select',
                'options'   => $select_options
            ),
            array(
                'id'        => 'controller',
                'type'      => 'sectionend'
            )
        ));
    }

    public function InitEditor() {
        parent::InitEditor();
        $controller = clearbase_get_controller();
        if ($controller instanceof Clearbase_View_Controller)
          $controller->InitEditor();
    }

    public function RenderEditor() {
        parent::RenderEditor();

        echo '<div id="cb_controller_editor_fields">';

        $controller = clearbase_get_controller();
        if ($controller instanceof Clearbase_View_Controller) {
          $controller->RenderEditor();
        }

        echo '<div class="waiting-overlay"></div></div>';
    }
}

