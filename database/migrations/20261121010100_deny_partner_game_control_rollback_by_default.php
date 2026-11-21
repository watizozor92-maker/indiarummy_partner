<?php

use think\migration\Migrator;

class DenyPartnerGameControlRollbackByDefault extends Migrator
{
    public function up(): void
    {
        $this->execute('ALTER TABLE `partner_game_control_scope` MODIFY `allow_rollback` tinyint unsigned NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE `partner_game_control_scope` MODIFY `allow_rollback` tinyint unsigned NOT NULL DEFAULT 1');
    }
}
