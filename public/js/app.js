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

  // Termo: botão Enviar abre picker se ainda sem arquivo (compartilha flag com inline de Pasta.php)
  if (!window.__protocoloTermoPickerBound) {
    window.__protocoloTermoPickerBound = true;
    // Delegação global para funcionar também em tabs carregadas via AJAX
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.termo-enviar-btn');
      if (!btn) return;
      const form = btn.closest('.termo-upload-form');
      if (!form) return;
      const input = form.querySelector('.termo-arquivo-input');
      if (!input) return;
      if (!input.files || input.files.length === 0) {
        e.preventDefault();
        e.stopPropagation();
        input.click();
      }
      // se já tem arquivo, deixa o submit ocorrer normalmente
    });

    // Feedback visual quando arquivo é selecionado
    document.addEventListener('change', (e) => {
      if (!e.target.classList.contains('termo-arquivo-input')) return;
      const input = e.target;
      const form = input.closest('.termo-upload-form');
      if (!form) return;
      const btn = form.querySelector('.termo-enviar-btn');
      if (!btn) return;
      if (input.files && input.files.length > 0) {
        const nome = input.files[0].name;
        btn.classList.remove('btn-dark');
        btn.classList.add('btn-success');
        btn.title = nome;
        // mostra nome abaixo do input se ainda não houver
        let hint = form.querySelector('.termo-arquivo-hint');
        if (!hint) {
          hint = document.createElement('small');
          hint.className = 'termo-arquivo-hint text-success d-block mt-1';
          input.parentElement.appendChild(hint);
        }
        hint.textContent = 'Selecionado: ' + nome + ' — clique em Enviar novamente para enviar.';
        hint.style.display = '';
      } else {
        btn.classList.add('btn-dark');
        btn.classList.remove('btn-success');
        const hint = form.querySelector('.termo-arquivo-hint');
        if (hint) hint.style.display = 'none';
      }
    });
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
        if (data.ok) { window.location.href = data.url; return; } // sucesso: abre a ficha nova
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
    // cria/redimensiona os gráficos após o pane ficar visível
    setTimeout(function(){
      if (typeof window.ptInitProtocoloCharts === 'function') window.ptInitProtocoloCharts();
    }, 60);
  }
  return false;
};
