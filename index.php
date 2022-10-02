<?php
/**
 * Plugin Name:       커스텀 메타 스터디
 * Description:       커스텀 오브젝트에 워드프레스 메타를 확장하여 적용하는 법 스터디.
 * Author:            changwoo
 * Author URI:        https://blog.changwoo.pe.kr
 * Plugin URI:        https://github.com/chwnam/wp-custom-meta
 * Version:           1.0.0
 * Requires at least: 5.0.0
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WCM1_DB_VERSION = '1.0.0';


/**
 * Get current install DB version.
 *
 * @return string
 */
function wcm1_get_db_version(): string {
	return get_option( 'wcm1_db_version', '' );
}


/**
 * Update DB version.
 *
 * @return void
 */
function wcm1_update_db_version(): void {
	update_option( 'wcm1_db_version', WCM1_DB_VERSION );
}


/**
 * Delete DB version.
 *
 * @return void
 */
function wcm1_delete_db_version(): void {
	delete_option( 'wcm1_db_version' );
}


/**
 * Create the plugin's custom tables.
 *
 * @return void
 */
function wcm1_create_tables() {
	global $wpdb;

	if ( ! function_exists( 'dbDelta' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}

	$query = "CREATE TABLE {$wpdb->prefix}news_users (\n" .
	         " id bigint(20) unsigned NOT NULL auto_increment,\n" .
	         " user_login varchar(60) NOT NULL default '',\n" .
	         " user_pass varchar(255) NOT NULL default '',\n" .
	         " user_email varchar(255) NOT NULL default '',\n" .
	         " PRIMARY KEY  (id),\n" .
	         " KEY idx_user_login (user_login),\n" .
	         " KEY idx_user_email (user_email)\n" .
	         ") {$wpdb->get_charset_collate()};\n";

	$query .= "CREATE TABLE {$wpdb->prefix}news_usermeta (\n" .
	          " meta_id bigint(20) unsigned NOT NULL auto_increment,\n" .
	          " news_user_id bigint(20) unsigned NOT NULL default '0',\n" .
	          " meta_key varchar(255) default NULL,\n" .
	          " meta_value longtext,\n" .
	          " PRIMARY KEY  (meta_id),\n" .
	          " KEY idx_news_user_id (news_user_id),\n" .
	          " KEY idx_meta_key (meta_key(191))\n" .
	          ") {$wpdb->get_charset_collate()};\n";

	dbDelta( $query );
}


/**
 * Insert initial records.
 *
 * @return void
 */
function wcm1_initial_records() {
	global $wpdb;

	$records = [
		[
			'id'         => '1',
			'user_login' => 'sysadmin',
			'user_pass'  => wp_generate_password( 25, true, true ),
			'user_email' => 'sysadmin@email.com',
		],
		[
			'id'         => '2',
			'user_login' => 'developer',
			'user_pass'  => wp_generate_password( 25, true, true ),
			'user_email' => 'developer@email.com',
		],
		[
			'id'         => '3',
			'user_login' => 'designer',
			'user_pass'  => wp_generate_password( 25, true, true ),
			'user_email' => 'designer@email.com',
		],
	];

	foreach ( $records as $record ) {
		$wpdb->insert(
			"{$wpdb->prefix}news_users",
			$record,
			[
				'id'         => '%d',
				'user_login' => '%s',
				'user_pass'  => '%s',
				'user_email' => '%s',
			]
		);
	}
}


/**
 * Drop the plugin's custom table.
 *
 * @return void
 */
function wcm1_drop_table() {
	global $wpdb;

	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}news_users" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}news_usermeta" );
}


/**
 * Install tables when the plugin is activated.
 *
 * @callback
 *
 * @return void
 */
function wcm1_install_tables() {
	global $wpdb;

	$suppress = $wpdb->suppress_errors( true );

	wcm1_create_tables();
	wcm1_initial_records();
	wcm1_update_db_version();

	$wpdb->suppress_errors( $suppress );
}


register_activation_hook( __FILE__, 'wcm1_install_tables' );


/**
 * Drop tables when the plugin is deactivated.
 *
 * @callback
 *
 * @return void
 */
function wcm1_uninstall_tables() {
	wcm1_drop_table();
	wcm1_delete_db_version();
}


register_deactivation_hook( __FILE__, 'wcm1_uninstall_tables' );


/**
 * Update table schema when the plugin's database version is changed.
 *
 * @return void
 */
function wcm1_update_tables() {
	if ( wcm1_get_db_version() !== WCM1_DB_VERSION ) {
		wcm1_create_tables(); // Run dbDelta().
		wcm1_update_db_version();
	}
}


add_action( 'plugins_loaded', 'wcm1_update_tables' );


/**
 * Define our custom object type.
 */
