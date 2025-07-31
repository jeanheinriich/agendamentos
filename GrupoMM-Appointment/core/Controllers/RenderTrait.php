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
 * renderização de templates que outras classes podem incluir.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Controllers;

use Core\Exceptions\TemplateNotFoundException;
use Core\Helpers\Path;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

trait RenderTrait
{
  /**
   * Obtém o caminho para o template informado, baseado nas
   * configurações para o serviço de renderização.
   * 
   * @param string $templateName
   *   O nome do template
   *
   * @throws TemplateNotFoundException
   *   Caso o template não exista
   */
  protected function checkTemplate(string $templateName): void
  {
    $templatePath = '';
    if ($this->has('settings')) {
      if ($this->settings->has('renderer')) {
        // Recupera a localização dos templates
        $templatePath = $this->settings['renderer']['templatePath'];
      }
    }

    $template = new Path($templatePath . '/' . $templateName);
    
    if (!$template->exists() || !$template->isFile()) {
      throw new TemplateNotFoundException($template);
    }

    if (!$template->isReadable()) {
      throw new RuntimeException("O template '{$templateName}' não "
        . "pode ser lido."
      );
    }
  }

  /**
   * Renderiza uma página.
   * 
   * @param ServerRequestInterface $request
   *   A requisição HTTP
   * @param ResponseInterface $response
   *   A resposta HTTP
   * @param string $templateName
   *   O nome do arquivo de template
   * @param array $params
   *   Os parâmetros utilizados na renderização
   * 
   * @return ResponseInterface
   *   A resposta contendo a página renderizada
   *
   * @throws RuntimeException
   * @throws TemplateNotFoundException
   */
  protected function render(
    ServerRequestInterface $request,
    ResponseInterface $response,
    string $templateName,
    array $params = []
  ): ResponseInterface
  {
    // Verifica o template
    $this->checkTemplate($templateName);

    if (!$this->has('renderer')) {
      throw new RuntimeException("Não foi encontrado um serviço de "
        . "renderização disponível."
      );
    }

    // Renderiza o template
    return $this->renderer->render($response, $templateName, $params);
  }

  /**
   * Renderiza um e-mail utilizando um template HTML.
   * 
   * @param string $templateName
   *   O nome do arquivo de template
   * @param array $params
   *   Os parâmetros utilizados na renderização
   * 
   * @return string
   *   O conteúdo do e-mail renderizado
   *
   * @throws RuntimeException
   * @throws TemplateNotFoundException
   */
  protected function renderHtmlMail(
    string $templateName,
    array $params = []
  ): string
  {
    // Verifica o template
    $this->checkTemplate($templateName);

    if (!$this->has('renderer')) {
      throw new RuntimeException("Não foi encontrado um serviço de "
        . "renderização disponível."
      );
    }
    
    // Renderiza o template
    return $this->renderer->fetch($templateName, $params);
  }

