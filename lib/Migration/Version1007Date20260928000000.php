<?php

declare(strict_types=1);

namespace OCA\SmartCook\Migration;

use Closure;
use OCA\SmartCook\Service\DifficultyNormalizer;
use OCP\DB\IQueryBuilder;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version1007Date20260928000000 extends SimpleMigrationStep {
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        $db = \OC::$server->getDatabaseConnection();
        $select = $db->getQueryBuilder();
        $select->select('id', 'difficulty')->from('smartcook_recipes')
            ->where($select->expr()->isNotNull('difficulty'));
        $rows = $select->executeQuery()->fetchAllAssociative();
        $normalizer = new DifficultyNormalizer();

        foreach ($rows as $row) {
            $difficulty = $normalizer->normalize($row['difficulty']);
            if ($difficulty === $row['difficulty']) {
                continue;
            }
            $update = $db->getQueryBuilder();
            $update->update('smartcook_recipes')
                ->set('difficulty', $update->createNamedParameter($difficulty, $difficulty === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
                ->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)))
                ->executeStatement();
        }
    }
}
