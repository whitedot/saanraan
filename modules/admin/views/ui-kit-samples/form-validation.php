<div class="ui-kit-sample-section" data-ui-kit-sample="form-validation">
<div class="container-fluid">
                    <div class="ui-grid ui-grid-cols-1 ui-gap-base">
                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">커스텀 스타일 유효성 검사 (Custom styles Validation)</h4>
                            </div>
                            <div class="ui-kit-example-body">
                                <form id="customValidationForm" class="ui-grid ui-grid-cols-md-12 ui-grid-cols-1 ui-gap-base"
                                    novalidate>
                                    <!-- First Name -->
                                    <div class="ui-col-md-4">
                                        <label class="form-label">이름 (First Name)</label>

                                        <div class="ui-relative">
                                            <input type="text" id="firstName" value="John" required
                                                class="input-field form-input" />

                                            <i
                                                class="iconify tabler--check valid-icon ui-absolute ui-top-half ui-right-3 ui-hidden ui-translate-y-center ui-text-success"></i>
                                            <i
                                                class="iconify tabler--alert-circle invalid-icon ui-absolute ui-top-half ui-right-3 ui-hidden ui-translate-y-center ui-text-danger"></i>
                                        </div>
                                        <p class="valid-msg ui-mt-1 ui-hidden ui-text-sm ui-text-success">좋습니다!</p>
                                        <p class="invalid-msg ui-mt-1 ui-hidden ui-text-sm ui-text-danger">이름을 입력해 주세요.</p>
                                    </div>

                                    <!-- Last Name -->
                                    <div class="ui-col-md-4">
                                        <label class="form-label">성 (Last Name)</label>

                                        <div class="ui-relative">
                                            <input type="text" id="lastName" value="Doe" required
                                                class="input-field form-input" />

                                            <i
                                                class="iconify tabler--check valid-icon ui-absolute ui-top-half ui-right-3 ui-hidden ui-translate-y-center ui-text-success"></i>
                                            <i
                                                class="iconify tabler--alert-circle invalid-icon ui-absolute ui-top-half ui-right-3 ui-hidden ui-translate-y-center ui-text-danger"></i>
                                        </div>
                                        <p class="valid-msg ui-mt-1 ui-hidden ui-text-sm ui-text-success">좋습니다!</p>
                                        <p class="invalid-msg ui-mt-1 ui-hidden ui-text-sm ui-text-danger">성을 입력해 주세요.</p>
                                    </div>

                                    <!-- Username -->
                                    <div class="ui-col-md-4">
                                        <label class="form-label">사용자 이름 (Username)</label>

                                        <div class="ui-relative input-group">
                                            <span class="input-group-text">@</span>
                                            <input type="text" id="username" placeholder="johndoe123" required
                                                class="input-field form-input" />

                                            <i
                                                class="iconify tabler--check valid-icon ui-absolute ui-top-half ui-right-3 ui-hidden ui-translate-y-center ui-text-success"></i>
                                            <i
                                                class="iconify tabler--alert-circle invalid-icon ui-absolute ui-top-half ui-right-3 ui-hidden ui-translate-y-center ui-text-danger"></i>
                                        </div>
                                        <p class="invalid-msg ui-mt-1 ui-hidden ui-text-sm ui-text-danger">유효한 사용자 이름을 선택해 주세요.</p>
                                    </div>

                                    <!-- City -->
                                    <div class="ui-col-md-6">
                                        <label class="form-label">도시 (City)</label>

                                        <div class="ui-relative">
                                            <input type="text" id="city" placeholder="San Francisco" required
                                                class="input-field form-input" />

                                            <i
                                                class="iconify tabler--check valid-icon ui-absolute ui-top-half ui-right-3 ui-hidden ui-translate-y-center ui-text-success"></i>
                                            <i
                                                class="iconify tabler--alert-circle invalid-icon ui-absolute ui-top-half ui-right-3 ui-hidden ui-translate-y-center ui-text-danger"></i>
                                        </div>
                                        <p class="invalid-msg ui-mt-1 ui-hidden ui-text-sm ui-text-danger">유효한 도시 이름을 입력해 주세요.</p>
                                    </div>

                                    <!-- State -->
                                    <div class="ui-col-md-3">
                                        <label class="form-label">주 (State)</label>

                                        <div class="ui-relative">
                                            <select id="state" required class="input-field form-input">
                                                <option value="">선택...</option>
                                                <option>California</option>
                                                <option>Texas</option>
                                                <option>New York</option>
                                                <option>Florida</option>
                                            </select>

                                            <i
                                                class="iconify tabler--check valid-icon ui-absolute ui-top-half ui-right-9 ui-hidden ui-translate-y-center ui-text-success"></i>
                                            <i
                                                class="iconify tabler--alert-circle invalid-icon ui-absolute ui-top-half ui-right-9 ui-hidden ui-translate-y-center ui-text-danger"></i>
                                        </div>
                                        <p class="invalid-msg ui-mt-1 ui-hidden ui-text-sm ui-text-danger">주를 선택해 주세요.
                                        </p>
                                    </div>

                                    <!-- Zip -->
                                    <div class="ui-col-md-3">
                                        <label class="form-label">우편번호 (Zip Code)</label>

                                        <div class="ui-relative">
                                            <input type="text" id="zip" placeholder="94107" required
                                                class="input-field form-input" />

                                            <i
                                                class="iconify tabler--check valid-icon ui-absolute ui-top-half ui-right-3 ui-hidden ui-translate-y-center ui-text-success"></i>
                                            <i
                                                class="iconify tabler--alert-circle invalid-icon ui-absolute ui-top-half ui-right-3 ui-hidden ui-translate-y-center ui-text-danger"></i>
                                        </div>
                                        <p class="invalid-msg ui-mt-1 ui-hidden ui-text-sm ui-text-danger">유효한 우편번호를 입력해 주세요.</p>
                                    </div>

                                    <!-- Terms -->
                                    <div class="ui-col-md-12">
                                        <div class="ui-flex ui-flex-wrap ui-items-center">
                                            <input id="terms" type="checkbox" required class="form-checkbox" />
                                            <label for="terms" class="ui-ms-2 ui-text-sm ui-text-default-700">이용 약관에
                                                동의합니다</label>
                                            <p class="invalid-msg ui-mt-2 ui-hidden ui-w-full ui-text-sm ui-text-danger">제출하기 전에 동의해야
                                                합니다.</p>
                                        </div>
                                    </div>

                                    <!-- Submit -->
                                    <div class="ui-col-md-12">
                                        <button type="submit"
                                            class="btn btn-solid-primary">양식 제출 (Submit
                                            Form)</button>
                                    </div>
                                </form>
                            </div>
                        </section>

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">서버 측 (Server-side)</h4>
                            </div>
                            <div class="ui-kit-example-body">
                                <form id="serverForm" class="ui-grid ui-grid-cols-1 ui-grid-cols-md-12 ui-gap-base" novalidate>
                                    <!-- First name -->
                                    <div class="ui-col-md-4">
                                        <label for="firstName" class="form-label">이름 (First name)</label>
                                        <div class="ui-relative">
                                            <input type="text" id="firstName" value="Mark" required
                                                class="form-input ui-border-success" />
                                            <div
                                                class="ui-pointer-none ui-absolute ui-inset-y-0 ui-end-0 ui-flex ui-items-center ui-pe-3">
                                                <i data-icon="tabler:check" class="iconify tabler--check ui-text-success ui-text-base"></i>
                                            </div>
                                        </div>
                                        <p class="ui-text-success ui-mt-1 ui-text-2xs">좋습니다!</p>
                                    </div>

                                    <!-- Last name -->
                                    <div class="ui-col-md-4">
                                        <label for="lastName" class="form-label">성 (Last name)</label>
                                        <div class="ui-relative">
                                            <input type="text" id="lastName" value="Otto" required
                                                class="form-input ui-border-success" />
                                            <div
                                                class="ui-pointer-none ui-absolute ui-inset-y-0 ui-end-0 ui-flex ui-items-center ui-pe-3">
                                                <i data-icon="tabler:check" class="iconify tabler--check ui-text-success ui-text-base"></i>
                                            </div>
                                        </div>
                                        <p class="ui-text-success ui-mt-1 ui-text-2xs">좋습니다!</p>
                                    </div>

                                    <!-- Username -->
                                    <div class="ui-col-md-4">
                                        <label for="username" class="form-label">사용자 이름 (Username)</label>
                                        <div class="ui-relative ui-flex ui-rounded-md">
                                            <span
                                                class="ui-border-default-300 ui-bg-default-100 ui-text-default-600 ui-inline-flex ui-items-center ui-rounded-s-md ui-border ui-px-3 ui-text-sm">@</span>
                                            <input type="text" id="username" name="username"
                                                class="form-input ui-border-danger ui-rounded-s-none"
                                                placeholder="johndoe123" required />
                                            <div
                                                class="ui-pointer-none ui-absolute ui-inset-y-0 ui-end-0 ui-flex ui-items-center ui-pe-3">
                                                <span data-icon="info" class="ui-text-danger ui-text-base"></span>
                                            </div>
                                        </div>
                                        <p class="ui-text-danger ui-mt-1 ui-text-2xs">사용자 이름을 선택해 주세요.</p>
                                    </div>

                                    <!-- City -->
                                    <div class="ui-col-md-6">
                                        <label for="city" class="form-label">도시 (City)</label>
                                        <div class="ui-relative">
                                            <input type="text" id="city" required placeholder="도시 입력"
                                                class="form-input ui-border-danger" />
                                            <div
                                                class="ui-pointer-none ui-absolute ui-inset-y-0 ui-end-0 ui-flex ui-items-center ui-pe-3">
                                                <span data-icon="info" class="ui-text-danger ui-text-base"></span>
                                            </div>
                                        </div>
                                        <p class="ui-text-danger ui-mt-1 ui-text-2xs">유효한 도시를 입력해 주세요.</p>
                                    </div>

                                    <!-- State -->
                                    <div class="ui-col-md-3">
                                        <label for="state" class="form-label">주 (State)</label>
                                        <div class="ui-relative">
                                            <select id="state" required class="form-input ui-border-danger">
                                                <option value="">선택...</option>
                                                <option>California</option>
                                                <option>Texas</option>
                                                <option>Florida</option>
                                            </select>
                                            <div
                                                class="ui-pointer-none ui-absolute ui-inset-y-0 ui-end-6 ui-flex ui-items-center ui-pe-3">
                                                <span data-icon="info" class="ui-text-danger ui-text-base"></span>
                                            </div>
                                        </div>
                                        <p class="ui-text-danger ui-mt-1 ui-text-2xs">유효한 주를 선택해 주세요.</p>
                                    </div>

                                    <!-- Zip -->
                                    <div class="ui-col-md-3">
                                        <label for="zip" class="form-label">우편번호 (Zip)</label>
                                        <div class="ui-relative">
                                            <input type="text" id="zip" required placeholder="우편번호"
                                                class="form-input ui-border-danger" />
                                            <div
                                                class="ui-pointer-none ui-absolute ui-inset-y-0 ui-end-0 ui-flex ui-items-center ui-pe-3">
                                                <span data-icon="info" class="ui-text-danger ui-text-base"></span>
                                            </div>
                                        </div>
                                        <p class="ui-text-danger ui-mt-1 ui-text-2xs">유효한 우편번호를 입력해 주세요.</p>
                                    </div>

                                    <!-- Checkbox -->
                                    <div class="ui-col-md-12">
                                        <label class="ui-flex ui-items-center ui-space-x-2">
                                            <input type="checkbox" id="terms" required
                                                class="form-checkbox ui-checked-bg-danger" />
                                            <span class="ui-text-danger">이용 약관에 동의합니다</span>
                                        </label>
                                        <p class="ui-text-danger ui-mt-1 ui-text-2xs">제출하기 전에 동의해야 합니다.</p>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="ui-col-md-12">
                                        <button type="submit"
                                            class="btn btn-solid-primary">양식 제출 (Submit
                                            form)</button>
                                    </div>
                                </form>
                            </div>
                        </section>

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">지원되는 요소 (Supported Elements)</h4>
                            </div>
                            <div class="ui-kit-example-body">
                                <form class="ui-stack-6" novalidate>
                                    <!-- Textarea -->
                                    <div>
                                        <label for="validationTextarea" class="form-label">텍스트 영역 (Textarea)</label>
                                        <textarea id="validationTextarea" placeholder="필수 입력 텍스트 영역 예시" required
                                            class="form-textarea ui-border-danger"></textarea>
                                        <p class="ui-text-danger ui-mt-1 ui-text-2xs">텍스트 영역에 메시지를 입력해 주세요.</p>
                                    </div>

                                    <!-- Checkbox -->
                                    <div class="ui-flex ui-items-start ui-gap-2">
                                        <input id="validationFormCheck1" type="checkbox" required
                                            class="form-checkbox ui-checked-bg-danger ui-border-danger ui-mt-0-5" />
                                        <div>
                                            <label for="validationFormCheck1" class="ui-text-danger">이 체크박스를 체크하세요</label>
                                            <p class="ui-text-danger ui-mt-1 ui-text-2xs">잘못된 피드백 텍스트 예시</p>
                                        </div>
                                    </div>

                                    <!-- Radios -->
                                    <div class="ui-stack-2">
                                        <div class="ui-flex ui-items-center ui-gap-2">
                                            <input id="validationFormCheck2" type="radio" name="radio-stacked" required
                                                class="form-radio ui-checked-bg-danger ui-border-danger" />
                                            <label for="validationFormCheck2" class="ui-text-danger">이 라디오 버튼을
                                                토글하세요</label>
                                        </div>

                                        <div class="ui-flex ui-items-center ui-gap-2">
                                            <input id="validationFormCheck3" type="radio" name="radio-stacked" required
                                                class="form-radio ui-checked-bg-danger ui-border-danger" />
                                            <label for="validationFormCheck3" class="ui-text-danger">또는 다른 라디오 버튼을
                                                토글하세요</label>
                                            <p class="ui-text-danger ui-mt-1 ui-text-2xs">추가적인 잘못된 피드백 텍스트 예시</p>
                                        </div>
                                    </div>

                                    <!-- Select -->
                                    <div>
                                        <select required class="form-input ui-border-success">
                                            <option value="">이 선택 메뉴를 여세요</option>
                                            <option value="1">하나</option>
                                            <option value="2">둘</option>
                                            <option value="3">셋</option>
                                        </select>
                                    </div>

                                    <!-- File Input -->
                                    <div>
                                        <input type="file" required class="form-input ui-block ui-w-full ui-text-sm" />
                                        <p class="ui-text-danger ui-mt-1 ui-text-2xs">잘못된 양식 파일 피드백 예시</p>
                                    </div>

                                    <!-- Submit -->
                                    <div>
                                        <button type="submit" disabled
                                            class="btn ui-cursor-not-allowed btn-solid-primary">양식
                                            제출 (Submit form)</button>
                                    </div>
                                </form>
                            </div>
                        </section>

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">브라우저 기본값 (Browser Defaults)</h4>
                            </div>
                            <div class="ui-kit-example-body">
                                <form action="">
                                    <div class="ui-grid ui-grid-cols-md-3 ui-grid-cols-1 ui-gap-base ui-mb-base">
                                        <div>
                                            <label for="validationDefault01" class="form-label">이름 (First name)</label>
                                            <input type="text" class="form-input" id="validationDefault01" value="Mark"
                                                required="" />
                                        </div>

                                        <div>
                                            <label for="validationDefault02" class="form-label">성 (Last name)</label>
                                            <input type="text" class="form-input" id="validationDefault02" value="Otto"
                                                required="" />
                                        </div>

                                        <div>
                                            <label for="validationDefaultUsername" class="form-label">사용자 이름
                                                (Username)</label>
                                            <div class="input-group">
                                                <span class="input-group-text" id="inputGroupPrepend2">@</span>
                                                <input type="text" class="form-input" id="validationDefaultUsername"
                                                    aria-describedby="inputGroupPrepend2" required="" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ui-grid ui-grid-cols-md-4 ui-grid-cols-1 ui-gap-base ui-mb-base">
                                        <div class="ui-col-md-2 ui-col-1">
                                            <label for="validationDefault03" class="form-label">도시 (City)</label>
                                            <input type="text" class="form-input" id="validationDefault03"
                                                required="" />
                                        </div>

                                        <div>
                                            <label for="validationDefault04" class="form-label">주 (State)</label>
                                            <select class="form-input" id="validationDefault04" required="">
                                                <option selected="" disabled="" value="">선택...</option>
                                                <option>...</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="validationDefault05" class="form-label">우편번호 (Zip)</label>
                                            <input type="text" class="form-input" id="validationDefault05"
                                                required="" />
                                        </div>
                                    </div>

                                    <div>
                                        <label class="ui-flex ui-items-center ui-space-x-2">
                                            <input type="checkbox" id="invalidCheck2" required class="form-checkbox" />
                                            <span class="ui-text-default-700">이용 약관에 동의합니다</span>
                                        </label>
                                    </div>

                                    <div class="ui-mt-base">
                                        <button class="btn btn-solid-primary"
                                            type="submit">양식 제출 (Submit form)</button>
                                    </div>
                                </form>
                            </div>
                        </section>
                    </div>
</div>
</div>
