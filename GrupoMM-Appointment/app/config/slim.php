<?php
/*
 * Este arquivo é parte do Sistema de ERP do Grupo M&M
 *
 * (c) Grupo M&M
 *
 * Para obter informações completas sobre direitos autorais e licenças,
 * consulte o arquivo LICENSE que foi distribuído com este código-fonte.
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * As configurações globais do aplicativo Slim.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

return [
  // A versão de protocolo usada pelo objeto Response. O padrão é '1.1'
  'httpVersion' => '2.0',
  // Tamanho de cada pedaço lido do corpo de resposta ao enviar para o
  // navegador. O padrão é 4096
  'responseChunkSize' => 4096,
  // Se falso, então nenhum buffer de saída é ativado. Se 'append' ou
  // 'prepend', então quaisquer declarações de eco ou impressão são
  // capturadas e anexadas sucedidas ou precedidas à resposta retornada
  // da rota que pode ser chamada. O padrão é 'append', porém
  // desativamos no modo console
  'outputBuffering' => $app->getSAPI() === 'cmd' ? false : 'append',
  // Quando verdadeiro, a rota é calculada antes de qualquer middleware
  // ser executado. Isto significa que você pode inspecionar os
  // parâmetros da rota no middleware, se necessário. O padrão é 'false'
  'determineRouteBeforeAppMiddleware' => true,
  // Quando verdadeiro, informações adicionais sobre exceções são
  // exibidas pelo manipulador de erros padrão. Quando o ambiente é de
  // desenvolvimento, setamos como 'true', quando for de produção
  // setamos como 'false'
  'displayErrorDetails' => $app->isDevelopment() ? true : false,
  // Quando verdadeiro, Slim irá adicionar um cabeçalho Content-Length à
  // resposta. Se você estiver usando uma ferramenta analítica de tempo
  // de execução, como New Relic, então ela deve ser desativada. O
  // padrão é 'true'
  'addContentLengthHeader' => false,
  // Nome de arquivo para o caching das rotas FastRoute. Deve ser
  // definido para um nome de arquivo válido dentro de um diretório
  // gravável. Se o arquivo não existe, então ele é criado com as
  // informações corretas do cache na primeira execução. Defina para
  // false para desabilitar o sistema de cache FastRoute. O padrão é
  // 'false'.
  'routerCacheFile' => $app->getCacheDir() . '/routes.php'
];
