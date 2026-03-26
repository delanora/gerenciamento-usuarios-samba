#!/bin/bash
# Finalidade:   Criar usuarios para o SAMBA
#-------------------------------------------------------------------------------------------

mydir='/var/www/html/usuarios'
cd $mydir

grupo_padrao='cliente'
arquivo_pendentes="$mydir/usuarios_pendentes.txt"
arquivo_criados="$mydir/usuarios_criados.txt"

# Verificar se está rodando como root
#-------------------------------------------------------------------------------------------
if [ "$EUID" -ne 0 ]; then 
        echo "ERRO: Este script precisa ser executado como root (use sudo)"
        exit 1
fi

# Verificar se o arquivo de pendentes existe
#-------------------------------------------------------------------------------------------
if [ ! -f "$arquivo_pendentes" ]; then
        echo "ERRO: Arquivo de pendentes não encontrado: $arquivo_pendentes"
        exit 1
fi

# Criar arquivo de criados se não existir
touch "$arquivo_criados"

# Tratamento do grupo padrao
#-------------------------------------------------------------------------------------------
# Verificar se o grupo padrão existe no LINUX
verifica=$(cat /etc/group | grep "^${grupo_padrao}:")

if [ -z "$verifica" ] ; then
        # Inserir grupo no LINUX
        echo "-->> Inserindo GRUPO PADRAO"              
        groupadd $grupo_padrao
fi

# Ler arquivos de pendentes e processar cada usuário
#-------------------------------------------------------------------------------------------
# Criar arquivo temporário para armazenar pendentes restantes
temp_pendentes=$(mktemp)

while IFS= read -r usuario || [ -n "$usuario" ]; do
        # Ignorar linhas vazias
        [ -z "$usuario" ] && continue
        
        # Remover espaços em branco
        usuario=$(echo "$usuario" | tr -d '[:space:]')
        
        # Extrair setor do login (tudo após o ponto)
        setor=$(echo "$usuario" | cut -d'.' -f2-)
        
        if [ -z "$setor" ] ; then
                echo "ERRO: Login inválido (sem setor): $usuario"
                # Manter na lista de pendentes se houver erro
                echo "$usuario" >> "$temp_pendentes"
                continue
        fi
        
        # Verificar se o usuário já existe no LINUX
        verifica_usuario_no_linux=$(cat /etc/passwd | grep "^${usuario}:")
        
        if [ -z "$verifica_usuario_no_linux" ] ; then
                # Gerar senha no formato: primeiros 2 caracteres do usuario + @ + 4 caracteres aleatórios
                senha="$(echo $usuario | cut -c 1,2)@$(date +%N | md5sum | cut -c 1-4)"
                echo -e "$usuario | $senha" >> $arquivo_criados
                
                echo "-->> Inserindo usuário no LINUX: $usuario"          
                useradd -s /bin/false $usuario
                
                echo "-->> Atualizando usuário no SAMBA: $usuario"                
                (echo $senha; echo $senha) | smbpasswd -s -a $usuario
                
                # Habilitar usuário no Samba
                smbpasswd -e $usuario > /dev/null 2>&1
        fi

        # Tratamento de 'grupos'
        #-------------------------------------------------------------------------------------------
        # Verificar se o usuario está no grupo padrão do LINUX
        verifica=$(cat /etc/group | grep "^${grupo_padrao}:" | grep "$usuario")

        if [ -z "$verifica" ] ; then
                echo "-->> Inserindo USUARIO no GRUPO PADRAO: $usuario"          
                usermod -a -G $grupo_padrao $usuario
        fi
        
        # Verificar se o grupo do setor existe
        verifica_grupo_setor=$(cat /etc/group | grep "^${setor}:")
        
        if [ -z "$verifica_grupo_setor" ] ; then
                echo "-->> Inserindo GRUPO SETOR: $setor"
                groupadd $setor
        fi
        
        # Verificar se o usuario está no grupo do setor
        verifica=$(cat /etc/group | grep "^${setor}:" | grep "$usuario")

        if [ -z "$verifica" ] ; then
                echo "-->> Inserindo USUARIO no GRUPO SETOR: $usuario -> $setor"          
                usermod -a -G $setor $usuario
        fi
        
        # Usuário processado com sucesso - NÃO adicionar ao arquivo temporário
        # (será removido da lista de pendentes)

done < "$arquivo_pendentes"

# Substituir arquivo de pendentes pelo arquivo temporário
mv "$temp_pendentes" "$arquivo_pendentes"
chown www-data:www-data $arquivo_pendentes
