# github-markdown-css reference

Markdown Editor의 기본 stylesheet는 아래 프로젝트의 요소 구성과 기본 밀도를 참고해 산란 런타임에 맞게 재구성한다.

- package: `github-markdown-css`
- version: `5.9.0`
- source: `https://github.com/sindresorhus/github-markdown-css`
- license: `MIT`
- local stylesheet: `assets/github-markdown.css`
- local license: `LICENSE.github-markdown-css`

로컬 stylesheet는 원본 파일을 그대로 public asset으로 호출하지 않는다. 원본의 `.markdown-body` 범위를 `.markdown-editor-body`로 바꾸고 light/dark 고정 색상을 `--sr-*` 테마 토큰으로 재구성한다. 시각 편집기와 연결되는 실제 property 선언 앞에는 `sr-control` 주석을 두며, 운영자가 저장하는 최종 stylesheet 자체가 설정의 정본이다. 외부 URL을 포함하는 아이콘 표현과 산란 renderer가 만들지 않는 GitHub 전용 동작은 기본값에서 제외할 수 있다.

기준 버전을 갱신할 때는 원본 selector와 상태 변경을 대조한 뒤 다음 항목을 함께 확인한다.

- `assets/github-markdown.css`의 출처 주석과 이 문서의 버전
- `LICENSE.github-markdown-css`
- `.markdown-editor-body` selector 범위
- `--sr-*` 기반 light/dark 색상
- 관리자 라이브 미리보기의 모든 선택 가능 요소에 공통 Margin/Padding/Border 사방향 컨트롤과 실제 property의 `sr-control` 매핑
- 텍스트가 있는 모든 선택 가능 요소에 공통 font/spacing/alignment/decoration/color 컨트롤
- 번들 원본을 출력하는 `default`와 사용자 stylesheet를 출력하는 `custom` 모드
- `php .tools/bin/check-markdown-editor-runtime.php`
