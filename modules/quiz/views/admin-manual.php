<?php
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<div class="quiz-manual-page">
<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">처음 시작 순서</h2>
    </div>
    <div class="admin-form-grid">
        <div class="admin-form-row">
            <span class="form-label">1. 기본값 정하기</span>
            <div class="admin-form-field">
                <p>먼저 사이드 메뉴의 <strong>퀴즈 환경설정</strong>에서 새 퀴즈를 만들 때 자동으로 들어갈 기본 상태, 채점 방식, 문제 선택지 수, 보상 기본값을 저장합니다. 이 설정은 새로 만드는 퀴즈의 시작값만 바꾸며, 이미 저장된 퀴즈는 자동으로 바뀌지 않습니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">2. 퀴즈 만들기</span>
            <div class="admin-form-field">
                <p><strong>퀴즈 관리</strong>에서 <strong>새 퀴즈</strong>를 누릅니다. 제목, 상태, 채점 방식, 응시 제한, 문제, 결과 규칙, 보상 정책, 연결 대상을 차례로 입력한 뒤 저장합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">3. 미리 점검하기</span>
            <div class="admin-form-field">
                <p>상태를 공개로 바꾸기 전에 문제 수, 정답, 통과 점수, 결과 규칙, 보상 종류, 공개 기간, 회원 그룹 조건을 다시 확인합니다. 저장 오류가 나오면 표시된 항목부터 고치면 됩니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">4. 운영 중 확인하기</span>
            <div class="admin-form-field">
                <p><strong>시도/보상 내역</strong>에서 회원 응시 상태, 점수, 통과 여부, 보상 지급 성공/실패를 확인합니다. 보상 실패가 있으면 보상 자산, 쿠폰 상태, 중복 지급 기준을 함께 확인합니다.</p>
            </div>
        </div>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">화면별 역할</h2>
    </div>
    <div class="admin-form-grid">
        <div class="admin-form-row">
            <span class="form-label">퀴즈 관리</span>
            <div class="admin-form-field">
                <p>퀴즈를 만들거나 기존 퀴즈를 수정, 복사, 삭제할 때 사용합니다. 검색, 필터, 새 퀴즈, 복사, 수정, 삭제 작업을 처리합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">퀴즈 환경설정</span>
            <div class="admin-form-field">
                <p>새 퀴즈 생성 화면의 기본값을 정할 때 사용합니다. 기본 상태, 기본 채점, 기본 문제 수, 기본 보상, CTA 문구, 공개 목록 수를 저장합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">시도/보상 내역</span>
            <div class="admin-form-field">
                <p>회원이 퀴즈를 풀었는지, 보상이 지급됐는지 확인할 때 사용합니다. 시도 상태, 점수, 통과 여부, 보상 상태, 실패 사유를 확인합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">퀴즈 매뉴얼</span>
            <div class="admin-form-field">
                <p>운영 절차를 다시 확인하거나 새 운영자에게 설명할 때 사용합니다. 설정, 생성, 복사, 공개, 보상 점검 순서를 확인합니다.</p>
            </div>
        </div>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">퀴즈 환경설정 사용법</h2>
    </div>
    <div class="admin-form-grid">
        <div class="admin-form-row">
            <span class="form-label">새 퀴즈 기본값</span>
            <div class="admin-form-field">
                <p>새 퀴즈를 만들 때 자동으로 채워질 시작값입니다.</p>
                <ul class="quiz-manual-list">
                    <li><strong>기본 상태</strong>: 검수 전 노출을 막으려면 초안으로 시작합니다.</li>
                    <li><strong>기본 채점 방식</strong>: 새 퀴즈에서 가장 자주 쓰는 유형을 선택합니다.</li>
                    <li><strong>새 문제 기본 선택지 수</strong>: 문제 추가 모달에 먼저 보일 선택지 입력칸 수입니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">기본 보상</span>
            <div class="admin-form-field">
                <p>새 퀴즈의 보상 기본값을 정합니다. 보상을 기본으로 쓰지 않으려면 지급안함을 선택합니다.</p>
                <ul class="quiz-manual-list">
                    <li><strong>지급안함</strong>: 새 퀴즈의 보상 사용을 꺼 둔 상태로 시작합니다.</li>
                    <li><strong>포인트/금액</strong>: 지급 항목과 금액을 입력합니다.</li>
                    <li><strong>쿠폰 발급</strong>: 현재 사용할 수 있는 쿠폰 중 하나를 선택합니다.</li>
                    <li><strong>쿠폰이 안 보일 때</strong>: 쿠폰 관리에서 활성 상태와 사용 기간을 먼저 확인합니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">공개 목록/연결</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li><strong>기본 연결 CTA 문구</strong>: 콘텐츠나 커뮤니티 게시글에 붙는 퀴즈 버튼 문구입니다.</li>
                    <li><strong>문구 예시</strong>: 퀴즈 풀기, 진단 시작하기, 혜택 받기.</li>
                    <li><strong>공개 목록 노출 수</strong>: 공개 퀴즈 목록에서 한 번에 보여줄 퀴즈 수입니다.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">새 퀴즈 만들기</h2>
    </div>
    <div class="admin-form-grid">
        <div class="admin-form-row">
            <span class="form-label">기본 정보</span>
            <div class="admin-form-field">
                <dl class="quiz-manual-example">
                    <dt class="quiz-manual-example-title">예시 입력값</dt>
                    <dd>
                        <dl class="quiz-manual-example-list">
                            <div>
                                <dt>관리용 키</dt>
                                <dd><strong>spring_benefit_quiz</strong></dd>
                            </div>
                            <div>
                                <dt>제목</dt>
                                <dd><strong>봄맞이 혜택 퀴즈</strong></dd>
                            </div>
                            <div>
                                <dt>상태</dt>
                                <dd><strong>초안</strong>으로 저장한 뒤 검수 후 공개</dd>
                            </div>
                        </dl>
                    </dd>
                </dl>
                <ul class="quiz-manual-list">
                    <li><strong>관리용 키</strong>는 주소와 내부 연결에 쓰는 고유 이름입니다.</li>
                    <li>영문 소문자, 숫자, 밑줄만 사용합니다.</li>
                    <li><strong>보관</strong>은 더 이상 운영하지 않는 퀴즈에 사용합니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">정답 통과</span>
            <div class="admin-form-field">
                <p>“몇 문제 이상 맞히면 성공”인 기본 퀴즈입니다.</p>
                <ul class="quiz-manual-list">
                    <li>예시: 3문제 중 2문제 이상 정답이면 성공.</li>
                    <li>통과 점수: <strong>2</strong>.</li>
                    <li>추천 상황: 통과한 회원에게 포인트나 쿠폰을 지급하는 이벤트.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">총점 결과</span>
            <div class="admin-form-field">
                <p>점수 구간에 따라 서로 다른 결과 안내를 보여줍니다.</p>
                <ul class="quiz-manual-list">
                    <li><strong>0~1점</strong>: 기본 안내를 다시 읽어보세요.</li>
                    <li><strong>2~3점</strong>: 핵심 기능을 이해했어요.</li>
                    <li><strong>4~5점</strong>: 사이트 이용 준비 완료.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">카테고리 진단</span>
            <div class="admin-form-field">
                <p>정답보다 회원에게 맞는 유형을 알려주는 진단에 적합합니다.</p>
                <ul class="quiz-manual-list">
                    <li><strong>discount</strong>: 즉시 할인 선호.</li>
                    <li><strong>point</strong>: 적립금 선호.</li>
                    <li><strong>shipping</strong>: 무료배송 선호.</li>
                    <li>가장 높은 카테고리에 맞춰 추천 결과를 보여줍니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">결과 규칙</span>
            <div class="admin-form-field">
                <p>퀴즈를 푼 뒤 보여줄 결과 안내입니다.</p>
                <ul class="quiz-manual-list">
                    <li><strong>점수형 퀴즈</strong>: 최소 점수와 최대 점수를 입력합니다.</li>
                    <li><strong>진단형 퀴즈</strong>: 카테고리 관리용 키와 기준값을 입력합니다.</li>
                    <li><strong>결과 관리용 키</strong>: 같은 퀴즈 안에서 중복될 수 없습니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">공개/응시 조건</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li><strong>공개 기간 없음</strong>: 상태가 공개인 동안 접근할 수 있습니다.</li>
                    <li><strong>기간 이벤트</strong>: 공개 시작일시와 종료일시를 모두 입력합니다.</li>
                    <li><strong>기간당 1회 제한</strong>: 하루 제한은 <strong>86400</strong>초로 입력합니다.</li>
                    <li><strong>회원 그룹 미선택</strong>: 로그인 회원 전체가 응시할 수 있습니다.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">문제와 선택지 입력</h2>
    </div>
    <div class="admin-form-grid">
        <div class="admin-form-row">
            <span class="form-label">문제 추가</span>
            <div class="admin-form-field">
                <dl class="quiz-manual-example">
                    <dt class="quiz-manual-example-title">봄맞이 혜택 퀴즈 첫 번째 문제</dt>
                    <dd>
                        <dl class="quiz-manual-example-list">
                            <div>
                                <dt>문제 관리용 키</dt>
                                <dd><strong>q1</strong></dd>
                            </div>
                            <div>
                                <dt>문제 내용</dt>
                                <dd><strong>봄맞이 할인 쿠폰의 사용 기한은 언제까지인가요?</strong></dd>
                            </div>
                            <div>
                                <dt>점수</dt>
                                <dd><strong>1</strong></dd>
                            </div>
                        </dl>
                    </dd>
                </dl>
                <ul class="quiz-manual-list">
                    <li>두 번째 문제는 <strong>q2</strong>, 세 번째 문제는 <strong>q3</strong>처럼 이어서 입력합니다.</li>
                    <li>문제 관리용 키는 회원에게 크게 보이는 문구가 아니라 관리자와 시스템이 문제를 구분하는 이름입니다.</li>
                    <li>같은 퀴즈 안에서 문제 관리용 키가 중복되면 저장할 수 없습니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">정답 선택</span>
            <div class="admin-form-field">
                <dl class="quiz-manual-example">
                    <dt class="quiz-manual-example-title">선택지 예시</dt>
                    <dd>
                        <dl class="quiz-manual-example-list">
                            <div>
                                <dt>c1</dt>
                                <dd>4월 30일</dd>
                            </div>
                            <div>
                                <dt>c2</dt>
                                <dd><strong>5월 31일</strong> - 정답 체크</dd>
                            </div>
                            <div>
                                <dt>c3</dt>
                                <dd>6월 30일</dd>
                            </div>
                            <div>
                                <dt>c4</dt>
                                <dd>제한 없음</dd>
                            </div>
                        </dl>
                    </dd>
                </dl>
                <ul class="quiz-manual-list">
                    <li><strong>단일 선택</strong>: 정답을 정확히 1개만 체크합니다.</li>
                    <li><strong>복수 선택</strong>: 정답 선택지를 2개 이상 체크할 수 있습니다.</li>
                    <li>선택지 내용은 회원에게 보이는 문구이므로 자연스러운 답변 문장으로 씁니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">카테고리 진단</span>
            <div class="admin-form-field">
                <dl class="quiz-manual-example">
                    <dt class="quiz-manual-example-title">선호 혜택 진단 예시</dt>
                    <dd>
                        <dl class="quiz-manual-example-list">
                            <div>
                                <dt>즉시 할인</dt>
                                <dd><strong>discount</strong> 1점</dd>
                            </div>
                            <div>
                                <dt>적립금</dt>
                                <dd><strong>point</strong> 1점</dd>
                            </div>
                            <div>
                                <dt>무료배송</dt>
                                <dd><strong>shipping</strong> 1점</dd>
                            </div>
                        </dl>
                    </dd>
                </dl>
                <p>회원이 여러 문제를 풀면 선택한 답변의 카테고리 점수가 합산됩니다.</p>
            </div>
        </div>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">보상 정책 설정</h2>
    </div>
    <div class="admin-form-grid">
        <div class="admin-form-row">
            <span class="form-label">보상 사용</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li>3문제, 각 1점, 통과 점수 2점이면 2개 이상 맞힌 회원만 통과합니다.</li>
                    <li><strong>보상 사용</strong>을 켜면 통과한 회원에게만 보상 지급을 시도합니다.</li>
                    <li>통과하지 못한 회원에게는 결과만 보여주고 보상은 지급하지 않습니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">포인트/금액</span>
            <div class="admin-form-field">
                <dl class="quiz-manual-example">
                    <dt class="quiz-manual-example-title">100포인트 지급 예시</dt>
                    <dd>
                        <dl class="quiz-manual-example-list">
                            <div>
                                <dt>보상 종류</dt>
                                <dd>포인트/금액</dd>
                            </div>
                            <div>
                                <dt>지급 항목</dt>
                                <dd>포인트</dd>
                            </div>
                            <div>
                                <dt>보상 금액</dt>
                                <dd><strong>100</strong></dd>
                            </div>
                        </dl>
                    </dd>
                </dl>
                <p>회원 잔액에 숫자로 보상을 더해 주고 싶을 때 사용합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">쿠폰 발급</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li>쿠폰 관리에 만들어 둔 쿠폰을 통과한 회원에게 1장 지급합니다.</li>
                    <li>예시: <strong>봄맞이 10% 할인 쿠폰</strong>.</li>
                    <li>쿠폰은 활성 상태이고 사용 기간 안에 있어야 목록에 표시됩니다.</li>
                    <li>목록에 없다면 쿠폰 관리에서 상태와 기간을 먼저 확인합니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">중복 지급 기준</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li><strong>퀴즈당 1회</strong>: 같은 회원에게 이 퀴즈 보상을 한 번만 지급합니다.</li>
                    <li><strong>출처당 1회</strong>: 같은 퀴즈라도 연결된 콘텐츠나 게시글이 다르면 각각 지급할 수 있습니다.</li>
                    <li><strong>응시마다</strong>: 통과할 때마다 보상이 나갈 수 있으므로 반복 참여형 캠페인에만 사용합니다.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">콘텐츠/커뮤니티 연결</h2>
    </div>
    <div class="admin-form-grid">
        <div class="admin-form-row">
            <span class="form-label">연결 대상 입력</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li>콘텐츠나 커뮤니티 게시글에 퀴즈를 붙일 때 대상 ID를 입력합니다.</li>
                    <li>여러 개를 넣을 때는 줄바꿈이나 쉼표로 구분합니다.</li>
                    <li>삭제되었거나 사용할 수 없는 대상은 저장 또는 복사 과정에서 제외될 수 있습니다.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">기존 퀴즈 복사</h2>
    </div>
    <div class="admin-form-grid">
        <div class="admin-form-row">
            <span class="form-label">복사 시작</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li>퀴즈 목록에서 복사할 퀴즈 행의 복사 아이콘을 누릅니다.</li>
                    <li>모달에서 새 퀴즈 관리용 키와 새 제목을 입력합니다.</li>
                    <li>복사본은 기본적으로 초안으로 생성됩니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">복사 범위</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li><strong>항상 복사</strong>: 기본 정보, 채점, 문제, 결과 규칙.</li>
                    <li><strong>선택 복사</strong>: 공개 기간, 회원 그룹, 보상 정책, 연결 대상, 원본 공개 상태.</li>
                    <li><strong>재확인 항목</strong>: 사용할 수 없는 보상 정책이나 연결 대상은 복사되지 않을 수 있습니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">복사 후 확인</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li>복사가 끝나면 새 퀴즈 수정 화면으로 이동합니다.</li>
                    <li>제목, 공개 상태, 문제, 결과 규칙, 보상 정책을 다시 확인합니다.</li>
                    <li>검수가 끝난 뒤 상태를 공개로 바꿉니다.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">공개 전 점검표</h2>
    </div>
    <div class="admin-form-grid">
        <div class="admin-form-row">
            <span class="form-label">상태</span>
            <div class="admin-form-field">
                <p>검수가 끝나기 전에는 초안, 공개할 때만 공개로 설정합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">문제와 정답</span>
            <div class="admin-form-field">
                <p>문제 목록에서 문제 수를 확인하고 각 문제 수정 모달에서 정답 체크를 확인합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">통과 점수</span>
            <div class="admin-form-field">
                <p>총 문제 점수와 통과 기준이 맞는지 확인합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">결과 규칙</span>
            <div class="admin-form-field">
                <p>점수 범위가 비거나 겹쳐서 의도하지 않은 결과가 나오지 않는지 확인합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">공개 기간</span>
            <div class="admin-form-field">
                <p>기간 이벤트라면 시작/종료 시각이 맞는지 확인합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">회원 조건</span>
            <div class="admin-form-field">
                <p>특정 회원 그룹만 참여해야 하는 퀴즈라면 응시 가능 회원 그룹을 확인합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">보상</span>
            <div class="admin-form-field">
                <p>보상 종류, 지급 항목, 금액 또는 쿠폰, 중복 지급 기준을 확인합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">연결 대상</span>
            <div class="admin-form-field">
                <p>콘텐츠 ID나 커뮤니티 게시글 ID가 올바른지 확인합니다.</p>
            </div>
        </div>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">운영 중 자주 생기는 상황</h2>
    </div>
    <div class="admin-form-grid">
        <div class="admin-form-row">
            <span class="form-label">퀴즈가 보이지 않음</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li>상태가 공개인지 확인합니다.</li>
                    <li>공개 시작/종료 기간 안인지 확인합니다.</li>
                    <li>삭제 또는 보관 상태가 아닌지 확인합니다.</li>
                    <li>회원 그룹 제한이 있다면 현재 회원이 그 그룹에 속해 있는지 확인합니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">저장 오류가 남</span>
            <div class="admin-form-field">
                <p>화면 상단 토스트의 오류 문구를 먼저 확인합니다.</p>
                <ul class="quiz-manual-list">
                    <li>관리용 키 중복.</li>
                    <li>필수 입력 누락.</li>
                    <li>문제 선택지 부족 또는 정답 개수 오류.</li>
                    <li>사용할 수 없는 쿠폰.</li>
                    <li>찾을 수 없는 연결 대상 ID.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">보상이 지급되지 않음</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li>시도/보상 내역에서 통과 여부를 확인합니다.</li>
                    <li>보상 상태와 실패 사유를 확인합니다.</li>
                    <li>중복 지급 기준에 이미 걸렸는지 확인합니다.</li>
                    <li>포인트/금액 항목 또는 쿠폰이 현재 사용 가능한지 확인합니다.</li>
                </ul>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">복사본이 바로 공개되지 않음</span>
            <div class="admin-form-field">
                <ul class="quiz-manual-list">
                    <li>복사본은 실수 공개를 막기 위해 초안으로 생성됩니다.</li>
                    <li>새 퀴즈 수정 화면에서 내용을 확인합니다.</li>
                    <li>검수가 끝나면 상태를 공개로 바꿉니다.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
    <a href="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="btn btn-solid-light">퀴즈 목록</a>
    <a href="<?php echo sr_e(sr_url('/admin/quiz/settings')); ?>" class="btn btn-solid-primary quiz-manual-settings-link">퀴즈 환경설정</a>
</div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
