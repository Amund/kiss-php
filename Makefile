# NOTE: les arguments précédés par des moins (-y, --version) seront capturés par make et ne seront pas disponibles pour les commandes
#
# On peut les forcer en ajoutant un argument "--" :
# Tout ce qui suit cet argument spécial n'est pas capturé par make, et sera donc correctement envoyé vers les commandes
#
# La notation générique permet de complètement contourner ce problème : make [action] -- [arguments]
# exemples :
#   make drush -- cim -y
#   make npm -- install malib --save-dev


## GESTION DES CONTAINERS
.PHONY: up down build config prune shell shell-root logs

up: # Start up containers
	@docker compose up -d --remove-orphans
down: # Stop containers
	@docker compose down
config: # Print config
	@docker compose config
shell:
	@docker compose exec app /bin/bash
shell-root:
	@docker compose exec -u 0:0 app /bin/bash
logs:
	@docker compose logs -f || true


## OUTILS COURANTS
.PHONY: composer kiss tests test phpcs phpstan coverage tag

composer:
	@docker compose exec app composer $(filter-out $@,$(MAKECMDGOALS)) || true
kiss:
	@docker compose exec app src/bin/kiss $(filter-out $@,$(MAKECMDGOALS)) || true
tests:
	@docker compose exec app vendor/bin/phpunit || true
test:
	@docker compose exec app vendor/bin/phpunit $(filter-out $@,$(MAKECMDGOALS)) || true
phpcs:
	@docker compose exec app vendor/bin/phpcs src tests $(filter-out $@,$(MAKECMDGOALS)) || true
phpstan:
	@docker compose exec app php -d memory_limit=512M vendor/bin/phpstan analyse --configuration=phpstan.neon $(filter-out $@,$(MAKECMDGOALS)) || true

coverage:
	@rm -rf coverage
	@docker compose exec app vendor/bin/phpunit 2>&1 | tail -5
	@echo "Report: coverage/index.html"

tag:
	@VERSION=$$(jq -r '.version' composer.json); \
	echo "Tagging $$VERSION..."; \
	git tag "$$VERSION"; \
	git push origin "$$VERSION"; \
	echo "Tagged."

%:
	@:
