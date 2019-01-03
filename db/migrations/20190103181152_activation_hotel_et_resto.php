<?php

use Phinx\Migration\AbstractMigration;

class ActivationHotelEtResto extends AbstractMigration
{
    public function change()
    {
        $sql = <<<SQL
ALTER TABLE `afup_forum`
ADD `speakers_diner_enabled` TINYINT DEFAULT 1,
ADD `accomodation_enabled` TINYINT DEFAULT 1
;
SQL;
        $this->execute($sql);
    }
}
