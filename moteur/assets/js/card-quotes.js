/**
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 *
 * CARTES_DEVIS_20260922 — devis exact des cartes de resultats.
 *
 * Le rendu serveur ne peut pas attendre le devis : 1,9 s par appel mesure le
 * 2026-09-22, soit 38 s pour vingt cartes. La carte s'affiche donc avec le
 * total reconstruit localement - exact au centime sur 17 comparaisons, 0 ecart -
 * puis ce script le confirme ou l'efface.
 *
 * Regle : aucun total ne survit si Lodgify refuse de chiffrer le sejour
 * (price_source "unavailable", sejour minimum non atteint, ou total nul).
 * Le cache de 10 minutes est cote serveur, par bien et par dates.
 */
(function ($) {
    'use strict';

    if (typeof lodgifyCardQuotes === 'undefined') { return; }

    var CONF = lodgifyCardQuotes;
    var enCours = 0;
    var file = [];

    function suivant() {
        while (enCours < (parseInt(CONF.simultanees, 10) || 4) && file.length) {
            traiter(file.shift());
        }
    }

    function indisponible($el) {
        /* Des dates SONT demandees : « Select dates » n'aurait aucun sens ici.
           On dit ce qui est vrai - Lodgify refuse ce sejour. */
        var texte = CONF.i18nIndispo || $el.attr('data-fallback') || '';
        $el.addClass('lodgify-total-indispo').text(texte);
    }

    function traiter($el) {
        enCours++;
        $.ajax({
            url: CONF.ajaxurl,
            type: 'POST',
            dataType: 'json',
            timeout: 25000,
            data: {
                action: 'lodgify_get_price',
                nonce: CONF.nonce,
                property_id: $el.attr('data-rental'),
                check_in: $el.attr('data-in'),
                check_out: $el.attr('data-out'),
                guests: parseInt($el.attr('data-guests'), 10) || 2
            }
        }).done(function (r) {
            var d = (r && r.success) ? r.data : null;
            if (!d) { indisponible($el); return; }

            var total = parseFloat(d.total);
            var refuse = (d.price_source === 'unavailable')
                      || !!d.min_stay_error
                      || !(total > 0);

            if (refuse) { indisponible($el); return; }

            var $montant = $el.find('.lodgify-total-montant');
            var texte = (d.currency_symbol || '') + total.toFixed(2);
            if ($montant.length) { $montant.text(texte); } else { $el.text(texte); }
            $el.addClass('lodgify-total-confirme');

            /* Le sejour minimum de la carte vient du meme appel : il suit donc
               la periode cherchee, sans requete supplementaire. */
            if (d.min_stay) {
                $el.closest('.jet-listing-grid__item, .elementor-loop-item, article, li')
                   .find('.lodgify-min-stay-valeur')
                   .text(d.min_stay);
            }
        }).fail(function () {
            indisponible($el);
        }).always(function () {
            enCours--;
            suivant();
        });
    }

    function demarrer() {
        var $cartes = $('.lodgify-total-stay[data-rental][data-in][data-out]').not('.lodgify-total-traite');
        if (!$cartes.length) { return; }
        $cartes.addClass('lodgify-total-traite').each(function () { file.push($(this)); });
        suivant();
    }

    /* Apres l'affichage, jamais pendant : la page ne doit pas attendre. */
    $(window).on('load', function () { setTimeout(demarrer, 50); });

    /* Les grilles JetSmartFilters se re-rendent en AJAX : on repasse dessus. */
    $(document).on('jet-filter-content-rendered jet-engine-request-calendar', function () {
        setTimeout(demarrer, 100);
    });
})(jQuery);
