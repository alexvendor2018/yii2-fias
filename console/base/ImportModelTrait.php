<?php
namespace alexvendor2018\fias\console\base;

use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\helpers\Console;

/**
 * @mixin ActiveRecord
 */
trait ImportModelTrait
{
    /**
     * @param XmlReader $reader
     * @param array|null $attributes
     * @throws \yii\db\Exception
     */
    public static function import(XmlReader $reader, $attributes = null)
    {
        if (is_null($attributes)) {
            $attributes = static::getXmlAttributes();
        }
        static::processRows($reader, $attributes);
        static::importCallback();
    }

    /**
     * @param XmlReader $reader
     * @param array|null $attributes
     */
    public static function updateRecords(XmlReader $reader, $attributes = null)
    {
        if (is_null($attributes)) {
            $attributes = static::getXmlAttributes();
        }
        static::processRows($reader, $attributes, true);
        static::updateCallback();
    }

    /**
     * @param XmlReader $reader
     */
    public static function remove(XmlReader $reader)
    {
        while ($rows = $reader->getRows()) {
            static::removeRows($rows);
        }
    }

    /**
     * @param array $rows
     * @throws InvalidConfigException
     */
    protected static function removeRows(array $rows)
    {
        $ids = [];
        $rowKey = array_search('id', static::getXmlAttributes());
        if ($rowKey === false) {
            throw new InvalidConfigException;
        }
        foreach ($rows as $row) {
            if (!empty($row[$rowKey])) {
                $ids[] = $row[$rowKey];
            }
        }

        static::deleteAll(['id' => $ids]);
    }

    /**
     * @param XmlReader $reader
     * @param array $attributes
     * @param bool $temporaryTable
     * @throws \yii\db\Exception
     */
    protected static function processRows(XmlReader $reader, $attributes, $temporaryTable = false)
    {
        if ($temporaryTable) {
            $tableName = static::temporaryTableName();
            static::getDb()->createCommand("DROP TABLE IF EXISTS {$tableName};")->execute();
            $primaryTable = static::tableName();
            static::getDb()->createCommand("CREATE TABLE {$tableName} SELECT * FROM {$primaryTable} LIMIT 0;")->execute();
            static::getDb()->createCommand()->addColumn($tableName, 'previous_id', 'char(36)')->execute();
        } else {
            $tableName = static::tableName();
            static::getDb()->createCommand()->truncateTable($tableName)->execute();
        }
        $count = 0;
        while ($data = $reader->getRows()) {
            $rows = [];
            foreach ($data as $row) {
                $rows[] = array_values($row);
            }

            if ($rows) {
                $count += static::getDb()->createCommand()->batchInsert($tableName, array_values($attributes), $rows)->execute();
                Console::output("Inserted {$count} rows");
            }
        }
    }

    /**
     * After import callback
     */
    public static function importCallback()
    {
    }

    /**
     * After update callback
     */
    public static function updateCallback()
    {
    }

    /**
     * @return array
     */
    public static function getXmlAttributes()
    {
        return [];
    }

    /**
     * @return array
     */
    public static function getXmlFilters()
    {
        return [];
    }

    /**
     * @return string
     */
    protected static function temporaryTableName()
    {
        return 'tmp_' . static::tableName();
    }
}