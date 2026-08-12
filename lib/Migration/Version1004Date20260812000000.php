<?php

declare(strict_types=1);

namespace OCA\SmartCook\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version1004Date20260812000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable('smartcook_recipes')) {
            return $schema;
        }

        $table = $schema->getTable('smartcook_recipes');
        if ($table->hasColumn('course')) {
            $table->dropColumn('course');
        }
        return $schema;
    }
}
