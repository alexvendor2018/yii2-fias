<?php

namespace alexvendor2018\fias\models;

use Yii;

/**
 * This is the model class for table "{{%fias_place_type}}".
 *
 * @property integer $id
 * @property integer $parent_id
 * @property string $title
 * @property string $system_name
 *
 * @property FiasPlace[] $fiasPlaces
 * @property FiasPlaceType $parent
 * @property FiasPlaceType[] $fiasPlaceTypes
 */
class FiasPlaceType extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%fias_place_type}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['parent_id'], 'integer'],
            [['title'], 'required'],
            [['title', 'system_name'], 'string', 'max' => 255],
            [['title'], 'unique'],
            [['system_name'], 'unique'],
            [['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => FiasPlaceType::className(), 'targetAttribute' => ['parent_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'parent_id' => 'Parent ID',
            'title' => 'Title',
            'system_name' => 'System Name',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFiasPlaces()
    {
        return $this->hasMany(FiasPlace::className(), ['type_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(FiasPlaceType::className(), ['id' => 'parent_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFiasPlaceTypes()
    {
        return $this->hasMany(FiasPlaceType::className(), ['parent_id' => 'id']);
    }
}
