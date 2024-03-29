<?php

namespace Akali\Postgrenerator\Commands\Bases;

use Akali\Postgrenerator\Traits\Migration;
use Illuminate\Console\Command;

class MigrationCommandBase extends Command
{
    use Migration;

    /**
     * Create a of the migration command.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->setMigrator();
    }
}
