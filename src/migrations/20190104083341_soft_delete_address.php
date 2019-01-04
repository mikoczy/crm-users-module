<?php


use Phinx\Migration\AbstractMigration;

class SoftDeleteAddress extends AbstractMigration
{
    public function change()
    {
        $this->table('addresses')
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'after' => 'updated_at'])
            ->save();
    }
}
