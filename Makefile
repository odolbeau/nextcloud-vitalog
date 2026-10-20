APP_ID := vitalog
VERSION := $(shell sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
PACKAGE_DIR := build/package
ARCHIVE := build/$(APP_ID)-$(VERSION).tar.gz

.PHONY: build test lint package clean
build:
	composer install --no-dev -o
	npm ci && npm run build
test:
	composer test
lint:
	composer lint && npm run lint

# Release archive: only what runs on a server (no sources, tests or dev tooling), under a top-level `vitalog/` folder.
# Usage: `make package` (builds the front end first), then upload $(ARCHIVE) to the App Store / a GitHub release.
package: clean
	npm ci && npm run build
	mkdir -p $(PACKAGE_DIR)/$(APP_ID)
	cp -r appinfo lib templates js l10n img LICENSE $(PACKAGE_DIR)/$(APP_ID)/
	find $(PACKAGE_DIR) -name '*.map' -delete
	tar -C $(PACKAGE_DIR) -czf $(ARCHIVE) $(APP_ID)
	@echo "Built $(ARCHIVE)"

clean:
	rm -rf build
