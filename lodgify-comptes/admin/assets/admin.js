/**
 * Ecran « Comptes Lodgify » : test de connexion, lecture des biens,
 * association bien Lodgify <-> fiche du site.
 */
(function ($) {
	'use strict';

	function poster(action, data, onOk, onKo) {
		$.post(fouruLodgify.ajaxurl, $.extend({ action: action, nonce: fouruLodgify.nonce }, data))
			.done(function (r) {
				if (r && r.success) { onOk(r.data || {}); }
				else { onKo((r && r.data && r.data.message) || 'Erreur'); }
			})
			.fail(function () { onKo('Erreur réseau'); });
	}

	$(document).on('click', '.fouru-tester', function () {
		var $b = $(this), site = $b.data('website');
		var $etat = $('.fouru-etat[data-website="' + site + '"]');
		$b.prop('disabled', true);
		$etat.html('<span class="description">' + fouruLodgify.i18n.test + '</span>');
		poster('fouru_tester_compte', { website_id: site },
			function (d) { $b.prop('disabled', false); $etat.html('<span class="fouru-ok">' + d.message + '</span>'); },
			function (m) { $b.prop('disabled', false); $etat.html('<span class="fouru-ko">' + m + '</span>'); });
	});

	$(document).on('click', '.fouru-biens', function () {
		var $b = $(this), site = $b.data('website');
		var $etat = $('.fouru-etat[data-website="' + site + '"]');
		$b.prop('disabled', true);
		$etat.html('<span class="description">' + fouruLodgify.i18n.biens + '</span>');
		poster('fouru_rafraichir_biens', { website_id: site },
			function (d) { $etat.html('<span class="fouru-ok">' + d.message + '</span>'); setTimeout(function () { location.reload(); }, 900); },
			function (m) { $b.prop('disabled', false); $etat.html('<span class="fouru-ko">' + m + '</span>'); });
	});

	/* Recherche de la fiche correspondante : on propose, l'utilisateur tranche.
	   Jamais d'association automatique - une mauvaise association ecrirait un
	   rental-id sur la mauvaise fiche, donc de mauvaises dates et un mauvais prix. */
	$(document).on('click', '.fouru-chercher', function () {
		var $b = $(this), $bien = $b.closest('.fouru-bien'), $res = $bien.find('.fouru-resultat');
		$b.prop('disabled', true);
		$res.html('<span class="description">' + fouruLodgify.i18n.chercher + '</span>');
		poster('fouru_suggestions', { nom: $bien.data('nom') }, function (d) {
			$b.prop('disabled', false);
			var s = d.suggestions || [];
			if (!s.length) { $res.html('<span class="description">' + fouruLodgify.i18n.aucune + '</span>'); return; }
			var h = '<ul class="fouru-sug">';
			s.forEach(function (x) {
				h += '<li><button type="button" class="button button-small fouru-associer" data-post="' + x.post_id + '">'
				  + fouruLodgify.i18n.associer + '</button> '
				  + '<span class="fouru-titre">' + $('<i>').text(x.titre).html() + '</span> '
				  + '<span class="fouru-score">' + x.score + ' %</span>'
				  + (x.rid ? ' <span class="fouru-ko">déjà lié à ' + x.rid + '</span>' : '')
				  + '</li>';
			});
			$res.html(h + '</ul>');
		}, function (m) { $b.prop('disabled', false); $res.html('<span class="fouru-ko">' + m + '</span>'); });
	});

	$(document).on('click', '.fouru-associer', function () {
		var $b = $(this), $bien = $b.closest('.fouru-bien');
		$b.prop('disabled', true);
		poster('fouru_associer', { post_id: $b.data('post'), property_id: $bien.data('property') },
			function (d) { $bien.find('.fouru-assoc').html('<span class="fouru-ok">' + d.message + '</span>'); },
			function (m) { $b.prop('disabled', false); alert(m); });
	});

	$(document).on('click', '.fouru-dissocier', function () {
		var $b = $(this), $bien = $b.closest('.fouru-bien');
		poster('fouru_dissocier', { post_id: $b.data('post') },
			function (d) { $bien.find('.fouru-assoc').html('<span class="description">' + d.message + '</span>'); },
			function (m) { alert(m); });
	});

	$(document).on('click', '.fouru-suppr', function (e) {
		if (!window.confirm(fouruLodgify.i18n.confirmer)) { e.preventDefault(); }
	});
})(jQuery);
