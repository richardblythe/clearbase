<?php
class Clearbase_View {
    public static $uid = 10; //init with 10 to allow for standard filtering priority
    protected $_activated, $fields, $update_fields, $save_event = 'save-editor';
    public function __construct($fields = array(), $auto_activate = true) {
        $fields = array_merge($this->EditorFields(), $fields);
        $fields = apply_filters('clearbase_editor_fields', $fields);
        $fields = apply_filters("clearbase_{$this->ID()}_editor_fields", $fields);
        $this->fields = $fields;

        add_action( 'wp_enqueue_scripts', array(&$this, 'Enqueue'));
        add_action( 'admin_enqueue_scripts', array(&$this, 'Enqueue'));

        $this->Init();
    } 
    
    public function ID() {
        throw new Exception("You must specify an ID for the view!");
    }
    
    public function Title() {
        global $cb_post;
        return isset($cb_post) ?  $cb_post->post_title : $this->ID();
    }
    
    public function Init() {}

    public function PageTitle($page_title) {
        return clearbase_is_root() ? $page_title : "{$this->Title()} &lsaquo; {$page_title}";
    }

    public function Header($header = array()) {
        return $header;
    }

    public function Enqueue() {}
    
    //Renders the view
    public function Render($data = null) {
        // $post = get_post($data);
    }

    public function InitEditor() {
        add_filter( 'admin_title', array(&$this, 'PageTitle'), ++Clearbase_View::$uid, 2 );
        add_filter( 'clearbase_workspace_header', array(&$this, 'Header'), Clearbase_View::$uid, 1 );
        add_action( 'clearbase_view_save_editor', array(&$this, 'SaveEditor'), 10, 1);
    }

    public function EditorFields() {
      return array();
    }

    public function SaveEditor($event = 'save-editor') {
        if ($event != $this->save_event || empty( $_POST ) || count($this->fields) == 0 )
            return false;

        global $post, $cb_post;
        // Options to update will be stored here
        $post = $cb_post; //set the wp global post to the clearbase global post
        $this->update_fields = array();

        // Loop options and get values to save
        foreach ( $this->fields as $field ) {

            if ( ! isset( $field['id'] ) )
                continue;
            $convertedID = str_replace('.', '-', $field['id']);
            
            $type = isset( $field['type'] ) ? sanitize_title( $field['type'] ) : '';

            // Get the option name
            $value = null;
            
            switch ( $type ) {
                // Standard types
                case "text" :
                case 'email':
                case 'number':
                case "select" :
                case "color" :
                case 'password' : 
                case 'radio' :
                    if ( isset( $_POST[$convertedID] ) ) {
                        $value = sanitize_text_field( stripslashes( $_POST[ $convertedID ] ) );
                    } else {
                        $value = isset($field['default']) ? $field['default'] : '';
                    }
                    break;
                case "textarea" :
                    if ( isset( $_POST[$convertedID] ) ) {
                        $value = wp_kses_post( trim( stripslashes( $_POST[ $convertedID] ) ) );
                    } else {
                    	$value = '';
                	}

                break;
             	  case "checkbox" :
                    if ( isset( $_POST[ $convertedID ] ) ) {
                        $value = 'yes';
                    } else {
                        $value = 'no';
                    }

                break;
                case 'sectionstart':
                case 'sectionend':
                  continue;
                // Custom handling
                case 'post_title':
                  $value = array();
                  if ( isset( $_POST['post-post_title'] ) )
                    $value['post_title'] = sanitize_text_field( stripslashes( $_POST[ 'post-post_title' ] ) );
                  if ( isset( $_POST['post-post_name'] ) )
                    $value['post_name'] = sanitize_text_field( stripslashes( $_POST[ 'post-post_name' ] ) );
                  break;

                default:
                    if (is_callable($field['save']))
                      call_user_func($field['save'], $field);
                    do_action( "clearbase_editor_field_{$type}_save", $field );
                    break;

            }

            if (is_null( $value ) )
              continue;
            //Split the $field into tokens that we can proccess
            $tokens = explode('.', $field['id']);

          	if ('post' === $tokens[0]) {
              if (!is_array($this->update_fields['post']))
                $this->update_fields['post'] = array();

              if (is_array($value))
                $this->update_fields['post'] = array_merge($this->update_fields['post'], $value);
              else
          		  $this->update_fields['post'][$tokens[1]] = $value;
          	} else if ('postmeta' === $tokens[0] && count($tokens) > 2) {
                /*  
                token[0] is keyword postmeta, 
                token[1] is the postmeta key
                token[2+] references a nested array 
                
                if the field id contains more than token[0] and token[1],
                then the user is referencing a nested array
                example: postmeta.house.doors.back.color
                */
                //ensure that the root meta reference is an array
                if (!is_array($this->update_fields[ 'postmeta.' . $tokens[1] ]))
                    $this->update_fields['postmeta.' . $tokens[1]] = array();
                $context =& _clearbase_array_traverse(array_slice($tokens, 2, count($tokens) - 3), $this->update_fields['postmeta.' . $tokens[1]], true );
                //now that we're at the correct nested array position, assign the specified value
                $context[$tokens[count($tokens) - 1]] = $value;    
          	} else {
                $this->update_fields[ $field['id'] ] = $value;
            }
        }

        // Now save the update fields
        foreach( $this->update_fields as $key => $value ) {
           clearbase_set_value($key, $value );
        }

        return true;
    }
    
