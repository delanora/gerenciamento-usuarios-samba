<?php
/**
 * Sistema de Gerenciamento de Usuários Linux/Samba
 * Página inicial - Menu principal
 */

require_once __DIR__ . '/auth.php';
$base_dir = dirname(dirname(__DIR__)) . '/usuarios';
$web_dir = __DIR__;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel - Gerenciamento de Usuários</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/home.css">
</head>
<body>
    <div class="container">
        <!-- Navbar -->
        <div class="navbar">
            <a href="home.php" class="navbar-brand">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#4095f5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
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

        <!-- Header -->
        <div class="header">
            <div class="header-icon">
                <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#4095f5" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <h1>Sistema de Gerenciamento de Usuários</h1>
            <p>
                Linux / Samba
                <span style="color:var(--border-primary)">|</span>
                <span style="color:var(--text-dim)">Bem-vindo, <strong style="color:var(--text-secondary)"><?php echo htmlspecialchars($_SESSION['usuario'] ?? ''); ?></strong></span>
            </p>
        </div>

        <!-- Menu Cards -->
        <div class="menu">
            <div class="menu-card add fade-in fade-in-d1">
                <a href="adicionar_usuario.php">
                    <div class="card-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#3fb950" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                    </div>
                    <h2>Adicionar Usuário</h2>
                </a>
            </div>

            <div class="menu-card list fade-in fade-in-d2">
                <a href="listar_usuarios.php">
                    <div class="card-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#4095f5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="8" y1="6" x2="21" y2="6"/>
                            <line x1="8" y1="12" x2="21" y2="12"/>
                            <line x1="8" y1="18" x2="21" y2="18"/>
                            <line x1="3" y1="6" x2="3.01" y2="6"/>
                            <line x1="3" y1="12" x2="3.01" y2="12"/>
                            <line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                    </div>
                    <h2>Listar Usuários</h2>
                </a>
            </div>

            <div class="menu-card groups fade-in fade-in-d3">
                <a href="gerenciar_setores.php">
                    <div class="card-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d29922" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                            <line x1="12" y1="11" x2="12" y2="17"/>
                            <line x1="9" y1="14" x2="15" y2="14"/>
                        </svg>
                    </div>
                    <h2>Gerenciar Setores</h2>
                </a>
            </div>

            <div class="menu-card shares fade-in fade-in-d4">
                <a href="gerenciar_compartilhamentos.php">
                    <div class="card-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#bc8cff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="18" cy="5" r="3"/>
                            <circle cx="6" cy="12" r="3"/>
                            <circle cx="18" cy="19" r="3"/>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                        </svg>
                    </div>
                    <h2>Compartilhamentos Samba</h2>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="app-footer">
            <span>Sistema de Gerenciamento de Usuários</span>
            <span class="footer-divider">|</span>
            <span>Linux / Samba</span>
            <span class="footer-divider">|</span>
            <span>Versão 2.0</span>
        </div>
    </div>
</body>
</html>
