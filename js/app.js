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
