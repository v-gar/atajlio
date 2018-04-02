<?php
/**
 * ATAJLIO - SIMPLE LINK SHORTENER
 * (c) 2018 Viktor Garske (info@v-gar.de)
 *
 * Atajlio is a very easy one-file link shortener service
 * for your server.
 *
 * --------------------------------------------------------------------
 *                      QUICK START GUIDE:
 * 1. ensure that your server supports PHP (preferably version >=7.0),
 *    PDO and (PDO) SQLite3
 * 2. place this file in your webroot (you even can rename it, too!)
 * 3. configure (if needed, standard values work as well) everything
 *    from line 68 to 72
 * 3.1 $dbname is the location of the SQLite3 database.
 *     default: atajlio.db in the same directory as this script
 * 3.2 $minLinkLength is the length of the generated shortlinks
 * 3.3 $defaultUrl is the redirect URL when no arguments are given
 *     (no shortlink,...)
 *     tip: use your homepage or so
 * 4. configure a URL rewrite to this script and the GET parameter
 *    ?l=...
 *    e.g. https://vgapps.de/-id1234 --> index.php?l=id1234
 * 5. use this pattern to configure $linkPrefix
 *    e.g.      private static $linkPrefix = 'https://vgapps.de/-';
 * 6. ---------------- VERY IMPORTANT! ----------------
 *    SECURE THE DATABASE: DENY ACCESS FROM OUTSIDE!
 *
 * --------------------------------------------------------------------
 *
 * LICENSE (MIT-License):
 * Copyright 2018 Viktor Garske
 *
 * Permission is hereby granted, free of charge,
 * to any person obtaining a copy of this software
 * and associated documentation files (the "Software"),
 * to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge,
 * publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice
 * shall be included in all copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
 * OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE
 * OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Configuration class
 * Configure your settings here
 */
class Config
{
    private static $dbtype = 'sqlite';
    private static $dbname = './atajlio.db';
    /*private static $dbhost = '';
    private static $dbuser = '';
    private static $dbpass = '';*/

    private static $minLinkLength = 4;

    private static $defaultUrl = '';

    private static $linkPrefix = '';

    public static function getConfig()
    {
        return array(
            'dbtype' => self::$dbtype,
            'dbname' => self::$dbname,
            /*'dbhost' => self::$dbhost,
            'dbuser' => self::$dbuser,
            'dbpass' => self::$dbpass,*/
            'minlinklength' => self::$minLinkLength,
        );
    }

    public static function getMinLinkLength()
    {
        return static::$minLinkLength;
    }

    public static function getLinkPrefix()
    {
        return static::$linkPrefix;
    }

    public static function getAdminUrl()
    {
        $fs = new FirstSetup();
        if(!$fs->checkSetupNeeded()) {
            // Database init
            $db = new Database();
            if($db->connect())
                $pdo = $db->getPdo();
            else
                return false;

            $sql = Database::getSelectConfigValueByKeySql();
            $statement = $pdo->prepare($sql);
            $statement->execute([':key' => 'admin_url']);

            $result = [];

            while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $result[] = [
                    'key' => $row['key'],
                    'value' => $row['value']
                ];
            }

            if(sizeof($result) > 0) {
                return $result[0]['value'];
            } else {
                echo "No key";
                return false;
            }
        } else {
            echo "Error E-GAU-DB: Setup error: database not available";
        }
    }

    public static function getDefaultUrl()
    {
        if(!static::$defaultUrl == '')
            return $this->defaultUrl;
        else
            return false;
    }
}

/**
 *
 */
class FirstSetup
{
    private $setupNeeded = false;

    private $adminUrl;

    function __construct()
    {
        $config = Config::getConfig();
        if($config['dbtype'] == 'sqlite') {
            if(!file_exists($config['dbname'])) {
                $this->setupNeeded = true;
            }
        }
    }

    public function checkSetupNeeded()
    {
        return $this->setupNeeded;
    }

    private function getSqlSchema()
    {
        return
        "
            BEGIN TRANSACTION;
            CREATE TABLE IF NOT EXISTS `link` (
            	`id`	INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
            	`shortlink`	TEXT NOT NULL UNIQUE,
            	`url`	TEXT NOT NULL,
            	`created`	TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            	`counter`	INTEGER
            );
            CREATE TABLE IF NOT EXISTS `config` (
            	`key`	TEXT NOT NULL,
            	`value`	TEXT,
            	PRIMARY KEY(`key`)
            );
            COMMIT;
        ";
    }

