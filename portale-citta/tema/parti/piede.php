<?php
/** Piè di pagina. Variabili attese: $citta (facoltativa), $altre. */
defined( 'PC_AVVIO' ) || exit;
$imp      = impostazioni();
$altre    = isset( $altre ) ? $altre : altre_citta( empty( $citta ) ? '' : $citta['id'] );
$telefono = empty( $citta ) ? $imp['telefono'] : contatto( $citta, 'telefono' );
$email    = empty( $citta ) ? $imp['email'] : contatto( $citta, 'email' );
$js_extra = isset( $js_extra ) ? $js_extra : '';
?>
<footer class="glp-bottombar">
	<div class="glp-bottombar__inner">
		<div class="glp-bottombar__col">
			<p class="glp-bottombar__name"><?php echo e( $imp['brand'] ); ?></p>
			<?php if ( ! empty( $citta ) && ! vuoto( $citta['indirizzo'] ) ) : ?>
				<p><?php echo e( $citta['indirizzo'] . ', ' . trim( $citta['cap'] . ' ' . $citta['nome'] ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! vuoto( $telefono ) ) : ?>
				<p><a href="tel:<?php echo e( tel( $telefono ) ); ?>"><?php echo e( $telefono ); ?></a></p>
			<?php endif; ?>
			<?php if ( ! vuoto( $email ) ) : ?>
				<p><a href="mailto:<?php echo e( $email ); ?>"><?php echo e( $email ); ?></a></p>
			<?php endif; ?>
			<?php if ( ! vuoto( $imp['piva'] ) ) : ?>
				<p>P. IVA <?php echo e( $imp['piva'] ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $altre ) ) : ?>
			<div class="glp-bottombar__col">
				<p class="glp-bottombar__label">Dove operiamo</p>
				<ul>
					<?php foreach ( array_slice( $altre, 0, 12 ) as $a ) : ?>
						<li><a href="<?php echo e_url( $a['url'] ); ?>"><?php echo e( $a['nome'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="glp-bottombar__col">
			<p class="glp-bottombar__label">Sito</p>
			<ul>
				<?php if ( ! vuoto( $imp['sito_principale'] ) ) : ?>
					<li><a href="<?php echo e_url( $imp['sito_principale'] ); ?>">Torna al sito principale</a></li>
				<?php endif; ?>
				<li><a href="<?php echo e( base_url() ); ?>/">Tutte le città</a></li>
				<?php if ( ! vuoto( $imp['privacy_url'] ) ) : ?>
					<li><a href="<?php echo e_url( $imp['privacy_url'] ); ?>">Privacy Policy</a></li>
				<?php endif; ?>
				<?php if ( ! vuoto( $imp['cookie_url'] ) ) : ?>
					<li><a href="<?php echo e_url( $imp['cookie_url'] ); ?>">Cookie Policy</a></li>
				<?php endif; ?>
			</ul>
		</div>
	</div>

	<p class="glp-bottombar__copy">© <?php echo e( date( 'Y' ) ); ?> <?php echo e( $imp['brand'] ); ?></p>
</footer>

<script src="<?php echo e( base_url() ); ?>/tema/script.js?v=<?php echo e( versione_asset( 'script.js' ) ); ?>"></script>
<?php if ( ! vuoto( $imp['js_globale'] ) ) : ?>
<script><?php echo $imp['js_globale']; // JavaScript inserito dall'amministratore. ?></script>
<?php endif; ?>
<?php if ( ! vuoto( $js_extra ) ) : ?>
<script><?php echo $js_extra; // JavaScript della città o della pagina. ?></script>
<?php endif; ?>
</body>
</html>
