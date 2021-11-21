<?php declare(strict_types=1);

namespace spawn\system\Core\Base\Database;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\DriverManager;
use spawn\system\Throwables\DatabaseConnectionException;

class DatabaseConnection
{

    protected static ?DBALConnection $connection = null;

    /**
     * @param string $host
     * @param string $database
     * @param string $port
     * @param string $username
     * @param string $password
     * @param string $driver
     * @param bool $persistConnection
     * @return DBALConnection
     * @throws DatabaseConnectionException
     */
    public static function createNewConnection(
        string $host = DB_HOST,
        string $database = DB_DATABASE,
        string $port = DB_PORT,
        string $username = DB_USERNAME,
        string $password = DB_PASSWORD,
        string $driver = DB_DRIVER,
        bool $persistConnection = true
   ): DBALConnection
    {
        $connection = null;
        try {
            $connectionParams = array(
                'dbname' => $database,
                'user' => $username,
                'password' => $password,
                'host' => $host,
                'driver' => $driver,
                'port' => (int)$port,
            );

            //https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#driver
            $connection = DriverManager::getConnection($connectionParams);
        }
        catch(\Exception $exception) {
            throw new DatabaseConnectionException($host, $database, $port, $driver, $username, $password);
        }

        if($persistConnection) {
            self::$connection = $connection;
        }

        return $connection;
    }

    /**
     * @return DBALConnection
     * @throws DatabaseConnectionException
     */
    public static function getConnection(): DBALConnection
    {
        if(self::$connection == null) {
            return self::createNewConnection();
        }

        return self::$connection;
    }


}