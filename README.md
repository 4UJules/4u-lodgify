# 4U Lodgify

Intégration Lodgify unifiée pour les sites de location de Sint Maarten.
Remplace progressivement `lodgify-availability-sync`.

## Modules

| Module | Rôle |
|---|---|
| `lodgify-comptes/` | Comptes Lodgify, clés chiffrées, biens, association aux fiches |
| `lodgify-calendar/` | Widget Elementor « Calendrier Lodgify » (lecture + sélection de dates) |
| `webhooks/` | Réception des événements Lodgify, resynchronisation ciblée, purge du cache |

## Écrivain unique

Une seule brique écrit dans les tables de disponibilité. En 1.0.0 cette brique
reste `lodgify-availability-sync` : le module webhooks ne fait que **déclencher**
sa synchronisation pour le compte concerné. Aucune écriture concurrente n'est
introduite.

## Sécurité

- Aucune clé API dans le dépôt : les clés vivent chiffrées en base (AES-256-CBC
  dérivé de `AUTH_KEY` + `SECURE_AUTH_SALT`).
- L'URL de réception des webhooks porte un jeton aléatoire stocké en base.
  Lodgify ne signe pas ses appels : le jeton est le seul contrôle possible.
- Aucun log ni sauvegarde versionnés (voir `.gitignore`).

## Déploiement

    ./deploy.sh 1.0.0            # les 7 sites
    ./deploy.sh 1.0.0 amazingstaysxm.com   # un seul site

Sauvegarde automatique avant chaque déploiement, `php -l` sur tous les fichiers,
abandon du site si un contrôle échoue.
