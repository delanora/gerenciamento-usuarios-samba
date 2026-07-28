#!/bin/bash
# Finalidade:   Aplicar configuração de compartilhamentos Samba
# Uso:          sudo ./aplicar_compartilhamentos.sh
# Descrição:    Lê o arquivo de staging gerado pela interface web,
#               valida com testparm, cria backup, aplica e recarrega o Samba.
#-------------------------------------------------------------------------------------------

mydir='/var/www/html/usuarios'
staging_file='/var/backups/samba/compartilhamentos_staging.conf'
smb_conf='/etc/samba/smb.conf'
backup_dir='/var/backups/samba'
log_file='/var/log/cria_usuarios.log'

# Função para log
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$log_file"
}

# Verificar se está rodando como root
if [ "$EUID" -ne 0 ]; then
    log "ERRO: Este script precisa ser executado como root (use sudo)"
    exit 1
fi

# Verificar se o arquivo de staging existe
if [ ! -f "$staging_file" ]; then
    log "ERRO: Arquivo de staging não encontrado: $staging_file"
    exit 1
fi

# Verificar se o arquivo de staging não está vazio
if [ ! -s "$staging_file" ]; then
    log "ERRO: Arquivo de staging está vazio"
    rm -f "$staging_file"
    exit 1
fi

# Validar configuração com testparm
log "Validando configuração com testparm..."
testparm -s "$staging_file" > /dev/null 2>&1
if [ $? -ne 0 ]; then
    log "ERRO: Configuração inválida! testparm reportou erros:"
    testparm -s "$staging_file" >> "$log_file" 2>&1
    rm -f "$staging_file"
    exit 1
fi
log "Configuração validada com sucesso."

# Extrair paths da nova configuração e verificar existencia
log "Verificando diretórios dos compartilhamentos..."
grep -i '^\s*path\s*=' "$staging_file" | while IFS='=' read -r chave valor; do
    caminho=$(echo "$valor" | tr -d '[:space:]' | sed "s/^['\"]//; s/['\"]$//")
    if [ -n "$caminho" ] && [ ! -d "$caminho" ]; then
        log "AVISO: Diretório '$caminho' não existe. O compartilhamento será criado, mas o diretório precisará ser criado manualmente."
    fi
done

# Criar backup
mkdir -p "$backup_dir"
timestamp=$(date +%Y%m%d_%H%M%S)
backup_file="$backup_dir/smb.conf.bak.$timestamp"
cp "$smb_conf" "$backup_file"
chmod 640 "$backup_file"
log "Backup criado: $backup_file"

# Manter apenas os 30 backups mais recentes
find "$backup_dir" -name 'smb.conf.bak.*' -type f | sort -r | tail -n +31 | xargs -r rm -f
log "Backups antigos limpos (mantidos os 30 mais recentes)."

# Aplicar nova configuração
cp "$staging_file" "$smb_conf"
chmod 644 "$smb_conf"
log "Nova configuração aplicada em $smb_conf."

# Testar novamente a configuração ativa
testparm -s > /dev/null 2>&1
if [ $? -ne 0 ]; then
    log "ERRO: Falha na validação pós-aplicação. Restaurando backup..."
    cp "$backup_file" "$smb_conf"
    log "Backup restaurado de: $backup_file"
    rm -f "$staging_file"
    exit 1
fi

# Recarregar configuração sem derrubar conexões ativas
log "Recarregando configuração do Samba..."
smbcontrol all reload-config 2>/dev/null
reload_exit=$?

if [ $reload_exit -ne 0 ]; then
    log "Reload não disponível, reiniciando serviço (conexões serão perdidas)..."
    systemctl restart smbd
    if [ $? -ne 0 ]; then
        log "ERRO: Falha ao reiniciar smbd. Restaurando backup..."
        cp "$backup_file" "$smb_conf"
        systemctl restart smbd 2>/dev/null
        log "Backup restaurado de: $backup_file"
        rm -f "$staging_file"
        exit 1
    fi
    log "Serviço Samba reiniciado com sucesso."
else
    log "Configuração recarregada sem desconexões."
fi

# Limpar arquivo de staging
rm -f "$staging_file"
log "Arquivo de staging removido."

log "Configuração de compartilhamentos aplicada com sucesso!"
exit 0
