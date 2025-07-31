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
 * As configurações globais do aplicativo Slim para execução de tarefas
 * em linha de comando.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

return [
  'determineRouteBeforeAppMiddleware' => true,
  'displayErrorDetails' => $app->environment['developmentMode'],
  'outputBuffering' => false,
  'addContentLengthHeader' => false // Permitir que o servidor Web
                                    // envie o cabeçalho com o tamanho
                                    // do conteúdo
];
