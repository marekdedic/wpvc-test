<?php

declare( strict_types=1 );

namespace SkautisIntegration\Frontend;

use SkautisIntegration\Auth\SkautisGateway;
use SkautisIntegration\Auth\WpLoginLogout;
use SkautisIntegration\Utils\Helpers;

final class Frontend {

	private $loginForm;
	private $wpLoginLogout;
	private $skautisGateway;
	private $frontendDirUrl = '';

	public function __construct( LoginForm $loginForm, WpLoginLogout $wpLoginLogout, SkautisGateway $skautisGateway ) {
		$this->loginForm       = $loginForm;
		$this->wpLoginLogout   = $wpLoginLogout;
		$this->skautisGateway  = $skautisGateway;
		$this->frontendDirUrl  = plugin_dir_url( __FILE__ ) . 'public/';
		$this->pluginLoginView = false;
		$this->initHooks();
	}

	private function initHooks() {
		if ( get_option( SKAUTISINTEGRATION_NAME . '_login_page_url' ) ) {
			add_filter( 'query_vars', array( $this, 'registerQueryVars' ) );
			add_filter( 'template_include', array( $this, 'registerTemplates' ) );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueStyles' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueueLoginStyles' ) );
		if ( $this->skautisGateway->isInitialized() ) {
			if ( $this->skautisGateway->getSkautisInstance()->getUser()->isLoggedIn() ) {
				add_action( 'admin_bar_menu', array( $this, 'addLogoutLinkToAdminBar' ), 20 );
			}
		}
	}

	public function registerQueryVars( array $vars = array() ): array {
		$vars[] = 'skautis_login';

		return $vars;
	}

	public function registerTemplates( string $path = '' ): string {
		$queryValue = get_query_var( 'skautis_login' );
		if ( $queryValue && ! empty( $queryValue ) ) {
			if ( file_exists( get_stylesheet_directory() . '/skautis/login.php' ) ) {
				return get_stylesheet_directory() . '/skautis/login.php';
			} elseif ( file_exists( get_template_directory() . '/skautis/login.php' ) ) {
				return get_template_directory() . '/skautis/login.php';
			} else {
				$this->pluginLoginView = true;

				return plugin_dir_path( __FILE__ ) . 'public/views/login.php';
			}
		}

		return $path;
	}

	public function enqueueStyles() {
		if ( $this->pluginLoginView ) {
			wp_enqueue_style( 'buttons' );
		}

		Helpers::enqueue_style('frontend', 'frontend/css/skautis-frontend.css');
	}

	public function enqueueLoginStyles() {
		Helpers::enqueue_style('frontend', 'frontend/css/skautis-frontend.css');
	}

	public function addLogoutLinkToAdminBar( \WP_Admin_Bar $wpAdminBar ) {
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
				'title'  => esc_html__( 'Odhlásit se (i ze skautISu)', 'skautis-integration' ),
				'href'   => $this->wpLoginLogout->getLogoutUrl(),
			)
		);
	}

}
