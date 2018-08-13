<?php
namespace alexvendor2018\fias\console\helpers;

use alexvendor2018\fias\models\FiasAddressObject;
use alexvendor2018\fias\models\FiasHouse;
use Yii;

class RawDataHelper
{
    public static function cleanHouses()
    {
        $table = FiasHouse::tableName();
        $inCorrectValues = ['нет', '-', 'стр.', 'стр1'];

        $placeholders = str_repeat('?,', count($inCorrectValues) - 1) . '?';

        $command = Yii::$app->getDb()->createCommand("UPDATE {$table}
            SET number    = lower(number),
                building  = CASE WHEN building  IN ({$placeholders}) THEN NULL ELSE lower(building)  END,
                structure = CASE WHEN structure IN ({$placeholders}) THEN NULL ELSE lower(structure) END
            WHERE number REGEXP '[^0-9]+'
                OR building REGEXP '[^0-9]+'
                OR structure REGEXP '[^0-9]+'
            ");

        $counter = 0;
        foreach ($inCorrectValues as $parent) {
            $counter++;
            $command->bindValue($counter, $parent);
        }

        foreach ($inCorrectValues as $parent) {
            $counter++;
            $command->bindValue($counter, $parent);
        }
        $command->execute();
        // Убираем ложные данные по корпусам и строениям ("1а" и в корпусе и в номере, например)
        Yii::$app->getDb()->createCommand("UPDATE {$table}
            SET building = NULL,
                structure = NULL
            WHERE number REGEXP '[^0-9]+'
                AND (
                    (structure REGEXP '[^0-9]+' AND number = structure)
                    OR
                    (building REGEXP '[^0-9]+' AND number = building)
                )
            ")->execute();

    
        // нормализуем адрес по яндексу
        Yii::$app->getDb()->createCommand(
            "UPDATE {$table}
            SET full_number = CONCAT(number, IF(building IS NULL, '', CONCAT(' корпус ', building)),
                    IF(structure IS NULL, '', CONCAT(' строение ', structure)))
            ")->execute();
    }

    public static function updateHousesCount()
    {
        $table = FiasAddressObject::tableName();
        $houseTable = FiasHouse::tableName();

        Yii::$app->getDb()->createCommand(
            "UPDATE {$table} ao
            SET house_count = (SELECT count(*) FROM {$houseTable} tmp WHERE tmp.address_id = ao.address_id),
                next_address_level = 0
            "
        )->execute();
    }

    public static function updateNextAddressLevelFlag()
    {
        $table = FiasAddressObject::tableName();

        Yii::$app->getDb()->createCommand(
            "UPDATE {$table} ao 
                  JOIN {$table} as ao2 ON ao2.parent_id = ao.address_id
            SET ao.next_address_level = ao2.address_level"
        )->execute();
    }
}
