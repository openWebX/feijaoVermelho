<?php

namespace openWebX\feijaoVermelho\Database;

use Dotenv\Dotenv;
use openWebX\openCache\Cache;
use Psr\Cache\InvalidArgumentException;
use RedBeanPHP\OODBBean;
use RedBeanPHP\R;
use RedBeanPHP\RedException\SQL;
use RedBeanPHP\ToolBox;
use Webmozart\Assert\Assert;

/**
 * Class Database
 *
 * Provides static methods for database operations using RedBeanPHP.
 */
class Database {

    /**
     * @var ToolBox|null RedBeanPHP Toolbox instance.
     */
    private static ?ToolBox $toolbox = null;

    /**
     * Initializes the database connection and returns the Toolbox.
     *
     * @return ToolBox
     */
    public static function init(): ToolBox {
        if (self::$toolbox === null) {
            self::loadEnvVariables();

            $dsn = sprintf('mysql:host=%s;dbname=%s', $_ENV['FEIJAO_DB_HOST'], $_ENV['FEIJAO_DB_NAME']);
            $user = $_ENV['FEIJAO_DB_USER'];
            $pass = $_ENV['FEIJAO_DB_PASS'];

            Assert::string($dsn, 'DSN must be a string.');
            self::$toolbox = R::setup($dsn, $user, $pass);

            R::useJSONFeatures(true);
            R::freeze(false);
        }
        return self::$toolbox;
    }

    /**
     * Closes the database connection.
     *
     * @return bool
     */
    public static function exit(): bool {
        R::close();
        return true;
    }

    /**
     * Stores a RedBeanPHP bean in the database.
     *
     * @param OODBBean $bean
     */
    public static function store(OODBBean $bean): void {
        self::init();
        try {
            R::store($bean);
        } catch (SQL $exception) {
            error_log('Database error: ' . $exception->getMessage());
        }
    }

    /**
     * Retrieves all database tables.
     *
     * @return array
     */
    public static function getAllTables(): array {
        self::init();
        return R::inspect();
    }

    /**
     * Retrieves fields of a specific table.
     *
     * @param string $table
     * @return array
     */
    public static function getTableFields(string $table): array {
        self::init();

        if (!in_array($table, self::getAllTables())) {
            return [];
        }
        return R::inspect($table);
    }

    /**
     * Finds a bean in the database based on the given selector and values.
     *
     * @param string $tableName
     * @param string $fieldSelector
     * @param array|null $fieldValues
     * @return OODBBean|null
     * @throws InvalidArgumentException
     */
    public static function findBean(string $tableName, string $fieldSelector, ?array $fieldValues): ?OODBBean {
        self::init();
        $fieldValues ??= [];

        // Generate a unique hash for caching purposes
        $cacheKey = sha1($tableName . $fieldSelector . serialize($fieldValues));

        // Attempt to fetch from cache (if caching enabled)
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $result = R::findOne($tableName, $fieldSelector, $fieldValues);
            Cache::set($cacheKey, $result);
            return $result;
        } catch (SQL $exception) {
            error_log('Database query error: ' . $exception->getMessage());
            return null;
        }
    }

    /**
     * Loads environment variables from the .env file if not already loaded.
     */
    private static function loadEnvVariables(): void {
        if (!isset($_ENV['FEIJAO_CONFIGURED']) || $_ENV['FEIJAO_CONFIGURED'] !== true) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();
        }
    }
}
