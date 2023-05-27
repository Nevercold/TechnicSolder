<?php

namespace Solder\service\dashboard;

use Solder\exception\NotFoundException;
use Solder\service\config\ConfigService;
use Solder\service\Service;

class AuthService
{
  public static function login(string $email, string $password): void
  {
    $statement = Service::getDatabaseService()->prepare("SELECT * FROM `users` WHERE `name` = ?", [$email]);
    if ($statement->rowCount() == 0) {
      throw new NotFoundException("User not found", 404);
    }
    $user = $statement->fetch();

    if (password_verify($password, $user['pass'])) {
      $_SESSION['user'] = $_POST['email'];
      $_SESSION['name'] = $user['display_name'];
      $_SESSION['perms'] = $user['perms'];
    } else {
      throw new NotFoundException("User not found", 404);
    }
  }

  public static function logout(): void
  {
    session_destroy();
    header("Location: ".ConfigService::getWebUrl()."/login");
    exit();
  }

  public static function isLoggedIn(): bool
  {
    if(!isset($_SESSION['user'])){
      return false;
    }

    $statement = Service::getDatabaseService()->prepare("SELECT * FROM `users` WHERE `name` = ?", [$_SESSION['user']]);
    if ($statement->rowCount() == 0) {
      return false;
    }
    return true;
  }
}