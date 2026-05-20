jQuery(document).ready(function ($) {
  let copiedMenuItem = null;

  // ============================================================
  // 折りたたみ機能 ヘルパー
  // ============================================================

  // .menu-item-depth-N クラスから depth を取得
  function getDepth($item) {
    const cls = $item.attr('class') || '';
    const m = cls.match(/menu-item-depth-(-?\d+)/);
    return m ? parseInt(m[1], 10) : 0;
  }

  // 直後の兄弟が自分より深ければ「子を持つ」
  function hasChildren($item) {
    const depth = getDepth($item);
    const $next = $item.next('.menu-item');
    return $next.length > 0 && getDepth($next) > depth;
  }

  // 自分の直系子孫を全て返す（自分の depth 以下が出現するまで）
  function getAllDescendants($item) {
    const depth = getDepth($item);
    const result = [];
    let $next = $item.next('.menu-item');
    while ($next.length) {
      if (getDepth($next) <= depth) break;
      result.push($next[0]);
      $next = $next.next('.menu-item');
    }
    return $(result);
  }

  // ============================================================
  // 折りたたみ状態の永続化（localStorage）
  // ============================================================

  function getStorageKey() {
    const menuId = $('#menu').val();
    return menuId ? 'menuitem-copy-paste-collapsed-' + menuId : null;
  }

  function loadCollapseState() {
    const key = getStorageKey();
    if (!key) return [];
    try {
      const data = window.localStorage.getItem(key);
      const arr = data ? JSON.parse(data) : [];
      return Array.isArray(arr) ? arr.map(String) : [];
    } catch (e) {
      return [];
    }
  }

  function saveCollapseState() {
    const key = getStorageKey();
    if (!key) return;
    const ids = [];
    $('.menu-item.menuitem-collapsed').each(function () {
      const id = this.id.replace('menu-item-', '');
      if (id) ids.push(id);
    });
    try {
      window.localStorage.setItem(key, JSON.stringify(ids));
    } catch (e) {
      // localStorage 利用不可・容量超過時はメモリのみで動作
    }
  }

  // ============================================================
  // 折りたたみロジック
  // ============================================================

  // collapsed な親の子孫すべてに .menuitem-hidden-by-collapse を付与
  // （アイデンポテント：何度呼んでも同じ結果になる）
  function refreshCollapseVisibility() {
    $('.menu-item').removeClass('menuitem-hidden-by-collapse');
    $('.menu-item.menuitem-collapsed').each(function () {
      getAllDescendants($(this)).addClass('menuitem-hidden-by-collapse');
    });
  }

  // 個別アイテムのトグルボタン表示・アイコン・ツールチップを更新
  function updateToggleButton($item) {
    const $btn = $item.find('> .menu-item-bar .button-toggle-collapse');
    if (!$btn.length) return;

    if (hasChildren($item)) {
      $btn.show();
      const isCollapsed = $item.hasClass('menuitem-collapsed');
      const $icon = $btn.find('.dashicons');
      $icon.removeClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2')
           .addClass(isCollapsed ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-up-alt2');
      $btn.attr('title', isCollapsed
        ? 'Expand (子メニューを表示)'
        : 'Collapse (子メニューを折りたたみ)');
    } else {
      $btn.hide();
      // 子を失った場合は collapsed 状態も解除
      if ($item.hasClass('menuitem-collapsed')) {
        $item.removeClass('menuitem-collapsed');
      }
    }
  }

  function updateAllToggleButtons() {
    $('.menu-item').each(function () {
      updateToggleButton($(this));
    });
  }

  function toggleCollapse($item) {
    if (!hasChildren($item)) return;
    $item.toggleClass('menuitem-collapsed');
    refreshCollapseVisibility();
    updateToggleButton($item);
    saveCollapseState();
  }

  function expandItem($item) {
    if ($item.hasClass('menuitem-collapsed')) {
      $item.removeClass('menuitem-collapsed');
      refreshCollapseVisibility();
      updateToggleButton($item);
      saveCollapseState();
    }
  }

  function restoreCollapseState() {
    const ids = loadCollapseState();
    ids.forEach(function (id) {
      const $item = $('#menu-item-' + id);
      if ($item.length && hasChildren($item)) {
        $item.addClass('menuitem-collapsed');
      }
    });
    refreshCollapseVisibility();
    updateAllToggleButtons();
  }

  // ============================================================
  // ボタン挿入（既存5 + 折りたたみ1）
  // ============================================================

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
            <button type="button" class="button-toggle-collapse" title="Collapse" data-id="${menuItemId}" style="display:none;">
              <span class="dashicons dashicons-arrow-up-alt2"></span>
            </button>
          </span>
        `);

        menuItemBar.append(buttons);
      }
    });
    updateAllToggleButtons();
  }

  // ============================================================
  // 既存のクリックハンドラ（New / Clone / Copy / Paste / Delete）
  // ============================================================

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
            // 削除により親の子が消えた可能性があるので再評価
            updateAllToggleButtons();
            saveCollapseState();
          });
        } else {
          alert('削除に失敗しました: ' + response.data);
        }
      }
    });
  });

  // ============================================================
  // 折りたたみ ハンドラ
  // ============================================================

  // 折りたたみボタンクリック
  $(document).on('click', '.button-toggle-collapse', function (e) {
    e.preventDefault();
    e.stopPropagation();
    const $item = $(this).closest('.menu-item');
    toggleCollapse($item);
  });

  // ドラッグ開始：折りたたまれていれば強制展開（子要素と一緒に動かすため）
  $(document).on('sortstart', '#menu-to-edit', function (e, ui) {
    if (ui && ui.item) {
      expandItem($(ui.item));
    }
  });

  // ドラッグ終了：階層変化を反映するため再評価（WP の depth 更新を待つため遅延）
  $(document).on('sortstop', '#menu-to-edit', function () {
    setTimeout(function () {
      updateAllToggleButtons();
      refreshCollapseVisibility();
      saveCollapseState();
    }, 100);
  });

  // ============================================================
  // 初期化
  // ============================================================

  // メニュー項目が追加されたときにボタンを追加
  $(document).on('menu-item-added', function () {
    addCopyPasteButtons();
  });

  // 初期化時にボタンを追加し、保存された折りたたみ状態を復元
  addCopyPasteButtons();
  restoreCollapseState();
});
