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
 * O controlador da página inicial do aplicativo de relatórios do
 * sistema STC desenvolvido para atender clientes específicos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\STC;

use Core\Controllers\Controller;
use Slim\Http\Request;
use Slim\Http\Response;

class MainController
  extends Controller
{
  /**
   * Exibe a página inicial.
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
      $this->path('STC\Home')
    );

    // Registra o acesso
    $this->debug("Acesso à página inicial.");

    // Renderiza a página
    return $this->render($request, $response, 'stc/home.twig');
  }
  
  /**
   * Exibe a página de apresentação do sistema de integração STC.
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
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Sobre',
      $this->path('STC\About')
    );

    // Registra o acesso
    $this->debug("Acesso à página sobre.");
    
    // Renderiza a página
    return $this->render($request, $response, 'stc/about.twig');
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
      $this->path('STC\Home')
    );
    $this->breadcrumb->push('Política de privacidade',
      $this->path('STC\Privacity')
    );

    // Registra o acesso
    $this->debug("Acesso à página de controle de privacidade.");
    
    // Renderiza a página
    return $this->render($request, $response, 'stc/privacity.twig');
  }
}
