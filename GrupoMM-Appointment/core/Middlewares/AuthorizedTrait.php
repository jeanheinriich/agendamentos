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
 * Essa é uma trait (característica) simples de inclusão de métodos para
 * manipulação das autorizações que outros middlewares podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Middlewares;

use Core\Traits\ExceptionTrait;

trait AuthorizedTrait
{
  /**
   * Os métodos para lidar com exceções e erros em uma requisição
   */
  use ExceptionTrait;

  /**
   * As rotas com acesso sempre autorizado para usuários que estejam
   * autenticados, permitindo uma usabilidade mínima ao sistema. Cada
   * rota deve estar no formato:
   *   [ routename => [ Métodos HTTP ] ]
   * 
   * @var array
   */
  protected $authorizedRoutes = [];

  /**
   * Verifica se o usuário está autenticado e não possui nenhum bloqueio
   * 
   * @return boolean
   *   O indicativo de autenticação do usuário
   */
  protected function hasLoggedIn():bool {
    // Certifica-se de que o serviço de autorização está disponível
    if ($this->has('authorization')) {
      // Primeiro, verificamos se o usuário está autenticado
      if ($this->authorization->hasLoggedIn()) {
        // Verifica outras condições de bloqueio
        if ($this->authorization->hasBlocked()) {
          // O usuário encontra-se bloqueado
          
          // Recupera suas informações
          $user = $this->authorization->getUser();

          // Adiciona uma mensagem relatando o erro
          $this->addError("A conta do usuário {$user->username} "
            . "encontra-se bloqueada", "Sua conta encontra-se bloqueada"
          );

          return false;
        }

        if ($this->authorization->hasExpired()) {
          // O acesso do usuário encontra-se expirado
          
          // Recupera as informações do usuário
          $user = $this->authorization->getUser();

          // Recupera a data de expiração
          $expiresat = $this->formatSQLDate($user->expiresat);

          // Adiciona uma mensagem relatando o erro
          $this->addError("A conta do usuário {$user->username} "
            . "expirou em {$expiresat}", "Sua conta expirou em "
            . "{$expiresat}"
          );

          return false;
        }

        return true;
      } else {
        // Adiciona uma mensagem relatando o erro
        $this->addError("O usuário não está autenticado",
          "Você precisa se autenticar antes de prosseguir"
        );
      }
    }

    return false;
  }

  /**
   * Verifica se uma determinada rota está dentro das rotas autorizadas
   * para usuários autenticados
   * 
   * @param string $routeName
   *   O nome da rota
   * @param string $httpMethod
   *   O método HTTP
   * @return bool
   *   O indicativo se a rota é uma das rotas autorizadas
   */
  protected function routeInAuthorizedRoutes(string $routeName,
    string $httpMethod):bool {
    // Percorre a tabela de rotas autorizadas, validando
    foreach ($this->authorizedRoutes as $nameOfRoute => $methods) {
      if ($routeName === $nameOfRoute) {
        if (is_array($methods)) {
          foreach ($methods as $method) {
            if ( ($method === $httpMethod) ||
                 ($method === 'ANY') ) {
              return true;
            }
          }
        }
      }
    }

    return false;
  }
  
  /**
   * Determina se o usuário possui autorização para ascender à rota
   * informada.
   * 
   * @param string $routeName
   *   O nome da rota
   * @param string $httpMethod
   *   O método HTTP da requisição
   * 
   * @return bool
   *   O indicativo de autorização para esta rota
   */
  protected function hasAuthorizationFor(string $routeName,
    string $httpMethod):bool
  {
    // Verifica se a rota está dentro das rotas autorizadas
    if ($this->routeInAuthorizedRoutes($routeName, $httpMethod)) {
      return true;
    }

    // Verifica se o usuário possui permissão para a rota
    if ($this->authorization->getAuthorizationFor($routeName,
      $httpMethod)) {
      return true;
    } else {
      // Recupera as informações do usuário
      $user = $this->authorization->getUser();

      // Adiciona uma mensagem relatando o erro
      $this->addError("Bloqueado acesso à '{$routeName}' usando o "
        . "método HTTP {$httpMethod}", "A conta do usuário "
        . "'{$user->username}' não possui permissão para acessar o "
        . "conteúdo da rota '{$routeName}'"
      );
    }

    return false;
  }
}
