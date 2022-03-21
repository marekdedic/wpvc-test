<?php

declare( strict_types=1 );

use SkautisIntegration\Services\Services;
use SkautisIntegration\Modules\Register\Register;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid

if ( ! function_exists( 'getSkautisLoginUrl' ) ) {
	function getSkautisLoginUrl(): string {
		return ( Services::getServicesContainer()['wpLoginLogout'] )->getLoginUrl();
	}
}

if ( ! function_exists( 'getSkautisLogoutUrl' ) ) {
	function getSkautisLogoutUrl(): string {
		return ( Services::getServicesContainer()['wpLoginLogout'] )->getLogoutUrl();
	}
}

if ( ! function_exists( 'getSkautisRegisterUrl' ) ) {
	function getSkautisRegisterUrl(): string {
		if ( Services::getServicesContainer()['modulesManager']->isModuleActivated( Register::getId() ) ) {
			return ( Services::getServicesContainer()[ Register::getId() ] )->getWpRegister()->getRegisterUrl();
		} else {
			return '';
		}
	}
}

if ( ! function_exists( 'isUserLoggedInSkautis' ) ) {
	function isUserLoggedInSkautis(): bool {
		return ( Services::getServicesContainer()['skautisLogin'] )->isUserLoggedInSkautis();
	}
}

if ( ! function_exists( 'userPassedRules' ) ) {
	function userPassedRules( array $rulesIds ): bool {
		return ( Services::getServicesContainer()['rules_manager'] )->checkIfUserPassedRules( $rulesIds );
	}
}
