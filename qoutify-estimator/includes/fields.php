<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All form fields live here. To add, remove or rename a field, edit this file only.
 * Field types: text, number, select, checkboxes, textarea.
 */
function qtf_us_states() {
	return array(
		'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
		'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida',
		'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana',
		'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
		'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
		'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire',
		'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota',
		'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
		'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
		'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin',
		'WY' => 'Wyoming',
	);
}

function qtf_project_types() {
	return array(
		'residential' => __( 'Residential', 'qoutify-estimator' ),
		'commercial'  => __( 'Commercial', 'qoutify-estimator' ),
	);
}

function qtf_condition_options() {
	return array(
		'cosmetic' => __( 'Cosmetic refresh', 'qoutify-estimator' ),
		'moderate' => __( 'Moderate renovation', 'qoutify-estimator' ),
		'major'    => __( 'Major renovation', 'qoutify-estimator' ),
		'gut'      => __( 'Full gut renovation', 'qoutify-estimator' ),
	);
}

function qtf_quality_options() {
	return array(
		'basic'    => __( 'Basic', 'qoutify-estimator' ),
		'standard' => __( 'Standard', 'qoutify-estimator' ),
		'premium'  => __( 'Premium', 'qoutify-estimator' ),
		'luxury'   => __( 'Luxury', 'qoutify-estimator' ),
	);
}

