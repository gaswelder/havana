<?php
namespace havana;

use PDO;

class dbclient_sqllite extends dbclient
{
    function __construct($url) {
        $u = parse_url($url);
        $dbname = substr($u['path'], 1);
        $spec = 'sqlite:'.realpath($dbname);
        $this->db = new PDO($spec, null, null);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        /*
		 * If we work with mysql and mysqlnd is used, get it to
		 * preserve value types.
		 */
        $dr = $this->db->getAttribute(PDO::ATTR_CLIENT_VERSION);
        if (strpos($dr, 'mysqlnd') !== false) {
            $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        /*
         * The `charset` parameter in DSN doesn't work
         * reliably, thus this query.
         */
        $this->db->exec("SET NAMES UTF8");
        /*
         * Use standard quoting, not mysql-specific.
         */
        $this->db->exec("SET SESSION sql_mode = 'ANSI'");
    }
}
