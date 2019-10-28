<?php
class CMB2_Plupload {

	function __construct() {
		add_action( 'cmb2_render_plupload', array( $this, 'cmb2_render_plupload'), 10, 5 );
		add_filter( 'cmb2_sanitize_plupload', array( $this, 'cmb2_sanitize_plupload'), 10, 5 );
		add_action('wp_ajax_plupload_upload_action', array( $this, 'plupload_save_file'), 10, 2);
		add_action('wp_ajax_plupload_remove_action', array( $this, 'plupload_remove_file'), 10, 2);
		add_action('wp_ajax_plupload_save_action', array( $this, 'plupload_save_field'), 10, 2);
	}

	/**
	 *  Render field
	 */
	public function cmb2_render_plupload( $field, $value, $object_id, $object_type, $field_type ) {

		// Only enqueue scripts if field is used.
		$this->setup_admin_scripts();

		// make sure we specify each part of the value we need.
		$args = wp_parse_args( $field->args, array(
			'multiple_files' => false, //allow upload multiple files
			'upload_dir'     => 'plupload', //default upload dir in wp-uploads directory
			'allowed_files'  => '*', //allowed files to upload
			'dragdrop'       => true, //drag and drop
			'custom_path'    => false, //allow set custom path
			'custom_name'    => false, //allow set custom name for uploaded file
			'fill_field'     => false, //fill selected field with url after upload
			'save_data'      => true, //save the data about uploaded files
			'upload_button'  => false, //custom upload button
			'upload_label' => 'Upload file(s)',
			'select_label'   => 'Select File(s)', //default button label
			'area_label'     => 'Drop file(s) here', //default drag and drop area label
		) );

		$field_value = $field->value; //not escaped because of json
		$id = $args['id'];

		$field_id = $id;
		$group_id = ( !empty($field->group) ) ? $field->group->args['id'] : '';
		$group_field_id = $args['_id'];
		$group_field_name = $args['_name'];
		$settings_id = $field->cmb_id;

		$container = 'plupload-upload-ui' . $id;
		$select_button = 'plupload-browse-button' . $id;
		$dragarea = 'plupload-drag-area' . $id;

		$multiple_files = (bool) $args['multiple_files'];
		$upload_dir     = (string) $args['upload_dir'];
		$allowed_files  = (string) $args['allowed_files'];
		$dragdrop       = (bool) $args['dragdrop'];

		$custom_path    = (bool) $args['custom_path'];
		$custom_name    = (bool) $args['custom_name'];
		$fill_field     = (string) $args['fill_field'];

		$upload_button = (bool) $args['upload_button'];
		if( $custom_path || $custom_name ) {
			$upload_button = true;
		}

		$save_data      = (bool) $args['save_data'];

		$select_label   = (string) $args['select_label'];
		$area_label     = (string) $args['area_label'];
		$upload_label   = (string) $args['upload_label'];

		$plupload_init = array(
			'runtimes'            => 'html5,silverlight,flash,html4',
			'container'           => $container,
			'browse_button'       => $select_button,
			'drop_element'        => $dragarea,
			'file_data_name'      => 'async-upload',
			'multiple_queues'     => true,
			'max_file_size'       => wp_max_upload_size() . 'b',
			'url'                 => admin_url('admin-ajax.php'),
			'flash_swf_url'       => includes_url('js/plupload/plupload.flash.swf'),
			'silverlight_xap_url' => includes_url('js/plupload/plupload.silverlight.xap'),
			'filters'             => array(
										array(
											'extensions' => $allowed_files
										)
									),
			'urlstream_upload'    => true,
			'multipart'           => true,
			// additional post data to send to our ajax hook
			'multipart_params'    => array(
				'_ajax_nonce'      => wp_create_nonce('plupload_upload_nonce'),
				'action'           => 'plupload_upload_action', // the ajax action name
				'upload_dir'       => $upload_dir,
				'settings_id'      => $settings_id,
				'field_id'         => $field_id,
				'group_id'         => $group_id,
				'group_field_id'   => $group_field_id,
				'group_field_name' => $group_field_name,
			),
		);

		$js_params = array(
			'ajax_url'         => admin_url('admin-ajax.php'),
			'nonce'            => wp_create_nonce('plupload_nonce'),
			'container'        => $container,
			'settings_id'      => $settings_id,
			'field_id'         => $field_id,
			'group_id'         => $group_id,
			'group_field_id'   => $group_field_id,
			'group_field_name' => $group_field_name,
			'object_id'        => $object_id,
			'multiple'         => $multiple_files,
			'dragdrop'         => $dragdrop,
			'upload_dir'       => $upload_dir,
			'fill_field'       => $fill_field,
			'plupload'         => $plupload_init,
		);

		?>

		<div id="<?= $container ?>">
			<div class="uploaded">
				<ul class="cmb2-media-status cmb-attach-list">
				<?php 
					$valueArr = json_decode($field_value);
					if( !empty($valueArr) ) {
						foreach( $valueArr as $v ) {
							if( strpos($v->type, 'image') !== false ) {
								echo '<li class="img-status cmb2-media-item" data-json=\'' . json_encode((array)$v) . '\'><img src="' . esc_url($v->url) . '" alt="' . esc_url($v->url) . '"> <a href="#" class="cmb2-remove-file-button">' . __('Remove', 'instalki') . '</a></li>';
							} else {
								echo '<li class="file-status cmb2-media-item" data-json=\'' . json_encode((array)$v) . '\'><span>' . esc_url($v->url) . '</span> <a href="#" class="cmb2-remove-file-button">' . __('Remove', 'instalki') . '</a>  <a href="#" class="cmb2-copy-file-button">Copy URL</a></li>';
							}
						}
					}
				?>
				</ul>
			</div>

			<?php if( $custom_path || $custom_name ) { ?>
			<div class="additional-fields">
				<?php if( $custom_path ) {
					$remote = ( !empty($group_id) ) ? $field->args['fields'][$group_field_id]['remote'] : $field->args['remote'];
					if( !empty($remote) ) {
						$url = ( !empty($remote['url']) ) ? rtrim($remote['url'],"/") : rtrim($remote['host'],"/") . '/' . $upload_dir;
					} else {
						$url = $upload_dir;
					}
					echo ' <label>Upload path [ <small>' . $url . '/</small> ]:</label> ';

					echo $field_type->input( array(
						'class'       => 'cmb_text_small',
						'name'        => '', //empty name because we do not want to save the data
						'id'          => $field_id . '_cpath',
						'desc'        => '',
						'value'       => '',
						'placeholder' => 'additional path',
					) );
				}

				if( $custom_name ) {
					echo ' <label>File name:</label> ';
					echo $field_type->input( array(
						'class'       => 'cmb_text_small',
						'name'        => '', //empty name because we do not want to save the data
						'id'          => $field_id . '_cname',
						'desc'        => '',
						'value'       => '',
						'placeholder' => 'new file name',
					) );
				}
				?>
			</div>
			<?php } ?>

			<div id="<?= $dragarea ?>" class="drag-drop-area">
				<div class="drag-drop-inside">
					<p class="drag-drop-info"><?= $area_label; ?></p>
					<p class="drag-drop-buttons"><input id="<?= $select_button ?>" type="button" value="<?= $select_label; ?>" class="button" /></p>
				</div>
			</div>
			
			<div class="progress">
				<div class="bar"></div>
			</div>
			
			<div class="logs"></div>

			<?php if( $upload_button ) { ?>
			<div class="upload-button">
				<button class="cmb2-upload-file-button button button-primary"><?= $upload_label; ?></button>
			</div>
			<?php } ?>
			
			<?php
				//hidden field to store data
				if( $save_data ) {
					echo $field_type->textarea( array(
						'id'     => $id,
						'class'  => 'hidden',
					) );
				}
			?>
		</div>

		<script type="text/javascript">
			jQuery(document).ready(function(){
				new CMB2_Plupload(<?= json_encode($js_params); ?>);
			});
		</script>
		<?php
	}

