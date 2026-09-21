/**
 * Calendrier Lodgify — affichage en lecture seule.
 *
 * Module AUTONOME : ne depend que de jQuery et de l'action AJAX
 * 'lodgify_get_unavailable_dates'. Aucun lien avec airbnb-booking.js.
 *
 * Regle d'affichage (identique au popup de reservation) :
 *   - nuit libre                          -> jour libre
 *   - nuit occupee, veille LIBRE          -> jour libre  (on peut en partir)
 *   - nuit occupee, veille occupee        -> jour reserve
 * Un jour "depart uniquement" s'affiche donc comme un jour libre, sans marqueur,
 * comme le fait Airbnb.
 * Copyright (c) 2026 4U Real Estate Agency. All rights reserved.
 */
(function ($) {
    'use strict';

    var Cal = {

        init: function () {
            $('.lcal').each(function () {
                var $el = $(this);
                if ($el.data('lcal-ready')) { return; }
                $el.data('lcal-ready', true);
                var inst = Object.create(Cal);
                inst.boot($el);
            });
        },

        boot: function ($el) {
            var self = this;
            this.$el = $el;
            try { this.cfg = $el.data('config') || {}; } catch (e) { this.cfg = {}; }
            if (!this.cfg.rentalId) { return; }

            this.nights = [];
            this.first = new Date();
            this.first.setDate(1);
            this.first.setHours(0, 0, 0, 0);

            this.$months = $el.find('.lcal-months');
            this.$loading = $el.find('.lcal-loading');

            $el.on('click', '.lcal-prev', function () { self.shift(-1); });
            $el.on('click', '.lcal-next', function () { self.shift(1); });

            this.arrivee = null;
            this.depart  = null;
            this.$msg    = $el.find('.lcal-message');

            if (this.cfg.selection) {
                $el.on('click', '.lcal-day[data-date]', function () { self.choisir($(this)); });
                $el.on('mouseenter', '.lcal-day[data-date]', function () { self.survol($(this)); });
                $el.on('mouseleave', '.lcal-body', function () { self.survol(null); });

                /* Pont avec le widget de reservation : un evenement partage, dans
                   les deux sens. Le drapeau 'source' evite qu'un widget ne se
                   reponde a lui-meme et ne boucle. */
                $(document).on('lodgify:dates', function (e, d) {
                    if (!d || d.source === 'calendrier') { return; }
                    if (String(d.propertyId) !== String(self.cfg.rentalId)) { return; }
                    self.arrivee = d.arrival ? self.parse(d.arrival) : null;
                    self.depart  = d.departure ? self.parse(d.departure) : null;
                    self.render();
                });
            }

            this.load();
        },

        /** Nombre de mois a afficher, en tenant compte du mobile. */
        count: function () {
            var mobile = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
            return mobile ? (this.cfg.monthsMobile || 1) : (this.cfg.months || 2);
        },

        load: function () {
            var self = this;

            /* Dates embarquees dans la page : aucun appel reseau. Le repli AJAX
               ne sert que si la page a ete rendue sans elles. */
            var embarquees = (this.cfg.nights && this.cfg.nights.length)
                ? this.cfg.nights
                : (window.LodgifyDates || {})[this.cfg.rentalId];
            if (embarquees) {
                this.nights = embarquees;
                this.$loading.hide();
                this.render();
                $(window).on('resize.lcal', this.debounce(function () { self.render(); }, 200));
                if (this.cfg.showPrice) { this.chargerPrix(); }
                return;
            }

            $.ajax({
                url: this.cfg.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                timeout: 20000,
                data: { action: 'lodgify_get_unavailable_dates', property_id: this.cfg.rentalId },
                success: function (r) {
                    self.nights = (r && r.success && r.data) ? r.data : [];
                },
                complete: function () {
                    self.$loading.hide();
                    self.render();
                    $(window).on('resize.lcal', self.debounce(function () { self.render(); }, 200));
                    // Les prix arrivent APRES : le calendrier ne les attend pas.
                    if (self.cfg.showPrice) { self.chargerPrix(); }
                }
            });
        },

        debounce: function (fn, ms) {
            var t;
            return function () { clearTimeout(t); t = setTimeout(fn, ms); };
        },

        /**
         * Un seul appel pour toute la periode affichee. Resultat conserve en
         * memoire pour la session : changer de mois ne redemande que ce qui
         * manque. Cote serveur, un cache d'une heure evite d'appeler Lodgify
         * a chaque visite.
         */
        chargerPrix: function () {
            var self = this;
            var n = this.count();
            var debut = new Date(this.first.getTime());
            var fin = new Date(this.first.getTime());
            fin.setMonth(fin.getMonth() + n);

            var cle = this.fmt(debut) + '|' + this.fmt(fin);
            this.prixCache = this.prixCache || {};
            if (this.prixCache[cle]) { this.injecterPrix(); return; }

            $.ajax({
                url: this.cfg.ajaxUrl,
                type: 'GET',
                dataType: 'json',
                timeout: 12000,
                data: {
                    action: 'lodgify_calendar_prices',
                    rental_id: this.cfg.rentalId,
                    start: this.fmt(debut),
                    end: this.fmt(fin)
                },
                success: function (r) {
                    if (!r || !r.success || !r.data) { return; }
                    self.prixCache[cle] = true;
                    self.prix = $.extend(self.prix || {}, r.data.prices || {});
                    self.minStay = $.extend(self.minStay || {}, r.data.min_stay || {});
                    self.devise = r.data.currency || '';
                    self.injecterPrix();
                }
                // en cas d'echec : rien. Le calendrier reste affiche sans prix.
            });
        },

        parse: function (str) {
            var p = String(str).split('-');
            return new Date(+p[0], +p[1] - 1, +p[2]);
        },

        /** Sejour minimum le plus contraignant sur la plage choisie. */
        minSejour: function (a, b) {
            var m = 1, cur = new Date(a.getTime());
            while (cur < b) {
                var v = (this.minStay || {})[this.fmt(cur)];
                if (v && v > m) { m = v; }
                cur.setDate(cur.getDate() + 1);
            }
            return m;
        },

        message: function (txt) {
            if (!this.$msg || !this.$msg.length) { return; }
            if (!txt) { this.$msg.hide().text(''); return; }
            this.$msg.text(txt).show();
        },

        /** Un depart est valide si toutes les nuits de l'arrivee a D-1 sont libres. */
        departValide: function (d) {
            if (!this.arrivee || d <= this.arrivee) { return false; }
            var cur = new Date(this.arrivee.getTime());
            while (cur < d) {
                if (this.occupee(this.fmt(cur))) { return false; }
                cur.setDate(cur.getDate() + 1);
            }
            return true;
        },

        choisir: function ($d) {
            var str = $d.attr('data-date');
            var d = this.parse(str);
            var today = new Date(); today.setHours(0, 0, 0, 0);
            if (d < today) { return; }

            // Pas d'arrivee, ou plage deja complete : on repart d'une arrivee.
            if (!this.arrivee || this.depart) {
                if (this.occupee(str)) { return; }   // nuit occupee : pas une arrivee
                this.arrivee = d; this.depart = null;
                this.message('');
                this.render();
                this.diffuser();
                return;
            }

            // Deuxieme clic : un depart.
            if (d <= this.arrivee) {
                if (this.occupee(str)) { return; }
                this.arrivee = d; this.depart = null;
                this.message('');
                this.render(); this.diffuser();
                return;
            }
            if (!this.departValide(d)) { return; }

            var n = Math.round((d - this.arrivee) / 86400000);
            var min = this.minSejour(this.arrivee, d);
            if (n < min) {
                this.message((this.cfg.minStayMsg || 'Minimum {n}').replace('{n}', min));
                return;
            }
            this.depart = d;
            this.message('');
            this.render();
            this.diffuser();
        },

        survol: function ($d) {
            if (!this.cfg.selection || !this.arrivee || this.depart) {
                this.$el.find('.lcal-day.is-preview').removeClass('is-preview');
                return;
            }
            this.$el.find('.lcal-day.is-preview').removeClass('is-preview');
            if (!$d) { return; }
            var fin = this.parse($d.attr('data-date'));
            if (fin <= this.arrivee || !this.departValide(fin)) { return; }
            var self = this;
            this.$el.find('.lcal-day[data-date]').each(function () {
                var x = self.parse($(this).attr('data-date'));
                if (x > self.arrivee && x < fin) { $(this).addClass('is-preview'); }
            });
        },

        /** Previent le widget de reservation. */
        diffuser: function () {
            $(document).trigger('lodgify:dates', [{
                source: 'calendrier',
                propertyId: this.cfg.rentalId,
                arrival: this.arrivee ? this.fmt(this.arrivee) : null,
                departure: this.depart ? this.fmt(this.depart) : null
            }]);
        },

        formatePrix: function (v) {
            var dec = (this.cfg.priceDecimals === 'yes') ? 2 : 0;
            var n = Number(v).toFixed(dec);
            var sym = this.devise || '';
            return (this.cfg.priceSymbolAfter === 'yes') ? (n + ' ' + sym) : (sym + n);
        },

        injecterPrix: function () {
            var self = this;
            if (!this.prix) { return; }
            this.$el.find('.lcal-day[data-date]').each(function () {
                var $d = $(this);
                var $p = $d.find('.lcal-price');
                if (!$p.length) { return; }
                // Pas de prix sur une nuit reservee ni sur une date passee :
                // afficher un tarif qu'on ne peut pas vendre n'a pas de sens.
                if ($d.hasClass('is-booked') || $d.hasClass('is-past')) { $p.text(''); return; }
                var v = self.prix[$d.attr('data-date')];
                $p.text((v === undefined || v === null) ? '' : self.formatePrix(v));
            });
        },

        shift: function (n) {
            var t = new Date(this.first.getTime());
            t.setMonth(t.getMonth() + n);
            var today = new Date(); today.setDate(1); today.setHours(0, 0, 0, 0);
            if (t < today) { return; }
            this.first = t;
            this.render();
            if (this.cfg.showPrice) { this.chargerPrix(); }
        },

        fmt: function (d) {
            var m = String(d.getMonth() + 1), j = String(d.getDate());
            return d.getFullYear() + '-' + (m.length < 2 ? '0' + m : m) + '-' + (j.length < 2 ? '0' + j : j);
        },

        occupee: function (dateStr) {
            return this.nights.indexOf(dateStr) !== -1;
        },

        /** Un jour est "reserve" a l'affichage si sa nuit ET celle de la veille le sont. */
        estReserve: function (d) {
            if (!this.occupee(this.fmt(d))) { return false; }
            var veille = new Date(d.getTime());
            veille.setDate(veille.getDate() - 1);
            return this.occupee(this.fmt(veille));
        },

        render: function () {
            var html = '', n = this.count();
            for (var i = 0; i < n; i++) {
                var m = new Date(this.first.getTime());
                m.setMonth(m.getMonth() + i);
                html += this.mois(m);
            }
            this.$months.html(html);

            var today = new Date(); today.setDate(1); today.setHours(0, 0, 0, 0);
            this.$el.find('.lcal-prev').prop('disabled', this.first <= today);
            if (this.cfg.showPrice) { this.injecterPrix(); }
        },

        mois: function (date) {
            var an = date.getFullYear(), mo = date.getMonth();
            var debut = new Date(an, mo, 1), fin = new Date(an, mo + 1, 0);
            var ws = parseInt(this.cfg.weekStart, 10) || 0;
            var decalage = (debut.getDay() - ws + 7) % 7;

            var noms = this.cfg.dayNames || ['S','M','T','W','T','F','S'];
            var dows = '';
            for (var k = 0; k < 7; k++) { dows += '<span>' + noms[(k + ws) % 7] + '</span>'; }

            var today = new Date(); today.setHours(0, 0, 0, 0);
            var cells = '';
            for (var v = 0; v < decalage; v++) { cells += '<div class="lcal-day is-empty"></div>'; }

            for (var j = 1; j <= fin.getDate(); j++) {
                var d = new Date(an, mo, j);
                var cls = ['lcal-day'];
                cls.push(this.estReserve(d) ? 'is-booked' : 'is-free');
                if (d.getTime() === today.getTime()) { cls.push('is-today'); }
                if (d < today) { cls.push('is-past'); }
                /* SEL_AIRBNB_2026-09-21 : has-range n'est pose que si la plage est
                   complete. Sans depart choisi, l'arrivee reste un rond seul,
                   sans amorce de bande. */
                var plageComplete = !!(this.arrivee && this.depart);
                if (this.arrivee && d.getTime() === this.arrivee.getTime()) {
                    cls.push('is-start'); if (plageComplete) { cls.push('has-range'); }
                }
                if (this.depart && d.getTime() === this.depart.getTime()) {
                    cls.push('is-end'); if (plageComplete) { cls.push('has-range'); }
                }
                if (this.arrivee && this.depart && d > this.arrivee && d < this.depart) { cls.push('is-between'); }
                cells += '<div class="' + cls.join(' ') + '" data-date="' + this.fmt(d) + '">'
                      + '<span class="lcal-num">' + j + '</span>'
                      + (this.cfg.showPrice ? '<span class="lcal-price"></span>' : '')
                      + '</div>';
            }

            return '<div class="lcal-month">'
                 + '<div class="lcal-month-name">' + (this.cfg.monthNames || [])[mo] + ' ' + an + '</div>'
                 + '<div class="lcal-dow">' + dows + '</div>'
                 + '<div class="lcal-days">' + cells + '</div>'
                 + '</div>';
        }
    };

    $(document).ready(function () { Cal.init(); });
    // Elementor : re-initialiser apres un rendu d'editeur ou un chargement Ajax.
    $(window).on('elementor/frontend/init', function () {
        if (window.elementorFrontend && elementorFrontend.hooks) {
            elementorFrontend.hooks.addAction('frontend/element_ready/lodgify_calendar.default', function () { Cal.init(); });
        }
    });
})(jQuery);
