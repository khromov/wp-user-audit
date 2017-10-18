<?php

/**
 * This class handles the addition of admin columns
 *
 * Class WP_Password_Audit_Admin_Columns
 */
class WP_User_Audit_Admin_Columns {

  /**
   * @param $column_headers
   *
   * @return array
   */
  static function add_user_column_password_strength($column_headers) {
    $column_headers['wpa_password_strength'] = __('Password strength', WP_User_Audit::TD);
    return $column_headers;
  }

  /**
   * @param $value
   * @param $column_name
   * @param $user_id
   *
   * @return string
   */
  static function get_password_strength_value($value, $column_name, $user_id) {
    if ( $column_name === 'wpa_password_strength' ) {
      return WP_User_Audit::get_friendly_password_strength($user_id);
    }

    return $value;
  }

  /**
   * @param $columns
   *
   * @return array
   */
  static function user_sortable_columns($columns) {
    $columns['wpa_password_strength'] = 'wpa_password_strength';
    return $columns;
  }

  /**
   * Modify the query to allow sorting
   *
   * @param $query
   */
  static function admin_columns_query_sort($query) {
    //Bail if not in admin
    if(!is_admin()) {
      return;
    }

    /* @var $query WP_User_Query  */
    if($query->get('orderby') === 'wpa_password_strength') {
      $query->set('orderby', 'meta_value');
      $query->set('meta_key', 'wpa_current_password_strength');
    }
  }
}