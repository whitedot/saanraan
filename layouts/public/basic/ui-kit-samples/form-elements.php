<div class="ui-kit-sample-section" data-ui-kit-sample="form-elements">
<div class="container-fluid">
                    <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-base">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">입력 텍스트 필드 유형 (Input Textfield Type)</h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Simple Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="simpleinput" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">기본 입력 (Simple
                                                    Input)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="simpleinput" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Floating Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">플로팅 입력 (Floating Input)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="ui-kit-position-context">
                                                    <input type="text" id="floatingInput" placeholder=""
                                                        class="form-floating-control form-input" />
                                                    <label for="floatingInput"
                                                        class="form-floating-label">이름</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Validation Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="validInput" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">유효한 입력 (Valid
                                                    Input) <span class="sr-required-label">(필수)</span></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="ui-kit-position-context">
                                                    <input type="text" id="validInput" name="validation-name-success"
                                                        class="form-input form-input-valid" required=""
                                                        aria-describedby="validation-name-success-helper" />
                                                    <div
                                                        class="ui-kit-state-disabled-pointer ui-kit-position-absolute ui-kit-position-block-0 ui-kit-position-end-0 ui-kit-cluster ui-kit-align-items-center ui-kit-end-pad-3">
                                                        <?php echo sr_material_icon_html('check', 'ui-kit-ink-success', '정상'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Placeholder -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-rounded" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">둥근 입력
                                                    (Rounded Input)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-rounded" class="form-input form-input-rounded"
                                                    placeholder="둥근 입력창" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Text Area -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-textarea" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">텍스트 영역 (Text
                                                    area)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <textarea id="example-textarea" rows="5" class="form-textarea"></textarea>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Disabled -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-disable" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">비활성화
                                                    (Disabled)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-disable" value="비활성화된 값" disabled
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Helping Text -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-helping" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">도움말 텍스트
                                                    (Helping text)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-helping" placeholder="도움말 텍스트"
                                                    class="form-input" />
                                                <small class="ui-kit-hint">새 줄로 나뉘며 한 줄 이상 확장될 수
                                                    있는 도움말 텍스트 블록입니다.</small>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Default select -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="discount" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">아이콘이 있는 선택 (Select
                                                    with Icon)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-icon-group">
                                                    <?php echo sr_material_icon_html('discount', 'input-icon'); ?>
                                                    <select id="discount" class="form-select">
                                                        <option selected>할인 선택</option>
                                                        <option>할인 없음</option>
                                                        <option>정액 할인</option>
                                                        <option>백분율 할인</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- with Label Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">라벨 입력 (Label Input)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div>
                                                    <label for="labelInputInput1" class="form-label">라벨 입력</label>
                                                    <input type="email" class="form-input" id="labelInputInput1"
                                                        placeholder="name@example.com" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Search Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="SearchInput" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">검색 스타일 (Search
                                                    Style)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-icon-group">
                                                    <?php echo sr_material_icon_html('search', 'input-icon'); ?>
                                                    <input type="search" id="SearchInput" placeholder="검색어 입력..."
                                                        class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Invalidation Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="inValidationInput" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">잘못된 입력
                                                    (Invalid Input) <span class="sr-required-label">(필수)</span></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-icon-group">
                                                    <input type="text" id="inValidationInput"
                                                        name="validation-name-success"
                                                        class="form-input form-input-invalid" required=""
                                                        aria-describedby="validation-name-success-helper" />
                                                    <?php echo sr_material_icon_html('info', 'input-icon ui-kit-ink-danger ui-kit-type-base', '오류'); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Placeholder -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-placeholder" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">플레이스홀더
                                                    (Placeholder)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-placeholder" class="form-input"
                                                    placeholder="플레이스홀더" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Readonly -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-readonly" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">읽기 전용
                                                    (Readonly)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-readonly" value="읽기 전용 값" readonly
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Static Control -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-static" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">정적 컨트롤 (Static
                                                    control)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-static" value="email@example.com"
                                                    readonly class="form-input form-input-plain" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Default select -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">기본 선택 (Default Select)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <select class="form-select">
                                                    <option selected>이 선택 메뉴를 여세요</option>
                                                    <option>하나</option>
                                                    <option>둘</option>
                                                    <option>셋</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Checkbox List -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <span class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">체크박스 목록
                                                    (Checkbox List)</span>
                                            </div>

                                            <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-x-4 ui-kit-column-lg-2">
                                                <label class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2" for="example-check-list-1">
                                                    <input id="example-check-list-1" type="checkbox" name="example_check_list[]" value="1" class="form-checkbox" checked />
                                                    1
                                                </label>
                                                <label class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2" for="example-check-list-2">
                                                    <input id="example-check-list-2" type="checkbox" name="example_check_list[]" value="2" class="form-checkbox" />
                                                    2
                                                </label>
                                                <label class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2" for="example-check-list-3">
                                                    <input id="example-check-list-3" type="checkbox" name="example_check_list[]" value="3" class="form-checkbox" checked />
                                                    3
                                                </label>
                                                <label class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2" for="example-check-list-4">
                                                    <input id="example-check-list-4" type="checkbox" name="example_check_list[]" value="4" class="form-checkbox" />
                                                    4
                                                </label>
                                                <label class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2" for="example-check-list-5">
                                                    <input id="example-check-list-5" type="checkbox" name="example_check_list[]" value="5" class="form-checkbox" />
                                                    5
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">입력 유형 (Input Types)</h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Email Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-email" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">이메일
                                                    (Email)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="email" id="example-email" placeholder="이메일"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Show/Hide Password -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="password" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">비밀번호 표시/숨기기
                                                    (Show/Hide Password)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="ui-kit-position-context ui-kit-cluster ui-kit-align-items-center">
                                                    <input id="password" type="password" class="form-input form-control-icon-end"
                                                        placeholder="비밀번호 입력" />
                                                    <button type="button"
                                                        data-toggle-password='{"target":"#password"}'
                                                        data-toggle-password-show-label="비밀번호 표시"
                                                        data-toggle-password-hide-label="비밀번호 숨기기"
                                                        aria-label="비밀번호 표시"
                                                        aria-pressed="false"
                                                        class="ui-kit-position-absolute ui-kit-position-end-3 ui-kit-position-top-half ui-kit-inline-cluster ui-kit-icon-size-6 ui-kit-center-y ui-kit-align-items-center ui-kit-distribute-center ui-kit-ink-default-500 ui-kit-transition-colors ui-kit-hover-ink-default-700 ui-kit-focus-plain">
                                                        <?php echo sr_material_icon_html('visibility', 'password-active-hide'); ?>
                                                        <?php echo sr_material_icon_html('visibility_off', 'password-active-show'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Time -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-time" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">시간
                                                    (Time)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="time" id="example-time" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Number -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-number" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">숫자
                                                    (Number)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input id="example-number" type="number" name="number"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Range -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-range" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">범위
                                                    (Range)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="range" class="form-range" id="example-range" min="0"
                                                    max="100" />
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Password -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-password" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">비밀번호
                                                    (Password)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="password" id="example-password" value="password"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Month -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-month" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">월
                                                    (Month)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="month" id="example-month" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Week -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-week" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">주 (Week)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input id="example-week" type="week" name="week" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Color -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-color" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">색상
                                                    (Color)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="color" id="example-color" value="#2563eb"
                                                    class="form-input form-input-color" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">입력 그룹 (Input Group)</h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Basic Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">사용자 이름 (Username)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">@</span>
                                                    <input type="text" placeholder="사용자 이름" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Currency Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">금액 (Amount)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-input" />
                                                    <span class="input-group-text">.00</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Textarea with Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">텍스트 영역 (Textarea)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">텍스트 영역 포함</span>
                                                    <textarea rows="2" class="form-textarea"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Flex-nowrap Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="ui-kit-space-before-2 ui-kit-block-flow ui-kit-weight-semibold">줄 바꿈 (Wrapping)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">@</span>
                                                    <input type="text" placeholder="사용자 이름" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Input group with text input and button -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">입력 + 버튼 (Input + Button)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <input type="text" placeholder="수신자 이름" class="form-input" />
                                                    <button type="button"
                                                        class="btn btn-solid-dark">버튼</button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Multiple Files  -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="formFileMultiple01" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">다중 파일
                                                    (Multiple Files)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="file" name="file-input" id="formFileMultiple01"
                                                    class="form-input" multiple />
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Email-like Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">수신자 (Recipient)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <input type="text" placeholder="수신자 이름" class="form-input" />
                                                    <span class="input-group-text">@example.com</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Multi-field Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">이메일 로그인 (Email Login)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <input type="text" placeholder="사용자 이름" class="form-input" />
                                                    <span class="input-group-text">@</span>
                                                    <input type="text" placeholder="서버" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Vanity URL Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">맞춤 URL (Vanity URL)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span
                                                        class="input-group-text ui-kit-text-nowrap">https://example.com/users/</span>
                                                    <input type="text" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Input group with dropdown and text input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="ui-kit-space-before-2 ui-kit-block-flow ui-kit-weight-semibold">드롭다운 + 입력 (Dropdown +
                                                    Input)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="dropdown-toggle btn btn-group-start btn-solid-primary"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown">
                                                            드롭다운 <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                                        </button>

                                                        <div class="dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div class="ui-kit-stack-0-5">
                                                                <a class="dropdown-item" href="#!">작업</a>

                                                                <a class="dropdown-item active" href="#!">다른 작업</a>

                                                                <a class="dropdown-item" href="#!">기타 사항</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <input type="text" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- File input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="inputGroupFile04" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">파일 입력 (File
                                                    Input)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="file" name="file-input" id="inputGroupFile04"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">입력 그룹 선택 (Input Group
                                                    Select)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">옵션</span>
                                                    <select class="form-select form-control-group-end">
                                                        <option selected>선택...</option>
                                                        <option>하나</option>
                                                        <option>둘</option>
                                                        <option>셋</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">플로팅 라벨 (Floating Labels)</h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Floating Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">이메일 주소</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <!-- Floating Input -->
                                                <div class="ui-kit-position-context">
                                                    <input type="email" id="floating-input-email"
                                                        class="form-floating-control form-input"
                                                        placeholder="you@email.com" />
                                                    <label for="floating-input-email"
                                                        class="form-floating-label">이메일</label>
                                                </div>
                                                <!-- End Floating Input -->
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Floating Textarea -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">의견 (Comments)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="ui-kit-position-context">
                                                    <textarea id="floatingTextarea" rows="4" placeholder=""
                                                        class="form-floating-control form-textarea"></textarea>
                                                    <label for="floatingTextarea"
                                                        class="form-floating-label">의견</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Floating Password -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">비밀번호</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="ui-kit-position-context">
                                                    <input type="password" id="floatingPassword" placeholder=""
                                                        class="form-floating-control form-input" />
                                                    <label for="floatingPassword"
                                                        class="form-floating-label">비밀번호</label>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">입력 크기 (Input Sizes)</h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Small Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="input-small" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">작게
                                                    (Small)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="input-small" placeholder=".input-sm"
                                                    class="form-input form-input-sm" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Large Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="input-large" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">크게
                                                    (Large)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="input-large" placeholder=".input-lg"
                                                    class="form-input form-input-lg" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Large Select -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">큰 선택 메뉴</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <select class="form-select form-select-lg">
                                                    <option selected>이 선택 메뉴를 여세요</option>
                                                    <option value="1">하나</option>
                                                    <option value="2">둘</option>
                                                    <option value="3">셋</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Normal Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="input-normal" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">보통
                                                    (Normal)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="input-normal" placeholder="Normal"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Grid Size Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="input-gridsize" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">그리드 크기 (Grid
                                                    Sizes)</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-2 ui-kit-grid-lg-3">
                                                    <div>
                                                        <input type="text" id="input-gridsize" placeholder="col-span-4"
                                                            class="form-input" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Small Select -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">작은 선택 메뉴</label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <select class="form-select form-select-sm">
                                                    <option selected>이 선택 메뉴를 여세요</option>
                                                    <option value="1">하나</option>
                                                    <option value="2">둘</option>
                                                    <option value="3">셋</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">체크박스, 라디오 및 스위치 (Checks, Radios and Switches)</h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Default Checkboxes -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">체크박스 (Checkboxes)</label>
                                            </div>

                                            <div class="ui-kit-stack-3 ui-kit-column-lg-2">
                                                <!-- Default Checkbox -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkDefault" class="form-checkbox" />
                                                    <label for="checkDefault">기본 체크박스</label>
                                                </div>

                                                <!-- Light Checkbox -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkLight"
                                                        class="form-checkbox form-choice-muted form-choice-primary" />
                                                    <label for="checkLight">연한 체크박스</label>
                                                </div>

                                                <!-- Inline Checkboxes -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-x-4">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkInline1" class="form-checkbox"
                                                            checked />
                                                        <label for="checkInline1">인라인 1</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkInline2"
                                                            class="form-checkbox" />
                                                        <label for="checkInline2">인라인 2</label>
                                                    </div>
                                                </div>

                                                <!-- Disabled/Indeterminate -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkIndeterminate"
                                                        class="form-checkbox" />
                                                    <label for="checkIndeterminate">비활성화된 중간 상태 체크박스</label>
                                                </div>

                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkCheckedDisabled"
                                                        class="form-checkbox" checked disabled />
                                                    <label for="checkCheckedDisabled">비활성화된 체크 상태 체크박스</label>
                                                </div>

                                                <!-- Sizes -->
                                                <h5 class="ui-kit-space-before-base ui-kit-space-after-2 ui-kit-weight-semibold">크기</h5>

                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkSize1" class="form-checkbox form-choice-md"
                                                        checked />
                                                    <label for="checkSize1">16px 체크박스</label>
                                                </div>

                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkSize2"
                                                        class="form-checkbox form-choice-secondary form-choice-lg"
                                                        checked />
                                                    <label for="checkSize2">20px 체크박스</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Switches -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">스위치 (Switches)</label>
                                            </div>

                                            <div class="ui-kit-stack-3 ui-kit-column-lg-2">
                                                <!-- Enabled Switch -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="switch1" class="form-switch" checked />
                                                    <label for="switch1">활성화된 스위치</label>
                                                </div>

                                                <!-- Disabled Switch -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="switch2" class="form-switch" disabled />
                                                    <label for="switch2" class="ui-kit-ink-default-400">비활성화된 스위치</label>
                                                </div>

                                                <!-- Sizes -->
                                                <h5 class="ui-kit-space-before-base ui-kit-space-after-2 ui-kit-weight-semibold">크기</h5>

                                                <!-- 16px Switch -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkboxSize16" class="form-switch"
                                                        checked />
                                                    <label for="checkboxSize16">16px 스위치</label>
                                                </div>

                                                <!-- 20px Switch -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkboxSize20"
                                                        class="form-switch form-switch-lg form-choice-secondary"
                                                        checked />
                                                    <label for="checkboxSize20">20px 스위치</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Colored Checkboxes -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">색상이 있는 체크박스</label>
                                            </div>

                                            <div class="ui-kit-column-1 ui-kit-cluster ui-kit-wrap ui-kit-gap-9 ui-kit-column-lg-2">
                                                <div class="ui-kit-stack-3">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkPrimary"
                                                            class="form-checkbox form-choice-primary" checked />
                                                        <label for="checkPrimary">Primary</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkSecondary"
                                                            class="form-checkbox form-choice-secondary" checked />
                                                        <label for="checkSecondary">Secondary</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkSuccess"
                                                            class="form-checkbox form-choice-success" checked />
                                                        <label for="checkSuccess">Success</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkInfo"
                                                            class="form-checkbox form-choice-info" checked />
                                                        <label for="checkInfo">Info</label>
                                                    </div>
                                                </div>

                                                <div class="ui-kit-stack-3">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkWarning"
                                                            class="form-checkbox form-choice-warning" checked />
                                                        <label for="checkWarning">Warning</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkDanger"
                                                            class="form-checkbox form-choice-danger" checked />
                                                        <label for="checkDanger">Danger</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkDark"
                                                            class="form-checkbox form-choice-dark" checked />
                                                        <label for="checkDark">Dark</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Colored Checkboxes -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">색상이 있는 스위치</label>
                                            </div>

                                            <div class="ui-kit-column-1 ui-kit-cluster ui-kit-wrap ui-kit-gap-9 ui-kit-column-lg-2">
                                                <div class="ui-kit-stack-3">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="switchPrimary"
                                                            class="form-switch form-choice-primary" checked />
                                                        <label for="switchPrimary">Primary</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="switchSecondary"
                                                            class="form-switch form-choice-secondary" checked />
                                                        <label for="switchSecondary">Secondary</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="switchSuccess"
                                                            class="form-switch form-choice-success" checked />
                                                        <label for="switchSuccess">Success</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="switchInfo"
                                                            class="form-switch form-choice-info" checked />
                                                        <label for="switchInfo">Info</label>
                                                    </div>
                                                </div>

                                                <div class="ui-kit-stack-3">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="switchWarning"
                                                            class="form-switch form-choice-warning" checked />
                                                        <label for="switchWarning">Warning</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="switchDanger"
                                                            class="form-switch form-choice-danger" checked />
                                                        <label for="switchDanger">Danger</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="switchDark"
                                                            class="form-switch form-choice-dark" checked />
                                                        <label for="switchDark">Dark</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Default Radios -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">라디오 (Radios)</label>
                                            </div>

                                            <div class="ui-kit-stack-3 ui-kit-column-lg-2">
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="radio" name="gridRadio" id="radio1"
                                                        class="form-radio" checked />
                                                    <label for="radio1">옵션 1</label>
                                                </div>

                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="radio" name="gridRadio" id="radio2"
                                                        class="form-radio" />
                                                    <label for="radio2">옵션 2</label>
                                                </div>

                                                <!-- Inline Radios -->
                                                <div class="ui-kit-cluster ui-kit-inline-space-4">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="inlineRadioOptions" id="inlineRadio1"
                                                            value="option1" class="form-radio" checked />
                                                        <label for="inlineRadio1">인라인 1</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="inlineRadioOptions" id="inlineRadio2"
                                                            value="option2" class="form-radio" />
                                                        <label for="inlineRadio2">인라인 2</label>
                                                    </div>
                                                </div>

                                                <!-- Disabled Checked -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="radio" name="disabledRadioOptions" id="inlineRadio3"
                                                        value="option3" class="form-radio" checked
                                                        disabled />
                                                    <label for="inlineRadio3" class="ui-kit-ink-default-400">비활성화된 체크 상태
                                                        라디오</label>
                                                </div>

                                                <!-- Sizes -->
                                                <h5 class="ui-kit-space-before-5 ui-kit-space-after-2 ui-kit-weight-semibold">크기</h5>

                                                <!-- 16px Radios -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-inline-space-4">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="paymentMethod" id="radioCash"
                                                            value="cash" class="form-radio form-choice-md"
                                                            checked />
                                                        <label for="radioCash">현금</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="paymentMethod" id="radioCard"
                                                            value="card" class="form-radio form-choice-md" />
                                                        <label for="radioCard">카드</label>
                                                    </div>
                                                </div>

                                                <!-- 20px Radios -->
                                                <div class="ui-kit-space-before-2 ui-kit-cluster ui-kit-align-items-center ui-kit-inline-space-4">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="deliveryOption" id="radioPickup"
                                                            value="pickup" class="form-radio form-choice-lg"
                                                            checked />
                                                        <label for="radioPickup">픽업</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="deliveryOption" id="radioHome"
                                                            value="home" class="form-radio form-choice-lg" />
                                                        <label for="radioHome">택배 배송</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Reverse -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">반대 방향 (Reverse)</label>
                                            </div>

                                            <div class="ui-kit-column-1 ui-kit-fill-width ui-kit-stack-3 ui-kit-column-lg-2 ui-kit-width-lg-half">
                                                <!-- Reverse Checkbox -->
                                                <div class="ui-kit-cluster ui-kit-cluster-reverse ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="reverseCheck1" class="form-checkbox"
                                                        checked />
                                                    <label for="reverseCheck1">반대 방향 체크박스</label>
                                                </div>

                                                <!-- Reverse Radio -->
                                                <div class="ui-kit-cluster ui-kit-cluster-reverse ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="radio" id="reverseCheck2" name="reverseRadio"
                                                        class="form-radio" disabled />
                                                    <label for="reverseCheck2">비활성화된 반대 방향 라디오</label>
                                                </div>

                                                <!-- Reverse Switch -->
                                                <div class="ui-kit-cluster ui-kit-cluster-reverse ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="switchCheckReverse" class="form-switch"
                                                        checked />
                                                    <label for="switchCheckReverse">반대 방향 스위치 체크박스 입력</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Colored Radios -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">색상이 있는 라디오</label>
                                            </div>

                                            <div class="ui-kit-column-1 ui-kit-cluster ui-kit-wrap ui-kit-gap-9 ui-kit-column-lg-2">
                                                <div class="ui-kit-stack-3">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="coloredRadio" id="radioPrimary"
                                                            class="form-radio form-choice-primary"
                                                            checked />
                                                        <label for="radioPrimary">Primary</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="coloredRadio" id="radioSecondary"
                                                            class="form-radio form-choice-secondary" />
                                                        <label for="radioSecondary">Secondary</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="coloredRadio" id="radioSuccess"
                                                            class="form-radio form-choice-success" />
                                                        <label for="radioSuccess">Success</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="coloredRadio" id="radioInfo"
                                                            class="form-radio form-choice-info" />
                                                        <label for="radioInfo">Info</label>
                                                    </div>
                                                </div>

                                                <div class="ui-kit-stack-3">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="coloredRadio" id="radioWarning"
                                                            class="form-radio form-choice-warning" />
                                                        <label for="radioWarning">Warning</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="coloredRadio" id="radioDanger"
                                                            class="form-radio form-choice-danger" />
                                                        <label for="radioDanger">Danger</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="coloredRadio" id="radioDark"
                                                            class="form-radio form-choice-dark" />
                                                        <label for="radioDark">Dark</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Toggle Checkboxes -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">체크박스 토글 (Checkbox Toggle)</label>
                                            </div>

                                            <div class="ui-kit-stack-3 ui-kit-column-lg-2">
                                                <!-- Single Toggle -->
                                                <div>
                                                    <input type="checkbox" id="toggleSingle" class="form-choice-toggle-input ui-kit-state-hidden" />
                                                    <label for="toggleSingle"
                                                        class="btn btn-choice-primary">단일
                                                        토글</label>
                                                </div>

                                                <!-- Group Toggle -->
                                                <div class="ui-kit-cluster">
                                                    <div>
                                                        <input type="checkbox" id="toggle1" class="form-choice-toggle-input ui-kit-state-hidden" />
                                                        <label for="toggle1"
                                                            class="btn btn-choice-primary btn-group-start">하나</label>
                                                    </div>
                                                    <div>
                                                        <input type="checkbox" id="toggle2" class="form-choice-toggle-input ui-kit-state-hidden" />
                                                        <label for="toggle2"
                                                            class="btn btn-choice-primary btn-group-middle">둘</label>
                                                    </div>
                                                    <div>
                                                        <input type="checkbox" id="toggle3" class="form-choice-toggle-input ui-kit-state-hidden" />
                                                        <label for="toggle3"
                                                            class="btn btn-choice-primary btn-group-end">셋</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-kit-line-default-300 ui-kit-block-space-base ui-kit-divider-top ui-kit-line-dashed"></div>

                                        <!-- Toggle Radios -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">라디오 토글 (Radio Toggle)</label>
                                            </div>

                                            <div class="ui-kit-column-1 ui-kit-cluster ui-kit-column-lg-2">
                                                <div>
                                                    <input type="radio" name="radiotoggle" id="radioLeft"
                                                        class="form-choice-toggle-input ui-kit-state-hidden" checked />
                                                    <label for="radioLeft"
                                                        class="btn btn-choice-secondary btn-group-start">왼쪽</label>
                                                </div>

                                                <div>
                                                    <input type="radio" name="radiotoggle" id="radioMiddle"
                                                        class="form-choice-toggle-input ui-kit-state-hidden" />
                                                    <label for="radioMiddle"
                                                        class="btn btn-choice-secondary btn-group-middle">가운데</label>
                                                </div>

                                                <div>
                                                    <input type="radio" name="radiotoggle" id="radioRight"
                                                        class="form-choice-toggle-input ui-kit-state-hidden" />
                                                    <label for="radioRight"
                                                        class="btn btn-choice-secondary btn-group-end">오른쪽</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
</div>
</div>