    public function RenderEditor() {
	      global $post, $cb_post;
        //assign the clearbase post object to the wp $post object for this context
        $post = $cb_post;

        foreach ( $this->fields as $field ) {
        if ( ! isset( $field['type'] ) ) continue;
	    	if ( ! isset( $field['id'] ) ) $field['id'] = '';
	    	if ( ! isset( $field['title'] ) ) $field['title'] = isset( $field['name'] ) ? $field['name'] : '';
	    	if ( ! isset( $field['class'] ) ) $field['class'] = '';
	    	if ( ! isset( $field['css'] ) ) $field['css'] = '';
	    	if ( ! isset( $field['default'] ) ) $field['default'] = '';
	    	if ( ! isset( $field['desc'] ) ) $field['desc'] = '';
	    	if ( ! isset( $field['desc_tip'] ) ) $field['desc_tip'] = false;

        $convertedID = str_replace('.', '-', $field['id']);
	    	// Custom attribute handling
			$custom_attributes = array();

			if ( ! empty( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) )
				foreach ( $field['custom_attributes'] as $attribute => $attribute_value )
					$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';

			// Description handling
			if ( $field['desc_tip'] === true ) {
				$description = '';
				$tip = $field['desc'];
			} elseif ( ! empty( $field['desc_tip'] ) ) {
				$description = $field['desc'];
				$tip = $field['desc_tip'];
			} elseif ( ! empty( $field['desc'] ) ) {
				$description = $field['desc'];
				$tip = '';
			} else {
				$description = $tip = '';
			}

			if ( $description && in_array( $field['type'], array( 'textarea', 'radio' ) ) ) {
				$description = '<p style="margin-top:0">' . wp_kses_post( $description ) . '</p>';
			} elseif ( $description && in_array( $field['type'], array( 'checkbox' ) ) ) {
				$description =  wp_kses_post( $description );
			} elseif ( $description ) {
				$description = '<span class="description">' . wp_kses_post( $description ) . '</span>';
			}

			if ( $tip && in_array( $field['type'], array( 'checkbox' ) ) ) {

				$tip = '<p class="description">' . $tip . '</p>';

			} elseif ( $tip ) {

				$tip = '<img />'; //'<img class="help_tip" data-tip="' . esc_attr( $tip ) . '" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16" />';

			}

			// Switch based on type
	        switch( $field['type'] ) {

	        	// Section Titles
	            case 'sectionstart':
	            	if ( ! empty( $field['title'] ) ) {
	            		echo '<h3>' . esc_html( $field['title'] ) . '</h3>';
	            	}
	            	if ( ! empty( $field['desc'] ) ) {
	            		echo wpautop( wptexturize( wp_kses_post( $field['desc'] ) ) );
	            	}
	            	echo '<table class="form-table ' . $field['class'] . '">'. "\n\n";
	            	if ( ! empty( $field['id'] ) ) {
	            		do_action( 'clearbase_editor_section_' . sanitize_title( $field['id'] ) );
	            	}
	            break;

	            // Section Ends
	            case 'sectionend':
	            	if ( ! empty( $field['id'] ) ) {
	            		do_action( 'clearbase_editor_section_' . sanitize_title( $field['id'] ) . '_end' );
	            	}
	            	echo '</table>';
	            	if ( ! empty( $field['id'] ) ) {
	            		do_action( 'clearbase_editor_section_' . sanitize_title( $field['id'] ) . '_after' );
	            	}
	            break;
              case 'post_title':
                ?><tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="post-post_title"><?php echo __('Title', 'clearbase'); ?></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input
                            name="post-post_title"
                            id="post-post_title"
                            type="text"
                            style="min-width: 300px"
                            value="<?php echo esc_attr( $post->post_title ); ?>"
                            class=""
                        /> <?php 
                        
                            //Render the permalink editor

                            $sample_permalink_html = get_sample_permalink_html($post->ID);
                            // $shortlink = wp_get_shortlink($post->ID, 'post');

                            // if ( !empty( $shortlink ) && $shortlink !== $permalink && $permalink !== home_url('?page_id=' . $post->ID) )
                            //     $sample_permalink_html .= '<input id="shortlink" type="hidden" value="' . esc_attr($shortlink) . '" /><a href="#" class="button button-small" onclick="prompt(&#39;URL:&#39;, jQuery(\'#shortlink\').val()); return false;">' . __('Get Shortlink') . '</a>';

                            if ( ! ( 'pending' == get_post_status( $post ) && !current_user_can( $post_type_object->cap->publish_posts ) ) ) {
                                $has_sample_permalink = $sample_permalink_html && 'auto-draft' != $post->post_status;
                            ?>
                                <div id="edit-slug-box" class="hide-if-no-js">
                                <?php
                                    if ( $has_sample_permalink )
                                        echo $sample_permalink_html;
                                ?>
                                </div>
                            <?php
                            }

                      ?>
                    </td>
                </tr>
                <tr valign="top" class="hide-if-js">
                    <th scope="row" class="titledesc">
                            <label for="post-post_name"><?php echo esc_html( __('Slug', 'clearbase')); ?></label>
                            <?php echo $tip; ?>
                    </th>
                    <td class="forminp forminp-text">
                      <input
                        name="post-post_name"
                        id="post-post_name"
                        type="text"
                        style="min-width: 300px"
                        value="<?php echo esc_attr( $post->post_name ); ?>"
                        class=""
                        /> <?php 
                        echo __('Specifies the permalink slug', 'clearbase'); 
                        ?>
                    </td>
                </tr>
                <?php
                break;
	            // Standard text inputs and subtypes like 'number'
	            case 'text':
	            case 'email':
	            case 'number':
	            case 'color' :
	            case 'password' :

	            	$type 			= $field['type'];
	            	$class 			= '';
	            	$option_value 	= clearbase_get_value($field['id'], $field['default'] );

	            	if ( $field['type'] == 'color' ) {
	            		$type = 'text';
	            		$field['class'] .= 'colorpick';
		            	$description .= '<div id="colorPickerDiv_' . $convertedID . 
                    ' " class="colorpickdiv" style="z-index: 100;background:#eee;border:1px solid #ccc;position:absolute;display:none;"></div>';
	            	}

	            	?><tr valign="top" class="<?php echo $field['tr_class'] ?>">
                            <th scope="row" class="titledesc <?php echo $field['th_class'] ?>">
                                    <label for="<?php echo $convertedID; ?>"><?php echo esc_html( $field['title'] ); ?></label>
                                    <?php echo $tip; ?>
                            </th>
	                    <td class="forminp forminp-<?php echo sanitize_title( $field['type'] ); echo $field['td_class']; ?>">
	                    	<input
	                    		name="<?php echo $convertedID ; ?>"
	                    		id="<?php echo $convertedID ; ?>"
	                    		type="<?php echo esc_attr( $type ); ?>"
	                    		style="<?php echo esc_attr( $field['css'] ); ?>"
	                    		value="<?php echo esc_attr( $option_value ); ?>"
	                    		class="<?php echo esc_attr( $field['class'] ); ?>"
	                    		<?php echo implode( ' ', $custom_attributes ); ?>
	                    		/> <?php 
                          echo $description; 
                          ?>
	                    </td>
	                </tr><?php
	            break;

	            // Textarea
	            case 'textarea':

	            	$option_value 	= clearbase_get_value($field['id'], $field['default'] );

	            	?><tr valign="top" class="<?php echo $field['tr_class'] ?>">
						<th scope="row" class="titledesc <?php echo $field['th_class'] ?>">
							<label for="<?php echo $convertedID; ?>"><?php echo esc_html( $field['title'] ); ?></label>
							<?php echo $tip; ?>
						</th>
	                    <td class="forminp forminp-<?php echo sanitize_title( $field['type'] ); echo $field['td_class']; ?>">
	                    	<?php echo $description; ?>

	                        <textarea
	                        	name="<?php echo $convertedID; ?>"
	                        	id="<?php echo $convertedID; ?>"
	                        	style="<?php echo esc_attr( $field['css'] ); ?>"
	                        	class="<?php echo esc_attr( $field['class'] ); ?>"
	                        	<?php echo implode( ' ', $custom_attributes ); ?>
	                        	><?php echo esc_textarea( $option_value );  ?></textarea>
	                    </td>
	                </tr><?php
	            break;

	            // Select boxes
	            case 'select' :
	            case 'multiselect' :

	            	$option_value 	= clearbase_get_value($field['id'], $field['default'] );

	            	?><tr valign="top" class="<?php echo $field['tr_class']; ?>">
						<th scope="row" class="titledesc <?php echo $field['th_class']; ?>">
							<label for="<?php echo $convertedID; ?>"><?php echo esc_html( $field['title'] ); ?></label>
							<?php echo $tip; ?>
						</th>
	                    <td class="forminp forminp-<?php echo sanitize_title( $field['type'] ); echo $field['td_class']; ?>">
	                    	<select
	                    		name="<?php echo $convertedID; ?><?php if ( $field['type'] == 'multiselect' ) echo '[]'; ?>"
	                    		id="<?php echo $convertedID; ?>"
	                    		style="<?php echo esc_attr( $field['css'] ); ?>"
	                    		class="<?php echo esc_attr( $field['class'] ); ?>"
	                    		<?php echo implode( ' ', $custom_attributes ); ?>
	                    		<?php if ( $field['type'] == 'multiselect' ) echo 'multiple="multiple"'; ?>
	                    		>
		                    	<?php
			                        foreach ( $field['options'] as $key => $val ) {
			                        	?>
			                        	<option value="<?php echo esc_attr( $key ); ?>" <?php

				                        	if ( is_array( $option_value ) )
				                        		selected( in_array( $key, $option_value ), true );
				                        	else
				                        		selected( $option_value, $key );

			                        	?>><?php echo $val ?></option>
			                        	<?php
			                        }
			                    ?>
	                       </select> <?php echo $description; ?>
	                    </td>
	                </tr><?php
	            break;

	            // Radio inputs
	            case 'radio' :

	            	$option_value 	= clearbase_get_value($field['id'], $field['default'] );

	            	?><tr valign="top" class="<?php echo $field['tr_class']; ?>">
						<th scope="row" class="titledesc <?php echo $field['th_class']; ?>">
							<label for="<?php echo $convertedID; ?>"><?php echo esc_html( $field['title'] ); ?></label>
							<?php echo $tip; ?>
						</th>
	                    <td class="forminp forminp-<?php echo sanitize_title( $field['type'] ); echo $field['td_class']; ?>">
	                    	<fieldset>
	                    		<?php echo $description; ?>
	                    		<ul>
	                    		<?php
	                    			foreach ( $field['options'] as $key => $val ) {
			                        	?>
			                        	<li>
			                        		<label><input
				                        		name="<?php echo $convertedID; ?>"
				                        		value="<?php echo $key; ?>"
				                        		type="radio"
					                    		style="<?php echo esc_attr( $field['css'] ); ?>"
					                    		class="<?php echo esc_attr( $field['class'] ); ?>"
					                    		<?php echo implode( ' ', $custom_attributes ); ?>
					                    		<?php checked( $key, $option_value ); ?>
				                        		/> <?php echo $val ?></label>
			                        	</li>
			                        	<?php
			                        }
	                    		?>
	                    		</ul>
	                    	</fieldset>
	                    </td>
	                </tr><?php
	            break;

	            // Checkbox input
	            case 'checkbox' :

                $option_value    = clearbase_get_value($field['id'], $field['default'] );
                $visbility_class = array();

	            	if ( ! isset( $field['hide_if_checked'] ) ) {
	            		$field['hide_if_checked'] = false;
	            	}
	            	if ( ! isset( $field['show_if_checked'] ) ) {
	            		$field['show_if_checked'] = false;
	            	}
	            	if ( $field['hide_if_checked'] == 'yes' || $field['show_if_checked'] == 'yes' ) {
	            		$visbility_class[] = 'hidden_option';
	            	}
	            	if ( $field['hide_if_checked'] == 'option' ) {
	            		$visbility_class[] = 'hide_options_if_checked';
	            	}
	            	if ( $field['show_if_checked'] == 'option' ) {
	            		$visbility_class[] = 'show_options_if_checked';
	            	}

	            	if ( ! isset( $field['checkboxgroup'] ) || 'start' == $field['checkboxgroup'] ) {
	            		?>
		            		<tr valign="top" class="<?php echo esc_attr( implode( ' ', $visbility_class ) ); echo $field['tr_class']; ?>">
								<th scope="row" class="titledesc <?php echo $field['th_class']; ?>"><?php echo esc_html( $field['title'] ) ?></th>
								<td class="forminp forminp-checkbox <?php echo $field['td_class']; ?>">
									<fieldset>
						<?php
	            	} else {
	            		?>
		            		<fieldset class="<?php echo esc_attr( implode( ' ', $visbility_class ) ); ?>">
	            		<?php
	            	}

	            	if ( ! empty( $field['title'] ) ) {
	            		?>
	            			<legend class="screen-reader-text"><span><?php echo esc_html( $field['title'] ) ?></span></legend>
	            		<?php
	            	}

	            	?>
						<label for="<?php echo $convertedID ?>">
							<input
								name="<?php echo $convertedID; ?>"
								id="<?php echo $convertedID; ?>"
								type="checkbox"
								value="1"
								<?php checked( $option_value, 'yes'); ?>
								<?php echo implode( ' ', $custom_attributes ); ?>
							/> <?php echo $description ?>
						</label> <?php echo $tip; ?>
					<?php

					if ( ! isset( $field['checkboxgroup'] ) || 'end' == $field['checkboxgroup'] ) {
									?>
									</fieldset>
								</td>
							</tr>
						<?php
					} else {
						?>
							</fieldset>
						<?php
					}
	            break;

	            // Default: run an action
	            default:
                if (is_callable($field['render']))
                    call_user_func($field['render'], $field);
	            	do_action( 'clearbase_editor_field_' . $field['type'], $field );
	            break;
	    	}
		}
	}
}
