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
occ app:disable firstrunwizard >/dev/null 2>&1 || true
occ app:enable vitalog
occ app:install calendar >/dev/null 2>&1 || occ app:enable calendar >/dev/null 2>&1 || true
echo "Prêt : http://localhost:${NC_PORT}  (admin / admin)"
