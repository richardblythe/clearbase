<?php
require_once (CLEARBASE_DIR . '/views/class-view.php');
class Clearbase_View_Admin_Settings extends Clearbase_View {
    public function ID() {
        return 'clearbase-settings';
    }
    
    public function Title() {
        return __('Clearbase Settings', 'clearbase');
    }
    
    public function __construct($fields = array()) {
        parent::__construct(array_merge(array( 
                array(
                    'id'        => 'settings',
                    'type'      => 'sectionstart'
                ),
                array(
                    'id'        => 'option.clearbase.media.exclude', 
                    'title'     => __( "Hide Attachments", 'clearbase' ),
                    'desc'      => __( "Hides clearbase attachments from the media library", 'clearbase' ),
                    'type'  => 'checkbox',
                    'css'   => 'min-width:300px;',
                    'default' => 'yes'
                ),
                array(
                    'id'        => 'option.clearbase.description',
                    'title'     => __( "Description", 'clearbase' ),
                    'desc'      => __( "Describes the awesomeness of clearbase", 'clearbase' ),
                    'type'  => 'textarea',
                    'css'   => 'min-width:300px;'
                ),
                array(
                    'id'        => 'settings',
                    'type'      => 'sectionend'
                )
            ),
            $fields)
        );
        
        if (isset($_POST['save-changes'])) 
            $this->Save();
    }

    public function Enqueue() {
        wp_enqueue_style('clearbase-admin-css', CLEARBASE_URL . '/css/style.css');
    }
    public function Render() {

        echo '<div class="wrap">';
        echo '<form id="'. $this->ID() .' action="" method="post">';
        $header = array();
        //Workspace Title
        $header['title'] = $this->Title();
        // $text = '', $type = 'primary large', $name = 'submit', $wrap = true, $other_attributes = '' 
        $header['save'] = get_submit_button(
            __('Save Changes', 'clearbase'),
            'primary large',
            'save-changes', 
            false,
            'class="button-primary"');

        //Allow for custom header handling
        $header = apply_filters('clearbase_admin_header', $header);
        
        echo '<h2 class="workspace-h2">';
            echo "<ul class='header'>\n";
            foreach ( $header as $id => $item ) {
              echo "<li class='header-item $id'>$item</li>";
            }
            echo "</ul>";
        echo '</h2>';
        // End Render Header

        //render the settings fields
        parent::Render();

        echo '</form></div>';
    }
}