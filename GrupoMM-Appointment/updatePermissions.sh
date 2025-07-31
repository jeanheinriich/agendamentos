#!/bin/bash
#
# Script que ajustas as permissões para o sistema

# Definição de pastas públicas
publicFolders=(
  "var"
  "var/storage"
  "var/storage/attachments"
  "var/storage/images"
  "var/cache"
  "var/log"
)

executableFiles=(
  "UpdatePermissions.sh"
)

echo "Ajustando as permissões do sistema"

# Ajustando as permissões de diretórios globalmente
printf " - Ajustando diretórios globalmente...            "
find . -type d -exec chmod 755 {} \;
printf "\e[92m[ OK ]\e[0m\n"

# Ajustando as permissões de arquivos globalmente
printf " - Ajustando arquivos globalmente...              "
find . -type f -exec chmod 644 {} \;
printf "\e[92m[ OK ]\e[0m\n"

# Corrigindo as permissões para as pastas com permissões de gravação
printf " - Ajustando pastas com permissão de gravação...  "
for publicDir in "${publicFolders[@]}"
do
  chmod 777 $publicDir
  find $publicDir -type d -exec chmod 777 {} \;
  find $publicDir -type f -exec chmod 666 {} \;

  # Verifica se temos o arquivo '.gitkeep'
  if test -f "$publicDir/.gitkeep"; then
    chmod 644 $publicDir/.gitkeep
  fi

  # Verifica se temos o arquivo 'README.md'
  if test -f "$publicDir/README.md"; then
    chmod 644 $publicDir/README.md
  fi
done
printf "\e[92m[ OK ]\e[0m\n"

# Corrigindo as permissões para os arquivos executáveis
printf " - Ajustando arquivos com permissão de execução..."
for executableFile in "${executableFiles[@]}"
do
  chmod +x $executableFile
done
printf "\e[92m[ OK ]\e[0m\n"

printf "\nConcluído\n\n"
