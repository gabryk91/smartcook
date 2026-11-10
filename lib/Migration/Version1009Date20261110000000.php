<?php

declare(strict_types=1);

namespace OCA\SmartCook\Migration;

use Closure;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version1009Date20261110000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?\OCP\DB\ISchemaWrapper {
        /** @var \OCP\DB\ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('smartcook_imports')) {
            return $schema;
        }
        $table = $schema->getTable('smartcook_imports');
        if (!$table->hasColumn('progress')) {
            $table->addColumn('progress', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        }
        if (!$table->hasColumn('stage')) {
            $table->addColumn('stage', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => 'Queued']);
        }
        return $schema;
    }
}
