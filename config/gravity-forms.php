<?php
/**
 * Gravity Forms 2.x entry tables, plus the legacy Gravity Forms 1.x tables
 * that may remain after upgrading.
 *
 * Entry field values can hold anything a form collects, so every field value
 * is replaced with a sentence: the form definitions would have to be parsed
 * to know which field is an email address, a phone number and so on. To use
 * a more specific rule for a field, or to keep a field's values, see the
 * README. Entry IDs, form IDs, dates, statuses and payment details are left
 * untouched.
 */

$ip_rule  = array( 'fake_data_type' => 'ipv4' );
$url_rule = array(
	// The page a form was submitted from. Query strings can carry personal data.
	'fake_data_type' => 'url',
);

// Field values, stored under the field ID, e.g. `1`, or the field and input ID
// for fields with several inputs, e.g. `2.3` for a name field's first name.
$field_value_rule = array(
	'constraint'     => array( 'meta_key' => array( 'regex' => '/^\d+(\.\d+)?$/' ) ),
	'fake_data_type' => 'sentence',
);

$draft_rules = array(
	'email'      => array( 'fake_data_type' => 'email' ),
	'ip'         => $ip_rule,
	'source_url' => $url_rule,
	// JSON with the submitted field values. Replace with an empty JSON object.
	'submission' => array(
		'fake_data_type' => 'randomElement',
		'fake_data_args' => array( array( '{}' ) ),
	),
);

$notes_rules = array(
	// Notes added by admins and add-ons can quote entry details.
	'value'     => array( 'fake_data_type' => 'sentence' ),
	'user_name' => array( 'fake_data_type' => 'userName' ),
);

return array(
	'gf_entry'                  => array(
		'ip'         => $ip_rule,
		'user_agent' => array( 'fake_data_type' => 'userAgent' ),
		'source_url' => $url_rule,
	),
	'gf_entry_meta'             => array(
		// Keys that aren't field IDs, e.g. add-on feed status, are left alone.
		'meta_value' => array( $field_value_rule ),
	),
	'gf_entry_notes'            => $notes_rules,
	'gf_draft_submissions'      => $draft_rules,
	'gf_form_view'              => array(
		'ip' => $ip_rule,
	),

	// Gravity Forms 1.x.
	'rg_lead'                   => array(
		'ip'         => $ip_rule,
		'user_agent' => array( 'fake_data_type' => 'userAgent' ),
		'source_url' => $url_rule,
	),
	'rg_lead_detail'            => array(
		'value' => array( 'fake_data_type' => 'sentence' ),
	),
	// Field values longer than 200 characters.
	'rg_lead_detail_long'       => array(
		'value' => array( 'fake_data_type' => 'sentence' ),
	),
	'rg_lead_notes'             => $notes_rules,
	'rg_incomplete_submissions' => $draft_rules,
	'rg_form_view'              => array(
		'ip' => $ip_rule,
	),
);