function qtf_fields( $type ) {
	$address = array(
		array( 'key' => 'street', 'label' => __( 'Street address', 'qoutify-estimator' ), 'type' => 'text', 'required' => true, 'max' => 120, 'group' => 'property' ),
		array( 'key' => 'city', 'label' => __( 'City', 'qoutify-estimator' ), 'type' => 'text', 'required' => true, 'max' => 80, 'group' => 'property' ),
		array( 'key' => 'state', 'label' => __( 'State', 'qoutify-estimator' ), 'type' => 'select', 'required' => true, 'options' => qtf_us_states(), 'group' => 'property' ),
		array( 'key' => 'zip', 'label' => __( 'ZIP code', 'qoutify-estimator' ), 'type' => 'text', 'required' => true, 'max' => 10, 'pattern' => '/^\d{5}(-\d{4})?$/', 'group' => 'property' ),
	);

	if ( 'commercial' === $type ) {
		$specific = array(
			array(
				'key' => 'building_use', 'label' => __( 'Building use', 'qoutify-estimator' ), 'type' => 'select', 'required' => true, 'group' => 'property',
				'options' => array(
					'office'     => __( 'Office', 'qoutify-estimator' ),
					'retail'     => __( 'Retail', 'qoutify-estimator' ),
					'restaurant' => __( 'Restaurant', 'qoutify-estimator' ),
					'warehouse'  => __( 'Warehouse / industrial', 'qoutify-estimator' ),
					'medical'    => __( 'Medical', 'qoutify-estimator' ),
					'hotel'      => __( 'Hotel', 'qoutify-estimator' ),
					'mixed_use'  => __( 'Mixed-use', 'qoutify-estimator' ),
					'other'      => __( 'Other', 'qoutify-estimator' ),
				),
			),
			array(
				'key' => 'project_kind', 'label' => __( 'Project kind', 'qoutify-estimator' ), 'type' => 'select', 'required' => true, 'group' => 'project',
				'options' => array(
					'renovation'         => __( 'Renovation', 'qoutify-estimator' ),
					'tenant_improvement' => __( 'Tenant improvement', 'qoutify-estimator' ),
					'addition'           => __( 'Addition', 'qoutify-estimator' ),
					'repair'             => __( 'Repair', 'qoutify-estimator' ),
					'new_construction'   => __( 'New construction', 'qoutify-estimator' ),
				),
			),
			array( 'key' => 'sqft', 'label' => __( 'Size (square feet)', 'qoutify-estimator' ), 'type' => 'number', 'required' => true, 'min' => 200, 'max' => 1000000, 'step' => 1, 'integer' => true, 'group' => 'project' ),
			array( 'key' => 'floors', 'label' => __( 'Floors', 'qoutify-estimator' ), 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 100, 'step' => 1, 'integer' => true, 'group' => 'project' ),
			array( 'key' => 'units', 'label' => __( 'Units / tenant spaces', 'qoutify-estimator' ), 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 1000, 'step' => 1, 'integer' => true, 'group' => 'project' ),
			array( 'key' => 'condition', 'label' => __( 'Current condition / work level', 'qoutify-estimator' ), 'type' => 'select', 'required' => true, 'options' => qtf_condition_options(), 'group' => 'project' ),
			array( 'key' => 'quality', 'label' => __( 'Finish quality', 'qoutify-estimator' ), 'type' => 'select', 'required' => true, 'options' => qtf_quality_options(), 'group' => 'project' ),
			array(
				'key' => 'occupied', 'label' => __( 'Building occupied during work?', 'qoutify-estimator' ), 'type' => 'select', 'required' => false, 'group' => 'project',
				'options' => array(
					'vacant'   => __( 'Vacant', 'qoutify-estimator' ),
					'occupied' => __( 'Occupied', 'qoutify-estimator' ),
				),
			),
			array( 'key' => 'year_built', 'label' => __( 'Year built', 'qoutify-estimator' ), 'type' => 'number', 'required' => false, 'min' => 1800, 'max' => (int) gmdate( 'Y' ), 'step' => 1, 'integer' => true, 'group' => 'project' ),
			array(
				'key' => 'work_wanted', 'label' => __( 'Work wanted', 'qoutify-estimator' ), 'type' => 'checkboxes', 'required' => false, 'group' => 'work',
				'options' => array(
					'hvac'            => __( 'HVAC', 'qoutify-estimator' ),
					'electrical'      => __( 'Electrical', 'qoutify-estimator' ),
					'plumbing'        => __( 'Plumbing', 'qoutify-estimator' ),
					'restrooms'       => __( 'Restrooms', 'qoutify-estimator' ),
					'fire_protection' => __( 'Fire protection', 'qoutify-estimator' ),
					'accessibility'   => __( 'Accessibility (ADA)', 'qoutify-estimator' ),
					'storefront'      => __( 'Storefront', 'qoutify-estimator' ),
					'ceilings'        => __( 'Ceilings', 'qoutify-estimator' ),
					'flooring'        => __( 'Flooring', 'qoutify-estimator' ),
					'roof'            => __( 'Roof', 'qoutify-estimator' ),
					'painting'        => __( 'Painting', 'qoutify-estimator' ),
					'other'           => __( 'Other', 'qoutify-estimator' ),
				),
			),
		);
	} else {
		$specific = array(
			array(
				'key' => 'property_type', 'label' => __( 'Property type', 'qoutify-estimator' ), 'type' => 'select', 'required' => true, 'group' => 'property',
				'options' => array(
					'single_family' => __( 'Single-family home', 'qoutify-estimator' ),
					'multi_family'  => __( 'Multi-family (2-4 units)', 'qoutify-estimator' ),
					'condo'         => __( 'Condo', 'qoutify-estimator' ),
					'townhouse'     => __( 'Townhouse', 'qoutify-estimator' ),
				),
			),
			array(
				'key' => 'project_kind', 'label' => __( 'Project kind', 'qoutify-estimator' ), 'type' => 'select', 'required' => true, 'group' => 'project',
				'options' => array(
					'renovation'       => __( 'Renovation', 'qoutify-estimator' ),
					'remodel'          => __( 'Remodel', 'qoutify-estimator' ),
					'addition'         => __( 'Addition', 'qoutify-estimator' ),
					'repair'           => __( 'Repair', 'qoutify-estimator' ),
					'new_construction' => __( 'New construction', 'qoutify-estimator' ),
				),
			),
			array( 'key' => 'sqft', 'label' => __( 'Size (square feet)', 'qoutify-estimator' ), 'type' => 'number', 'required' => true, 'min' => 200, 'max' => 50000, 'step' => 1, 'integer' => true, 'group' => 'project' ),
			array( 'key' => 'floors', 'label' => __( 'Floors', 'qoutify-estimator' ), 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 10, 'step' => 1, 'integer' => true, 'group' => 'project' ),
			array( 'key' => 'bedrooms', 'label' => __( 'Bedrooms', 'qoutify-estimator' ), 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 30, 'step' => 1, 'integer' => true, 'group' => 'project' ),
			array( 'key' => 'bathrooms', 'label' => __( 'Bathrooms', 'qoutify-estimator' ), 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 30, 'step' => 0.5, 'integer' => false, 'group' => 'project' ),
			array( 'key' => 'condition', 'label' => __( 'Current condition / work level', 'qoutify-estimator' ), 'type' => 'select', 'required' => true, 'options' => qtf_condition_options(), 'group' => 'project' ),
			array( 'key' => 'quality', 'label' => __( 'Finish quality', 'qoutify-estimator' ), 'type' => 'select', 'required' => true, 'options' => qtf_quality_options(), 'group' => 'project' ),
			array( 'key' => 'year_built', 'label' => __( 'Year built', 'qoutify-estimator' ), 'type' => 'number', 'required' => false, 'min' => 1800, 'max' => (int) gmdate( 'Y' ), 'step' => 1, 'integer' => true, 'group' => 'project' ),
			array(
				'key' => 'work_wanted', 'label' => __( 'Work wanted', 'qoutify-estimator' ), 'type' => 'checkboxes', 'required' => false, 'group' => 'work',
				'options' => array(
					'kitchen'       => __( 'Kitchen', 'qoutify-estimator' ),
					'bathrooms'     => __( 'Bathrooms', 'qoutify-estimator' ),
					'roof'          => __( 'Roof', 'qoutify-estimator' ),
					'flooring'      => __( 'Flooring', 'qoutify-estimator' ),
					'windows_doors' => __( 'Windows & doors', 'qoutify-estimator' ),
					'electrical'    => __( 'Electrical', 'qoutify-estimator' ),
					'plumbing'      => __( 'Plumbing', 'qoutify-estimator' ),
					'hvac'          => __( 'HVAC', 'qoutify-estimator' ),
					'painting'      => __( 'Painting', 'qoutify-estimator' ),
					'exterior'      => __( 'Exterior', 'qoutify-estimator' ),
					'other'         => __( 'Other', 'qoutify-estimator' ),
				),
			),
		);
	}

	$description = array(
		array( 'key' => 'description', 'label' => __( 'Project description (optional)', 'qoutify-estimator' ), 'type' => 'textarea', 'required' => false, 'max' => 2000, 'group' => 'work' ),
	);

	$fields = array_merge( $address, $specific, $description );
	$hints  = qtf_field_hints( $type );
	foreach ( $fields as $i => $field ) {
		if ( isset( $hints[ $field['key'] ] ) ) {
			$fields[ $i ]['placeholder'] = $hints[ $field['key'] ][0];
			$fields[ $i ]['help']        = $hints[ $field['key'] ][1];
		}
	}
	return $fields;
}

