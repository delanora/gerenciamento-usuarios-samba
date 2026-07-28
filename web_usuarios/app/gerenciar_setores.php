<?php
/**
 * Sistema de Gerenciamento de Usuários Linux/Samba
 * Página para gerenciar setores/grupos
 */

require_once __DIR__ . '/auth.php';
$base_dir = dirname(dirname(__DIR__)) . '/usuarios';
$setores_file = $base_dir . '/setores.conf';

$mensagem = '';
$tipo_mensagem = '';

// Processar exclusão de setor
if (isset($_GET['remover']) && !empty($_GET['remover'])) {
    $remover = trim($_GET['remover']);

    // Carregar setores atuais
    $setores = carregar_setores($setores_file);

    if (in_array($remover, $setores)) {
        // Remover setor da lista
        $setores = array_values(array_filter($setores, function($s) use ($remover) {
            return $s !== $remover;
        }));

        if (salvar_setores($setores_file, $setores)) {
            $mensagem = "Setor '$remover' removido com sucesso!";
            $tipo_mensagem = 'sucesso';
        } else {
            $mensagem = 'Erro ao remover setor. Verifique permissões do arquivo.';
            $tipo_mensagem = 'erro';
        }
    } else {
        $mensagem = "Setor '$remover' não encontrado!";
        $tipo_mensagem = 'erro';
    }
}

// Processar formulário de adição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novo_setor = trim($_POST['setor'] ?? '');

    // Validações
    if (empty($novo_setor)) {
        $mensagem = 'Nome do setor é obrigatório!';
        $tipo_mensagem = 'erro';
    } elseif (!preg_match('/^[a-z0-9_]+$/', $novo_setor)) {
        $mensagem = 'O nome do setor deve conter apenas letras minúsculas, números e underscore!';
        $tipo_mensagem = 'erro';
    } elseif (strlen($novo_setor) < 2) {
        $mensagem = 'O nome do setor deve ter pelo menos 2 caracteres!';
        $tipo_mensagem = 'erro';
    } else {
        // Carregar setores atuais
        $setores = carregar_setores($setores_file);

        if (in_array($novo_setor, $setores)) {
            $mensagem = "O setor '$novo_setor' já existe!";
            $tipo_mensagem = 'erro';
        } else {
            // Adicionar novo setor
            $setores[] = $novo_setor;
            sort($setores);

            if (salvar_setores($setores_file, $setores)) {
                $mensagem = "Setor '$novo_setor' adicionado com sucesso!";
                $tipo_mensagem = 'sucesso';
            } else {
                $mensagem = 'Erro ao salvar setor. Verifique permissões do arquivo.';
                $tipo_mensagem = 'erro';
            }
        }
    }
}

// Carregar setores para exibição
$setores = carregar_setores($setores_file);

/**
 * Carrega a lista de setores do arquivo
 */
function carregar_setores($arquivo) {
    $setores = [];
    if (file_exists($arquivo) && is_readable($arquivo)) {
        $linhas = @file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($linhas !== false && is_array($linhas)) {
            $setores = array_map('trim', $linhas);
            $setores = array_values(array_filter($setores, function($s) {
                return !empty($s) && strpos($s, '#') !== 0;
            }));
            sort($setores);
        }
    }
    return $setores;
}

/**
 * Salva a lista de setores no arquivo
 */
