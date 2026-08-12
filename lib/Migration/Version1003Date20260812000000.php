<?php

declare(strict_types=1);

namespace OCA\SmartCook\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version1003Date20260812000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('smartcook_taxonomy')) {
            return $schema;
        }

        $table = $schema->createTable('smartcook_taxonomy');
        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
        $table->setPrimaryKey(['id']);
        $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 32]);
        $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('norm_name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addUniqueIndex(['user_id', 'kind', 'norm_name'], 'sc_taxonomy_user_kind_name_uniq');
        $table->addIndex(['user_id', 'kind'], 'sc_taxonomy_user_kind');
        return $schema;
    }
}