/**
 * Placeholder and short help for every field. These are guidance only: the visible label,
 * the required flag and the validation come from the field definitions above.
 */
function qtf_field_hints( $type ) {
	$commercial = ( 'commercial' === $type );
	return array(
		'street'        => array( __( 'Enter the property street address', 'qoutify-estimator' ), __( 'For example: 123 Main Street. The street address is not sent to the AI.', 'qoutify-estimator' ) ),
		'city'          => array( __( 'Enter the city', 'qoutify-estimator' ), __( 'The city where the property is located.', 'qoutify-estimator' ) ),
		'state'         => array( __( 'Select the state', 'qoutify-estimator' ), __( 'Local pricing depends on the state.', 'qoutify-estimator' ) ),
		'zip'           => array( __( 'Enter the ZIP code, for example 78701', 'qoutify-estimator' ), __( '5 digits, or 9 digits with a dash.', 'qoutify-estimator' ) ),
		'property_type' => array( __( 'Select the property type', 'qoutify-estimator' ), __( 'Choose the type that fits best.', 'qoutify-estimator' ) ),
		'building_use'  => array( __( 'Select how the building is used', 'qoutify-estimator' ), __( 'The use changes the plumbing, electrical and code requirements.', 'qoutify-estimator' ) ),
		'project_kind'  => array( __( 'Select what kind of project this is', 'qoutify-estimator' ), __( 'For example a renovation of an existing building.', 'qoutify-estimator' ) ),
		'sqft'          => array(
			__( 'Enter total area in square feet', 'qoutify-estimator' ),
			$commercial ? __( 'Total building area involved in the work, for example 12000.', 'qoutify-estimator' ) : __( 'Total living area, for example 2000.', 'qoutify-estimator' ),
		),
		'floors'        => array( __( 'Enter the number of floors', 'qoutify-estimator' ), __( 'For example: 2.', 'qoutify-estimator' ) ),
		'bedrooms'      => array( __( 'Enter the number of bedrooms', 'qoutify-estimator' ), __( 'Use 0 for a studio.', 'qoutify-estimator' ) ),
		'bathrooms'     => array( __( 'Enter the number of bathrooms', 'qoutify-estimator' ), __( 'Use 0.5 for a half bath, for example 2.5.', 'qoutify-estimator' ) ),
		'units'         => array( __( 'Enter the number of units or tenant spaces', 'qoutify-estimator' ), __( 'For example: 4.', 'qoutify-estimator' ) ),
		'condition'     => array( __( 'Select how much work is needed', 'qoutify-estimator' ), __( 'From a light refresh to a full gut renovation.', 'qoutify-estimator' ) ),
		'quality'       => array( __( 'Select the finish quality', 'qoutify-estimator' ), __( 'Basic uses economy materials; Luxury uses high-end materials.', 'qoutify-estimator' ) ),
		'occupied'      => array( __( 'Select if the building is in use during the work', 'qoutify-estimator' ), __( 'Working in an occupied building usually costs more.', 'qoutify-estimator' ) ),
		'year_built'    => array( __( 'Enter the year built, for example 1995', 'qoutify-estimator' ), __( 'Optional. Older buildings often have hidden costs.', 'qoutify-estimator' ) ),
		'work_wanted'   => array( '', __( 'Tick everything you want done. This helps the AI decide the scope.', 'qoutify-estimator' ) ),
		'description'   => array( __( 'Describe the planned work, known issues, or special requirements', 'qoutify-estimator' ), __( 'Optional. More detail gives a more reliable estimate. Maximum 2,000 characters.', 'qoutify-estimator' ) ),
	);
}

