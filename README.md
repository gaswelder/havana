# Havana

Havana is a lightweight PHP library for making web backends.


## Hello world

```php
use havana\app;

$app = new app(__DIR__);
$app->get('/', function() {
    return '<p>Howdy, Globe!</p>';
});
$app->run();
```

## Configuration

This library parses `.env` file in the application root and applies the values to its environment variables list. The file might look like this:

```
# Comments
DATABASE=mysql://root:root@127.0.0.1/dbname
DEBUG=1
```

The `.env` file provides "default" values. That means, if a variable has been defined before (for example, by the parent process or a system config), it won't be overwritten with a value from the .env file.


## Serving URLs

Use `get` or `post` function, depending on the request method, and provide the URL pattern and a callable function:

```php
$app->get('/', function() {...})
$app->post('/', function() {...})
```

URL patterns are defined as parts separated by slashes. Each part as a regular string, but may contain PCRE expressions encosed in curly braces:

```php
$app->get('/items/sku{\d+}', function($sku) {
    // ...
});
$app->get('/users/{[a-z0-9]+})', function($username) {
    // ...
});
```

Note that since slashes are handles separately, a regular expression in the braces can't ever match a slash.

The extracted arguments are passed to the callback in the same order as they were defined:

```php
$app->get('/archive/{\d\d\d\d}/{\d\d}', function($year, $month) {
    
});
```

The leading and the trailing slashes in URL templates are optional.


## Response

If a URL callback returns a string, it is assumed to be HTML to serve with a `200 OK` status:

```php
$app->get('/world-communism', function() {
    return '<body>Under construction</body>';
});
```

This allows the most common use case (generating HTML from templates) to be simple:

```php
$app->get('/', function() {
    return tpl('homepage');
});
```

If a number is returned, it is assumed to be an HTTP status code:

```php
$app->get('/politicians/conscience', function() {
    return 404;
});
```

Of course "magic constants" are not always friendly, but everyone knows what 404 is. Anyway, status codes are also enumerated in the `response` class:

```php
$app->get('/snowden', function() {
    return response::STATUS_NOTFOUND;
});
```

A `response` object can be returned explicitly when specific response parameters are needed:

```php
$app->get('/dev/random', function() {
    $response = (new response)
        ->setHeader('Cache-Control', 'no-cache')
        ->setHeader('Content-Type', 'text/plain')
        ->setContent('42');
    return $response;
});
```

There are also several factory functions for some common cases:

```php
$app->get('/privatedoc', function() {
    // This file doesn't have to be in webserver's root.
    return response::staticFile('/home/username/treasure-map.pdf');
});
$app->get('/feed/random', function() {
    return response::json(['value' => mt_rand()]);
});
$app->get('/where-is-johhny', function() {
    return response::redirect('/here-is-johhny');
});
```

The `redirect` method accepts an HTTP code as the second argument. By default it is `302` (`Found`), which is safe for use in most of the cases (like redirecting a user after making a `POST` operation). But sometimes it's needed to tell browsers that the resource has moved:

```php
$app->get('/where-is-johhny', function() {
    return response::redirect('/here-is-johhny', response::MOVED_PERMANENTLY);
});
```

Since these factory functions return a `response` object, it can be further tweaked before returning:

```php
$app->get('/feed/random', function() {
    return response::json(['value' => mt_rand()])->setHeader('Cache-Control', 'no-cache');
});
```

## Request

The `request` class is a static class that works with the `$_SERVER`, `$_POST`, `$_GET` and `$_FILES` superglobals.

To get the POST fields use the `request::post($name)` method:

```php
$login = request::post('login');
$password = request::post('password');
```

Likewise, for query parameters use the `get` method:

```php
$searchTerm = request::get('q');
```

To get the requested URL:

```php
$url = request::url();
```

The returned url is a url object. It has the `__toString` method so it can be used as a string. But it also can be used to get information that is not easily accessible otherwise:

```php
echo $url->path; // /index.htm
echo $url->port; // null
echo $url->scheme; // http
echo $url->host; // example.net
echo $url->domain; // http://example.net
```

To get uploaded files use the `files` method:

```php
$uploads = request::files('photos');
$upload = $uploads[0];
```

The `files` method works the same regardles of whether the file input was "multiple" or not. In all cases an array of `upload` objects is returned. An `upload` object can be investigated with methods:

```php
echo $upload->type(); // image/jpeg
echo $upload->name(); // mycat.jpg
echo $upload->size(); // 627213
```

The file can be saved to a specific path:

```php
$upload->saveTo('uploads/theircat.jpg');
```

It can also be saved with the name generated automatically:

