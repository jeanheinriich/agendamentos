#!/bin/bash
#
#                   ##   ###       ##                               ###
# ######            ##    ##       ##        ######                  ##
# ##   ##                 ##       ##          ##                    ##
# ##   ## ##   ## ####    ##   ######          ##    #####   #####   ##   #####
# ######  ##   ##   ##    ##  ##   ##          ##   ##   ## ##   ##  ##  ##
# ##   ## ##   ##   ##    ##  ##   ##          ##   ##   ## ##   ##  ##   ####
# ##   ## ##  ###   ##    ##  ##   ##          ##   ##   ## ##   ##  ##      ##
# ######   ### ## ###### ####  ######          ##    #####   #####  #### #####
# 
# Ferramentas auxiliares para o desenvolvimento

# ---[ Variáveis globais ]----------------------------------------------
VERSION=0.1.0
SUBJECT=buildtools

# ---[ Definição de cores ]---------------------------------------------
# Utilizado para impressão de cores no terminal.
# ----------------------------------------------------------------------
normal=$'\e[0m'
bold=$'\e[1m'
dim=$'\e[2m'

dft=$'\e[39m'
blk=$'\e[1;30m'
red=$'\e[1;31m'
grn=$'\e[1;32m'
yel=$'\e[1;33m'
blu=$'\e[1;34m'
mag=$'\e[1;35m'
cyn=$'\e[1;36m'
wyt=$'\e[1;37m'
drkblk=$'\e[0;30m'
drkred=$'\e[0;31m'
drkgrn=$'\e[0;32m'
drkyel=$'\e[0;33m'
drkblu=$'\e[0;34m'
drkmag=$'\e[0;35m'
drkcyn=$'\e[0;36m'
drkwyt=$'\e[0;37m'
end=$'\e[0m'

# ---[ Definição de funções ]--------------------------------------------

# Exibe uma mensagem de uso
usage() {
  printf "${wyt}Uso: ${dim}buildtools.sh ${yel}-ihv opção args${end}\n\n"
  printf "${wyt}${dim}As opções podem ser: ${end}\n"
  printf "     css: compila e atualiza todos os CSS\n"
  printf "      js: compila e atualiza todos os scripts\n"
  printf "    libs: compila e atualiza todas as bibliotecas\n"
  printf "   icons: compila e atualiza todos os ícones\n"
  printf "favicons: compila e atualiza todos os favicons\n"
  printf "    dirs: atualiza a estrutura de diretórios e permissões\n"
  printf "     all: executa todas as funções\n"
}

# Exibe uma mensagem de atenção
warning() {
  local text=$1

  printf "${yel}Atenção!\n\n${wyt}${dim}%s${end}\n" "$text"
}

# Exibe uma mensagem de erro
error() {
  local text=$1

  printf "${red}Erro:\n\n${wyt}${dim}%s${end}\n" "$text"
}

# Exibe uma linha informativa e, opcionalmente, o resultado da execução
write() {
  local text=$1
  local result=$2

  if [ $result == 'NONE' ] ; then
    printf "${wyt}[   ]${dim} %s${end}\r" "$text"
  else
    if [ $result == 'ERRO' ] ; then
      printf "${wyt}[${red} ✗ ${wyt}]${dim} %s${end}\n" "$text"
    else
      printf "${wyt}[${grn} ✓ ${wyt}]${dim} %s${end}\n" "$text"
    fi
  fi
}

# Imprime as informações do script
showAppInfo() {
  printf "Build Tools ${dim}- Ferramenta auxiliar de construção${end}\n\n"
  printf "${dim}Versão ${normal}${wyt}%s${end} - Copyright © 2019 - Emerson Cavalcanti\n" $VERSION
}

# Atualiza permissões globais das pastas
updateGlobalPermissions() {
  local step1="Ajustando diretórios globalmente..."
  local step2="Ajustando arquivos globalmente..."
  write "$step1" "NONE"
  find . -type d -exec chmod 755 {} \;
  write "$step1" "OK"

  # Ajustando as permissões de arquivos globalmente
  write "$step2" "NONE"
  find . -type f -exec chmod 644 {} \;
  write "$step2" "OK"
}