function salvar_setores($arquivo, $setores) {
    sort($setores);
    $conteudo = implode(PHP_EOL, $setores) . PHP_EOL;
    return file_put_contents($arquivo, $conteudo, LOCK_EX) !== false;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Setores</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/form.css">
    <link rel="stylesheet" href="../css/list.css">
    <link rel="stylesheet" href="../css/setores.css">
</head>
<body>
    <div class="container">
        <!-- Navbar -->
        <div class="navbar">
            <a href="home.php" class="navbar-brand">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4095f5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span>Gerenciamento de Usuários</span>
            </a>
            <div class="navbar-nav">
                <span class="navbar-user">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <?php echo htmlspecialchars($_SESSION['usuario'] ?? ''); ?>
                </span>
                <a href="../logout.php" class="btn btn-sm btn-danger">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Sair
                </a>
            </div>
        </div>

        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="home.php">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                Home
            </a>
            <span class="separator">▶</span>
            <span class="current">Gerenciar Setores</span>
        </div>

        <!-- Header -->
        <div class="list-header">
            <h1>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d29922" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    <line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/>
                </svg>
                Gerenciar Setores
            </h1>
            <div class="list-header-actions">
                <a href="home.php" class="btn btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    Home
                </a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="mensagem <?php echo $tipo_mensagem; ?>" style="margin-bottom: 20px;">
                <span class="mensagem-icon">
                    <?php if ($tipo_mensagem === 'sucesso'): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php else: ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <?php endif; ?>
                </span>
                <span><?php echo htmlspecialchars($mensagem); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid-setores">
            <!-- Card de Adicionar Setor -->
            <div class="setor-form-card fade-in fade-in-d1">
                <h2>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3fb950" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        <line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/>
                    </svg>
                    Adicionar Setor
                </h2>
                <p>
                    Os setores são usados como grupos no Linux/Samba e como categorias para organizar os usuários.
                </p>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="setor">Nome do Setor</label>
                        <input type="text"
                               id="setor"
                               name="setor"
                               required
                               pattern="[a-z0-9_]+"
                               minlength="2"
                               placeholder="Ex: vendas, rh, ti, financeiro"
                               autofocus>
                        <div class="info" style="margin-top:8px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span>Apenas letras minúsculas, números e underscore. Mínimo de 2 caracteres.</span>
                        </div>
                    </div>
                    <button type="submit" style="width:100%;margin-top:8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
                        Adicionar Setor
                    </button>
                </form>
            </div>

            <!-- Card de Listar Setores -->
            <div class="setor-list-card fade-in fade-in-d2">
                <h2>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d29922" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/>
                        <line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                    </svg>
                    Setores
                    <span class="count-badge"><?php echo count($setores); ?></span>
                </h2>

                <?php if (empty($setores)): ?>
                    <div class="empty-setores">
                        <div class="empty-state-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        Nenhum setor cadastrado ainda.<br>
                        Adicione setores ao lado para começar.
                    </div>
                <?php else: ?>
                    <div class="info-box">
                        <span class="info-box-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        </span>
                        <span>Usuários já criados com este setor não serão afetados ao removê-lo.</span>
                    </div>
                    <div class="lista-setores">
                        <?php foreach ($setores as $setor): ?>
                            <div class="setor-item">
                                <span class="nome">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d29922" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                    <?php echo htmlspecialchars(ucfirst($setor)); ?>
                                </span>
                                <div class="acoes">
                                    <a href="#"
                                       class="btn-remove-setor"
                                       onclick="confirmarRemocao(event, '<?php echo htmlspecialchars($setor, ENT_QUOTES); ?>')">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        Remover
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div class="modal-overlay" id="modalConfirmacao">
        <div class="modal-box">
            <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d29922" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Confirmar Remoção
            </h3>
            <p id="modalMensagem">Tem certeza que deseja remover este setor?</p>
            <div class="modal-actions">
                <button class="btn" onclick="fecharModal()">Cancelar</button>
                <a href="#" class="btn-confirm-remove" id="btnConfirmarRemocao">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Remover
                </a>
            </div>
        </div>
    </div>

    <script>
        function confirmarRemocao(event, setor) {
            event.preventDefault();
            document.getElementById('modalMensagem').textContent =
                "Tem certeza que deseja remover o setor '" + setor + "'?";
            document.getElementById('btnConfirmarRemocao').href = '?remover=' + encodeURIComponent(setor);
            document.getElementById('modalConfirmacao').classList.add('active');
        }

        function fecharModal() {
            document.getElementById('modalConfirmacao').classList.remove('active');
        }

        document.getElementById('modalConfirmacao').addEventListener('click', function(event) {
            if (event.target === this) fecharModal();
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') fecharModal();
        });
    </script>
</body>
</html>
