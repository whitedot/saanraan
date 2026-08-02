// 관리자 대시보드 섹션 배치 동작.
// 대시보드 섹션의 표시, 크기, 순서와 관리 모달 상태만 담당한다.
window.AdminDashboard = {
    initialized: false,

    init() {
        if (this.initialized) {
            return;
        }

        this.initialized = true;

        const dashboardSectionsRoot = document.querySelector('[data-admin-dashboard-sections]');
        if (dashboardSectionsRoot) {
            const orderStorageKey = 'sr_admin_dashboard_section_order_v3';
            const visibilityStorageKey = 'sr_admin_dashboard_section_visibility';
            const managerToggle = document.querySelector('[data-admin-dashboard-manager-toggle]');
            const managerPanel = document.querySelector('[data-admin-dashboard-manager]');
            const managerCloseButtons = Array.prototype.slice.call(document.querySelectorAll('[data-admin-dashboard-manager-close]'));
            const managerList = document.querySelector('[data-admin-dashboard-manager-list]');
            const changeCancel = document.querySelector('[data-admin-dashboard-change-cancel]');
            let draggedSection = null;
            let currentDropPosition = null;
            let managerDraggedItem = null;
            let managerDraggedSection = null;
            let managerCurrentDropPosition = null;
            let managerPreviousFocus = null;
            let managerOpenSnapshot = null;
            const dropLine = document.createElement('div');
            dropLine.className = 'admin-dashboard-drop-line';
            dropLine.setAttribute('aria-hidden', 'true');
            const managerDropLine = document.createElement('div');
            managerDropLine.className = 'admin-dashboard-drop-line';
            managerDropLine.setAttribute('aria-hidden', 'true');
            const managerDragGhost = document.createElement('span');
            managerDragGhost.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;';
            managerDragGhost.setAttribute('aria-hidden', 'true');
            document.body.appendChild(managerDragGhost);

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
                } else if (span === 'third') {
                    section.dataset.adminDashboardSpan = 'third';
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
                            span: ['full', 'half', 'third'].includes(section.dataset.adminDashboardSpan || '')
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

                return 4;
            };
            const sectionWidthUnits = section => {
                const span = section ? (section.dataset.adminDashboardSpan || '') : '';
                if (span === 'full') {
                    return 12;
                }
                if (span === 'half') {
                    return 6;
                }
                if (span === 'third') {
                    return 4;
                }
                return 3;
            };
            const layoutRowsFromSections = sectionList => {
                const rows = [];
                let row = [];
                let rowUnits = 0;

                sectionList.forEach(section => {
                    const widthUnits = sectionWidthUnits(section);
                    if (row.length > 0 && rowUnits + widthUnits > 12) {
                        rows.push(row);
                        row = [];
                        rowUnits = 0;
                    }

                    row.push(section);
                    rowUnits += widthUnits;
                    if (rowUnits >= 12) {
                        rows.push(row);
                        row = [];
                        rowUnits = 0;
                    }
                });

                if (row.length > 0) {
                    rows.push(row);
                }

                return rows;
            };
            const normalizedLayoutRows = rows => {
                const nextRows = [];

                rows.forEach(row => {
                    const items = row.filter(Boolean);
                    if (items.length === 0) {
                        return;
                    }

                    for (let index = 0; index < items.length; index += 4) {
                        nextRows.push(items.slice(index, index + 4));
                    }
                });

                return nextRows;
            };
            const applyLayoutRows = rows => {
                const normalizedRows = normalizedLayoutRows(rows);

                normalizedRows.forEach(row => {
                    const span = row.length === 1
                        ? 'full'
                        : (row.length === 2
                            ? 'half'
                            : (row.length === 3 ? 'third' : ''));

                    row.forEach(section => {
                        applySectionSpan(section, span, true);
                        dashboardSectionsRoot.appendChild(section);
                    });
                });

                sections().filter(section => section.hidden).forEach(section => {
                    dashboardSectionsRoot.appendChild(section);
                });

                return normalizedRows;
            };
            const normalizeVisibleSectionLayout = () => {
                applyLayoutRows(layoutRowsFromSections(visibleSections()));
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
            const dashboardStateSnapshot = () => ({
                items: sections().map(section => ({
                    auto_span: section.dataset.adminDashboardAutoSpan === '1',
                    key: sectionKey(section),
                    span: ['full', 'half', 'third'].includes(section.dataset.adminDashboardSpan || '')
                        ? section.dataset.adminDashboardSpan
                        : '',
                    visible: sectionIsVisible(section)
                }))
            });
            const dashboardSnapshotsEqual = (left, right) => JSON.stringify(left || null) === JSON.stringify(right || null);
            const updateChangeCancelVisibility = () => {
                if (!changeCancel) {
                    return;
                }

                changeCancel.hidden = !managerOpenSnapshot || dashboardSnapshotsEqual(managerOpenSnapshot, dashboardStateSnapshot());
            };
            const restoreDashboardSnapshot = snapshot => {
                if (!snapshot || !Array.isArray(snapshot.items)) {
                    return;
                }

                const nextVisibilityState = {};
                snapshot.items.forEach(item => {
                    const section = sections().find(candidate => sectionKey(candidate) === String(item.key || ''));
                    if (!section) {
                        return;
                    }

                    nextVisibilityState[sectionKey(section)] = item.visible !== false;
                    applySectionSpan(section, ['full', 'half', 'third'].includes(item.span || '') ? item.span : '', item.auto_span === true);
                    section.hidden = item.visible === false;
                    dashboardSectionsRoot.appendChild(section);
                });

                visibilityState = nextVisibilityState;
                normalizeVisibleSectionLayout();
                saveVisibilityState();
                saveSectionOrder();
                clearDropLine();
                clearManagerDropLine();
                renderVisibilityManager();
                updateChangeCancelVisibility();
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
                if (!visible) {
                    dashboardSectionsRoot.appendChild(section);
                }
                normalizeVisibleSectionLayout();
                saveVisibilityState();
                saveSectionOrder();
                clearDropLine();
                updateChangeCancelVisibility();
            };
            const renderVisibilityManager = () => {
                if (!managerList) {
                    return;
                }

                managerList.innerHTML = '';
                sections().forEach(section => {
                    const item = document.createElement('div');
                    const handle = document.createElement('button');
                    const handleIcon = document.createElement('span');
                    const title = document.createElement('span');
                    const toggleLabel = document.createElement('label');
                    const input = document.createElement('input');
                    const key = sectionKey(section);
                    const label = sectionLabel(section);
                    const span = ['full', 'half', 'third'].includes(section.dataset.adminDashboardSpan || '')
                        ? section.dataset.adminDashboardSpan
                        : '';

                    item.className = 'admin-dashboard-manager-item';
                    item.classList.toggle('is-hidden-section', !sectionIsVisible(section));
                    item.dataset.adminDashboardManagerItem = key;
                    if (span !== '' && sectionIsVisible(section)) {
                        item.dataset.adminDashboardSpan = span;
                    }
                    handle.type = 'button';
                    handle.className = 'admin-dashboard-manager-handle';
                    handle.draggable = sectionIsVisible(section);
                    handle.setAttribute('aria-label', `${label} 순서 이동`);
                    handleIcon.className = 'sr-icon material-symbols-outlined admin-dashboard-manager-handle-icon';
                    handleIcon.setAttribute('aria-hidden', 'true');
                    handleIcon.setAttribute('data-sr-material-icon', '');
                    handleIcon.textContent = 'apps';
                    title.className = 'admin-dashboard-manager-title';
                    title.textContent = label;
                    toggleLabel.className = 'admin-dashboard-manager-toggle';
                    input.type = 'checkbox';
                    input.className = 'form-switch form-switch-light';
                    input.checked = sectionIsVisible(section);
                    input.setAttribute('aria-label', `${label} 표시`);

                    input.addEventListener('change', () => {
                        setSectionVisible(section, input.checked);
                        renderVisibilityManager();
                    });

                    handle.addEventListener('dragstart', event => {
                        if (!sectionIsVisible(section)) {
                            event.preventDefault();
                            return;
                        }

                        managerDraggedItem = item;
                        managerDraggedSection = section;
                        item.classList.add('is-dragging');
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', key);
                        event.dataTransfer.setDragImage(managerDragGhost, 0, 0);
                    });

                    handle.addEventListener('dragend', () => {
                        finishManagerDrag(managerCurrentDropPosition !== null);
                    });

                    handle.appendChild(handleIcon);
                    toggleLabel.appendChild(input);
                    item.appendChild(handle);
                    item.appendChild(title);
                    item.appendChild(toggleLabel);
                    managerList.appendChild(item);
                });
            };
            const clearLine = line => {
                if (line.parentNode) {
                    line.parentNode.removeChild(line);
                }
                line.classList.remove('is-horizontal', 'is-vertical');
                line.removeAttribute('style');
            };
            const clearDropLine = () => {
                clearLine(dropLine);
            };
            const clearManagerDropLine = () => {
                clearLine(managerDropLine);
            };
            const dashboardRows = availableItems => {
                const rowTolerance = 8;
                const rows = [];
                const items = availableItems
                    .map(item => ({
                        item,
                        rect: item.getBoundingClientRect()
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
            const rowIndexForItem = (rows, targetItem) => rows.findIndex(row => (
                row.items.some(item => item.item === targetItem)
            ));
            const verticalDropLineX = (rows, position) => {
                const rect = position.rect;
                if (!rect) {
                    return null;
                }

                const row = rows.find(candidate => (
                    candidate.items.some(item => item.item === position.item)
                ));
                const sortedItems = row
                    ? row.items.slice().sort((left, right) => left.rect.left - right.rect.left)
                    : [];
                const itemIndex = sortedItems.findIndex(item => item.item === position.item);
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
            const horizontalDropPosition = (root, rows, rowIndex, after, referenceForItem) => {
                const nextRowIndex = after ? rowIndex + 1 : rowIndex;
                const previousRow = rows[nextRowIndex - 1] || null;
                const nextRow = rows[nextRowIndex] || null;
                const referenceItem = nextRow && nextRow.items[0] ? nextRow.items[0].item : null;
                const reference = referenceItem ? referenceForItem(referenceItem) : null;
                const fallbackY = previousRow
                    ? previousRow.bottom + 8
                    : (nextRow ? nextRow.top - 8 : root.getBoundingClientRect().top);
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
            const getDropPositionForItems = (root, availableItems, draggedItem, event, referenceForItem) => {
                const items = availableItems.filter(item => item !== draggedItem);
                const rows = dashboardRows(items);

                for (let index = 0; index < items.length; index += 1) {
                    const item = items[index];
                    const nextItem = items[index + 1] || null;
                    const rect = item.getBoundingClientRect();

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
                    const rowIndex = rowIndexForItem(rows, item);
                    const section = referenceForItem(item);
                    const nextSection = nextItem ? referenceForItem(nextItem) : null;

                    if (side === 'left') {
                        return {
                            reference: section,
                            rect,
                            item,
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
                            item,
                            section,
                            side: 'right',
                            orientation: 'vertical',
                            span: ''
                        };
                    }

                    return horizontalDropPosition(root, rows, rowIndex, side === 'bottom', referenceForItem);
                }

                let closest = null;
                for (let index = 0; index < items.length; index += 1) {
                    const item = items[index];
                    const rect = item.getBoundingClientRect();
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
                            item,
                            rect,
                            score,
                            xDistance,
                            yDistance
                        };
                    }
                }

                if (closest && closest.xDistance > closest.yDistance) {
                    const closestSection = referenceForItem(closest.item);
                    const nextSection = items[closest.index + 1] ? referenceForItem(items[closest.index + 1]) : null;
                    return {
                        reference: event.clientX < closest.rect.left
                            ? closestSection
                            : nextSection,
                        rect: closest.rect,
                        item: closest.item,
                        section: closestSection,
                        side: event.clientX < closest.rect.left ? 'left' : 'right',
                        orientation: 'vertical',
                        span: ''
                    };
                }

                if (closest) {
                    const rowIndex = rowIndexForItem(rows, closest.item);
                    return horizontalDropPosition(root, rows, rowIndex, event.clientY > (closest.rect.top + closest.rect.height / 2), referenceForItem);
                }

                return {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
            };
            const placeDropLineInRoot = (root, line, availableItems, draggedItem, position) => {
                const nextPosition = position || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
                const orientation = nextPosition.orientation === 'vertical' ? 'vertical' : 'horizontal';
                const rootRect = root.getBoundingClientRect();
                const rect = nextPosition.rect || null;
                const lineBoxSize = 16;

                line.classList.toggle('is-vertical', orientation === 'vertical');
                line.classList.toggle('is-horizontal', orientation !== 'vertical');

                if (!line.parentNode) {
                    root.appendChild(line);
                }

                if (orientation === 'vertical' && rect) {
                    const lineX = verticalDropLineX(dashboardRows(availableItems.filter(item => item !== draggedItem)), nextPosition)
                        || (nextPosition.side === 'left' ? rect.left : rect.right);
                    line.style.left = `${Math.round(lineX - rootRect.left - lineBoxSize / 2)}px`;
                    line.style.top = `${Math.round(rect.top - rootRect.top)}px`;
                    line.style.width = `${lineBoxSize}px`;
                    line.style.height = `${Math.max(48, Math.round(rect.height))}px`;
                } else if (rect) {
                    const lineY = nextPosition.side === 'top' ? rect.top : rect.bottom;
                    line.style.left = '0px';
                    line.style.top = `${Math.round(lineY - rootRect.top - lineBoxSize / 2)}px`;
                    line.style.width = `${Math.round(rootRect.width)}px`;
                    line.style.height = `${lineBoxSize}px`;
                } else {
                    line.style.left = '0px';
                    line.style.top = `${Math.round(rootRect.height - lineBoxSize / 2)}px`;
                    line.style.width = `${Math.round(rootRect.width)}px`;
                    line.style.height = `${lineBoxSize}px`;
                }
            };
            const getDropPosition = event => getDropPositionForItems(
                dashboardSectionsRoot,
                visibleSections(),
                draggedSection,
                event,
                item => item
            );
            const placeDropLine = position => {
                currentDropPosition = position || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
                placeDropLineInRoot(dashboardSectionsRoot, dropLine, visibleSections(), draggedSection, currentDropPosition);
            };
            const insertSectionAtDropLine = (section, dropPosition) => {
                const position = dropPosition || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };

                if (!section) {
                    return;
                }

                const rows = layoutRowsFromSections(visibleSections());
                const findSectionRow = target => {
                    for (let rowIndex = 0; rowIndex < rows.length; rowIndex += 1) {
                        const columnIndex = rows[rowIndex].indexOf(target);
                        if (columnIndex !== -1) {
                            return { rowIndex, columnIndex };
                        }
                    }

                    return null;
                };
                const currentPosition = findSectionRow(section);
                if (currentPosition) {
                    rows[currentPosition.rowIndex].splice(currentPosition.columnIndex, 1);
                }

                if (position.orientation === 'horizontal') {
                    if (position.reference) {
                        const referencePosition = findSectionRow(position.reference);
                        rows.splice(referencePosition ? referencePosition.rowIndex : rows.length, 0, [section]);
                    } else {
                        rows.push([section]);
                    }
                } else if (position.section) {
                    const targetPosition = findSectionRow(position.section);
                    if (targetPosition) {
                        rows[targetPosition.rowIndex].splice(
                            position.side === 'left' ? targetPosition.columnIndex : targetPosition.columnIndex + 1,
                            0,
                            section
                        );
                    } else {
                        rows.push([section]);
                    }
                } else if (position.reference) {
                    const referencePosition = findSectionRow(position.reference);
                    if (referencePosition) {
                        rows[referencePosition.rowIndex].splice(referencePosition.columnIndex, 0, section);
                    } else {
                        rows.push([section]);
                    }
                } else {
                    rows.push([section]);
                }

                applyLayoutRows(rows);
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
                updateChangeCancelVisibility();
            };
            const managerItems = () => managerList
                ? Array.prototype.slice.call(managerList.querySelectorAll('[data-admin-dashboard-manager-item]:not(.is-hidden-section)'))
                : [];
            const managerSectionForItem = item => {
                const key = item ? (item.dataset.adminDashboardManagerItem || '') : '';
                return sections().find(section => sectionKey(section) === key) || null;
            };
            const getManagerDropPosition = event => managerList
                ? getDropPositionForItems(managerList, managerItems(), managerDraggedItem, event, managerSectionForItem)
                : null;
            const placeManagerDropLine = position => {
                if (!managerList) {
                    return;
                }

                managerCurrentDropPosition = position || {
                    reference: null,
                    orientation: 'horizontal',
                    span: 'full'
                };
                placeDropLineInRoot(managerList, managerDropLine, managerItems(), managerDraggedItem, managerCurrentDropPosition);
            };
            const finishManagerDrag = commit => {
                if (commit && managerDraggedSection) {
                    insertSectionAtDropLine(managerDraggedSection, managerCurrentDropPosition);
                    saveSectionOrder();
                }

                if (managerDraggedItem) {
                    managerDraggedItem.classList.remove('is-dragging');
                }

                clearManagerDropLine();
                managerDraggedItem = null;
                managerDraggedSection = null;
                managerCurrentDropPosition = null;
                renderVisibilityManager();
                updateChangeCancelVisibility();
            };

            let hasSavedLayout = false;
            try {
                const savedState = JSON.parse(localStorage.getItem(orderStorageKey) || '[]');
                const savedItems = Array.isArray(savedState)
                    ? savedState.map(key => ({ key: String(key), span: '' }))
                    : (Array.isArray(savedState.items) ? savedState.items : []);
                hasSavedLayout = savedItems.length > 0;
                if (savedItems.length > 0) {
                    savedItems.forEach(item => {
                        const key = typeof item === 'string' ? item : String(item.key || '');
                        const section = sections().find(candidate => sectionKey(candidate) === key);
                        if (!section) {
                            return;
                        }

                        applySectionSpan(section, ['full', 'half', 'third'].includes(item.span || '') ? item.span : '', item.auto_span === true);
                        dashboardSectionsRoot.appendChild(section);
                    });
                }
            } catch (err) {}

            applySectionVisibility();
            if (!hasSavedLayout) {
                sections().forEach(section => {
                    applySectionSpan(section, 'full', true);
                });
            }
            normalizeVisibleSectionLayout();
            renderVisibilityManager();

            const openDashboardManager = () => {
                if (!managerPanel) {
                    return;
                }

                managerPreviousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                managerOpenSnapshot = dashboardStateSnapshot();
                renderVisibilityManager();
                updateChangeCancelVisibility();
                managerPanel.hidden = false;
                managerPanel.classList.remove('hidden', 'pointer-events-none', 'opacity-0');
                managerPanel.removeAttribute('aria-hidden');
                window.requestAnimationFrame(() => {
                    managerPanel.classList.add('overlay-open');
                });
                if (managerToggle) {
                    managerToggle.setAttribute('aria-expanded', 'true');
                }
                managerPanel.focus({ preventScroll: true });
            };
            const closeDashboardManager = () => {
                if (!managerPanel) {
                    return;
                }

                managerPanel.classList.remove('overlay-open');
                managerPanel.classList.add('hidden', 'pointer-events-none', 'opacity-0');
                managerPanel.setAttribute('aria-hidden', 'true');
                managerPanel.hidden = true;
                if (managerToggle) {
                    managerToggle.setAttribute('aria-expanded', 'false');
                }
                if (managerPreviousFocus && typeof managerPreviousFocus.focus === 'function') {
                    managerPreviousFocus.focus({ preventScroll: true });
                }
                managerPreviousFocus = null;
            };

            if (managerToggle && managerPanel) {
                managerToggle.addEventListener('click', () => {
                    if (managerPanel.hidden) {
                        openDashboardManager();
                    } else {
                        closeDashboardManager();
                    }
                });
            }

            if (managerPanel) {
                managerCloseButtons.forEach(button => {
                    button.addEventListener('click', closeDashboardManager);
                });

                managerPanel.addEventListener('click', event => {
                    if (event.target === managerPanel) {
                        closeDashboardManager();
                    }
                });

                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape' && !managerPanel.hidden) {
                        closeDashboardManager();
                    }
                });
            }

            if (changeCancel) {
                changeCancel.addEventListener('click', () => {
                    restoreDashboardSnapshot(managerOpenSnapshot);
                });
            }

            if (managerList) {
                managerList.addEventListener('dragover', event => {
                    if (!managerDraggedSection) {
                        return;
                    }

                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'move';
                    placeManagerDropLine(getManagerDropPosition(event));
                });

                managerList.addEventListener('drop', event => {
                    if (!managerDraggedSection) {
                        return;
                    }

                    event.preventDefault();
                    finishManagerDrag(true);
                });
            }

            window.addEventListener('resize', () => {
                normalizeVisibleSectionLayout();
                saveSectionOrder();
                renderVisibilityManager();
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
                    finishDashboardDrag(currentDropPosition !== null);
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

            document.addEventListener('dragover', event => {
                if (!draggedSection || currentDropPosition === null) {
                    return;
                }

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
            });

            document.addEventListener('drop', event => {
                if (!draggedSection || currentDropPosition === null) {
                    return;
                }

                event.preventDefault();
                finishDashboardDrag(true);
            });
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.AdminDashboard.init();
});
