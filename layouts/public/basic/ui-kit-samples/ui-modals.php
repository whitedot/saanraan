<div class="ui-kit-sample-section" data-ui-kit-sample="ui-modals">
<div class="container-fluid">
                    <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-base">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">모달 (Modals)</h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-ink-default-400 ui-kit-space-after-4">헤더, 본문 및 푸터의 작업 세트가 포함된 렌더링된 모달입니다.</p>
                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                    <!-- Standard modal content -->
                                    <div>
                                        <button type="button"
                                            class="btn btn-solid-primary"
                                            aria-haspopup="dialog" aria-expanded="false" aria-controls="standard-modal"
                                            data-overlay="#standard-modal">표준 모달</button>

                                        <div id="standard-modal"
                                            class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                            role="dialog" tabindex="-1" aria-labelledby="standard-modal-label">
                                            <div
                                                class="modal-dialog">
                                                <div
                                                    class="modal-content">
                                                    <div
                                                        class="modal-header">
                                                        <h3 id="standard-modal-label" class="modal-title">
                                                            모달 제목</h3>
                                                        <button type="button" class="modal-close" aria-label="Close"
                                                            data-overlay="#standard-modal">
                                                            <span class="sr-only">닫기</span>
                                                            <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                        </button>
                                                    </div>

                                                    <div class="modal-body">
                                                        <h5 class="ui-kit-space-after-2">모달의 텍스트</h5>
                                                        <p class="ui-kit-space-after-4">모달 본문에 들어갈 텍스트 예시입니다. 여기에 중요한 정보를 표시할 수 있습니다.
                                                        </p>
                                                        <hr class="ui-kit-line-default-300 ui-kit-block-space-4" />
                                                        <h5 class="ui-kit-space-after-2">스크롤 동작을 보여주기 위한 넘치는 텍스트</h5>
                                                        <p class="ui-kit-space-after-4">콘텐츠가 너무 길어지면 모달 내부에 스크롤이 생겨 모든 내용을 확인할 수 있습니다.
                                                        </p>
                                                        <p class="ui-kit-space-after-4">사용자가 정보를 쉽게 읽을 수 있도록 적절한 여백과 구조를 제공합니다.</p>
                                                        <p>다양한 상황에 맞춰 모달의 크기와 내용을 조절하여 사용할 수 있습니다.</p>
                                                    </div>

                                                    <div
                                                        class="modal-footer">
                                                        <button type="button"
                                                            class="btn btn-subtle-light modal-action"
                                                            data-overlay="#standard-modal">닫기</button>

                                                        <button type="button"
                                                            class="btn btn-solid-primary modal-action">변경사항
                                                            저장</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!--  Modal content for the Large example -->
                                    <div>
                                        <button type="button" class="btn btn-solid-info-strong"
                                            aria-haspopup="dialog" aria-expanded="false"
                                            aria-controls="bs-example-modal-lg"
                                            data-overlay="#bs-example-modal-lg">대형 모달</button>

                                        <div id="bs-example-modal-lg"
                                            class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                            role="dialog" tabindex="-1" aria-labelledby="bs-example-modal-lg-label">
                                            <div
                                                class="modal-dialog modal-dialog-lg">
                                                <div
                                                    class="modal-content">
                                                    <div
                                                        class="modal-header">
                                                        <h3 id="bs-example-modal-lg-label"
                                                            class="modal-title">대형 모달</h3>
                                                        <button type="button" class="modal-close" aria-label="Close"
                                                            data-overlay="#bs-example-modal-lg">
                                                            <span class="sr-only">닫기</span>
                                                            <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">내용...</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!--  Modal content for the Small example -->
                                    <div>
                                        <button type="button"
                                            class="btn btn-solid-success-strong"
                                            aria-haspopup="dialog" aria-expanded="false"
                                            aria-controls="bs-example-modal-sm"
                                            data-overlay="#bs-example-modal-sm">소형 모달</button>

                                        <div id="bs-example-modal-sm"
                                            class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                            role="dialog" tabindex="-1" aria-labelledby="bs-example-modal-sm-label">
                                            <div class="modal-dialog-sm">
                                                <div
                                                    class="modal-content">
                                                    <div
                                                        class="modal-header">
                                                        <h3 id="bs-example-modal-sm-label"
                                                            class="modal-title">소형 모달</h3>
                                                        <button type="button" class="modal-close" aria-label="Close"
                                                            data-overlay="#bs-example-modal-sm">
                                                            <span class="sr-only">닫기</span>
                                                            <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                        </button>
                                                    </div>

                                                    <div class="modal-body">내용...</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Full width modal content -->
                                    <div>
                                        <button type="button"
                                            class="btn btn-solid-primary"
                                            aria-haspopup="dialog" aria-expanded="false"
                                            aria-controls="full-width-modal" data-overlay="#full-width-modal">전체 너비
                                            모달</button>

                                        <div id="full-width-modal"
                                            class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                            role="dialog" tabindex="-1" aria-labelledby="full-width-modal-label">
                                            <div
                                                class="modal-dialog modal-dialog-full">
                                                <div
                                                    class="modal-content">
                                                    <div
                                                        class="modal-header">
                                                        <h3 id="full-width-modal-label" class="modal-title">
                                                            모달 제목</h3>
                                                        <button type="button" class="modal-close" aria-label="Close"
                                                            data-overlay="#full-width-modal">
                                                            <span class="sr-only">닫기</span>
                                                            <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                        </button>
                                                    </div>

                                                    <div class="modal-body">
                                                        <h5 class="ui-kit-space-after-2">모달의 텍스트</h5>
                                                        <p class="ui-kit-space-after-4">전체 너비를 사용하는 모달입니다. 더 많은 정보를 한눈에 보여줄 때 유용합니다.</p>
                                                        <hr class="ui-kit-line-default-300 ui-kit-block-space-4" />
                                                        <h5 class="ui-kit-space-after-2">스크롤 동작을 보여주기 위한 넘치는 텍스트</h5>
                                                        <p class="ui-kit-space-after-4">화면 전체를 활용하여 사용자에게 몰입감 있는 경험을 제공할 수 있습니다.</p>
                                                        <p class="ui-kit-space-after-4">복잡한 데이터나 상세한 설명을 표시하기에 적합한 레이아웃입니다.</p>
                                                        <p>사용자의 시선을 사로잡는 강력한 디자인 도구로 활용해 보세요.</p>
                                                    </div>

                                                    <div
                                                        class="modal-footer">
                                                        <button type="button"
                                                            class="btn btn-subtle-light modal-action"
                                                            data-overlay="#full-width-modal">닫기</button>

                                                        <button type="button"
                                                            class="btn btn-solid-primary modal-action">변경사항
                                                            저장</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Long Content Scroll Modal -->
                                    <div>
                                        <button type="button"
                                            class="btn btn-solid-secondary"
                                            aria-haspopup="dialog" aria-expanded="false"
                                            aria-controls="scrollable-modal" data-overlay="#scrollable-modal">스크롤 가능
                                            모달</button>

                                        <div id="scrollable-modal"
                                            class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                            role="dialog" tabindex="-1" aria-labelledby="scrollable-modal-label">
                                            <div
                                                class="modal-dialog">
                                                <div
                                                    class="modal-content">
                                                    <div
                                                        class="modal-header">
                                                        <h3 id="scrollable-modal-label" class="modal-title">
                                                            모달 제목</h3>
                                                        <button type="button" class="modal-close" aria-label="Close"
                                                            data-overlay="#scrollable-modal">
                                                            <span class="sr-only">닫기</span>
                                                            <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                        </button>
                                                    </div>

                                                    <div class="modal-body-scroll">
                                                        <p class="ui-kit-space-after-4">긴 콘텐츠를 포함하는 스크롤 가능한 모달의 예시입니다. 본문에 많은 양의 텍스트나
                                                            리스트를 넣어야 할 때 사용합니다.</p>
                                                        <p class="ui-kit-space-after-4">사용자가 스크롤을 내려서 전체 내용을 확인할 수 있으며, 헤더와 푸터는 고정되어 있어
                                                            조작이 용이합니다.</p>
                                                        <p class="ui-kit-space-after-4">일관된 사용자 경험을 제공하기 위해 스크롤바의 스타일도 디자인에 맞춰 조정할 수
                                                            있습니다.</p>
                                                        <p class="ui-kit-space-after-4">반복되는 패턴의 텍스트를 통해 스크롤 기능을 시뮬레이션하고 있습니다.</p>
                                                        <p class="ui-kit-space-after-4">다양한 디바이스 환경에서도 내용이 잘 보일 수 있도록 최적화되어 있습니다.</p>
                                                        <p class="ui-kit-space-after-4">중요한 약관동의나 리포트 조회 등에 적합한 형태입니다.</p>
                                                        <p class="ui-kit-space-after-4">계속해서 내용을 추가하여 스크롤이 어떻게 동작하는지 확인해 보세요.</p>
                                                        <p class="ui-kit-space-after-4">UX 디자인 원칙을 준수하여 사용자가 길을 잃지 않도록 돕습니다.</p>
                                                        <p class="ui-kit-space-after-4">모달 내부의 구조적인 정렬을 통해 가독성을 높였습니다.</p>
                                                        <p class="ui-kit-space-after-4">애니메이션 효과와 함께 부드러운 전환을 제공합니다.</p>
                                                        <p class="ui-kit-space-after-4">웹 접근성을 고려하여 키보드 제어로도 본문을 탐색할 수 있습니다.</p>
                                                        <p class="ui-kit-space-after-4">데이터가 많은 경우에도 성능 저하 없이 부드럽게 작동합니다.</p>
                                                        <p class="ui-kit-space-after-4">상세 정보를 효율적으로 전달하는 모던한 방식을 경험해 보세요.</p>
                                                        <p class="ui-kit-space-after-4">레이아웃의 유연성을 통해 다양한 콘텐츠 유형을 수용합니다.</p>
                                                        <p class="ui-kit-space-after-4">사용자 인터랙션에 반응하는 세밀한 디자인 요소들을 포함하고 있습니다.</p>
                                                        <p class="ui-kit-space-after-4">콘텐츠의 흐름을 방해하지 않는 최적의 스크롤 구현 스타일입니다.</p>
                                                        <p class="ui-kit-space-after-4">복잡한 시나리오에서도 명확한 시각적 계층 구조를 유지합니다.</p>
                                                        <p>실제 프로젝트에서 이 모달을 활용하여 콘텐츠를 풍성하게 구성해 보세요.</p>
                                                    </div>

                                                    <div
                                                        class="modal-footer">
                                                        <button type="button"
                                                            class="btn btn-solid-secondary modal-action"
                                                            data-overlay="#scrollable-modal">닫기</button>

                                                        <button type="button"
                                                            class="btn btn-solid-primary modal-action">변경사항
                                                            저장</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- end card-body-->

                    <!-- end card-->

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">모달 위치 (Modal Position)</h4>
                        </div>

                        <div class="card-body">
                            <p class="ui-kit-ink-default-400 ui-kit-space-after-4">모달의 위치를 지정합니다. 상단, 하단 또는 페이지 중앙에 모달을 표시할 수 있습니다.</p>

                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                <!-- Top modal content -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-secondary"
                                        aria-haspopup="dialog" aria-expanded="false" aria-controls="top-modal"
                                        data-overlay="#top-modal">상단 모달</button>

                                    <div id="top-modal"
                                        class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                        role="dialog" tabindex="-1" aria-labelledby="top-modal-label">
                                        <div class="modal-dialog">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="top-modal-label" class="modal-title">모달 제목</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#top-modal">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <h5 class="ui-kit-space-after-2">모달의 텍스트</h5>
                                                    <p>상단에 위치하는 모달의 예시입니다. 알림이나 빠른 설정 사항을 보여주기에 적합합니다.</p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-subtle-light modal-action"
                                                        data-overlay="#top-modal">닫기</button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action">변경사항
                                                        저장</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bottom modal content -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-secondary"
                                        aria-haspopup="dialog" aria-expanded="false" aria-controls="bottom-modal"
                                        data-overlay="#bottom-modal">하단 모달</button>

                                    <div id="bottom-modal"
                                        class="modal-overlay-bottom overlay ui-kit-state-hidden ui-kit-state-disabled-pointer"
                                        role="dialog" tabindex="-1" aria-labelledby="bottom-modal-label">
                                        <div
                                            class="modal-dialog-bottom">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="bottom-modal-label" class="modal-title">
                                                        모달 제목</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#bottom-modal">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <h5 class="ui-kit-space-after-2">모달의 텍스트</h5>
                                                    <p>하단에 위치하는 모달의 예시입니다. 모바일 기기에서의 시트 스타일 UI를 구현할 때 많이 사용됩니다.</p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-subtle-light modal-action"
                                                        data-overlay="#bottom-modal">닫기</button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action">변경사항
                                                        저장</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Center modal content -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-secondary"
                                        aria-haspopup="dialog" aria-expanded="false" aria-controls="centermodal"
                                        data-overlay="#centermodal">중앙 모달</button>

                                    <div id="centermodal"
                                        class="modal-overlay-bottom overlay ui-kit-state-hidden ui-kit-state-disabled-pointer"
                                        role="dialog" tabindex="-1" aria-labelledby="centermodal-label">
                                        <div
                                            class="modal-dialog-center">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="centermodal-label" class="modal-title">모달 제목
                                                    </h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#centermodal">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <h5 class="ui-kit-space-after-2">모달의 텍스트</h5>
                                                    <p>화면 중앙에 정렬되는 기본 스타일의 모달입니다. 가장 보편적으로 사용되는 모달 레이아웃입니다.</p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-subtle-light modal-action"
                                                        data-overlay="#centermodal">닫기</button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action">변경사항
                                                        저장</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- end card-body-->
                    </div>
                    <!-- end card-->

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">다중 모달 (Multiple Modal)</h4>
                        </div>

                        <div class="card-body">
                            <p class="ui-kit-ink-default-400 ui-kit-space-after-4">사용자에게 여러 측면을 안내하거나 단계별 입력을 받기 위해 일련의 모달을 하나씩 표시합니다.</p>
                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                <!-- Modal Heading -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-primary"
                                        aria-haspopup="dialog" aria-expanded="false" aria-controls="multiple-one"
                                        data-overlay="#multiple-one">다중 모달 시작</button>

                                    <div id="multiple-one"
                                        class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                        role="dialog" tabindex="-1" aria-labelledby="multiple-one-label">
                                        <div class="modal-dialog">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="multiple-one-label" class="modal-title">
                                                        첫 번째 모달 제목</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#multiple-one">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <h5 class="ui-kit-space-after-2">단계별 안내</h5>
                                                    <p>연속된 모달을 통해 복잡한 과정을 단계별로 나눠서 설명할 수 있습니다.</p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button"
                                                        class="btn btn-solid-primary"
                                                        aria-haspopup="dialog" aria-expanded="false"
                                                        aria-controls="multiple-two" data-overlay="#multiple-two">다음
                                                        단계</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="multiple-two"
                                        class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                        role="dialog" tabindex="-1" aria-labelledby="multiple-two-label">
                                        <div class="ui-kit-outer-space-3 ui-kit-inline-margin-sm-auto ui-kit-fill-width-sm ui-kit-max-width-sm-lg">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="multiple-two-label" class="modal-title">
                                                        두 번째 모달 제목</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#multiple-two">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <h5 class="ui-kit-space-after-2">완료 단계</h5>
                                                    <p>모든 단계가 완료되었습니다. 최종 확인 후 닫기를 눌러주세요.</p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"
                                                        data-overlay="#multiple-two">닫기</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- end card-body-->
                    </div>
                    <!-- end card-->

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">모달 간 전환 (Toggle Between Modals)</h4>
                        </div>

                        <div class="card-body">
                            <p class="ui-kit-ink-default-400 ui-kit-space-after-4"><code>data-overlay</code> 속성을 스마트하게 배치하여 여러 모달 사이를
                                전환합니다.</p>
                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-secondary"
                                        aria-haspopup="dialog" aria-expanded="false" aria-controls="exampleModalToggle1"
                                        data-overlay="#exampleModalToggle1">첫 번째 모달 열기</button>

                                    <div id="exampleModalToggle1"
                                        class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                        role="dialog" tabindex="-1" aria-labelledby="exampleModalToggle1-label">
                                        <div
                                            class="modal-dialog-center">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="exampleModalToggle1-label" class="modal-title">
                                                        모달 1</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalToggle1">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <p>아래 버튼을 사용하여 이 모달을 닫고 두 번째 모달을 표시합니다.</p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button"
                                                        class="btn btn-solid-primary"
                                                        aria-haspopup="dialog" aria-expanded="false"
                                                        aria-controls="exampleModalToggle2"
                                                        data-overlay="#exampleModalToggle2">
                                                        두 번째 모달 열기
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="exampleModalToggle2"
                                        class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                        role="dialog" tabindex="-1" aria-labelledby="exampleModalToggle2-label">
                                        <div
                                            class="modal-dialog-center">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="exampleModalToggle2-label" class="modal-title">
                                                        모달 2</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalToggle2">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <p>이 모달을 닫고 다시 첫 번째 모달로 돌아갑니다.</p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"
                                                        data-overlay="#exampleModalToggle1">첫 번째로 돌아가기</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- end card-body-->
                    </div>
                    <!-- end card-->

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">전체 화면 모달 (Fullscreen Modal)</h4>
                        </div>

                        <div class="card-body">
                            <p class="ui-kit-ink-default-400 ui-kit-space-after-4">사용자의 뷰포트 전체를 덮는 모달을 띄우는 옵션입니다.</p>
                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                <!-- Full Screen Modal -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-primary"
                                        aria-haspopup="dialog" aria-expanded="false"
                                        aria-controls="fullscreeexampleModal"
                                        data-overlay="#fullscreeexampleModal">전체 화면 모달</button>

                                    <div id="fullscreeexampleModal"
                                        class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                        role="dialog" tabindex="-1" aria-labelledby="fullscreeexampleModal-label">
                                        <div
                                            class="modal-dialog-fluid">
                                            <div
                                                class="modal-content-fullscreen">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="fullscreeexampleModal-label"
                                                        class="modal-title">전체 화면 모달</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#fullscreeexampleModal">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill">내용...</div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-subtle-light modal-action"
                                                        data-overlay="#fullscreeexampleModal">닫기</button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action">변경사항
                                                        저장</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Full screen below sm -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-primary"
                                        aria-haspopup="dialog" aria-expanded="false"
                                        aria-controls="exampleModalFullscreenSm"
                                        data-overlay="#exampleModalFullscreenSm">
                                        sm 미만에서 전체 화면
                                    </button>

                                    <div id="exampleModalFullscreenSm"
                                        class="modal-overlay overlay ui-kit-state-hidden ui-kit-state-disabled-pointer"
                                        role="dialog" tabindex="-1" aria-labelledby="exampleModalFullscreenSm-label">
                                        <div
                                            class="modal-dialog-responsive ui-kit-modal-open-sm-mt ui-kit-inline-margin-sm-auto ui-kit-space-before-sm-0 ui-kit-auto-height-sm ui-kit-max-height-sm-none ui-kit-max-width-sm-lg">
                                            <div
                                                class="modal-content-fluid ui-kit-radius-md ui-kit-frame">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="exampleModalFullscreenSm-label"
                                                        class="modal-title">sm 미만에서 전체 화면</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalFullscreenSm">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill">내용...</div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-subtle-light modal-action"
                                                        data-overlay="#exampleModalFullscreenSm">닫기</button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action">변경사항
                                                        저장</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Full screen below md -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-primary"
                                        aria-haspopup="dialog" aria-expanded="false"
                                        aria-controls="exampleModalFullscreenMd"
                                        data-overlay="#exampleModalFullscreenMd">
                                        md 미만에서 전체 화면
                                    </button>

                                    <div id="exampleModalFullscreenMd"
                                        class="modal-overlay overlay ui-kit-state-hidden ui-kit-state-disabled-pointer"
                                        role="dialog" tabindex="-1" aria-labelledby="exampleModalFullscreenMd-label">
                                        <div
                                            class="modal-dialog-responsive ui-kit-modal-open-md-mt ui-kit-inline-margin-md-auto ui-kit-space-before-md-0 ui-kit-auto-height-md ui-kit-max-height-md-none ui-kit-max-width-md-lg">
                                            <div
                                                class="modal-content-fluid ui-kit-auto-height-md ui-kit-max-height-md-none ui-kit-max-width-md-lg ui-kit-radius-md-xl ui-kit-line-md ui-kit-shadow-md-subtle">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="exampleModalFullscreenMd-label"
                                                        class="modal-title">md 미만에서 전체 화면</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalFullscreenMd">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill">내용...</div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-subtle-light modal-action"
                                                        data-overlay="#exampleModalFullscreenMd">닫기</button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action">변경사항
                                                        저장</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Full screen below lg -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-primary"
                                        aria-haspopup="dialog" aria-expanded="false"
                                        aria-controls="exampleModalFullscreenLg"
                                        data-overlay="#exampleModalFullscreenLg">
                                        lg 미만에서 전체 화면
                                    </button>

                                    <div id="exampleModalFullscreenLg"
                                        class="modal-overlay overlay ui-kit-state-hidden ui-kit-state-disabled-pointer"
                                        role="dialog" tabindex="-1" aria-labelledby="exampleModalFullscreenLg-label">
                                        <div
                                            class="modal-dialog-responsive ui-kit-modal-open-lg-mt ui-kit-inline-margin-lg-auto ui-kit-space-before-lg-0 ui-kit-auto-height-lg ui-kit-max-height-lg-none ui-kit-max-width-lg-lg">
                                            <div
                                                class="modal-content-fluid ui-kit-auto-height-lg ui-kit-max-height-lg-none ui-kit-max-width-lg-lg ui-kit-radius-lg-xl ui-kit-line-lg ui-kit-shadow-lg-subtle">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="exampleModalFullscreenLg-label"
                                                        class="modal-title">lg 미만에서 전체 화면</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalFullscreenLg">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill">내용...</div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-subtle-light modal-action"
                                                        data-overlay="#exampleModalFullscreenLg">닫기</button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action">변경사항
                                                        저장</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Full screen below xl -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-primary"
                                        aria-haspopup="dialog" aria-expanded="false"
                                        aria-controls="exampleModalFullscreenXl"
                                        data-overlay="#exampleModalFullscreenXl">
                                        xl 미만에서 전체 화면
                                    </button>

                                    <div id="exampleModalFullscreenXl"
                                        class="modal-overlay overlay ui-kit-state-hidden ui-kit-state-disabled-pointer"
                                        role="dialog" tabindex="-1" aria-labelledby="exampleModalFullscreenXl-label">
                                        <div
                                            class="modal-dialog-responsive ui-kit-modal-open-xl-mt ui-kit-inline-margin-xl-auto ui-kit-space-before-xl-0 ui-kit-auto-height-xl ui-kit-max-height-xl-none ui-kit-max-width-xl-xl">
                                            <div
                                                class="modal-content-fluid ui-kit-auto-height-xl ui-kit-max-height-xl-none ui-kit-max-width-xl-lg ui-kit-radius-xl-xl ui-kit-line-xl ui-kit-shadow-xl-subtle">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="exampleModalFullscreenXl-label"
                                                        class="modal-title">xl 미만에서 전체 화면</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalFullscreenXl">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill">내용...</div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-subtle-light modal-action"
                                                        data-overlay="#exampleModalFullscreenXl">닫기</button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action">변경사항
                                                        저장</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Full screen below xxl -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-primary"
                                        aria-haspopup="dialog" aria-expanded="false"
                                        aria-controls="exampleModalFullscreenXxl"
                                        data-overlay="#exampleModalFullscreenXxl">
                                        xxl 미만에서 전체 화면
                                    </button>

                                    <div id="exampleModalFullscreenXxl"
                                        class="modal-overlay overlay ui-kit-state-hidden ui-kit-state-disabled-pointer"
                                        role="dialog" tabindex="-1" aria-labelledby="exampleModalFullscreenXxl-label">
                                        <div
                                            class="modal-dialog-responsive ui-kit-modal-open-xl-mt ui-kit-inline-margin-xl-auto ui-kit-space-before-xl-0 ui-kit-auto-height-xl ui-kit-max-height-xl-none ui-kit-max-width-xl-xl">
                                            <div
                                                class="modal-content-fluid ui-kit-auto-height-xl ui-kit-max-height-xl-none ui-kit-max-width-xl-lg ui-kit-radius-xl-xl ui-kit-line-xl ui-kit-shadow-xl-subtle">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="exampleModalFullscreenXxl-label"
                                                        class="modal-title">xxl 미만에서 전체 화면</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalFullscreenXxl">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill">내용...</div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-subtle-light modal-action"
                                                        data-overlay="#exampleModalFullscreenXxl">닫기</button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action">변경사항
                                                        저장</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end card-body-->

                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">고정 배경 (Static Backdrop)</h4>
                        </div>

                        <div class="card-body">
                            <p class="ui-kit-ink-default-400 ui-kit-space-after-4">백드롭이 정적(static)으로 설정되면 모달 외부를 클릭해도 모달이 닫히지 않습니다. 아래 버튼을 눌러 확인해
                                보세요.</p>
                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                <!-- Static Backdrop modal -->
                                <div>
                                    <button type="button" class="btn btn-solid-info"
                                        aria-haspopup="dialog" aria-expanded="false" aria-controls="staticBackdrop"
                                        data-overlay="#staticBackdrop">고정 배경 모달</button>

                                    <div id="staticBackdrop"
                                        class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent" data-overlay-static="true"
                                        role="dialog" tabindex="-1" aria-labelledby="staticBackdrop-label">
                                        <div class="modal-dialog">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="staticBackdrop-label" class="modal-title">
                                                        모달 제목</h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#staticBackdrop">
                                                        <span class="sr-only">닫기</span>
                                                        <i data-icon="tabler:x" class="iconify tabler--x ui-kit-type-xl"></i>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <p>모달 외부를 클릭해도 닫히지 않습니다. ESC 키를 눌러도 닫히지 않도록 설정할 수 있습니다.</p>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button"
                                                        class="btn btn-solid-secondary modal-action"
                                                        data-overlay="#staticBackdrop">닫기</button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action">변경사항
                                                        저장</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end card-body-->
                </div>
                <!-- end card-->

        </div>
        <!-- end card-->
        </div>
    </div>
</div>
</div>
