<?php

add_shortcode('clearbase', 'clearbase_short_code_handler');

function clearbase_short_code_handler($atts) {
    $folder = clearbase_load_folder($atts['id']);
    if (is_wp_error($folder))
        return;

    ob_start();
    $controller = clearbase_get_controller($folder);
    if ($controller) {
        $controller->Render($folder);
    } else {
        //TODO: clearbase_default_render($folder);
    }

    $output_string = ob_get_contents();
    ob_end_clean();
    return $output_string;
}