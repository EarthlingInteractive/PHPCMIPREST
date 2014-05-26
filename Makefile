all: test

.PHONY: \
	all \
	clean \
	test

vendor: composer.json
	if [ -e "$@" ] ; then composer update ; else composer install ; fi
	touch "$@"

clean:
	rm -rf vendor

test: vendor
	phpunit --bootstrap vendor/autoload.php test
