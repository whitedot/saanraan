#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_asset_settlement_check_contains(string $file, array $needles): void
{
    global $errors;

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read required contract document: ' . $file;
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $errors[] = $file . ' must document asset settlement contract marker: ' . $needle;
        }
    }
}

sr_asset_settlement_check_contains('docs/core-decisions.md', [
    '멱등 key는 회원, 소비 모듈, `reference_type`, `reference_id`, 기준금액, 기준 통화, 클라이언트 요청 토큰처럼 재시도 사이에 변하지 않는 입력으로만 만들고',
    '클라이언트 요청 토큰은 HTTP attempt마다 새로 만들지 않고 구매 의도(intent), 즉 확인 화면 렌더 시점에 1회 생성해 확정 POST 재시도 전체에서 동일하게 운반합니다',
    '실행 트랜잭션에 들어가면 원장 row를 잠그기 전에 안정 입력 기반 dedupe key를 가진 claim row를 먼저 insert해야 하며',
    '이 key에는 DB unique 제약을 둡니다',
    '동시 중복 요청은 원장 lock이 아니라 이 unique claim 충돌에서 `processing` 또는 저장된 `completed` 결과로 흡수하고',
    '성공 결과만 claim row와 함께 커밋해 sticky 저장하고',
    '재검증 거부나 실행 실패는 같은 트랜잭션 rollback으로 claim row도 사라지게 둡니다',
    '거부/실패 재시도는 저장된 거부 결과를 반환하지 않고 현재 상태로 부작용 없이 재평가합니다',
    '성공 claim row의 TTL은 확인 token의 staleness window보다 길게 유지하며',
    'window를 막 지난 late duplicate도 새 실행으로 보지 않고 저장된 성공 결과와 표시용 snapshot을 반환합니다',
    '자산별 차감량, 잔액 snapshot, settlement 배분 결과, 확인 화면 fingerprint는 key 재계산 입력으로 쓰지 않습니다',
    '실행 트랜잭션은 참여 자산 row를 결정적 `deduction_order`와 `asset_module` 사전순 tiebreak 순서대로 잠근 뒤',
    '무효화 사유는 잔액 부족/동시 차감처럼 plan 수량을 실행할 수 없는 경우와',
    '확인-실행 사이 구매력 snapshot, 통화 min-unit, rounding/carry `rounding_policy_version`이 달라진 경우를 분리해 기록합니다',
    '마지막 자산의 잔여 settlement 흡수는 정확 충당이 가능한 범위까지만 허용하고',
    '1 자산 단위의 settlement 가치가 통화 최소단위보다 커서 정확한 기준금액을 만들 수 없는 경우 1단위 미만 ceil overpay는 허용하지 않습니다',
    '`price.currency == 각 참여 자산의 purchase_power.settlement_currency`',
    '`asset_units`와 `settlement_units`는 양의 정수여야 하고',
    '`settlement_currency`는 core/settings의 known currency min-unit registry에 존재해야 합니다',
    '자산 설정 저장 또는 관리자 config 로드 시점에 설정 오류로 노출합니다',
    '기존 확인 화면에서 진행 중인 in-flight 요청은 fail-closed로 재확인이 발생할 수 있음을 통화/정책 변경 워크플로에 안내합니다',
    '통화 min-unit registry는 자산 모듈이 아니라 core/settings가 소유하며',
    '`snapshot_schema_version`은 snapshot 구조 버전',
    '`rounding_policy_version`은 금액 계산/반올림/잔여 처리 버전',
    '`settlement_kind`는 `paid`, `free`, `paid_settled_zero`, `preview_test_zero`, `legacy_unknown` 중 하나',
    '`free`는 무료 접근뿐 아니라 지급/적립처럼 기준가격 settlement가 발생하지 않는 non-use row를 포함하고',
    '같은 PDO transaction에 동참해야 하며 내부 commit이나 별도 connection을 쓰면 안 됩니다',
    '문구 존재를 보는 정적 체크는 계약 조항 삭제를 막는 가드일 뿐 transaction 동참, carry, overpay, lock 순서의 런타임 준수를 증명하지 못하므로',
    'InnoDB에서는 미커밋 unique claim row에 대한 중복 insert가 선행 트랜잭션의 commit/rollback까지 블록될 수 있으므로',
    'commit 후 duplicate-key, rollback 후 insert 성공, lock wait timeout 시 `processing` 응답을 모두 확인해야 합니다',
]);

