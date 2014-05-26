generated_files = \
	.create-database.sql \
	.create-tables.sql \
	.database-created \
	.drop-tables.sql \
	test-schema.php \
	vendor

all: test

.DELETE_ON_ERROR:

.SILENT: \
	.database-created

.PHONY: \
	all \
	clean \
	test

util/SchemaSchemaDemo.jar: util/SchemaSchemaDemo.jar.urn
	rm -f $@
	mkdir -p `dirname $@`
	curl -o $@ 'http://pvps1.nuke24.net/uri-res/N2R?'`cat "$<"`

run_schema_processor = \
	java -jar util/SchemaSchemaDemo.jar \
	-o-create-tables-script .create-tables.sql \
	-o-drop-tables-script .drop-tables.sql \
	-o-schema-php test-schema.php -php-schema-class-namespace EarthIT_Schema \
	test-schema.txt

.create-tables.sql: test-schema.txt util/SchemaSchemaDemo.jar
	${run_schema_processor}

.drop-tables.sql: test-schema.txt util/SchemaSchemaDemo.jar
	${run_schema_processor}

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

.database-created: config/test-dbc.json .create-database.sql
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

test: vendor .database-created test-schema.php .create-tables.sql .drop-tables.sql
	phpunit --bootstrap vendor/autoload.php test
