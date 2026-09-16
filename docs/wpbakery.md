# WPBakery integration

The site builds its pages with WPBakery Page Builder (`js_composer`), version
**9.0.1** at the time of writing. The active theme is the Kalium child theme
`kalium-child-construction`.

## Why shortcodes work without any integration

WPBakery is a shortcode engine. Every element it produces (`[vc_row]`,
`[vc_column]`, and the rest) is an ordinary WordPress shortcode, and page
content still runs through `the_content`. Any shortcode this plugin registers
therefore renders on a WPBakery page with no special handling — drop it into a
**Text Block** or **Raw HTML** element and it works.

`vc_map()` is the optional layer on top. It does **not** handle rendering; it
only declares the settings form shown in the builder. Rendering stays in the
normal `add_shortcode` callback.

## How elements are registered here

Each feature class registers both in its `init()`:

```php
add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
add_action( 'vc_before_init', array( __CLASS__, 'map' ) );
```

`vc_before_init` is the correct hook for `vc_map()`. The `map()` method guards
with `function_exists( 'vc_map' )` so the plugin does not fatal if WPBakery is
ever deactivated — the shortcode keeps working on its own.

## Registered elements

Both appear in the builder under the **LCCL** category.

| Element name | Shortcode base | Params |
| --- | --- | --- |
| LCCL Blood Donor Registration | `lccl_blood_donor_form` | `title`, `intro` |
| LCCL Hello | `lccl_hello` | `title`, `message` |

## Adding another element

1. Register the shortcode as usual with `add_shortcode`.
2. Call `vc_map()` on `vc_before_init`.
3. Set `base` to the exact shortcode tag — this is what links the two.
4. Set `category` to `LCCL` so it groups with the others.
5. Give each `params` entry a `param_name` matching a `shortcode_atts` key.

```php
vc_map( array(
	'name'     => __( 'LCCL Example', 'lccl-de' ),
	'base'     => 'lccl_example',
	'category' => __( 'LCCL', 'lccl-de' ),
	'icon'     => 'icon-wpb-ui-separator',
	'params'   => array(
		array(
			'type'        => 'textfield',
			'heading'     => __( 'Title', 'lccl-de' ),
			'param_name'  => 'title',
			'value'       => '',
			'admin_label' => true,
		),
	),
) );
```

`admin_label => true` surfaces the value on the element tile in the builder,
which makes stacked elements easier to tell apart.

## Useful param types

`textfield`, `textarea`, `textarea_html`, `dropdown`, `checkbox`,
`colorpicker`, `attach_image`, `vc_link`, `css_editor`.

## Gotchas

- Changing a `base` orphans any existing usage on published pages, because the
  old shortcode tag stays in the post content.
- WPBakery caches its element list. If a new element does not appear, do a hard
  refresh of the editor.
- Keep default values in `vc_map` params matching the `shortcode_atts`
  defaults, otherwise output differs between manual shortcode use and builder
  use.
