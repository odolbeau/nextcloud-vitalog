# Vitalog

Application [Nextcloud](https://nextcloud.com) pour garder une trace de ses rendez-vous médicaux.

- **Rendez-vous** : date, durée, motif, notes, statut (planifié / effectué / annulé), praticien et lieu.
- **Praticiens et lieux** : créés une fois (ou à la volée depuis un rendez-vous), réutilisés partout.
  Un praticien peut être lié à une fiche de l'app **Contacts** (facultatif) : la fiche fait foi pour le nom, la spécialité et le
  téléphone, la copie locale reste utilisable si la fiche disparaît, et le menu des contacts propose « Rendez-vous Vitalog ».
- **Documents** : ordonnances, comptes rendus, résultats… joints depuis vos fichiers Nextcloud.
- **Calendrier** : les rendez-vous planifiés sont reflétés dans le calendrier de votre choix (avec rappel la veille) ;
  déplacement, annulation et suppression sont répercutés.
  En option (désactivée par défaut), un événement ajouté à ce calendrier crée aussi un rendez-vous (« effectué » s'il est déjà
  passé). Sens unique : les modifications ultérieures de l'événement ne sont pas répercutées, et les événements récurrents sont ignorés.
- Interface en anglais et en français. Les données restent sur votre instance et sont strictement privées à chaque utilisateur.

Compatible Nextcloud **34 et 35** (PHP 8.2+ ; PHP 8.3+ pour Nextcloud 35). Nextcloud 33 n'est volontairement pas supporté :
fin de vie le 2027-02-18.

## Développement

Prérequis : PHP ≥ 8.2, Composer, Node ≥ 20, Docker.

```sh
composer install && npm install
npm run dev          # build du front en mode watch
dev/up.sh            # instance Nextcloud 35 sur http://localhost:8480 (admin / admin), app activée
dev/up.sh 34         # idem avec Nextcloud 34
NC_PORT=8500 dev/up.sh
docker compose -f dev/docker-compose.yml down -v   # tout supprimer
```

`dev/up.sh` démarre une pile isolée (`vitalog-dev` : Nextcloud + PostgreSQL, ports et noms de conteneurs préfixés) : elle ne touche
pas à vos autres conteneurs. Le dépôt est monté dans `custom_apps/vitalog` : après `npm run build`, rechargez la page (Ctrl+Maj+R,
Nextcloud met les assets en cache). Le script désactive le *first run wizard* et les limitations de débit pour faciliter les tests ;
il installe aussi l'app Calendrier.

### Tests et qualité

| Commande | Contenu |
| --- | --- |
| `composer test` | PHPUnit : tests unitaires des services (sync calendrier, pièces jointes, cascades, validation) |
| `composer lint` / `composer cs:fix` | php-cs-fixer |
| `composer psalm` | analyse statique |
| `npm run lint` | ESLint (src, e2e, scripts) |
| `npm run l10n:check` | toutes les chaînes ont leur traduction française (et pas de clé obsolète) ; `npm run l10n:build` régénère `l10n/fr.js` |
| `npm run e2e` | Playwright contre `dev/up.sh` : API (CRUD, isolation entre utilisateurs, pièces jointes, événements CalDAV), interface (parcours complets, FR, accessibilité axe, mobile) |

La CI GitHub (`.github/workflows/ci.yml`) exécute le lint, Psalm, PHPUnit sur PHP 8.2 à 8.5, le build et la suite e2e sur Nextcloud 34 et 35.

### Architecture

- `lib/Db` : entités et mappers (tables `vitalog_*`, migration dans `lib/Migration`). Toute requête est filtrée par `user_id`.
- `lib/Service` : logique métier. `CalendarSyncService` reflète un rendez-vous dans le calendrier cible ; un échec de synchronisation
  est journalisé mais n'empêche jamais l'enregistrement.
- `lib/Calendar` : `CalendarGateway` (interface) et `DavCalendarGateway`. L'API publique `OCP\Calendar` ne permet que de **créer** un
  événement ; la mise à jour et la suppression passent donc par `OCA\DAV\CalDAV\CalDavBackend` (classe interne mais utilisée par les
  apps officielles), isolée derrière l'interface.
- `lib/Contacts` : `ContactGateway` et `ManagerContactGateway` (API publique `OCP\Contacts\IManager`, carnets de l'utilisateur
  connecté) ; `lib/ContactsMenu` : action « Rendez-vous Vitalog » sur les fiches liées.
- `lib/Controller` : API OCS sous `/ocs/v2.php/apps/vitalog/api/` (`appointments`, `practitioners`, `places`, `settings`,
  `appointments/{id}/attachments`). `PUT` remplace l'objet entier.
- `src` : Vue 3 + `@nextcloud/vue` 9 + Pinia (`stores/data.js`), build Vite (`@nextcloud/vite-config`).

## Limites connues (dépendances)

Constatées avec `@nextcloud/vue` 9.13.1 ; non corrigées dans ce dépôt :

- le titre de l'onglet affiche `[object Object]` : `NcContent` fournit `appName` sous forme de `computed` alors que `useAppName()`
  attend une chaîne ;
- le séparateur `splitpanes` de `NcAppContent` est focalisable avec `role="separator"` mais sans `aria-valuenow` (axe : `aria-required-attr`) ;
- `NcModal` ignore Échap quand le focus est dans un champ de saisie : un formulaire ne se ferme pas avec Échap depuis un champ.

## Licence

AGPL-3.0-or-later
