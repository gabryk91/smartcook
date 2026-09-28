<?php

declare(strict_types=1);

namespace OCA\SmartCook\Migration;

use Closure;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version1008Date20260928000000 extends SimpleMigrationStep {
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        $db = \OC::$server->get(IDBConnection::class);
        foreach (['smartcook_tags', 'smartcook_cats', 'smartcook_tools', 'smartcook_taxonomy'] as $table) {
            $this->capitalizeColumn($db, $table, 'name');
        }
        foreach (['cuisine', 'meal_type', 'cook_method', 'season'] as $column) {
            $this->capitalizeColumn($db, 'smartcook_recipes', $column);
        }
    }

    private function capitalizeColumn(IDBConnection $db, string $table, string $column): void {
        $select = $db->getQueryBuilder();
        $rows = $select->select('id', $column)->from($table)
            ->where($select->expr()->isNotNull($column))
            ->executeQuery()->fetchAllAssociative();
        foreach ($rows as $row) {
            $value = trim((string)$row[$column]);
            $capitalized = $this->capitalize($value);
            if ($capitalized === $value) {
                continue;
            }
            $update = $db->getQueryBuilder();
            $update->update($table)
                ->set($column, $update->createNamedParameter($capitalized, IQueryBuilder::PARAM_STR))
                ->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)))
                ->executeStatement();
        }
    }

    private function capitalize(string $value): string {
        if ($value === '') {
            return '';
        }
        preg_match('/^./us', $value, $match);
        $first = $match[0] ?? $value[0];
        $upper = function_exists('mb_strtoupper') ? mb_strtoupper($first) : strtoupper(strtr($first, ['à' => 'À', 'è' => 'È', 'é' => 'É', 'ì' => 'Ì', 'ò' => 'Ò', 'ù' => 'Ù']));
        return $upper . substr($value, strlen($first));
    }
}
