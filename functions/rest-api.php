<?php
add_action( 'rest_api_init', function () {
	register_rest_route( 'clearbase/v1', '/nth-image/(?P<nth>\d+)', array(
		'methods' => 'GET',
		'callback' => 'clearbase_rest_api_nth_image'//?size=[],format=['id, image, url,'title',caption']
	) );

	register_rest_route( 'clearbase/v1', '/get-children/(?P<folder_id>\d+)', array(
		'methods' => 'GET',
		'callback' => 'clearbase_rest_api_get_children'
	) );
	
} );


add_filter( 'rest_pre_serve_request', 'clearbase_rest_pre_serve_request', 10, 4 );

function clearbase_rest_pre_serve_request( $served, $result, $request, $server ) {
	// assumes 'format' was passed into the intial API route
	// example: https://baconipsum.com/wp-json/baconipsum/test-response?format=text
	// the default JSON response will be handled automatically by WP-API
	$attributes = $request->get_attributes();
	if (!is_wp_error($result->data) && 'clearbase_rest_api_nth_image' == $attributes['callback']) {
//		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
//		echo $result->data;
//		$served = true; // tells the WP-API that we sent the response already

		$uploads = wp_upload_dir();
		$format = $request->get_param('format');
		//set a default size
		$size = $request->get_param('size');
		if ($size != 'full' ) {
			$sizes = get_intermediate_image_sizes();
			if (!in_array($size, $sizes)) {
				$size = $sizes[0];
			}
		}


		switch ($format) {
			case 'id':
				header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
		        echo $result->data;
				$served = true;
				break;
			case 'url':
				// Get the image object
				$image_object = wp_get_attachment_image_src($result->data->ID, $size );
				// Isolate the url
				$image_url = $image_object[0];
				header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
		        echo $result->data;
				$served = true;
				break;
			case 'title':
				header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
				echo $result->data->post_title;
				$served = true;
				break;
			case 'caption':
				header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
				echo $result->data->post_excerpt;
				$served = true;
				break;
			default:
				// Get the image object
				$image_object = wp_get_attachment_image_src($result->data->ID, $size );
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
				break;
		}

	}
	return $served;
}



/**
 * Grab latest clearbase image!
 *
 * @param array $data Options for the function.
 * @return string|null Post title for the latest,  * or null if none.
 */
function clearbase_rest_api_nth_image( $data ) {
	//the function: [clearbase_rest_pre_serve_request] serves the actual image...
	return clearbase_get_nth_attachment('image', null, $data['nth'], true);
}

function clearbase_rest_api_get_children($data) {
	return clearbase_get_children($data['folder_id']);
}