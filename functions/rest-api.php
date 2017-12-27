<?php
add_action( 'rest_api_init', function () {
	register_rest_route( 'clearbase/v1', '/nth-image-url/(?P<nth>\d+)', array(
		'methods' => 'GET',
		'callback' => 'clearbase_rest_api_nth_image_url'
	) );
} );


add_filter( 'rest_pre_serve_request', 'clearbase_rest_pre_serve_request', 10, 4 );

function clearbase_rest_pre_serve_request( $served, $result, $request, $server ) {
	// assumes 'format' was passed into the intial API route
	// example: https://baconipsum.com/wp-json/baconipsum/test-response?format=text
	// the default JSON response will be handled automatically by WP-API
	$attributes = $request->get_attributes();
	if ('clearbase_rest_api_nth_image_url' == $attributes['callback']) {
		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
		echo $result->data;
		$served = true; // tells the WP-API that we sent the response already
	}
	return $served;
}



/**
 * Grab latest clearbase image url!
 *
 * @param array $data Options for the function.
 * @return string|null Post title for the latest,  * or null if none.
 */
function clearbase_rest_api_nth_image_url( $data ) {
	$attachment = clearbase_get_nth_attachment('image', null, $data['nth']);

	if ( !is_wp_error($attachment) )  {
		$str = stripslashes(wp_get_attachment_image_url($attachment->ID, 'medium'));
		return $str;
	}

	return 'not working yet';
}