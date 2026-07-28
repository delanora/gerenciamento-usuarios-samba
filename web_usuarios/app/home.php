<?php
/**
 * Sistema de Gerenciamento de Usuários Linux/Samba
 * Página inicial - Menu principal
 */

$base_dir = dirname(dirname(__DIR__)) . '/usuarios';
$web_dir = __DIR__;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Usuários</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/home.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Sistema de Gerenciamento de Usuários</h1>
            <p>Linux / Samba | Usuário: ti.pmc | <a href="../logout.php">Sair</a></p>
        </div>
        
        <div class="menu">
            <div class="menu-card add">
                <a href="adicionar_usuario.php">
                    <div class="card-icon">➕</div>
                    <h2>Adicionar Usuário</h2>
                    <p>Registrar novo usuário para criação</p>
                </a>
            </div>
            
            <div class="menu-card list">
                <a href="listar_usuarios.php">
                    <div class="card-icon">📋</div>
                    <h2>Listar Usuários</h2>
                    <p>Ver pendentes e criados</p>
                </a>
            </div>
            
            <div class="menu-card groups">
                <a href="gerenciar_setores.php">
                    <div class="card-icon">📂</div>
                    <h2>Gerenciar Setores</h2>
                    <p>Adicionar e remover grupos/setores</p>
                </a>
            </div>
        </div>
    </div>
</body>
</html>

