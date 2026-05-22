<div class="ui-kit-sample-section" data-ui-kit-sample="form-validation">
<div class="container-fluid">
                    <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-base">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">커스텀 스타일 유효성 검사 (Custom styles Validation)</h4>
                            </div>
                            <div class="card-body">
                                <form id="customValidationForm" class="ui-kit-grid ui-kit-grid-md-12 ui-kit-grid-1 ui-kit-gap-base"
                                    novalidate>
                                    <!-- First Name -->
                                    <div class="ui-kit-column-md-4">
                                        <label class="form-label" for="customFirstName">이름 (First Name) <span class="sr-required-label">(필수)</span></label>

                                        <div class="ui-kit-position-context">
                                            <input type="text" id="customFirstName" value="John" required
                                                class="input-field form-input" />

                                            <span class="ui-kit-icon-text valid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-3 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-success">정상</span>
                                            <span class="ui-kit-icon-text invalid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-3 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-danger">오류</span>
                                        </div>
                                        <p class="valid-msg ui-kit-space-before-1 ui-kit-state-hidden ui-kit-type-sm ui-kit-ink-success">좋습니다!</p>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-state-hidden ui-kit-type-sm ui-kit-ink-danger">이름을 입력해 주세요.</p>
                                    </div>

                                    <!-- Last Name -->
                                    <div class="ui-kit-column-md-4">
                                        <label class="form-label" for="customLastName">성 (Last Name) <span class="sr-required-label">(필수)</span></label>

                                        <div class="ui-kit-position-context">
                                            <input type="text" id="customLastName" value="Doe" required
                                                class="input-field form-input" />

                                            <span class="ui-kit-icon-text valid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-3 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-success">정상</span>
                                            <span class="ui-kit-icon-text invalid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-3 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-danger">오류</span>
                                        </div>
                                        <p class="valid-msg ui-kit-space-before-1 ui-kit-state-hidden ui-kit-type-sm ui-kit-ink-success">좋습니다!</p>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-state-hidden ui-kit-type-sm ui-kit-ink-danger">성을 입력해 주세요.</p>
                                    </div>

                                    <!-- Username -->
                                    <div class="ui-kit-column-md-4">
                                        <label class="form-label" for="customUsername">사용자 이름 (Username) <span class="sr-required-label">(필수)</span></label>

                                        <div class="ui-kit-position-context input-group">
                                            <span class="input-group-text">@</span>
                                            <input type="text" id="customUsername" placeholder="johndoe123" required
                                                class="input-field form-input" />

                                            <span class="ui-kit-icon-text valid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-3 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-success">정상</span>
                                            <span class="ui-kit-icon-text invalid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-3 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-danger">오류</span>
                                        </div>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-state-hidden ui-kit-type-sm ui-kit-ink-danger">유효한 사용자 이름을 선택해 주세요.</p>
                                    </div>

                                    <!-- City -->
                                    <div class="ui-kit-column-md-6">
                                        <label class="form-label" for="customCity">도시 (City) <span class="sr-required-label">(필수)</span></label>

                                        <div class="ui-kit-position-context">
                                            <input type="text" id="customCity" placeholder="San Francisco" required
                                                class="input-field form-input" />

                                            <span class="ui-kit-icon-text valid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-3 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-success">정상</span>
                                            <span class="ui-kit-icon-text invalid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-3 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-danger">오류</span>
                                        </div>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-state-hidden ui-kit-type-sm ui-kit-ink-danger">유효한 도시 이름을 입력해 주세요.</p>
                                    </div>

                                    <!-- State -->
                                    <div class="ui-kit-column-md-3">
                                        <label class="form-label" for="customState">주 (State) <span class="sr-required-label">(필수)</span></label>

                                        <div class="ui-kit-position-context">
                                            <select id="customState" required class="input-field form-select">
                                                <option value="">선택...</option>
                                                <option>California</option>
                                                <option>Texas</option>
                                                <option>New York</option>
                                                <option>Florida</option>
                                            </select>

                                            <span class="ui-kit-icon-text valid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-9 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-success">정상</span>
                                            <span class="ui-kit-icon-text invalid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-9 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-danger">오류</span>
                                        </div>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-state-hidden ui-kit-type-sm ui-kit-ink-danger">주를 선택해 주세요.
                                        </p>
                                    </div>

                                    <!-- Zip -->
                                    <div class="ui-kit-column-md-3">
                                        <label class="form-label" for="customZip">우편번호 (Zip Code) <span class="sr-required-label">(필수)</span></label>

                                        <div class="ui-kit-position-context">
                                            <input type="text" id="customZip" placeholder="94107" required
                                                class="input-field form-input" />

                                            <span class="ui-kit-icon-text valid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-3 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-success">정상</span>
                                            <span class="ui-kit-icon-text invalid-icon ui-kit-position-absolute ui-kit-position-top-half ui-kit-position-right-3 ui-kit-state-hidden ui-kit-center-y ui-kit-ink-danger">오류</span>
                                        </div>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-state-hidden ui-kit-type-sm ui-kit-ink-danger">유효한 우편번호를 입력해 주세요.</p>
                                    </div>

                                    <!-- Terms -->
                                    <div class="ui-kit-column-md-12">
                                        <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center">
                                            <input id="customTerms" type="checkbox" required class="form-checkbox" />
                                            <label for="customTerms" class="ui-kit-start-margin-2 ui-kit-type-sm ui-kit-ink-default-700">이용 약관에
                                                동의합니다 <span class="sr-required-label">(필수)</span></label>
                                            <p class="invalid-msg ui-kit-space-before-2 ui-kit-state-hidden ui-kit-fill-width ui-kit-type-sm ui-kit-ink-danger">제출하기 전에 동의해야
                                                합니다.</p>
                                        </div>
                                    </div>

                                    <!-- Submit -->
                                    <div class="ui-kit-column-md-12">
                                        <button type="submit"
                                            class="btn btn-solid-primary">양식 제출 (Submit
                                            Form)</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">서버 측 (Server-side)</h4>
                            </div>
                            <div class="card-body">
                                <form id="serverForm" class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-md-12 ui-kit-gap-base" novalidate>
                                    <!-- First name -->
                                    <div class="ui-kit-column-md-4">
                                        <label for="serverFirstName" class="form-label">이름 (First name) <span class="sr-required-label">(필수)</span></label>
                                        <div class="ui-kit-position-context">
                                            <input type="text" id="serverFirstName" value="Mark" required
                                                class="form-input form-input-valid" />
                                            <div
                                                class="ui-kit-state-disabled-pointer ui-kit-position-absolute ui-kit-position-block-0 ui-kit-position-end-0 ui-kit-cluster ui-kit-align-items-center ui-kit-end-pad-3">
                                                <span class="ui-kit-icon-text">check</span>
                                            </div>
                                        </div>
                                        <p class="ui-kit-ink-success ui-kit-space-before-1 ui-kit-type-2xs">좋습니다!</p>
                                    </div>

                                    <!-- Last name -->
                                    <div class="ui-kit-column-md-4">
                                        <label for="serverLastName" class="form-label">성 (Last name) <span class="sr-required-label">(필수)</span></label>
                                        <div class="ui-kit-position-context">
                                            <input type="text" id="serverLastName" value="Otto" required
                                                class="form-input form-input-valid" />
                                            <div
                                                class="ui-kit-state-disabled-pointer ui-kit-position-absolute ui-kit-position-block-0 ui-kit-position-end-0 ui-kit-cluster ui-kit-align-items-center ui-kit-end-pad-3">
                                                <span class="ui-kit-icon-text">check</span>
                                            </div>
                                        </div>
                                        <p class="ui-kit-ink-success ui-kit-space-before-1 ui-kit-type-2xs">좋습니다!</p>
                                    </div>

                                    <!-- Username -->
                                    <div class="ui-kit-column-md-4">
                                        <label for="serverUsername" class="form-label">사용자 이름 (Username) <span class="sr-required-label">(필수)</span></label>
                                        <div class="ui-kit-position-context ui-kit-cluster ui-kit-radius-md">
                                            <span
                                                class="ui-kit-line-default-300 ui-kit-surface-default-100 ui-kit-ink-default-600 ui-kit-inline-cluster ui-kit-align-items-center ui-kit-radius-s-md ui-kit-frame ui-kit-inline-pad-3 ui-kit-type-sm">@</span>
                                            <input type="text" id="serverUsername" name="username"
                                                class="form-input form-input-invalid form-control-group-end"
                                                placeholder="johndoe123" required />
                                            <div
                                                class="ui-kit-state-disabled-pointer ui-kit-position-absolute ui-kit-position-block-0 ui-kit-position-end-0 ui-kit-cluster ui-kit-align-items-center ui-kit-end-pad-3">
                                                <span class="ui-kit-icon-text ui-kit-ink-danger ui-kit-type-base">정보</span>
                                            </div>
                                        </div>
                                        <p class="ui-kit-ink-danger ui-kit-space-before-1 ui-kit-type-2xs">사용자 이름을 선택해 주세요.</p>
                                    </div>

                                    <!-- City -->
                                    <div class="ui-kit-column-md-6">
                                        <label for="serverCity" class="form-label">도시 (City) <span class="sr-required-label">(필수)</span></label>
                                        <div class="ui-kit-position-context">
                                            <input type="text" id="serverCity" required placeholder="도시 입력"
                                                class="form-input form-input-invalid" />
                                            <div
                                                class="ui-kit-state-disabled-pointer ui-kit-position-absolute ui-kit-position-block-0 ui-kit-position-end-0 ui-kit-cluster ui-kit-align-items-center ui-kit-end-pad-3">
                                                <span class="ui-kit-icon-text ui-kit-ink-danger ui-kit-type-base">정보</span>
                                            </div>
                                        </div>
                                        <p class="ui-kit-ink-danger ui-kit-space-before-1 ui-kit-type-2xs">유효한 도시를 입력해 주세요.</p>
                                    </div>

                                    <!-- State -->
                                    <div class="ui-kit-column-md-3">
                                        <label for="serverState" class="form-label">주 (State) <span class="sr-required-label">(필수)</span></label>
                                        <div class="ui-kit-position-context">
                                            <select id="serverState" required class="form-select form-select-invalid">
                                                <option value="">선택...</option>
                                                <option>California</option>
                                                <option>Texas</option>
                                                <option>Florida</option>
                                            </select>
                                            <div
                                                class="ui-kit-state-disabled-pointer ui-kit-position-absolute ui-kit-position-block-0 ui-kit-position-end-6 ui-kit-cluster ui-kit-align-items-center ui-kit-end-pad-3">
                                                <span class="ui-kit-icon-text ui-kit-ink-danger ui-kit-type-base">정보</span>
                                            </div>
                                        </div>
                                        <p class="ui-kit-ink-danger ui-kit-space-before-1 ui-kit-type-2xs">유효한 주를 선택해 주세요.</p>
                                    </div>

                                    <!-- Zip -->
                                    <div class="ui-kit-column-md-3">
                                        <label for="serverZip" class="form-label">우편번호 (Zip) <span class="sr-required-label">(필수)</span></label>
                                        <div class="ui-kit-position-context">
                                            <input type="text" id="serverZip" required placeholder="우편번호"
                                                class="form-input form-input-invalid" />
                                            <div
                                                class="ui-kit-state-disabled-pointer ui-kit-position-absolute ui-kit-position-block-0 ui-kit-position-end-0 ui-kit-cluster ui-kit-align-items-center ui-kit-end-pad-3">
                                                <span class="ui-kit-icon-text ui-kit-ink-danger ui-kit-type-base">정보</span>
                                            </div>
                                        </div>
                                        <p class="ui-kit-ink-danger ui-kit-space-before-1 ui-kit-type-2xs">유효한 우편번호를 입력해 주세요.</p>
                                    </div>

                                    <!-- Checkbox -->
                                    <div class="ui-kit-column-md-12">
                                        <label class="ui-kit-cluster ui-kit-align-items-center ui-kit-inline-space-2" for="serverTerms">
                                            <input type="checkbox" id="serverTerms" required
                                                class="form-checkbox form-choice-danger" />
                                            <span class="ui-kit-ink-danger">이용 약관에 동의합니다 <span class="sr-required-label">(필수)</span></span>
                                        </label>
                                        <p class="ui-kit-ink-danger ui-kit-space-before-1 ui-kit-type-2xs">제출하기 전에 동의해야 합니다.</p>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="ui-kit-column-md-12">
                                        <button type="submit"
                                            class="btn btn-solid-primary">양식 제출 (Submit
                                            form)</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">지원되는 요소 (Supported Elements)</h4>
                            </div>
                            <div class="card-body">
                                <form class="ui-kit-stack-6" novalidate>
                                    <!-- Textarea -->
                                    <div>
                                        <label for="validationTextarea" class="form-label">텍스트 영역 (Textarea) <span class="sr-required-label">(필수)</span></label>
                                        <textarea id="validationTextarea" placeholder="필수 입력 텍스트 영역 예시" required
                                            class="form-textarea form-textarea-invalid"></textarea>
                                        <p class="ui-kit-ink-danger ui-kit-space-before-1 ui-kit-type-2xs">텍스트 영역에 메시지를 입력해 주세요.</p>
                                    </div>

                                    <!-- Checkbox -->
                                    <div class="ui-kit-cluster ui-kit-align-items-start ui-kit-gap-2">
                                        <input id="validationFormCheck1" type="checkbox" required
                                            class="form-checkbox form-choice-danger form-choice-invalid form-choice-offset" />
                                        <div>
                                            <label for="validationFormCheck1" class="ui-kit-ink-danger">이 체크박스를 체크하세요 <span class="sr-required-label">(필수)</span></label>
                                            <p class="ui-kit-ink-danger ui-kit-space-before-1 ui-kit-type-2xs">잘못된 피드백 텍스트 예시</p>
                                        </div>
                                    </div>

                                    <!-- Radios -->
                                    <div class="ui-kit-stack-2">
                                        <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                            <input id="validationFormCheck2" type="radio" name="radio-stacked" required
                                                class="form-radio form-choice-danger form-choice-invalid" />
                                            <label for="validationFormCheck2" class="ui-kit-ink-danger">이 라디오 버튼을
                                                토글하세요 <span class="sr-required-label">(필수)</span></label>
                                        </div>

                                        <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                            <input id="validationFormCheck3" type="radio" name="radio-stacked" required
                                                class="form-radio form-choice-danger form-choice-invalid" />
                                            <label for="validationFormCheck3" class="ui-kit-ink-danger">또는 다른 라디오 버튼을
                                                토글하세요 <span class="sr-required-label">(필수)</span></label>
                                            <p class="ui-kit-ink-danger ui-kit-space-before-1 ui-kit-type-2xs">추가적인 잘못된 피드백 텍스트 예시</p>
                                        </div>
                                    </div>

                                    <!-- Select -->
                                    <div>
                                        <span class="form-label">선택 메뉴 <span class="sr-required-label">(필수)</span></span>
                                        <select required class="form-select form-select-valid">
                                            <option value="">이 선택 메뉴를 여세요</option>
                                            <option value="1">하나</option>
                                            <option value="2">둘</option>
                                            <option value="3">셋</option>
                                        </select>
                                    </div>

                                    <!-- File Input -->
                                    <div>
                                        <span class="form-label">파일 <span class="sr-required-label">(필수)</span></span>
                                        <input type="file" required class="form-input" />
                                        <p class="ui-kit-ink-danger ui-kit-space-before-1 ui-kit-type-2xs">잘못된 양식 파일 피드백 예시</p>
                                    </div>

                                    <!-- Submit -->
                                    <div>
                                        <button type="submit" disabled
                                            class="btn btn-solid-primary">양식
                                            제출 (Submit form)</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">브라우저 기본값 (Browser Defaults)</h4>
                            </div>
                            <div class="card-body">
                                <form action="">
                                    <div class="ui-kit-grid ui-kit-grid-md-3 ui-kit-grid-1 ui-kit-gap-base ui-kit-space-after-base">
                                        <div>
                                            <label for="validationDefault01" class="form-label">이름 (First name) <span class="sr-required-label">(필수)</span></label>
                                            <input type="text" class="form-input" id="validationDefault01" value="Mark"
                                                required="" />
                                        </div>

                                        <div>
                                            <label for="validationDefault02" class="form-label">성 (Last name) <span class="sr-required-label">(필수)</span></label>
                                            <input type="text" class="form-input" id="validationDefault02" value="Otto"
                                                required="" />
                                        </div>

                                        <div>
                                            <label for="validationDefaultUsername" class="form-label">사용자 이름
                                                (Username) <span class="sr-required-label">(필수)</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text" id="inputGroupPrepend2">@</span>
                                                <input type="text" class="form-input" id="validationDefaultUsername"
                                                    aria-describedby="inputGroupPrepend2" required="" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ui-kit-grid ui-kit-grid-md-4 ui-kit-grid-1 ui-kit-gap-base ui-kit-space-after-base">
                                        <div class="ui-kit-column-md-2 ui-kit-column-1">
                                            <label for="validationDefault03" class="form-label">도시 (City) <span class="sr-required-label">(필수)</span></label>
                                            <input type="text" class="form-input" id="validationDefault03"
                                                required="" />
                                        </div>

                                        <div>
                                            <label for="validationDefault04" class="form-label">주 (State) <span class="sr-required-label">(필수)</span></label>
                                            <select class="form-select" id="validationDefault04" required="">
                                                <option selected="" disabled="" value="">선택...</option>
                                                <option>...</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="validationDefault05" class="form-label">우편번호 (Zip) <span class="sr-required-label">(필수)</span></label>
                                            <input type="text" class="form-input" id="validationDefault05"
                                                required="" />
                                        </div>
                                    </div>

                                    <div>
                                        <label class="ui-kit-cluster ui-kit-align-items-center ui-kit-inline-space-2" for="invalidCheck2">
                                            <input type="checkbox" id="invalidCheck2" required class="form-checkbox" />
                                            <span class="ui-kit-ink-default-700">이용 약관에 동의합니다 <span class="sr-required-label">(필수)</span></span>
                                        </label>
                                    </div>

                                    <div class="ui-kit-space-before-base">
                                        <button class="btn btn-solid-primary"
                                            type="submit">양식 제출 (Submit form)</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
</div>
</div>
