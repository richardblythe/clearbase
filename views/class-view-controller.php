<?php
require_once (CLEARBASE_DIR . '/views/class-view.php');
class Clearbase_View_Controller extends Clearbase_View {
    protected $_registered_scripts, $_registered_styles, $_footer_registered;
    public function __construct($fields = array()) {
        parent::__construct($fields);

        add_filter('clearbase_load_controllers', array($this, '_attach_controller'));
    }

    public function _attach_controller($controllers = array()) {
        $controllers[$this->ID()] = $this;
        return $controllers;
    }

    public function FolderSettings($folder = null) {
        return null;
        /**********************************
         * Possible values that can be set
         **********************************
        return array(
            //Specifies if child folders are shown
            'child_folders'       => true,

            //Specifies if child folders are allowed to create child folders of their own
            //(Defaults to: 'show_nesting') 
            'allow_child_nesting' => true, 

            'media_filter' => 'image'
        );
        */
    }

    public function IsCurrentController() {
        return $this->ID() == clearbase_get_controller_id();
    }

    protected function register_script($handle, $src, $deps = array(), $ver = false) {
        if (!isset($this->_registered_scripts))
            $this->_registered_scripts = array();

        $this->_registered_scripts[$handle] = true;
        wp_register_script($handle, $src, $deps, $ver, true );
    }

    protected function register_style( $handle, $src, $deps = array(), $ver = false, $media = 'all' ) {
        if (!isset($this->_registered_styles))
            $this->_registered_styles = array();

        $this->_registered_styles[$handle] = true;
        wp_register_style( $handle, $src, $deps, $ver, $media);
    }


    protected function enqueue_registered() {
        if (!isset($this->_footer_registered)) {
            add_action('wp_footer', array(&$this, '_wp_footer'));
            $this->_footer_registered = true;
        }
    }


    public function _wp_footer() {
        if (isset($this->_registered_scripts)) {
            foreach ($this->_registered_scripts as $handle => $value) {
                wp_enqueue_script($handle);
            }
        }

        if (isset($this->_registered_styles)) {
            foreach ($this->_registered_styles as $handle => $value) {
                wp_enqueue_style($handle);
            }
        }
    }
}
