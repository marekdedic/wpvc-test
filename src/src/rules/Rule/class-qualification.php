<?php

declare( strict_types=1 );

namespace SkautisIntegration\Rules\Rule;

use SkautisIntegration\Rules\Rule;
use SkautisIntegration\Auth\Skautis_Gateway;

class Qualification implements Rule {

	public static $id           = 'qualification';
	protected static $type      = 'string';
	protected static $input     = 'qualificationInput';
	protected static $multiple  = true;
	protected static $operators = array( 'in' );

	protected $skautis_gateway;

	public function __construct( Skautis_Gateway $skautisGateway ) {
		$this->skautis_gateway = $skautisGateway;
	}

	public function get_id(): string {
		return self::$id;
	}

	public function get_label(): string {
		return __( 'Kvalifikace', 'skautis-integration' );
	}

	public function get_type(): string {
		return self::$type;
	}

	public function get_input(): string {
		return self::$input;
	}

	public function get_multiple(): bool {
		return self::$multiple;
	}

	public function get_operators(): array {
		return self::$operators;
	}

	public function get_placeholder(): string {
		return '';
	}

	public function get_description(): string {
		return '';
	}

	public function get_values(): array {
		$result         = array();
		$qualifications = $this->skautis_gateway->get_skautis_instance()->OrganizationUnit->QualificationTypeAll();

		foreach ( $qualifications as $qualification ) {
			$result[ $qualification->ID ] = $qualification->DisplayName;
		}

		return $result;
	}

	protected function getUserQualifications(): array {
		static $userQualifications = null;

		if ( is_null( $userQualifications ) ) {
			$userDetail         = $this->skautis_gateway->get_skautis_instance()->UserManagement->UserDetail();
			$userQualifications = $this->skautis_gateway->get_skautis_instance()->OrganizationUnit->QualificationAll(
				array(
					'ID_Person'   => $userDetail->ID_Person,
					'ShowHistory' => true,
					'isValid'     => true,
				)
			);

			$result = array();

			if ( ! is_array( $userQualifications ) || empty( $userQualifications ) ) {
				return array();
			}

			foreach ( $userQualifications as $userQualification ) {
				$result[] = $userQualification->ID_QualificationType;
			}

			$userQualifications = $result;
		}

		if ( ! is_array( $userQualifications ) ) {
			return array();
		}

		return $userQualifications;
	}

	public function is_rule_passed( string $rolesOperator, $data ): bool {
		// parse and prepare data from rules UI
		$output = array();
		preg_match_all( '|[^~]+|', $data, $output );
		if ( isset( $output[0], $output[0][0] ) ) {
			$qualifications = $output[0][0];
			$qualifications = explode( ',', $qualifications );
		} else {
			return false;
		}

		$userQualifications = $this->getUserQualifications();
		$userPass           = 0;
		foreach ( $qualifications as $qualification ) {
			if ( in_array( $qualification, $userQualifications, true ) ) {
				++$userPass;
			}
		}

		if ( is_int( $userPass ) && $userPass > 0 ) {
			return true;
		}

		return false;
	}

}