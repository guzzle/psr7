test:
	vendor/bin/phpunit $(TEST)

static: static-phpstan static-codestyle-check static-composer-normalize-check

static-phpstan:
	composer install
	composer bin phpstan update
	vendor/bin/phpstan analyze $(PHPSTAN_PARAMS)

static-phpstan-update-baseline:
	composer install
	composer bin phpstan update
	$(MAKE) static-phpstan PHPSTAN_PARAMS="--generate-baseline"

static-codestyle-fix:
	composer install
	composer bin php-cs-fixer update
	vendor/bin/php-cs-fixer fix --diff $(CS_PARAMS)

static-codestyle-check:
	$(MAKE) static-codestyle-fix CS_PARAMS="--dry-run"

static-composer-normalize-fix:
	composer install
	composer bin composer-normalize update
	composer bin composer-normalize normalize --diff $(COMPOSER_NORMALIZE_PARAMS) ../../composer.json

static-composer-normalize-check:
	$(MAKE) static-composer-normalize-fix COMPOSER_NORMALIZE_PARAMS="--dry-run"

check-tag:
	$(if $(TAG),,$(error TAG is not defined. Pass via "make tag TAG=4.2.1"))

tag: check-tag
	@echo Tagging $(TAG)
	chag update $(TAG)
	git commit -a -m '$(TAG) release'
	chag tag
	@echo "Release has been created. Push using 'make release'"
	@echo "Changes made in the release commit"
	git diff HEAD~1 HEAD

release: check-tag
	git push origin master
	git push origin $(TAG)
