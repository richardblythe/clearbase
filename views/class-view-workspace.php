<?php
require_once (CLEARBASE_DIR . '/views/class-view.php');
class Clearbase_View_Workspace extends Clearbase_View {
    protected $breadcrumbs, $view;
    
    public function ID() {
        return 'clearbase-workspace';
    }
    
    public function PageTitle($admin_title) {
        return $admin_title;
    }

    public function __construct($folder_root = 0) {
        parent::__construct();

        global $cb_folder_root, $cb_post_id, $cb_post, $cb_post_type_obj, 
               $view_id, $cb_action, $cb_workspace_saving;
        $cb_folder_root = $folder_root;
        clearbase_folder_set_global(clearbase_empty_default($_GET, 'id', 0));

        add_action( 'post_updated', array(&$this, '_updated'), 10, 1 );
        add_action( 'edit_attachment', array(&$this, '_updated'), 10, 1);

        $cb_action = isset($_REQUEST['cbaction']) && -1 != $_REQUEST['cbaction'] ? $_REQUEST['cbaction'] : '';
        $cb_workspace_saving = isset($_POST['save-changes']);
        //
        do_action('clearbase_workspace_start');

        add_filter('clearbase_workspace_view', array($this, 'view'), 10, 3);
        $this->view = apply_filters('clearbase_workspace_view', null, $cb_post->post_type, $cb_action);
        if ($this->view instanceof Clearbase_View)
            $this->view->InitEditor();

        do_action('clearbase_workspace_loaded');
        
        //allow the loaded views to save any editor changes
        if (isset($_REQUEST['save-editor']))
            do_action('clearbase_view_save_editor', 'save-editor');

        $args = apply_filters('clearbase_workspace_action', array(
            'action'        => $cb_action, 
            'nonce_action'  => $cb_action,
            'nonce_field'   => isset($_REQUEST['cbnonce']) ? 'cbnonce' : '_wpnonce',
            'posts'         => clearbase_empty_default($_REQUEST, 'posts', array($cb_post_id)),
            'handled'       => false
        ));

        // Process any Clearbase actions, if the action has not already been handled
        if (isset($args) && is_array($args) && !$args['handled']) {
            switch ($args['action']) {
                case 'add-folder':
                    check_admin_referer($args['nonce_action'], $args['nonce_field'] );

                    $result = clearbase_add_folder($cb_post_id);
                    if (is_wp_error($result))
                        die ($result->get_error_message());

                    wp_redirect(clearbase_workspace_url(array(
                        'id' => $result,
                        'cbaction' => 'edit',  
                        'back' => urlencode(clearbase_workspace_url(array('id' => $cb_post_id))
                    ))));
                    exit;
                    break;
                case 'move':
                    check_admin_referer($args['nonce_action'], $args['nonce_field'] );

                    $folder_id = clearbase_empty_default($_REQUEST, 'folderid', '');
                    if (!is_numeric($folder_id)) {
                        die('Folder ID is invalid.');
                    }

                    $result = clearbase_move_to_folder($folder_id, $args['posts']);
                    if (is_wp_error($result))
                        die ($result->get_error_message());

                    wp_redirect(clearbase_workspace_url(array( 
                        'id' => $folder_id
                    )));
                    exit;
                    break;
                case 'trash':
                case 'delete':
                    check_admin_referer($args['nonce_action'], $args['nonce_field'] );
                    $parent_id;

                    $posts = $args['posts'];
                    for ($i = count($posts) - 1; $i > -1; $i--) {
                        if (!isset($parent_id) && $p = get_post($posts[$i])) {
                            $parent_id = $p->post_parent;
                        }
                        wp_delete_post($posts[$i], 'delete' == $args['action'] );
                    }

                    wp_redirect(clearbase_workspace_url(array( 
                        'id' => $parent_id
                    )));

                    exit;
                    break;
            }
        }
    }

    public function _updated($post_id) {
        global $cb_post_id, $cb_message;
        if ($post_id == $cb_post_id) {
            clearbase_folder_set_global($cb_post_id);
            $cb_message = 'updated';
        }   
    }

    public function view($view, $post_type, $action) {
        
        if ('clearbase_folder' === $post_type) {
            if ('edit' === $action) {
                require_once (CLEARBASE_DIR . '/views/class-view-folder-properties.php');
                return new Clearbase_View_Folder_Properties();
            } else {
                require_once (CLEARBASE_DIR . '/views/class-view-folder.php');
                return new Clearbase_View_Folder();
            }
        } else if ('attachment' === $post_type) {
            require_once (CLEARBASE_DIR . '/views/class-view-attachment.php');
            return new Clearbase_View_Attachment();
        }

        return $view;
    }

