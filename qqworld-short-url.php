<?php
/*
Plugin Name: QQWorld Short URL
Plugin URI: https://wordpress.org/plugins/qqworld-short-url/
Description: Automatically generate short link url when publishing post.
Version: 1.0.1
Author: Michael Wang
Author URI: http://www.qqworld.org
Text Domain: qqworld-short-url
*/

define('QQWORLD_SHORTURL_DIR', __DIR__);
define('QQWORLD_SHORTURL_URL', plugin_dir_url(__FILE__));

function qqworld_get_shortlink($post_id, $mode='local') {
	switch ($mode) {
		case 'local':
			$shorturl_code = get_post_meta($post_id, 'shorturl_code', true);
			$shorturl_code = !empty($shorturl_code) ? home_url($shorturl_code) : null;
			break;
		case 'google':
			$shorturl = get_post_meta($post_id, 'google_shorturl', true);
			break;
		case 'baidu':
			$shorturl = get_post_meta($post_id, 'baidu_shorturl', true);
			break;
		case 'sina':
			$shorturl = get_post_meta($post_id, 'sina_shorturl', true);
			break;
		case 'netease':
			$shorturl = get_post_meta($post_id, 'netease_shorturl', true);
			break;
	}
	return $shorturl;
}

class qqworld_shorturl {
	var $value;
	var $mode;
	var $google_api_key;
	var $sina_api_key;
	var $netease_api_key;
	var $posttype;
	var $posttypes;
	var $shorturl_code;
	public function __construct() {
		$this->values = get_option('qqworld-short-url');
		$this->mode = isset($this->values['mode']) ? $this->values['mode'] : 'local';
		$this->posttypes = isset($this->values['post_types']) ? $this->values['post_types'] : array();
		$this->google_api_key = isset($this->values['google']['api_key']) ? $this->values['google']['api_key'] : '';
		$this->sina_api_key = isset($this->values['sina']['api_key']) ? $this->values['sina']['api_key'] : '';
		$this->netease_api_key = isset($this->values['netease']['api_key']) ? $this->values['netease']['api_key'] : '';
		$this->posttype = $this->get_current_post_type();
		if ( !empty($this->posttype) && in_array($this->posttype, $this->posttypes) ) add_action("publish_{$this->posttype}", array($this, 'generate_short_url'), 10, 2 );
		add_action( 'admin_menu', array($this, 'create_menu') );
		add_action( 'admin_init', array($this, 'register_setting') );
		add_filter( 'plugin_row_meta', array($this, 'registerPluginLinks'),10,2 );
		add_action( 'plugins_loaded', array($this, 'load_language') );
		add_filter( 'get_shortlink', array($this, 'get_shortlink'), 10, 4 );
		add_action( 'publish_posts', array($this, 'publish_posts') );
		add_action( 'setup_theme', array($this, 'parse_shorturl') );
	}

	public function parse_shorturl() {
		$url = explode('/', $_SERVER['REQUEST_URI']);
		if ( count($url)==2 ) {
			$posts = $this->is_exist($url[1]);
			if ( $posts ) {
				$id = $posts[0]->ID;
				$url = get_permalink($id);
				wp_safe_redirect( esc_url( $url ) );
				die;
			}
		}
	}

	public function get_shortlink($shortlink, $id, $context, $allow_slugs) {
		switch ($this->mode) {
			case 'local':
				$shorturl_code = get_post_meta($id, 'shorturl_code', true);
				if (!empty($shorturl_code)) $shortlink = home_url($shorturl_code);
				break;
			case 'google':
				$shorturl = get_post_meta($id, 'google_shorturl', true);
				if (!empty($shorturl)) $shortlink = $shorturl;
				break;
			case 'baidu':
				$shorturl = get_post_meta($id, 'baidu_shorturl', true);
				if (!empty($shorturl)) $shortlink = $shorturl;
				break;
			case 'sina':
				$shorturl = get_post_meta($id, 'sina_shorturl', true);
				if (!empty($shorturl)) $shortlink = $shorturl;
				break;
			case 'netease':
				$shorturl = get_post_meta($id, 'netease_shorturl', true);
				if (!empty($shorturl)) $shortlink = $shorturl;
				break;
		}

		return $shortlink;
	}

	public function is_exist($shorturl_code) {
		$posttypes = get_post_types();
		$args = array(
			'post_type' => array_keys($posttypes),
			'meta_query' => array(
				array( 
					'key' => 'shorturl_code',
					'value' => $shorturl_code,
					'compare' => '='
				)
			)
		);
		$posts = get_posts($args);
		if ( empty($posts) ) return false;
		else return $posts;
	}