	/**
	 * Sanitize field value
	 *
	 * @param [mixed] $override_value
	 * @param [mixed] $value
	 * @param [int] $object_id
	 * @param [array] $field_args
	 * @return void
	 */
	public function cmb2_sanitize_plupload( $override_value, $value, $object_id, $field_args ) {
		//? not needed anymore?
		//workaround: if value is empty update field manually
		// if( empty($value) ) {
		// 	update_post_meta( $object_id, $field_args['id'], '' );
		// }
		//return unescaped value (json)
		return $value;
	}

	/**
	 * Save uploaded file
	 *
	 * @return void
	 */
	public function plupload_save_file() {
		check_ajax_referer('plupload_upload_nonce');

		$file = $_FILES['async-upload'];

		$settings_id = sanitize_text_field($_POST['settings_id']);
		$field_id = sanitize_text_field($_POST['field_id']);
		$group_id = sanitize_text_field($_POST['group_id']);
		$group_field_id = sanitize_text_field($_POST['group_field_id']);
		$new_file_name = sanitize_file_name($_POST['file_name']);

		//get field data depends on group or single field
		$field = ( !empty($group_id) ) ? cmb2_get_field( $settings_id, $group_id ) : cmb2_get_field( $settings_id, $field_id );

		//get remote params
		$remote = ( !empty($group_id) ) ? $field->args['fields'][$group_field_id]['remote'] : $field->args['remote'];

		//save file to remote FTP server
		if( !empty($remote) ) {
			$host = $remote['host'];
			$username = $remote['login'];
			$password = $remote['password'];

			$custom_dir = ( !empty($_POST['custom_dir']) ) ? '/' . trim($_POST['custom_dir'], '/') : '';

			//upload dir with custom path
			$upload_dir = rtrim($_POST['upload_dir'], '/') . $custom_dir;

			//url with custom path
			$url = ( !empty($remote['url']) ) ? rtrim($remote['url'],"/") . $custom_dir : rtrim($host,"/") . '/' . $upload_dir;

			$local_file = $file['tmp_name'];

			//original or new file name
			$remote_file = ( !empty($_POST['custom_name']) ) ? sanitize_file_name($_POST['custom_name']) : $file['name'];

			// connect and login to FTP server
			$ftp_conn = ftp_connect($host);
			$login = ftp_login($ftp_conn, $username, $password);
			if( !$ftp_conn || !$login ) {
				wp_send_json_error('FTP: connection error');
			}

			if( !empty($upload_dir) ) {
				//create directory if not exists
				if (ftp_nlist($ftp_conn, $upload_dir) == false) {
					ftp_mkdir($ftp_conn, $upload_dir);
				}
				//change directory to upload dir
				ftp_chdir($ftp_conn, $upload_dir);
			}

			$path = ftp_pwd($ftp_conn);
			$contents_on_server = ftp_nlist($ftp_conn, $path);

			// check if file exists
			if( in_array($remote_file, $contents_on_server) ) {
				wp_send_json_error('FTP: file already exists (' . $remote_file . ')');
			}

			$file_path = rtrim($path,"/") . '/' . $remote_file;
			$file_url  = rtrim($url,"/") . '/' . $remote_file;

			// upload file
			if ( ftp_put($ftp_conn, $remote_file, $local_file) ) {
	
				$response = new stdClass();
				$response->file = $file_path;
				$response->url = $file_url;
				$response->type = $file['type'];

				ftp_close($ftp_conn);
				wp_send_json($response);

			} else {
				ftp_close($ftp_conn);
				wp_send_json_error('FTP: error uploading (' . $remote_file . ')');
			}

		//save fale locally
		} else {

			//check if custom directory is set
			if( !empty($_POST['upload_dir']) ) {

				function custom_upload_dir( $upload ) {

					$custom_dir = ( !empty($_POST['custom_dir']) ) ? '/' . trim($_POST['custom_dir'], '/') : '';

					//upload dir with custom path
					$upload_dir = trim($_POST['upload_dir'], '/') . $custom_dir;

					$upload['subdir'] = '/' . $upload_dir;
					$upload['path']   = $upload['basedir'] . $upload['subdir'];
					$upload['url']    = $upload['baseurl'] . $upload['subdir'];

					//print_r($upload);
					return $upload;
				}
				// Register our path override.
				add_filter( 'upload_dir', 'custom_upload_dir' );
			}

			// wp handle local upload
			$status = wp_handle_upload($file, array('test_form' => true, 'action' => 'plupload_upload_action'));

			if( !empty($_POST['upload_dir']) ) {
				// Set everything back to normal.
				remove_filter( 'upload_dir', 'custom_upload_dir' );
			}

			//TODO resize uploaded file/generate thumbnail(s) wp_get_image_editor

			if( $status ) {
				// output the results
				wp_send_json($status);
			} else {
				wp_send_json_error('Upload error');
			}

		}

	}

