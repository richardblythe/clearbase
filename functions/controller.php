<?php
/**
* Gets a Clearbase controller
*
* @param string $selector
* @return mixed|Clearbase_View_Controller|null
*/
function clearbase_get_controller($selector = '', $traverse = true) {
    $controllers = clearbase_get_controllers();
    
    if (empty($selector) || is_numeric($selector) || $selector instanceof WP_Post) {
        //attempt to load a controller from a folder;
        $folder = clearbase_load_folder($selector);
        $controller_id = clearbase_get_value('postmeta.clearbase_controller', '', $folder);
        while ($traverse && empty($id) && !is_wp_error($folder) && 0 != $folder->post_parent) {
            $folder = clearbase_load_folder($folder->post_parent);
            $controller_id = clearbase_get_value('postmeta.clearbase_controller', '', $folder);
        }
        $selector = $controller_id;
    }

    $controller = isset($controllers[$selector]) ? $controllers[$selector] : null;
    
    return apply_filters('clearbase_get_controller', $controller, $selector);
}

function clearbase_get_controller_id($folder_id = null) {
    $controller = clearbase_get_controller($folder_id);
    return isset($controller) ? $controller->ID() : null;
}

/**
* Gets an array of Clearbase controllers
*
* @return array
*/
function clearbase_get_controllers() {         
    global $clearbase_controllers;
    if ( !isset($clearbase_controllers) ) {
        $clearbase_controllers = array();
        $clearbase_controllers = apply_filters('clearbase_load_controllers', $clearbase_controllers);
    }
    
    return $clearbase_controllers;
}