	public function generate_unique_short_url() {
		$shorturl_code = $this->generate_random_string(4);
		if ( !$this->is_exist($shorturl_code) ) return $shorturl_code;
		else return $this->generate_unique_short_url();
	}

	public function generate_short_url($post_id) {
		//Check to make sure function is not executed more than once on save
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
		return;

		if ( !current_user_can('edit_post', $post_id) ) 
		return;

		$url = wp_get_shortlink($post_id);
		switch ($this->mode) {
			case 'local':
				$shorturl_code = get_post_meta($post_id, 'shorturl_code', true);
				if (empty($shorturl_code)) update_post_meta($post_id, 'shorturl_code', $this->generate_unique_short_url());
				break;
			case 'google':
				$shorturl = get_post_meta($post_id, 'google_shorturl', true);
				if (empty($shorturl)) update_post_meta($post_id, 'google_shorturl', $this->generate_google_short_url($url));
				break;
			case 'baidu':
				$shorturl = get_post_meta($post_id, 'baidu_shorturl', true);
				if (empty($shorturl)) update_post_meta($post_id, 'baidu_shorturl', $this->generate_baidu_short_url($url));
				break;
			case 'sina':
				$shorturl = get_post_meta($post_id, 'sina_shorturl', true);
				if (empty($shorturl)) update_post_meta($post_id, 'sina_shorturl', $this->generate_sina_short_url($url));
				break;
			case 'netease':
				$shorturl = get_post_meta($post_id, 'netease_shorturl', true);
				if (empty($shorturl)) update_post_meta($post_id, 'netease_shorturl', $this->generate_netease_short_url($url));
				break;
		}
	}

	public function generate_google_short_url($url) {
		$google = new GoogleUrlShortener($this->google_api_key);
		return $google->shorten('http://www.qqworld.org');
	}

	public function generate_baidu_short_url($url) {
		return baiduUrlShortener::shorten($url);
	}

	public function generate_sina_short_url($url) {
	}

	public function generate_netease_short_url($url) {
	}

	public function generate_random_string($length=8) {  
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';  
		$str = '';  
		for ( $i = 0; $i < $length; $i++ ) {  
			$str .= $chars[ mt_rand(0, strlen($chars) - 1) ];  
		}  
		return $str;  
	}

	/**
	* gets the current post type in the WordPress Admin
	*/
	public function get_current_post_type() {
		global $post, $typenow, $current_screen;

		if (isset($_GET['post']) && $_GET['post']) {
			$post_type = get_post_type($_GET['post']);
			return $post_type;
		}

		//we have a post so we can just get the post type from that
		if ( $post && $post->post_type )
			return $post->post_type;

		//check the global $typenow - set in admin.php
		elseif( $typenow )
			return $typenow;

		//check the global $current_screen object - set in sceen.php
		elseif( $current_screen && $current_screen->post_type )
			return $current_screen->post_type;

		//lastly check the post_type querystring
		elseif( isset( $_REQUEST['post_type'] ) )
			return sanitize_key( $_REQUEST['post_type'] );

		//we do not know the post type!
		return null;
	}

	public function outside_language() {
		__('Michael Wang', 'qqworld-short-url');
	}

