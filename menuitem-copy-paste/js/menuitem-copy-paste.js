jQuery(document).ready(function ($) {
  let copiedMenuItem = null;

  // コピー&ペーストボタンの追加
  function addCopyPasteButtons() {
    $('.menu-item').each(function () {
      if (!$(this).find('.menuitem-copy-paste-buttons').length) {
        const menuItemBar = $(this).find('.menu-item-bar .menu-item-handle');
        const menuItemId = $(this).attr('id').replace('menu-item-', '');

        const buttons = $(`
          <span class="menuitem-copy-paste-buttons">
            <button type="button" class="button-new" title="New (下に新規カスタムリンク)" data-id="${menuItemId}">
              <span class="dashicons dashicons-plus-alt"></span>
            </button>
            <button type="button" class="button-clone" title="Clone (下に複製)" data-id="${menuItemId}">
              <span class="dashicons dashicons-admin-page"></span>
            </button>
            <button type="button" class="button-copy" title="Copy (項目をコピー)" data-id="${menuItemId}">
              <span class="dashicons dashicons-admin-links"></span>
            </button>
            <button type="button" class="button-paste is-disabled" title="Paste (下にペースト)" data-id="${menuItemId}">
              <span class="dashicons dashicons-clipboard"></span>
            </button>
            <button type="button" class="button-delete" title="Delete (項目を削除)" data-id="${menuItemId}">
              <span class="dashicons dashicons-trash"></span>
            </button>
          </span>
        `);

        menuItemBar.append(buttons);
      }
    });
  }

  // Newボタンのクリックハンドラ
  $(document).on('click', '.button-new', function (e) {
    e.preventDefault();
    const menuItemId = $(this).data('id');
    const menuId = $('#menu').val();

    $.ajax({
      url: menuCopyPaste.ajaxurl,
      type: 'POST',
      data: {
        action: 'menucoam_add_new_custom_link',
        nonce: menuCopyPaste.nonce,
        menu_id: menuId,
        after_item_id: menuItemId
      },
      success: function (response) {
        if (response.success) {
          // ページをリロードして新しい項目を表示
          window.location.reload();
        } else {
          alert('新規カスタムリンクの作成に失敗しました: ' + response.data);
        }
      }
    });
  });

  // Cloneボタンのクリックハンドラ
  $(document).on('click', '.button-clone', function (e) {
    e.preventDefault();
    const menuItemId = $(this).data('id');

    $.ajax({
      url: menuCopyPaste.ajaxurl,
      type: 'POST',
      data: {
        action: 'menucoam_clone_menu_item',
        nonce: menuCopyPaste.nonce,
        menu_item_id: menuItemId
      },
      success: function (response) {
        if (response.success) {
          // ページをリロードして新しい項目を表示
          window.location.reload();
        } else {
          alert('複製に失敗しました: ' + response.data);
        }
      }
    });
  });

  // コピーボタンのクリックハンドラ
  $(document).on('click', '.button-copy', function (e) {
    e.preventDefault();
    const menuItemId = $(this).data('id');

    // 既存のcopied-menu-itemクラスをすべて削除（新しくコピーする前に）
    $('.menu-item').removeClass('copied-menu-item');

    $.ajax({
      url: menuCopyPaste.ajaxurl,
      type: 'POST',
      data: {
        action: 'menucoam_copy_menu_item',
        nonce: menuCopyPaste.nonce,
        menu_item_id: menuItemId
      },
      success: function (response) {
        if (response.success) {
          copiedMenuItem = response.data;
          $('.button-paste').removeClass('is-disabled');

          // コピーしたメニューアイテムにクラスを追加
          $('#menu-item-' + menuItemId).addClass('copied-menu-item');

          alert('項目をコピーしました');
        } else {
          alert('コピーに失敗しました: ' + response.data);
        }
      }
    });
  });

  // ペーストボタンのクリックハンドラ
  $(document).on('click', '.button-paste', function (e) {
    e.preventDefault();
    if (!copiedMenuItem) {
      alert('先に項目をコピーしてください');
      return;
    }

    const menuId = $('#menu').val();
    const afterItemId = $(this).data('id'); // このボタンが属するメニューアイテムのID

    $.ajax({
      url: menuCopyPaste.ajaxurl,
      type: 'POST',
      data: {
        action: 'menucoam_paste_menu_item',
        nonce: menuCopyPaste.nonce,
        menu_id: menuId,
        after_item_id: afterItemId,
        item_data: JSON.stringify(copiedMenuItem)
      },
      success: function (response) {
        if (response.success) {
          // ページをリロードして新しい項目を表示
          window.location.reload();
        } else {
          alert('ペーストに失敗しました: ' + response.data);
        }
      }
    });
  });

  // 削除ボタンのクリックハンドラ
  $(document).on('click', '.button-delete', function (e) {
    e.preventDefault();
    const menuItemId = $(this).data('id');
    const menuItemTitle = $('#menu-item-' + menuItemId).find('.menu-item-title').text();

    if (!confirm(`「${menuItemTitle}」を削除しますか？\nこの操作は取り消せません。`)) {
      return;
    }

    $.ajax({
      url: menuCopyPaste.ajaxurl,
      type: 'POST',
      data: {
        action: 'menucoam_delete_menu_item',
        nonce: menuCopyPaste.nonce,
        menu_item_id: menuItemId
      },
      success: function (response) {
        if (response.success) {
          // メニューアイテムをDOMから削除（アニメーション付き）
          $('#menu-item-' + menuItemId).fadeOut(300, function() {
            $(this).remove();
          });
        } else {
          alert('削除に失敗しました: ' + response.data);
        }
      }
    });
  });

  // メニュー項目が追加されたときにボタンを追加
  $(document).on('menu-item-added', function () {
    addCopyPasteButtons();
  });

  // 初期化時にボタンを追加
  addCopyPasteButtons();
});
