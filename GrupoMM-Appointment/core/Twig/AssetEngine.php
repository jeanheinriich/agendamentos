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
 * Classe responsável por permitir incluir os recursos externos nos
 * templates Twig.
 */

namespace Core\Twig;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Http\Uri;

class AssetEngine
{
  // A pasta base
  protected $basePath;
  
  // A requisição
  protected $request;
  
  /**
   * O construtor de nossa engine.
   * 
   * @param Request $request
   *   A requisição originária.
   * @param string $basePath
   *   O path base
   */
  public function __construct(Request $request, ?string $basePath = null)
  {
    $this->request = $request;
    
    if (empty($basePath)) {
      $this->basePath = 'assets';
    } else {
      $this->basePath = 'assets/' . trim($basePath, '/');
    }
  }

  /**
   * Recupera o diretório para os recursos (Assets), e incluí no
   * endereço retornado.
   * 
   * @param string $resource
   *   O arquivo do recurso que se deseja
   * 
   * @return string
   *   O endereço do recurso
   */
  public function asset(string $resource): string
  {
    /** @var Uri $uri */
    $uri = $this->request->getUri();
    return $uri->getBaseUrl()
      . '/' . $this->basePath
      . '/' . $resource
    ;
  }

  /**
   * Recupera o diretório para os arquivos de internacionalização, e
   * incluí no endereço retornado.
   * 
   * @param string $resource
   *   O arquivo de internacionalização
   * 
   * @return string
   *   O endereço do recurso
   */
  public function i18n(string $resource): string
  {
    return ''
      . '/' . $this->basePath
      . '/libs/' . $resource
    ;
  }

  /**
   * Renderiza uma tag para importação de código CSS.
   * 
   * @param string $resource
   *   O nome do arquivo de recurso com o conteúdo CSS
   * 
   * @return string
   *   O elemento de importação renderizado
   */
  public function css(string $resource): string
  {
    if (strpos($resource, '/')) {
      // For fornecido o caminho do CSS, então precisa decompor para
      // permitir a correta análise
      $parts    = pathinfo($resource);
      $resource = basename($resource);
      $local    = $parts['dirname'];
    } else {
      $local    = 'css';
    }

    $tag ='<link rel="stylesheet" type="text/css" href="%s">';
    /** @var Uri $uri */
    $uri = $this->request->getUri();
    $content = $uri->getBaseUrl()
      . '/' . $this->basePath
      . '/' . $local
      . '/'. $resource
    ;

    return sprintf($tag, $content);
  }

  /**
   * Renderiza uma tag para importação de código Java Script.
   * 
   * @param string $resource
   *   O nome do arquivo de recurso com o conteúdo JS
   * 
   * @return string
   *   O elemento de importação renderizado
   */
  public function js(string $resource): string
  {
    $tag ='<script type="text/javascript" src="%s"></script>';
    /** @var Uri $uri */
    $uri = $this->request->getUri();
    $content = $uri->getBaseUrl()
      . '/' . $this->basePath
      . '/js/' . $resource
    ;

    return sprintf($tag, $content);
  }

  /**
   * Renderiza uma tag para importação de biblioteca Java Script.
   * 
   * @param string $resource
   *   O nome do arquivo de recurso com o conteúdo JS dentro da pasta
   *   'lib'
   * 
   * @return string
   *   O elemento de importação renderizado
   */
  public function lib(string $resource): string
  {
    $tag ='<script type="text/javascript" src="%s"></script>';
    /** @var Uri $uri */
    $uri = $this->request->getUri();
    $content = $uri->getBaseUrl()
      . '/' . $this->basePath
      . '/libs/' . $resource
    ;

    return sprintf($tag, $content);
  }

  /**
   * Recupera a tag para o recurso da imagem de um ícone.
   * 
   * @param string $resource
   *   O arquivo do ícone dentro da pasta 'icons'
   * @param string $alt
   *   O nome alternativo
   * 
   * @return string
   *   A tag <img>
   */
  public function icon(
    string $resource,
    string $alt
  ): string
  {
    $tag ='<img class="ui image" src="%s" alt="%s">';
    /** @var Uri $uri */
    $uri = $this->request->getUri();
    $address = $uri->getBaseUrl()
      . '/' . $this->basePath
      . '/icons/' . $resource
    ;

    return sprintf($tag, $address, $alt);
  }
}
