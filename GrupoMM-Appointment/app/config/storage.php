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
 * As configurações de armazenamento.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

// Configurações de armazenamento de arquivos
return [ 'storage' => [
    // As informações do diretório de armazenamento de imagens
    'images' => $app->getStorageDir() . DIRECTORY_SEPARATOR . 'images',

    // As informações do diretório de armazenamento de anexos
    'attachments' => $app->getStorageDir() . DIRECTORY_SEPARATOR . 'attachments',

    // As informações do diretório de armazenamento de conciliação bancária
    'conciliations' => $app->getStorageDir() . DIRECTORY_SEPARATOR . 'conciliations'
  ]
];
