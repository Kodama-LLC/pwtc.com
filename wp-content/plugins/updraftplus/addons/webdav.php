<?php
// @codingStandardsIgnoreStart
/*
UpdraftPlus Addon: webdav:WebDAV Support
Description: Allows UpdraftPlus to back up to WebDAV servers
Version: 2.2
Shop: /shop/webdav/
Include: includes/PEAR
IncludePHP: methods/stream-base.php
Latest Change: 1.12.35
*/
// @codingStandardsIgnoreEnd

/*
To look at:
http://sabre.io/dav/http-patch/
http://sabre.io/dav/davclient/
https://blog.sphere.chronosempire.org.uk/2012/11/21/webdav-and-the-http-patch-nightmare
*/

if (!defined('UPDRAFTPLUS_DIR')) die('No direct access allowed');

// In PHP 5.2, the instantiation of the class has to be after it is defined, if the class is extending a class from another file. Hence, that has been moved to the end of this file.

if (!class_exists('UpdraftPlus_AddonStorage_viastream')) require_once(UPDRAFTPLUS_DIR.'/methods/stream-base.php');

class UpdraftPlus_Addons_RemoteStorage_webdav extends UpdraftPlus_AddonStorage_viastream {

	public function __construct() {
		parent::__construct('webdav', 'WebDAV');
	}

	/**
	 * This method overrides the parent method and lists the supported features of this remote storage option.
	 *
	 * @return Array - an array of supported features (any features not
	 * mentioned are assumed to not be supported)
	 */
	public function get_supported_features() {
		// This options format is handled via only accessing options via $this->get_options()
		return array('multi_options');
	}

	/**
	 * Retrieve default options for this remote storage module.
	 *
	 * @return Array - an array of options
	 */
	public function get_default_options() {
		return array(
			'url' => ''
		);
	}

	public function bootstrap($opts = false, $connect = true) {
		if (!class_exists('HTTP_WebDAV_Client_Stream')) {
			// Needed in the include path because PEAR modules (including the file immediately required) will themselves require based on the relative path only
			set_include_path(UPDRAFTPLUS_DIR.'/includes/PEAR'.PATH_SEPARATOR.get_include_path());
			include_once(UPDRAFTPLUS_DIR.'/includes/PEAR/HTTP/WebDAV/Client.php');
		}
		return true;
	}

	public function config_print_middlesection($url) {
		$parse_url = @parse_url($url);
		if (false === $parse_url) $url = '';

		$classes = $this->get_css_classes();
		?>
			<tr class="<?php echo $classes; ?>">
				<th><?php _e('WebDAV URL', 'updraftplus');?>:</th>
				<td>
					<input data-updraft_settings_test="url" type="text" style="width: 532px" <?php $this->output_settings_field_name_and_id('url');?> value="<?php echo esc_attr(urldecode($url));?>" readonly />
					<p>
						<em><?php _e('This WebDAV URL is generated by filling in the options below. If you do not know the details, then you will need to ask your WebDAV provider.', 'updraftplus');?></em>
					</p>
				</td>
			</tr>
			<tr class="<?php echo $classes; ?>">
				<th><?php _e('Protocol (SSL or not)', 'updraftplus');?>:</th>
				<td>
					<select <?php $this->output_settings_field_name_and_id('webdav');?> class="updraft_webdav_settings" >
						<option value="webdav://"
							<?php
								if (@parse_url($url, PHP_URL_SCHEME) == "webdav") {
									echo "selected='selected'";
								}
							?>
							>webdav://
						</option>
						<option value="webdavs://"
							<?php
								if (@parse_url($url, PHP_URL_SCHEME) == "webdavs") {
									echo "selected='selected'";
								}
							?>
							>webdavs://
						</option>
					</select>
				</td>
			</tr>
			<tr class="<?php echo $classes; ?>">
				<th><?php _e('Username', 'updraftplus');?>:</th>
				<td>
					<input type="text" style="width: 432px" <?php $this->output_settings_field_name_and_id('user');?> class="updraft_webdav_settings" value="<?php echo esc_attr(urldecode(@parse_url($url, PHP_URL_USER)));?>"/>
				</td>
			</tr>
			<tr class="<?php echo $classes; ?>">
				<th><?php _e('Password', 'updraftplus');?>:</th>
				<td>
					<input type="<?php echo apply_filters('updraftplus_admin_secret_field_type', 'password'); ?>" style="width: 432px" <?php $this->output_settings_field_name_and_id('pass');?> class="updraft_webdav_settings" value="<?php echo esc_attr(urldecode(@parse_url($url, PHP_URL_PASS)));?>" />
				</td>
			</tr>
			<tr class="<?php echo $classes; ?>">
				<th><?php _e('Host', 'updraftplus');?>:</th>
				<td>
					<input type="text" style="width: 432px" <?php $this->output_settings_field_name_and_id('host');?> class="updraft_webdav_settings" value="<?php echo esc_attr(urldecode(@parse_url($url, PHP_URL_HOST)));?>"/>
					<br>
					<em id="updraft_webdav_host_error" style="display: none;"><?php echo __('Error:', 'updraftplus').' '.__('A host name cannot contain a slash.', 'updraftplus').' '.__('Enter any path in the field below.', 'updraftplus'); ?></em>
				</td>
			</tr>
			<tr class="<?php echo $classes; ?>">
				<th><?php _e('Port', 'updraftplus');?>:</th>
				<td>
					<input type="number" step="1" min="1" max="65535" style="width: 432px" <?php $this->output_settings_field_name_and_id('port');?> class="updraft_webdav_settings" value="<?php echo esc_attr(@parse_url($url, PHP_URL_PORT));?>" />
					<br>
					<em><?php _e('Leave this blank to use the default (80 for webdav, 443 for webdavs)', 'updraftplus');?></em>
				</td>
			</tr>

			<tr class="<?php echo $classes; ?>">
				<th><?php _e('Path', 'updraftplus');?>:</th>
				<td>
					<input type="text" style="width: 432px" <?php $this->output_settings_field_name_and_id('path');?> class="updraft_webdav_settings" value="<?php echo esc_attr(@parse_url($url, PHP_URL_PATH));?>"/>
				</td>
			</tr>
		<?php
	}

	public function credentials_test($posted_settings) {
	
		if (empty($posted_settings['url'])) {
			printf(__("Failure: No %s was given.", 'updraftplus'), 'URL');
			return;
		}

		$url = preg_replace('/^http/i', 'webdav', untrailingslashit($posted_settings['url']));
		$this->credentials_test_go($url);
	}
}

// Do *not* instantiate here; it is a storage module, so is instantiated on-demand
// $updraftplus_addons_webdav = new UpdraftPlus_Addons_RemoteStorage_webdav;
