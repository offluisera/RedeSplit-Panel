<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// --- SEGURANÇA: Apenas Admin e Master podem acessar ---
if (!$is_admin) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// Adicionar Cargo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_rank'])) {
    $id = $_POST['rank_id'];
    $name = $_POST['display_name'];
    $color = $_POST['color'];
    $prefix = $_POST['prefix'];
    
    // Tratamento do Parent Rank (se for vazio, vira NULL)
    $parent = !empty($_POST['parent_rank']) ? $_POST['parent_rank'] : null;

    $stmt = $conn->prepare("INSERT INTO rs_ranks (rank_id, display_name, color, prefix, parent_rank) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $id, $name, $color, $prefix, $parent);
    
    if($stmt->execute()) {
        // Envia comando para atualizar permissões no servidor
        sendWebCommand($conn, "GLOBAL", "update_perms", "all");
    }
}

// Deletar Cargo
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM rs_ranks WHERE rank_id = ?");
    $stmt->bind_param("s", $_GET['delete']);
    $stmt->execute();
    echo "<script>window.location.href='ranks.php';</script>";
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="fa-solid fa-layer-group"></i> Gerenciar Cargos</h4>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRankModal">
        <i class="fa-solid fa-plus"></i> Novo Cargo
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Herda de (Pai)</th> <th>Prefixo</th>
                    <th>Cor</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $ranks = $conn->query("SELECT * FROM rs_ranks ORDER BY rank_id ASC");
                // Precisamos de um array para o Select do Modal também
                $ranks_array = []; 
                
                while ($r = $ranks->fetch_assoc()):
                    $ranks_array[] = $r; // Salva para usar no modal
                    
                    // Lógica visual do Parent Rank
                    $parent_display = $r['parent_rank'] 
                        ? '<span class="badge bg-secondary"><i class="fa-solid fa-arrow-up"></i> '.$r['parent_rank'].'</span>' 
                        : '<span class="text-muted small">-</span>';
                ?>
                <tr>
                    <td><code><?= $r['rank_id'] ?></code></td>
                    <td><?= $r['display_name'] ?></td>
                    <td><?= $parent_display ?></td>
                    <td><?= str_replace('§', '&', $r['prefix']) ?></td> 
                    <td><span class="badge bg-secondary" style="color:#<?= $r['color'] ?>">&<?= $r['color'] ?></span></td>
                    <td>
                        <a href="permissions.php?rank=<?= $r['rank_id'] ?>" class="btn btn-sm btn-info text-white"><i class="fa-solid fa-lock"></i> Perms</a>
                        <a href="?delete=<?= $r['rank_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza? Isso pode quebrar a herança de outros cargos!')"><i class="fa-solid fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addRankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Cargo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="fw-bold">ID (Sistema)</label>
                        <input type="text" name="rank_id" class="form-control" placeholder="ex: ajudante" required>
                        <small class="text-muted">Use letras minúsculas, sem espaço.</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Nome Exibido</label>
                            <input type="text" name="display_name" class="form-control" placeholder="ex: Ajudante" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Herda de (Opcional)</label>
                            <select name="parent_rank" class="form-select">
                                <option value="">Nenhum (Base)</option>
                                <?php foreach($ranks_array as $rk): ?>
                                    <option value="<?= $rk['rank_id'] ?>"><?= $rk['display_name'] ?> (<?= $rk['rank_id'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold">Prefixo</label>
                        <input type="text" name="prefix" class="form-control" placeholder="ex: &e[Ajudante] ">
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Cor (Código)</label>
                        <input type="text" name="color" class="form-control" placeholder="ex: e" maxlength="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_rank" class="btn btn-primary fw-bold">SALVAR CARGO</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>