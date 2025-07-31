<?php
/*
 * This file is part of the payment's API library.
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
 * Uma classe abstrata para servir como base para renderizadores de
 * boletos bancários com base no padrão FEBRABAN.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankingBillet\Renderer;

use Core\Payments\BankingBillet\BankingBillet;
use Core\Payments\BankingBillet\FormatterTrait;
use Core\Payments\Coins;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;
use RuntimeException;

abstract class AbstractRenderer
{
  /**
   * Os métodos para formatação
   */
  use FormatterTrait;

  /**
   * A matriz com os boletos.
   *
   * @var BankingBillet[]
   */
  protected $billets = [];

  /**
   * A informação se devemos ou não imprimir as instruções de impressão.
   *
   * @var boolean
   */
  protected $printInstructions = false;

  /**
   * A informação se devemos ou não imprimir a página de capa do carnê.
   *
   * @var boolean
   */
  protected $printBookCover = false;

  /**
   * O indicativo que deve ser mostrado o endereço do beneficiário logo
   * abaixo da razão social e CNPJ na ficha de compensação.
   *
   * @var boolean
   */
  protected $showPayerAddress = false;
  
  /**
   * Nome do arquivo do layout a ser usado (modelo de boleto).
   *
   * @var string
   */
  protected $layout = 'default';
  
  /**
   * A informação se o código de barras deve ser gerado como uma imagem
   * do tipo PNG (o padrão é não, gerando um SVG).
   *
   * @var bool
   */
  protected $barcodeAsPNG = false;
  
  /**
   * Localização de recursos (imagens, estilos, etc)
   *
   * @var string
   */
  protected $resourcePath =  __DIR__ . '/resources';

  /**
   * O construtor de nosso renderizador
   */
  public function __construct()
  {
    // Marca a pasta de resources padrão
    $this->setResourcePath(__DIR__ . '/resources');
  }

  /**
   * Adiciona um boleto a ser renderizado.
   *
   * @param BankingBillet $billet
   *   O boleto a ser adicionado
   *
   * @return $this
   *   A instância do renderizador
   */
  public function addBillet(BankingBillet $billet): self
  {
    $this->billets[] = $billet;

    return $this;
  }

  // =====[ Funções de layout do boleto ]===============================

  /**
   * Define o modo de impressão para boleto avulso.
   * 
   * @return $this
   *   A instância do renderizador
   */
  public function setSingleLayout(): self
  {
    $this->layout = 'default';
    
    return $this;
  }

  /**
   * Define o modo de impressão para fatura.
   * 
   * @return $this
   *   A instância do renderizador
   */
  public function setInvoiceLayout(): self
  {
    $this->layout = 'invoice';
    
    return $this;
  }

  /**
   * Define o modo de impressão para carnê.
   * 
   * @return $this
   *   A instância do renderizador
   */
  public function setBookletLayout(): self
  {
    $this->layout = 'paymentbooklet';
    
    return $this;
  }

  /**
   * Habilita a exibição das instruções de impressão.
   * 
   * @return $this
   *   A instância do renderizador
   */
  public function enablePrintInstructions(): self
  {
    $this->printInstructions = true;
    
    return $this;
  }
  
  /**
   * Obtém se devemos imprimir ou não as instruções de impressão.
   *
   * @return bool
   */
  public function getPrintInstructions(): bool
  {
    return $this->printInstructions;
  }

  /**
   * Habilita a impressão da capa (apenas para carnês).
   * 
   * @return $this
   *   A instância do renderizador
   */
  public function printBookCover(): self
  {
    $this->printBookCover = true;
    
    return $this;
  }
  
  /**
   * Obtém se devemos imprimir ou não a capa.
   *
   * @return bool
   */
  public function getPrintBookCover(): bool
  {
    return $this->printBookCover;
  }


  // =====[ Funções de manipulação de imagens ]=========================

  /**
   * Define a localização da pasta de recursos.
   *
   * @param string $resourcePath
   *   O caminho para a pasta de recursos
   * 
   * @return $this
   *   A instância do renderizador
   */
  public function setResourcePath(string $resourcePath): self
  {
    $this->resourcePath = $resourcePath;
    
    return $this;
  }
  
  /**
   * Obtém a localização da pasta de recursos.
   *
   * @return string
   */
  public function getResourcePath(): string
  {
    return $this->resourcePath;
  }

  /**
   * Obtém o conteúdo da imagem no formato Base64.
   *
   * @return string
   */
  public function getImageAsBase64($filename): string
  {
    $logoData = 'data:image/'
      . pathinfo($filename, PATHINFO_EXTENSION)
      . ';base64,' . base64_encode(
        file_get_contents($this->resourcePath . '/images/'
          . $filename)
        )
    ;
    
    return $logoData;
  }

  /**
   * Obtém a imagem do código de barras, segundo o padrão Febraban.
   *
   * @param string $code
   *   O código para o qual iremos gerar as barras
   *
   * @return mixed
   *   A imagem contendo o código de barras no formato PNG ou SVG
   */
  public function getBarCode(string $code)
  {
    // Renderiza o código de barras
    $generator = ($this->barcodeAsPNG)
      ? new BarcodeGeneratorPNG()
      : new BarcodeGeneratorSVG()
    ;
    $imageType = ($this->barcodeAsPNG)
      ? "image/png"
      : "image/svg"
    ;

    $barcodeImage = $generator->getBarcode(
      $code,                  // O código a ser transformado em barras
      $generator::TYPE_INTERLEAVED_2_5, // O formato das barras
      1,                                // O fator de comprimento
      49.13                             // A altura do código de barras
    );
    
    $barcodeData = "data:{$imageType}" .
      ';base64,' . base64_encode($barcodeImage)
    ;
    
    return $barcodeData;    
  }

  // =====[ Funções de renderização do boleto ]=========================
  
  protected function getLayoutFileName(): string
  {
    return $this->resourcePath . '/layouts/' . $this->layout . '.phtml';
  }

  /**
   * Obtém os dados do boleto formatados para impressão.
   *
   * @param BankingBillet $billet
   *   O boleto a ser impresso
   *
   * @return array
   */
  protected function getBilletData(BankingBillet $billet): array
  {
    // Geramos uma matriz com os dados do boleto a ser impresso
    $data = [];

    $messages = '';
    if (!$billet->isValid($messages)) {
      throw new RuntimeException('Um ou mais campos requeridos para a '
        . 'emissão do boleto estão ausentes: ' . $messages
      );
    }

    // Informações do banco emissor
    $data['bank'] = [
      'logo' => $this->getImageAsBase64($billet->getBankLogo()),
      'code' => $billet->getBankCodeWithDAC()
    ];

    // Beneficiário (emissor)
    $emitter = $billet->getEmitter();
    $data['emitter'] = [
      'name'             => $emitter->getName(),
      'document'         => $emitter->getDocument(),
      'documentNumber'   => $emitter->getDocumentNumber(),
      'documentType'     => $emitter->getDocumentType(),
      'addressLine1'     => $emitter->getAddressLine1(),
      'addressLine2'     => $emitter->getAddressLine2(),
      'logo'             => $emitter->getLogoAsBase64(),
      'agencyAndAccount' => $billet->getAgencyNumberAndEmitterCode()
    ];

    // Pagante
    $payer = $billet->getPayer();
    $data['payer'] = [
      'name'           => $payer->getName(),
      'document'       => $payer->getDocument(),
      'documentNumber' => $payer->getDocumentNumber(),
      'documentType'   => $payer->getDocumentType(),
      'addressLine1'   => $payer->getAddressLine1(),
      'addressLine2'   => $payer->getAddressLine2()
    ];

    // Sacador/avalista
    $guarantor = $billet->getGuarantor();
    $data['guarantor'] = $guarantor
      ? $guarantor->getNameAndDocument()
      : null
    ;

    // Informações do contrato com o banco emissor
    $data['walletName'] = $billet->getWalletName();
    $complementaryData = $billet->getComplementaryData();
    if (count($complementaryData) > 0) {
      $data = array_merge($data, $complementaryData);
    }

    // Informações do documento
    $data['dateOfDocument'] = $billet->getDateOfDocument()->format('d/m/Y');
    $data['kindOfDocument'] = $billet->getKindOfDocument();
    $data['documentNumber'] = $billet->getDocumentNumber();
    $data['acceptance'] = $billet->getAcceptance();
    $data['specie'] = Coins::getSpecie($billet->getCurrency());
    $data['amount'] = $this->moneyFormat($billet->getAmount());
    $data['documentValue'] = $this->moneyFormat($billet->getDocumentValue());
    // Conforme o layout do boleto, acrescentamos as informações
    // pertinentes
    switch ($this->layout) {
      case 'invoice':
        // Faturas
        $data['totalizer'] = $billet->getTotalizer();
        $data['parcel'] = $billet->getParcel();
        $data['referenceMonth'] = $billet->getReferenceMonth();
        $data['historic'] = $billet->getHistoric();

        break;
      case 'paymentbooklet':
        // Carnê
        $data['printBookCover'] = $this->getPrintBookCover();
        $data['parcel'] = $billet->getParcel();
        $data['referenceMonth'] = $billet->getReferenceMonth();

        break;
      default:
        $data['printInstructions'] = $this->getPrintInstructions();
        $data['printInstructionsText'] = $billet->getPrintInstructionsText();
        $data['demonstrativeText'] = $billet->getDemonstrativeText();

        break;
    }

    // Informações da cobrança
    $data['dateOfProcessing'] = $billet->getDateOfProcessing()->format('d/m/Y');
    $data['minimumPayment']   = $this->moneyFormat($billet->getMinimumPayment());
    $data['dateOfExpiration'] = $billet->getAgainstPresentation()
      ? 'Contra Apresenta&ccedil;&atilde;o'
      : $billet->getDateOfExpiration()->format('d/m/Y')
    ;
    $data['forBankUse'] = $billet->getForBankUse();
    $data['instructionsText'] = $billet->getInstructionsText();
    if ($this->printInstructions) {
      $data['printInstructionsText'] = $billet->getPrintInstructionsText();
    }
    $data['paymentPlace'] = $billet->getPaymentPlace();

    // Informações calculadas
    $data['bankIdentificationNumber'] = $billet->getBankIdentificationNumberForBillet();
    $data['barcode'] = $this->getBarCode($billet->getBarCodeNumber());
    $data['digitableLine'] = $billet->getDigitableLine();

    return $data;
  }

  /**
   * Renderiza o boleto.
   *
   * @return string
   *   O conteúdo do boleto
   */
  abstract public function render(): string;
}