<?php

declare( strict_types=1 );

namespace Skautis_Integration\Auth;

use Skautis_Integration\Utils\Helpers;

final class Skautis_Login {

	private $skautis_gateway;
	private $wp_login_logout;

	public function __construct( Skautis_Gateway $skautis_gateway, WP_Login_Logout $wp_login_logout ) {
		$this->skautis_gateway = $skautis_gateway;
		$this->wp_login_logout = $wp_login_logout;
	}

	public function is_user_logged_in_skautis(): bool {
		if ( $this->skautis_gateway->is_initialized() ) {
			return $this->skautis_gateway->get_skautis_instance()->getUser()->isLoggedIn() && $this->skautis_gateway->get_skautis_instance()->getUser()->isLoggedIn( true );
		}

		return false;
	}

	public function set_login_data_to_local_skautis_instance( array $data = array() ): bool {
		$data = apply_filters( SKAUTIS_INTEGRATION_NAME . '_login_data_for_skautis_instance', $data );

		if ( isset( $data['skautIS_Token'] ) ) {
			$this->skautis_gateway->get_skautis_instance()->setLoginData( $data );

			if ( ! $this->is_user_logged_in_skautis() ) {
				return false;
			}

			do_action( SKAUTIS_INTEGRATION_NAME . '_after_user_is_logged_in_skautis', $data );

			return true;
		}

		return false;
	}

	public function login() {
		$return_url = Helpers::get_login_logout_redirect();

		if ( strpos( $return_url, 'logoutFromSkautis' ) !== false ) {
			$this->skautis_gateway->logout();
			$return_url = remove_query_arg( 'logoutFromSkautis', $return_url );
		}

		if ( ! $this->is_user_logged_in_skautis() ) {
			wp_safe_redirect( esc_url_raw( $this->skautis_gateway->get_skautis_instance()->getLoginUrl( $return_url ) ), 302 );
			exit;
		}

		if ( strpos( $return_url, 'noWpLogin' ) !== false ) {
			$this->wp_login_logout->try_to_login_to_wp();
			wp_safe_redirect( esc_url_raw( $return_url ), 302 );
			exit;
		} else {
			$this->wp_login_logout->login_to_wp();
		}
	}

	public function login_confirm() {
		$return_url = Helpers::get_return_url();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $this->set_login_data_to_local_skautis_instance( $_POST ) ) {
			if ( is_null( $return_url ) || strpos( $return_url, 'noWpLogin' ) === false ) {
				$this->wp_login_logout->login_to_wp();
			} elseif ( ! is_null( $return_url ) ) {
				$this->wp_login_logout->try_to_login_to_wp();
				wp_safe_redirect( $return_url, 302 );
				exit;
			}
		} elseif ( $this->is_user_logged_in_skautis() ) {
			if ( is_null( $return_url ) || strpos( $return_url, 'noWpLogin' ) === false ) {
				$this->wp_login_logout->login_to_wp();
			} elseif ( ! is_null( $return_url ) ) {
				$this->wp_login_logout->try_to_login_to_wp();
				wp_safe_redirect( $return_url, 302 );
				exit;
			}
		}
	}

	public function change_user_role_in_skautis( int $role_id ) {
		if ( $role_id > 0 ) {
			$result = $this->skautis_gateway->get_skautis_instance()->UserManagement->LoginUpdate(
				array(
					'ID'          => $this->skautis_gateway->get_skautis_instance()->getUser()->getLoginId(),
					'ID_UserRole' => $role_id,
				)
			);

			if ( ! $result || ! isset( $result->ID_Unit ) ) {
				return;
			}

			$this->skautis_gateway->get_skautis_instance()->getUser()->updateLoginData(
				$this->skautis_gateway->get_skautis_instance()->getUser()->getLoginId(),
				$role_id,
				$result->ID_Unit
			);
		}
	}

}
