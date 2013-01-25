<?php
/**
Plugin Name: Head JS Loader
Plugin URI: http://wordpress.org/extend/plugins/headjs-loader/
Description: A plugin to load <a href="http://headjs.com" target="_BLANK">Head JS</a> in Wordpress.
Version: 0.2
Author: ChuckMac
Author URI: http://www.chuckmac.info
Text Domain: headjs-loader
*/

/**
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
*/

if (!class_exists('headJS_loader')) {
/*
 * headJS_loader is the class that handles ALL of the plugin functionality.
 * It helps us avoid name collisions
 * http://codex.wordpress.org/Writing_a_Plugin#Avoiding_Function_Name_Collisions
 * @package headJS_loader
 */
class headJS_loader {
	var $pluginName      = 'headJS_loader';
	var $headJSVersion   = '0.99';
	var $scriptsUsed     = array();

	/**
	 * Initializes the plugin
	 */
	function headJS_loader() {
		add_action('init', array($this,'headJS_init'));
	}
	
	/*
	 * Sets up all actions and hooks necessary.
	 */
	function headJS_init() {
		
		/* No need to run on admin / rss / xmlrpc */
		if (!is_admin() && !is_feed() && !defined('XMLRPC_REQUEST')) {
			add_action('init', array($this, 'headjs_pre_content'), 99998);
			add_action('wp_print_scripts', array($this, 'headjs_inspect_scripts'));
			add_action('wp_footer', array($this, 'headjs_post_content'));
		}
		/* Load the admin menu */
		elseif (is_admin()) {
			add_action('admin_menu', array($this,'headjs_admin'));
			add_filter('plugin_action_links', array($this, 'headjs_admin_link_plugin_settings'), 10, 2);
		}
	}
	
	/**
	 *  Check what is enqueued properly, grab the source, then deenqueue it
	 */
	function headjs_inspect_scripts() {
		global $wp_scripts;
		if(!empty($wp_scripts)) {
			$scripts = $wp_scripts->queue;
			$wp_scripts->all_deps( $scripts );	
			$headjs_queue = $wp_scripts->to_do;	
			
			foreach( $headjs_queue as $handle ) {
				$this->scriptsUsed[$handle] = $wp_scripts->registered[$handle]->src;
				wp_deregister_script($handle);
				wp_dequeue_script($handle);
			}
		}
	}
	
	/**
	 * Buffer the output so we can play with it.
	 */
	function headjs_pre_content() {
		ob_start(array($this, 'headjs_modify_buffer'));

		/* Variable for sanity checking */
		$this->buffer_started = true;
    }
	
	/**
	 * Modify the buffer.  Search for any js tags in it and replace them
	 * with Head JS calls for scripts not enqueued properly.
	 *
	 * @return string buffer
	 */
	function headjs_modify_buffer($buffer) {
	
		/* Get the options set from the admin page */
		$headjsOptions = $this->headjs_admin_options();
	
		$script_array = array();
		/* Look for any script tags in the buffer */
		preg_match_all('/<script([^>]*?)>(.*)<\/script>/i', $buffer, $script_tags_match);
		
		if (!empty($script_tags_match[0])) {
			foreach ($script_tags_match[0] as $script_tag) {
				if (strpos(strtolower($script_tag), 'text/javascript') !== false) {
					/* Pull out any scripts that are not enqueued properly */
					preg_match('/<script([^>]*?)src=[\'"]([^\'"]+)([^>]*?)>/', $script_tag, $src_match);
					if (!empty ($src_match[1])) {
						/* Remove the script tags */
						$buffer = str_replace($script_tag, '', $buffer);
						/* Save the script location */
						$script_array[] = $src_match[1];
					} elseif ($headjsOptions['wrap_inline_js'] == 'true') {
						/* Add head.ready function to inline javascript */
						$buffer = preg_replace('/<script([^>]*?)>/', "<script$1>\nhead.ready(function (){\n", $buffer);
						$buffer = str_replace('</script>', "\n}); // end head.ready\n</script>", $buffer);
					}
				} elseif ($headjsOptions['wrap_inline_js'] == 'true') {
					/* Add head.ready function to inline javascript */
					$buffer = preg_replace('/<script([^>]*?)>/', "<script$1>\nhead.ready(function (){\n", $buffer);
					$buffer = str_replace('</script>', "\n}); // end head.ready\n</script>", $buffer);
				}
			}
		}
	
		/* Sort out the Head JS */
		$headJSfile = $this->headjs_location();
		$headJS = '<script type="text/javascript" src="' . $headJSfile . '"></script>';
		
		$i=0;
		$js_files = '';
		/* Load the enqueued scripts */
		if (!empty($this->scriptsUsed)) {
			foreach ($this->scriptsUsed as $script_name => $script_location) {
				if ($i != 0) { $js_files .= ",\n    "; }
				$js_files .= '"' . $script_location . '"';
				/* Loading with names was not working in dev environment, possibly fix this later */
				//$js_files .= '{'. $script_name . ': "' . $script_location . '"}';
				$i++;
			}
		}
		
		/* Load the other scraped scripts */
		if (!empty($script_array)) {
			$script_array = array_unique($script_array);
			foreach ($script_array as $script_location) {
				if ($i != 0) { $js_files .= ",\n    "; }
				$js_files .= '"' . $script_location . '"';
				$i++;
			}
		}		
		
		/* Wrap what we want to load in script tag / head.js function */
		if ((!empty($script_array)) || (!empty($this->scriptsUsed))) {
			$headJSqueue = "\n<script type=\"text/javascript\">\nhead.js(\n    " . $js_files . "\n);\n</script>";
		}
		
		/* Load HeadJS depending on the options settings */
		if ($headjsOptions['headjs_location'] == 'start_head') {
			$buffer = preg_replace('/<head([^>]*?)>/', "<head$1>\n$headJS\n", $buffer);
		} elseif ($headjsOptions['headjs_location'] == 'after_title') {
			$buffer = str_replace('</title>', "</title>\n" . $headJS, $buffer);
		} elseif ($headjsOptions['headjs_location'] == 'before_head') {
			$buffer = str_replace('</head>', $headJS . "\n</head>", $buffer);
		} elseif ($headjsOptions['headjs_location'] == 'in_footer') {
			$buffer = str_replace('</body>', $headJS . "\n</body>", $buffer);
		}

		
		/* Write HeadJS queue before the end of head */
		$buffer = str_replace('</head>', $headJSqueue . "\n</head>", $buffer);
		
		return $buffer;
	}
	
	/**
	 * After we are done modifying the contents, flush everything out to the screen.
	 */
	function headjs_post_content() {
      // sanity checking
      if ($this->buffer_started) {
        ob_end_flush();
      }
    }
	
	/*
	 * Return the location of the headJS file to use
	 */
	function headjs_location() {
		$headjsOptions = $this->headjs_admin_options();
		$headJSLocations = array(
							'cdn_headjs' => '//cdnjs.cloudflare.com/ajax/libs/headjs/0.99/head.min.js',
							'local_headjs' => plugins_url('/js/head.min.js', __FILE__ ),
							'cdn_headjs_core' => '//cdnjs.cloudflare.com/ajax/libs/headjs/0.99/head.core.min.js',
							'local_headjs_core' => plugins_url('/js/head.core.min.js', __FILE__ ),
							'cdn_headjs_asset' => '//cdnjs.cloudflare.com/ajax/libs/headjs/0.99/head.load.min.js',
							'local_headjs_asset' => plugins_url('/js/head.load.min.js', __FILE__ ),
							'custom_headjs' => $headjsOptions['custom_location']
							);
		return $headJSLocations[$headjsOptions['headjs_type']];
	}
	
	/**
	* Add to the admin settings menu
	*/
	function headjs_admin() {
		add_options_page('HeadJS Loader', 'HeadJS Loader', 'manage_options', basename(__FILE__), array($this, 'headjs_admin_panel'));
	}
	
	/**
	* Add our admin page
	*/
	function headjs_admin_panel() {
		$headjsUpdated = $this->headjs_admin_update_options();
		$headjsOptions = $this->headjs_admin_options();
		$headjsTypes = array(
						'cdn_headjs' => __('CDN HeadJS (<a href="http://cdnjs.com/" target="_BLANK">Cloudflare</a>)', $this->pluginName),
						'local_headjs' => __('Local HeadJS', $this->pluginName),
						'cdn_headjs_core' => __('CDN HeadJS Core (<a href="http://cdnjs.com/" target="_BLANK">Cloudflare</a>) [Responsive Design &amp; Feature Detection Only]', $this->pluginName),
						'local_headjs_core' => __('Local HeadJS [Responsive Design &amp; Feature Detection Only]', $this->pluginName),
						'cdn_headjs_asset' => __('CDN HeadJS Load (<a href="http://cdnjs.com/" target="_BLANK">Cloudflare</a>) [Asset Loader Only]', $this->pluginName),
						'local_headjs_asset' => __('Local HeadJS [Asset Loader Only]', $this->pluginName),
						'custom_headjs' => __('Load from custom location', $this->pluginName)
						);
		?>
		<div class=wrap>
			<?php echo $headjsUpdated; ?>
			<script type="text/javascript">
			jQuery(document).ready(function(){
				if (jQuery('input[value=custom_headjs]:checked').length == 0){
					jQuery('#head_js_custom_location').hide();
				}			
			});
			jQuery(function() {
				jQuery('input[name=headjs_type]').click(function() {
					if (jQuery('input[value=custom_headjs]:checked').length == 0){
						jQuery('#head_js_custom_location').hide();
					} else {		
						jQuery('#head_js_custom_location').show();
					}
				});
			});
			</script>
			<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
			<h2><?php _e('HeadJS Loader', $this->pluginName) ?></h2>	
			<p><?php _e('For more information on the different versions of HeadJS please visit their homepage at', $this->pluginName) ?> <a href="http://headjs.com" target="_BLANK" title="HeadJS">http://headjs.com</a></p>
			<h3><?php _e('Select the headJS file you would like to use:', $this->pluginName) ?></h3>
			<?php foreach ($headjsTypes as $id => $value) { ?>
				<p><input type="radio" name="headjs_type" id="<?php echo $id; ?>" value="<?php echo $id; ?>" <?php if ($headjsOptions['headjs_type'] == $id) { echo 'checked="checked" '; }?>/>
				<label for="<?php echo $id; ?>"><?php echo $value; ?></label></p>
			<?php } ?>
			<div id="head_js_custom_location">
			<label for="custom_location"><?php _e('Custom Location URL', $this->pluginName) ?> : </label>
			<input type="text" name="custom_location" id="custom_location" value="<?php echo $headjsOptions['custom_location'];?>" />
			</div>
			<br />
			<h3><?php _e('Other Options:', $this->pluginName) ?></h3>
			<p>
			<input type="checkbox" name="wrap_inline_js" id="wrap_inline_js" value="true" <?php if ($headjsOptions['wrap_inline_js'] == 'true') { echo 'checked="checked" '; }?>/>
			<label for="wrap_inline_js"><?php _e('Wrap inline javascript with head.ready function', $this->pluginName) ?></label>
			</p>
			<p>
			<select name="headjs_location" id="headjs_location">
				<option value="start_head"<?php if ($headjsOptions['headjs_location'] == 'start_head') { echo ' selected'; }?>>
					<?php _e('After &lt;head&gt; tag', $this->pluginName) ?>
				</option>
				<option value="after_title"<?php if ($headjsOptions['headjs_location'] == 'after_title') { echo ' selected'; }?>>
					<?php _e('After &lt;/title&gt; tag', $this->pluginName) ?>
				</option>
				<option value="before_head"<?php if ($headjsOptions['headjs_location'] == 'before_head') { echo ' selected'; }?>>
					<?php _e('Before &lt;/head&gt; tag', $this->pluginName) ?>
				</option>
				<option value="in_footer"<?php if ($headjsOptions['headjs_location'] == 'in_footer') { echo ' selected'; }?>>
					<?php _e('Before &lt;/body&gt; tag', $this->pluginName) ?>
				</option>
			</select>
			<label for="headjs_location"><?php _e('Where to place HeadJS script', $this->pluginName) ?></label>

			 </p>
			<div class="submit"><input type="submit" name="update_headjsSettings" value="<?php _e('Update Settings', $this->pluginName) ?>" /></div>
			</form>	
		</div>
		<?php		
	}
	
	/*
	 * Returns an array of our admin options
	*/
	function headjs_admin_options() {
		$headjsAdminOptions = array(
			'headjs_type' => 'cdn_headjs',
			'custom_location' => 'http://',
			'wrap_inline_js' => 'true',
			'headjs_location' => 'start_head'
			);
		$headjsOptions = get_option($this->pluginName);
		if (!empty($headjsOptions)) {
			foreach ($headjsOptions as $key => $option)
				$headjsAdminOptions[$key] = $option;
		}				
		update_option($this->pluginName, $headjsAdminOptions);
		return $headjsAdminOptions;
	}

	/*
	 * Check if we need to update our options, and execute if so
	 */
	function headjs_admin_update_options() {
		$return = '';

		/* If form is submitted, update options */
		
		if (isset($_POST['update_headjsSettings'])) {
		 	$currentOptions = $this->headjs_admin_options();
			if (isset($_POST['headjs_type'])) {
				$currentOptions['headjs_type'] = $_POST['headjs_type'];
			}  
			if (isset($_POST['custom_location'])) {
				$currentOptions['custom_location'] = apply_filters('content_save_pre', $_POST['custom_location']);
			}  
			if (isset($_POST['wrap_inline_js'])) {
				$currentOptions['wrap_inline_js'] = $_POST['wrap_inline_js'];
			} else {
				$currentOptions['wrap_inline_js'] = false;
			}
			if (isset($_POST['headjs_location'])) {
				$currentOptions['headjs_location'] = $_POST['headjs_location'];
			}  
			update_option($this->pluginName, $currentOptions);
			$return = '<div class="updated"><p><strong>' . __("Settings Updated.", $this->pluginName) . '</strong></p></div>';
		}
		
		return $return;
	}
	
	/*
	 * Create a link to our settings page on the plugin page
	 */
	function headjs_admin_link_plugin_settings($links, $file) {
		$this_plugin = plugin_basename(__FILE__);
		
		if ($file == $this_plugin) {
			$settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=' . basename($this_plugin) .'">Settings</a>';
			array_unshift($links, $settings_link);
		}
	
		return $links;
	}
		
} // class headJS_loader
} // if !class_exists('headJS_loader')


/* 
 * Instantiate our class
 */
if (class_exists('headJS_loader')) {
  $headJS_loader = new headJS_loader();
}