(function () {
  'use strict';

  var typeLabels = { text: '텍스트', textarea: '긴 텍스트', select: '선택', checkbox: '체크박스' };

  function input(root, key) {
    return root.querySelector('[data-community-admin-post-extra-field-input="' + key + '"]');
  }

  function reportTemporaryValidity(control, message) {
    control.setCustomValidity(message);
    var valid = control.reportValidity();
    control.setCustomValidity('');
    return valid;
  }

  function normalize(raw) {
    var definitions = [];
    var seen = {};
    if (!Array.isArray(raw)) return definitions;
    raw.forEach(function (item) {
      if (!item || typeof item !== 'object' || definitions.length >= 20) return;
      var key = String(item.key || '').trim().toLowerCase();
      var label = String(item.label || '').replace(/\s+/g, ' ').trim().slice(0, 120);
      if (!/^[a-z][a-z0-9_]{1,59}$/.test(key) || seen[key] || !label) return;
      var type = Object.prototype.hasOwnProperty.call(typeLabels, item.type) ? item.type : 'text';
      var options = [];
      if (type === 'select') {
        (Array.isArray(item.options) ? item.options : []).forEach(function (option) {
          option = String(option || '').replace(/\s+/g, ' ').trim().slice(0, 120);
          if (option && options.indexOf(option) === -1 && options.length < 50) options.push(option);
        });
        if (!options.length) return;
      }
      definitions.push({
        key: key,
        label: label,
        type: type,
        required: !!item.required,
        options: options,
        visibility: item.visibility === 'admin' ? 'admin' : 'public',
        show_on_view: !Object.prototype.hasOwnProperty.call(item, 'show_on_view') || !!item.show_on_view,
        show_in_admin: !!item.show_in_admin,
        privacy_purpose: String(item.privacy_purpose || '').trim(),
        export_policy: item.export_policy === 'exclude' ? 'exclude' : 'include',
        cleanup_policy: item.cleanup_policy === 'retain' ? 'retain' : 'anonymize'
      });
      seen[key] = true;
    });
    return definitions;
  }

  function parse(root) {
    var textarea = root.querySelector('[data-community-admin-post-extra-fields-json]');
    try {
      return normalize(JSON.parse(textarea ? textarea.value || '[]' : '[]'));
    } catch (error) {
      return [];
    }
  }

  function write(root, definitions) {
    var textarea = root.querySelector('[data-community-admin-post-extra-fields-json]');
    if (!textarea) return;
    textarea.value = JSON.stringify(normalize(definitions));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function randomKey(definitions) {
    var used = {};
    definitions.forEach(function (field) { used[field.key] = true; });
    var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    var key = '';
    do {
      key = 'field_';
      var bytes = window.crypto && window.crypto.getRandomValues ? window.crypto.getRandomValues(new Uint8Array(10)) : null;
      for (var index = 0; index < 10; index += 1) {
        key += chars[(bytes ? bytes[index] : Math.floor(Math.random() * 256)) % chars.length];
      }
    } while (used[key]);
    return key;
  }

  function render(root) {
    var definitions = parse(root);
    var list = root.querySelector('[data-community-admin-post-extra-field-list]');
    var wrap = root.querySelector('[data-community-admin-post-extra-field-table-wrap]');
    var empty = root.querySelector('[data-community-admin-post-extra-field-empty]');
    if (!list) return;
    list.innerHTML = '';
    definitions.forEach(function (field, index) {
      var row = document.createElement('tr');
      row.setAttribute('data-admin-reorder-item', '');
      row.setAttribute('data-admin-reorder-key', field.key);
      row.setAttribute('data-community-admin-post-extra-field-key', field.key);

      var order = document.createElement('td');
      var orderActions = document.createElement('div');
      orderActions.className = 'admin-row-actions';
      orderActions.innerHTML = '<span class="admin-drag-handle" draggable="true" data-admin-reorder-handle title="드래그해서 순서 변경" aria-label="드래그해서 순서 변경"><span class="material-symbols-outlined admin-drag-handle-icon" aria-hidden="true">apps</span></span>';
      [['up', '위로', 'arrow_upward'], ['down', '아래로', 'arrow_downward']].forEach(function (action) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-icon btn-solid-light';
        button.setAttribute('data-admin-reorder-move', action[0]);
        button.setAttribute('aria-label', action[1]);
        button.title = action[1];
        button.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">' + action[2] + '</span>';
        button.disabled = (action[0] === 'up' && index === 0) || (action[0] === 'down' && index === definitions.length - 1);
        orderActions.appendChild(button);
      });
      order.appendChild(orderActions);
      row.appendChild(order);

      var label = document.createElement('td');
      label.textContent = field.label;
      if (field.required) {
        var required = document.createElement('span');
        required.className = 'sr-required-label';
        required.textContent = '(필수)';
        label.appendChild(required);
      }
      row.appendChild(label);

      var type = document.createElement('td');
      type.textContent = typeLabels[field.type];
      row.appendChild(type);

      var display = document.createElement('td');
      var displayLabels = [field.visibility === 'admin' ? '관리자 전용' : '공개'];
      if (field.show_on_view) displayLabels.push('본문');
      if (field.show_in_admin) displayLabels.push('관리자 목록');
      display.textContent = displayLabels.join(' / ');
      row.appendChild(display);

      var privacy = document.createElement('td');
      privacy.textContent = (field.privacy_purpose || '수집·이용 목적 미입력') + ' / '
        + (field.export_policy === 'exclude' ? '사본 제외' : '사본 포함') + ' / '
        + (field.cleanup_policy === 'retain' ? '계정 정리 후 보관' : '계정 정리 시 제거');
      row.appendChild(privacy);

      var actions = document.createElement('td');
      actions.className = 'text-end';
      var modal = root.querySelector('[data-community-admin-post-extra-field-modal]');
      var modalSelector = modal && modal.id ? '#' + modal.id : '';
      actions.innerHTML = '<button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" data-overlay="' + modalSelector + '" data-community-admin-post-extra-field-edit="' + index + '">수정</button> '
        + '<button type="button" class="btn btn-sm btn-outline-danger" data-community-admin-post-extra-field-remove="' + index + '">제거</button>';
      row.appendChild(actions);
      list.appendChild(row);
    });
    if (wrap) wrap.hidden = definitions.length === 0;
    if (empty) empty.hidden = definitions.length !== 0;
  }

  function syncOptions(modal) {
    var type = input(modal, 'type');
    var row = modal.querySelector('[data-community-admin-post-extra-field-options-row]');
    var isSelect = type && type.value === 'select';
    if (row) row.hidden = !isSelect;
  }

  function setModal(root, field, index) {
    var modal = root.querySelector('[data-community-admin-post-extra-field-modal]');
    if (!modal) return;
    field = field || { key: randomKey(parse(root)), label: '', type: 'text', required: false, options: [], visibility: 'public', show_on_view: true, show_in_admin: false, privacy_purpose: '', export_policy: 'include', cleanup_policy: 'anonymize' };
    modal.querySelector('[data-community-admin-post-extra-field-index]').value = index >= 0 ? String(index) : '';
    input(modal, 'key').value = field.key || '';
    input(modal, 'label').value = field.label || '';
    input(modal, 'type').value = field.type || 'text';
    input(modal, 'options').value = Array.isArray(field.options) ? field.options.join('\n') : '';
    input(modal, 'required').checked = !!field.required;
    input(modal, 'visibility').value = field.visibility === 'admin' ? 'admin' : 'public';
    input(modal, 'show_on_view').checked = !Object.prototype.hasOwnProperty.call(field, 'show_on_view') || !!field.show_on_view;
    input(modal, 'show_in_admin').checked = !!field.show_in_admin;
    input(modal, 'privacy_purpose').value = field.privacy_purpose || '';
    input(modal, 'export_policy').value = field.export_policy === 'exclude' ? 'exclude' : 'include';
    input(modal, 'cleanup_policy').value = field.cleanup_policy === 'retain' ? 'retain' : 'anonymize';
    input(modal, 'label').setCustomValidity('');
    input(modal, 'options').setCustomValidity('');
    modal.querySelector('[data-community-admin-post-extra-field-modal-title]').textContent = index >= 0 ? '게시글 입력 항목 수정' : '게시글 입력 항목 추가';
    syncOptions(modal);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-community-admin-post-extra-fields-editor]').forEach(function (root) {
      render(root);
      root.addEventListener('click', function (event) {
        var add = event.target.closest('[data-community-admin-post-extra-field-add]');
        var edit = event.target.closest('[data-community-admin-post-extra-field-edit]');
        var remove = event.target.closest('[data-community-admin-post-extra-field-remove]');
        var save = event.target.closest('[data-community-admin-post-extra-field-save]');
        if (add) setModal(root, null, -1);
        if (edit) {
          var editIndex = parseInt(edit.getAttribute('data-community-admin-post-extra-field-edit'), 10);
          setModal(root, parse(root)[editIndex] || null, editIndex);
        }
        if (remove) {
          var definitions = parse(root);
          definitions.splice(parseInt(remove.getAttribute('data-community-admin-post-extra-field-remove'), 10), 1);
          write(root, definitions);
          render(root);
        }
        if (save) {
          var modal = root.querySelector('[data-community-admin-post-extra-field-modal]');
          var label = input(modal, 'label');
          var type = input(modal, 'type');
          var options = input(modal, 'options');
          var optionValues = options.value.split(/\r?\n/).map(function (value) { return value.replace(/\s+/g, ' ').trim().slice(0, 120); }).filter(function (value, index, values) { return value && values.indexOf(value) === index; }).slice(0, 50);
          if (!label.value.trim()) {
            reportTemporaryValidity(label, '라벨을 입력해 주세요.');
            return;
          }
          if (type.value === 'select' && !optionValues.length) {
            reportTemporaryValidity(options, '선택지를 하나 이상 입력해 주세요.');
            return;
          }
          var current = parse(root);
          var indexValue = modal.querySelector('[data-community-admin-post-extra-field-index]').value;
          var field = {
            key: input(modal, 'key').value || randomKey(current),
            label: label.value.replace(/\s+/g, ' ').trim(),
            type: type.value,
            required: input(modal, 'required').checked,
            options: type.value === 'select' ? optionValues : [],
            visibility: input(modal, 'visibility').value === 'admin' ? 'admin' : 'public',
            show_on_view: input(modal, 'show_on_view').checked,
            show_in_admin: input(modal, 'show_in_admin').checked,
            privacy_purpose: input(modal, 'privacy_purpose').value.trim(),
            export_policy: input(modal, 'export_policy').value === 'exclude' ? 'exclude' : 'include',
            cleanup_policy: input(modal, 'cleanup_policy').value === 'retain' ? 'retain' : 'anonymize'
          };
          if (indexValue === '') current.push(field); else current[parseInt(indexValue, 10)] = field;
          write(root, current);
          render(root);
          var close = modal.querySelector('.modal-close');
          if (close) close.click();
        }
      });
      var modal = root.querySelector('[data-community-admin-post-extra-field-modal]');
      var type = modal ? input(modal, 'type') : null;
      if (type) type.addEventListener('change', function () { syncOptions(modal); });
      var list = root.querySelector('[data-community-admin-post-extra-field-list]');
      if (list) list.addEventListener('admin:reorder', function () {
        var byKey = {};
        parse(root).forEach(function (field) { byKey[field.key] = field; });
        var reordered = Array.prototype.map.call(list.querySelectorAll('[data-community-admin-post-extra-field-key]'), function (row) { return byKey[row.getAttribute('data-community-admin-post-extra-field-key')]; }).filter(Boolean);
        write(root, reordered);
        render(root);
      });
    });
  });
}());