```php
$path = $upload->saveToDir('uploads');
// uploads/59cee1d6c90c9.jpeg
```


## Database

The `DATABASE` environment variable must be set to the URL of the database resource, for example:

    DATABASE=mysql://user:password@dbhost.net

A database object can be accessed with the global `db` function:

```php
$total = db()->getValue('select sum(amount) from transactions');
```

The database object has a set of methods for common tasks:

```php
$rows = db()->getRows('select sum, timestamp from transactions where from_id = ?', 42);
$first = db()->getRow('select * from users where name = ?', 'John');
$total = db()->getValue('select sum(amount) from transactions where original_currency = ?', 'btc');
$suckers = db()->getValues('select distinct from_id from transactions');
$id = db()->insert('users', ['email' => 'bob@example.net', 'name' => 'Bob']);
db()->update('users', ['status' => 'approved'], ['email' => 'bob@example.net']);
db()->exec('update transactions set amount = amount * 2 where original_currency = ?', $cur);
```


## Database models

Database models are object abstractions on top of table rows. To create a database object model extend the `dbobject` class and define the `TABLE_NAME` constant:

```php
class article extends dbobject {
    const TABLE_NAME = 'articles';
    const TABLE_KEY = 'id';
}
```

The models will use the same `db()` instance underneath. `TABLE_KEY` defines the name for the primary key column. If it's omitted, then "id" is assumed.

To get an instance of the model by its primary key use the static `get` method:

```php
$article = article::get(12);
```

If there is no such key, `$article` will be `null`.

A `dbobject` is aware only of its primary key and the fields explicitly defined in its class. So in the article model above only the `id` column will be selected and updated by the generated queries. To make the model aware of other columns in the database add them as fields:

```php
class article extends dbobject {
    const TABLE_NAME = 'articles';
    const TABLE_KEY = 'id';

    public $title;
    public $content;
}
```

Now title and content will be fetched from the database too:

```php
$article = article::get(12);
echo $article->title;
```

An attempt to read or set any field that is not explicitly defined will trigger an error:

```php
$article->random_field = true;
// Exception: unknown field in the 'article' class
```

Calling `save` on a model will result in an update operation in the database or an insert if the model was created rather than fetched.

```php
$article = new article();
$article->title = "Article name":
$argicle->save();
    // insert into articles (title) values ('Article name');

$article->title .= " (updated)";
$article->save();
    // update articles set title = 'Article name (updated)' where id = 12
```

Sometimes naive object mapping is not powerful enough. For those cases it's possible to instantiate a model from a result of an arbitrary SQL query:

```php
$row = db()->getRow("
    select a.* from articles a
    JOIN article_index ai ON ai.article_id = a.id
    WHERE a.score > 10
    ORDER BY ai.fulltext @@ plainto_tsquery(?) desc limit 1", "some search phrase");
$article = article::fromRow($row);
```

If `$row` is null, then `$article` will also be null.

Note that even though we select all columns in the query, the model will pick from the returned row only those columns that it knows about (title and content in the example above).


### Relationships

Suppose in addition to articles we also have authors:

```php
class author extends dbobject {
    const TABLE_NAME = 'authors';
}
```

Getting an article's author is achieved by making a straightforward lookup by a foreign key:

```php
class article extends dbobject {
    const TABLE_NAME = 'articles';
    public $title;
    public $content;
    private $author_id;

    function author() {
        return author::findOne(['id' => $this->author_id]);
    }
}
```

Note that we added the `author_id` field to make it accessible by our model.


## Session

The `user` static object is a wrapper around existing PHP's session mechanism. It initializes sessions on demand, so if it's not used, the session won't be touched.

To authorize the user call the `addRole` function:

```php
if (password_ok()) {
    user::addRole('customer');
}
```

The first argument is just a "role name" that can be chosen freely by the application.

To check if the user has a role, use the `getRole` function:

```php
if (!user::getRole('admin')) {
    unauthorized();
}
```

Often there's an identifier that goes along with the role, thus the second argument for the `auth`:

```php
$account_id = check_login();
if ($account_id) {
    user::addRole('customer', $account_id);
}
```

The given value is only stored, so the application is free to apply its own semantics to both values.

To get the identier back, use the `id` function:

```php
$account_id = user::getRole('customer')->id;
```

## Templates

The templates processed by the `tpl` function are regular PHP files with one addition. The double braces syntax is parsed and translated into regular `<?= ?>` PHP tags with the contents wrapped in `htmlspecialchars` function. So, this template:

```php
<a href="/user/{{$id}}">{{$name}}</a>
```

is equivalent to:

```php
<a href="/user/<?= htmlspecialchars($id) ?>"><?= $name ?></a>
```
