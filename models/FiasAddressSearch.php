<?php
namespace alexvendor2018\fias\models;


use yii\base\Model;
use yii\db\Expression;

class FiasAddressSearch extends FiasAddressObject
{
    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        // bypass behaviors() implementation in the parent class
        return Model::behaviors();
    }

    /**
     * @param $query
     * @return array|\yii\db\ActiveRecord[]
     */
    public function search($query)
    {
        $addressParts = static::splitAddress($query);

        $address = static::findByAddress($addressParts['address']);

        if ($address && $address['house_count']) {
            $rows = $this->findHouses($addressParts['pattern'], $address['address_id']);
            $rows = $this->setIsCompleteFlag($rows, true);

            if (static::find()->where(['parent_id' => $address['address_id']])->exists()) {
                $addressRows = static::findAddresses($addressParts['pattern'], $address['address_id']);
                $addressRows = $this->setIsCompleteFlag($addressRows, false);
                $rows = array_merge($rows, $addressRows);
            }
        } else {
            $rows = static::findAddresses($addressParts['pattern'], isset($address['address_id']) ? $address['address_id'] : null);
            $rows = $this->setIsCompleteFlag($rows, false);
        }

        return $rows;
    }

    /**
     * @param $pattern
     * @param $parentId
     * @param int $limit
     * @return array|\yii\db\ActiveRecord[]
     */
    protected function findHouses($pattern, $parentId, $limit = 10)
    {
        return FiasHouse::find()->select(["CONCAT_WS(\", \",full_title, full_number) title, h.id"])->alias('h')
            ->where(['h.address_id' => $parentId])
            ->andWhere('full_number LIKE :pattern', [':pattern' => $pattern . '%'])
            ->innerJoin(static::tableName() . ' ao', 'ao.address_id = h.address_id')
            ->orderBy('full_number')->limit($limit)->asArray()->all();
    }

    /**
     * @param array $rows
     * @param $value
     * @return array
     */
    protected function setIsCompleteFlag($rows, $value)
    {
        foreach ($rows as $key => &$row) {
            $row['is_complete'] = $value;
        }

        return $rows;
    }

    /**
     * @param $address
     * @return array
     */
    protected static function splitAddress($address)
    {
        $tmp = explode(',', $address);

        return [
            'pattern' => static::cleanAddressPart(array_pop($tmp)),
            'address' => implode(',', $tmp),
        ];
    }

    /**
     * @param $rawAddress
     * @return string
     */
    protected static function cleanAddressPart($rawAddress)
    {
        $cleanAddress = preg_replace('
            {
                (?<= ^ | [^а-яА-ЯЁё] )

                (?:ул|улица|снт|деревня|тер|линия|проезд|гск|город|дом|д)

                (?= [^а-яА-ЯЁё] | $ )

                [.,-]*
            }x',
            '',
            $rawAddress
        );

        return trim($cleanAddress);
    }
}