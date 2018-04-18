<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace edgardmessias\db\ibm\db2;

use yii\base\InvalidParamException;
use yii\db\Expression;

/**
 * QueryBuilder is the query builder for DB2 databases.
 *
 * @author Edgard Lorraine Messias <edgardmessias@gmail.com>
 * @author Nikita Verkhovin <vernik91@gmail.com>
 */
class QueryBuilder extends \yii\db\QueryBuilder
{
    public $typeMap = [
        Schema::TYPE_PK => 'integer NOT NULL GENERATED BY DEFAULT AS IDENTITY (START WITH 1, INCREMENT BY 1)',
        Schema::TYPE_BIGPK => 'bigint NOT NULL GENERATED BY DEFAULT AS IDENTITY (START WITH 1, INCREMENT BY 1)',
        Schema::TYPE_STRING => 'varchar(255)',
        Schema::TYPE_TEXT => 'clob',
        Schema::TYPE_SMALLINT => 'smallint',
        Schema::TYPE_INTEGER => 'integer',
        Schema::TYPE_BIGINT => 'bigint',
        Schema::TYPE_FLOAT => 'float',
        Schema::TYPE_DOUBLE => 'double',
        Schema::TYPE_DECIMAL => 'decimal(10,0)',
        Schema::TYPE_DATETIME => 'timestamp',
        Schema::TYPE_TIMESTAMP => 'timestamp',
        Schema::TYPE_TIME => 'time',
        Schema::TYPE_DATE => 'date',
        Schema::TYPE_BINARY => 'blob',
        Schema::TYPE_BOOLEAN => 'smallint',
        Schema::TYPE_MONEY => 'decimal(19,4)',
    ];

    /**
     * Builds a SQL statement for truncating a DB table.
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     * @return string the SQL statement for truncating a DB table.
     */
    public function truncateTable($table)
    {
        return 'TRUNCATE TABLE ' . $this->db->quoteTableName($table) . ' IMMEDIATE';
    }

    /**
     * @inheritdoc
     */
    public function resetSequence($tableName, $value = null)
    {
        $table = $this->db->getTableSchema($tableName);

        if ($table !== null && isset($table->columns[$table->sequenceName])) {
            if ($value === null) {
                $sql = 'SELECT MAX("'. $table->sequenceName .'") FROM "'. $tableName . '"';
                $value = $this->db->createCommand($sql)->queryScalar() + 1;
            } else {
                $value = (int) $value;
            }
            return 'ALTER TABLE "' . $tableName . '" ALTER COLUMN "'.$table->sequenceName.'" RESTART WITH ' . $value;
        } elseif ($table === null) {
            throw new InvalidParamException("Table not found: $tableName");
        } else {
            throw new InvalidParamException("There is no sequence associated with table '$tableName'.");
        }
    }

    /**
     * Builds a SQL statement for enabling or disabling integrity check.
     * @param boolean $check whether to turn on or off the integrity check.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @param string $table the table name. Defaults to empty string, meaning that no table will be changed.
     * @return string the SQL statement for checking integrity
     * @throws \yii\base\NotSupportedException if this is not supported by the underlying DBMS
     * @see http://www-01.ibm.com/support/knowledgecenter/SSEPGG_10.5.0/com.ibm.db2.luw.sql.ref.doc/doc/r0000998.html?cp=SSEPGG_10.5.0%2F2-12-7-227
     */
    public function checkIntegrity($check = true, $schema = '', $table = '')
    {
        if ($table) {
            $tableNames = [$table];
        } else {
            //Return only tables
            $sql = "SELECT t.tabname FROM syscat.tables AS t"
                    . " WHERE t.type in ('T') AND t.ownertype != 'S'";

            /**
             * Filter by integrity pending
             * @see http://www-01.ibm.com/support/knowledgecenter/SSEPGG_9.7.0/com.ibm.db2.luw.sql.ref.doc/doc/r0001063.html
             */
            if ($check) {
                $sql .= " AND t.status = 'C'";
            }
            if ($schema) {
                $sql .= ' AND t.tabschema = :schema';
            }
            
            $command = $this->db->createCommand($sql);
            if ($schema) {
                $command->bindValue(':schema', $schema);
            }

            $tableNames = $command->queryColumn();
        }

        if (empty($tableNames)) {
            return '';
        }

        $quotedTableNames = [];
        foreach ($tableNames as $tableName) {
            $quotedTableNames[] = $this->db->quoteTableName($tableName) . ($check? '' : ' ALL');
        }

        $enable = $check ? 'CHECKED' : 'UNCHECKED';
        return 'SET INTEGRITY FOR ' . implode(', ', $quotedTableNames) . ' IMMEDIATE ' . $enable. ';';
    }

