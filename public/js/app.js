// js/app.js - GLPI plugin Protocolo (port de assets/js/app.js)
document.addEventListener('DOMContentLoaded', () => {
  // Adicionar itens dinamicamente em pasta.form.php
  const btnAdd = document.getElementById('btnAddItem');
  const wrap = document.getElementById('itensWrap');
  if (btnAdd && wrap) {
    btnAdd.addEventListener('click', () => {
      const idx = wrap.querySelectorAll('.item-row').length;
      const row = document.createElement('div');
      row.className = 'row g-2 mb-2 item-row';
      row.innerHTML = `
        <div class="col-md-7"><input name="itens[${idx}][descricao]" class="form-control" placeholder="Descrição do item" required></div>
        <div class="col-md-2"><input name="itens[${idx}][quantidade]" type="number" min="1" value="1" class="form-control" placeholder="Qtd"></div>
        <div class="col-md-2"><input name="itens[${idx}][observacao]" class="form-control" placeholder="Obs."></div>
        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 btnRemove"><i class="ti ti-trash"></i></button></div>`;
      wrap.appendChild(row);
    });
    wrap.addEventListener('click', e => {
      if (e.target.closest('.btnRemove')) {
        const row = e.target.closest('.item-row');
        const tid = row.dataset.tipoId;
        if (tid) {
          const chk = document.getElementById('tipo' + tid);
          if (chk) chk.checked = false;
        }
        row.remove();
        if (wrap.querySelectorAll('.item-row').length === 0) {
          btnAdd.click();
        }
      }
    });
  }

  // Sincroniza Tipos -> Itens
  const tipoChecks = document.querySelectorAll('.tipo-check');
  if (tipoChecks.length && wrap) {
    tipoChecks.forEach(chk => {
      chk.addEventListener('change', () => {
        const tid = chk.value;
        const nome = chk.dataset.nome || chk.nextElementSibling?.textContent.trim() || 'Item';
        if (chk.checked) {
          if (wrap.querySelector(`.item-row[data-tipo-id="${tid}"]`)) return;
          const rows = wrap.querySelectorAll('.item-row');
          let targetRow = null;
          if (rows.length === 1) {
            const inp = rows[0].querySelector('input[name*="[descricao]"]');
            if (inp && inp.value.trim() === '' && !rows[0].dataset.tipoId) targetRow = rows[0];
          }
          if (targetRow) {
            targetRow.dataset.tipoId = tid;
            const inp = targetRow.querySelector('input[name*="[descricao]"]');
            if (inp) inp.value = nome;
            targetRow.classList.add('border', 'border-warning', 'rounded', 'p-1');
          } else {
            const idx = wrap.querySelectorAll('.item-row').length;
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 item-row border border-warning rounded p-1';
            row.dataset.tipoId = tid;
            row.innerHTML = `
              <div class="col-md-7"><input name="itens[${idx}][descricao]" class="form-control" value="${nome.replace(/"/g, '&quot;')}" required></div>
              <div class="col-md-2"><input name="itens[${idx}][quantidade]" type="number" min="1" value="1" class="form-control" placeholder="Qtd"></div>
              <div class="col-md-2"><input name="itens[${idx}][observacao]" class="form-control" placeholder="Obs."></div>
              <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 btnRemove"><i class="ti ti-trash"></i></button></div>`;
            wrap.appendChild(row);
          }
        } else {
          const row = wrap.querySelector(`.item-row[data-tipo-id="${tid}"]`);
          if (row) {
            row.remove();
            if (wrap.querySelectorAll('.item-row').length === 0) btnAdd.click();
          }
        }
      });
    });
  }

  // TomSelect para escola se existir (GLPI já tem Select2, mas mantemos)
  const escolaSelect = document.querySelector('select[name="plugin_protocolo_escolas_id"]');
  if (escolaSelect && window.TomSelect) {
    new TomSelect(escolaSelect, {create:false, sortField:{field:"text", direction:"asc"}, maxOptions:100});
  }

});

// ---- Toggle Tema Claro/Escuro (padronizado com assetmgrstatus: amToggleTheme) ----
var _ptThemeKey = 'pt_theme';
var _ptIsDark = false;

function _ptApplyTheme(dark) {
  _ptIsDark = dark;
  var body = document.body;
  if (!body) return;
  if (dark) {
    body.classList.add('pt-dark-mode');
  } else {
    body.classList.remove('pt-dark-mode');
  }
  var btn = document.getElementById('pt-theme-btn');
  if (btn) btn.innerHTML = dark ? '<i class="ti ti-sun"></i>' : '<i class="ti ti-moon"></i>';
}

