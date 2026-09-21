# Consolidation — de `lodgify-availability-sync` vers `4u-lodgify`

État au 2026-09-21. Ce document est la carte de la migration : ce qui reste à rapatrier,
dans quel ordre, et ce qui ne doit surtout pas changer de nom en route.

---

## 1. La base canonique

`lodgify-availability-sync` s'annonce partout en **« Version 1.0.0 »**, mais il existe
**trois variantes** sur le parc (41 fichiers, ~16 200 lignes) :

| empreinte | sites | écart |
|---|---|---|
| `25c1e3b0dda3` | 4u-realestate **.com** et **.org**, aqua-resort, dolcebeachresidence, sintmaartenrealestateproperties | référence |
| `102564f95b47` | amazingstaysxm | **aucun écart fonctionnel** : `LODGIFY_SYNC_VERSION` à `1.0.1` et l'asset `airbnb-booking.js` à `1.5.2` — deux chaînes de cache-busting |
| `283ba07113c9` | thehillsresidence | **un seul écart réel**, décrit au §2 |

Un quatrième exemplaire dort sur 4udevelopment.com (35 fichiers, plugin **inactif**) : hors périmètre.

**Base de rapatriement : la copie serveur de `.com` (`25c1e3b0dda3`), corrigée de l'écart §2.**
Ne pas repartir de `~/lodgify-dev/ancien-plugin` : cette copie date du 20/09 et ne contient pas
le travail du 21/09 (`AMM_PONT_CALENDRIER`, `SEL_AIRBNB_2026-09-21` — le pont de sélection entre
le calendrier du bas et le popup de réservation).

## 2. Le seul désaccord de code du parc : la fenêtre des nuits occupées

Dans `lodgify-availability-sync.php` (~l. 1450), la réconciliation des réservations **`pending`**
de `jet_apartment_bookings` développe une réservation en nuits de deux façons contradictoires :

```php
// 5 sites   : nuits = ]check_in, check_out]   ← décalé d'un jour
for ($d = $ci + 86400; $d <= $co; $d += 86400)

// thehills  : nuits = [check_in, check_out[   ← correct
for ($d = $ci;         $d <  $co; $d += 86400)
```

**La forme thehills est la bonne.** Tranché sur les données, pas sur l'usage :

- le chemin réellement servi aux visiteurs, `lodgify-calendar/dates.php`, utilise `for ($t = $d; $t < $f; …)`,
  donc `[check_in, check_out[` — et c'est ce calendrier qui est vérifié juste en production ;
- les lignes de `jet_apartment_bookings` portent des timestamps à minuit UTC ; une réservation
  26/02 → 03/03 y donne 6 nuits et libère le 3 mars, ce qu'attend le moteur de réservation ;
- `dates.php` documente au passage le décalage d'une seconde des lignes créées par JetBooking
  (arrivée 00:00:01, départ 00:00:00) : **normaliser en date avant de boucler**, jamais comparer
  les timestamps bruts.

**Impact actuel : nul.** Ce bloc ne lit que `status = 'pending'`, et les 7 sites n'ont
**aucune** ligne `pending` — uniquement des lignes `external` (155 à 660 selon le site).
C'est du code mort. Inutile donc de patcher les 6 sites en place : il suffit de **ne rapatrier
que la forme correcte**.

## 3. Ce qui ne doit jamais changer de nom

Ces identifiants sont écrits en dur dans `_elementor_data`, dans le JS livré, ou dans du code
déjà déployé de `4u-lodgify`. Les renommer casse silencieusement l'existant.

**Widgets Elementor** — `lodgify_calendar`, `lodgify_airbnb_booking`, `lodgify_booking_button`,
`lodgify_price_widget`, `lodgify_total_price`.

**Dynamic tags** — `lodgify-price-per-day`, `lodgify-min-stay`, `lodgify-nights-count`,
`lodgify-total-stay`, `lodgify-booking-url`.

