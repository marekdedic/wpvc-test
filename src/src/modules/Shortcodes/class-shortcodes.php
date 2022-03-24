<?php

declare( strict_types=1 );

namespace SkautisIntegration\Modules\Shortcodes;

use SkautisIntegration\Auth\Skautis_Login;
use SkautisIntegration\Modules\Module;
use SkautisIntegration\Modules\Shortcodes\Admin\Admin;
use SkautisIntegration\Modules\Shortcodes\Frontend\Frontend;
use SkautisIntegration\Rules\Rules_Manager;
use SkautisIntegration\Auth\WP_Login_Logout;

final class Shortcodes implements Module {

	const REGISTER_ACTION = 'shortcodes';

	// TODO: Unused?
	private $rules_manager;
	// TODO: Unused?
	private $skautis_login;
	// TODO: Unused?
	private $wp_login_logout;

	public static $id = 'module_Shortcodes';

	public function __construct( Rules_Manager $rulesManager, Skautis_Login $skautisLogin, WP_Login_Logout $wpLoginLogout ) {
		$this->rules_manager   = $rulesManager;
		$this->skautis_login   = $skautisLogin;
		$this->wp_login_logout = $wpLoginLogout;
		if ( is_admin() ) {
			( new Admin( $this->rules_manager ) );
		} else {
			( new Frontend( $this->skautis_login, $this->rules_manager, $this->wp_login_logout ) );
		}
	}

	public static function get_id(): string {
		return self::$id;
	}

	public static function get_label(): string {
		return __( 'Shortcodes', 'skautis-integration' );
	}

	public static function get_path(): string {
		return plugin_dir_path( __FILE__ );
	}

	public static function get_url(): string {
		return plugin_dir_url( __FILE__ );
	}

}