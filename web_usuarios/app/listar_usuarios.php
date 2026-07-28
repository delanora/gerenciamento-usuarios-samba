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
            <span class="current">Listar Usuários</span>
        </div>

        <!-- Header -->
        <div class="list-header">
            <h1>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">
                    <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
                Lista de Usuários
            </h1>
            <div class="list-header-actions">
                <a href="home.php" class="btn btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    Home
                </a>
            </div>
        </div>

        <div class="grid">
            <!-- Usuários Pendentes -->
            <div class="list-card pendentes fade-in fade-in-d1">
                <h2>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d29922" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    Pendentes
                    <span class="count"><?php echo count($pendentes); ?></span>
                </h2>

                <?php if (empty($pendentes)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                <line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                            </svg>
                        </div>
                        Nenhum usuário pendente
                    </div>
                <?php else: ?>
                    <div class="info-box">
                        <span class="info-box-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        </span>
                        <span>Estes usuários aguardam processamento pelo script bash</span>
                    </div>
                    <div class="lista">
                        <?php foreach ($pendentes as $usuario): ?>
                            <div class="item pendente">
                                <div class="item-header">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d29922" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <?php echo htmlspecialchars($usuario['login']); ?>
                                </div>
                                <div class="item-detail">
                                    Setor: <span class="tag tag-yellow"><?php echo htmlspecialchars(ucfirst($usuario['setor'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Usuários Criados -->
            <div class="list-card criados fade-in fade-in-d2">
                <h2>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3fb950" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    Criados
                    <span class="count"><?php echo count($criados); ?></span>
                </h2>

                <?php if (empty($criados)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                <line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                            </svg>
                        </div>
                        Nenhum usuário criado ainda
                    </div>
                <?php else: ?>
                    <div class="info-box">
                        <span class="info-box-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        </span>
                        <span>Usuários já processados e criados no sistema</span>
                    </div>
                    <div class="lista">
                        <?php foreach ($criados as $usuario): ?>
                            <div class="item criado">
                                <div class="item-header">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3fb950" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <?php echo htmlspecialchars($usuario['login']); ?>
                                </div>
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
