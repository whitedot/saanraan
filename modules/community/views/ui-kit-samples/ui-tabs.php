<div class="ui-kit-sample-section" data-ui-kit-sample="ui-tabs">
<div class="container-fluid">
                    <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-xl-2 ui-kit-gap-base">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.default.tabs.9fc5b992')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="sample-note ui-kit-space-after-4"><?php echo sr_e(sr_t('admin::ui.text.76d77ea3')); ?></p>
                                <div>
                                    <nav class="tab-nav" aria-label="Tabs" role="tablist"
                                        data-tab-select="#tab-select">
                                        <button type="button"
                                            class="tab-trigger-underline active"
                                            id="overview" aria-selected="true" data-tab="#default-overview"
                                            aria-controls="default-overview" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.dc015df6')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-underline"
                                            id="activity" aria-selected="false" data-tab="#default-activity"
                                            aria-controls="default-activity" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.d4b47a5c')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-underline"
                                            id="settings" aria-selected="false" data-tab="#default-settings"
                                            aria-controls="default-settings" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.settings.115bced4')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-underline"
                                            id="disabled" aria-selected="false" data-tab="#default-Disabled"
                                            aria-controls="default-Disabled" role="tab" disabled>
                                            <?php echo sr_e(sr_t('admin::ui.text.9fd8413e')); ?>
                                        </button>
                                    </nav>
                                </div>

                                <div class="tab-panel-space">
                                    <div id="default-overview" role="tabpanel"
                                        aria-labelledby="overview">
                                        <p><?php echo sr_e(sr_t('admin::ui.dashboard.status.login.active.74ffa722')); ?></p>
                                    </div>

                                    <div id="default-activity" class="sample-hidden" role="tabpanel" aria-labelledby="activity">
                                        <p><?php echo sr_e(sr_t('admin::ui.all.status.notification.status.b3013a7d')); ?></p>
                                    </div>

                                    <div id="default-settings" class="sample-hidden" role="tabpanel"
                                        aria-labelledby="settings">
                                        <p><?php echo sr_e(sr_t('admin::ui.notification.settings.settings.active.251ac9a5')); ?></p>
                                    </div>
                                </div>
                            </div>
                            <!-- end card-body-->
                        </div>
                        <!-- end card-->

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.tabs.justified.6504c838')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="sample-note ui-kit-space-after-4"><?php echo sr_e(sr_t('admin::ui.active.all.all.d71866ef')); ?></p>

                                <div>
                                    <nav class="tab-nav-justified" aria-label="Tabs" role="tablist"
                                        data-tab-select="#tab-select">
                                        <button type="button"
                                            class="tab-trigger-underline-justified active"
                                            id="overview-1" aria-selected="true" data-tab="#overview1"
                                            aria-controls="overview1" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.dc015df6')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-underline-justified"
                                            id="profile-1" aria-selected="false" data-tab="#profile1"
                                            aria-controls="profile1" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.4a784986')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-underline-justified"
                                            id="settings-1" aria-selected="false" data-tab="#settings1"
                                            aria-controls="settings1" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.settings.115bced4')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-underline-justified"
                                            id="projects-1" aria-selected="false" data-tab="#projects1"
                                            aria-controls="projects1" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.f05481be')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-underline-justified"
                                            id="Support-1" aria-selected="false" data-tab="#Support1"
                                            aria-controls="Support1" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.0e7cfc63')); ?>
                                        </button>
                                    </nav>
                                </div>

                                <div class="tab-panel-space">
                                    <div id="overview1" role="tabpanel" aria-labelledby="overview-1">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.2d83d75e')); ?></p>
                                    </div>

                                    <div id="profile1" class="sample-hidden" role="tabpanel" aria-labelledby="profile-1">
                                        <p><?php echo sr_e(sr_t('admin::ui.settings.password.settings.3cf58eeb')); ?></p>
                                    </div>

                                    <div id="settings1" class="sample-hidden" role="tabpanel" aria-labelledby="settings-1">
                                        <p><?php echo sr_e(sr_t('admin::ui.settings.notification.settings.6ae8f9f3')); ?></p>
                                    </div>

                                    <div id="projects1" class="sample-hidden" role="tabpanel" aria-labelledby="projects-1">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.6b7a6f12')); ?></p>
                                    </div>

                                    <div id="Support1" class="sample-hidden" role="tabpanel" aria-labelledby="Support-1">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.4f1ecbca')); ?></p>
                                    </div>
                                </div>
                            </div>
                            <!-- end card-body-->
                        </div>
                        <!-- end card-->

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.9d8322f6')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="sample-note ui-kit-space-after-4"><?php echo sr_e(sr_t('admin::ui.flex.1ff5c66c')); ?></p>

                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-md-4 ui-kit-gap-base">
                                    <nav aria-label="Tabs" role="tablist" data-tab-select="#tab-select">
                                        <button type="button"
                                            class="tab-trigger-pill-primary active"
                                            id="vertical-overview" aria-selected="true" data-tab="#v-pills-home-tab"
                                            aria-controls="v-pills-home-tab" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.dc015df6')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-pill-primary"
                                            id="vertical-activity" aria-selected="false"
                                            data-tab="#v-pills-profile-tab" aria-controls="v-pills-profile-tab"
                                            role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.d4b47a5c')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-pill-primary"
                                            id="vertical-settings" aria-selected="false"
                                            data-tab="#v-pills-settings-tab" aria-controls="v-pills-settings-tab"
                                            role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.settings.115bced4')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-pill-primary"
                                            id="vertical-disabled" aria-selected="false"
                                            data-tab="#v-pills-projects-tab" aria-controls="v-pills-projects-tab"
                                            role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.f05481be')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-pill-primary"
                                            id="vertical-support" aria-selected="false"
                                            data-tab="#v-pills-support-tab" aria-controls="v-pills-support-tab"
                                            role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.0e7cfc63')); ?>
                                        </button>
                                    </nav>

                                    <div class="ui-kit-column-md-3">
                                        <div id="v-pills-home-tab" role="tabpanel" aria-labelledby="vertical-overview">
                                            <p class="ui-kit-space-after-2"><?php echo sr_e(sr_t('admin::ui.dashboard.9a160551')); ?></p>
                                            <ul class="ui-kit-space-after-4 ui-kit-list-disc ui-kit-stack-1 ui-kit-start-pad-8">
                                                <li><?php echo sr_e(sr_t('admin::ui.all.status.bf90fe33')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.8dedca4e')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.c50f9982')); ?></li>
                                            </ul>
                                            <p><?php echo sr_e(sr_t('admin::ui.dashboard.2e6e72bc')); ?></p>
                                        </div>

                                        <div id="v-pills-profile-tab" class="sample-hidden" role="tabpanel"
                                            aria-labelledby="vertical-activity">
                                            <p class="ui-kit-space-after-2"><?php echo sr_e(sr_t('admin::ui.text.f2a801b9')); ?></p>
                                            <ul class="ui-kit-space-after-4 ui-kit-list-disc ui-kit-stack-1 ui-kit-start-pad-8">
                                                <li><?php echo sr_e(sr_t('admin::ui.name.email.95480bf4')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.password.bf1d4719')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.98b83b01')); ?></li>
                                            </ul>
                                            <p><?php echo sr_e(sr_t('admin::ui.status.0577e68a')); ?></p>
                                        </div>

                                        <div id="v-pills-settings-tab" class="sample-hidden" role="tabpanel"
                                            aria-labelledby="vertical-settings">
                                            <p class="ui-kit-space-after-2"><?php echo sr_e(sr_t('admin::ui.notification.settings.active.cf8a0dbd')); ?></p>
                                            <ul class="ui-kit-space-after-4 ui-kit-list-disc ui-kit-stack-1 ui-kit-start-pad-8">
                                                <li><?php echo sr_e(sr_t('admin::ui.select.2b63f28a')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.email.notification.e0391734')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.d155b96c')); ?></li>
                                            </ul>
                                            <p><?php echo sr_e(sr_t('admin::ui.settings.5e4833a3')); ?></p>
                                        </div>

                                        <div id="v-pills-projects-tab" class="sample-hidden" role="tabpanel"
                                            aria-labelledby="vertical-disabled">
                                            <p class="ui-kit-space-after-2"><?php echo sr_e(sr_t('admin::ui.text.81672289')); ?></p>
                                            <ul class="ui-kit-space-after-4 ui-kit-list-disc ui-kit-stack-1 ui-kit-start-pad-8">
                                                <li><?php echo sr_e(sr_t('admin::ui.text.e41fb787')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.1d692400')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.25e193b8')); ?></li>
                                            </ul>
                                            <p><?php echo sr_e(sr_t('admin::ui.active.0bf30f64')); ?></p>
                                        </div>

                                        <div id="v-pills-support-tab" class="sample-hidden" role="tabpanel"
                                            aria-labelledby="vertical-support">
                                            <p class="ui-kit-space-after-2"><?php echo sr_e(sr_t('admin::ui.text.299130dd')); ?></p>
                                            <ul class="ui-kit-space-after-4 ui-kit-list-disc ui-kit-stack-1 ui-kit-start-pad-8">
                                                <li><?php echo sr_e(sr_t('admin::ui.text.950ef2ef')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.e72037d3')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.4dc94e7e')); ?></li>
                                            </ul>
                                            <p><?php echo sr_e(sr_t('admin::ui.text.745e026d')); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <!-- end card-body-->
                            </div>
                        </div>
                        <!-- end card-->

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.pills.a7b47b14')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="sample-note ui-kit-space-after-4"><?php echo sr_e(sr_t('admin::ui.pill.status.58fdd3c1')); ?></p>

                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-md-4 ui-kit-gap-base">
                                    <div class="ui-kit-column-md-3">
                                        <div id="v-pills-home-right" role="tabpanel" aria-labelledby="right-overview">
                                            <p class="ui-kit-space-after-2"><?php echo sr_e(sr_t('admin::ui.dashboard.9a160551')); ?></p>
                                            <ul class="ui-kit-space-after-4 ui-kit-list-disc ui-kit-stack-1 ui-kit-start-pad-8">
                                                <li><?php echo sr_e(sr_t('admin::ui.all.status.bf90fe33')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.8dedca4e')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.c50f9982')); ?></li>
                                            </ul>
                                            <p><?php echo sr_e(sr_t('admin::ui.dashboard.2e6e72bc')); ?></p>
                                        </div>

                                        <div id="v-pills-profile-right" class="sample-hidden" role="tabpanel"
                                            aria-labelledby="right-activity">
                                            <p class="ui-kit-space-after-2"><?php echo sr_e(sr_t('admin::ui.text.f2a801b9')); ?></p>
                                            <ul class="ui-kit-space-after-4 ui-kit-list-disc ui-kit-stack-1 ui-kit-start-pad-8">
                                                <li><?php echo sr_e(sr_t('admin::ui.name.email.95480bf4')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.password.bf1d4719')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.98b83b01')); ?></li>
                                            </ul>
                                            <p><?php echo sr_e(sr_t('admin::ui.status.0577e68a')); ?></p>
                                        </div>

                                        <div id="v-pills-settings-right" class="sample-hidden" role="tabpanel"
                                            aria-labelledby="right-settings">
                                            <p class="ui-kit-space-after-2"><?php echo sr_e(sr_t('admin::ui.notification.settings.active.cf8a0dbd')); ?></p>
                                            <ul class="ui-kit-space-after-4 ui-kit-list-disc ui-kit-stack-1 ui-kit-start-pad-8">
                                                <li><?php echo sr_e(sr_t('admin::ui.select.2b63f28a')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.email.notification.e0391734')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.d155b96c')); ?></li>
                                            </ul>
                                            <p><?php echo sr_e(sr_t('admin::ui.settings.5e4833a3')); ?></p>
                                        </div>

                                        <div id="v-pills-projects-right" class="sample-hidden" role="tabpanel"
                                            aria-labelledby="right-disabled">
                                            <p class="ui-kit-space-after-2"><?php echo sr_e(sr_t('admin::ui.text.81672289')); ?></p>
                                            <ul class="ui-kit-space-after-4 ui-kit-list-disc ui-kit-stack-1 ui-kit-start-pad-8">
                                                <li><?php echo sr_e(sr_t('admin::ui.text.e41fb787')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.1d692400')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.25e193b8')); ?></li>
                                            </ul>
                                            <p><?php echo sr_e(sr_t('admin::ui.active.0bf30f64')); ?></p>
                                        </div>

                                        <div id="v-pills-support-right" class="sample-hidden" role="tabpanel"
                                            aria-labelledby="right-support">
                                            <p class="ui-kit-space-after-2"><?php echo sr_e(sr_t('admin::ui.text.299130dd')); ?></p>
                                            <ul class="ui-kit-space-after-4 ui-kit-list-disc ui-kit-stack-1 ui-kit-start-pad-8">
                                                <li><?php echo sr_e(sr_t('admin::ui.text.950ef2ef')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.e72037d3')); ?></li>
                                                <li><?php echo sr_e(sr_t('admin::ui.text.4dc94e7e')); ?></li>
                                            </ul>
                                            <p><?php echo sr_e(sr_t('admin::ui.text.745e026d')); ?></p>
                                        </div>
                                    </div>

                                    <nav aria-label="Tabs" role="tablist" data-tab-select="#tab-select">
                                        <button type="button"
                                            class="tab-trigger-pill-secondary active"
                                            id="right-overview" aria-selected="true" data-tab="#v-pills-home-right"
                                            aria-controls="v-pills-home-right" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.dc015df6')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-pill-secondary"
                                            id="right-activity" aria-selected="false"
                                            data-tab="#v-pills-profile-right" aria-controls="v-pills-profile-right"
                                            role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.d4b47a5c')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-pill-secondary"
                                            id="right-settings" aria-selected="false"
                                            data-tab="#v-pills-settings-right" aria-controls="v-pills-settings-right"
                                            role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.settings.115bced4')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-pill-secondary"
                                            id="right-disabled" aria-selected="false"
                                            data-tab="#v-pills-projects-right" aria-controls="v-pills-projects-right"
                                            role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.f05481be')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-pill-secondary"
                                            id="right-support" aria-selected="false"
                                            data-tab="#v-pills-support-right" aria-controls="v-pills-support-right"
                                            role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.0e7cfc63')); ?>
                                        </button>
                                    </nav>
                                </div>
                            </div>
                        </div>
                        <!-- end card-->

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.tabs.bordered.1cd533a1')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="sample-note ui-kit-space-after-4"><?php echo sr_e(sr_t('admin::ui.status.55c3c3ba')); ?></p>

                                <div>
                                    <nav class="tab-nav-bordered" aria-label="Tabs"
                                        role="tablist" data-tab-select="#tab-select">
                                        <button type="button"
                                            class="tab-trigger-line-primary active"
                                            id="home-border" aria-selected="true" data-tab="#home-b1"
                                            aria-controls="home-b1" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.034999fa')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-line-primary"
                                            id="profile-border" aria-selected="false" data-tab="#profile-b1"
                                            aria-controls="profile-b1" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.4a784986')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-line-primary"
                                            id="settings-border" aria-selected="false" data-tab="#settings-b1"
                                            aria-controls="settings-b1" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.settings.115bced4')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-line-primary"
                                            id="about-border" aria-selected="false" data-tab="#about-b1"
                                            aria-controls="about-b1" role="tab">
                                            <?php echo sr_e(sr_t('admin::ui.text.b8cf07ac')); ?>
                                        </button>
                                    </nav>
                                </div>

                                <div class="tab-panel-space">
                                    <div id="home-b1" role="tabpanel" aria-labelledby="home-border">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.4d91b0d6')); ?></p>
                                    </div>

                                    <div id="profile-b1" class="sample-hidden" role="tabpanel" aria-labelledby="profile-border">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.0a90fdd6')); ?></p>
                                    </div>

                                    <div id="settings-b1" class="sample-hidden" role="tabpanel"
                                        aria-labelledby="settings-border">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.a9441910')); ?></p>
                                    </div>

                                    <div id="about-b1" class="sample-hidden" role="tabpanel" aria-labelledby="about-border">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.661a1ea2')); ?></p>
                                    </div>
                                </div>
                            </div>
                            <!-- end card-body-->
                        </div>
                        <!-- end card-->

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.tabs.with.colored.border.dfdbfa28')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="sample-note ui-kit-space-after-4"><?php echo sr_e(sr_t('admin::ui.select.active.26177deb')); ?></p>

                                <div>
                                    <nav class="tab-nav-bordered-tight" aria-label="Tabs" role="tablist"
                                        data-tab-select="#tab-select">
                                        <button type="button"
                                            class="tab-trigger-line-danger active"
                                            id="home-icon" aria-selected="true" data-tab="#home-ib1"
                                            aria-controls="home-ib1" role="tab">
                                            <?php echo sr_material_icon_html('home', '', sr_t('admin::ui.text.034999fa')); ?>
                                            <div class="tabs-hidden-until-md"><?php echo sr_e(sr_t('admin::ui.text.034999fa')); ?></div>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-line-danger"
                                            id="profile-icon" aria-selected="false" data-tab="#profile-ib1"
                                            aria-controls="profile-ib1" role="tab">
                                            <?php echo sr_material_icon_html('person', '', sr_t('admin::ui.active.1ac6e422')); ?>
                                            <div class="tabs-hidden-until-md"><?php echo sr_e(sr_t('admin::ui.text.4a784986')); ?></div>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-line-danger"
                                            id="settings-icon" aria-selected="false" data-tab="#settings-ib1"
                                            aria-controls="settings-ib1" role="tab">
                                            <?php echo sr_material_icon_html('settings', '', sr_t('admin::ui.settings.115bced4')); ?>
                                            <div class="tabs-hidden-until-md"><?php echo sr_e(sr_t('admin::ui.settings.115bced4')); ?></div>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-line-danger"
                                            id="about-icon" aria-selected="false" data-tab="#about-ib1"
                                            aria-controls="about-ib1" role="tab">
                                            <?php echo sr_material_icon_html('warning', '', sr_t('admin::ui.text.6c0f7510')); ?>
                                            <div class="tabs-hidden-until-md"><?php echo sr_e(sr_t('admin::ui.text.b8cf07ac')); ?></div>
                                        </button>
                                    </nav>
                                </div>

                                <div class="tab-panel-space">
                                    <div id="home-ib1" role="tabpanel" aria-labelledby="home-icon">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.002c4c0a')); ?></p>
                                    </div>

                                    <div id="profile-ib1" class="sample-hidden" role="tabpanel" aria-labelledby="profile-icon">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.d0eb5a5a')); ?></p>
                                    </div>

                                    <div id="settings-ib1" class="sample-hidden" role="tabpanel"
                                        aria-labelledby="settings-icon">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.63a0eaba')); ?></p>
                                    </div>

                                    <div id="about-ib1" class="sample-hidden" role="tabpanel" aria-labelledby="about-icon">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.6e9916b7')); ?></p>
                                    </div>
                                </div>
                            </div>
                            <!-- end card-body-->
                        </div>
                        <!-- end card-->

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.icons.tabs.d011b88f')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="sample-note ui-kit-space-after-4"><?php echo sr_e(sr_t('admin::ui.status.active.700c0ef0')); ?></p>

                                <div>
                                    <nav class="tab-nav-bordered" aria-label="Tabs"
                                        role="tablist" data-tab-select="#tab-select">
                                        <button type="button"
                                            class="tab-trigger-line-success active"
                                            id="home-icon-2" aria-selected="true" data-tab="#home-i1"
                                            aria-controls="home-i1" role="tab">
                                            <?php echo sr_material_icon_html('home', '', sr_t('admin::ui.text.034999fa')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-line-success"
                                            id="profile-icon-2" aria-selected="false" data-tab="#profile-i1"
                                            aria-controls="profile-i1" role="tab">
                                            <?php echo sr_material_icon_html('person', '', sr_t('admin::ui.active.1ac6e422')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-line-success"
                                            id="settings-icon-2" aria-selected="false" data-tab="#settings-i1"
                                            aria-controls="settings-i1" role="tab">
                                            <?php echo sr_material_icon_html('settings', '', sr_t('admin::ui.settings.115bced4')); ?>
                                        </button>

                                        <button type="button"
                                            class="tab-trigger-line-success"
                                            id="about-icon-2" aria-selected="false" data-tab="#about-i1"
                                            aria-controls="about-i1" role="tab">
                                            <?php echo sr_material_icon_html('warning', '', sr_t('admin::ui.text.6c0f7510')); ?>
                                        </button>
                                    </nav>
                                </div>

                                <div class="tab-panel-space">
                                    <div id="home-i1" role="tabpanel" aria-labelledby="home-icon-2">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.9f084c3a')); ?></p>
                                    </div>

                                    <div id="profile-i1" class="sample-hidden" role="tabpanel" aria-labelledby="profile-icon-2">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.efb948b4')); ?></p>
                                    </div>

                                    <div id="settings-i1" class="sample-hidden" role="tabpanel"
                                        aria-labelledby="settings-icon-2">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.db8f5867')); ?></p>
                                    </div>

                                    <div id="about-i1" class="sample-hidden" role="tabpanel" aria-labelledby="about-icon-2">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.4e442ab1')); ?></p>
                                    </div>
                                </div>
                            </div>
                            <!-- end card-body-->
                        </div>
                        <!-- end card-->

                        <div class="card">
                            <div class="card-header sample-border-dashed">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.card.with.tabs.07403201')); ?></h4>

                                <nav class="nav-tabs" aria-label="Tabs" role="tablist" data-tab-select="#tab-select">
                                    <button type="button"
                                        class="nav-link nav-link-line-primary active"
                                        id="summary" aria-selected="true" data-tab="#home-ct" aria-controls="home-ct"
                                        role="tab">
                                        <?php echo sr_material_icon_html('home', '', sr_t('admin::ui.text.034999fa')); ?>
                                        <div class="tabs-hidden-until-md"><?php echo sr_e(sr_t('admin::ui.text.50f30154')); ?></div>
                                    </button>

                                    <button type="button"
                                        class="nav-link nav-link-line-primary"
                                        id="accounts" aria-selected="false" data-tab="#profile-ct"
                                        aria-controls="profile-ct" role="tab">
                                        <?php echo sr_material_icon_html('person', '', sr_t('admin::ui.active.1ac6e422')); ?>
                                        <div class="tabs-hidden-until-md"><?php echo sr_e(sr_t('admin::ui.text.b0b3d3bc')); ?></div>
                                    </button>

                                    <button type="button"
                                        class="nav-link nav-link-line-primary"
                                        id="setting" aria-selected="false" data-tab="#settings-ct"
                                        aria-controls="settings-ct" role="tab">
                                        <?php echo sr_material_icon_html('settings', '', sr_t('admin::ui.settings.115bced4')); ?>
                                        <div class="tabs-hidden-until-md"><?php echo sr_e(sr_t('admin::ui.settings.115bced4')); ?></div>
                                    </button>
                                </nav>
                            </div>

                            <div class="card-body">
                                <div>
                                    <div id="home-ct" role="tabpanel" aria-labelledby="summary">
                                        <p><?php echo sr_e(sr_t('admin::ui.dashboard.f6df4a9f')); ?></p>
                                    </div>

                                    <div id="profile-ct" class="sample-hidden" role="tabpanel" aria-labelledby="accounts">
                                        <p><?php echo sr_e(sr_t('admin::ui.text.924c7ec3')); ?></p>
                                    </div>

                                    <div id="settings-ct" class="sample-hidden" role="tabpanel" aria-labelledby="setting">
                                        <p><?php echo sr_e(sr_t('admin::ui.notification.settings.active.notification.select.3f5ffd6a')); ?></p>
                                    </div>
                                </div>
                            </div>
                            <!-- end card-body-->
                        </div>
                        <!-- end card-->
                    </div>
</div>
</div>
