#!/bin/bash
# ==============================================================
#  🖥️  Instalador do Sistema de Gerenciamento de Usuários
#       Linux / Samba - Interface Web + Scripts Bash
# ==============================================================
#  Uso:   chmod +x instalar.sh && sudo ./instalar.sh
#  Desc:  Script interativo que instala dependências, copia
#         arquivos, configura permissões e prepara o ambiente.
# ==============================================================

set -euo pipefail

# ──────────────────────────────────────────────────────────────
#  CORES PARA OUTPUT
# ──────────────────────────────────────────────────────────────
VERDE='\033[0;32m'
AZUL='\033[0;34m'
AMARELO='\033[1;33m'
VERMELHO='\033[0;31m'
CIANO='\033[0;36m'
ROXO='\033[0;35m'
NEGRITO='\033[1m'
RESET='\033[0m'

# ──────────────────────────────────────────────────────────────
#  FUNÇÕES DE UTILIDADE
# ──────────────────────────────────────────────────────────────

log_info()    { echo -e "${AZUL}[INFO]${RESET}  $*"; }
log_ok()      { echo -e "${VERDE}[OK]${RESET}    $*"; }
log_aviso()   { echo -e "${AMARELO}[AVISO]${RESET} $*"; }
log_erro()    { echo -e "${VERMELHO}[ERRO]${RESET} $*"; exit 1; }
log_destaque(){ echo -e "${ROXO}$*${RESET}"; }
log_pergunta(){ echo -e "${CIANO}▶ $*${RESET}"; }