sr_asset_settlement_check_contains('docs/module-guide.md', [
    '`deduction_order`가 같으면 소비 모듈과 공통 helper는 `asset_module` 사전순으로 정렬해야 하며',
    '다중 자산 row lock도 같은 순서로 잡아 동시 복합 차감 간 deadlock 가능성을 낮춘다',
    '`transaction_function`은 호출자가 이미 시작한 같은 PDO transaction에 동참해야 하며',
    '`purchase_power => [\'asset_units\' => 양의 정수, \'settlement_units\' => 양의 정수, \'settlement_currency\' => 통화 코드]`',
    '`asset_units`와 `settlement_units`는 양의 정수, `settlement_currency`는 core/settings min-unit registry에 존재하는 통화인지 자산 설정 저장 또는 관리자 config 로드 시점에 검증해 setup 오류로 노출한다',
    'settlement 기반 차감의 멱등 key는 회원, 소비 모듈, `reference_type`, `reference_id`, 기준금액, 기준 통화, 클라이언트 요청 토큰처럼 안정 입력만 사용한다',
    '클라이언트 요청 토큰은 HTTP attempt마다 새로 만들지 않고 구매 의도(intent), 즉 확인 화면 렌더 시점에 1회 생성해 확정 POST 재시도 전체에서 동일하게 운반한다',
    '실행 트랜잭션은 원장 row lock보다 먼저 안정 입력 기반 dedupe key의 claim row를 insert해야 하며',
    '이 key에는 DB unique 제약을 둔다',
    'duplicate-key가 나면 동시 중복으로 보고 `processing` 또는 저장된 성공 결과를 반환하며',
    '성공 결과만 claim row와 함께 커밋해 sticky 저장하고',
    '재검증 거부나 실행 실패는 rollback으로 claim row도 사라지게 두어 재시도 시 현재 상태로 부작용 없이 재평가한다',
    '마지막 자산의 잔여 settlement 흡수는 정확 충당이 가능한 범위까지만 허용한다',
    '확인 화면 이후 실행 전 잔액이 줄어든 경우와 구매력 snapshot, 통화 min-unit, rounding/carry `rounding_policy_version`이 바뀐 경우를 별도 무효화 사유로 기록하고',
    '운영자가 통화 min-unit 또는 rounding/carry `rounding_policy_version`을 변경하면 기존 확인 화면의 in-flight 요청이 fail-closed 재확인으로 떨어질 수 있음을 변경 워크플로에 안내한다',
    '`snapshot_schema_version`, rounding/carry `rounding_policy_version`, 0원/legacy 분류 `settlement_kind`',
    '`settlement_kind`는 `paid`, `free`, `paid_settled_zero`, `preview_test_zero`, `legacy_unknown` 중 하나',
    '`free`는 무료 접근뿐 아니라 지급/적립처럼 기준가격 settlement가 발생하지 않는 non-use row를 포함하고',
    '정적 체크는 계약 문구 회귀 방지용이며 transaction 동참, carry, overpay, lock 순서의 런타임 준수는 구현 시점 테스트 fixture로 검증한다',
    'InnoDB의 미커밋 unique claim 중복 insert는 선행 트랜잭션 commit/rollback까지 블록될 수 있으므로 commit 후 duplicate-key, rollback 후 insert 성공, lock wait timeout 시 `processing` 응답을 함께 확인한다',
]);

