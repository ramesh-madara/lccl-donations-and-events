<?php
/**
 * Shared Dialog e-SMS credentials.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array  $settings Current option values.
 * @var string $message  Flash code.
 * @var string $error    Flash error.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $settings ) || ! is_array( $settings ) ) {
	$settings = LCCL_DE_Settings::get();
}

$ready = ! empty( $settings['sms_ready'] );
?>
<nav class="lccl-prog__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'lccl-de' ); ?>">
	<span class="lccl-prog__crumbs-current"><?php esc_html_e( 'SMS', 'lccl-de' ); ?></span>
</nav>

<?php if ( 'saved' === $message ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'SMS settings saved.', 'lccl-de' ); ?></p></div>
<?php elseif ( 'error' === $message && ! empty( $error ) ) : ?>
	<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
<?php endif; ?>

<div class="lccl-prog__section">
	<h2 class="lccl-prog__panel-title"><?php esc_html_e( 'Dialog e-SMS', 'lccl-de' ); ?></h2>
	<p class="lccl-prog__hint">
		<?php esc_html_e( 'One Dialog login is used by every program. Turn SMS on or off for each program under Programs. The LCCL sender mask is applied by Dialog — do not put it in the message.', 'lccl-de' ); ?>
	</p>
	<p class="lccl-prog__hint">
		<?php
		echo $ready
			? esc_html__( 'Credentials are saved. Leave the password blank to keep the stored value.', 'lccl-de' )
			: esc_html__( 'No credentials are saved yet. Programs cannot send SMS until this tab is complete.', 'lccl-de' );
		?>
	</p>

	<form method="post" action="<?php echo esc_url( LCCL_DE_Admin_Programs::sms_url() ); ?>" data-lccl-sms-form novalidate>
		<?php wp_nonce_field( LCCL_DE_Settings::NONCE ); ?>
		<input type="hidden" name="lccl_de_sms_save" value="1">

		<p>
			<label for="lccl-de-sms-api-key"><strong><?php esc_html_e( 'SMS username', 'lccl-de' ); ?></strong></label><br>
			<input
				type="text"
				id="lccl-de-sms-api-key"
				name="sms_api_key"
				value="<?php echo esc_attr( isset( $settings['sms_api_key'] ) ? $settings['sms_api_key'] : '' ); ?>"
				autocomplete="off"
				required
			>
		</p>
		<p>
			<label for="lccl-de-sms-password"><strong><?php esc_html_e( 'SMS password', 'lccl-de' ); ?></strong></label><br>
			<input
				type="password"
				id="lccl-de-sms-password"
				name="sms_password"
				value=""
				autocomplete="new-password"
				<?php echo $ready ? '' : 'required'; ?>
				placeholder="<?php echo $ready ? esc_attr__( 'Saved. Leave blank to keep it.', 'lccl-de' ) : ''; ?>"
			>
		</p>
		<p class="lccl-prog__hint"><?php esc_html_e( 'Dialog e-SMS POST login. Username is the portal / API username (sometimes labelled API key).', 'lccl-de' ); ?></p>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save SMS settings', 'lccl-de' ); ?></button>
		</p>
	</form>
</div>
