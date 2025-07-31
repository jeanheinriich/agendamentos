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
 * função 'buildURL' que modifica a função path_for de forma a permitir
 * a inclusão simplificada de URLs com parâmetros.
 */

namespace Core\Twig;

use Core\Breadcrumbs\Breadcrumb;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Slim\Interfaces\RouterInterface;

class BuildURLExtension
  extends AbstractExtension
{
  /**
   * A interface de roteamento.
   * 
   * @var RouterInterface
   */
  private $router;
  
  /**
   * A URI do recurso.
   * 
   * @var string
   */
  private $uri;
  
  // O construtor
  public function __construct(RouterInterface $router, string $uri)
  {
    $this->router = $router;
    $this->uri = $uri;
  }
  
  /**
   * Recupera as funções para o sistema Twig.
   * 
   * @return array
   */
  public function getFunctions()
  {
    return [
      new TwigFunction('buildURL', [$this, 'buildURL'], [
          'needs_environment' => false,
          'is_safe' => ['html']
        ])
    ];
  }
  
  /**
   * Monta a URL de uma maneira mais inteligente.
   * 
   * @param string $routeName
   *   O nome da rota
   * @param array $data
   *   A matriz com os dados de parâmetros
   * @param array $queryParams
   *   A matriz com os parâmetros a serem substituídos
   *   
   * @return string
   *   A URL gerada
   */
  public function buildURL(string $routeName, array $data = [],
    array $queryParams = [])
  {
    // Verifica se temos parâmetros
    if (is_array($data) && is_array($queryParams)) {
      if ( (count($data) > 0) || (count($queryParams) > 0) ) {
        $dataFixed = [];
        $dataFields = [];
        $dataValues = [];
        foreach ($data AS $key => $value) {
          $dataFixed[$key] = '_' . $key;
          $dataFields[] = '_' . $key;
          $dataValues[] = is_numeric($value) === true
            ? "{$value}"
            : "\" + {$value}   + \""
          ;
        }
        // Recupera a informação da URL base
        $URLBase = $this->router->pathFor($routeName, $dataFixed,
          $queryParams)
        ;

        // Monta a URL final
        $uri = str_replace($dataFields, $dataValues, $URLBase);
      } else {
        // Devolve a URL sem modificação
        $uri = $this->router->pathFor($routeName);
      }
    } else {
      // Devolve a URL sem modificação
      $uri = $this->router->pathFor($routeName);
    }
    
    return $uri;
  }
}
