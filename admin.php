<?php
/**
 * Admin for wp-custom-meta.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'wcm1_admin_menu' );

/**
 * Add menu page.
 *
 * @return void
 */
function wcm1_admin_menu() {
	add_menu_page(
		'WP Custom Meta 플러그인 예제 페이지',
		'WCM Sample',
		'manage_options',
		'wcm1',
		'wmc1_output_menu_page'
	);
}


/**
 * Output menu page content.
 *
 * @return void
 */
function wmc1_output_menu_page() {
	global $wpdb;

	$news_users = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}news_users WHERE id IN (1, 2, 3)" );
	?>
    <div class="wrap">
        <h1 class="wp-heading-inline">WP Custom Meta 샘플</h1>
        <hr class="wp-header-end">
        <form method="post" action="<?php echo esc_url( 'admin-post.php' ); ?>">
			<?php foreach ( $news_users as $idx => $user ) :
				/**
				 * @var stdClass{
				 *  id: int,
				 *  user_login: string,
				 *  user_pass: string,
				 *  user_email: string,
				 * } $user
				 */

				$recipents = wcm1_get_news_user_meta( $user->id, '_recipients', true );
				?>
                <h2>뉴스 회원 #<?php echo intval( $user->id ); ?> 정보</h2>

                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="user_login-<?php echo intval( $user->id ); ?>">로그인</label></th>
                        <td>
                            <input id="user_login-<?php echo intval( $user->id ); ?>"
                                   name="user_login[<?php echo intval( $user->id ); ?>]"
                                   type="text"
                                   maxlength="60"
                                   value="<?php echo esc_attr( $user->user_login ); ?>"/>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="user_pass-<?php echo intval( $user->id ); ?>">패스워드</label></th>
                        <td>
                            <input id="user_pass-<?php echo intval( $user->id ); ?>"
                                   name="user_pass[<?php echo intval( $user->id ); ?>]"
                                   type="password"
                                   maxlength="255"
                                   value="<?php echo esc_attr( $user->user_pass ); ?>"/>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="user_email-<?php echo intval( $user->id ); ?>">이메일</label></th>
                        <td>
                            <input id="user_email-<?php echo intval( $user->id ); ?>"
                                   name="user_email[<?php echo intval( $user->id ); ?>]"
                                   type="email"
                                   maxlength="255"
                                   value="<?php echo esc_attr( $user->user_email ); ?>"/>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="recipients-<?php echo intval( $user->id ); ?>">수신자 목록</label></th>
                        <td>
                        <textarea id="recipients-<?php echo intval( $user->id ); ?>"
                                  name="recipients[<?php echo intval( $user->id ); ?>]"
                                  rows="4"
                                  cols="80"><?php echo esc_textarea( $recipents ); ?></textarea>
                            <p class="description">한 줄에 하나씩 이메일을 입력해 주세요.</p>
                        </td>
                    </tr>
                    </tbody>
                </table>
				<?php echo $idx + 1 < count( $news_users ) ? '<hr>' : ''; ?>
			<?php endforeach; ?>
			<?php
			submit_button( "저장하기" );
			wp_nonce_field( 'wcm1' );
			?>
            <input type="hidden" name="action" value="submit_update_news_users">
        </form>
    </div>
	<?php
}


function wmc1_submit_update_news_users() {
	global $wpdb;

	check_admin_referer( 'wcm1' );

	$user_logins    = array_map( 'sanitize_text_field', $_REQUEST['user_login'] ?? [] );
	$user_emails    = array_map( 'sanitize_email', $_REQUEST['user_email'] ?? [] );
	$user_passes    = array_map( 'sanitize_text_field', $_REQUEST['user_pass'] ?? [] );
	$all_recipients = array_map( 'sanitize_textarea_field', $_REQUEST['recipients'] ?? [] );

	$user_ids = array_unique(
		array_filter(
			[
				...array_keys( $user_logins ),
				...array_keys( $user_emails ),
				...array_keys( $user_passes ),
				...array_keys( $all_recipients ),
			]
		)
	);

	sort( $user_ids );

	foreach ( $user_ids as $user_id ) {
		$user_login = $user_logins[ $user_id ] ?? '';
		$user_email = $user_emails[ $user_id ] ?? '';
		$user_pass  = $user_passes[ $user_id ] ?? '';
		$recipients = $all_recipients[ $user_id ] ?? '';

		$wpdb->update(
			"{$wpdb->prefix}news_users",
			[
				'user_login' => $user_login,
				'user_email' => $user_email,
				'user_pass'  => $user_pass,
			],
			[ 'id' => $user_id ],
			[
				'user_login' => '%s',
				'user_email' => '%s',
				'user_pass'  => '%s',
			],
			[ 'id' => '%d' ]
		);

		wcm1_update_news_user_meta( $user_id, '_recipients', $recipients );
	}

	wp_safe_redirect( wp_get_referer() );

	exit;
}

add_action( 'admin_post_submit_update_news_users', 'wmc1_submit_update_news_users' );
