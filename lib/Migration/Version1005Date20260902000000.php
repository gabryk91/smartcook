<?php

declare(strict_types=1);

namespace OCA\SmartCook\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version1005Date20260902000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('smartcook_r_ingr')) {
            return $schema;
        }

        $table = $schema->getTable('smartcook_r_ingr');
        if (!$table->hasColumn('alternatives')) {
            $table->addColumn('alternatives', Types::TEXT, ['notnull' => false]);
        }
        return $schema;
    }
}
