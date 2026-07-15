(function () {
  'use strict';

  var typeLabels = { text: '텍스트', textarea: '긴 텍스트', select: '선택', checkbox: '체크박스' };

  function parse(root) {
    var textarea = root.querySelector('[data-admin-comment-extra-fields-json]');
    try {
      var parsed = JSON.parse(textarea ? textarea.value || '[]' : '[]');
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  }

  function fieldInput(root, key) {
    return root.querySelector('[data-admin-comment-extra-field-input="' + key + '"]');
  }

  function randomKey(definitions) {
    var existing = {};
    definitions.forEach(function (field) { existing[String(field.key || '')] = true; });
    var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    var key = '';
    do {
      key = 'field_';
      var bytes = window.crypto && window.crypto.getRandomValues ? window.crypto.getRandomValues(new Uint8Array(10)) : null;
      for (var index = 0; index < 10; index += 1) {
        key += chars[(bytes ? bytes[index] : Math.floor(Math.random() * 256)) % chars.length];
      }
    } while (existing[key]);
    return key;
  }

  function normalize(definitions) {
    return definitions.slice(0, 20).map(function (field) {
      var type = Object.prototype.hasOwnProperty.call(typeLabels, field.type) ? field.type : 'text';
      return {
        key: String(field.key || ''),
        label: String(field.label || '').trim(),
        type: type,
        required: !!field.required,
        options: Array.isArray(field.options) ? field.options.map(String).filter(Boolean) : [],
        privacy_purpose: String(field.privacy_purpose || '').trim(),
        show_privacy_purpose: !Object.prototype.hasOwnProperty.call(field, 'show_privacy_purpose') || !!field.show_privacy_purpose,
        export_policy: field.export_policy === 'exclude' ? 'exclude' : 'include',
        cleanup_policy: field.cleanup_policy === 'retain' ? 'retain' : 'anonymize'
      };
    }).filter(function (field) { return field.key && field.label; });
  }

  function write(root, definitions) {
    var textarea = root.querySelector('[data-admin-comment-extra-fields-json]');
    if (!textarea) return;
    textarea.value = JSON.stringify(normalize(definitions));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function render(root) {
    var definitions = normalize(parse(root));
    var list = root.querySelector('[data-admin-comment-extra-field-list]');
    var wrap = root.querySelector('[data-admin-comment-extra-field-table-wrap]');
    var empty = root.querySelector('[data-admin-comment-extra-field-empty]');
    if (!list) return;
    list.innerHTML = '';
    definitions.forEach(function (field, index) {
      var row = document.createElement('tr');
      row.setAttribute('data-admin-reorder-item', '');
      row.setAttribute('data-admin-reorder-key', field.key);
      row.setAttribute('data-admin-comment-extra-field-key', field.key);

      var order = document.createElement('td');
      order.className = 'admin-comment-extra-field-order';
      order.innerHTML = '<div class="admin-row-actions"><span class="admin-drag-handle" draggable="true" data-admin-reorder-handle title="드래그해서 순서 변경" aria-label="드래그해서 순서 변경"><span class="material-symbols-outlined admin-drag-handle-icon" aria-hidden="true">apps</span></span></div>';
      [['up', '위로', 'arrow_upward'], ['down', '아래로', 'arrow_downward']].forEach(function (action) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-icon btn-solid-light';
        button.setAttribute('data-admin-reorder-move', action[0]);
        button.title = action[1];
        button.setAttribute('aria-label', action[1]);
        button.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">' + action[2] + '</span>';
        button.disabled = (action[0] === 'up' && index === 0) || (action[0] === 'down' && index === definitions.length - 1);
        order.firstChild.appendChild(button);
      });
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
      var privacy = document.createElement('td');
      privacy.textContent = (field.privacy_purpose || '수집·이용 목적 미입력')
        + (field.privacy_purpose ? (field.show_privacy_purpose ? ' (작성 화면 표시)' : ' (작성 화면 숨김)') : '') + ' / '
        + (field.export_policy === 'exclude' ? '사본에 포함하지 않음' : '사본에 포함') + ' / '
        + (field.cleanup_policy === 'retain' ? '계정 정리 후에도 보관' : '계정 정리 시 제거');
      row.appendChild(privacy);
      var actions = document.createElement('td');
      actions.className = 'text-end';
      actions.innerHTML = '<button type="button" class="btn btn-sm btn-solid-light" data-admin-comment-extra-field-edit="' + index + '">수정</button> '
        + '<button type="button" class="btn btn-sm btn-outline-danger" data-admin-comment-extra-field-remove="' + index + '">제거</button>';
      row.appendChild(actions);
      list.appendChild(row);
    });
    if (wrap) wrap.hidden = definitions.length === 0;
    if (empty) empty.hidden = definitions.length !== 0;
  }

  function setModal(root, field, index) {
    var modal = root.querySelector('[data-admin-comment-extra-field-modal]');
    if (!modal) return;
    field = field || { key: randomKey(parse(root)), label: '', type: 'text', required: false, options: [], privacy_purpose: '', show_privacy_purpose: true, export_policy: 'include', cleanup_policy: 'anonymize' };
    modal.querySelector('[data-admin-comment-extra-field-index]').value = index >= 0 ? String(index) : '';
    ['key', 'label', 'type', 'privacy_purpose', 'export_policy', 'cleanup_policy'].forEach(function (key) {
      fieldInput(modal, key).value = field[key] || (key === 'type' ? 'text' : '');
    });
    fieldInput(modal, 'required').checked = !!field.required;
    fieldInput(modal, 'show_privacy_purpose').checked = !Object.prototype.hasOwnProperty.call(field, 'show_privacy_purpose') || !!field.show_privacy_purpose;
    fieldInput(modal, 'options').value = Array.isArray(field.options) ? field.options.join('\n') : '';
    modal.querySelector('[data-admin-comment-extra-field-modal-title]').textContent = index >= 0 ? '댓글 입력 항목 수정' : '댓글 입력 항목 추가';
    modal.querySelector('[data-admin-comment-extra-field-options-row]').hidden = field.type !== 'select';
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-admin-comment-extra-fields-editor]').forEach(function (root) {
      render(root);
      root.addEventListener('click', function (event) {
        var add = event.target.closest('[data-admin-comment-extra-field-add]');
        var edit = event.target.closest('[data-admin-comment-extra-field-edit]');
        var remove = event.target.closest('[data-admin-comment-extra-field-remove]');
        var save = event.target.closest('[data-admin-comment-extra-field-save]');
        if (add) setModal(root, null, -1);
        if (edit) {
          var editIndex = parseInt(edit.getAttribute('data-admin-comment-extra-field-edit'), 10);
          setModal(root, parse(root)[editIndex] || null, editIndex);
        }
        if (remove) {
          var definitions = parse(root);
          definitions.splice(parseInt(remove.getAttribute('data-admin-comment-extra-field-remove'), 10), 1);
          write(root, definitions);
          render(root);
        }
        if (save) {
          var modal = root.querySelector('[data-admin-comment-extra-field-modal]');
          var label = fieldInput(modal, 'label');
          var type = fieldInput(modal, 'type');
          var options = fieldInput(modal, 'options');
          label.setCustomValidity(label.value.trim() ? '' : '라벨을 입력해 주세요.');
          var optionValues = options.value.split(/\r?\n/).map(function (value) { return value.trim(); }).filter(Boolean);
          options.setCustomValidity(type.value === 'select' && optionValues.length === 0 ? '선택지를 하나 이상 입력해 주세요.' : '');
          if (!label.reportValidity() || !options.reportValidity()) return;
          var current = parse(root);
          var indexValue = modal.querySelector('[data-admin-comment-extra-field-index]').value;
          var field = {
            key: fieldInput(modal, 'key').value || randomKey(current),
            label: label.value.trim(),
            type: type.value,
            required: fieldInput(modal, 'required').checked,
            options: type.value === 'select' ? optionValues : [],
            privacy_purpose: fieldInput(modal, 'privacy_purpose').value.trim(),
            show_privacy_purpose: fieldInput(modal, 'show_privacy_purpose').checked,
            export_policy: fieldInput(modal, 'export_policy').value === 'exclude' ? 'exclude' : 'include',
            cleanup_policy: fieldInput(modal, 'cleanup_policy').value === 'retain' ? 'retain' : 'anonymize'
          };
          if (indexValue === '') current.push(field); else current[parseInt(indexValue, 10)] = field;
          write(root, current);
          render(root);
          var close = modal.querySelector('.modal-close');
          if (close) close.click();
        }
      });
      var type = root.querySelector('[data-admin-comment-extra-field-modal] [data-admin-comment-extra-field-input="type"]');
      if (type) type.addEventListener('change', function () {
        root.querySelector('[data-admin-comment-extra-field-options-row]').hidden = type.value !== 'select';
      });
      var list = root.querySelector('[data-admin-comment-extra-field-list]');
      if (list) list.addEventListener('admin:reorder', function () {
        var byKey = {};
        parse(root).forEach(function (field) { byKey[field.key] = field; });
        var reordered = Array.prototype.map.call(list.querySelectorAll('[data-admin-comment-extra-field-key]'), function (row) {
          return byKey[row.getAttribute('data-admin-comment-extra-field-key')];
        }).filter(Boolean);
        write(root, reordered);
        render(root);
      });
    });
  });
}());