	/**
	 * Remove uploaded file
	 *
	 * @return void
	 */
	public function plupload_remove_file() {
		check_ajax_referer('plupload_nonce');
		//print_r($_POST);

		$settings_id = sanitize_text_field($_POST['settings_id']);
		$field_id = sanitize_text_field($_POST['field_id']);
		$group_id = sanitize_text_field($_POST['group_id']);
		$group_field_id = sanitize_text_field($_POST['group_field_id']);

		$path = sanitize_text_field($_POST['path']);

		//get field data depends on group or single field
		$field = ( !empty($group_id) ) ? cmb2_get_field( $settings_id, $group_id ) : cmb2_get_field( $settings_id, $field_id );

		//get remote params
		$remote = ( !empty($group_id) ) ? $field->args['fields'][$group_field_id]['remote'] : $field->args['remote'];

		//remove file on FTP
		if( !empty($remote) ) {

			$host = $remote['host'];
			$username = $remote['login'];
			$password = $remote['password'];

			// connect and login to FTP server
			$ftp_conn = ftp_connect($host);
			$login = ftp_login($ftp_conn, $username, $password);
			if( !$ftp_conn || !$login ) {
				ftp_close($ftp_conn);
				wp_send_json_error('FTP: connection error');
			}

			if( !ftp_delete($ftp_conn, $path) ) {
				ftp_close($ftp_conn);
				wp_send_json_error('FTP: cannot remove file (' . $path . ')');
			}

		} else {
			//if local path, remove file
			if( !empty($path) ) {
				wp_delete_file($_POST['path']);
			}
		}

		wp_send_json('File removed.');
	}

