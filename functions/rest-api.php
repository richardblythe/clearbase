<?php
add_action( 'rest_api_init', function () {
	register_rest_route( 'clearbase/v1', '/nth-image-url/(?P<nth>\d+)', array(
		'methods' => 'GET',
		'callback' => 'clearbase_rest_api_nth_image_url',
	) );
} );


/**
 * Grab latest clearbase image url!
 *
 * @param array $data Options for the function.
 * @return string|null Post title for the latest,  * or null if none.
 */
function clearbase_rest_api_nth_image_url( $data ) {
	$attachment = clearbase_get_nth_attachment('image', null, $data['nth']);

	if ( !is_wp_error($attachment) )  {
		return wp_get_attachment_image_url($attachment->ID, 'medium');
	}

	return 'not working yet';
}