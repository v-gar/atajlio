# Atajlio
Atajlio is a very easy one-file link shortening service for your web server.
## Installation:
1. ensure that your server supports **PHP** (preferably version >=7.0), PDO and (PDO) **SQLite3**
2. **download** [index.php](https://github.com/v-gar/atajlio/raw/master/index.php)
3. **place** this file in your webroot (you even can rename it, too!)
4. **configure** (if needed, standard values work as well) everything from line 68 to 72
   1. `$dbname` is the location of the SQLite3 database.
   
      *default:* atajlio.db in the same directory as this script
   2. `$minLinkLength` is the length of the generated shortlinks
   3. `$defaultUrl` is the redirect URL when no arguments are given (no shortlink,...)
   
       tip: use your homepage or so
5. **configure** a URL rewrite to this script and the GET parameter `?l=...`

    e.g. `https://vgapps.de/-id1234 --> index.php?l=id1234`
6. use this pattern to configure `$linkPrefix`
   e.g. `private static $linkPrefix = 'https://vgapps.de/-'`;
7. **VERY IMPORTANT!** SECURE THE DATABASE: DENY ACCESS FROM OUTSIDE!
8. make sure that Atajlio can **write in the database directory** (default: own directory)
9. **open** the Atajlio file (default name: index.php) on the server so that Atajlio can install itself
10. Atajlio shows an admin URL after a successful installation. Use it like a short link to access the admin interface.