function qtf_field_groups() {
	return array(
		'property' => __( 'Property', 'qoutify-estimator' ),
		'project'  => __( 'Project', 'qoutify-estimator' ),
		'work'     => __( 'Work and notes', 'qoutify-estimator' ),
	);
}

/**
 * Cleans and checks the submitted details. Returns array( clean values, errors by field key ).
 */
function qtf_validate_details( $type, $input, $unslash = true ) {
	$clean  = array();
	$errors = array();

	foreach ( qtf_fields( $type ) as $field ) {
		$key = $field['key'];
		$raw = isset( $input[ $key ] ) ? ( $unslash ? wp_unslash( $input[ $key ] ) : $input[ $key ] ) : null;
		$req = ! empty( $field['required'] );

		switch ( $field['type'] ) {
			case 'checkboxes':
				$values        = is_array( $raw ) ? array_map( 'sanitize_text_field', $raw ) : array();
				$clean[ $key ] = array_values( array_intersect( $values, array_keys( $field['options'] ) ) );
				break;

			case 'select':
				$value = is_string( $raw ) ? sanitize_text_field( $raw ) : '';
				if ( '' === $value ) {
					$clean[ $key ] = '';
					if ( $req ) {
						$errors[ $key ] = __( 'Please choose an option.', 'qoutify-estimator' );
					}
				} elseif ( ! isset( $field['options'][ $value ] ) ) {
					$clean[ $key ]  = '';
					$errors[ $key ] = __( 'Please choose a valid option.', 'qoutify-estimator' );
				} else {
					$clean[ $key ] = $value;
				}
				break;

			case 'number':
				$text = is_scalar( $raw ) ? trim( (string) $raw ) : '';
				if ( '' === $text ) {
					$clean[ $key ] = '';
					if ( $req ) {
						$errors[ $key ] = __( 'This field is required.', 'qoutify-estimator' );
					}
				} elseif ( ! is_numeric( $text ) ) {
					$clean[ $key ]  = '';
					$errors[ $key ] = __( 'Enter a number.', 'qoutify-estimator' );
				} else {
					$number = (float) $text;
					if ( ! empty( $field['integer'] ) && floor( $number ) !== $number ) {
						$errors[ $key ] = __( 'Enter a whole number.', 'qoutify-estimator' );
					} elseif ( $number < $field['min'] || $number > $field['max'] ) {
						/* translators: 1: minimum, 2: maximum */
						$errors[ $key ] = sprintf( __( 'Enter a number between %1$s and %2$s.', 'qoutify-estimator' ), number_format_i18n( $field['min'] ), number_format_i18n( $field['max'] ) );
					}
					$clean[ $key ] = ( floor( $number ) === $number ) ? (int) $number : $number;
				}
				break;

			case 'textarea':
				$value = is_string( $raw ) ? trim( sanitize_textarea_field( $raw ) ) : '';
				if ( function_exists( 'mb_strlen' ) && mb_strlen( $value ) > $field['max'] ) {
					$errors[ $key ] = __( 'This text is too long.', 'qoutify-estimator' );
					$value          = mb_substr( $value, 0, $field['max'] );
				}
				$clean[ $key ] = $value;
				if ( $req && '' === $value ) {
					$errors[ $key ] = __( 'This field is required.', 'qoutify-estimator' );
				}
				break;

			default:
				$value = is_string( $raw ) ? trim( sanitize_text_field( $raw ) ) : '';
				if ( '' === $value && $req ) {
					$errors[ $key ] = __( 'This field is required.', 'qoutify-estimator' );
				} elseif ( '' !== $value && isset( $field['pattern'] ) && ! preg_match( $field['pattern'], $value ) ) {
					$errors[ $key ] = __( 'Enter a valid value.', 'qoutify-estimator' );
				} elseif ( ! empty( $field['max'] ) && strlen( $value ) > $field['max'] ) {
					$errors[ $key ] = __( 'This text is too long.', 'qoutify-estimator' );
				}
				$clean[ $key ] = $value;
		}
	}

	return array( $clean, $errors );
}

