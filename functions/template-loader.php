<?php
  /**
   * Load a template.
   *
   * Handles template usage so that we can use our own templates instead of the themes.
   *
   * Templates are in the 'templates' folder. woocommerce looks for theme
   * overrides in /theme/woocommerce/ by default
   *
   * For beginners, it also looks for a woocommerce.php template first. If the user adds
   * this to the theme (containing a woocommerce() inside) this will be used for all
   * woocommerce templates.
   *
   */

  if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
  }

  function clearbase_get_header() {
    get_header();
  }
  add_action('clearbase_get_header', 'clearbase_get_header');

  // Add specific CSS class by filter
  add_filter( 'body_class', 'clearbase_body_class' );
  function clearbase_body_class( $classes ) {
    if ($controller = clearbase_get_controller())
      $classes[] = "controller-{$controller->ID()}";
    // return the $classes array
    return $classes;
  }


  function clearbase_controller_before() {
    echo '<div class="clearbase-content">';
  }
  add_action('clearbase_controller_before', 'clearbase_controller_before');


  function clearbase_controller_after() {
    echo '</div>';
  }
  add_action('clearbase_controller_after', 'clearbase_controller_after');


  function clearbase_get_sidebar() {
    get_sidebar();
  }
  add_action('clearbase_get_sidebar', 'clearbase_get_sidebar');


  function clearbase_get_footer() {
    get_footer();
  }
  add_action('clearbase_get_footer', 'clearbase_get_footer');

  do_action('clearbase_template_start');
  //fire the actions
  do_action('clearbase_get_header');
  do_action('clearbase_controller_before');

  if ( $controller = clearbase_get_controller() ) {
    $controller->Render();

    do_action("clearbase_controller_{$controller->ID()}_after", $controller);
    do_action('clearbase_controller_after', $controller);
  } else {
    echo 'Hello World!';
  }

  do_action('clearbase_get_sidebar');
  do_action('clearbase_get_footer');
