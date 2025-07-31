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
 * Essa é uma trait (característica) simples de abstração da manipulação
 * de caminhos (path) em função da aplicação que as classes de
 * manipulação de erros podem incluir. Ela permite definir arquivos de
 * templates separados e/ou endereços de página inicial em função do
 * aplicativo ao qual o caminho pertence.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti (at) gmail (dot) com>
 */

namespace Core\Traits;

use Core\Exceptions\TemplateNotFoundException;

trait ApplicationTrait
{
  /**
   * Recupera a sub-aplicação à qual pertence a rota informada, e/ou
   * '/' se a mesma for da aplicação principal.
   * 
   * @param string $path
   *   A URL que se deseja analisar
   * 
   * @return string
   *   A sub-aplicação 
   */
  public function getApplication(string $path): string
  {
    // Separamos nosso caminho em partes
    $partsOfURL = explode("/", trim($path, "/"));
    $result = '/';

    // Recuperamos as informações de aplicações definidas
    $apps = [
      '/' => '*'
    ];
    if ($this->has('settings')) {
      if ($this->settings->has('applications')) {
        $apps = $this->settings['applications'];
      }
    }

    if (count($partsOfURL) > 0) {
      if (array_key_exists(strtolower('/' . $partsOfURL[0]), $apps)) {
        $result = $result . strtolower($partsOfURL[0]);
      }
    }

    return $result;
  }

  /**
   * Recupera se o endereço informado pertence à uma das páginas da área
   * pública do site, e que não precisam de autenticação para acesso.
   * 
   * @param string $path
   *   O caminho (url) para o recurso
   * 
   * @return boolean
   *   O indicativo de se a URL pertence às páginas da área pública
   */
  protected function belongsToPublicArea(string $path): bool
  {
    // Separamos nosso caminho em partes
    $partsOfURL = explode("/", trim($path, "/"));
    $result = false;

    // Recuperamos as informações de aplicações definidas
    $apps = [
      '/' => '*'
    ];
    if ($this->has('settings')) {
      if ($this->settings->has('applications')) {
        $apps = $this->settings->get('applications');
      }
    }

    if (count($partsOfURL) > 0) {
      $app = '/' . strtolower($partsOfURL[0]);
      if (array_key_exists($app, $apps)) {
        // O caminho pertence à uma aplicação
        if (is_array($apps[$app])) {
          // A aplicação possui uma ou mais páginas públicas, então
          // analisa se temos alguma página informada
          if (count($partsOfURL) > 1) {
            // Retorna se a página for uma das páginas definidas como
            // sendo públicas nesta aplicação
            $result = in_array($partsOfURL[1], $apps[$app]);
          }
        } else {
          // Retorna se a aplicação definiu todas as páginas como
          // públicas
          $result = ($apps[$app] === '*');
        }
      }
    } else {
      $result = ($apps['/'] === '*');
    }

    return $result;
  }
  
  /**
   * Determina o endereço da página inicial em função da parte do
   * sistema em que estamos. Se o endereço pertencer a uma aplicação
   * então o caminho inicial será o caminho inicial desta aplicação.
   * 
   * @param string $path
   *   O caminho (url)
   * 
   * @return string
   *   O caminho para a página inicial
   */
  protected function determineHomeAddress(string $path): string
  {
    $homeAddress = strtoupper(trim($this->getApplication($path), '/'))
      . '\Home'
    ;
    return trim($homeAddress, '\\');
  }

  /**
   * Determina o endereço da página de login em função da parte do
   * sistema em que estamos. Se o endereço pertencer a uma aplicação
   * então o login será o caminho inicial desta aplicação seguido de
   * 'login'.
   * 
   * @param string $path
   *   O caminho (url)
   * 
   * @return string
   *   O caminho para a página de login
   */
  protected function determineLoginAddress(string $path): string
  {
    $baseAddress = strtoupper(trim($this->getApplication($path), '/'));

    return trim($baseAddress . '\Login', '\\');
  }
}
