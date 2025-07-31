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
 * das requisições ao container da aplicação.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti (at) gmail (dot) com>
 */

namespace Core\Traits;

use Slim\Exception\ContainerValueNotFoundException;
use Psr\Container\ContainerInterface;

trait ContainerTrait
{
  /**
   * O container da aplicação Slim
   * 
   * @var ContainerInterface
   */
  protected $container = null;
  
  /**
   * Obtém um serviço de um container
   * 
   * @param string $property
   *   O nome do container
   * 
   * @return object
   */
  public function __get($property)
  {
    if ($this->has($property)) {
      return $this->container->get($property);
    }

    throw new ContainerValueNotFoundException(sprintf('O identificador '
      . '"%s" não está definido no container da aplicação.', $property)
    );
  }
  
  /**
   * Determina se um serviço está armazenado no container da aplicação.
   * 
   * @param string $property
   *   O nome do serviço
   * 
   * @return bool
   *   O indicativo se o serviço está disponível no container
   */
  public function has($property): bool
  {
    return $this->container->has($property);
  }
  
  /**
   * Obtém o container da aplicação.
   * 
   * @return object
   */
  public function getContainer()
  {
    return $this->container;
  }
}