cabecalho() {
    clear
    echo -e "${ROXO}"
    echo '  ╔══════════════════════════════════════════════════╗'
    echo '  ║                                                  ║'
    echo '  ║   🖥️  Gerenciamento de Usuários Linux/Samba     ║'
    echo '  ║         Instalador Automático v2.0               ║'
    echo '  ║                                                  ║'
    echo '  ╚══════════════════════════════════════════════════╝'
    echo -e "${RESET}"
    echo ""
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: LER ARQUIVOS DE ORIGEM (diretório do script)
# ──────────────────────────────────────────────────────────────

# Diretório onde está este script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

verificar_arquivos_origem() {
    log_info "Verificando arquivos de origem em: ${SCRIPT_DIR}"

    local erros=0

    # Verificar diretório usuarios/
    if [ ! -d "${SCRIPT_DIR}/usuarios" ]; then
        log_erro "Diretório 'usuarios/' não encontrado em ${SCRIPT_DIR}"
    fi

    # Arquivos obrigatórios em usuarios/
    for arq in "cria_usuarios.sh" "aplicar_compartilhamentos.sh" "setores.conf" "usuarios_sistema.example.txt"; do
        if [ ! -f "${SCRIPT_DIR}/usuarios/${arq}" ]; then
            log_erro "Arquivo 'usuarios/${arq}' não encontrado!"
        fi
    done

    # Verificar diretório web_usuarios/
    if [ ! -d "${SCRIPT_DIR}/web_usuarios" ]; then
        log_erro "Diretório 'web_usuarios/' não encontrado em ${SCRIPT_DIR}"
    fi

    # Arquivos obrigatórios em web_usuarios/
    for arq in "index.php" "logout.php" ".htaccess"; do
        if [ ! -f "${SCRIPT_DIR}/web_usuarios/${arq}" ]; then
            log_erro "Arquivo 'web_usuarios/${arq}' não encontrado!"
        fi
    done

    # Verificar app/
    if [ ! -d "${SCRIPT_DIR}/web_usuarios/app" ]; then
        log_erro "Diretório 'web_usuarios/app/' não encontrado!"
    fi

    for arq in "auth.php" "home.php" "adicionar_usuario.php" "listar_usuarios.php" "gerenciar_setores.php" "gerenciar_compartilhamentos.php"; do
        if [ ! -f "${SCRIPT_DIR}/web_usuarios/app/${arq}" ]; then
            log_erro "Arquivo 'web_usuarios/app/${arq}' não encontrado!"
        fi
    done

    # Verificar css/
    if [ ! -d "${SCRIPT_DIR}/web_usuarios/css" ]; then
        log_erro "Diretório 'web_usuarios/css/' não encontrado!"
    fi

    log_ok "Todos os arquivos de origem foram encontrados."
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: DETECTAR DISTRIBUIÇÃO
# ──────────────────────────────────────────────────────────────

detectar_distro() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        DISTRO_ID="$ID"
        DISTRO_NOME="$NAME"
        DISTRO_VERSAO="$VERSION_ID"
    elif [ -f /etc/debian_version ]; then
        DISTRO_ID="debian"
        DISTRO_NOME="Debian"
        DISTRO_VERSAO=$(cat /etc/debian_version)
    elif [ -f /etc/redhat-release ]; then
        DISTRO_ID="rhel"
        DISTRO_NOME="Red Hat"
        DISTRO_VERSAO=$(cat /etc/redhat-release)
    else
        DISTRO_ID="unknown"
        DISTRO_NOME="Distribuição desconhecida"
        DISTRO_VERSAO="?"
    fi

    DISTRO_ID=$(echo "$DISTRO_ID" | tr '[:upper:]' '[:lower:]')
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: INSTALAR PACOTES (baseado na distro)
# ──────────────────────────────────────────────────────────────

instalar_pacotes() {
    cabecalho
    echo -e "${NEGRITO}── Passo 2: Instalação de dependências ──${RESET}"
    echo ""

    log_info "Distribuição detectada: ${DISTRO_NOME} ${DISTRO_VERSAO}"

    # Debian / Ubuntu / Linux Mint / etc.
    if [[ "$DISTRO_ID" =~ ^(debian|ubuntu|linuxmint|pop|elementary|zorin|kali|raspbian)$ ]]; then
        PKG_MANAGER="apt-get"
        PKG_UPDATE="$PKG_MANAGER update"
        PKG_INSTALL="$PKG_MANAGER install -y"

        PACOTES_APACHE="apache2 libapache2-mod-php"
        PACOTES_PHP="php php-common php-mbstring php-xml"
        PACOTES_SAMBA="samba smbclient samba-common-bin"
        PACOTES_EXTRAS="curl wget sudo"

        SERVICO_APACHE="apache2"
        SERVICO_SAMBA="smbd"
        WWW_USER="www-data"
        WWW_DIR="/var/www/html"
    # RHEL / CentOS / Fedora / AlmaLinux / Rocky
    elif [[ "$DISTRO_ID" =~ ^(rhel|centos|fedora|almalinux|rocky)$ ]]; then
        if [[ "$DISTRO_ID" == "fedora" ]]; then
            PKG_MANAGER="dnf"
        else
            PKG_MANAGER="yum"
        fi
        PKG_UPDATE="$PKG_MANAGER check-update || true"
        PKG_INSTALL="$PKG_MANAGER install -y"

        PACOTES_APACHE="httpd mod_php"
        PACOTES_PHP="php php-common php-mbstring php-xml"
        PACOTES_SAMBA="samba samba-client samba-common"
        PACOTES_EXTRAS="curl wget sudo"

        SERVICO_APACHE="httpd"
        SERVICO_SAMBA="smb"
        WWW_USER="apache"
        WWW_DIR="/var/www/html"
    # openSUSE
    elif [[ "$DISTRO_ID" == "opensuse"* || "$DISTRO_ID" == "suse" ]]; then
        PKG_MANAGER="zypper"
        PKG_UPDATE="$PKG_MANAGER refresh"
        PKG_INSTALL="$PKG_MANAGER install -y"

        PACOTES_APACHE="apache2 apache2-mod_php"
        PACOTES_PHP="php php-mbstring php-xml"
        PACOTES_SAMBA="samba samba-client"
        PACOTES_EXTRAS="curl wget sudo"

        SERVICO_APACHE="apache2"
        SERVICO_SAMBA="smb"
        WWW_USER="wwwrun"
        WWW_DIR="/srv/www/htdocs"
    # Arch Linux / Manjaro
    elif [[ "$DISTRO_ID" =~ ^(arch|manjaro|endeavouros|arcolinux)$ ]]; then
        PKG_MANAGER="pacman"
        PKG_UPDATE="$PKG_MANAGER -Syu --noconfirm"
        PKG_INSTALL="$PKG_MANAGER -S --noconfirm"

        PACOTES_APACHE="apache"
        PACOTES_PHP="php php-apache php-mbstring"
        PACOTES_SAMBA="samba"
        PACOTES_EXTRAS="curl wget sudo"

        SERVICO_APACHE="httpd"
        SERVICO_SAMBA="smb"
        WWW_USER="http"
        WWW_DIR="/srv/http"
    else
        log_aviso "Distribuição '${DISTRO_NOME}' não reconhecida automaticamente."
        log_aviso "Você precisará instalar os pacotes manualmente."
        log_aviso "Requisitos: Apache 2.4+, PHP 7.4+, Samba 4.x"
        echo ""

        # Perguntar caminhos manualmente
        read -r -p "Digite o diretório web do servidor [padrão: /var/www/html]: " WWW_DIR_INPUT
        WWW_DIR="${WWW_DIR_INPUT:-/var/www/html}"

        read -r -p "Digite o usuário do servidor web [padrão: www-data]: " WWW_USER_INPUT
        WWW_USER="${WWW_USER_INPUT:-www-data}"

        # Não vamos instalar pacotes manualmente
        INSTALAR_PACOTES="nao"
        return
    fi

    # Perguntar se deseja instalar
    log_info "Pacotes que serão instalados:"
    echo "  • Apache:     ${PACOTES_APACHE}"
    echo "  • PHP:        ${PACOTES_PHP}"
    echo "  • Samba:      ${PACOTES_SAMBA}"
    echo "  • Extras:     ${PACOTES_EXTRAS}"
    echo ""

    INSTALAR_PACOTES="sim"

    # Se já estiver tudo instalado, podemos pular
    local ja_instalado=false
    for cmd in apache2 httpd; do
        if command -v "$cmd" &>/dev/null; then ja_instalado=true; break; fi
    done
    command -v php &>/dev/null && command -v smbpasswd &>/dev/null || ja_instalado=false

    if $ja_instalado; then
        log_ok "Apache, PHP e Samba já parecem estar instalados."
        read -r -p "Deseja reinstalar/atualizar mesmo assim? (s/N): " RESP
        if [[ ! "$RESP" =~ ^[Ss]$ ]]; then
            INSTALAR_PACOTES="nao"
            return
        fi
    fi
}

executar_instalacao_pacotes() {
    cabecalho
    echo -e "${NEGRITO}── Instalando pacotes... ──${RESET}"
    echo ""

    log_info "Atualizando lista de pacotes..."
    eval "$PKG_UPDATE" || true

    log_info "Instalando Apache..."
    eval "$PKG_INSTALL $PACOTES_APACHE" || log_erro "Falha ao instalar Apache"

    log_info "Instalando PHP..."
    eval "$PKG_INSTALL $PACOTES_PHP" || log_erro "Falha ao instalar PHP"

    log_info "Instalando Samba..."
    eval "$PKG_INSTALL $PACOTES_SAMBA" || log_erro "Falha ao instalar Samba"

    log_info "Instalando pacotes extras..."
    eval "$PKG_INSTALL $PACOTES_EXTRAS" || true

    # Ativar módulos necessários
    if [[ "$SERVICO_APACHE" == "apache2" ]]; then
        log_info "Ativando mod_rewrite..."
        a2enmod rewrite || true
    fi

    # Habilitar e iniciar serviços
    log_info "Iniciando serviços..."
    systemctl enable "$SERVICO_APACHE" 2>/dev/null || true
    systemctl enable "$SERVICO_SAMBA" 2>/dev/null || true
    systemctl restart "$SERVICO_APACHE" 2>/dev/null || true
    systemctl restart "$SERVICO_SAMBA" 2>/dev/null || true

    log_ok "Pacotes instalados e serviços iniciados com sucesso!"
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: PERGUNTAR CONFIGURAÇÕES
# ──────────────────────────────────────────────────────────────

perguntar_configuracoes() {
    cabecalho
    echo -e "${NEGRITO}── Passo 3: Configuração do sistema ──${RESET}"
    echo ""

    # ─── Grupo padrão ───
    log_pergunta "Qual será o nome do grupo padrão para novos usuários?"
    echo "  (Grupo Linux que todos os usuários criados pertencerão)"
    read -r -p "  Grupo padrão [cliente]: " GRUPO_PADRAO_INPUT
    GRUPO_PADRAO="${GRUPO_PADRAO_INPUT:-cliente}"
    echo ""

    # ─── Diretório de instalação ───
    if [ -z "${WWW_DIR:-}" ]; then
        WWW_DIR="/var/www/html"
    fi
    log_pergunta "O diretório web padrão é: ${WWW_DIR}"
    read -r -p "  Deseja alterar? (Digite novo caminho ou Enter para manter): " WWW_DIR_INPUT
    WWW_DIR="${WWW_DIR_INPUT:-$WWW_DIR}"
    echo ""

    # ─── Usuário do servidor web ───
    if [ -z "${WWW_USER:-}" ]; then
        WWW_USER="www-data"
    fi
    log_pergunta "O usuário do servidor web detectado é: ${WWW_USER}"
    read -r -p "  Deseja alterar? (Digite o nome do usuário ou Enter para manter): " WWW_USER_INPUT
    WWW_USER="${WWW_USER_INPUT:-$WWW_USER}"
    echo ""

    # ─── Credenciais do admin ───
    log_pergunta "Configurar usuário administrador do painel web"
    echo "  (Usado para fazer login na interface web)"
    read -r -p "  Nome de usuário [admin]: " ADMIN_USUARIO_INPUT
    ADMIN_USUARIO="${ADMIN_USUARIO_INPUT:-admin}"

    ADMIN_SENHA=""
    while true; do
        read -r -s -p "  Senha do administrador: " ADMIN_SENHA
        echo ""
        if [ -z "$ADMIN_SENHA" ]; then
            echo "  A senha não pode estar vazia!"
            continue
        elif [ ${#ADMIN_SENHA} -lt 4 ]; then
            echo "  A senha deve ter pelo menos 4 caracteres!"
            continue
        fi
        read -r -s -p "  Confirme a senha: " ADMIN_SENHA_CONFIRM
        echo ""
        if [ "$ADMIN_SENHA" = "$ADMIN_SENHA_CONFIRM" ]; then
            break
        fi
        echo "  As senhas não conferem! Tente novamente."
    done
    echo ""

    # ─── Setores iniciais ───
    log_pergunta "Deseja adicionar setores iniciais?"
    echo "  (Os setores são grupos no Linux/Samba usados para organizar usuários)"
    echo "  Digite os nomes separados por espaço (ex: vendas ti rh financeiro)"
    read -r -p "  Setores [grupos]: " SETORES_INPUT
    SETORES_LIST="${SETORES_INPUT:-grupos}"
    echo ""

    # ─── Cron ───
    log_pergunta "Deseja configurar agendamento automático (cron) para criação de usuários?"
    echo "  O script cria_usuarios.sh será executado a cada hora automaticamente."
    read -r -p "  Configurar cron? (S/n): " CRON_RESP
    CONFIGURAR_CRON=true
    if [[ "$CRON_RESP" =~ ^[Nn]$ ]]; then
        CONFIGURAR_CRON=false
    fi
    echo ""

    # ─── Sudo para www-data ───
    log_pergunta "Deseja configurar sudo para o gerenciador de compartilhamentos Samba?"
    echo "  Necessário para que a interface web possa editar o smb.conf e reiniciar o Samba."
    read -r -p "  Configurar sudo? (S/n): " SUDO_RESP
    CONFIGURAR_SUDO=true
    if [[ "$SUDO_RESP" =~ ^[Nn]$ ]]; then
        CONFIGURAR_SUDO=false
    fi
    echo ""
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: COPIAR ARQUIVOS E CRIAR ESTRUTURA
# ──────────────────────────────────────────────────────────────

copiar_arquivos() {
    cabecalho
    echo -e "${NEGRITO}── Passo 4: Copiando arquivos para ${WWW_DIR} ──${RESET}"
    echo ""

    # Criar diretórios (se não existirem)
    log_info "Criando diretórios..."
    mkdir -p "${WWW_DIR}/usuarios"
    mkdir -p "${WWW_DIR}/web_usuarios/css"
    mkdir -p "${WWW_DIR}/web_usuarios/app"
    mkdir -p "/var/backups/samba"

    # Copiar arquivos de dados (usuarios/)
    log_info "Copiando scripts e dados..."
    cp -r "${SCRIPT_DIR}/usuarios/"* "${WWW_DIR}/usuarios/"
    log_ok "  → ${WWW_DIR}/usuarios/"

    # Copiar interface web
    log_info "Copiando interface web..."
    cp -r "${SCRIPT_DIR}/web_usuarios/"* "${WWW_DIR}/web_usuarios/"
    log_ok "  → ${WWW_DIR}/web_usuarios/"

    # Criar arquivos de dados vazios
    log_info "Criando arquivos de dados..."
    touch "${WWW_DIR}/usuarios/usuarios_pendentes.txt"
    touch "${WWW_DIR}/usuarios/usuarios_criados.txt"
    log_ok "  → usuarios_pendentes.txt (vazio)"
    log_ok "  → usuarios_criados.txt (vazio)"

    # Criar credenciais do admin a partir do template
    log_info "Configurando credenciais do administrador..."
    if [ -f "${WWW_DIR}/usuarios/usuarios_sistema.example.txt" ]; then
        mv "${WWW_DIR}/usuarios/usuarios_sistema.example.txt" "${WWW_DIR}/usuarios/usuarios_sistema.txt"
    fi
    # Adicionar credenciais (se não existir nenhuma)
    if [ ! -s "${WWW_DIR}/usuarios/usuarios_sistema.txt" ] || grep -q "^#" "${WWW_DIR}/usuarios/usuarios_sistema.txt"; then
        echo "${ADMIN_USUARIO} | ${ADMIN_SENHA}" > "${WWW_DIR}/usuarios/usuarios_sistema.txt"
        log_ok "  → Credenciais configuradas: ${ADMIN_USUARIO}"
    else
        log_aviso "  → Arquivo usuarios_sistema.txt já existe e não será sobrescrito."
    fi

    # Configurar setores (só se o arquivo estiver vazio ou com conteúdo padrão)
    if [ -s "${WWW_DIR}/usuarios/setores.conf" ] && [ "$(wc -l < "${WWW_DIR}/usuarios/setores.conf")" -gt 1 ]; then
        log_aviso "  → setores.conf já existe e não foi sobrescrito."
    else
        log_info "Configurando setores iniciais..."
        > "${WWW_DIR}/usuarios/setores.conf"
        for s in $SETORES_LIST; do
            echo "$s" >> "${WWW_DIR}/usuarios/setores.conf"
        done
        log_ok "  → Setores configurados: ${SETORES_LIST}"
    fi

    log_ok "Arquivos copiados com sucesso!"
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: APLICAR PERMISSÕES
# ──────────────────────────────────────────────────────────────

aplicar_permissoes() {
    cabecalho
    echo -e "${NEGRITO}── Passo 5: Aplicando permissões ──${RESET}"
    echo ""

    log_info "Aplicando permissões nos arquivos..."

    # Pendentes (PHP escreve, Script lê/escreve)
    chown "${WWW_USER}:${WWW_USER}" "${WWW_DIR}/usuarios/usuarios_pendentes.txt" 2>/dev/null || true
    chmod 664 "${WWW_DIR}/usuarios/usuarios_pendentes.txt" 2>/dev/null || true

    # Criados (Script escreve, PHP lê)
    chown "${WWW_USER}:${WWW_USER}" "${WWW_DIR}/usuarios/usuarios_criados.txt" 2>/dev/null || true
    chmod 664 "${WWW_DIR}/usuarios/usuarios_criados.txt" 2>/dev/null || true

    # Setores (PHP escreve via interface web)
    chown "root:${WWW_USER}" "${WWW_DIR}/usuarios/setores.conf" 2>/dev/null || true
    chmod 664 "${WWW_DIR}/usuarios/setores.conf" 2>/dev/null || true

    # Credenciais do admin (mais restrito)
    chown "root:${WWW_USER}" "${WWW_DIR}/usuarios/usuarios_sistema.txt" 2>/dev/null || true
    chmod 640 "${WWW_DIR}/usuarios/usuarios_sistema.txt" 2>/dev/null || true

    # Scripts bash (executáveis como root)
    chown root:root "${WWW_DIR}/usuarios/cria_usuarios.sh" 2>/dev/null || true
    chmod 755 "${WWW_DIR}/usuarios/cria_usuarios.sh" 2>/dev/null || true

    chown root:root "${WWW_DIR}/usuarios/aplicar_compartilhamentos.sh" 2>/dev/null || true
    chmod 755 "${WWW_DIR}/usuarios/aplicar_compartilhamentos.sh" 2>/dev/null || true

    # PHP files
    chown -R "${WWW_USER}:${WWW_USER}" "${WWW_DIR}/web_usuarios/"*.php 2>/dev/null || true
    chmod 644 "${WWW_DIR}/web_usuarios/"*.php 2>/dev/null || true

    # Diretório app/
    find "${WWW_DIR}/web_usuarios/app/" -name "*.php" -exec chown "${WWW_USER}:${WWW_USER}" {} \; 2>/dev/null || true
    find "${WWW_DIR}/web_usuarios/app/" -name "*.php" -exec chmod 644 {} \; 2>/dev/null || true

    # CSS
    find "${WWW_DIR}/web_usuarios/css/" -name "*.css" -exec chown "${WWW_USER}:${WWW_USER}" {} \; 2>/dev/null || true
    find "${WWW_DIR}/web_usuarios/css/" -name "*.css" -exec chmod 644 {} \; 2>/dev/null || true

    # .htaccess
    chown "${WWW_USER}:${WWW_USER}" "${WWW_DIR}/web_usuarios/.htaccess" 2>/dev/null || true
    chmod 644 "${WWW_DIR}/web_usuarios/.htaccess" 2>/dev/null || true

    # Log
    touch /var/log/cria_usuarios.log 2>/dev/null || true
    chmod 644 /var/log/cria_usuarios.log 2>/dev/null || true

    # Backup dir
    chmod 755 /var/backups/samba 2>/dev/null || true

    log_ok "Permissões aplicadas com sucesso!"
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: ATUALIZAR GRUPO PADRÃO NO SCRIPT cria_usuarios.sh
# ──────────────────────────────────────────────────────────────

atualizar_grupo_padrao() {
    log_info "Atualizando grupo padrão no script cria_usuarios.sh..."

    if [ -f "${WWW_DIR}/usuarios/cria_usuarios.sh" ]; then
        sed -i "s/^grupo_padrao='.*'/grupo_padrao='${GRUPO_PADRAO}'/" "${WWW_DIR}/usuarios/cria_usuarios.sh"
        log_ok "  → Grupo padrão alterado para: '${GRUPO_PADRAO}'"
    fi
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: CONFIGURAR CRON
# ──────────────────────────────────────────────────────────────

configurar_cron() {
    if [ "$CONFIGURAR_CRON" != true ]; then
        log_aviso "Configuração de cron pulada (conforme solicitado)."
        return
    fi

    cabecalho
    echo -e "${NEGRITO}── Configurando agendamento automático (cron) ──${RESET}"
    echo ""

    local CRON_LINHA="0 * * * * ${WWW_DIR}/usuarios/cria_usuarios.sh >> /var/log/cria_usuarios.log 2>&1"

    log_info "Adicionando entrada ao crontab do root..."
    (crontab -l 2>/dev/null | grep -v "cria_usuarios.sh"; echo "$CRON_LINHA") | crontab -

    log_ok "Cron configurado! O script será executado automaticamente a cada hora."
    echo "  Comando: ${CRON_LINHA}"
    echo "  Log:     tail -f /var/log/cria_usuarios.log"
    echo ""
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: CONFIGURAR SUDO
# ──────────────────────────────────────────────────────────────

configurar_sudo() {
    if [ "$CONFIGURAR_SUDO" != true ]; then
        log_aviso "Configuração de sudo pulada (conforme solicitado)."
        return
    fi

    cabecalho
    echo -e "${NEGRITO}── Configurando sudo para www-data ──${RESET}"
    echo ""

    local SUDO_FILE="/etc/sudoers.d/samba-gerenciamento"
    local SUDO_LINHA="${WWW_USER} ALL=(root) NOPASSWD: ${WWW_DIR}/usuarios/aplicar_compartilhamentos.sh"

    if [ -f "$SUDO_FILE" ]; then
        log_aviso "Arquivo ${SUDO_FILE} já existe. Verificando conteúdo..."
        if grep -q "aplicar_compartilhamentos.sh" "$SUDO_FILE"; then
            log_ok "Regra sudo já configurada."
            return
        fi
    fi

    log_info "Criando arquivo: ${SUDO_FILE}"
    echo "# Permitir que o Apache (${WWW_USER}) execute o script de compartilhamentos Samba sem senha" > "$SUDO_FILE"
    echo "${SUDO_LINHA}" >> "$SUDO_FILE"
    chmod 440 "$SUDO_FILE"

    log_ok "Sudo configurado com sucesso!"
    echo "  Regra: ${SUDO_LINHA}"
    echo ""
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: VERIFICAR APÓS INSTALAÇÃO
# ──────────────────────────────────────────────────────────────

verificar_instalacao() {
    cabecalho
    echo -e "${NEGRITO}── Verificando instalação ──${RESET}"
    echo ""

    local ERROS=0

    # Apache
    if systemctl is-active --quiet "$SERVICO_APACHE" 2>/dev/null; then
        log_ok "Apache ($SERVICO_APACHE): ativo"
    else
        log_aviso "Apache ($SERVICO_APACHE): parece não estar ativo"
        ((ERROS++))
    fi

    # PHP
    if command -v php &>/dev/null; then
        PHP_VER=$(php -r "echo PHP_VERSION;" 2>/dev/null)
        log_ok "PHP: ${PHP_VER:-versão desconhecida}"
    else
        log_aviso "PHP: não encontrado (php não está no PATH)"
        ((ERROS++))
    fi

    # Samba
    if command -v smbpasswd &>/dev/null; then
        log_ok "Samba (smbpasswd): disponível"
    else
        log_aviso "Samba (smbpasswd): não encontrado"
        ((ERROS++))
    fi

    # Arquivos
    for dir in "${WWW_DIR}/usuarios" "${WWW_DIR}/web_usuarios"; do
        if [ -d "$dir" ]; then
            log_ok "Diretório $dir: OK"
        else
            log_erro "Diretório $dir NÃO ENCONTRADO!"
        fi
    done

    # Arquivos principais
    for arq in "cria_usuarios.sh" "aplicar_compartilhamentos.sh" "setores.conf" "usuarios_sistema.txt" "usuarios_pendentes.txt" "usuarios_criados.txt"; do
        if [ -f "${WWW_DIR}/usuarios/${arq}" ]; then
            log_ok "  ${arq}: OK"
        else
            log_aviso "  ${arq}: NÃO ENCONTRADO"
            ((ERROS++))
        fi
    done

    echo ""

    if [ "$ERROS" -gt 0 ]; then
        log_aviso "Foram encontrados ${ERROS} problema(s). Verifique as mensagens acima."
    else
        log_ok "Tudo verificado com sucesso!"
    fi
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: RESUMO FINAL
# ──────────────────────────────────────────────────────────────

exibir_resumo() {
    cabecalho
    echo -e "${NEGRITO}╔══════════════════════════════════════════════════════════╗${RESET}"
    echo -e "${NEGRITO}║         ✅ INSTALAÇÃO CONCLUÍDA COM SUCESSO!            ║${RESET}"
    echo -e "${NEGRITO}╚══════════════════════════════════════════════════════════╝${RESET}"
    echo ""

    echo -e "  ${ROXO}🌐 Endereço de acesso:${RESET}"
    echo "    http://SEU_SERVIDOR/web_usuarios/"
    echo ""

    echo -e "  ${ROXO}🔐 Credenciais do painel:${RESET}"
    echo "    Usuário: ${NEGRITO}${ADMIN_USUARIO}${RESET}"
    echo "    Senha:   ${NEGRITO}(a senha que você definiu)${RESET}"
    echo ""

    echo -e "  ${ROXO}📂 Diretórios do sistema:${RESET}"
    echo "    Dados:   ${WWW_DIR}/usuarios/"
    echo "    Web:     ${WWW_DIR}/web_usuarios/"
    echo "    Backup:  /var/backups/samba/"
    echo ""

    echo -e "  ${ROXO}⚙️  Configurações aplicadas:${RESET}"
    echo "    Grupo padrão:    ${GRUPO_PADRAO}"
    echo "    Setores iniciais: ${SETORES_LIST}"
    echo "    Cron (automático): $([ "$CONFIGURAR_CRON" == true ] && echo '✅ Ativado' || echo '❌ Não configurado')"
    echo "    Sudo (compartilhamentos): $([ "$CONFIGURAR_SUDO" == true ] && echo '✅ Configurado' || echo '❌ Não configurado')"
    echo ""

    echo -e "  ${ROXO}📋 Para criar usuários manualmente:${RESET}"
    echo "    sudo ${WWW_DIR}/usuarios/cria_usuarios.sh"
    echo ""

    echo -e "  ${ROXO}📋 Para visualizar logs:${RESET}"
    echo "    tail -f /var/log/cria_usuarios.log"
    echo ""

    echo -e "  ${ROXO}🔒 Segurança:${RESET}"
    echo "    • Recomenda-se configurar HTTPS no servidor"
    echo "    • Para maior segurança, gere um hash bcrypt para a senha do admin:"
    echo "      php -r \"echo password_hash('sua_senha', PASSWORD_BCRYPT);\""
    echo "    • Substitua a senha em texto em ${WWW_DIR}/usuarios/usuarios_sistema.txt"
    echo ""

    echo -e "${AMARELO}💡 Dica: Acesse o painel web e adicione mais setores se necessário.${RESET}"
    echo ""
}

# ──────────────────────────────────────────────────────────────
#  FUNÇÃO: PRINCIPAL (orquestra tudo)
# ──────────────────────────────────────────────────────────────

main() {
    # ─── Verificar root ───
    if [ "$EUID" -ne 0 ]; then
        echo ""
        log_erro "Este script precisa ser executado como root (use sudo)."
        echo "  Uso: sudo ./instalar.sh"
        exit 1
    fi

    # ─── Banner ───
    cabecalho
    echo -e "${NEGRITO}Bem-vindo ao instalador do Sistema de Gerenciamento de Usuários!${RESET}"
    echo ""
    echo "  Este script irá guiá-lo pela instalação completa do sistema,"
    echo "  incluindo instalação de dependências, configuração de"
    echo "  permissões e agendamento automático."
    echo ""
    read -r -p "Pressione ENTER para continuar ou Ctrl+C para cancelar..."
    echo ""

    # ─── Passo 1: Verificar arquivos de origem ───
    cabecalho
    echo -e "${NEGRITO}── Passo 1: Verificando arquivos de origem ──${RESET}"
    echo ""
    verificar_arquivos_origem
    echo ""
    read -r -p "Pressione ENTER para continuar..."
    echo ""

    # ─── Detectar distribuição ───
    detectar_distro

    # ─── Passo 2: Instalação de pacotes ───
    instalar_pacotes
    if [ "$INSTALAR_PACOTES" == "sim" ]; then
        executar_instalacao_pacotes
    else
        cabecalho
        log_aviso "Pulando instalação de pacotes. Certifique-se de que os requisitos estão instalados:"
        echo "  - Apache 2.4+ (com mod_rewrite)"
        echo "  - PHP 7.4+ (com suporte a sessions)"
        echo "  - Samba 4.x (com smbpasswd)"
        echo ""
        read -r -p "Pressione ENTER para continuar..."
    fi
    echo ""

    # ─── Passo 3: Perguntar configurações ───
    perguntar_configuracoes

    # ─── Passo 4: Copiar arquivos ───
    copiar_arquivos

    # ─── Passo 5: Atualizar grupo padrão ───
    atualizar_grupo_padrao

    # ─── Passo 6: Aplicar permissões ───
    aplicar_permissoes

    # ─── Passo 7: Configurar cron ───
    configurar_cron

    # ─── Passo 8: Configurar sudo ───
    configurar_sudo

    # ─── Passo 9: Verificar instalação ───
    verificar_instalacao

    # ─── Resumo final ───
    exibir_resumo

    echo ""
    echo -e "${VERDE}Instalação concluída! Acesse http://SEU_SERVIDOR/web_usuarios/ para começar.${RESET}"
    echo ""
}

# ──────────────────────────────────────────────────────────────
#  EXECUTAR
# ──────────────────────────────────────────────────────────────
main
