<?php
/*
Plugin Name: User Audit
Plugin URI: https://wordpress.org/plugins/user-audit/
Description: Keep track of your users password strength and remind them to set a strong password
Version: 1.0
Author: khromov
Author URI: https://khromov.se
Text Domain: user-audit
Domain Path: /languages
*/

define('WP_USER_AUDIT_DIR', dirname(__FILE__));

require WP_USER_AUDIT_DIR . '/vendor/autoload.php';
require WP_USER_AUDIT_DIR . '/classes/AdminColumns.php';
require WP_USER_AUDIT_DIR . '/classes/AdminPasswordNotice.php';

class WP_User_Audit {

  const TD = 'user-audit';

  function __construct()
  {
  	//Translations
    add_action( 'init', array( $this, 'load_textdomain' ) );

  	//On changed password hooks
    add_action('after_password_reset', array($this, 'on_password_reset'), 10, 2);
    add_action('profile_update', array($this, 'on_password_change'), 10, 2);

    //This is a workaround for grabbing the unhashed password.
    //Because we hook so late, we assume there  will be no errors following this hook.
    add_filter('user_profile_update_errors', array($this, 'inspect_password_field'), 10000, 3);

    //Display info on user profile
    add_action( 'show_user_profile', array($this, 'additional_profile_fields'), apply_filters('wpa_profile_info_priority', 100));
    add_action( 'edit_user_profile', array($this, 'additional_profile_fields'), apply_filters('wpa_profile_info_priority', 100));

	//Add admin columns
    add_action('manage_users_columns', array('WP_User_Audit_Admin_Columns', 'add_user_column_password_strength'), 100);
    add_action('wpmu_users_columns', array('WP_User_Audit_Admin_Columns', 'add_user_column_password_strength'), 100);

    //Populate admin columns
    add_action('manage_users_custom_column', array('WP_User_Audit_Admin_Columns', 'get_password_strength_value'), 10, 3);

    //Make password field sortable
    add_filter('manage_users_sortable_columns', array('WP_User_Audit_Admin_Columns', 'user_sortable_columns'));
    add_filter('manage_users-network_sortable_columns', array('WP_User_Audit_Admin_Columns', 'user_sortable_columns'));

    //Perform sorting when needed
    add_action('pre_get_users', array('WP_User_Audit_Admin_Columns', 'admin_columns_query_sort'));

    //Admin notice
    add_action( 'admin_notices', array('WP_User_Audit_Admin_Password_Notice', 'maybe_show_notice'), 1 );
  }

  /**
   * Load the textdomain so we can support other languages
   */
  public function load_textdomain() {
    load_plugin_textdomain( self::TD, false, basename( dirname( __FILE__ ) ) . '/languages' );
  }

  /**
   * When password gets reset through the "Forgot password" mechanism
   *
   * @param WP_User $user
   * @param string $new_password
   */
  function on_password_reset($user, $new_password) {

    update_user_option($user->ID, 'wpa_last_update_timestamp', current_time('timestamp'), true);
    update_user_option($user->ID, 'wpa_last_update_user_id', $user->ID, true);
    update_user_option($user->ID, 'wpa_current_password_strength', self::get_password_strength($user->ID, $new_password), true);
  }

  /**
   * When password gets changed through profile page
   */
  function on_password_change($user_ID, $old_data) {

  	//Get existing user data
    $new_data = get_userdata($user_ID);

    //If password was changed, make a note of it
    if ( $new_data->user_pass && $new_data->user_pass != $old_data->user_pass ) {
      //Mark that the password was updated
      update_user_option($user_ID, 'wpa_last_update_timestamp', current_time('timestamp'), true);

      //Mark who updated the password
      update_user_option($user_ID, 'wpa_last_update_user_id', get_current_user_id(), true);
    }
  }

  /**
   * Inspects the password field and updated the user profile
   * with its strength.
   *
   * @param $errors
   * @param $update
   * @param WP_User $user
   */
  function inspect_password_field($errors, $update, $user) {

  	//Only run this if there are no existing errors.
    if(!$errors->errors) {
      $strength = self::get_password_strength($user->ID, $user->user_pass);
      update_user_option($user->ID, 'wpa_current_password_strength', $strength, true);
    }
  }

  /**
   * Display the data
   *
   * @param WP_User $user
   * @return null
   */
  function additional_profile_fields( $user ) {

    /**
     * Handle the access control.
     *
     * - If this is a multisite, we allow super admins to view the data
     * - If this is a single site, we allow administrators to see other users data. For info on implementation see https://core.trac.wordpress.org/ticket/22624
     * - If none of these are true, we allow users to see their own data if wpa_current_user_can_see_own_info filter returns "true". (On by default)
     */

    if((is_multisite() ? is_super_admin(get_current_user_id()) : current_user_can('administrator')) ||
      (apply_filters('wpa_current_user_can_see_own_info', true) && get_current_user_id() === $user->ID)) {
      $this->render_profile_fields($user);
    }
    else {
        return;
    }
  }

