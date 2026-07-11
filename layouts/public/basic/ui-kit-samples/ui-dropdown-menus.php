<div class="ui-kit-sample-section" data-ui-kit-sample="ui-dropdown-menus">
<div class="container-fluid">
                    <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-xl-2 ui-kit-gap-base">
                        <!-- 기본 메뉴 -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">기본 메뉴</h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4">아바타, 상태 배지, 액션 행, 인라인 토글을 포함한 드롭다운 메뉴 스타일입니다.</p>

                                <div class="dropdown-menu dropdown-menu-profile dropdown-menu-profile-preview" role="group" aria-label="회원 드롭다운 메뉴 샘플">
                                    <div class="dropdown-profile-header">
                                        <span class="dropdown-profile-avatar member-avatar-color-4" aria-hidden="true">섭</span>
                                        <span class="dropdown-profile-identity">
                                            <strong class="dropdown-profile-name">섭웨이b</strong>
                                            <span class="dropdown-profile-email">admin@saanraan.com</span>
                                        </span>
                                        <span class="badge badge-soft-warning badge-pill">PRO</span>
                                    </div>

                                    <label class="dropdown-profile-item">
                                        <?php echo sr_material_icon_html('dark_mode', '', '다크 모드'); ?>
                                        <span class="dropdown-profile-item-text">다크 모드</span>
                                        <span class="dropdown-profile-item-meta">
                                            <input type="checkbox" class="form-switch form-switch-light" aria-label="다크 모드">
                                        </span>
                                    </label>

                                    <hr class="dropdown-profile-divider">

                                    <a class="dropdown-profile-item" href="#">
                                        <?php echo sr_material_icon_html('monitoring', '', '활동'); ?>
                                        <span class="dropdown-profile-item-text">활동</span>
                                        <span class="dropdown-profile-item-meta"></span>
                                    </a>
                                    <a class="dropdown-profile-item is-active" href="#">
                                        <?php echo sr_material_icon_html('hub', '', '연동'); ?>
                                        <span class="dropdown-profile-item-text">연동</span>
                                        <span class="dropdown-profile-item-meta"><span class="material-symbols-outlined" aria-hidden="true" data-sr-material-icon>chevron_right</span></span>
                                    </a>
                                    <a class="dropdown-profile-item" href="#">
                                        <?php echo sr_material_icon_html('settings', '', '설정'); ?>
                                        <span class="dropdown-profile-item-text">설정</span>
                                        <span class="dropdown-profile-item-meta"></span>
                                    </a>

                                    <hr class="dropdown-profile-divider">

                                    <a class="dropdown-profile-item" href="#">
                                        <?php echo sr_material_icon_html('logout', '', '로그아웃'); ?>
                                        <span class="dropdown-profile-item-text">로그아웃</span>
                                        <span class="dropdown-profile-item-meta"></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
</div>
</div>
