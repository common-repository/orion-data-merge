<?php

/**
 * Outputs the settings fields
 *
 * @param array $options Settings to output
 */
if ( ! function_exists( 'o_admin_fields' ) ) {
	function o_admin_fields( $options ) {
		global $o_row_templates;
		ob_start();
		foreach ( $options as $value ) {
			if ( ! isset( $value['type'] ) ) {
				continue;
			}
			if ( ! isset( $value['id'] ) ) {
				$value['id'] = '';
			}
			if ( ! isset( $value['name'] ) ) {
				$value['name'] = $value['id'];
			}
			if ( ! isset( $value['hierarchy'] ) ) {
				$value['hierarchy'] = array( $value['name'] );
			}
			if ( ! isset( $value['title'] ) ) {
				$value['title'] = isset( $value['name'] ) ? $value['name'] : '';
			}
			if ( ! isset( $value['class'] ) ) {
				$value['class'] = '';
			}
			if ( ! isset( $value['row_class'] ) ) {
				$value['row_class'] = '';
			}
			if ( ! isset( $value['css'] ) ) {
				$value['css'] = '';
			}
			if ( ! isset( $value['row_css'] ) ) {
				$value['row_css'] = '';
			}
			if ( ! isset( $value['default'] ) ) {
				$value['default'] = '';
			}
			if ( ! isset( $value['desc'] ) ) {
				$value['desc'] = '';
			}
			if ( ! isset( $value['desc_tip'] ) ) {
				$value['desc_tip'] = false;
			}
			if ( ! isset( $value['ignore_desc_col'] ) ) {
				$value['ignore_desc_col'] = false;
			}
			if ( ! isset( $value['label_class'] ) ) {
				$value['label_class'] = '';
			}
			$tip = '';
			if ( isset( $value['tip'] ) ) {
				$tip = "<span class='o-info' data-tooltip-title='" . $value['tip'] . "'></span>";
			}

			// Custom attribute handling
			$custom_attributes = array();

			if ( ! empty( $value['custom_attributes'] ) && is_array( $value['custom_attributes'] ) ) {
				foreach ( $value['custom_attributes'] as $attribute => $attribute_value ) {
					$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
				}
			}

			// Attributes custom attribute handling
			$options_custom_attributes = array();

			if ( ! empty( $value['options_custom_attributes'] ) && is_array( $value['options_custom_attributes'] ) ) {
				foreach ( $value['options_custom_attributes'] as $option_key => $option_attributes ) {
					foreach ( $option_attributes as $attribute => $attribute_value ) {
						$options_custom_attributes[ $option_key ][] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
					}
				}
			}

			$description = $value['desc'];

			if ( $description && in_array( $value['type'], array( 'textarea', 'radio' ) ) ) {
				$description = '<p style="margin-top:0">' . wp_kses_post( $description ) . '</p>';
			} elseif ( $description && in_array( $value['type'], array( 'checkbox' ) ) ) {
				$description = wp_kses_post( $description );
			} elseif ( $description ) {
				$description = '<span class="description">' . wp_kses_post( $description ) . '</span>';
			}

			$post_id         = get_the_ID();
			$option_value    = '';
			$url_field_value = '';
			$raw_hierarchy   = o_explodeX( array( '[', ']' ), $value['name'] );
			$hierarchy       = array_filter( $raw_hierarchy );
			$section_types   = array( 'sectionbegin', 'sectionend' );
			$settings_table  = o_get_proper_value( $options[0], 'table', 'metas' );

			if ( ! in_array( $value['type'], $section_types ) & ! empty( $hierarchy ) ) {
				$root_key    = $hierarchy[0];
				$session_key = $root_key . "_$post_id";
				// We check if the meta is already stored in the session (db optimization) otherwise, we look for the original meta
				// $session=  o_get_proper_value($_SESSION, "o-data", array());
				$option_value = null;
				if ( isset( $_SESSION['o-data'] ) ) {
					$_SESSION['o-data'] = filter_input( INPUT_POST, 'o-data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
					$option_value       = o_get_proper_value( $_SESSION['o-data'], $session_key, false );
				}

				if ( ! $option_value ) {
					// Retrive from the metas
					if ( $settings_table == 'metas' ) {
						$option_value = get_post_meta( $post_id, $root_key, true );
						// var_dump("metaextraction");
						// var_dump("$root_key");
						$_SESSION['o-data'][ $session_key ] = $option_value;
						// var_dump($option_value);
					}
					// Retrive from the options
					elseif ( $settings_table == 'options' ) {
						$option_value                       = get_option( $root_key );
						$_SESSION['o-data'][ $session_key ] = $option_value;
					} elseif ( $settings_table == 'custom' ) {
						$option_value                       = o_get_proper_value( $options[0], 'data', array() );
						$_SESSION['o-data'][ $session_key ] = $option_value;
					}
				}

				$i    = 0;
				$prev = '';

				$session_key = $root_key . "_$post_id";
				$root_value  = o_get_proper_value( $_SESSION['o-data'], $session_key, false );
				if ( $root_key != $value['name'] ) {
					$option_value = o_find_in_array_by_key( $root_value, $value['name'] );
				}
			}
			$col_class = o_get_proper_value( $value, 'col_class', '' );
			if ( ! $option_value && $option_value !== '0' ) {
				$option_value = $value['default'];
			}
			if ( ! in_array( $value['type'], $section_types ) && ! $value['ignore_desc_col'] ) {
				?>
<tr style="<?php echo esc_attr( $value['row_css'] ); ?>" class="<?php echo esc_attr( $value['row_class'] ); ?>">
<td class='label <?php echo esc_attr( $col_class ); ?>'>
				<?php echo esc_attr( $value['title'] . $tip ); ?>
<div class='o-desc'>
				<?php echo esc_attr( $value['desc'] ); ?>
</div>
</td>
				<?php
			}

			if ( ! in_array( $value['type'], $section_types ) ) {
				if ( isset( $value['show_as_label'] ) ) {
					echo wp_kses_post( "<label class='" . $value['label_class'] . "'>" . $value['title'] . $tip );
				} else {
					echo wp_kses_post( "<td class='$col_class'>" );
				}
			}
			// Switch based on type
			switch ( $value['type'] ) {
				case 'sectionbegin':
					// We start/reset the session
					$_SESSION['o-data'] = array();
					?>
<div class="o-wrap">
<div id="<?php echo esc_attr( $value['id'] ); ?>" class="o-metabox-container">
<div class='block-form'>
<table class="wp-list-table widefat fixed pages o-root">
<tbody>
					<?php
					break;
				case 'sectionend':
					?>
</tbody>
</table>
</div>
</div>
</div>
					<?php
					break;
				// Standard text inputs and subtypes like 'number'
				case 'text':
				case 'email':
				case 'number':
				case 'password':
					$type = $value['type'];
					?>

<input name="<?php echo esc_attr( $value['name'] ); ?>" id="<?php echo esc_attr( $value['id'] ); ?>" type="<?php echo esc_attr( $type ); ?>" style="<?php echo esc_attr( $value['css'] ); ?>" value="<?php echo esc_attr( $option_value ); ?>" class="<?php echo esc_attr( $value['class'] ); ?>" <?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?> />

					<?php
					break;

				case 'color':
					$type            = 'text';
					$value['class'] .= 'o-color';
					?>
<div class="o-color-container">
<input name="<?php echo esc_attr( $value['name'] ); ?>" id="<?php echo esc_attr( $value['id'] ); ?>" type="<?php echo esc_attr( $type ); ?>" style="<?php echo esc_attr( $value['css'] ); ?>" value="<?php echo esc_attr( $option_value ); ?>" class="<?php echo esc_attr( $value['class'] ); ?>" <?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?> />
<span class="o-color-btn"></span>
</div>

					<?php
					break;

				case 'textarea':
					?>

<textarea name="<?php echo esc_attr( $value['name'] ); ?>" id="<?php echo esc_attr( $value['id'] ); ?>" style="<?php echo esc_attr( $value['css'] ); ?>" class="<?php echo esc_attr( $value['class'] ); ?>" <?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?>><?php echo esc_textarea( $option_value ); ?></textarea>

					<?php
					break;

				case 'texteditor':
					wp_editor(
						$option_value,
						$value['id'],
						array(
							'wpautop'       => true,
							'media_buttons' => false,
							'textarea_name' => $value['name'],
							'textarea_rows' => 10,
							'false'         => true,
						)
					);
					break;

				case 'select':
				case 'multiselect':
				case 'post-type':
					if ( $value['type'] == 'post-type' ) {
						// We make sure the limit is -1 if not set
						$value['args']['posts_per_page'] = o_get_proper_value( $value['args'], 'posts_per_page', -1 );
						$posts                           = get_posts( $value['args'] );
						$posts_ids                       = o_get_proper_value( $value, 'first_value', array() );
						foreach ( $posts as $post ) {
							$posts_ids[ $post->ID ] = $post->post_title;
						}
						$value['options'] = $posts_ids;
					}
					?>

<select name="<?php echo esc_attr( $value['name'] ); ?>
						 <?php
							if ( $value['type'] == 'multiselect' || in_array( 'multiple="multiple"', $custom_attributes ) ) {
								echo esc_attr( '[]' );
							}
							?>
" id="<?php echo esc_attr( $value['id'] ); ?>" style="<?php echo esc_attr( $value['css'] ); ?>" class="<?php echo esc_attr( $value['class'] ); ?>" <?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?> <?php
if ( $value['type'] == 'multiselect' ) {
	echo esc_attr( 'multiple="multiple"' );
}
?>
>
					<?php
					foreach ( $value['options'] as $key => $val ) {
						$option_custom_attributes = o_get_proper_value( $options_custom_attributes, $key, array() );
						?>
<option value="<?php echo esc_attr( $key ); ?>" <?php echo esc_attr( implode( ' ', $option_custom_attributes ) ); ?> <?php
if ( is_array( $option_value ) ) {
	selected( in_array( $key, $option_value ), true );
} else {
	selected( $option_value, $key );
}
?>
												><?php echo esc_attr( $val ); ?></option>
						<?php
					}
					?>
</select>

					<?php
					break;
				case 'groupedselect':
					?>
<select name="<?php echo esc_attr( $value['name'] ); ?>
						<?php
							if ( $value['type'] == 'multiselect' ) {
								echo esc_attr( '[]' );
							}
							?>
" id="<?php echo esc_attr( $value['id'] ); ?>" style="<?php echo esc_attr( $value['css'] ); ?>" class="<?php echo esc_attr( $value['class'] ); ?>" <?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?> <?php
if ( $value['type'] == 'multiselect' ) {
	echo esc_attr( 'multiple="multiple"' );
}
?>
>
					<?php
					foreach ( $value['options'] as $group => $group_values ) {
						?>
		<optgroup label="<?php echo esc_attr( $group ); ?>">
						<?php
						foreach ( $group_values as $key => $val ) {
							?>
<option value="<?php echo esc_attr( $key ); ?>" 
							<?php
							if ( is_array( $option_value ) ) {
								selected( in_array( $key, $option_value ), true );
							} else {
								selected( $option_value, $key );
							}
							?>
><?php echo esc_attr( $val ); ?></option>
							<?php
						}
						?>
</optgroup>
						<?php
					}
					?>
</select> <?php echo esc_attr( $description ); ?>
<!--</td>-->
					<?php
					break;

				// Radio inputs
				case 'radio':
					?>
<fieldset>
<ul>
					<?php
					foreach ( $value['options'] as $key => $val ) {
						?>
<li>
<label><input name="<?php echo esc_attr( $value['name'] ); ?>" value="<?php echo esc_attr( $key ); ?>" type="radio" style="<?php echo esc_attr( $value['css'] ); ?>" class="<?php echo esc_attr( $value['class'] ); ?>" <?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?> <?php checked( $key, $option_value ); ?> /> <?php echo esc_attr( $val ); ?></label>
</li>
						<?php
					}
					?>
</ul>
</fieldset>
					<?php
					break;

				case 'checkbox':
					$visbility_class = array();

					if ( ! isset( $value['hide_if_checked'] ) ) {
						$value['hide_if_checked'] = false;
					}
					if ( ! isset( $value['show_if_checked'] ) ) {
						$value['show_if_checked'] = false;
					}
					if ( $value['hide_if_checked'] == 'yes' || $value['show_if_checked'] == 'yes' ) {
						$visbility_class[] = 'hidden_option';
					}
					if ( $value['hide_if_checked'] == 'option' ) {
						$visbility_class[] = 'hide_options_if_checked';
					}
					if ( $value['show_if_checked'] == 'option' ) {
						$visbility_class[] = 'show_options_if_checked';
					}

					if ( ! isset( $value['checkboxgroup'] ) || 'start' == $value['checkboxgroup'] ) {
						?>
<fieldset>
						<?php
					} else {
						?>
<fieldset class="<?php echo esc_attr( implode( ' ', $visbility_class ) ); ?>">
						<?php
					}

					if ( ! empty( $value['title'] ) ) {
						?>
<legend class="screen-reader-text"><span><?php echo esc_html( $value['title'] ); ?></span></legend>
						<?php
					}
					$cb_value = o_get_proper_value( $value, 'value', false );
					if ( ! $cb_value ) {
						$cb_value = o_get_proper_value( $value, 'default', 1 );
					}
					?>
<label for="<?php echo esc_attr( $value['id'] ); ?>">
<input name="<?php echo esc_attr( $value['name'] ); ?>" id="<?php echo esc_attr( $value['id'] ); ?>" type="checkbox" value="<?php echo esc_attr( $cb_value ); ?>" <?php checked( $option_value, $cb_value ); ?> <?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?> /> <?php echo esc_attr( $description ); ?>
</label> <?php echo esc_attr( $tip ); ?>
					<?php
					if ( ! isset( $value['checkboxgroup'] ) || 'end' == $value['checkboxgroup'] ) {
						?>
</fieldset>

						<?php
					} else {
						?>
</fieldset>
						<?php
					}
					break;

				case 'image':
					$set_btn_label    = o_get_proper_value( $value, 'set', 'Set image' );
					$remove_btn_label = o_get_proper_value( $value, 'remove', 'Remove image' );

					$img_src      = '';
					$root_img_src = o_get_proper_image_url( $option_value, false );
					if ( $root_img_src ) {
						$img_src = o_get_medias_root_url( "/$root_img_src" );
					}
					?>
<div class="<?php echo esc_attr( $value['class'] ); ?>">
<button class="button o-add-media"><?php echo esc_attr( $set_btn_label ); ?></button>
<button class="button o-remove-media"><?php echo esc_attr( $remove_btn_label ); ?></button>
<input type="hidden" name="<?php echo esc_attr( $value['name'] ); ?>" value="<?php echo esc_attr( $root_img_src ); ?>">
					<div class="media-preview">
					<?php
					if ( isset( $option_value ) ) {
						echo wp_kses_post( "<img src='$img_src'>" );
					}
					?>
</div>
</div>

					<?php
					break;
				case 'file':
					$set_btn_label    = o_get_proper_value( $value, 'set', 'Set file' );
					$remove_btn_label = o_get_proper_value( $value, 'remove', 'Remove file' );
					?>
<div class="<?php echo esc_attr( $value['class'] ); ?>">
<button class="button o-add-media"><?php echo esc_attr( $set_btn_label ); ?></button>
<button class="button o-remove-media"><?php echo esc_attr( $remove_btn_label ); ?></button>
<input type="hidden" name="<?php echo esc_attr( $value['name'] ); ?>" value="<?php echo esc_attr( $option_value ); ?>">
					<div class="media-name">
					<?php
					if ( isset( $option_value ) ) {
						echo esc_attr( basename( $option_value ) );
					}
					?>
</div>
</div>

					<?php
					break;

				case 'date':
					$type            = 'date';
					$value['class'] .= 'o-date';
					?>
<div class="o-date-container">
<input name="<?php echo esc_attr( $value['name'] ); ?>" id="<?php echo esc_attr( $value['id'] ); ?>" type="<?php echo esc_attr( $type ); ?>" style="<?php echo esc_attr( $value['css'] ); ?>" value="<?php echo esc_attr( $option_value ); ?>" class="<?php echo esc_attr( $value['class'] ); ?>" <?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?> />
<!-- <span class="o-date-btn"></span> -->
</div>

					<?php
					break;

				case 'repeatable-fields':
					if ( ! is_array( $option_value ) ) {
						$option_value = array();
					}
					$value['popup'] = o_get_proper_value( $value, 'popup', false );
					$lazy_mode      = o_get_proper_value( $value, 'lazyload', false );

					if ( $value['popup'] ) {
						add_thickbox();
						$modal_id            = uniqid( 'o-modal-' );
						$modal_trigger_class = '';
						// We don't need to do the lazy load for empty popups
						if ( $lazy_mode && ! empty( $option_value ) ) {
							$modal_trigger_class = 'lazy-popup';
						}
						echo wp_kses_post( "<a class='o-modal-trigger button button-primary button-large $modal_trigger_class' data-toggle='o-modal' data-target='#$modal_id' data-modalid='$modal_id'>" . $value['popup_button'] . '</a>' );
						echo wp_kses_post( '<div class="omodal fade o-modal" id="' . $modal_id . '" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
<div class="omodal-dialog">
<div class="omodal-content">
<div class="omodal-header">
<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
<h4 class="omodal-title" id="myModalLabel' . $modal_id . '">' . $value['popup_title'] . '</h4>
</div>
<div class="omodal-body">' );
						$value['class'] .= ' table-fixed-layout';
					}
					if ( $lazy_mode ) {
						echo wp_kses_post( "<img id='$modal_id" . "-spinner' src='" . VPC_URL . "/admin/images/spinner.gif' style='display: none;'>" );
					}
					?>

<table id="<?php echo esc_attr( $value['id'] ); ?>" class="<?php echo esc_attr( $value['class'] ); ?> widefat repeatable-fields-table">
<thead>
<tr>
					<?php
					foreach ( $value['fields'] as $field ) {
						$tip = '';
						if ( isset( $field['tip'] ) ) {
								$tip = "<span class='o-info' data-tooltip-title=\"" . $field['tip'] . '"></span>';
						}
						$col_class = o_get_proper_value( $field, 'col_class', '' );
						echo wp_kses_post( "<td class='$col_class'>" . $field['title'] . "$tip</td>" );
					}
					?>
<td style="width: 20px;"></td>
</tr>
</thead>
<tbody>
					<?php
					if ( $lazy_mode ) {
						$option_value = array();
					}
					o_get_repeatable_field_table_rows( $value, $option_value );
					$row_tpl = o_get_row_template( $value );
					$row_tpl = preg_replace( "/\r|\n/", '', $row_tpl );
					$row_tpl = preg_replace( '/\s+/', ' ', $row_tpl );
					// if(!isset($o_row_templates))
					// $o_row_templates=array();
					$tpl_id                     = uniqid();
					$o_row_templates[ $tpl_id ] = $row_tpl;

					$add_label = o_get_proper_value( $value, 'add_btn_label', __( 'Add', 'vpc' ) );
					?>
</tbody>
</table>
					<a class="button mg-top add-rf-row" data-tpl="<?php echo esc_attr( $tpl_id ); ?>"><?php echo esc_attr( $add_label ); ?></a>
					<?php

					if ( $value['popup'] ) {
						echo wp_kses_post( '</div></div></div></div>' );
					}
					break;

				case 'groupedfields':
					?>

<div class="o-wrap xl-gutter-8">
					<?php
					foreach ( $value['fields'] as $field ) {
						$field['show_as_label']   = true;
						$field['ignore_desc_col'] = true;
						$field['table']           = $settings_table;
						if ( ! isset( $field['label_class'] ) ) {
							$nb_cols = count( $value['fields'] );
							// if($nb_cols>12)
							// $nb_cols=12;
							$field['label_class'] = 'o-col xl-1-' . $nb_cols;
						}
						echo wp_kses_post( o_admin_fields( array( $field ) ) );
					}
					?>
</div>

					<?php
					break;

				case 'custom':
					call_user_func( $value['callback'] );
					break;
				case 'button':
					?>

<a id="<?php echo esc_attr( $value['id'] ); ?>" style="<?php echo esc_attr( $value['css'] ); ?>" value="<?php echo esc_attr( $option_value ); ?>" class="<?php echo esc_attr( $value['class'] ); ?>" <?php echo esc_attr( implode( ' ', $custom_attributes ) ); ?>><?php echo esc_attr( $value['title'] ); ?></a>

					<?php
					break;
				case 'google-font':
					o_get_google_fonts_selector( $option_value, esc_attr( $value['id'] ), esc_attr( $value['name'] ), esc_attr( $value['css'] ), esc_attr( $value['class'] ) );
					break;

						// Default: run an action
				default:
					do_action( 'o_admin_field_' . $value['type'], $value );
					break;
			}
			if ( ! in_array( $value['type'], $section_types ) ) {
				if ( isset( $value['show_as_label'] ) ) {
					echo wp_kses_post( '</label>' );
				} else {
					echo wp_kses_post( '</td>' );
				}
			}
			if ( ! in_array( $value['type'], $section_types ) && ! $value['ignore_desc_col'] ) {
				?>
</tr>
				<?php
			}
		}

			return ob_get_clean();
	}
}

if ( ! function_exists( 'o_get_row_template' ) ) {
	function o_get_row_template( $value ) {
		 $row_class = o_get_proper_value( $value, 'row_class', '' );
		$row_tpl    = "<tr class='o-rf-row $row_class'>";
		// ID unique permettant d'identifier de façon unique tous les indexes de ce template et de la remplacer tous ensemble en cas de besoin
		$index = uniqid();
		foreach ( $value['fields'] as $field ) {
			$field_tpl = $field;
			// ob_start();
			if ( $field['type'] == 'groupedfields' ) {
				foreach ( $field_tpl['fields'] as $i => $grouped_field ) {
					$field_tpl['fields'][ $i ]['name'] = $value['name'] . '[{' . $index . '}][' . $grouped_field['name'] . ']';
				}
			}
			$field_tpl['name']            = $value['name'] . '[{' . $index . '}][' . $field_tpl['name'] . ']';
			$field_tpl['ignore_desc_col'] = true;
			$row_tpl                     .= o_admin_fields( array( $field_tpl ) );
			// $row_tpl.=ob_get_clean();
		}
		// We add the remove button to the template
		$row_tpl .= '<td><a class="remove-rf-row"></a></td></tr>';

		return $row_tpl;
	}
}

	/**
		* Get a value by key in an array if defined
	 *
		* @param array $values Array to search into
		* @param string $search_key Searched key
		* @param mixed $default_value Value if the key does not exist in the array
		* @return mixed
		*/
if ( ! function_exists( 'o_get_proper_value' ) ) {
	function o_get_proper_value( $values, $search_key, $default_value = '' ) {
		if ( isset( $values[ $search_key ] ) ) {
			$default_value = $values[ $search_key ];
		}
		return $default_value;
	}
}


if ( ! function_exists( 'o_explodeX' ) ) {
	function o_explodeX( $delimiters, $string ) {
		return explode( chr( 1 ), str_replace( $delimiters, chr( 1 ), $string ) );
	}
}


	/**
		* Returns a media URL
	 *
		* @param type $media_id Media ID
		* @return type
		*/
if ( ! function_exists( 'o_get_media_url' ) ) {
	function o_get_media_url( $media_id ) {
		 $attachment    = wp_get_attachment_image_src( $media_id, 'full' );
		$attachment_url = $attachment[0];
		return $attachment_url;
	}
}

if ( ! function_exists( 'o_find_in_array_by_key' ) ) {
	function o_find_in_array_by_key( $root_value, $key ) {
		$bracket_pos         = strpos( $key, '[' );
		$usable_value_index  = substr( $key, $bracket_pos );
		$search              = array( '[', ']' );
		$usable_value_index2 = str_replace( $search, '', $usable_value_index );

		if ( is_array( $root_value ) && ( isset( $root_value[ $usable_value_index2 ] ) ) ) {
			return $root_value[ $usable_value_index2 ];
		}
		return false;
	}
}


if ( ! function_exists( 'o_get_proper_image_url' ) ) {
	function o_get_proper_image_url( $suspected_link, $with_root = true ) {
		 // var_dump($suspected_link);
		if ( empty( $suspected_link ) ) {
			return $suspected_link;
		}
		$img_src = $suspected_link;
		if ( is_numeric( $suspected_link ) ) {
			$raw_img_src = wp_get_attachment_url( $suspected_link );
			$img_src     = str_replace( o_get_medias_root_url( '/' ), '', $raw_img_src );
		}
		$img_src = str_replace( o_get_medias_root_url( '/' ), '', $img_src );
		// Code for bad https handling
		if ( strpos( o_get_medias_root_url( '/' ), 'https' ) === false ) {
			$https_home = str_replace( 'http', 'https', o_get_medias_root_url( '/' ) );
			$img_src    = str_replace( $https_home, '', $img_src );
		}

		if ( $with_root ) {
			$img_src = o_get_medias_root_url( "/$img_src" );
		}
		return $img_src;
	}
}


if ( ! function_exists( 'o_startsWith' ) ) {
	function o_startsWith( $haystack, $needle ) {
		// search backwards starting from haystack length characters from the end
		return $needle === '' || strrpos( $haystack, $needle, -strlen( $haystack ) ) !== false;
	}
}


if ( ! function_exists( 'o_endsWith' ) ) {
	function o_endsWith( $haystack, $needle ) {
		 // search forward starting from end minus needle length characters
		return $needle === '' || ( ( $temp = strlen( $haystack ) - strlen( $needle ) ) >= 0 && strpos( $haystack, $needle, $temp ) !== false );
	}
}


if ( ! function_exists( 'o_get_medias_root_url' ) ) {
	function o_get_medias_root_url( $path = '/' ) {
		 $upload_url_path = get_option( 'upload_url_path' );
		if ( $upload_url_path ) {
			return $upload_url_path . $path;
		} else {
			return site_url( $path );
		}
	}
}


if ( ! function_exists( 'o_get_google_fonts_selector' ) ) {
	function o_get_google_fonts_selector( $selected_font = false, $id = '', $name = '', $style = '', $class = '' ) {
		$file_path       = plugin_dir_path( __FILE__ ) . 'googlefont.json';
		$fonts_json_file = fopen( $file_path, 'r' );
		$font_content    = fread( $fonts_json_file, filesize( $file_path ) );
		fclose( $fonts_json_file );
		$decoded_fonts = json_decode( $font_content, true );
		// var_dump($decoded_fonts);
		?>
<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" style="<?php echo esc_attr( $style ); ?>" class="o-google-font-selector <?php echo esc_attr( $class ); ?>">
		<?php
		echo wp_kses_post( '<option value="">Pick a font</option>' );
		foreach ( $decoded_fonts['items'] as $font ) {
			if ( isset( $font['family'] ) and isset( $font['files'] ) and isset( $font['files']['regular'] ) ) {
				$selected    = '';
				$field_value = 'http://fonts.googleapis.com/css?family=' . urlencode( $font['family'] ) . '|' . $font['family'] . '|' . $font['category'];
				if ( $selected_font == $field_value ) {
					$selected = 'selected';
				}
				echo wp_kses_post( '<option value="' . $field_value . '" ' . $selected . '>' . $font['family'] . '</option> ' );
			}
		}
		?>
</select>
		<?php
		return $decoded_fonts['items'];
	}
}

if ( ! function_exists( 'o_register_google_font' ) ) {
	function o_register_google_font( $font_name, $raw_url ) {
		$font_url = str_replace( 'http://', '//', $raw_url );
		if ( $font_url ) {
			$handler = sanitize_title( $font_name );
			wp_register_style( $handler, $font_url, array(), false, 'all' );
			wp_enqueue_style( $handler );
		}
	}
}

if ( ! function_exists( 'o_get_repeatable_field_table_rows' ) ) {
	function o_get_repeatable_field_table_rows( $repeatable_field_settings, $repeatable_fields_data ) {
		foreach ( $repeatable_fields_data as $i => $row ) {
			echo wp_kses_post( "<tr class='" . $repeatable_field_settings['row_class'] . "'>" );
			foreach ( $repeatable_field_settings['fields'] as $field ) {
				if ( isset( $row[ $field['name'] ] ) ) {
					$field_value = $row[ $field['name'] ];
				} else {
					$field_value = '';
				}
				$field['name'] = $repeatable_field_settings['name'] . "[$i][" . $field['name'] . ']';
				// If it's a grouped field
				if ( $field['type'] == 'groupedfields' ) {
					foreach ( $field['fields'] as $grouped_field_index => $grouped_field_item ) {
						$field['fields'][ $grouped_field_index ]['name'] = $repeatable_field_settings['name'] . "[$i][" . $grouped_field_item['name'] . ']';
					}
				} elseif ( $field['type'] == 'image' && isset( $field['url_name'] ) ) {
					$field['url_name'] = $repeatable_field_settings['name'] . "[$i][" . $field['url_name'] . ']';
				}
				$field['default']         = $field_value;
				$field['ignore_desc_col'] = true;

				echo wp_kses_post( o_admin_fields( array( $field ) ) );
			}
			?>
<td>
<a class="remove-rf-row"></a>
</td>
			<?php
			echo wp_kses_post( '</tr>' );
		}
	}
}