sr_asset_settlement_check_contains('docs/smoke-test.md', [
    '클라이언트 요청 토큰은 HTTP attempt가 아니라 구매 의도(intent)마다 확인 화면 렌더 시점에 1회 생성되어야 하며',
    '실행 트랜잭션은 원장 row lock보다 먼저 unique 제약이 있는 claim row를 insert해야 하며',
    '성공 후에는 잔액 snapshot이나 자산별 계산 결과가 바뀌어도 원장을 다시 만들지 않고 저장된 성공 결과와 표시용 snapshot을 반환해야 한다',
    '재검증 거부나 실행 실패는 rollback으로 claim row도 사라지므로, 재시도 시 저장된 거부 결과가 아니라 현재 상태로 부작용 없이 재평가되어야 한다',
    '두 탭 동시 제출은 duplicate-key에서 `processing` 또는 저장된 성공 결과로 흡수되고 lock 획득 뒤에도 claim row 상태를 다시 확인해야 한다',
    '확인 window를 막 지난 late duplicate도 만료 직후 새 실행이 아니라 저장된 성공 결과로 떨어져야 한다',
    'commit 후 duplicate-key, rollback 후 insert 성공, lock wait timeout 시 `processing` 응답을 fixture로 확인한다',
    '구매력/통화 min-unit/`rounding_policy_version`이 바뀌면 snapshot drift 사유로 별도 기록하며',
    'settlement 로그에는 `settlement_kind`, `snapshot_schema_version`, `rounding_policy_version`이 저장되어야 하며',
    '기존 분류 불가 0원 backfill row는 `legacy_unknown`으로 남아야 한다',
    '다중 자산 row lock은 `deduction_order`와 `asset_module` tiebreak 순서로 잡는지 확인한다',
    '`asset_units`/`settlement_units` 양의 정수 여부와 `settlement_currency`의 min-unit registry 존재 여부는 설정 저장 또는 관리자 config 로드 시점에 setup 오류로 드러나야 한다',
    '통화 min-unit 또는 rounding/carry `rounding_policy_version` 변경 직후 기존 확인 화면의 in-flight 요청은 fail-closed 재확인으로 떨어질 수 있음을 운영 워크플로에서 확인한다',
    '1P = 10 KRW, 가격 1,005 KRW 같은 케이스는 정확 충당 불가로 실패하고 ceil overpay가 없어야 하며',
    '기준금액 0은 차감 없이 `settlement_amount=0` 로그와 접근권만 남겨야 한다',
    '문서 정적 체크는 계약 조항 삭제 방지용이므로 transaction 동참, carry, overpay, lock 순서는 구현 테스트 fixture와 필요한 HTTP smoke로 행위를 검증한다',
]);

sr_asset_settlement_check_contains('docs/records/issue-115-settlement-contract-2026-06-11.md', [
    '실행 트랜잭션은 원장 row lock보다 먼저 안정 입력 기반 dedupe key의 claim row를 insert해야 하며',
    '최초 성공 결과는 claim row와 함께 저장한다',
    '재검증 거부나 실행 실패는 같은 transaction rollback으로 claim row도 사라지게 두며',
    'window를 막 지난 late duplicate도 새 실행으로 보지 않고 저장된 성공 결과와 표시용 snapshot을 반환하고 원장을 다시 만들지 않는다',
    '런타임 통화 불변식은 `price.currency == 각 참여 자산의 purchase_power.settlement_currency`이며',
    '`member-assets.php` 거래 helper는 같은 PDO transaction에 동참해야 하며 내부 commit이나 별도 connection을 쓰면 복합 차감 후보에서 제외한다',
]);

$memberAssetsHelper = file_get_contents('modules/member/helpers/assets.php');
if (!is_string($memberAssetsHelper)) {
    $errors[] = 'cannot read modules/member/helpers/assets.php';
} elseif (!str_contains($memberAssetsHelper, 'strcmp((string) ($left[\'module_key\'] ?? \'\'), (string) ($right[\'module_key\'] ?? \'\'))')) {
    $errors[] = 'member asset definition sorting must keep deterministic module_key tiebreak for equal deduction_order';
}

$settlementSchemaFiles = [
    'modules/content/install.sql',
    'modules/community/install.sql',
    'modules/content/helpers/assets.php',
    'modules/community/helpers/assets.php',
    'modules/content/privacy-export.php',
    'modules/community/privacy-export.php',
];
foreach ($settlementSchemaFiles as $settlementSchemaFile) {
    sr_asset_settlement_check_contains($settlementSchemaFile, [
        'settlement_kind',
        'snapshot_schema_version',
        'rounding_policy_version',
    ]);
}

foreach (['modules/content/privacy-export.php', 'modules/community/privacy-export.php'] as $privacyExportFile) {
    $privacyExport = file_get_contents($privacyExportFile);
    if (!is_string($privacyExport)) {
        $errors[] = 'cannot read privacy export file: ' . $privacyExportFile;
        continue;
    }

    if (str_contains($privacyExport, "'policy_version' =>")) {
        $errors[] = $privacyExportFile . ' must expose rounding_policy_version instead of policy_version in privacy export summaries';
    }
}

if ($errors !== []) {
    fwrite(STDERR, "asset settlement contract checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset settlement contract checks completed.\n";