function _ptInitTheme() {
  try {
    var saved = localStorage.getItem(_ptThemeKey);
    // Padrão sempre claro — dark só se explicitamente escolhido (igual assetmgrstatus)
    _ptApplyTheme(saved === 'dark');
  } catch (e) { _ptApplyTheme(false); }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', _ptInitTheme);
} else {
  _ptInitTheme();
}

window.ptToggleTheme = function() {
  var newDark = !_ptIsDark;
  try { localStorage.setItem(_ptThemeKey, newDark ? 'dark' : 'light'); } catch (e) {}
  _ptApplyTheme(newDark);
};

// ---- Helper genérico para blocos de filtro colapsáveis (dashboard/pasta) ----
window.ptToggleFilter = function(contentId, btnId, textId, iconId) {
  var c = document.getElementById(contentId);
  var b = btnId ? document.getElementById(btnId) : null;
  var t = textId ? document.getElementById(textId) : null;
  var ic = iconId ? document.getElementById(iconId) : null;
  if (!c) return;
  var isHidden = c.style.display === 'none' || c.classList.contains('collapsed');
  if (isHidden) {
    c.style.display = 'block'; c.classList.remove('collapsed'); c.classList.add('expanded');
    if (b) b.classList.add('active');
    if (t) t.textContent = 'Recolher';
    if (ic) { ic.classList.remove('ti-chevron-down'); ic.classList.add('ti-chevron-up'); }
  } else {
    c.style.display = 'none'; c.classList.add('collapsed'); c.classList.remove('expanded');
    if (b) b.classList.remove('active');
    if (t) t.textContent = 'Expandir';
    if (ic) { ic.classList.remove('ti-chevron-up'); ic.classList.add('ti-chevron-down'); }
  }
};

// ---- Janela flutuante Registrar Entrada (estilo modal de transferência) ----
function ptPluginBase() {
  var m = window.location.pathname.match(/^(.*\/(?:plugins|marketplace)\/protocolo)/);
  return m ? m[1] : '/plugins/protocolo';
}
function ptDoOpenRegisterModal() {
  var m = document.getElementById('pt-register-overlay');
  if (!m) return;
  m.classList.add('open');
  document.body.style.overflow = 'hidden';
}
window.ptOpenRegisterModal = function(ev) {
  // Pre-checagem SEMPRE (com ou sem modal na página): valida a permissão da
  // SESSÃO ATUAL antes de qualquer coisa. Sem direito, explica em popup em
  // vez de deixar navegar para um 403 feio ou preencher o form à toa.
  if (ev) ev.preventDefault();
  var linkHref = (ev && ev.currentTarget && ev.currentTarget.href) ? ev.currentTarget.href : null;
  function goFallback() {
    var m = document.getElementById('pt-register-overlay');
    if (m) { ptDoOpenRegisterModal(); return; }
    if (linkHref) window.location.href = linkHref;
  }
  function noPerm(d) {
    var who = d ? (' (usuário ' + d.uid + ", perfil ativo '" + d.pname + "' #" + d.pid + ')') : '';
    ptShowMsgPopup('error', 'Sem permissão',
      'Sua sessão atual não tem direito de Registrar Entrada' + who + '.\n\nSe a aba Perfis mostra Usar = Sim para outro perfil, é esse o problema: vale o perfil ATIVO (barra de cima).\n\nRecarregue a página (F5). Se persistir, confira em Administração > Perfis > esse perfil > aba Protocolo > Efetivo precisa dizer PODE (e entre de novo no GLPI).');
  }
  try {
    fetch(ptPluginBase() + '/ajax/can.php', {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d && d.canCreate) { goFallback(); return; }
        noPerm(d);
      })
      .catch(function(){ goFallback(); }); // sem resposta: segue o fluxo antigo e deixa o envio decidir
  } catch (e) { goFallback(); }
  return false;
};
window.ptCloseRegisterModal = function(ev) {
  // fecha no ESC, no botão X/Fechar ou clicando no fundo escuro
  if (ev && ev.target && ev.target.id !== 'pt-register-overlay' && !(ev.target.closest && ev.target.closest('.pt-modal-close'))) return;
  var m = document.getElementById('pt-register-overlay');
  if (m) m.classList.remove('open');
  document.body.style.overflow = '';
  return false;
};
document.addEventListener('keydown', function(e){
  if (e.key !== 'Escape' && e.key !== 'Esc') return;
  // Só age quando a janela Registrar Entrada existe na página
  if (!document.getElementById('pt-register-overlay')) return;
  var msg = document.getElementById('pt-msg-overlay');
  if (msg) { msg.remove(); return; }
  // Combo select2 aberta? Fecha ela e NÃO fecha a janela
  try {
    if (window.jQuery && window.jQuery('.select2-container--open').length) {
      window.jQuery('select').each(function(){
        try { var s = window.jQuery(this); if (s.data('select2') && s.select2('isOpen')) s.select2('close'); } catch (err) {}
      });
      e.preventDefault();
      if (e.stopImmediatePropagation) e.stopImmediatePropagation();
      return;
    }
  } catch (err) {}
  // Select nativo com foco (pode estar aberto): 1º ESC fecha o select, 2º fecha a janela
  var ae = document.activeElement;
  if (ae && ae.tagName === 'SELECT' && !window.__ptEscArmed) {
    window.__ptEscArmed = true;
    setTimeout(function(){ window.__ptEscArmed = false; }, 1500);
    return;
  }
  window.__ptEscArmed = false;
  window.ptCloseRegisterModal();
});

