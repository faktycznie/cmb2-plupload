<?php
/*
Plugin Name: CMB2 Plupload field
Plugin URI: https://github.com/faktycznie/cmb2-plupload
Description: CMB2 developer's toolkit Upload field with drag and drop, thumbnails and more.
Version: 1.01
Author: Artur Kaczmarek
Text Domain: cmb2_plupload
Author URI: https://github.com/faktycznie
License: GPL2
Tags: plupload, upload, cmb2
*/

if ( !defined( 'ABSPATH' ) ) exit;

if( !class_exists('Plupload_CMB2') ) {
	class Plupload_CMB2 {

		protected static $single_instance = null;

		function __construct() {
			add_action( 'cmb2_render_plupload', [$this, 'renderField'], 10, 5 );
			add_filter( 'cmb2_sanitize_plupload', [$this, 'sanitizeField'], 10, 5 );
			add_action( 'wp_ajax_plupload_upload_action', [$this, 'saveFile'], 10, 2 );
			add_action( 'wp_ajax_plupload_remove_action', [$this, 'removeFile'], 10, 2 );
			add_action( 'wp_ajax_plupload_save_action', [$this, 'saveField'], 10, 2 );
		}

		/**
		 * Creates or returns an instance of this class.
		 * @return A single instance of this class.
		 */
		public static function getInstance() {
			if ( null === self::$single_instance ) {
				self::$single_instance = new self();
			}
			return self::$single_instance;
		}

		/**
		 * Render CMB2 Field
		 */
		public function renderField( $field, $value, $object_id, $object_type, $field_type ) {

			// Only enqueue scripts if field is used.
			$this->setupAdminScripts();

			// make sure we specify each part of the value we need.
			$args = wp_parse_args( $field->args, array(
				'multiple_files'        => false, // allow upload multiple files
				'upload_dir'            => 'plupload', // default upload dir in wp-uploads directory
				'upload_alpha'          => false, // organize files alphabetically
				'allowed_files'         => '*', // allowed files to upload
				'dragdrop'              => true, // drag and drop
				'custom_path'           => false, // allow set custom path
				'custom_name'           => false, // allow set custom name for uploaded file
				'fill_field'            => false, // fill selected field with url after upload
				'save_data'             => true, // save the data about uploaded files
				'upload_button'         => false, // custom upload button
				'upload_label'          => __('Upload file(s)', 'cmb2_plupload'),
				'select_label'          => __('Select File(s)', 'cmb2_plupload'), // default button label
				'area_label'            => __('Drop file(s) here', 'cmb2_plupload'), // default drag and drop area label
			) );

			$field_value    = $field->value; // not escaped because of json
			$id             = $args['id'];

			$field_id       = $id;
			
			$group_id       = ( !empty($field->group) ) ? $field->group->args['id'] : '';
			$group_key      = ( !empty($field->group) ) ? $args['_id'] : '';

			$settings_id    = $field->cmb_id;

			$container      = 'plupload-upload-ui' . $id;
			$select_button  = 'plupload-browse-button' . $id;
			$dragarea       = 'plupload-drag-area' . $id;

			$multiple_files = (bool) $args['multiple_files'];
			$upload_dir     = (string) $args['upload_dir'];
			$upload_alpha   = (bool) $args['upload_alpha'];
			$allowed_files  = (string) $args['allowed_files'];
			$dragdrop       = (bool) $args['dragdrop'];

			$custom_path    = (bool) $args['custom_path'];
			$custom_name    = (bool) $args['custom_name'];
			$fill_field     = (string) $args['fill_field'];

			$upload_button  = (bool) $args['upload_button'];
			if( $custom_path || $custom_name ) { // we need upload button when want to set custom dirs/names
				$upload_button = true;
			}

			$save_data      = (bool) $args['save_data'];

			$select_label   = (string) $args['select_label'];
			$area_label     = (string) $args['area_label'];
			$upload_label   = (string) $args['upload_label'];

			$remote         = ( !empty($args['remote']) ) ? $args['remote'] : false;

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
					'_ajax_nonce' => wp_create_nonce('plupload_upload_nonce'),
					'action'      => 'plupload_upload_action', // ajax action name
					'upload_dir'  => $upload_dir,
					'settings_id' => $settings_id,
					'field_id'    => $field_id,
					'group_id'    => $group_id,
					'group_key'   => $group_key,
				),
			);

			$js_params = array(
				'ajax_url'         => admin_url('admin-ajax.php'),
				'nonce'            => wp_create_nonce('plupload_nonce'),
				'container'        => $container,
				'settings_id'      => $settings_id,
				'field_id'         => $field_id,
				'group_id'         => $group_id,
				'group_key'        => $group_key,
				'object_id'        => $object_id,
				'multiple'         => $multiple_files,
				'dragdrop'         => $dragdrop,
				'upload_dir'       => $upload_dir,
				'upload_alpha'     => $upload_alpha,
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
								if( false !== strpos($v->type, 'image') ) {
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
						if( $remote ) {
							$url = ( !empty($remote['url']) ) ? rtrim($remote['url'], '/') : rtrim($remote['host'], '/') . '/' . $upload_dir;
						} else {
							$url = $upload_dir;
						}

						echo ' <label>' . __('Upload path', 'cmb2_plupload') . ' <small>( ' . $url . ' )</small>:</label> ';
						echo $field_type->input( array(
							'class'       => 'cmb_text_small',
							'name'        => '', // empty name because we do not want to save the data
							'id'          => $field_id . '_cpath',
							'desc'        => '',
							'value'       => '',
							'placeholder' => __('additional path', 'cmb2_plupload'),
						) );
					}

					if( $custom_name ) {
						echo ' <label>' . __('File name:', 'cmb2_plupload') . '</label> ';
						echo $field_type->input( array(
							'class'       => 'cmb_text_small',
							'name'        => '', // empty name because we do not want to save the data
							'id'          => $field_id . '_cname',
							'desc'        => '',
							'value'       => '',
							'placeholder' => __('new file name', 'cmb2_plupload'),
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
				<?php }
					// hidden field to store data only if we need to store the data
					if( $save_data ) {
						echo $field_type->textarea( array(
							'id'     => $id,
							'class'  => 'data-holder hidden',
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
		public function sanitizeField( $override_value, $value, $object_id, $field_args ) {
			//TODO: sanitize data
			return $value;
		}

		/**
		 * Get field object
		 *
		 * @param string $field_id
		 * @param string $settings_id
		 * @param mixed $group_id group id if field is in group
		 * @return object
		 */
		public function getField( $field_id, $settings_id, $group_id = '' ) {
			return ( !empty($group_id) ) ? cmb2_get_field( $settings_id, $group_id ) : cmb2_get_field( $settings_id, $field_id );
		}

		/**
		 * Get field params
		 *
		 * @param string $param parameter name
		 * @param object $field field obejct
		 * @param string $group_key group key if field is in group
		 * @return mixed
		 */
		public function getParam( $param, $field, $group_key = '' ) {
			$value = ( !empty($group_key) ) ? $field->args['fields'][$group_key][$param] : $field->args[$param];
			return ( !empty($value) ) ? $value : false;
		}

		/**
		 * Save uploaded file
		 *
		 * @return void
		 */
		public function saveFile() {
			check_ajax_referer('plupload_upload_nonce');

			$file = $_FILES['async-upload'];

			$settings_id = sanitize_text_field( $_POST['settings_id'] );
			$field_id    = sanitize_text_field( $_POST['field_id'] );
			$group_id    = sanitize_text_field( $_POST['group_id'] );
			$group_key   = sanitize_text_field( $_POST['group_key'] );

			$upload_dir   = trim( $_POST['upload_dir'], '/' );
			$upload_alpha = sanitize_file_name( $_POST['upload_alpha'] );
			$custom_dir   = trim( $_POST['custom_dir'], '/' );
			$custom_name  = sanitize_file_name( $_POST['custom_name'] );

			// get field data depends on group or single field
			$field = $this->getField( $field_id, $settings_id, $group_id);

			// get field parameters
			$remote     = $this->getParam('remote', $field, $group_key);
			$thumbnails = $this->getParam('thumbnails', $field, $group_key);

			$data = new stdClass;
			$data->upload_dir   = $upload_dir;
			$data->upload_alpha = $upload_alpha;
			$data->custom_dir   = $custom_dir;
			$data->custom_name  = $custom_name;
			$data->thumbnails   = $thumbnails;

			// save file to remote FTP server
			if( $remote ) {

				$data->host = ( !empty($remote['host']) ) ? $remote['host'] : false;
				$data->user = ( !empty($remote['login']) ) ? sanitize_user($remote['login']) : false;
				$data->pass = ( !empty($remote['password']) ) ? sanitize_text_field($remote['password']) : false;
				$data->url  = ( !empty($remote['url']) ) ? esc_url_raw($remote['url']) : false;

				$this->uploadRemoteFile($file, $data);

			} else { // save file locally
				$this->uploadLocalFile($file, $data);
			}
		}

		/**
		 * Upload file to remote FTP
		 *
		 * @param object $file
		 * @param object $data
		 * @return void
		 */
		private function uploadRemoteFile( $file, $data ) {

			// custom path if needed
			$custom_dir = ( !empty($data->custom_dir) ) ? '/' . $data->custom_dir : '';

			// upload dir with custom path
			$upload_dir = $data->upload_dir . $custom_dir;

			// url with custom path
			$url = ( !empty($data->url) ) ? rtrim($data->url,'/') . $custom_dir : $data->host . '/' . $upload_dir;

			$local_file = $file['tmp_name'];

			// original or new file name
			$remote_file = ( !empty($data->custom_name) ) ? $data->custom_name : $file['name'];

			// connect and login to FTP server
			$ftp_conn = ftp_connect($data->host);
			$login = ftp_login($ftp_conn, $data->user, $data->pass);
			if( !$ftp_conn || !$login ) {
				wp_send_json_error('FTP: connection error');
			}

			if( !empty($upload_dir) ) {
				// create directory if not exists
				if (ftp_nlist($ftp_conn, $upload_dir) == false) {
					ftp_mkdir($ftp_conn, $upload_dir);
				}
				// change directory to upload dir
				ftp_chdir($ftp_conn, $upload_dir);
			}

			$path = ftp_pwd($ftp_conn);
			$contents_on_server = ftp_nlist($ftp_conn, $path);

			// check if file exists
			if( in_array($remote_file, $contents_on_server) ) {
				wp_send_json_error('FTP: file already exists (' . $remote_file . ')');
			}

			$file_path = rtrim($path,'/') . '/' . $remote_file;
			$file_url  = rtrim($url,'/') . '/' . $remote_file;

			// upload file
			if ( ftp_put($ftp_conn, $remote_file, $local_file, FTP_BINARY) ) {

				$resp = new stdClass();
				$resp->file = $file_path;
				$resp->url  = $file_url;
				$resp->type = $file['type'];

				ftp_close($ftp_conn);
				wp_send_json($resp);

			} else {
				ftp_close($ftp_conn);
				$msg = error_get_last();
				wp_send_json_error('FTP: error ( ' . $remote_file . ' ): ' . $msg['message']);
			}
		}

		/**
		 * Upload file locally
		 *
		 * @param object $file
		 * @param object $data
		 * @return void
		 */
		private function uploadLocalFile( $file, $data ) {

			// check if custom directory is set
			if( !empty($data->upload_dir) ) {

				$GLOBALS['plupload_data'] = $data;

				function custom_upload_dir( $upload ) {

					$data = $GLOBALS['plupload_data'];

					$custom_dir = ( !empty($data->custom_dir) ) ? '/' . $data->custom_dir : '';
					$upload_alpha = ( !empty($data->upload_alpha) ) ? '/' . $data->upload_alpha : '';

					// upload dir with custom path
					$upload_dir = $data->upload_dir . $upload_alpha . $custom_dir;

					$upload['subdir'] = '/' . $upload_dir;
					$upload['path']   = $upload['basedir'] . $upload['subdir'];
					$upload['url']    = $upload['baseurl'] . $upload['subdir'];

					return $upload;
				}
				// register our path override
				add_filter( 'upload_dir', 'custom_upload_dir' );
			}

			$upload_dir = wp_upload_dir();

			// wp handle local upload
			$status = wp_handle_upload($file, array('test_form' => true, 'action' => 'plupload_upload_action'));

			if( !empty($data->upload_dir) ) {
				// set everything back to normal
				remove_filter( 'upload_dir', 'custom_upload_dir' );
				unset($GLOBALS['plupload_data']);
			}

			if( $status ) {

				//TODO: add option to save media in WP database
				/* code related to save wordpress attachment, needed for media
				
				$args = array(
					'guid'           => $status['file'], 
					'post_mime_type' => $status['type'],
					'post_title'     => preg_replace( '/\.[^.]+$/', '', basename($status['file']) ),
					'post_content'   => '',
					'post_status'    => 'inherit'
				);

				$upload_id = wp_insert_attachment( $args, $status['file'] );
			
				// wp_generate_attachment_metadata() won't work if you do not include this file
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
			
				// Generate and save the attachment metas into the database
				wp_update_attachment_metadata( $upload_id, wp_generate_attachment_metadata( $upload_id, $status['file'] ) );
				*/

				$status['name'] = pathinfo($status['file'], PATHINFO_FILENAME);
				$status['extension'] = pathinfo($status['file'], PATHINFO_EXTENSION);

				if( !empty($data->thumbnails) ) { // generate thumbnails
					foreach( $data->thumbnails as $name => $thumb ) {
						$image = wp_get_image_editor( $status['file'] );
						if ( ! is_wp_error( $image ) ) {

							$thumb_name = $status['name'] . '-' . $thumb['width'] . 'x' . $thumb['height'];
							$thumb_path = str_replace( $status['name'], $thumb_name, $status['file']);
							$thumb_url = str_replace( $status['name'], $thumb_name, $status['url']);

							$image->resize( $thumb['width'], $thumb['height'], (bool) $thumb['crop'] );
							$image->save( $thumb_path );

							$t       = new stdClass();
							$t->name = $thumb_name;
							$t->path = $thumb_path;
							$t->url  = $thumb_url;

							$status['thumbnails'][$name] = $t;
						}
					}
				}

				// output the results
				wp_send_json($status);
			} else {
				wp_send_json_error('Upload error');
			}
		}

		/**
		 * Remove uploaded file
		 *
		 * @return void
		 */
		public function removeFile() {
			check_ajax_referer('plupload_nonce');

			$settings_id = sanitize_text_field($_POST['settings_id']);
			$field_id    = sanitize_text_field($_POST['field_id']);
			$group_id    = sanitize_text_field($_POST['group_id']);
			$group_key   = sanitize_text_field($_POST['group_key']);

			$file_object = $_POST['file_object'];
			$file = $file_object['file'];

			// get field data depends on group or single field
			$field = $this->getField( $field_id, $settings_id, $group_id);

			// get field parameters
			$remote = $this->getParam('remote', $field, $group_key);
			$thumbnails = ( !empty($file_object['thumbnails']) ) ? $file_object['thumbnails'] : false;

			$data = new stdClass();
			$data->thumbnails = $thumbnails;

			// remove file on FTP
			if( $remote ) {

				$data->host = ( !empty($remote['host']) ) ? $remote['host'] : false;
				$data->user = ( !empty($remote['login']) ) ? sanitize_user($remote['login']) : false;
				$data->pass = ( !empty($remote['password']) ) ? sanitize_text_field($remote['password']) : false;

				$this->removeRemoteFile($file, $data);

			} else { // local file
				$this->removeLocalFile($file, $data);
			}
		}

		/**
		 * Remove file on a remote FTP server
		 *
		 * @param string $file full resource path
		 * @param object $data
		 * @return void
		 */
		private function removeRemoteFile($file, $data) {
			// connect and login to FTP server
			$ftp_conn = ftp_connect($data->host);
			$login = ftp_login($ftp_conn, $data->user, $data->pass);
			if( !$ftp_conn || !$login ) {
				ftp_close($ftp_conn);
				wp_send_json_error('FTP: connection error');
			}

			if( !ftp_delete($ftp_conn, $file) ) {
				ftp_close($ftp_conn);
				wp_send_json_error('FTP: cannot remove file (' . $file . ')');
			}

			wp_send_json('File removed from remote FTP');
		}

		/**
		 * Remove file from local directory
		 *
		 * @param string $file full resource path
		 * @param object $data
		 * @return void
		 */
		private function removeLocalFile( $file, $data ) {
			wp_delete_file($file); //remove main file
			if( !empty($data->thumbnails) ) { //remove thumbnails
				foreach( $data->thumbnails as $thumb ) {
					wp_delete_file( $thumb['path'] );
				}
			}

			wp_send_json('File removed from local directory');
		}

		/**
		 * Save field after upload finished
		 *
		 * @return void
		 */
		public function saveField() {
			check_ajax_referer('plupload_nonce');

			$object_id   = sanitize_text_field($_POST['object_id']);
			$field_id    = sanitize_text_field($_POST['field_id']);
			$group_id    = sanitize_text_field($_POST['group_id']);
			$group_key   = sanitize_text_field($_POST['group_key']);
			$field_value = $_POST['field_value'];

			// save field after upload
			if( !empty($object_id) && !empty($field_id) ) {

				if( !empty($group_id) ) { // save data if group

					// get number of field
					$i = abs((int) filter_var($field_id, FILTER_SANITIZE_NUMBER_INT));
					// get current value
					$value = get_post_meta( $object_id, $group_id, true );
					// add new value
					$value[$i][$group_key] = $field_value;
					update_post_meta( $object_id, $group_id, $value );

				} else { // save data if regular field
					update_post_meta( $object_id, $field_id, $field_value );
				}

			}
			wp_send_json('Field saved');
		}

		/**
		 * Load scripts
		 */
		public function setupAdminScripts() {
			wp_enqueue_script('plupload-all');
			wp_enqueue_script( 'cmb2_field_plupload_js', plugins_url( '/assets/cmb2_plupload.js', __FILE__ ), array( 'jquery' ), '1.01' );
		}

	}
	Plupload_CMB2::getInstance();
}