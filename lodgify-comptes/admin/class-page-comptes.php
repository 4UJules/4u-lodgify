<?php
/**
 * Ecran d'administration « Comptes Lodgify ».
 *
 * @package FourU_Lodgify
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FourU_Lodgify_Page_Comptes {

	const SLUG = 'fouru-lodgify-comptes';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 9999 );
		add_action( 'admin_post_fouru_compte_save', array( $this, 'enregistrer' ) );
		add_action( 'admin_post_fouru_compte_delete', array( $this, 'supprimer' ) );
		add_action( 'admin_post_fouru_biens_save', array( $this, 'enregistrer_biens' ) );
		add_action( 'wp_ajax_fouru_tester_compte', array( $this, 'ajax_tester' ) );
		add_action( 'wp_ajax_fouru_rafraichir_biens', array( $this, 'ajax_biens' ) );
		add_action( 'wp_ajax_fouru_suggestions', array( $this, 'ajax_suggestions' ) );
		add_action( 'wp_ajax_fouru_associer', array( $this, 'ajax_associer' ) );
		add_action( 'wp_ajax_fouru_dissocier', array( $this, 'ajax_dissocier' ) );
		add_action( 'wp_ajax_fouru_chercher_biens', array( $this, 'ajax_chercher_biens' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu() {
		add_menu_page(
			__( 'Comptes Lodgify', '4u-lodgify' ),
			__( 'Lodgify', '4u-lodgify' ),
			'manage_options',
			self::SLUG,
			array( $this, 'rendu' ),
			'dashicons-calendar-alt',
			58
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( $hook, self::SLUG ) ) { return; }
		wp_enqueue_style( 'fouru-lodgify-admin', LODGIFY_COMPTES_URL . 'admin/assets/admin.css', array(), LODGIFY_COMPTES_VERSION );
		wp_enqueue_script( 'fouru-lodgify-admin', LODGIFY_COMPTES_URL . 'admin/assets/admin.js', array( 'jquery' ), LODGIFY_COMPTES_VERSION, true );
		wp_localize_script( 'fouru-lodgify-admin', 'fouruLodgify', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fouru_lodgify' ),
			'i18n'    => array(
				'test'      => __( 'Test en cours…', '4u-lodgify' ),
				'biens'     => __( 'Lecture des biens…', '4u-lodgify' ),
				'confirmer' => __( 'Supprimer ce compte ? Les biens associés seront retirés de cette liste. Aucune donnée Lodgify n\'est touchée.', '4u-lodgify' ),
				'chercher'  => __( 'Recherche de la fiche correspondante…', '4u-lodgify' ),
				'associer'  => __( 'Associer', '4u-lodgify' ),
				'aucune'    => __( 'Aucune fiche proche trouvée.', '4u-lodgify' ),
			),
		) );
	}

	/* ------------------------------------------------------------------ */

	private function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public function rendu() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$edit = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';
		$compte = $edit ? FourU_Lodgify_Comptes::par_website( $edit ) : null;
		$comptes = FourU_Lodgify_Comptes::tous();
		?>
		<div class="wrap fouru-lodgify">
			<h1><?php esc_html_e( 'Comptes Lodgify', '4u-lodgify' ); ?></h1>

			<?php if ( isset( $_GET['message'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php
					echo esc_html( sanitize_text_field( wp_unslash( $_GET['message'] ) ) );
				?></p></div>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( "Un compte = un site Lodgify. La clé API est chiffrée avec les sels de ce WordPress : elle n'est jamais réaffichée, et une copie de la base seule ne permet pas de la lire.", '4u-lodgify' ); ?>
			</p>

			<h2><?php echo $compte ? esc_html__( 'Modifier le compte', '4u-lodgify' ) : esc_html__( 'Ajouter un compte', '4u-lodgify' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fouru-form">
				<?php wp_nonce_field( 'fouru_compte_save' ); ?>
				<input type="hidden" name="action" value="fouru_compte_save">
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="nom"><?php esc_html_e( 'Nom', '4u-lodgify' ); ?></label></th>
						<td><input name="nom" id="nom" type="text" class="regular-text"
							value="<?php echo esc_attr( $compte->nom ?? '' ); ?>" placeholder="The Hills"></td>
					</tr>
					<tr>
						<th><label for="website_id"><?php esc_html_e( 'Identifiant de site (website_id)', '4u-lodgify' ); ?></label></th>
						<td><input name="website_id" id="website_id" type="text" class="regular-text"
							value="<?php echo esc_attr( $compte->website_id ?? '' ); ?>"
							<?php echo $compte ? 'readonly' : ''; ?> required placeholder="453125">
							<?php if ( $compte ) : ?><p class="description"><?php esc_html_e( "Non modifiable : c'est la clé du compte.", '4u-lodgify' ); ?></p><?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="checkout_slug"><?php esc_html_e( 'Slug de réservation', '4u-lodgify' ); ?></label></th>
						<td><input name="checkout_slug" id="checkout_slug" type="text" class="regular-text"
							value="<?php echo esc_attr( $compte->checkout_slug ?? '' ); ?>" placeholder="thehills">
							<p class="description">checkout.lodgify.com/<strong>slug</strong>/{bien}</p></td>
					</tr>
					<tr>
						<th><label for="cle_api"><?php esc_html_e( 'Clé API', '4u-lodgify' ); ?></label></th>
						<td>
							<input name="cle_api" id="cle_api" type="password" class="large-text" autocomplete="off"
								placeholder="<?php echo $compte && $compte->cle_chiffree
									? esc_attr__( 'Laisser vide pour conserver la clé actuelle', '4u-lodgify' )
									: esc_attr__( 'Collez la clé API Lodgify', '4u-lodgify' ); ?>">
							<?php if ( $compte && $compte->cle_chiffree ) : ?>
								<p class="description"><?php esc_html_e( 'Clé enregistrée :', '4u-lodgify' ); ?>
									<code><?php echo esc_html( FourU_Lodgify_Comptes::masquer( FourU_Lodgify_Comptes::dechiffrer( $compte->cle_chiffree ) ) ); ?></code>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Actif', '4u-lodgify' ); ?></th>
						<td><label><input type="checkbox" name="actif" value="1" <?php checked( $compte->actif ?? 1, 1 ); ?>>
							<?php esc_html_e( 'Ce compte est utilisé par le site', '4u-lodgify' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Déduit de l\'API', '4u-lodgify' ); ?></th>
						<td>
							<?php if ( $compte && $compte->devise ) : ?>
								<code><?php echo esc_html( $compte->devise ); ?></code>
								<span class="description"><?php esc_html_e( '— devise, biens, frais et taxes sont lus chez Lodgify, jamais saisis ici.', '4u-lodgify' ); ?></span>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Enregistrez puis lancez « Tester la connexion ».', '4u-lodgify' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button class="button button-primary"><?php echo $compte ? esc_html__( 'Enregistrer', '4u-lodgify' ) : esc_html__( 'Ajouter', '4u-lodgify' ); ?></button>
					<?php if ( $compte ) : ?>
						<a class="button" href="<?php echo esc_url( $this->url() ); ?>"><?php esc_html_e( 'Annuler', '4u-lodgify' ); ?></a>
					<?php endif; ?>
				</p>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Comptes enregistrés', '4u-lodgify' ); ?></h2>

			<?php if ( ! $comptes ) : ?>
				<p><?php esc_html_e( 'Aucun compte. Les comptes de l\'ancien plugin sont repris automatiquement à l\'activation.', '4u-lodgify' ); ?></p>
			<?php else : ?>
			<table class="widefat striped fouru-table">
				<thead><tr>
					<th><?php esc_html_e( 'Nom', '4u-lodgify' ); ?></th>
					<th><?php esc_html_e( 'Site', '4u-lodgify' ); ?></th>
					<th><?php esc_html_e( 'Slug', '4u-lodgify' ); ?></th>
					<th><?php esc_html_e( 'Devise', '4u-lodgify' ); ?></th>
					<th><?php esc_html_e( 'Clé', '4u-lodgify' ); ?></th>
					<th><?php esc_html_e( 'État', '4u-lodgify' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $comptes as $c ) :
					$biens = FourU_Lodgify_Comptes::biens( $c->website_id );
					$nb_aff = 0; foreach ( $biens as $b ) { $nb_aff += (int) $b->affiche; } ?>
					<tr>
						<td><strong><?php echo esc_html( $c->nom ?: '—' ); ?></strong>
							<?php if ( ! $c->actif ) : ?><span class="fouru-inactif"><?php esc_html_e( 'inactif', '4u-lodgify' ); ?></span><?php endif; ?></td>
						<td><code><?php echo esc_html( $c->website_id ); ?></code></td>
						<td><?php echo esc_html( $c->checkout_slug ?: '—' ); ?></td>
						<td><?php echo esc_html( $c->devise ?: '—' ); ?></td>
						<td><?php echo $c->cle_chiffree
							? '<span class="fouru-ok">' . esc_html__( 'enregistrée', '4u-lodgify' ) . '</span>'
							: '<span class="fouru-ko">' . esc_html__( 'absente', '4u-lodgify' ) . '</span>'; ?></td>
						<td class="fouru-etat" data-website="<?php echo esc_attr( $c->website_id ); ?>">
							<?php if ( $c->derniere_verif ) : ?>
								<span class="description"><?php echo esc_html( mysql2date( 'j M H:i', $c->derniere_verif ) ); ?></span><br>
								<span class="description"><?php echo esc_html( wp_trim_words( (string) $c->dernier_message, 18 ) ); ?></span>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'jamais testé', '4u-lodgify' ); ?></span>
							<?php endif; ?>
							<?php /* FOURU_PAGINATION_BIENS_2026-09-21 : le total annonce par Lodgify est la
							     reference ; s'il differe du nombre lu ici, la lecture est incomplete. */
							$total_lodgify = ( isset( $c->biens_total ) && null !== $c->biens_total && '' !== $c->biens_total ) ? (int) $c->biens_total : null;
							if ( null !== $total_lodgify ) : ?>
								<br><span class="description"><strong><?php
									printf( esc_html__( 'Lodgify annonce %d bien(s)', '4u-lodgify' ), $total_lodgify );
								?></strong></span>
							<?php endif; ?>
							<?php if ( $biens ) : ?>
								<br><span class="<?php echo ( null !== $total_lodgify && count( $biens ) !== $total_lodgify ) ? 'fouru-ko' : 'description'; ?>"><?php
									printf( esc_html__( '%1$d lu(s) ici, %2$d affiché(s)', '4u-lodgify' ), count( $biens ), $nb_aff );
								?></span>
							<?php endif; ?>
						</td>
						<td class="fouru-actions">
							<button class="button fouru-tester" data-website="<?php echo esc_attr( $c->website_id ); ?>"><?php esc_html_e( 'Tester la connexion', '4u-lodgify' ); ?></button>
							<button class="button fouru-biens" data-website="<?php echo esc_attr( $c->website_id ); ?>"><?php esc_html_e( 'Lire les biens', '4u-lodgify' ); ?></button>
							<a class="button" href="<?php echo esc_url( $this->url( array( 'edit' => $c->website_id ) ) ); ?>"><?php esc_html_e( 'Modifier', '4u-lodgify' ); ?></a>
							<a class="button fouru-suppr" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=fouru_compte_delete&website_id=' . rawurlencode( $c->website_id ) ), 'fouru_compte_delete' ) ); ?>"><?php esc_html_e( 'Supprimer', '4u-lodgify' ); ?></a>
						</td>
					</tr>
					<?php if ( $biens ) : ?>
					<tr class="fouru-biens-ligne">
						<td colspan="7">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'fouru_biens_save' ); ?>
								<input type="hidden" name="action" value="fouru_biens_save">
								<input type="hidden" name="website_id" value="<?php echo esc_attr( $c->website_id ); ?>">
								<p><strong><?php esc_html_e( 'Biens affichés sur ce site', '4u-lodgify' ); ?></strong>
									<span class="description"> — <?php
										printf(
											esc_html__( '%1$d bien(s) lus ici · %2$s annoncé(s) par Lodgify', '4u-lodgify' ),
											count( $biens ),
											( isset( $c->biens_total ) && null !== $c->biens_total && '' !== $c->biens_total )
												? (int) $c->biens_total
												: esc_html__( 'total inconnu, lancer « Lire les biens »', '4u-lodgify' )
										);
									?></span></p>
								<div class="fouru-grille">
									<?php foreach ( $biens as $b ) :
										$lie = FourU_Lodgify_Association::fiches( $b->property_id );
										$assoc = array();
										foreach ( $lie as $f ) { if ( (string) $f->rid === (string) $b->property_id ) { $assoc[] = $f; } } ?>
										<div class="fouru-bien" data-property="<?php echo esc_attr( $b->property_id ); ?>" data-nom="<?php echo esc_attr( $b->nom ); ?>">
											<label><input type="checkbox" name="affiches[]" value="<?php echo esc_attr( $b->property_id ); ?>" <?php checked( $b->affiche, 1 ); ?>>
												<strong><?php echo esc_html( $b->nom ?: $b->property_id ); ?></strong>
												<span class="description"><?php echo esc_html( $b->property_id ); ?></span>
											</label>
											<div class="fouru-assoc">
												<?php if ( $assoc ) : ?>
													<span class="fouru-ok"><?php esc_html_e( 'Fiche :', '4u-lodgify' ); ?>
														<a href="<?php echo esc_url( get_permalink( $assoc[0]->ID ) ); ?>" target="_blank"><?php echo esc_html( wp_trim_words( $assoc[0]->post_title, 7 ) ); ?></a></span>
													<button type="button" class="button-link fouru-dissocier" data-post="<?php echo esc_attr( $assoc[0]->ID ); ?>"><?php esc_html_e( 'dissocier', '4u-lodgify' ); ?></button>
												<?php else : ?>
													<button type="button" class="button button-small fouru-chercher"><?php esc_html_e( 'Chercher la fiche', '4u-lodgify' ); ?></button>
													<span class="fouru-resultat"></span>
												<?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
								<p><button class="button button-secondary"><?php esc_html_e( 'Enregistrer les biens affichés', '4u-lodgify' ); ?></button></p>
							</form>
						</td>
					</tr>
					<?php endif; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Ecritures                                                           */
	/* ------------------------------------------------------------------ */

	public function enregistrer() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( '-1' ); }
		check_admin_referer( 'fouru_compte_save' );
		$r = FourU_Lodgify_Comptes::enregistrer( array(
			'nom'           => wp_unslash( $_POST['nom'] ?? '' ),
			'website_id'    => wp_unslash( $_POST['website_id'] ?? '' ),
			'checkout_slug' => wp_unslash( $_POST['checkout_slug'] ?? '' ),
			'cle_api'       => wp_unslash( $_POST['cle_api'] ?? '' ),
			'actif'         => ! empty( $_POST['actif'] ),
		) );
		$msg = is_wp_error( $r ) ? $r->get_error_message() : __( 'Compte enregistré.', '4u-lodgify' );
		wp_safe_redirect( $this->url( array( 'message' => $msg ) ) );
		exit;
	}

	public function supprimer() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( '-1' ); }
		check_admin_referer( 'fouru_compte_delete' );
		FourU_Lodgify_Comptes::supprimer( sanitize_text_field( wp_unslash( $_GET['website_id'] ?? '' ) ) );
		wp_safe_redirect( $this->url( array( 'message' => __( 'Compte supprimé.', '4u-lodgify' ) ) ) );
		exit;
	}

	public function enregistrer_biens() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( '-1' ); }
		check_admin_referer( 'fouru_biens_save' );
		FourU_Lodgify_Comptes::definir_affichage(
			sanitize_text_field( wp_unslash( $_POST['website_id'] ?? '' ) ),
			array_map( 'sanitize_text_field', (array) ( $_POST['affiches'] ?? array() ) )
		);
		wp_safe_redirect( $this->url( array( 'message' => __( 'Biens affichés mis à jour.', '4u-lodgify' ) ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	private function garde() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'refusé' ) ); }
		check_ajax_referer( 'fouru_lodgify', 'nonce' );
	}

	public function ajax_tester() {
		$this->garde();
		global $wpdb;
		$website = sanitize_text_field( wp_unslash( $_POST['website_id'] ?? '' ) );
		$cle = FourU_Lodgify_Comptes::cle_api( $website );
		if ( '' === $cle ) { wp_send_json_error( array( 'message' => __( 'Aucune clé enregistrée pour ce compte.', '4u-lodgify' ) ) ); }

		$api = new FourU_Lodgify_API( $cle );
		$r   = $api->tester();

		$maj = array( 'derniere_verif' => current_time( 'mysql' ), 'dernier_message' => $r['message'] );
		if ( ! empty( $r['devise'] ) ) { $maj['devise'] = $r['devise']; }
		if ( isset( $r['total'] ) && null !== $r['total'] ) {
			$maj['biens_total'] = (int) $r['total'];
			$maj['biens_maj']   = current_time( 'mysql' );
		}
		$wpdb->update( $wpdb->prefix . FourU_Lodgify_Comptes::TABLE_COMPTES, $maj, array( 'website_id' => $website ) );

		$r['ok'] ? wp_send_json_success( $r ) : wp_send_json_error( $r );
	}

	public function ajax_suggestions() {
		$this->garde();
		$nom = sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) );
		if ( '' === $nom ) { wp_send_json_error( array( 'message' => __( 'Nom manquant.', '4u-lodgify' ) ) ); }
		$sug = FourU_Lodgify_Association::suggestions( $nom, 3 );
		// En dessous de 35 la proposition n'a pas de sens : mieux vaut ne rien
		// proposer que d'inviter a valider une association au hasard.
		$sug = array_values( array_filter( $sug, function ( $x ) { return $x['score'] >= 35; } ) );
		wp_send_json_success( array( 'suggestions' => $sug ) );
	}

	public function ajax_associer() {
		$this->garde();
		$post = (int) ( $_POST['post_id'] ?? 0 );
		$rid  = sanitize_text_field( wp_unslash( $_POST['property_id'] ?? '' ) );
		$faits = FourU_Lodgify_Association::associer( $post, $rid );
		if ( ! $faits ) { wp_send_json_error( array( 'message' => __( 'Association impossible.', '4u-lodgify' ) ) ); }
		wp_send_json_success( array(
			'message' => sprintf(
				_n( 'Associé sur %d fiche.', 'Associé sur %d fiches (traductions comprises).', count( $faits ), '4u-lodgify' ),
				count( $faits )
			),
			'lien' => get_permalink( $post ),
		) );
	}

	public function ajax_dissocier() {
		$this->garde();
		FourU_Lodgify_Association::dissocier( (int) ( $_POST['post_id'] ?? 0 ) );
		wp_send_json_success( array( 'message' => __( 'Association retirée.', '4u-lodgify' ) ) );
	}

	public function ajax_chercher_biens() {
		$this->garde();
		wp_send_json_success( array(
			'biens' => FourU_Lodgify_Recherche::chercher( wp_unslash( $_POST['terme'] ?? '' ), 40 ),
		) );
	}

	public function ajax_biens() {
		$this->garde();
		$website = sanitize_text_field( wp_unslash( $_POST['website_id'] ?? '' ) );
		$cle = FourU_Lodgify_Comptes::cle_api( $website );
		if ( '' === $cle ) { wp_send_json_error( array( 'message' => __( 'Aucune clé enregistrée.', '4u-lodgify' ) ) ); }

		$api   = new FourU_Lodgify_API( $cle );
		$biens = $api->biens();
		if ( is_wp_error( $biens ) ) { wp_send_json_error( array( 'message' => $biens->get_error_message() ) ); }

		FourU_Lodgify_Comptes::enregistrer_biens( $website, $biens );
		FourU_Lodgify_Comptes::enregistrer_total( $website, $api->total_annonce() );

		// Frais et taxes du premier bien, a titre indicatif : ils varient par bien.
		$info = '';
		foreach ( $biens as $b ) {
			if ( '' === $b['room_type_id'] ) { continue; }
			$rg = $api->reglages_tarifaires( $b['property_id'], $b['room_type_id'] );
			if ( is_wp_error( $rg ) ) { break; }
			$f = array(); foreach ( $rg['frais'] as $x ) { $f[] = ( $x['nom'] ?: 'frais' ) . ' ' . ( null !== $x['montant'] ? $x['montant'] : $x['pct'] . ' %' ); }
			$t = array(); foreach ( $rg['taxes'] as $x ) { $t[] = ( $x['nom'] ?: 'taxe' ) . ' ' . ( null !== $x['pct'] ? $x['pct'] . ' %' : $x['montant'] ); }
			$info = sprintf( __( 'Devise %s · frais : %s · taxes : %s · %d promotion(s)', '4u-lodgify' ),
				$rg['devise'] ?: '—', $f ? implode( ', ', $f ) : '—', $t ? implode( ', ', $t ) : '—', $rg['promotions'] );
			break;
		}

		wp_send_json_success( array(
			'nombre'  => count( $biens ),
			'total'   => (int) $api->total_annonce(),
			'message' => sprintf(
				__( '%1$d bien(s) lus, %2$d annoncés par Lodgify.', '4u-lodgify' ),
				count( $biens ), (int) $api->total_annonce()
			) . ( $info ? ' ' . $info : '' ),
		) );
	}
}
