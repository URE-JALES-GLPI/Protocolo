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
window.ptOpenRegisterModal = function(ev) {
  var m = document.getElementById('pt-register-overlay');
  if (!m) return true; // sem modal na página: segue o link normalmente
  if (ev) ev.preventDefault();
  m.classList.add('open');
  document.body.style.overflow = 'hidden';
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
  if (e.key === 'Escape') window.ptCloseRegisterModal();
});

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
