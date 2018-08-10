<?php
/**
 * Plugin Name: TCC Doctor Import
 * Plugin URI: http://sparkstek.com/tcc-doctor-import
 * Description: Import plugin to create doctor profiles based on a file upload.
 * Version: 1.0
 * Author: Ron Sparks
 * Author URI: http://www.sparkstek.com
 */

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class TCC_Doctor_Import
{
	public function __construct()
	{
		// Hook into the admin menu
		add_action( 'admin_menu', array( $this, 'tcc_doctor_import_menu' ) );
	}

	public function tcc_doctor_import_menu() 
	{
	    // Add the menu item and page
	    $page_title = 'TCC Doctor Import';
	    $menu_title = 'TCC Doctor<br/>Import';
	    $capability = 'manage_options';
	    $slug = 'tcc-doctor-import-options';
	    $callback = array( $this, 'tcc_doctor_import_content' );
	    $icon = 'dashicons-upload';
	    $position = 100;

	    add_menu_page( $page_title, $menu_title, $capability, $slug, $callback, $icon, $position );
	}

	public function tcc_doctor_import_content() 
	{
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		?>

		<div class="wrap">
			<h1>TCC Doctor Import</h1>
			<form name="tcc_import_form" method="post" action="<?php echo get_permalink(); ?>" method="post" enctype="multipart/form-data"> 
				<p/>&nbsp;<p/>
				File to import: <br/>

				<?php wp_nonce_field('csv-import'); ?>
				<label for="file">Filename:</label>
        		<input type="file" name="file" id="file"><br>
				<?php submit_button('Load File'); ?>
			</form>
		</div>

		<?php

		if ( ! empty( $_FILES ) ) 
		{
			echo 'Current PHP version: ' . phpversion() . "<p/>";
			$result = $this->upload_the_file();
			echo ("File is valid, and was successfully uploaded as " . $result["file"] . "<p/>");
			$this->process_doctor_import($result["file"]);
		}
	}


	public function create_doctor_page($doctor)
	{
		$return = false;

		//get values from the $doctors array
	    $doctorId = $doctor['DOCTOR_ID'];
        $telephone = $doctor['TELEPHONE'];
        $bannerImage = $doctor['BANNER_IMAGE'];
        $affiliation = $doctor['AFFILIATION'];
        $doctorName = $doctor['NAME'];
        $slug = "doctors-" . strtolower(str_replace(" ","-",$doctorName));
        $specialty = $doctor['SPECIALTY'];
        $profileBlock = $doctor['PROFILE_BLOCK'];

        $page = get_page_by_path( $slug );

        if ( $page->ID === NULL) 
        {
	        $page_id = wp_insert_post (array 
	        		(
	        			'post_title' => $doctorName,
	        			'post_type' => 'page',
	        			'post_name' => $slug,
	        			'post_status'=> 'publish',
	        			'post_content' => $profileBlock
	        		)
	        	);

	        //set the page template to apply
	        if( -1 != $page_id ) 
	        {
	        	add_post_meta( $page_id, '_wp_page_template',  'page-md-profile.php' );

	        	//set the ACF values
				update_field( 'doctors_name', $doctorName, $page_id );
				update_field( 'specialty', $specialty, $page_id );
				update_field( 'affiliation', $affiliation, $page_id );
				update_field( 'telephone', $telephone, $page_id );

	        	$return = true;
	        }
        }
        else
        {
        	echo (" - page already exists");
        }

		return $return;
	}

	public function process_doctor_import ($filename)
	{
		$json = file_get_contents($filename);

		$jsonIterator = new RecursiveIteratorIterator(
    		new RecursiveArrayIterator(json_decode($json, TRUE)),
    
    	RecursiveIteratorIterator::SELF_FIRST);

		$x=0;

    	while ($jsonIterator->valid()) 
    	{
		    if ($jsonIterator->hasChildren()) 
		    {
		        foreach ($jsonIterator->getChildren() as $key => $value) 
		        {
		            echo "Creating doctor " . $key . ' : ' . $value['NAME'] . ", " . $value['SPECIALTY'];
		            $x++;

		            if ($this->create_doctor_page($value)==true)
		            {
		            	echo (" - success!<p/>");
		            }
		            else
		            {
		            	echo (" - failed!<p/>");
		            }

		            //if ($x >= 3) {break;}
		        }
		    } 
		    else 
		    {
		        echo "No children.\n";
		    }

		    $iterator->next();
		}
	}

	public function upload_the_file()
	{
		if ( ! function_exists( 'wp_handle_upload' ) ) {
		    require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		add_filter('upload_dir', 'set_upload_dir' );
		add_filter('upload_mimes', 'enable_extended_upload');

		$uploadedfile = $_FILES['file'];
		$upload_overrides = array( 'test_form' => false );

		$movefile = wp_handle_upload( $uploadedfile, $upload_overrides, $mimes );

		remove_filter( 'upload_dir', 'set_upload_dir' );
		remove_filter('upload_mimes', 'enable_extended_upload');

		if ( $movefile && ! isset( $movefile['error'] ) ) 
		{
		    $return = array (
		    	"status" => "success",
		    	"url" => $movefile["url"],
		    	"file" => $movefile["file"]);
		} 
		else 
		{
		    echo $movefile['error'];
		    $return = array (
		    	"status" => "fail",
		    	"error" => $movefile['error']);
		}

		return $return;

	}
}

	new TCC_Doctor_Import();

	function set_upload_dir( $dirs ) 
	{
	    $dirs['subdir'] = '/tcc-dr-profiles';
	    $dirs['path'] = $dirs['basedir'] . '/tcc-dr-profiles';
	    $dirs['url'] = $dirs['baseurl'] . '/tcc-dr-profiles';

	    return $dirs;
	}

	function enable_extended_upload ( $mime_types =array() ) 
	{
		$mime_types['json']  = 'application/json';

		return $mime_types;

	}

?>