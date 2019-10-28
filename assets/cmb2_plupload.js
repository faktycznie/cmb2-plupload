(function ($) {

	"use strict";

	var CMB2_Plupload = function (options) {

		if ('undefined' === typeof options || !options) {
			return;
		}

		this.events_set = false;

		this.container    = '#' + options['container'];
		this.settings_id  = options['settings_id'];
		this.field_id     = options['field_id'];

		this.group_id          = options['group_id'];
		this.group_field_id    = options['group_field_id'];
		this.group_field_name  = options['group_field_name'];

		this.object_id = options['object_id'];
		this.ajax_url  = options['ajax_url'];
		this.nonce     = options['nonce'];

		this.plupload  = options['plupload'];
		this.multiple  = options['multiple'];
		this.dragdrop  = options['dragdrop'];

		this.result    = $(this.container).find('.uploaded ul');
		this.field     = $(this.container).find('textarea');
		this.logs      = $(this.container).find('.logs');

		this.upload_dir = options['upload_dir'];
		this.custom_path = '#' + this.field_id + '_cpath';
		this.custom_name = '#' + this.field_id + '_cname';
		this.fill_field  = options['fill_field'];

		//console.log(this.plupload);

		//init plupload
		this.init();
	};

	CMB2_Plupload.prototype = {
		constructor: CMB2_Plupload,

		init: function () {
			var $this = this;

			// create the uploader and pass the config
			var uploader = new plupload.Uploader($this.plupload);

			// checks if browser supports drag and drop upload, makes some css adjustments if necessary
			uploader.bind('Init', function(up){
				if ( up.features.dragdrop && $this.dragdrop ) {
					$($this.container).addClass('drag-drop');
				} else {
					$($this.container).removeClass('drag-drop');
				}

				//custom upload button
				if( $($this.container).find('.cmb2-upload-file-button').length ) {

					$($this.container).on('click', '.cmb2-upload-file-button', function(e) {
						e.stopPropagation();
						e.preventDefault();
	
						var cpath = $($this.custom_path),
							cname = $($this.custom_name),
							cname_value = cname.val(),
							cpath_value = cpath.val();
	
						//change upload path if custom input enabled
						if( cpath.length && cpath_value ) {
							up.settings.multipart_params.custom_dir = cpath_value;
						}
	
						//change file name if custom input enabled
						if( cname.length && cname_value ) {
							up.settings.multipart_params.custom_name = cname_value;
						}
	
						//fire upload
						up.refresh();
						up.start();
	
					});
				}

			});

			uploader.init();

			// a file was added in the queue
			uploader.bind('FilesAdded', function(up, files) {

				$this.log(); //clear previous logs

				var hundredmb = 100 * 1024 * 1024,
					max = parseInt(up.settings.max_file_size, 10);

				plupload.each(files, function(file){
					//console.log(file);
					if (max > hundredmb && file.size > hundredmb && up.runtime != 'html5'){
						// file size error
						$this.log( 'File size error (' + file.size + ')', 'error' );
					} else {
						$this.log( 'File added (' + file.name + ')', 'success' );
					}
				});

				//autoupload
				if( ! $($this.container).find('.cmb2-upload-file-button').length ) {
					up.refresh();
					up.start();
				}

			});

			//progress
			uploader.bind('UploadProgress', function(up, file) {
				$($this.container).find('.progress .bar').css('width', file.percent + '%');
			});

			// a file was uploaded 
			uploader.bind('FileUploaded', function(up, file, response) {

				$($this.container).find('.progress .bar').css('width', '');

				var json = response.response;
				var data = $.parseJSON(json);
				var error = ( data['success'] == false || response.status != 200 ) ? true : false;

				if( ! error ) {

					var output;
					if( data['type'].indexOf("image") >= 0 ) { //if file type is image
						output = '<li class="img-status cmb2-media-item" data-json=\'' + json + '\'><img src="' + data['url'] + '" alt="' + data['url'] + '"> <a href="#" class="cmb2-remove-file-button">Remove</a></li>';
					} else { //other files
						output = '<li class="file-status cmb2-media-item" data-json=\'' + json + '\'><span>' + data['url'] + '</span> <a href="#" class="cmb2-remove-file-button">Remove</a> <a href="#" class="cmb2-copy-file-button">Copy URL</a></li>';
					}

					if( $this.multiple ) {
						$this.result.append( output );
					} else {

						//trigger file remove action if not multiupload
						$this.result.find('li').each(function() {
							var data = $(this).attr('data-json');
							data = JSON.parse(data);
							$this.remove( data['file'] );
						});

						//display result
						$this.result.html( output );
					}

					$this.log('File uploaded', 'success');

					$this.buildJSON();

					//automatically fill selected field
					if( $this.fill_field ) {
						var link = $(document).find($this.fill_field);
						if( link.length ) {
							var input = link.last().find('input[type="text"]');
							var parent = input.parent();
							var status = parent.find('.plupload-status');
							if( input.length ) {
								var old_val = input.val();
								input.val( data['url'] );
								status.remove();
								if( old_val ) {
									parent.append('<p class="plupload-status changed">Warning, the link has been changed: ' + old_val + '</p>');
								}else {
									parent.append('<p class="plupload-status added">URL updated</p>');
								}
							}
						}
					}

				} else {
					//display error message
					$this.log( data['data'], 'error' );
				}

			});

			this.sort();
			this.events();
		},

		/**
		 * Returns saved elements
		 *
		 * @returns
		 */
		getElements: function () {
			return this.result.find('li');
		},

		/**
		 * Make AJAX call to save field data
		 */
		saveField: function () {

			var $this = this;
			var field_value = this.field.val();

			$.ajax({
				url : $this.ajax_url,
				type : 'post',
				data : {
					action           : 'plupload_save_action',
					_ajax_nonce      : $this.nonce,
					settings_id      : $this.settings_id,
					field_id         : $this.field_id,
					group_id         : $this.group_id,
					group_field_id   : $this.group_field_id,
					group_field_name : $this.group_field_name,
					object_id        : $this.object_id,
					field_value      : field_value,
				},
				success : function( response ) {
					//console.log(response);
					//$this.log(response);
				}
			});
	
		},

		/**
		 * Build JSON object and fill textarea
		 */
		buildJSON: function () {
			var arr = [],
				elem = this.getElements();
			
			elem.each(function() {
				var data = $(this).attr('data-json');
				if( data ) {
					arr.push( JSON.parse(data) );
				}
			});

			if( this.field.length ) {
				if( arr.length ) {
					this.field.val( JSON.stringify(arr) );
				} else {
					this.field.val('');
				}
			}
			this.saveField();
		},

		/**
		 * Allow sorting elements
		 */
		sort: function () {
			var $this = this;

			this.result.sortable({
				stop: function( event, ui ) {
					$this.buildJSON();
				}
			});
		},


		/**
		 * Init events for buttons
		 */
		events: function () {

			if( this.events_set === true ) {
				return;
			}

			var $this = this;

			this.result.on('click', '.cmb2-remove-file-button', function(e) {
				e.stopPropagation();
				e.preventDefault();

				var li = $(this).closest('li');
				var data = li.attr('data-json');
				data = JSON.parse(data);

				li.remove();
				$this.buildJSON();

				//trigger file remove action
				$this.remove( data['file'] );
			});

			this.result.on('click', '.cmb2-copy-file-button', function(e) {
				e.stopPropagation();
				e.preventDefault();

				var li = $(this).closest('li');
				var data = li.attr('data-json');
				data = JSON.parse(data);

				$this.copyToClipboard( data['url'] );

			});

			this.events_set = true;

		},

		/**
		 * Remeve local or remote file
		 *
		 * @param {string} file path to the file
		 */
		remove: function( file ) {
			var $this = this;

			$.ajax({
				url : $this.ajax_url,
				type : 'post',
				data : {
					action : 'plupload_remove_action',
					_ajax_nonce : $this.nonce,
					settings_id      : $this.settings_id,
					field_id         : $this.field_id,
					group_id         : $this.group_id,
					group_field_id   : $this.group_field_id,
					group_field_name : $this.group_field_name,
					object_id        : $this.object_id,
					path             : file,
				},
				success : function( response ) {
					//console.log(response);
					//$this.log(response);
				}
			});
		},

		/**
		 * Copy provided text to clipboard
		 *
		 * @param {string} text 
		 */
		copyToClipboard: function ( text ) {
			var textArea = document.createElement( "textarea" );
			textArea.value = text;
			document.body.appendChild( textArea );
			textArea.select();
			document.execCommand( 'copy' );
			document.body.removeChild( textArea );
		},

		log: function ( text, status, mode ) {
			if(typeof text == 'undefinded' || !text) {
				this.logs.html(''); //clear logs
				return;
			}
			if(typeof mode == 'undefinded' || !mode) {
				var mode = 'add';
			}

			var style = '';
			if(typeof status == 'undefinded' || !status) {
				style = '';
			} else if (status == 'success') {
				style = ' style="color: green"';
			} else if (status == 'error') {
				style = ' style="color: red"';
			}

			if( mode == 'add') {
				this.logs.append('<p ' + style + '>' + text + '</p>');
			} else {
				this.logs.html('<p ' + style + '>' + text + '</p>');
			}

		}

	}

	window.CMB2_Plupload = CMB2_Plupload;

})(jQuery);
