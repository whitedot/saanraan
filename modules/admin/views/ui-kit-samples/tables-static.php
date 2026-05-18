<div class="ui-kit-sample-section" data-ui-kit-sample="tables-static">
<div class="container-fluid">
                    <div class="ui-grid ui-grid-cols-1 ui-gap-base">
                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">기본 테이블</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table">
                                        <thead class="ui-font-semibold ui-text-xs">
                                            <tr>
                                                <th>상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                                <th>상태</th>
                                                <th>작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ui-font-medium">무선 헤드폰</td>
                                                <td>전자제품</td>
                                                <td>$99.00</td>
                                                <td>120</td>
                                                <td>4.5 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-flex ui-gap-1-5">
                                                    <button
                                                        class="btn btn-sm btn-solid-primary">수정</button>
                                                    <button
                                                        class="btn btn-sm btn-solid-danger">삭제</button>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">러닝화</td>
                                                <td>신발</td>
                                                <td>$59.99</td>
                                                <td>80</td>
                                                <td>4.2 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-flex ui-gap-1-5">
                                                    <button
                                                        class="btn btn-sm btn-solid-primary">수정</button>
                                                    <button
                                                        class="btn btn-sm btn-solid-danger">삭제</button>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">스마트워치</td>
                                                <td>웨어러블</td>
                                                <td>$129.00</td>
                                                <td>0</td>
                                                <td>4.0 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Out of
                                                        Stock</span>
                                                </td>
                                                <td class="ui-flex ui-gap-1-5">
                                                    <button
                                                        class="btn btn-sm btn-solid-primary">수정</button>
                                                    <button
                                                        class="btn btn-sm btn-solid-danger">삭제</button>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">게이밍 마우스</td>
                                                <td>액세서리</td>
                                                <td>$39.50</td>
                                                <td>250</td>
                                                <td>4.7 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-flex ui-gap-1-5">
                                                    <button
                                                        class="btn btn-sm btn-solid-primary">수정</button>
                                                    <button
                                                        class="btn btn-sm btn-solid-danger">삭제</button>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">사무용 의자</td>
                                                <td>가구</td>
                                                <td>$149.00</td>
                                                <td>35</td>
                                                <td>4.3 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-flex ui-gap-1-5">
                                                    <button
                                                        class="btn btn-sm btn-solid-primary">수정</button>
                                                    <button
                                                        class="btn btn-sm btn-solid-danger">삭제</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- end card body-->
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">커스텀 테이블</h4>
                            </div>

                            <div class="table-wrapper">
                                <table class="table">
                                    <thead class="ui-border-default-300 ui-bg-default-100 ui-border-b ui-font-semibold ui-text-xs">
                                        <tr>
                                            <th>상품명</th>
                                            <th>카테고리</th>
                                            <th>가격</th>
                                            <th>재고</th>
                                            <th>평점</th>
                                            <th>상태</th>
                                            <th class="ui-text-end">작업</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="ui-font-medium">블루투스 스피커</td>
                                            <td>오디오</td>
                                            <td>$49.00</td>
                                            <td>200</td>
                                            <td>4.6 ★</td>
                                            <td>
                                                <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                            </td>
                                            <td class="ui-text-end">
                                                <div class="hs-dropdown ui-relative ui-inline-flex">
                                                    <button type="button"
                                                        class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                        aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                        <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                    </button>
                                                    <div class="hs-dropdown-menu" role="menu"
                                                        aria-orientation="vertical">
                                                        <a class="dropdown-item" href="#">
                                                            <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                            개요
                                                        </a>
                                                        <a class="dropdown-item" href="#">
                                                            <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                            수정
                                                        </a>
                                                        <a class="dropdown-item ui-text-danger" href="#">
                                                            <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                            삭제
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td class="ui-font-medium">가죽 지갑</td>
                                            <td>액세서리</td>
                                            <td>$29.99</td>
                                            <td>150</td>
                                            <td>4.3 ★</td>
                                            <td>
                                                <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                            </td>
                                            <td class="ui-text-end">
                                                <div class="hs-dropdown ui-relative ui-inline-flex">
                                                    <button type="button"
                                                        class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                        aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                        <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                    </button>
                                                    <div class="hs-dropdown-menu" role="menu"
                                                        aria-orientation="vertical">
                                                        <div>
                                                            <a class="dropdown-item" href="#">
                                                                <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                개요
                                                            </a>
                                                            <a class="dropdown-item" href="#">
                                                                <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                수정
                                                            </a>
                                                            <a class="dropdown-item ui-text-danger" href="#">
                                                                <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                삭제
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td class="ui-font-medium">피트니스 트래커</td>
                                            <td>웨어러블</td>
                                            <td>$89.00</td>
                                            <td>60</td>
                                            <td>4.1 ★</td>
                                            <td>
                                                <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                    Stock</span>
                                            </td>
                                            <td class="ui-text-end">
                                                <div class="hs-dropdown ui-relative ui-inline-flex">
                                                    <button type="button"
                                                        class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                        aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                        <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                    </button>
                                                    <div class="hs-dropdown-menu" role="menu"
                                                        aria-orientation="vertical">
                                                        <div>
                                                            <a class="dropdown-item" href="#">
                                                                <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                개요
                                                            </a>
                                                            <a class="dropdown-item" href="#">
                                                                <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                수정
                                                            </a>
                                                            <a class="dropdown-item ui-text-danger" href="#">
                                                                <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                삭제
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td class="ui-font-medium">4K 모니터</td>
                                            <td>전자제품</td>
                                            <td>$349.00</td>
                                            <td>30</td>
                                            <td>4.8 ★</td>
                                            <td>
                                                <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                            </td>
                                            <td class="ui-text-end">
                                                <div class="hs-dropdown ui-relative ui-inline-flex">
                                                    <button type="button"
                                                        class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                        aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                        <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                    </button>
                                                    <div class="hs-dropdown-menu" role="menu"
                                                        aria-orientation="vertical">
                                                        <div>
                                                            <a class="dropdown-item" href="#">
                                                                <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                개요
                                                            </a>
                                                            <a class="dropdown-item" href="#">
                                                                <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                수정
                                                            </a>
                                                            <a class="dropdown-item ui-text-danger" href="#">
                                                                <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                삭제
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td class="ui-font-medium">스탠딩 데스크</td>
                                            <td>가구</td>
                                            <td>$499.00</td>
                                            <td>10</td>
                                            <td>4.4 ★</td>
                                            <td>
                                                <span class="badge badge-label ui-bg-info-faint ui-text-info">신규</span>
                                            </td>
                                            <td class="ui-text-end">
                                                <div class="hs-dropdown ui-relative ui-inline-flex">
                                                    <button type="button"
                                                        class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                        aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                        <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                    </button>
                                                    <div class="hs-dropdown-menu" role="menu"
                                                        aria-orientation="vertical">
                                                        <div>
                                                            <a class="dropdown-item" href="#">
                                                                <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                개요
                                                            </a>
                                                            <a class="dropdown-item" href="#">
                                                                <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                수정
                                                            </a>
                                                            <a class="dropdown-item ui-text-danger" href="#">
                                                                <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                삭제
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">테이블 변형</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table">
                                        <thead class="ui-border-default-300 ui-bg-default-100 ui-border-b ui-font-semibold ui-text-xs">
                                            <tr>
                                                <th>상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                                <th>상태</th>
                                                <th class="ui-text-end ui-w-action">작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="ui-border-default-300 ui-bg-primary-soft ui-border-b">
                                                <td class="ui-font-medium">블루투스 스피커</td>
                                                <td>오디오</td>
                                                <td>$49.00</td>
                                                <td>200</td>
                                                <td>4.6 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">가죽 지갑</td>
                                                <td>액세서리</td>
                                                <td>$29.99</td>
                                                <td>150</td>
                                                <td class="ui-bg-warning-soft ui-px-2-25 ui-py-3">4.3 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">피트니스 트래커</td>
                                                <td>웨어러블</td>
                                                <td class="ui-bg-info-soft ui-px-2-25 ui-py-3">$89.00</td>
                                                <td>60</td>
                                                <td>4.1 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                        Stock</span>
                                                </td>
                                                <td class="ui-bg-light-soft ui-px-2-25 ui-py-3 ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">4K 모니터</td>
                                                <td>전자제품</td>
                                                <td>$349.00</td>
                                                <td class="ui-bg-danger-soft ui-px-2-25 ui-py-3">30</td>
                                                <td>4.8 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-bg-dark ui-px-2-25 ui-py-3 ui-font-medium ui-text-white">스탠딩 데스크
                                                </td>
                                                <td>가구</td>
                                                <td>$499.00</td>
                                                <td>10</td>
                                                <td>4.4 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-info-faint ui-text-info">신규</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">줄무늬 행</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table">
                                        <thead class="ui-font-semibold ui-text-xs">
                                            <tr>
                                                <th>상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                                <th>상태</th>
                                                <th class="ui-text-end ui-w-action">작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="ui-border-default-300 ui-row-odd-default ui-border-b">
                                                <td class="ui-font-medium">블루투스 스피커</td>
                                                <td>오디오</td>
                                                <td>$49.00</td>
                                                <td>200</td>
                                                <td>4.6 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr class="ui-border-default-300 ui-row-odd-default ui-border-b">
                                                <td class="ui-font-medium">가죽 지갑</td>
                                                <td>액세서리</td>
                                                <td>$29.99</td>
                                                <td>150</td>
                                                <td>4.3 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr class="ui-border-default-300 ui-row-odd-default ui-border-b">
                                                <td class="ui-font-medium">피트니스 트래커</td>
                                                <td>웨어러블</td>
                                                <td>$89.00</td>
                                                <td>60</td>
                                                <td>4.1 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                        Stock</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">줄무늬 열</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table">
                                        <thead class="ui-font-semibold ui-text-xs">
                                            <tr>
                                                <th>상품명</th>
                                                <td class="ui-bg-default-100 ui-p-2 ui-text-start">카테고리</td>
                                                <th>가격</th>
                                                <td class="ui-bg-default-100 ui-p-2 ui-text-start">재고</td>
                                                <th>평점</th>
                                                <td class="ui-bg-default-100 ui-p-2 ui-text-start">상태</td>
                                                <th class="ui-text-end ui-w-action">작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ui-font-medium">블루투스 스피커</td>
                                                <td class="ui-bg-default-100 ui-px-2-25 ui-py-3">오디오</td>
                                                <td>$49.00</td>
                                                <td class="ui-bg-default-100 ui-px-2-25 ui-py-3">200</td>
                                                <td>4.6 ★</td>
                                                <td class="ui-bg-default-100 ui-px-2-25 ui-py-3">
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">가죽 지갑</td>
                                                <td class="ui-bg-default-100 ui-px-2-25 ui-py-3">액세서리</td>
                                                <td>$29.99</td>
                                                <td class="ui-bg-default-100 ui-px-2-25 ui-py-3">150</td>
                                                <td>4.3 ★</td>
                                                <td class="ui-bg-default-100 ui-px-2-25 ui-py-3">
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">피트니스 트래커</td>
                                                <td class="ui-bg-default-100 ui-px-2-25 ui-py-3">웨어러블</td>
                                                <td>$89.00</td>
                                                <td class="ui-bg-default-100 ui-px-2-25 ui-py-3">60</td>
                                                <td>4.1 ★</td>
                                                <td class="ui-bg-default-100 ui-px-2-25 ui-py-3">
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                        Stock</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">마우스 오버 행</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table table-hover">
                                        <thead class="ui-font-semibold ui-text-xs">
                                            <tr>
                                                <th>상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                                <th>상태</th>
                                                <th class="ui-text-end">작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ui-font-medium">블루투스 스피커</td>
                                                <td>오디오</td>
                                                <td>$49.00</td>
                                                <td>200</td>
                                                <td>4.6 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">가죽 지갑</td>
                                                <td>액세서리</td>
                                                <td>$29.99</td>
                                                <td>150</td>
                                                <td>4.3 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">피트니스 트래커</td>
                                                <td>웨어러블</td>
                                                <td>$89.00</td>
                                                <td>60</td>
                                                <td>4.1 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                        Stock</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">활성 Tables</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table">
                                        <thead class="ui-font-semibold ui-text-xs">
                                            <tr>
                                                <th>상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                                <th>상태</th>
                                                <th class="ui-text-end">작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="ui-border-default-300 ui-bg-default-100 ui-border-b">
                                                <td class="ui-font-medium">블루투스 스피커</td>
                                                <td>오디오</td>
                                                <td>$49.00</td>
                                                <td>200</td>
                                                <td>4.6 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">가죽 지갑</td>
                                                <td>액세서리</td>
                                                <td>$29.99</td>
                                                <td>150</td>
                                                <td>4.3 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">피트니스 트래커</td>
                                                <td>웨어러블</td>
                                                <td class="ui-bg-default-100 ui-px-2 ui-py-3">$89.00</td>
                                                <td>60</td>
                                                <td>4.1 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                        Stock</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">테두리가 있는 테이블</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table">
                                        <thead class="ui-border-default-300 ui-border ui-font-semibold ui-text-xs">
                                            <tr class="ui-divide-default-300 ui-divide-x">
                                                <th>상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                                <th>상태</th>
                                                <th class="ui-text-end" style="width: 1%">작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="ui-border-default-300 ui-divide-default-300 ui-divide-x ui-border">
                                                <td class="ui-font-medium">블루투스 스피커</td>
                                                <td>오디오</td>
                                                <td>$49.00</td>
                                                <td>200</td>
                                                <td>4.6 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr class="ui-border-default-300 ui-divide-default-300 ui-divide-x ui-border">
                                                <td class="ui-font-medium">가죽 지갑</td>
                                                <td>액세서리</td>
                                                <td>$29.99</td>
                                                <td>150</td>
                                                <td>4.3 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr class="ui-border-default-300 ui-divide-default-300 ui-divide-x ui-border">
                                                <td class="ui-font-medium">피트니스 트래커</td>
                                                <td>웨어러블</td>
                                                <td>$89.00</td>
                                                <td>60</td>
                                                <td>4.1 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                        Stock</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">테두리 없는 테이블</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table">
                                        <thead class="ui-font-semibold ui-text-xs">
                                            <tr>
                                                <th>상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                                <th>상태</th>
                                                <th class="ui-text-end" style="width: 1%">작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ui-font-medium">블루투스 스피커</td>
                                                <td>오디오</td>
                                                <td>$49.00</td>
                                                <td>200</td>
                                                <td>4.6 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">가죽 지갑</td>
                                                <td>액세서리</td>
                                                <td>$29.99</td>
                                                <td>150</td>
                                                <td>4.3 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">피트니스 트래커</td>
                                                <td>웨어러블</td>
                                                <td>$89.00</td>
                                                <td>60</td>
                                                <td>4.1 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                        Stock</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">작은 테이블</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table">
                                        <thead class="ui-font-semibold ui-text-xs">
                                            <tr>
                                                <th>상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ui-p-2 ui-font-medium">블루투스 스피커</td>
                                                <td class="ui-p-2">오디오</td>
                                                <td class="ui-p-2">$49.00</td>
                                                <td class="ui-p-2">200</td>
                                                <td class="ui-p-2">4.6 ★</td>
                                            </tr>

                                            <tr>
                                                <td class="ui-p-2 ui-font-medium">가죽 지갑</td>
                                                <td class="ui-p-2">액세서리</td>
                                                <td class="ui-p-2">$29.99</td>
                                                <td class="ui-p-2">150</td>
                                                <td class="ui-p-2">4.3 ★</td>
                                            </tr>

                                            <tr>
                                                <td class="ui-p-2 ui-font-medium">피트니스 트래커</td>
                                                <td class="ui-p-2">웨어러블</td>
                                                <td class="ui-p-2">$89.00</td>
                                                <td class="ui-p-2">60</td>
                                                <td class="ui-p-2">4.1 ★</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">테이블 그룹 구분선</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table">
                                        <thead class="ui-border-default-600 ui-border-b-2 ui-font-semibold ui-text-xs">
                                            <tr>
                                                <th>상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                                <th>상태</th>
                                                <th class="ui-text-end">작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ui-font-medium">블루투스 스피커</td>
                                                <td>오디오</td>
                                                <td>$49.00</td>
                                                <td>200</td>
                                                <td>4.6 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">가죽 지갑</td>
                                                <td>액세서리</td>
                                                <td>$29.99</td>
                                                <td>150</td>
                                                <td>4.3 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">피트니스 트래커</td>
                                                <td>웨어러블</td>
                                                <td>$89.00</td>
                                                <td>60</td>
                                                <td>4.1 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                        Stock</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">중첩 테이블</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table">
                                        <thead class="ui-font-semibold ui-text-xs">
                                            <tr>
                                                <th>상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                                <th>상태</th>
                                                <th class="ui-text-end">작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ui-font-medium">블루투스 스피커</td>
                                                <td>오디오</td>
                                                <td>$49.00</td>
                                                <td>200</td>
                                                <td>4.6 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td colspan="7" class="ui-border-default-300 ui-w-full ui-border-b ui-p-5">
                                                    <table class="table">
                                                        <thead class="ui-font-semibold ui-text-xs">
                                                            <tr>
                                                                <th>Variant</th>
                                                                <th>Color</th>
                                                                <th>SKU</th>
                                                                <th>재고</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td class="ui-p-2 ui-font-medium">Mini</td>
                                                                <td class="ui-p-2">Black</td>
                                                                <td class="ui-p-2">SPK-M-BLK</td>
                                                                <td class="ui-p-2">80</td>
                                                            </tr>

                                                            <tr>
                                                                <td class="ui-p-2 ui-font-medium">Standard</td>
                                                                <td class="ui-p-2">Blue</td>
                                                                <td class="ui-p-2">SPK-S-BLU</td>
                                                                <td class="ui-p-2">120</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">가죽 지갑</td>
                                                <td>액세서리</td>
                                                <td>$29.99</td>
                                                <td>150</td>
                                                <td>4.3 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">피트니스 트래커</td>
                                                <td>웨어러블</td>
                                                <td>$89.00</td>
                                                <td>60</td>
                                                <td>4.1 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                        Stock</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">테이블 헤더</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="table">
                                        <thead class="ui-bg-dark">
                                            <tr class="ui-children-text-white">
                                                <th class="ui-text-start">상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                                <th>상태</th>
                                                <th class="ui-text-end">작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ui-font-medium">블루투스 스피커</td>
                                                <td>오디오</td>
                                                <td>$49.00</td>
                                                <td>200</td>
                                                <td>4.6 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">가죽 지갑</td>
                                                <td>액세서리</td>
                                                <td>$29.99</td>
                                                <td>150</td>
                                                <td>4.3 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">피트니스 트래커</td>
                                                <td>웨어러블</td>
                                                <td>$89.00</td>
                                                <td>60</td>
                                                <td>4.1 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                        Stock</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->

                        <section class="ui-kit-example">
                            <div class="ui-kit-example-header">
                                <h4 class="ui-kit-example-title">캡션</h4>
                            </div>

                            <div class="ui-kit-example-body">
                                <div class="table-wrapper">
                                    <table class="ui-w-full table table-hover">
                                        <caption class="ui-text-default-400 ui-caption-bottom ui-py-3 ui-text-start">
                                            이커머스 상품 목록
                                        </caption>
                                        <thead class="ui-font-semibold ui-text-xs">
                                            <tr>
                                                <th>상품명</th>
                                                <th>카테고리</th>
                                                <th>가격</th>
                                                <th>재고</th>
                                                <th>평점</th>
                                                <th>상태</th>
                                                <th class="ui-p-2 ui-text-end">작업</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ui-font-medium">블루투스 스피커</td>
                                                <td>오디오</td>
                                                <td>$49.00</td>
                                                <td>200</td>
                                                <td>4.6 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">가죽 지갑</td>
                                                <td>액세서리</td>
                                                <td>$29.99</td>
                                                <td>150</td>
                                                <td>4.3 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-success-faint ui-text-success">활성</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td class="ui-font-medium">피트니스 트래커</td>
                                                <td>웨어러블</td>
                                                <td>$89.00</td>
                                                <td>60</td>
                                                <td>4.1 ★</td>
                                                <td>
                                                    <span class="badge badge-label ui-bg-warning-faint ui-text-warning">Limited
                                                        Stock</span>
                                                </td>
                                                <td class="ui-text-end">
                                                    <div class="hs-dropdown ui-relative ui-inline-flex">
                                                        <button type="button"
                                                            class="hs-dropdown-toggle ui-flex ui-h-7-5 ui-w-11-25 ui-items-center ui-justify-center ui-font-semibold"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown" data-dropdown-placement="bottom-end">
                                                            <i data-icon="tabler:dots-vertical" class="iconify tabler--dots-vertical ui-text-xl"></i>
                                                        </button>
                                                        <div class="hs-dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:eye" class="iconify tabler--eye ui-text-xs"></i>
                                                                    개요
                                                                </a>
                                                                <a class="dropdown-item" href="#">
                                                                    <i data-icon="tabler:edit" class="iconify tabler--edit ui-text-xs"></i>
                                                                    수정
                                                                </a>
                                                                <a class="dropdown-item ui-text-danger" href="#">
                                                                    <i data-icon="tabler:trash" class="iconify tabler--trash ui-text-xs"></i>
                                                                    삭제
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        <!-- end card -->
                    </div>
</div>
</div>
