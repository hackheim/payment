<?php

use Phinx\Migration\AbstractMigration;

class InitialTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('member');
        $table->addColumn('email', 'string')
              ->addColumn('customer_id', 'string', array('null' => true))
              ->addColumn('name', 'string', array('null' => true))
              ->addColumn('phone', 'string', array('null' => true))
              ->addColumn('token', 'string', array('null' => true))
              ->addColumn('address', 'string', array('null' => true))
              ->addColumn('zip', 'string', array('null' => true))
              ->addColumn('state', 'string', array('null' => true))
              ->addColumn('city', 'string', array('null' => true))
              ->addColumn('country', 'string', array('null' => true))
              ->addColumn('organization_number', 'string', array('null' => true))
              ->addColumn('company_name', 'string', array('null' => true))
              ->create();
    }
}
