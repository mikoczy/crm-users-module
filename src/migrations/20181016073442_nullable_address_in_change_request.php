<?php


use Phinx\Migration\AbstractMigration;

class NullableAddressInChangeRequest extends AbstractMigration
{
    public function up()
    {
        $this->table('address_change_requests')
            ->changeColumn('address_id', 'integer', ['null' => true, 'after' => 'user_id'])
            ->save();
    }

    public function down()
    {
        $this->table('address_change_requests')
            ->changeColumn('address_id', 'integer', ['null' => false, 'after' => 'user_id'])
            ->save();
    }
}
