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
 * O controlador da página inicial da administração do aplicativo de
 * ERP de controle de rastreadores.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM;

use Core\Controllers\Controller;
use Slim\Http\Request;
use Slim\Http\Response;

class MainController
  extends Controller
{
  /**
   * Exibe a página inicial da administração.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function home(Request $request, Response $response)
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );

    // Registra o acesso
    $this->debug("Acesso à página inicial.");
    
    // Renderiza a página
    return $this->render($request, $response, 'adm/home.twig');
  }
  
  /**
   * Exibe a página de apresentação do sistema de ERP.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function about(Request $request, Response $response)
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Sobre',
      $this->path('ADM\About')
    );

    // Registra o acesso
    $this->debug("Acesso à página sobre.");
    
    // Renderiza a página
    return $this->render($request, $response, 'adm/about.twig');
  }
  
  /**
   * Exibe a página de controle de privacidade.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function privacity(Request $request, Response $response)
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Política de privacidade',
      $this->path('ADM\Privacity')
    );

    // Registra o acesso
    $this->debug("Acesso à página de controle de privacidade.");
    
    // Renderiza a página
    return $this->render($request, $response, 'adm/privacity.twig');
  }
}
