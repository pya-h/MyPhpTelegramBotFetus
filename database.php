<?php
require_once './config.php';

defined('RELATED_DATA_SEPARATOR') or define('RELATED_DATA_SEPARATOR', '/');
defined('DATA_JOIN_SIGN') or define('DATA_JOIN_SIGN', '<>');
defined('INDEX_SEPARATOR') or define('INDEX_SEPARATOR', ':');

// database tables
defined('DB_TABLE_USERS') or define('DB_TABLE_USERS','users');

defined('DB_TABLE_MESSAGES') or define('DB_TABLE_MESSAGES','messages');

// database table:COMMON fields
defined('DB_ITEM_ID') or define('DB_ITEM_ID','id');
defined('DB_ITEM_NAME') or define('DB_ITEM_NAME','name'); // for both course and teacher tables

// database table user fields:
defined('DB_USER_ID') or define('DB_USER_ID','id');
defined('DB_USER_ACTION') or define('DB_USER_ACTION','action');
defined('DB_USER_MODE') or define('DB_USER_MODE','mode');
defined('DB_USER_ACTION_CACHE') or define('DB_USER_ACTION_CACHE','action_cache');

//database table:messages fields
defined('DB_MESSAGES_SENDER_ID') or define('DB_MESSAGES_SENDER_ID','sender_id');
defined('DB_MESSAGES_ANSWERED') or define('DB_MESSAGES_ANSWERED','answered');


defined('MAX_GODS') or define('MAX_GODS', 2);


// user modes:
defined('NORMAL_USER') or define('NORMAL_USER', 0);
defined('ADMIN_USER') or define('ADMIN_USER', 1);
defined('GOD_USER') or define('GOD_USER', 2);

// database engine
class Database {
  private $connection;
  private static $database;

  public static function getInstance($option = null): Database
  {
    if (self::$database == null){
      self::$database = new Database($option);
    }

    return self::$database;
  }

  private function __construct(){
    $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($this->connection->connect_error) {
      echo "Connection failed: " . $this->connection->connect_error;
      exit;
    }
    $this->connection->query("SET NAMES 'utf8'");
  }

  public function update($sql_query, $query_data = array()){
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }
    return $result;
  }

  public function insert($sql_query, $query_data = array()){
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }
    return mysqli_insert_id($this->connection);;
  }

  private function safeQuery($sql_query, $query_data){
    if($query_data)
      foreach ($query_data as $key=>$value){
        $value = $this->connection->real_escape_string($value);
        $value = "'$value'";

        $sql_query = str_replace(":$key", $value, $sql_query);
      }
    return $this->connection->query($sql_query);
  }

  public function query($sql_query, $query_data = null, $specific_column = null){
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }

    $records = array();
    if ($result->num_rows == 0) {
      return $records;
    }
    while($row = $result->fetch_assoc()) {
      $records[] = !$specific_column ? $row : $row[$specific_column];
    }
    return $records;
  }

  public function getConnection(): mysqli
  {
    return $this->connection;
  }

  public function fuckoff(){
    $this->connection->close();
  }

}

// project specific functions:

function getStatistics(): array
{
    $db = Database::getInstance();
    $users = $db->query('SELECT * FROM ' . DB_TABLE_USERS);
    $other_tables = array(DB_TABLE_COURSES => 'تعداد درس ها', DB_TABLE_MESSAGES => "تعداد پیام های کاربران");
    $admins = array_filter($users, function($item) {
        return $item[DB_USER_MODE] == ADMIN_USER;
    });
    $stats = array(DB_TABLE_USERS => array('total' => count($users), 'fa' => 'تعداد کل کاربران ربات'), 'admins' => array('total' => count($admins), 'fa' => 'تعداد ادمین ها'));
    foreach($other_tables as $table=>$table_fa) {
        $result = $db->query("SELECT COUNT(*) AS TOTAL FROM $table");
        $stats[$table] = array('total' => $result[0]['TOTAL'] ?? 0, 'fa' => $table_fa);
    }
    return $stats;
}
