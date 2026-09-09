# 운영 대시보드 디자인 기준

2026-09-09 기준. 관리자 대시보드는 모듈 수만큼 같은 지표 카드를 반복하기보다, **현재 규모 파악 → 대기 업무 처리 → 자산 확인 → 최근 활동 확인 → 필요한 상세 열기** 순서로 읽도록 구성한다.

## 참고 자료와 적용

| 레퍼런스 | 참고한 부분 | 현재 적용 |
| --- | --- | --- |
| [Shopify Home](https://help.shopify.com/en/manual/shopify-admin/shopify-home) | 현황과 미처리 업무를 구분하고 업무 종류별 건수를 제공 | 서비스 지표와 처리 대기 목록을 별도 영역으로 구성 |
| [Stripe Dashboard](https://docs.stripe.com/dashboard/basics) | 사업 현황, 주의가 필요한 항목, 잔액·거래 화면으로 이어지는 탐색 | 대기 업무와 자산 요약에서 소유 모듈의 관리 화면으로 직접 이동 |
| [Linear Dashboards](https://linear.app/docs/dashboards) | 지표 블록·목록·상세 탐색을 한 화면에서 조합 | 서비스 지표, 업무 목록, 자산 목록, 활동 요약을 각 목적에 맞는 밀도로 표시 |
| [IBM Carbon: Dashboards](https://carbondesignsystem.com/data-visualization/dashboards/) | 정보의 중요도와 대비, 지표 수 제한, 여백을 통한 그룹 구분 | 대기 업무에 넓은 영역을 배정하고 나머지를 간결한 요약으로 묶음 |
| [NN/g: Complex Application Design](https://www.nngroup.com/articles/complex-application-design/) | 필요한 상세를 점진적으로 제공하고 불필요한 시각 요소를 줄임 | 전체 모듈 카드와 사이트 정보는 펼칠 수 있는 상세 영역으로 제공 |
| [NN/g: Dashboards](https://www.nngroup.com/articles/dashboards-preattentive/) | 한눈에 구분되는 정보와 빠른 판단을 지원하는 시각 표현 | 숫자·라벨·기간을 구분하고 업무 건수와 현재 상태를 명확히 표시 |
| [Atlassian: Service Management Queues](https://support.atlassian.com/jira-service-management-cloud/docs/check-out-your-queues/) | 처리할 일을 목록으로 확인하고 실제 업무로 이동 | 검토·신고·환불·출금 대기를 각각의 필터된 관리 목록에 연결 |
| [토스페이먼츠 상점관리자](https://docs.tosspayments.com/resources/glossary/dashboard) | 결제·정산 등 운영 도메인의 조회와 관리 연결 | 자산 종류별 현황과 관리 경로를 함께 배치 |

위 자료의 화면을 복제하지 않고, 현재 saanraan이 제공하는 집계와 모듈 경계에 맞춰 적용한다.

## 현재 화면과 데이터 기준

운영 현황 전체를 감싸는 섹션에는 카드 표면을 두지 않는다. 활성 회원·공개 콘텐츠·게시글 지표는 각각 UI kit 카드로 표시하고, 처리 대기, 자산·쿠폰, 최근 7일 활동도 각각 UI kit 카드를 유지한다. 처리 대기와 자산·쿠폰은 데스크톱에서 같은 너비로 배치한다. 자산·쿠폰은 처리 대기처럼 항목별 한 행으로 배치하고, 항목명은 포인트 총 잔액·쿠폰 지급처럼 표시하고 오른쪽에는 수치만 표시한다. 두 카드는 같은 높이로 늘어나도록 배치한다. 왼쪽에 이름과 보유 회원·활성 쿠폰 수, 오른쪽에 잔액·지급 현황과 이동 아이콘을 표시한다. 펼쳐 보는 모듈 상세 카드도 항목명·보조 정보는 왼쪽, 수치는 오른쪽인 행 구조로 통일한다. 기존 데이터와 관리 링크는 유지한다.

- 서비스 규모: 활성 회원, 공개 콘텐츠, 게시글과 각 지표의 보조 집계.
- 처리 대기: 모듈이 `task`로 명시한 건수만 합산한다. 잔액, 쿠폰 사용, 성공 거래와 섞지 않는다.
- 자산·쿠폰: 각 모듈이 제공하는 잔액·지급 현황을 별도로 표시한다. 서로 다른 자산을 합산하지 않는다.
- 최근 7일 활동: 실제 최근 7일 집계가 제공되는 거래·사용·성공 건수만 표시한다. 이 영역에 누적 실패 건수를 섞지 않는다.
- 집계 실패는 0건으로 보정하지 않는다. 확인 필요 항목 수를 따로 안내한다.
- 모든 값은 페이지 조회 시점 기준이다. 현재 계약이 제공하지 않는 증감률, 시계열, 목표 달성률은 표시하지 않는다.
- 숨긴 모듈은 요약에서도 제외한다. 기존 순서·크기·표시 설정은 유지한다.
- 복구 주의 항목은 상단 안내와 열린 상세 영역으로 접근할 수 있게 유지한다.

## 구현과 검증

데이터·상태 조건·이동 경로는 각 모듈의 `dashboard.php`가 소유한다. admin은 이미 조회된 값과 선택 `overview` 메타데이터를 조합하며 추가 SQL을 실행하지 않는다. 화면 조립은 `actions/dashboard.php` → `helpers/dashboard.php` → `views/dashboard.php` → `views/dashboard-overview.php`에서 추적할 수 있다.

[관리자 화면 가이드](admin-ui-guide.md#대시보드), [모듈 계약 가이드](module-guide.md), [검증 기준](smoke-test.md#운영-대시보드-요약)을 함께 따른다. 브라우저 fixture에는 샘플 데이터를 사용한다. 실제 PHP 렌더링, 설치 DB, 인증 요청의 검증 여부는 별도로 기록한다.
