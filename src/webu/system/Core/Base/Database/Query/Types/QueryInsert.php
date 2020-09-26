<?php

namespace webu\system\Core\Base\Database\Query\Types;


use MongoDB\Driver\Query;

class QueryInsert extends QueryBase
{

    /** @var string */
    const COMMAND = 'INSERT ';

    //INSERT INTO table (col1,col2,col3) VALUES (val1,val2,val3)
    public $table = '';
    public $values = array();



    public function __construct()
    {
        return $this;
    }

    /**
     * Builds an returns the sql
     *
     * @return string
     */
    public function getSql(): string
    {
        $sql = self::COMMAND . ' ';

        $sql .= 'INTO ' . $this->table . ' ';

        $sql .= '(';
            $keys = array_keys($this->values);
            $sql .= implode(',', $keys);
        $sql .= ') ';


        $sql .= 'VALUES (';
            $sql .= implode(',', $this->values);
        $sql .= ')';


        return $sql;
    }


    /**
     * @param string $tableName
     * @return QueryInsert
     */
    public function into(string $tableName) : QueryInsert
    {
        $this->table = $tableName;
        return $this;
    }

    /**
     * @param string $column
     * @param $value
     * @return QueryInsert
     */
    public function setValue(string $column, $value) : QueryInsert {

        $this->formatParam($value);
        $this->values[$column] = $value;

        return $this;
    }

}