<?php
/**
 * Sistema de Gerenciamento de Usuários Linux/Samba
 * Página para gerenciar compartilhamentos Samba (smb.conf)
 */

require_once __DIR__ . '/auth.php';
$base_dir = dirname(dirname(__DIR__)) . '/usuarios';
$smb_conf_path = '/etc/samba/smb.conf';
$staging_file = '/var/backups/samba/compartilhamentos_staging.conf';
$helper_script = $base_dir . '/aplicar_compartilhamentos.sh';

$mensagem = '';
$tipo_mensagem = '';
$editando = null; // Nome do compartilhamento sendo editado (null = novo)

// ========== FUNÇÕES ==========

/**
 * Parseia o smb.conf e retorna sua estrutura
 */
function parse_smbconf($caminho) {
    if (!file_exists($caminho) || !is_readable($caminho)) {
        return null;
    }

    $conteudo = file_get_contents($caminho);
    if ($conteudo === false) {
        return null;
    }

    $linhas = explode("\n", $conteudo);
    $estrutura = [
        'header' => [],    // Linhas antes do primeiro [global]
        'global' => [],    // Parâmetros da seção [global]
        'shares' => []     // Compartilhamentos
    ];

    $modo = 'header';
    $secao_atual = '';
    $parametros = [];
    $linhas_header = [];

    foreach ($linhas as $linha) {
        $trimmed = trim($linha);

        // Detectar seção
        if (preg_match('/^\[(.+)\]$/', $trimmed, $matches)) {
            $nome_secao = trim($matches[1]);

            if ($modo === 'linhas_header' || $modo === 'header') {
                if (!empty($parametros) || !empty($linhas_header)) {
                    // Finalizar header
                    if ($modo === 'linhas_header') {
                        $linhas_header = $parametros;
                    }
                }
            } elseif ($modo === 'global') {
                $estrutura['global'] = $parametros;
            } elseif ($modo === 'share') {
                $estrutura['shares'][$secao_atual] = $parametros;
            }

            $parametros = [];
            $secao_atual = $nome_secao;

            if (strtolower($nome_secao) === 'global') {
                $modo = 'global';
            } else {
                $modo = 'share';
            }
            continue;
        }

        // Guardar linha
        if ($modo === 'header') {
            $linhas_header[] = $linha;
            $modo = 'linhas_header';
        } elseif ($modo === 'linhas_header') {
            $linhas_header[] = $linha;
        } else {
            $parametros[] = $linha;
        }
    }

    // Finalizar última seção
    if ($modo === 'global') {
        $estrutura['global'] = $parametros;
    } elseif ($modo === 'share') {
        $estrutura['shares'][$secao_atual] = $parametros;
    }

    $estrutura['header'] = $linhas_header;
    return $estrutura;
}

/**
 * Gera o conteúdo do smb.conf a partir da estrutura
 */
function gerar_smbconf($header, $global_params, $shares) {
    $linhas = [];

    // Header (comentários iniciais, etc.)
    foreach ($header as $linha) {
        $linhas[] = $linha;
    }

    // Garantir linha em branco antes de [global]
    if (!empty($linhas) && trim(end($linhas)) !== '') {
        $linhas[] = '';
    }

    // Seção [global]
    $linhas[] = '[global]';
    foreach ($global_params as $linha) {
        $linhas[] = $linha;
    }

    // Compartilhamentos
    foreach ($shares as $nome => $params) {
        $linhas[] = '';
        $linhas[] = '[' . $nome . ']';
        foreach ($params as $linha) {
            $linhas[] = $linha;
        }
    }

    $linhas[] = '';
    return implode("\n", $linhas);
}

/**
 * Extrai parâmetros de um compartilhamento como array associativo
 */
function extrair_parametros_share($linhas) {
    $params = [];
    foreach ($linhas as $linha) {
        $trimmed = trim($linha);
        if (empty($trimmed) || $trimmed[0] === '#' || $trimmed[0] === ';') {
            continue;
        }
        if (strpos($trimmed, '=') !== false) {
            $parts = explode('=', $trimmed, 2);
            $chave = strtolower(trim($parts[0]));
            $valor = trim($parts[1]);
            $params[$chave] = $valor;
        }
    }
    return $params;
}

/**
 * Gera linhas de parâmetros a partir de array chave=>valor
 */
