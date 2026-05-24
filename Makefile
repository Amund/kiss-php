PHP_VERSION ?= 8.2
PHP = docker run --rm -t -v $(PWD):/app -w /app php:$(PHP_VERSION)-cli
COMPOSER = docker run --rm -t -e COMPOSER_COLOR=1 -v $(PWD):/app -w /app composer:2

.PHONY: tests test phpcs phpstan coverage phar docs docs-watch tag shell

install:
	@$(COMPOSER) install

tests:
	@$(PHP) vendor/bin/phpunit 2>&1 | grep -v 'coverage driver\|OK, but there' || true

test:
	@$(PHP) vendor/bin/phpunit $(filter-out $@,$(MAKECMDGOALS))

phpcs:
	@$(PHP) vendor/bin/phpcs src tests

phpstan:
	@$(PHP) php -d memory_limit=512M vendor/bin/phpstan analyse

coverage:
	@$(PHP) \
	  sh -c 'rm -rf coverage && \
	         pecl install pcov 2>/dev/null && \
	         docker-php-ext-enable pcov 2>/dev/null && \
	         vendor/bin/phpunit' 2>&1 | grep -v 'generated' || true
	@echo "Report: coverage/index.html"

phar:
	@$(COMPOSER) install --no-dev --no-interaction --quiet && \
	 $(PHP) php -d phar.readonly=0 build-phar.php && \
	 $(COMPOSER) install --no-interaction --quiet
	@mkdir -p $(HOME)/.local/bin && \
	 mv -f kiss.phar $(HOME)/.local/bin/kiss && \
	 chmod +x $(HOME)/.local/bin/kiss && \
	 echo "Installed: $(HOME)/.local/bin/kiss"

docs:
	@KISS_ENTRY=doc-site/kiss.yml kiss build
	@echo "Docs generated in docs/"

docs-watch:
	@KISS_ENTRY=doc-site/kiss.yml kiss watch

tag:
	@VERSION=$$(jq -r '.version' composer.json); \
	echo "Tagging $$VERSION..."; \
	git tag "$$VERSION"; \
	git push origin "$$VERSION"; \
	echo "Tagged."

shell:
	@docker run --rm -it -v $(PWD):/app -w /app php:$(PHP_VERSION)-cli /bin/bash

%:
	@:
