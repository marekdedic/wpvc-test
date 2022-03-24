<?php

declare( strict_types=1 );

namespace SkautisIntegration\Admin;

use SkautisIntegration\Auth\Skautis_Gateway;
use SkautisIntegration\Auth\WP_Login_Logout;
use SkautisIntegration\Auth\Skautis_Login;
use SkautisIntegration\Auth\Connect_And_Disconnect_WP_Account;
use SkautisIntegration\Repository\Users as UsersRepository;
use SkautisIntegration\General\Actions;
use SkautisIntegration\Services\Services;
use SkautisIntegration\Modules\Register\Register;
use SkautisIntegration\Utils\Helpers;
use SkautisIntegration\Utils\Role_Changer;

class Users_Management {

	// TODO: Make all of them private?
	protected $skautis_gateway;
	protected $wp_login_logout;
	protected $skautis_login;
	protected $connect_and_disconnect_wp_account;
	protected $users_repository;
	protected $role_changer;
	// TODO: Unused?
	protected $admin_dir_url = '';

	public function __construct( Skautis_Gateway $skautisGateway, WP_Login_Logout $wpLoginLogout, Skautis_Login $skautisLogin, Connect_And_Disconnect_WP_Account $connectAndDisconnectWpAccount, UsersRepository $usersRepository, Role_Changer $roleChanger ) {
		$this->skautis_gateway                   = $skautisGateway;
		$this->wp_login_logout                   = $wpLoginLogout;
		$this->skautis_login                     = $skautisLogin;
		$this->connect_and_disconnect_wp_account = $connectAndDisconnectWpAccount;
		$this->users_repository                  = $usersRepository;
		$this->role_changer                      = $roleChanger;
		$this->admin_dir_url                     = plugin_dir_url( __FILE__ ) . 'public/';
		$this->check_if_user_change_skautis_role();
		$this->init_hooks();
	}

	protected function init_hooks() {
		add_action(
			'admin_menu',
			array(
				$this,
				'setup_users_management_page',
			),
			10
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );
	}

	protected function check_if_user_change_skautis_role() {
		add_action(
			'init',
			function () {
				if ( isset( $_POST['changeSkautisUserRole'], $_POST['_wpnonce'], $_POST['_wp_http_referer'] ) ) {
					if ( check_admin_referer( SKAUTISINTEGRATION_NAME . '_changeSkautisUserRole', '_wpnonce' ) ) {
						if ( $this->skautis_login->is_user_logged_in_skautis() ) {
							$this->skautis_login->change_user_role_in_skautis( absint( $_POST['changeSkautisUserRole'] ) );
						}
					}
				}
			}
		);
	}

	public function enqueue_scripts_and_styles( $hook_suffix ) {
		if ( ! str_ends_with( $hook_suffix, SKAUTISINTEGRATION_NAME . '_usersManagement' ) ) {
			return;
		}
		wp_enqueue_script( 'thickbox' );
		wp_enqueue_style( 'thickbox' );
		if ( is_network_admin() ) {
			add_action( 'admin_head', '_thickbox_path_admin_subfolder' );
		}

		wp_enqueue_style(
			SKAUTISINTEGRATION_NAME . '_datatables',
			SKAUTISINTEGRATION_URL . 'bundled/jquery.dataTables.min.css',
			array(),
			SKAUTISINTEGRATION_VERSION,
			'all'
		);

		wp_enqueue_script(
			SKAUTISINTEGRATION_NAME . '_datatables',
			SKAUTISINTEGRATION_URL . 'bundled/jquery.dataTables.min.js',
			array( 'jquery' ),
			SKAUTISINTEGRATION_VERSION,
			true
		);

		Helpers::enqueue_style( 'admin', 'admin/css/skautis-admin.min.css' );
		Helpers::enqueue_style( 'admin-users-management', 'admin/css/skautis-admin-users-management.min.css' );
		Helpers::enqueue_script(
			'admin-users-management',
			'admin/js/skautis-admin-users-management.min.js',
			array( 'jquery', SKAUTISINTEGRATION_NAME . '_select2' ),
		);

		wp_localize_script(
			SKAUTISINTEGRATION_NAME . '_admin-users-management',
			'skautisIntegrationAdminUsersManagementLocalize',
			array(
				'cancel'             => esc_html__( 'Zrušit', 'skautis-integration' ),
				'datatablesFilesUrl' => SKAUTISINTEGRATION_URL . 'bundled/datatables-files',
				'searchNonceName'    => SKAUTISINTEGRATION_NAME . '_skautis_search_user_nonce',
				'searchNonceValue'   => wp_create_nonce( SKAUTISINTEGRATION_NAME . '_skautis_search_user' ),
			)
		);
	}

