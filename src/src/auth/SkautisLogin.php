<?php

declare( strict_types=1 );

namespace SkautisIntegration\Auth;

use SkautisIntegration\Utils\Helpers;

final class SkautisLogin {

	private $skautisGateway;
	private $wpLoginLogout;

	public function __construct( SkautisGateway $skautisGateway, WpLoginLogout $wpLoginLogout ) {
		$this->skautisGateway = $skautisGateway;
		$this->wpLoginLogout  = $wpLoginLogout;
	}

	public function isUserLoggedInSkautis(): bool {
		if ( $this->skautisGateway->isInitialized() ) {
			return $this->skautisGateway->getSkautisInstance()->getUser()->isLoggedIn() && $this->skautisGateway->getSkautisInstance()->getUser()->isLoggedIn( true );
		}

		return false;
	}

	public function setLoginDataToLocalSkautisInstance( array $data = array() ): bool {
		$data = apply_filters( SKAUTISINTEGRATION_NAME . '_login_data_for_skautis_instance', $data );

		if ( isset( $data['skautIS_Token'] ) ) {
			$this->skautisGateway->getSkautisInstance()->setLoginData( $data );

			if ( ! $this->isUserLoggedInSkautis() ) {
				return false;
			}

			do_action( SKAUTISINTEGRATION_NAME . '_after_user_is_logged_in_skautis', $data );

			return true;
		}

		return false;
	}

	public function login() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $_GET['redirect_to'] ) && $_GET['redirect_to'] ) {
			$returnUrl = esc_url_raw( $_GET['redirect_to'] );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} elseif ( isset( $_GET['ReturnUrl'] ) && $_GET['ReturnUrl'] ) {
			$returnUrl = esc_url_raw( $_GET['ReturnUrl'] );
		} else {
			$returnUrl = Helpers::getCurrentUrl();
		}

		if ( strpos( $returnUrl, 'logoutFromSkautis' ) !== false ) {
			$this->skautisGateway->logout();
			$returnUrl = remove_query_arg( 'logoutFromSkautis', $returnUrl );
		}

		if ( ! $this->isUserLoggedInSkautis() ) {
			wp_redirect( esc_url_raw( $this->skautisGateway->getSkautisInstance()->getLoginUrl( $returnUrl ) ), 302 );
			exit;
		}

		if ( strpos( $returnUrl, 'noWpLogin' ) !== false ) {
			$this->wpLoginLogout->tryToLoginToWp();
			wp_safe_redirect( esc_url_raw( $returnUrl ), 302 );
			exit;
		} else {
			$this->wpLoginLogout->loginToWp();
		}
	}

	public function loginConfirm() {
		if ( $this->setLoginDataToLocalSkautisInstance( $_POST ) ) {
			if ( ! isset( $_GET['ReturnUrl'] ) || strpos( esc_url_raw( $_GET['ReturnUrl'] ), 'noWpLogin' ) === false ) {
				$this->wpLoginLogout->loginToWp();
			} elseif ( isset( $_GET['ReturnUrl'] ) ) {
				$this->wpLoginLogout->tryToLoginToWp();
				wp_safe_redirect( esc_url_raw( $_GET['ReturnUrl'] ), 302 );
				exit;
			}
		} elseif ( $this->isUserLoggedInSkautis() ) {
			if ( ! isset( $_GET['ReturnUrl'] ) || strpos( esc_url_raw( $_GET['ReturnUrl'] ), 'noWpLogin' ) === false ) {
				$this->wpLoginLogout->loginToWp();
			} elseif ( isset( $_GET['ReturnUrl'] ) ) {
				$this->wpLoginLogout->tryToLoginToWp();
				wp_safe_redirect( esc_url_raw( $_GET['ReturnUrl'] ), 302 );
				exit;
			}
		}
	}

	public function changeUserRoleInSkautis( int $roleId ) {
		if ( $roleId > 0 ) {
			$result = $this->skautisGateway->getSkautisInstance()->UserManagement->LoginUpdate(
				array(
					'ID'          => $this->skautisGateway->getSkautisInstance()->getUser()->getLoginId(),
					'ID_UserRole' => $roleId,
				)
			);

			if ( ! $result || ! isset( $result->ID_Unit ) ) {
				return;
			}

			$this->skautisGateway->getSkautisInstance()->getUser()->updateLoginData(
				$this->skautisGateway->getSkautisInstance()->getUser()->getLoginId(),
				$roleId,
				$result->ID_Unit
			);
		}
	}

}
