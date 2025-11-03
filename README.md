# WATCHMAN

This is my personal playground to use the TMDB (The Movie Database) API to fetch
all series having a premier or a season start in the next 7 days.

Use as you like. No license. If this deletes your hard-drive, don't blame me.

Create a file `private/token.env` containing your API token.

Running `bin/watchman` will parse data into `public/index.html` (and an archive).

Maybe run this via cronjob every 7 days, I guess?

Let me know if this is something you use.

# Specials

You can create a `private/hook.php` file that is executed on each indexing,
for example to send a mail.
