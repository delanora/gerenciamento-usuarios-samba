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
$editando = null;

// Carregar setores para uso nas permissões
$setores_file = $base_dir . '/setores.conf';
$setores_list = [];
if (file_exists($setores_file) && is_readable($setores_file)) {
    $linhas = @file($setores_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($linhas !== false && is_array($linhas)) {
        $setores_list = array_map('trim', $linhas);
        $setores_list = array_values(array_filter($setores_list, function($s) {
            return !empty($s) && strpos($s, '#') !== 0;
        }));
        sort($setores_list);
    }
}

// ========== FUNÇÕES ==========

function parse_smbconf($caminho) {
    if (!file_exists($caminho) || !is_readable($caminho)) return null;
    $conteudo = file_get_contents($caminho);
    if ($conteudo === false) return null;
    $linhas = explode("\n", $conteudo);
    $estrutura = ['header' => [], 'global' => [], 'shares' => []];
    $modo = 'header';
    $secao_atual = '';
    $parametros = [];
    $linhas_header = [];

    foreach ($linhas as $linha) {
        $trimmed = trim($linha);
        if (preg_match('/^\[(.+)\]$/', $trimmed, $matches)) {
            $nome_secao = trim($matches[1]);
            if ($modo === 'linhas_header' || $modo === 'header') {
                if (!empty($parametros) || !empty($linhas_header)) {
                    if ($modo === 'linhas_header') $linhas_header = $parametros;
                }
            } elseif ($modo === 'global') { $estrutura['global'] = $parametros;
            } elseif ($modo === 'share') { $estrutura['shares'][$secao_atual] = $parametros; }
            $parametros = [];
            $secao_atual = $nome_secao;
            $modo = strtolower($nome_secao) === 'global' ? 'global' : 'share';
            continue;
        }
        if ($modo === 'header') { $linhas_header[] = $linha; $modo = 'linhas_header';
        } elseif ($modo === 'linhas_header') { $linhas_header[] = $linha;
        } else { $parametros[] = $linha; }
    }
    if ($modo === 'global') $estrutura['global'] = $parametros;
    elseif ($modo === 'share') $estrutura['shares'][$secao_atual] = $parametros;
    $estrutura['header'] = $linhas_header;
    return $estrutura;
}

function gerar_smbconf($header, $global_params, $shares) {
    $linhas = [];
    foreach ($header as $linha) $linhas[] = $linha;
    if (!empty($linhas) && trim(end($linhas)) !== '') $linhas[] = '';
    $linhas[] = '[global]';
    foreach ($global_params as $linha) $linhas[] = $linha;
    foreach ($shares as $nome => $params) {
        $linhas[] = '';
        $linhas[] = '[' . $nome . ']';
        foreach ($params as $linha) $linhas[] = $linha;
    }
    $linhas[] = '';
    return implode("\n", $linhas);
}

function extrair_parametros_share($linhas) {
    $params = [];
    foreach ($linhas as $linha) {
        $trimmed = trim($linha);
        if (empty($trimmed) || $trimmed[0] === '#' || $trimmed[0] === ';') continue;
        if (strpos($trimmed, '=') !== false) {
            $parts = explode('=', $trimmed, 2);
            $params[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
    }
    return $params;
}

function gerar_linhas_parametros($params) {
    $linhas = [];
    foreach ($params as $chave => $valor) {
        if ($valor !== '' && $valor !== null) $linhas[] = "   $chave = $valor";
    }
    return $linhas;
}

function shell_exec_disponivel() {
    $disabled = explode(',', ini_get('disable_functions'));
    return function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', $disabled));
}

/**
 * Extrai nomes de setores (@grupo) de uma string de permissão Samba
 */
function extrair_setores_permissao($valor) {
    $setores = [];
    $partes = explode(' ', trim($valor));
    foreach ($partes as $p) {
        $p = trim($p);
        if (strpos($p, '@') === 0) {
            $setores[] = substr($p, 1);
        }
    }
    return $setores;
}

/**
 * Remove entradas @setor de uma string, mantendo apenas usuários individuais
 */
function filtrar_usuarios_sem_setor($valor) {
    $partes = explode(' ', trim($valor));
    $filtradas = array_filter($partes, function($p) {
        $p = trim($p);
        return !empty($p) && strpos($p, '@') !== 0;
    });
    return trim(implode(' ', $filtradas));
}

// ========== PROCESSAMENTO ==========

$config = parse_smbconf($smb_conf_path);
if ($config === null) {
    $mensagem = "Não foi possível ler o arquivo $smb_conf_path. Verifique se o Samba está instalado e o arquivo existe.";
    $tipo_mensagem = 'erro';
}

if (isset($_GET['edit']) && $config !== null) {
    $nome_share = trim($_GET['edit']);
    if (isset($config['shares'][$nome_share])) $editando = $nome_share;
    else { $mensagem = "Compartilhamento '$nome_share' não encontrado!"; $tipo_mensagem = 'erro'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $config !== null) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar' || $acao === 'adicionar') {
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

        $erros = [];
        if (empty($share_nome)) $erros[] = 'Nome do compartilhamento é obrigatório!';
        elseif (!preg_match('/^[a-zA-Z0-9_ -]+$/', $share_nome)) $erros[] = 'Nome do compartilhamento deve conter apenas letras, números, underscore, hífen e espaços!';
        if (empty($share_path)) $erros[] = 'Caminho (path) é obrigatório!';

        if (empty($erros)) {
            $is_new = ($acao === 'adicionar');
            $is_rename = ($acao === 'salvar' && $share_nome !== $share_nome_original);
            if ($is_new || $is_rename) {
                if (isset($config['shares'][$share_nome])) $erros[] = "Já existe um compartilhamento com o nome '$share_nome'!";
            }
        }

        if (!empty($erros)) {
            $mensagem = implode(' ', $erros);
            $tipo_mensagem = 'erro';
            $editando = $share_nome;
        } else {
            // Processar setores selecionados nas permissões
            $sector_valid = $_POST['sector_valid'] ?? [];
            $sector_write = $_POST['sector_write'] ?? [];

            // Montar lista de @setores de leitura
            $valid_sectors_list = [];
            if (is_array($sector_valid)) {
                foreach ($sector_valid as $s) {
                    $s = trim($s);
                    if (!empty($s)) $valid_sectors_list[] = '@' . $s;
                }
            }

            // Montar lista de @setores de escrita
            $write_sectors_list = [];
            if (is_array($sector_write)) {
                foreach ($sector_write as $s) {
                    $s = trim($s);
                    if (!empty($s)) $write_sectors_list[] = '@' . $s;
                }
            }

            // Manter apenas usuários manuais (sem @) nos campos de texto
            $share_valid_users_manual = filtrar_usuarios_sem_setor($share_valid_users);
            $share_write_list_manual = filtrar_usuarios_sem_setor($share_write_list);

            // Combinar setores + usuários manuais
            $share_valid_combined = array_merge($valid_sectors_list,
                !empty($share_valid_users_manual) ? explode(' ', $share_valid_users_manual) : []);
            $share_write_combined = array_merge($write_sectors_list,
                !empty($share_write_list_manual) ? explode(' ', $share_write_list_manual) : []);

            $share_valid_final = trim(implode(' ', array_unique($share_valid_combined)));
            $share_write_final = trim(implode(' ', array_unique($share_write_combined)));

            $params = [];
            if (!empty($share_comment))       $params['comment'] = $share_comment;
            $params['path'] = $share_path;
            $params['browseable'] = $share_browseable;
            $params['read only'] = $share_read_only;
            $params['guest ok'] = $share_guest_ok;
            if (!empty($share_valid_final))   $params['valid users'] = $share_valid_final;
            if (!empty($share_write_final))    $params['write list'] = $share_write_final;
            if (!empty($share_create_mask))   $params['create mask'] = $share_create_mask;
            if (!empty($share_directory_mask))$params['directory mask'] = $share_directory_mask;

            $linhas_share = gerar_linhas_parametros($params);
            if ($acao === 'adicionar') {
                $config['shares'][$share_nome] = $linhas_share;
            } else {
                if ($is_rename) unset($config['shares'][$share_nome_original]);
                $config['shares'][$share_nome] = $linhas_share;
            }

            $novo_conteudo = gerar_smbconf($config['header'], $config['global'], $config['shares']);
            $escrito = file_put_contents($staging_file, $novo_conteudo, LOCK_EX);
            if ($escrito === false) {
                $mensagem = 'Erro ao escrever arquivo de staging. Verifique permissões.';
                $tipo_mensagem = 'erro';
            } else {
                if (shell_exec_disponivel()) {
                    $comando = 'sudo ' . escapeshellcmd($helper_script) . ' 2>&1';
                    $output = shell_exec($comando);
                    $output = trim($output ?? '');
                    if (strpos($output, 'sucesso') !== false || strpos($output, 'aplicada') !== false) {
                        $acao_texto = ($acao === 'adicionar') ? 'adicionado' : 'atualizado';
                        $mensagem = "Compartilhamento '$share_nome' $acao_texto e Samba reiniciado com sucesso!";
                        $tipo_mensagem = 'sucesso';
                        $editando = null;
                        $config = parse_smbconf($smb_conf_path);
                    } elseif (strpos($output, 'ERRO') !== false || strpos($output, 'erro') !== false) {
                        $mensagem = "Erro ao aplicar configuração: $output";
                        $tipo_mensagem = 'erro';
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
            $novo_conteudo = gerar_smbconf($config['header'], $config['global'], $config['shares']);
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

$shares_exibir = [];
$shares_especiais = ['homes', 'printers', 'print$'];
if ($config !== null) {
    foreach ($config['shares'] as $nome => $params) {
        if (in_array(strtolower($nome), $shares_especiais)) continue;
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
    <link rel="stylesheet" href="../css/compartilhamentos.css">
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
            <span class="current">Compartilhamentos Samba</span>
        </div>

        <!-- Header -->
        <div class="list-header">
            <h1>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#bc8cff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0">
                    <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>
                Compartilhamentos Samba
            </h1>
            <div class="list-header-actions">
                <a href="home.php" class="btn btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    Home
                </a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="mensagem <?php echo $tipo_mensagem; ?>" style="margin-bottom:16px;">
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

        <?php if ($config === null): ?>
            <div class="mensagem erro" style="margin-bottom:16px;">
                <span class="mensagem-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </span>
                <span>Não foi possível ler o arquivo de configuração do Samba (<code><?php echo htmlspecialchars($smb_conf_path); ?></code>). Verifique se o Samba está instalado e o arquivo existe.</span>
            </div>
        <?php else: ?>

        <!-- Banner de informações -->
        <div class="info-banner">
            <strong>Como funciona:</strong> As alterações são salvas em um arquivo temporário e aplicadas via <code>sudo</code> usando <code><?php echo htmlspecialchars(basename($helper_script)); ?></code>. O Samba é recarregado automaticamente sem desconectar usuários ativos. Um backup é criado antes de cada alteração em <code>/var/backups/samba/</code>.
            <?php if (!shell_exec_disponivel()): ?>
                <br><br>
                <strong>Atenção:</strong> <code>shell_exec</code> está desabilitado no PHP. As alterações serão salvas em staging, mas precisam ser aplicadas manualmente via terminal: <code>sudo <?php echo htmlspecialchars($helper_script); ?></code>
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

            // Extrair setores já configurados e remover dos campos de texto
            $share_valid_sectors = extrair_setores_permissao($share_valid_users);
            $share_write_sectors = extrair_setores_permissao($share_write_list);
            $share_valid_users = filtrar_usuarios_sem_setor($share_valid_users);
            $share_write_list = filtrar_usuarios_sem_setor($share_write_list);
        ?>
        <div class="form-card">
            <h2>
                <?php if ($is_edit): ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4095f5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Editar Compartilhamento
                <?php else: ?>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3fb950" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    Novo Compartilhamento
                <?php endif; ?>
            </h2>
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

                    <div>
                        <label for="share_browseable">Navegável (browseable)</label>
                        <select id="share_browseable" name="share_browseable">
                            <option value="yes" <?php echo $share_browseable === 'yes' ? 'selected' : ''; ?>>Sim</option>
                            <option value="no" <?php echo $share_browseable === 'no' ? 'selected' : ''; ?>>Não</option>
                        </select>
                        <div class="field-hint">Se "Não", o compartilhamento fica oculto na rede.</div>
                    </div>

                    <div>
                        <label for="share_read_only">Somente Leitura (read only)</label>
                        <select id="share_read_only" name="share_read_only">
                            <option value="yes" <?php echo $share_read_only === 'yes' ? 'selected' : ''; ?>>Sim</option>
                            <option value="no" <?php echo $share_read_only === 'no' ? 'selected' : ''; ?>>Não</option>
                        </select>
                        <div class="field-hint">Se "Sim", os usuários não podem alterar arquivos.</div>
                    </div>

                    <div>
                        <label for="share_guest_ok">Convidados (guest ok)</label>
                        <select id="share_guest_ok" name="share_guest_ok">
                            <option value="no" <?php echo $share_guest_ok === 'no' ? 'selected' : ''; ?>>Não</option>
                            <option value="yes" <?php echo $share_guest_ok === 'yes' ? 'selected' : ''; ?>>Sim</option>
                        </select>
                        <div class="field-hint">Se "Sim", usuários sem autenticação podem acessar.</div>
                    </div>

                    <div class="full-width">
                        <label>Setores com Acesso</label>
                        <div class="field-hint" style="margin-bottom:8px;">Selecione os setores que terão permissão de leitura e/ou escrita neste compartilhamento.</div>
                        <div class="setores-permissions">
                            <div class="setores-header">
                                <span class="setores-col-label">Setor</span>
                                <span class="setores-col-access">Leitura</span>
                                <span class="setores-col-access">Escrita</span>
                            </div>
                            <?php if (empty($setores_list)): ?>
                                <div class="setores-empty">Nenhum setor cadastrado. Adicione setores em Gerenciar Setores.</div>
                            <?php else: ?>
                                <?php foreach ($setores_list as $s): 
                                    $checked_valid = in_array($s, $share_valid_sectors ?? []) ? 'checked' : '';
                                    $checked_write = in_array($s, $share_write_sectors ?? []) ? 'checked' : '';
                                ?>
                                <div class="setor-row">
                                    <span class="setores-col-label">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d29922" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                        <?php echo htmlspecialchars(ucfirst($s)); ?>
                                    </span>
                                    <span class="setores-col-access">
                                        <label class="checkbox-wrap">
                                            <input type="checkbox" name="sector_valid[]" value="<?php echo htmlspecialchars($s); ?>"
                                                   <?php echo $checked_valid; ?>
                                                   onchange="toggleSetor(this, 'valid')">
                                            <span class="checkmark"></span>
                                        </label>
                                    </span>
                                    <span class="setores-col-access">
                                        <label class="checkbox-wrap">
                                            <input type="checkbox" name="sector_write[]" value="<?php echo htmlspecialchars($s); ?>"
                                                   <?php echo $checked_write; ?>
                                                   onchange="toggleSetor(this, 'write')">
                                            <span class="checkmark sector-write"></span>
                                        </label>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <label for="share_valid_users">Usuários Adicionais (valid users)</label>
                        <input type="text" id="share_valid_users" name="share_valid_users"
                               value="<?php echo htmlspecialchars($share_valid_users); ?>"
                               placeholder="usuario1 usuario2">
                        <div class="field-hint">Usuários individuais com acesso (sem @). Setores já são gerenciados acima.</div>
                    </div>

                    <div>
                        <label for="share_write_list">Usuários Adicionais - Escrita (write list)</label>
                        <input type="text" id="share_write_list" name="share_write_list"
                               value="<?php echo htmlspecialchars($share_write_list); ?>"
                               placeholder="usuario1 usuario2">
                        <div class="field-hint">Usuários individuais que podem escrever (sem @).</div>
                    </div>

                    <div>
                        <label for="share_create_mask">Máscara de Criação (create mask)</label>
                        <input type="text" id="share_create_mask" name="share_create_mask"
                               value="<?php echo htmlspecialchars($share_create_mask); ?>"
                               placeholder="0755" pattern="0[0-7]{3}">
                        <div class="field-hint">Permissão Unix para novos arquivos (ex: 0755, 0775).</div>
                    </div>

                    <div>
                        <label for="share_directory_mask">Máscara de Diretório (directory mask)</label>
                        <input type="text" id="share_directory_mask" name="share_directory_mask"
                               value="<?php echo htmlspecialchars($share_directory_mask); ?>"
                               placeholder="0755" pattern="0[0-7]{3}">
                        <div class="field-hint">Permissão Unix para novos diretórios (ex: 0755, 0775).</div>
                    </div>

                    <div class="form-actions">
                        <button type="submit">
                            <?php if ($is_edit): ?>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                Salvar Alterações
                            <?php else: ?>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                Adicionar Compartilhamento
                            <?php endif; ?>
                        </button>
                        <a href="gerenciar_compartilhamentos.php" class="btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Cancelar
                        </a>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Lista de Compartilhamentos -->
        <div class="shares-list-card">
            <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4095f5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>
                Compartilhamentos Ativos
                <span class="count-badge"><?php echo count($shares_exibir); ?></span>
            </h2>

            <div class="shares-toolbar">
                <a href="?novo=1" class="btn btn-success btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    Novo Compartilhamento
                </a>
            </div>

            <?php if (empty($shares_exibir)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    </div>
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
                            <h3>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4095f5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <?php echo htmlspecialchars($nome); ?>
                            </h3>
                            <div class="share-path">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <?php echo htmlspecialchars($dados['path'] ?? 'Caminho não definido'); ?>
                            </div>
                            <div class="share-tags">
                                <?php if (isset($dados['comment'])): ?>
                                    <span class="tag tag-blue"><?php echo htmlspecialchars($dados['comment']); ?></span>
                                <?php endif; ?>
                                <span class="tag <?php echo ($dados['browseable'] ?? 'yes') === 'yes' ? 'tag-green' : 'tag-red'; ?>">
                                    Navegável: <?php echo ($dados['browseable'] ?? 'yes') === 'yes' ? 'Sim' : 'Não'; ?>
                                </span>
                                <span class="tag <?php echo ($dados['read only'] ?? 'yes') === 'no' ? 'tag-green' : 'tag-yellow'; ?>">
                                    Escrita: <?php echo ($dados['read only'] ?? 'yes') === 'no' ? 'Sim' : 'Não'; ?>
                                </span>
                                <span class="tag <?php echo ($dados['guest ok'] ?? 'no') === 'yes' ? 'tag-green' : 'tag-red'; ?>">
                                    Convidados: <?php echo ($dados['guest ok'] ?? 'no') === 'yes' ? 'Sim' : 'Não'; ?>
                                </span>
                                <?php if (!empty($dados['valid users'])): ?>
                                    <span class="tag tag-blue"><?php echo htmlspecialchars($dados['valid users']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="share-actions">
                            <a href="?edit=<?php echo urlencode($nome); ?>" class="btn btn-sm">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Editar
                            </a>
                            <button type="button" class="btn btn-sm btn-danger"
                                    onclick="confirmarExclusao('<?php echo htmlspecialchars($nome, ENT_QUOTES); ?>')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                Remover
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </div>

    <!-- Modal de Exclusão -->
    <div class="modal-overlay" id="modalExclusao">
        <div class="modal-box">
            <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d29922" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Confirmar Exclusão
            </h3>
            <p id="modalMensagem">Tem certeza que deseja remover este compartilhamento?</p>
            <p style="color: var(--accent-red); font-size: 13px; margin-bottom: 16px;">
                O Samba será reiniciado automaticamente. Conexões ativas serão temporariamente interrompidas.
            </p>
            <form method="POST" action="" id="formExclusao">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="share_nome" id="inputExcluirNome">
                <div class="modal-actions">
                    <button type="button" class="btn" onclick="fecharModalExclusao()">Cancelar</button>
                    <button type="submit" class="btn-danger-action">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Remover Compartilhamento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        /**
         * Sincroniza checkboxes de setores com os campos de texto
         */
        function toggleSetor(checkbox, tipo) {
            var campo = tipo === 'valid' ? 'share_valid_users' : 'share_write_list';
            var input = document.getElementById(campo);
            var setorNome = checkbox.value;
            var prefixo = '@' + setorNome;

            var valorAtual = input.value.trim();
            var partes = valorAtual ? valorAtual.split(/\s+/) : [];

            if (checkbox.checked) {
                // Adicionar @setor se não existir
                var jaExiste = partes.some(function(p) { return p === prefixo; });
                if (!jaExiste) {
                    partes.push(prefixo);
                }
            } else {
                // Remover @setor
                partes = partes.filter(function(p) { return p !== prefixo; });
            }

            input.value = partes.join(' ');
        }

        /**
         * Inicializa os campos de texto com os setores já marcados ao carregar a página
         */
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[name="sector_valid[]"]:checked').forEach(function(cb) {
                toggleSetor(cb, 'valid');
            });
            document.querySelectorAll('input[name="sector_write[]"]:checked').forEach(function(cb) {
                toggleSetor(cb, 'write');
            });
        });

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
            if (event.target === this) fecharModalExclusao();
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') fecharModalExclusao();
        });
    </script>
</body>
</html>