function gerar_linhas_parametros($params) {
    $linhas = [];
    foreach ($params as $chave => $valor) {
        if ($valor !== '' && $valor !== null) {
            $linhas[] = "   $chave = $valor";
        }
    }
    return $linhas;
}

/**
 * Verifica se shell_exec está disponível
 */
function shell_exec_disponivel() {
    $disabled = explode(',', ini_get('disable_functions'));
    return function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', $disabled));
}

// ========== PROCESSAMENTO ==========

// Carregar configuração atual
$config = parse_smbconf($smb_conf_path);
if ($config === null) {
    $mensagem = "Não foi possível ler o arquivo $smb_conf_path. Verifique se o Samba está instalado e o arquivo existe.";
    $tipo_mensagem = 'erro';
}

// Processar ações GET
if (isset($_GET['edit']) && $config !== null) {
    $nome_share = trim($_GET['edit']);
    if (isset($config['shares'][$nome_share])) {
        $editando = $nome_share;
    } else {
        $mensagem = "Compartilhamento '$nome_share' não encontrado!";
        $tipo_mensagem = 'erro';
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $config !== null) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar' || $acao === 'adicionar') {
        // Dados do formulário
        $share_nome = trim($_POST['share_nome'] ?? '');
        $share_nome_original = trim($_POST['share_nome_original'] ?? '');
        $share_comment = trim($_POST['share_comment'] ?? '');
        $share_path = trim($_POST['share_path'] ?? '');
        $share_browseable = trim($_POST['share_browseable'] ?? 'yes');
        $share_read_only = trim($_POST['share_read_only'] ?? 'yes');
        $share_guest_ok = trim($_POST['share_guest_ok'] ?? 'no');
        $share_valid_users = trim($_POST['share_valid_users'] ?? '');
        $share_write_list = trim($_POST['share_write_list'] ?? '');
        $share_create_mask = trim($_POST['share_create_mask'] ?? '');
        $share_directory_mask = trim($_POST['share_directory_mask'] ?? '');

        // Validações
        $erros = [];
        if (empty($share_nome)) {
            $erros[] = 'Nome do compartilhamento é obrigatório!';
        } elseif (!preg_match('/^[a-zA-Z0-9_ -]+$/', $share_nome)) {
            $erros[] = 'Nome do compartilhamento deve conter apenas letras, números, underscore, hífen e espaços!';
        }
        if (empty($share_path)) {
            $erros[] = 'Caminho (path) é obrigatório!';
        }

        if (empty($erros)) {
            // Verificar se o nome já existe (caso seja novo ou renomeado)
            $is_new = ($acao === 'adicionar');
            $is_rename = ($acao === 'salvar' && $share_nome !== $share_nome_original);

            if ($is_new || $is_rename) {
                if (isset($config['shares'][$share_nome])) {
                    $erros[] = "Já existe um compartilhamento com o nome '$share_nome'!";
                }
            }
        }

        if (!empty($erros)) {
            $mensagem = implode(' ', $erros);
            $tipo_mensagem = 'erro';
            $editando = $share_nome;
        } else {
            // Montar parâmetros do compartilhamento
            $params = [];
            if (!empty($share_comment))       $params['comment'] = $share_comment;
            $params['path'] = $share_path;
            $params['browseable'] = $share_browseable;
            $params['read only'] = $share_read_only;
            $params['guest ok'] = $share_guest_ok;
            if (!empty($share_valid_users))   $params['valid users'] = $share_valid_users;
            if (!empty($share_write_list))    $params['write list'] = $share_write_list;
            if (!empty($share_create_mask))   $params['create mask'] = $share_create_mask;
            if (!empty($share_directory_mask))$params['directory mask'] = $share_directory_mask;

            $linhas_share = gerar_linhas_parametros($params);

            // Adicionar ou atualizar no array de shares
            if ($acao === 'adicionar') {
                $config['shares'][$share_nome] = $linhas_share;
            } else {
                // Se renomeou, remover o antigo
                if ($is_rename) {
                    unset($config['shares'][$share_nome_original]);
                }
                $config['shares'][$share_nome] = $linhas_share;
            }

            // Gerar novo conteúdo do smb.conf
            $novo_conteudo = gerar_smbconf(
                $config['header'],
                $config['global'],
                $config['shares']
            );

            // Escrever no arquivo de staging
            $escrito = file_put_contents($staging_file, $novo_conteudo, LOCK_EX);
            if ($escrito === false) {
                $mensagem = 'Erro ao escrever arquivo de staging. Verifique permissões.';
                $tipo_mensagem = 'erro';
            } else {
                // Executar script helper via sudo
                if (shell_exec_disponivel()) {
                    $comando = 'sudo ' . escapeshellcmd($helper_script) . ' 2>&1';
                    $output = shell_exec($comando);
                    $output = trim($output ?? '');

                    // Verificar resultado
                    if (strpos($output, 'sucesso') !== false || strpos($output, 'aplicada') !== false) {
                        $acao_texto = ($acao === 'adicionar') ? 'adicionado' : 'atualizado';
                        $mensagem = "Compartilhamento '$share_nome' $acao_texto e Samba reiniciado com sucesso!";
                        $tipo_mensagem = 'sucesso';
                        $editando = null;
                        // Recarregar configuração
                        $config = parse_smbconf($smb_conf_path);
                    } elseif (strpos($output, 'ERRO') !== false || strpos($output, 'erro') !== false) {
                        $mensagem = "Erro ao aplicar configuração: $output";
                        $tipo_mensagem = 'erro';
                        // Recarregar configuração original (backup foi restaurado)
                        $config = parse_smbconf($smb_conf_path);
                    } else {
                        $mensagem = "Compartilhamento salvo, mas não foi possível verificar o resultado: $output";
                        $tipo_mensagem = 'sucesso';
                        $editando = null;
                        $config = parse_smbconf($smb_conf_path);
                    }
                } else {
                    $mensagem = "Configuração salva em arquivo de staging, mas shell_exec está desabilitado. Execute manualmente como root: sudo $helper_script";
                    $tipo_mensagem = 'sucesso';
                    $editando = null;
                }
            }
        }
    } elseif ($acao === 'excluir') {
        $share_nome = trim($_POST['share_nome'] ?? '');

        if (isset($config['shares'][$share_nome])) {
            unset($config['shares'][$share_nome]);

            $novo_conteudo = gerar_smbconf(
                $config['header'],
                $config['global'],
                $config['shares']
            );

            $escrito = file_put_contents($staging_file, $novo_conteudo, LOCK_EX);
            if ($escrito === false) {
                $mensagem = 'Erro ao escrever arquivo de staging.';
                $tipo_mensagem = 'erro';
            } else {
                if (shell_exec_disponivel()) {
                    $comando = 'sudo ' . escapeshellcmd($helper_script) . ' 2>&1';
                    $output = shell_exec($comando);
                    $output = trim($output ?? '');

                    if (strpos($output, 'sucesso') !== false || strpos($output, 'aplicada') !== false) {
                        $mensagem = "Compartilhamento '$share_nome' removido e Samba reiniciado com sucesso!";
                        $tipo_mensagem = 'sucesso';
                        $config = parse_smbconf($smb_conf_path);
                    } else {
                        $mensagem = "Erro ao remover compartilhamento: $output";
                        $tipo_mensagem = 'erro';
                        $config = parse_smbconf($smb_conf_path);
                    }
                } else {
                    $mensagem = "Solicitação de exclusão salva. Execute manualmente: sudo $helper_script";
                    $tipo_mensagem = 'sucesso';
                }
            }
        } else {
            $mensagem = "Compartilhamento '$share_nome' não encontrado!";
            $tipo_mensagem = 'erro';
        }
    }
}

