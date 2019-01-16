<?php


use Phinx\Migration\AbstractMigration;

class AddIpIndexToLoginAttempts extends AbstractMigration
{
    public function change()
    {
        $this->table('login_attempts')
            ->addIndex('ip')
            ->removeIndex(['user_id', 'ip'])
            ->save();
    }
}
