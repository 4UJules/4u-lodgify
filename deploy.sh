#!/usr/bin/env bash
#
# Déploiement d'une version taguée de 4u-lodgify sur les sites de production.
#
#   ./deploy.sh 1.0.0                      les 7 sites
#   ./deploy.sh 1.0.0 amazingstaysxm.com   un seul site
#
# Sauvegarde automatique avant écrasement, `php -l` sur chaque fichier PHP,
# abandon du site au premier contrôle en échec (le site garde sa version).
set -euo pipefail

TAG="${1:-}"
CIBLE="${2:-}"
SRV="root@108.175.2.53"
CLE="$HOME/.ssh/id_ed25519"
DEST="wp-content/plugins/4u-lodgify"
HORO="$(date +%Y%m%d-%H%M%S)"

SITES=(
  amazingstaysxm.com
  thehillsresidence.com
  4u-realestate.com
  4u-realestate.org
  sintmaartenrealestateproperties.com
  aqua-resort.com
  dolcebeachresidence.com
)

if [[ -z "$TAG" ]]; then
  echo "usage : ./deploy.sh <tag> [site]" >&2; exit 1
fi
if [[ -n "$(git status --porcelain)" ]]; then
  echo "refus : l'arbre de travail n'est pas propre. Commite d'abord." >&2; exit 1
fi
if ! git rev-parse "$TAG" >/dev/null 2>&1; then
  echo "refus : le tag $TAG n'existe pas." >&2; exit 1
fi

# On déploie l'arbre du TAG, pas le répertoire courant : ce qui part en
# production est exactement ce qui est versionné.
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
git archive "$TAG" | tar -x -C "$TMP"
rm -f "$TMP/deploy.sh"          # l'outil de déploiement n'a rien à faire sur le serveur

[[ -n "$CIBLE" ]] && SITES=("$CIBLE")

for S in "${SITES[@]}"; do
  V="/var/www/vhosts/$S/httpdocs"
  printf '  %-38s ' "$S"

  if ! ssh -i "$CLE" "$SRV" "[ -d $V ]"; then echo "vhost introuvable, ignoré"; continue; fi

  # sauvegarde hors de wp-content : jamais un dossier de plugin en backup
  ssh -i "$CLE" "$SRV" "[ -d $V/$DEST ] && cp -a $V/$DEST /tmp/4u-lodgify-backup-$S-$HORO || true"

  ssh -i "$CLE" "$SRV" "mkdir -p $V/$DEST"
  rsync -a --delete -e "ssh -i $CLE" "$TMP"/ "$SRV:$V/$DEST/"

  # propriétaire du vhost, sinon WordPress ne peut pas lire le plugin
  OWN="$(ssh -i "$CLE" "$SRV" "stat -c '%U:%G' $V/wp-content/plugins")"
  ssh -i "$CLE" "$SRV" "chown -R $OWN $V/$DEST"

  ERR="$(ssh -i "$CLE" "$SRV" "find $V/$DEST -name '*.php' -exec /opt/plesk/php/8.3/bin/php -l {} \; 2>&1 | grep -v 'No syntax errors' | head -3")"
  if [[ -n "$ERR" ]]; then
    echo "ÉCHEC php -l, restauration"; echo "$ERR" | sed 's/^/      /'
    ssh -i "$CLE" "$SRV" "rm -rf $V/$DEST && [ -d /tmp/4u-lodgify-backup-$S-$HORO ] && cp -a /tmp/4u-lodgify-backup-$S-$HORO $V/$DEST || true"
    continue
  fi

  ssh -i "$CLE" "$SRV" "/opt/plesk/php/8.3/bin/php -d memory_limit=1536M /usr/local/bin/wp eval 'if(function_exists(\"rocket_clean_domain\")){rocket_clean_domain();rocket_clean_minify();}' --allow-root --path=$V >/dev/null 2>&1 || true"
  echo "déployé $TAG · sauvegarde /tmp/4u-lodgify-backup-$S-$HORO"
done
