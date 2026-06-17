<div class="ui-kit-sample-section" data-ui-kit-sample="form-validation">
<div class="container-fluid">
                    <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-base">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.custom.styles.validation.e4e968f6')); ?></h4>
                            </div>
                            <div class="card-body">
                                <form id="customValidationForm" class="ui-kit-grid ui-kit-grid-md-12 ui-kit-grid-1 ui-kit-gap-base"
                                    novalidate>
                                    <!-- First Name -->
                                    <div class="ui-kit-column-md-4">
                                        <label class="form-label" for="customFirstName"><?php echo sr_e(sr_t('admin::ui.name.first.name.1a3108a8')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>

                                        <div class="validation-field">
                                            <input type="text" id="customFirstName" value="John" required
                                                class="input-field form-input" />

                                            <?php echo sr_material_icon_html('check', 'valid-icon validation-status-icon validation-status-icon-success', sr_t('admin::ui.text.35688a85')); ?>
                                            <?php echo sr_material_icon_html('info', 'invalid-icon validation-status-icon validation-status-icon-danger', sr_t('admin::ui.text.b49f20d8')); ?>
                                        </div>
                                        <p class="valid-msg ui-kit-space-before-1 ui-kit-hidden ui-kit-type-sm ui-kit-ink-success"><?php echo sr_e(sr_t('admin::ui.text.78dc433d')); ?></p>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-hidden ui-kit-type-sm validation-danger-label"><?php echo sr_e(sr_t('admin::ui.name.b6997ef6')); ?></p>
                                    </div>

                                    <!-- Last Name -->
                                    <div class="ui-kit-column-md-4">
                                        <label class="form-label" for="customLastName"><?php echo sr_e(sr_t('admin::ui.last.name.9b3ddc78')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>

                                        <div class="validation-field">
                                            <input type="text" id="customLastName" value="Doe" required
                                                class="input-field form-input" />

                                            <?php echo sr_material_icon_html('check', 'valid-icon validation-status-icon validation-status-icon-success', sr_t('admin::ui.text.35688a85')); ?>
                                            <?php echo sr_material_icon_html('info', 'invalid-icon validation-status-icon validation-status-icon-danger', sr_t('admin::ui.text.b49f20d8')); ?>
                                        </div>
                                        <p class="valid-msg ui-kit-space-before-1 ui-kit-hidden ui-kit-type-sm ui-kit-ink-success"><?php echo sr_e(sr_t('admin::ui.text.78dc433d')); ?></p>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-hidden ui-kit-type-sm validation-danger-label"><?php echo sr_e(sr_t('admin::ui.text.90eea241')); ?></p>
                                    </div>

                                    <!-- Username -->
                                    <div class="ui-kit-column-md-4">
                                        <label class="form-label" for="customUsername"><?php echo sr_e(sr_t('admin::ui.active.name.username.b19d3d5e')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>

                                        <div class="validation-field input-group">
                                            <span class="input-group-text">@</span>
                                            <input type="text" id="customUsername" placeholder="johndoe123" required
                                                class="input-field form-input" />

                                            <?php echo sr_material_icon_html('check', 'valid-icon validation-status-icon validation-status-icon-success', sr_t('admin::ui.text.35688a85')); ?>
                                            <?php echo sr_material_icon_html('info', 'invalid-icon validation-status-icon validation-status-icon-danger', sr_t('admin::ui.text.b49f20d8')); ?>
                                        </div>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-hidden ui-kit-type-sm validation-danger-label"><?php echo sr_e(sr_t('admin::ui.active.name.select.c8bf3683')); ?></p>
                                    </div>

                                    <!-- City -->
                                    <div class="ui-kit-column-md-6">
                                        <label class="form-label" for="customCity"><?php echo sr_e(sr_t('admin::ui.city.68649934')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>

                                        <div class="validation-field">
                                            <input type="text" id="customCity" placeholder="San Francisco" required
                                                class="input-field form-input" />

                                            <?php echo sr_material_icon_html('check', 'valid-icon validation-status-icon validation-status-icon-success', sr_t('admin::ui.text.35688a85')); ?>
                                            <?php echo sr_material_icon_html('info', 'invalid-icon validation-status-icon validation-status-icon-danger', sr_t('admin::ui.text.b49f20d8')); ?>
                                        </div>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-hidden ui-kit-type-sm validation-danger-label"><?php echo sr_e(sr_t('admin::ui.name.f5853c38')); ?></p>
                                    </div>

                                    <!-- State -->
                                    <div class="ui-kit-column-md-3">
                                        <label class="form-label" for="customState"><?php echo sr_e(sr_t('admin::ui.state.b93c5a3b')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>

                                        <div class="validation-field">
                                            <select id="customState" required class="input-field form-select">
                                                <option value=""><?php echo sr_e(sr_t('admin::ui.select.5b1efda0')); ?></option>
                                                <option>California</option>
                                                <option>Texas</option>
                                                <option>New York</option>
                                                <option>Florida</option>
                                            </select>

                                            <?php echo sr_material_icon_html('check', 'valid-icon validation-status-icon validation-status-icon-select validation-status-icon-success', sr_t('admin::ui.text.35688a85')); ?>
                                            <?php echo sr_material_icon_html('info', 'invalid-icon validation-status-icon validation-status-icon-select validation-status-icon-danger', sr_t('admin::ui.text.b49f20d8')); ?>
                                        </div>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-hidden ui-kit-type-sm validation-danger-label"><?php echo sr_e(sr_t('admin::ui.select.684bc485')); ?>
                                        </p>
                                    </div>

                                    <!-- Zip -->
                                    <div class="ui-kit-column-md-3">
                                        <label class="form-label" for="customZip"><?php echo sr_e(sr_t('admin::ui.zip.code.45119a29')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>

                                        <div class="validation-field">
                                            <input type="text" id="customZip" placeholder="94107" required
                                                class="input-field form-input" />

                                            <?php echo sr_material_icon_html('check', 'valid-icon validation-status-icon validation-status-icon-success', sr_t('admin::ui.text.35688a85')); ?>
                                            <?php echo sr_material_icon_html('info', 'invalid-icon validation-status-icon validation-status-icon-danger', sr_t('admin::ui.text.b49f20d8')); ?>
                                        </div>
                                        <p class="invalid-msg ui-kit-space-before-1 ui-kit-hidden ui-kit-type-sm validation-danger-label"><?php echo sr_e(sr_t('admin::ui.text.286154b2')); ?></p>
                                    </div>

                                    <!-- Terms -->
                                    <div class="ui-kit-column-md-12">
                                        <div class="ui-kit-cluster ui-kit-wrap ui-kit-align-items-center">
                                            <input id="customTerms" type="checkbox" required class="form-checkbox" />
                                            <label for="customTerms" class="ui-kit-start-margin-2 ui-kit-type-sm ui-kit-ink-default-700"><?php echo sr_e(sr_t('admin::ui.text.867eda7d')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                            <p class="invalid-msg ui-kit-space-before-2 ui-kit-hidden ui-kit-fill-width ui-kit-type-sm validation-danger-label"><?php echo sr_e(sr_t('admin::ui.text.6d48c575')); ?></p>
                                        </div>
                                    </div>

                                    <!-- Submit -->
                                    <div class="ui-kit-column-md-12">
                                        <button type="submit"
                                            class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.submit.form.58504893')); ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.server.side.28a1abe1')); ?></h4>
                            </div>
                            <div class="card-body">
                                <form id="serverForm" class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-md-12 ui-kit-gap-base" novalidate>
                                    <!-- First name -->
                                    <div class="ui-kit-column-md-4">
                                        <label for="serverFirstName" class="form-label"><?php echo sr_e(sr_t('admin::ui.name.first.name.04ab2143')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                        <div class="validation-field">
                                            <input type="text" id="serverFirstName" value="Mark" required
                                                class="form-input form-input-valid" />
                                            <div
                                                class="validation-static-icon">
                                                <?php echo sr_material_icon_html('check', 'validation-success-icon', sr_t('admin::ui.text.35688a85')); ?>
                                            </div>
                                        </div>
                                        <p class="validation-success-note"><?php echo sr_e(sr_t('admin::ui.text.78dc433d')); ?></p>
                                    </div>

                                    <!-- Last name -->
                                    <div class="ui-kit-column-md-4">
                                        <label for="serverLastName" class="form-label"><?php echo sr_e(sr_t('admin::ui.last.name.dfe12126')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                        <div class="validation-field">
                                            <input type="text" id="serverLastName" value="Otto" required
                                                class="form-input form-input-valid" />
                                            <div
                                                class="validation-static-icon">
                                                <?php echo sr_material_icon_html('check', 'validation-success-icon', sr_t('admin::ui.text.35688a85')); ?>
                                            </div>
                                        </div>
                                        <p class="validation-success-note"><?php echo sr_e(sr_t('admin::ui.text.78dc433d')); ?></p>
                                    </div>

                                    <!-- Username -->
                                    <div class="ui-kit-column-md-4">
                                        <label for="serverUsername" class="form-label"><?php echo sr_e(sr_t('admin::ui.active.name.username.b19d3d5e')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                        <div class="validation-field ui-kit-cluster ui-kit-radius-md">
                                            <span
                                                class="validation-addon">@</span>
                                            <input type="text" id="serverUsername" name="username"
                                                class="form-input form-input-invalid form-control-group-end"
                                                placeholder="johndoe123" required />
                                            <div
                                                class="validation-static-icon">
                                                <?php echo sr_material_icon_html('info', 'validation-error-icon', sr_t('admin::ui.text.b8cf07ac')); ?>
                                            </div>
                                        </div>
                                        <p class="validation-error-note"><?php echo sr_e(sr_t('admin::ui.active.name.select.4de06bf1')); ?></p>
                                    </div>

                                    <!-- City -->
                                    <div class="ui-kit-column-md-6">
                                        <label for="serverCity" class="form-label"><?php echo sr_e(sr_t('admin::ui.city.68649934')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                        <div class="validation-field">
                                            <input type="text" id="serverCity" required placeholder="<?php echo sr_e(sr_t('admin::ui.text.51c3d48a')); ?>"
                                                class="form-input form-input-invalid" />
                                            <div
                                                class="validation-static-icon">
                                                <?php echo sr_material_icon_html('info', 'validation-error-icon', sr_t('admin::ui.text.b8cf07ac')); ?>
                                            </div>
                                        </div>
                                        <p class="validation-error-note"><?php echo sr_e(sr_t('admin::ui.text.09359b44')); ?></p>
                                    </div>

                                    <!-- State -->
                                    <div class="ui-kit-column-md-3">
                                        <label for="serverState" class="form-label"><?php echo sr_e(sr_t('admin::ui.state.b93c5a3b')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                        <div class="validation-field">
                                            <select id="serverState" required class="form-select form-select-invalid">
                                                <option value=""><?php echo sr_e(sr_t('admin::ui.select.5b1efda0')); ?></option>
                                                <option>California</option>
                                                <option>Texas</option>
                                                <option>Florida</option>
                                            </select>
                                            <div
                                                class="validation-static-icon validation-static-icon-select">
                                                <?php echo sr_material_icon_html('info', 'validation-error-icon', sr_t('admin::ui.text.b8cf07ac')); ?>
                                            </div>
                                        </div>
                                        <p class="validation-error-note"><?php echo sr_e(sr_t('admin::ui.select.cf278ee1')); ?></p>
                                    </div>

                                    <!-- Zip -->
                                    <div class="ui-kit-column-md-3">
                                        <label for="serverZip" class="form-label"><?php echo sr_e(sr_t('admin::ui.zip.918b7b76')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                        <div class="validation-field">
                                            <input type="text" id="serverZip" required placeholder="<?php echo sr_e(sr_t('admin::ui.text.1b50041e')); ?>"
                                                class="form-input form-input-invalid" />
                                            <div
                                                class="validation-static-icon">
                                                <?php echo sr_material_icon_html('info', 'validation-error-icon', sr_t('admin::ui.text.b8cf07ac')); ?>
                                            </div>
                                        </div>
                                        <p class="validation-error-note"><?php echo sr_e(sr_t('admin::ui.text.286154b2')); ?></p>
                                    </div>

                                    <!-- Checkbox -->
                                    <div class="ui-kit-column-md-12">
                                        <label class="ui-kit-cluster ui-kit-align-items-center ui-kit-inline-space-2" for="serverTerms">
                                            <input type="checkbox" id="serverTerms" required
                                                class="form-checkbox form-choice-danger" />
                                            <span class="validation-danger-label"><?php echo sr_e(sr_t('admin::ui.text.07231b0d')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span>
                                        </label>
                                        <p class="validation-error-note"><?php echo sr_e(sr_t('admin::ui.text.e3bc5fd9')); ?></p>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="ui-kit-column-md-12">
                                        <button type="submit"
                                            class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.submit.form.353c958a')); ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.supported.elements.3d56cb84')); ?></h4>
                            </div>
                            <div class="card-body">
                                <form class="ui-kit-stack-6" novalidate>
                                    <!-- Textarea -->
                                    <div>
                                        <label for="validationTextarea" class="form-label"><?php echo sr_e(sr_t('admin::ui.textarea.9cbf1bae')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                        <textarea id="validationTextarea" placeholder="<?php echo sr_e(sr_t('admin::ui.required.9ac81744')); ?>" required
                                            class="form-textarea form-textarea-invalid"></textarea>
                                        <p class="validation-error-note"><?php echo sr_e(sr_t('admin::ui.text.e7b2bd4a')); ?></p>
                                    </div>

                                    <!-- Checkbox -->
                                    <div class="ui-kit-cluster ui-kit-align-items-start ui-kit-gap-2">
                                        <input id="validationFormCheck1" type="checkbox" required
                                            class="form-checkbox form-choice-danger form-choice-invalid form-choice-offset" />
                                        <div>
                                            <label for="validationFormCheck1" class="validation-danger-label"><?php echo sr_e(sr_t('admin::ui.text.31cc6171')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                            <p class="validation-error-note"><?php echo sr_e(sr_t('admin::ui.text.3388da7e')); ?></p>
                                        </div>
                                    </div>

                                    <!-- Radios -->
                                    <div class="ui-kit-stack-2">
                                        <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                            <input id="validationFormCheck2" type="radio" name="radio-stacked" required
                                                class="form-radio form-choice-danger form-choice-invalid" />
                                            <label for="validationFormCheck2" class="validation-danger-label"><?php echo sr_e(sr_t('admin::ui.text.724f8e18')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                        </div>

                                        <div class="ui-kit-cluster ui-kit-align-items-center ui-kit-gap-2">
                                            <input id="validationFormCheck3" type="radio" name="radio-stacked" required
                                                class="form-radio form-choice-danger form-choice-invalid" />
                                            <label for="validationFormCheck3" class="validation-danger-label"><?php echo sr_e(sr_t('admin::ui.text.2407692e')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                            <p class="validation-error-note"><?php echo sr_e(sr_t('admin::ui.text.6905bf2e')); ?></p>
                                        </div>
                                    </div>

                                    <!-- Select -->
                                    <div>
                                        <span class="form-label"><?php echo sr_e(sr_t('admin::ui.select.menu.76cfe1a7')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span>
                                        <select required class="form-select form-select-valid">
                                            <option value=""><?php echo sr_e(sr_t('admin::ui.select.menu.0c8ad3cb')); ?></option>
                                            <option value="1"><?php echo sr_e(sr_t('admin::ui.text.556dcbf0')); ?></option>
                                            <option value="2"><?php echo sr_e(sr_t('admin::ui.text.ca76b128')); ?></option>
                                            <option value="3"><?php echo sr_e(sr_t('admin::ui.text.28ed1f7d')); ?></option>
                                        </select>
                                    </div>

                                    <!-- File Input -->
                                    <div>
                                        <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.0c8354d0')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span>
                                        <input type="file" required class="form-input" />
                                        <p class="validation-error-note"><?php echo sr_e(sr_t('admin::ui.text.8bce73cb')); ?></p>
                                    </div>

                                    <!-- Submit -->
                                    <div>
                                        <button type="submit" disabled
                                            class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.submit.form.449afbaf')); ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('admin::ui.browser.defaults.8abc599f')); ?></h4>
                            </div>
                            <div class="card-body">
                                <form action="">
                                    <div class="ui-kit-grid ui-kit-grid-md-3 ui-kit-grid-1 ui-kit-gap-base ui-kit-space-after-base">
                                        <div>
                                            <label for="validationDefault01" class="form-label"><?php echo sr_e(sr_t('admin::ui.name.first.name.04ab2143')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                            <input type="text" class="form-input" id="validationDefault01" value="Mark"
                                                required="" />
                                        </div>

                                        <div>
                                            <label for="validationDefault02" class="form-label"><?php echo sr_e(sr_t('admin::ui.last.name.dfe12126')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                            <input type="text" class="form-input" id="validationDefault02" value="Otto"
                                                required="" />
                                        </div>

                                        <div>
                                            <label for="validationDefaultUsername" class="form-label"><?php echo sr_e(sr_t('admin::ui.active.name.username.e89a222c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                            <div class="input-group">
                                                <span class="input-group-text" id="inputGroupPrepend2">@</span>
                                                <input type="text" class="form-input" id="validationDefaultUsername"
                                                    aria-describedby="inputGroupPrepend2" required="" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ui-kit-grid ui-kit-grid-md-4 ui-kit-grid-1 ui-kit-gap-base ui-kit-space-after-base">
                                        <div class="ui-kit-column-md-2 ui-kit-column-1">
                                            <label for="validationDefault03" class="form-label"><?php echo sr_e(sr_t('admin::ui.city.68649934')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                            <input type="text" class="form-input" id="validationDefault03"
                                                required="" />
                                        </div>

                                        <div>
                                            <label for="validationDefault04" class="form-label"><?php echo sr_e(sr_t('admin::ui.state.b93c5a3b')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                            <select class="form-select" id="validationDefault04" required="">
                                                <option selected="" disabled="" value=""><?php echo sr_e(sr_t('admin::ui.select.5b1efda0')); ?></option>
                                                <option>...</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="validationDefault05" class="form-label"><?php echo sr_e(sr_t('admin::ui.zip.918b7b76')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                            <input type="text" class="form-input" id="validationDefault05"
                                                required="" />
                                        </div>
                                    </div>

                                    <div>
                                        <label class="ui-kit-cluster ui-kit-align-items-center ui-kit-inline-space-2" for="invalidCheck2">
                                            <input type="checkbox" id="invalidCheck2" required class="form-checkbox" />
                                            <span class="ui-kit-ink-default-700"><?php echo sr_e(sr_t('admin::ui.text.07231b0d')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span>
                                        </label>
                                    </div>

                                    <div class="ui-kit-space-before-base">
                                        <button class="btn btn-solid-primary"
                                            type="submit"><?php echo sr_e(sr_t('admin::ui.submit.form.d55e232f')); ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
</div>
</div>
