# gist.lua
## Gist program and proxy script for ComputerCraft.

By necKro 2013, WTFPL.  Do what thou wilt.

### `gist.lua`

You must edit this script to include the URL to `gist-proxy.php`.  Then, put
it on PasteBin and retrieve it using ComputerCraft's `pastebin` script.

Usage:

    gist (update/u) (Filename)
    gist (get/g) (Gist ID)/(Commit) (Filename)
    gist (put/p) (Filename)

With `gist get`, the filename is optional.  If not specified, the filename on
the Gist will be used. Existing files will not be overwritten.

`gist update` will overwrite existing files, so be careful!

### `gist-proxy.php`

This script handles the GitHub API logic and also functions as an HTTPS proxy.

The script accesses the GitHub API anonymously, so requests are throttled to
(currently) 60 per hour.  If logging is enabled, the number of remaining API
requests is also logged.

To enable logging of requests, edit the `$log_file` variable at the top.

REQUIREMENTS: Requires at least PHP 5.3, I think.  Developed with 5.4.
Must have `pecl_http` and `php5-curl` installed.  In Debian:
`sudo apt-get install php5-dev php5-curl php-pear && sudo pecl install pecl_http`

Script URL parameters:

` ?gist=[gist id]/[commit] `
- Retrieve Gist ID or hash, full or partial commit hash optional.
- First line returned is the filename.
- Gist URL added to first line of returned script.
- Strips ".lua" extension from filenames on retrieve.

` ?filename=[filename]&data=[data] `
- POST or GET.  Post Gist as [data] with [filename].
- ".lua" file extension added on post.
- First line returned is Gist ID.
- Second line returned is forked ID (if any).
- Returns HTTP/400 on error.
