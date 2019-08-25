<?php

use yii\db\Migration;
use yii\db\Schema;

/**
 * Class m190824_190450_create_table_paper
 */
class m190824_190450_create_table_paper extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        $this->createTable('paper', [
            'id' => Schema::TYPE_PK,
            'name' => Schema::TYPE_STRING . ' NOT NULL',
            'price' => Schema::TYPE_FLOAT . ' NOT NULL',
            'date' => Schema::TYPE_DATETIME . ' NOT NULL',
            'created_at' => Schema::TYPE_DATETIME . ' NOT NULL'
        ]);

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
         $this->dropTable('paper');

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m190824_190450_create_table_paper cannot be reverted.\n";

        return false;
    }
    */
}