    public function setup()
    {
        if(!$this->setupNeeded)
            return false;

        $db = new Database();
        if($db->connect())
            $pdo = $db->getPdo();
        else
            return false;

        // create the tables
        $pdo->exec($this->getSqlSchema());

        // schema version
        $stmt_schemaversion = $pdo->prepare(Database::getNewConfigValueSql());
        $stmt_schemaversion->bindValue(':key', 'schema_version');
        $stmt_schemaversion->bindValue(':value', '1');
        $stmt_schemaversion->execute();

        // admin url
        $this->adminUrl = StringGenerator::generateRandomString(16);
        $stmt_adminurl = $pdo->prepare(Database::getNewConfigValueSql());
        $stmt_adminurl->bindValue(':key', 'admin_url');
        $stmt_adminurl->bindValue(':value', $this->adminUrl);
        $stmt_adminurl->execute();

        return true;
    }

    public function getAdminUrl()
    {
        return $this->adminUrl;
    }
}

/**
 *
 */
class Database
{
    private $pdo;

    private $config;

    function __construct()
    {
        $this->config = Config::getConfig();
    }

    public function connect()
    {
        if($this->pdo == NULL) {
            try {
                if($this->config['dbtype'] == 'sqlite') {
                    $this->pdo = new PDO('sqlite:' . $this->config['dbname']);
                    return true;
                }
            } catch (PDOException $e) {
                echo "Error while establishing connection: " . $e;
            }
        }
        return false;
    }

    public function getPdo()
    {
        if($this->pdo != NULL)
            return $this->pdo;
    }

    public static function getNewConfigValueSql()
    {
        return "INSERT INTO config(key, value) VALUES(:key, :value)";
    }

    public static function getNewUrlSql()
    {
        return "INSERT INTO link(shortlink, url, counter)
            VALUES(:shortlink, :url, 0)";
    }

    public static function getSelectUrlByShortlinkSql()
    {
        return "SELECT * FROM link WHERE shortlink = :shortlink";
    }

    public static function getSelectConfigValueByKeySql()
    {
        return "SELECT * FROM config WHERE key = :key";
    }
}

/**
 *
 */
class StringGenerator
{
    public static function generateRandomString($length = 10)
    {
        $characters = '0123456789';
        $characters .= 'abcdefghijklmnopqrstuvwxyz';
        $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

/**
 * Link represents a
 */
class Link
{
    private $url;
    private $shortlink;
    private $pdo;

    private $valid = false;

    function __construct($url, $customShortlink = NULL, $pdo = NULL)
    {
        $this->url = $url;

        if(filter_var($url, FILTER_VALIDATE_URL)) {
            $this->valid = true;
            if($customShortlink == NULL) {
                $this->generateShortlink();
            } else {
                // query whether shortlink isn't in use
                $query = new Query($customShortlink, $this->pdo);
                if($query->getSuccess()) {
                    $this->generateShortlink();
                } else {
                    $this->shortlink = $customShortlink;
                }
            }
        } else {
            return false;
        }

        // Database
        if($pdo == NULL) {
            $db = new Database();
            if($db->connect())
                $this->pdo = $db->getPdo();
            else
                return false;
        } else {
            $this->pdo = $pdo;
        }
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getValidStatus()
    {
        return $this->valid;
    }

    public function getShortlink()
    {
        return ($this->valid ? $this->shortlink : false);
    }

    private function generateShortlink()
    {
        $linkProposal = '';
        $query = false;
        /* generate link proposals for the shortlink
        until a not already used link is found */
        do {
            $linkProposal = StringGenerator::generateRandomString(
                Config::getMinLinkLength()
            );
            $query = new Query($linkProposal, $this->pdo);
        } while ($query->getSuccess()); // check whether used already
        $this->shortlink = $linkProposal;
    }

    public function save()
    {
        if($this->pdo == NULL)
            return false;

        $sql = Database::getNewUrlSql();
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':shortlink', $this->shortlink);
        $statement->bindValue(':url', $this->url);
        $statement->execute();

        return true;
    }
}

/**
 * Query handler gets the shortlink query and returns the url if possible
 */
class Query
{
    private $query;
    private $pdo;

    private $successful = false;
    private $url;

    function __construct($query, $pdo=NULL)
    {
        $this->query = $query;

        // Database
        if($pdo == NULL) {
            $db = new Database();
            if($db->connect())
                $this->pdo = $db->getPdo();
            else
                return false;
        } else {
            $this->pdo = $pdo;
        }

        $this->handle();
    }

    private function handle()
    {
        if($this->pdo == NULL)
            return false;

        $sql = Database::getSelectUrlByShortlinkSql();
        $statement = $this->pdo->prepare($sql);
        $statement->execute([':shortlink' => $this->query]);

        $result = [];

        while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $result[] = [
                'shortlink' => $row['shortlink'],
                'url' => $row['url']
            ];
        }

        if(sizeof($result) > 0) {
            $this->url = $result[0]['url'];
            $this->successful = true;
        }
    }

    public function getSuccess()
    {
        return $this->successful;
    }

    public function getUrl()
    {
        if($this->successful)
            return $this->url;
        else
            return false;
    }
}


/**
 * The requests handler handles incoming requests
 */
class RequestHandler
{
    private $getRequest = [];

