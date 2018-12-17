<?php

use Phinx\Migration\AbstractMigration;

class AddressChangeRequestUnify extends AbstractMigration
{
    public function up()
    {
        $this->table('addresses')
            ->changeColumn('first_name', 'string', ['null' => true])
            ->changeColumn('last_name', 'string', ['null' => true])
            ->renameColumn('ico', 'company_id')
            ->renameColumn('dic', 'company_tax_id')
            ->renameColumn('icdph', 'company_vat_id')
            ->save();

        $this->table('address_change_requests')
            ->changeColumn('first_name', 'string', ['null' => true])
            ->changeColumn('last_name', 'string', ['null' => true])
            ->changeColumn('phone_number', 'string', ['null' => true])
            ->addColumn('country_id', 'integer', ['null' => true, 'after' => 'zip'])
            ->addColumn('old_country_id', 'integer', ['null' => true, 'after' => 'old_zip'])
            ->addColumn('company_name', 'string', ['null' => true, 'after' => 'last_name'])
            ->addColumn('old_company_name', 'string', ['null' => true, 'after' => 'old_last_name'])
            ->addColumn('company_id', 'string', ['null' => true, 'after' => 'country_id'])
            ->addColumn('old_company_id', 'string', ['null' => true, 'after' => 'old_country_id'])
            ->addColumn('company_tax_id', 'string', ['null' => true, 'after' => 'company_id'])
            ->addColumn('old_company_tax_id', 'string', ['null' => true, 'after' => 'old_company_id'])
            ->addColumn('company_vat_id', 'string', ['null' => true, 'after' => 'company_tax_id'])
            ->addColumn('old_company_vat_id', 'string', ['null' => true, 'after' => 'old_company_tax_id'])
            ->save();
    }

    public function down()
    {
        $this->table('addresses')
            ->renameColumn('company_id', 'ico')
            ->renameColumn('company_tax_id', 'dic')
            ->renameColumn('company_vat_id', 'icdph')
            ->save();

        $this->execute("UPDATE address_change_requests SET first_name = '' WHERE first_name IS NULL");
        $this->execute("UPDATE address_change_requests SET last_name = '' WHERE last_name IS NULL");
        $this->execute("UPDATE address_change_requests SET phone_number = '' WHERE phone_number IS NULL");

        $this->table('address_change_requests')
            ->changeColumn('first_name', 'string', ['null' => false])
            ->changeColumn('last_name', 'string', ['null' => false])
            ->changeColumn('phone_number', 'string', ['null' => false])
            ->removeColumn('country_id')
            ->removeColumn('old_country_id')
            ->removeColumn('company_name')
            ->removeColumn('old_company_name')
            ->removeColumn('company_id')
            ->removeColumn('old_company_id')
            ->removeColumn('company_tax_id')
            ->removeColumn('old_company_tax_id')
            ->removeColumn('company_vat_id')
            ->removeColumn('old_company_vat_id')
            ->save();
    }
}
