<?php
/**
 * Membership fee payment result page.
 *
 * Rendered after MPGS redirects the browser back with ?lccl_mpgs_return=1.
 * Shows a success, failure, cancelled, or error state based on $result['status'].
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array $result  From LCCL_DE_Membership_Form::handle_callback() {
 *     @type string $status        'paid'|'failed'|'cancelled'|'error'
 *     @type string $order_ref
 *     @type string $receipt
 *     @type float  $amount
 *     @type string $member_name
 *     @type string $error_message
 * }
 * @var array $atts    Shortcode attributes.
 */

defined( 'ABSPATH' ) || exit;

$status  = isset( $result['status'] ) ? $result['status'] : 'error';
$is_paid = 'paid' === $status;
$form_url = remove_query_arg(
	array(
		LCCL_DE_Membership_Form::QA_RETURN,
		LCCL_DE_Membership_Form::QA_ORDER_REF,
		'resultIndicator',
	),
	get_permalink()
);
?>
<div class="lccl-bdf lccl-bdf--membership lccl-mf--result">

	<?php if ( $is_paid ) : ?>

		<div class="lccl-mf__result lccl-mf__result--success" role="alert">

			<div class="lccl-mf__result-icon lccl-mf__result-icon--success" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="#2e7d32" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="32" cy="32" r="29"/>
					<polyline points="20 33 29 42 45 24"/>
				</svg>
			</div>

			<h2 class="lccl-mf__result-title"><?php esc_html_e( 'Payment Successful!', 'lccl-de' ); ?></h2>
			<p class="lccl-mf__result-lead">
				<?php
				printf(
					/* translators: %s: member name */
					esc_html__( 'Thank you, %s. Your annual membership fee has been received.', 'lccl-de' ),
					'<strong>' . esc_html( $result['member_name'] ) . '</strong>'
				);
				?>
			</p>

			<dl class="lccl-mf__result-details">
				<?php if ( ! empty( $result['receipt'] ) ) : ?>
					<div class="lccl-mf__result-row">
						<dt><?php esc_html_e( 'Gateway Receipt', 'lccl-de' ); ?></dt>
						<dd><code><?php echo esc_html( $result['receipt'] ); ?></code></dd>
					</div>
				<?php endif; ?>
				<div class="lccl-mf__result-row">
					<dt><?php esc_html_e( 'Payment Reference', 'lccl-de' ); ?></dt>
					<dd><code><?php echo esc_html( $result['order_ref'] ); ?></code></dd>
				</div>
				<div class="lccl-mf__result-row">
					<dt><?php esc_html_e( 'Amount Paid', 'lccl-de' ); ?></dt>
					<dd><strong><?php echo esc_html( 'LKR ' . number_format( (float) $result['amount'], 2 ) ); ?></strong></dd>
				</div>
			</dl>

			<p class="lccl-mf__result-note">
				<?php esc_html_e( 'A confirmation email has been sent to your registered email address. Please save your receipt number for your records.', 'lccl-de' ); ?>
			</p>

		</div>

	<?php elseif ( 'cancelled' === $status ) : ?>

		<div class="lccl-mf__result lccl-mf__result--warning" role="alert">

			<div class="lccl-mf__result-icon lccl-mf__result-icon--warning" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="#f57c00" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="32" cy="32" r="29"/>
					<line x1="32" y1="20" x2="32" y2="36"/>
					<circle cx="32" cy="44" r="1.5" fill="#f57c00"/>
				</svg>
			</div>

			<h2 class="lccl-mf__result-title"><?php esc_html_e( 'Payment Cancelled', 'lccl-de' ); ?></h2>
			<p class="lccl-mf__result-lead">
				<?php esc_html_e( 'You cancelled the payment. No charge has been made.', 'lccl-de' ); ?>
			</p>

			<a href="<?php echo esc_url( $form_url ); ?>" class="lccl-bdf__submit lccl-df__submit lccl-mf__result-btn">
				<?php esc_html_e( 'Try Again', 'lccl-de' ); ?>
			</a>

		</div>

	<?php else : ?>

		<div class="lccl-mf__result lccl-mf__result--error" role="alert">

			<div class="lccl-mf__result-icon lccl-mf__result-icon--error" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="#c62828" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="32" cy="32" r="29"/>
					<line x1="22" y1="22" x2="42" y2="42"/>
					<line x1="42" y1="22" x2="22" y2="42"/>
				</svg>
			</div>

			<h2 class="lccl-mf__result-title"><?php esc_html_e( 'Payment Failed', 'lccl-de' ); ?></h2>
			<p class="lccl-mf__result-lead">
				<?php esc_html_e( 'Your payment could not be completed. No charge has been made.', 'lccl-de' ); ?>
			</p>

			<?php if ( ! empty( $result['error_message'] ) ) : ?>
				<p class="lccl-mf__result-error-detail">
					<?php echo esc_html( $result['error_message'] ); ?>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $result['order_ref'] ) ) : ?>
				<p class="lccl-mf__result-ref">
					<?php
					printf(
						/* translators: %s: order reference */
						esc_html__( 'Reference: %s', 'lccl-de' ),
						'<code>' . esc_html( $result['order_ref'] ) . '</code>'
					);
					?>
				</p>
			<?php endif; ?>

			<a href="<?php echo esc_url( $form_url ); ?>" class="lccl-bdf__submit lccl-df__submit lccl-mf__result-btn">
				<?php esc_html_e( 'Try Again', 'lccl-de' ); ?>
			</a>

		</div>

	<?php endif; ?>

</div>
