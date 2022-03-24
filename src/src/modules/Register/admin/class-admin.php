<?php

declare( strict_types=1 );

namespace SkautisIntegration\Modules\Register\Admin;

use SkautisIntegration\Rules\Rules_Manager;
use SkautisIntegration\Utils\Helpers;

final class Admin {

	private $rules_manager;
	// TODO: Unused?
	private $admin_dir_url = '';

	public function __construct( Rules_Manager $rulesManager ) {
		$this->rules_manager = $rulesManager;
		$this->admin_dir_url = plugin_dir_url( __FILE__ ) . 'public/';
		( new Settings( $this->rules_manager ) );
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_footer', array( $this, 'init_rules_options' ) );
		add_action( 'admin_footer', array( $this, 'init_rules_data' ) );
	}

	public function enqueue_styles() {
		if ( get_current_screen()->id === 'skautis_page_skautis-integration_modules_register' ) {
			Helpers::enqueue_style( 'modules_register', 'modules/Register/admin/css/skautis-modules-register-admin.min.css' );
		}
	}

	public function enqueue_scripts() {
		if ( get_current_screen()->id === 'skautis_page_skautis-integration_modules_register' ) {
			wp_enqueue_script( 'jquery-ui-sortable' );

			wp_enqueue_script(
				SKAUTISINTEGRATION_NAME . '_jquery.repeater',
				SKAUTISINTEGRATION_URL . 'bundled/jquery.repeater.min.js',
				array( 'jquery' ),
				SKAUTISINTEGRATION_VERSION,
				true
			);

			Helpers::enqueue_script(
				'modules_register',
				'modules/Register/admin/js/skautis-modules-register-admin.min.js',
				array( SKAUTISINTEGRATION_NAME . '_jquery.repeater' ),
			);
		}
	}

	public function init_rules_options() {
		if ( get_current_screen()->id === 'skautis_page_skautis-integration_modules_register' ) {
			$rules = array();
			foreach ( (array) $this->rules_manager->get_all_rules() as $rule ) {
				$rules[ $rule->ID ] = $rule->post_title;
			}
			?>
			<script>
				window.rulesOptions = <?php echo wp_json_encode( $rules ); ?>;
			</script>
			<?php
		}
	}

	public function init_rules_data() {
		if ( get_current_screen()->id === 'skautis_page_skautis-integration_modules_register' ) {
			$data = get_option( SKAUTISINTEGRATION_NAME . '_modules_register_rules' );
			?>
			<script>
				window.rulesData = <?php echo wp_json_encode( $data ); ?>;
			</script>
			<?php
		}
	}

}