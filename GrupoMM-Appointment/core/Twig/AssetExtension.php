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
 * Classe responsável por extender o Twig permitindo a inclusão da
 * função 'Asset' que determina a localização dos recursos utilizados na
 * renderização da página ao cliente.
 */

namespace Core\Twig;

use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssetExtension
  extends AbstractExtension
{
  /**
   * A engine de renderização de recursos.
   * 
   * @var AssetsEngine
   */
  private $engine;
  
  /**
   * O construtor de nossa extensão.
   * 
   * @param Request $request
   *   A requisição de entrada
   * @param string|null $basePath
   *   O diretório base (default='/')
   */
  public function __construct(
    Request $request,
    ?string $basePath = null
  )
  {
    $this->engine = new AssetEngine($request, $basePath);
  }
  
  /**
   * Recupera as funções para o sistema Twig.
   * 
   * @return array
   */
  public function getFunctions()
  {
    return [
      new TwigFunction('asset', [$this, 'asset']),
      new TwigFunction('i18n', [$this, 'i18n']),
      new TwigFunction('css', [$this, 'css'], [
          'needs_environment' => false,
          'is_safe' => ['html']
        ]),
      new TwigFunction('js', [$this, 'js'], [
          'needs_environment' => false,
          'is_safe' => ['html']
        ]),
      new TwigFunction('lib', [$this, 'lib'], [
          'needs_environment' => false,
          'is_safe' => ['html']
        ]),
      new TwigFunction('icon', [$this, 'icon'], [
          'needs_environment' => false,
          'is_safe' => ['html']
        ])
    ];
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
    return $this->engine->asset($resource);
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
    return $this->engine->i18n($resource);
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
  public function css(
    string $resource,
    string $local = 'css'
  ): string
  {
    return $this->engine->css($resource, $local);
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
    return $this->engine->js($resource);
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
    return $this->engine->lib($resource);
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
  ):string
  {
    return $this->engine->icon($resource, $alt);
  }
}
