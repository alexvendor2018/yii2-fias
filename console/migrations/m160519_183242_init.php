<?php

use yii\db\Migration;

class m160519_183242_init extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%fias_house}}', [
            'id' => $this->char(36)->notNull()->comment('идентификационный код записи'),
            'house_id' => $this->char(36)->notNull()->comment('идентификационный код дома'),
            'address_id' => $this->char(36)->comment('идентификационный код адресного объекта'),
            'number' => $this->string()->comment('номер дома'),
            'full_number' => $this->string(),
            'building' => $this->string()->comment('корпус'),
            'structure' => $this->string()->comment('строение'),
            'postal_code' => $this->string()->comment('индекс'),
        ], $tableOptions);

        $this->addPrimaryKey('pk', '{{%fias_house}}', 'id');
        $this->createIndex('house_address_id_fkey_idx', '{{%fias_house}}', 'address_id');
        $this->createIndex('house_full_number_idx', '{{%fias_house}}', 'full_number');

        $this->createTable('{{%fias_address_object}}', [
            'id' => $this->char(36)->notNull()->comment('идентификационный код записи'),
            'address_id' => $this->char(36)->unique()->comment('идентификационный код адресного объекта'),
            'parent_id' => $this->char(36)->notNull()->comment('идентификационный код родительского адресного объекта'),
            'level' => $this->integer()->comment('уровень объекта по parent_id (0 для региона и далее по возрастающей)'),
            'address_level' => $this->integer()->comment('уровень объекта по ФИАС'),
            'house_count' => $this->integer()->comment('количество домов'),
            'next_address_level' => $this->integer()->comment('уровень следующего дочернего объекта по ФИАС'),
            'title' => $this->string()->comment('наименование объекта'),
            'full_title' => $this->string()->comment('полное наименование объекта'),
            'postal_code' => $this->integer()->comment('индекс'),
            'region' => $this->string()->comment('регион'),
            'prefix' => $this->string()->comment('ул., пр. и так далее'),
        ], $tableOptions);

        $this->addPrimaryKey('pk', '{{%fias_address_object}}', 'id');
        $this->createIndex('address_object_parent_id_fkey_idx', '{{%fias_address_object}}', 'parent_id');
        $this->createIndex('address_object_level_full_title_lower_idx', '{{%fias_address_object}}', 'level, full_title');
        $this->createIndex('address_object_title_lower_idx', '{{%fias_address_object}}', 'title');

        $this->createTable('{{%fias_address_object_level}}', [
            'id' => $this->integer()->comment('идентификационный код записи'),
            'title' => $this->string()->comment('описание уровня'),
            'code' => $this->string()->comment('код уровня'),
        ], $tableOptions);

        $this->addPrimaryKey('pk', '{{%fias_address_object_level}}', 'id');

        $this->createTable('{{%fias_update_log}}', [
            'id' => $this->primaryKey(),
            'version_id' => $this->integer()->unique()->notNull()->comment('id версии, полученной от базы ФИАС'),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createTable('{{%fias_place}}', [
            'id' => $this->primaryKey(),
            'parent_id' => $this->integer()->comment('идентификатор родительского места'),
            'type_id' => $this->integer()->notNull()->comment('идентификатор типа места'),
            'title' => $this->string()->comment('название места'),
            'full_title' => $this->string()->comment('название места с типом'),
            'have_children' => $this->boolean()->defaultValue(0)->comment('have_children'),
        ], $tableOptions);

        $this->createIndex('places_title_idx', '{{%fias_place}}', 'title');
        $this->createIndex('place_type_id_fkey_idx', '{{%fias_place}}', 'type_id');
        $this->createIndex('place_title_type_id_parent_id_uq_idx', '{{%fias_place}}', 'title, type_id, parent_id', true);

        $this->createTable('{{%fias_place_type}}', [
            'id' => $this->primaryKey(),
            'parent_id' => $this->integer()->comment('идентификатор типа родителя'),
            'title' => $this->string()->notNull()->unique()->comment('название типа для пользователя'),
            'system_name' => $this->string()->unique()->comment('системное имя типа, для использования в программном коде'),
        ], $tableOptions);

        $this->createIndex('place_type_id_fkey_idx', '{{%fias_place_type}}', 'parent_id');

        $this->createTable('{{%fias_region}}', [
            'id' => $this->string()->comment('номер региона'),
            'title' => $this->string()->comment('название региона'),
        ], $tableOptions);

        $this->addPrimaryKey('pk', '{{%fias_region}}', 'id');

        $this->addForeignKey('houses_parent_id_fkey', '{{%fias_house}}', 'address_id', '{{%fias_address_object}}', 'address_id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('place_types_parent_id_fkey', '{{%fias_place_type}}', 'parent_id', '{{%fias_place_type}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('address_object_parent_id_fkey', '{{%fias_address_object}}', 'parent_id', '{{%fias_address_object}}', 'address_id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('address_object_address_level_fkey', '{{%fias_address_object}}', 'address_level', '{{%fias_address_object_level}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('places_type_id_fkey', '{{%fias_place}}', 'type_id', '{{%fias_place_type}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function down()
    {
        $this->dropTable('{{%fias_place}}');
        $this->dropTable('{{%fias_place_type}}');
        $this->dropTable('{{%fias_house}}');
        $this->dropTable('{{%fias_address_object}}');
        $this->dropTable('{{%fias_address_object_level}}');
        $this->dropTable('{{%fias_update_log}}');
        $this->dropTable('{{%fias_region}}');
    }
}
