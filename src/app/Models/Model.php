<?php

declare(strict_types=1);

namespace GreenNet\Models;

use GreenNet\Core\Database;
use PDO;

abstract class Model
{
    protected static function db(): PDO
    {
        return Database::connection();
    }
}