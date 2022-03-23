<?php

declare( strict_types=1 );

namespace SkautisIntegration\Admin;

use SkautisIntegration\Auth\Skautis_Gateway;
use SkautisIntegration\Auth\WP_Login_Logout;
use SkautisIntegration\Rules\Rules_Manager;
use SkautisIntegration\Utils\Helpers;

final class Admin {

	private $settings;
	private $users;
	private $rulesManager;
	private $wpLoginLogout;
	private $skautisGateway;
	private $usersManagement;
	private $adminDirUrl = '';

	public function __construct( Settings $settings, Users $users, Rules_Manager $rulesManager, Users_Management $usersManagement, WP_Login_Logout $wpLoginLogout, Skautis_Gateway $skautisGateway ) {
		$this->settings        = $settings;
		$this->users           = $users;
		$this->rulesManager    = $rulesManager;
		$this->usersManagement = $usersManagement;
		$this->wpLoginLogout   = $wpLoginLogout;
		$this->skautisGateway  = $skautisGateway;
		$this->adminDirUrl     = plugin_dir_url( __FILE__ ) . 'public/';
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );
		add_action( 'admin_print_scripts', array( $this, 'print_inline_js' ) );

		if ( $this->skautisGateway->isInitialized() ) {
			if ( $this->skautisGateway->get_skautis_instance()->getUser()->isLoggedIn() ) {
				add_action( 'admin_bar_menu', array( $this, 'add_logout_link_to_admin_bar' ), 20 );
			}
		}
	}

	public function enqueue_scripts_and_styles() {
		wp_enqueue_style(
			SKAUTISINTEGRATION_NAME . '_select2',
			SKAUTISINTEGRATION_URL . 'bundled/select2.min.css',
			array(),
			SKAUTISINTEGRATION_VERSION,
			'all'
		);

		wp_enqueue_script(
			SKAUTISINTEGRATION_NAME . '_select2',
			SKAUTISINTEGRATION_URL . 'bundled/select2.min.js',
			array( 'jquery' ),
			SKAUTISINTEGRATION_VERSION,
			false
		);

		Helpers::enqueue_style( 'admin', 'admin/css/skautis-admin.min.css' );
	}

	public function print_inline_js() {
		?>
		<script type="text/javascript">
			//<![CDATA[
			window.skautis = window.skautis || {};
			//]]>
		</script>
		<?php
	}

	public function add_logout_link_to_admin_bar( \WP_Admin_Bar $wpAdminBar ) {
		if ( ! function_exists( 'is_admin_bar_showing' ) ) {
			return;
		}
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		if ( method_exists( $wpAdminBar, 'get_node' ) ) {
			if ( $wpAdminBar->get_node( 'user-actions' ) ) {
				$parent = 'user-actions';
			} else {
				return;
			}
		} elseif ( get_option( 'show_avatars' ) ) {
			$parent = 'my-account-with-avatar';
		} else {
			$parent = 'my-account';
		}

		$wpAdminBar->add_menu(
			array(
				'parent' => $parent,
				'id'     => SKAUTISINTEGRATION_NAME . '_adminBar_logout',
				'title'  => esc_html__( 'Log Out (too from skautIS)', 'skautis-integration' ),
				'href'   => $this->wpLoginLogout->get_logout_url(),
			)
		);
	}

}
