# Filter reference

Every dropdown in the registration form runs its options through a filter, so
the lists can be changed from the theme's `functions.php` or a small companion
plugin without editing this plugin's code.

| Filter | Field | Shape |
| --- | --- | --- |
| `lccl_de_districts` | District | `value => label` |
| `lccl_de_blood_banks_by_district` | Preferred blood bank, grouped | `district => array( key => label )` |
| `lccl_de_blood_banks` | Preferred blood bank, flattened | `key => label`, plus `$district` argument |
| `lccl_de_donation_preferences` | How would you prefer to donate? | `value => label` |
| `lccl_de_donation_history_options` | Have you donated blood before? | `value => label` |
| `lccl_de_contact_methods` | Preferred Contact Method | `value => label` |

## Blood banks are grouped by district

The blood bank field depends on the district field, so the source data is a
two level array keyed by district name:

```php
array(
	'Colombo' => array(
		'nbc-narahenpita' => 'National Blood Center, Narahenpita',
		'nhsl'            => 'National Hospital of Sri Lanka, Colombo',
		// ...
	),
	'Kandy'   => array(
		'kandy'   => 'Kandy',
		'gampola' => 'Gampola',
		// ...
	),
)
```

District keys must match the values produced by `lccl_de_districts` exactly, or
the banks for that district will never be reachable.

### Where the data came from

The shipped list holds **108 blood banks across all 25 districts**. Sites come
from the NBTS Statistical Annual Report 2024. **Hospital names** come from the
Ministry of Health institution list dated 31 December 2024.

Labels follow `Place - Hospital name Blood Bank`, for example
`Panadura - Base Hospital Panadura Blood Bank`. Base Hospital Type A and Type B
are both shown as "Base Hospital". Stored values are the array keys, not the
labels, so renaming a label later does not break saved submissions.

One caveat worth knowing: **NBTS organises its blood banks into 24 geographic
clusters, not districts.** Cluster names do not always match district names —
the Chilaw cluster covers Puttalam district, Karapitiya covers Galle, and the
Colombo area is split across the CIM, CNTH, and Colombo clusters. The shipped
data remaps each facility onto the district it physically sits in, which is
what a donor filling in the form would expect.

Because that remapping is a judgement call rather than an official mapping, the
list should be reviewed by someone who knows the network before launch.
Dehiattakandiya is the clearest example: NBTS groups it under the Polonnaruwa
cluster and it is listed here under Polonnaruwa, but it is administratively in
Ampara district.

### Replacing the list

```php
add_filter( 'lccl_de_blood_banks_by_district', function () {
	return array(
		'Colombo' => array(
			'nbc-narahenpita' => 'National Blood Center, Narahenpita',
		),
		'Kandy'   => array(
			'kandy' => 'Kandy',
		),
	);
} );
```

### Adjusting a single district

```php
add_filter( 'lccl_de_blood_banks_by_district', function ( $banks ) {
	$banks['Colombo']['new-facility'] = 'New Facility, Colombo';
	return $banks;
} );
```

### The flattened filter

`lccl_de_blood_banks` runs after the grouped list is reduced for a single
district. It receives the district as a second argument, and an empty string
when every bank is being requested at once.

```php
add_filter( 'lccl_de_blood_banks', function ( $banks, $district ) {
	if ( 'Colombo' === $district ) {
		unset( $banks['army-hospital'] );
	}
	return $banks;
}, 10, 2 );
```

## Districts

The 25 districts of Sri Lanka, using the district name as both key and label.
Every district currently has at least one blood bank.

## Changing labels only

Keep the existing keys when you only want different wording, otherwise any
already stored values stop resolving to a label:

```php
add_filter( 'lccl_de_contact_methods', function ( $methods ) {
	$methods['whatsapp'] = 'WhatsApp message';
	return $methods;
} );
```

## A note on stored values

Once submissions are being saved, these keys become historical data. Removing
or renaming a key does not rewrite rows already stored, so old records will
hold a value with no matching label. Prefer adding new keys over renaming
existing ones, and keep retired keys in the array if old submissions still need
to display correctly.
