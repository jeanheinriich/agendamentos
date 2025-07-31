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
 * As configurações de renderização dos templates.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

return [ 'renderer' => [
    'templatePath' => $app->getRootDir() . '/app/views',
    'pdf' => [
      'cache' => $app->getCacheDir() . '/pdf/',
      'fonts' => $app->getRootDir() . '/var/fonts'
    ],
    'twig' => [
      'cache' => $app->getCacheDir() . '/renderer/',
      'debug' => (($app->isDevelopment())
        ? true
        : false),
      'auto_reload' => (($app->isDevelopment())
        ? true
        : false),
      'minifier' => [
        'enabled' => (($app->isDevelopment())
          ? false
          : true),
        'flaggedComments' => true
      ]
    ]
  ]
];