function wcm1_define_extra_objects() {
	global $wpdb;

	static $init = false;

	if ( ! $init ) {
		$init = true;

		// Dynamically add our extra tables.
		// $wpdb->{$object_type}
		$wpdb->news_user     = $wpdb->prefix . 'news_users';
		$wpdb->news_usermeta = $wpdb->prefix . 'news_usermeta';

		// In our case, $object_type is 'news_user'.
		add_filter( 'get_object_subtype_news_user', fn() => 'news_user', 10, 2 );
	}
}


add_action( 'plugins_loaded', 'wcm1_define_extra_objects' );


/**
 * Initialize meta field.
 *
 * @return void
 *
 * @uses wcm1_sanitize_recipients()
 * @uses wcm1_authorize_recipients()
 */
function wcm1_init_meta() {
	register_meta(
		'news_user',
		'_recipients',
		[
			'type'              => 'string',
			'description'       => '이메일 수신자 목록. 한 줄에 하나씩 입력하세요. 저장 후 오름차순으로 정렬됩니다.',
			'default'           => '',
			'single'            => true,
			'sanitize_callback' => 'wcm1_sanitize_recipients',
			'auth_callback'     => 'wcm1_authorize_recipients',
			'show_in_rest'      => false,
		]
	);
}


/**
 * @param mixed  $meta_value
 * @param string $meta_key
 * @param string $object_type
 *
 * @return string
 */
function wcm1_sanitize_recipients( $meta_value, string $meta_key, string $object_type ): string {
	if ( 'news_user' === $object_type && '_recipients' === $meta_key ) {
		$emails = array_unique( array_filter( array_map( 'sanitize_email', explode( "\r\n", $meta_value ) ) ) );
		sort( $emails );
		$meta_value = implode( "\r\n", $emails );
	}

	return $meta_value;
}


/**
 * @param bool   $allowed
 * @param string $meta_key
 * @param int    $object_id
 * @param int    $user_id
 * @param string $cap
 * @param array  $caps
 *
 * @return bool
 */
function wcm1_authorize_recipients( bool $allowed, string $meta_key, int $object_id, int $user_id, string $cap, array $caps ): bool {
	if ( '_recipients' === $meta_key ) {
		if ( $object_id === $user_id ) {
			$allowed = true;
		} else {
			$allowed = current_user_can( 'administrator' );
		}
	}

	return $allowed;
}


add_action( 'init', 'wcm1_init_meta' );


/**
 * Map meta cap for custom meta keys
 *
 * @param array  $caps
 * @param string $cap
 * @param int    $user_id
 * @param        $args
 *
 * @return array
 */
function wcm1_map_meta_cap( array $caps, string $cap, int $user_id, $args ): array {
	switch ( $cap ) {
		case 'add_news_user_meta':
		case 'edit_news_user_meta':
		case 'delete_news_user_meta':
		case 'get_news_user_meta':
			// Actually every user should have $cap capabiliy, but this is just an illustration.
			// Go easy. Just reset $cap.
			$caps      = [];
			$object_id = $args[0] ?? 0;
			$meta_key  = $args[1] ?? '';

			if ( $meta_key ) {
				$allowed = ! is_protected_meta( $meta_key, 'news_user' );
				if ( has_filter( "auth_news_user_meta_{$meta_key}" ) ) {
					$allowed = apply_filters( "auth_news_user_meta_{$meta_key}", $allowed, $meta_key, $object_id, $user_id, $cap, $caps );
				}
				if ( ! $allowed ) {
					$caps[] = 'do_now_allow';
				}
			}
			break;
	}

	return $caps;
}


add_filter( 'map_meta_cap', 'wcm1_map_meta_cap', 10, 4 );


/**
 * @param int    $user_id
 * @param string $meta_key
 * @param mixed  $meta_value
 * @param bool   $unique
 *
 * @return false|int
 */
function wcm1_add_news_user_meta( int $user_id, string $meta_key, $meta_value, bool $unique = false ) {
	return add_metadata( 'news_user', $user_id, $meta_key, $meta_value, $unique );
}


/**
 * @param int    $user_id
 * @param string $meta_key
 * @param mixed  $meta_value
 *
 * @return bool
 */
function wcm1_delete_news_user_meta( int $user_id, string $meta_key, $meta_value = '' ): bool {
	return delete_metadata( 'news_user', $user_id, $meta_key, $meta_value );
}


/**
 * @param int    $user_id
 * @param string $meta_key
 * @param bool   $single
 *
 * @return array|false|mixed
 */
function wcm1_get_news_user_meta( int $user_id, string $meta_key = '', bool $single = false ) {
	return get_metadata( 'news_user', $user_id, $meta_key, $single );
}


/**
 * @param int    $user_id
 * @param string $meta_key
 * @param mixed  $meta_value
 * @param mixed  $prev_value
 *
 * @return bool|int
 */
function wcm1_update_news_user_meta( int $user_id, string $meta_key, $meta_value, $prev_value = '' ) {
	return update_metadata( 'news_user', $user_id, $meta_key, $meta_value, $prev_value );
}


if ( is_admin() ) {
	include __DIR__ . '/admin.php';
}
