CREATE SEQUENCE "entityids" START 1001;

CREATE TYPE mood AS ENUM('ugh!', 'really bad', 'meh', 'sort of okay', 'nice', 'pretty sweet', 'rad!');

CREATE TABLE "resource" (
	"id" BIGINT NOT NULL DEFAULT nextval('entityids'),
	"urn" VARCHAR(127) NOT NULL,
	PRIMARY KEY ("id")
);
CREATE TABLE "person" (
	"id" BIGINT NOT NULL DEFAULT nextval('entityids'),
	"name" VARCHAR(127) NOT NULL,
	PRIMARY KEY ("id")
);
CREATE TABLE "rating" (
	"authorid" BIGINT NOT NULL,
	"subjectid" BIGINT NOT NULL,
	"comment" TEXT,
	"qualityrating" SMALLINT,
	"resourceisfake" BOOLEAN,
	"feeling" mood,
	PRIMARY KEY ("authorid", "subjectid"),
	FOREIGN KEY ("authorid") REFERENCES "person" ("id"),
	FOREIGN KEY ("subjectid") REFERENCES "resource" ("id")
);
