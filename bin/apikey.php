<?php
/**
 * Fill these out when you want to use the examples and rename or copy it to apikey.php
 */

// Specify the later introduced bearer token
define('TMDB_BEARER_TOKEN', 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiIyY2NjNjdlZDE3ZTNlMzFiY2JjYWJhZGY0NWZiZjZiYiIsIm5iZiI6MTc0NzcyOTU3MC42MDgsInN1YiI6IjY4MmMzY2EyNjNmYzNjMjI4YTJmNzg1MCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.ApsoowYy0NLPM6EWtjtTM8abzG2uBVfaDEUbpsR7SHQ');

// Or specify the which I guess can now be considered "legacy api key".
define('TMDB_API_KEY', '2ccc67ed17e3e31bcbcabadf45fbf6bb');

// Globals
define('TMDB_LANGUAGE', 'en-US');
define('TMDB_REGION', 'us');

// Session based
define('TMDB_REQUEST_TOKEN', 'TMDB_REQUEST_TOKEN'); // for accounts
define('TMDB_SESSION_TOKEN', 'TMDB_SESSION_TOKEN'); // for accounts
define('TMDB_GUEST_SESSION_TOKEN', 'TMDB_GUEST_SESSION_TOKEN'); // for guest sessions
define('TMDB_ACCOUNT_ID', 'TMDB_ACCOUNT_ID'); // numeric id, fetch through account info

// Account based
define('TMDB_LIST_ID', 'TMDB_LIST_ID');
