/**
 * Airbnb-style Booking Widget JavaScript
 * Avec popup calendrier style Airbnb
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */

(function($) {
    'use strict';
    
    var AirbnbBookingWidget = {
        
        $widget: null,
        $popup: null,
        propertyId: null,
        checkoutUrl: null,
        currency: '$',
        maxGuests: 10,
        
        arrivalDate: null,
        departureDate: null,
        guests: 1,
        
        currentMonth: null,
        selectingField: 'arrival',
        unavailableDates: [],
        /* RACE_NUITS_20260922 : tant que les nuits occupees ne sont pas
           connues, le calendrier parait entierement libre. On bloque donc
           toute selection jusqu'a leur arrivee. */
        datesPretes: false,
        devisOk: false,
        /* ETATS_DISTINCTS_20260922 : minimums par nuit, pour ecarter les
           departs trop proches AVANT le clic, comme le fait le calendrier du
           bas. Meme source que lui : l'action lodgify_calendar_prices. */
        minStayParNuit: {},
        
        init: function() {
            var self = this;

            /* RACE_NUITS_20260922 : initGuestsDropdown() appelle
               updateBrowserUrl(), qui SUPPRIME checkin/checkout quand aucune
               plage n'est encore posee - et il s'execute AVANT checkUrlDates().
               Lue en direct, window.location.search a donc deja perdu les
               parametres, et un lien partage n'ouvrait jamais ses dates. On
               fige la chaine d'origine ici, avant que quoi que ce soit ne la
               reecrive. Le format meta= survivait, lui, parce que
               updateBrowserUrl() n'y touche pas : d'ou l'asymetrie observee. */
            if (Object.prototype.hasOwnProperty.call(self, 'urlInitiale') === false) {
                self.urlInitiale = window.location.search;
            }
            
            $('.airbnb-booking-widget').each(function() {
                var $widget = $(this);
                // Éviter la double initialisation
                if ($widget.data('abw-initialized')) {
                    return;
                }
                $widget.data('abw-initialized', true);
                
                var instance = Object.create(self);
                instance.initWidget($widget);
            });
        },
        
        initWidget: function($widget) {
            var self = this;
            this.$widget = $widget;
            this.$popup = $widget.siblings('.abw-calendar-popup').first();
            
            if (!this.$popup.length) {
                this.$popup = $('#abw-popup-' + $widget.attr('id').replace('abw-', ''));
            }
            
            this.checkoutUrl = $widget.data('checkout-url');

            /* AMM_ACCOUNT 2026-09-21 : le compte Lodgify du bien n'a pas pu etre
               resolu cote serveur. On ne construit aucun lien de reservation :
               mieux vaut un bouton inactif qu'un envoi vers le mauvais compte. */
            this.accountError = String($widget.data('account-error')) === '1';
            this.currency = $widget.data('currency') || '€';
            this.maxGuests = parseInt($widget.data('max-guests')) || 10;
            this.labelSingular = $widget.data('label-singular') || 'voyageur';
            this.labelPlural = $widget.data('label-plural') || 'voyageurs';
            this.labelReserve = $widget.data('label-reserve') || 'Réserver';
            this.labelCheck = $widget.data('label-check') || 'Vérifier la disponibilité';
            this.minStayMessageTemplate = $widget.data('min-stay-message') || 'Minimum stay of {min_stay} nights required';
            
            // Récupérer le propertyId - PRIORITÉ au data-attribute du widget (vient du PHP/rental-id)
            this.propertyId = null;
            
            // 1. PRIORITÉ: depuis data-attribute du widget (généré par PHP depuis rental-id)
            var widgetPropertyId = $widget.data('property-id');
            if (widgetPropertyId && widgetPropertyId !== '' && widgetPropertyId !== '0') {
                this.propertyId = String(widgetPropertyId);
                console.log('ABW: Property ID from widget data (rental-id):', this.propertyId);
            }
            
            // 2. Fallback: extraire depuis checkout_url du widget
            if (!this.propertyId && this.checkoutUrl) {
                var match = this.checkoutUrl.match(/\/(\d+)(?:\/|$)/);
                if (match) {
                    this.propertyId = match[1];
                    console.log('ABW: Property ID from checkout URL:', this.propertyId);
                }
            }
            
            // 3. Fallback: chercher le bouton Lodgify sur la page
            if (!this.propertyId) {
                var $localBookBtn = $widget.closest('.elementor-section').find('a[href*="checkout.lodgify.com"]').first();
                if (!$localBookBtn.length) {
                    $localBookBtn = $('a[href*="checkout.lodgify.com"]').first();
                }
                if ($localBookBtn.length) {
                    var href = $localBookBtn.attr('href');
                    var btnMatch = href.match(/\/(\d+)\//);
                    if (btnMatch) {
                        this.propertyId = btnMatch[1];
                        console.log('ABW: Property ID from Lodgify button:', this.propertyId);
                    }
                }
            }
            
            // 3. Fallback: depuis meta de la page
            if (!this.propertyId) {
                var $metaPropertyId = $('meta[name="lodgify_property_id"], meta[name="rental_id"]');
                if ($metaPropertyId.length) {
                    this.propertyId = $metaPropertyId.attr('content');
                    console.log('ABW: Property ID from meta:', this.propertyId);
                }
            }
            
            console.log('ABW: Widget initialized', {
                widgetId: $widget.attr('id'),
                propertyId: this.propertyId,
                checkoutUrl: this.checkoutUrl,
                currency: this.currency,
                maxGuests: this.maxGuests,
                popupFound: this.$popup.length > 0
            });
            
            this.currentMonth = new Date();
            this.currentMonth.setDate(1);
            
            this.bindEvents($widget);
            this.majBouton();
            if (this.accountError) { this.disableBooking(); }

            /* AMM_PONT_CALENDRIER 2026-09-21 : le calendrier du bas de page et
               ce widget partagent la meme selection, dans les deux sens. Le
               drapeau 'source' empeche l'echo infini entre les deux. */
            $(document).on('lodgify:dates', function (e, d) {
                if (!d || d.source === 'reservation') { return; }
                if (String(d.propertyId) !== String(self.propertyId)) { return; }
                self.arrivalDate = d.arrival ? self.parseDate(d.arrival) : null;
                self.departureDate = d.departure ? self.parseDate(d.departure) : null;
                self.selectingField = (self.arrivalDate && !self.departureDate) ? 'departure' : 'arrival';
                /* Le calendrier du bas valide avant d'emettre, mais rien ne
                   garantit que l'emetteur soit lui : on revalide ici aussi. */
                if (!self.revaliderSelection()) { return; }
                self.renderCalendar();
                self.updateWidgetDisplay();
                if (self.arrivalDate && self.departureDate) {
                    self.syncDatesToJetBooking();
                    self.updateBrowserUrl();
                    self.fetchPrice();
                }
            });

            this.loadUnavailableDates();
            this.chargerMinStay();

            // Initialiser le dropdown voyageurs avec maxGuests AVANT de vérifier l'URL
            this.initGuestsDropdown();
            
            // Vérifier les dates et guests depuis l'URL APRÈS l'initialisation du dropdown
            this.checkUrlDates();
            
            // Initialiser le mode sticky mobile
            this.initMobileSticky();
        },
        
        initMobileSticky: function() {
            var self = this;
            var $widget = this.$widget;
            var enableSticky = $widget.data('mobile-sticky') === true || $widget.data('mobile-sticky') === 'true';
            var breakpoint = parseInt($widget.data('sticky-breakpoint')) || 768;
            
            if (!enableSticky) {
                return;
            }
            
            function checkStickyMode() {
                var windowWidth = $(window).width();
                
                if (windowWidth <= breakpoint) {
                    // Activer le mode sticky
                    $widget.addClass('abw-mobile-sticky');
                } else {
                    // Désactiver le mode sticky
                    $widget.removeClass('abw-mobile-sticky');
                }
            }
            
            // Vérifier au chargement et au redimensionnement
            checkStickyMode();
            $(window).on('resize', checkStickyMode);
        },
        
        bindEvents: function($widget) {
            var self = this;
            
            // Clic sur les champs de date - ouvre le popup
            $widget.on('click', '.abw-date-field', function() {
                self.selectingField = $(this).data('type');
                self.openCalendar();
            });
            
            // Clic sur le champ voyageurs
            $widget.on('click', '.abw-guests-field', function(e) {
                e.stopPropagation();
                var $row = $(this).closest('.abw-guests-row');
                var $dropdown = $row.find('.abw-guests-dropdown');
                
                console.log('ABW: Guests field clicked, options found:', $dropdown.find('.abw-guest-option').length);
                
                $row.toggleClass('open');
                $dropdown.toggle();
            });
            
            // Clic sur une option de la liste voyageurs
            $widget.on('click', '.abw-guest-option', function(e) {
                e.stopPropagation();
                var val = parseInt($(this).data('value'));
                
                console.log('ABW: Guest option clicked:', val);
                
                // Mettre à jour la sélection
                $widget.find('.abw-guest-option').removeClass('selected');
                $(this).addClass('selected');
                
                self.guests = val;
                self.updateGuestsDisplay();
                
                // Fermer le dropdown
                $widget.find('.abw-guests-row').removeClass('open');
                $widget.find('.abw-guests-dropdown').hide();
                
                // Recalculer le prix si des dates sont sélectionnées
                if (self.arrivalDate && self.departureDate) {
                    self.fetchPrice();
                }
            });
            
            // Fermer dropdown au clic extérieur
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.abw-guests-row').length) {
                    $widget.find('.abw-guests-row').removeClass('open');
                    $widget.find('.abw-guests-dropdown').hide();
                }
            });
            
            // Bouton principal
            $widget.on('click', '.abw-submit-btn', function() {
                if ($(this).hasClass('abw-check-availability')) {
                    self.selectingField = 'arrival';
                    self.openCalendar();
                } else if ($(this).hasClass('abw-reserve')) {
                    self.goToCheckout();
                }
            });
            
            // Events du popup calendrier
            if (this.$popup.length) {
                this.$popup.on('click', '.abw-calendar-overlay', function() {
                    self.closeCalendar();
                });
                
                this.$popup.on('click', '.abw-close-calendar', function() {
                    self.closeCalendar();
                });
                
                this.$popup.on('click', '.abw-clear-all', function() {
                    self.clearDates();
                });
                
                this.$popup.on('click', '.abw-clear-date', function() {
                    var type = $(this).data('type');
                    if (type === 'arrival') {
                        self.arrivalDate = null;
                    } else {
                        self.departureDate = null;
                    }
                    self.updateCalendarDisplay();
                    self.updateWidgetDisplay();
                });
                
                this.$popup.on('click', '.abw-nav-prev', function() {
                    self.prevMonth();
                });
                
                this.$popup.on('click', '.abw-nav-next', function() {
                    self.nextMonth();
                });
                
                this.$popup.on('click', '.abw-day:not(.disabled):not(.other-month)', function() {
                    if (!self.datesPretes) {
                        self.showDayNotice(self.msgChargement());
                        return;
                    }
                    self.selectDate($(this).data('date'));
                });

                /* ETATS_DISTINCTS_20260922 : au toucher, l'attribut `title` ne
                   s'affiche pas. Une date ecartee par le seul sejour minimum
                   doit quand meme pouvoir se justifier : on repond au tap par
                   le meme message. Elle reste non selectionnable. */
                this.$popup.on('click', '.abw-day.abw-min-court', function() {
                    var t = $(this).attr('title');
                    if (t) { self.showDayNotice(t); }
                    return false;
                });
                
                this.$popup.on('click', '.abw-input-arrival', function() {
                    self.selectingField = 'arrival';
                    // renderCalendar() et non updateCalendarDisplay() : ce dernier
                    // ne touche que les libelles, pas les classes des jours.
                    self.renderCalendar();
                });
                
                this.$popup.on('click', '.abw-input-departure', function() {
                    self.selectingField = 'departure';
                    self.renderCalendar();
                });
            }
        },
        
        /**
         * Minimums par nuit, sur la periode affichee. Une seule requete pour
         * tout le calendrier, et le serveur met deja le resultat en cache.
         */
        chargerMinStay: function () {
            var self = this;
            if (!this.propertyId || typeof airbnbBooking === 'undefined') { return; }

            var debut = new Date(this.currentMonth.getTime());
            var fin = new Date(this.currentMonth.getTime());
            fin.setMonth(fin.getMonth() + 3);

            $.ajax({
                url: airbnbBooking.ajaxurl,
                type: 'GET',
                dataType: 'json',
                timeout: 20000,
                data: {
                    action: 'lodgify_calendar_prices',
                    rental_id: this.propertyId,
                    start: this.formatDate(debut),
                    end: this.formatDate(fin)
                },
                success: function (r) {
                    if (!r || !r.success || !r.data || !r.data.min_stay) { return; }
                    self.minStayParNuit = $.extend(self.minStayParNuit || {}, r.data.min_stay);
                    self.renderCalendar();
                }
            });
        },

        /** Minimum le plus contraignant entre deux dates, regle Lodgify. */
        minSejourEntre: function (a, b) {
            var m = 1, cur = new Date(a.getTime());
            while (cur < b) {
                var v = (this.minStayParNuit || {})[this.formatDate(cur)];
                if (v && v > m) { m = v; }
                cur.setDate(cur.getDate() + 1);
            }
            return m;
        },

        msgChargement: function () {
            return (typeof airbnbBooking !== 'undefined' && airbnbBooking.i18nChargement)
                ? airbnbBooking.i18nChargement
                : 'Loading availability\u2026';
        },

        msgPlageInvalide: function () {
            return (typeof airbnbBooking !== 'undefined' && airbnbBooking.i18nPlageInvalide)
                ? airbnbBooking.i18nPlageInvalide
                : 'Your dates include a booked night. Please choose again.';
        },

        /**
         * RACE_NUITS_20260922 - seul endroit ou unavailableDates est etabli.
         * Marque les dates pretes, revalide la selection en cours, re-rend et
         * remet le bouton dans le bon etat.
         */
        appliquerNuits: function (liste, source) {
            var avant = this.unavailableDates.length;
            if (Array.isArray(liste)) {
                for (var i = 0; i < liste.length; i++) {
                    if (this.unavailableDates.indexOf(liste[i]) === -1) {
                        this.unavailableDates.push(liste[i]);
                    }
                }
            }
            this.datesPretes = true;
            console.log('ABW: nuits occupees etablies (' + (source || '?') + ') : '
                + avant + ' -> ' + this.unavailableDates.length);
            this.revaliderSelection();
            if (this.selectionEnAttente) {
                var f = this.selectionEnAttente;
                this.selectionEnAttente = null;
                f();
            }
            this.renderCalendar();
            this.majBouton();
        },

        /**
         * Une selection posee avant l'arrivee des nuits peut etre devenue
         * invalide. On la verifie a chaque changement et on l'efface plutot
         * que de la laisser produire un prix qui n'existe pas.
         */
        revaliderSelection: function () {
            if (!this.arrivalDate || !this.departureDate) { return true; }
            var cur = new Date(this.arrivalDate.getTime());
            var occupee = false;
            while (cur < this.departureDate) {
                if (this.unavailableDates.indexOf(this.formatDate(cur)) !== -1) { occupee = true; break; }
                cur.setDate(cur.getDate() + 1);
            }
            if (!occupee) { return true; }

            this.arrivalDate = null;
            this.departureDate = null;
            this.selectingField = 'arrival';
            this.devisOk = false;
            this.masquerPrix();
            this.showDayNotice(this.msgPlageInvalide());
            this.updateWidgetDisplay();
            return false;
        },

        /** Aucun prix visible : ni chiffre, ni « 0,00 ». */
        masquerPrix: function () {
            var $p = this.$widget.find('.abw-price-display');
            $p.find('.abw-price-current').text('');
            $p.find('.abw-price-original').hide();
            $p.find('.abw-price-nights').text('');
            this.$widget.find('.abw-with-dates').hide();
            this.$widget.find('.abw-no-dates').show();
        },

        /**
         * Le meme bouton sert deux fois : « Check Availability » tant qu'aucune
         * plage n'est posee - il ouvre le calendrier et doit rester cliquable -
         * puis « Reserve » une fois les deux dates choisies. Ce n'est que dans
         * ce second etat qu'il doit etre bloque sans devis abouti. Le desactiver
         * dans le premier etat empecherait d'ouvrir le popup.
         */
        majBouton: function () {
            var $b = this.$widget.find('.abw-submit-btn');
            if (!$b.length) { return; }
            var enModeReserve = !!(this.arrivalDate && this.departureDate);
            var ok = !enModeReserve || !!(this.datesPretes && this.devisOk);
            $b.prop('disabled', !ok)
              .attr('aria-disabled', ok ? 'false' : 'true')
              .css('opacity', ok ? '1' : '0.5');
        },

        loadUnavailableDates: function() {
            var self = this;
            
            console.log('ABW: Loading unavailable dates for property:', this.propertyId);
            
            // 1. Lire depuis window.JetABAFData (données du Booking Availability Calendar)
            if (typeof window.JetABAFData !== 'undefined') {
                console.log('ABW: Found JetABAFData:', window.JetABAFData);
                
                // booked_dates = dates réservées
                if (window.JetABAFData.booked_dates && window.JetABAFData.booked_dates.length) {
                    window.JetABAFData.booked_dates.forEach(function(date) {
                        if (self.unavailableDates.indexOf(date) === -1) {
                            self.unavailableDates.push(date);
                        }
                    });
                    console.log('ABW: Added', window.JetABAFData.booked_dates.length, 'booked_dates from JetABAFData');
                }
                
                // disabled_days = jours désactivés (ex: certains jours de la semaine)
                if (window.JetABAFData.disabled_days && window.JetABAFData.disabled_days.length) {
                    console.log('ABW: Found disabled_days:', window.JetABAFData.disabled_days);
                    // disabled_days est généralement un tableau de numéros de jour (0-6)
                    // On va générer les dates pour les 12 prochains mois
                    var today = new Date();
                    for (var m = 0; m < 12; m++) {
                        var monthDate = new Date(today.getFullYear(), today.getMonth() + m, 1);
                        var lastDay = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();
                        
                        for (var d = 1; d <= lastDay; d++) {
                            var checkDate = new Date(monthDate.getFullYear(), monthDate.getMonth(), d);
                            var dayOfWeek = checkDate.getDay(); // 0 = Dimanche
                            
                            if (window.JetABAFData.disabled_days.indexOf(dayOfWeek) !== -1 ||
                                window.JetABAFData.disabled_days.indexOf(String(dayOfWeek)) !== -1) {
                                var dateStr = self.formatDate(checkDate);
                                if (self.unavailableDates.indexOf(dateStr) === -1) {
                                    self.unavailableDates.push(dateStr);
                                }
                            }
                        }
                    }
                }
                
                // days_off = jours de congé spécifiques
                if (window.JetABAFData.days_off && window.JetABAFData.days_off.length) {
                    window.JetABAFData.days_off.forEach(function(date) {
                        if (self.unavailableDates.indexOf(date) === -1) {
                            self.unavailableDates.push(date);
                        }
                    });
                    console.log('ABW: Added', window.JetABAFData.days_off.length, 'days_off from JetABAFData');
                }
            } else {
                console.log('ABW: JetABAFData not found');
            }
            
            console.log('ABW: Total unavailable dates from JetBooking:', self.unavailableDates.length);
            
            var localesL = this.datesEmbarquees();
            if (localesL) {
                self.appliquerNuits(localesL, 'page');
                return;
            }

            // Charger depuis l'API pour avoir toutes les dates (important!)
            if (this.propertyId && typeof airbnbBooking !== 'undefined') {
                $.ajax({
                    url: airbnbBooking.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lodgify_get_unavailable_dates',
                        property_id: this.propertyId
                    },
                    success: function(response) {
                        console.log('ABW: API unavailable dates response:', response);
                        if (response.success && response.data && response.data.length) {
                            response.data.forEach(function(date) {
                                if (self.unavailableDates.indexOf(date) === -1) {
                                    self.unavailableDates.push(date);
                                }
                            });
                            self.appliquerNuits([], 'ajax');
                        } else {
                            self.appliquerNuits([], 'ajax-vide');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('ABW: API unavailable dates error:', error);
                    }
                });
            }
        },
        
        openCalendar: function() {
            var self = this;
            
            // Recharger les dates indisponibles à chaque ouverture
            this.loadUnavailableDatesAndRender();
            
            this.$popup.fadeIn(200);
            $('body').css('overflow', 'hidden');
        },
        
        /* AMM_DATES_INLINE 2026-09-21 : les nuits occupees sont desormais
           embarquees dans la page par le widget calendrier. Un appel a
           admin-ajax.php coute 1,4 s d'amorcage WordPress - mesure : une action
           vide en coute deja 1,37. On lit donc la donnee locale quand elle est
           la, et l'AJAX ne sert plus que de repli. */
        datesEmbarquees: function () {
            var d = (window.LodgifyDates || {})[this.propertyId];
            return (d && d.length) ? d : null;
        },

        loadUnavailableDatesAndRender: function() {
            var self = this;
            
            // D'abord rendre le calendrier avec les dates déjà connues
            this.renderCalendar();
            
            var localesR = this.datesEmbarquees();
            if (localesR) {
                this.appliquerNuits(localesR, 'page-ouverture');
                return;
            }

            // Puis charger les dates depuis l'API
            if (this.propertyId && typeof airbnbBooking !== 'undefined') {
                $.ajax({
                    url: airbnbBooking.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lodgify_get_unavailable_dates',
                        property_id: this.propertyId
                    },
                    success: function(response) {
                        console.log('ABW: Popup - API unavailable dates:', response);
                        if (response.success && response.data) {
                            var dates = response.data;
                            if (typeof dates === 'object' && !Array.isArray(dates)) {
                                // Si c'est un objet, extraire les valeurs
                                dates = Object.values(dates);
                            }
                            if (Array.isArray(dates) && dates.length > 0) {
                                dates.forEach(function(date) {
                                    if (self.unavailableDates.indexOf(date) === -1) {
                                        self.unavailableDates.push(date);
                                    }
                                });
                                self.appliquerNuits([], 'ajax-popup');
                            }
                        }
                    }
                });
            }
        },
        
        closeCalendar: function() {
            this.$popup.fadeOut(200);
            $('body').css('overflow', '');
        },
        
        renderCalendar: function() {
            var month1 = new Date(this.currentMonth);
            var month2 = new Date(this.currentMonth);
            month2.setMonth(month2.getMonth() + 1);
            
            this.$popup.find('.abw-month-1').html(this.renderMonth(month1));
            this.$popup.find('.abw-month-2').html(this.renderMonth(month2));
            
            this.updateCalendarDisplay();
            
            // Disable prev si mois actuel
            var today = new Date();
            var canGoPrev = this.currentMonth.getFullYear() > today.getFullYear() || 
                           (this.currentMonth.getFullYear() === today.getFullYear() && 
                            this.currentMonth.getMonth() > today.getMonth());
            
            this.$popup.find('.abw-nav-prev').prop('disabled', !canGoPrev);
        },
        
        renderMonth: function(date) {
            var self = this;
            var year = date.getFullYear();
            var month = date.getMonth();
            var monthNames = typeof airbnbBooking !== 'undefined' ? airbnbBooking.months : 
                ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            var dayNames = typeof airbnbBooking !== 'undefined' ? airbnbBooking.days : ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
            var monthName = monthNames[month] + ' ' + year;
            
            var html = '<div class="abw-month-header">' + monthName + '</div>';
            
            // Weekdays
            html += '<div class="abw-weekdays">';
            dayNames.forEach(function(day) {
                html += '<div class="abw-weekday">' + day + '</div>';
            });
            html += '</div>';
            
            // Days
            html += '<div class="abw-days">';
            
            var firstDay = new Date(year, month, 1);
            var lastDay = new Date(year, month + 1, 0);
            var startDay = (firstDay.getDay() + 6) % 7; // Monday = 0
            
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            
            // Empty cells before first day
            for (var i = 0; i < startDay; i++) {
                html += '<div class="abw-day other-month"></div>';
            }
            
            // Days of month
            for (var d = 1; d <= lastDay.getDate(); d++) {
                var dayDate = new Date(year, month, d);
                var dateStr = this.formatDate(dayDate);
                var classes = ['abw-day'];
                
                // Check if today
                if (dayDate.getTime() === today.getTime()) {
                    classes.push('today');
                }
                
                /* Comportement Airbnb : un jour dont la nuit est occupee mais
                   dont la veille est libre reste valable en DEPART. On l'affiche
                   donc comme un jour normal - texte fonce, non barre, cliquable -
                   sans marqueur particulier. Le refus eventuel se fait au moment
                   du choix, dans selectDate(), pas a l'affichage. */
                var veille = new Date(dayDate.getTime());
                veille.setDate(veille.getDate() - 1);
                var valableEnDepart = !this.isUnavailableForArrival(this.formatDate(veille));

                var indisponible;
                if (this.selectingField === 'departure') {
                    indisponible = this.isUnavailableForDeparture(dateStr);
                } else {
                    // grise seulement si le jour n'est ni une arrivee ni un depart possible
                    indisponible = this.isUnavailableForArrival(dateStr) && !valableEnDepart;
                }
                if (dayDate < today || indisponible) {
                    classes.push('disabled');
                }

                /* ETATS_DISTINCTS_20260922 : une date LIBRE mais trop proche de
                   l'arrivee est ecartee elle aussi - mais elle ne doit pas
                   ressembler a une nuit reservee. On ajoute `disabled` pour que
                   le gestionnaire de clic la refuse (il exclut `.disabled`), et
                   `abw-min-court` pour que la CSS lui retire la rature. */
                var titreJour = '';
                if (this.selectingField === 'departure' && this.arrivalDate
                    && dayDate > this.arrivalDate
                    && dayDate >= today
                    && !this.isUnavailableForDeparture(dateStr)) {
                    var ecart = Math.round((dayDate - this.arrivalDate) / 86400000);
                    var minJour = this.minSejourEntre(this.arrivalDate, dayDate);
                    if (ecart < minJour) {
                        classes.push('disabled', 'abw-min-court');
                        titreJour = ((typeof airbnbBooking !== 'undefined' && airbnbBooking.i18nMinStayTitre)
                            ? airbnbBooking.i18nMinStayTitre
                            : 'Minimum stay of {n} nights').replace('{n}', minJour);
                    }
                }
                
                // Check if selected
                /* SEL_AIRBNB_2026-09-21 : has-range seulement quand la plage est
                   complete, sinon l'arrivee seule amorcerait une bande. */
                var plageComplete = !!(this.arrivalDate && this.departureDate);
                if (this.arrivalDate && this.isSameDay(dayDate, this.arrivalDate)) {
                    classes.push('selected', 'range-start');
                    if (plageComplete) { classes.push('has-range'); }
                }
                if (this.departureDate && this.isSameDay(dayDate, this.departureDate)) {
                    classes.push('selected', 'range-end');
                    if (plageComplete) { classes.push('has-range'); }
                }
                
                // Check if in range
                if (this.arrivalDate && this.departureDate && 
                    dayDate > this.arrivalDate && dayDate < this.departureDate) {
                    classes.push('in-range');
                }
                
                html += '<div class="' + classes.join(' ') + '" data-date="' + dateStr + '"'
                     + (titreJour ? ' title="' + titreJour.replace(/"/g, '&quot;') + '"' : '')
                     + '>' + d + '</div>';
            }
            
            html += '</div>';
            
            return html;
        },
        
        selectDate: function(dateStr) {
            var date = this.parseDate(dateStr);

            /* Un jour peut etre cliquable parce qu'il est valable en DEPART tout
               en etant impossible en ARRIVEE (sa nuit est occupee). On refuse ici,
               au moment du choix, plutot que de le signaler a l'avance. */
            if (this.selectingField === 'arrival' && this.isUnavailableForArrival(dateStr)) {
                this.showDayNotice(airbnbBooking && airbnbBooking.i18nDepartOnly
                    ? airbnbBooking.i18nDepartOnly
                    : 'Cette date n\u2019est disponible qu\u2019en d\u00e9part.');
                return;
            }

            if (this.selectingField === 'arrival') {
                this.arrivalDate = date;
                
                // Si départ est avant arrivée, reset
                if (this.departureDate && this.departureDate <= date) {
                    this.departureDate = null;
                }
                
                this.selectingField = 'departure';
            } else {
                // Si date sélectionnée est avant arrivée, swap
                if (this.arrivalDate && date <= this.arrivalDate) {
                    this.arrivalDate = date;
                    this.departureDate = null;
                    this.selectingField = 'departure';
                } else {
                    this.departureDate = date;
                    this.selectingField = 'arrival';
                    
                    // Les deux dates sont sélectionnées, fermer et fetch le prix
                    if (this.arrivalDate && this.departureDate) {
                        var self = this;
                        setTimeout(function() {
                            self.closeCalendar();
                            self.syncDatesToJetBooking();
                            self.updateBrowserUrl();
                            self.fetchPrice();
                        }, 300);
                    }
                }
            }
            
            this.renderCalendar();
            this.updateWidgetDisplay();
            this.diffuserDates();
        },

        /** AMM_PONT_CALENDRIER : previent le calendrier du bas de page. */
        diffuserDates: function () {
            $(document).trigger('lodgify:dates', [{
                source: 'reservation',
                propertyId: this.propertyId,
                arrival: this.arrivalDate ? this.formatDate(this.arrivalDate) : null,
                departure: this.departureDate ? this.formatDate(this.departureDate) : null
            }]);
        },

        syncDatesToJetBooking: function() {
            // Synchroniser les dates avec le calendrier JetBooking et les boutons de la page
            if (!this.arrivalDate || !this.departureDate) return;
            
            var arrivalStr = this.formatDate(this.arrivalDate);
            var departureStr = this.formatDate(this.departureDate);
            
            console.log('ABW: Syncing dates to page:', arrivalStr, '-', departureStr);
            
            // Mettre à jour le widget JetSmartFilters Date Period
            var arrParts = arrivalStr.split('-');
            var depParts = departureStr.split('-');
            
            // Format DD/MM/YYYY pour l'affichage (format français)
            var arrDisplay = arrParts[2] + '/' + arrParts[1] + '/' + arrParts[0];
            var depDisplay = depParts[2] + '/' + depParts[1] + '/' + depParts[0];
            
            $('.jet-date-period-start').text(arrDisplay);
            $('.jet-date-period-end').text(depDisplay);
            
            // Format pour l'input caché JetSmartFilters
            var arrInput = arrParts[0] + '.' + parseInt(arrParts[1]) + '.' + parseInt(arrParts[2]);
            var depInput = depParts[0] + '.' + parseInt(depParts[1]) + '.' + parseInt(depParts[2]);
            $('.jet-date-period__datepicker-input').val(arrInput + '-' + depInput);
            
            // Mettre à jour SEULEMENT les boutons qui correspondent à ce property_id
            var self = this;
            var propertyId = String(this.propertyId);
            
            $('a[href*="checkout.lodgify.com"]').each(function() {
                var href = $(this).attr('href');
                
                // Vérifier si ce bouton correspond à notre property_id
                if (href.indexOf('/' + propertyId + '/') === -1) {
                    console.log('ABW: Skipping button for different property:', href);
                    return; // Skip this button
                }
                
                try {
                    var url = new URL(href);
                    url.searchParams.set('arrival', arrivalStr);
                    url.searchParams.set('departure', departureStr);
                    url.searchParams.set('adults', self.guests);
                    $(this).attr('href', url.toString());
                    console.log('ABW: Updated button URL for property ' + propertyId + ':', url.toString());
                } catch (e) {
                    console.log('ABW: Error updating URL:', e);
                }
            });
        },
        
        updateCalendarDisplay: function() {
            var i18n = typeof airbnbBooking !== 'undefined' ? airbnbBooking.i18n : {
                night: 'nuit', nights: 'nuits', addDate: 'Ajouter une date'
            };
            
            // Update header summary
            if (this.arrivalDate && this.departureDate) {
                var nights = this.getNights();
                var nightsText = nights + ' ' + (nights === 1 ? i18n.night : i18n.nights);
                var rangeText = this.formatDisplayDate(this.arrivalDate) + ' - ' + this.formatDisplayDate(this.departureDate);
                
                this.$popup.find('.abw-nights-count').text(nightsText);
                this.$popup.find('.abw-dates-range').text(rangeText);
            } else {
                this.$popup.find('.abw-nights-count').text('');
                this.$popup.find('.abw-dates-range').text(i18n.addDate);
            }
            
            // Update input fields
            var arrivalText = this.arrivalDate ? this.formatInputDate(this.arrivalDate) : i18n.addDate;
            var departureText = this.departureDate ? this.formatInputDate(this.departureDate) : i18n.addDate;
            
            this.$popup.find('.abw-input-arrival .abw-input-value').text(arrivalText);
            this.$popup.find('.abw-input-departure .abw-input-value').text(departureText);
            
            // Highlight active field
            this.$popup.find('.abw-input-field').removeClass('active');
            if (this.selectingField === 'arrival') {
                this.$popup.find('.abw-input-arrival').addClass('active');
            } else {
                this.$popup.find('.abw-input-departure').addClass('active');
            }
        },
        
        updateWidgetDisplay: function() {
            var $widget = this.$widget;
            var i18n = typeof airbnbBooking !== 'undefined' ? airbnbBooking.i18n : {
                addDate: 'Ajouter une date', reserve: 'Réserver', 
                checkAvailability: 'Vérifier la disponibilité', guest: 'voyageur'
            };
            
            // Update date fields
            var $arrival = $widget.find('.abw-arrival');
            var $departure = $widget.find('.abw-departure');
            
            if (this.arrivalDate) {
                $arrival.find('.abw-date-value').text(this.formatInputDate(this.arrivalDate));
                $arrival.find('input').val(this.formatDate(this.arrivalDate));
                $arrival.addClass('has-value');
            } else {
                $arrival.find('.abw-date-value').text(i18n.addDate);
                $arrival.find('input').val('');
                $arrival.removeClass('has-value');
            }
            
            if (this.departureDate) {
                $departure.find('.abw-date-value').text(this.formatInputDate(this.departureDate));
                $departure.find('input').val(this.formatDate(this.departureDate));
                $departure.addClass('has-value');
            } else {
                $departure.find('.abw-date-value').text(i18n.addDate);
                $departure.find('input').val('');
                $departure.removeClass('has-value');
            }
            
            // Toggle states - utiliser les labels personnalisés du widget
            if (this.arrivalDate && this.departureDate) {
                $widget.find('.abw-no-dates').hide();
                $widget.find('.abw-with-dates').show();
                $widget.find('.abw-submit-btn')
                    .removeClass('abw-check-availability')
                    .addClass('abw-reserve')
                    .text(this.labelReserve);
                $widget.find('.abw-reassurance').show();
            } else {
                $widget.find('.abw-no-dates').show();
                $widget.find('.abw-with-dates').hide();
                $widget.find('.abw-submit-btn')
                    .removeClass('abw-reserve')
                    .addClass('abw-check-availability')
                    .text(this.labelCheck);
                $widget.find('.abw-reassurance').hide();
            }
        },
        
        initGuestsDropdown: function() {
            console.log('ABW: Init guests dropdown, maxGuests:', this.maxGuests);
            
            // Initialiser avec 1 voyageur
            this.guests = 1;
            this.updateGuestsDisplay();
        },
        
        updateGuestsDisplay: function() {
            // Utiliser les labels personnalisés du widget (data-attributes)
            var guestWord = this.guests === 1 ? this.labelSingular : this.labelPlural;
            var text = this.guests + ' ' + guestWord;
            
            this.$widget.find('.abw-guests-value').text(text);
            this.$widget.find('input[name="guests"]').val(this.guests);
            
            // Mettre à jour la sélection dans la liste
            this.$widget.find('.abw-guest-option').removeClass('selected');
            this.$widget.find('.abw-guest-option[data-value="' + this.guests + '"]').addClass('selected');
            
            console.log('ABW: Guests updated to', this.guests, '/', this.maxGuests);
            
            // Mettre à jour l'URL avec le nombre de guests
            this.updateBrowserUrl();
        },
        
        updateBrowserUrl: function() {
            // Mettre à jour l'URL du navigateur avec les dates et guests sélectionnés
            var url = new URL(window.location.href);
            
            if (this.arrivalDate && this.departureDate) {
                url.searchParams.set('checkin', this.formatDate(this.arrivalDate));
                url.searchParams.set('checkout', this.formatDate(this.departureDate));
            } else {
                url.searchParams.delete('checkin');
                url.searchParams.delete('checkout');
            }
            
            if (this.guests && this.guests > 1) {
                url.searchParams.set('guests', this.guests);
            } else {
                url.searchParams.delete('guests');
            }
            
            // Mettre à jour l'URL sans recharger la page
            window.history.replaceState({}, '', url.toString());
            console.log('ABW: Browser URL updated:', url.toString());
        },
        
        fetchPrice: function() {
            var self = this;
            
            console.log('ABW: fetchPrice called', {
                propertyId: this.propertyId,
                arrivalDate: this.arrivalDate,
                departureDate: this.departureDate
            });
            
            if (!this.propertyId) {
                console.log('ABW: No property ID, cannot fetch price');
                return;
            }
            
            if (!this.arrivalDate || !this.departureDate) {
                console.log('ABW: Missing dates, cannot fetch price');
                return;
            }
            
            var checkIn = this.formatDate(this.arrivalDate);
            var checkOut = this.formatDate(this.departureDate);
            
            console.log('ABW: Fetching price for', checkIn, 'to', checkOut);
            
            this.$widget.addClass('loading');
            
            var ajaxUrl = typeof airbnbBooking !== 'undefined' ? airbnbBooking.ajaxurl : '/wp-admin/admin-ajax.php';
            var nonce = typeof airbnbBooking !== 'undefined' ? airbnbBooking.nonce : '';
            
            console.log('ABW: Making AJAX request to:', ajaxUrl);
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                timeout: 30000,
                data: {
                    action: 'lodgify_get_price',
                    nonce: nonce,
                    property_id: this.propertyId,
                    check_in: checkIn,
                    check_out: checkOut,
                    guests: this.guests
                },
                beforeSend: function() {
                    console.log('ABW: AJAX request starting...');
                },
                success: function(response) {
                    console.log('ABW: Price response:', response);
                    if (response.success && response.data) {
                        self.displayPrice(response.data);
                        self.updateBookingButton(response.data);
                    } else {
                        console.log('ABW: Price fetch failed:', response);
                        // Afficher un message d'erreur
                        self.$widget.find('.abw-price-period').text(response.data && response.data.message ? response.data.message : 'Prix non disponible');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('ABW: Price AJAX error:', status, error);
                    console.log('ABW: Response:', xhr.responseText);
                    self.$widget.find('.abw-price-period').text('Erreur de connexion');
                },
                complete: function() {
                    console.log('ABW: AJAX request complete');
                    self.$widget.removeClass('loading');
                }
            });
        },
        
        displayPrice: function(data) {
            console.log('ABW: displayPrice called with data:', data);
            
            var i18n = typeof airbnbBooking !== 'undefined' ? airbnbBooking.i18n : {
                'for': 'pour', night: 'nuit', nights: 'nuits'
            };
            
            var nights = this.getNights();
            var nightsText = i18n['for'] + ' ' + nights + ' ' + 
                           (nights === 1 ? i18n.night : i18n.nights);
            
            var $priceDisplay = this.$widget.find('.abw-price-display');
            console.log('ABW: Price display element found:', $priceDisplay.length);
            
            /* AMM_QUOTE : le devis Lodgify n'a pas repondu. On n'invente pas de
               total - on annonce que le prix exact viendra a l'etape suivante.
               Le bouton Book reste actif, son lien est mis a jour normalement. */
            if (data.price_source === 'unavailable' || data.total === null) {
                $priceDisplay.find('.abw-price-original').hide();
                $priceDisplay.find('.abw-price-current').text(
                    data.unavailable_message || 'Prix exact \u00e0 l\u2019\u00e9tape suivante');
                $priceDisplay.find('.abw-price-nights').text(nightsText);
                this.$widget.find('.abw-no-dates').hide();
                this.$widget.find('.abw-with-dates').show();
                this.$widget.find('.abw-reassurance').show();
                return;
            }

            // Total price
            var total = data.total || data.total_price || data.price || 0;
            console.log('ABW: Total price:', total);

            /* RACE_NUITS_20260922 : un devis qui echoue renvoie 0. Afficher
               « 0,00 » ferait croire a un sejour gratuit : on n'affiche rien
               et on laisse le bouton desactive. */
            if (!(parseFloat(total) > 0)) {
                this.devisOk = false;
                this.masquerPrix();
                this.majBouton();
                return;
            }
            this.devisOk = true;
            
            // Original price (barré)
            if (data.original_total && data.original_total > total) {
                $priceDisplay.find('.abw-price-original').text(this.formatPrice(data.original_total)).show();
            } else {
                $priceDisplay.find('.abw-price-original').hide();
            }
            
            // Prix actuel
            $priceDisplay.find('.abw-price-current').text(this.formatPrice(total));
            
            // Texte nuits
            $priceDisplay.find('.abw-price-nights').text(nightsText);
            
            // S'assurer que la div avec prix est visible
            this.$widget.find('.abw-no-dates').hide();
            this.$widget.find('.abw-with-dates').show();
            this.$widget.find('.abw-reassurance').show();
            
            // Afficher message d'erreur min_stay si nécessaire
            var $minStayError = this.$widget.find('.abw-min-stay-error');
            if (!$minStayError.length) {
                $minStayError = $('<div class="abw-min-stay-error" style="color: #dc3545; font-size: 13px; font-weight: 600; margin-top: 8px; padding: 8px 12px; background: #fff5f5; border: 1px solid #dc3545; border-radius: 4px; display: none;"></div>');
                this.$widget.find('.abw-price-display').after($minStayError);
            }
            
            if (data.min_stay_error && data.min_stay) {
                var minStayMessage = this.minStayMessageTemplate.replace('{min_stay}', data.min_stay);
                $minStayError.text(minStayMessage).show();
                this.$widget.find('.abw-submit-btn').prop('disabled', true).css('opacity', '0.5');
            } else {
                $minStayError.hide();
            }
            if (data.min_stay_error && data.min_stay) { this.devisOk = false; }
            this.majBouton();
            
            console.log('ABW: Price display updated, showing .abw-with-dates');
            
            // Mettre à jour les éléments existants sur la page
            this.updateExistingElements(data);
        },
        
        updateBookingButton: function(data) {
            // Stocker l'URL de réservation pour ce widget
            if (data.booking_url) {
                this.bookingUrl = data.booking_url;
                console.log('ABW: Booking URL saved:', this.bookingUrl);
                
                var $submitBtn = this.$widget.find('.abw-submit-btn');
                if ($submitBtn.length) {
                    $submitBtn.data('booking-url', data.booking_url);
                }
            }
            
            // Mettre à jour le texte du bouton avec le label personnalisé
            this.$widget.find('.abw-submit-btn').text(this.labelReserve);
        },
        
        updateExistingElements: function(data) {
            var self = this;
            var total = data.total || data.total_price || 0;
            
            // Update Total heading
            $('h1, h2, h3, h4, h5, h6, .elementor-heading-title').each(function() {
                var text = $(this).text();
                if (text.indexOf('Total:') > -1 || text.indexOf('Total :') > -1) {
                    var prefix = text.split(':')[0] + ': ';
                    $(this).text(prefix + self.formatPrice(total));
                }
            });
            
            // Update booking button URL
            var $bookBtn = $('a[href*="checkout.lodgify.com"]');
            if ($bookBtn.length && this.arrivalDate && this.departureDate) {
                var href = $bookBtn.attr('href');
                try {
                    var url = new URL(href);
                    url.searchParams.set('arrival', this.formatDate(this.arrivalDate));
                    url.searchParams.set('departure', this.formatDate(this.departureDate));
                    url.searchParams.set('adults', this.guests);
                    $bookBtn.attr('href', url.toString());
                } catch (e) {}
            }
        },
        
        goToCheckout: function() {
            // Vérifier qu'on a les données nécessaires
            if (!this.propertyId || !this.arrivalDate || !this.departureDate) {
                console.log('ABW: Cannot go to checkout - missing data');
                return;
            }
            
            var finalUrl = '';
            
            // Si on a une URL stockée, la modifier pour mettre à jour le nombre de guests
            if (this.bookingUrl) {
                try {
                    var url = new URL(this.bookingUrl);
                    url.searchParams.set('adults', this.guests);
                    finalUrl = url.toString();
                    console.log('ABW: Going to checkout with updated stored URL:', finalUrl);
                } catch (e) {
                    // Si erreur de parsing, construire manuellement
                    finalUrl = '';
                }
            }
            
            /* AMM_ACCOUNT : plus de repli en dur. L'ancien code reconstruisait
               l'URL sur 'thehills' et 'currency=USD' quand aucune URL n'etait
               disponible - un bien Amazing Stay partait donc chez The Hills, en
               dollars. On preferera toujours ne rien faire. */
            if (!finalUrl && this.checkoutUrl) {
                var base = this.checkoutUrl.replace(/\/+$/, '');
                finalUrl = base + '/addons'
                    + '?arrival=' + this.formatDate(this.arrivalDate)
                    + '&departure=' + this.formatDate(this.departureDate)
                    + '&adults=' + this.guests
                    + '&ref=bnbox';
            }

            if (!finalUrl) {
                this.disableBooking();
                return;
            }

            window.location.href = finalUrl;
        },
        
        /* AMM_ACCOUNT : neutralise la reservation sans casser le reste du widget.
           Le calendrier et les prix restent consultables. */
        disableBooking: function() {
            var msg = (typeof airbnbBooking !== 'undefined' && airbnbBooking.i18nBookingUnavailable)
                ? airbnbBooking.i18nBookingUnavailable
                : 'R\u00e9servation momentan\u00e9ment indisponible';

            var $btns = this.$widget.find('.abw-reserve-btn, .abw-submit-btn')
                .add(this.$widget.closest('.elementor-section').find('a[href*="checkout.lodgify.com"]'));

            $btns.each(function () {
                var $b = $(this);
                $b.prop('disabled', true)
                  .attr('aria-disabled', 'true')
                  .addClass('abw-booking-disabled')
                  .css({ 'pointer-events': 'none', 'opacity': 0.55 });
                if ($b.is('a')) { $b.removeAttr('href'); }
            });

            var $n = this.$widget.find('.abw-booking-notice');
            if (!$n.length) {
                $n = $('<div class="abw-booking-notice" role="status"></div>').appendTo(this.$widget);
            }
            $n.text(msg).show();
        },

        clearDates: function() {
            this.arrivalDate = null;
            this.departureDate = null;
            this.selectingField = 'arrival';
            this.renderCalendar();
            this.updateWidgetDisplay();
            this.diffuserDates();   // AMM_PONT_CALENDRIER : efface aussi en bas
        },
        
        prevMonth: function() {
            this.currentMonth.setMonth(this.currentMonth.getMonth() - 1);
            this.renderCalendar();
        },
        
        nextMonth: function() {
            this.currentMonth.setMonth(this.currentMonth.getMonth() + 1);
            this.renderCalendar();
        },
        
        checkUrlDates: function() {
            var self = this;
            var brut = (typeof this.urlInitiale === 'string' && this.urlInitiale !== '')
                ? this.urlInitiale
                : window.location.search;
            var urlParams = new URLSearchParams(brut);
            var foundDates = false;
            var foundGuests = false;
            
            // 1. Vérifier les nouveaux paramètres checkin/checkout/guests
            var checkin = urlParams.get('checkin');
            var checkout = urlParams.get('checkout');
            var guests = urlParams.get('guests');
            
            if (checkin && checkout) {
                var arrival = this.parseIsoDate(checkin);
                var departure = this.parseIsoDate(checkout);
                
                if (arrival && departure) {
                    this.arrivalDate = arrival;
                    this.departureDate = departure;
                    foundDates = true;
                    console.log('ABW: Loaded dates from URL params:', checkin, checkout);
                }
            }
            
            if (guests) {
                var guestsNum = parseInt(guests);
                if (guestsNum > 0 && guestsNum <= this.maxGuests) {
                    this.guests = guestsNum;
                    foundGuests = true;
                    console.log('ABW: Loaded guests from URL:', guestsNum);
                }
            }
            
            // 2. Fallback: format meta (JetSmartFilters)
            var meta = urlParams.get('meta');
            if (meta) {
                // Parser les dates depuis meta: checkin_checkout!date:YYYY.M.D-YYYY.M.D
                if (!foundDates && meta.indexOf('checkin_checkout!date:') !== -1) {
                    var datePart = meta.split('checkin_checkout!date:')[1];
                    if (datePart) {
                        // Séparer du reste des paramètres (séparés par ;)
                        datePart = datePart.split(';')[0];
                        var dates = datePart.split('-');
                        if (dates.length === 2) {
                            var arrival = this.parseUrlDate(dates[0]);
                            var departure = this.parseUrlDate(dates[1]);
                            
                            if (arrival && departure) {
                                this.arrivalDate = arrival;
                                this.departureDate = departure;
                                foundDates = true;
                                console.log('ABW: Loaded dates from meta:', dates[0], dates[1]);
                            }
                        }
                    }
                }
                
                // Parser le nombre de guests depuis meta: guest!compare-greater:X ou guest!is:X
                if (!foundGuests) {
                    var guestMatch = meta.match(/guest!(?:compare-greater|is):(\d+)/);
                    if (guestMatch) {
                        var guestsNum = parseInt(guestMatch[1]);
                        if (guestsNum > 0 && guestsNum <= this.maxGuests) {
                            this.guests = guestsNum;
                            foundGuests = true;
                            console.log('ABW: Loaded guests from meta:', guestsNum);
                        }
                    }
                }
            }
            
            // Mettre à jour l'affichage des guests si trouvé
            if (foundGuests) {
                this.updateGuestsDisplay();
                // Mettre à jour la sélection dans le dropdown
                this.$widget.find('.abw-guest-option').removeClass('selected');
                this.$widget.find('.abw-guest-option[data-value="' + this.guests + '"]').addClass('selected');
            }
            
            if (foundDates) {
                /* RACE_NUITS_20260922 : les dates de l'URL - celles qu'ecrit
                   updateBrowserUrl(), donc un rechargement ou un lien partage -
                   ne valent pas mieux qu'un clic. Elles ne sont appliquees
                   qu'une fois les nuits occupees connues, et revalidees. */
                var self = this;
                var poser = function () {
                    if (!self.revaliderSelection()) { self.updateBrowserUrl(); return; }
                    self.updateWidgetDisplay();
                    self.fetchPrice();
                };
                if (this.datesPretes) {
                    poser();
                } else {
                    this.selectionEnAttente = poser;
                    this.updateWidgetDisplay();
                }
            }
        },
        
        parseIsoDate: function(dateStr) {
            // Parse YYYY-MM-DD format
            var parts = dateStr.split('-');
            if (parts.length === 3) {
                return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            }
            return null;
        },
        
        parseUrlDate: function(dateStr) {
            var parts = dateStr.split('.');
            if (parts.length === 3) {
                return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            }
            return null;
        },
        
        // Utility functions
        formatDate: function(date) {
            var y = date.getFullYear();
            var m = String(date.getMonth() + 1).padStart(2, '0');
            var d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        },
        
        formatInputDate: function(date) {
            var d = String(date.getDate()).padStart(2, '0');
            var m = String(date.getMonth() + 1).padStart(2, '0');
            var y = date.getFullYear();
            return d + '/' + m + '/' + y;
        },
        
        formatDisplayDate: function(date) {
            var monthNames = typeof airbnbBooking !== 'undefined' ? airbnbBooking.months : 
                ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
            var d = date.getDate();
            var m = monthNames[date.getMonth()].substring(0, 3).toLowerCase();
            var y = date.getFullYear();
            return d + ' ' + m + '. ' + y;
        },
        
        formatPrice: function(amount) {
            var num = parseFloat(amount).toFixed(2);
            if (this.currency === '€') {
                return num.replace('.', ',') + ' €';
            }
            return this.currency + num;
        },
        
        parseDate: function(dateStr) {
            var parts = dateStr.split('-');
            return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        },
        
        isSameDay: function(d1, d2) {
            return d1.getFullYear() === d2.getFullYear() &&
                   d1.getMonth() === d2.getMonth() &&
                   d1.getDate() === d2.getDate();
        },
        
        getNights: function() {
            if (!this.arrivalDate || !this.departureDate) return 0;
            var diff = this.departureDate.getTime() - this.arrivalDate.getTime();
            return Math.round(diff / (1000 * 60 * 60 * 24));
        },
        
        /* unavailableDates = liste des NUITS occupees. Une nuit N est la nuit
           du jour N au jour N+1. Les deux regles ci-dessous en decoulent. */

        // ARRIVEE le jour A : on dort la nuit A. Inchange par rapport a l'existant.
        isUnavailableForArrival: function(dateStr) {
            return this.unavailableDates.indexOf(dateStr) !== -1;
        },

        // DEPART le jour D : la derniere nuit est D-1. La nuit D ne compte pas.
        isUnavailableForDeparture: function(dateStr) {
            var d = this.parseDate(dateStr);

            if (this.arrivalDate) {
                // un depart doit suivre l'arrivee
                if (d <= this.arrivalDate) { return true; }
                // toutes les nuits de l'arrivee a D-1 doivent etre libres
                var cur = new Date(this.arrivalDate.getTime());
                while (cur < d) {
                    if (this.unavailableDates.indexOf(this.formatDate(cur)) !== -1) {
                        return true;
                    }
                    cur.setDate(cur.getDate() + 1);
                }
                return false;
            }

            // pas encore d'arrivee choisie : D est un depart plausible si la
            // nuit precedente est libre (il existe au moins un sejour finissant la)
            var veille = new Date(d.getTime());
            veille.setDate(veille.getDate() - 1);
            return this.unavailableDates.indexOf(this.formatDate(veille)) !== -1;
        },

        // message bref affiche dans le calendrier, puis efface
        showDayNotice: function(message) {
            var $body = this.$popup.find('.abw-calendar-body');
            if (!$body.length) { return; }
            var $n = $body.find('.abw-day-notice');
            if (!$n.length) {
                $n = $('<div class="abw-day-notice" role="status" aria-live="polite"></div>').prependTo($body);
            }
            $n.text(message).stop(true, true).fadeIn(120);
            clearTimeout(this._noticeTimer);
            this._noticeTimer = setTimeout(function () { $n.fadeOut(200); }, 3200);
        },

        // conservee : plus appelee dans ce fichier, gardee pour compatibilite
        isUnavailable: function(dateStr) {
            return this.isUnavailableForArrival(dateStr);
        }
    };
    
    $(document).ready(function() {
        AirbnbBookingWidget.init();
    });
    
})(jQuery);
