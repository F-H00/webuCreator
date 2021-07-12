<?php


namespace spawn\system\Core\Base\Database\Query;


use spawn\system\Core\Base\Database\DatabaseConnection;
use spawn\system\Core\Base\Database\Query\Types\QueryDelete;
use spawn\system\Core\Base\Database\Query\Types\QueryInsert;
use spawn\system\Core\Base\Database\Query\Types\QuerySelect;
use spawn\system\Core\Base\Database\Query\Types\QueryUpdate;

class QueryBuilder
{

    /** @var DatabaseConnection $connection */
    public $connection;

    /**
     * QueryBuilder constructor.
     * @param DatabaseConnection $connection
     */
    public function __construct(DatabaseConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string|array
     * @return QuerySelect
     */
    public function select($columns): QuerySelect
    {
        return new QuerySelect($this->connection, $columns);
    }

    /**
     * @return QueryInsert
     */
    public function insert() {
        return new QueryInsert($this->connection);
    }


    /**
     * @return QueryUpdate
     */
    public function update($table) {
        return new QueryUpdate($this->connection, $table);
    }


    /**
     * @return QueryDelete
     */
    public function delete() {
        return new QueryDelete($this->connection);
    }


}