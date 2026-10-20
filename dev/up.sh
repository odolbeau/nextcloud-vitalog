#!/usr/bin/env bash
# Démarre une instance Nextcloud de dev isolée avec Vitalog activée.
# Usage : dev/up.sh [version NC, défaut 35]   (NC_PORT pour changer le port, défaut 8480)
set -euo pipefail
cd "$(dirname "$0")"
export NC_VERSION="${1:-${NC_VERSION:-35}}"
export NC_PORT="${NC_PORT:-8480}"

docker compose up -d --wait --wait-timeout 300 || true
occ() { docker compose exec -T -u www-data nextcloud php occ "$@"; }

echo "Attente de l'installation de Nextcloud…"
for _ in $(seq 1 120); do
	occ status 2>/dev/null | grep -q 'installed: true' && break
	sleep 3
done

echo "Attente d'Apache…"
for _ in $(seq 1 60); do
	curl -fs "http://localhost:${NC_PORT}/status.php" 2>/dev/null | grep -q '"installed":true' && break
	sleep 2
done

occ config:system:set debug --value=true --type=boolean >/dev/null
# environnement de test : pas de limitation de débit ni d’anti brute-force (les suites e2e enchaînent des centaines d’appels)
occ config:system:set ratelimit.protection.enabled --value=false --type=boolean >/dev/null
occ config:system:set auth.bruteforce.protection.enabled --value=false --type=boolean >/dev/null
# pas de corbeille pour les calendriers : les tests en créent beaucoup, et les calendriers supprimés comptent dans la limite de 30 par utilisateur
occ config:app:set dav calendarRetentionObligation --value=0 >/dev/null
occ app:disable firstrunwizard >/dev/null 2>&1 || true
occ app:enable vitalog
# custom_apps appartient à root dans l'image : www-data doit pouvoir y installer des apps
docker compose exec -T nextcloud chown www-data:www-data /var/www/html/custom_apps
for a in calendar contacts; do occ app:install $a || occ app:enable $a; done
echo "Prêt : http://localhost:${NC_PORT}  (admin / admin)"
