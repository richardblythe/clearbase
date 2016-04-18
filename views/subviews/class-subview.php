<?php
require_once (CLEARBASE_DIR . '/views/class-view.php');
class Clearbase_Subview extends Clearbase_View {

      public function __construct($fields = array()) {
        parent::__construct($fields);
      }
}