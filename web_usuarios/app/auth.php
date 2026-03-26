<?php
/**
 * Sistema de Autenticação
 * Verifica se o usuário está logado
 */

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se não estiver logado, redirecionar para index.php (login)
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Usar caminho relativo - voltar um nível para chegar em web_usuarios
    header('Location: ../index.php');
    exit;
}
?>

