<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$pdo = getPDO();

$escolas = $pdo->query("SELECT id, nome, codigo FROM escolas WHERE ativo=1 ORDER BY nome")->fetchAll();
try { $tiposDisponiveis = $pdo->query("SELECT id, nome FROM tipos_arquivo WHERE ativo=1 ORDER BY nome")->fetchAll(); } catch(PDOException $e) { $tiposDisponiveis = []; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) die('CSRF inválido');

    $escola_id = (int)($_POST['escola_id'] ?? 0);
    $recebido_de = trim($_POST['recebido_de'] ?? '');
    $recebido_documento = trim($_POST['recebido_documento'] ?? '');
    $data_recebimento = trim($_POST['data_recebimento'] ?? '');
    $observacao = trim($_POST['observacao'] ?? '');
    $tipos = $_POST['tipos'] ?? [];
    $itens = $_POST['itens'] ?? [];

    if (!$escola_id || $recebido_de==='') {
        flash('error','Escola e "Recebido de" são obrigatórios.');
        header('Location: pasta_nova.php'); exit;
    }
    // filtra itens vazios
    $itensValidos = [];
    foreach ($itens as $it) {
        $desc = trim($it['descricao'] ?? '');
        if ($desc==='') continue;
        $itensValidos[] = [
            'descricao'=> $desc,
            'quantidade'=> max(1, (int)($it['quantidade'] ?? 1)),
            'observacao'=> trim($it['observacao'] ?? '')
        ];
    }
    if (count($itensValidos)===0) {
        flash('error','Adicione pelo menos 1 item na pasta.');
        header('Location: pasta_nova.php'); exit;
    }
    // valida tipos de arquivo (caixinhas) - só exige se houver tipos cadastrados
    $tiposValidos = array_filter(array_map('intval', (array)$tipos));
    if (!empty($tiposDisponiveis) && count($tiposValidos)===0) {
        flash('error','Selecione pelo menos 1 tipo de arquivo (Quais tipos de arquivos).');
        header('Location: pasta_nova.php'); exit;
    }
    if ($data_recebimento==='') $data_recebimento = date('Y-m-d\TH:i');
    $data_recebimento = str_replace('T',' ',$data_recebimento) . (strlen($data_recebimento)==16?':00':'');

    try{
        $pdo->beginTransaction();
        $codigo = gerarCodigoPasta($pdo);
        $stmt = $pdo->prepare("INSERT INTO pastas (codigo, escola_id, status, data_recebimento, recebido_de, recebido_documento, observacao, criado_por) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$codigo, $escola_id, 'aguardando', $data_recebimento, $recebido_de, $recebido_documento ?: null, $observacao ?: null, $_SESSION['usuario_id']]);
        $pasta_id = (int)$pdo->lastInsertId();
        $stmtItem = $pdo->prepare("INSERT INTO pasta_itens (pasta_id, descricao, quantidade, observacao) VALUES (?,?,?,?)");
        foreach ($itensValidos as $iv) {
            $stmtItem->execute([$pasta_id, $iv['descricao'], $iv['quantidade'], $iv['observacao'] ?: null]);
        }
        // salva tipos marcados
        $stmtTipo = $pdo->prepare("INSERT INTO pasta_tipos (pasta_id, tipo_id) VALUES (?,?)");
        foreach ($tiposValidos as $tid) {
            $stmtTipo->execute([$pasta_id, $tid]);
        }
        // registra termo de recebimento pendente
        $codigoTermo = gerarCodigoTermo('recebimento');
        $pdo->prepare("INSERT INTO termos (pasta_id, tipo, codigo, hash_verificacao, criado_por) VALUES (?,?,?,?,?)")
            ->execute([$pasta_id, 'recebimento', $codigoTermo, bin2hex(random_bytes(16)), $_SESSION['usuario_id']]);

        $pdo->commit();
        flash('success',"Pasta $codigo registrada com sucesso! Agora gere o Termo de Recebimento.");
        header('Location: pasta_view.php?id='.$pasta_id); exit;
    } catch(Exception $e){
        $pdo->rollBack();
        flash('error','Erro ao salvar: '.$e->getMessage());
        header('Location: pasta_nova.php'); exit;
    }
}