# Verifica se os diretórios com dados variáveis estão criados e com as
# devidas permissões
updatePublicDirs() {
  # Definição de pastas públicas
  local publicFolders=(
    "var"
    "var/attachments"
    "var/cache"
    "var/log"
  )
  local message="Atualizando pastas públicas..."

  # Analisa as pastas publicas e garante a correta permissão
  write "$message" "NONE"
  for publicDir in "${publicFolders[@]}"
  do
    # Se o diretório não existir, cria-o
    if [ ! -d $publicDir ]; then
      mkdir $publicDir
    fi
    # Corrige a sua permissão
    chmod 777 $publicDir
    find $publicDir -type d -exec chmod 777 {} \;
    find $publicDir -type f -exec chmod 666 {} \;

    # Verifica se temos o arquivo '.gitkeep', se não cria-o
    local gitKeepFile="$publicDir/.gitkeep"
    if [ ! -f $gitKeepFile ]; then
      touch $gitKeepFile
    fi
    chmod 644 $gitKeepFile

    # Verifica se temos o arquivo 'README.md'
    local readmeFile="$publicDir/README.md"
    if [ ! -f $readmeFile ]; then
      touch $readmeFile
    fi
    chmod 644 $readmeFile
  done
  write "$message" "OK"
}

# Verifica as devidas permissões para executáveis
updateExecutableFiles() {
  local executableFiles=(
    "buildtools.sh"
  )
  local message="Ajustando arquivos com permissão de execução..."

  # Corrigindo as permissões para os arquivos executáveis
  write "$message" "NONE"
  for executableFile in "${executableFiles[@]}"
  do
    chmod +x $executableFile
  done
  write "$message" "OK"
}

# Compila nosso código SASS, resultando no CSS final
buildCSS() {
  local SASS=(
    breadcrumb
    clearfix
    colorselector
    content
    dialog
    fileinput
    flash
    floatingactionbutton
    footer
    form
    loading
    login
    pdf
    responsive
    searchbar
    sidebar
    site
    tabularmenu
  )

  # Se o diretório não existir, cria-o
  if [ ! -d "public/assets/css" ]; then
    mkdir -p "public/assets/css"
  fi
  
  printf "Compilando as bibliotecas em SASS para gerar os CSS:\n"
  for libSASS in ${SASS[*]} 
  do
    compileSASS "$libSASS"
  done
}

# Reduz nosso código Java Script, resultando no JS final
buildJS() {
  printf "Publicando códigos Java Script:\n"
  publishJS "app" "assets/js" "public/assets/js"
  publishJS "site" "assets/js" "public/assets/js"
  publishJS "cookies" "assets/js" "public/assets/js"
  publishJS "calendar" "assets/library/calendar" "public/assets/libs/calendar"
  publishJS "accordion-menu" "assets/library/accordion-menu" "public/assets/libs/accordion-menu"
  publishJS "template.engine" "assets/library/template-engine" "public/assets/libs/template-engine"
  publishJS "extension" "assets/library/extension" "public/assets/libs/extension"
}

