/**
 * Lodgify Ajax Price - Mise à jour dynamique des prix
 * Compatible avec JetSmartFilters
 */

(function($) {
    'use strict';
    
    // Configuration
    var LodgifyAjaxPrice = {
        
        // Flags pour éviter les appels répétitifs
        _debounceTimer: null,
        _isUpdating: false,
        _lastDates: null,
        
        init: function() {
            this.bindEvents();
            this.parseUrlDates();
            
            // Sur page propriété, vérifier aussi le JetDatePeriod au chargement
            var self = this;
            setTimeout(function() {
                self.checkJetDatePeriod();
            }, 500);
        },
        
        /**
         * Écouter les changements de filtres JetSmartFilters
         */
        bindEvents: function() {
            var self = this;
            
            // Écouter les changements d'URL (JetSmartFilters utilise l'historique)
            $(window).on('popstate', function() {
                self.parseUrlDates();
            });
            
            // Écouter les événements JetSmartFilters
            $(document).on('jet-smart-filters/inited', function() {
                self.parseUrlDates();
                
                // S'abonner aux changements d'items actifs
                if (typeof JetSmartFilters !== 'undefined' && JetSmartFilters.events) {
                    JetSmartFilters.events.subscribe('activeItems/change', function(activeItems) {
                        setTimeout(function() {
                            self.parseUrlDates();
                        }, 300);
                    });
                    
                    // Écouter aussi la fin du rendu AJAX
                    JetSmartFilters.events.subscribe('ajaxFilters/end-loading', function() {
                        setTimeout(function() {
                            self.parseUrlDates();
                        }, 300);
                    });
                }
            });
            
            // Écouter les soumissions de filtres
            $(document).on('jet-filter-content-rendered', function() {
                setTimeout(function() {
                    self.parseUrlDates();
                }, 100);
            });
            
            // Écouter tous les événements JetSmartFilters
            $(document).on('jet-smart-filters/before-ajax-request jet-smart-filters/after-ajax-request', function(e) {
                setTimeout(function() {
                    self.parseUrlDates();
                }, 200);
            });
            
            // Observer les changements dans l'URL
            var lastUrl = location.href;
            new MutationObserver(function() {
                if (location.href !== lastUrl) {
                    lastUrl = location.href;
                    self.parseUrlDates();
                }
            }).observe(document, {subtree: true, childList: true});
            
            // Écouter les changements dans les inputs de date
            $(document).on('change', '.jet-date-range input, .jet-smart-filters-datepicker, .jet-date-period__input', function() {
                setTimeout(function() {
                    self.parseUrlDates();
                }, 300);
            });
            
            // Clic sur le bouton de sélection de date
            $(document).on('click', '.jet-date-period__datepicker-button', function() {
            });
            
            // === JET BOOKING CALENDAR & DATE PICKER (page propriété) ===
            
            // Debounce pour éviter les appels multiples
            var calendarDebounce = null;
            
            // Écouter les clics sur les jours des calendriers
            $(document).on('click', '.jet-booking-calendar .day.valid, .date-picker-wrapper .day.valid', function() {
                if (calendarDebounce) clearTimeout(calendarDebounce);
                calendarDebounce = setTimeout(function() {
                    self.readJetBookingDates();
                }, 300);
            });
            
            // Observer les changements de classes sur les calendriers
            setTimeout(function() {
                var calendars = [
                    '.jet-booking-calendar',
                    '.date-picker-wrapper'
                ];
                
                calendars.forEach(function(selector) {
                    var $calendar = $(selector).first();
                    if ($calendar.length) {
                        var observer = new MutationObserver(function() {
                            if (calendarDebounce) clearTimeout(calendarDebounce);
                            calendarDebounce = setTimeout(function() {
                                self.readJetBookingDates();
                            }, 300);
                        });
                        observer.observe($calendar[0], { 
                            subtree: true, 
                            attributes: true, 
                            attributeFilter: ['class'] 
                        });
                    }
                });
            }, 1000);
            
            // Réinitialiser les observers quand le DOM change
            var domObserver = new MutationObserver(function(mutations) {
                var shouldReinit = false;
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && 
                                ($(node).find('.jet-date-period-start').length || 
                                 $(node).hasClass('jet-date-period-start'))) {
                                shouldReinit = true;
                            }
                        });
                    }
                });
                if (shouldReinit) {
                    self.initDatePeriodObservers();
                }
            });
            domObserver.observe(document.body, { childList: true, subtree: true });
            
            // Initialiser les observers pour les éléments existants
            this.initDatePeriodObservers();
        },
        
        /**
         * Initialiser les observers pour les date pickers JetEngine
         */
        initDatePeriodObservers: function() {
            var self = this;
            
            var datePickerObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                        setTimeout(function() {
                            self.checkJetDatePeriod();
                        }, 100);
                    }
                });
            });
            
            $('.jet-date-period__datepicker-button, .jet-date-period-start, .jet-date-period-end').each(function() {
                if (!$(this).data('lodgify-observed')) {
                    datePickerObserver.observe(this, { 
                        childList: true, 
                        characterData: true,
                        subtree: true
                    });
                    $(this).data('lodgify-observed', true);
                }
            });
            
            // === CALENDRIER SUR PAGE PROPRIÉTÉ ===
            
            // Écouter les clics sur les jours du calendrier (Lodgify Availability Calendar)
            $(document).on('click', '.lodgify-calendar td, .availability-calendar td, .calendar-day, [class*="calendar"] td', function() {
                setTimeout(function() {
                    self.checkCalendarDates();
                }, 100);
            });
            
            // Écouter les changements dans les inputs de date du calendrier
            $(document).on('change', 'input[name*="check"], input[name*="arrival"], input[name*="departure"], input[name*="date"]', function() {
                setTimeout(function() {
                    self.checkCalendarDates();
                }, 100);
            });
            
            // Écouter les événements personnalisés de calendrier
            $(document).on('lodgify:dates-changed calendar:dates-changed', function(e, data) {
                if (data && data.checkIn && data.checkOut) {
                    self.updatePropertyPage(data.checkIn, data.checkOut);
                }
            });
            
            // Observer les changements dans les éléments de calendrier
            var calendarObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' || mutation.type === 'childList') {
                        var target = $(mutation.target);
                        if (target.closest('[class*="calendar"]').length || 
                            target.hasClass('selected') || 
                            target.attr('class')?.includes('selected')) {
                            self.checkCalendarDates();
                        }
                    }
                });
            });
            
            // Observer les calendriers sur la page
            $('[class*="calendar"], .lodgify-calendar, .availability-calendar').each(function() {
                calendarObserver.observe(this, { 
                    attributes: true, 
                    childList: true, 
                    subtree: true,
                    attributeFilter: ['class']
                });
            });
        },
        
        /**
         * Synchroniser les dates du calendrier Lodgify vers le widget date
         */
        syncCalendarToDateWidget: function() {
            var self = this;
            
            // Chercher les dates sélectionnées dans le calendrier Lodgify
            // Le calendrier Lodgify utilise généralement des classes comme 'selected', 'check-in', 'check-out'
            var $calendar = $('.lodgify-calendar, [class*="lodgify"][class*="calendar"], .availability-calendar').first();
            
            if (!$calendar.length) {
                return;
            }
            
            var checkIn = null;
            var checkOut = null;
            
            // Méthode 1: Chercher les éléments avec data-date et classe selected/check-in/check-out
            var $checkInEl = $calendar.find('.check-in, .checkin, .start-date, [class*="check-in"]').first();
            var $checkOutEl = $calendar.find('.check-out, .checkout, .end-date, [class*="check-out"]').first();
            
            if ($checkInEl.length) {
                checkIn = $checkInEl.data('date') || $checkInEl.attr('data-date');
            }
            if ($checkOutEl.length) {
                checkOut = $checkOutEl.data('date') || $checkOutEl.attr('data-date');
            }
            
            // Méthode 2: Chercher tous les jours sélectionnés
            if (!checkIn || !checkOut) {
                var $selectedDays = $calendar.find('.selected, .in-range, [class*="selected"]').filter('[data-date]');
                if ($selectedDays.length >= 2) {
                    var dates = [];
                    $selectedDays.each(function() {
                        var d = $(this).data('date') || $(this).attr('data-date');
                        if (d) dates.push(d);
                    });
                    dates.sort();
                    checkIn = dates[0];
                    checkOut = dates[dates.length - 1];
                }
            }
            
            // Méthode 3: Chercher dans les inputs cachés du calendrier
            if (!checkIn || !checkOut) {
                var $arrInput = $calendar.find('input[name*="arrival"], input[name*="check_in"], input.check-in').first();
                var $depInput = $calendar.find('input[name*="departure"], input[name*="check_out"], input.check-out').first();
                if ($arrInput.length) checkIn = $arrInput.val();
                if ($depInput.length) checkOut = $depInput.val();
            }
            
            
            if (!checkIn || !checkOut) {
                return;
            }
            
            // Formater les dates pour le widget (MM/DD/YYYY)
            var checkInFormatted = this.formatDateForWidget(checkIn);
            var checkOutFormatted = this.formatDateForWidget(checkOut);
            
            
            // Mettre à jour le widget de date à droite
            var $startDate = $('.jet-date-period-start');
            var $endDate = $('.jet-date-period-end');
            
            if ($startDate.length && $endDate.length) {
                $startDate.text(checkInFormatted);
                $endDate.text(checkOutFormatted);
                
                // Déclencher un événement pour que le widget recalcule le prix
                $(document).trigger('lodgify:dates-synced', {
                    checkIn: checkIn,
                    checkOut: checkOut
                });
            }
        },
        
        /**
         * Formater une date pour le widget (MM/DD/YYYY)
         */
        formatDateForWidget: function(dateStr) {
            if (!dateStr) return '';
            
            var date;
            
            // Si c'est déjà au format YYYY-MM-DD
            if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
                var parts = dateStr.split('-');
                return parts[1] + '/' + parts[2] + '/' + parts[0];
            }
            
            // Si c'est au format DD/MM/YYYY ou MM/DD/YYYY, retourner tel quel
            if (dateStr.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                return dateStr;
            }
            
            // Essayer de parser comme timestamp ou autre
            try {
                date = new Date(dateStr);
                if (!isNaN(date.getTime())) {
                    var month = String(date.getMonth() + 1).padStart(2, '0');
                    var day = String(date.getDate()).padStart(2, '0');
                    var year = date.getFullYear();
                    return month + '/' + day + '/' + year;
                }
            } catch (e) {
            }
            
            return dateStr;
        },
        
        /**
         * Vérifier les dates dans le JetEngine Date Period picker
         */
        checkJetDatePeriod: function() {
            var self = this;
            
            // Chercher les dates dans le date picker JetEngine
            var $startDate = $('.jet-date-period-start').first();
            var $endDate = $('.jet-date-period-end').first();
            
            if (!$startDate.length || !$endDate.length) {
                return;
            }
            
            var startText = $startDate.text().trim();
            var endText = $endDate.text().trim();
            
            
            if (!startText || !endText || startText === 'Check-in' || endText === 'Check-out') {
                return;
            }
            
            // Parser les dates (format MM/DD/YYYY)
            var checkIn = this.parseJetDate(startText);
            var checkOut = this.parseJetDate(endText);
            
            
            if (checkIn && checkOut) {
                this.updatePropertyPage(checkIn, checkOut);
            }
        },
        
        /**
         * Parser une date au format MM/DD/YYYY vers YYYY-MM-DD
         */
        parseJetDate: function(dateStr) {
            // Format attendu: MM/DD/YYYY ou DD/MM/YYYY
            var parts = dateStr.split('/');
            if (parts.length !== 3) return null;
            
            var month, day, year;
            
            // Détecter le format (US: MM/DD/YYYY vs EU: DD/MM/YYYY)
            if (parseInt(parts[2]) > 1000) {
                // Format MM/DD/YYYY ou DD/MM/YYYY avec année en dernier
                year = parts[2];
                // Supposer US format (MM/DD/YYYY)
                month = parts[0].padStart(2, '0');
                day = parts[1].padStart(2, '0');
            } else {
                return null;
            }
            
            return year + '-' + month + '-' + day;
        },
        
        /**
         * Vérifier les dates sélectionnées dans le calendrier (page propriété)
         */
        checkCalendarDates: function() {
            var self = this;
            var checkIn = null;
            var checkOut = null;
            
            // Méthode 1: Chercher dans les inputs
            var $arrivalInput = $('input[name*="arrival"], input[name*="check_in"], input[name*="checkin"], #arrival, #check-in');
            var $departureInput = $('input[name*="departure"], input[name*="check_out"], input[name*="checkout"], #departure, #check-out');
            
            if ($arrivalInput.length && $departureInput.length) {
                checkIn = $arrivalInput.val();
                checkOut = $departureInput.val();
            }
            
            // Méthode 2: Chercher les jours sélectionnés dans le calendrier
            if (!checkIn || !checkOut) {
                var $selectedDays = $('.selected, .calendar-selected, [class*="selected"]').filter('[data-date]');
                if ($selectedDays.length >= 2) {
                    var dates = [];
                    $selectedDays.each(function() {
                        var date = $(this).data('date') || $(this).attr('data-date');
                        if (date) dates.push(date);
                    });
                    dates.sort();
                    if (dates.length >= 2) {
                        checkIn = dates[0];
                        checkOut = dates[dates.length - 1];
                    }
                }
            }
            
            // Méthode 3: Chercher dans l'URL
            if (!checkIn || !checkOut) {
                var urlDates = this.parseUrlDatesOnly();
                if (urlDates) {
                    checkIn = urlDates.checkIn;
                    checkOut = urlDates.checkOut;
                }
            }
            
            
            if (checkIn && checkOut) {
                this.updatePropertyPage(checkIn, checkOut);
            }
        },
        
        /**
         * Parser les dates depuis l'URL (retourne les dates sans mise à jour)
         */
        parseUrlDatesOnly: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var meta = urlParams.get('meta');
            
            if (!meta || meta.indexOf('checkin_checkout!date:') === -1) {
                return null;
            }
            
            var datePart = meta.split('checkin_checkout!date:')[1];
            if (!datePart) return null;
            
            var dates = datePart.split('-');
            if (dates.length !== 2) return null;
            
            var checkIn = this.formatDate(dates[0]);
            var checkOut = this.formatDate(dates[1]);
            
            if (!checkIn || !checkOut) return null;
            
            return { checkIn: checkIn, checkOut: checkOut };
        },
        
        /**
         * Lire les dates sélectionnées dans le calendrier JetBooking
         */
        readJetBookingDates: function() {
            // Chercher dans les deux types de calendriers
            var $calendar = $('.jet-booking-calendar, .date-picker-wrapper').first();
            if (!$calendar.length) return;
            
            // Chercher les jours sélectionnés
            var $firstDate = $calendar.find('.first-date-selected');
            var $lastDate = $calendar.find('.last-date-selected');
            
            if (!$firstDate.length || !$lastDate.length) return;
            
            // Lire les timestamps
            var startTime = parseInt($firstDate.attr('time'));
            var endTime = parseInt($lastDate.attr('time'));
            
            if (!startTime || !endTime) return;
            
            // Convertir en dates YYYY-MM-DD (sans problème de timezone)
            var startDate = new Date(startTime);
            var endDate = new Date(endTime);
            
            var checkIn = startDate.getFullYear() + '-' + 
                          String(startDate.getMonth() + 1).padStart(2, '0') + '-' + 
                          String(startDate.getDate()).padStart(2, '0');
            
            var checkOut = endDate.getFullYear() + '-' + 
                           String(endDate.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(endDate.getDate()).padStart(2, '0');
            
            // Mettre à jour le widget date-period
            this.updateDatePeriodWidget(checkIn, checkOut);
            
            // Mettre à jour le prix
            this.updatePropertyPage(checkIn, checkOut);
        },
        
        /**
         * Mettre à jour le widget JetSmartFilters Date Period
         */
        updateDatePeriodWidget: function(checkIn, checkOut) {
            // Format pour l'affichage: MM/DD/YYYY
            var startParts = checkIn.split('-');
            var endParts = checkOut.split('-');
            
            var startDisplay = startParts[1] + '/' + startParts[2] + '/' + startParts[0];
            var endDisplay = endParts[1] + '/' + endParts[2] + '/' + endParts[0];
            
            // Format pour l'input: YYYY.M.D-YYYY.M.D
            var startInput = startParts[0] + '.' + parseInt(startParts[1]) + '.' + parseInt(startParts[2]);
            var endInput = endParts[0] + '.' + parseInt(endParts[1]) + '.' + parseInt(endParts[2]);
            
            // Mettre à jour l'affichage
            $('.jet-date-period-start').text(startDisplay);
            $('.jet-date-period-end').text(endDisplay);
            
            // Mettre à jour l'input
            $('.jet-date-period__datepicker-input').val(startInput + '-' + endInput);
            
            // Mettre à jour le bouton de réservation Lodgify
            var $bookBtn = $('a[href*="checkout.lodgify.com"]');
            if ($bookBtn.length) {
                var href = $bookBtn.attr('href');
                try {
                    var url = new URL(href);
                    url.searchParams.set('arrival', checkIn);
                    url.searchParams.set('departure', checkOut);
                    $bookBtn.attr('href', url.toString());
                } catch (e) {}
            }
        },
        
        /**
         * Mettre à jour les éléments sur la page propriété (avec debounce)
         */
        updatePropertyPage: function(checkIn, checkOut) {
            var self = this;
            
            // Éviter les appels répétitifs avec les mêmes dates
            var dateKey = checkIn + '-' + checkOut;
            if (this._lastDates === dateKey) {
                return;
            }
            
            // Debounce - annuler le timer précédent
            if (this._debounceTimer) {
                clearTimeout(this._debounceTimer);
            }
            
            // Éviter les appels multiples simultanés
            if (this._isUpdating) {
                return;
            }
            
            this._debounceTimer = setTimeout(function() {
                self._doUpdatePropertyPage(checkIn, checkOut, dateKey);
            }, 500);
        },
        
        /**
         * Exécuter la mise à jour (appelé après debounce)
         */
        _doUpdatePropertyPage: function(checkIn, checkOut, dateKey) {
            var self = this;
            
            // Récupérer le property ID depuis la page
            var propertyId = this.getPropertyIdFromPage();
            
            if (!propertyId) {
                return;
            }
            
            this._isUpdating = true;
            this._lastDates = dateKey;
            
            // Récupérer le nombre de guests depuis l'URL
            var guests = this.getGuestsFromUrl();
            
            // Appel AJAX pour récupérer le prix
            $.ajax({
                url: lodgifyAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'lodgify_get_price',
                    nonce: lodgifyAjax.nonce,
                    property_id: propertyId,
                    check_in: checkIn,
                    check_out: checkOut,
                    guests: guests
                },
                success: function(response) {
                    if (response.success) {
                        self.updatePropertyPageElements(response.data, checkIn, checkOut);
                    }
                },
                complete: function() {
                    self._isUpdating = false;
                }
            });
        },
        
        /**
         * Récupérer le nombre de guests depuis l'URL
         */
        getGuestsFromUrl: function() {
            var guests = 2; // Défaut
            var urlParams = new URLSearchParams(window.location.search);
            var meta = urlParams.get('meta');
            
            if (meta) {
                // Format: guest!compare-greater:X ou guest!is:X
                var match = meta.match(/guest!(?:compare-greater|is):(\d+)/);
                if (match) {
                    guests = parseInt(match[1]);
                }
            }
            
            return Math.max(1, guests);
        },
        
        /**
         * Récupérer le property ID depuis la page
         */
        getPropertyIdFromPage: function() {
            // 1. Chercher dans un élément avec data-rental-id
            var $rentalEl = $('[data-rental-id]').first();
            if ($rentalEl.length) {
                return $rentalEl.data('rental-id');
            }
            
            // 2. Chercher dans un élément caché
            var $hidden = $('.lodgify-rental-id, #rental-id, [name="rental_id"]').first();
            if ($hidden.length) {
                return $hidden.val() || $hidden.text().trim() || $hidden.data('id');
            }
            
            // 3. Chercher dans les widgets Lodgify
            var $widget = $('.lodgify-ajax-total, .lodgify-booking-btn').first();
            if ($widget.length) {
                return $widget.data('property-id');
            }
            
            // 4. Chercher dans le bouton de réservation Lodgify
            var $bookingLink = $('a[href*="checkout.lodgify.com"]').first();
            if ($bookingLink.length) {
                var href = $bookingLink.attr('href');
                var match = href.match(/\/(\d+)\//);
                if (match) return match[1];
            }
            
            return null;
        },
        
        /**
         * Mettre à jour les éléments de la page propriété avec les nouvelles données
         */
        updatePropertyPageElements: function(data, checkIn, checkOut) {
            var symbol = data.currency_symbol || '$';
            
            // Formater le prix
            var formatPrice = function(amount) {
                var num = parseFloat(amount).toFixed(2);
                if (symbol === '€') {
                    return num.replace('.', ',') + '€';
                }
                return '$' + num;
            };
            
            // 1. Mettre à jour le prix total
            // Chercher les headings qui contiennent "Total:"
            var $totalElements = $('.lodgify-total-heading, .lodgify-ajax-total, [class*="total-price"]');
            
            // Si aucun élément trouvé, chercher un heading avec "Total:" dans le texte
            if (!$totalElements.length) {
                $('h1, h2, h3, h4, h5, h6, .elementor-heading-title').each(function() {
                    if ($(this).text().indexOf('Total:') > -1 || $(this).text().indexOf('Total :') > -1) {
                        $totalElements = $totalElements.add($(this));
                    }
                });
            }
            
            $totalElements.each(function() {
                var $el = $(this);
                if ($el.hasClass('lodgify-ajax-total')) {
                    // Widget complet - laisser le widget gérer
                } else {
                    // Heading simple
                    var text = $el.text();
                    var prefix = 'Total: ';
                    if (text.indexOf(':') > -1) {
                        prefix = text.split(':')[0] + ': ';
                    }
                    $el.text(prefix + formatPrice(data.total));
                }
            });
            
            // 2. Mettre à jour le bouton de réservation
            var $bookingLinks = $('a[href*="checkout.lodgify.com"], .lodgify-booking-btn');
            $bookingLinks.each(function() {
                var $link = $(this);
                var href = $link.attr('href');
                if (href) {
                    try {
                        var url = new URL(href);
                        url.searchParams.set('arrival', checkIn);
                        url.searchParams.set('departure', checkOut);
                        $link.attr('href', url.toString());
                    } catch (e) {
                    }
                }
            });
            
            // 3. Mettre à jour le prix par nuit si présent
            if (data.price_per_day) {
                $('.lodgify-price-per-day, [class*="price-per-night"]').each(function() {
                    $(this).text(formatPrice(data.price_per_day) + '/night');
                });
            }
            
            // 4. Mettre à jour le nombre de nuits
            if (data.nights) {
                $('.lodgify-nights, [class*="nights-count"]').each(function() {
                    $(this).text(data.nights + ' nights');
                });
            }
        },
        
        /**
         * Parser les dates depuis l'URL
         */
        parseUrlDates: function() {
            
            var urlParams = new URLSearchParams(window.location.search);
            var meta = urlParams.get('meta');
            
            
            if (!meta || meta.indexOf('checkin_checkout!date:') === -1) {
                this.showNoDates();
                return;
            }
            
            // Extraire les dates
            var datePart = meta.split('checkin_checkout!date:')[1];
            if (!datePart) {
                this.showNoDates();
                return;
            }
            
            var dates = datePart.split('-');
            if (dates.length !== 2) {
                this.showNoDates();
                return;
            }
            
            // Convertir les dates du format YYYY.M.D vers YYYY-MM-DD
            var checkIn = this.formatDate(dates[0]);
            var checkOut = this.formatDate(dates[1]);
            
            if (!checkIn || !checkOut) {
                this.showNoDates();
                return;
            }
            
            
            // Mettre à jour tous les widgets de prix
            this.updateAllPriceWidgets(checkIn, checkOut);
        },
        
        /**
         * Formater une date de YYYY.M.D vers YYYY-MM-DD
         */
        formatDate: function(dateStr) {
            var parts = dateStr.split('.');
            if (parts.length !== 3) return null;
            
            var year = parts[0];
            var month = parts[1].padStart(2, '0');
            var day = parts[2].padStart(2, '0');
            
            return year + '-' + month + '-' + day;
        },
        
        /**
         * Afficher le message "pas de dates"
         */
        showNoDates: function() {
            $('.lodgify-ajax-total').each(function() {
                var $container = $(this);
                var noDatesText = $container.data('no-dates-text') || 'Select dates to see the price';
                $container.html('<div class="lodgify-no-dates">' + noDatesText + '</div>');
            });
            
            // Mettre à jour les boutons de réservation
            $('.lodgify-booking-btn').each(function() {
                var $btn = $(this);
                var noDatesText = $btn.closest('.lodgify-booking-btn-wrapper').data('no-dates-text') || 'Check Availability';
                $btn.text(noDatesText);
            });
        },
        
        /**
         * Mettre à jour tous les widgets de prix
         */
        updateAllPriceWidgets: function(checkIn, checkOut) {
            var self = this;
            
            // 1. Widgets avec classe lodgify-ajax-total
            var widgets = $('.lodgify-ajax-total');
            
            widgets.each(function() {
                self.updatePriceWidget($(this), checkIn, checkOut);
            });
            
            // 2. Headings avec classe lodgify-total-heading (dans les listings JetEngine)
            var headings = $('.lodgify-total-heading');
            
            headings.each(function() {
                self.updatePriceHeading($(this), checkIn, checkOut);
            });
            
            // Mettre à jour les boutons de réservation
            $('.lodgify-booking-btn').each(function() {
                self.updateBookingButton($(this), checkIn, checkOut);
            });
        },
        
        /**
         * Mettre à jour un Heading de prix (dans un listing JetEngine)
         */
        updatePriceHeading: function($heading, checkIn, checkOut) {
            var self = this;
            
            // Chercher le rental-id dans la carte parente
            var $card = $heading.closest('.jet-listing-grid__item, .elementor-element, [data-rental-id]');
            var propertyId = null;
            
            // Chercher un élément avec data-rental-id ou classe lodgify-rental-id
            var $rentalIdEl = $card.find('[data-rental-id]').first();
            if ($rentalIdEl.length) {
                propertyId = $rentalIdEl.data('rental-id');
            }
            
            // Sinon chercher un span/div caché avec classe lodgify-rental-id
            if (!propertyId) {
                var $hiddenId = $card.find('.lodgify-rental-id').first();
                if ($hiddenId.length) {
                    propertyId = $hiddenId.text().trim() || $hiddenId.data('id');
                }
            }
            
            
            if (!propertyId) {
                return;
            }
            
            // Sauvegarder le texte original
            var originalText = $heading.text();
            var prefix = 'Total: ';
            if (originalText.indexOf(':') > -1) {
                prefix = originalText.split(':')[0] + ': ';
            }
            
            $heading.text(prefix + 'Loading...');
            
            // Appel AJAX
            $.ajax({
                url: lodgifyAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'lodgify_get_price',
                    nonce: lodgifyAjax.nonce,
                    property_id: propertyId,
                    check_in: checkIn,
                    check_out: checkOut
                },
                success: function(response) {
                    if (response.success && response.data.total) {
                        var symbol = response.data.currency_symbol || '$';
                        var total = parseFloat(response.data.total).toFixed(2);
                        // Format avec virgule pour EUR
                        if (symbol === '€') {
                            total = total.replace('.', ',');
                            $heading.text(prefix + total + '€');
                        } else {
                            $heading.text(prefix + '$' + total);
                        }
                    } else {
                        $heading.text(prefix + 'N/A');
                    }
                },
                error: function() {
                    $heading.text(prefix + 'Error');
                }
            });
        },
        
        /**
         * Mettre à jour un widget de prix individuel
         */
        updatePriceWidget: function($container, checkIn, checkOut) {
            var self = this;
            var propertyId = $container.data('property-id');
            
            
            if (!propertyId) {
                return;
            }
            
            var loadingText = $container.data('loading-text') || 'Calculating...';
            $container.html('<div class="lodgify-loading">' + loadingText + '</div>');
            
            // Récupérer les paramètres
            var taxRate = parseFloat($container.data('tax-rate')) || 0;
            var cleaningFee = parseFloat($container.data('cleaning-fee')) || 0;
            var showBreakdown = $container.data('show-breakdown') === '1';
            var labelNights = $container.data('label-nights') || 'nights';
            var labelTaxes = $container.data('label-taxes') || 'Taxes';
            var labelCleaning = $container.data('label-cleaning') || 'Cleaning fee';
            var labelTotal = $container.data('label-total') || 'Total';
            
            // Appel AJAX
            $.ajax({
                url: lodgifyAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'lodgify_get_price',
                    nonce: lodgifyAjax.nonce,
                    property_id: propertyId,
                    check_in: checkIn,
                    check_out: checkOut,
                    tax_rate: taxRate,
                    cleaning_fee: cleaningFee
                },
                success: function(response) {
                    if (response.success) {
                        self.renderPriceBreakdown($container, response.data, {
                            showBreakdown: showBreakdown,
                            labelNights: labelNights,
                            labelTaxes: labelTaxes,
                            labelCleaning: labelCleaning,
                            labelTotal: labelTotal,
                            taxRate: taxRate
                        });
                    } else {
                        $container.html('<div class="lodgify-error">Price not available</div>');
                    }
                },
                error: function() {
                    $container.html('<div class="lodgify-error">Error loading price</div>');
                }
            });
        },
        
        /**
         * Afficher le détail du prix (données réelles API Lodgify)
         */
        renderPriceBreakdown: function($container, data, options) {
            var self = this;
            var html = '';
            
            var formatPrice = function(amount) {
                if (data.currency_symbol === '€') {
                    return self.formatNumber(amount) + '€';
                }
                return '$' + self.formatNumber(amount);
            };
            
            if (options.showBreakdown) {
                html += '<div class="lodgify-breakdown">';
                
                // Ligne des nuits
                html += '<div class="lodgify-breakdown-item">';
                html += '<span class="lodgify-label">' + data.nights + ' ' + options.labelNights + '</span>';
                html += '<span class="lodgify-value">' + formatPrice(data.nights_total || data.base_price) + '</span>';
                html += '</div>';
                
                // Frais de ménage (depuis l'API)
                if (data.cleaning_fee > 0) {
                    var cleaningLabel = data.cleaning_name || options.labelCleaning;
                    html += '<div class="lodgify-breakdown-item">';
                    html += '<span class="lodgify-label">' + cleaningLabel + '</span>';
                    html += '<span class="lodgify-value">' + formatPrice(data.cleaning_fee) + '</span>';
                    html += '</div>';
                }
                
                // Taxes (depuis l'API)
                if (data.tax_amount > 0) {
                    var taxLabel = data.tax_name || options.labelTaxes;
                    if (data.tax_percentage > 0) {
                        taxLabel += ' (' + data.tax_percentage + '%)';
                    }
                    html += '<div class="lodgify-breakdown-item">';
                    html += '<span class="lodgify-label">' + taxLabel + '</span>';
                    html += '<span class="lodgify-value">' + formatPrice(data.tax_amount) + '</span>';
                    html += '</div>';
                }
                
                // Insurance fee
                if (data.insurance_fee > 0) {
                    var insuranceLabel = data.insurance_name || 'Insurance';
                    html += '<div class="lodgify-breakdown-item">';
                    html += '<span class="lodgify-label">' + insuranceLabel + '</span>';
                    html += '<span class="lodgify-value">' + formatPrice(data.insurance_fee) + '</span>';
                    html += '</div>';
                }
                
                html += '</div>';
                
                // Ligne total
                html += '<div class="lodgify-total-line">';
                html += '<span class="lodgify-label">' + options.labelTotal + '</span>';
                html += '<span class="lodgify-value">' + formatPrice(data.total) + '</span>';
                html += '</div>';
            } else {
                html += '<div class="lodgify-total-simple">';
                html += '<span class="lodgify-label">' + options.labelTotal + ':</span> ';
                html += '<span class="lodgify-value">' + formatPrice(data.total) + '</span>';
                html += '</div>';
            }
            
            $container.html(html);
        },
        
        /**
         * Formater un nombre avec 2 décimales
         */
        formatNumber: function(num) {
            return parseFloat(num).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },
        
        /**
         * Mettre à jour le bouton de réservation
         */
        updateBookingButton: function($btn, checkIn, checkOut) {
            var currentUrl = $btn.attr('href');
            if (!currentUrl || currentUrl === '#') return;
            
            // Mettre à jour l'URL avec les nouvelles dates
            var url = new URL(currentUrl);
            url.searchParams.set('arrival', checkIn);
            url.searchParams.set('departure', checkOut);
            
            $btn.attr('href', url.toString());
            
            // Mettre à jour le texte si nécessaire
            var bookText = $btn.data('book-text') || 'Book Now';
            $btn.text(bookText);
        }
    };
    
    // Initialiser au chargement
    $(document).ready(function() {
        LodgifyAjaxPrice.init();
    });
    
})(jQuery);
