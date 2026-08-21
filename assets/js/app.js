// assets/js/app.js
document.addEventListener('DOMContentLoaded', ()=>{
  // Adicionar itens dinamicamente em pasta_nova.php
  const btnAdd = document.getElementById('btnAddItem');
  const wrap = document.getElementById('itensWrap');
  if(btnAdd && wrap){
    btnAdd.addEventListener('click', ()=>{
      const idx = wrap.querySelectorAll('.item-row').length;
      const row = document.createElement('div');
      row.className = 'row g-2 mb-2 item-row';
      row.innerHTML = `
        <div class="col-md-7"><input name="itens[${idx}][descricao]" class="form-control" placeholder="Descrição do item" required></div>
        <div class="col-md-2"><input name="itens[${idx}][quantidade]" type="number" min="1" value="1" class="form-control" placeholder="Qtd"></div>
        <div class="col-md-2"><input name="itens[${idx}][observacao]" class="form-control" placeholder="Obs."></div>
        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 btnRemove"><i class="bi bi-trash"></i></button></div>`;
      wrap.appendChild(row);
    });
    wrap.addEventListener('click', e=>{
      if(e.target.closest('.btnRemove')) {
        const row = e.target.closest('.item-row');
        // se remover linha auto, desmarca a caixinha correspondente
        const tid = row.dataset.tipoId;
        if(tid){
          const chk = document.getElementById('tipo'+tid);
          if(chk) chk.checked = false;
        }
        row.remove();
        if(wrap.querySelectorAll('.item-row').length===0){
          // garante pelo menos uma linha vazia
          btnAdd.click();
        }
      }
    });
  }

  // Sincroniza Tipos -> Itens
  const tipoChecks = document.querySelectorAll('.tipo-check');
  if(tipoChecks.length && wrap){
    tipoChecks.forEach(chk=>{
      chk.addEventListener('change', ()=>{
        const tid = chk.value;
        const nome = chk.dataset.nome || chk.nextElementSibling?.textContent.trim() || 'Item';
        if(chk.checked){
          // evita duplicar
          if(wrap.querySelector(`.item-row[data-tipo-id="${tid}"]`)) return;
          // se só tem 1 linha vazia, reaproveita
          const rows = wrap.querySelectorAll('.item-row');
          let targetRow = null;
          if(rows.length===1){
            const inp = rows[0].querySelector('input[name*="[descricao]"]');
            if(inp && inp.value.trim()==='' && !rows[0].dataset.tipoId) targetRow = rows[0];
          }
          if(targetRow){
            targetRow.dataset.tipoId = tid;
            const inp = targetRow.querySelector('input[name*="[descricao]"]');
            if(inp) inp.value = nome;
            targetRow.classList.add('border','border-warning','rounded','p-1');
          } else {
            const idx = wrap.querySelectorAll('.item-row').length;
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 item-row border border-warning rounded p-1';
            row.dataset.tipoId = tid;
            row.innerHTML = `
              <div class="col-md-7"><input name="itens[${idx}][descricao]" class="form-control" value="${nome.replace(/"/g,'&quot;')}" required></div>
              <div class="col-md-2"><input name="itens[${idx}][quantidade]" type="number" min="1" value="1" class="form-control" placeholder="Qtd"></div>
              <div class="col-md-2"><input name="itens[${idx}][observacao]" class="form-control" placeholder="Obs."></div>
              <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 btnRemove"><i class="bi bi-trash"></i></button></div>`;
            wrap.appendChild(row);
          }
        } else {
          // desmarcou -> remove linha correspondente
          const row = wrap.querySelector(`.item-row[data-tipo-id="${tid}"]`);
          if(row) {
            row.remove();
            if(wrap.querySelectorAll('.item-row').length===0) btnAdd.click();
          }
        }
      });
    });
  }
});
