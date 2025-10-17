jQuery(document).ready(function($) {
  let copiedMenuItem = null;
  
  // コピー&ペーストボタンの追加
  function addCopyPasteButtons() {
      $('.menu-item').each(function() {
          if (!$(this).find('.menuitem-copy-paste-buttons').length) {
              const menuItemBar = $(this).find('.menu-item-bar .menu-item-handle');
              const menuItemId = $(this).attr('id').replace('menu-item-', '');
              
              const buttons = $(`
                  <span class="menuitem-copy-paste-buttons">
                      <button type="button" class="button-copy" title="Copy" data-id="${menuItemId}">
                          <span class="dashicons dashicons-admin-page"></span>
                      </button>
                      <button type="button" class="button-paste" title="Paste" data-id="${menuItemId}">
                          <span class="dashicons dashicons-clipboard"></span>
                      </button>
                  </span>
              `);
              
              menuItemBar.append(buttons);
          }
      });
  }

  // コピーボタンのクリックハンドラ
  $(document).on('click', '.button-copy', function(e) {
      e.preventDefault();
      const menuItemId = $(this).data('id');
      
      $.ajax({
          url: menuCopyPaste.ajaxurl,
          type: 'POST',
          data: {
              action: 'copy_menu_item',
              nonce: menuCopyPaste.nonce,
              menu_item_id: menuItemId
          },
          success: function(response) {
              if (response.success) {
                  copiedMenuItem = response.data;
                  $('.button-paste').removeClass('disabled');
                  alert('項目をコピーしました');
              } else {
                  alert('コピーに失敗しました: ' + response.data);
              }
          }
      });
  });

  // ペーストボタンのクリックハンドラ
  $(document).on('click', '.button-paste', function(e) {
      e.preventDefault();
      if (!copiedMenuItem) {
          alert('先に項目をコピーしてください');
          return;
      }

      const menuId = $('#menu').val();
      
      $.ajax({
          url: menuCopyPaste.ajaxurl,
          type: 'POST',
          data: {
              action: 'paste_menu_item',
              nonce: menuCopyPaste.nonce,
              menu_id: menuId,
              item_data: JSON.stringify(copiedMenuItem)
          },
          success: function(response) {
              if (response.success) {
                  // ページをリロードして新しい項目を表示
                  window.location.reload();
              } else {
                  alert('ペーストに失敗しました: ' + response.data);
              }
          }
      });
  });

  // メニュー項目が追加されたときにボタンを追加
  $(document).on('menu-item-added', function() {
      addCopyPasteButtons();
  });

  // 初期化時にボタンを追加
  addCopyPasteButtons();
});