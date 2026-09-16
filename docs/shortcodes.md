# Shortcode reference

## `[lccl_blood_donor_form]`

Renders the blood donation programme registration form.

```
[lccl_blood_donor_form]
[lccl_blood_donor_form title="Donor Sign Up" intro="Tell us how to reach you."]
```

| Attribute | Default | Notes |
| --- | --- | --- |
| `title` | `Registration Form` | Rendered as an `<h2>`, uppercased by CSS |
| `intro` | See below | Pass an empty string to hide the paragraph |

Default intro text: *"Please provide the information below so we can identify a convenient blood bank or Lions blood donation campaign in your area."*

The heading block also includes the required-fields note: *"Fields marked with an * are required"*.

The form has no `max-width` and no padding of its own, so it fills whatever
column or `.wpb_wrapper` it is dropped into.

### Fields

Required fields are marked with a red asterisk and carry the HTML `required`
attribute. These `name` values are the contract the submission handler and
database schema will be built against, so avoid renaming them casually.

| Field name | Control | Required | Notes |
| --- | --- | --- | --- |
| `first_name` | text | yes | `autocomplete="given-name"` |
| `last_name` | text | yes | `autocomplete="family-name"` |
| `address` | text | yes | Full width row |
| `city` | text | yes | |
| `postal_code` | text | no | `inputmode="numeric"` |
| `email` | email | no | |
| `phone` | tel | yes | Placeholder `+94 71 0000000` |
| `district` | select | yes | 25 districts of Sri Lanka |
| `blood_bank` | select | yes | Depends on `district`, see below |
| `donation_preference` | select | no | Blood bank / campaign / either |
| `donated_before` | select | no | Yes / no / not sure |
| `contact_method` | select | yes | Phone / WhatsApp / SMS / email |
| `notify_campaigns` | checkbox | no | Value `1` when ticked |
| `consent` | checkbox | yes | Value `1`, privacy consent |

Every select starts with a blank `<option>` so no value is preselected.

### The district and blood bank dependency

Blood banks belong to a district, so the two fields are linked.

- The blood bank field starts locked, showing only *"Select your district
  first"*.
- Trying to open it while locked cancels the interaction, reveals an inline
  message, and moves focus to the district field.
- Choosing a district reveals only that district's banks.
- Changing the district **always clears** any bank already chosen, because the
  previous choice belongs to a different district.
- Clearing the district locks the field again.

Implemented in `assets/js/lccl-de-blood-donor-form.js`.

**Without JavaScript the field still works.** The markup renders every
district's banks as `<optgroup>` elements grouped by district name; the script
detaches them on load and reinserts only the matching group. A visitor with
scripting disabled sees one long grouped list rather than a broken field.

Because the browser side can be bypassed, the submission handler must confirm
the pairing server side:

```php
LCCL_DE_Blood_Donor_Form::is_valid_blood_bank( $bank_key, $district );
```

### Security

The form posts to itself and already includes a nonce:

```php
wp_nonce_field( 'lccl_de_blood_donor_register', 'lccl_de_nonce' );
```

Verify it with `wp_verify_nonce( $_POST['lccl_de_nonce'], 'lccl_de_blood_donor_register' )`
when the handler is written.

### Repopulating after validation errors

`templates/blood-donor-form.php` already accepts two variables from the
render method:

- `$values` — previously submitted values keyed by field name
- `$errors` — validation errors keyed by field name

`$values` is wired up and will repopulate inputs, selects, and checkboxes.
`$errors` is passed but not yet displayed; error message markup still needs to
be added when validation is implemented.

## `[lccl_hello]`

Scaffolding card used to confirm the plugin is active and rendering. Shows the
shortcode tag, plugin version, and whether the visitor is signed in.

```
[lccl_hello]
[lccl_hello title="Smoke test" message="Custom text"]
```

| Attribute | Default |
| --- | --- |
| `title` | `Hello from LCCL` |
| `message` | `The plugin is active and this UI is rendering from a shortcode.` |

Safe to remove once real features replace it.
