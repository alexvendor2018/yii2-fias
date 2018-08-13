<?php

namespace alexvendor2018\fias\models;

use alexvendor2018\fias\console\base\ImportModelTrait;
use alexvendor2018\fias\console\base\XmlReader;
use Yii;
use yii\db\Query;
use yii\helpers\Console;

/**
 * This is the model class for table "{{%fias_address_object}}".
 *
 * @property string $id
 * @property string $address_id
 * @property string $parent_id
 * @property integer $level
 * @property integer $address_level
 * @property integer $house_count
 * @property integer $next_address_level
 * @property string $title
 * @property string $full_title
 * @property integer $postal_code
 * @property string $region
 * @property string $prefix
 *
 * @property FiasAddressObjectLevel $addressLevel
 * @property FiasAddressObject $parent
 * @property FiasAddressObject[] $fiasAddressObjects
 * @property FiasHouse[] $fiasHouses
 */
class FiasAddressObject extends \yii\db\ActiveRecord
{
    CONST XML_OBJECT_KEY = 'Object';

    use ImportModelTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%fias_address_object}}';
    }

    /**
     * @return array
     */
    public static function getXmlAttributes()
    {
        return [
            'AOID' => 'id',
            'AOGUID' => 'address_id',
            'AOLEVEL' => 'address_level',
            'PARENTGUID' => 'parent_id',
            'FORMALNAME' => 'title',
            'POSTALCODE' => 'postal_code',
            'SHORTNAME' => 'prefix',
            'REGIONCODE' => 'region',
        ];
    }

    /**
     * @return array
     */
    public static function getXmlFilters()
    {
        return [['field' => 'ACTSTATUS', 'type' => 'eq', 'value' => 1]];
    }

    /**
     * @param $parent
     */
    protected static function recursiveTitle($parent)
    {
        foreach (static::find()->where(['parent_id' => $parent->address_id])->each() as $value) {
            $value->full_title = $parent->full_title . ', ' . static::replaceTitle($value);
            $value->level = $parent->level + 1;
            $value->save(false);

            static::recursiveTitle($value);
        }
    }

    /**
     * @param $model
     * @return string
     */
    protected static function replaceTitle($model)
    {
        switch ($model->prefix) {
            case 'обл':
                return $model->title . ' область';
            case 'р-н':
                return $model->title . ' район';
            case 'проезд':
                return $model->title . ' проезд';
            case 'б-р':
                return $model->title . ' бульвар';
            case 'пер':
                return $model->title . ' переулок'; 
            case 'ал':
                return $model->title . ' аллея';
            case 'ш':
                return $model->title . ' шоссе';
            case 'г':
                return 'г. ' . $model->title;
            case 'линия':
                return 'линия ' . $model->title;
            case 'ул':
                return 'ул. ' . $model->title;
            case 'пр-кт':
                return $model->title . ' проспект';
            default:
                return trim($model->prefix . '. ' . $model->title);
        }
    }

    /**
     * @inheritdoc
     */
    public static function importCallback()
    {
        foreach (static::find()->where('parent_id =""')->each() as $value) {
            /** @var static $value */
            $value->full_title = static::replaceTitle($value);
            $value->level = 0;
            $value->save(false);
            static::recursiveTitle($value);
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
            "UPDATE {$tableName} ao_old
                SET title = ao_new.title,
                 postal_code = ao_new.postal_code,
                 prefix = ao_new.prefix,
                 parent_id = ao_new.parent_id
            FROM {$tTableName} ao_new
            WHERE (ao_old.address_id = ao_new.address_id OR ao_old.id = ao_new.previous_id)
            AND (
                COALESCE(ao_old.title, '') != COALESCE(ao_new.title, '')
                OR COALESCE(ao_old.postal_code, 0) != COALESCE(ao_new.postal_code, 0)
                OR COALESCE(ao_old.prefix, '') != COALESCE(ao_new.prefix, '')
                OR COALESCE(ao_old.parent_id, '') != COALESCE(ao_new.parent_id, '')
            )")->execute();

        static::getDb()->createCommand(
            "INSERT INTO {$tableName}(id, address_id, parent_id, title, postal_code, prefix)
            SELECT ao_new.id, ao_new.address_id, ao_new.parent_id, ao_new.title, ao_new.postal_code, ao_new.prefix
            FROM {$tTableName} ao_new
            LEFT JOIN address_objects ao_old
                ON (ao_old.address_id = ao_new.address_id OR ao_old.id = ao_new.previous_id)
            WHERE ao_old.id IS NULL
            ")->execute();

        static::getDb()->createCommand()->dropTable($tTableName);
        static::importCallback();
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
                if (in_array($row['REGIONCODE'], [50, 77])) {
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
    public function rules()
    {
        return [
            [['id', 'parent_id'], 'required'],
            [['level', 'address_level', 'house_count', 'next_address_level', 'postal_code'], 'integer'],
            [['id', 'address_id', 'parent_id'], 'string', 'max' => 36],
            [['title', 'full_title', 'region', 'prefix'], 'string', 'max' => 255],
            [['address_id'], 'unique'],
            [['address_level'], 'exist', 'skipOnError' => true, 'targetClass' => FiasAddressObjectLevel::className(), 'targetAttribute' => ['address_level' => 'id']],
            [['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => FiasAddressObject::className(), 'targetAttribute' => ['parent_id' => 'address_id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'address_id' => 'Address ID',
            'parent_id' => 'Parent ID',
            'level' => 'Level',
            'address_level' => 'Address Level',
            'house_count' => 'House Count',
            'next_address_level' => 'Next Address Level',
            'title' => 'Title',
            'full_title' => 'Full Title',
            'postal_code' => 'Postal Code',
            'region' => 'Region',
            'prefix' => 'Prefix',
        ];
    }

    /**
     * @param $address
     * @return array|null|\yii\db\ActiveRecord
     */
    public static function findByAddress($address)
    {
        $level = count(explode(',', $address)) - 1;
        return static::find()->where(['level' => $level, 'full_title' => $address])->asArray()->limit(1)->one();
    }

    /**
     * @param $address
     * @param null $parentId
     * @param int $limit
     * @return array|\yii\db\ActiveRecord[]
     */
    public static function findAddresses($address, $parentId = null, $limit = 10)
    {
        if ($parentId) {
            $query = static::find()->select('id, full_title title')
                ->where(['parent_id' => $parentId])
                ->andWhere(['LIKE', 'full_title', $address])

                ->asArray()->limit($limit);
        } else {
            $query2 = static::find()->select('ao.id, ao.full_title title')->alias('ao')
                ->andWhere(['LIKE', 'ao.full_title', $address])
                ->limit($limit);

            $query1 = static::find()->select('id, full_title title')->alias('ao')
                ->where('parent_id = ""')
                ->andWhere(['LIKE', 'ao.full_title', $address])
                ->asArray()->limit($limit);

            $query = (new Query())->select('*')->from(['tmp' => $query1->union($query2)]);
        }
        
        return $query->orderBy('title')->limit($limit)->all();
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAddressLevel()
    {
        return $this->hasOne(FiasAddressObjectLevel::className(), ['id' => 'address_level']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(FiasAddressObject::className(), ['address_id' => 'parent_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFiasAddressObjects()
    {
        return $this->hasMany(FiasAddressObject::className(), ['parent_id' => 'address_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFiasHouses()
    {
        return $this->hasMany(FiasHouse::className(), ['address_id' => 'address_id']);
    }
}
