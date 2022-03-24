<?php

declare( strict_types=1 );

namespace SkautisIntegration\Auth;

use SkautisIntegration\General\Actions;
use SkautisIntegration\Utils\Helpers;

final class Connect_And_Disconnect_WP_Account {

	private $skautis_gateway;
	private $skautis_login;

	public function __construct( Skautis_Gateway $skautisGateway, Skautis_Login $skautisLogin ) {
		$this->skautis_gateway = $skautisGateway;
		$this->skautis_login   = $skautisLogin;
	}

	private function set_skautis_user_id_to_wp_account( int $wpUserId, int $skautisUserId ) {
		$returnUrl = Helpers::get_return_url();
		if ( ! is_null( $returnUrl ) ) {
			Helpers::validate_nonce_from_url( $returnUrl, SKAUTISINTEGRATION_NAME . '_connectWpAccountWithSkautis' );

			update_user_meta( $wpUserId, 'skautisUserId_' . $this->skautis_gateway->get_env(), absint( $skautisUserId ) );

			wp_safe_redirect( $returnUrl, 302 );
			exit;
		}
	}

	public function print_connect_and_disconnect_button( int $wpUserId ) {
		$skautisUserId = get_user_meta( $wpUserId, 'skautisUserId_' . $this->skautis_gateway->get_env(), true );
		if ( $skautisUserId ) {
			if ( ! Helpers::user_is_skautis_manager() && get_option( SKAUTISINTEGRATION_NAME . '_allowUsersDisconnectFromSkautis' ) !== '1' ) {
				return;
			}
			$returnUrl = add_query_arg( SKAUTISINTEGRATION_NAME . '_disconnectWpAccountFromSkautis', wp_create_nonce( SKAUTISINTEGRATION_NAME . '_disconnectWpAccountFromSkautis' ), Helpers::get_current_url() );
			$url       = add_query_arg( 'ReturnUrl', rawurlencode( $returnUrl ), get_home_url( null, 'skautis/auth/' . Actions::DISCONNECT_ACTION ) );

			echo '
			<a href="' . esc_url( $url ) . '"
			   class="button">' . esc_html__( 'Zrušit propojení účtu se skautISem', 'skautis-integration' ) . '</a>
			';
		} elseif ( get_current_screen()->id === 'profile' ) {
			$returnUrl = add_query_arg( SKAUTISINTEGRATION_NAME . '_connectWpAccountWithSkautis', wp_create_nonce( SKAUTISINTEGRATION_NAME . '_connectWpAccountWithSkautis' ), Helpers::get_current_url() );
			$url       = add_query_arg( 'ReturnUrl', rawurlencode( $returnUrl ), get_home_url( null, 'skautis/auth/' . Actions::CONNECT_ACTION ) );

			echo '
			<a href="' . esc_url( $url ) . '"
			   class="button">' . esc_html__( 'Propojit tento účet se skautISem', 'skautis-integration' ) . '</a>
			';
		}
	}

	public function connect() {
		if ( ! $this->skautis_login->is_user_logged_in_skautis() ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! $this->skautis_login->set_login_data_to_local_skautis_instance( $_POST ) ) {
				$returnUrl = Helpers::get_return_url() ?? Helpers::get_current_url();
				wp_safe_redirect( esc_url_raw( $this->skautis_gateway->get_skautis_instance()->getLoginUrl( $returnUrl ) ), 302 );
				exit;
			}
		}

		$userDetail = $this->skautis_gateway->get_skautis_instance()->UserManagement->UserDetail();

		if ( $userDetail && isset( $userDetail->ID ) && $userDetail->ID > 0 ) {
			$this->set_skautis_user_id_to_wp_account( get_current_user_id(), $userDetail->ID );
		}
	}

	public function connect_wp_user_to_skautis() {
		if ( ! isset( $_GET[ SKAUTISINTEGRATION_NAME . '_connect_user_nonce' ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ SKAUTISINTEGRATION_NAME . '_connect_user_nonce' ] ) ), SKAUTISINTEGRATION_NAME . '_connect_user' ) ||
			! $this->skautis_login->is_user_logged_in_skautis() ||
			! Helpers::user_is_skautis_manager() ||
			is_null( Helpers::get_return_url() )
		) {
			wp_die( esc_html__( 'Nemáte oprávnění k propojování uživatelů.', 'skautis-integration' ), esc_html__( 'Neautorizovaný přístup', 'skautis-integration' ) );
		}

		if ( ! isset( $_GET['wpUserId'], $_GET['skautisUserId'] ) ) {
			return;
		}

		$wpUserId      = absint( $_GET['wpUserId'] );
		$skautisUserId = absint( $_GET['skautisUserId'] );

		if ( $wpUserId > 0 && $skautisUserId > 0 ) {
			$this->set_skautis_user_id_to_wp_account( $wpUserId, $skautisUserId );
		}
	}

	public function get_connect_wp_user_to_skautis_url(): string {
		$returnUrl = Helpers::get_current_url();
		$returnUrl = add_query_arg( SKAUTISINTEGRATION_NAME . '_connectWpAccountWithSkautis', wp_create_nonce( SKAUTISINTEGRATION_NAME . '_connectWpAccountWithSkautis' ), $returnUrl );
		$url       = add_query_arg( 'ReturnUrl', rawurlencode( $returnUrl ), get_home_url( null, 'skautis/auth/' . Actions::CONNECT_WP_USER_TO_SKAUTIS_ACTION ) );

		return esc_url( wp_nonce_url( $url, SKAUTISINTEGRATION_NAME . '_connect_user', SKAUTISINTEGRATION_NAME . '_connect_user_nonce' ) );
	}

	public function disconnect() {
		if ( is_user_logged_in() ) {
			$returnUrl = Helpers::get_return_url();
			if ( ! is_null( $returnUrl ) ) {
				Helpers::validate_nonce_from_url( $returnUrl, SKAUTISINTEGRATION_NAME . '_disconnectWpAccountFromSkautis' );

				if ( strpos( $returnUrl, 'profile.php' ) !== false ) {
					delete_user_meta( get_current_user_id(), 'skautisUserId_' . $this->skautis_gateway->get_env() );
				} elseif ( ( strpos( $returnUrl, 'user-edit_php' ) !== false ||
							strpos( $returnUrl, 'user-edit.php' ) !== false ) &&
							strpos( $returnUrl, 'user_id=' ) !== false ) {
					if ( ! preg_match( '~user_id=(\d+)~', $returnUrl, $result ) ) {
						return;
					}
					if ( is_array( $result ) && isset( $result[1] ) && $result[1] > 0 ) {
						$userId = absint( $result[1] );
						if ( Helpers::user_is_skautis_manager() ) {
							delete_user_meta( $userId, 'skautisUserId_' . $this->skautis_gateway->get_env() );
						}
					}
				}
			}
		}

		$returnUrl = Helpers::get_return_url();
		if ( ! is_null( $returnUrl ) ) {
			wp_safe_redirect( $returnUrl, 302 );
			exit;
		} else {
			wp_safe_redirect( get_home_url(), 302 );
			exit;
		}
	}

}