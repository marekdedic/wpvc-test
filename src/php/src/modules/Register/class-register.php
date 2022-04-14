<?php
/**
 * Contains the Register class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Register;

use Skautis_Integration\Auth\Skautis_Gateway;
use Skautis_Integration\Auth\Skautis_Login;
use Skautis_Integration\Auth\WP_Login_Logout;
use Skautis_Integration\Rules\Rules_Manager;
use Skautis_Integration\Repository\Users as UsersRepository;
use Skautis_Integration\Modules\Module;
use Skautis_Integration\Modules\Register\Admin\Admin;
use Skautis_Integration\Modules\Register\Frontend\Frontend;
use Skautis_Integration\Modules\Register\Frontend\Login_Form;
use Skautis_Integration\Utils\Helpers;

final class Register implements Module {

	const REGISTER_ACTION                  = 'register';
	const MANUALLY_REGISTER_WP_USER_ACTION = 'registerManually';

	public static $id = 'module_Register';

	private $skautis_gateway;
	private $skautis_login;
	private $wp_login_logout;
	private $rules_manager;
	// TODO: Unused?
	private $users_repository;
	private $wp_register;

	/**
	 * Constructs the module and saves all dependencies.
	 */
	public function __construct( Skautis_Gateway $skautis_gateway, Skautis_Login $skautis_login, WP_Login_Logout $wp_login_logout, Rules_Manager $rules_manager, UsersRepository $users_repository ) {
		$this->skautis_gateway  = $skautis_gateway;
		$this->skautis_login    = $skautis_login;
		$this->wp_login_logout  = $wp_login_logout;
		$this->rules_manager    = $rules_manager;
		$this->users_repository = $users_repository;
		$this->wp_register      = new WP_Register( $this->skautis_gateway, $this->users_repository );
		if ( is_admin() ) {
			( new Admin( $this->rules_manager ) );
		} else {
			( new Frontend( new Login_Form( $this->wp_register ) ) );
		}
		$this->init_hooks();
	}

	/**
	 * Intializes all hooks used by the object.
	 */
	private function init_hooks() {
		add_filter( SKAUTIS_INTEGRATION_NAME . '_frontend_actions_router', array( $this, 'addActionsToRouter' ) );
		$return_url = Helpers::get_return_url();
		if ( ! is_null( $return_url ) ) {
			if ( Helpers::get_nonce_from_url( $return_url, SKAUTIS_INTEGRATION_NAME . '_registerToWpBySkautis' ) ) {
				add_action( SKAUTIS_INTEGRATION_NAME . '_after_skautis_token_is_set', array( $this, 'registerConfirm' ) );
			}
		}
	}

	/**
	 * Redirects the user to login with SkautIS.
	 */
	private function loginUserAfterRegistration() {
		$return_url = Helpers::get_login_logout_redirect();
		$return_url = remove_query_arg( SKAUTIS_INTEGRATION_NAME . '_registerToWpBySkautis', urldecode( $return_url ) );
		wp_safe_redirect( esc_url_raw( $this->wp_login_logout->get_login_url( $return_url ) ), 302 );
		exit;
	}

	/**
	 * Adds new actions to Actions.
	 *
	 * This function modifies the behaviour of Actions to add new possible redirects using SkautIS.
	 *
	 * @see Actions::auth_actions_router() for more details about how the actions are used.
	 */
	public function addActionsToRouter( array $actions = array() ): array {
		$actions[ self::REGISTER_ACTION ]                  = array( $this, 'register' );
		$actions[ self::MANUALLY_REGISTER_WP_USER_ACTION ] = array( $this, 'registerUserManually' );

		return $actions;
	}

	/**
	 * Fires upon redirect back from SkautIS after login and handles the user login and potential registration.
	 */
	public function registerConfirm( array $data = array() ) {
		// TODO: Why is this not one conditional?
		if ( $this->skautis_login->set_login_data_to_local_skautis_instance( $data ) ) {
			$this->registerUser();
		} elseif ( $this->skautis_login->is_user_logged_in_skautis() ) {
			$this->registerUser();
		}
	}

	/**
	 * Returns the module ID.
	 */
	public static function get_id(): string {
		return self::$id;
	}

	/**
	 * Returns the localized module name.
	 */
	public static function get_label(): string {
		return __( 'Registrace', 'skautis-integration' );
	}

	/**
	 * Returns the path to the module.
	 */
	public static function get_path(): string {
		return plugin_dir_path( __FILE__ );
	}

	/**
	 * Returns the URL of the module.
	 */
	public static function get_url(): string {
		return plugin_dir_url( __FILE__ );
	}

	/**
	 * Returns the module WP_Register instance.
	 */
	public function getWpRegister(): WP_Register {
		return $this->wp_register;
	}

	/**
	 * Returns the module Rules_Manager instance.
	 */
	public function getRulesManager(): Rules_Manager {
		return $this->rules_manager;
	}

	/**
	 * Handles a call to log the user into SkautIS and possibly register them in WordPress in the process.
	 *
	 * @see Actions::auth_actions_router() for more details about how this function gets called.
	 * @see Register::addActionsToRouter() for more details about how this function gets called.
	 */
	public function register() {
		if ( ! $this->skautis_login->is_user_logged_in_skautis() ) {
			$return_url = Helpers::get_return_url() ?? Helpers::get_current_url();
			wp_safe_redirect( esc_url_raw( $this->skautis_gateway->get_skautis_instance()->getLoginUrl( $return_url ) ), 302 );
			exit;
		}

		$this->registerUser();
	}

	/**
	 * This function actually handles the user login and potential registration.
	 */
	public function registerUser() {
		$wp_role = $this->rules_manager->check_if_user_passed_rules_and_get_his_role();
		if ( $wp_role ) {
			if ( $this->wp_register->register_to_wp( $wp_role ) ) {
				$this->loginUserAfterRegistration();
			}
		} else {
			$wp_user_id = $this->wp_register->check_if_user_is_already_registered_and_get_his_user_id();
			if ( $wp_user_id > 0 ) {
				if ( get_option( SKAUTIS_INTEGRATION_NAME . '_checkUserPrivilegesIfLoginBySkautis' ) ) {
					if ( user_can( $wp_user_id, Helpers::get_skautis_manager_capability() ) ) {
						$this->loginUserAfterRegistration();
					}
				} else {
					$this->loginUserAfterRegistration();
				}
			}
		}

		$this->skautis_gateway->logout();

		$return_url = Helpers::get_return_url();
		if ( ! is_null( $return_url ) ) {
			/* translators: 1: Start of the link back 2: End of the link back */
			wp_die( sprintf( esc_html__( 'Nemáte oprávnění k registraci. %1$sZkuste to znovu%2$s', 'skautis-integration' ), '<a href="' . esc_url( $return_url ) . '">', '</a>' ), esc_html__( 'Neautorizovaný přístup', 'skautis-integration' ) );
		}
		wp_die( esc_html__( 'Nemáte oprávnění k registraci.', 'skautis-integration' ), esc_html__( 'Neautorizovaný přístup', 'skautis-integration' ) );
	}

	/**
	 * Registers an existing SkautIS user as a new WordPress user.
	 *
	 * This function is used to register other users than the current user.
	 */
	public function registerUserManually() {
		$return_url = Helpers::get_return_url();
		if ( ! isset( $_GET[ SKAUTIS_INTEGRATION_NAME . '_register_user_nonce' ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ SKAUTIS_INTEGRATION_NAME . '_register_user_nonce' ] ) ), SKAUTIS_INTEGRATION_NAME . '_register_user' ) ||
			! $this->skautis_login->is_user_logged_in_skautis() ||
			! Helpers::user_is_skautis_manager() ||
			! current_user_can( 'create_users' ) ||
			is_null( $return_url ) ||
			! isset( $_GET['wpRole'], $_GET['skautisUserId'] ) ) {
			wp_die( esc_html__( 'Nemáte oprávnění k registraci nových uživatelů.', 'skautis-integration' ), esc_html__( 'Neautorizovaný přístup', 'skautis-integration' ) );
		}

		$wp_role = sanitize_text_field( wp_unslash( $_GET['wpRole'] ) );
		if ( ! wp_roles()->is_role( $wp_role ) ) {
			wp_die( esc_html__( 'Uživatele se nepodařilo zaregistrovat - role neexistuje.', 'skautis-integration' ), esc_html__( 'Chyba při registraci uživatele', 'skautis-integration' ) );
		}
		$skautis_user_id = absint( $_GET['skautisUserId'] );

		if ( $this->wp_register->register_to_wp_manually( $wp_role, $skautis_user_id ) ) {
			wp_safe_redirect( $return_url, 302 );
			exit;
		} else {
			wp_die( esc_html__( 'Uživatele se nepodařilo zaregistrovat', 'skautis-integration' ), esc_html__( 'Chyba při registraci uživatele', 'skautis-integration' ) );
		}
	}

}
