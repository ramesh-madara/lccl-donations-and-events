<?php
/**
 * Country list and ITU-T E.164 dialing codes helper.
 *
 * @package LCCL_Donations_And_Events
 */

defined('ABSPATH') || exit;

/**
 * Provides comprehensive worldwide country list with ISO-3166 alpha-2 codes,
 * international calling codes, and phone validation lengths.
 */
class LCCL_DE_Countries
{

	/**
	 * Cached country list.
	 *
	 * @var array|null
	 */
	private static $countries = null;

	/**
	 * Return all worldwide countries sorted alphabetically, with Sri Lanka (+94)
	 * pinned at the top as the default, and "Other (+...)" at the bottom.
	 *
	 * @return array Array of arrays with 'code', 'dial', and 'name'.
	 */
	public static function get_all()
	{
		if (null !== self::$countries) {
			return self::$countries;
		}

		$raw = array(
			array('code' => 'AF', 'dial' => '+93', 'name' => __('Afghanistan (+93)', 'lccl-de')),
			array('code' => 'AL', 'dial' => '+355', 'name' => __('Albania (+355)', 'lccl-de')),
			array('code' => 'DZ', 'dial' => '+213', 'name' => __('Algeria (+213)', 'lccl-de')),
			array('code' => 'AS', 'dial' => '+1684', 'name' => __('American Samoa (+1684)', 'lccl-de')),
			array('code' => 'AD', 'dial' => '+376', 'name' => __('Andorra (+376)', 'lccl-de')),
			array('code' => 'AO', 'dial' => '+244', 'name' => __('Angola (+244)', 'lccl-de')),
			array('code' => 'AI', 'dial' => '+1264', 'name' => __('Anguilla (+1264)', 'lccl-de')),
			array('code' => 'AG', 'dial' => '+1268', 'name' => __('Antigua and Barbuda (+1268)', 'lccl-de')),
			array('code' => 'AR', 'dial' => '+54', 'name' => __('Argentina (+54)', 'lccl-de')),
			array('code' => 'AM', 'dial' => '+374', 'name' => __('Armenia (+374)', 'lccl-de')),
			array('code' => 'AW', 'dial' => '+297', 'name' => __('Aruba (+297)', 'lccl-de')),
			array('code' => 'AU', 'dial' => '+61', 'name' => __('Australia (+61)', 'lccl-de')),
			array('code' => 'AT', 'dial' => '+43', 'name' => __('Austria (+43)', 'lccl-de')),
			array('code' => 'AZ', 'dial' => '+994', 'name' => __('Azerbaijan (+994)', 'lccl-de')),
			array('code' => 'BS', 'dial' => '+1242', 'name' => __('Bahamas (+1242)', 'lccl-de')),
			array('code' => 'BH', 'dial' => '+973', 'name' => __('Bahrain (+973)', 'lccl-de')),
			array('code' => 'BD', 'dial' => '+880', 'name' => __('Bangladesh (+880)', 'lccl-de')),
			array('code' => 'BB', 'dial' => '+1246', 'name' => __('Barbados (+1246)', 'lccl-de')),
			array('code' => 'BY', 'dial' => '+375', 'name' => __('Belarus (+375)', 'lccl-de')),
			array('code' => 'BE', 'dial' => '+32', 'name' => __('Belgium (+32)', 'lccl-de')),
			array('code' => 'BZ', 'dial' => '+501', 'name' => __('Belize (+501)', 'lccl-de')),
			array('code' => 'BJ', 'dial' => '+229', 'name' => __('Benin (+229)', 'lccl-de')),
			array('code' => 'BM', 'dial' => '+1441', 'name' => __('Bermuda (+1441)', 'lccl-de')),
			array('code' => 'BT', 'dial' => '+975', 'name' => __('Bhutan (+975)', 'lccl-de')),
			array('code' => 'BO', 'dial' => '+591', 'name' => __('Bolivia (+591)', 'lccl-de')),
			array('code' => 'BA', 'dial' => '+387', 'name' => __('Bosnia and Herzegovina (+387)', 'lccl-de')),
			array('code' => 'BW', 'dial' => '+267', 'name' => __('Botswana (+267)', 'lccl-de')),
			array('code' => 'BR', 'dial' => '+55', 'name' => __('Brazil (+55)', 'lccl-de')),
			array('code' => 'BN', 'dial' => '+673', 'name' => __('Brunei (+673)', 'lccl-de')),
			array('code' => 'BG', 'dial' => '+359', 'name' => __('Bulgaria (+359)', 'lccl-de')),
			array('code' => 'BF', 'dial' => '+226', 'name' => __('Burkina Faso (+226)', 'lccl-de')),
			array('code' => 'BI', 'dial' => '+257', 'name' => __('Burundi (+257)', 'lccl-de')),
			array('code' => 'KH', 'dial' => '+855', 'name' => __('Cambodia (+855)', 'lccl-de')),
			array('code' => 'CM', 'dial' => '+237', 'name' => __('Cameroon (+237)', 'lccl-de')),
			array('code' => 'CA', 'dial' => '+1', 'name' => __('Canada (+1)', 'lccl-de')),
			array('code' => 'CV', 'dial' => '+238', 'name' => __('Cape Verde (+238)', 'lccl-de')),
			array('code' => 'KY', 'dial' => '+1345', 'name' => __('Cayman Islands (+1345)', 'lccl-de')),
			array('code' => 'CF', 'dial' => '+236', 'name' => __('Central African Republic (+236)', 'lccl-de')),
			array('code' => 'TD', 'dial' => '+235', 'name' => __('Chad (+235)', 'lccl-de')),
			array('code' => 'CL', 'dial' => '+56', 'name' => __('Chile (+56)', 'lccl-de')),
			array('code' => 'CN', 'dial' => '+86', 'name' => __('China (+86)', 'lccl-de')),
			array('code' => 'CO', 'dial' => '+57', 'name' => __('Colombia (+57)', 'lccl-de')),
			array('code' => 'KM', 'dial' => '+269', 'name' => __('Comoros (+269)', 'lccl-de')),
			array('code' => 'CG', 'dial' => '+242', 'name' => __('Congo - Brazzaville (+242)', 'lccl-de')),
			array('code' => 'CD', 'dial' => '+243', 'name' => __('Congo - Kinshasa (+243)', 'lccl-de')),
			array('code' => 'CK', 'dial' => '+682', 'name' => __('Cook Islands (+682)', 'lccl-de')),
			array('code' => 'CR', 'dial' => '+506', 'name' => __('Costa Rica (+506)', 'lccl-de')),
			array('code' => 'HR', 'dial' => '+385', 'name' => __('Croatia (+385)', 'lccl-de')),
			array('code' => 'CU', 'dial' => '+53', 'name' => __('Cuba (+53)', 'lccl-de')),
			array('code' => 'CY', 'dial' => '+357', 'name' => __('Cyprus (+357)', 'lccl-de')),
			array('code' => 'CZ', 'dial' => '+420', 'name' => __('Czech Republic (+420)', 'lccl-de')),
			array('code' => 'DK', 'dial' => '+45', 'name' => __('Denmark (+45)', 'lccl-de')),
			array('code' => 'DJ', 'dial' => '+253', 'name' => __('Djibouti (+253)', 'lccl-de')),
			array('code' => 'DM', 'dial' => '+1767', 'name' => __('Dominica (+1767)', 'lccl-de')),
			array('code' => 'DO', 'dial' => '+1809', 'name' => __('Dominican Republic (+1809)', 'lccl-de')),
			array('code' => 'EC', 'dial' => '+593', 'name' => __('Ecuador (+593)', 'lccl-de')),
			array('code' => 'EG', 'dial' => '+20', 'name' => __('Egypt (+20)', 'lccl-de')),
			array('code' => 'SV', 'dial' => '+503', 'name' => __('El Salvador (+503)', 'lccl-de')),
			array('code' => 'GQ', 'dial' => '+240', 'name' => __('Equatorial Guinea (+240)', 'lccl-de')),
			array('code' => 'ER', 'dial' => '+291', 'name' => __('Eritrea (+291)', 'lccl-de')),
			array('code' => 'EE', 'dial' => '+372', 'name' => __('Estonia (+372)', 'lccl-de')),
			array('code' => 'SZ', 'dial' => '+268', 'name' => __('Eswatini (+268)', 'lccl-de')),
			array('code' => 'ET', 'dial' => '+251', 'name' => __('Ethiopia (+251)', 'lccl-de')),
			array('code' => 'FJ', 'dial' => '+679', 'name' => __('Fiji (+679)', 'lccl-de')),
			array('code' => 'FI', 'dial' => '+358', 'name' => __('Finland (+358)', 'lccl-de')),
			array('code' => 'FR', 'dial' => '+33', 'name' => __('France (+33)', 'lccl-de')),
			array('code' => 'GF', 'dial' => '+594', 'name' => __('French Guiana (+594)', 'lccl-de')),
			array('code' => 'PF', 'dial' => '+689', 'name' => __('French Polynesia (+689)', 'lccl-de')),
			array('code' => 'GA', 'dial' => '+241', 'name' => __('Gabon (+241)', 'lccl-de')),
			array('code' => 'GM', 'dial' => '+220', 'name' => __('Gambia (+220)', 'lccl-de')),
			array('code' => 'GE', 'dial' => '+995', 'name' => __('Georgia (+995)', 'lccl-de')),
			array('code' => 'DE', 'dial' => '+49', 'name' => __('Germany (+49)', 'lccl-de')),
			array('code' => 'GH', 'dial' => '+233', 'name' => __('Ghana (+233)', 'lccl-de')),
			array('code' => 'GI', 'dial' => '+350', 'name' => __('Gibraltar (+350)', 'lccl-de')),
			array('code' => 'GR', 'dial' => '+30', 'name' => __('Greece (+30)', 'lccl-de')),
			array('code' => 'GL', 'dial' => '+299', 'name' => __('Greenland (+299)', 'lccl-de')),
			array('code' => 'GD', 'dial' => '+1473', 'name' => __('Grenada (+1473)', 'lccl-de')),
			array('code' => 'GP', 'dial' => '+590', 'name' => __('Guadeloupe (+590)', 'lccl-de')),
			array('code' => 'GU', 'dial' => '+1671', 'name' => __('Guam (+1671)', 'lccl-de')),
			array('code' => 'GT', 'dial' => '+502', 'name' => __('Guatemala (+502)', 'lccl-de')),
			array('code' => 'GN', 'dial' => '+224', 'name' => __('Guinea (+224)', 'lccl-de')),
			array('code' => 'GW', 'dial' => '+245', 'name' => __('Guinea-Bissau (+245)', 'lccl-de')),
			array('code' => 'GY', 'dial' => '+592', 'name' => __('Guyana (+592)', 'lccl-de')),
			array('code' => 'HT', 'dial' => '+509', 'name' => __('Haiti (+509)', 'lccl-de')),
			array('code' => 'HN', 'dial' => '+504', 'name' => __('Honduras (+504)', 'lccl-de')),
			array('code' => 'HK', 'dial' => '+852', 'name' => __('Hong Kong (+852)', 'lccl-de')),
			array('code' => 'HU', 'dial' => '+36', 'name' => __('Hungary (+36)', 'lccl-de')),
			array('code' => 'IS', 'dial' => '+354', 'name' => __('Iceland (+354)', 'lccl-de')),
			array('code' => 'IN', 'dial' => '+91', 'name' => __('India (+91)', 'lccl-de')),
			array('code' => 'ID', 'dial' => '+62', 'name' => __('Indonesia (+62)', 'lccl-de')),
			array('code' => 'IR', 'dial' => '+98', 'name' => __('Iran (+98)', 'lccl-de')),
			array('code' => 'IQ', 'dial' => '+964', 'name' => __('Iraq (+964)', 'lccl-de')),
			array('code' => 'IE', 'dial' => '+353', 'name' => __('Ireland (+353)', 'lccl-de')),
			array('code' => 'IL', 'dial' => '+972', 'name' => __('Israel (+972)', 'lccl-de')),
			array('code' => 'IT', 'dial' => '+39', 'name' => __('Italy (+39)', 'lccl-de')),
			array('code' => 'CI', 'dial' => '+225', 'name' => __('Ivory Coast (+225)', 'lccl-de')),
			array('code' => 'JM', 'dial' => '+1876', 'name' => __('Jamaica (+1876)', 'lccl-de')),
			array('code' => 'JP', 'dial' => '+81', 'name' => __('Japan (+81)', 'lccl-de')),
			array('code' => 'JO', 'dial' => '+962', 'name' => __('Jordan (+962)', 'lccl-de')),
			array('code' => 'KZ', 'dial' => '+7', 'name' => __('Kazakhstan (+7)', 'lccl-de')),
			array('code' => 'KE', 'dial' => '+254', 'name' => __('Kenya (+254)', 'lccl-de')),
			array('code' => 'KI', 'dial' => '+686', 'name' => __('Kiribati (+686)', 'lccl-de')),
			array('code' => 'KW', 'dial' => '+965', 'name' => __('Kuwait (+965)', 'lccl-de')),
			array('code' => 'KG', 'dial' => '+996', 'name' => __('Kyrgyzstan (+996)', 'lccl-de')),
			array('code' => 'LA', 'dial' => '+856', 'name' => __('Laos (+856)', 'lccl-de')),
			array('code' => 'LV', 'dial' => '+371', 'name' => __('Latvia (+371)', 'lccl-de')),
			array('code' => 'LB', 'dial' => '+961', 'name' => __('Lebanon (+961)', 'lccl-de')),
			array('code' => 'LS', 'dial' => '+266', 'name' => __('Lesotho (+266)', 'lccl-de')),
			array('code' => 'LR', 'dial' => '+231', 'name' => __('Liberia (+231)', 'lccl-de')),
			array('code' => 'LY', 'dial' => '+218', 'name' => __('Libya (+218)', 'lccl-de')),
			array('code' => 'LI', 'dial' => '+423', 'name' => __('Liechtenstein (+423)', 'lccl-de')),
			array('code' => 'LT', 'dial' => '+370', 'name' => __('Lithuania (+370)', 'lccl-de')),
			array('code' => 'LU', 'dial' => '+352', 'name' => __('Luxembourg (+352)', 'lccl-de')),
			array('code' => 'MO', 'dial' => '+853', 'name' => __('Macau (+853)', 'lccl-de')),
			array('code' => 'MG', 'dial' => '+261', 'name' => __('Madagascar (+261)', 'lccl-de')),
			array('code' => 'MW', 'dial' => '+265', 'name' => __('Malawi (+265)', 'lccl-de')),
			array('code' => 'MY', 'dial' => '+60', 'name' => __('Malaysia (+60)', 'lccl-de')),
			array('code' => 'MV', 'dial' => '+960', 'name' => __('Maldives (+960)', 'lccl-de')),
			array('code' => 'ML', 'dial' => '+223', 'name' => __('Mali (+223)', 'lccl-de')),
			array('code' => 'MT', 'dial' => '+356', 'name' => __('Malta (+356)', 'lccl-de')),
			array('code' => 'MH', 'dial' => '+692', 'name' => __('Marshall Islands (+692)', 'lccl-de')),
			array('code' => 'MQ', 'dial' => '+596', 'name' => __('Martinique (+596)', 'lccl-de')),
			array('code' => 'MR', 'dial' => '+222', 'name' => __('Mauritania (+222)', 'lccl-de')),
			array('code' => 'MU', 'dial' => '+230', 'name' => __('Mauritius (+230)', 'lccl-de')),
			array('code' => 'YT', 'dial' => '+262', 'name' => __('Mayotte (+262)', 'lccl-de')),
			array('code' => 'MX', 'dial' => '+52', 'name' => __('Mexico (+52)', 'lccl-de')),
			array('code' => 'FM', 'dial' => '+691', 'name' => __('Micronesia (+691)', 'lccl-de')),
			array('code' => 'MD', 'dial' => '+373', 'name' => __('Moldova (+373)', 'lccl-de')),
			array('code' => 'MC', 'dial' => '+377', 'name' => __('Monaco (+377)', 'lccl-de')),
			array('code' => 'MN', 'dial' => '+976', 'name' => __('Mongolia (+976)', 'lccl-de')),
			array('code' => 'ME', 'dial' => '+382', 'name' => __('Montenegro (+382)', 'lccl-de')),
			array('code' => 'MS', 'dial' => '+1664', 'name' => __('Montserrat (+1664)', 'lccl-de')),
			array('code' => 'MA', 'dial' => '+212', 'name' => __('Morocco (+212)', 'lccl-de')),
			array('code' => 'MZ', 'dial' => '+258', 'name' => __('Mozambique (+258)', 'lccl-de')),
			array('code' => 'MM', 'dial' => '+95', 'name' => __('Myanmar (+95)', 'lccl-de')),
			array('code' => 'NA', 'dial' => '+264', 'name' => __('Namibia (+264)', 'lccl-de')),
			array('code' => 'NR', 'dial' => '+674', 'name' => __('Nauru (+674)', 'lccl-de')),
			array('code' => 'NP', 'dial' => '+977', 'name' => __('Nepal (+977)', 'lccl-de')),
			array('code' => 'NL', 'dial' => '+31', 'name' => __('Netherlands (+31)', 'lccl-de')),
			array('code' => 'NC', 'dial' => '+687', 'name' => __('New Caledonia (+687)', 'lccl-de')),
			array('code' => 'NZ', 'dial' => '+64', 'name' => __('New Zealand (+64)', 'lccl-de')),
			array('code' => 'NI', 'dial' => '+505', 'name' => __('Nicaragua (+505)', 'lccl-de')),
			array('code' => 'NE', 'dial' => '+227', 'name' => __('Niger (+227)', 'lccl-de')),
			array('code' => 'NG', 'dial' => '+234', 'name' => __('Nigeria (+234)', 'lccl-de')),
			array('code' => 'MK', 'dial' => '+389', 'name' => __('North Macedonia (+389)', 'lccl-de')),
			array('code' => 'NO', 'dial' => '+47', 'name' => __('Norway (+47)', 'lccl-de')),
			array('code' => 'OM', 'dial' => '+968', 'name' => __('Oman (+968)', 'lccl-de')),
			array('code' => 'PK', 'dial' => '+92', 'name' => __('Pakistan (+92)', 'lccl-de')),
			array('code' => 'PW', 'dial' => '+680', 'name' => __('Palau (+680)', 'lccl-de')),
			array('code' => 'PS', 'dial' => '+970', 'name' => __('Palestine (+970)', 'lccl-de')),
			array('code' => 'PA', 'dial' => '+507', 'name' => __('Panama (+507)', 'lccl-de')),
			array('code' => 'PG', 'dial' => '+675', 'name' => __('Papua New Guinea (+675)', 'lccl-de')),
			array('code' => 'PY', 'dial' => '+595', 'name' => __('Paraguay (+595)', 'lccl-de')),
			array('code' => 'PE', 'dial' => '+51', 'name' => __('Peru (+51)', 'lccl-de')),
			array('code' => 'PH', 'dial' => '+63', 'name' => __('Philippines (+63)', 'lccl-de')),
			array('code' => 'PL', 'dial' => '+48', 'name' => __('Poland (+48)', 'lccl-de')),
			array('code' => 'PT', 'dial' => '+351', 'name' => __('Portugal (+351)', 'lccl-de')),
			array('code' => 'PR', 'dial' => '+1787', 'name' => __('Puerto Rico (+1787)', 'lccl-de')),
			array('code' => 'QA', 'dial' => '+974', 'name' => __('Qatar (+974)', 'lccl-de')),
			array('code' => 'RE', 'dial' => '+262', 'name' => __('Reunion (+262)', 'lccl-de')),
			array('code' => 'RO', 'dial' => '+40', 'name' => __('Romania (+40)', 'lccl-de')),
			array('code' => 'RU', 'dial' => '+7', 'name' => __('Russia (+7)', 'lccl-de')),
			array('code' => 'RW', 'dial' => '+250', 'name' => __('Rwanda (+250)', 'lccl-de')),
			array('code' => 'KN', 'dial' => '+1869', 'name' => __('Saint Kitts and Nevis (+1869)', 'lccl-de')),
			array('code' => 'LC', 'dial' => '+1758', 'name' => __('Saint Lucia (+1758)', 'lccl-de')),
			array('code' => 'VC', 'dial' => '+1784', 'name' => __('Saint Vincent and the Grenadines (+1784)', 'lccl-de')),
			array('code' => 'WS', 'dial' => '+685', 'name' => __('Samoa (+685)', 'lccl-de')),
			array('code' => 'SM', 'dial' => '+378', 'name' => __('San Marino (+378)', 'lccl-de')),
			array('code' => 'ST', 'dial' => '+239', 'name' => __('Sao Tome and Principe (+239)', 'lccl-de')),
			array('code' => 'SA', 'dial' => '+966', 'name' => __('Saudi Arabia (+966)', 'lccl-de')),
			array('code' => 'SN', 'dial' => '+221', 'name' => __('Senegal (+221)', 'lccl-de')),
			array('code' => 'RS', 'dial' => '+381', 'name' => __('Serbia (+381)', 'lccl-de')),
			array('code' => 'SC', 'dial' => '+248', 'name' => __('Seychelles (+248)', 'lccl-de')),
			array('code' => 'SL', 'dial' => '+232', 'name' => __('Sierra Leone (+232)', 'lccl-de')),
			array('code' => 'SG', 'dial' => '+65', 'name' => __('Singapore (+65)', 'lccl-de')),
			array('code' => 'SK', 'dial' => '+421', 'name' => __('Slovakia (+421)', 'lccl-de')),
			array('code' => 'SI', 'dial' => '+386', 'name' => __('Slovenia (+386)', 'lccl-de')),
			array('code' => 'SB', 'dial' => '+677', 'name' => __('Solomon Islands (+677)', 'lccl-de')),
			array('code' => 'SO', 'dial' => '+252', 'name' => __('Somalia (+252)', 'lccl-de')),
			array('code' => 'ZA', 'dial' => '+27', 'name' => __('South Africa (+27)', 'lccl-de')),
			array('code' => 'KR', 'dial' => '+82', 'name' => __('South Korea (+82)', 'lccl-de')),
			array('code' => 'SS', 'dial' => '+211', 'name' => __('South Sudan (+211)', 'lccl-de')),
			array('code' => 'ES', 'dial' => '+34', 'name' => __('Spain (+34)', 'lccl-de')),
			array('code' => 'LK', 'dial' => '+94', 'name' => __('Sri Lanka (+94)', 'lccl-de')),
			array('code' => 'SD', 'dial' => '+249', 'name' => __('Sudan (+249)', 'lccl-de')),
			array('code' => 'SR', 'dial' => '+597', 'name' => __('Suriname (+597)', 'lccl-de')),
			array('code' => 'SE', 'dial' => '+46', 'name' => __('Sweden (+46)', 'lccl-de')),
			array('code' => 'CH', 'dial' => '+41', 'name' => __('Switzerland (+41)', 'lccl-de')),
			array('code' => 'SY', 'dial' => '+963', 'name' => __('Syria (+963)', 'lccl-de')),
			array('code' => 'TW', 'dial' => '+886', 'name' => __('Taiwan (+886)', 'lccl-de')),
			array('code' => 'TJ', 'dial' => '+992', 'name' => __('Tajikistan (+992)', 'lccl-de')),
			array('code' => 'TZ', 'dial' => '+255', 'name' => __('Tanzania (+255)', 'lccl-de')),
			array('code' => 'TH', 'dial' => '+66', 'name' => __('Thailand (+66)', 'lccl-de')),
			array('code' => 'TL', 'dial' => '+670', 'name' => __('Timor-Leste (+670)', 'lccl-de')),
			array('code' => 'TG', 'dial' => '+228', 'name' => __('Togo (+228)', 'lccl-de')),
			array('code' => 'TO', 'dial' => '+676', 'name' => __('Tonga (+676)', 'lccl-de')),
			array('code' => 'TT', 'dial' => '+1868', 'name' => __('Trinidad and Tobago (+1868)', 'lccl-de')),
			array('code' => 'TN', 'dial' => '+216', 'name' => __('Tunisia (+216)', 'lccl-de')),
			array('code' => 'TR', 'dial' => '+90', 'name' => __('Turkey (+90)', 'lccl-de')),
			array('code' => 'TM', 'dial' => '+993', 'name' => __('Turkmenistan (+993)', 'lccl-de')),
			array('code' => 'TC', 'dial' => '+1649', 'name' => __('Turks and Caicos Islands (+1649)', 'lccl-de')),
			array('code' => 'TV', 'dial' => '+688', 'name' => __('Tuvalu (+688)', 'lccl-de')),
			array('code' => 'UG', 'dial' => '+256', 'name' => __('Uganda (+256)', 'lccl-de')),
			array('code' => 'UA', 'dial' => '+380', 'name' => __('Ukraine (+380)', 'lccl-de')),
			array('code' => 'AE', 'dial' => '+971', 'name' => __('United Arab Emirates (+971)', 'lccl-de')),
			array('code' => 'GB', 'dial' => '+44', 'name' => __('United Kingdom (+44)', 'lccl-de')),
			array('code' => 'US', 'dial' => '+1', 'name' => __('United States (+1)', 'lccl-de')),
			array('code' => 'UY', 'dial' => '+598', 'name' => __('Uruguay (+598)', 'lccl-de')),
			array('code' => 'UZ', 'dial' => '+998', 'name' => __('Uzbekistan (+998)', 'lccl-de')),
			array('code' => 'VU', 'dial' => '+678', 'name' => __('Vanuatu (+678)', 'lccl-de')),
			array('code' => 'VA', 'dial' => '+379', 'name' => __('Vatican City (+379)', 'lccl-de')),
			array('code' => 'VE', 'dial' => '+58', 'name' => __('Venezuela (+58)', 'lccl-de')),
			array('code' => 'VN', 'dial' => '+84', 'name' => __('Vietnam (+84)', 'lccl-de')),
			array('code' => 'VG', 'dial' => '+1284', 'name' => __('Virgin Islands (British) (+1284)', 'lccl-de')),
			array('code' => 'VI', 'dial' => '+1340', 'name' => __('Virgin Islands (U.S.) (+1340)', 'lccl-de')),
			array('code' => 'YE', 'dial' => '+967', 'name' => __('Yemen (+967)', 'lccl-de')),
			array('code' => 'ZM', 'dial' => '+260', 'name' => __('Zambia (+260)', 'lccl-de')),
			array('code' => 'ZW', 'dial' => '+263', 'name' => __('Zimbabwe (+263)', 'lccl-de')),
		);

		$list = $raw;

		// Catch-all Other option at the bottom.
		$list[] = array('code' => '+', 'dial' => '', 'name' => __('Other (+...)', 'lccl-de'));

		foreach ($list as &$item) {
			$flag = self::flag_emoji($item['code']);
			$item['flag'] = $flag;
			$item['flag_url'] = self::flag_url($item['code']);
			$clean = trim((string) preg_replace('/\s*\(\+[^)]*\)$/', '', $item['name']));
			$item['clean_name'] = $clean;
			if ('' !== $item['dial']) {
				$item['option_label'] = $flag . ' (' . $item['dial'] . ') ' . $clean;
			} else {
				$item['option_label'] = $flag . ' ' . $clean;
			}
		}
		unset($item);

		self::$countries = $list;

		return self::$countries;
	}