  function render_profile_fields($user) {
    $date_format = get_option( 'date_format' );
    $last_updated = (int)get_user_option('wpa_last_update_timestamp', $user->ID);

    $updated_by = (int)get_user_option('wpa_last_update_user_id', $user->ID);
    $updated_by_user = new WP_User($updated_by);

    $password_last_set_days_ago = floor((current_time('timestamp') - $last_updated) / 86400);

    $password_strength_string = self::get_friendly_password_strength($user->ID)

    ?>
	  <h3><?php _e('Password audit information', self::TD); ?></h3>

	  <table class="form-table">

		  <tr>
			  <th><label for="last-update"><?php _e('Last password update', self::TD); ?></label></th>
			  <td>
                <?php if($last_updated): ?>
                  <?php echo date_i18n($date_format, get_site_option('wpa_activation_timestamp')); ?> - <?php echo $password_last_set_days_ago; ?> <?php _e('day(s) ago', self::TD); ?>.
                <?php else: ?>
                  <?php _e('Not updated since plugin activation on ' . date_i18n($date_format, get_site_option('wpa_activation_timestamp')), self::TD); ?>
                <?php endif; ?>
			  </td>
		  </tr>
		  <tr>
			  <th><label for="last-update"><?php _e('Current password strength', self::TD); ?></label></th>
			  <td>
                <?php if($last_updated): ?>
                  <?php echo $password_strength_string; ?>
                <?php else: ?>
                  <?php _e('N/A', self::TD); ?>
                <?php endif; ?>
			  </td>
		  </tr>
		  <tr>
			  <th><label for="last-update"><?php _e('Current password set by', self::TD); ?></label></th>
			  <td>
                <?php if($last_updated): ?>
                  <?php if($updated_by_user->ID): ?>
					    <a href="<?php echo get_edit_user_link($updated_by_user->ID); ?>"><?php echo $updated_by_user->nickname; ?> (User ID: <?php echo $updated_by_user->ID; ?>)</a>
                  <?php else: ?>
					    <?php _e('Unknown / deleted user.', self::TD); ?>
                  <?php endif; ?>
                <?php else: ?>
				    <?php _e('N/A', self::TD); ?>
                <?php endif; ?>

			  </td>
		  </tr>

	  </table>
    <?php
  }

  /**
   * Returns a printable representation of the password strength
   *
   * @param $user_id
   *
   * @return string
   */
  static function get_friendly_password_strength($user_id) {

    $password_strength = get_user_option('wpa_current_password_strength', $user_id);

    $password_strength_mapping = array(
      0 => '&#9733;<br>' . __('Very weak', self::TD),
      1 => '&#9733;&#9733;<br>' . __('Weak', self::TD),
      2 => '&#9733;&#9733;&#9733;<br>' . __('Medium', self::TD),
      3 => '&#9733;&#9733;&#9733;&#9733;<br>' . __('Strong', self::TD),
      4 => '&#9733;&#9733;&#9733;&#9733;&#9733;<br>' . __('Very strong', self::TD)
    );

    return isset($password_strength_mapping[$password_strength]) ? $password_strength_mapping[$password_strength] : __('-', self::TD);
  }

  /**
   * Calculates password strength and returns it
   *
   * @param $user_id
   * @param $new_password
   * @return int Password strength
   */
  static function get_password_strength($user_id, $new_password) {
    //Mark the password strength
    $zxcvbn = new \ZxcvbnPhp\Zxcvbn();

    //The data below is sent to the JS-driven password change form. We try to replicate them as best we can.
    //[ 'user_login', 'first_name', 'last_name', 'nickname', 'display_name', 'email', 'url', 'description', 'weblog_title', 'admin_email' ];

    $userInputs = array_filter(array(
      self::get_user_profile_field('user_login', $user_id),
      self::get_user_profile_field('first_name', $user_id),
      self::get_user_profile_field('last_name', $user_id),
      self::get_user_profile_field('nickname', $user_id),
      self::get_user_profile_field('display_name', $user_id),
      self::get_user_profile_field('user_email', $user_id),
      self::get_user_profile_field('user_url', $user_id),
      self::get_user_profile_field('description', $user_id),
      get_option('admin_email'),
      get_option('blogname'),
      get_site_url()
    ));

    $score_data = $zxcvbn->passwordStrength($new_password, $userInputs);
    return $score_data['score'];
  }

  static private function get_user_profile_field($field, $user_id) {
    $user = get_user_by('id', $user_id);

    return isset($user->$field) ? $user->$field : false;
  }

  /**
   * Mark plugin activation
   */
  static function install() {
    update_site_option('wpa_activation_timestamp', current_time('timestamp'));

    global $wpdb;

    //This is required so that we can sort by users password strength later
    //We're doing a raw query because there may be some quirkiness otherwise on multisite, see: https://core.trac.wordpress.org/ticket/38851
    $users = $wpdb->get_results("SELECT ID FROM {$wpdb->users};");
    foreach($users as $user) {
      update_user_option($user->ID, 'wpa_current_password_strength', -1, true);
    }
  }

  /**
   * Delete last activation timestamp
   */
  static function uninstall() {
    delete_site_option('wpa_activation_timestamp');

    global $wpdb;

    ///We're doing a raw query because there may be some quirkiness otherwise on multisite, see: https://core.trac.wordpress.org/ticket/38851
    $users = $wpdb->get_results("SELECT ID FROM {$wpdb->users};");
    foreach($users as $user) {
      delete_user_option($user->ID, 'wpa_current_password_strength', true);
      delete_user_option($user->ID, 'wpa_last_update_timestamp', true);
      delete_user_option($user->ID, 'wpa_last_update_user_id', true);
    }
  }
}

/**
 * Activation / deactivation hooks
 */
register_activation_hook( __FILE__, array( 'WP_User_Audit', 'install' ) );
register_deactivation_hook( __FILE__, array( 'WP_User_Audit', 'uninstall' ) );

$wp_password_audit = new WP_User_Audit();

