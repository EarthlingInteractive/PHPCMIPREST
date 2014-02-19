# CMIP REST

Handles CMIP-style (see below) REST requests using a schema definition.

## What the heck is CMIP

A convention for REST services.

URLs are of the form ```/<collection>[;<modifiers>][/<identifier>[/<property>]]```, hence 'CMIP'.

There are five basic operations: search, get, post, put, patch, delete.

- **GET** ```/<collection>[;<modifiers>][?<searchparameters>]``` returns a list of all objects in the collection, including all their 'simple fields'.
  The list can be filtered by adding search parameters.
  The names of these parameters match the names of the fields their values match.
  Asterisks in values indicate 'match any string'.
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

All identifiers are squishedtogetherlowercase (this is a bit of a
compromise and I may change my mind about it).

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
seems to be to use squishedtogetherlowercase, which is convenient, but
shouldn't be relied upon.
