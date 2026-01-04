<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// --- AÇÕES (BACKEND) ---

// 1. Adicionar Banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner'])) {
    $img = $conn->real_escape_string($_POST['image']);
    $title = $conn->real_escape_string($_POST['title']);
    $sub = $conn->real_escape_string($_POST['subtitle']);
    $btn_txt = $conn->real_escape_string($_POST['btn_text']);
    $btn_link = $conn->real_escape_string($_POST['btn_link']);
    
    $sql = "INSERT INTO rs_banners (image_url, title, subtitle, btn_text, btn_link) VALUES ('$img', '$title', '$sub', '$btn_txt', '$btn_link')";
    if ($conn->query($sql)) echo "<script>Swal.fire('Sucesso', 'Banner adicionado!', 'success');</script>";
}

// 2. Deletar Banner
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM rs_banners WHERE id=$id");
    echo "<script>window.location.href='admin_banners.php';</script>";
}

// 3. Alternar Status (Ativo/Inativo)
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE rs_banners SET active = NOT active WHERE id=$id");
    echo "<script>window.location.href='admin_banners.php';</script>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="fa-solid fa-images"></i> Gerenciar Banners da Loja</h3>
    <a href="loja.php" class="btn btn-outline-primary" target="_blank"><i class="fa-solid fa-eye"></i> Ver Loja</a>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white fw-bold">Novo Banner</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_banner" value="1">
                    
                    <div class="mb-2">
                        <label class="small fw-bold">URL da Imagem (1200x400 recomendado)</label>
                        <input type="url" name="image" class="form-control" placeholder="https://imgur.com/..." required>
                    </div>

                    <div class="mb-2">
                        <label class="small fw-bold">Título Principal</label>
                        <input type="text" name="title" class="form-control" placeholder="Ex: PROMOÇÃO DE NATAL">
                    </div>

                    <div class="mb-2">
                        <label class="small fw-bold">Subtítulo (Descrição)</label>
                        <textarea name="subtitle" class="form-control" rows="2" placeholder="Ex: Aproveite 20% de bônus..."></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">Texto Botão</label>
                            <input type="text" name="btn_text" class="form-control" placeholder="VER AGORA">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Link Botão</label>
                            <input type="text" name="btn_link" class="form-control" placeholder="?server=RankUP">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 fw-bold">SALVAR BANNER</button>
                </form>
            </div>
        </div>
        
        <div class="alert alert-info small">
            <i class="fa-solid fa-circle-info"></i> Use imagens escuras ou o sistema escurecerá automaticamente para o texto aparecer.
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">Banners Ativos</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="150">Preview</th>
                            <th>Textos</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $banners = $conn->query("SELECT * FROM rs_banners ORDER BY id DESC");
                        if ($banners->num_rows > 0):
                        while($b = $banners->fetch_assoc()):
                            $statusClass = $b['active'] ? 'bg-success' : 'bg-secondary';
                            $statusText = $b['active'] ? 'Ativo' : 'Oculto';
                        ?>
                        <tr>
                            <td>
                                <img src="<?= $b['image_url'] ?>" class="rounded shadow-sm" style="width: 120px; height: 40px; object-fit: cover;">
                            </td>
                            <td>
                                <div class="fw-bold text-truncate" style="max-width: 200px;"><?= $b['title'] ?></div>
                                <div class="small text-muted text-truncate" style="max-width: 200px;"><?= $b['subtitle'] ?></div>
                            </td>
                            <td>
                                <a href="?toggle=<?= $b['id'] ?>" class="badge <?= $statusClass ?> text-decoration-none" title="Clique para alterar">
                                    <?= $statusText ?>
                                </a>
                            </td>
                            <td class="text-end">
                                <a href="?del=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apagar este banner?')">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">Nenhum banner criado. A loja usará o padrão?</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>