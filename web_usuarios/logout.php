<?php
/**
 * Sistema de Gerenciamento de Usuários Linux/Samba
 * Página de Logout
 */

session_start();
session_destroy();
header('Location: index.php');
exit;
?>
