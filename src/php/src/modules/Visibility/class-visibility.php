<?php

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Visibility;

use Skautis_Integration\Auth\WP_Login_Logout;
use Skautis_Integration\Modules\Module;
use Skautis_Integration\Rules\Rules_Manager;
use Skautis_Integration\Auth\Skautis_Login;
use Skautis_Integration\Modules\Visibility\Admin\Admin;
use Skautis_Integration\Modules\Visibility\Frontend\Frontend;

final class Visibility implements Module {

	const REGISTER_ACTION = 'visibility';

	// TODO: Unused?
	private $rules_manager;
	// TODO: Unused?
	private $skautis_login;
	// TODO: Unused?
	private $wp_login_logout;
	private $frontend;

	public static $id = 'module_Visibility';

	public function __construct( Rules_Manager $rules_manager, Skautis_Login $skautis_login, WP_Login_Logout $wp_login_logout ) {
		$this->rules_manager   = $rules_manager;
		$this->skautis_login   = $skautis_login;
		$this->wp_login_logout = $wp_login_logout;
		$post_types            = (array) get_option( SKAUTIS_INTEGRATION_NAME . '_modules_visibility_postTypes', array() );
		$this->frontend        = new Frontend( $post_types, $this->rules_manager, $this->skautis_login, $this->wp_login_logout );
		if ( is_admin() ) {
			( new Admin( $post_types, $this->rules_manager, $this->frontend ) );
		} else {
			$this->frontend->init_hooks();
		}
	}

	public static function get_id(): string {
		return self::$id;
	}

	public static function get_label(): string {
		return __( 'Viditelnost obsahu', 'skautis-integration' );
	}

	public static function get_path(): string {
		return plugin_dir_path( __FILE__ );
	}

	public static function get_url(): string {
		return plugin_dir_url( __FILE__ );
	}

}