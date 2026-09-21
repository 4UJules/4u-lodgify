<?php
/**
 * Ecran « Webhooks Lodgify » : etat des abonnements, journal des evenements,
 * delai de propagation mesure.
 *
 * @package FourU_Lodgify
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FourU_Lodgify_Page_Webhooks {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_fouru_wh_abonner', array( $this, 'abonner' ) );
		add_action( 'admin_post_fouru_wh_desabonner', array( $this, 'desabonner' ) );
	}

	public function menu() {
		add_submenu_page(
			'lodgify-comptes',
			__( 'Webhooks Lodgify', '4u-lodgify' ),
			__( 'Webhooks', '4u-lodgify' ),
			'manage_options',
			'fouru-lodgify-webhooks',
			array( $this, 'rendu' )
		);
	}

	private function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'fouru-lodgify-webhooks' ), $args ), admin_url( 'admin.php' ) );
	}

	public function abonner() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( '-1' ); }
		check_admin_referer( 'fouru_wh' );
		$w = sanitize_text_field( wp_unslash( $_GET['website_id'] ?? '' ) );
		$cle = FourU_Lodgify_Comptes::cle_api( $w );
		$ok = 0; $ko = array();
		foreach ( FourU_Lodgify_Webhooks::evenements() as $e ) {
			$r = FourU_Lodgify_Webhooks::abonner( $cle, $e, $w );
			if ( ! is_wp_error( $r ) && $r['code'] >= 200 && $r['code'] < 300 ) { $ok++; }
			else { $ko[] = $e . ' (' . ( is_wp_error( $r ) ? $r->get_error_message() : $r['code'] ) . ')'; }
		}
		wp_safe_redirect( $this->url( array( 'message' => sprintf( '%d abonnement(s) posé(s).', $ok ) . ( $ko ? ' Échecs : ' . implode( ', ', $ko ) : '' ) ) ) );
		exit;
	}

	public function desabonner() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( '-1' ); }
		check_admin_referer( 'fouru_wh' );
		$w  = sanitize_text_field( wp_unslash( $_GET['website_id'] ?? '' ) );
		$id = sanitize_text_field( wp_unslash( $_GET['abonnement'] ?? '' ) );
		FourU_Lodgify_Webhooks::desabonner( FourU_Lodgify_Comptes::cle_api( $w ), $id );
		wp_safe_redirect( $this->url( array( 'message' => 'Abonnement retiré.' ) ) );
		exit;
	}

	public function rendu() {
		global $wpdb;
		$t = $wpdb->prefix . FourU_Lodgify_Webhooks::TABLE;
		$journal = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC LIMIT 40" );
		$comptes = class_exists( 'FourU_Lodgify_Comptes' ) ? FourU_Lodgify_Comptes::tous() : array(); ?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Webhooks Lodgify', '4u-lodgify' ); ?></h1>
			<?php if ( ! empty( $_GET['message'] ) ) : ?>
				<div class="notice notice-info"><p><?php echo esc_html( wp_unslash( $_GET['message'] ) ); ?></p></div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Lodgify ne signe pas ses appels. L\'URL de réception porte donc un jeton secret : ne la diffusez pas.', '4u-lodgify' ); ?></p>
			<p><code style="word-break:break-all"><?php echo esc_html( FourU_Lodgify_Webhooks::url_reception() ); ?></code></p>

			<h2><?php esc_html_e( 'Abonnements par compte', '4u-lodgify' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Compte', '4u-lodgify' ); ?></th>
					<th><?php esc_html_e( 'Abonnements chez Lodgify', '4u-lodgify' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $comptes as $c ) :
					if ( ! $c->actif ) { continue; }
					$liste = FourU_Lodgify_Webhooks::lister( FourU_Lodgify_Comptes::cle_api( $c->website_id ) );
					$items = is_wp_error( $liste ) ? array() : ( $liste['items'] ?? ( is_array( $liste ) ? $liste : array() ) ); ?>
					<tr>
						<td><strong><?php echo esc_html( $c->nom ); ?></strong><br><code><?php echo esc_html( $c->website_id ); ?></code></td>
						<td>
							<?php if ( is_wp_error( $liste ) ) : ?>
								<span style="color:#b32d2e"><?php echo esc_html( $liste->get_error_message() ); ?></span>
							<?php elseif ( ! $items ) : ?>
								<em><?php esc_html_e( 'aucun', '4u-lodgify' ); ?></em>
							<?php else : foreach ( $items as $i ) : ?>
								<div><?php echo esc_html( $i['event'] ?? '?' ); ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fouru_wh_desabonner&website_id=' . rawurlencode( $c->website_id ) . '&abonnement=' . rawurlencode( $i['id'] ?? '' ) ), 'fouru_wh' ) ); ?>"><?php esc_html_e( 'retirer', '4u-lodgify' ); ?></a>
								</div>
							<?php endforeach; endif; ?>
						</td>
						<td><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fouru_wh_abonner&website_id=' . rawurlencode( $c->website_id ) ), 'fouru_wh' ) ); ?>"><?php esc_html_e( 'Abonner ce compte', '4u-lodgify' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Derniers événements reçus', '4u-lodgify' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Reçu le', '4u-lodgify' ); ?></th>
					<th><?php esc_html_e( 'Événement', '4u-lodgify' ); ?></th>
					<th><?php esc_html_e( 'Bien', '4u-lodgify' ); ?></th>
					<th><?php esc_html_e( 'Résultat', '4u-lodgify' ); ?></th>
					<th><?php esc_html_e( 'Durée', '4u-lodgify' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $journal ) : ?>
					<tr><td colspan="5"><em><?php esc_html_e( 'Aucun événement reçu pour l\'instant.', '4u-lodgify' ); ?></em></td></tr>
				<?php else : foreach ( $journal as $j ) : ?>
					<tr>
						<td><?php echo esc_html( $j->recu_le ); ?></td>
						<td><?php echo esc_html( $j->evenement ?: '—' ); ?></td>
						<td><?php echo esc_html( $j->property_id ?: '—' ); ?></td>
						<td><?php echo esc_html( $j->resultat ?: '—' ); ?></td>
						<td><?php echo esc_html( $j->duree_ms ); ?> ms</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
