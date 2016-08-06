## How to request compound actions

You can invoke multiple API calls via a single HTTP request.

To do this, make a request with method ```POST``` and path ```/api;compound```.

The content of the request should be JSON-encoded (```Content-Type: application/json```)
and encodes all the requests you want to make in an array called ```actions```.

Keys in the ```actions``` array are arbitrary, but actions will be run in the order
they appear in the JSON.  i.e. ```actions``` may be a list like
```[ action1, action2, ... ]``` or an associative array like
```{ "act1": action1, "act2": action2, ... }```.

Each action has a ```method``` (e.g. ```GET```, ```PATCH```, ```DELETE```)
a ```path``` that will be appended to ```/api``` (e.g. ```/properties```)
and optionally ```queryString``` and ```content``` or ```contentObject```
(```content``` being a string containing what would have been the HTTP
request content, ```contentObject``` being the JSON object that would
have been encoded by that string).

```json
{
	"actions": [
		{
			"method": "POST",
			"path": "/formulas",
			"contentObject": [
				{
					"expressionText": "23 * f",
					...
				},
				...more formulas?...
			]
		},
		{
			"method": "DELETE",
			"path": "/formulas/1234"
		},
		{
			"method": "DELETE",
			"path": "/formulas/1235"
		}
	]
}
```

If any of the sub-actions fail, you'll get an error response for the entire batch.

Otherwise you'll get a status code of 200 for the batch.

The response will be a JSON-encoded object with a ```actionResults``` array whose
keys correspond to the ```actions``` array you passed in.
Each will have a ```statusCode```.
If there is any content there will also be a ```contentObject``` key mapping
to an array or something.
