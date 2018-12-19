<?php

use Phinx\Migration\AbstractMigration;

class AddressChangeRequestUnify extends AbstractMigration
{
    public function up()
    {
        return;
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

        // link existing change requests to their address
        $sql = <<<SQL
update address_change_requests
join addresses 
  on address_change_requests.user_id = addresses.user_id
  and addresses.type = 'print' 
  and addresses.user_id in (SELECT user_id FROM addresses where `type` = 'print' group by user_id having count(*) = 1)
set address_id = addresses.id
where address_change_requests.type = 'print'
SQL;
        $this->execute($sql);


        // create accepted change request for every print address that doesn't have it yet
        $sql = <<<SQL
insert into address_change_requests (`type`, `user_id`, `address_id`, `first_name`, `last_name`, `company_name`, `address`, `number`, `city`, `zip`, `country_id`, `company_id`, `company_tax_id`, `company_vat_id`, `phone_number`, `status`, `created_at`, `updated_at`)
select addresses.type, addresses.user_id, addresses.id, addresses.first_name, addresses.last_name, addresses.company_name, addresses.address, addresses.number, addresses.city, addresses.zip, addresses.country_id, addresses.company_id, addresses.company_tax_id, addresses.company_vat_id, addresses.phone_number, 'accepted', addresses.created_at, addresses.updated_at
from addresses
left join address_change_requests on address_id = addresses.id
where addresses.type = 'print'
and address_change_requests.id is null
SQL;

        $this->execute($sql);
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
