generated_files = \
	.create-database.sql \
	.database-created \
	test-schema.php \
	vendor

default: run-tests

.DELETE_ON_ERROR:

.SILENT: \
	.database-created

.PHONY: \
	default \
	clean \
	run-tests

util/test-psql: config/test-dbc.json util/generate-psql-script
	php util/generate-psql-script "$<" > "$@"
	chmod +x "$@"

util/SchemaSchemaDemo.jar: util/SchemaSchemaDemo.jar.urn
	rm -f $@
	mkdir -p `dirname $@`
	java -jar util/TJFetcher.jar \
		-repo pvps1.nuke24.net \
		-repo fs.marvin.nuke24.net \
		-repo robert.nuke24.net:8080 \
		-o $@ `cat $<`

run_schema_processor = \
	java -jar util/SchemaSchemaDemo.jar \
	-o-schema-php test-schema.php -php-schema-class-namespace EarthIT_Schema \
	test-schema.txt

test-schema.php: test-schema.txt util/SchemaSchemaDemo.jar
	${run_schema_processor}

vendor: composer.json
	if [ -e "$@" ] ; then composer update ; else composer install ; fi
	touch "$@"

clean:
	rm -rf ${generated_files}

config/test-dbc.json:
	cp config/test-dbc.json.example config/test-dbc.json

.create-database.sql: config/test-dbc.json
	php util/generate-database-creation-script config/test-dbc.json >"$@"

.database-created: config/test-dbc.json .create-database.sql util/test-psql
	echo "-------------------------------------------------------------------" >&2
	echo "Creating database on default postgres server based on configuration" >&2
	echo "in config/test-dbc.json." >&2
	echo "To do this yourself, create the database and 'touch $@'." >&2
	echo "If the configuration is not correct, abort this Make invocation, edit" >&2
	echo "the config file, and run Make again." >&2
	echo "The current configuration is as follows:" >&2
	echo "---------------------  config/test-dbc.json  ----------------------" >&2
	cat config/test-dbc.json >&2
	echo "---------------------  SQL to be executed  ------------------------" >&2
	cat .create-database.sql
	echo "-------------------------------------------------------------------" >&2
	cat .create-database.sql | sudo -u postgres psql
	echo "This file is a marker created/used by the build process to indicate"      >"$@"
	echo "that a database has been created.  If you've deleted the datbase"        >>"$@"
	echo "or need to re-create it for some other reason, you may delete this file" >>"$@"
	cat util/create-test-tables.sql | util/test-psql -v ON_ERROR_STOP=1

run-tests: vendor .database-created test-schema.php
	phpunit --bootstrap vendor/autoload.php test
