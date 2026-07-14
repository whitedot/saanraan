<?php

declare(strict_types=1);

require_once SR_ROOT . '/core/helpers/privacy-export.php';

if (defined('SR_ROOT') && is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}

return static function (PDO $pdo, int $accountId): array {
    $sectionLimits = [];
    if ($accountId < 1) {
        return ['records' => [], '_limits' => []];
    }

    $stmt = $pdo->prepare(
        'SELECT r.id,
                r.target_module,
                r.target_type,
                r.target_id,
                r.reaction_key,
                COALESCE(d.label, \'\') AS current_label,
                r.created_at,
                r.updated_at
         FROM sr_reaction_records r
         LEFT JOIN sr_reaction_definitions d ON d.reaction_key = r.reaction_key
         WHERE r.account_id = :account_id
         ORDER BY r.id ASC
         LIMIT 1001'
    );
    $stmt->execute(['account_id' => $accountId]);
    $records = sr_privacy_export_limit_rows($stmt->fetchAll(), 'records', $sectionLimits, 1000);

    if (function_exists('sr_reaction_resolve_targets')) {
        try {
            $targetGroups = [];
            foreach ($records as $record) {
                $targetModule = (string) ($record['target_module'] ?? '');
                $targetType = (string) ($record['target_type'] ?? '');
                $targetId = (string) ($record['target_id'] ?? '');
                if ($targetModule !== '' && $targetType !== '' && $targetId !== '') {
                    $targetGroups[$targetModule . '/' . $targetType][] = $targetId;
                }
            }

            $resolvedTargets = [];
            foreach ($targetGroups as $groupKey => $targetIds) {
                [$targetModule, $targetType] = array_pad(explode('/', $groupKey, 2), 2, '');
                foreach (sr_reaction_resolve_targets($pdo, $targetModule, $targetType, $targetIds, $accountId, ['context' => 'privacy_export']) as $targetId => $target) {
                    $resolvedTargets[$groupKey . '/' . (string) $targetId] = $target;
                }
            }

            foreach ($records as $index => $record) {
                $targetKey = (string) ($record['target_module'] ?? '') . '/' . (string) ($record['target_type'] ?? '') . '/' . (string) ($record['target_id'] ?? '');
                $target = isset($resolvedTargets[$targetKey]) && is_array($resolvedTargets[$targetKey]) ? $resolvedTargets[$targetKey] : null;
                if (is_array($target) && (string) ($target['status'] ?? '') === 'active' && !empty($target['can_view'])) {
                    $records[$index]['target_label'] = (string) ($target['label'] ?? '');
                    $records[$index]['target_url'] = (string) ($target['public_url'] ?? '');
                }
            }
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'reaction_privacy_export_target_resolve');
            }
        }
    }

    return [
        'records' => $records,
        '_limits' => $sectionLimits,
    ];
};
