<?php

declare(strict_types=1);

namespace OCA\SmartCook\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version1001Date20260723000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('smartcook_media')) {
            return $schema;
        }
        $table = $schema->getTable('smartcook_media');
        if (!$table->hasColumn('file_size')) {
            $table->addColumn('file_size', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
        }
        return $schema;
    }
}
