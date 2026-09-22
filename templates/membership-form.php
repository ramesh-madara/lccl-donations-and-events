<?php
/**
 * Sample annual membership fee markup. No payment or database write.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array $atts   Shortcode attributes.
 * @var array $values Previously entered values, keyed by field name.
 * @var array $fees   Calculated sample fee breakdown.
 */

defined( 'ABSPATH' ) || exit;

$val = static function ( $key ) use ( $values ) {
	return isset( $values[ $key ] ) ? $values[ $key ] : '';
};

$type  = $val( 'membership_type' );
$count = $val( 'family_count' );
$count = '' !== $count ? $count : '2';
?>
<div class="lccl-bdf lccl-bdf--membership">
	<form
		class="lccl-bdf__form"
		method="post"
		action="#"
		data-rate="<?php echo esc_attr( (string) LCCL_DE_Membership_Form::RATE ); ?>"
		data-principal-usd="<?php echo esc_attr( (string) LCCL_DE_Membership_Form::PRINCIPAL_USD ); ?>"
		data-family-usd="<?php echo esc_attr( (string) LCCL_DE_Membership_Form::FAMILY_USD ); ?>"
		data-district="<?php echo esc_attr( (string) LCCL_DE_Membership_Form::DISTRICT_LKR ); ?>"
		data-club="<?php echo esc_attr( (string) LCCL_DE_Membership_Form::CLUB_LKR ); ?>"
		novalidate
	>
		<?php if ( '' !== $atts['title'] || '' !== $atts['intro'] ) : ?>
			<header class="lccl-bdf__header">
				<?php if ( '' !== $atts['title'] ) : ?>
					<h2 class="lccl-bdf__title"><?php echo esc_html( $atts['title'] ); ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $atts['intro'] ) : ?>
					<p class="lccl-bdf__intro"><?php echo esc_html( $atts['intro'] ); ?></p>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<div class="lccl-mf">
			<div class="lccl-mf__section">
				<h3 class="lccl-mf__section-title"><?php esc_html_e( 'Membership Type', 'lccl-de' ); ?></h3>
				<div class="lccl-bdf__field lccl-mf__field--first">
					<label class="lccl-bdf__label" for="lccl-mf-type">
						<?php esc_html_e( 'Select Membership Type', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
					</label>
					<select class="lccl-bdf__select" id="lccl-mf-type" name="membership_type">
						<option value=""><?php esc_html_e( 'Select membership type', 'lccl-de' ); ?></option>
						<?php foreach ( LCCL_DE_Membership_Form::types() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="lccl-mf__family" data-lccl-family<?php echo 'family' === $type ? '' : ' hidden'; ?>>
					<div class="lccl-bdf__field lccl-mf__field--first">
						<label class="lccl-bdf__label" for="lccl-mf-count">
							<?php esc_html_e( 'Number of Family Members (Including Main Member)', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<select class="lccl-bdf__select" id="lccl-mf-count" name="family_count">
							<?php foreach ( LCCL_DE_Membership_Form::family_counts() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $count, $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<p class="lccl-mf__note">
						<?php esc_html_e( 'The main member is responsible for the payment of the annual membership fees for all family members included in the membership.', 'lccl-de' ); ?>
					</p>
				</div>
			</div>

			<div class="lccl-mf__section">
				<h3 class="lccl-mf__section-title"><?php esc_html_e( 'Member Information', 'lccl-de' ); ?></h3>
				<div class="lccl-df__pair">
					<div class="lccl-bdf__field lccl-mf__field--first">
						<label class="lccl-bdf__label" for="lccl-mf-first-name">
							<?php esc_html_e( 'First Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="text"
							id="lccl-mf-first-name"
							name="first_name"
							value="<?php echo esc_attr( $val( 'first_name' ) ); ?>"
							placeholder="<?php esc_attr_e( 'Saman', 'lccl-de' ); ?>"
							autocomplete="given-name"
							maxlength="100"
						>
					</div>
					<div class="lccl-bdf__field lccl-mf__field--first">
						<label class="lccl-bdf__label" for="lccl-mf-last-name">
							<?php esc_html_e( 'Last Name', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="text"
							id="lccl-mf-last-name"
							name="last_name"
							value="<?php echo esc_attr( $val( 'last_name' ) ); ?>"
							placeholder="<?php esc_attr_e( 'Perera', 'lccl-de' ); ?>"
							autocomplete="family-name"
							maxlength="100"
						>
					</div>
				</div>

				<div class="lccl-df__pair">
					<div class="lccl-bdf__field">
						<label class="lccl-bdf__label" for="lccl-mf-email">
							<?php esc_html_e( 'Email', 'lccl-de' ); ?> <span class="lccl-bdf__req">*</span>
						</label>
						<input
							class="lccl-bdf__input"
							type="email"
							id="lccl-mf-email"
							name="email"
							value="<?php echo esc_attr( $val( 'email' ) ); ?>"
							placeholder="<?php esc_attr_e( 'name@example.com', 'lccl-de' ); ?>"
							autocomplete="email"
							maxlength="191"
						>
					</div>
					<div class="lccl-bdf__field">
						<label class="lccl-bdf__label" for="lccl-mf-phone">
							<?php esc_html_e( 'Mobile / WhatsApp Number', 'lccl-de' ); ?>
						</label>
						<input
							class="lccl-bdf__input"
							type="tel"
							id="lccl-mf-phone"
							name="phone"
							value="<?php echo esc_attr( $val( 'phone' ) ); ?>"
							placeholder="<?php esc_attr_e( '07X XXX XXXX', 'lccl-de' ); ?>"
							inputmode="tel"
							autocomplete="tel"
							maxlength="12"
						>
					</div>
				</div>
			</div>

			<div class="lccl-mf__section">
				<h3 class="lccl-mf__section-title"><?php esc_html_e( 'Fee Calculation', 'lccl-de' ); ?></h3>
				<div class="lccl-mf__info">
					<p>
						<strong><?php esc_html_e( 'Exchange Rate:', 'lccl-de' ); ?></strong>
						<?php
						printf(
							/* translators: %s: formatted LKR rate */
							esc_html__( '1 USD = LKR %s', 'lccl-de' ),
							'<span data-lccl-rate>' . esc_html( LCCL_DE_Membership_Form::format_rate( $fees['rate'] ) ) . '</span>'
						);
						?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Main Member Fee:', 'lccl-de' ); ?></strong>
						<?php esc_html_e( 'USD 50.00', 'lccl-de' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Additional Family Member Fee:', 'lccl-de' ); ?></strong>
						<?php esc_html_e( 'USD 25.00 per additional family member', 'lccl-de' ); ?>
					</p>
				</div>

				<table class="lccl-mf__table">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Lions International Membership Fee', 'lccl-de' ); ?></td>
							<td data-lccl-intl><?php echo esc_html( LCCL_DE_Membership_Form::format_lkr( $fees['international_main'] ) ); ?></td>
						</tr>
						<tr data-lccl-family-line<?php echo ! empty( $fees['is_family'] ) ? '' : ' hidden'; ?>>
							<td><?php esc_html_e( 'Additional Family Member Fee', 'lccl-de' ); ?></td>
							<td data-lccl-family-fee><?php echo esc_html( LCCL_DE_Membership_Form::format_lkr( $fees['family_fee'] ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'District Payment', 'lccl-de' ); ?></td>
							<td data-lccl-district><?php echo esc_html( LCCL_DE_Membership_Form::format_lkr( $fees['district'] ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Club Payment', 'lccl-de' ); ?></td>
							<td data-lccl-club><?php echo esc_html( LCCL_DE_Membership_Form::format_lkr( $fees['club'] ) ); ?></td>
						</tr>
					</tbody>
				</table>

				<div class="lccl-df__total">
					<div class="lccl-df__total-label">
						<span><?php esc_html_e( 'Total Amount', 'lccl-de' ); ?></span>
					</div>
					<div class="lccl-mf__total-value" aria-live="polite">
						<span><?php esc_html_e( 'LKR', 'lccl-de' ); ?></span>
						<span data-lccl-total><?php echo esc_html( number_format( (float) $fees['total'], 2, '.', ',' ) ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<button class="lccl-bdf__submit lccl-df__submit" type="submit">
			<?php esc_html_e( 'Pay Membership Fee', 'lccl-de' ); ?>
		</button>
		<p class="lccl-mf__small">
			<?php esc_html_e( 'The exchange rate and District/Club payment amounts should be updated according to the current approved rates and fees.', 'lccl-de' ); ?>
		</p>
	</form>
</div>
