<?php

namespace Solder\service\database;

use PDO;
use PDOException;
use PDOStatement;
use Solder\exception\DatabaseException;
use Solder\Solder;

class DatabaseService
{
  private static int $serviceStarted;
  private readonly array $databaseConfig;
  private static PDO $database;

  public function __construct ()
  {
    $this->databaseConfig = json_decode(file_get_contents(Solder::getPath() . '/config/database/mariadb.json'), true);

    $this->initial();
  }
  
  /**
   * @throws DatabaseException
   */
  private function initial (): void
  {
    if (!class_exists(PDO::class)) {
      throw new DatabaseException("Class PDO not found", 404);
    }
    self::$serviceStarted = time();
    $config = $this->databaseConfig;

    try {
      $pdo = new PDO('mysql:host=' . $config['host'] . ';charset=utf8;dbname=' . $config['database'].';port='.$config['port'], $config['user'], $config['password'], [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      self::$database = $pdo;
    } catch (PDOException $e) {
      throw new DatabaseException($e->getMessage(), 0);
    }
  }

  /**
   * @deprecated
   *
   * @param              $statement
   * @return array
   */
  public function fetchDatabaseQuery ($statement): array
  {
    return $statement->fetch(PDO::FETCH_ASSOC);
  }

  /**
   * @deprecated
   *
   *
   * @param              $statement
   * @return int
   */
  public function rowCount ($statement): int
  {
    return $statement->rowCount();
  }

  /**
   * @throws null
   */
  public function prepare (string $query, array $params = []): PDOStatement
  {
    try {
      $statement = $this->getDatabase()->prepare($query);
      $statement->execute($params);
    } catch (\PDOException $e){
      throw new DatabaseException($e->getMessage(), 0, $e);
    }
    return $statement;
  }

  /**
   * @return PDO
   * @throws DatabaseException
   */
  private function getDatabase (): PDO
  {
    if (time() + 3600 < self::$serviceStarted) {
      $this->initial();
    }

    return self::$database;
  }

  public function lastInsertId(string $idField = "id"): string
  {
    return $this->getDatabase()->lastInsertId($idField);
  }

  public function getAttribute($attribute){
    return $this->getDatabase()->getAttribute($attribute);
  }

  public function query(string $string): bool|PDOStatement
  {
    return $this->getDatabase()->query($string);
  }
}