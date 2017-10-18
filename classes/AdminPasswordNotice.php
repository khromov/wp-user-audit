<?php

/**
 * This class handles display of notices for users with weak passwords
 *
 * Class AdminPasswordNotice
 */
class WP_User_Audit_Admin_Password_Notice {
  static function maybe_show_notice() {
    $current_password_strength = (int)get_user_option('wpa_current_password_strength');
    ?>
    <?php if($current_password_strength > -1 && $current_password_strength < apply_filters('wpa_password_notice_password_strength_less_than_strength', 3) && get_current_screen() && get_current_screen()->base !== 'profile'): ?>
    <div class="notice notice-warning">
      <p><strong><?php _e( 'Password warning', WP_User_Audit::TD ); ?></strong></p>
      <p>
        <?php _e('You are using a weak password. This poses a security risk. Please change to a better password on your ', WP_User_Audit::TD ); ?>
	      <a href="<?php echo get_edit_user_link(); ?>#password"><?php _e('user profile page'); ?>.</a>
      </p>
    </div>
    <?php endif;
  }
}