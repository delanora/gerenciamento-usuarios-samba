<?php
/**
 * Sistema de Gerenciamento de Usuários Linux/Samba
 * Página para listar usuários pendentes e criados
 */

require_once __DIR__ . '/auth.php';
$base_dir = dirname(dirname(__DIR__)) . '/usuarios';
$pendentes_file = $base_dir . '/usuarios_pendentes.txt';
$criados_file = $base_dir . '/usuarios_criados.txt';

// Carregar usuários pendentes (formato: login | setor)
$pendentes = [];
if (file_exists($pendentes_file) && is_readable($pendentes_file)) {
    $linhas = @file($pendentes_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($linhas !== false && is_array($linhas)) {
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (empty($linha)) continue;

            $parts = explode(' | ', $linha);
            $login = trim($parts[0]);
            $setor = isset($parts[1]) ? trim($parts[1]) : '';

            if (!empty($login)) {
                $pendentes[] = [
                    'login' => $login,
                    'setor' => $setor
                ];
            }
        }
    }
}

// Carregar usuários criados
$criados = [];
if (file_exists($criados_file) && is_readable($criados_file)) {
    $linhas = @file($criados_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($linhas !== false && is_array($linhas)) {
        foreach ($linhas as $linha) {
            $parts = explode(' | ', $linha);
            if (!empty($parts[0])) {
                $login = trim($parts[0]);
                $senha = isset($parts[1]) ? trim($parts[1]) : '';
                $criados[] = [
                    'login' => $login,
                    'senha' => $senha
                ];
            }
        }
    }
}

// Ordenar
usort($pendentes, function($a, $b) {
    return strcmp($a['login'], $b['login']);
});
usort($criados, function($a, $b) {
    return strcmp($a['login'], $b['login']);
});
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listar Usuários</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/list.css">
</head>
<body>
    <div class="container">
        <div class="list-header">
            <h1>📋 Lista de Usuários</h1>
            <div class="list-header-actions">
                <span><?php echo htmlspecialchars($_SESSION['usuario'] ?? ''); ?></span>
                <a href="home.php" class="btn">← Voltar</a>
                <a href="../logout.php" class="btn logout">Sair</a>
            </div>
        </div>
        
        <div class="grid">
            <!-- Usuários Pendentes -->
            <div class="list-card pendentes">
                <h2>
                    ⏳ Pendentes
                    <span class="count"><?php echo count($pendentes); ?></span>
                </h2>
                
                <?php if (empty($pendentes)): ?>
                    <div class="vazio">
                        Nenhum usuário pendente
                    </div>
                <?php else: ?>
                    <div class="info-box">
                        ⓘ Estes usuários aguardam processamento pelo script bash
                    </div>
                    <div class="lista">
                        <?php foreach ($pendentes as $usuario): ?>
                            <div class="item pendente">
                                <div class="item-header"><?php echo htmlspecialchars($usuario['login']); ?></div>
                                <div class="item-detail">
                                    Setor: <span class="setor"><?php echo htmlspecialchars(ucfirst($usuario['setor'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Usuários Criados -->
            <div class="list-card criados">
                <h2>
                    ✅ Criados
                    <span class="count"><?php echo count($criados); ?></span>
                </h2>
                
                <?php if (empty($criados)): ?>
                    <div class="vazio">
                        Nenhum usuário criado ainda
                    </div>
                <?php else: ?>
                    <div class="info-box">
                        ⓘ Usuários já processados e criados no sistema
                    </div>
                    <div class="lista">
                        <?php foreach ($criados as $usuario): ?>
                            <div class="item criado">
                                <div class="item-header"><?php echo htmlspecialchars($usuario['login']); ?></div>
                                <div class="item-detail">
                                    Senha: <span class="senha"><?php echo htmlspecialchars($usuario['senha']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
