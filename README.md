# CMIP REST

Handles CMIP-style (see below) REST requests using a schema definition.

## What the heck is CMIP

A convention for REST services.

URLs are of the form ```/<collection>[;<modifiers>][/<identifier>[/<property>]]```, hence 'CMIP'.

There are five basic operations: search, get, post, put, patch, delete.

- **GET** ```/<collection>[;<modifiers>][?<filter>]``` returns a list of all objects in the collection, including all their 'simple fields'
  (see 'Collection filters', below).
- **GET** ```/<collection>[;<modifiers>]/<id>``` returns a single object of the collection identified by _id_.
- **POST** ```/<collection>``` adds a new record to the collection.
  The record's data is provided as JSON in the request content.
- **PUT** ```/<collection>/<id>``` replaces a record with the data given as JSON in the request content.
  All fields must be provided.
- **PATCH** ```/<collection>/<id>``` updates a record with data given as JSON in the request content.
  Any fields not explicitly updated retain their old value.
- **DELETE** ```/<collection>/<id>``` deletes a record.

Modifiers indicate how to structure the resulting objects.
The ```with``` modifier is a comma-separated list indicating what related objects should be returned with each record.
A path like ```/authors;with=books``` would indicate to return a list of books with each author record, whereas
a path like ```/books;with=author,publisher``` would indicate to return a single author and publisher with each book record.
The set of related objects that may be requested is defined by the service, and services may provide
alternate modifiers, possibly as aliases for long ```with=``` lists.

Additional levels of nesting may be requested by using a dot to
separate path components.
e.g. ```doctor;with=patients.facility.staff``` to get a list of
patients, each including their facility, including a list of all its
staff, for each returned doctor record.

## Collection filters

Except for a few reserved parameters, query parameters to a collection
GET request correspond to fields of the items in the collection.

The reserved parameters are:

- ```orderBy=[+-]<fieldName>...``` - indicate sort order of results
- ```limit=[<skip>,]<count>``` - indicate how many rows of the result set to skip and include

Field-matching parameter values may be in one of the following formats:

- ```<pattern>``` - match a pattern where ```*``` stands for any
  substring.  Only valid if the pattern does not contain a colon.  May
  be optimized as an exact match if the given pattern doesn't contain
  any asterisks.
- ```eq:<value>``` - match a value exactly
- ```like:<pattern>``` - match a string based on a pattern, where ```*``` stands for any substring
- ```lt:<value>``` - matches values less than that given
- ```gt:<value>``` - matches values greater than that given
- ```in:<list>``` - matches any value that is mentioned in the comma-separated list

Search parameters will be automatically parsed as appropriate given
the field that they are matching on (e.g. if there is a field,
```someNumericField``` that is typed as containing a number, a seach
for ```someNumericField=eq:5``` is interpreted as equals the number 5,
not the string "5")


## Collection-Table mapping

When translating a database record to its REST form, all primary key
values are combined into a single 'id', with multiple fields separated
by dashes.

When interpreting an ID given in a URL or in JSON, it must be
converted to its component fields.  Even if there really is only a
single ID field int the database record, the ID data from JSON or the
URL must be converted to the correct type.

Field names may be translated between naming conventions when loading
and storing.  The naming convention for tables and columns in Postgres
seems to be to use squishedtogetherlowercase.


### Conventional name translation

Behind the scenes, class and field names can be translated to any
convention for mapping to database tables/fields.
In the view exposed by the REST services:

- collection name is dash-separated (e.g. ```patient-stays```)
- field names, both in the URL (modifiers and search parameters) and
  in request/response JSON data, are camelCase
  (e.g. ```patientAdmissionDate```)

It is a goal of this project to make it simple to override these
conventions and to allow special cases for different views of things.
e.g. a class may be exposed as ```super-duper-car-washes```, but the
backing table actually be called ```x_okaycarwash```.


TODO: Add example of using this library in PHP.
