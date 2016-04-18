<?php
require_once (CLEARBASE_DIR . '/views/class-view.php');
class Clearbase_View_Collection extends Clearbase_View {
    var $subviews, $current_view;

    public function __construct($subviews = array()) {
        parent::__construct();
        global $cb_post_id, $cb_post;
        
        $this->subviews = $subviews;
        $this->subviews = apply_filters("clearbase_{$this->ID()}_subviews", $this->subviews, $cb_post);
        //
        $subview_id = clearbase_empty_default($_REQUEST, 'subview', '');
        //handle the scenario where no subview is specified...
        if (empty($subview_id)) {
            $this->current_view = reset($this->subviews);
        } else {
            foreach ($this->subviews as $subview) {
                if ($subview->ID() == $subview_id) {
                    $this->current_view = $subview;
                    break;
                }
            }
        }

        if (isset($this->current_view))
            $this->current_view->InitEditor();
    }

    public function RenderEditor() {
        global $cb_post_id;
        echo "<ul class=\"{$this->ID()} subviews\">";
            $i = 0;
            foreach ($this->subviews as $subview) {
                echo 
                '<li>
                    '. ($i == 0 ? '' : '&nbsp|&nbsp') . '
                    <a 
                        class="' . ($this->current_view->ID() == $subview->ID() ? 'current' : '') .'"
                        href="' .  ($this->current_view->ID() == $subview->ID() ? '#' : 
                            clearbase_workspace_url(array(
                                'id'   => $cb_post_id,
                                'cbaction' => $_REQUEST['cbaction'], 
                                'subview' => $subview->ID()))) . '">' . 
                            $subview->Title() . 
                    '</a>
                </li>';
                $i++; 
            }
        echo '</ul>';

        echo "<div class=\"{$this->ID()} subview-content\">";
        $this->current_view->RenderEditor();
        echo '</div>';
        
    }
}
