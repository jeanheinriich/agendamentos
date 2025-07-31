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
 * O manipulador de erros de CSRF (Cross-Site Request Forgery).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Handlers;

use Core\Handlers\Formatters\JSONFormatter;
use Core\Handlers\Formatters\XMLFormatter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Body;
use UnexpectedValueException;

class CSRFFailureHandler
  extends AbstractHandler
{
  /**
   * O método invocado para manipular o erro.
   * 
   * @param ServerRequestInterface $request
   *   O objeto mais recente de Request
   * @param ResponseInterface $response
   *   O objeto mais recente de Response
   *
   * @return ResponseInterface
   *
   * @throws UnexpectedValueException
   *   Em caso de algum erro inesperado
   */
  public function __invoke(ServerRequestInterface $request,
    ResponseInterface $response)
  {
    // Determina o endereço atual
    $uri    = $request->getUri();
    $path   = $uri->getPath();
    $params = $request->getQueryParams();

    // Verifica o método da solicitação
    $method = $request->getMethod();

    // Determina o tipo de conteúdo requisitado
    $contentType = $this->determineContentType($request);

    // Determina uma mensagem de erro padrão, independente do tipo de
    // requisição
    $errorMessage = "O formulário expirou devido a inatividade e/ou a "
      . "uma falha de verificação de autenticidade. Por favor, tente "
      . "novamente."
    ;

    // Em função do tipo de conteúdo requisitado, seleciona um formatador
    // adequado
    $output = '';
    switch ($contentType) {
      case 'application/json':
        // Inicializa o formatador para JSON
        $formatter = new JSONFormatter($this->displayErrorDetails);

        break;
      case 'text/xml':
      case 'application/xml':
        // Inicializa o formatador para XML
        $formatter = new XMLFormatter($this->displayErrorDetails);

        break;
      case 'text/html':
        // Adiciona a mensagem de erro no sistema de Flash
        $this->container['flash']->addMessage('error', $errorMessage);
        
        // Retorna para a mesma página, porém com uma mensagem de flash
        // indicando o erro
        return $response
                 ->withRedirect($path);
      default:
        throw new UnexpectedValueException("Não é possível processar o "
          . "tipo de conteúdo desconhecido " . $contentType)
        ;
    }

    // Renderiza a mensagem de erro
    $output = $formatter->renderErrorMessage($errorMessage, $params);

    // Registra o erro no log
    $this->error("Falha na verificação do CSRF para a requisição "
      . "[{method}] {path}", 
      [ 'method' => $method,
        'path' => ltrim($request->getUri()->getPath(), '/') ]
    );

    // Renderiza a resposta
    $body = new Body(fopen('php://temp', 'r+'));
    $body->write($output);
    
    return $response
      ->withStatus(419)
      ->withHeader('Content-type', $contentType)
      ->withBody($body)
    ;
  }
}
