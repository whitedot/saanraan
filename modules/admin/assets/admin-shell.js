// 관리자 공통 shell 동작.
// sidebar, profile menu, mobile overlay 같은 레이아웃 상태만 담당하고, 화면별 업무 로직은 각 admin-*.js로 분리한다.
window.AdminShell = {
    initialized: false,

    init() {
        if (this.initialized) {
            return;
        }

        this.initialized = true;

        const menuStorageKey = 'sr_admin_sidebar_collapsed';
        const mobileQuery = window.matchMedia('(max-width: 1023px)');
        const body = document.body;
        const gnb = document.getElementById('gnb');
        const container = document.getElementById('container');
        const desktopToggle = document.getElementById('btn_gnb');
        const mobileToggle = document.getElementById('btn_gnb_mobile');
        const sidebarBackdrop = document.getElementById('adminSidebarBackdrop');
        const profileButton = document.querySelector('.tnb_mb_btn');
        const profileMenu = document.querySelector('.tnb_mb_area');
        const scrollWrap = document.querySelector('#gnb .gnb_menu_scroll_wrap');
        const menuScroll = document.getElementById('gnbMenuScroll');
        const scrollbar = scrollWrap ? scrollWrap.querySelector('.gnb_scrollbar') : null;
        const scrollThumb = scrollWrap ? scrollWrap.querySelector('.gnb_scrollbar_thumb') : null;
        const themeToggle = document.getElementById('admin_theme_toggle');
        const themeToggleIcon = document.getElementById('admin_theme_toggle_icon');
        const navRoot = document.getElementById('adminNavList');
        const scrollTopButton = document.querySelector('.admin-footer-scroll-top');
        const toastStack = document.querySelector('[data-admin-toast-stack]');
        const colorSchemeSelect = document.querySelector('[data-admin-color-scheme-select]');
        const sortableRows = Array.prototype.slice.call(document.querySelectorAll('[data-admin-sortable-row]'));
        const tabRoot = document.querySelector('[data-admin-tabs]');
        const memberRuleDefinition = document.querySelector('[data-member-rule-definition]');
        const dateQuickButtons = Array.prototype.slice.call(document.querySelectorAll('[data-datetime-target]'));
        const dashboardSectionsRoot = document.querySelector('[data-admin-dashboard-sections]');
        let hideScrollbarTimer = null;
        let themeSaving = false;

        const isMobileViewport = () => mobileQuery.matches;
        const systemColorSchemeQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

        const systemPrefersDark = () => systemColorSchemeQuery && systemColorSchemeQuery.matches;

        const resolvedThemeForScheme = scheme => {
            if (scheme === 'dark') {
                return 'dark';
            }

            if (scheme === 'system' && systemPrefersDark()) {
                return 'dark';
            }

            return 'light';
        };

        const applyColorScheme = scheme => {
            const nextScheme = ['light', 'dark', 'system'].includes(scheme) ? scheme : 'light';
            const nextTheme = resolvedThemeForScheme(nextScheme);
            document.documentElement.setAttribute('data-color-scheme', nextScheme);
            document.documentElement.setAttribute('data-theme', nextTheme);
            syncThemeUI();
        };

        const updateMenuScrollbar = () => {
            if (!scrollWrap || !menuScroll || !scrollbar || !scrollThumb) {
                return;
            }

            const scrollHeight = menuScroll.scrollHeight;
            const clientHeight = menuScroll.clientHeight;
            const canScroll = scrollHeight > clientHeight + 1;

            scrollWrap.classList.toggle('is-scrollable', canScroll);

            if (!canScroll) {
                scrollThumb.style.height = '0';
                scrollThumb.style.transform = 'translateY(0)';
                return;
            }

            const trackHeight = scrollbar.getBoundingClientRect().height;
            const thumbHeight = Math.max(28, Math.round(trackHeight * (clientHeight / scrollHeight)));
            const maxThumbTop = Math.max(0, trackHeight - thumbHeight);
            const maxScrollTop = Math.max(1, scrollHeight - clientHeight);
            const thumbTop = Math.round((menuScroll.scrollTop / maxScrollTop) * maxThumbTop);

            scrollThumb.style.height = `${thumbHeight}px`;
            scrollThumb.style.transform = `translateY(${thumbTop}px)`;
        };

        const syncDesktopSidebarState = () => {
            if (!gnb || !container || !desktopToggle) {
                return;
            }

            const collapsed = gnb.classList.contains('gnb_small');
            const desktopCollapsed = !isMobileViewport() && collapsed;
            body.classList.toggle('admin-sidebar-condensed', desktopCollapsed);
            container.classList.toggle('container-small', desktopCollapsed);
            desktopToggle.classList.toggle('btn_gnb_open', desktopCollapsed);
            desktopToggle.setAttribute('aria-pressed', desktopCollapsed ? 'true' : 'false');
        };

        const setDesktopCollapsed = nextCollapsed => {
            try {
                localStorage.setItem(menuStorageKey, nextCollapsed ? '1' : '0');
            } catch (err) {}

            if (gnb) {
                gnb.classList.toggle('gnb_small', nextCollapsed);
            }
            syncDesktopSidebarState();
        };

        const setMobileSidebar = opened => {
            if (!isMobileViewport()) {
                return;
            }

            body.classList.toggle('admin-sidebar-open', opened);
            body.classList.toggle('overflow-hidden', opened);

            if (mobileToggle) {
                mobileToggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
            }

            if (sidebarBackdrop) {
                sidebarBackdrop.classList.toggle('hidden', !opened);
            }
        };

        const showMenuScrollbar = () => {
            if (!scrollWrap || !scrollWrap.classList.contains('is-scrollable')) {
                return;
            }

            clearTimeout(hideScrollbarTimer);
            scrollWrap.classList.add('is-scrollbar-visible');
        };

        const hideMenuScrollbar = delay => {
            if (!scrollWrap) {
                return;
            }

            clearTimeout(hideScrollbarTimer);
            hideScrollbarTimer = window.setTimeout(() => {
                scrollWrap.classList.remove('is-scrollbar-visible');
            }, delay || 140);
        };

        const syncThemeUI = () => {
            if (!themeToggle || !themeToggleIcon) {
                return;
            }

            const dark = document.documentElement.getAttribute('data-theme') === 'dark';
            const nextModeLabel = dark ? '라이트 모드' : '다크 모드';
            const iconName = dark ? 'light_mode' : 'dark_mode';
            themeToggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
            themeToggle.setAttribute('aria-label', `${nextModeLabel} 전환`);
            themeToggle.setAttribute('title', `${nextModeLabel} 전환`);
            themeToggle.disabled = themeSaving;
            themeToggleIcon.textContent = iconName;
        };

        const setNavItemState = (item, opened) => {
            if (!item) {
                return;
            }

            item.classList.toggle('is-open', opened);

            const panel = item.querySelector('.admin-nav-panel');
            if (panel) {
                panel.classList.toggle('hidden', !opened);
            }

            const trigger = item.querySelector('.admin-nav-trigger');
            if (trigger) {
                trigger.setAttribute('aria-expanded', opened ? 'true' : 'false');
            }
        };

        if (profileButton && profileMenu) {
            profileButton.addEventListener('click', () => {
                profileMenu.classList.toggle('hidden');
            });

            document.addEventListener('click', event => {
                if (!event.target.closest('.tnb_li.relative')) {
                    profileMenu.classList.add('hidden');
                }
            });
        }

        if (desktopToggle) {
            desktopToggle.addEventListener('click', () => {
                const nextCollapsed = !(gnb && gnb.classList.contains('gnb_small'));
                setDesktopCollapsed(nextCollapsed);
            });
        }

        if (mobileToggle) {
            mobileToggle.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();

                if (isMobileViewport()) {
                    setMobileSidebar(!body.classList.contains('admin-sidebar-open'));
                    return;
                }

                if (gnb && gnb.classList.contains('gnb_small')) {
                    setDesktopCollapsed(false);
                }
            });
        }

        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', () => {
                setMobileSidebar(false);
            });
        }

        window.addEventListener('resize', () => {
            if (!isMobileViewport()) {
                body.classList.remove('admin-sidebar-open', 'overflow-hidden');
                if (mobileToggle) {
                    mobileToggle.setAttribute('aria-expanded', 'false');
                }
                if (sidebarBackdrop) {
                    sidebarBackdrop.classList.add('hidden');
                }
            }

            syncDesktopSidebarState();
            updateMenuScrollbar();
        });

        if (gnb) {
            gnb.addEventListener('click', event => {
                if (isMobileViewport() && event.target.closest('a')) {
                    setMobileSidebar(false);
                }
            });
        }

        if (menuScroll) {
            menuScroll.addEventListener('scroll', () => {
                updateMenuScrollbar();
                showMenuScrollbar();
                hideMenuScrollbar(420);
            });
        }

        if (scrollWrap) {
            scrollWrap.addEventListener('mouseenter', () => {
                updateMenuScrollbar();
                showMenuScrollbar();
            });

            scrollWrap.addEventListener('mouseleave', () => {
                hideMenuScrollbar(120);
            });

            scrollWrap.addEventListener('focusin', () => {
                updateMenuScrollbar();
                showMenuScrollbar();
            });

            scrollWrap.addEventListener('focusout', () => {
                window.setTimeout(() => {
                    if (!scrollWrap.contains(document.activeElement)) {
                        hideMenuScrollbar(120);
                    }
                }, 0);
            });
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                if (themeSaving) {
                    return;
                }

                const previousScheme = document.documentElement.getAttribute('data-color-scheme') || 'light';
                const dark = document.documentElement.getAttribute('data-theme') === 'dark';
                const nextScheme = dark ? 'light' : 'dark';
                const endpoint = themeToggle.getAttribute('data-admin-theme-url') || '';
                const csrfToken = themeToggle.getAttribute('data-admin-theme-csrf') || '';

                applyColorScheme(nextScheme);
                if (colorSchemeSelect) {
                    colorSchemeSelect.value = nextScheme;
                }

                if (!endpoint || !csrfToken || !window.fetch) {
                    return;
                }

                themeSaving = true;
                syncThemeUI();

                const bodyParams = new URLSearchParams();
                bodyParams.set('csrf_token', csrfToken);
                bodyParams.set('ui_color_scheme', nextScheme);

                window.fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: bodyParams.toString(),
                }).then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to save color scheme.');
                    }

                    return response.json();
                }).then(payload => {
                    const savedScheme = payload && payload.ui_color_scheme ? payload.ui_color_scheme : nextScheme;
                    applyColorScheme(savedScheme);
                    if (colorSchemeSelect) {
                        colorSchemeSelect.value = savedScheme;
                    }
                }).catch(() => {
                    applyColorScheme(previousScheme);
                    if (colorSchemeSelect) {
                        colorSchemeSelect.value = previousScheme;
                    }
                }).finally(() => {
                    themeSaving = false;
                    syncThemeUI();
                });
            });
        }

        if (colorSchemeSelect) {
            colorSchemeSelect.addEventListener('change', () => {
                applyColorScheme(colorSchemeSelect.value);
            });
        }

        if (systemColorSchemeQuery) {
            const syncSystemColorScheme = () => {
                if (document.documentElement.getAttribute('data-color-scheme') === 'system') {
                    applyColorScheme('system');
                }
            };

            if (typeof systemColorSchemeQuery.addEventListener === 'function') {
                systemColorSchemeQuery.addEventListener('change', syncSystemColorScheme);
            } else if (typeof systemColorSchemeQuery.addListener === 'function') {
                systemColorSchemeQuery.addListener(syncSystemColorScheme);
            }
        }

        if (navRoot) {
            const navItems = Array.prototype.slice.call(navRoot.querySelectorAll('.admin-nav-item'));
            navItems.forEach(item => {
                setNavItemState(item, item.classList.contains('is-open'));
            });

            navRoot.addEventListener('click', event => {
                const trigger = event.target.closest('.admin-nav-trigger');
                if (!trigger || !navRoot.contains(trigger)) {
                    return;
                }

                const activeItem = trigger.closest('.admin-nav-item');
                if (!activeItem) {
                    return;
                }

                const willOpen = !activeItem.classList.contains('is-open');
                navItems.forEach(item => {
                    setNavItemState(item, item === activeItem ? willOpen : false);
                });

                updateMenuScrollbar();
            });
        }

        if (scrollTopButton) {
            scrollTopButton.addEventListener('click', event => {
                event.preventDefault();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth',
                });
            });
        }

        if (toastStack) {
            const closeToast = toast => {
                if (!toast) {
                    return;
                }

                toast.classList.add('is-hiding');
                window.setTimeout(() => {
                    toast.remove();
                    if (toastStack.children.length === 0) {
                        toastStack.remove();
                    }
                }, 180);
            };

            toastStack.addEventListener('click', event => {
                const closeButton = event.target.closest('[data-admin-toast-close]');
                if (!closeButton) {
                    return;
                }

                closeToast(closeButton.closest('[data-admin-toast]'));
            });

            Array.prototype.slice.call(toastStack.querySelectorAll('[data-admin-toast]')).forEach(toast => {
                window.setTimeout(() => closeToast(toast), 6500);
            });
        }

        if (sortableRows.length > 0) {
            let draggedRow = null;

            const renumberRows = scope => {
                const rows = Array.prototype.slice.call(document.querySelectorAll(`[data-admin-sortable-row][data-sort-scope="${scope}"]`));
                rows.forEach((row, index) => {
                    const input = row.querySelector('[data-admin-sort-order]');
                    if (input) {
                        input.value = String((index + 1) * 10);
                    }
                });
            };

            sortableRows.forEach(row => {
                row.addEventListener('dragstart', event => {
                    draggedRow = row;
                    row.classList.add('is-dragging');
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', '');
                });

                row.addEventListener('dragend', () => {
                    row.classList.remove('is-dragging');
                    draggedRow = null;
                    renumberRows(row.dataset.sortScope || '');
                });

                row.addEventListener('dragover', event => {
                    if (!draggedRow || draggedRow === row) {
                        return;
                    }

                    if (
                        draggedRow.dataset.sortScope !== row.dataset.sortScope
                        || draggedRow.dataset.sortParent !== row.dataset.sortParent
                    ) {
                        return;
                    }

                    event.preventDefault();
                    const rect = row.getBoundingClientRect();
                    const after = event.clientY > rect.top + rect.height / 2;
                    row.parentNode.insertBefore(draggedRow, after ? row.nextSibling : row);
                });
            });
        }

        if (tabRoot) {
            const buttons = Array.prototype.slice.call(tabRoot.querySelectorAll('[data-admin-tab-target]'));
            const panels = Array.prototype.slice.call(document.querySelectorAll('[data-admin-tab-panel]'));
            const activateTab = tabName => {
                buttons.forEach(button => {
                    const active = button.dataset.adminTabTarget === tabName;
                    button.classList.toggle('active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panels.forEach(panel => {
                    panel.hidden = panel.dataset.adminTabPanel !== tabName;
                });
            };

            buttons.forEach(button => {
                button.setAttribute('role', 'tab');
                button.addEventListener('click', () => activateTab(button.dataset.adminTabTarget || ''));
            });
            tabRoot.setAttribute('role', 'tablist');
        }

        if (memberRuleDefinition) {
            const panels = Array.prototype.slice.call(document.querySelectorAll('[data-rule-param-panel]'));
            const syncRuleParamPanel = () => {
                panels.forEach(panel => {
                    const active = panel.dataset.ruleParamPanel === memberRuleDefinition.value;
                    panel.hidden = !active;
                    Array.prototype.slice.call(panel.querySelectorAll('input, select, textarea')).forEach(input => {
                        input.disabled = !active;
                    });
                });
            };
            memberRuleDefinition.addEventListener('change', syncRuleParamPanel);
            syncRuleParamPanel();
        }

        if (dateQuickButtons.length > 0) {
            const toLocalDatetimeValue = date => {
                const pad = value => String(value).padStart(2, '0');
                return [
                    date.getFullYear(),
                    pad(date.getMonth() + 1),
                    pad(date.getDate()),
                ].join('-') + 'T' + [pad(date.getHours()), pad(date.getMinutes())].join(':');
            };

            dateQuickButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const target = document.getElementById(button.dataset.datetimeTarget || '');
                    if (!target) {
                        return;
                    }

                    const days = Number(button.dataset.datetimeQuickDays || '0');
                    const date = new Date();
                    if (button.dataset.datetimeQuick !== 'now' && Number.isFinite(days)) {
                        date.setDate(date.getDate() + days);
                    }
                    target.value = toLocalDatetimeValue(date);
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        }

        if (dashboardSectionsRoot) {
            const orderStorageKey = 'sr_admin_dashboard_section_order';
            const visibilityStorageKey = 'sr_admin_dashboard_section_visibility';
            const managerToggle = document.querySelector('[data-admin-dashboard-manager-toggle]');
            const managerPanel = document.querySelector('[data-admin-dashboard-manager]');
            const managerClose = document.querySelector('[data-admin-dashboard-manager-close]');
            const managerList = document.querySelector('[data-admin-dashboard-manager-list]');
            const visibilityReset = document.querySelector('[data-admin-dashboard-visibility-reset]');
            let draggedSection = null;
            let currentDropPosition = null;
            const dropLine = document.createElement('div');
            dropLine.className = 'admin-dashboard-drop-line';
            dropLine.setAttribute('aria-hidden', 'true');

            const sections = () => Array.prototype.slice.call(dashboardSectionsRoot.querySelectorAll('[data-admin-dashboard-section]'));
            const visibleSections = () => sections().filter(section => !section.hidden);
            const sectionKey = section => section ? (section.dataset.adminDashboardSection || '') : '';
            const sectionLabel = section => section ? (section.dataset.adminDashboardLabel || sectionKey(section)) : '';
            const sectionDefaultVisible = section => !section || section.dataset.adminDashboardDefaultVisible !== '0';
            const loadVisibilityState = () => {
                try {
                    const savedState = JSON.parse(localStorage.getItem(visibilityStorageKey) || '{}');
                    return savedState && typeof savedState === 'object' && !Array.isArray(savedState) ? savedState : {};
                } catch (err) {
                    return {};
                }
            };
            let visibilityState = loadVisibilityState();
            const applySectionSpan = (section, span, auto) => {
                if (!section) {
                    return;
                }

                if (span === 'full') {
                    section.dataset.adminDashboardSpan = 'full';
                } else if (span === 'half') {
                    section.dataset.adminDashboardSpan = 'half';
                } else {
                    delete section.dataset.adminDashboardSpan;
                }

                if (auto) {
                    section.dataset.adminDashboardAutoSpan = '1';
                } else {
                    delete section.dataset.adminDashboardAutoSpan;
                }
            };
            const saveSectionOrder = () => {
                try {
                    localStorage.setItem(orderStorageKey, JSON.stringify({
                        items: sections().map(section => ({
                            key: sectionKey(section),
                            span: ['full', 'half'].includes(section.dataset.adminDashboardSpan || '')
                                ? section.dataset.adminDashboardSpan
                                : '',
                            auto_span: section.dataset.adminDashboardAutoSpan === '1'
                        }))
                    }));
                } catch (err) {}
            };
            const dashboardColumnCount = () => {
                if (window.matchMedia('(max-width: 767px)').matches) {
                    return 1;
                }

                if (window.matchMedia('(max-width: 1279px)').matches) {
                    return 2;
                }

                return 3;
            };
            const normalizeSectionRun = (run, columnCount) => {
                if (run.length === 0 || columnCount <= 1) {
                    return;
                }

                for (let index = 0; index < run.length; index += columnCount) {
                    const chunk = run.slice(index, index + columnCount);
                    const span = chunk.length === 1
                        ? 'full'
                        : (chunk.length === 2 && columnCount >= 3 ? 'half' : '');

                    chunk.forEach(section => {
                        applySectionSpan(section, span, true);
                    });
                }
            };
            const normalizeVisibleSectionLayout = () => {
                const columnCount = dashboardColumnCount();
                let run = [];

                if (columnCount <= 1) {
                    return;
                }

                visibleSections().forEach(section => {
                    if (section.dataset.adminDashboardSpan === 'full' && section.dataset.adminDashboardAutoSpan !== '1') {
                        normalizeSectionRun(run, columnCount);
                        run = [];
                        return;
                    }

                    run.push(section);
                });

                normalizeSectionRun(run, columnCount);
            };
            const sectionIsVisible = section => {
                const key = sectionKey(section);
                if (Object.prototype.hasOwnProperty.call(visibilityState, key)) {
                    return visibilityState[key] !== false;
                }

                return sectionDefaultVisible(section);
            };
            const applySectionVisibility = () => {
                sections().forEach(section => {
                    section.hidden = !sectionIsVisible(section);
                });
            };
            const saveVisibilityState = () => {
                try {
                    localStorage.setItem(visibilityStorageKey, JSON.stringify(visibilityState));
                } catch (err) {}
            };
            const setSectionVisible = (section, visible) => {
                const key = sectionKey(section);
                const wasHidden = section.hidden;
                visibilityState[key] = visible;

                if (visible && wasHidden) {
                    applySectionSpan(section, 'full');
                    dashboardSectionsRoot.appendChild(section);
                }

                section.hidden = !visible;
                normalizeVisibleSectionLayout();
                saveVisibilityState();
                saveSectionOrder();
                clearDropLine();
            };
            const renderVisibilityManager = () => {
                if (!managerList) {
                    return;
                }

                managerList.innerHTML = '';
                sections().forEach(section => {
                    const label = document.createElement('label');
                    const input = document.createElement('input');
                    const text = document.createElement('span');

                    label.className = 'admin-dashboard-manager-item form-label';
                    input.type = 'checkbox';
                    input.className = 'form-checkbox';
                    input.checked = sectionIsVisible(section);
                    text.textContent = sectionLabel(section);

                    input.addEventListener('change', () => {
                        setSectionVisible(section, input.checked);
                    });

                    label.appendChild(input);
                    label.appendChild(text);
                    managerList.appendChild(label);
                });
            };
            const clearDropLine = () => {
                if (dropLine.parentNode) {
                    dropLine.parentNode.removeChild(dropLine);
                }
                dropLine.classList.remove('is-horizontal', 'is-vertical');
                dropLine.removeAttribute('style');
            };
            const dashboardRows = availableSections => {
                const rowTolerance = 8;
                const rows = [];
                const items = availableSections
                    .map(section => ({
                        rect: section.getBoundingClientRect(),
                        section
                    }))
                    .sort((left, right) => left.rect.top - right.rect.top || left.rect.left - right.rect.left);

                items.forEach(item => {
                    const row = rows[rows.length - 1];
                    if (row && Math.abs(item.rect.top - row.top) <= rowTolerance) {
                        row.items.push(item);
                        row.left = Math.min(row.left, item.rect.left);
                        row.right = Math.max(row.right, item.rect.right);
                        row.top = Math.min(row.top, item.rect.top);
                        row.bottom = Math.max(row.bottom, item.rect.bottom);
                        return;
                    }

                    rows.push({
                        bottom: item.rect.bottom,
                        items: [item],
                        left: item.rect.left,
                        right: item.rect.right,
                        top: item.rect.top
                    });
                });

                return rows;
            };
            const rowIndexForSection = (rows, section) => rows.findIndex(row => (
                row.items.some(item => item.section === section)
            ));
            const verticalDropLineX = (rows, position) => {
                const rect = position.rect;
                if (!rect) {
                    return null;
                }

                const row = rows.find(candidate => (
                    candidate.items.some(item => item.section === position.section)
                ));
                const sortedItems = row
                    ? row.items.slice().sort((left, right) => left.rect.left - right.rect.left)
                    : [];
                const itemIndex = sortedItems.findIndex(item => item.section === position.section);
                const previousItem = position.side === 'left'
                    ? sortedItems[itemIndex - 1]
                    : sortedItems[itemIndex];
                const nextItem = position.side === 'left'
                    ? sortedItems[itemIndex]
                    : sortedItems[itemIndex + 1];

                if (previousItem && nextItem) {
                    return (previousItem.rect.right + nextItem.rect.left) / 2;
                }

                return position.side === 'left' ? rect.left : rect.right;
            };
            const horizontalDropPosition = (rows, rowIndex, after) => {
                const nextRowIndex = after ? rowIndex + 1 : rowIndex;
                const previousRow = rows[nextRowIndex - 1] || null;
                const nextRow = rows[nextRowIndex] || null;
                const reference = nextRow && nextRow.items[0] ? nextRow.items[0].section : null;
                const fallbackY = previousRow
                    ? previousRow.bottom + 8
                    : (nextRow ? nextRow.top - 8 : dashboardSectionsRoot.getBoundingClientRect().top);
                const lineY = previousRow && nextRow
                    ? (previousRow.bottom + nextRow.top) / 2
                    : fallbackY;

                return {
                    reference,
                    rect: {
                        bottom: lineY,
                        left: 0,
                        right: 0,
                        top: lineY
                    },
                    orientation: 'horizontal',
                    side: 'slot',
                    span: 'full'
                };
            };
            const getDropPosition = event => {
                const availableSections = visibleSections().filter(section => section !== draggedSection);
                const rows = dashboardRows(availableSections);

                for (let index = 0; index < availableSections.length; index += 1) {
                    const section = availableSections[index];
                    const nextSection = availableSections[index + 1] || null;
                    const rect = section.getBoundingClientRect();

                    if (event.clientY > rect.bottom || event.clientX < rect.left || event.clientX > rect.right) {
                        continue;
                    }

                    const distances = {
                        top: Math.abs(event.clientY - rect.top),
                        right: Math.abs(rect.right - event.clientX),
                        bottom: Math.abs(rect.bottom - event.clientY),
                        left: Math.abs(event.clientX - rect.left)
                    };
                    const side = Object.keys(distances).reduce((closest, key) => (
                        distances[key] < distances[closest] ? key : closest
                    ), 'top');
                    const rowIndex = rowIndexForSection(rows, section);

                    if (side === 'left') {
                        return {
                            reference: section,
                            rect,
                            section,
                            side: 'left',
                            orientation: 'vertical',
                            span: ''
                        };
                    }

                    if (side === 'right') {
                        return {
                            reference: nextSection,
                            rect,
                            section,
                            side: 'right',
                            orientation: 'vertical',
                            span: ''
                        };
                    }

                    return horizontalDropPosition(rows, rowIndex, side === 'bottom');
                }

                let closest = null;
                for (let index = 0; index < availableSections.length; index += 1) {
                    const section = availableSections[index];
                    const rect = section.getBoundingClientRect();
                    const xDistance = event.clientX < rect.left
                        ? rect.left - event.clientX
                        : Math.max(0, event.clientX - rect.right);
                    const yDistance = event.clientY < rect.top
                        ? rect.top - event.clientY
                        : Math.max(0, event.clientY - rect.bottom);
                    const score = xDistance * xDistance + yDistance * yDistance;

                    if (!closest || score < closest.score) {
                        closest = {
                            index,
                            rect,
                            score,
                            section,
                            xDistance,
                            yDistance
                        };
                    }
                }

                if (closest && closest.xDistance > closest.yDistance) {
                    return {
                        reference: event.clientX < closest.rect.left
                            ? closest.section
                            : (availableSections[closest.index + 1] || null),
                        rect: closest.rect,
                        section: closest.section,
                        side: event.clientX < closest.rect.left ? 'left' : 'right',
                        orientation: 'vertical',
                        span: ''
                    };
                }

                if (closest) {
                    const rowIndex = rowIndexForSection(rows, closest.section);
                    return horizontalDropPosition(rows, rowIndex, event.clientY > (closest.rect.top + closest.rect.height / 2));
                }

                return {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
            };
            const placeDropLine = position => {
                const nextPosition = position || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
                const reference = nextPosition.reference;
                const orientation = nextPosition.orientation === 'vertical' ? 'vertical' : 'horizontal';
                const rootRect = dashboardSectionsRoot.getBoundingClientRect();
                const rect = nextPosition.rect || null;
                const lineBoxSize = 16;

                currentDropPosition = nextPosition;
                dropLine.classList.toggle('is-vertical', orientation === 'vertical');
                dropLine.classList.toggle('is-horizontal', orientation !== 'vertical');

                if (!dropLine.parentNode) {
                    dashboardSectionsRoot.appendChild(dropLine);
                }

                if (orientation === 'vertical' && rect) {
                    const lineX = verticalDropLineX(dashboardRows(visibleSections().filter(section => section !== draggedSection)), nextPosition)
                        || (nextPosition.side === 'left' ? rect.left : rect.right);
                    dropLine.style.left = `${Math.round(lineX - rootRect.left - lineBoxSize / 2)}px`;
                    dropLine.style.top = `${Math.round(rect.top - rootRect.top)}px`;
                    dropLine.style.width = `${lineBoxSize}px`;
                    dropLine.style.height = `${Math.max(48, Math.round(rect.height))}px`;
                } else if (rect) {
                    const lineY = nextPosition.side === 'top' ? rect.top : rect.bottom;
                    dropLine.style.left = '0px';
                    dropLine.style.top = `${Math.round(lineY - rootRect.top - lineBoxSize / 2)}px`;
                    dropLine.style.width = `${Math.round(rootRect.width)}px`;
                    dropLine.style.height = `${lineBoxSize}px`;
                } else {
                    dropLine.style.left = '0px';
                    dropLine.style.top = `${Math.round(rootRect.height - lineBoxSize / 2)}px`;
                    dropLine.style.width = `${Math.round(rootRect.width)}px`;
                    dropLine.style.height = `${lineBoxSize}px`;
                }
            };
            const insertSectionAtDropLine = (section, dropPosition) => {
                const position = dropPosition || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
                const targetSection = position.section || null;
                const isSideDropOnFullSection = position.orientation === 'vertical'
                    && targetSection
                    && targetSection.dataset.adminDashboardSpan === 'full'
                    && dashboardColumnCount() > 1;

                if (isSideDropOnFullSection) {
                    const reference = position.side === 'left'
                        ? targetSection
                        : targetSection.nextSibling;

                    applySectionSpan(section, 'half', true);
                    if (reference && reference.parentNode === dashboardSectionsRoot) {
                        dashboardSectionsRoot.insertBefore(section, reference);
                    } else {
                        dashboardSectionsRoot.appendChild(section);
                    }
                    normalizeVisibleSectionLayout();
                    applySectionSpan(targetSection, 'half', true);
                    applySectionSpan(section, 'half', true);
                    return;
                }

                applySectionSpan(section, position.span || '');
                if (position.reference && position.reference.parentNode === dashboardSectionsRoot) {
                    dashboardSectionsRoot.insertBefore(section, position.reference);
                } else {
                    dashboardSectionsRoot.appendChild(section);
                }
                normalizeVisibleSectionLayout();
            };
            const finishDashboardDrag = commit => {
                if (commit && draggedSection) {
                    insertSectionAtDropLine(draggedSection, currentDropPosition);
                    saveSectionOrder();
                }

                if (draggedSection) {
                    draggedSection.classList.remove('is-dragging');
                }

                clearDropLine();
                currentDropPosition = null;
                draggedSection = null;
            };

            try {
                const savedState = JSON.parse(localStorage.getItem(orderStorageKey) || '[]');
                const savedItems = Array.isArray(savedState)
                    ? savedState.map(key => ({ key: String(key), span: '' }))
                    : (Array.isArray(savedState.items) ? savedState.items : []);
                if (savedItems.length > 0) {
                    savedItems.forEach(item => {
                        const key = typeof item === 'string' ? item : String(item.key || '');
                        const section = sections().find(candidate => sectionKey(candidate) === key);
                        if (!section) {
                            return;
                        }

                        applySectionSpan(section, ['full', 'half'].includes(item.span || '') ? item.span : '', item.auto_span === true);
                        dashboardSectionsRoot.appendChild(section);
                    });
                }
            } catch (err) {}

            applySectionVisibility();
            normalizeVisibleSectionLayout();
            renderVisibilityManager();

            if (managerToggle && managerPanel) {
                managerToggle.addEventListener('click', () => {
                    const nextExpanded = managerPanel.hidden;
                    managerPanel.hidden = !nextExpanded;
                    managerToggle.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
                });
            }

            if (managerClose && managerPanel) {
                managerClose.addEventListener('click', () => {
                    managerPanel.hidden = true;
                    if (managerToggle) {
                        managerToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            if (visibilityReset) {
                visibilityReset.addEventListener('click', () => {
                    visibilityState = {};
                    try {
                        localStorage.removeItem(visibilityStorageKey);
                    } catch (err) {}
                    applySectionVisibility();
                    normalizeVisibleSectionLayout();
                    saveSectionOrder();
                    renderVisibilityManager();
                });
            }

            window.addEventListener('resize', () => {
                normalizeVisibleSectionLayout();
                saveSectionOrder();
            });

            sections().forEach(section => {
                const handle = section.querySelector('.admin-dashboard-section-handle');

                if (!handle) {
                    return;
                }

                handle.addEventListener('dragstart', event => {
                    draggedSection = section;
                    section.classList.add('is-dragging');
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', '');
                });

                handle.addEventListener('dragend', () => {
                    finishDashboardDrag(false);
                });
            });

            dashboardSectionsRoot.addEventListener('dragover', event => {
                if (!draggedSection) {
                    return;
                }

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                placeDropLine(getDropPosition(event));
            });

            dashboardSectionsRoot.addEventListener('drop', event => {
                if (!draggedSection) {
                    return;
                }

                event.preventDefault();
                finishDashboardDrag(true);
            });
        }

        syncDesktopSidebarState();
        try {
            if (!isMobileViewport() && localStorage.getItem(menuStorageKey) === '1') {
                setDesktopCollapsed(true);
            }
        } catch (err) {}
        syncThemeUI();
        updateMenuScrollbar();
        window.requestAnimationFrame(updateMenuScrollbar);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.AdminShell.init();
});
