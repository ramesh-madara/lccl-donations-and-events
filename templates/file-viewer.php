<?php
/**
 * Reusable signed-in file viewer.
 *
 * @package LCCL_Donations_And_Events
 *
 * @var array $file Viewer payload.
 */

defined( 'ABSPATH' ) || exit;

$error         = ! empty( $file['error'] ) ? (string) $file['error'] : '';
$name          = ! empty( $file['name'] ) ? (string) $file['name'] : __( 'File', 'lccl-de' );
$kind          = ! empty( $file['kind'] ) ? (string) $file['kind'] : '';
$raw_url       = ! empty( $file['raw_url'] ) ? (string) $file['raw_url'] : '';
$download_url  = ! empty( $file['download_url'] ) ? (string) $file['download_url'] : '';
$sign_in_url   = ! empty( $file['sign_in_url'] ) ? (string) $file['sign_in_url'] : '';
$is_image      = in_array( $kind, array( 'jpg', 'png' ), true );
$is_pdf        = 'pdf' === $kind;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( $name ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( LCCL_DE_URL . 'assets/css/lccl-de-file-viewer.css?ver=' . rawurlencode( LCCL_DE_VERSION ) ); ?>">
</head>
<body class="lccl-fv<?php echo $error ? ' lccl-fv--error' : ''; ?><?php echo $is_pdf ? ' lccl-fv--pdf' : ''; ?><?php echo $is_image ? ' lccl-fv--image' : ''; ?>">
	<header class="lccl-fv__bar">
		<div class="lccl-fv__identity">
			<?php if ( $kind ) : ?>
				<span class="lccl-fv__type" aria-hidden="true"><?php echo esc_html( strtoupper( $kind ) ); ?></span>
			<?php endif; ?>
			<h1 class="lccl-fv__title"><?php echo esc_html( $name ); ?></h1>
		</div>
		<div class="lccl-fv__tools">
			<?php if ( $is_image ) : ?>
				<div class="lccl-fv__zoom" data-zoom-tools>
					<button class="lccl-fv__tool" type="button" data-zoom="out" aria-label="<?php esc_attr_e( 'Zoom out', 'lccl-de' ); ?>">−</button>
					<span class="lccl-fv__zoom-label" data-zoom-label>100%</span>
					<button class="lccl-fv__tool" type="button" data-zoom="in" aria-label="<?php esc_attr_e( 'Zoom in', 'lccl-de' ); ?>">+</button>
					<button class="lccl-fv__tool" type="button" data-zoom="reset"><?php esc_html_e( 'Reset', 'lccl-de' ); ?></button>
				</div>
			<?php endif; ?>
			<?php if ( $download_url ) : ?>
				<a class="lccl-fv__download" href="<?php echo esc_url( $download_url ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
						<path d="M12 3v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
						<path d="M7 11l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M4 21h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
					</svg>
					<span><?php esc_html_e( 'Download', 'lccl-de' ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</header>

	<main class="lccl-fv__stage" data-stage>
		<?php if ( $error ) : ?>
			<div class="lccl-fv__empty">
				<p><?php echo esc_html( $error ); ?></p>
				<?php if ( $sign_in_url ) : ?>
					<p><a href="<?php echo esc_url( $sign_in_url ); ?>"><?php esc_html_e( 'Sign in', 'lccl-de' ); ?></a></p>
				<?php endif; ?>
			</div>
		<?php elseif ( $is_image ) : ?>
			<img class="lccl-fv__image" data-file-image src="<?php echo esc_url( $raw_url ); ?>" alt="<?php echo esc_attr( $name ); ?>">
		<?php elseif ( $is_pdf ) : ?>
			<iframe class="lccl-fv__frame" src="<?php echo esc_url( $raw_url ); ?>" title="<?php echo esc_attr( $name ); ?>"></iframe>
		<?php else : ?>
			<div class="lccl-fv__empty">
				<p><?php esc_html_e( 'This file type cannot be previewed.', 'lccl-de' ); ?></p>
			</div>
		<?php endif; ?>
	</main>

	<?php if ( $is_image ) : ?>
		<script src="<?php echo esc_url( LCCL_DE_URL . 'assets/js/lccl-de-file-viewer.js?ver=' . rawurlencode( LCCL_DE_VERSION ) ); ?>"></script>
	<?php endif; ?>
</body>
</html>
