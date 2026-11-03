APP_ID := vitalog
VERSION := $(shell sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
PACKAGE_DIR := build/package
ARCHIVE := build/$(APP_ID)-$(VERSION).tar.gz
# App Store signing material (private key + certificate signed by Nextcloud), kept outside the repository
CERT_DIR ?= $(HOME)/.nextcloud/certificates
NC_IMAGE ?= nextcloud:35-apache

.PHONY: build test lint package package-files signed-package release clean
build:
	composer install --no-dev -o
	npm ci && npm run build
test:
	composer test
lint:
	composer lint && npm run lint

# Release archive: only what runs on a server (no sources, tests or dev tooling), under a top-level `vitalog/` folder.
# Usage: `make package` (builds the front end first), then upload $(ARCHIVE) to the App Store / a GitHub release.
package: package-files
	tar -C $(PACKAGE_DIR) -czf $(ARCHIVE) $(APP_ID)
	@echo "Built $(ARCHIVE)"

package-files: clean
	npm ci && npm run build
	mkdir -p $(PACKAGE_DIR)/$(APP_ID)
	cp -r appinfo lib templates js l10n img LICENSE CHANGELOG.md $(PACKAGE_DIR)/$(APP_ID)/
	find $(PACKAGE_DIR) -name '*.map' -delete

# App Store release: the same archive, code-signed (appinfo/signature.json, checked by Nextcloud on install) and with the
# archive signature asked by the App Store in $(ARCHIVE).sig. The code is signed by `occ integrity:sign-app` in a throwaway
# Nextcloud (SQLite) container, the key being mounted read-only; the signature is then checked the way a server does.
signed-package: package-files
	docker run --rm -u $$(id -u):$$(id -g) --entrypoint sh \
		-v $(CURDIR)/$(PACKAGE_DIR)/$(APP_ID):/app -v $(CERT_DIR):/certs:ro $(NC_IMAGE) -c '\
		set -e; cp -r /usr/src/nextcloud /tmp/nc && cd /tmp/nc; \
		php occ maintenance:install --database=sqlite --admin-user=admin --admin-pass=signing-only >/dev/null; \
		php occ integrity:sign-app --path=/app --privateKey=/certs/$(APP_ID).key --certificate=/certs/$(APP_ID).crt; \
		cp -r /app apps/$(APP_ID) && php occ integrity:check-app $(APP_ID) --path=/tmp/nc/apps/$(APP_ID) && echo "Code signature OK"'
	tar -C $(PACKAGE_DIR) -czf $(ARCHIVE) $(APP_ID)
	openssl dgst -sha512 -sign $(CERT_DIR)/$(APP_ID).key $(ARCHIVE) | openssl base64 -A > $(ARCHIVE).sig
	openssl x509 -in $(CERT_DIR)/$(APP_ID).crt -noout -pubkey > build/$(APP_ID).pub
	openssl base64 -d -A -in $(ARCHIVE).sig | openssl dgst -sha512 -verify build/$(APP_ID).pub -signature /dev/stdin $(ARCHIVE)
	@echo "Built $(ARCHIVE) and its App Store signature $(ARCHIVE).sig"

# GitHub release v$(VERSION) with the signed archive, from a clean tree whose HEAD is already on origin/main.
release: signed-package
	@test -z "$$(git status --porcelain)" || { echo "The working tree is not clean"; exit 1; }
	git fetch -q origin main && git merge-base --is-ancestor HEAD origin/main || { echo "Push HEAD to origin/main first"; exit 1; }
	gh release create v$(VERSION) $(ARCHIVE) --target $$(git rev-parse HEAD) --title v$(VERSION) --generate-notes
	@echo "App Store (https://apps.nextcloud.com/developer/apps/releases/new):"
	@echo "  download:  https://github.com/odolbeau/nextcloud-vitalog/releases/download/v$(VERSION)/$(notdir $(ARCHIVE))"
	@echo "  signature: $$(cat $(ARCHIVE).sig)"

clean:
	rm -rf build
