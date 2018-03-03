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

This library parses `.env` file in the application root and updates its environment variables from it. The file might look like this:

```
# Comments
DATABASE=mysql://root:root@127.0.0.1/dbname
DEBUG=1
```

The `.env` file provides "default" values. That means, if a variable has been defined beforehand (for example, by the parent process or in a system config), it won't be overwritten by the `.env` file.

## Serving URLs

URL handlers are declared with the `get` and `post` functions, which take a URL pattern and a callable:

```php
$app->get('/', function() {...})
$app->post('/', function() {...})
```

URL patterns are defined as parts separated by slashes. Each part is a regular string, but may also contain PCRE expressions enclosed in curly braces:

```php
// Will match '/items/sku4', '/items/sku56'
$app->get('/items/sku{\d+}', function($sku) {
    // ...
});

// Will match 'users/john', 'users/bob'
$app->get('/users/{[a-z0-9]+})', function($username) {
    // ...
});
```

Each pattern is first split into parts (using slashes as separators) and then each part is filtered through the matcher, so a regular expression in braces couldn't possibly encounter a slash.

Every regular expression results in an argument added to the handler's function call:

```php
$app->get('/archive/{\d\d\d\d}/{\d\d}', function($year, $month) {

});
```

The leading and the trailing slashes in URL templates are optional. The three following definitions are equivalent:

```php
$app->get('foo', $foo);
$app->get('/foo', $foo);
$app->get('/foo/', $foo);
```

## Response format

If a URL callback returns a string, it is assumed to be HTML and is served with a `200 OK` status:

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

If a number is returned, it is assumed to be an HTTP status code. The example below will produce a `404 Not Found` response:

```php
$app->get('/politicians/conscience', function() {
    return 404;
});
```

Everyone knows what 404 is, but status codes are also enumerated as constants in the `response` class:

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

## Middleware

Middleware functions are a generalization of pre-response and post-response hooks. As the response hooks, middleware is typically used to check some conditions or modify the returned response in some way.

Middleware functions are added using the `middleware` method and themselves accept a function that returns the response as the only argument. For example, a middleware that implements a login guard might look like this:

```php
$app->middleware(function($next) {
    if (!user::getRole('user') && request::url()->path != '/login') {
        return response::redirect('/login');
    }
    return $next();
});
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

Templates are loaded from the application's `templates` directory by name using the `tpl` function, which takes the template's name and optional key-value array of arguments. For example, the following handler will process the template from the `templates/home.php` file:

```php
$app->get('/', function() {
    return tpl('home');
});
```

The templates are regular PHP files with one addition. The double braces syntax is parsed and translated into regular `<?= ?>` PHP tags with the contents wrapped in `htmlspecialchars` function. So, this template:

```php
<body>
<p>Hello, {{$name}}</p>
</body>
```

is equivalent to:

```php
<body>
<p>Hello, <?= htmlspecialchars($name) ?></p>
</body>
```

and this call:

```php
tpl('home', ['name' => 'Bob']);
```

will return string:

```html
<body>
<p>Hello, Bob</p>
</body>
```

## Namespace

All public objects are in the `havana` namespace:

```php
use havana\app;
use havana\request;
use havana\response;
use havana\user;
```

There are also few global functions:

```php
db();
dd($var, ...);
dump($var, ...);
panic($message);
```

Any error triggered by the library will be thrown as an instance of `havana\Exception`.

## Command-line scripts

It's possible to define command line scripts using the `cmd` function:

```php
$app->cmd('echo', function($args) {
    foreach ($args as $arg) {
        echo $arg, "\n";
    }
});
```

To run a defined command simply call the main script from the command line:

```
$ php main.php echo one two
one
two
```

When the application is run, it will detect that it's being run from a command-line interface and will call the corresponding command instead of serving the HTTP request. This mode is useful for running maintenance scripts as they will have access to all the objects the regular web script has.
