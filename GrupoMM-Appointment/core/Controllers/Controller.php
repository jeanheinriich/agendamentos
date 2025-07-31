<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the 
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject
 * to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * Uma classe abstrata para servir como base para controladores do
 * aplicativo.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Controllers;

use Core\Flash\FlashTrait;
use Core\Logger\LoggerTrait;
use Core\Traits\ApplicationTrait;
use Core\Traits\ContainerTrait;
use Core\Traits\RouterTrait;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class Controller
{
  /**
   * Os métodos para manipulação do container
   */
  use ContainerTrait;

  /**
   * Os métodos para registro de eventos em Logs
   */
  use LoggerTrait;

  /**
   * Os métodos para manipulação dos aplicativos
   */
  use ApplicationTrait;
  
  /**
   * Os métodos para manipulação das rotas
   */
  use RouterTrait;
  
  /**
   * Os métodos para renderizar páginas
   */
  use RenderTrait;

  /**
   * Os métodos para envio de mensagens Flash
   */
  use FlashTrait;

  /**
   * A instância do aplicativo.
   * 
   * @var Core\Application
   */
  protected $app;

  /**
   * Rotas para as quais não devemos redirecionar após o login, pois são
   * rotas que pertencem à autenticação e/ou a página inicial.
   * 
   * @var array
   */
  protected $unredirectRoutes = [
    'Home', 'Login', 'Logout'
  ];

  /**
   * O construtor do nosso controlador.
   * 
   * @param ContainerInterface $container
   *   A estrutura que contém os containers da aplicação
   * @param Core\Application $app
   *   O acesso à aplicação
   */
  public function __construct(ContainerInterface $container, $app)
  {
    $this->container = $container;
    $this->app       = $app;
    unset($container);
    unset($app);
  }

  /**
   * Verifica se é capaz de redirecionar para a rota informada.
   * 
   * @param ServerRequestInterface $request
   *   A requisição
   * @param string $routeName
   *   O nome da rota para a qual desejamos redirecionar
   * 
   * @return bool
   */
  protected function ableRedirectToRoute(
    ServerRequestInterface $request,
    string $routeName
  ): bool
  {
    // Recupera as informações do aplicativo para a rota em que estamos
    $uri  = $request->getUri();
    $path = $uri->getPath();
    $app  = trim($this->getApplication($path), '/');

    // Verifica se a parte inicial do nome da rota coincide com o da
    // aplicação à qual estamos
    if (strncmp($routeName, $app, strlen($app)) === 0) {
      foreach ($this->unredirectRoutes as $unredirectRouteName) {
        // Acrescentamos o nome da aplicação, se necessário
        $unredirectRouteName = sprintf("%s%s",
          (trim($app) !== '') ? '' : $app . '/',
          $unredirectRouteName
        );

        // Se a rota pertencer às rotas de autenticação e/ou a página
        // inicial, não redirecionamos
        if ($routeName === $unredirectRouteName) {
          return false;
        }
      }

      return true;
    }

    return false;
  }
}
