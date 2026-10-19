.PHONY: build test lint
build:
	composer install --no-dev -o
	npm ci && npm run build
test:
	composer test
lint:
	composer lint && npm run lint
