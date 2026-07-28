<?php
/**
 * Sistema de Gerenciamento de Usuários Linux/Samba
 * Página para adicionar novo usuário
 */

require_once __DIR__ . '/auth.php';
$base_dir = dirname(dirname(__DIR__)) . '/usuarios';
$pendentes_file = $base_dir . '/usuarios_pendentes.txt';
$criados_file = $base_dir . '/usuarios_criados.txt';
$setores_file = $base_dir . '/setores.conf';

$mensagem = '';
$tipo_mensagem = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $sobrenome = trim($_POST['sobrenome'] ?? '');
    $setor = trim($_POST['setor'] ?? '');
    
    // Validações
    if (empty($nome)) {
        $mensagem = 'Nome é obrigatório!';
        $tipo_mensagem = 'erro';
    } elseif (empty($sobrenome)) {
        $mensagem = 'Sobrenome é obrigatório!';
        $tipo_mensagem = 'erro';
    } elseif (empty($setor)) {
        $mensagem = 'Setor é obrigatório!';
        $tipo_mensagem = 'erro';
    } elseif (!preg_match('/^[a-z0-9_]+$/', $nome)) {
        $mensagem = 'Nome deve conter apenas letras minúsculas, números e underscore!';
        $tipo_mensagem = 'erro';
    } elseif (!preg_match('/^[a-z0-9_]+$/', $sobrenome)) {
        $mensagem = 'Sobrenome deve conter apenas letras minúsculas, números e underscore!';
        $tipo_mensagem = 'erro';
    } else {
        // Gerar login no formato: nome.sobrenome
        $login = strtolower($nome) . '.' . strtolower($sobrenome);
        // Linha a ser salva no formato: login | setor
        $linha_pendente = $login . ' | ' . strtolower($setor);
        
        // Carregar lista de pendentes (formato: login | setor)
        $pendentes = [];
        if (file_exists($pendentes_file) && is_readable($pendentes_file)) {
            $linhas = @file($pendentes_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($linhas !== false && is_array($linhas)) {
                foreach ($linhas as $linha) {
                    $parts = explode(' | ', $linha);
                    if (!empty($parts[0])) {
                        $pendentes[] = trim($parts[0]);
                    }
                }
            }
        }
        
        if (in_array($login, $pendentes)) {
            $mensagem = "Usuário '$login' já está na lista de pendentes!";
            $tipo_mensagem = 'erro';
        } else {
            // Verificar se já existe nos criados
            $criados = [];
            if (file_exists($criados_file) && is_readable($criados_file)) {
                $linhas = @file($criados_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($linhas !== false && is_array($linhas)) {
                    foreach ($linhas as $linha) {
                        $parts = explode(' | ', $linha);
                        if (!empty($parts[0])) {
                            $criados[] = trim($parts[0]);
                        }
                    }
                }
            }
            
            if (in_array($login, $criados)) {
                $mensagem = "Usuário '$login' já foi criado!";
                $tipo_mensagem = 'erro';
            } else {
                // Adicionar à lista de pendentes (login | setor)
                if (file_put_contents($pendentes_file, $linha_pendente . PHP_EOL, FILE_APPEND | LOCK_EX) !== false) {
                    $mensagem = "Usuário '$login' adicionado com sucesso à lista de pendentes! (Setor: " . ucfirst($setor) . ")";
                    $tipo_mensagem = 'sucesso';
                    // Limpar campos
                    $nome = '';
                    $sobrenome = '';
                    $setor = '';
                } else {
                    $mensagem = 'Erro ao salvar usuário. Verifique permissões do arquivo.';
                    $tipo_mensagem = 'erro';
                }
            }
        }
    }
}

// Carregar lista de setores
$setores = [];
if (file_exists($setores_file) && is_readable($setores_file)) {
    $setores = @file($setores_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($setores !== false && is_array($setores)) {
        $setores = array_filter($setores, function($s) { return !empty(trim($s)); });
        $setores = array_map('trim', $setores);
        sort($setores);
    } else {
        $setores = [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Usuário</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/form.css">
</head>
<body>
    <div class="form-container container">
        <div class="card">
            <h1>➕ Adicionar Novo Usuário</h1>
            
            <?php if ($mensagem): ?>
                <div class="mensagem <?php echo $tipo_mensagem; ?>">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome:</label>
                        <input type="text" 
                               id="nome" 
                               name="nome" 
                               value="<?php echo htmlspecialchars($nome ?? ''); ?>" 
                               required 
                               pattern="[a-z0-9_]+"
                               placeholder="Ex: joao"
                               autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="sobrenome">Sobrenome:</label>
                        <input type="text" 
                               id="sobrenome" 
                               name="sobrenome" 
                               value="<?php echo htmlspecialchars($sobrenome ?? ''); ?>" 
                               required 
                               pattern="[a-z0-9_]+"
                               placeholder="Ex: silva">
                    </div>
                </div>
                
                <div class="info" style="margin-top: 0; margin-bottom: 16px;">
                    ⓘ Apenas letras minúsculas. O login será gerado automaticamente como: <strong>nome.sobrenome</strong>
                </div>
                
                <div class="form-group">
                    <label for="setor">Setor (grupo no Linux/Samba):</label>
                    <select id="setor" name="setor" required>
                        <option value="">Selecione um setor</option>
                        <?php foreach ($setores as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>" 
                                    <?php echo (isset($setor) && $setor === $s) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($s)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit">Adicionar Usuário</button>
                    <a href="home.php" class="btn">Voltar</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