	public function setup_users_management_page() {
		add_submenu_page(
			SKAUTISINTEGRATION_NAME,
			__( 'Správa uživatelů', 'skautis-integration' ),
			__( 'Správa uživatelů', 'skautis-integration' ),
			Helpers::get_skautis_manager_capability(),
			SKAUTISINTEGRATION_NAME . '_usersManagement',
			array( $this, 'print_child_users' )
		);
	}

	public function print_child_users() {
		if ( ! Helpers::user_is_skautis_manager() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'skautis-integration' ) );
		}

		echo '
		<div class="wrap">
			<h1>' . esc_html__( 'Správa uživatelů', 'skautis-integration' ) . '</h1>
			<p>' . esc_html__( 'Zde si můžete propojit členy ze skautISu s uživateli ve WordPressu nebo je rovnou zaregistrovat (vyžaduje aktivovaný modul Registrace).', 'skautis-integration' ) . '</p>
		';

		if ( ! $this->skautis_login->is_user_logged_in_skautis() ) {
			if ( $this->skautis_gateway->is_initialized() ) {
				echo '<a href="' . esc_url( $this->wp_login_logout->get_login_url( add_query_arg( 'noWpLogin', true, Helpers::get_current_url() ) ) ) . '">' . esc_html__( 'Pro zobrazení obsahu je nutné se přihlásit do skautISu', 'skautis-integration' ) . '</a>';
				echo '
		</div>
			';
			} else {
				/* translators: 1: Start of link to the settings 2: End of link to the settings */
				printf( esc_html__( 'Vyberte v %1$snastavení%2$s pluginu typ prostředí skautISu', 'skautis-integration' ), '<a href="' . esc_url( admin_url( 'admin.php?page=' . SKAUTISINTEGRATION_NAME ) ) . '">', '</a>' );
				echo '
		</div>
			';
			}

			return;
		}

		$this->role_changer->print_change_roles_form();

		echo '<table class="skautis-user-management-table"><thead style="font-weight: bold;"><tr>';
		echo '<th>' . esc_html__( 'Jméno a příjmení', 'skautis-integration' ) . '</th><th>' . esc_html__( 'Přezdívka', 'skautis-integration' ) . '</th><th>' . esc_html__( 'ID uživatele', 'skautis-integration' ) . '</th><th>' . esc_html__( 'Propojený uživatel', 'skautis-integration' ) . '</th><th>' . esc_html__( 'Propojení', 'skautis-integration' ) . '</th>';
		echo '</tr></thead ><tbody>';

		$usersData = $this->users_repository->get_connected_wp_users();

		$users = $this->users_repository->get_users()['users'];

		foreach ( $users as $user ) {
			if ( isset( $usersData[ $user->id ] ) ) {
				$homeUrl               = get_home_url( null, 'skautis/auth/' . Actions::DISCONNECT_ACTION );
				$nonce                 = wp_create_nonce( SKAUTISINTEGRATION_NAME . '_disconnectWpAccountFromSkautis' );
				$userEditLink          = get_edit_user_link( $usersData[ $user->id ]['id'] );
				$returnUrl             = add_query_arg( SKAUTISINTEGRATION_NAME . '_disconnectWpAccountFromSkautis', $nonce, Helpers::get_current_url() );
				$returnUrl             = add_query_arg( 'user-edit_php', '', $returnUrl );
				$returnUrl             = add_query_arg( 'user_id', $usersData[ $user->id ]['id'], $returnUrl );
				$connectDisconnectLink = add_query_arg( 'ReturnUrl', rawurlencode( $returnUrl ), $homeUrl );
				echo '<tr style="background-color: #d1ffd1;">
	<td class="username">
		<span class="firstName">' . esc_html( $user->firstName ) . '</span> <span class="lastName">' . esc_html( $user->lastName ) . '</span>
	</td>
	<td>&nbsp;&nbsp;<span class="nickName">' . esc_html( $user->nickName ) . '</span></td><td>&nbsp;&nbsp;<span class="skautisUserId">' . esc_html( $user->id ) . '</span></td><td><a href="' . esc_url( $userEditLink ) . '">' . esc_html( $usersData[ $user->id ]['name'] ) . '</a></td><td><a href="' . esc_url( $connectDisconnectLink ) . '" class="button">' . esc_html__( 'Odpojit', 'skautis-integration' ) . '</a></td></tr>';
			} else {
				echo '<tr>
	<td class="username">
		<span class="firstName">' . esc_html( $user->firstName ) . '</span> <span class="lastName">' . esc_html( $user->lastName ) . '</span>
	</td>
	<td>&nbsp;&nbsp;<span class="nickName">' . esc_html( $user->nickName ) . '</span></td><td>&nbsp;&nbsp;<span class="skautisUserId">' . esc_html( $user->id ) . '</span></td><td></td><td><a href="#TB_inline?width=450&height=380&inlineId=connectUserToSkautisModal" class="button thickbox">' . esc_html__( 'Propojit', 'skautis-integration' ) . '</a></td></tr>';
			}
		}
		echo '</tbody></table>';

		?>
		</div>
		<div id="connectUserToSkautisModal" class="hidden">
			<div class="content">
				<h3><?php esc_html_e( 'Propojení uživatele', 'skautis-integration' ); ?> <span
						id="connectUserToSkautisModal_username"></span> <?php esc_html_e( 'se skautISem', 'skautis-integration' ); ?>
				</h3>
				<h4><?php esc_html_e( 'Vyberte uživatele již registrovaného ve WordPressu', 'skautis-integration' ); ?>:</h4>
				<select id="connectUserToSkautisModal_select">
					<option><?php esc_html_e( 'Vyberte uživatele...', 'skautis-integration' ); ?></option>
					<?php
					foreach ( $this->users_repository->get_connectable_wp_users() as $user ) {
						$userName = $user->data->display_name;
						if ( ! $userName ) {
							$userName = $user->data->user_login;
						}
						echo '
						<option value="' . absint( $user->ID ) . '">' . esc_html( $userName ) . '</option>
						';
					}
					?>
				</select>
				<a id="connectUserToSkautisModal_connectLink" class="button button-primary"
					href="<?php echo esc_url( $this->connect_and_disconnect_wp_account->get_connect_wp_user_to_skautis_url() ); ?>"><?php esc_html_e( 'Potvrdit', 'skautis-integration' ); ?></a>
				<div>
					<em><?php esc_html_e( 'Je možné vybrat pouze ty uživatele, kteří ještě nemají propojený účet se skautISem.', 'skautis-integration' ); ?></em>
				</div>
				<?php
				if ( Services::get_services_container()['modulesManager']->is_module_activated( Register::get_id() ) ) {
					?>
					<hr/>
					<h3><?php esc_html_e( 'Vytvořit nový účet', 'skautis-integration' ); ?></h3>
					<p>
						<?php esc_html_e( 'Vytvoří nového uživatele ve WordPressu se jménem, příjmením, přezdívkou a emailem ze skautISu. Účet bude automaticky propojen se skautISem.', 'skautis-integration' ); ?>
					</p>
					<label>
						<span><?php esc_html_e( 'Vyberte úroveň nového uživatele', 'skautis-integration' ); ?></span>
						<select name="role" id="connectUserToSkautisModal_defaultRole">
							<?php wp_dropdown_roles( get_option( SKAUTISINTEGRATION_NAME . '_modules_register_defaultwpRole' ) ); ?>
						</select>
					</label>
					<p>
						<a id="connectUserToSkautisModal_registerLink" class="button button-primary"
							href="<?php echo esc_url( Services::get_services_container()[ Register::get_id() ]->getWpRegister()->get_manually_register_wp_user_url() ); ?>"><?php esc_html_e( 'Vytvořit nový účet', 'skautis-integration' ); ?></a>
					</p>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}

}