$pageTitle='Nova Pasta - Entrada';
include __DIR__.'/includes/header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<h4 class="mb-3"><i class="bi bi-folder-plus"></i> Registrar entrada de pasta</h4>
<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" id="formPasta">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Escola destinatária *</label>
          <select name="escola_id" id="escolaSelect" class="form-select" required placeholder="Digite para buscar...">
            <option value="">Selecione...</option>
            <?php foreach($escolas as $e): ?><option value="<?= (int)$e['id'] ?>"><?= h($e['nome']) ?> <?= $e['codigo']?'('.h($e['codigo']).')':'' ?></option><?php endforeach; ?>
          </select>
          <div class="form-text">Digite para buscar. Se a escola não estiver na lista, cadastre em <a href="escolas.php">Escolas</a>.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Data/hora recebimento</label>
          <input type="datetime-local" name="data_recebimento" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Código (gerado auto)</label>
          <input class="form-control" disabled placeholder="PROT-YYYY-0001">
        </div>
        <div class="col-md-7">
          <label class="form-label">Recebido de (quem deixou a pasta) *</label>
          <input name="recebido_de" class="form-control" required placeholder="Ex: João da Silva - Secretaria de Educação">
        </div>
        <div class="col-md-5">
          <label class="form-label">Documento (CPF/RG)</label>
          <input name="recebido_documento" class="form-control" placeholder="Opcional">
        </div>
        <div class="col-12">
          <label class="form-label">Observação</label>
          <textarea name="observacao" class="form-control" rows="2" placeholder="Observações gerais sobre a pasta..."></textarea>
        </div>
      </div>

      <hr class="my-4">
      <div class="card border-warning mb-4">
        <div class="card-header bg-warning bg-opacity-10"><strong><i class="bi bi-tags"></i> Quais tipos de arquivos *</strong> <small class="text-muted">— marque as caixinhas conforme o conteúdo da pasta</small> <a href="tipos_arquivo.php" target="_blank" class="float-end small">Cadastrar/gerenciar tipos</a></div>
        <div class="card-body">
          <?php if (!$tiposDisponiveis): ?>
            <div class="alert alert-warning mb-0 small">Nenhum tipo cadastrado. <a href="tipos_arquivo.php">Cadastre os tipos</a> primeiro.</div>
          <?php else: ?>
            <div class="row g-2">
              <?php foreach ($tiposDisponiveis as $t): ?>
                <div class="col-md-4 col-sm-6">
                  <div class="form-check">
                    <input class="form-check-input tipo-check" type="checkbox" name="tipos[]" value="<?= (int)$t['id'] ?>" id="tipo<?= (int)$t['id'] ?>" data-nome="<?= h($t['nome']) ?>">
                    <label class="form-check-label" for="tipo<?= (int)$t['id'] ?>"><?= h($t['nome']) ?></label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="form-text mt-2">Selecione pelo menos 1. Os itens abaixo são preenchidos automaticamente — ajuste Qtd/Obs se precisar.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0"><i class="bi bi-list-check"></i> Itens da pasta *</h6>
        <button type="button" id="btnAddItem" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-circle"></i> Adicionar item</button>
      </div>
      <div id="itensWrap">
        <div class="row g-2 mb-2 item-row">
          <div class="col-md-7"><input name="itens[0][descricao]" class="form-control" placeholder="Descrição do item" required></div>
          <div class="col-md-2"><input name="itens[0][quantidade]" type="number" min="1" value="1" class="form-control" placeholder="Qtd"></div>
          <div class="col-md-2"><input name="itens[0][observacao]" class="form-control" placeholder="Obs."></div>
          <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 btnRemove"><i class="bi bi-trash"></i></button></div>
        </div>
      </div>
      <div class="form-text mb-3">Exemplos: "Ofício nº 123/2026", "Processo de matrícula", "Documentos RH" - detalhe o máximo possível para o termo.</div>

      <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-circle"></i> Registrar pasta</button>
        <a href="pastas.php" class="btn btn-light">Cancelar</a>
      </div>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var el = document.getElementById('escolaSelect');
  if(el && window.TomSelect) new TomSelect(el, {create:false, sortField:{field:"text", direction:"asc"}, maxOptions: 100, placeholder: "Digite para buscar..."});
});
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
