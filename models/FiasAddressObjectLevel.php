<?php

namespace alexvendor2018\fias\models;

use Yii;

/**
 * This is the model class for table "{{%fias_address_object_level}}".
 *
 * @property integer $id
 * @property string $title
 * @property string $code
 *
 * @property FiasAddressObject[] $fiasAddressObjects
 */
class FiasAddressObjectLevel extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%fias_address_object_level}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id'], 'required'],
            [['id'], 'integer'],
            [['title', 'code'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'code' => 'Code',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFiasAddressObjects()
    {
        return $this->hasMany(FiasAddressObject::className(), ['address_level' => 'id']);
    }
}
