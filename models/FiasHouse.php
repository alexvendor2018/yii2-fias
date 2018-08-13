<?php

namespace alexvendor2018\fias\models;

use alexvendor2018\fias\console\base\ImportModelTrait;
use alexvendor2018\fias\console\base\XmlReader;
use alexvendor2018\fias\console\helpers\RawDataHelper;
use Yii;
use yii\helpers\Console;

/**
 * This is the model class for table "{{%fias_house}}".
 *
 * @property string $id
 * @property string $house_id
 * @property string $address_id
 * @property string $number
 * @property string $full_number
 * @property string $building
 * @property string $structure
 * @property string $postal_code
 *
 * @property FiasAddressObject $address
 */
class FiasHouse extends \yii\db\ActiveRecord
{
    CONST XML_OBJECT_KEY = 'House';

    use ImportModelTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%fias_house}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'house_id'], 'required'],
            [['id', 'house_id', 'address_id'], 'string', 'max' => 36],
            [['number', 'full_number', 'building', 'structure', 'postal_code'], 'string', 'max' => 255],
            [['address_id'], 'exist', 'skipOnError' => true, 'targetClass' => FiasAddressObject::className(), 'targetAttribute' => ['address_id' => 'address_id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'house_id' => 'House ID',
            'address_id' => 'Address ID',
            'number' => 'Number',
            'full_number' => 'Full Number',
            'building' => 'Building',
            'structure' => 'Structure',
            'postal_code' => 'Postal Code',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAddress()
    {
        return $this->hasOne(FiasAddressObject::className(), ['address_id' => 'address_id']);
    }

    /**
     * @return array
     */
    public static function getXmlAttributes()
    {
        return [
            'HOUSEID' => 'id',
            'HOUSEGUID' => 'house_id',
            'AOGUID' => 'address_id',
            'HOUSENUM' => 'number',
            'BUILDNUM' => 'building',
            'STRUCNUM' => 'structure',
        ];
    }

    /**
     * @inheritdoc
     */
    public static function importCallback()
    {
        RawDataHelper::cleanHouses();
        RawDataHelper::updateHousesCount();
        RawDataHelper::updateNextAddressLevelFlag();
    }

    /**
     * @inheritdoc
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
        $addresses = FiasAddressObject::find()->indexBy('address_id')->select('address_id')
            ->where(['region' => [50, 77]])->asArray()->all();
        $count = 0;
        while ($data = $reader->getRows()) {
            $rows = [];
            foreach ($data as $row) {
                if (isset($addresses[$row['AOGUID']])) {
                    $rows[] = array_values($row);
                }
            }
            if ($rows) {
                $count += static::getDb()->createCommand()->batchInsert($tableName, array_values($attributes), $rows)->execute();
                Console::output("Inserted {$count} rows");
            }
        }
    }
    
    /**
     * @inheritdoc
     */
    public static function updateCallback()
    {
        $tTableName = static::temporaryTableName();
        $tableName = static::tableName();

        static::getDb()->createCommand(
            "DELETE FROM {$tTableName} h
            USING (
                SELECT DISTINCT h.address_id
                FROM {$tTableName} h
                LEFT JOIN address_objects ao
                    ON ao.address_id = h.address_id
                WHERE ao.id IS NULL
            ) a
            WHERE a.address_id = h.address_id")->execute();

        static::getDb()->createCommand(
            "DELETE FROM {$tableName} h_old
            USING {$tTableName} h_new
            WHERE (h_old.house_id = h_new.house_id OR h_old.id = h_new.previous_id)")->execute();

        static::getDb()->createCommand(
            "INSERT INTO {$tableName}(id, house_id, address_id, number, building, structure, full_number)
            SELECT h_new.id, h_new.house_id, h_new.address_id, h_new.number, h_new.building, h_new.structure, h_new.full_number
            FROM {$tTableName} h_new")->execute();

        static::getDb()->createCommand()->dropTable($tTableName);
        static::importCallback();
    }
}