// Recarrega se a página voltar do cache do navegador (botão Voltar):
// evita tela velha com sessão/perfil trocados (só nas telas do Protocolo)
window.addEventListener('pageshow', function(e){
  if (e.persisted && (document.getElementById('pt-register-overlay') || document.getElementById('plugin_protocolo_pasta_form'))) {
    window.location.reload();
  }
});

// ---- Envio do Registrar Entrada via AJAX (fica na tela; erro vira popup) ----
window.ptSubmitRegisterAjax = function(form) {
  var btn = form.querySelector('button[type="submit"][name="add"]');
  var origHtml = btn ? btn.innerHTML : '';
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ti ti-loader"></i> Registrando...'; }
  function restore(){ if (btn) { btn.disabled = false; btn.innerHTML = origHtml; } }
  function sessionDead() {
    restore();
    ptShowMsgPopup('error', 'Sessão expirada',
      'Sua sessão expirou (ou o perfil mudou) enquanto você preenchia.\n\nNÃO FECHE esta janela: abra o GLPI em outra aba, entre de novo, volte aqui e clique em Registrar novamente — os dados continuam preenchidos.');
  }
  // Revalida a sessão/permissão na hora do envio (o form pode ficar aberto por muito tempo)
  var fd = new FormData(form);
  if (!fd.has('add')) fd.append('add', '1'); // FormData não inclui o botão de submit
  if (!fd.has('ajax')) fd.append('ajax', '1'); // flag robusta (header pode ser removido por proxy)
  // GLPI 11 (CheckCsrfListener): para AJAX (X-Requested-With) o token CSRF vai
  // no header X-Glpi-Csrf-Token — o _glpi_csrf_token do body é IGNORADO nesse caso.
  var csrfTok = '';
  try {
    var csrfInp = form.querySelector('input[name="_glpi_csrf_token"]');
    if (csrfInp && csrfInp.value) csrfTok = csrfInp.value;
    else if (fd.has('_glpi_csrf_token')) csrfTok = fd.get('_glpi_csrf_token');
  } catch (e) {}
  fetch(ptPluginBase() + '/ajax/can.php', {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d || !d.canCreate) { sessionDead(); return; }
      fetch(form.action, {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest', 'X-Glpi-Csrf-Token': csrfTok}})
    .then(function(resp){
      return resp.text().then(function(text){ return {resp: resp, text: text}; });
    })
    .then(function(out){
      // Tenta JSON primeiro (mesmo em 403: nosso PHP devolve JSON com o motivo).
      var data403 = null;
      if (out.resp.status === 403) {
        try {
          var raw403 = (out.text || '').replace(/^\s+/, '');
          var s403 = raw403.indexOf('{');
          if (s403 > 0) raw403 = raw403.substring(s403);
          data403 = JSON.parse(raw403);
        } catch (e) { data403 = null; }
        if (data403 && typeof data403.ok !== 'undefined' && !data403.ok) {
          restore();
          var errs403 = (data403.errors && data403.errors.length) ? data403.errors.join('\n') : 'Verifique os campos e tente novamente.';
          if (data403.code) errs403 += '\n\nCódigo: ' + data403.code;
          var title403 = data403.code === 'SESSION_DEAD' ? 'Sessão expirada' : 'Não foi possível registrar';
          ptShowMsgPopup('error', title403, errs403);
          return;
        }
        restore();
        var bodyTxt = '';
        try { bodyTxt = ptExtractServerErrors(out.text); } catch (e) { bodyTxt = ''; }
        if (bodyTxt && bodyTxt !== 'Verifique os campos e tente novamente.') {
          ptShowMsgPopup('error', 'Não foi possível registrar', bodyTxt + '\n\nCódigo: HTTP_403');
        } else {
          ptShowMsgPopup('error', 'Sessão desatualizada',
            'O servidor recusou o envio (erro 403) sem detalhar o motivo.\n\nIsso acontece quando a sessão expirou ou o perfil/entidade mudou depois que esta tela foi aberta.\n\nRecarregue a página (F5) e tente novamente. Se persistir, confira em Administração > Perfis > (seu perfil) > aba Protocolo > Usar = Sim (Efetivo precisa dizer PODE) e entre de novo no GLPI.');
        }
        return;
      }
      var data = null;
      try {
        var raw = (out.text || '').replace(/^\s+/, '');
        var s = raw.indexOf('{');
        if (s > 0) raw = raw.substring(s); // descarta BOM/avisos PHP antes do JSON
        data = JSON.parse(raw);
      } catch (e) { data = null; }
      if (data && typeof data.ok !== 'undefined') {
        if (data.ok) {
          restore();
          var termoUrl = ptPluginBase() + '/front/termo.php?id=' + encodeURIComponent(data.id) + '&tipo=recebimento';
          var ov = document.createElement('div');
          ov.id = 'pt-msg-overlay';
          ov.className = 'pt-modal-overlay open';
          ov.innerHTML = '<div class="pt-modal" style="max-width:440px;" role="alertdialog" aria-modal="true">'
            + '<div class="pt-modal-header"><div class="pt-modal-title"><i class="ti ti-check"></i><span>Pasta registrada!</span></div></div>'
            + '<div class="pt-modal-body"><p style="margin:0;font-size:.9rem;">O termo de entrada foi gerado.' + (data.id ? ' Imprima a via de quem está deixando o item.' : '') + '</p></div>'
            + '<div style="padding:12px 24px;border-top:1px solid #f0f2f8;display:flex;gap:8px;justify-content:flex-end;background:#fafbff;border-radius:0 0 20px 20px;">'
            + '<a class="pt-btn pt-btn-green pt-btn-sm" target="_blank" href="' + termoUrl + '"><i class="ti ti-printer"></i> Imprimir termo</a>'
            + '<a class="pt-btn pt-btn-primary pt-btn-sm" href="' + data.url + '">Ver ficha</a></div></div>';
          document.body.appendChild(ov);
          return;
        }
        restore();
        var errs = (data.errors && data.errors.length) ? data.errors.join('\n') : 'Verifique os campos e tente novamente.';
        if (data.code) errs += '\n\nCódigo: ' + data.code;
        ptShowMsgPopup('error', 'Não foi possível registrar', errs);
        return;
      }
      // fallback legado (resposta HTML em vez de JSON)
      var url = out.resp.url || '';
      if (out.resp.redirected || /pasta\.form\.php[^?]*\?id=\d+/.test(url)) { window.location.href = url; return; }
      restore();
      ptShowMsgPopup('error', 'Não foi possível registrar', ptExtractServerErrors(out.text));
    })
    .catch(function(){
      restore();
      ptShowMsgPopup('error', 'Falha de conexão', 'Não foi possível falar com o servidor. Tente novamente.');
    });
    })
    .catch(function(){ sessionDead(); });
  return false;
};

function ptExtractServerErrors(html) {
  try {
    var doc = new DOMParser().parseFromString(html, 'text/html');
    var found = [];
    doc.querySelectorAll('.alert-danger, .text-bg-danger, .toast-error, .toast.bg-danger, div[role="alert"].alert-danger').forEach(function(el){
      var t = ((el.innerText || el.textContent) || '').trim().replace(/\s+/g, ' ');
      if (t && found.indexOf(t) === -1) found.push(t);
    });
    if (!found.length) {
      var h = doc.querySelector('.error, .ui-error, .messages.error');
      if (h) {
        var t2 = (h.textContent || '').trim().replace(/\s+/g, ' ');
        if (t2) found.push(t2);
      }
    }
    if (!found.length) found.push('Verifique os campos e tente novamente.');
    return found.join('\n');
  } catch (e) { return 'Verifique os campos e tente novamente.'; }
}

// ---- Ficha da pasta: visualização travada, edição via botão Editar ----
function ptSetPastaViewOnly(disabled) {
  var form = document.getElementById('plugin_protocolo_pasta_form');
  if (!form || !form.hasAttribute('data-viewonly')) return;
  var els = form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea');
  // via jQuery o Select2 (dropdowns de escola) trava/destrava o widget junto
  if (window.jQuery) { window.jQuery(els).prop('disabled', disabled); }
  else { els.forEach(function(el){ el.disabled = disabled; }); }
}
window.ptTogglePastaEdit = function(on) {
  ptSetPastaViewOnly(!on);
  var editBtn = document.getElementById('pt-pasta-edit-btn');
  var saveBtn = document.getElementById('pt-pasta-save-btn');
  var cancelBtn = document.getElementById('pt-pasta-canceledit-btn');
  if (editBtn) editBtn.style.display = on ? 'none' : '';
  if (saveBtn) saveBtn.style.display = on ? '' : 'none';
  if (cancelBtn) cancelBtn.style.display = on ? '' : 'none';
};
document.addEventListener('DOMContentLoaded', function(){ ptSetPastaViewOnly(true); });

// ---- Popup genérico de mensagem (erro/sucesso) sobre a tela atual ----
window.ptShowMsgPopup = function(type, title, msg) {
  var old = document.getElementById('pt-msg-overlay');
  if (old) old.remove();
  var isErr = type !== 'success';
  var ov = document.createElement('div');
  ov.id = 'pt-msg-overlay';
  ov.className = 'pt-modal-overlay open';
  ov.innerHTML = '<div class="pt-modal" style="max-width:440px;" onclick="event.stopPropagation()" role="alertdialog" aria-modal="true">'
    + '<div class="pt-modal-header"' + (isErr ? ' style="background:linear-gradient(135deg,#dc2626,#991b1b);"' : '') + '>'
    + '<div class="pt-modal-title"><i class="ti ' + (isErr ? 'ti-alert-triangle' : 'ti-check') + '"></i><span></span></div>'
    + '<button type="button" class="pt-modal-close" aria-label="Fechar"><i class="ti ti-x"></i></button></div>'
    + '<div class="pt-modal-body"><p style="white-space:pre-line;margin:0;font-size:.9rem;"></p></div>'
    + '<div style="padding:12px 24px;border-top:1px solid #f0f2f8;display:flex;justify-content:flex-end;background:#fafbff;border-radius:0 0 20px 20px;"><button type="button" class="pt-btn pt-btn-primary pt-btn-sm">Entendi</button></div></div>';
  ov.querySelector('.pt-modal-title span').textContent = title;
  ov.querySelector('.pt-modal-body p').textContent = msg;
  function close(){ ov.remove(); }
  ov.addEventListener('click', close);
  ov.querySelector('.pt-modal-close').addEventListener('click', close);
  ov.querySelector('.pt-btn').addEventListener('click', close);
  document.body.appendChild(ov);
};

// ---- Abas Resumo/Dashboards sem reload (transição fluida, sem piscar) ----
window.ptDashTab = function(ev, tab) {
  if (ev) ev.preventDefault();
  var panes = {resumo: 'tab-resumo', dashboards: 'tab-dashboards'};
  if (!panes[tab]) return false;
  Object.keys(panes).forEach(function(k){
    var p = document.getElementById(panes[k]);
    if (p) p.classList.remove('show', 'active');
  });
  document.querySelectorAll('#protocoloDashTabs .nav-link').forEach(function(a){
    a.classList.remove('active');
    a.removeAttribute('aria-selected');
  });
  var pane = document.getElementById(panes[tab]);
  if (pane) {
    pane.classList.add('active');
    // adiciona .show no próximo frame para a transição de fade acontecer
    requestAnimationFrame(function(){
      requestAnimationFrame(function(){ pane.classList.add('show'); });
    });
  }
  var link = document.querySelector('#protocoloDashTabs .nav-link[data-pt-tab="' + tab + '"]');
  if (link) { link.classList.add('active'); link.setAttribute('aria-selected', 'true'); }
  try {
    var u = new URL(window.location.href);
    u.searchParams.set('tab', tab);
    history.replaceState(null, '', u.toString());
  } catch (e) {}
  if (tab === 'dashboards') {
    setTimeout(function(){
      if (typeof window.ptInitProtocoloCharts === 'function') window.ptInitProtocoloCharts();
    }, 60);
  }
  return false;
};

window.__ptRetIds = [];
window.__ptRetDrawn = false;
function ptRetSelected(){
  var out = [];
  document.querySelectorAll('.pt-ret-check:checked').forEach(function(cb){ out.push({id: parseInt(cb.value, 10), codigo: cb.getAttribute('data-codigo') || ('#' + cb.value)}); });
  return out;
}
function ptRetRefreshBar(){
  var sel = ptRetSelected();
  var bar = document.getElementById('pt-ret-bulkbar');
  var count = document.getElementById('pt-ret-bulkcount');
  var all = document.getElementById('pt-ret-check-all');
  if (bar) bar.style.display = sel.length ? 'flex' : 'none';
  if (count) count.textContent = sel.length + ' selecionada(s)';
  if (all) {
    var boxes = document.querySelectorAll('.pt-ret-check');
    all.checked = boxes.length > 0 && sel.length === boxes.length;
  }
}
window.ptRetClearSelection = function(){
  document.querySelectorAll('.pt-ret-check:checked').forEach(function(cb){ cb.checked = false; });
  ptRetRefreshBar();
};
window.ptOpenRetiradaBulk = function(){
  var sel = ptRetSelected();
  if (!sel.length) return;
  ptOpenRetiradaModal(sel.map(function(s){ return s.id; }));
};
window.ptOpenRetiradaModal = function(ids){
  if (!ids || !ids.length) return;
  window.__ptRetIds = ids.map(function(v){ return parseInt(v, 10); });
  var ov = document.getElementById('pt-retirada-overlay');
  if (!ov) return;
  var list = document.getElementById('pt-ret-list');
  if (list) {
    var names = [];
    document.querySelectorAll('.pt-ret-check:checked').forEach(function(cb){ names.push(cb.getAttribute('data-codigo') || ''); });
    if (!names.length) names = ids.map(function(v){ return 'Pasta #' + v; });
    list.innerHTML = '<strong>' + window.__ptRetIds.length + ' pasta(s):</strong> ' + names.join(', ');
  }
  var nm = document.getElementById('pt-ret-nome');
  if (nm) nm.value = '';
  var dc = document.getElementById('pt-ret-doc');
  if (dc) dc.value = '';
  var dd = document.getElementById('pt-ret-doc-display');
  if (dd) { dd.textContent = 'Toque nos números'; dd.style.color = '#9ca3af'; }
  var ob = document.getElementById('pt-ret-obs');
  if (ob) ob.value = '';
  window.__ptRetDocType = 'cpf';
  ptRetDocType('cpf', true);
  ptRetClearCanvas();
  ptRWizShow(1);
  ov.classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(ptRetFitCanvas, 60);
  if (nm) nm.focus();
};
window.ptCloseRetiradaModal = function(){
  var ov = document.getElementById('pt-retirada-overlay');
  if (ov) ov.classList.remove('open');
  document.body.style.overflow = '';
};
window.__ptRetWiz = 1;
window.__ptRetTermos = [];
window.ptRWizShow = function(n){
  window.__ptRetWiz = n;
  [1, 2, 3, 4, 5].forEach(function(i){
    var p = document.getElementById('pt-ret-w' + i);
    if (p) p.style.display = (i === n) ? '' : 'none';
  });
  var t = document.getElementById('pt-ret-wiz-title');
  if (t) t.textContent = n === 5 ? 'Retirada concluída' : ('Retirada — Etapa ' + n + ' de 4');
  var bar = document.getElementById('pt-ret-progress');
  if (bar) bar.style.width = n === 5 ? '100%' : (['25%', '50%', '75%', '100%'][n - 1] || '25%');
  if (n === 4) setTimeout(ptRetFitCanvas, 60);
};
window.ptRWizNext = function(cur){
  if (cur === 1) {
    var nm = document.getElementById('pt-ret-nome');
    if (!nm || !nm.value.trim()) { if (nm) nm.focus(); return; }
    ptRWizShow(2);
  } else if (cur === 3) {
    ptRWizShow(4);
  }
};
window.ptRetChooseDoc = function(t){
  window.__ptRetDocType = (t === 'rg') ? 'rg' : 'cpf';
  var badge = document.getElementById('pt-ret-doc-badge');
  if (badge) badge.textContent = window.__ptRetDocType.toUpperCase();
  ptRetClearDoc();
  ptRWizShow(3);
};
window.ptRetDocType = function(t, silent){
  window.__ptRetDocType = (t === 'rg') ? 'rg' : 'cpf';
  var badge = document.getElementById('pt-ret-doc-badge');
  if (badge) badge.textContent = window.__ptRetDocType.toUpperCase();
};
window.ptRetPrintTermos = function(){
  (window.__ptRetTermos || []).forEach(function(t){ try { window.open(t.url, '_blank'); } catch (e) {} });
};
window.ptRetPress = function(d){
  var h = document.getElementById('pt-ret-doc');
  if (!h) return;
  var t = window.__ptRetDocType || 'cpf';
  var max = (t === 'rg') ? 9 : 11;
  var v = (h.value || '').replace(/\D/g, '');
  if (d === 'del') v = v.slice(0, -1);
  else if (/^[0-9]$/.test(d) && v.length < max) v += d;
  h.value = v;
  var disp = document.getElementById('pt-ret-doc-display');
  if (disp) { disp.textContent = v || 'Toque nos números'; disp.style.color = v ? '#1e1b4b' : '#9ca3af'; }
};
window.ptRetClearDoc = function(){
  var h = document.getElementById('pt-ret-doc');
  if (h) h.value = '';
  var disp = document.getElementById('pt-ret-doc-display');
  if (disp) { disp.textContent = 'Toque nos números'; disp.style.color = '#9ca3af'; }
};
function ptRetFitCanvas(){
  var c = document.getElementById('pt-ret-canvas');
  if (!c) return;
  var r = c.getBoundingClientRect();
  var w = Math.max(280, Math.floor(r.width));
  c.width = w;
  c.height = 200;
  window.__ptRetDrawn = false;
}
window.ptRetClearCanvas = function(){
  var c = document.getElementById('pt-ret-canvas');
  if (!c) return;
  ptRetFitCanvas();
  var ctx = c.getContext('2d');
  ctx.clearRect(0, 0, c.width, c.height);
  window.__ptRetDrawn = false;
};
function ptRetBindCanvas(){
  var c = document.getElementById('pt-ret-canvas');
  if (!c || c.dataset.bound) return;
  c.dataset.bound = '1';
  var ctx = c.getContext('2d');
  ctx.lineWidth = 2.5;
  ctx.lineCap = 'round';
  ctx.strokeStyle = '#111827';
  var drawing = false;
  function pos(ev){
    var r = c.getBoundingClientRect();
    var x, y;
    if (ev.touches && ev.touches.length) { x = ev.touches[0].clientX; y = ev.touches[0].clientY; }
    else { x = ev.clientX; y = ev.clientY; }
    var sx = c.width / r.width;
    var sy = c.height / r.height;
    return [(x - r.left) * sx, (y - r.top) * sy];
  }
  function start(ev){ drawing = true; var p = pos(ev); ctx.beginPath(); ctx.moveTo(p[0], p[1]); if (ev.preventDefault) ev.preventDefault(); }
  function move(ev){ if (!drawing) return; var p = pos(ev); ctx.lineTo(p[0], p[1]); ctx.stroke(); window.__ptRetDrawn = true; if (ev.preventDefault) ev.preventDefault(); }
  function end(){ drawing = false; }
  c.addEventListener('mousedown', start);
  c.addEventListener('mousemove', move);
  document.addEventListener('mouseup', end);
  c.addEventListener('touchstart', start, {passive: false});
  c.addEventListener('touchmove', move, {passive: false});
  c.addEventListener('touchend', end);
}
window.ptRetErr = function(msg){
  var e = document.getElementById('pt-ret-err');
  if (e) { e.textContent = msg; e.style.display = 'block'; }
  try { console.error('[protocolo] retirada: ' + msg); } catch (ex) {}
};
window.ptSubmitRetirada = function(){
  var err0 = document.getElementById('pt-ret-err');
  if (err0) err0.style.display = 'none';
  var nomeEl = document.getElementById('pt-ret-nome');
  var nome = nomeEl ? nomeEl.value.trim() : '';
  if (!nome) { window.ptRetErr('Informe o nome de quem retira.'); if (nomeEl) nomeEl.focus(); return; }
  if (!window.__ptRetDrawn) { window.ptRetErr('Faça a assinatura no quadro.'); return; }
  var docEl = document.getElementById('pt-ret-doc');
  var obsEl = document.getElementById('pt-ret-obs');
  var csrfEl = document.getElementById('pt-ret-csrf');
  var c = document.getElementById('pt-ret-canvas');
  var img = '';
  try { img = c.toDataURL('image/png'); } catch (e) { return; }
  var btn = document.getElementById('pt-ret-confirm');
  if (btn) { btn.disabled = true; }
  var base = (typeof ptPluginBase === 'function') ? ptPluginBase() : '/plugins/protocolo';
  var csrfHd = csrfEl ? csrfEl.value : '';
  fetch(base + '/ajax/retirada_save.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Glpi-Csrf-Token': csrfHd},
    body: JSON.stringify({
      ids: window.__ptRetIds,
      nome: nome,
      doc_tipo: window.__ptRetDocType || 'cpf',
      doc: docEl ? docEl.value : '',
      obs: obsEl ? obsEl.value : '',
      image: img,
      _glpi_csrf_token: csrfEl ? csrfEl.value : ''
    })
  }).then(function(r){ return r.json(); })
    .then(function(d){
      if (btn) { btn.disabled = false; }
      if (d && d.ok) {
        window.__ptRetTermos = d.termos || [];
        var info = document.getElementById('pt-ret-w5-info');
        if (info) info.textContent = d.done + ' pasta(s) retirada(s) com 1 assinatura. Os termos abrirão para impressão.';
        ptRWizShow(5);
        (window.__ptRetTermos || []).forEach(function(t){ try { window.open(t.url, '_blank'); } catch (e) {} });
      } else {
        window.ptRetErr((d && d.error) || 'Falha ao registrar. Tente de novo.');
      }
    })
    .catch(function(){ if (btn) { btn.disabled = false; } window.ptRetErr('Falha de conexão. Tente de novo.'); });
};
document.addEventListener('change', function(e){
  if (e.target && e.target.id === 'pt-ret-check-all') {
    var on = e.target.checked;
    document.querySelectorAll('.pt-ret-check').forEach(function(cb){ cb.checked = on; });
    ptRetRefreshBar();
  } else if (e.target && e.target.classList && e.target.classList.contains('pt-ret-check')) {
    ptRetRefreshBar();
  }
});
window.ptSetInvView = function(v){
  if (v !== 'grid' && v !== 'list') return;
  var tw = document.getElementById('pt-aguard-table');
  var cw = document.getElementById('pt-aguard-cards');
  if (tw) tw.style.display = (v === 'list') ? '' : 'none';
  if (cw) {
    cw.style.display = (v === 'grid') ? 'grid' : 'none';
    if (v === 'grid') {
      cw.style.gridTemplateColumns = 'repeat(auto-fill,minmax(300px,1fr))';
      cw.style.gap = '10px';
      cw.style.padding = '4px 12px 12px';
    }
  }
  document.querySelectorAll('.pt-view-btn').forEach(function(b){
    if (b.getAttribute('data-v') === v) b.classList.add('on');
    else b.classList.remove('on');
  });
  try { localStorage.setItem('pt_inv_view', v); } catch (e) {}
  try {
    var base = (typeof ptPluginBase === 'function') ? ptPluginBase() : '/plugins/protocolo';
    fetch(base + '/ajax/view.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
      body: JSON.stringify({view: v})
    }).catch(function(){});
  } catch (e) {}
};
document.addEventListener('DOMContentLoaded', function(){
  ptRetBindCanvas();
  ptRetRefreshBar();
  var cur = null;
  var tw = document.getElementById('pt-aguard-table');
  if (tw) cur = (tw.style.display === 'none') ? 'grid' : 'list';
  if (!cur) {
    try { cur = localStorage.getItem('pt_inv_view'); } catch (e) {}
  }
  if (cur !== 'grid' && cur !== 'list') cur = 'grid';
  document.querySelectorAll('.pt-view-btn').forEach(function(b){
    if (b.getAttribute('data-v') === cur) b.classList.add('on');
  });
});

