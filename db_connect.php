<?php
 
/**
 * ### Sponsor.net.ua ###
 * A class file to connect to database
 */
class DB_CONNECT {

    // constructor
    function __construct() {  }
 
    // destructor
    function __destruct() { }

    /**
     * Connecting to the database
     * @return mysqli
     */
    function connect() {
        // import database connection variables
        require_once __DIR__ . '/db_config.php';
 
        // Connecting to mysql database
        $link = mysqli_connect(DB_SERVER, DB_USER, DB_PASSWORD, DB_DATABASE);

        if (!$link) {
//            echo "Error: Unable to connect to MySQL." . PHP_EOL;
//            echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
//            echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
            exit;
        }

//        echo "Success: A proper connection to MySQL was made! The my_db database is great." . PHP_EOL;
//        echo "Host information: " . mysqli_get_host_info($link) . PHP_EOL;
        
        mysqli_set_charset($link, "utf8");

        // returing connection cursor
        return $link;
    }
}