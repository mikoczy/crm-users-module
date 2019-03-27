<?php


use Phinx\Migration\AbstractMigration;

class AddKeyIndexToUserMetaTable extends AbstractMigration
{
    public function change()
    {
        $this->table('user_meta')
            ->addIndex('key')
            ->save();
    }
}
