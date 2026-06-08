<div class="ui-kit-sample-section" data-ui-kit-sample="form-elements">
<div class="container-fluid">
                    <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-base">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.input.textfield.type.694d460d')); ?></h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Simple Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="simpleinput" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.simple.input.cb0b2f70')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="simpleinput" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Floating Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.floating.input.cba11e12')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="validation-field">
                                                    <input type="text" id="floatingInput" placeholder=""
                                                        class="form-floating-control form-input" />
                                                    <label for="floatingInput"
                                                        class="form-floating-label"><?php echo sr_e(sr_t('admin::ui.name.253d1510')); ?></label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Validation Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="validInput" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.valid.input.da64ada7')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="validation-field">
                                                    <input type="text" id="validInput" name="validation-name-success"
                                                        class="form-input form-input-valid" required=""
                                                        aria-describedby="validation-name-success-helper" />
                                                    <div
                                                        class="validation-static-icon">
                                                        <?php echo sr_material_icon_html('check', 'sample-success-text', sr_t('admin::ui.text.35688a85')); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Placeholder -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-rounded" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.rounded.input.f0ad333f')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-rounded" class="form-input form-input-rounded"
                                                    placeholder="<?php echo sr_e(sr_t('admin::ui.text.be606da9')); ?>" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Text Area -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-textarea" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.text.area.2dbabf8d')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <textarea id="example-textarea" rows="5" class="form-textarea"></textarea>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Disabled -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-disable" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.disabled.54612c16')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-disable" value="<?php echo sr_e(sr_t('admin::ui.text.78462e3a')); ?>" disabled
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Helping Text -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-helping" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.helping.text.e77e662a')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-helping" placeholder="<?php echo sr_e(sr_t('admin::ui.text.318b9368')); ?>"
                                                    class="form-input" />
                                                <small class="sample-help-text"><?php echo sr_e(sr_t('admin::ui.text.b02e5a63')); ?></small>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Default select -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="discount" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.select.select.with.icon.623ff179')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-icon-group">
                                                    <?php echo sr_material_icon_html('sell', 'input-icon'); ?>
                                                    <select id="discount" class="form-select">
                                                        <option selected><?php echo sr_e(sr_t('admin::ui.select.80a8056b')); ?></option>
                                                        <option><?php echo sr_e(sr_t('admin::ui.text.123a955e')); ?></option>
                                                        <option><?php echo sr_e(sr_t('admin::ui.text.b1e694eb')); ?></option>
                                                        <option><?php echo sr_e(sr_t('admin::ui.text.dbdf550f')); ?></option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- with Label Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.label.input.5bde99cc')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div>
                                                    <label for="labelInputInput1" class="form-label"><?php echo sr_e(sr_t('admin::ui.text.89d2f38f')); ?></label>
                                                    <input type="email" class="form-input" id="labelInputInput1"
                                                        placeholder="name@example.com" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Search Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="SearchInput" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.search.search.style.ca458659')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-icon-group">
                                                    <?php echo sr_material_icon_html('search', 'input-icon'); ?>
                                                    <input type="search" id="SearchInput" placeholder="<?php echo sr_e(sr_t('admin::ui.search.09b42aed')); ?>"
                                                        class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Invalidation Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="inValidationInput" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.invalid.input.97005652')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-icon-group">
                                                    <input type="text" id="inValidationInput"
                                                        name="validation-name-success"
                                                        class="form-input form-input-invalid" required=""
                                                        aria-describedby="validation-name-success-helper" />
                                                    <?php echo sr_material_icon_html('info', 'input-icon validation-error-icon', sr_t('admin::ui.text.b49f20d8')); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Placeholder -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-placeholder" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.placeholder.37969de3')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-placeholder" class="form-input"
                                                    placeholder="<?php echo sr_e(sr_t('admin::ui.text.4b1c62ac')); ?>" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Readonly -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-readonly" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.readonly.664fd112')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-readonly" value="<?php echo sr_e(sr_t('admin::ui.text.3e05543f')); ?>" readonly
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Static Control -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-static" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.static.control.e9099e05')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="example-static" value="email@example.com"
                                                    readonly class="form-input form-input-plain" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Default select -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.select.default.select.861b0eab')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <select class="form-select">
                                                    <option selected><?php echo sr_e(sr_t('admin::ui.select.menu.0c8ad3cb')); ?></option>
                                                    <option><?php echo sr_e(sr_t('admin::ui.text.556dcbf0')); ?></option>
                                                    <option><?php echo sr_e(sr_t('admin::ui.text.ca76b128')); ?></option>
                                                    <option><?php echo sr_e(sr_t('admin::ui.text.28ed1f7d')); ?></option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Checkbox List -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <span class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.list.checkbox.list.d31f7ae8')); ?></span>
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
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.input.types.a2a7e61f')); ?></h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Email Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-email" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.email.email.8b8a829e')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="email" id="example-email" placeholder="<?php echo sr_e(sr_t('admin::ui.email.3b7dbc4c')); ?>"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Show/Hide Password -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="password" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.password.show.hide.password.bffe9d7a')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="validation-field ui-kit-cluster ui-kit-align-items-center">
                                                    <input id="password" type="password" class="form-input form-control-icon-end"
                                                        placeholder="<?php echo sr_e(sr_t('admin::ui.password.9e396000')); ?>" />
                                                    <button type="button"
                                                        data-toggle-password='{"target":"#password"}'
                                                        data-toggle-password-show-label="<?php echo sr_e(sr_t('admin::ui.password.ad78e4b8')); ?>"
                                                        data-toggle-password-hide-label="<?php echo sr_e(sr_t('admin::ui.password.52dc26ff')); ?>"
                                                        aria-label="<?php echo sr_e(sr_t('admin::ui.password.ad78e4b8')); ?>"
                                                        aria-pressed="false"
                                                        class="form-input-action">
                                                        <?php echo sr_material_icon_html('visibility', 'password-active-hide'); ?>
                                                        <?php echo sr_material_icon_html('visibility_off', 'password-active-show'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Time -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-time" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.time.a85f0011')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="time" id="example-time" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Number -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-number" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.number.7e569561')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input id="example-number" type="number" name="number"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Range -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-range" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.range.c39250e4')); ?></label>
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
                                                <label for="example-password" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.password.password.22c84385')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="password" id="example-password" value="password"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Month -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-month" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.month.b274c0c9')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="month" id="example-month" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Week -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-week" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.week.aab53b22')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input id="example-week" type="week" name="week" class="form-input" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Color -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="example-color" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.color.aa8ae7a1')); ?></label>
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
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.input.group.0e2ff079')); ?></h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Basic Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.active.name.username.b19d3d5e')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">@</span>
                                                    <input type="text" placeholder="<?php echo sr_e(sr_t('admin::ui.active.name.f82a5457')); ?>" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Currency Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.amount.f78975a2')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-input" />
                                                    <span class="input-group-text">.00</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Textarea with Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.textarea.9cbf1bae')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text"><?php echo sr_e(sr_t('admin::ui.text.c2eeb6c2')); ?></span>
                                                    <textarea rows="2" class="form-textarea"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Flex-nowrap Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="ui-kit-space-before-2 ui-kit-block-flow sample-emphasis"><?php echo sr_e(sr_t('admin::ui.wrapping.4812dc30')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">@</span>
                                                    <input type="text" placeholder="<?php echo sr_e(sr_t('admin::ui.active.name.f82a5457')); ?>" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Input group with text input and button -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.input.button.f4339c75')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <input type="text" placeholder="<?php echo sr_e(sr_t('admin::ui.name.5ed209e6')); ?>" class="form-input" />
                                                    <button type="button"
                                                        class="btn btn-solid-dark"><?php echo sr_e(sr_t('admin::ui.text.60563203')); ?></button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Multiple Files  -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="formFileMultiple01" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.multiple.files.48167df8')); ?></label>
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
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.recipient.2c64ddc4')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <input type="text" placeholder="<?php echo sr_e(sr_t('admin::ui.name.5ed209e6')); ?>" class="form-input" />
                                                    <span class="input-group-text">@example.com</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Multi-field Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.email.login.email.login.62fef2c1')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <input type="text" placeholder="<?php echo sr_e(sr_t('admin::ui.active.name.f82a5457')); ?>" class="form-input" />
                                                    <span class="input-group-text">@</span>
                                                    <input type="text" placeholder="<?php echo sr_e(sr_t('admin::ui.text.7d585c38')); ?>" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Vanity URL Input Group -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.url.vanity.url.3e033a6a')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span
                                                        class="input-group-text sample-nowrap">https://example.com/users/</span>
                                                    <input type="text" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Input group with dropdown and text input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="ui-kit-space-before-2 ui-kit-block-flow sample-emphasis"><?php echo sr_e(sr_t('admin::ui.dropdown.input.ffa2ab72')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <div class="dropdown">
                                                        <button type="button"
                                                            class="dropdown-toggle btn btn-group-start btn-solid-primary"
                                                            aria-haspopup="menu" aria-expanded="false"
                                                            aria-label="Dropdown">
                                                            <?php echo sr_e(sr_t('admin::ui.text.a1631f46')); ?> <?php echo sr_ui_arrow_icon_html('down', 'dropdown-icon'); ?>
                                                        </button>

                                                        <div class="dropdown-menu" role="menu"
                                                            aria-orientation="vertical">
                                                            <div class="ui-kit-stack-0-5">
                                                                <a class="dropdown-item" href="#!"><?php echo sr_e(sr_t('admin::ui.text.01dfd369')); ?></a>

                                                                <a class="dropdown-item active" href="#!"><?php echo sr_e(sr_t('admin::ui.text.47a7f13d')); ?></a>

                                                                <a class="dropdown-item" href="#!"><?php echo sr_e(sr_t('admin::ui.text.baf7b1bd')); ?></a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <input type="text" class="form-input" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- File input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="inputGroupFile04" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.file.input.c956dabe')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="file" name="file-input" id="inputGroupFile04"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.select.input.group.select.ab4404f1')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="input-group">
                                                    <span class="input-group-text"><?php echo sr_e(sr_t('admin::ui.text.cb076d97')); ?></span>
                                                    <select class="form-select form-control-group-end">
                                                        <option selected><?php echo sr_e(sr_t('admin::ui.select.5b1efda0')); ?></option>
                                                        <option><?php echo sr_e(sr_t('admin::ui.text.556dcbf0')); ?></option>
                                                        <option><?php echo sr_e(sr_t('admin::ui.text.ca76b128')); ?></option>
                                                        <option><?php echo sr_e(sr_t('admin::ui.text.28ed1f7d')); ?></option>
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
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.floating.labels.ec65b9da')); ?></h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Floating Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.email.e9abda44')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <!-- Floating Input -->
                                                <div class="validation-field">
                                                    <input type="email" id="floating-input-email"
                                                        class="form-floating-control form-input"
                                                        placeholder="you@email.com" />
                                                    <label for="floating-input-email"
                                                        class="form-floating-label"><?php echo sr_e(sr_t('admin::ui.email.3b7dbc4c')); ?></label>
                                                </div>
                                                <!-- End Floating Input -->
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Floating Textarea -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.comments.8eea4e12')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="validation-field">
                                                    <textarea id="floatingTextarea" rows="4" placeholder=""
                                                        class="form-floating-control form-textarea"></textarea>
                                                    <label for="floatingTextarea"
                                                        class="form-floating-label"><?php echo sr_e(sr_t('admin::ui.text.1eaed37a')); ?></label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Floating Password -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.password.4fa210a0')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <div class="validation-field">
                                                    <input type="password" id="floatingPassword" placeholder=""
                                                        class="form-floating-control form-input" />
                                                    <label for="floatingPassword"
                                                        class="form-floating-label"><?php echo sr_e(sr_t('admin::ui.password.4fa210a0')); ?></label>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.input.sizes.c4e3d441')); ?></h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Small Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="input-small" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.small.32265979')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="input-small" placeholder=".input-sm"
                                                    class="form-input form-input-sm" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Large Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="input-large" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.large.865b14fe')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="input-large" placeholder=".input-lg"
                                                    class="form-input form-input-lg" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Large Select -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.select.menu.9633940e')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <select class="form-select form-select-lg">
                                                    <option selected><?php echo sr_e(sr_t('admin::ui.select.menu.0c8ad3cb')); ?></option>
                                                    <option value="1"><?php echo sr_e(sr_t('admin::ui.text.556dcbf0')); ?></option>
                                                    <option value="2"><?php echo sr_e(sr_t('admin::ui.text.ca76b128')); ?></option>
                                                    <option value="3"><?php echo sr_e(sr_t('admin::ui.text.28ed1f7d')); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <!-- Normal Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="input-normal" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.normal.339d3ab4')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <input type="text" id="input-normal" placeholder="Normal"
                                                    class="form-input" />
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Grid Size Input -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label for="input-gridsize" class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.grid.sizes.dcc4d2ee')); ?></label>
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

                                        <div class="sample-field-divider"></div>

                                        <!-- Small Select -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.select.menu.c84a7b65')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-lg-2">
                                                <select class="form-select form-select-sm">
                                                    <option selected><?php echo sr_e(sr_t('admin::ui.select.menu.0c8ad3cb')); ?></option>
                                                    <option value="1"><?php echo sr_e(sr_t('admin::ui.text.556dcbf0')); ?></option>
                                                    <option value="2"><?php echo sr_e(sr_t('admin::ui.text.ca76b128')); ?></option>
                                                    <option value="3"><?php echo sr_e(sr_t('admin::ui.text.28ed1f7d')); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.checks.radios.and.switches.2bcc4c3e')); ?></h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-lg-2 ui-kit-gap-base">
                                    <div>
                                        <!-- Default Checkboxes -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.checkboxes.3f234b18')); ?></label>
                                            </div>

                                            <div class="ui-kit-stack-3 ui-kit-column-lg-2">
                                                <!-- Default Checkbox -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkDefault" class="form-checkbox" />
                                                    <label for="checkDefault"><?php echo sr_e(sr_t('admin::ui.text.518c8759')); ?></label>
                                                </div>

                                                <!-- Light Checkbox -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkLight"
                                                        class="form-checkbox form-choice-muted form-choice-primary" />
                                                    <label for="checkLight"><?php echo sr_e(sr_t('admin::ui.text.bc13d1f4')); ?></label>
                                                </div>

                                                <!-- Inline Checkboxes -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-x-4">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkInline1" class="form-checkbox"
                                                            checked />
                                                        <label for="checkInline1"><?php echo sr_e(sr_t('admin::ui.1.5716fb16')); ?></label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkInline2"
                                                            class="form-checkbox" />
                                                        <label for="checkInline2"><?php echo sr_e(sr_t('admin::ui.2.2ca8baed')); ?></label>
                                                    </div>
                                                </div>

                                                <!-- Disabled/Indeterminate -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkIndeterminate"
                                                        class="form-checkbox" />
                                                    <label for="checkIndeterminate"><?php echo sr_e(sr_t('admin::ui.status.396c827b')); ?></label>
                                                </div>

                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkCheckedDisabled"
                                                        class="form-checkbox" checked disabled />
                                                    <label for="checkCheckedDisabled"><?php echo sr_e(sr_t('admin::ui.status.afd1aabe')); ?></label>
                                                </div>

                                                <!-- Sizes -->
                                                <h5 class="ui-kit-space-before-base ui-kit-space-after-2 sample-emphasis"><?php echo sr_e(sr_t('admin::ui.text.82232621')); ?></h5>

                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkSize1" class="form-checkbox form-choice-md"
                                                        checked />
                                                    <label for="checkSize1"><?php echo sr_e(sr_t('admin::ui.16px.41c433a0')); ?></label>
                                                </div>

                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkSize2"
                                                        class="form-checkbox form-choice-secondary form-choice-lg"
                                                        checked />
                                                    <label for="checkSize2"><?php echo sr_e(sr_t('admin::ui.20px.1a2fc945')); ?></label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Switches -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.switches.86b5bc92')); ?></label>
                                            </div>

                                            <div class="ui-kit-stack-3 ui-kit-column-lg-2">
                                                <!-- Enabled Switch -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="switch1" class="form-switch" checked />
                                                    <label for="switch1"><?php echo sr_e(sr_t('admin::ui.text.81d6e435')); ?></label>
                                                </div>

                                                <!-- Disabled Switch -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="switch2" class="form-switch" disabled />
                                                    <label for="switch2" class="sample-muted"><?php echo sr_e(sr_t('admin::ui.text.b9af9dc0')); ?></label>
                                                </div>

                                                <!-- Sizes -->
                                                <h5 class="ui-kit-space-before-base ui-kit-space-after-2 sample-emphasis"><?php echo sr_e(sr_t('admin::ui.text.82232621')); ?></h5>

                                                <!-- 16px Switch -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkboxSize16" class="form-switch"
                                                        checked />
                                                    <label for="checkboxSize16"><?php echo sr_e(sr_t('admin::ui.16px.bdbcfc48')); ?></label>
                                                </div>

                                                <!-- 20px Switch -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="checkboxSize20"
                                                        class="form-switch form-switch-lg form-choice-secondary"
                                                        checked />
                                                    <label for="checkboxSize20"><?php echo sr_e(sr_t('admin::ui.20px.8661da39')); ?></label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Colored Checkboxes -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.text.1855fe4d')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-1 ui-kit-cluster ui-kit-wrap ui-kit-gap-9 ui-kit-column-lg-2">
                                                <div class="ui-kit-stack-3">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkPrimary"
                                                            class="form-checkbox form-choice-outline form-choice-primary" checked />
                                                        <label for="checkPrimary">Primary</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkSecondary"
                                                            class="form-checkbox form-choice-outline form-choice-secondary" checked />
                                                        <label for="checkSecondary">Secondary</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkSuccess"
                                                            class="form-checkbox form-choice-outline form-choice-success" checked />
                                                        <label for="checkSuccess">Success</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkInfo"
                                                            class="form-checkbox form-choice-outline form-choice-info" checked />
                                                        <label for="checkInfo">Info</label>
                                                    </div>
                                                </div>

                                                <div class="ui-kit-stack-3">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkWarning"
                                                            class="form-checkbox form-choice-outline form-choice-warning" checked />
                                                        <label for="checkWarning">Warning</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkDanger"
                                                            class="form-checkbox form-choice-outline form-choice-danger" checked />
                                                        <label for="checkDanger">Danger</label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="checkbox" id="checkDark"
                                                            class="form-checkbox form-choice-outline form-choice-dark" checked />
                                                        <label for="checkDark">Dark</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Colored Checkboxes -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.text.1942c077')); ?></label>
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
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.radios.a8e1ef53')); ?></label>
                                            </div>

                                            <div class="ui-kit-stack-3 ui-kit-column-lg-2">
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="radio" name="gridRadio" id="radio1"
                                                        class="form-radio" checked />
                                                    <label for="radio1"><?php echo sr_e(sr_t('admin::ui.1.fbbaca81')); ?></label>
                                                </div>

                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="radio" name="gridRadio" id="radio2"
                                                        class="form-radio" />
                                                    <label for="radio2"><?php echo sr_e(sr_t('admin::ui.2.6ebdb471')); ?></label>
                                                </div>

                                                <!-- Inline Radios -->
                                                <div class="ui-kit-cluster ui-kit-inline-space-4">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="inlineRadioOptions" id="inlineRadio1"
                                                            value="option1" class="form-radio" checked />
                                                        <label for="inlineRadio1"><?php echo sr_e(sr_t('admin::ui.1.5716fb16')); ?></label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="inlineRadioOptions" id="inlineRadio2"
                                                            value="option2" class="form-radio" />
                                                        <label for="inlineRadio2"><?php echo sr_e(sr_t('admin::ui.2.2ca8baed')); ?></label>
                                                    </div>
                                                </div>

                                                <!-- Disabled Checked -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="radio" name="disabledRadioOptions" id="inlineRadio3"
                                                        value="option3" class="form-radio" checked
                                                        disabled />
                                                    <label for="inlineRadio3" class="sample-muted"><?php echo sr_e(sr_t('admin::ui.status.457b2a9e')); ?></label>
                                                </div>

                                                <!-- Sizes -->
                                                <h5 class="ui-kit-space-before-5 ui-kit-space-after-2 sample-emphasis"><?php echo sr_e(sr_t('admin::ui.text.82232621')); ?></h5>

                                                <!-- 16px Radios -->
                                                <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-inline-space-4">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="paymentMethod" id="radioCash"
                                                            value="cash" class="form-radio form-choice-md"
                                                            checked />
                                                        <label for="radioCash"><?php echo sr_e(sr_t('admin::ui.text.95b487c6')); ?></label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="paymentMethod" id="radioCard"
                                                            value="card" class="form-radio form-choice-md" />
                                                        <label for="radioCard"><?php echo sr_e(sr_t('admin::ui.text.62b41b5a')); ?></label>
                                                    </div>
                                                </div>

                                                <!-- 20px Radios -->
                                                <div class="ui-kit-space-before-2 ui-kit-cluster ui-kit-align-items-center ui-kit-inline-space-4">
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="deliveryOption" id="radioPickup"
                                                            value="pickup" class="form-radio form-choice-lg"
                                                            checked />
                                                        <label for="radioPickup"><?php echo sr_e(sr_t('admin::ui.text.a53ed118')); ?></label>
                                                    </div>
                                                    <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                                        <input type="radio" name="deliveryOption" id="radioHome"
                                                            value="home" class="form-radio form-choice-lg" />
                                                        <label for="radioHome"><?php echo sr_e(sr_t('admin::ui.text.cd87310d')); ?></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Reverse -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.reverse.dfe78742')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-1 ui-kit-fill-width ui-kit-stack-3 ui-kit-column-lg-2 ui-kit-width-lg-half">
                                                <!-- Reverse Checkbox -->
                                                <div class="ui-kit-cluster ui-kit-cluster-reverse ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="reverseCheck1" class="form-checkbox"
                                                        checked />
                                                    <label for="reverseCheck1"><?php echo sr_e(sr_t('admin::ui.text.5eb3f52e')); ?></label>
                                                </div>

                                                <!-- Reverse Radio -->
                                                <div class="ui-kit-cluster ui-kit-cluster-reverse ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="radio" id="reverseCheck2" name="reverseRadio"
                                                        class="form-radio" disabled />
                                                    <label for="reverseCheck2"><?php echo sr_e(sr_t('admin::ui.text.b268eb23')); ?></label>
                                                </div>

                                                <!-- Reverse Switch -->
                                                <div class="ui-kit-cluster ui-kit-cluster-reverse ui-kit-align-items-center ui-kit-gap-2">
                                                    <input type="checkbox" id="switchCheckReverse" class="form-switch"
                                                        checked />
                                                    <label for="switchCheckReverse"><?php echo sr_e(sr_t('admin::ui.text.cb66827c')); ?></label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Colored Radios -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.text.a133349e')); ?></label>
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

                                        <div class="sample-field-divider"></div>

                                        <!-- Toggle Checkboxes -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.checkbox.toggle.50291b0c')); ?></label>
                                            </div>

                                            <div class="ui-kit-stack-3 ui-kit-column-lg-2">
                                                <!-- Group Toggle -->
                                                <div class="ui-kit-cluster">
                                                    <div>
                                                        <input type="checkbox" id="toggle1" class="form-choice-toggle-input sample-hidden" />
                                                        <label for="toggle1"
                                                            class="btn btn-choice-primary btn-group-start"><?php echo sr_e(sr_t('admin::ui.text.556dcbf0')); ?></label>
                                                    </div>
                                                    <div>
                                                        <input type="checkbox" id="toggle2" class="form-choice-toggle-input sample-hidden" />
                                                        <label for="toggle2"
                                                            class="btn btn-choice-primary btn-group-middle"><?php echo sr_e(sr_t('admin::ui.text.ca76b128')); ?></label>
                                                    </div>
                                                    <div>
                                                        <input type="checkbox" id="toggle3" class="form-choice-toggle-input sample-hidden" />
                                                        <label for="toggle3"
                                                            class="btn btn-choice-primary btn-group-end"><?php echo sr_e(sr_t('admin::ui.text.28ed1f7d')); ?></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">체크박스 토글 그룹 색상</label>
                                            </div>

                                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2 ui-kit-column-lg-2">
                                                <?php foreach (['primary' => 'Primary', 'secondary' => 'Secondary', 'success' => 'Success', 'info' => 'Info', 'warning' => 'Warning', 'danger' => 'Danger', 'dark' => 'Dark', 'light' => 'Light'] as $choiceColor => $choiceLabel) { ?>
                                                    <?php $choiceColorId = 'toggleColor' . ucfirst($choiceColor); ?>
                                                    <div class="ui-kit-cluster">
                                                        <div>
                                                            <input type="checkbox" id="<?php echo sr_e($choiceColorId); ?>A" class="form-choice-toggle-input sample-hidden" checked />
                                                            <label for="<?php echo sr_e($choiceColorId); ?>A" class="btn btn-choice-<?php echo sr_e($choiceColor); ?> btn-group-start"><?php echo sr_e($choiceLabel); ?></label>
                                                        </div>
                                                        <div>
                                                            <input type="checkbox" id="<?php echo sr_e($choiceColorId); ?>B" class="form-choice-toggle-input sample-hidden" />
                                                            <label for="<?php echo sr_e($choiceColorId); ?>B" class="btn btn-choice-<?php echo sr_e($choiceColor); ?> btn-group-end">Alt</label>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <!-- Toggle Radios -->
                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0"><?php echo sr_e(sr_t('admin::ui.radio.toggle.1512d5a1')); ?></label>
                                            </div>

                                            <div class="ui-kit-column-1 ui-kit-cluster ui-kit-column-lg-2">
                                                <div>
                                                    <input type="radio" name="radiotoggle" id="radioLeft"
                                                        class="form-choice-toggle-input sample-hidden" checked />
                                                    <label for="radioLeft"
                                                        class="btn btn-choice-secondary btn-group-start"><?php echo sr_e(sr_t('admin::ui.text.dc0103c1')); ?></label>
                                                </div>

                                                <div>
                                                    <input type="radio" name="radiotoggle" id="radioMiddle"
                                                        class="form-choice-toggle-input sample-hidden" />
                                                    <label for="radioMiddle"
                                                        class="btn btn-choice-secondary btn-group-middle"><?php echo sr_e(sr_t('admin::ui.text.d41ad4fc')); ?></label>
                                                </div>

                                                <div>
                                                    <input type="radio" name="radiotoggle" id="radioRight"
                                                        class="form-choice-toggle-input sample-hidden" />
                                                    <label for="radioRight"
                                                        class="btn btn-choice-secondary btn-group-end"><?php echo sr_e(sr_t('admin::ui.text.f594aa6a')); ?></label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sample-field-divider"></div>

                                        <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-1-5 ui-kit-grid-lg-3 ui-kit-gap-lg-9">
                                            <div>
                                                <label class="form-label ui-kit-block-pad-2 ui-kit-space-after-0">라디오 토글 색상</label>
                                            </div>

                                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2 ui-kit-column-lg-2">
                                                <?php foreach (['primary' => 'Primary', 'secondary' => 'Secondary', 'success' => 'Success', 'info' => 'Info', 'warning' => 'Warning', 'danger' => 'Danger', 'dark' => 'Dark', 'light' => 'Light'] as $choiceColor => $choiceLabel) { ?>
                                                    <div>
                                                        <input type="radio" name="radioToggleColor" id="radioToggleColor<?php echo sr_e(ucfirst($choiceColor)); ?>" class="form-choice-toggle-input sample-hidden"<?php echo $choiceColor === 'primary' ? ' checked' : ''; ?> />
                                                        <label for="radioToggleColor<?php echo sr_e(ucfirst($choiceColor)); ?>" class="btn btn-choice-<?php echo sr_e($choiceColor); ?>"><?php echo sr_e($choiceLabel); ?></label>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
</div>
</div>