**Actions AJAX publiques** — `lodgify_get_unavailable_dates`, `lodgify_get_price`,
`lodgify_calendar_prices` (chacune en `wp_ajax_` **et** `wp_ajax_nopriv_`).

**Crons** — `lodgify_availability_sync_cron`, `lodgify_weekly_prices_sync_cron`.

**Tables** — `lodgify_availabilities`, `lodgify_daily_prices`, `lodgify_prices`,
et en lecture `jet_apartment_bookings`.

**Transients** — `lodgify_dates_` + `md5($rental_id)` et `lcal_px_` + `md5("$rental|$debut|$fin")`.
`FourU_Lodgify_Webhooks::purger_fiches` (déjà en production depuis 1.0.6) en dépend directement.

**Clés de `data-config` du widget calendrier** — lues telles quelles par `lodgify-calendar.js`
(`rentalId`, `nights`, `selection`, `showPrice`, `minStayMsg`, `priceSymbolAfter`…).

## 4. Le verrou à lever avant tout rapatriement

`4u-lodgify.php` saute **entièrement** les modules « comptes » et « calendrier » tant que
`lodgify-availability-sync` est actif, pour éviter la double déclaration de classes. Conséquence :
tout module rapatrié reste **mort** jusqu'à la désactivation de l'ancien plugin. La bascule serait
donc tout-ou-rien, par site, sans retour arrière — exactement ce qu'il ne faut pas pour 7 sites
en production.

Le bon motif existe déjà dans le dépôt, en 1.1.0 : `filtres/class-filtre-dates.php` se charge
**toujours** et ne s'active que sur option. À généraliser :

1. chaque module rapatrié est chargé dans tous les cas, mais **inerte** derrière sa propre option ;
2. quand son option est levée, le module `remove_action()` l'enregistrement équivalent de l'ancien
   plugin avant de poser le sien ;
3. la désactivation de `lodgify-availability-sync` devient alors du **nettoyage**, plus le moment
   risqué.

Bascule par module **et** par site, réversible en basculant une option.

## 5. Ordre des incréments

**A. La synchro** — `includes/class-availability-manager.php` (1 066 l.) et la réconciliation du
fichier principal : écriture de `lodgify_availabilities`, puis report vers `jet_apartment_bookings`
en lignes `external`. C'est le **producteur** que tout le reste lit ; le webhook de `4u-lodgify` ne
fait aujourd'hui que déclencher cette synchro-là. À faire en premier, et le §2 en est le prérequis.

**B. La réservation** — `elementor/widgets/airbnb-booking-widget.php` (1 142 l.),
`assets/js/airbnb-booking.js` (1 295 l., avec le pont `lodgify:dates`), le popup, et l'endpoint
`lodgify_get_unavailable_dates`.

**C. Les prix** — devis `lodgify_get_price`, `includes/class-daily-prices-sync.php` (688 l.),
`class-prices-manager.php`, `class-min-stay-filter.php` (925 l.), les dynamic tags prix/jour et
séjour minimum, et le cron hebdomadaire des prix.

Le module **Calendrier** est déjà dans `4u-lodgify` mais en **double** avec la copie de l'ancien
plugin ; il ne devient réellement unique qu'une fois A, B et C passés et l'ancien plugin désactivé.

## 6. Déjà fait, hors plugin

- `lodgify-cancellation-cleaner` inactif partout ;
- `lodgify-booking-box` désactivé sur sintmaarten (widget officiel Lodgify, mono-compte,
  `%website_id%` jamais substitué — voir `~/lodgify-dev/RAPPORT-booking-box.md`) ;
- route `/ical-sync` neutralisée par mu-plugin (écrivain latent vers `jet_apartment_bookings`) ;
- `4u-real-estate-sync` : **à préciser** — c'est le plugin qui sert `POST /wp-json/4u-sync/v1/properties`,
  seul appelant réel étant le CRM externe (axios, 104.192.4.22). Il écrit des posts. Point ouvert
  dans la question de l'écrivain unique.