/**
 * Human-readable value of a stored field.
 */
function qtf_field_display( $field, $value ) {
	if ( 'checkboxes' === $field['type'] ) {
		$labels = array();
		foreach ( (array) $value as $item ) {
			if ( isset( $field['options'][ $item ] ) ) {
				$labels[] = $field['options'][ $item ];
			}
		}
		return $labels ? implode( ', ', $labels ) : '-';
	}
	if ( 'select' === $field['type'] ) {
		return ( '' !== $value && isset( $field['options'][ $value ] ) ) ? $field['options'][ $value ] : '-';
	}
	return ( '' === $value || null === $value ) ? '-' : (string) $value;
}

function qtf_render_field( $field, $value, $error ) {
	$key   = $field['key'];
	$id    = 'qtf_f_' . $key;
	$name  = 'qtf_d[' . $key . ']';
	$help  = isset( $field['help'] ) ? $field['help'] : '';
	$ph    = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
	$desc  = array();
	if ( $help ) {
		$desc[] = $id . '_help';
	}
	if ( $error ) {
		$desc[] = $id . '_err';
	}
	$aria  = $desc ? ' aria-describedby="' . esc_attr( implode( ' ', $desc ) ) . '"' : '';
	$html  = '<div class="qtf-field' . ( $error ? ' has-error' : '' ) . '">';
	$label = esc_html( $field['label'] ) . ( ! empty( $field['required'] ) ? ' <span class="qtf-req" aria-hidden="true">*</span>' : '' );

	if ( 'checkboxes' === $field['type'] ) {
		$html .= '<span class="qtf-label">' . $label . '</span><div class="qtf-checks">';
		foreach ( $field['options'] as $opt => $opt_label ) {
			$checked = in_array( (string) $opt, array_map( 'strval', (array) $value ), true ) ? ' checked' : '';
			$html   .= '<label class="qtf-check"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $opt ) . '"' . $checked . '> ' . esc_html( $opt_label ) . '</label>';
		}
		$html .= '</div>';
	} else {
		$html .= '<label for="' . esc_attr( $id ) . '">' . $label . '</label>';
		$req   = ! empty( $field['required'] ) ? ' required' : '';
		$phatt = $ph ? ' placeholder="' . esc_attr( $ph ) . '"' : '';
		if ( 'select' === $field['type'] ) {
			$html .= '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $req . $aria . '><option value="">' . esc_html( $ph ? $ph : __( 'Select...', 'qoutify-estimator' ) ) . '</option>';
			foreach ( $field['options'] as $opt => $opt_label ) {
				$html .= '<option value="' . esc_attr( $opt ) . '"' . selected( (string) $value, (string) $opt, false ) . '>' . esc_html( $opt_label ) . '</option>';
			}
			$html .= '</select>';
		} elseif ( 'textarea' === $field['type'] ) {
			$html .= '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" maxlength="' . (int) $field['max'] . '"' . $phatt . $aria . '>' . esc_textarea( (string) $value ) . '</textarea>';
		} elseif ( 'number' === $field['type'] ) {
			$html .= '<input type="number" inputmode="decimal" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" min="' . esc_attr( $field['min'] ) . '" max="' . esc_attr( $field['max'] ) . '" step="' . esc_attr( $field['step'] ) . '"' . $phatt . $req . $aria . '>';
		} else {
			$html .= '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" maxlength="' . (int) $field['max'] . '"' . $phatt . $req . $aria . '>';
		}
	}

	if ( $help ) {
		$html .= '<span class="qtf-help" id="' . esc_attr( $id . '_help' ) . '">' . esc_html( $help ) . '</span>';
	}
	if ( $error ) {
		$html .= '<span class="qtf-error" id="' . esc_attr( $id . '_err' ) . '">' . esc_html( $error ) . '</span>';
	}
	return $html . '</div>';
}
