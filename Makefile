generated_files = \
	test/db-scripts/create-database.sql \
	.database-created \
	composer.lock \
	test/schema.php \
	vendor

default: run-unit-tests

.DELETE_ON_ERROR:

.SILENT: \
	.database-created

.PHONY: \
	default \
	clean \
	run-unit-tests

util/test-psql: test/config/dbc.json util/generate-psql-script
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
	-o-schema-php test/schema.php -php-schema-class-namespace EarthIT_Schema \
	-o-create-tables-script test/db-scripts/create-tables.sql \
	test/schema.txt

test/schema.php: test/schema.txt util/SchemaSchemaDemo.jar
	${run_schema_processor}
test/db-scripts/create-tables.sql: test/schema.txt util/SchemaSchemaDemo.jar
	${run_schema_processor}

composer.lock: composer.json
	rm -f composer.lock
	composer install

vendor: composer.lock
	composer install
	touch "$@"

clean:
	rm -rf ${generated_files}

test/config/dbc.json:
	cp test/config/dbc.json.example test/config/dbc.json

test/db-scripts/create-database.sql: test/config/dbc.json vendor
	mkdir -p test/db-scripts
	php util/generate-database-creation-script test/config/dbc.json >"$@"

.database-created: test/config/dbc.json test/db-scripts/create-database.sql | util/test-psql
	echo "-------------------------------------------------------------------" >&2
	echo "Creating database on default postgres server based on configuration" >&2
	echo "in test/config/dbc.json." >&2
	echo "To do this yourself, create the database and 'touch $@'." >&2
	echo "If the configuration is not correct, abort this Make invocation, edit" >&2
	echo "the config file, and run Make again." >&2
	echo "The current configuration is as follows:" >&2
	echo "---------------------  test/config/dbc.json  ----------------------" >&2
	cat test/config/dbc.json >&2
	echo "---------------------  SQL to be executed  ------------------------" >&2
	cat test/db-scripts/create-database.sql
	echo "-------------------------------------------------------------------" >&2
	cat test/db-scripts/create-database.sql | sudo -u postgres psql
	echo "This file is a marker created/used by the build process to indicate"      >"$@"
	echo "that a database has been created.  If you've deleted the datbase"        >>"$@"
	echo "or need to re-create it for some other reason, you may delete this file" >>"$@"
	cat util/create-test-tables.sql | util/test-psql -v ON_ERROR_STOP=1

run-unit-tests: vendor .database-created test/schema.php test/db-scripts/create-tables.sql
	util/test-psql -v ON_ERROR_STOP=1 <test/db-scripts/drop-schema.sql
	util/test-psql -v ON_ERROR_STOP=1 <test/db-scripts/create-schema.sql
	util/test-psql -v ON_ERROR_STOP=1 <test/db-scripts/create-tables.sql
	phpunit --bootstrap vendor/autoload.php test
