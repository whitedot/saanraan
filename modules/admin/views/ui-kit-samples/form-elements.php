<div class="ui-kit-sample-section" data-ui-kit-sample="form-elements">
<div class="container-fluid">
                    <div class="ui-grid ui-grid-cols-1 ui-gap-base">
                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">관리자 모드 폼 기준 (Admin Form Baseline)</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <form action="#" method="get" class="admin-form ui-form-theme">
                                    <section class="admin-card card">
                                        <h2>관리자 폼 기준</h2>
                                        <div class="admin-form-row">
                                            <div class="admin-form-label"><span class="form-label">사이트 이름</span></div>
                                            <div class="admin-form-field">
                                                <input type="text" name="site_name" value="saanraan">
                                                <span class="admin-form-help">관리자 화면에서 class 없는 기본 input이 받는 스타일입니다.</span>
                                            </div>
                                        </div>
                                        <div class="admin-form-row">
                                            <div class="admin-form-label"><span class="form-label">운영 상태</span></div>
                                            <div class="admin-form-field">
                                                <select name="site_status">
                                                    <option selected>운영</option>
                                                    <option>점검</option>
                                                    <option>비공개</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="admin-form-row">
                                            <div class="admin-form-label"><span class="form-label">관리 메모</span></div>
                                            <div class="admin-form-field">
                                                <textarea name="memo" rows="4">현재 관리자 화면과 같은 폼 컨텍스트에서 표시됩니다.</textarea>
                                            </div>
                                        </div>
                                        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
                                            <button type="button" class="btn btn-primary">저장</button>
                                            <button type="button" class="btn btn-light">취소</button>
                                        </div>
                                    </section>
                                </form>
                            </div>
                        </section>

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">입력 텍스트 필드 유형 (Input Textfield Type)</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="ui-grid ui-grid-cols-1 ui-grid-cols-lg-2 ui-gap-base">
                                    <div>
                                        <!-- Simple Input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="simpleinput" class="form-label ui-py-2 ui-mb-0">기본 입력 (Simple
                                                    Input)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="text" id="simpleinput" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Floating Input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">플로팅 입력 (Floating Input)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="ui-relative">
                                                    <input type="text" id="floatingInput" placeholder=""
                                                        class="ui-floating-control form-input" />
                                                    <label for="floatingInput"
                                                        class="ui-text-default-500 ui-floating-label ui-absolute ui-start-3 ui-top-2 ui-transition-all">이름</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Validation Input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="validInput" class="form-label ui-py-2 ui-mb-0">유효한 입력 (Valid
                                                    Input)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="ui-relative">
                                                    <input type="text" id="validInput" name="hs-validation-name-success"
                                                        class="form-input ui-border-success" required=""
                                                        aria-describedby="hs-validation-name-success-helper" />
                                                    <div
                                                        class="ui-pointer-none ui-absolute ui-inset-y-0 ui-end-0 ui-flex ui-items-center ui-pe-3">
                                                        <i data-icon="tabler:check" class="iconify tabler--check ui-text-success ui-text-base"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Placeholder -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-rounded" class="form-label ui-py-2 ui-mb-0">둥근 입력
                                                    (Rounded Input)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="text" id="example-rounded" class="form-input ui-rounded-full"
                                                    placeholder="둥근 입력창" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Text Area -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-textarea" class="form-label ui-py-2 ui-mb-0">텍스트 영역 (Text
                                                    area)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <textarea id="example-textarea" rows="5" class="form-textarea"></textarea>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Disabled -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-disable" class="form-label ui-py-2 ui-mb-0">비활성화
                                                    (Disabled)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="text" id="example-disable" value="비활성화된 값" disabled
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Helping Text -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-helping" class="form-label ui-py-2 ui-mb-0">도움말 텍스트
                                                    (Helping text)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="text" id="example-helping" placeholder="도움말 텍스트"
                                                    class="form-input" />
                                                <small class="ui-hint-text">새 줄로 나뉘며 한 줄 이상 확장될 수
                                                    있는 도움말 텍스트 블록입니다.</small>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Default select -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="discount" class="form-label ui-py-2 ui-mb-0">아이콘이 있는 선택 (Select
                                                    with Icon)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-icon-group">
                                                    <i data-icon="tabler:discount" class="iconify tabler--discount input-icon"></i>
                                                    <select id="discount" class="form-input">
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
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">라벨 입력 (Label Input)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div>
                                                    <label for="labelInputInput1" class="form-label">라벨 입력</label>
                                                    <input type="email" class="form-input" id="labelInputInput1"
                                                        placeholder="name@example.com" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Search Input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="SearchInput" class="form-label ui-py-2 ui-mb-0">검색 스타일 (Search
                                                    Style)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-icon-group">
                                                    <i data-icon="tabler:search" class="iconify tabler--search input-icon"></i>
                                                    <input type="search" id="SearchInput" placeholder="검색어 입력..."
                                                        class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Invalidation Input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="inValidationInput" class="form-label ui-py-2 ui-mb-0">잘못된 입력
                                                    (Invalid Input)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-icon-group">
                                                    <input type="text" id="inValidationInput"
                                                        name="hs-validation-name-success"
                                                        class="form-input ui-border-danger" required=""
                                                        aria-describedby="hs-validation-name-success-helper" />
                                                    <i
                                                        class="iconify tabler--info-circle ui-text-danger input-icon ui-text-base"></i>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Placeholder -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-placeholder" class="form-label ui-py-2 ui-mb-0">플레이스홀더
                                                    (Placeholder)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="text" id="example-placeholder" class="form-input"
                                                    placeholder="플레이스홀더" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Readonly -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-readonly" class="form-label ui-py-2 ui-mb-0">읽기 전용
                                                    (Readonly)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="text" id="example-readonly" value="읽기 전용 값" readonly
                                                    class="form-input ui-cursor-not-allowed" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Static Control -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-static" class="form-label ui-py-2 ui-mb-0">정적 컨트롤 (Static
                                                    control)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="text" id="example-static" value="email@example.com"
                                                    readonly class="form-input ui-border-transparent ui-bg-transparent" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Default select -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">기본 선택 (Default Select)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <select class="form-select">
                                                    <option selected>이 선택 메뉴를 여세요</option>
                                                    <option>하나</option>
                                                    <option>둘</option>
                                                    <option>셋</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Multiple Select -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-multiselect" class="form-label ui-py-2 ui-mb-0">다중 선택
                                                    (Multiple Select)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <select id="example-multiselect" multiple class="form-input ui-h-auto">
                                                    <option>1</option>
                                                    <option>2</option>
                                                    <option>3</option>
                                                    <option>4</option>
                                                    <option>5</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">입력 유형 (Input Types)</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="ui-grid ui-grid-cols-1 ui-grid-cols-lg-2 ui-gap-base">
                                    <div>
                                        <!-- Email Input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-email" class="form-label ui-py-2 ui-mb-0">이메일
                                                    (Email)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="email" id="example-email" placeholder="이메일"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Show/Hide Password -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="password" class="form-label ui-py-2 ui-mb-0">비밀번호 표시/숨기기
                                                    (Show/Hide Password)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="ui-relative ui-flex ui-items-center">
                                                    <input id="password" type="password" class="form-input ui-w-full ui-pe-10"
                                                        placeholder="비밀번호 입력" />
                                                    <button type="button"
                                                        data-hs-toggle-password='{"target":"#password"}'
                                                        class="ui-absolute ui-end-3 ui-top-half ui-inline-flex ui-size-6 ui-translate-y-center ui-items-center ui-justify-center ui-text-default-500 ui-transition-colors ui-hover-text-default-700 ui-focus-outline-none">
                                                        <i data-icon="tabler:eye"
                                                            class="iconify tabler--eye ui-password-active-hide ui-block ui-text-sm"></i>
                                                        <i data-icon="tabler:eye-off"
                                                            class="iconify tabler--eye-off ui-password-active-show ui-hidden ui-text-sm"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Time -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-time" class="form-label ui-py-2 ui-mb-0">시간
                                                    (Time)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="time" id="example-time" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Number -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-number" class="form-label ui-py-2 ui-mb-0">숫자
                                                    (Number)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input id="example-number" type="number" name="number"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Range -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-range" class="form-label ui-py-2 ui-mb-0">범위
                                                    (Range)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="range" class="form-range" id="example-range" min="0"
                                                    max="100" />
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Password -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-password" class="form-label ui-py-2 ui-mb-0">비밀번호
                                                    (Password)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="password" id="example-password" value="password"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Month -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-month" class="form-label ui-py-2 ui-mb-0">월
                                                    (Month)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="month" id="example-month" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Week -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-week" class="form-label ui-py-2 ui-mb-0">주 (Week)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input id="example-week" type="week" name="week" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Color -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="example-color" class="form-label ui-py-2 ui-mb-0">색상
                                                    (Color)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="color" id="example-color" value="#2563eb"
                                                    class="form-input ui-h-10 ui-p-1" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">입력 그룹 (Input Group)</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="ui-grid ui-grid-cols-1 ui-grid-cols-lg-2 ui-gap-base">
                                    <div>
                                        <!-- Basic Input Group -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">사용자 이름 (Username)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">@</span>
                                                    <input type="text" placeholder="사용자 이름" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Currency Input Group -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">금액 (Amount)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-input" />
                                                    <span class="input-group-text">.00</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Textarea with Input Group -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">텍스트 영역 (Textarea)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">텍스트 영역 포함</span>
                                                    <textarea rows="2" class="form-textarea"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Flex-nowrap Input Group -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="ui-mt-2 ui-block ui-font-semibold">줄 바꿈 (Wrapping)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">@</span>
                                                    <input type="text" placeholder="사용자 이름" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Input group with text input and button -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">입력 + 버튼 (Input + Button)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-group">
                                                    <input type="text" placeholder="수신자 이름" class="form-input" />
                                                    <button type="button"
                                                        class="btn btn-solid-dark">버튼</button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Multiple Files  -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="formFileMultiple01" class="form-label ui-py-2 ui-mb-0">다중 파일
                                                    (Multiple Files)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="file" name="file-input" id="formFileMultiple01"
                                                    class="form-input" multiple />
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Email-like Input Group -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">수신자 (Recipient)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-group">
                                                    <input type="text" placeholder="수신자 이름" class="form-input" />
                                                    <span class="input-group-text">@example.com</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Multi-field Input Group -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">이메일 로그인 (Email Login)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-group">
                                                    <input type="text" placeholder="사용자 이름" class="form-input" />
                                                    <span class="input-group-text">@</span>
                                                    <input type="text" placeholder="서버" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Vanity URL Input Group -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">맞춤 URL (Vanity URL)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-group">
                                                    <span
                                                        class="input-group-text ui-text-nowrap">https://example.com/users/</span>
                                                    <input type="text" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Input group with dropdown and text input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="ui-mt-2 ui-block ui-font-semibold">드롭다운 + 입력 (Dropdown +
                                                    Input)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-group">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle btn ui-rounded-e-none btn-solid-primary"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown">
                                                            드롭다운 <i
                                                                class="iconify tabler--chevron-down ui-text-base ui-text-white"></i>
                                                        </button>

                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div class="ui-stack-0-5">
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

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- File input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="inputGroupFile04" class="form-label ui-py-2 ui-mb-0">파일 입력 (File
                                                    Input)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="file" name="file-input" id="inputGroupFile04"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">입력 그룹 선택 (Input Group
                                                    Select)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">옵션</span>
                                                    <select class="form-input ui-rounded-s-none">
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
                        </section>

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">플로팅 라벨 (Floating Labels)</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="ui-grid ui-grid-cols-1 ui-grid-cols-lg-2 ui-gap-base">
                                    <div>
                                        <!-- Floating Input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">이메일 주소</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <!-- Floating Input -->
                                                <div class="ui-relative">
                                                    <input type="email" id="hs-floating-input-email"
                                                        class="ui-floating-control form-input"
                                                        placeholder="you@email.com" />
                                                    <label for="hs-floating-input-email"
                                                        class="ui-floating-label ui-pointer-none ui-absolute ui-start-0 ui-top-0 ui-h-full ui-origin-top-left ui-truncate ui-border ui-border-transparent ui-p-4 ui-transition ui-duration-100 ui-ease-in-out ui-text-sm">이메일</label>
                                                </div>
                                                <!-- End Floating Input -->
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Floating Textarea -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">의견 (Comments)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="ui-relative">
                                                    <textarea id="floatingTextarea" rows="4" placeholder=""
                                                        class="ui-floating-control form-textarea"></textarea>
                                                    <label for="floatingTextarea"
                                                        class="ui-floating-label ui-pointer-none ui-absolute ui-start-0 ui-top-0 ui-h-full ui-origin-top-left ui-truncate ui-border ui-border-transparent ui-p-4 ui-transition ui-duration-100 ui-ease-in-out ui-text-sm">의견</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Floating Password -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">비밀번호</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="ui-relative">
                                                    <input type="password" id="floatingPassword" placeholder=""
                                                        class="ui-floating-control form-input" />
                                                    <label for="floatingPassword"
                                                        class="ui-floating-label ui-pointer-none ui-absolute ui-start-0 ui-top-0 ui-h-full ui-origin-top-left ui-truncate ui-border ui-border-transparent ui-p-4 ui-transition ui-duration-100 ui-ease-in-out ui-text-sm">비밀번호</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Floating Select -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">선택 메뉴</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="ui-relative">
                                                    <select id="floatingSelect" placeholder=""
                                                        class="ui-floating-control form-select">
                                                        <option value="" selected disabled>이 선택 메뉴를 여세요
                                                        </option>
                                                        <option value="1">하나</option>
                                                        <option value="2">둘</option>
                                                        <option value="3">셋</option>
                                                    </select>
                                                    <label for="floatingSelect"
                                                        class="ui-floating-label ui-pointer-none ui-absolute ui-start-0 ui-top-0 ui-h-full ui-origin-top-left ui-truncate ui-border ui-border-transparent ui-p-4 ui-transition ui-duration-100 ui-ease-in-out ui-text-sm">선택
                                                        가능</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">입력 크기 (Input Sizes)</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="ui-grid ui-grid-cols-1 ui-grid-cols-lg-2 ui-gap-base">
                                    <div>
                                        <!-- Small Input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="input-small" class="form-label ui-py-2 ui-mb-0">작게
                                                    (Small)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="text" id="input-small" placeholder=".input-sm"
                                                    class="form-input form-input-sm" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Large Input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="input-large" class="form-label ui-py-2 ui-mb-0">크게
                                                    (Large)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="text" id="input-large" placeholder=".input-lg"
                                                    class="form-input form-input-lg" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Large Select -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">큰 선택 메뉴</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <select class="form-input form-input-lg">
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
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="input-normal" class="form-label ui-py-2 ui-mb-0">보통
                                                    (Normal)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <input type="text" id="input-normal" placeholder="Normal"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Grid Size Input -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label for="input-gridsize" class="form-label ui-py-2 ui-mb-0">그리드 크기 (Grid
                                                    Sizes)</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <div class="ui-grid ui-grid-cols-1 ui-gap-2 ui-grid-cols-lg-3">
                                                    <div>
                                                        <input type="text" id="input-gridsize" placeholder="col-span-4"
                                                            class="form-input" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Small Select -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">작은 선택 메뉴</label>
                                            </div>

                                            <div class="ui-col-lg-2">
                                                <select class="form-input form-input-sm">
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
                        </section>

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">체크박스, 라디오 및 스위치 (Checks, Radios and Switches)</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="ui-grid ui-grid-cols-1 ui-grid-cols-lg-2 ui-gap-base">
                                    <div>
                                        <!-- Default Checkboxes -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">체크박스 (Checkboxes)</label>
                                            </div>

                                            <div class="ui-stack-3 ui-col-lg-2">
                                                <!-- Default Checkbox -->
                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="checkDefault" class="form-checkbox" />
                                                    <label for="checkDefault">기본 체크박스</label>
                                                </div>

                                                <!-- Light Checkbox -->
                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="checkLight"
                                                        class="form-checkbox ui-bg-default-200 ui-checked-bg-primary" />
                                                    <label for="checkLight">연한 체크박스</label>
                                                </div>

                                                <!-- Inline Checkboxes -->
                                                <div class="ui-flex ui-items-center ui-gap-x-4">
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkInline1" class="form-checkbox"
                                                            checked />
                                                        <label for="checkInline1">인라인 1</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkInline2"
                                                            class="form-checkbox" />
                                                        <label for="checkInline2">인라인 2</label>
                                                    </div>
                                                </div>

                                                <!-- Disabled/Indeterminate -->
                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="checkIndeterminate"
                                                        class="form-checkbox" />
                                                    <label for="checkIndeterminate">비활성화된 중간 상태 체크박스</label>
                                                </div>

                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="checkCheckedDisabled"
                                                        class="form-checkbox" checked disabled />
                                                    <label for="checkCheckedDisabled">비활성화된 체크 상태 체크박스</label>
                                                </div>

                                                <!-- Sizes -->
                                                <h5 class="ui-mt-base ui-mb-2 ui-font-semibold">크기</h5>

                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="checkSize1" class="form-checkbox ui-size-5"
                                                        checked />
                                                    <label for="checkSize1">16px 체크박스</label>
                                                </div>

                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="checkSize2"
                                                        class="form-checkbox ui-checked-bg-secondary ui-size-6-25"
                                                        checked />
                                                    <label for="checkSize2">20px 체크박스</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Switches -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">스위치 (Switches)</label>
                                            </div>

                                            <div class="ui-stack-3 ui-col-lg-2">
                                                <!-- Enabled Switch -->
                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="switch1" class="form-switch" checked />
                                                    <label for="switch1">활성화된 스위치</label>
                                                </div>

                                                <!-- Disabled Switch -->
                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="switch2" class="form-switch" disabled />
                                                    <label for="switch2" class="ui-text-default-400">비활성화된 스위치</label>
                                                </div>

                                                <!-- Sizes -->
                                                <h5 class="ui-mt-base ui-mb-2 ui-font-semibold">크기</h5>

                                                <!-- 16px Switch -->
                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="checkboxSize16" class="form-switch"
                                                        checked />
                                                    <label for="checkboxSize16">16px 스위치</label>
                                                </div>

                                                <!-- 20px Switch -->
                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="checkboxSize20"
                                                        class="form-switch form-switch-lg ui-checked-bg-secondary"
                                                        checked />
                                                    <label for="checkboxSize20">20px 스위치</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Colored Checkboxes -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">색상이 있는 체크박스</label>
                                            </div>

                                            <div class="ui-col-1 ui-flex ui-flex-wrap ui-gap-9 ui-col-lg-2">
                                                <div class="ui-stack-3">
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkPrimary"
                                                            class="form-checkbox ui-checked-bg-primary" checked />
                                                        <label for="checkPrimary">Primary</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkSecondary"
                                                            class="form-checkbox ui-checked-bg-secondary" checked />
                                                        <label for="checkSecondary">Secondary</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkSuccess"
                                                            class="form-checkbox ui-checked-bg-success" checked />
                                                        <label for="checkSuccess">Success</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkInfo"
                                                            class="form-checkbox ui-checked-bg-info" checked />
                                                        <label for="checkInfo">Info</label>
                                                    </div>
                                                </div>

                                                <div class="ui-stack-3">
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkWarning"
                                                            class="form-checkbox ui-checked-bg-warning" checked />
                                                        <label for="checkWarning">Warning</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkDanger"
                                                            class="form-checkbox ui-checked-bg-danger" checked />
                                                        <label for="checkDanger">Danger</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkDark"
                                                            class="form-checkbox ui-checked-bg-dark" checked />
                                                        <label for="checkDark">Dark</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Colored Checkboxes -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">색상이 있는 스위치</label>
                                            </div>

                                            <div class="ui-col-1 ui-flex ui-flex-wrap ui-gap-9 ui-col-lg-2">
                                                <div class="ui-stack-3">
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkPrimary"
                                                            class="form-switch ui-checked-bg-primary" checked />
                                                        <label for="checkPrimary">Primary</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkSecondary"
                                                            class="form-switch ui-checked-bg-secondary" checked />
                                                        <label for="checkSecondary">Secondary</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkSuccess"
                                                            class="form-switch ui-checked-bg-success" checked />
                                                        <label for="checkSuccess">Success</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkInfo"
                                                            class="form-switch ui-checked-bg-info" checked />
                                                        <label for="checkInfo">Info</label>
                                                    </div>
                                                </div>

                                                <div class="ui-stack-3">
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkWarning"
                                                            class="form-switch ui-checked-bg-warning" checked />
                                                        <label for="checkWarning">Warning</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkDanger"
                                                            class="form-switch ui-checked-bg-danger" checked />
                                                        <label for="checkDanger">Danger</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkDark"
                                                            class="form-switch ui-checked-bg-dark" checked />
                                                        <label for="checkDark">Dark</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Default Radios -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">라디오 (Radios)</label>
                                            </div>

                                            <div class="ui-stack-3 ui-col-lg-2">
                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="radio" name="gridRadio" id="radio1"
                                                        class="form-radio ui-rounded-full" checked />
                                                    <label for="radio1">옵션 1</label>
                                                </div>

                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="radio" name="gridRadio" id="radio2"
                                                        class="form-radio ui-rounded-full" />
                                                    <label for="radio2">옵션 2</label>
                                                </div>

                                                <!-- Inline Radios -->
                                                <div class="ui-flex ui-space-x-4">
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="radio" name="inlineRadioOptions" id="inlineRadio1"
                                                            value="option1" class="form-radio ui-rounded-full" checked />
                                                        <label for="inlineRadio1">인라인 1</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="radio" name="inlineRadioOptions" id="inlineRadio2"
                                                            value="option2" class="form-radio ui-rounded-full" />
                                                        <label for="inlineRadio2">인라인 2</label>
                                                    </div>
                                                </div>

                                                <!-- Disabled Checked -->
                                                <div class="ui-flex ui-items-center ui-gap-2">
                                                    <input type="radio" name="disabledRadioOptions" id="inlineRadio3"
                                                        value="option3" class="form-radio ui-rounded-full" checked
                                                        disabled />
                                                    <label for="inlineRadio3" class="ui-text-default-400">비활성화된 체크 상태
                                                        라디오</label>
                                                </div>

                                                <!-- Sizes -->
                                                <h5 class="ui-mt-5 ui-mb-2 ui-font-semibold">크기</h5>

                                                <!-- 16px Radios -->
                                                <div class="ui-flex ui-items-center ui-space-x-4">
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="radio" name="paymentMethod" id="radioCash"
                                                            value="cash" class="form-radio ui-rounded-full ui-size-5"
                                                            checked />
                                                        <label for="radioCash">현금</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="radio" name="paymentMethod" id="radioCard"
                                                            value="card" class="form-radio ui-rounded-full ui-size-5" />
                                                        <label for="radioCard">카드</label>
                                                    </div>
                                                </div>

                                                <!-- 20px Radios -->
                                                <div class="ui-mt-2 ui-flex ui-items-center ui-space-x-4">
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="radio" name="deliveryOption" id="radioPickup"
                                                            value="pickup" class="form-radio ui-rounded-full ui-size-6"
                                                            checked />
                                                        <label for="radioPickup">픽업</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="radio" name="deliveryOption" id="radioHome"
                                                            value="home" class="form-radio ui-rounded-full ui-size-6" />
                                                        <label for="radioHome">택배 배송</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Reverse -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">반대 방향 (Reverse)</label>
                                            </div>

                                            <div class="ui-col-1 ui-w-full ui-stack-3 ui-col-lg-2 ui-w-lg-half">
                                                <!-- Reverse Checkbox -->
                                                <div class="ui-flex ui-flex-row-reverse ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="reverseCheck1" class="form-checkbox"
                                                        checked />
                                                    <label for="reverseCheck1">반대 방향 체크박스</label>
                                                </div>

                                                <!-- Reverse Radio -->
                                                <div class="ui-flex ui-flex-row-reverse ui-items-center ui-gap-2">
                                                    <input type="radio" id="reverseCheck2" name="reverseRadio"
                                                        class="form-radio ui-rounded-full" disabled />
                                                    <label for="reverseCheck2">비활성화된 반대 방향 라디오</label>
                                                </div>

                                                <!-- Reverse Switch -->
                                                <div class="ui-flex ui-flex-row-reverse ui-items-center ui-gap-2">
                                                    <input type="checkbox" id="switchCheckReverse" class="form-switch"
                                                        checked />
                                                    <label for="switchCheckReverse">반대 방향 스위치 체크박스 입력</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Colored Radios -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">색상이 있는 라디오</label>
                                            </div>

                                            <div class="ui-col-1 ui-flex ui-flex-wrap ui-gap-9 ui-col-lg-2">
                                                <div class="ui-stack-3">
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkPrimary"
                                                            class="form-radio ui-rounded-full ui-checked-bg-primary"
                                                            checked />
                                                        <label for="checkPrimary">Primary</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkSecondary"
                                                            class="form-radio ui-rounded-full ui-checked-bg-secondary"
                                                            checked />
                                                        <label for="checkSecondary">Secondary</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkSuccess"
                                                            class="form-radio ui-rounded-full ui-checked-bg-success"
                                                            checked />
                                                        <label for="checkSuccess">Success</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkInfo"
                                                            class="form-radio ui-rounded-full ui-checked-bg-info" checked />
                                                        <label for="checkInfo">Info</label>
                                                    </div>
                                                </div>

                                                <div class="ui-stack-3">
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkWarning"
                                                            class="form-radio ui-rounded-full ui-checked-bg-warning"
                                                            checked />
                                                        <label for="checkWarning">Warning</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkDanger"
                                                            class="form-radio ui-rounded-full ui-checked-bg-danger"
                                                            checked />
                                                        <label for="checkDanger">Danger</label>
                                                    </div>
                                                    <div class="ui-flex ui-items-center ui-gap-2">
                                                        <input type="checkbox" id="checkDark"
                                                            class="form-radio ui-rounded-full ui-checked-bg-dark" checked />
                                                        <label for="checkDark">Dark</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Toggle Checkboxes -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">체크박스 토글 (Checkbox Toggle)</label>
                                            </div>

                                            <div class="ui-stack-3 ui-col-lg-2">
                                                <!-- Single Toggle -->
                                                <div>
                                                    <input type="checkbox" id="toggleSingle" class="ui-floating-control ui-hidden" />
                                                    <label for="toggleSingle"
                                                        class="btn btn-choice-primary">단일
                                                        토글</label>
                                                </div>

                                                <!-- Group Toggle -->
                                                <div class="ui-flex">
                                                    <div>
                                                        <input type="checkbox" id="toggle1" class="ui-floating-control ui-hidden" />
                                                        <label for="toggle1"
                                                            class="btn btn-choice-primary ui-rounded-e-none">하나</label>
                                                    </div>
                                                    <div>
                                                        <input type="checkbox" id="toggle2" class="ui-floating-control ui-hidden" />
                                                        <label for="toggle2"
                                                            class="btn btn-choice-primary ui-rounded-none ui-border-x-0">둘</label>
                                                    </div>
                                                    <div>
                                                        <input type="checkbox" id="toggle3" class="ui-floating-control ui-hidden" />
                                                        <label for="toggle3"
                                                            class="btn btn-choice-primary ui-rounded-s-none">셋</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="ui-border-default-300 ui-my-base ui-border-t ui-border-dashed"></div>

                                        <!-- Toggle Radios -->
                                        <div class="ui-grid ui-grid-cols-1 ui-gap-1-5 ui-grid-cols-lg-3 ui-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-py-2 ui-mb-0">라디오 토글 (Radio Toggle)</label>
                                            </div>

                                            <div class="ui-col-1 ui-flex ui-col-lg-2">
                                                <div>
                                                    <input type="radio" name="radiotoggle" id="radioLeft"
                                                        class="ui-floating-control ui-hidden" checked />
                                                    <label for="radioLeft"
                                                        class="btn btn-choice-secondary ui-rounded-e-none">왼쪽</label>
                                                </div>

                                                <div>
                                                    <input type="radio" name="radiotoggle" id="radioMiddle"
                                                        class="ui-floating-control ui-hidden" />
                                                    <label for="radioMiddle"
                                                        class="btn btn-choice-secondary ui-rounded-none ui-border-x-0">가운데</label>
                                                </div>

                                                <div>
                                                    <input type="radio" name="radiotoggle" id="radioRight"
                                                        class="ui-floating-control ui-hidden" />
                                                    <label for="radioRight"
                                                        class="btn btn-choice-secondary ui-rounded-s-none">오른쪽</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
</div>
</div>
