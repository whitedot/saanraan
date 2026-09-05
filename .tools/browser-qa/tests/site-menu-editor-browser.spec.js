const { test, expect } = require('@playwright/test');
const path = require('path');

const script = path.resolve(__dirname, '../../../modules/site_menu/assets/admin.js');

async function editorFixture(page) {
  const forms = ['save_menu', 'save_item', 'delete_item', 'publish_site_menus', 'discard_changes'];
  await page.setContent(`
    <p data-site-menu-pending></p>
    <input name="item_sort_order[1]" value="30" form="site-menu-draft-form" data-admin-sort-order>
    <input name="item_sort_order[2]" value="10" form="site-menu-draft-form" data-admin-sort-order>
    <form id="site-menu-draft-form" data-site-menu-editor-form><input name="intent" value="save_draft"></form>
    ${forms.map(intent => `<form id="${intent}" data-site-menu-editor-form><input name="intent" value="${intent}"></form>`).join('')}
    <button data-overlay="#site_menu_edit_item_1">Edit</button>
    <div id="site_menu_edit_item_1"><input name="sort_order" value="100"></div>
  `);
  await page.addScriptTag({ path: script });
  await page.evaluate(() => {
    window.editorSubmissions = [];
    document.addEventListener('submit', event => {
      if (!event.defaultPrevented) {
        window.editorSubmissions.push(Array.from(new FormData(event.target).entries()));
      }
      event.preventDefault();
    });
  });
}

test('site menu editor carries current order through every staged operation and publish', async ({ page }) => {
  await editorFixture(page);
  for (const id of ['save_menu', 'save_item', 'delete_item', 'publish_site_menus', 'site-menu-draft-form']) {
    await page.evaluate(id => document.getElementById(id).requestSubmit(), id);
    const entries = await page.evaluate(() => window.editorSubmissions.at(-1));
    expect(entries.filter(([name]) => name === 'item_sort_order[1]')).toEqual([['item_sort_order[1]', '30']]);
    expect(entries.filter(([name]) => name === 'item_sort_order[2]')).toEqual([['item_sort_order[2]', '10']]);
  }
  await page.locator('[data-admin-sort-order][name="item_sort_order[1]"]').fill('50');
  await page.locator('[data-admin-sort-order][name="item_sort_order[1]"]').dispatchEvent('change');
  await expect(page.locator('[data-site-menu-pending]')).toHaveText('임시저장되지 않은 변경이 있습니다.');
  await page.evaluate(() => document.getElementById('save_menu').requestSubmit());
  expect((await page.evaluate(() => window.editorSubmissions.at(-1))).filter(([name]) => name === 'item_sort_order[1]')).toEqual([['item_sort_order[1]', '50']]);
  await page.getByRole('button', { name: 'Edit' }).click();
  await expect(page.locator('#site_menu_edit_item_1 [name="sort_order"]')).toHaveValue('50');
  await page.evaluate(() => document.getElementById('discard_changes').requestSubmit());
  expect(await page.evaluate(() => window.editorSubmissions.at(-1))).toEqual([['intent', 'discard_changes']]);
});

test('site menu editor descendant confirmation gates the actual submitted payload', async ({ page }) => {
  await editorFixture(page);
  await page.evaluate(() => {
    const form = document.getElementById('delete_item');
    form.setAttribute('data-site-menu-delete-descendants', '2');
    form.setAttribute('data-site-menu-delete-message', 'Delete 2 descendants?');
    form.insertAdjacentHTML('beforeend', '<input name="confirm_descendant_delete" value="0" data-site-menu-delete-confirm-input>');
    window.confirm = () => false;
    form.requestSubmit();
  });
  expect(await page.evaluate(() => window.editorSubmissions)).toEqual([]);
  await page.evaluate(() => {
    window.confirm = () => true;
    document.getElementById('delete_item').requestSubmit();
  });
  const entries = await page.evaluate(() => window.editorSubmissions.at(-1));
  expect(entries).toContainEqual(['confirm_descendant_delete', '1']);
  expect(entries).toContainEqual(['item_sort_order[1]', '30']);
});

test('site menu saved draft deletion requires confirmation and excludes pending order inputs', async ({ page }) => {
  await editorFixture(page);
  await page.evaluate(() => {
    document.body.insertAdjacentHTML('beforeend', '<form id="delete_draft" data-site-menu-editor-form data-site-menu-delete-message="Discard saved draft and current edits?"><input name="intent" value="delete_draft"><input name="confirm_delete_draft" value="0" data-site-menu-delete-draft-confirm></form>');
    window.confirm = () => false;
    document.getElementById('delete_draft').requestSubmit();
  });
  expect(await page.evaluate(() => window.editorSubmissions)).toEqual([]);
  await expect(page.locator('[data-admin-sort-order]').first()).toHaveValue('30');
  await page.evaluate(() => {
    window.confirm = () => true;
    document.getElementById('delete_draft').requestSubmit();
  });
  expect(await page.evaluate(() => window.editorSubmissions.at(-1))).toEqual([
    ['intent', 'delete_draft'], ['confirm_delete_draft', '1'],
  ]);
});
