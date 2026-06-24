(function () {
    'use strict';

    var MEMBER_FORM_SELECTOR = '[data-admin-member-search-form]';
    var REFERENCE_FORM_SELECTOR = '[data-admin-reference-search-form]';

    function closest(target, selector) {
        var element = target && target.nodeType === 1 ? target : target.parentElement;
        return element && typeof element.closest === 'function' ? element.closest(selector) : null;
    }

    function setText(element, value) {
        if (element) {
            element.textContent = value;
        }
    }

    function clearNode(element) {
        while (element && element.firstChild) {
            element.removeChild(element.firstChild);
        }
    }

    function closeOverlay(overlay) {
        if (!overlay || !overlay.id) {
            return;
        }

        var trigger = overlay.querySelector('[data-overlay="#' + overlay.id + '"], [data-overlay="' + overlay.id + '"]');
        if (trigger) {
            trigger.click();
        }
    }

    function openOverlay(selector) {
        if (!selector || selector === '#') {
            return;
        }

        var overlay = null;
        try {
            overlay = document.querySelector(selector);
        } catch (error) {
            return;
        }
        if (overlay && overlay.classList.contains('overlay-open')) {
            return;
        }

        var triggers = document.querySelectorAll('[data-overlay="' + selector + '"]');
        for (var i = 0; i < triggers.length; i += 1) {
            var hiddenOverlay = triggers[i].closest && triggers[i].closest('.overlay[aria-hidden="true"]');
            if (!hiddenOverlay) {
                triggers[i].click();
                return;
            }
        }
    }

    function returnToOverlay(overlay) {
        if (!overlay) {
            return;
        }

        var selector = overlay.getAttribute('data-admin-return-overlay') || '';
        closeOverlay(overlay);
        if (selector) {
            window.requestAnimationFrame(function () {
                openOverlay(selector);
            });
        }
    }

    function endpointUrl(form) {
        var endpoint = form.getAttribute('data-endpoint') || '';
        if (!endpoint) {
            return '';
        }

        var params = new URLSearchParams(new FormData(form));
        var cursor = form.getAttribute('data-admin-reference-cursor') || '';
        if (cursor) {
            params.set('cursor', cursor);
        }
        return endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + params.toString();
    }

    function renderEmpty(results, message) {
        clearNode(results);
        var paragraph = document.createElement('p');
        paragraph.className = 'admin-empty-state admin-lookup-empty';
        paragraph.textContent = message;
        results.appendChild(paragraph);
    }

    function renderError(results) {
        renderEmpty(results, '검색 중 오류가 발생했습니다.');
    }

    function appendMeta(parent, values) {
        var meta = document.createElement('div');
        meta.className = 'admin-lookup-result-meta';
        values.forEach(function (value) {
            if (!value) {
                return;
            }
            var span = document.createElement('span');
            span.textContent = value;
            meta.appendChild(span);
        });
        parent.appendChild(meta);
    }

    function renderMemberResults(form, items) {
        var results = document.querySelector(form.getAttribute('data-results'));
        if (!results) {
            return;
        }

        if (!items.length) {
            renderEmpty(results, '검색된 회원이 없습니다.');
            return;
        }

        clearNode(results);
        var list = document.createElement('div');
        list.className = 'admin-lookup-results-list';
        items.forEach(function (item) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'admin-lookup-result-button';
            button.setAttribute('data-admin-member-apply', 'true');
            button.setAttribute('data-target', form.getAttribute('data-target') || '');
            button.setAttribute('data-value', item.account_public_hash || '');
            button.setAttribute('data-return-overlay', form.getAttribute('data-return-overlay') || '');

            var title = document.createElement('strong');
            title.textContent = item.display_name || '(이름 없음)';
            button.appendChild(title);
            appendMeta(button, [
                item.email || '',
                item.status || '',
                item.account_public_hash || ''
            ]);
            list.appendChild(button);
        });
        results.appendChild(list);
    }

    function renderReferenceResults(form, payload, append) {
        var items = Array.isArray(payload) ? payload : (Array.isArray(payload.items) ? payload.items : []);
        var results = document.querySelector(form.getAttribute('data-results'));
        if (!results) {
            return;
        }

        if (!append && !items.length) {
            renderEmpty(results, '검색된 참조가 없습니다.');
            if (payload && payload.notice) {
                renderNotice(results, payload.notice);
            }
            return;
        }

        if (!append) {
            clearNode(results);
        }
        if (payload && payload.notice && !append) {
            renderNotice(results, payload.notice);
        }
        var list = append ? results.querySelector('.admin-lookup-results-list') : null;
        if (!list) {
            list = document.createElement('div');
            list.className = 'admin-lookup-results-list';
            results.appendChild(list);
        }
        items.forEach(function (item) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'admin-lookup-result-button';
            button.setAttribute('data-admin-reference-apply', 'true');
            button.setAttribute('data-type-target', form.getAttribute('data-type-target') || '');
            button.setAttribute('data-id-target', form.getAttribute('data-id-target') || '');
            button.setAttribute('data-reference-type', item.reference_type || '');
            button.setAttribute('data-reference-id', item.reference_id || '');
            button.setAttribute('data-return-overlay', form.getAttribute('data-return-overlay') || '');

            var title = document.createElement('strong');
            title.textContent = item.title || ((item.reference_type || '') + ':' + (item.reference_id || ''));
            button.appendChild(title);
            appendMeta(button, [
                item.reason || '',
                item.capability_label || '',
                item.pricing_label || '',
                item.policy_summary || '',
                item.member_name || '',
                item.member_email || '',
                item.created_at || ''
            ]);
            button.setAttribute('data-reference-summary', [
                title.textContent,
                item.reason || '',
                item.pricing_label || '',
                item.policy_summary || '',
                item.member_name || '',
                item.member_email || '',
                item.created_at || ''
            ].filter(Boolean).join(' / '));
            list.appendChild(button);
        });
        var oldMore = results.querySelector('[data-admin-reference-more]');
        if (oldMore) {
            oldMore.remove();
        }
        if (payload && payload.has_more && payload.next_cursor) {
            form.setAttribute('data-admin-reference-cursor-next', payload.next_cursor);
            var more = document.createElement('button');
            more.type = 'button';
            more.className = 'btn btn-solid-light admin-lookup-more';
            more.setAttribute('data-admin-reference-more', 'true');
            more.textContent = '더 보기';
            results.appendChild(more);
        } else {
            form.removeAttribute('data-admin-reference-cursor-next');
        }
    }

    function renderNotice(results, message) {
        var paragraph = document.createElement('p');
        paragraph.className = 'admin-empty-state admin-lookup-empty';
        paragraph.textContent = message;
        results.appendChild(paragraph);
    }

    function runSearch(form, render, append) {
        var results = document.querySelector(form.getAttribute('data-results'));
        if (!append) {
            form.removeAttribute('data-admin-reference-cursor');
        }
        if (results && !append) {
            renderEmpty(results, '검색 중입니다.');
        }
        if (results && append) {
            var more = results.querySelector('[data-admin-reference-more]');
            if (more) {
                more.disabled = true;
                more.textContent = '불러오는 중입니다.';
            }
        }

        var url = endpointUrl(form);
        if (!url) {
            renderError(results);
            return;
        }

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Search failed.');
            }
            return response.json();
        }).then(function (payload) {
            render(form, render === renderReferenceResults ? payload : (Array.isArray(payload.items) ? payload.items : []), !!append);
        }).catch(function () {
            renderError(results);
        });
    }

    function syncReferencePair(form) {
        var typeInput = form.querySelector('[data-admin-reference-type]');
        var idInput = form.querySelector('[data-admin-reference-id]');
        if (!typeInput || !idInput) {
            return;
        }

        var typeRequired = idInput.value.trim() !== '';
        var idRequired = typeInput.value !== '';
        var typeLabel = form.querySelector('[data-admin-reference-type-required]');
        var idLabel = form.querySelector('[data-admin-reference-id-required]');
        typeInput.required = typeRequired;
        idInput.required = idRequired;
        if (typeLabel) {
            typeLabel.hidden = !typeRequired;
        }
        if (idLabel) {
            idLabel.hidden = !idRequired;
        }
    }

    function syncAllReferencePairs() {
        document.querySelectorAll('[data-admin-reference-pair]').forEach(syncReferencePair);
    }

    function syncReferenceFilters(form) {
        var typeSelect = form ? form.querySelector('select[name="reference_type"]') : null;
        if (!typeSelect) {
            return;
        }
        var communityPost = typeSelect.value === 'community_post';
        form.querySelectorAll('[data-admin-community-post-filter]').forEach(function (control) {
            control.disabled = !communityPost;
            control.hidden = !communityPost;
            if (!communityPost) {
                control.value = '';
            }
        });
    }

    document.addEventListener('change', function (event) {
        var control = closest(event.target, '[data-admin-reference-type], [data-admin-reference-id]');
        if (!control) {
            var referenceSearchForm = closest(event.target, REFERENCE_FORM_SELECTOR);
            if (referenceSearchForm) {
                syncReferenceFilters(referenceSearchForm);
            }
            return;
        }

        var form = closest(control, '[data-admin-reference-pair]');
        if (form) {
            syncReferencePair(form);
        }
    });

    document.addEventListener('input', function (event) {
        var control = closest(event.target, '[data-admin-reference-id]');
        if (!control) {
            return;
        }

        var form = closest(control, '[data-admin-reference-pair]');
        if (form) {
            syncReferencePair(form);
        }
    });

    document.addEventListener('DOMContentLoaded', syncAllReferencePairs);
    syncAllReferencePairs();
    document.querySelectorAll(REFERENCE_FORM_SELECTOR).forEach(syncReferenceFilters);

    document.addEventListener('submit', function (event) {
        var memberForm = closest(event.target, MEMBER_FORM_SELECTOR);
        if (memberForm) {
            event.preventDefault();
            runSearch(memberForm, renderMemberResults);
            return;
        }

        var referenceForm = closest(event.target, REFERENCE_FORM_SELECTOR);
        if (referenceForm) {
            event.preventDefault();
            referenceForm.removeAttribute('data-admin-reference-cursor');
            runSearch(referenceForm, renderReferenceResults, false);
        }
    });

    document.addEventListener('click', function (event) {
        var memberApply = closest(event.target, '[data-admin-member-apply]');
        if (memberApply) {
            event.preventDefault();
            var memberTarget = document.querySelector(memberApply.getAttribute('data-target') || '');
            if (memberTarget) {
                memberTarget.value = memberApply.getAttribute('data-value') || '';
                memberTarget.dispatchEvent(new Event('change', { bubbles: true }));
            }
            returnToOverlay(memberApply.closest('.overlay'));
            return;
        }

        var referenceApply = closest(event.target, '[data-admin-reference-apply]');
        if (referenceApply) {
            event.preventDefault();
            var typeTarget = document.querySelector(referenceApply.getAttribute('data-type-target') || '');
            var idTarget = document.querySelector(referenceApply.getAttribute('data-id-target') || '');
            var summaryTarget = document.querySelector(referenceApply.getAttribute('data-summary-target') || '');
            if (typeTarget) {
                typeTarget.value = referenceApply.getAttribute('data-reference-type') || '';
                typeTarget.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (idTarget) {
                idTarget.value = referenceApply.getAttribute('data-reference-id') || '';
                idTarget.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (summaryTarget) {
                summaryTarget.textContent = referenceApply.getAttribute('data-reference-summary') || '';
                summaryTarget.hidden = summaryTarget.textContent === '';
            }
            returnToOverlay(referenceApply.closest('.overlay'));
            return;
        }

        var referenceMore = closest(event.target, '[data-admin-reference-more]');
        if (referenceMore) {
            event.preventDefault();
            var moreForm = closest(referenceMore, '.modal-body') ? closest(referenceMore, '.modal-body').querySelector(REFERENCE_FORM_SELECTOR) : null;
            if (moreForm) {
                moreForm.setAttribute('data-admin-reference-cursor', moreForm.getAttribute('data-admin-reference-cursor-next') || '');
                runSearch(moreForm, renderReferenceResults, true);
            }
            return;
        }

        var memberOpen = closest(event.target, '[data-admin-member-lookup-open]');
        if (memberOpen) {
            var memberInput = document.querySelector(memberOpen.getAttribute('data-target') || '');
            var memberModal = document.querySelector(memberOpen.getAttribute('data-overlay') || '');
            if (memberInput && memberModal) {
                var memberReturnOverlay = memberOpen.getAttribute('data-return-overlay') || '';
                var memberParentOverlay = memberOpen.closest('.overlay');
                if (!memberReturnOverlay && memberParentOverlay && memberParentOverlay.id) {
                    memberReturnOverlay = '#' + memberParentOverlay.id;
                }
                if (memberReturnOverlay) {
                    memberModal.setAttribute('data-admin-return-overlay', memberReturnOverlay);
                }
                var memberForm = memberModal.querySelector(MEMBER_FORM_SELECTOR);
                if (memberForm) {
                    memberForm.setAttribute('data-target', memberOpen.getAttribute('data-target') || '');
                    memberForm.setAttribute('data-return-overlay', memberReturnOverlay);
                }
                var memberQuery = memberModal.querySelector('input[name="q"]');
                if (memberQuery && memberQuery.value === '') {
                    memberQuery.value = memberInput.value;
                }
            }
            return;
        }

        var referenceOpen = closest(event.target, '[data-admin-reference-lookup-open]');
        if (referenceOpen) {
            var referenceModal = document.querySelector(referenceOpen.getAttribute('data-overlay') || '');
            var referenceType = document.querySelector(referenceOpen.getAttribute('data-type-target') || '');
            var referenceId = document.querySelector(referenceOpen.getAttribute('data-id-target') || '');
            if (referenceModal) {
                var modalType = referenceModal.querySelector('select[name="reference_type"]');
                var modalQuery = referenceModal.querySelector('input[name="q"]');
                if (modalType && referenceType) {
                    modalType.value = referenceType.value;
                }
                var modalForm = referenceModal.querySelector(REFERENCE_FORM_SELECTOR);
                if (modalForm) {
                    modalForm.setAttribute('data-summary-target', referenceOpen.getAttribute('data-summary-target') || '');
                    modalForm.removeAttribute('data-admin-reference-cursor');
                    modalForm.removeAttribute('data-admin-reference-cursor-next');
                    syncReferenceFilters(modalForm);
                }
                if (modalQuery && referenceId && modalQuery.value === '') {
                    modalQuery.value = referenceId.value;
                }
            }
        }
    });
})();
