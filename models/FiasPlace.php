<?php

namespace alexvendor2018\fias\models;

use Yii;

/**
 * This is the model class for table "{{%fias_place}}".
 *
 * @property integer $id
 * @property integer $parent_id
 * @property integer $type_id
 * @property string $title
 * @property string $full_title
 * @property integer $have_children
 *
 * @property FiasPlaceType $type
 */
class FiasPlace extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%fias_place}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['parent_id', 'type_id', 'have_children'], 'integer'],
            [['type_id'], 'required'],
            [['title', 'full_title'], 'string', 'max' => 255],
            [['title', 'type_id', 'parent_id'], 'unique', 'targetAttribute' => ['title', 'type_id', 'parent_id'], 'message' => 'The combination of Parent ID, Type ID and Title has already been taken.'],
            [['type_id'], 'exist', 'skipOnError' => true, 'targetClass' => FiasPlaceType::className(), 'targetAttribute' => ['type_id' => 'id']],
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
            'type_id' => 'Type ID',
            'title' => 'Title',
            'full_title' => 'Full Title',
            'have_children' => 'Have Children',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getType()
    {
        return $this->hasOne(FiasPlaceType::className(), ['id' => 'type_id']);
    }
}