    public function Enqueue() {
        global $cb_post;
        // wp_enqueue_script('clearbase', CLEARBASE_URL . '/js/clearbase.js');     
        //

        wp_enqueue_style('clearbase-admin-css', CLEARBASE_URL . '/css/style.css');
        wp_enqueue_style('jstree', CLEARBASE_URL . '/includes/assets/jstree/themes/default/style.min.css');

        //WP has touch-punch version 0.2.2.  This patch version is 0.2.3, which fixes the click issue
        wp_enqueue_script('jquery-touch-punch-patch', CLEARBASE_URL . '/includes/assets/jquery.ui.touch-punch.min.js', array(
            'jquery',
            'jquery-ui-core',
            'jquery-ui-mouse'
        ), '0.2.3');
        
        wp_enqueue_script('jstree', CLEARBASE_URL . '/includes/assets/jstree/jstree.min.js', array('jquery'));
        wp_enqueue_script('clearbase', CLEARBASE_URL . '/js/clearbase.js', array(
            'jquery',
            'underscore',
            'jquery-ui-core',
            'jquery-ui-draggable',
            'jquery-ui-droppable',
            'jquery-ui-sortable',
            'jquery-touch-punch-patch',
            'jstree'
        ));

         wp_localize_script('clearbase', 'clearbase', 
            apply_filters('clearbase_client', array(
                'url'          => CLEARBASE_URL,
                'workspaceUrl' => clearbase_workspace_url(),
                'cbnonce' => wp_create_nonce('clearbase_ajax'),
                'permalink_nonce' => wp_create_nonce('samplepermalink'),
                
                'l10n' => array(
                    'notice_dismiss' => __('Dismiss this notice', 'clearbase'),
                    'saveAlert'      => __('You have unsaved changes on this page. Are you sure you want to leave this page?', 'clearbase'),
                    'deleteWarning'  => __('Are you sure you want to delete this item? This action cannot be undone!', 'clearbase'),
                    'ok'             => __('OK', 'clearbase'),
                    'cancel'         => __('Cancel', 'clearbase')
                ),

                'post' => apply_filters('clearbase_client_post', array(
                    'ID' => $cb_post ? $cb_post->ID : 0,
                    'post_modified' => $cb_post ? $cb_post->post_modified : ''
                )),

                'uploader' => apply_filters('clearbase_client_uploader', array(
                    'l10n' => array(
                        'title' => __('Upload Media', 'clearbase')
                    )
                )),

                'folderTree' => apply_filters('clearbase_client_folder_tree', array(
                    'l10n' => array(
                        'title' => __('Choose Destination', 'clearbase'),
                        'button' => __('Move', 'clearbase')
                    )
                )),
            ))
        );
    }
    
    public function Render($data = null) {
        $this->RenderEditor();
    }

    public function RenderEditor() {
        global $cb_post_id, $cb_post, $cb_post_type_obj, $cb_message, $cb_render_sidebar;

        echo '<div class="wrap">';

        $cb_message = apply_filters('clearbase_workspace_message', $cb_message);
        if (!empty($cb_message)) {
            switch ($cb_message) {
                case 'updated':
                case 'saved':
                    ?>
                    <div id="message" class="<?php echo $cb_message ?> notice notice-success is-dismissible">
                        <p><?php 
                            printf( __( '%s Updated.', 'clearbase' ), $cb_post_type_obj->labels->singular_name ); 
                        ?>
                        </p>
                        <button type="button" class="notice-dismiss">
                            <span class="screen-reader-text"><?php _e('Dismiss this notice', 'clearbase'); ?></span>
                        </button>
                    </div>
                    <?php
                    break;
                default:
                    echo $cb_message;
            }
            
        }

        $attributes = apply_filters("clearbase_workspace_form_attributes", array(
            'id'        => $this->ID(),
            'method'    => 'post',
            'data-post' => $cb_post_id
        ));

        echo '<form';
        foreach ($attributes as $key => $value) {
            echo " $key=\"$value\"";
        }
        echo '>';

        $hidden = apply_filters('clearbase_client_hidden', array(
            'page' => $_REQUEST['page'],
            'id'   => $_REQUEST['id']
        ));
        
        foreach ($hidden as $k => $v) {
            echo '<input type="hidden" id="'. esc_attr($k) . 
                '" name="'. esc_attr($k) .'" value="'. esc_attr($v) .'">';
        }

        // Render Header
        $header = array('back' => '');
        if (!clearbase_is_root()) {
            $href = isset($_GET['back']) ? esc_attr($_GET['back']) : 
                clearbase_workspace_url(array('id' => $cb_post->post_parent));
            $header['back'] = '<a class="clearbase-back-button button-secondary"' . 
                ' href="'. $href .'">' . __('Back', 'clearbase') . '</a>';
        }
        //Workspace Title
        if (clearbase_is_root(false)) {
           $header['title'] = 'Clearbase Framework';
        } else {
            //'<a href="' . get_permalink( $post->ID ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $att_title ) ) . '" rel="permalink">' . __( 'View' ) . '</a>';
            $header['title'] = 
                '<a class="clearbase-header-permalink" 
                    title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $cb_post->post_title ) ) . '" 
                    rel="permalink"
                    href="' . get_permalink($cb_post) . '">' . 
                        esc_html($cb_post->post_title) . 
                '</a>';
        }

        //Allow for custom header handling
        $header = apply_filters('clearbase_workspace_header', $header);
        
        echo '<h2 class="workspace-h2">';
            echo "<ul class='header'>\n";
            foreach ( $header as $id => $item ) {
              echo "<li class='header-item $id'>$item</li>";
            }
            echo "</ul>";
        echo '</h2>';
        // End Render Header

        echo '<div class="content">';
        if (isset($this->view))
            $this->view->RenderEditor();
        echo '</div>';
        
        echo '</form></div>';
    }     

}