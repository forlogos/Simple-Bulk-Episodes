<?php

/*
  Plugin Name: Simple Bulk Episodes
  Plugin URI:
  Description: A simple way to add a bulk of episodes for the Seriously Simple Podcasting plugin
  Version: 2.1
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
							<em>title*, publish date time*, HTML post content, audio file URL*, audio duration, date recorded, explicit, block from itunes, episode type, Featured image id, podcast ids, tags, iTunes season number, iTunes episode number, iTunes episode type</em><br/><br/>
						Leave data blank if you don't need it.
						</p>
						<textarea name="ep_info" style="width:100%;height:200px;"></textarea>
						<br/><br/><strong>Notes:</strong>
						<ol>
							<li>* are minimum required data. Everything else is optional</li>
							<li>Future publish date time will be scheduled to publish on the specified time.</li>
							<li>File size will calculated automatically if possible.</li>
							<li>An attempt to calculate Audio Duration will be made if not provided.</li>
							<li>No line breaks/line spaces in the HTML post content column.</li>
							<li>Any format for the <em>publish date time</em> and <em>date recorded</em> values can be used, it will be reformatted to the format needed. Double check their values after you click on "continue" below.</li>
							<li>Values for the <em>explicit</em> and <em>block from itunes</em> columns must be "on" if you want them selected or empty if not.</li>
							<li><em>Episode type</em> can be "audio" or "video" - if not provided, it will default to "audio"</li>
							<li>Podcast ids should be the ID of a single podcast or a comma separated list of ids</li>
							<li>Tags should be a comma separated list of tag names, not IDs</li>
							<li>iTunes episode type must be either "full", "trailer", or "bonus"</li>
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
				$rd = trim($rd); //need to trim possible trailing newline character - thanks @tkittich
				if($rd!='' && $rd!=array()) {//verify that there is data for this row
					$output.='<tr>';
					$cells=explode( "\t", $rd);//get data for each cell
					$i=15;//set a counter for the # of cells we should have

					foreach($cells as $cc=>$c) {//let's loop thru cell data, use Cell Count and Cell as key value vars
						if($cc==1) {//date column
							$output.='<td>'.date('Y-m-d',strtotime($c)).'<br/>'.date('H:i',strtotime($c)).'</td>';//save for output
							$time=(date('H',strtotime($c))=='00' && date('i',strtotime($c))=='00'?'00:01':date('H:i',strtotime($c)));//change the time if set to 00:00. Personally, it's confusing as to which day that belongs to.
							$hiddenformfields.='<input type="hidden" name="data['.$rk.'][]" value="'.date('Y-m-d '.$time.':s.u',strtotime($c)).'">';
						}elseif($cc==5) {//date recorded column
							$output.='<td>'.date('Y-m-d',strtotime($c)).'</td>';//save for output
							$hiddenformfields.='<input type="hidden" name="data['.$rk.'][]" value="'.date('d-m-Y',strtotime($c)).'">';
						}elseif($cc==9) {//featured image id column
							$imgurl = wp_get_attachment_image_src($c);
							$output.='<td><img src="'.$imgurl[0].'" width="150px"></td>';//save for output
							$hiddenformfields.='<input type="hidden" name="data['.$rk.'][]" value="'.htmlspecialchars(trim($c)).'">';
						}else{
							$output.='<td>'.stripslashes(trim($c)).'</td>';//save for output
							$hiddenformfields.='<input type="hidden" name="data['.$rk.'][]" value="'.htmlspecialchars(trim($c)).'">';
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
						<th>title</th><th>publish date time</th><th>HTML post content</th><th>Audio file URL</th><th>Audio duration</th><th>date recorded</th><th>explicit</th><th>block from itunes</th><th>episode type</th><th>Featured image id</th><th>podcast ids</th><th>tags</th><th>iTunes season number</th><th>iTunes episode number</th><th>iTunes episode type</th>
					</tr></thead>
					<?php echo $output; ?>
					<tfoot><tr>
						<th>title</th><th>publish date time</th><th>HTML post content</th><th>Audio file URL</th><th>Audio duration</th><th>date recorded</th><th>explicit</th><th>block from itunes</th><th>episode type</th><th>Featured image id</th><th>podcast ids</th><th>tags</th><th>iTunes season number</th><th>iTunes episode number</th><th>iTunes episode type</th>
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

					// call ssp's function to get file size - credit to @tkittich https://profiles.wordpress.org/tkittich/
					global $ss_podcasting;

					if (isset($ss_podcasting)) {
						$size = $ss_podcasting->episode_repository->get_file_size( $ep[3] );
					}
					if (!empty($size['raw'])) {
						add_post_meta($post_id, 'filesize_raw', $size['raw']);
					}
					if (!empty($size['formatted'])) {
						add_post_meta($post_id, 'filesize', $size['formatted']);
					}

				}
				if(!empty($ep[4])) {//if audio duration provided - add
					add_post_meta($post_id, 'duration', $ep[4]);
				} else {//attempt to calculate if not provided - credit to @tkittich https://profiles.wordpress.org/tkittich/
				// code from Jonathan Bossenger (@psykro) https://wordpress.org/support/topic/calling-get_file_duration-from-another-plugin-during-bulk-episodes-imports/
					global $ss_podcasting;
					$duration = false;
					if (isset($ss_podcasting)) {
						$duration = $ss_podcasting->get_file_duration( $ep[3] );
					}
					if(!empty($duration)) {
						add_post_meta($post_id, 'duration', $duration);
					}
 				}
				if(!empty($ep[5])) {//if date recorded provided - add
					add_post_meta($post_id, 'date_recorded', $ep[5]);
				}
				if(!empty($ep[6]) && $ep[6]=='on') {//if explicited provided and is "on"- add
					add_post_meta($post_id, 'explicit', $ep[6]);
				}
				if(!empty($ep[7]) && $ep[7]=='on') {//if block from itunes provided and is "on"- add
					add_post_meta($post_id, 'block', $ep[7]);
				}
				//default episode type to audio if not video
				if(!empty($ep[8]) && $ep[8]=='video') {
					add_post_meta($post_id, 'episode_type', $ep[8]);
				}else{
					add_post_meta($post_id, 'episode_type', 'audio');
				}

				if(!empty($ep[9])) {//if featured image id url, already in the media library - add
					set_post_thumbnail($post_id, (int)$ep[9]);//set WP post featured image
					add_post_meta($post_id, 'cover_image_id', $ep[9]);//set as episode cover_image_id
					$imgurl = wp_get_attachment_image_src($ep[9]);
					add_post_meta($post_id, 'cover_image', $imgurl[0]);//set as episode cover_image
				}
				if(!empty($ep[10])) {//if podcasts/serie
					$podcasts = explode(',', $ep[10]);
					wp_set_post_terms( $post_id, $podcasts, 'series' );
				}
				if(!empty($ep[11])) {//if tags - add
					$tags = explode(',', $ep[11]);
					wp_set_post_terms( $post_id, $tags, 'post_tag' );
				}

				if(!empty($ep[12])) {//if iTunes season number - add
					add_post_meta($post_id, 'itunes_season_number', $ep[12]);
				}
				if(!empty($ep[13])) {//if iTunes episode number - add
					add_post_meta($post_id, 'itunes_episode_number', $ep[13]);
				}
				if(!empty($ep[14])) {//if iTunes episode type - add
					// valid types: full, trailer, bonus
					add_post_meta($post_id, 'itunes_episode_type', $ep[14]);
				}

				echo get_the_title($post_id).': <a href="post.php?post='.$post_id.'&action=edit">edit</a> | <a href="'.get_permalink($post_id).'">view</a><br/>';
			}
			echo '<p><a href="edit.php?post_type=podcast&page=bulk_episodes">Add more!</a></p></div>';
		}
	}
}

SSP_bulk_eps::get_instance();