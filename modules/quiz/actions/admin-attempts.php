<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz/attempts', 'view');

$stmt = $pdo->query(
    'SELECT a.id, a.status, a.account_id, a.total_score, a.passed, a.submitted_at, a.updated_at, q.quiz_key, q.title
     FROM sr_quiz_attempts a
     INNER JOIN sr_quiz_sets q ON q.id = a.quiz_id
     ORDER BY a.updated_at DESC, a.id DESC
     LIMIT 100'
);
$attempts = $stmt->fetchAll();
?>
<div class="admin-card">
    <div class="admin-card-header">
        <h2>퀴즈 시도/보상 내역</h2>
    </div>
    <div class="admin-card-body">
        <?php if ($attempts === []): ?>
            <p class="admin-empty">기록된 퀴즈 시도가 없습니다.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>퀴즈</th>
                        <th>회원 ID</th>
                        <th>상태</th>
                        <th>점수</th>
                        <th>통과</th>
                        <th>제출일</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $attempt): ?>
                        <tr>
                            <td><?php echo sr_e((string) $attempt['id']); ?></td>
                            <td><?php echo sr_e((string) $attempt['title']); ?></td>
                            <td><?php echo sr_e((string) $attempt['account_id']); ?></td>
                            <td><?php echo sr_e((string) $attempt['status']); ?></td>
                            <td><?php echo sr_e((string) ($attempt['total_score'] ?? '')); ?></td>
                            <td><?php echo ((int) ($attempt['passed'] ?? 0) === 1) ? '예' : '아니오'; ?></td>
                            <td><?php echo sr_e((string) ($attempt['submitted_at'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
