add_action('init', function() {
	
	set_time_limit(30000);
	ini_set('memory_limit', '-1');

	foreach ( get_intermediate_image_sizes() as $size ) {
        remove_image_size( $size );
    }

	function get_product_cats_ids($str) {

		$cats = explode(' ## ', $str);
	
		foreach( $cats as $key => $val ) {
	
			if( $val == 'Каталог' ) {
				unset($cats[$key]);
			}
	
			if( $val == 'Каталог/Техника по уходу за одеждой' ) {
				unset($cats[$key]);
			}
	
			if( $val == 'Каталог/Техника для кухни' ) {
				unset($cats[$key]);
			}
			
			if( strpos($val, 'Каталог/Выбор по брендам') !== false ) {
				unset($cats[$key]);
			}
	
		}
	
		$content = array_shift($cats);
		
		$cats = explode('/', $content);
		
		$product_cat = array_pop($cats);
	
		$category_ids = [];
		if( $term = get_term_by( 'name', $product_cat, 'product_cat' ) ) {
			$category_ids[] = $term->term_id;
		} else {
			$new_term = wp_insert_term($product_cat, 'product_cat');
			$category_ids[] = $new_term['term_id'];
		}
		
		return $category_ids;
	}
	
	function get_product_images_ids($str) {
		$image_urls = explode(' ', $str);
	
		$image_ids = [];
		foreach( $image_urls as $url ) {
			$image_ids[] = rudr_upload_file_by_url($url);
		}
	
		return $image_ids;
	}

	function rudr_upload_file_by_url( $image_url ) {;
		$upload_dir = wp_upload_dir();

		$image_data = file_get_contents( $image_url );

		$filename = basename( $image_url );

		if ( wp_mkdir_p( $upload_dir['path'] ) ) {
		$file = $upload_dir['path'] . '/' . $filename;
		}
		else {
		$file = $upload_dir['basedir'] . '/' . $filename;
		}

		file_put_contents( $file, $image_data );

		$wp_filetype = wp_check_filetype( $filename, null );

		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => sanitize_file_name( $filename ),
			'post_content' => '',
			'post_status' => 'inherit'
		);

		$attach_id = wp_insert_attachment( $attachment, $file );
		// require_once( ABSPATH . 'wp-admin/includes/image.php' );
		// $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		// wp_update_attachment_metadata( $attach_id, $attach_data );

		return $attach_id;
	}

	$products_csv_path = get_template_directory() . '/products.csv';

	if( ($file = fopen($products_csv_path, 'r')) !== false ) {
		$counter = 0;
		$titles = [];
		
		while( ($data = fgetcsv($file, 1000000, ';')) !== false ) {

			$counter++;
			if( $counter == 1 ) continue;

			// if( $counter == 4 ) break;

			// $range_from = 0;
			// $range_to = 10;
			// if( $counter >= $range_to ) {
			// 	break;
			// }
			// if( $counter <= $range_from ) {
			// 	continue;
			// }

			// foreach( wc_get_attribute_taxonomies() as $key => $args ) {
			// 	wc_delete_attribute($args->attribute_id);
			// }
			break;
				
			if( $counter == 2 ) {
				
				$titles = $data;
				
                @array_shift($titles);
                @array_shift($titles);
                @array_shift($titles);
                @array_shift($titles);
                @array_shift($titles);
                @array_shift($titles);
                @array_shift($titles);

			} else {

				$product_id = @array_shift($data);
				$product_name = @array_shift($data);
				$product_description = @array_shift($data);
				$product_cats_ids = get_product_cats_ids(@array_shift($data));
				$product_images_ids = @get_product_images_ids(array_shift($data));
				// $product_images_ids = [5]; array_shift($data);
				$product_sku = @array_shift($data);
				$product_price = str_replace(',0', '', @array_shift($data));

				$product = new WC_Product_Simple();
				$product->set_name(	$product_name );
				$product->set_sku( $product_sku );
				$product->set_description( $product_description );
				$product->set_category_ids( $product_cats_ids );

				$product_image_id = array_shift($product_images_ids);
				$product->set_image_id( $product_image_id );
				if( ! empty( $product_images_ids ) ) {
					$product->set_gallery_image_ids( $product_images_ids );
				}

				$product->set_regular_price( $product_price );
				
				$attributes = [];
				for( $i=0; $i<count($data); $i++ ) {
					$attr_name = str_replace('Параметр: ', '', $titles[$i]);
					$attr_value = $data[$i];

					if( $attr_value == '' ) {
						continue;
					}

					// дополнительнуые данные в индивидуальные аттрибут
					$personal_attrs = [
						'Дополнительные данные',
						'Дополнительные возможности',
						'Дополнительные аксессуары (приобретаются отдельно)',
						'Дополнительная информация',
						'Дополнительная комплектация',
						'Дополнительные функции',
						'Дополнительные данные и особенности морозильной камеры',
						'Дополнительные данные и особенности холодильной камеры',
						'Дополнительные параметры',
						'Ширина',
						"Ширина, см",
						'Вес брутто (кг)',
						'Вес (кг)',
						'Вес нетто (кг)',
						'Высота',
						'Глубина',
						'Общий объем (л)',
						'Объем (л)',
					];

					$attribute = new WC_Product_Attribute();
					if( in_array($attr_name, $personal_attrs) ) {

						$p_attr_name = $attr_name;
						$p_attr_options = [$attr_value];
						$p_attr_position = 200;

					} else {

						$tax_id = wc_attribute_taxonomy_id_by_name( $attr_name );

						if( ! $tax_id ) {
							$tax_id = wc_create_attribute([
								'name' => $attr_name,
								'type' => 'select',
								'order_by' => 'menu_order',
								'has_archives' => 0,
							]);
						}
	
						$taxonomy_slug = wc_attribute_taxonomy_slug( $attr_name );
	
						$woo_style_term = 'pa_' . $taxonomy_slug;
						@register_taxonomy($woo_style_term, ['product'], []);
	
						$term = term_exists($attr_value, $woo_style_term);
						if( $term == null ) {
							$term = wp_insert_term($attr_value, $woo_style_term);
						}
	
						$term_id = @array_shift($term);
						settype($term_id, 'int');

						$attribute->set_id( $tax_id );
						$p_attr_name = $woo_style_term;
						$p_attr_position = 1;
						$p_attr_options = [$term_id];
					}

					$attribute->set_name( $p_attr_name );
					$attribute->set_options( $p_attr_options );
					$attribute->set_position( $p_attr_position );
					$attribute->set_visible( true );
					$attribute->set_variation( false );

					$attributes[$woo_style_term] = $attribute;
				}

				$product->set_attributes( $attributes );

				$product->save();
			}
			
		}

	}

});