	/**
	 * Get local flag PNG image URL by ISO-2 country code.
	 *
	 * @param string $code ISO 3166-1 alpha-2 code (e.g. 'LK', 'US').
	 * @return string Full local URL to the flag PNG or empty string if invalid.
	 */
	public static function flag_url($code)
	{
		$code = strtolower(trim((string) $code));
		if (2 !== strlen($code) || !ctype_alpha($code)) {
			return '';
		}
		return LCCL_DE_URL . 'assets/img/w20/' . $code . '.png';
	}

	/**
	 * Convert 2-letter ISO country code to Unicode Flag Emoji.
	 *
	 * @param string $code ISO 3166-1 alpha-2 code (e.g. 'LK', 'US').
	 * @return string Unicode flag emoji or globe emoji for invalid/other.
	 */
	public static function flag_emoji($code)
	{
		$code = strtoupper(trim((string) $code));
		if (2 !== strlen($code) || !ctype_alpha($code)) {
			return '🌐';
		}
		$first = 0x1F1E6 + (ord($code[0]) - ord('A'));
		$second = 0x1F1E6 + (ord($code[1]) - ord('A'));
		if (function_exists('mb_chr')) {
			return mb_chr($first, 'UTF-8') . mb_chr($second, 'UTF-8');
		}
		return '🌐';
	}

	/**
	 * Find country option by dialing code or return default (LK).
	 *
	 * @param string $dial_code Calling code (e.g. '+94', '+1').
	 * @return array Country info array.
	 */
	public static function get_by_dial($dial_code)
	{
		$all = self::get_all();
		$default = null;
		foreach ($all as $c) {
			if ('LK' === $c['code']) {
				$default = $c;
			}
			if ($c['dial'] === $dial_code) {
				return $c;
			}
		}
		return $default ? $default : $all[0];
	}
}

