Wordpress CMB2 Plupload field

Usage:
```php
$cmb->add_field( array(
		'name' => 'Upload',
		'id'   => '_prefix_upload',
		'type' => 'plupload',
		'button_label' => 'Add file',
		'multiple_files'  => true,
		'dragdrop'        => true,
		'upload_dir'      => 'public_html/files',
		'custom_path'     => true,
		'custom_name'     => true,
		'save_data'       => false,
		'fill_field'      => '.plupload-fill-value', //wrapper id or class
		'remote'      => array(
			'host'     => 'domain.com',
			'login'    => 'username',
			'password' => 'password',
			'url'      => 'https://domain.com/files'
		)
	) );
```

![alt text](https://raw.githubusercontent.com/faktycznie/cmb2-plupload/master/assets/image.png)