// Separar shares especiais
$shares_exibir = [];
$shares_especiais = ['homes', 'printers', 'print$'];
if ($config !== null) {
    foreach ($config['shares'] as $nome => $params) {
        if (in_array(strtolower($nome), $shares_especiais)) {
            continue; // Esconder da lista principal
        }
        $shares_exibir[$nome] = $params;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Compartilhamentos Samba</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/form.css">
    <link rel="stylesheet" href="../css/list.css">
    <style>
        .page-header {
            background: #161b22;
            border: 1px solid #30363d;
            padding: 16px 24px;
            border-radius: 6px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header h1 {
            color: #f0f6fc;
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }

        .page-header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .page-header-actions span {
            color: #8b949e;
            font-size: 12px;
        }

        /* Card de formulário */
        .form-card {
            background: #161b22;
            border: 1px solid #30363d;
            padding: 24px;
            border-radius: 6px;
            margin-bottom: 16px;
        }

        .form-card h2 {
            color: #f0f6fc;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #30363d;
            border-bottom-color: #1f6feb;
            font-size: 18px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 16px;
        }

        .form-grid .full-width {
            grid-column: 1 / -1;
        }

        .form-grid label {
            display: block;
            margin-bottom: 4px;
            color: #f0f6fc;
            font-weight: 600;
            font-size: 13px;
        }

        .form-grid input[type="text"],
        .form-grid select {
            width: 100%;
            padding: 6px 10px;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            font-size: 13px;
            color: #c9d1d9;
            transition: border-color 0.2s;
        }

        .form-grid input[type="text"]:focus,
        .form-grid select:focus {
            outline: none;
            border-color: #58a6ff;
            background: #161b22;
        }

        .form-grid input[type="text"]::placeholder {
            color: #6e7681;
        }

        .field-hint {
            font-size: 11px;
            color: #6e7681;
            margin-top: 2px;
        }

        .form-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            grid-column: 1 / -1;
        }

        /* Lista de compartilhamentos */
        .share-list-card {
            background: #161b22;
            border: 1px solid #30363d;
            padding: 24px;
            border-radius: 6px;
        }

        .share-list-card h2 {
            color: #f0f6fc;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #30363d;
            border-bottom-color: #1f6feb;
            font-size: 18px;
        }

        .share-item {
            background: #0d1117;
            border: 1px solid #21262d;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 8px;
            border-left: 3px solid #1f6feb;
            transition: border-color 0.2s, background-color 0.2s;
        }

        .share-item:hover {
            background: #161b22;
            border-color: #30363d;
        }

        .share-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .share-info h3 {
            color: #f0f6fc;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .share-info .share-path {
            color: #8b949e;
            font-size: 12px;
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
            margin-bottom: 8px;
        }

        .share-tags {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .share-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .share-tag.tag-yes {
            background: rgba(35, 134, 54, 0.15);
            border: 1px solid rgba(35, 134, 54, 0.3);
            color: #3fb950;
        }

        .share-tag.tag-no {
            background: rgba(218, 54, 51, 0.1);
            border: 1px solid rgba(218, 54, 51, 0.2);
            color: #f85149;
        }

        .share-tag.tag-info {
            background: rgba(56, 139, 253, 0.1);
            border: 1px solid rgba(56, 139, 253, 0.2);
            color: #79c0ff;
        }

        .share-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }

        .btn-sm {
            padding: 4px 10px;
            font-size: 12px;
            border-radius: 6px;
            border: 1px solid #30363d;
            background: #21262d;
            color: #c9d1d9;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-sm:hover {
            background: #30363d;
            text-decoration: none;
        }

        .btn-sm-edit {
            border-color: rgba(56, 139, 253, 0.3);
            color: #58a6ff;
        }

        .btn-sm-edit:hover {
            background: rgba(56, 139, 253, 0.15);
        }

        .btn-sm-delete {
            border-color: rgba(218, 54, 51, 0.3);
            color: #f85149;
        }

        .btn-sm-delete:hover {
            background: rgba(218, 54, 51, 0.15);
        }

        .vazio {
            text-align: center;
            padding: 48px;
            color: #6e7681;
            font-style: italic;
            font-size: 14px;
        }

        .btn-novo {
            background: #238636;
            border-color: #2ea043;
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
        }

        .btn-novo:hover {
            background: #2ea043;
            text-decoration: none;
        }

        .btn-cancel {
            padding: 8px 16px;
            background: #21262d;
            border: 1px solid #30363d;
            color: #c9d1d9;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s;
        }

        .btn-cancel:hover {
            background: #30363d;
            text-decoration: none;
        }

        .info-banner {
            background: rgba(56, 139, 253, 0.1);
            border: 1px solid rgba(56, 139, 253, 0.2);
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #79c0ff;
            line-height: 1.6;
        }

        code {
            background: #21262d;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            color: #c9d1d9;
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
            max-width: 440px;
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

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .share-header {
                flex-direction: column;
            }
            .share-actions {
                align-self: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>📁 Compartilhamentos Samba</h1>
            <div class="page-header-actions">
                <span><?php echo htmlspecialchars($_SESSION['usuario'] ?? ''); ?></span>
                <a href="home.php" class="btn">← Voltar</a>
                <a href="../logout.php" class="btn logout">Sair</a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="mensagem <?php echo $tipo_mensagem; ?>" style="margin-bottom: 16px;">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <?php if ($config === null): ?>
            <div class="mensagem erro" style="margin-bottom: 16px;">
                ⚠️ Não foi possível ler o arquivo de configuração do Samba
                (<code><?php echo htmlspecialchars($smb_conf_path); ?></code>).
                Verifique se o Samba está instalado e o arquivo existe.
            </div>
        <?php else: ?>

        <!-- Banner de informações -->
        <div class="info-banner">
            ⚙️ <strong>Como funciona:</strong> As alterações são salvas em um arquivo temporário e aplicadas via
            <code>sudo</code> usando <code><?php echo htmlspecialchars(basename($helper_script)); ?></code>.
            O Samba é recarregado automaticamente (sem desconectar usuários ativos). Um backup é criado antes de cada alteração em
            <code>/var/backups/samba/</code>.
            <?php if (!shell_exec_disponivel()): ?>
                <br><br>⚠️ <strong>Atenção:</strong> <code>shell_exec</code> está desabilitado no PHP.
                As alterações serão salvas em staging, mas precisam ser aplicadas manualmente via terminal:
                <code>sudo <?php echo htmlspecialchars($helper_script); ?></code>
            <?php endif; ?>
        </div>

        <!-- Formulário de Adicionar/Editar -->
        <?php if ($editando !== null || isset($_GET['novo'])): 
            $is_edit = ($editando !== null && !isset($_GET['novo']));
            $share_data = [];

            if ($is_edit && isset($config['shares'][$editando])) {
                $share_data = extrair_parametros_share($config['shares'][$editando]);
            }

            $share_nome     = $_POST['share_nome'] ?? ($is_edit ? $editando : '');
            $share_comment  = $_POST['share_comment'] ?? ($share_data['comment'] ?? '');
            $share_path     = $_POST['share_path'] ?? ($share_data['path'] ?? '');
            $share_browseable = $_POST['share_browseable'] ?? ($share_data['browseable'] ?? 'yes');
            $share_read_only  = $_POST['share_read_only'] ?? ($share_data['read only'] ?? 'yes');
            $share_guest_ok   = $_POST['share_guest_ok'] ?? ($share_data['guest ok'] ?? 'no');
            $share_valid_users = $_POST['share_valid_users'] ?? ($share_data['valid users'] ?? '');
            $share_write_list = $_POST['share_write_list'] ?? ($share_data['write list'] ?? '');
            $share_create_mask = $_POST['share_create_mask'] ?? ($share_data['create mask'] ?? '0755');
            $share_directory_mask = $_POST['share_directory_mask'] ?? ($share_data['directory mask'] ?? '0755');
        ?>
        <div class="form-card">
            <h2><?php echo $is_edit ? '✏️ Editar Compartilhamento' : '➕ Novo Compartilhamento'; ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="acao" value="<?php echo $is_edit ? 'salvar' : 'adicionar'; ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="share_nome_original" value="<?php echo htmlspecialchars($editando); ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="full-width">
                        <label for="share_nome">Nome do Compartilhamento</label>
                        <input type="text" id="share_nome" name="share_nome"
                               value="<?php echo htmlspecialchars($share_nome); ?>"
                               required pattern="[a-zA-Z0-9_ -]+"
                               placeholder="Ex: documentos, backup, publico"
                               <?php echo $is_edit ? '' : 'autofocus'; ?>>
                        <div class="field-hint">Nome que aparecerá na rede. Letras, números, underscore, hífen e espaços.</div>
                    </div>

                    <div class="full-width">
                        <label for="share_comment">Descrição (comment)</label>
                        <input type="text" id="share_comment" name="share_comment"
                               value="<?php echo htmlspecialchars($share_comment); ?>"
                               placeholder="Ex: Documentos compartilhados do setor de vendas">
                        <div class="field-hint">Descrição amigável exibida ao lado do compartilhamento na rede.</div>
                    </div>

                    <div class="full-width">
                        <label for="share_path">Caminho (path) *</label>
                        <input type="text" id="share_path" name="share_path"
                               value="<?php echo htmlspecialchars($share_path); ?>"
                               required placeholder="Ex: /srv/samba/documentos">
                        <div class="field-hint">Diretório completo no servidor a ser compartilhado. Deve existir e ter permissões corretas.</div>
                    </div>

                    <div class="form-group">
                        <label for="share_browseable">Navegável (browseable)</label>
                        <select id="share_browseable" name="share_browseable">
                            <option value="yes" <?php echo $share_browseable === 'yes' ? 'selected' : ''; ?>>Sim</option>
                            <option value="no" <?php echo $share_browseable === 'no' ? 'selected' : ''; ?>>Não</option>
                        </select>
                        <div class="field-hint">Se "Não", o compartilhamento fica oculto na rede (acesso direto pelo nome).</div>
                    </div>

                    <div class="form-group">
                        <label for="share_read_only">Somente Leitura (read only)</label>
                        <select id="share_read_only" name="share_read_only">
                            <option value="yes" <?php echo $share_read_only === 'yes' ? 'selected' : ''; ?>>Sim</option>
                            <option value="no" <?php echo $share_read_only === 'no' ? 'selected' : ''; ?>>Não</option>
                        </select>
                        <div class="field-hint">Se "Sim", os usuários não podem alterar arquivos (apenas leitura).</div>
                    </div>

                    <div class="form-group">
                        <label for="share_guest_ok">Convidados (guest ok)</label>
                        <select id="share_guest_ok" name="share_guest_ok">
                            <option value="no" <?php echo $share_guest_ok === 'no' ? 'selected' : ''; ?>>Não</option>
                            <option value="yes" <?php echo $share_guest_ok === 'yes' ? 'selected' : ''; ?>>Sim</option>
                        </select>
                        <div class="field-hint">Se "Sim", usuários sem autenticação podem acessar (público).</div>
                    </div>

                    <div class="form-group">
                        <label for="share_valid_users">Usuários Válidos (valid users)</label>
                        <input type="text" id="share_valid_users" name="share_valid_users"
                               value="<?php echo htmlspecialchars($share_valid_users); ?>"
                               placeholder="Ex: @vendas @ti usuario1">
                        <div class="field-hint">Usuários ou grupos (@grupo) com permissão de acesso. Separar por espaços.</div>
                    </div>

                    <div class="form-group">
                        <label for="share_write_list">Lista de Escrita (write list)</label>
                        <input type="text" id="share_write_list" name="share_write_list"
                               value="<?php echo htmlspecialchars($share_write_list); ?>"
                               placeholder="Ex: @ti admin">
                        <div class="field-hint">Usuários que podem escrever mesmo em compartilhamentos somente leitura.</div>
                    </div>

                    <div class="form-group">
                        <label for="share_create_mask">Máscara de Criação (create mask)</label>
                        <input type="text" id="share_create_mask" name="share_create_mask"
                               value="<?php echo htmlspecialchars($share_create_mask); ?>"
                               placeholder="0755" pattern="0[0-7]{3}">
                        <div class="field-hint">Permissão Unix para novos arquivos (ex: 0755, 0775, 0700).</div>
                    </div>

                    <div class="form-group">
                        <label for="share_directory_mask">Máscara de Diretório (directory mask)</label>
                        <input type="text" id="share_directory_mask" name="share_directory_mask"
                               value="<?php echo htmlspecialchars($share_directory_mask); ?>"
                               placeholder="0755" pattern="0[0-7]{3}">
                        <div class="field-hint">Permissão Unix para novos diretórios (ex: 0755, 0775).</div>
                    </div>

                    <div class="form-actions">
                        <button type="submit">
                            <?php echo $is_edit ? 'Salvar Alterações' : 'Adicionar Compartilhamento'; ?>
                        </button>
                        <a href="gerenciar_compartilhamentos.php" class="btn-cancel">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Lista de Compartilhamentos -->
        <div class="share-list-card">
            <h2>
                📋 Compartilhamentos Ativos
                <span class="count-badge" style="display: inline-block; background: #21262d; border: 1px solid #30363d; color: #c9d1d9; padding: 2px 10px; border-radius: 12px; font-size: 12px; margin-left: 8px;">
                    <?php echo count($shares_exibir); ?>
                </span>
            </h2>

            <div style="margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="?novo=1" class="btn-novo">➕ Novo Compartilhamento</a>
            </div>

            <?php if (empty($shares_exibir)): ?>
                <div class="vazio">
                    Nenhum compartilhamento personalizado criado.<br>
                    Clique em "Novo Compartilhamento" para adicionar um.
                </div>
            <?php else: ?>
                <?php foreach ($shares_exibir as $nome => $params):
                    $dados = extrair_parametros_share($params);
                ?>
                <div class="share-item">
                    <div class="share-header">
                        <div class="share-info">
                            <h3><?php echo htmlspecialchars($nome); ?></h3>
                            <div class="share-path">
                                📂 <?php echo htmlspecialchars($dados['path'] ?? 'Caminho não definido'); ?>
                            </div>
                            <div class="share-tags">
                                <?php if (isset($dados['comment'])): ?>
                                    <span class="share-tag tag-info"><?php echo htmlspecialchars($dados['comment']); ?></span>
                                <?php endif; ?>

                                <span class="share-tag <?php echo ($dados['browseable'] ?? 'yes') === 'yes' ? 'tag-yes' : 'tag-no'; ?>">
                                    Navegável: <?php echo ($dados['browseable'] ?? 'yes') === 'yes' ? 'Sim' : 'Não'; ?>
                                </span>

                                <span class="share-tag <?php echo ($dados['read only'] ?? 'yes') === 'no' ? 'tag-yes' : 'tag-no'; ?>">
                                    Escrita: <?php echo ($dados['read only'] ?? 'yes') === 'no' ? 'Sim' : 'Não'; ?>
                                </span>

                                <span class="share-tag <?php echo ($dados['guest ok'] ?? 'no') === 'yes' ? 'tag-yes' : 'tag-no'; ?>">
                                    Convidados: <?php echo ($dados['guest ok'] ?? 'no') === 'yes' ? 'Sim' : 'Não'; ?>
                                </span>

                                <?php if (!empty($dados['valid users'])): ?>
                                    <span class="share-tag tag-info">👥 <?php echo htmlspecialchars($dados['valid users']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="share-actions">
                            <a href="?edit=<?php echo urlencode($nome); ?>" class="btn-sm btn-sm-edit">✏️ Editar</a>
                            <button type="button" class="btn-sm btn-sm-delete"
                                    onclick="confirmarExclusao('<?php echo htmlspecialchars($nome, ENT_QUOTES); ?>')">
                                🗑️ Remover
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php endif; // config !== null ?>
    </div>

    <!-- Modal de Exclusão -->
    <div class="modal-overlay" id="modalExclusao">
        <div class="modal-box">
            <h3>⚠️ Confirmar Exclusão</h3>
            <p id="modalMensagem">Tem certeza que deseja remover este compartilhamento?</p>
            <p style="color: #f85149; font-size: 13px; margin-bottom: 16px;">
                ⚠️ O Samba será reiniciado automaticamente. Conexões ativas serão temporariamente interrompidas.
            </p>
            <form method="POST" action="" id="formExclusao">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="share_nome" id="inputExcluirNome">
                <div class="modal-actions">
                    <button type="button" class="btn-cancelar" onclick="fecharModalExclusao()"
                            style="padding: 8px 16px; background: #21262d; border: 1px solid #30363d; color: #c9d1d9; border-radius: 6px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="submit"
                            style="padding: 8px 16px; background: rgba(218, 54, 51, 0.2); border: 1px solid #f85149; color: #f85149; border-radius: 6px; cursor: pointer; font-weight: 500;">
                        Remover Compartilhamento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmarExclusao(nome) {
            document.getElementById('modalMensagem').textContent =
                "Tem certeza que deseja remover o compartilhamento '" + nome + "'?";
            document.getElementById('inputExcluirNome').value = nome;
            document.getElementById('modalExclusao').classList.add('active');
        }

        function fecharModalExclusao() {
            document.getElementById('modalExclusao').classList.remove('active');
        }

        document.getElementById('modalExclusao').addEventListener('click', function(event) {
            if (event.target === this) {
                fecharModalExclusao();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                fecharModalExclusao();
            }
        });
    </script>
</body>
</html>
