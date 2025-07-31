<?php
/*
 * This file is part of Extension Library.
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
 * Um formatador de mensagens de erro no formato XML.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Handlers\Formatters;

use SimpleXMLElement;
use Throwable;

class XMLFormatter
  extends ErrorFormatter
{
  /**
   * Renderiza um erro.
   * 
   * @param Throwable $error
   *   A exceção/erro que contém a mensagem
   * @param array $params
   *   Os parâmetros da requisição
   * 
   * @return string
   *   A mensagem de erro formatada
   */
  public function renderError(
    Throwable $error,
    array $params
  ): string
  {
    $xmlBase = '<?xml version="1.0" encoding="UTF-8"?><error></error>';
    $xml = new SimpleXMLElement($xmlBase);

    $xml->addChild("message", "Erro interno do aplicativo");
    $xml->addChild("description", $this->getErrorDescription($error));

    // Adicionamos os parâmetros ao nosso XML
    $this->array2xml($params, $xml->addChild('params'));

    if ($this->displayErrorDetails) {
      do {
        $exception = $xml->addChild("exception");
        $exception->addChild("type", get_class($error));
        $exception->addChild("code", $error->getCode());
        $exception->addChild("message",
          $this->createCdataSection($error->getMessage())
        );
        $exception->addChild("file", $error->getFile());
        $exception->addChild("line", $error->getLine());
        $exception->addChild("trace",
          $this->createCdataSection($error->getTraceAsString())
        );
      } while ($error = $error->getPrevious());
    }
    
    return $xml->asXML();
  }

  /**
   * Renderiza uma mensagem de erro.
   * 
   * @param string $error
   *   A mensagem de erro
   * @param array $params
   *   Os parâmetros da requisição
   * 
   * @return string
   *   A mensagem de erro formatada
   */
  public function renderErrorMessage(
    string $error,
    array $params
  ): string
  {
    $xmlBase = '<?xml version="1.0" encoding="UTF-8"?><error></error>';
    $xml = new SimpleXMLElement($xmlBase);

    $xml->addChild("message", $error);
    
    return $xml->asXML();
  }
  
  /**
   * Retorna uma seção CDATA com o conteúdo fornecido.
   *
   * @param string $content
   *   O conteúdo da seção
   *
   * @return string
   */
  protected function createCdataSection($content): string
  {
    return sprintf('<![CDATA[%s]]>', str_replace(']]>', ']]]]><![CDATA[>', $content));
  }

  /**
   * Converte os parâmetros em uma representação XML.
   * 
   * @param array $array
   *   A matriz que desejamos converter
   * @param xml $xml
   *   O objeto XML
   * 
   * @return string
   *   O conteúdo em XML
   */
  protected function array2xml(
    ?array $array,
    $xml = false
  ): string
  {
    if ($xml === false) {
      $xml = new SimpleXMLElement('<params></params>');
    }

    // Percorremos nossa matriz, adicionando os parâmetros
    foreach ($array as $key => $value) {
      if (is_array($value)) {
        // Chamamos nossa função recursivamente
        $this->array2xml($value, $xml->addChild($key));
      } else {
        // Adicionamos o nó
        $xml->addChild($key, $value);
      }
    }

    return $xml->asXML();
  }
}