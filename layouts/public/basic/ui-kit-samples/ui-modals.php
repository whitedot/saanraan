<div class="ui-kit-sample-section" data-ui-kit-sample="ui-modals">
<div class="container-fluid">
                    <div class="ui-kit-grid ui-kit-grid-1 ui-kit-gap-base">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo sr_e(sr_t('ui.modals.c78d69ef')); ?></h4>
                            </div>

                            <div class="card-body">
                                <p class="ui-kit-ink-default-400 ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.7e7bb680')); ?></p>
                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                    <!-- Standard modal content -->
                                    <div>
                                        <button type="button"
                                            class="btn btn-solid-primary"
                                            aria-haspopup="dialog" aria-expanded="false" aria-controls="standard-modal"
                                            data-overlay="#standard-modal"><?php echo sr_e(sr_t('ui.text.a31d2287')); ?></button>

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
                                                            <?php echo sr_e(sr_t('ui.text.70c1de4a')); ?></h3>
                                                        <button type="button" class="modal-close" aria-label="Close"
                                                            data-overlay="#standard-modal">
                                                            <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                            <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        </button>
                                                    </div>

                                                    <div class="modal-body">
                                                        <h5 class="ui-kit-space-after-2"><?php echo sr_e(sr_t('ui.text.e2e62d72')); ?></h5>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.48bc8889')); ?>
                                                        </p>
                                                        <hr class="ui-kit-line-default-300 ui-kit-block-space-4" />
                                                        <h5 class="ui-kit-space-after-2"><?php echo sr_e(sr_t('ui.text.aa096f4f')); ?></h5>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.2d5768ee')); ?>
                                                        </p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.active.e21b042b')); ?></p>
                                                        <p><?php echo sr_e(sr_t('ui.active.e8f7e1aa')); ?></p>
                                                    </div>

                                                    <div
                                                        class="modal-footer">
                                                        <button type="button"
                                                            class="btn btn-soft-default modal-action"
                                                            data-overlay="#standard-modal"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                        <button type="button"
                                                            class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.cc69ae1b')); ?></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!--  Modal content for the Large example -->
                                    <div>
                                        <button type="button" class="btn btn-solid-primary"
                                            aria-haspopup="dialog" aria-expanded="false"
                                            aria-controls="bs-example-modal-lg"
                                            data-overlay="#bs-example-modal-lg"><?php echo sr_e(sr_t('ui.text.efbc0d6c')); ?></button>

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
                                                            class="modal-title"><?php echo sr_e(sr_t('ui.text.efbc0d6c')); ?></h3>
                                                        <button type="button" class="modal-close" aria-label="Close"
                                                            data-overlay="#bs-example-modal-lg">
                                                            <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                            <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body"><?php echo sr_e(sr_t('ui.text.01025abc')); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!--  Modal content for the Small example -->
                                    <div>
                                        <button type="button"
                                            class="btn btn-solid-primary"
                                            aria-haspopup="dialog" aria-expanded="false"
                                            aria-controls="bs-example-modal-sm"
                                            data-overlay="#bs-example-modal-sm"><?php echo sr_e(sr_t('ui.text.87b9e8c6')); ?></button>

                                        <div id="bs-example-modal-sm"
                                            class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                            role="dialog" tabindex="-1" aria-labelledby="bs-example-modal-sm-label">
                                            <div class="modal-dialog-sm">
                                                <div
                                                    class="modal-content">
                                                    <div
                                                        class="modal-header">
                                                        <h3 id="bs-example-modal-sm-label"
                                                            class="modal-title"><?php echo sr_e(sr_t('ui.text.87b9e8c6')); ?></h3>
                                                        <button type="button" class="modal-close" aria-label="Close"
                                                            data-overlay="#bs-example-modal-sm">
                                                            <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                            <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        </button>
                                                    </div>

                                                    <div class="modal-body"><?php echo sr_e(sr_t('ui.text.01025abc')); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Full width modal content -->
                                    <div>
                                        <button type="button"
                                            class="btn btn-solid-primary"
                                            aria-haspopup="dialog" aria-expanded="false"
                                            aria-controls="full-width-modal" data-overlay="#full-width-modal"><?php echo sr_e(sr_t('ui.all.0c976cbf')); ?></button>

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
                                                            <?php echo sr_e(sr_t('ui.text.70c1de4a')); ?></h3>
                                                        <button type="button" class="modal-close" aria-label="Close"
                                                            data-overlay="#full-width-modal">
                                                            <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                            <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        </button>
                                                    </div>

                                                    <div class="modal-body">
                                                        <h5 class="ui-kit-space-after-2"><?php echo sr_e(sr_t('ui.text.e2e62d72')); ?></h5>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.all.active.f80dbc9e')); ?></p>
                                                        <hr class="ui-kit-line-default-300 ui-kit-block-space-4" />
                                                        <h5 class="ui-kit-space-after-2"><?php echo sr_e(sr_t('ui.text.aa096f4f')); ?></h5>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.all.active.e049f4e9')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.62923020')); ?></p>
                                                        <p><?php echo sr_e(sr_t('ui.active.14f74c3f')); ?></p>
                                                    </div>

                                                    <div
                                                        class="modal-footer">
                                                        <button type="button"
                                                            class="btn btn-soft-default modal-action"
                                                            data-overlay="#full-width-modal"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                        <button type="button"
                                                            class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.cc69ae1b')); ?></button>
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
                                            aria-controls="scrollable-modal" data-overlay="#scrollable-modal"><?php echo sr_e(sr_t('ui.text.1a3608ec')); ?></button>

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
                                                            <?php echo sr_e(sr_t('ui.text.70c1de4a')); ?></h3>
                                                        <button type="button" class="modal-close" aria-label="Close"
                                                            data-overlay="#scrollable-modal">
                                                            <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                            <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        </button>
                                                    </div>

                                                    <div class="modal-body-scroll">
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.active.c990082a')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.active.all.f294c133')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.active.a2124c01')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.08bab8cd')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.68f743d8')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.0a5185cd')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.5c726503')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.ux.active.08e9823e')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.036164df')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.d94ecc29')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.44457493')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.0eb2d7ab')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.e3dcdf0d')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.2c6a529e')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.active.d80e89e6')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.223f5dbb')); ?></p>
                                                        <p class="ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.text.049fe5f6')); ?></p>
                                                        <p><?php echo sr_e(sr_t('ui.text.b9c6aa3d')); ?></p>
                                                    </div>

                                                    <div
                                                        class="modal-footer">
                                                        <button type="button"
                                                            class="btn btn-solid-secondary modal-action"
                                                            data-overlay="#scrollable-modal"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                        <button type="button"
                                                            class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.cc69ae1b')); ?></button>
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
                            <h4 class="card-title"><?php echo sr_e(sr_t('ui.modal.position.60f37d79')); ?></h4>
                        </div>

                        <div class="card-body">
                            <p class="ui-kit-ink-default-400 ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.page.354cddad')); ?></p>

                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                <!-- Top modal content -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-secondary"
                                        aria-haspopup="dialog" aria-expanded="false" aria-controls="top-modal"
                                        data-overlay="#top-modal"><?php echo sr_e(sr_t('ui.text.9b2af47e')); ?></button>

                                    <div id="top-modal"
                                        class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                        role="dialog" tabindex="-1" aria-labelledby="top-modal-label">
                                        <div class="modal-dialog">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="top-modal-label" class="modal-title"><?php echo sr_e(sr_t('ui.text.70c1de4a')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#top-modal">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <h5 class="ui-kit-space-after-2"><?php echo sr_e(sr_t('ui.text.e2e62d72')); ?></h5>
                                                    <p><?php echo sr_e(sr_t('ui.notification.settings.623a999c')); ?></p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-soft-default modal-action"
                                                        data-overlay="#top-modal"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.011c4ffe')); ?></button>
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
                                        data-overlay="#bottom-modal"><?php echo sr_e(sr_t('ui.text.08c1e17c')); ?></button>

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
                                                        <?php echo sr_e(sr_t('ui.text.70c1de4a')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#bottom-modal">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <h5 class="ui-kit-space-after-2"><?php echo sr_e(sr_t('ui.text.e2e62d72')); ?></h5>
                                                    <p><?php echo sr_e(sr_t('ui.active.660b1c39')); ?></p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-soft-default modal-action"
                                                        data-overlay="#bottom-modal"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.011c4ffe')); ?></button>
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
                                        data-overlay="#centermodal"><?php echo sr_e(sr_t('ui.text.aafa9b08')); ?></button>

                                    <div id="centermodal"
                                        class="modal-overlay-bottom overlay ui-kit-state-hidden ui-kit-state-disabled-pointer"
                                        role="dialog" tabindex="-1" aria-labelledby="centermodal-label">
                                        <div
                                            class="modal-dialog-center">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="centermodal-label" class="modal-title"><?php echo sr_e(sr_t('ui.text.70c1de4a')); ?>
                                                    </h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#centermodal">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <h5 class="ui-kit-space-after-2"><?php echo sr_e(sr_t('ui.text.e2e62d72')); ?></h5>
                                                    <p><?php echo sr_e(sr_t('ui.active.dc3e0c96')); ?></p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-soft-default modal-action"
                                                        data-overlay="#centermodal"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.011c4ffe')); ?></button>
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
                            <h4 class="card-title"><?php echo sr_e(sr_t('ui.multiple.modal.4d07d17a')); ?></h4>
                        </div>

                        <div class="card-body">
                            <p class="ui-kit-ink-default-400 ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.active.623f1f1c')); ?></p>
                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                <!-- Modal Heading -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-primary"
                                        aria-haspopup="dialog" aria-expanded="false" aria-controls="multiple-one"
                                        data-overlay="#multiple-one"><?php echo sr_e(sr_t('ui.text.3303bc36')); ?></button>

                                    <div id="multiple-one"
                                        class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent"
                                        role="dialog" tabindex="-1" aria-labelledby="multiple-one-label">
                                        <div class="modal-dialog">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="multiple-one-label" class="modal-title">
                                                        <?php echo sr_e(sr_t('ui.text.e7087ac6')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#multiple-one">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <h5 class="ui-kit-space-after-2"><?php echo sr_e(sr_t('ui.text.6a7618af')); ?></h5>
                                                    <p><?php echo sr_e(sr_t('ui.text.6745faf4')); ?></p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button"
                                                        class="btn btn-solid-primary"
                                                        aria-haspopup="dialog" aria-expanded="false"
                                                        aria-controls="multiple-two" data-overlay="#multiple-two"><?php echo sr_e(sr_t('ui.text.63cd5285')); ?></button>
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
                                                        <?php echo sr_e(sr_t('ui.text.53ae0906')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#multiple-two">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <h5 class="ui-kit-space-after-2"><?php echo sr_e(sr_t('ui.text.54c1bfe3')); ?></h5>
                                                    <p><?php echo sr_e(sr_t('ui.close.6278c252')); ?></p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"
                                                        data-overlay="#multiple-two"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>
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
                            <h4 class="card-title"><?php echo sr_e(sr_t('ui.toggle.between.modals.75a64fa1')); ?></h4>
                        </div>

                        <div class="card-body">
                            <p class="ui-kit-ink-default-400 ui-kit-space-after-4"><code>data-overlay</code> <?php echo sr_e(sr_t('ui.text.1f4997e4')); ?></p>
                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-secondary"
                                        aria-haspopup="dialog" aria-expanded="false" aria-controls="exampleModalToggle1"
                                        data-overlay="#exampleModalToggle1"><?php echo sr_e(sr_t('ui.text.fdc5ece2')); ?></button>

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
                                                        <?php echo sr_e(sr_t('ui.1.a04ecf87')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalToggle1">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <p><?php echo sr_e(sr_t('ui.active.c4b6564e')); ?></p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button"
                                                        class="btn btn-solid-primary"
                                                        aria-haspopup="dialog" aria-expanded="false"
                                                        aria-controls="exampleModalToggle2"
                                                        data-overlay="#exampleModalToggle2">
                                                        <?php echo sr_e(sr_t('ui.text.a6a62630')); ?>
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
                                                        <?php echo sr_e(sr_t('ui.2.e7ab7d3d')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalToggle2">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <p><?php echo sr_e(sr_t('ui.text.291f1669')); ?></p>
                                                </div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"
                                                        data-overlay="#exampleModalToggle1"><?php echo sr_e(sr_t('ui.text.56db1f70')); ?></button>
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
                            <h4 class="card-title"><?php echo sr_e(sr_t('ui.all.fullscreen.modal.cfc5e840')); ?></h4>
                        </div>

                        <div class="card-body">
                            <p class="ui-kit-ink-default-400 ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.active.all.07d37118')); ?></p>
                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                <!-- Full Screen Modal -->
                                <div>
                                    <button type="button"
                                        class="btn btn-solid-primary"
                                        aria-haspopup="dialog" aria-expanded="false"
                                        aria-controls="fullscreeexampleModal"
                                        data-overlay="#fullscreeexampleModal"><?php echo sr_e(sr_t('ui.all.a511f914')); ?></button>

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
                                                        class="modal-title"><?php echo sr_e(sr_t('ui.all.a511f914')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#fullscreeexampleModal">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill"><?php echo sr_e(sr_t('ui.text.01025abc')); ?></div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-soft-default modal-action"
                                                        data-overlay="#fullscreeexampleModal"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.011c4ffe')); ?></button>
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
                                        <?php echo sr_e(sr_t('ui.sm.all.46c07fe1')); ?>
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
                                                        class="modal-title"><?php echo sr_e(sr_t('ui.sm.all.46c07fe1')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalFullscreenSm">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill"><?php echo sr_e(sr_t('ui.text.01025abc')); ?></div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-soft-default modal-action"
                                                        data-overlay="#exampleModalFullscreenSm"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.011c4ffe')); ?></button>
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
                                        <?php echo sr_e(sr_t('ui.md.all.72277b49')); ?>
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
                                                        class="modal-title"><?php echo sr_e(sr_t('ui.md.all.72277b49')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalFullscreenMd">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill"><?php echo sr_e(sr_t('ui.text.01025abc')); ?></div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-soft-default modal-action"
                                                        data-overlay="#exampleModalFullscreenMd"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.011c4ffe')); ?></button>
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
                                        <?php echo sr_e(sr_t('ui.lg.all.e1be95a5')); ?>
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
                                                        class="modal-title"><?php echo sr_e(sr_t('ui.lg.all.e1be95a5')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalFullscreenLg">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill"><?php echo sr_e(sr_t('ui.text.01025abc')); ?></div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-soft-default modal-action"
                                                        data-overlay="#exampleModalFullscreenLg"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.011c4ffe')); ?></button>
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
                                        <?php echo sr_e(sr_t('ui.xl.all.73cd73a0')); ?>
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
                                                        class="modal-title"><?php echo sr_e(sr_t('ui.xl.all.73cd73a0')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalFullscreenXl">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill"><?php echo sr_e(sr_t('ui.text.01025abc')); ?></div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-soft-default modal-action"
                                                        data-overlay="#exampleModalFullscreenXl"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.011c4ffe')); ?></button>
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
                                        <?php echo sr_e(sr_t('ui.xxl.all.9f6190c6')); ?>
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
                                                        class="modal-title"><?php echo sr_e(sr_t('ui.xxl.all.9f6190c6')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#exampleModalFullscreenXxl">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body-fill"><?php echo sr_e(sr_t('ui.text.01025abc')); ?></div>

                                                <div
                                                    class="modal-footer">
                                                    <button type="button" class="btn btn-soft-default modal-action"
                                                        data-overlay="#exampleModalFullscreenXxl"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.011c4ffe')); ?></button>
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
                            <h4 class="card-title"><?php echo sr_e(sr_t('ui.static.backdrop.3e621249')); ?></h4>
                        </div>

                        <div class="card-body">
                            <p class="ui-kit-ink-default-400 ui-kit-space-after-4"><?php echo sr_e(sr_t('ui.static.settings.4d8a4647')); ?></p>
                            <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2-5">
                                <!-- Static Backdrop modal -->
                                <div>
                                    <button type="button" class="btn btn-solid-info"
                                        aria-haspopup="dialog" aria-expanded="false" aria-controls="staticBackdrop"
                                        data-overlay="#staticBackdrop"><?php echo sr_e(sr_t('ui.text.b1ec1529')); ?></button>

                                    <div id="staticBackdrop"
                                        class="modal-overlay modal-overlay-fade overlay ui-kit-state-hidden ui-kit-state-disabled-pointer ui-kit-state-transparent" data-overlay-static="true"
                                        role="dialog" tabindex="-1" aria-labelledby="staticBackdrop-label">
                                        <div class="modal-dialog">
                                            <div
                                                class="modal-content">
                                                <div
                                                    class="modal-header">
                                                    <h3 id="staticBackdrop-label" class="modal-title">
                                                        <?php echo sr_e(sr_t('ui.text.70c1de4a')); ?></h3>

                                                    <button type="button" class="modal-close" aria-label="Close"
                                                        data-overlay="#staticBackdrop">
                                                        <span class="sr-only"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                        <span class="ui-kit-icon-text"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></span>
                                                    </button>
                                                </div>

                                                <div class="modal-body">
                                                    <p><?php echo sr_e(sr_t('ui.esc.settings.6c5eaa31')); ?></p>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button"
                                                        class="btn btn-solid-secondary modal-action"
                                                        data-overlay="#staticBackdrop"><?php echo sr_e(sr_t('ui.close.1e8c1020')); ?></button>

                                                    <button type="button"
                                                        class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('ui.save.011c4ffe')); ?></button>
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
