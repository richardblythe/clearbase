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
//		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
//		echo $result->data;
//		$served = true; // tells the WP-API that we sent the response already


		$uploads = wp_upload_dir();

		// Get the image object
		$image_object = wp_get_attachment_image_src($result->data, 'medium' );
		// Isolate the url
		$image_url = $image_object[0];
		// Using the wp_upload_dir replace the baseurl with the basedir
		$image_path = str_replace( $uploads['baseurl'], $uploads['basedir'], $image_url );
		//replace forward slashes with back slashes
		$image_path = str_replace('\\','/', $image_path);

		$handle = fopen($image_path, "rb");
		$contents = fread($handle, filesize($image_path));
		fclose($handle);

		header("Content-Type: image/jpeg");

		echo $contents;
		$served = true;
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
//		$str = get_attached_file($attachment->ID);
//		return $str;
		return $attachment->ID;
	}

	return 'not working yet';
}