	public function load_language() {
		load_plugin_textdomain( 'qqworld-short-url', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}

	public function registerPluginLinks($links, $file) {
		$base = plugin_basename(__FILE__);
		if ($file == $base) {
			$links[] = '<a href="' . menu_page_url( 'qqworld-short-url', 0 ) . '">' . __('Settings') . '</a>';
		}
		return $links;
	}

	function register_setting() {
		register_setting('qqworld-short-url', 'qqworld-short-url');
	}

	public function create_menu() {
		add_submenu_page('options-general.php', __('QQWorld Short URL', 'qqworld-short-url'), __('QQWorld Short URL', 'qqworld-short-url'), 'administrator', 'qqworld-short-url', array($this, 'fn') );
	}

	function fn() {
?>
	<div class="wrap">
		<h2><?php _e('QQWorld Short URL', 'qqworld-short-url'); ?></h2>
		<p><?php _e('Automatically generate short link url when publishing post.', 'qqworld-short-url'); ?></p>
		<p><?php _e('Using <strong>wp_get_shortlink($post_id)</strong> to output short url.', 'qqworld-short-url'); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields('qqworld-short-url'); ?>
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">
							<label for="mode"><?php _e('Post Types', 'qqworld-short-url'); ?></label>
						</th>
						<td><?php $post_types = get_post_types('', 'objects'); ?>
						<?php foreach ($post_types as $name => $post_type) :
							if ( !in_array($name, array('attachment', 'revision', 'nav_menu_item') )) : ?>
							<label><input name="qqworld-short-url[post_types][]" type="checkbox" value="<?php echo $name; ?>"<?php if (!empty($this->posttypes) && in_array($name, $this->posttypes)) echo ' checked'; ?> /> <?php echo $post_type->labels->name; ?></label>
						<?php endif;
						endforeach;
						?></td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="mode"><?php _e('Mode', 'qqworld-short-url'); ?></label>
						</th>
						<td>
							<aside class="admin_box_unit">
								<select id="mode" name="qqworld-short-url[mode]">
									<option value="local" <?php selected('local', $this->mode); ?>><?php _e('Local Server', 'qqworld-short-url'); ?>
									<option value="google" <?php selected('google', $this->mode); ?>><?php _e('Google URL Shortener (goo.gl)', 'qqworld-short-url'); ?>
									<option value="baidu" <?php selected('baidu', $this->mode); ?>><?php _e('Baidu Short URL (dwz.cn)', 'qqworld-short-url'); ?>
									<!--<option value="sina" <?php selected('sina', $this->mode); ?>><?php _e('Sina URL Shortener (sina.lt)', 'qqworld-short-url'); ?>
									<option value="netease" <?php selected('netease', $this->mode); ?>><?php _e('NetEase Short URL (126.am)', 'qqworld-short-url'); ?>-->
								</select>
							</aside>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="google_api_key"><?php _e('Google API Key', 'qqworld-short-url'); ?></label>
						</th>
						<td>
							<aside class="admin_box_unit">
								<input type="text" id="google_api_key" class="regular-text" name="qqworld-short-url[google][api_key]" value="<?php echo $this->google_api_key; ?>" />
							</aside>
						</td>
					</tr>
					<!--<tr valign="top">
						<th scope="row">
							<label for="sina_api_key"><?php _e('Sina API Key', 'qqworld-short-url'); ?></label>
						</th>
						<td>
							<aside class="admin_box_unit">
								<input type="text" id="sina_api_key" class="regular-text" name="qqworld-short-url[sina][api_key]" value="<?php echo $this->sina_api_key; ?>" />
							</aside>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="netease_api_key"><?php _e('NetEase API Key', 'qqworld-short-url'); ?></label>
						</th>
						<td>
							<aside class="admin_box_unit">
								<input type="text" id="netease_api_key" class="regular-text" name="qqworld-short-url[netease][api_key]" value="<?php echo $this->netease_api_key; ?>" />
							</aside>
						</td>
					</tr>-->
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
<?php
	}
}
new qqworld_shorturl;

class GoogleUrlShortener {
	var $apiKey;
	public function __construct($apiKey) {
		$this->apiKey = $apiKey;
	}

	public function shorten($longUrl) {
		$postData = array(
			'longUrl' => $longUrl,
			'key' => $this->apiKey
		);
		$jsonData = json_encode($postData);
		$curlObj = curl_init();
		curl_setopt($curlObj, CURLOPT_URL, 'https://www.googleapis.com/urlshortener/v1/url');
		curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curlObj, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curlObj, CURLOPT_HEADER, 0);
		curl_setopt($curlObj, CURLOPT_HTTPHEADER, array('Content-type:application/json'));
		curl_setopt($curlObj, CURLOPT_POST, 1);
		curl_setopt($curlObj, CURLOPT_POSTFIELDS, $jsonData);
		$response = curl_exec($curlObj);

		// Change the response json string to object
		$json = json_decode($response);
		curl_close($curlObj);

		return isset($json->id) ? $json->id : null;	
	}
}

class baiduUrlShortener {
	public static function shorten($url){
		$baseurl = 'http://dwz.cn/create.php';
		$ch=curl_init();
		curl_setopt($ch,CURLOPT_URL,$baseurl);
		curl_setopt($ch,CURLOPT_POST,true);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		$data=array('url'=>$url);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
		$strRes=curl_exec($ch);
		curl_close($ch);
		$arrResponse=json_decode($strRes,true);
		if($arrResponse['status']!=0) {
			echo 'ErrorCode: ['.$arrResponse['status'].'] ErrorMsg: ['.$arrResponse['err_msg']."]<br/>";
			return null;
		}
		return $arrResponse['tinyurl'];
	}
}
?>