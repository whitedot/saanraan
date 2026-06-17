<div class="ui-kit-sample-section" data-ui-kit-sample="ui-dropdowns">
<div class="container-fluid">
                    <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-xl-2 ui-kit-gap-base">
                        <!-- Single Button Dropdowns -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.a7542165')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.menu.active.99b41e46')); ?></p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown">
                                        <button type="button" class="dropdown-toggle btn btn-soft-default"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.select.aedc2eb7')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.settings.0dc82bb5')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.notification.12ddd6ca')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.919c1b32')); ?></a>
                                        </div>
                                    </div>

                                    <div class="dropdown">
                                        <a class="dropdown-toggle btn btn-solid-primary"
                                            href="#" role="button" id="dropdownMenuLink" aria-haspopup="true"
                                            aria-expanded="false">
                                            <?php echo sr_e(sr_t('ui.text.553c43c9')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </a>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.61cbfb01')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.36b3f9a0')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.55e86e37')); ?></a>
                                        </div>
                                    </div>

                                    <div class="dropdown" data-dropdown-trigger="hover">
                                        <button type="button" class="dropdown-toggle btn btn-soft-default"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.106f8e82')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="hover-dropdown">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.settings.0dc82bb5')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.notification.12ddd6ca')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.919c1b32')); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Menu Alignment -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.menu.caec9fc5')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4">
                                    <code>data-dropdown-placement="bottom-right"</code>
                                    <?php echo sr_e(sr_t('ui.active.menu.8cf273b0')); ?>
                                </p>

                                <div class="dropdown" data-dropdown-placement="bottom-right">
                                    <button type="button" class="dropdown-toggle btn btn-soft-default"
                                        aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                        <?php echo sr_e(sr_t('ui.menu.250808dc')); ?>
                                        <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                    </button>

                                    <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                        <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                        <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                        <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Dropdown Arrow -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.62c91826')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.eab18637')); ?></p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-primary"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown"><?php echo sr_e(sr_t('ui.text.95dd914e')); ?></button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.e094fe4f')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.bde16b30')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.91ea82c1')); ?></a>
                                        </div>
                                    </div>

                                    <div class="dropdown">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-outline-primary"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.13351e0b')); ?>
                                            <?php echo sr_material_icon_html('edit', '', sr_t('ui.edit.3537f0cc')); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.edit.9034544e')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.settings.64c15812')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.919c1b32')); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Split Button Dropdowns -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.20ba4976')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.4aa7ed8f')); ?></p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown-split">
                                        <button type="button"
                                            class="btn btn-solid-primary dropdown-split-main"><?php echo sr_e(sr_t('ui.primary.5c1b8e5f')); ?></button>

                                        <div class="dropdown" data-dropdown-placement="bottom-left">
                                            <button type="button"
                                                class="dropdown-toggle btn btn-solid-primary-muted dropdown-split-toggle">
                                                <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                            </button>

                                            <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                                <hr class="dropdown-divider" />
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="dropdown-split">
                                        <button type="button"
                                            class="btn btn-soft-default dropdown-split-main"><?php echo sr_e(sr_t('ui.secondary.1d6f4945')); ?></button>

                                        <div class="dropdown" data-dropdown-placement="bottom-left">
                                            <button type="button"
                                                class="dropdown-toggle btn btn-soft-default dropdown-split-toggle">
                                                <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                            </button>

                                            <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                                <hr class="dropdown-divider" />
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="dropdown-split">
                                        <button type="button"
                                            class="btn btn-soft-success dropdown-split-main"><?php echo sr_e(sr_t('ui.success.54159b7c')); ?></button>

                                        <div class="dropdown" data-dropdown-placement="bottom-left">
                                            <button type="button"
                                                class="dropdown-toggle btn btn-soft-success dropdown-split-toggle">
                                                <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                            </button>

                                            <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                                <hr class="dropdown-divider" />
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="dropdown-split">
                                        <button type="button"
                                            class="btn btn-solid-info dropdown-split-main"><?php echo sr_e(sr_t('ui.info.26ff73fa')); ?></button>

                                        <div class="dropdown" data-dropdown-placement="bottom-left">
                                            <button type="button"
                                                class="dropdown-toggle btn btn-solid-info-muted dropdown-split-toggle">
                                                <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                            </button>

                                            <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                                <hr class="dropdown-divider" />
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Variant -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.27a9c9e1')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.menu.active.3ed4e224')); ?></p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-primary is-disabled-look"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.primary.5c1b8e5f')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.61cbfb01')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.save.7f1fb44d')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.31d45e1d')); ?></a>
                                            <hr class="dropdown-divider" />
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.a2c0ef22')); ?></a>
                                        </div>
                                    </div>

                                    <div class="dropdown">
                                        <button type="button" class="dropdown-toggle btn btn-soft-default"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.secondary.1d6f4945')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.settings.115bced4')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.settings.f5bf4963')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.25914f73')); ?></a>
                                            <hr class="dropdown-divider" />
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.919c1b32')); ?></a>
                                        </div>
                                    </div>

                                    <div class="dropdown">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-success-contrast"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.success.54159b7c')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.0f5bcfc2')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.e094fe4f')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.36f07297')); ?></a>
                                            <hr class="dropdown-divider" />
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.6d4795cf')); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sizing -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.e89c2291')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.menu.5548d650')); ?></p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-soft-default dropdown-toggle-lg"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.0ba54b43')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                            <hr class="dropdown-divider" />
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                        </div>
                                    </div>

                                    <div class="dropdown-split">
                                        <button type="button"
                                            class="btn btn-soft-default dropdown-split-main dropdown-toggle-lg"><?php echo sr_e(sr_t('ui.text.91285b5a')); ?></button>

                                        <div class="dropdown" data-dropdown-placement="bottom-left">
                                            <button type="button"
                                                class="dropdown-toggle btn btn-soft-default dropdown-split-toggle">
                                                <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                            </button>

                                            <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                                <hr class="dropdown-divider" />
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="dropdown">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-sm btn-soft-default"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.7b8bcce7')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                            <hr class="dropdown-divider" />
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                        </div>
                                    </div>

                                    <div class="dropdown-split">
                                        <button type="button"
                                            class="btn btn-sm btn-group-start btn-soft-default"><?php echo sr_e(sr_t('ui.text.be41fd02')); ?></button>

                                        <div class="dropdown" data-dropdown-placement="bottom-left">
                                            <button type="button"
                                                class="dropdown-toggle btn btn-sm btn-soft-default dropdown-split-toggle">
                                                <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                            </button>

                                            <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                                <hr class="dropdown-divider" />
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 드롭업 Variation -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.eb00a816')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4">
                                    <?php echo sr_e(sr_t('ui.text.d40aee63')); ?>
                                    <code>data-dropdown-placement="top"</code>
                                    <?php echo sr_e(sr_t('ui.text.82d047b9')); ?>
                                    <code>data-dropdown-placement="top-left"</code>
                                    <?php echo sr_e(sr_t('ui.active.menu.3b57a848')); ?>
                                </p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown" data-dropdown-placement="top">
                                        <button type="button" class="dropdown-toggle btn btn-soft-default"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.c34e13c9')); ?>
                                            <?php echo sr_material_icon_html('expand_less', '', sr_t('ui.text.aba08853')); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                            <hr class="dropdown-divider" />
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                        </div>
                                    </div>

                                    <div class="dropdown-split">
                                        <button type="button"
                                            class="btn btn-group-start btn-soft-default"><?php echo sr_e(sr_t('ui.text.2b5c6d74')); ?></button>

                                        <div class="dropdown" data-dropdown-placement="top-left">
                                            <button type="button"
                                                class="dropdown-toggle btn btn-soft-default dropdown-split-toggle">
                                                <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                            </button>

                                            <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.158d042f')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.active.085d511c')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.settings.d6806693')); ?></a>
                                                <hr class="dropdown-divider" />
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.project.settings.9d31f37f')); ?></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 드롭스타트 Variation -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.1c977f4a')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4">
                                    <?php echo sr_e(sr_t('ui.text.d40aee63')); ?>
                                    <code>data-dropdown-placement="left-start"</code>
                                    <?php echo sr_e(sr_t('ui.active.menu.a4009a70')); ?>
                                </p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown" data-dropdown-placement="left-start">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-secondary"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_ui_arrow_icon_html('left', 'dropdown-icon'); ?>
                                            <?php echo sr_e(sr_t('ui.text.7fa847c5')); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                            <hr class="dropdown-divider" />
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                        </div>
                                    </div>

                                    <div class="dropdown-split">
                                        <div class="dropdown" data-dropdown-placement="left-start">
                                            <button type="button"
                                                class="dropdown-toggle btn btn-solid-secondary-muted btn-group-start dropdown-toggle-compact">
                                                <?php echo sr_ui_arrow_icon_html('left', 'dropdown-icon'); ?>
                                            </button>

                                            <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                                <hr class="dropdown-divider" />
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                            </div>
                                        </div>

                                        <button type="button"
                                            class="btn btn-group-end btn-solid-secondary"><?php echo sr_e(sr_t('ui.text.38c005cc')); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dropend Variation -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.9615eba3')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4">
                                    <?php echo sr_e(sr_t('ui.text.d40aee63')); ?>
                                    <code>data-dropdown-placement="right-end"</code>
                                    <?php echo sr_e(sr_t('ui.active.menu.6049a1ab')); ?>
                                </p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown" data-dropdown-placement="right-end">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-primary"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            Dropend
                                            <?php echo sr_ui_arrow_icon_html('right', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.24470b61')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.67156903')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.fb8ebabd')); ?></a>
                                            <hr class="dropdown-divider" />
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.active.91c30fe8')); ?></a>
                                        </div>
                                    </div>

                                    <div class="dropdown-split">
                                        <button type="button"
                                            class="btn btn-solid-primary dropdown-split-main"><?php echo sr_e(sr_t('ui.text.38c005cc')); ?></button>

                                        <div class="dropdown" data-dropdown-placement="right-end">
                                            <button type="button"
                                                class="dropdown-toggle btn btn-solid-primary-muted dropdown-split-toggle dropdown-toggle-compact">
                                                <?php echo sr_ui_arrow_icon_html('right', 'dropdown-icon'); ?>
                                            </button>

                                            <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.84a7bf51')); ?></a>
                                                <hr class="dropdown-divider" />
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.1b4019bd')); ?></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Item -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.e31486b9')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4">
                                    <?php echo sr_e(sr_t('ui.text.fb7e1c75')); ?>
                                    <code>.active</code>
                                    <?php echo sr_e(sr_t('ui.active.select.42d6d19e')); ?>
                                </p>

                                <div class="dropdown" data-dropdown-placement="bottom-end">
                                    <button type="button"
                                        class="dropdown-toggle btn btn-solid-secondary"
                                        aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                        <?php echo sr_e(sr_t('ui.text.91258d4f')); ?>
                                        <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                    </button>

                                    <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                        <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.2f8d87ff')); ?></a>
                                        <a class="dropdown-item active" href="#"><?php echo sr_e(sr_t('ui.text.137b8114')); ?></a>
                                        <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.815ab7a1')); ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 비활성화됨 Item -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.253c2a1a')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4">
                                    <?php echo sr_e(sr_t('ui.text.fb7e1c75')); ?>
                                    <code>.disabled</code>
                                    <?php echo sr_e(sr_t('ui.active.menu.active.4ac36575')); ?>
                                </p>

                                <div class="dropdown" data-dropdown-placement="bottom-end">
                                    <button type="button"
                                        class="dropdown-toggle btn btn-solid-primary"
                                        aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                        <?php echo sr_e(sr_t('ui.text.cb0f8c54')); ?>
                                        <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                    </button>

                                    <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                        <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.2f8d87ff')); ?></a>
                                        <a class="dropdown-item active" href="#" disabled><?php echo sr_e(sr_t('ui.text.ecccfb25')); ?></a>
                                        <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.815ab7a1')); ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 헤더s -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.999150ec')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.menu.menu.8e3b133d')); ?>
                                </p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown" data-dropdown-placement="bottom-end">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-secondary-contrast"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.999150ec')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <h6 class="dropdown-header"><?php echo sr_e(sr_t('ui.text.0611ad02')); ?></h6>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.47a7f13d')); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 다크 드롭다운s -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.0b557e35')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4">
                                    <?php echo sr_e(sr_t('ui.menu.1a2ccc44')); ?>
                                    <code>data-theme="dark"</code>
                                    <?php echo sr_e(sr_t('ui.menu.69b294ff')); ?>
                                </p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown" data-dropdown-placement="bottom-end">
                                        <button type="button" class="dropdown-toggle btn btn-solid-dark"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.0b557e35')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div data-theme="dark" class="dropdown-menu" role="menu"
                                            aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.dashboard.2b1a8070')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.c1d865a7')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.billing.settings.bb7531de')); ?></a>
                                            <hr class="dropdown-divider" />
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.919c1b32')); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Centered Dropdowns -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.b6ea5e2c')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4">
                                    <code>data-dropdown-placement="bottom"</code>
                                    <?php echo sr_e(sr_t('ui.text.82d047b9')); ?>
                                    <code>data-dropdown-placement="top"</code>
                                    <?php echo sr_e(sr_t('ui.active.menu.00a89ae5')); ?>
                                </p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown" data-dropdown-placement="bottom">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-secondary"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.617d1288')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <div class="ui-kit-stack-0-5">
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.2.f882a558')); ?></a>
                                                <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.3.cbdf3320')); ?></a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="dropdown" data-dropdown-placement="top">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-secondary"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.88a39bb6')); ?>
                                            <?php echo sr_material_icon_html('expand_less', '', sr_t('ui.text.aba08853')); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.text.01dfd369')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.2.f882a558')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.3.cbdf3320')); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Auto Close Behavior -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.close.5f3ace98')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4">
                                    <?php echo sr_e(sr_t('ui.menu.autoclose.active.ebf30f94')); ?>
                                </p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center ui-kit-gap-2-5">
                                    <div class="dropdown">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-secondary"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.9802a140')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.menu.c3a52c01')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.menu.c3a52c01')); ?></a>
                                            <a class="dropdown-item" href="#"><?php echo sr_e(sr_t('ui.menu.c3a52c01')); ?></a>
                                        </div>
                                    </div>

                                    <div class="dropdown" data-dropdown-auto-close="inside">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-secondary"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.54c5ba29')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#">Menu item</a>
                                            <a class="dropdown-item" href="#">Menu item</a>
                                            <a class="dropdown-item" href="#">Menu item</a>
                                        </div>
                                    </div>

                                    <div class="dropdown" data-dropdown-auto-close="outside">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-secondary"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.text.a5d2c5f2')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#">Menu item</a>
                                            <a class="dropdown-item" href="#">Menu item</a>
                                            <a class="dropdown-item" href="#">Menu item</a>
                                        </div>
                                    </div>

                                    <div class="dropdown" data-dropdown-auto-close="false">
                                        <button type="button"
                                            class="dropdown-toggle btn btn-solid-secondary"
                                            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                            <?php echo sr_e(sr_t('ui.close.42ab9687')); ?>
                                            <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                        </button>

                                        <div class="dropdown-menu" role="menu" aria-orientation="vertical">
                                            <a class="dropdown-item" href="#">Menu item</a>
                                            <a class="dropdown-item" href="#">Menu item</a>
                                            <a class="dropdown-item" href="#">Menu item</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Text -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.text.258ad4b0')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-hint ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.menu.active.menu.d08ffdf3')); ?></p>
                                <div class="dropdown" data-dropdown-placement="bottom-end">
                                    <button type="button"
                                        class="dropdown-toggle btn btn-solid-primary"
                                        aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                                        <?php echo sr_e(sr_t('ui.text.5785447b')); ?>
                                        <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-wide dropdown-menu-padded" role="menu" aria-orientation="vertical">
                                        <span class="ui-kit-hint ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.menu.ab99a84e')); ?></span>
                                        <p class="ui-kit-hint"><?php echo sr_e(sr_t('ui.text.f96634d5')); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
</div>
</div>
