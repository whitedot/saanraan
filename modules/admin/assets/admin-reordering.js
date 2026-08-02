// 관리자 공통 순서 변경 동작.
// 테이블 행과 일반 목록의 키보드, 버튼, drag-and-drop 순서 변경만 담당한다.
window.AdminShellReordering = {
    initialized: false,

    init() {
        if (this.initialized) {
            return;
        }

        this.initialized = true;

        const sortableRows = Array.prototype.slice.call(document.querySelectorAll('[data-admin-sortable-row]'));
        const reorderLists = Array.prototype.slice.call(document.querySelectorAll('[data-admin-reorder-list]'));
        const movedCellAnimations = new WeakMap();
        let reorderKeySeed = 0;

        const reorderItemKey = item => {
            if (!item) {
                return '';
            }
            if (item.dataset.adminReorderKey) {
                return item.dataset.adminReorderKey;
            }
            if (item.dataset.sortKey) {
                return `${item.dataset.sortScope || ''}|${item.dataset.sortKey}`;
            }
            reorderKeySeed += 1;
            item.dataset.adminReorderKey = `admin-reorder-${reorderKeySeed}`;
            return item.dataset.adminReorderKey;
        };

        const reorderAnimatedElements = item => item && item.cells
            ? Array.prototype.slice.call(item.cells)
            : (item ? [item] : []);

        const captureReorderLayout = items => {
            const positions = new Map();
            Array.prototype.slice.call(items || []).forEach(item => {
                reorderAnimatedElements(item).forEach(element => {
                    const previousAnimation = movedCellAnimations.get(element);
                    if (previousAnimation) {
                        previousAnimation.cancel();
                        movedCellAnimations.delete(element);
                    }
                });
                positions.set(reorderItemKey(item), item.getBoundingClientRect().top);
            });
            return positions;
        };

        const animateReorderLayout = (items, positions, movedItems = []) => {
            if (!positions
                || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
                return;
            }

            const movedKeys = new Set(Array.prototype.slice.call(movedItems || []).map(reorderItemKey));
            Array.prototype.slice.call(items || []).forEach(item => {
                const key = reorderItemKey(item);
                const startTop = positions.get(key);
                if (startTop === undefined) {
                    return;
                }

                const distance = startTop - item.getBoundingClientRect().top;
                if (Math.abs(distance) < 1) {
                    return;
                }

                const moved = movedKeys.has(key);
                if (moved) {
                    const rect = item.getBoundingClientRect();
                    const shadow = document.createElement('span');
                    shadow.className = 'admin-sort-move-shadow';
                    shadow.setAttribute('aria-hidden', 'true');
                    shadow.style.top = `${rect.top}px`;
                    shadow.style.left = `${rect.left}px`;
                    shadow.style.width = `${rect.width}px`;
                    shadow.style.height = `${rect.height}px`;
                    document.body.appendChild(shadow);

                    const shadowOvershoot = distance > 0 ? -1.5 : 1.5;
                    const shadowAnimation = shadow.animate([
                        { transform: `translateY(${distance}px) scale(1.004)` },
                        { offset: .82, transform: `translateY(${shadowOvershoot}px) scale(1.002)` },
                        { transform: 'translateY(0) scale(1)' },
                    ], {
                        duration: 320,
                        easing: 'cubic-bezier(.2, .8, .2, 1)',
                    });
                    const removeShadow = () => shadow.remove();
                    shadowAnimation.addEventListener('finish', () => {
                        const shadowFadeAnimation = shadow.animate([
                            { opacity: 1 },
                            { opacity: 0 },
                        ], {
                            duration: 120,
                            easing: 'ease-out',
                        });
                        shadowFadeAnimation.addEventListener('finish', removeShadow, { once: true });
                        shadowFadeAnimation.addEventListener('cancel', removeShadow, { once: true });
                    }, { once: true });
                    shadowAnimation.addEventListener('cancel', removeShadow, { once: true });
                }

                reorderAnimatedElements(item).forEach(element => {
                    if (typeof element.animate !== 'function') {
                        return;
                    }
                    const overshoot = distance > 0 ? -1.5 : 1.5;
                    const keyframes = moved
                        ? [
                            { transform: `translateY(${distance}px) scale(1.004)` },
                            { offset: .82, transform: `translateY(${overshoot}px) scale(1.002)` },
                            { transform: 'translateY(0) scale(1)' },
                        ]
                        : [
                            { transform: `translateY(${distance}px)` },
                            { transform: 'translateY(0)' },
                        ];
                    const animation = element.animate(keyframes, {
                        duration: moved ? 320 : 260,
                        easing: 'cubic-bezier(.2, .8, .2, 1)',
                    });
                    const clearAnimation = () => {
                        if (movedCellAnimations.get(element) === animation) {
                            movedCellAnimations.delete(element);
                        }
                    };
                    movedCellAnimations.set(element, animation);
                    if (moved) {
                        animation.addEventListener('finish', () => {
                            if (movedCellAnimations.get(element) !== animation) {
                                return;
                            }
                            const fadeAnimation = element.animate([
                                { opacity: .7 },
                                { opacity: 1 },
                            ], {
                                duration: 120,
                                easing: 'ease-out',
                            });
                            const clearFadeAnimation = () => {
                                if (movedCellAnimations.get(element) === fadeAnimation) {
                                    movedCellAnimations.delete(element);
                                }
                            };
                            movedCellAnimations.set(element, fadeAnimation);
                            fadeAnimation.addEventListener('finish', clearFadeAnimation, { once: true });
                            fadeAnimation.addEventListener('cancel', clearFadeAnimation, { once: true });
                        }, { once: true });
                    } else {
                        animation.addEventListener('finish', clearAnimation, { once: true });
                    }
                    animation.addEventListener('cancel', clearAnimation, { once: true });
                });
            });
        };

        this.captureReorderLayout = captureReorderLayout;
        this.animateReorderLayout = animateReorderLayout;

        if (sortableRows.length > 0) {
            let draggedRow = null;
            let draggedRows = [];
            let draggedOriginalReference = null;
            let draggedStartPositions = null;
            let placeholderRow = null;
            let collapsedMenuRows = new Set();

            try {
                const storedCollapsedRows = JSON.parse(window.localStorage.getItem(menuCollapseStorageKey) || '[]');
                if (Array.isArray(storedCollapsedRows)) {
                    collapsedMenuRows = new Set(storedCollapsedRows.filter(value => typeof value === 'string'));
                }
            } catch (error) {
                collapsedMenuRows = new Set();
            }

            const sortableRowKey = row => row
                ? `${row.dataset.sortScope || ''}|${row.dataset.sortKey || ''}`
                : '';

            const currentSortableRows = () => Array.prototype.slice.call(document.querySelectorAll('[data-admin-sortable-row]'));

            const renumberRows = (scope, parent) => {
                const rows = currentSortableRows().filter(row => {
                    return row.dataset.sortScope === scope && row.dataset.sortParent === parent;
                });
                rows.forEach((row, index) => {
                    const input = row.querySelector('[data-admin-sort-order]');
                    if (input) {
                        input.value = String((index + 1) * 10);
                    }
                });
            };

            const rowDepth = row => {
                const depth = Number.parseInt(row.dataset.sortDepth || '0', 10);
                return Number.isFinite(depth) ? depth : 0;
            };

            const sortablePeers = (scope, parent) => {
                return Array.prototype.slice.call(document.querySelectorAll('[data-admin-sortable-row]')).filter(row => {
                    return row.dataset.sortScope === scope
                        && row.dataset.sortParent === parent
                        && !row.hidden
                        && !draggedRows.includes(row);
                });
            };

            const visibleSortableRows = () => currentSortableRows().filter(row => !row.hidden);

            const sortableRowBlock = row => {
                const rows = [row];
                const depth = rowDepth(row);
                let next = row.nextElementSibling;
                while (next && next.matches('[data-admin-sortable-row]') && rowDepth(next) > depth) {
                    rows.push(next);
                    next = next.nextElementSibling;
                }

                return rows;
            };

            const sortableContainers = Array.from(new Set(sortableRows.map(row => row.parentNode))).filter(Boolean);

            const targetInsertionReference = row => {
                let reference = row;
                const depth = rowDepth(row);
                let next = row.nextElementSibling;
                while (next && next.matches('[data-admin-sortable-row]') && rowDepth(next) > depth) {
                    reference = next;
                    next = next.nextElementSibling;
                }

                return reference.nextSibling;
            };

            const saveCollapsedMenuRows = () => {
                try {
                    window.localStorage.setItem(menuCollapseStorageKey, JSON.stringify(Array.from(collapsedMenuRows)));
                } catch (error) {
                    return;
                }
            };

            const syncMenuToggleButton = row => {
                const toggle = row.querySelector('[data-admin-menu-children-toggle]');
                if (!toggle) {
                    return;
                }

                const collapsed = collapsedMenuRows.has(sortableRowKey(row));
                const label = toggle.getAttribute(collapsed ? 'data-expand-label' : 'data-collapse-label') || '';
                const rowLabel = row.querySelector('.admin-menu-target-label');
                const icon = toggle.querySelector('[data-sr-material-icon]');
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggle.setAttribute('aria-label', `${rowLabel ? rowLabel.textContent.trim() : ''} ${label}`.trim());
                toggle.setAttribute('title', label);
                toggle.classList.toggle('is-collapsed', collapsed);
                if (icon) {
                    icon.textContent = collapsed ? 'unfold_more' : 'unfold_less';
                }
            };

            const syncMenuCollapsedRows = () => {
                const collapsedAncestors = [];
                currentSortableRows().forEach(row => {
                    const depth = rowDepth(row);
                    while (collapsedAncestors.length > 0 && depth <= collapsedAncestors[collapsedAncestors.length - 1]) {
                        collapsedAncestors.pop();
                    }

                    row.hidden = collapsedAncestors.length > 0;
                    if (collapsedMenuRows.has(sortableRowKey(row))) {
                        collapsedAncestors.push(depth);
                    }

                    syncMenuToggleButton(row);
                });

                document.querySelectorAll('[data-admin-menu-toggle-all]').forEach(button => {
                    const hasCollapsedRows = currentSortableRows().some(row => {
                        return row.dataset.sortHasChildren === '1' && collapsedMenuRows.has(sortableRowKey(row));
                    });
                    const label = button.getAttribute(hasCollapsedRows ? 'data-expand-label' : 'data-collapse-label') || '';
                    const icon = button.querySelector('[data-sr-material-icon]');
                    const labelNode = button.querySelector('[data-admin-menu-toggle-all-label]');
                    if (icon) {
                        icon.textContent = hasCollapsedRows ? 'unfold_more' : 'unfold_less';
                    }
                    if (labelNode) {
                        labelNode.textContent = label;
                    }
                    button.setAttribute('aria-label', label);
                    button.setAttribute('title', label);
                });
            };

            const setAllMenuRowsCollapsed = collapsed => {
                collapsedMenuRows = new Set();
                if (collapsed) {
                    currentSortableRows().forEach(row => {
                        if (row.dataset.sortHasChildren === '1') {
                            collapsedMenuRows.add(sortableRowKey(row));
                        }
                    });
                }

                saveCollapsedMenuRows();
                syncMenuCollapsedRows();
                refreshMoveButtons();
            };

            const refreshMoveButtons = () => {
                currentSortableRows().forEach(row => {
                    const scope = row.dataset.sortScope || '';
                    const parent = row.dataset.sortParent || '';
                    const peers = visibleSortableRows().filter(peer => {
                        return peer.dataset.sortScope === scope && peer.dataset.sortParent === parent;
                    });
                    const index = peers.indexOf(row);
                    const up = row.querySelector('[data-admin-sort-move="up"]');
                    const down = row.querySelector('[data-admin-sort-move="down"]');
                    if (up) {
                        up.disabled = index <= 0;
                    }
                    if (down) {
                        down.disabled = index === -1 || index >= peers.length - 1;
                    }
                });
            };

            const refreshSortableState = (scope, parent) => {
                if (scope !== undefined && parent !== undefined) {
                    renumberRows(scope, parent);
                }
                syncMenuCollapsedRows();
                refreshMoveButtons();
            };

            const moveSortableRow = (row, direction) => {
                if (!row || row.hidden) {
                    return;
                }

                const scope = row.dataset.sortScope || '';
                const parent = row.dataset.sortParent || '';
                const peers = visibleSortableRows().filter(peer => {
                    return peer.dataset.sortScope === scope && peer.dataset.sortParent === parent;
                });
                const index = peers.indexOf(row);
                const target = direction === 'up' ? peers[index - 1] : peers[index + 1];
                if (!target) {
                    return;
                }

                const container = row.parentNode;
                const block = sortableRowBlock(row);
                const startPositions = captureReorderLayout(currentSortableRows());
                const reference = direction === 'up' ? target : targetInsertionReference(target);
                block.forEach(blockRow => {
                    container.insertBefore(blockRow, reference);
                });
                refreshSortableState(scope, parent);
                animateReorderLayout(currentSortableRows(), startPositions, [row]);
            };

            const removePlaceholder = () => {
                if (placeholderRow && placeholderRow.parentNode) {
                    placeholderRow.parentNode.removeChild(placeholderRow);
                }
                placeholderRow = null;
            };

            const ensurePlaceholder = () => {
                if (placeholderRow) {
                    return placeholderRow;
                }

                const columnCount = draggedRow && draggedRow.cells ? Math.max(1, draggedRow.cells.length) : 1;
                placeholderRow = document.createElement('tr');
                placeholderRow.className = 'admin-sort-placeholder-row';
                placeholderRow.setAttribute('aria-hidden', 'true');

                const cell = document.createElement('td');
                cell.className = 'admin-sort-placeholder-cell';
                cell.colSpan = columnCount;
                placeholderRow.appendChild(cell);

                return placeholderRow;
            };

            const placePlaceholder = (container, reference) => {
                const placeholder = ensurePlaceholder();
                if (placeholder.parentNode !== container || placeholder.nextSibling !== reference) {
                    container.insertBefore(placeholder, reference);
                }
            };

            const updatePlaceholder = event => {
                if (!draggedRow) {
                    return false;
                }

                const scope = draggedRow.dataset.sortScope || '';
                const parent = draggedRow.dataset.sortParent || '';
                const peers = sortablePeers(scope, parent);
                if (peers.length === 0) {
                    removePlaceholder();
                    return false;
                }

                let targetPeer = peers[peers.length - 1];
                let reference = targetInsertionReference(targetPeer);

                for (const peer of peers) {
                    const rect = peer.getBoundingClientRect();
                    if (event.clientY < rect.top + rect.height / 2) {
                        targetPeer = peer;
                        reference = peer;
                        break;
                    }
                }

                placePlaceholder(targetPeer.parentNode, reference);
                return true;
            };

            const finishSortableDrag = row => {
                draggedRows.forEach(blockRow => blockRow.classList.remove('is-dragging'));

                const movedRow = draggedRow;
                const movedScope = draggedRow ? draggedRow.dataset.sortScope || '' : row.dataset.sortScope || '';
                const movedParent = draggedRow ? draggedRow.dataset.sortParent || '' : row.dataset.sortParent || '';
                if (placeholderRow && placeholderRow.parentNode && draggedRow) {
                    const parent = placeholderRow.parentNode;
                    draggedRows.forEach(blockRow => {
                        parent.insertBefore(blockRow, placeholderRow);
                    });
                }

                removePlaceholder();
                const rowWasMoved = movedRow && targetInsertionReference(movedRow) !== draggedOriginalReference;
                const startPositions = draggedStartPositions;
                draggedRow = null;
                draggedRows = [];
                draggedOriginalReference = null;
                draggedStartPositions = null;
                refreshSortableState(movedScope, movedParent);
                if (rowWasMoved) {
                    animateReorderLayout(currentSortableRows(), startPositions, [movedRow]);
                }
            };

            sortableRows.forEach(row => {
                const handle = row.querySelector('.admin-drag-handle');
                if (!handle) {
                    return;
                }

                handle.addEventListener('dragstart', event => {
                    draggedRow = row;
                    draggedRows = sortableRowBlock(row);
                    draggedOriginalReference = targetInsertionReference(row);
                    draggedStartPositions = captureReorderLayout(currentSortableRows());
                    draggedRows.forEach(blockRow => blockRow.classList.add('is-dragging'));
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', '');
                });

                handle.addEventListener('dragend', () => {
                    finishSortableDrag(row);
                });
            });

            sortableRows.forEach(row => {
                row.querySelectorAll('[data-admin-sort-move]').forEach(button => {
                    button.addEventListener('click', () => {
                        moveSortableRow(row, button.getAttribute('data-admin-sort-move') || '');
                    });
                });

                const toggle = row.querySelector('[data-admin-menu-children-toggle]');
                if (toggle) {
                    toggle.addEventListener('click', () => {
                        const key = sortableRowKey(row);
                        if (collapsedMenuRows.has(key)) {
                            collapsedMenuRows.delete(key);
                        } else {
                            collapsedMenuRows.add(key);
                        }

                        saveCollapsedMenuRows();
                        syncMenuCollapsedRows();
                        refreshMoveButtons();
                    });
                }
            });

            document.querySelectorAll('[data-admin-menu-toggle-all]').forEach(button => {
                button.addEventListener('click', () => {
                    const hasCollapsedRows = currentSortableRows().some(row => {
                        return row.dataset.sortHasChildren === '1' && collapsedMenuRows.has(sortableRowKey(row));
                    });
                    setAllMenuRowsCollapsed(!hasCollapsedRows);
                });
            });

            sortableContainers.forEach(container => {
                container.addEventListener('dragover', event => {
                    if (!updatePlaceholder(event)) {
                        return;
                    }

                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'move';
                });

                container.addEventListener('drop', event => {
                    if (!draggedRow) {
                        return;
                    }

                    event.preventDefault();
                    finishSortableDrag(draggedRow);
                });

                container.addEventListener('dragleave', event => {
                    if (!draggedRow || container.contains(event.relatedTarget)) {
                        return;
                    }

                    removePlaceholder();
                });
            });

            syncMenuCollapsedRows();
            refreshMoveButtons();
        }

        reorderLists.forEach(list => {
            let draggedItem = null;
            let draggedLayout = null;
            let draggedOriginalIndex = -1;
            let dropGuide = null;
            let dropInsertionIndex = null;
            let initialOrder = '';

            const listItems = () => Array.prototype.slice.call(list.querySelectorAll('[data-admin-reorder-item]')).filter(item => {
                return item.closest('[data-admin-reorder-list]') === list;
            });
            const removeDropGuide = () => {
                if (dropGuide) {
                    dropGuide.remove();
                    dropGuide = null;
                }
            };
            const clearDropClasses = () => {
                listItems().forEach(item => item.classList.remove('is-dragging', 'is-drop-before', 'is-drop-after'));
            };
            const clearDropState = () => {
                clearDropClasses();
                removeDropGuide();
            };
            const availableDropItems = () => listItems().filter(item => item !== draggedItem);
            const dropInsertionIndexAt = clientY => {
                const items = availableDropItems();
                for (let index = 0; index < items.length; index += 1) {
                    const rect = items[index].getBoundingClientRect();
                    if (clientY < rect.top + rect.height / 2) {
                        return index;
                    }
                }
                return items.length;
            };
            const showDropGuide = insertionIndex => {
                const items = availableDropItems();
                const previousItem = items[insertionIndex - 1] || null;
                const nextItem = items[insertionIndex] || null;
                const referenceItem = nextItem || previousItem;
                if (!referenceItem) {
                    removeDropGuide();
                    return;
                }

                if (!dropGuide) {
                    dropGuide = document.createElement('span');
                    dropGuide.className = 'admin-reorder-drop-guide';
                    dropGuide.setAttribute('aria-hidden', 'true');
                    document.body.appendChild(dropGuide);
                }

                const referenceRect = referenceItem.getBoundingClientRect();
                let guideTop = nextItem ? referenceRect.top : referenceRect.bottom;
                if (previousItem && nextItem) {
                    const previousRect = previousItem.getBoundingClientRect();
                    const nextRect = nextItem.getBoundingClientRect();
                    guideTop = (previousRect.bottom + nextRect.top) / 2;
                }

                dropGuide.style.top = `${guideTop}px`;
                dropGuide.style.left = `${referenceRect.left}px`;
                dropGuide.style.width = `${referenceRect.width}px`;
            };
            const syncMoveButtons = () => {
                const items = listItems();
                items.forEach((item, index) => {
                    const up = item.querySelector('[data-admin-reorder-move="up"]');
                    const down = item.querySelector('[data-admin-reorder-move="down"]');
                    if (up) {
                        up.disabled = index === 0;
                    }
                    if (down) {
                        down.disabled = index === items.length - 1;
                    }
                });
            };
            const dispatchReorder = item => {
                syncMoveButtons();
                list.dispatchEvent(new CustomEvent('admin:reorder', {
                    bubbles: true,
                    detail: { item },
                }));
            };

            list.addEventListener('click', event => {
                const button = event.target && event.target.closest
                    ? event.target.closest('[data-admin-reorder-move]')
                    : null;
                if (!button || !list.contains(button)) {
                    return;
                }

                const item = button.closest('[data-admin-reorder-item]');
                const items = listItems();
                const index = items.indexOf(item);
                const direction = button.getAttribute('data-admin-reorder-move') || '';
                const target = direction === 'up' ? items[index - 1] : items[index + 1];
                if (!item || !target) {
                    return;
                }

                const layout = captureReorderLayout(items);
                if (direction === 'up') {
                    list.insertBefore(item, target);
                } else {
                    list.insertBefore(target, item);
                }
                dispatchReorder(item);
                animateReorderLayout(listItems(), layout, [item]);
                button.focus();
            });

            list.addEventListener('dragstart', event => {
                const handle = event.target && event.target.closest
                    ? event.target.closest('[data-admin-reorder-handle]')
                    : null;
                if (!handle || !list.contains(handle)) {
                    return;
                }

                draggedItem = handle.closest('[data-admin-reorder-item]');
                if (!draggedItem) {
                    return;
                }
                const items = listItems();
                draggedLayout = captureReorderLayout(items);
                draggedOriginalIndex = items.indexOf(draggedItem);
                dropInsertionIndex = null;
                initialOrder = items.map(reorderItemKey).join('|');
                draggedItem.classList.add('is-dragging');
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', reorderItemKey(draggedItem));
                }
            });

            list.addEventListener('dragover', event => {
                if (!draggedItem) {
                    return;
                }
                event.preventDefault();
                clearDropClasses();
                draggedItem.classList.add('is-dragging');
                dropInsertionIndex = dropInsertionIndexAt(event.clientY);
                if (dropInsertionIndex === draggedOriginalIndex) {
                    removeDropGuide();
                } else {
                    showDropGuide(dropInsertionIndex);
                }
                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = 'move';
                }
            });

            list.addEventListener('drop', event => {
                if (!draggedItem) {
                    return;
                }
                event.preventDefault();
                const movedItem = draggedItem;
                if (dropInsertionIndex !== null && dropInsertionIndex !== draggedOriginalIndex) {
                    const items = availableDropItems();
                    list.insertBefore(draggedItem, items[dropInsertionIndex] || null);
                }
                const moved = listItems().map(reorderItemKey).join('|') !== initialOrder;
                clearDropState();
                draggedItem = null;
                draggedOriginalIndex = -1;
                dropInsertionIndex = null;
                initialOrder = '';
                if (moved) {
                    dispatchReorder(movedItem);
                    animateReorderLayout(listItems(), draggedLayout, [movedItem]);
                }
                draggedLayout = null;
            });

            list.addEventListener('dragend', () => {
                clearDropState();
                draggedItem = null;
                draggedLayout = null;
                draggedOriginalIndex = -1;
                dropInsertionIndex = null;
                initialOrder = '';
            });

            syncMoveButtons();
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.AdminShellReordering.init();
});
