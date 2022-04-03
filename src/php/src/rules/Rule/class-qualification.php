<?php

declare( strict_types=1 );

namespace Skautis_Integration\Rules\Rule;

use Skautis_Integration\Rules\Rule;
use Skautis_Integration\Auth\Skautis_Gateway;

class Qualification implements Rule {

	public static $id           = 'qualification';
	protected static $type      = 'string';
	protected static $input     = 'qualificationInput';
	protected static $multiple  = true;
	protected static $operators = array( 'in' );

	protected $skautis_gateway;

	public function __construct( Skautis_Gateway $skautis_gateway ) {
		$this->skautis_gateway = $skautis_gateway;
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
		static $user_qualifications = null;

		if ( is_null( $user_qualifications ) ) {
			$user_detail         = $this->skautis_gateway->get_skautis_instance()->UserManagement->UserDetail();
			$user_qualifications = $this->skautis_gateway->get_skautis_instance()->OrganizationUnit->QualificationAll(
				array(
					'ID_Person'   => $user_detail->ID_Person,
					'ShowHistory' => true,
					'isValid'     => true,
				)
			);

			$result = array();

			if ( ! is_array( $user_qualifications ) || empty( $user_qualifications ) ) {
				return array();
			}

			foreach ( $user_qualifications as $user_qualification ) {
				$result[] = $user_qualification->ID_QualificationType;
			}

			$user_qualifications = $result;
		}

		if ( ! is_array( $user_qualifications ) ) {
			return array();
		}

		return $user_qualifications;
	}

	// TODO: Unused first parameter?
	public function is_rule_passed( string $roles_operator, $data ): bool {
		// parse and prepare data from rules UI
		$output = array();
		preg_match_all( '|[^~]+|', $data, $output );
		if ( isset( $output[0], $output[0][0] ) ) {
			$qualifications = $output[0][0];
			$qualifications = explode( ',', $qualifications );
		} else {
			return false;
		}

		$user_qualifications = $this->getUserQualifications();
		$user_pass           = 0;
		foreach ( $qualifications as $qualification ) {
			if ( in_array( $qualification, $user_qualifications, true ) ) {
				++$user_pass;
			}
		}

		if ( is_int( $user_pass ) && $user_pass > 0 ) {
			return true;
		}

		return false;
	}

}