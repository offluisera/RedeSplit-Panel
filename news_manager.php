<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// --- SEGURANÇA: Apenas Admin e Master ---
// Você pode adicionar 'moderador' aqui se quiser que mods postem avisos
if (!$is_admin) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$msg = "";

// 1. PUBLICAR NOTÍCIA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $type = $conn->real_escape_string($_POST['type']);
    $content = $conn->real_escape_string($_POST['content']);
    $author = $_SESSION['admin_user'];

    $stmt = $conn->prepare("INSERT INTO rs_news (title, content, type, author) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $title, $content, $type, $author);
    
    if ($stmt->execute()) {
        $msg = "<div class='alert alert-success'>Notícia publicada com sucesso!</div>";
        
        // Opcional: Enviar para Discord (se quiser ativar, descomente abaixo)
        /*
        include 'includes/discord.php';
        sendDiscordLog("📢 Nova Notícia: $title", $content, "f1c40f", [
            ["name" => "Tipo", "value" => $type, "inline" => true],
            ["name" => "Autor", "value" => $author, "inline" => true]
        ]);
        */
    } else {
        $msg = "<div class='alert alert-danger'>Erro ao publicar: " . $conn->error . "</div>";
    }
}

// 2. DELETAR NOTÍCIA
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM rs_news WHERE id = $id");
    
    // Solução: Redirecionamento via JavaScript
    echo "<script>window.location.href='news_manager.php';</script>";
    exit;
}



?>

<div class="row mb-4">
    <div class="col-12">
        <h3><i class="fa-solid fa-newspaper text-primary"></i> Gerenciador de Notícias</h3>
        <p class="text-muted">Publique atualizações, avisos de manutenção ou eventos.</p>
        <?= $msg ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card shadow border-primary h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fa-solid fa-pen-to-square"></i> Escrever Post</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="publish" value="true">
                    
                    <div class="mb-3">
                        <label class="fw-bold">Título</label>
                        <input type="text" name="title" class="form-control" placeholder="Ex: Novo Spawn Lançado!" required>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold">Tipo</label>
                        <select name="type" class="form-select">
                            <option value="UPDATE">UPDATE (Atualização)</option>
                            <option value="EVENTO">EVENTO</option>
                            <option value="AVISO">AVISO / ALERTA</option>
                            <option value="MANUTENCAO">MANUTENÇÃO</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold">Conteúdo</label>
                        <textarea name="content" class="form-control" rows="6" placeholder="Descreva as novidades..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold">PUBLICAR AGORA</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Histórico de Postagens</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Título</th>
                            <th>Autor</th>
                            <th class="text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $news_query = $conn->query("SELECT * FROM rs_news ORDER BY id DESC LIMIT 20");
                        if ($news_query->num_rows > 0):
                            while ($n = $news_query->fetch_assoc()):
                                // Cores das Tags
                                $badge_color = 'secondary';
                                if ($n['type'] == 'UPDATE') $badge_color = 'info text-dark';
                                if ($n['type'] == 'EVENTO') $badge_color = 'warning text-dark';
                                if ($n['type'] == 'AVISO') $badge_color = 'danger';
                                if ($n['type'] == 'MANUTENCAO') $badge_color = 'dark';
                        ?>
                        <tr>
                            <td style="font-size: 0.8em"><?= date('d/m/y H:i', strtotime($n['created_at'])) ?></td>
                            <td><span class="badge bg-<?= $badge_color ?>"><?= $n['type'] ?></span></td>
                            <td class="fw-bold text-truncate" style="max-width: 200px;"><?= htmlspecialchars($n['title']) ?></td>
                            <td><small><?= $n['author'] ?></small></td>
                            <td class="text-end">
                                <a href="?delete=<?= $n['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apagar esta notícia permanentemente?')">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Nenhuma notícia postada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>