  /**
   * Gera as configurações do PDF
   * 
   * @param string $pageSize
   *   O tamanho da página
   * @param string $pageOrientation
   *   A orientação da página
   * @param bool $withHeaderAndFooter
   *   O indicativo que teremos cabeçalho n
   * 
   * @return array
   *   A configuração do MPDF
   */
  protected function generatePDFConfig(
    string $pageSize,
    string $pageOrientation,
    bool $withHeaderAndFooter = true
  ): array
  {
    $pageFormat  = strtoupper($pageSize) . '-'
      . strtoupper($pageOrientation[0])
    ;
    $orientation = strtoupper($pageOrientation[0]);
    $options = $this->container['settings']['renderer'];

    // Obtemos as informações padrões de configuração do MPDF para as
    // fontes de letra
    $defaultConfig = (new ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];
    $defaultFontConfig = (new FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $pdfConfig = [
      'tempDir'           => $options['pdf']['cache'],
      // A codificação padrão
      'mode'              => 'utf-8',
      // O formato da página
      'format'            => $pageFormat,
      // O tamanho padrão da fonte
      'default_font_size' => 6,
      // As configurações de fontes
      'fontDir' => array_merge($fontDirs, [
        $options['pdf']['fonts']
      ]),
      'fontdata' => $fontData + [
        'bebasneue' => [
          'R' => "BebasNeue.ttf"
        ]
      ],
      // A fonte de letra padrão
      'default_font'      => 'DejaVuSansMono',
      // A orientação da página (P - portrait, L - landscape)
      'orientation'       => $orientation
    ];

    if ($withHeaderAndFooter) {
      $margins = [
        // As margens
        'margin_left'       => $orientation==='P'?20:10,
        'margin_right'      => 10,
        'margin_top'        => 20,
        'margin_bottom'     => $orientation==='P'?20:17,
        'margin_header'     => 10,
        'margin_footer'     => 10,
      ];
    } else {
      $margins = [
        // As margens
        'margin_left'       => 10,
        'margin_right'      => 10,
        'margin_top'        => 10,
        'margin_bottom'     => 10,
        'margin_header'     => 0,
        'margin_footer'     => 0
      ];
    }

    return array_merge($pdfConfig, $margins);
  }

  /**
   * Renderiza um template PDF
   * 
   * @param string $templateName
   *   O nome do arquivo de template
   * @param array $params
   *   Os parâmetros utilizados na renderização
   * 
   * @return string
   *   O conteúdo do PDF renderizado
   *
   * @throws RuntimeException
   * @throws TemplateNotFoundException
   */
  protected function renderPDFFromString(
    string $template,
    array $params = []
  ): string
  {
    if (!$this->has('renderer')) {
      throw new RuntimeException("Não foi encontrado um serviço de "
        . "renderização disponível."
      );
    }
    
    // Renderiza o template
    return $this->renderer->fetchFromString($template, $params);
  }

  /**
   * Renderiza um template PDF
   * 
   * @param string $templateName
   *   O nome do arquivo de template
   * @param array $params
   *   Os parâmetros utilizados na renderização
   * 
   * @return string
   *   O conteúdo do PDF renderizado
   *
   * @throws RuntimeException
   * @throws TemplateNotFoundException
   */
  protected function renderPDF(
    string $templateName,
    array $params = []
  ): string
  {
    // Verifica o template
    $this->checkTemplate($templateName);
    
    if (!$this->has('renderer')) {
      throw new RuntimeException("Não foi encontrado um serviço de "
        . "renderização disponível."
      );
    }
    
    // Renderiza o template
    return $this->renderer->fetch($templateName, $params);
  }

  /**
   * Renderiza o cabeçalho de uma página PDF.
   * 
   * @param string $title
   *   O título da página
   * @param string $image
   *   A imagem a ser exibida no canto superior esquerdo
   * 
   * @return string
   *   O conteúdo do cabeçalho do PDF renderizado
   *
   * @throws RuntimeException
   * @throws TemplateNotFoundException
   */
  protected function renderPDFHeader(
    string $title,
    string $image
  ): string
  {
    // Recupera o template de cabeçalho
    $templateName = 'templates/headerPDF.twig';

    // Verifica o template
    $this->checkTemplate($templateName);

    // Monta os parâmetros
    $params = [
      'title' => $title,
      'image' => $image
    ];
    
    if (!$this->has('renderer')) {
      throw new RuntimeException("Não foi encontrado um serviço de "
        . "renderização disponível."
      );
    }
    
    // Renderiza o template
    return $this->renderer->fetch($templateName, $params);
  }

  /**
   * Renderiza o rodapé de uma página PDF
   * 
   * @return string
   *   O conteúdo do rodapé do PDF renderizado
   *
   * @throws RuntimeException
   * @throws TemplateNotFoundException
   */
  protected function renderPDFFooter(): string
  {
    // Recupera o template de rodapé
    $templateName = 'templates/footerPDF.twig';

    // Verifica o template
    $this->checkTemplate($templateName);

    // Monta os parâmetros
    $params = [];
    
    if (!$this->has('renderer')) {
      throw new RuntimeException("Não foi encontrado um serviço de "
        . "renderização disponível."
      );
    }
    
    // Renderiza o template
    return $this->renderer->fetch($templateName, $params);
  }

  /**
   * Recupera a logomarca do contratante a partir de seu UUID.
   * 
   * @param string $uuid
   *   A UUID do contratante
   * @param string $type
   *   A indicação se desejamos o logo normal ou invertido
   * 
   * @return string
   *   Os dados da imagem da logomarca do contratante codificado em
   *   base64
   */
  protected function getContractorLogo(
    string $uuid,
    string $type='normal'
  ): string
  {
    // Determina o tipo da logomarca (normal ou invertida)
    if (trim(strtolower($type)) === 'normal') {
      $suffix = 'N';
    } else {
      $suffix = 'I';
    }

    // Recupera o local de armazenamento das imagens
    $storageSettings = $this->container['settings']['storage'];
    $logoDirectory = $storageSettings['images'];

    // Localiza a logomarca
    $searchText    = $logoDirectory . DIRECTORY_SEPARATOR
      . "Logo_" . $uuid . "_?.*"
    ;
    $files = glob($searchText);
    $imageDataBase64 = 'assets/icons/erp/erp.svg';
    if (count($files) > 0) {
      // Processa cada arquivo individualmente
      foreach ($files as $count => $imageFile) {
        // Em função do sufixo presente no nome do arquivo, associa o
        // conteúdo da imagem ao respectivo campo
        if ($this->getImageSuffix($imageFile) === $suffix) {
          // Codifica o conteúdo do arquivo em Base64
          $imageDataBase64 = $this->readBase64Image($imageFile);
        }
      }
    }

    return $imageDataBase64;
  }

  /**
   * Recupera a estampa de pago.
   * 
   * @return string
   *   O nome do arquivo que contém a imagem de estampa de pago
   */
  protected function getPaidStamp(): string
  {
    // Recupera o local de armazenamento das imagens
    $storageSettings = $this->container['settings']['storage'];
    $logoDirectory = $storageSettings['images'];

    // Localiza a imagem
    $imageFile = $logoDirectory . DIRECTORY_SEPARATOR . "PaidStamp.png";
    
    return $imageFile;
  }
}