    function __construct($getRequest)
    {
        $this->getRequest = $getRequest;
        $this->handle();
    }

    private function handle()
    {
        if(array_key_exists('l', $this->getRequest)) {
            $value = htmlspecialchars($this->getRequest['l']);
            $query = new Query($value);
            if($query->getSuccess()) {
                HttpHelper::redirect($query->getUrl());
            } else {
                if(Config::getAdminUrl() != false &&
                   Config::getAdminUrl() == $value) {
                       /* Administration */

                       /* POST request -> creating a link */
                       if(isset($_POST['action']) &&
                          isset($_POST['url'])) {
                           if($_POST['action'] == "newlink" &&
                              $_POST['url'] != "" &&
                              $_POST['customurl'] != "") {
                               /* Create the link */
                               $link = new Link(
                                   $_POST['url'],
                                   ($_POST['customurl'] != '' ?
                                   $_POST['customurl'] : NULL)
                               );
                               if($link->save()) {
                                   /* Return the shortlink */
                                   echo "Your new link: " .
                                   Config::getLinkPrefix() .
                                   $link->getShortlink();
                               } else {
                                   echo "Something went wrong... Did you ".
                                   "use a valid URL?";
                               }
                           } else {
                               Template::getAdminPage();
                           }
                       } else {
                           Template::getAdminPage();
                       }
                } else {
                    if(Config::getDefaultUrl() != false) {
                        HttpHelper::redirect(Config::getDefaultUrl());
                    } else {
                        echo "Couldn't find link.";
                    }
                }
            }
        }
    }
}

class HttpHelper {
    public static function redirect($url)
    {
        Header('Location: ' . $url);
    }
}

class Template {
    public static function getAdminPage() {
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Atajlio Admin</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <style type="text/css">
            body {
                font-family: "DejaVu Sans", "Arial", sans-serif;
                background-color: #e8e8e8;
            }
            div.box {
                width: 50%;
                margin: auto;
                border: 1px solid grey;
                background-color: white;
                text-align: center;
            }
            input[type=text], input[type=password] {
                border: 1px solid black;
                padding: 4px;
                font-size: 16pt;
            }

            input[type=submit] {
                background-color: #009933;
                border: 1px solid grey;
                width: 30px;
                height: 30px;
                color: white;
            }

            div.copyright {
                font-size: 80%;
                padding-top: 10px;
                padding-bottom: 10px;
            }

            a {
                color: black;
                border-bottom: 1px dotted grey;
            }

            a:hover {
                border-bottom: 1px solid grey;
            }

            @media only screen and (max-width: 600px) {
                div.box {
                    width: 99%;
                }
            }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Atajlio Link-Shortener</h1>
            <form method="post">
                <input type="text" name="url" placeholder="URL" />
                <input type="text" name="customurl"
                placeholder="Custom Short URL (optional)" />
                <input type="hidden" name="action" value="newlink" />
                <!--<input type="password" name="pw"
                placeholder="Your admin password" />-->
                <input type="submit" value=">" />
            </form>
            <div class="copyright">
                &copy; 2018
                <a href="https://www.v-gar.de/">
                    Viktor Garske
                </a>
            </div>
        </div>
    </body>
</html>

<?php
    }

    public static function getFirstStartPage($adminurl) {
        echo "<h1>Welcome at Atajlio!</h1>";
        echo "<h2>The initial configuration was successful!</h2>";
        echo "<h2>Your secret admin URL for adding URLs is: "
            . $adminurl . "</h2>";
        echo "<p>You can <a href='?l=$adminurl'>start</a> now. Have fun!</p>";
    }
}

/*
------------------------------------------------------------------------------
*/

/* check whether DB exists - otherwise try to create it */
$fs = new FirstSetup();
if($fs->checkSetupNeeded()) {
    if($fs->setup())
        Template::getFirstStartPage($fs->getAdminUrl());
    else
        echo "Error while initializing the database";
}

/* handle incoming requests */
new RequestHandler($_GET);

?>
