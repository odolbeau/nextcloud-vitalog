# Vitalog

App Nextcloud pour garder une trace de ses rendez-vous médicaux (RDV, praticiens, lieux, pièces jointes, sync CalDAV).

- App id `vitalog`, namespace `OCA\Vitalog`, Nextcloud 34–35
- Front : Vue 3 + `@nextcloud/vue` 9 (Vite)

## Développement

    composer install && npm install
    npm run dev      # build en watch
    composer test    # phpunit
    composer lint    # php-cs-fixer (dry-run)
    npm run lint

L'app se monte dans `apps-extra` d'une instance [nextcloud-docker-dev](https://github.com/juliusknorr/nextcloud-docker-dev), puis `occ app:enable vitalog`.
