<?php

/*
  Plugin Name: Simple Bulk Episodes
  Plugin URI:
  Description: A simple way to add a bulk of episodes for the Seriously Simple Podcasting plugin
  Version: 1.0
  Author: forlogos
  Author URI: http://jasonjalbuena.com
  License: GPL V3
 */

class SSP_bulk_eps {
	private static $instance = null;
	private $plugin_path;
	private $plugin_url;
    	private $text_domain = '';

	/**
	 * Creates or returns an instance of this class.
	 */
	public static function get_instance() {
		// If an instance hasn't been created and set to $instance create an instance and set it to $instance.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Initializes the plugin by setting localization, hooks, filters, and administrative functions.
	 */
	private function __construct() {

		//for the future
		//load_plugin_textdomain( $this->text_domain, false, $this->plugin_path . '\lang' );

		add_action('admin_menu',array( $this,'add_submenu'));

		$this->run_plugin();
	}

	//add the menu to the SSP Podcasts admin menu
    public function add_submenu() {
 	   add_submenu_page ( 'edit.php?post_type=podcast', 'Bulk Episodes', 'Bulk Episodes', 'publish_posts', 'bulk_episodes', 'add_bulk_episodes' );
	}

    private function run_plugin() {

    	//let's load the main plugin page
    	function add_bulk_episodes() {
			// Check whether the continue button has been pressed AND also check the nonce
			if (isset($_POST['continue_button']) && check_admin_referer('continue_clicked')) {
				// the button has been pressed AND we've passed the security check
				continue_action();
			}elseif(isset($_POST['verify_button']) && check_admin_referer('verify_clicked')) {
				ver_submit_action();
			}else{	?>
				<div class="wrap">
					<h2>Simple Bulk Episodes</h2>
					<form method="post" action="edit.php?post_type=podcast&page=bulk_episodes">
						<h3>Paste Excel data</h3>
						<p>Data should be formatted with each row as one episode. Column data should be in this order:<br/><br/>
							<em>title, publish date time, HTML post content, Audio file URL, Audio duration, date recorded, explicit, block from itunes</em><br/><br/>
						Leave data blank if you don't need it.
						</p>
						<textarea name="ep_info" style="width:100%;height:200px;"></textarea>
						<br/><br/><strong>Notes:</strong>
						<ol>
							<li>Future publish date time will be scheduled to publish on the specified time.</li>
							<li>File size will calculated automatically if possible.</li>
							<li>No line breaks/line spaces in the HTML post content column.</li>
							<li>Values for the explicit and block from itunes columns must be "no"</li>
						</ol>
						<?php wp_nonce_field('continue_clicked');
						echo '<input type="hidden" value="true" name="continue_button" />';
						submit_button('Continue');
					echo '</form>
				</div>';
			} ?>
			<style type="text/css">
				#outputable th, #outputable td {padding:3px 6px;border:1px solid #000;text-align:left;}
			</style>
		<?php }

		//page to verify submitted data and to choose to add it or start over
    	function continue_action() {
    		$data=explode( "\n", $_POST['ep_info']);//save POST data to a var

    		//make vars
    		$output='';//to display data for verification
    		$hiddenformfields='';//pass data to submission thru hidden form fields. Other methods (like serializing or json_encode) were available, but chose this since the POST value submitted would be cleaner

    		//let's loop thru our data
			foreach($data as $rk=>$rd) {//use Row Key and Row Data as key value vars
				if($rd!='' && $rd!=array()) {//verify that there is data for this row
					$output.='<tr>';
					$cells=explode( "\t", $rd);//get data for each cell
					$i=8;//set a counter for the # of cells we should have

					foreach($cells as $cc=>$c) {//let's loop thru cell data, use Cell Count and Cell as key value vars
						if($cc==1) {//if this is the date column use a different format
							$output.='<td>'.date('Y-m-d',strtotime($c)).'<br/>'.date('H:i',strtotime($c)).'</td>';//save for output
							$time=(date('H',strtotime($c))=='00' && date('i',strtotime($c))=='00'?'00:01':date('H:i',strtotime($c)));//change the time if set to 00:00. Personally, it's confusing as to which day that belongs to.
							$hiddenformfields.='<input type="hidden" name="data['.$rk.'][]" value="'.date('Y-m-d '.$time.':s.u',strtotime($c)).'">';

						}elseif($cc==5) {//if this is the date recorded column
							$output.='<td>'.date('Y-m-d',strtotime($c)).'</td>';//save for output
							$hiddenformfields.='<input type="hidden" name="data['.$rk.'][]" value="'.date('d-m-Y',strtotime($c)).'">';
						}else{
							$output.='<td>'.stripslashes($c).'</td>';//save for output
							$hiddenformfields.='<input type="hidden" name="data['.$rk.'][]" value="'.htmlspecialchars($c).'">';
						}
						$i--;//make the counter smaller
					}

					//in case not all data was submitted since not all columns are required, let's add some empty data for the remainder
					while($i!=0) {
						$output.='<td></td>';//for output
						$i--;//make the counter smaller
					}
					$output.='</tr>';
				}else{//no data in the row
					unset($data[$rk]);//remove it!
				}
			} ?>

    		<div id="message" class="updated fade"><p>Please verify submitted data</p></div>

			<div class="wrap">
				<h2>Simple Bulk Episodes</h2>
				<h3>Verify Data</h3>
				<p>If the data below is not correct, please <a href="edit.php?post_type=podcast&page=bulk_episodes">start over</a>.</p>

				<table id="outputable">
					<thead><tr>
						<th>title</th><th>publish date time</th><th>HTML post content</th><th>Audio file URL</th><th>Audio duration</th><th>date recorded</th><th>explicit</th><th>block from itunes</th>
					</tr></thead>
					<?php echo $output; ?>
					<tfoot><tr>
						<th>title</th><th>publish date time</th><th>HTML post content</th><th>Audio file URL</th><th>Audio duration</th><th>date recorded</th><th>explicit</th><th>block from itunes</th>
					</tr></tfoot>
				</table>

				<form method="post" action="edit.php?post_type=podcast&page=bulk_episodes">
					<input type="hidden" value="true" name="verify_button" />
					<?php echo $hiddenformfields;
					wp_nonce_field('verify_clicked');
					submit_button('Verify and Submit'); ?>
				</form>
			</div>
		<?php }

		//after user opts to add episodes, actually add the episode datum and verify/show links
		function ver_submit_action() {
			$data=$_POST['data'];
			echo '<div class="wrap">
			<h2>Simple Bulk Episodes</h2>
			<p><strong>The following episodes were added:</strong></p>';

			foreach($data as $ep) {//let's loop thru each episode and add each to the DB
				$my_post = array(//set vars for wp_posts
					'post_title'	=> stripslashes($ep[0]),
					'post_date'		=> $ep[1],
					'post_content'  => stripslashes($ep[2]),
					'post_status'   => 'publish',
					'post_type'		=> 'podcast'
				);
				$post_id =wp_insert_post( $my_post );//add the data to the db, get ID

				//let's add all the other info to wp_postmeta
				if(!empty($ep[3])) {//if audio file provided
					//add this as the enclosure and audio URL
					add_post_meta($post_id, 'enclosure', $ep[3]);
					add_post_meta($post_id, 'audio_file', $ep[3]);

					//calculate file size based on the URL
					$file=$ep[3];

					//the following code from SSP : seriously-simple-podcasting/includes/class-ssp-frontend.php
					// Include media functions if necessary
					if ( ! function_exists( 'wp_read_audio_metadata' ) ) {
						require_once( ABSPATH . 'wp-admin/includes/media.php' );
					}
					// Get file data (for local file)
					$data = wp_read_audio_metadata( $file );
					$raw = $formatted = '';
					if ( $data ) {
						$raw = $data['filesize'];
						$formatted = $this->format_bytes( $raw );
					} else {
						// get file data (for remote file)
						$data = wp_remote_head( $file, array( 'timeout' => 10, 'redirection' => 5 ) );
						if ( ! is_wp_error( $data ) && is_array( $data ) && isset( $data['headers']['content-length'] ) ) {
							$raw = $data['headers']['content-length'];

							$base = log ( $raw ) / log( 1024 );
							$suffixes = array( '' , 'k' , 'M' , 'G' , 'T' );
							$formatted = round( pow( 1024 , $base - floor( $base ) ) , 2 ) . $suffixes[ floor( $base ) ];
						}
					}
					//end code from SSP
					add_post_meta($post_id, 'filesize_raw', $raw);
					add_post_meta($post_id, 'filesize', $formatted);
				}
				if(!empty($ep[4])) {//if audio duration provided - add
					add_post_meta($post_id, 'duration', $ep[4]);
				}
				if(!empty($ep[5])) {//if date recorded provided - add
					add_post_meta($post_id, 'date_recorded', $ep[5]);
				}
				if(!empty($ep[6]) && $ep[6]=='no') {//if explicited provided and is "no"- add
					add_post_meta($post_id, 'explicit', $ep[6]);
				}
				if(!empty($ep[7]) && $ep[7]=='no') {//if block from itunes and is "no"- add
					add_post_meta($post_id, 'block', $ep[7]);
				}

				echo get_the_title($post_id).': <a href="post.php?post='.$post_id.'&action=edit">edit</a> | <a href="'.get_permalink($post_id).'">view</a><br/>';
			}
			echo '<p><a href="edit.php?post_type=podcast&page=bulk_episodes">Add more!</a></p></div>';
		}
	}
}

SSP_bulk_eps::get_instance();