	/**
	 * Save field after upload finished
	 *
	 * @return void
	 */
	public function plupload_save_field() {
		check_ajax_referer('plupload_nonce');
		//print_r($_POST);

		$object_id = sanitize_text_field($_POST['object_id']);
		$field_id = sanitize_text_field($_POST['field_id']);
		$group_id = sanitize_text_field($_POST['group_id']);
		$group_field_id = sanitize_text_field($_POST['group_field_id']);
		$field_value = $_POST['field_value'];

		//save field after upload
		if( !empty($object_id) && !empty($field_id) ) {

			if( !empty($group_id) ) { //save data if group

				//get number of field
				$i = abs((int) filter_var($field_id, FILTER_SANITIZE_NUMBER_INT));
				//get current value
				$value = get_post_meta( $object_id, $group_id, true );
				//add new value
				$value[$i][$group_field_id] = $field_value;
				update_post_meta( $object_id, $group_id, $value );

			} else { //save data if regular field
				update_post_meta( $object_id, $field_id, $field_value );
			}

		}
		wp_send_json('Field saved.');

	}

	/**
	 * Load scripts
	 */
	public function setup_admin_scripts() {
		wp_enqueue_script('plupload-all');
		wp_enqueue_script( 'cmb2_plupload_js', plugin_dir_url( __FILE__ ) . 'assets/cmb2_plupload.js', array( 'jquery' ), '1.00' );
	}

}

$cmb2_plupload = new CMB2_Plupload;