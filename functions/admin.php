<?php

function clearbase_init_menus() {
    
    $page =  clearbase_empty_default($_GET, 'page', '');
    $folder_id = 0;
    if (0 === strrpos($page, 'cbfolder')) {
        $folder_id = absint(substr(strrchr($page, "_"), 1));
        $page = 'cbfolder';
    }

    global $cb_admin_view;
    switch ($page) {
        case 'clearbase':
        case 'cbfolder':
          require_once (CLEARBASE_DIR . '/views/class-view-workspace.php');
          $cb_admin_view = new Clearbase_View_Workspace($folder_id);
          break;
        case 'clearbase-settings':
          require_once (CLEARBASE_DIR . '/views/class-view-admin-settings.php');
          $cb_admin_view = new Clearbase_View_Admin_Settings();
          break;
    }

    /*
    Render the main Clearbase menus
    */
    add_menu_page(__('Clearbase', 'clearbase'), 
                  __('Clearbase', 'clearbase'),
                  'manage_options', 'clearbase', 
                  '_clearbase_admin_render_view', 
                  'dashicons-screenoptions');
    

    //now add the sub menus
    add_submenu_page('clearbase', 
                  __('Clearbase Framework', 'clearbase'), 
                  __('Folders', 'clearbase'), 
                 'manage_options', 'clearbase',
                 '_clearbase_admin_render_view');
    
    add_submenu_page('clearbase', 
              __('Clearbase Settings', 'clearbase'), 
              __('Settings', 'clearbase'), 
             'manage_options', 'clearbase-settings',
             '_clearbase_admin_render_view');

    //now loop through all of the root clearbase folders that have menus specified
    $query = new WP_Query(array(
        'post_type' => 'clearbase_folder',
        'post_parent' => 0,
        'meta_query' => array(
            array(
             'key' => 'menu',
             'compare' => 'EXISTS' // this should work...
            ),
        )
    ));

    global $post;
    while ($query->have_posts()) : $query->the_post();
        $meta = clearbase_get_value('postmeta.menu', array());
        if ('yes' != clearbase_empty_default($meta, 'show', 'no'))
          continue;
        $menu_slug = 'cbfolder_'. get_the_ID();
        
        add_menu_page(
              clearbase_empty_default($meta, 'page_title', get_the_title()), 
              clearbase_empty_default($meta, 'title', get_the_title()), 
              clearbase_empty_default($meta, 'capability', 'edit_posts'),
              $menu_slug, 
              '_clearbase_admin_render_view', 
              clearbase_empty_default($meta, 'icon', 'dashicons-screenoptions'), 
              clearbase_empty_default($meta, 'position', 21)
        );
        //fire the parent menu
        do_action('clearbase_folder_menu', $menu_slug);
    endwhile;

}

function _clearbase_admin_render_view() {
    global $cb_admin_view;
    $cb_admin_view->Render();
}


add_action('admin_menu', 'clearbase_init_menus', 9999);
