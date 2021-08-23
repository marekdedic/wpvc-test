<?php

declare( strict_types=1 );

namespace SkautisIntegration\Modules\Register;

use SkautisIntegration\Auth\SkautisGateway;
use SkautisIntegration\Repository\Users as UsersRepository;
use SkautisIntegration\Utils\Helpers;

final class WpRegister {

	private $skautisGateway;
	private $usersRepository;

	public function __construct( SkautisGateway $skautisGateway, UsersRepository $usersRepository ) {
		$this->skautisGateway  = $skautisGateway;
		$this->usersRepository = $usersRepository;
	}

	private function resolveNotificationsAndRegisterUserToWp( string $userLogin, string $userEmail ): int {
		remove_action( 'register_new_user', 'wp_send_new_user_notifications' );
		add_action(
			'register_new_user',
			function ( $userId ) {
				$notify = apply_filters( SKAUTISINTEGRATION_NAME . '_modules_register_newUserNotifications', get_option( SKAUTISINTEGRATION_NAME . '_modules_register_notifications', 'none' ) );
				if ( $notify != 'none' ) {
					global $wp_locale_switcher;
					if ( ! $wp_locale_switcher ) {
						$GLOBALS['wp_locale_switcher'] = new \WP_Locale_Switcher();
						$GLOBALS['wp_locale_switcher']->init();
					}
					wp_send_new_user_notifications( $userId, $notify );
				}
			}
		);

		add_filter( 'sanitize_user', array( $this, 'sanitizeUsername' ), 10, 3 );
		$userId = register_new_user( $userLogin, $userEmail );
		remove_filter( 'sanitize_user', array( $this, 'sanitizeUsername' ), 10 );

		add_action( 'register_new_user', 'wp_send_new_user_notifications' );

		if ( is_wp_error( $userId ) ) {
			if ( isset( $userId->errors ) && ( isset( $userId->errors['username_exists'] ) || isset( $userId->errors['email_exists'] ) ) ) {
				wp_die( sprintf( esc_html__( 'Vás email %s je již na webu registrován, ale není propojen se skautIS účtem.', 'skautis-integration' ), esc_html( $userEmail ) ), esc_html__( 'Chyba při registraci', 'skautis-integration' ) );
			}
			wp_die( sprintf( esc_html__( 'Při registraci nastala neočekávaná chyba: %s', 'skautis-integration' ), esc_html( $userId->get_error_message() ) ), esc_html__( 'Chyba při registraci', 'skautis-integration' ) );
		}

		return $userId;
	}

	private function prepareUserData( $skautisUser ): array {
		$skautisUserDetail = $this->skautisGateway->getSkautisInstance()->OrganizationUnit->PersonDetail(
			array(
				'ID_Login' => $this->skautisGateway->getSkautisInstance()->getUser()->getLoginId(),
				'ID'       => $skautisUser->ID_Person,
			)
		);

		$user = array(
			'id'        => $skautisUser->ID,
			'UserName'  => $skautisUser->UserName,
			'personId'  => $skautisUser->ID_Person,
			'email'     => $skautisUserDetail->Email,
			'firstName' => $skautisUserDetail->FirstName,
			'lastName'  => $skautisUserDetail->LastName,
			'nickName'  => $skautisUserDetail->NickName,
		);

		return $user;
	}

	private function processWpUserRegistration( array $user, string $wpRole ): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( isset( $_GET['ReturnUrl'] ) && $_GET['ReturnUrl'] ) {
			Helpers::validateNonceFromUrl( esc_url_raw( wp_unslash( $_GET['ReturnUrl'] ) ), SKAUTISINTEGRATION_NAME . '_registerToWpBySkautis' );

			// check for skautIS User ID collision with existing users
			$usersWpQuery = new \WP_User_Query(
				array(
					'number'     => 1,
					'meta_query' => array(
						array(
							'key'     => 'skautisUserId_' . $this->skautisGateway->getEnv(),
							'value'   => absint( $user['id'] ),
							'compare' => '=',
						),
					),
				)
			);
			$users        = $usersWpQuery->get_results();

			if ( ! empty( $users ) ) {
				return true;
			}

			if ( ! isset( $user['UserName'] ) || mb_strlen( $user['UserName'] ) == 0 ) {
				return false;
			}

			$username = mb_strcut( $user['UserName'], 0, 60 );

			$userId = $this->resolveNotificationsAndRegisterUserToWp( $username, $user['email'] );

			if ( $userId === 0 ) {
				return false;
			}

			if ( ! add_user_meta( $userId, 'skautisUserId_' . $this->skautisGateway->getEnv(), absint( $user['id'] ) ) ) {
				return false;
			}

			$firstName = $user['firstName'];
			$lastName  = $user['lastName'];
			if ( $nickName = $user['nickName'] ) {
				$displayName = $nickName;
			} else {
				$nickName    = '';
				$displayName = $firstName . ' ' . $lastName;
			}

			if ( is_wp_error(
				wp_update_user(
					array(
						'ID'           => $userId,
						'first_name'   => $firstName,
						'last_name'    => $lastName,
						'nickname'     => $nickName,
						'display_name' => $displayName,
						'role'         => $wpRole,
					)
				)
			) ) {
				return false;
			}

			return true;
		}

