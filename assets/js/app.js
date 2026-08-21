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
      if(e.target.closest('.btnRemove')) e.target.closest('.item-row').remove();
    });
  }
});
