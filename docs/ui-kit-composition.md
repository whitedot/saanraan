# UI kit 구성과 테마·스킨 적용 기준

관련 작업: [#423](https://github.com/whitedot/saanraan/issues/423)

## 일관성과 다양성

UI kit은 버튼의 행동, 상태 의미, focus, 입력 규칙과 기본 typography를 통일한다. 화면 전체의 정보 구조까지 하나의 카드 grid로 고정하지 않는다. 네 kit의 `Compositions & States`는 같은 기본 요소를 읽기 중심, 목록 중심, 작업 중심으로 조합하는 정적 예시다.

- 콘텐츠: 대표 이야기의 제목 위계, 이미지 비율, 읽기 폭과 여백으로 강조한다. 본문과 보조 정보는 크기·배치로 구분한다.
- 커뮤니티: 제목·상태·작성자·반응을 빠르게 훑는 행 중심 목록을 우선한다. 댓글은 대화의 흐름과 깊이가 중요하다.
- 계정/관리자: 작업의 순서, 필수 입력, 결과와 다음 행동을 명확하게 보여준다. 카드가 필요 없는 짧은 목록은 구분선과 여백으로 나눈다.

변화를 줄 때는 배치 → 밀도/간격 → 제목 위계 → 이미지/구획 → 테마 장식 순서로 검토한다. 같은 의미의 버튼을 페이지마다 새로 꾸미거나 색상을 임의로 추가하지 않는다. 새로운 variant는 실제 반복 수요와 이름 붙일 수 있는 의미가 있을 때만 kit에 추가한다.

## 소유권과 적용 순서

| 책임 | 소유자 |
| --- | --- |
| header/footer, shell 배치와 layout.js | 선택된 `layout_key`의 제공자 |
| reset, kit, body module.css, module.js | 현재 화면의 소유 모듈 |
| 목록/상세/폼의 구성과 스킨 CSS | 해당 기능의 theme/skin |
| reaction, profile 등 외부 기능의 마크업·상태·asset | 기능 제공자의 공개 계약 |

`content`/`community` helper는 **소유 모듈 reset → common → 선택 shell → module → 명시적으로 전달한 screen/skin asset → 선택 theme.css** 순서를 유지한다. shell을 삽입할 때는 `consumer_domain`의 module asset을 기준으로 한다. 먼저 전달된 reaction 등 외부 module.css를 현재 화면의 본문으로 오인하지 않는다. 중복 asset은 한 번만 출력한다.

`common.basic`의 root layout.css와 layout script는 common layout이 로드한다. utility 화면은 기존 style profile과 site layout 상속을 유지한다. 소유 module asset이 없는 경우에는 기존 append 동작을 유지한다. admin kit은 관리자 shell에서 독립적으로 로드한다. `content.none`의 embedded/no-layout 경로는 별도 계약을 유지한다.

다른 모듈의 layout을 선택해도 본문 kit과 skin의 소유권이 바뀌지 않는다. 선택 theme의 asset이 없을 때는 제공자별 basic fallback을 적용한다. shell 선택은 다른 모듈의 body stylesheet를 함께 로드하라는 뜻이 아니다. 새 theme/skin은 하드코딩된 basic view 접근, 선언 asset, 지원 target, fallback을 함께 검토한다.

## 공통 표현 사용

- 텍스트 행동: `btn btn-text btn-ghost-default`와 의미에 맞는 기존 ghost 색상을 조합한다. 댓글의 작은 행동에 사용한다. 주요 제출 행동이나 모든 모바일 버튼을 텍스트 버튼으로 축소하지 않는다. focus/disabled 동작은 기존 btn을 따른다.
- 필드: `ui-field` 안에 연결된 `label[for]`와 control, `ui-field-help`를 배치한다. 설명/오류는 `aria-describedby`로 연결하고 오류 시 `aria-invalid="true"`를 사용한다. 필수 조건과 검증의 정본은 서버다.
- 빈 상태: `ui-empty-state`에 현재 상태, 짧은 설명, 가능한 다음 행동을 둔다. 실제로 실행할 수 없는 생성/관리 행동을 권한 없는 사용자에게 보여주지 않는다.
- 처리 중: 유효한 제출 이후에만 기존 버튼을 `disabled`와 `aria-busy="true"`로 표시하고 동작을 설명하는 label을 사용한다. 취소/실패 시 복원과 서버 측 중복 처리 정책은 해당 기능이 소유한다. 예시는 정적이며 자동 submit 차단기를 추가하지 않는다.
- 토스트: 기존 `alert` 표면을 재사용하며 위치·수명·PRG flash는 해당 요청 흐름에서 정한다. 성공 메시지를 필드 오류 대신 사용하지 않는다.
- 페이지 이동: 기존 `sr_public_pagination_html()`에 소유 모듈이 계산한 page/URL/anchor와 btn 표현을 넘긴다. kit 예제는 정적 마크업이며 데이터·권한·URL 정책을 소유하지 않는다.

추가 후보인 filter chip, 검색 도구 모음, 첨부 상태, skeleton은 실제 사용처에서 반복되는 필요가 확인될 때 도입한다. 이미 있는 details, alert, pagination을 이름만 바꾼 신규 컴포넌트로 중복 구현하지 않는다.

## 색상과 접근성

기본 palette, 배경, 채도, radius는 유지한다. 밝은 warning/info solid 버튼은 채움색을 바꾸지 않고 기본 foreground를 기존 `--color-slate-950`로 조정한다. info의 짙은 hover/active 채움에는 기존 흰색 글자를 유지한다. reaction의 선택 상태는 kit의 success foreground를 따른다. 상태 badge는 dark에서 동일 상태색에 소량의 흰색을 섞어 foreground만 보정한다. 이 변경을 모든 variant의 WCAG 충족으로 해석하지 않는다. outline/soft/badge의 남은 저대비 조합은 개별 사용처·크기·상태를 측정한 후 같은 계열에서 제한적으로 조정한다.

모달은 열린 최상위 overlay 안에서 Tab/Shift+Tab을 순환하고 종료 시 기존 trigger로 focus를 되돌린다. static 모달에서 Escape가 뒤쪽 모달을 닫지 않는다. role dialog/alertdialog의 활성 상태에 aria-modal을 제공한다. 예제 label은 실행 후 추측해 붙이지 않고 HTML에 명시한다.

## 검증

- `php .tools/bin/check-ui-kit-layout-order.php`: content/community 소유자 × common/content/community shell × basic/누락 theme, 외부 asset 순서·중복·소유권
- `php .tools/bin/check-content-layout-selection.php`: no-layout와 기존 콘텐츠 레이아웃 흐름
- `php .tools/bin/check-skin-theme-ui.php`: theme/skin 계약
- `.tools/browser-qa/tests/ui-kit-quality.spec.js`: 실제 CSS/JS와 PHP sample을 사용하는 light/dark, 390/1280px, label, overflow, text action, 상태 색상 및 modal keyboard fixture
- 공유 helper/shell 변경 시 `php .tools/bin/check.php`와 HTTP smoke를 함께 실행한다.

fixture 검증은 로그인한 관리자 화면, 실제 회원 권한 분기, 설치된 제3자 theme/skin의 실화면 검증을 대신하지 않는다. 이러한 범위는 사용 가능한 테스트 계정과 fixture를 갖춘 환경에서 별도로 확인한다.


## 스크롤 표와 메뉴

`table-wrapper`의 가로 스크롤과 `table-card`의 모서리 처리는 유지한다. 공통 dropdown과 회원 소유 profile menu는 Popover API가 있는 브라우저에서 top layer로 표시한다. 메뉴 DOM을 body로 이동하지 않아 선택 theme의 토큰, 폼 소속, 모달 내부 focus 관계를 유지한다. viewport 경계에서 위치를 조정하고 크기를 제한한다.

표를 스크롤하여 trigger가 이동하면 열린 메뉴를 닫는다. 회원 메뉴는 Escape로 닫고 이름 trigger에 focus를 돌려주며, 바깥 클릭으로도 닫힌다. 실제 쪽지·팔로우 권한과 action은 회원 모듈의 기존 계약이 유지한다. kit의 회원 목록 메뉴는 페이지 내 예시 링크만 제공한다.

Popover API가 없는 구형 브라우저에서는 fixed 배치를 사용하며, transform/contain이 적용된 조상까지 벗어나는 보장은 없다. 현재 browser fixture는 Chromium의 Popover API를 검증한다. `.tools/browser-qa/tests/table-popover-clipping.spec.js`는 네 kit 각각의 light/dark·390/1280px, 가로 스크롤 유지, 잘림 경계 밖 메뉴의 실제 hit-test, viewport 범위, 닫힘 동작을 검사한다.
