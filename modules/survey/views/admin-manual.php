<?php
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<section class="card">
    <div class="card-header"><h2 class="card-title">운영 순서</h2></div>
    <div class="form-grid">
        <div class="form-row">
            <span class="form-label">1. 기본값</span>
            <div class="form-field"><p>환경설정에서 새 설문 기본 상태, 로그인/동의 필요 여부, 응답 제한, 공개 목록 노출 수를 먼저 정합니다.</p></div>
        </div>
        <div class="form-row">
            <span class="form-label">2. 설문 작성</span>
            <div class="form-field"><p>설문 관리에서 연구 목적, 대상자, 모집 방법, 동의 문구, 개인정보 안내, 문항과 선택지를 입력합니다.</p></div>
        </div>
        <div class="form-row">
            <span class="form-label">3. 공개 전 점검</span>
            <div class="form-field"><p>필수 문항, 선택지 수, 숫자 범위, 기간 제한, 보상 중복 기준, 검색 색인 정책을 확인한 뒤 공개 상태로 바꿉니다.</p></div>
        </div>
        <div class="form-row">
            <span class="form-label">4. 응답 관리</span>
            <div class="form-field"><p>응답 관리에서 품질 상태를 포함, 검토, 제외로 표시하고 필요한 메모를 남깁니다. 통계 화면은 제외 응답을 집계에서 빼고 계산합니다.</p></div>
        </div>
        <div class="form-row">
            <span class="form-label">5. 리워드 로그</span>
            <div class="form-field"><p>리워드 로그에서 설문 응답 보상 지급 성공/실패와 실패 사유를 확인합니다. 보상 실패가 있으면 보상 자산, 쿠폰 상태, 중복 지급 기준을 함께 확인합니다.</p></div>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-header"><h2 class="card-title">보상과 개인정보</h2></div>
    <div class="card-body">
        <ul class="quiz-manual-list">
            <li>보상 설문은 로그인 필요 상태에서만 저장할 수 있습니다.</li>
            <li>응답 저장 시 동의 문구와 설문 메타데이터 스냅샷을 함께 보존합니다.</li>
            <li>응답 보상 지급 기록은 리워드 로그에서 설문, 응답, 회원, 보상 공급자, 지급 참조, 실패 사유 기준으로 확인합니다.</li>
            <li>회원 개인정보 정리 요청이 처리되면 설문 응답과 보상 지급 기록의 회원 연결값, IP 해시, 사용자 에이전트 해시를 익명화합니다.</li>
            <li>CSV 내보내기는 관리자 감사 로그를 남기고 최대 5,000행까지 내려받습니다.</li>
        </ul>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
