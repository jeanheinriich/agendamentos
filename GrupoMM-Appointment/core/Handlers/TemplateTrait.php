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
 * de templates em função da aplicação que as classes de
 * manipulação de erros podem incluir. Ela permite definir arquivos de
 * templates separados em função do aplicativo ao qual o caminho
 * pertence.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Handlers;

use Core\Exceptions\TemplateNotFoundException;
use Core\Helpers\Path;
use RuntimeException;

trait TemplateTrait
{
  /**
   * O nome do template Twig para renderização do erro.
   * 
   * @var string
   */
  protected $template;

  /**
   * Recupera o endereço relativo de um template para renderização do
   * erro em função do caminho do recurso, respeitando sub-aplicações
   * caso a URL pertença à esta sub-aplicação.
   * 
   * @param string $path
   *   O caminho (url)
   * @param string $templateName
   *   O nome do template
   * 
   * @return string
   *   O nome do template acrescido do caminho em função da parte do
   * sistema em que nos encontramos
   */
  protected function getTemplate(string $path,
    string $templateName): string
  {
    $templateDir = 'error';
    $app = $this->getApplication($path);

    return trim($templateDir . $app, "/") . '/' . $templateName;
  }

  /**
   * Renderiza um template com o conteúdo do erro.
   * 
   * @param array $params
   *   Os parâmetros utilizados para renderizarmos o template
   * 
   * @return string
   *   O conteúdo de nossa mensagem renderizada
   * 
   * @throws TemplateNotFoundException
   */
  protected function renderUsingTemplate(array $params): string
  {
    // Seta no 'breadcrumb' que temos erros e que não deve ser exibido
    // o caminho completo
    if ($this->has('breadcrumb')) {
      $this->breadcrumb->setHasError();
    }

    // Recupera a localização dos templates
    $templatePath = '';
    if ($this->has('settings')) {
      if ($this->settings->has('renderer')) {
        $templatePath = $this->settings['renderer']['templatePath'];
      }
    }

    $templateName = $this->template;
    $template = new Path($templatePath . '/' . $templateName);

    if (!$template->exists() || !$template->isFile()) {
      throw new TemplateNotFoundException($template);
    }

    if (!$template->isReadable()) {
      throw new RuntimeException("O template '{$templateName}' não "
        . "pode ser lido.")
      ;
    }

    if (!$this->has('renderer')) {
      throw new RuntimeException("Não foi encontrado um serviço de "
        . "renderização disponível.")
      ;
    }

    // Renderiza o template
    return $this->renderer->fetch($templateName, $params);
  }
}
