/**
 * Lodgify Elementor Editor Scripts
 */

(function($) {
    'use strict';
    
    // Attendre que Elementor soit prêt
    $(window).on('elementor:init', function() {
        
        // Ajouter des notifications pour les Dynamic Tags
        if (typeof elementor !== 'undefined') {
            
            // Écouter les changements dans les contrôles
            elementor.hooks.addAction('panel/open_editor/widget', function(panel, model, view) {
                // Notification pour les widgets Lodgify
                if (model.get('widgetType') && model.get('widgetType').indexOf('lodgify') !== -1) {
                    console.log('Lodgify Widget loaded:', model.get('widgetType'));
                }
            });
        }
    });
    
})(jQuery);
