jQuery(document).ready(function ($) {
  var T = window.menuCoamTools || {};
  var i18n = T.i18n || {};

  function currentMenuId() {
    var v = $('#menu').val();
    return v ? parseInt(v, 10) : 0;
  }

  // ============================================================
  // ボタン注入
  // ============================================================

  // 右上：Import / Export（メニュー選択行の .manage-menus 内）
  function injectTopButtons() {
    if ($('#menucoam-tools-top').length) { return; }
    var $anchor = $('.manage-menus');
    if (!$anchor.length) { return; }
    var $wrap = $('<span id="menucoam-tools-top" class="menucoam-tools-top"></span>');
    var $imp = $('<button type="button" class="button" id="menucoam-import"></button>').text(i18n.importBtn || 'Import Menu');
    var $exp = $('<button type="button" class="button" id="menucoam-export"></button>').text(i18n.exportBtn || 'Export Menu');
    var $file = $('<input type="file" id="menucoam-import-file" accept=".json,application/json" style="display:none;">');
    $wrap.append($imp).append(document.createTextNode(' ')).append($exp).append($file);
    $anchor.append($wrap);
  }

  // 下部：複製（#save_menu_footer の隣）
  function injectDuplicateButton() {
    if ($('#menucoam-duplicate').length) { return; }
    var $save = $('#save_menu_footer');
    if (!$save.length) { return; }
    var $btn = $('<button type="button" class="button" id="menucoam-duplicate"></button>').text(i18n.duplicateBtn || 'メニューを複製');
    $save.after($btn).after(document.createTextNode(' '));
  }

  // ============================================================
  // ハンドラ
  // ============================================================

  // 複製
  $(document).on('click', '#menucoam-duplicate', function (e) {
    e.preventDefault();
    var menuId = currentMenuId();
    if (!menuId) { window.alert(i18n.noMenu); return; }
    if (!window.confirm(i18n.duplicateConfirm)) { return; }
    $.post(T.ajaxurl, {
      action: 'menucoam_duplicate_menu',
      nonce: T.nonce,
      menu_id: menuId
    }).done(function (res) {
      if (res && res.success) {
        window.location.href = res.data.redirect;
      } else {
        window.alert(i18n.genericError + (res && res.data ? res.data : ''));
      }
    }).fail(function () { window.alert(i18n.genericError); });
  });

  // エクスポート
  $(document).on('click', '#menucoam-export', function (e) {
    e.preventDefault();
    var menuId = currentMenuId();
    if (!menuId) { window.alert(i18n.noMenu); return; }
    $.post(T.ajaxurl, {
      action: 'menucoam_export_menu',
      nonce: T.nonce,
      menu_id: menuId
    }).done(function (res) {
      if (!res || !res.success) {
        window.alert(i18n.genericError + (res && res.data ? res.data : ''));
        return;
      }
      var data = res.data;
      var filename = data.filename || 'menu-export.json';
      var payload = $.extend({}, data);
      delete payload.filename;
      var blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }).fail(function () { window.alert(i18n.genericError); });
  });

  // インポート：ボタン → 隠しfile input をクリック（value リセットで同一ファイル再選択も発火）
  $(document).on('click', '#menucoam-import', function (e) {
    e.preventDefault();
    $('#menucoam-import-file').val('').trigger('click');
  });

  $(document).on('change', '#menucoam-import-file', function () {
    var file = this.files && this.files[0];
    if (!file) { return; }
    if (!/\.json$/i.test(file.name)) { window.alert(i18n.invalidFile); return; }
    var reader = new FileReader();
    reader.onload = function (ev) {
      var parsed;
      try {
        parsed = JSON.parse(ev.target.result);
      } catch (err) {
        window.alert(i18n.parseError);
        return;
      }
      if (!parsed || !parsed.items || !parsed.items.length) {
        window.alert(i18n.missingItems);
        return;
      }
      $.post(T.ajaxurl, {
        action: 'menucoam_import_menu',
        nonce: T.nonce,
        data: JSON.stringify(parsed)
      }).done(function (res) {
        if (res && res.success) {
          var d = res.data;
          var msg = d.imported + ' ' + (i18n.importedFmt || '件を取り込みました。');
          if (d.unresolved && d.unresolved.length) {
            msg += '\n' + d.unresolved.length + ' ' + (i18n.unresolvedFmt || '件はリンク未解決（要確認）です。');
          }
          window.alert(msg);
          window.location.href = d.redirect;
        } else {
          window.alert(i18n.genericError + (res && res.data ? res.data : ''));
        }
      }).fail(function () { window.alert(i18n.genericError); });
    };
    reader.onerror = function () { window.alert(i18n.genericError); };
    reader.readAsText(file);
  });

  // ============================================================
  // 初期化
  // ============================================================
  injectTopButtons();
  injectDuplicateButton();
});