# Compila nossas bibliotecas, se necessário e reduz o código
buildLibs() {
  printf "Publicando bibliotecas:\n"
  publishLib "Semantic UI" "assets/library/semantic-ui/dist/*" "public/assets/libs/semantic-ui"
  publishLib "Semantic Requisitions" "assets/library/semantic-requisitions/*" "public/assets/libs/semantic-ui/components/"
  publishLib "jQuery" "node_modules/jquery/dist/*" "public/assets/libs/jquery"
  publishLib "DataTables Base" "node_modules/datatables.net/js/*" "public/assets/libs/datatables"
  publishLib "DataTables Semantic JS" "node_modules/datatables.net-se/js/*.js" "public/assets/libs/datatables"
  publishLib "DataTables Semantic CSS" "node_modules/datatables.net-se/css/*.css" "public/assets/libs/datatables"
  publishLib "DataTables Plugins" "node_modules/datatables.net-plugins/*" "public/assets/libs/datatables/plugins"
  publishLib "DataTables Select Plugin Base" "node_modules/datatables.net-select/js/*" "public/assets/libs/datatables/plugins/select"
  publishLib "DataTables Select Plugin Semantic JS" "node_modules/datatables.net-select-se/js/*.js" "public/assets/libs/datatables/plugins/select"
  publishLib "DataTables Select Plugin Semantic CSS" "node_modules/datatables.net-select-se/css/*.css" "public/assets/libs/datatables/plugins/select"
  publishLib "DataTables Fixed Column Plugin Base" "node_modules/datatables.net-fixedcolumns/js/*" "public/assets/libs/datatables/plugins/fixedColumn"
  publishLib "DataTables Fixed Column Plugin Semantic JS" "node_modules/datatables.net-fixedcolumns-se/js/*.js" "public/assets/libs/datatables/plugins/fixedColumn"
  publishLib "DataTables Fixed Column Plugin Semantic CSS" "node_modules/datatables.net-fixedcolumns-se/css/*.css" "public/assets/libs/datatables/plugins/fixedColumn"
  publishLib "DataTables Handle Errors" "assets/library/datatables/handleErrors/handleErrors.js" "public/assets/libs/datatables/plugins/handleErrors"
  publishLib "DataTables i18n pt-br" "assets/library/datatables/i18n/*.json" "public/assets/libs/datatables/plugins/i18n"
  publishLib "Font Awesome Free" "node_modules/@fortawesome/fontawesome-free/*" "public/assets/libs/fontawesome-free"
  buildAndPublishLib "download" "assets/library/download" "public/assets/libs/download"
  buildAndPublishLib "masked.input" "assets/library/jquery/masked.input" "public/assets/libs/jquery/plugins/masked.input"
  buildAndPublishLib "file.input" "assets/library/jquery/file.input" "public/assets/libs/jquery/plugins/file.input"
  buildAndPublishLib "logo.input" "assets/library/jquery/logo.input" "public/assets/libs/jquery/plugins/logo.input"
  buildAndPublishLib "autocomplete" "assets/library/jquery/autocomplete" "public/assets/libs/jquery/plugins/autocomplete"
  buildAndPublishLib "spinner.input" "assets/library/jquery/spinner.input" "public/assets/libs/jquery/plugins/spinner.input"
  buildAndPublishLib "password.strength.inspector" "assets/library/jquery/password.strength.inspector" "public/assets/libs/jquery/plugins/password.strength.inspector"
  buildAndPublishLib "observe.input" "assets/library/jquery/observe.input" "public/assets/libs/jquery/plugins/observe.input"
  buildAndPublishLib "js.cookie" "assets/library/jquery/js.cookie" "public/assets/libs/jquery/plugins/js.cookie"
  publishLib "Jquery Chart" "assets/library/jquery/chart/*.js" "public/assets/libs/jquery/plugins/chart"
  
  printf "\n"
  printf "Publicando formatadores de colunas personalizados:\n"
  EXT="js"
  for i in assets/library/datatables/formatters/*; do
    if [ "${i}" != "${i%.${EXT}}" ];then
      local name=$(basename $i .js)
      local path=$(dirname "${i}")
      publishJS "$name" "$path" "public/assets/libs/datatables/formatters"
    fi
  done
}

buildIcons() {
  local iconsSite=(
    "about"
    "alarm"
    "bankslip"
    "camerasurveillance"
    "contactus"
    "fuelconsumption"
    "monitoring"
    "privacity"
    "tracking"
  )
  local iconsADM=(
    "accessorytypes"
    "accumulatedvalues"
    "banks"
    "cities"
    "contractors"
    "documenttypes"
    "equipmentbrands"
    "equipmentmodels"
    "features"
    "fieldtypes"
    "fueltypes"
    "genders"
    "holidays"
    "home"
    "indicators"
    "maritalstatus"
    "measuretypes"
    "mobileoperators"
    "monitors"
    "ownershiptypes"
    "password"
    "permissions"
    "phonetypes"
    "privacity"
    "simcardtypes"
    "systemactions"
    "user"
    "users"
    "vehiclecolors"
    "vehicletypes"
    "vehiclesubtypes"
  )
  local iconsERP=(
    "attach"
    "billings"
    "billingtypes"
    "carnet"
    "contracts"
    "contracttypes"
    "customer"
    "customers"
    "definedmethods"
    "deposits"
    "duedays"
    "equipmentbrands"
    "equipmentmodels"
    "equipments"
    "erp"
    "history"
    "home"
    "insertsimcard"
    "installmenttypes"
    "mailingprofiles"
    "monthlycalculations"
    "password"
    "paymentconditions"
    "payments"
    "plans"
    "privacity"
    "rapidresponses"
    "return"
    "simcards"
    "sellers"
    "serviceproviders"
    "serviceorders"
    "suppliers"
    "technician"
    "transfer"
    "user"
    "users"
    "vehiclebrands"
    "vehiclemodels"
    "vehicles"
  )
  local iconsSTC=(
    "cities"
    "customers"
    "drivers"
    "equipments"
    "equipmentmanufactures"
    "erp"
    "home"
    "journeys"
    "roadtrips"
    "password"
    "positions"
    "privacity"
    "syncdrivers"
    "user"
    "vehicles"
    "vehiclebrands"
    "vehiclemodels"
    "vehicletypes"
    "workdays"
  )
  local origin="assets/icons"
  local destination="public/assets/icons"

  printf "Publicando ícones site:\n"
  write "$message" "NONE"
  for icon in "${iconsSite[@]}"
  do
    publishIcon "$icon" "$origin/site" "$destination/site"
  done

  printf "Publicando ícones administração:\n"
  write "$message" "NONE"
  for icon in "${iconsADM[@]}"
  do
    publishIcon "$icon" "$origin/adm" "$destination/adm"
  done

  printf "Publicando ícones aplicativo:\n"
  write "$message" "NONE"
  for icon in "${iconsERP[@]}"
  do
    publishIcon "$icon" "$origin/erp" "$destination/erp"
  done

  printf "Publicando ícones integração STC:\n"
  write "$message" "NONE"
  for icon in "${iconsSTC[@]}"
  do
    publishIcon "$icon" "$origin/stc" "$destination/stc"
  done
}

# Compila nosso FavIcon
buildFavIcon() {
  local size=(16 32 24 48 72 96 144 152 192 196)
  local pathDestination="public/assets/favicon"

  printf "Gerando os FavIcons:\n"
  # Se o diretório não existir, cria-o
  if [ ! -d $pathDestination ]; then
    mkdir -p $pathDestination
  fi
  for resolution in ${size[*]}; do
    size="${resolution}x${resolution}"
    write "$size" "NONE"
    exec 3>&1
    errorMessage=$(inkscape assets/favicon/favicon.svg --export-filename="$pathDestination/favicon-$size.png" --export-overwrite --export-type=png --export-width=$resolution --export-height=$resolution > /dev/null 2>&1)
    exitcode="${?}"
    exec 3>&-
    if [ ${exitcode} == 0 ] ; then
      write "$size" "OK"
    else 
      write "$size" "ERRO"
      printf "${yel}$errorMessage${end}\n\n"
    fi
  done

  write "Otimizando PNGs" "NONE"
  exec 3>&1
  errorMessage=$(pngquant -f --ext .png $pathDestination/favicon-*.png --posterize 4 --speed 1 2>&1 1>&3)
  exitcode="${?}"
  exec 3>&-
  if [ ${exitcode} == 0 ] ; then
    write "Otimizando PNGs" "OK"
  else 
    write "Otimizando PNGs" "ERRO"
    printf "${yel}$errorMessage${end}\n\n"
  fi

  printf "Gerando o ícone padrão:\n"
  write "Gerando arquivo .ICO" "NONE"
  exec 3>&1
  errorMessage=$(convert $(ls -v $pathDestination/favicon-*.png) $pathDestination/favicon.ico 2>&1 1>&3)
  exitcode="${?}"
  exec 3>&-
  if [ ${exitcode} == 0 ] ; then
    write "Gerando arquivo .ICO" "OK"
  else 
    write "Gerando arquivo .ICO" "ERRO"
    printf "${yel}$errorMessage${end}\n\n"
  fi

  local size=(57 60 72 76 114 120 144 152 180)

  printf "Gerando os ícones Apple:\n"
  for resolution in ${size[*]}; do
    size="${resolution}x${resolution}"
    write "$size" "NONE"
    exec 3>&1
    errorMessage=$(inkscape assets/favicon/favicon.svg --export-filename="$pathDestination/apple-touch-icon-$size.png" --export-overwrite --export-type=png --export-width=$resolution --export-height=$resolution > /dev/null 2>&1)
    exitcode="${?}"
    exec 3>&-
    if [ ${exitcode} == 0 ] ; then
      write "$size" "OK"
    else 
      write "$size" "ERRO"
      printf "${yel}$errorMessage${end}\n\n"
    fi
  done

  write "Otimizando PNGs" "NONE"
  exec 3>&1
  errorMessage=$(pngquant -f --ext .png $pathDestination/apple-touch-icon-*.png --posterize 4 --speed 1 2>&1 1>&3)
  exitcode="${?}"
  exec 3>&-
  if [ ${exitcode} == 0 ] ; then
    write "Otimizando PNGs" "OK"
  else 
    write "Otimizando PNGs" "ERRO"
    printf "${yel}$errorMessage${end}\n\n"
  fi

  local size=(36 48 72 96 144 192)

  printf "Gerando os ícones Android:\n"
  for resolution in ${size[*]}; do
    size="${resolution}x${resolution}"
    write "$size" "NONE"
    exec 3>&1
    errorMessage=$(inkscape assets/favicon/favicon.svg --export-filename="$pathDestination/android-icon-$size.png" --export-overwrite --export-type=png --export-width=$resolution --export-height=$resolution > /dev/null 2>&1)
    exitcode="${?}"
    exec 3>&-
    if [ ${exitcode} == 0 ] ; then
      write "$size" "OK"
    else 
      write "$size" "ERRO"
      printf "${yel}$errorMessage${end}\n\n"
    fi
  done

  write "Otimizando PNGs" "NONE"
  exec 3>&1
  errorMessage=$(pngquant -f --ext .png $pathDestination/android-icon-*.png --posterize 4 --speed 1 2>&1 1>&3)
  exitcode="${?}"
  exec 3>&-
  if [ ${exitcode} == 0 ] ; then
    write "Otimizando PNGs" "OK"
  else 
    write "Otimizando PNGs" "ERRO"
    printf "${yel}$errorMessage${end}\n\n"
  fi
  write "Copiando manifest" "NONE"
  exec 3>&1
  errorMessage=$(cp assets/favicon/manifest.json $pathDestination/ 2>&1 1>&3)
  exitcode="${?}"
  exec 3>&-
  if [ ${exitcode} == 0 ] ; then
    write "Copiando manifest" "OK"
  else 
    write "Copiando manifest" "ERRO"
    printf "${yel}$errorMessage${end}\n\n"
  fi
}

# Compila nosso código SASS, resultando no CSS final
compileSASS() {
  local libName=$1

  name="$(tr '[:lower:]' '[:upper:]' <<< ${libName:0:1})${libName:1}"
  write "$name normal" "NONE"
  exec 3>&1
  errorMessage=$(sassc --sourcemap=auto -t expanded assets/scss/$libName/$libName.scss public/assets/css/$libName.css 2>&1 1>&3)
  exitcode="${?}"
  exec 3>&-
  if [ ${exitcode} == 0 ] ; then
    write "$name normal" "OK"
  else 
    write "$name normal" "ERRO"
    printf "${yel}$errorMessage${end}\n\n"
  fi
  write "$name minified" "NONE"
  exec 3>&1
  errorMessage=$(sassc --sourcemap=auto -t compressed assets/scss/$libName/$libName.scss public/assets/css/$libName.min.css 2>&1 1>&3)
  exitcode="${?}"
  exec 3>&-
  if [ ${exitcode} == 0 ] ; then
    write "$name minified" "OK"
  else 
    write "$name minified" "ERRO"
    printf "${yel}$errorMessage${end}\n\n"
  fi
}

# Publica um determinado código JS
publishJS() {
  local scriptName="$1"
  local scriptOrigin="$2"
  local scriptDestination="$3"

  name="$(tr '[:lower:]' '[:upper:]' <<< ${scriptName:0:1})${scriptName:1}"
  write "$name normal" "NONE"
  # Se o diretório não existir, cria-o
  if [ ! -d $scriptDestination ]; then
    mkdir -p $scriptDestination
  fi

  # Copia da origem para o destino
  cp $scriptOrigin/$scriptName.js $scriptDestination/$scriptName.js
  write "$name normal" "OK"

  write "$name minified" "NONE"
  java -jar ~/.local/bin/yuicompressor-2.4.8.jar --type js $scriptOrigin/$scriptName.js -o $scriptDestination/$scriptName.min.js
  write "$name minified" "OK"
}

# Publica uma determinada biblioteca
publishLib() {
  local libraryName="$1"
  local libraryOrigin="$2"
  local libraryDestination="$3"

  write "$libraryName" "NONE"
  # Se o diretório não existir, cria-o
  if [ ! -d $libraryDestination ]; then
    mkdir -p $libraryDestination
  fi
  # Copia da origem para o destino
  cp -r $libraryOrigin $libraryDestination
  write "$libraryName" "OK"
}

# Compila e publica uma determinada biblioteca
buildAndPublishLib() {
  local libraryName="$1"
  local libraryOrigin="$2"
  local libraryDestination="$3"

  # Se o diretório não existir, cria-o
  if [ ! -d $libraryDestination ]; then
    mkdir -p $libraryDestination
  fi

  printf "Compilando $libraryName\n"

  # Verifica se precisamos compilar o SASS
    if [ -f "$libraryOrigin/$libraryName.scss" ]; then
    name="$(tr '[:lower:]' '[:upper:]' <<< ${libraryName:0:1})${libraryName:1}"
    write "CSS $name normal" "NONE"
    exec 3>&1
    errorMessage=$(sassc --sourcemap=auto -t expanded $libraryOrigin/$libraryName.scss $libraryDestination/$libraryName.css 2>&1 1>&3)
    exitcode="${?}"
    exec 3>&-
    if [ ${exitcode} == 0 ] ; then
      write "CSS $name normal" "OK"
    else 
      write "CSS $name normal" "ERRO"
      printf "${yel}$errorMessage${end}\n\n"
    fi
    write "CSS $name minified" "NONE"
    exec 3>&1
    errorMessage=$(sassc --sourcemap=auto -t compressed $libraryOrigin/$libraryName.scss $libraryDestination/$libraryName.min.css 2>&1 1>&3)
    exitcode="${?}"
    exec 3>&-
    if [ ${exitcode} == 0 ] ; then
      write "CSS $name minified" "OK"
    else 
      write "CSS $name minified" "ERRO"
      printf "${yel}$errorMessage${end}\n\n"
    fi
  fi
  
  # Compilar o JS
  publishJS $libraryName $libraryOrigin $libraryDestination

  printf "Concluído $libraryName\n"
}

# Publica um determinado icone
publishIcon() {
  local iconName="$1"
  local iconOrigin="$2"
  local iconDestination="$3"

  write "$iconName" "NONE"
  # Se o diretório não existir, cria-o
  if [ ! -d $iconDestination ]; then
    mkdir -p $iconDestination
  fi
  # Converte do SVG na origem para o destino
  # inkscape $iconOrigin/$iconName.svg --export-png=$iconDestination/$iconName.png -w70 -h70 > /dev/null 2>&1
  # Limpa o SVG existente na origem para gerar o SVG de destino
  exec 3>&1
  errorMessage=$(svgcleaner --quiet $iconOrigin/$iconName.svg $iconDestination/$iconName.svg 2>&1 1>&3)
  exitcode="${?}"
  exec 3>&-
  if [ ${exitcode} == 0 ] ; then
    if [[ $errorMessage == *"Error"* ]]; then
      write "$iconName" "ERRO"
      printf "${yel}$errorMessage${end}\n\n"
    else
      write "$iconName" "OK"
    fi
  else 
    write "$iconName" "ERRO"
    printf "${yel}$errorMessage${end}\n\n"
  fi
}


# ---[ Processamento das opções ]---------------------------------------
while getopts "::vh" optname
do
  case "$optname" in
    "v")
      showAppInfo
      exit 0;
      ;;
    "h")
      usage
      exit 0;
      ;;
    "?")
      error "A opção '-$OPTARG' é desconhecida"
      exit 0;
      ;;
    ":")
      error "Nenhum valor de argumento para a opção $OPTARG"
      exit 0;
      ;;
    *)
      error "Não foi possível processar as opções"
      exit 0;
      ;;
  esac
done

shift $(($OPTIND - 1))

command=$1
param=$2

# ---[ Controle de execução única ]-------------------------------------
LOCK_FILE=/tmp/$SUBJECT.$UID.lock
if [ -f "$LOCK_FILE" ]; then
  warning "Este script já está sendo executado"
  exit 0;
fi

trap "rm -f $LOCK_FILE" EXIT
touch $LOCK_FILE


# ---[ Principal ]------------------------------------------------------

showAppInfo
printf "\n"

case "$command" in
  "css")
    buildCSS
    ;;
  "js")
    buildJS
    ;;
  "libs")
    buildLibs
    ;;
  "icons")
    buildIcons
    ;;
  "favicon")
    buildFavIcon
    ;;
  "dirs")
    updateGlobalPermissions
    updatePublicDirs
    updateExecutableFiles
    ;;
  "all")
    # Executa todas as tarefas
    warning "All"
    ;;
  *)
    error "Informe um comando a ser executado"
    usage
    exit 0;
    ;;
esac

printf "\n${yel}Concluído${end}\n"