(function(){
  try {
    if (window.__ptVersionWatch) return;
    window.__ptVersionWatch = true;
    if (window.location.pathname.indexOf('/protocolo/') === -1) return;
    var base = (typeof ptPluginBase === 'function') ? ptPluginBase() : '/plugins/protocolo';
    var baseline = null;
    var shown = false;
    function showUpdateModal(){
      if (shown || document.getElementById('pt-update-overlay')) return;
      shown = true;
      var ov = document.createElement('div');
      ov.id = 'pt-update-overlay';
      ov.setAttribute('role', 'alertdialog');
      ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.72);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;';
      ov.innerHTML = '<div style="background:#fff;border-radius:16px;max-width:420px;width:100%;padding:28px 24px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4);">'
        + '<div style="width:56px;height:56px;border-radius:50%;background:#16a34a;color:#fff;font-size:1.8rem;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">!</div>'
        + '<div style="font-size:1.1rem;font-weight:800;color:#1e1b4b;margin-bottom:8px;">Nova atualiza&ccedil;&atilde;o dispon&iacute;vel</div>'
        + '<p style="font-size:.88rem;color:#6b7280;margin:0 0 20px;">O sistema foi atualizado. Atualize a p&aacute;gina para continuar.</p>'
        + '<button type="button" id="pt-update-reload" style="background:#16a34a;color:#fff;border:0;border-radius:10px;padding:12px 24px;font-size:.95rem;font-weight:700;cursor:pointer;width:100%;">Atualizar agora</button></div>';
      document.body.appendChild(ov);
      document.getElementById('pt-update-reload').addEventListener('click', function(){ window.location.reload(); });
    }
    function check(){
      try {
        fetch(base + '/ajax/version.php?t=' + Date.now(), {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}, cache: 'no-store'})
          .then(function(r){ return r.json(); })
          .then(function(d){
            if (!d || !d.build) return;
            if (!baseline) { baseline = d.build; return; }
            if (d.build !== baseline) showUpdateModal();
          })
          .catch(function(){});
      } catch (e) {}
    }
    if (document.readyState === 'complete' || document.readyState === 'interactive') setTimeout(check, 5000);
    else document.addEventListener('DOMContentLoaded', function(){ setTimeout(check, 5000); });
    setInterval(check, 60000);
  } catch (e) {}
})();