    /**
     * @inheritdoc
     */
/*    public function buildOrderByAndLimit($sql, $orderBy, $limit, $offset)
    {
        $orderByStatment = $this->buildOrderBy($orderBy);

        $limitOffsetStatment = $this->buildLimit($limit, $offset);
        if ($limitOffsetStatment != '') {
            $sql = str_replace(':query', $sql, $limitOffsetStatment);
            $sql = str_replace(':order', $orderByStatment,$sql);
        }else{
            if ($orderByStatment !== '') {
                $sql .= $this->separator . $orderByStatment;
            }
        }
        return $sql;
    }*/

    /**
     * @inheritdoc
     */
/*    public function buildLimit($limit, $offset)
    {
        if (!$this->hasLimit($limit) && !$this->hasOffset($offset)) {
            return '';
        }

        $limitOffsetStatment = 'SELECT * FROM (SELECT SUBQUERY_.*, ROW_NUMBER() OVER(:order) AS RN_ FROM ( :query ) AS SUBQUERY_) as t WHERE :offset :limit';

        $replacement = $this->hasOffset($offset) ? 't.RN_ > ' . $offset : 't.RN_ > 0';
        $limitOffsetStatment = str_replace(':offset', $replacement, $limitOffsetStatment);

        $replacement = $this->hasLimit($limit) ? 'AND t.RN_ <= ' . ($limit + $offset) : '';
        $limitOffsetStatment = str_replace(':limit', $replacement, $limitOffsetStatment);

        return $limitOffsetStatment;
    }*/

    /**
     * @inheritdoc
     */
    public function alterColumn($table, $column, $type)
    {
        return 'ALTER TABLE ' . $this->db->quoteTableName($table) . ' ALTER COLUMN '
        . $this->db->quoteColumnName($column) . ' SET DATA TYPE '
        . $this->getColumnType($type);
    }

    /**
     * @inheritdoc
     */
    protected function buildCompositeInCondition($operator, $columns, $values, &$params)
    {
        $vss = [];
        foreach ($values as $value) {
            $vs = [];
            foreach ($columns as $column) {
                if (isset($value[$column])) {
                    $phName = self::PARAM_PREFIX . count($params);
                    $params[$phName] = $value[$column];
                    $vs[] = $phName;
                } else {
                    $vs[] = 'NULL';
                }
            }
            $vss[] = 'select ' . implode(', ', $vs) . ' from SYSIBM.SYSDUMMY1';
        }
        foreach ($columns as $i => $column) {
            if (strpos($column, '(') === false) {
                $columns[$i] = $this->db->quoteColumnName($column);
            }
        }

        return '(' . implode(', ', $columns) . ") $operator (" . implode(' UNION ', $vss) . ')';
    }

    /**
     * @inheritdoc
     */
    public function insert($table, $columns, &$params)
    {
        $schema = $this->db->getSchema();
        if (($tableSchema = $schema->getTableSchema($table)) !== null) {
            $columnSchemas = $tableSchema->columns;
        } else {
            $columnSchemas = [];
        }
        $names = [];
        $placeholders = [];
        foreach ($columns as $name => $value) {
            $names[] = $schema->quoteColumnName($name);
            if ($value instanceof Expression) {
                $placeholders[] = $value->expression;
                foreach ($value->params as $n => $v) {
                    $params[$n] = $v;
                }
            } else {
                $phName = self::PARAM_PREFIX . count($params);
                $placeholders[] = $phName;
                $params[$phName] = !is_array($value) && isset($columnSchemas[$name]) ? $columnSchemas[$name]->dbTypecast($value) : $value;
            }
        }

        if (empty($placeholders)) {
            $placeholders = array_fill(0, count($columnSchemas), 'DEFAULT');
        }

        return 'INSERT INTO ' . $schema->quoteTableName($table)
        . (!empty($names) ? ' (' . implode(', ', $names) . ')' : '')
        . ' VALUES (' . implode(', ', $placeholders) . ')';
    }
}
