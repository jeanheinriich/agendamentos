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
 * O manipulador de erros do PHP. Ele exibe a mensagem de
 * erro e as informações de diagnóstico em JSON, XML ou HTML com base no
 * cabeçalho Accept.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Handlers;

use Core\Handlers\Formatters\HTMLFormatter;
use Core\Handlers\Formatters\JSONFormatter;
use Core\Handlers\Formatters\StreamFormatter;
use Core\Handlers\Formatters\TextFormatter;
use Core\Handlers\Formatters\XMLFormatter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Body;
use UnexpectedValueException;
use Throwable;

class PhpErrorHandler
  extends AbstractHandler
{
  /**
   * O método invocado para manipular o erro.
   * 
   * @param ServerRequestInterface $request
   *   O objeto mais recente de Request
   * @param ResponseInterface $response
   *   O objeto mais recente de Response
   * @param Throwable $exception
   *   O objeto Throwable capturado
   *
   * @return ResponseInterface
   *
   * @throws UnexpectedValueException
   *   Em caso de algum erro inesperado
   */
  public function __invoke(
    ServerRequestInterface $request,
    ResponseInterface $response,
    Throwable $exception
  )
  {
    // Determina o endereço atual
    $uri    = $request->getUri();
    $path   = $uri->getPath();
    $params = $request->getQueryParams();

    // Determina o tipo de conteúdo requisitado
    $contentType = $this->determineContentType($request);

    // Em função do tipo de conteúdo requisitado, seleciona um formatador
    // adequado
    $output = '';
    $useTemplate = false;
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
      case 'text/event-stream':
        // Inicializa o formatador para uma stream de eventos
        $formatter = new StreamFormatter($this->displayErrorDetails);

        break;
      case 'text/html':
        if (PHP_SAPI === 'cli') {
          // Inicializa o formatador para texto
          $formatter = new TextFormatter($this->displayErrorDetails);
        } else {
          // Determina qual o template será utilizado
          $this->template = $this->getTemplate($path, '500.twig');

          // Inicializa o formatador para HTML
          $formatter = new HTMLFormatter($this->displayErrorDetails);
          $useTemplate = true;
        }

        break;
      default:
        throw new UnexpectedValueException("Não é possível processar o "
          . "tipo de conteúdo desconhecido " . $contentType)
        ;
    }

    // Renderiza a mensagem de erro
    $output = $formatter->renderError($exception, $params);

    // Se for necessário, usa o template para formatar o erro ao usuário
    if ($useTemplate) {
      $homepage = $this->determineHomeAddress($path);

      // Renderiza utilizando o template
      $output = $this->renderUsingTemplate([
        'error' => $output,
        'homepage' => $this->path($homepage),
        'public' => $this->belongsToPublicArea($path)
      ]);
    }

    // Registra o erro no log
    $this->error("Ocorreu um erro interno do tipo '{class}' (Código "
      . "{code}): {message} em {file}:{line}",
      [ 'class' => get_class($exception),
        'code' => $exception->getCode(),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine() ]
    );
    
    $this->writeToErrorLog($exception);
    
    // Renderiza a resposta
    $body = new Body(fopen('php://temp', 'r+'));
    $body->write($output);
    
    return $response
      ->withStatus(500)
      ->withHeader('Content-type', $contentType)
      ->withBody($body)
    ;
  }
}
