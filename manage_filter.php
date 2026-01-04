<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

if (!$is_staff) { echo "<script>window.location.href='index.php';</script>"; exit; }

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_word'])) {
    $word = $conn->real_escape_string(trim(strtolower($_POST['word'])));
    $sev  = $_POST['severity'];
    if (!empty($word)) {
        $conn->query("INSERT INTO rs_banned_words (word, severity) VALUES ('$word', '$sev') ON DUPLICATE KEY UPDATE severity = '$sev'");
        $msg = "<div class='alert alert-success'>Palavra <b>$word</b> adicionada como nível <b>$sev</b>!</div>";
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM rs_banned_words WHERE id = $id");
    $msg = "<div class='alert alert-info'>Termo removido.</div>";
}

// Ao adicionar:
if ($conn->query("INSERT INTO rs_banned_words ...")) {
    logStaffAction($conn, $_SESSION['admin_user'], 'FILTRO_ADICIONAR', "Adicionou a palavra: $word com severidade: $severity");
}

// Ao remover:
if (isset($_GET['delete'])) {
    logStaffAction($conn, $_SESSION['admin_user'], 'FILTRO_REMOVER', "Removeu o ID de palavra: $id");
    $conn->query("DELETE FROM rs_banned_words WHERE id = $id");
}

?>

<div class="row mb-4">
    <div class="col-12">
        <h3><i class="fa-solid fa-filter text-primary"></i> Gerenciar Filtro de Chat</h3>
        <p class="text-muted small">Palavras cadastradas aqui serão marcadas como suspeitas no Monitor de Chat.</p>
        <?= $msg ?>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-primary mb-4">
            <div class="card-header bg-primary text-white fw-bold">Configurar Filtro</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_word" value="true">
                    <div class="mb-3">
                        <label class="small fw-bold">Palavra/IP</label>
                        <input type="text" name="word" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Intensidade</label>
                        <select name="severity" class="form-select">
                            <option value="baixo">Baixa (Mute Curto)</option>
                            <option value="medio">Média (Mute Médio)</option>
                            <option value="alto">Alta (Mute Longo/Perm)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">ADICIONAR</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Palavra</th><th>Intensidade</th><th>Ação</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $res = $conn->query("SELECT * FROM rs_banned_words ORDER BY severity DESC, word ASC");
                        while($r = $res->fetch_assoc()):
                            $color = ($r['severity'] == 'alto') ? 'danger' : (($r['severity'] == 'medio') ? 'warning text-dark' : 'info text-dark');
                        ?>
                        <tr>
                            <td><code><?= htmlspecialchars($r['word']) ?></code></td>
                            <td><span class="badge bg-<?= $color ?>"><?= strtoupper($r['severity']) ?></span></td>
                            <td><a href="?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
