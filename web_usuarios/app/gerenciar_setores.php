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
    <style>
        /* Estilos específicos para gerenciamento de setores */
        .grid-setores {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }

        .setor-form-card {
            background: #161b22;
            border: 1px solid #30363d;
            padding: 24px;
            border-radius: 6px;
        }

        .setor-form-card h2 {
            color: #f0f6fc;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #30363d;
            border-bottom-color: #1f6feb;
            font-size: 18px;
            font-weight: 600;
        }

        .setor-list-card {
            background: #161b22;
            border: 1px solid #30363d;
            padding: 24px;
            border-radius: 6px;
        }

        .setor-list-card h2 {
            color: #f0f6fc;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #30363d;
            border-bottom-color: #d29922;
            font-size: 18px;
            font-weight: 600;
        }

        .count-badge {
            display: inline-block;
            background: #21262d;
            border: 1px solid #30363d;
            color: #c9d1d9;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 8px;
        }

        .setor-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            margin-bottom: 8px;
            background: #0d1117;
            border: 1px solid #21262d;
            border-radius: 6px;
            border-left: 3px solid #d29922;
            transition: border-color 0.2s, background-color 0.2s;
        }

        .setor-item:hover {
            background: #161b22;
            border-color: #30363d;
        }

        .setor-item .nome {
            font-weight: 600;
            color: #f0f6fc;
            font-size: 14px;
        }

        .setor-item .acoes {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-remover {
            padding: 4px 12px;
            background: rgba(218, 54, 51, 0.1);
            border: 1px solid rgba(218, 54, 51, 0.3);
            color: #f85149;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s;
            text-decoration: none;
        }

        .btn-remover:hover {
            background: rgba(218, 54, 51, 0.2);
            border-color: #f85149;
            text-decoration: none;
        }

        .vazio-setores {
            text-align: center;
            padding: 48px 24px;
            color: #6e7681;
            font-style: italic;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .grid-setores {
                grid-template-columns: 1fr;
            }
        }

        /* Modal de confirmação */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #161b22;
            border: 1px solid #30363d;
            padding: 24px;
            border-radius: 6px;
            max-width: 400px;
            width: 90%;
        }

        .modal-box h3 {
            color: #f0f6fc;
            margin-bottom: 12px;
            font-size: 16px;
        }

        .modal-box p {
            color: #8b949e;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .modal-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .btn-cancelar {
            padding: 8px 16px;
            background: #21262d;
            border: 1px solid #30363d;
            color: #c9d1d9;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-cancelar:hover {
            background: #30363d;
        }

        .btn-confirmar {
            padding: 8px 16px;
            background: rgba(218, 54, 51, 0.2);
            border: 1px solid #f85149;
            color: #f85149;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-confirmar:hover {
            background: rgba(218, 54, 51, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="list-header">
            <h1>📂 Gerenciar Setores</h1>
            <div class="list-header-actions">
                <span><?php echo htmlspecialchars($_SESSION['usuario'] ?? ''); ?></span>
                <a href="home.php" class="btn">← Voltar</a>
                <a href="../logout.php" class="btn logout">Sair</a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="mensagem <?php echo $tipo_mensagem; ?>" style="margin-bottom: 20px;">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <div class="grid-setores">
            <!-- Card de Adicionar Setor -->
            <div class="setor-form-card">
                <h2>➕ Adicionar Setor</h2>
                <p style="color: #8b949e; font-size: 13px; margin-bottom: 16px;">
                    Os setores são usados como grupos no Linux/Samba e como categorias para organizar os usuários.
                </p>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="setor">Nome do Setor:</label>
                        <input type="text" 
                               id="setor" 
                               name="setor" 
                               required 
                               pattern="[a-z0-9_]+"
                               minlength="2"
                               placeholder="Ex: vendas, rh, ti, financeiro"
                               autofocus>
                        <div class="info" style="margin-top: 8px;">
                            ⓘ Apenas letras minúsculas, números e underscore. Mínimo de 2 caracteres.
                        </div>
                    </div>
                    <button type="submit" style="width: 100%; margin-top: 8px;">Adicionar Setor</button>
                </form>
            </div>

            <!-- Card de Listar Setores -->
            <div class="setor-list-card">
                <h2>
                    📋 Setores
                    <span class="count-badge"><?php echo count($setores); ?></span>
                </h2>

                <?php if (empty($setores)): ?>
                    <div class="vazio-setores">
                        Nenhum setor cadastrado ainda.<br>
                        Adicione setores ao lado para começar.
                    </div>
                <?php else: ?>
                    <div class="info-box">
                        ⓘ Clique em "Remover" para excluir um setor. Usuários já criados com este setor não serão afetados.
                    </div>
                    <div class="lista" style="max-height: 400px;">
                        <?php foreach ($setores as $setor): ?>
                            <div class="setor-item">
                                <span class="nome"><?php echo htmlspecialchars(ucfirst($setor)); ?></span>
                                <div class="acoes">
                                    <a href="#" 
                                       class="btn-remover" 
                                       data-setor="<?php echo htmlspecialchars($setor); ?>"
                                       onclick="confirmarRemocao(event, '<?php echo htmlspecialchars($setor, ENT_QUOTES); ?>')">
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
            <h3>⚠️ Confirmar Remoção</h3>
            <p id="modalMensagem">Tem certeza que deseja remover este setor?</p>
            <div class="modal-actions">
                <button class="btn-cancelar" onclick="fecharModal()">Cancelar</button>
                <a href="#" class="btn-confirmar" id="btnConfirmarRemocao">Remover</a>
            </div>
        </div>
    </div>

    <script>
        function confirmarRemocao(event, setor) {
            event.preventDefault();
            var modal = document.getElementById('modalConfirmacao');
            var mensagem = document.getElementById('modalMensagem');
            var btnConfirmar = document.getElementById('btnConfirmarRemocao');
            
            mensagem.textContent = "Tem certeza que deseja remover o setor '" + setor + "'?";
            btnConfirmar.href = '?remover=' + encodeURIComponent(setor);
            
            modal.classList.add('active');
        }

        function fecharModal() {
            document.getElementById('modalConfirmacao').classList.remove('active');
        }

        // Fechar modal ao clicar fora
        document.getElementById('modalConfirmacao').addEventListener('click', function(event) {
            if (event.target === this) {
                fecharModal();
            }
        });

        // Fechar modal com tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                fecharModal();
            }
        });
    </script>
</body>
</html>
