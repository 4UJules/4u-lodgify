/**
 * Scripts pour les widgets Elementor du plugin Lodgify Availability Sync
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

(function($) {
    'use strict';
    
    // Initialisation du widget de prix
    var initPriceWidget = function() {
        $('.lodgify-price-widget').each(function() {
            // Ajouter des fonctionnalités interactives si nécessaire
        });
    };
    
    // Initialiser les widgets au chargement de la page
    $(document).ready(function() {
        initPriceWidget();
    });
    
    // Initialiser les widgets lors de la mise à jour du frontend d'Elementor
    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/lodgify_price_widget.default', initPriceWidget);
        }
    });
    
})(jQuery);
