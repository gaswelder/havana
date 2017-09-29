<?php

function panic($message) {
	throw new Exception($message);
}

set_error_handler(function ($errno, $msg, $path, $line, $context) {
	panic("$msg at $path:$line");
});

require __DIR__.'/app.php';
require __DIR__.'/lang.php';
require __DIR__.'/mail.php';
require __DIR__.'/match.php';
require __DIR__.'/mime.php';
require __DIR__.'/response.php';
require __DIR__.'/request.php';
require __DIR__.'/upload.php';
require __DIR__.'/db/db.php';
require __DIR__.'/db/dbobject.php';
require __DIR__.'/tpl.php';
require __DIR__.'/user.php';
require __DIR__.'/func.php';