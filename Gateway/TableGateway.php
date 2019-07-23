<?php

declare(strict_types=1);

namespace Qant\ORM\Gateway;

use Zend\Db\TableGateway\TableGateway as ZendTableGateway;

class TableGateway extends ZendTableGateway
{
    /**
     * Insert
     *
     * @param  array $set
     * @return int
     */
    public function insert($_set, array $_onConflict = null)
    {
        if (! $this->isInitialized) {
            $this->initialize();
        }

        $insert = $this->sql->insert();
        $insert->values($_set, $insert::VALUES_MULTI);

        if ($_onConflict) {
            $insert->onConflict($_onConflict);
        }

        return $this->executeInsert($insert);
    }

}
