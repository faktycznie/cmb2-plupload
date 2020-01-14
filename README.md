Wordpress CMB2 Plupload field

Usage:
```php
$cmb->add_field( array(
		'name'            => 'Upload',
		'id'              => '_prefix_upload',
		'type'            => 'plupload',
		'button_label'    => 'Add file', // upload button name
		'multiple_files'  => true, // allow upload multiple files
		'dragdrop'        => true, // display drag and drop area
		'upload_dir'      => 'public_html/files', // upload directory, if not provided default upload dir will be used
		'upload_alpha'    => true, // add first letter of post title to upload path to better organize upload directory
		'custom_path'     => true, // allow provide additionla path where file will be uploaded
		'custom_name'     => true, // allow change file name
		'save_data'       => false, // save data about uploaded files in WP database
		'fill_field'      => '.plupload-fill-value', // wrapper id or class of field where url to file will be inserted
		'remote' => array( // ftp credentials
			'host'     => 'domain.com',
			'login'    => 'username',
			'password' => 'password',
			'url'      => 'https://domain.com/files' //full URL to upload directory
		),
		'thumbnails' => array( //thumbnails to generate if image uploaded, structure => $_wp_additional_image_sizes
			'thumb_name' => array(
				'width'  => 200,
				'height' => 150,
				'crop'   => 1,
			)
		)
	) );
```

![alt text](https://raw.githubusercontent.com/faktycznie/cmb2-plupload/master/assets/image.png)
