<?php

// we will define validators here

// numbers, phone numbers, email, dates, mandatory, url, alphanumeric, IP address
// @field.validate [min:5|second_validation|third_validation]

$_Q_Validators = [
	// mandatory is a special rule
	"mandatory" => ["!empty(\$value)", "<b>\$property</b> is mandatory"],
	"required" => ["!empty(\$value)", "\$value is required", "This field is required!"],
	"boolean" => ["(is_bool(\$value) !== true)", "\$property needs to be boolean!", "\$property needs to be boolean!"],
	"true" => ["\$value", "\$value must evaluate to true"],
	"false" => ["!\$value", "\$value must evaluate to false"],
	"url" => ["filter_var(\$value, FILTER_VALIDATE_URL)", "Invalid URL \$value"],
	"email" => ["filter_var(\$value, FILTER_VALIDATE_EMAIL)", "Invalid email <b>\$value</b>!", "Email correct format is nicknamedomain.com"],
	"ip" => ["filter_var(\$value, FILTER_VALIDATE_IP)", "Invalid IP \$value", "IP correct format is xxx.xxx.xxx.xxx"],
	"mac" => ["filter_var(\$value, FILTER_VALIDATE_MAC)", "Invalid MAC \$value", "MAC address correct format is xx.xx.xx.xx.xx.xx"],
	"float" => ["filter_var(\$value, FILTER_VALIDATE_FLOAT)", "Only numeric values are allowed", "Only numeric values are allowed"],
	"int" => ["filter_var(\$value, FILTER_VALIDATE_INT)", "Only integer values are allowed", "Only integer values are allowed"],
	"alnum" => "ctype_alnum(\$value)",
	"alpha" => "ctype_alpha(\$value)",
	"digits" => "ctype_digit(\$value)",
	"hex" => "ctype_xdigit(\$value)",
	"min(\$_min_)" => ["strlen(\$value) >= \$_min_", "Min length is <b>\$_min_</b>!"],
	"max(\$_max_)" => ["strlen(\$value) <= \$_max_", "Max length is <b>\$_max_</b>!"],
	"regex(\$_pattern_)" => ["preg_match(\"\$_pattern_\", \$value)",  "Incorrect password format", "Incorrect password format"],

	"between(\$_min_, \$_max_)" => ["(\$value > \$_min_) && (\$value < \$_max_)", 
		"Value should be between <b>\$_min_</b> and <b>\$_max_</b>!"
	],

	"betweenInc(\$_min_, \$_max_)" => ["(\$value >= \$_min_) && (\$value <= \$_max_)", 
		"Value should be between <b>\$_min_</b> and <b>\$_max_</b>, inclusive!"
	],

	"strBetween(\$_min_, \$_max_)" => ["(strlen(\$value) > \$_min_) && (strlen(\$value) < \$_max_)", 
		"The length of \$property should be between <b>\$_min_</b> and <b>\$_max_</b>!",
		"The length of \$property should be between <b>\$_min_</b> and <b>\$_max_</b>!"
	],

	"strBetweenInc(\$_min_, \$_max_)" => ["(strlen(\$value) >= \$_min_) && (strlen(\$value) <= \$_max_)", 
		"The length of \$property should be between <b>\$_min_</b> and <b>\$_max_</b>, inclusive!",
		"The length of \$property should be between <b>\$_min_</b> and <b>\$_max_</b>, inclusive!"
	],

	"positive" => "\$value >= 0",
	"negative" => "\$value < 0",
	"greater(\$_number_)" => "\$value > \$_number_",
	"smaller(\$_number_)" => "\$value < \$_number_",
	"greaterOr(\$_number_)" => ["\$value >= \$_number_", "Min value allowed is \$_number_", "Min value allowed is \$_number_"],
	"smallerOr(\$_number_)" => "\$value <= \$_number_",
	"equal(\$_number_)" => "\$value == \$_number_",
	"length(\$_number_)" => "strlen(\$value) == \$_number_",
	"security(\$_selector_)" => "qSecurityCheck(\$value, \$_selector_)",
	"securityApi(\$_selector_, \$_class_, \$_method_)" => "qSecurityCheck(\$value, \$_selector_, \$_class_, \$_method_)",
];

// automate: date, datetime, time

// a setting to TRIM all strings !!!

$_Q_Fixers = [
	"trim" => "(\$value !== null) ? trim(\$value) : null",
	"maxSelector(\$_maxselector_)" => "qIntersectSelectors(\$_maxselector_, \$value)",
	"secureSelector(\$_className_, \$_maxselector_)" => "qSecureSelector(\$_className_, \$value, \$_maxselector_)",
];

$_Q_Encoders = [
	"md5" => "(\$value !== null) ? md5(\$value) : null",
	"sha1" => "(\$value !== null) ? sha1(\$value) : null",
];