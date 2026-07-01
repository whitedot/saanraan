# #374-a 문자열 선언 계약 기록 - 2026-07-02

## 결정

#374는 두 단계로 나눈다. #374-a는 새 코드와 변경 코드가 사용자 노출 문자열을 추가할 때 따를 문자열 선언 계약을 고정하는 작은 gate이고, #374-b는 실제 두 번째 locale 번역과 기존 하드코딩 문구 전수 이관이다.

#374-a의 현재 기준은 다음과 같다.

- 번역 key는 기존 `module::dot.hierarchy` 규약을 사용한다. core key는 `module::` 없이 `dot.hierarchy`만 사용한다.
- `sr_t()`에 정적 문자열 key를 넘기는 신규/변경 코드는 같은 작업에서 fallback locale인 `ko` 번역 파일에 key를 선언한다.
- 정적 key 선언 여부와 key 형식은 `.tools/bin/check-i18n-contract.php`가 검사하고, 이 검사는 `php .tools/bin/check.php` 통합 gate에 포함된다.
- 요청 locale에 key가 없어 fallback locale로 내려간 호출은 `sr_translation_fallback_events()`에 기록한다.
- JavaScript 메시지는 새 JS 파일 한국어 리터럴을 늘리기보다 서버 렌더링 markup의 `data-*` attribute나 페이지별 JSON dictionary로 전달한다. 공통 주입 helper는 첫 실제 소비 이슈에서 구현한다.

## 비범위

- 기존 하드코딩 한국어 문구 전수 이관은 #374-a 범위가 아니다.
- 두 번째 locale 번역 내용 작성과 locale별 smoke target 구성은 #374-b 또는 실제 운영 수요가 확인된 하위 이슈에서 처리한다.
- 사용자 콘텐츠 다국어화는 UI 번역과 별도 설계 이슈로 남긴다.

## 검증

- `php .tools/bin/check-i18n-contract.php`

## 관련 문서

- `docs/module-guide.md` 9-4. 번역 파일
- `docs/verification-status.md`
- GitHub issue #374
- GitHub issue #395
