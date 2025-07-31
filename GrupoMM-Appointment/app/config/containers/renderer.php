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
 * As configurações do container de controle do sistema de renderização
 * de templates.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

use Core\Twig\AddressExtension;
use Core\Twig\AssetExtension;
use Core\Twig\BooleanFilter;
use Core\Twig\BreadcrumbExtension;
use Core\Twig\BuildURLExtension;
use Core\Twig\CSRFExtension;
use Core\Twig\HTMLMinifierExtension;
use Core\Twig\ImageBase64Extension;
use Core\Twig\JSMinifierExtension;
use Core\Twig\JsonFilter;
use Core\Twig\LocalizedDateFilter;
use Core\Twig\ToWordsExtension;
use Core\Twig\ValidatorExtension;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Twig\Extension\DebugExtension;

// View
// O sistema de renderização das views usando templates Twig
$container['renderer'] = function ($container) {
  $config = $container['settings']['renderer'];
  $twig = new Twig($config['templatePath'], $config['twig']);

  // -------------------------------------[ Adiciona as extensões ]-----
  
  // Extensão que adiciona as funções urlFor, baseURL, siteURL e
  // currentURL
  $twig->addExtension(new TwigExtension(
    $container['router'],
    $container['request']->getUri())
  );

  // Adiciona a extensão de depuração que permite o uso da função 'dump'
  $twig->addExtension(new DebugExtension());

  // A extensão 'Asset' que permite carregar conteúdos externos
  $twig->addExtension(new AssetExtension($container['request']));

  $twig->addExtension(new BreadcrumbExtension($container['breadcrumb']));
  $twig->addExtension(new BuildURLExtension(
    $container['router'],
    $container['request']->getUri()));
  $twig->addExtension(new CSRFExtension($container['csrf']));
  $twig->addExtension(new ImageBase64Extension(__DIR__ . '/public'));

  // Extensão para permitir colocar informações de contato nos templates
  $addresses = $container->get('settings')['addresses'];
  $twig->addExtension(new AddressExtension($addresses));

  // Extensão para permitir transcrever um valor para a sua representação
  // por extenso
  $twig->addExtension(new ToWordsExtension());

  $twig->addExtension(new ValidatorExtension($container['validator']));
  $twig->addExtension(new JSMinifierExtension($config['twig']['minifier']));
  $twig->addExtension(new HTMLMinifierExtension($config['twig']['minifier']));

  // Adiciona os filtros
  $twig->addExtension(new BooleanFilter());
  $twig->addExtension(new LocalizedDateFilter());
  $twig->addExtension(new JsonFilter());

  // Adiciona o nome do domínio na renderização
  $domainName = $container['settings']['domainName'];
  $twig->getEnvironment()->addGlobal('domainName', $domainName);

  // Adiciona as mensagens Flash
  $twig->getEnvironment()->addGlobal('flash',
    $container->flash);

  // Adiciona a URL corrente
  $twig->getEnvironment()->addGlobal('CurrentURL',
    $container->get('request')->getUri()->getPath());

  // Adiciona os tipos de arquivos permitidos em campos do tipo arquivo
  // TODO: Verificar erro com formulários usando encode multipart/form-data
  //       quando se realiza o envio de arquivos ZIP e o CSRF
  $acceptFileFormats = [
    'image/png',
    'image/jpeg',
    //'image/svg+xml',
    //'application/zip',
    //'application/x-rar-compressed',
    'application/msword',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.oasis.opendocument.spreadsheet',
    'application/pdf'
  ];
  $twig->getEnvironment()->addGlobal('AcceptFileFormats',
    $acceptFileFormats);

  // Adiciona o sistema de autorização na view
  $twig->getEnvironment()->addGlobal('authorization', $container['authorization']);

  return $twig;
};