		return false;
	}

	public function checkIfUserIsAlreadyRegisteredAndGetHisUserId(): int {
		$userDetail = $this->skautisGateway->getSkautisInstance()->UserManagement->UserDetail();

		if ( ! $userDetail || ! isset( $userDetail->ID ) || ! $userDetail->ID > 0 ) {
			return 0;
		}

		// check for skautIS User ID collision with existing users
		$usersWpQuery = new \WP_User_Query(
			array(
				'number'     => 1,
				'meta_query' => array(
					array(
						'key'     => 'skautisUserId_' . $this->skautisGateway->getEnv(),
						'value'   => absint( $userDetail->ID ),
						'compare' => '=',
					),
				),
			)
		);
		$users        = $usersWpQuery->get_results();

		if ( ! empty( $users ) ) {
			return $users[0]->ID;
		}

		return 0;
	}

	public function getRegisterUrl(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( isset( $_GET['redirect_to'] ) && $_GET['redirect_to'] ) {
			$returnUrl = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		} elseif ( isset( $_GET['ReturnUrl'] ) && $_GET['ReturnUrl'] ) {
			$returnUrl = esc_url_raw( wp_unslash( $_GET['ReturnUrl'] ) );
		} else {
			$returnUrl = Helpers::getCurrentUrl();
		}

		$returnUrl = remove_query_arg( 'loggedout', urldecode( $returnUrl ) );

		$returnUrl = add_query_arg( SKAUTISINTEGRATION_NAME . '_registerToWpBySkautis', wp_create_nonce( SKAUTISINTEGRATION_NAME . '_registerToWpBySkautis' ), $returnUrl );
		$url       = add_query_arg( 'ReturnUrl', urlencode( $returnUrl ), get_home_url( null, 'skautis/auth/' . Register::REGISTER_ACTION ) );

		return esc_url( $url );
	}

	public function registerToWp( string $wpRole ): bool {
		$userDetail = $this->skautisGateway->getSkautisInstance()->UserManagement->UserDetail();

		if ( $userDetail && isset( $userDetail->ID ) && $userDetail->ID > 0 ) {
			$user = $this->prepareUserData( $userDetail );

			return $this->processWpUserRegistration( $user, $wpRole );
		}

		return false;
	}

	public function getManuallyRegisterWpUserUrl(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( isset( $_GET['redirect_to'] ) && $_GET['redirect_to'] ) {
			$returnUrl = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		} elseif ( isset( $_GET['ReturnUrl'] ) && $_GET['ReturnUrl'] ) {
			$returnUrl = esc_url_raw( wp_unslash( $_GET['ReturnUrl'] ) );
		} else {
			$returnUrl = Helpers::getCurrentUrl();
		}

		$returnUrl = add_query_arurlg( SKAUTISINTEGRATION_NAME . '_registerToWpBySkautis', wp_create_nonce( SKAUTISINTEGRATION_NAME . '_registerToWpBySkautis' ), $returnUrl );
		$url       = add_query_arg( 'ReturnUrl', urlencode( $returnUrl ), get_home_url( null, 'skautis/auth/' . Register::MANUALLY_REGISTER_WP_USER_ACTION ) );

		return esc_url( $url );
	}

	public function registerToWpManually( string $wpRole, int $skautisUserId ): bool {
		$userDetail = $this->usersRepository->getUserDetail( $skautisUserId );

		return $this->processWpUserRegistration( $userDetail, $wpRole );
	}

	public function sanitizeUsername( string $username, string $rawUsername, bool $strict ): string {
		$username = wp_strip_all_tags( $rawUsername );

		// $username = remove_accents ($username);

		// Kill octets
		$username = preg_replace( '|%([a-fA-F0-9][a-fA-F0-9])|', '', $username );

		// Kill entities
		$username = preg_replace( '/&.+?;/', '', $username );

		// If strict, reduce to ASCII, Latin and Cyrillic characters for max portability.
		if ( $strict ) {
			$username = preg_replace( '|[^a-z\p{Latin}\p{Cyrillic}0-9 _.\-@]|iu', '', $username );
		}

		$username = trim( $username );

		// Consolidate contiguous whitespace
		$username = preg_replace( '|\s+|', ' ', $username );

		return $username;
	}

}