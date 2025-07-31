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
 * A definição doss dados de uma transação numa operação de transferência
 * bancária por PIX.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Payments\BankTransfer;

use LengthException;
use Mpdf\QrCode\QrCode;
use Mpdf\QrCode\Output;

class Pix
  extends Payload
{
  /**
  * Constantes utilizadas numa transferência por PIX.
  * 
  * @var string
  */
  const ID_PAYLOAD_FORMAT_INDICATOR = '00';
  const ID_POINT_OF_INITIATION_METHOD = '01';
  const ID_MERCHANT_ACCOUNT_INFORMATION = '26';
  const ID_MERCHANT_ACCOUNT_INFORMATION_GUI = '00';
  const ID_MERCHANT_ACCOUNT_INFORMATION_KEY = '01';
  const ID_MERCHANT_ACCOUNT_INFORMATION_DESCRIPTION = '02';
  const ID_MERCHANT_ACCOUNT_INFORMATION_URL = '25';
  const ID_MERCHANT_CATEGORY_CODE = '52';
  const ID_TRANSACTION_CURRENCY = '53';
  const ID_TRANSACTION_AMOUNT = '54';
  const ID_COUNTRY_CODE = '58';
  const ID_MERCHANT_NAME = '59';
  const ID_MERCHANT_CITY = '60';
  const ID_ADDITIONAL_DATA_FIELD_TEMPLATE = '62';
  const ID_ADDITIONAL_DATA_FIELD_TEMPLATE_TXID = '05';
  const ID_CRC16 = '63';

  /**
   * Define se o pagamento deve ser feito apenas uma vez.
   * 
   * @var boolean
   */
  private $uniquePayment = false;

  /**
   * URL para pagamento dinâmico.
   * 
   * @var string
   */
  private $url;

  /**
   * Define se o pagamento deve ser único.
   * 
   * @param boolean $uniquePayment
   *   O indicativo se o pagamento deve ser único
   * 
   * @return $this
   *   A instância do payload
   */
  public function setUniquePayment(bool $uniquePayment): self
  {
    $this->uniquePayment = $uniquePayment;

    return $this;
  }

  /**
   * Define a URL para pagamentos dinâmicos.
   * 
   * @param string $url
   *   A URL do pagamento
   * 
   * @return $this
   *   A instância do payload
   */
  public function setUrl(string $url): self
  {
    $this->url = $url;

    return $this;
  }

  /**
   * Obtém o valor da transação formatado.
   * 
   * @return string
   *   O valor da transação formatado
   */
  public function getFormatedAmount(): string
  {
    return (string) number_format($this->amount, 2, '.', '');
  }

  /**
   * Retorna o código de pagamento no padrão EMV. O código de pagamento
   * é um campo de texto alfanumérico (A-Z,0-9) permitindo os caracteres
   * especiais $ % * + - . / :.
   * 
   * Na estrutura EMV®1 os dois primeiros dígitos representam o código
   * ID do EMV e os dois dígitos seguintes contendo o tamanho do campo.
   * O conteúdo do campo são os caracteres seguintes até a quantidade de
   * caracteres estabelecida.
   * 
   * Exemplos de código EMV:
   * 
   * 1. No código 000200 temos:
   *   00 Código EMV 00 que representa o Payload Format Indicator;
   *   02 Indica que o conteúdo deste campo possui dois caracteres;
   *   00 O conteúdo deste campo é 00.
   * 
   * 2. No código 5303986 temos:
   *   53 Código EMV 53 que indica a Transaction Currency, ou seja,
   *      a moeda da transação.
   *   03 Indica que o tamanho do campo possui três caracteres;
   *   986 Conteúdo do campo é 986, que é o código para BRL (real
   *       brasileiro) na ISO4217.
   * 
   * 3. No código 5802BR temos:
   *   58 Código EMV 58 que indica o Country Code.
   *   02 Indica que o tamanho do campo possui dois caracteres;
   *   BR Conteúdo do campo é BR, que é o código do país Brasil conforme
   *      ISO3166-1 alpha 2.
   * 
   * @param string $idEMV
   *   O código ID do EMV
   * @param string $value
   *   O valor a ser formatado
   * 
   * @return string
   *   O valor formatado dentro do padrão EMV
   * 
   * @throws LengthException
   *   Em caso de informado um valor que exceda os limites do campo
   */
  private function getPaymentCode(
    string $idEMV,
    string $value
  ): string
  {
    $length = mb_strlen($value);

    if ($length > 99) {
      throw new LengthException("O valor {$value} extrapola os limites "
        . "do campo EMV"
      );
    }

    // Obtemos o tamanho do valor informado formatado para duas casas
    // decimais
    $size = str_pad($length,2,'0',STR_PAD_LEFT);

    // Retornamos os campos formatados no padrão EMV
    return $idEMV . $size . $value;
  }

  /**
   * Método responsável por retornar os valores completos da informação
   * da conta sendo cobrada.
   * 
   * @return string
   *   Os valores formatados com as informações da conta
   */
  private function getMerchantAccountInformation(): string
  {
    // Geramos cada parte do código separadamente
    
    // O domínio do banco
    $gui = $this->getPaymentCode(
      self::ID_MERCHANT_ACCOUNT_INFORMATION_GUI,
      'br.gov.bcb.pix'
    );

    // A chave PIX
    $key = strlen($this->emitterEntity->getPixKey())
      ? $this->getPaymentCode(
          self::ID_MERCHANT_ACCOUNT_INFORMATION_KEY,
          $this->emitterEntity->getPixKey()
        )
      : ''
    ;

    // A descrição do pagamento
    $description = strlen($this->description)
      ? $this->getPaymentCode(
          self::ID_MERCHANT_ACCOUNT_INFORMATION_DESCRIPTION,
          $this->description
        )
      : ''
    ;

    // URL do QR code dinâmico
    $url = strlen($this->url)
      ? $this->getPaymentCode(
          self::ID_MERCHANT_ACCOUNT_INFORMATION_URL,
          preg_replace('/^https?\:\/\//','',$this->url)
        )
      : ''
    ;

    // Montamos o resultado sendo a soma de cada parte
    return $this->getPaymentCode(
      self::ID_MERCHANT_ACCOUNT_INFORMATION,
      $gui . $key . $description . $url
    );
  }

  /**
   * Método responsável por retornar os valores completos do campo
   * adicional do pix (ID da transação).
   * 
   * @return string
   */
  private function getAdditionalDataFieldTemplate(): string
  {
    // O ID da transação
    $transactionID = $this->getPaymentCode(
      self::ID_ADDITIONAL_DATA_FIELD_TEMPLATE_TXID,
      $this->transactionID
    );

    return $this->getPaymentCode(
      self::ID_ADDITIONAL_DATA_FIELD_TEMPLATE,
      $transactionID
    );
  }

  /**
   * Obtém se esta transação é única ou não.
   * 
   * @return string
   */
  private function getUniquePayment(): string
  {
    return $this->uniquePayment
      ? $this->getPaymentCode(
          self::ID_POINT_OF_INITIATION_METHOD,
          '12'
        )
      : ''
    ;
  }

  /**
   * Método responsável por gerar o código completo do payload Pix
   * 
   * @return string
   */
  public function getPayload(): string
  {
    $payload = ''
      . $this->getPaymentCode(
          self::ID_PAYLOAD_FORMAT_INDICATOR,
          '01'
        )
      . $this->getUniquePayment()
      . $this->getMerchantAccountInformation()
      . $this->getPaymentCode(
          self::ID_MERCHANT_CATEGORY_CODE,
          '0000'
        )
      . $this->getPaymentCode(
          self::ID_TRANSACTION_CURRENCY,
          '986'
        )
      . $this->getPaymentCode(
          self::ID_TRANSACTION_AMOUNT,
          $this->getFormatedAmount()
        )
      . $this->getPaymentCode(
          self::ID_COUNTRY_CODE,
          'BR'
        )
      . $this->getPaymentCode(
          self::ID_MERCHANT_NAME,
          $this->emitterEntity->getName()
        )
      . $this->getPaymentCode(
          self::ID_MERCHANT_CITY,
          $this->emitterEntity->getCity()
        )
      . $this->getAdditionalDataFieldTemplate()
    ;

    // Retorna os dados mais o CRC16
    return $payload . $this->getCRC16($payload);
  }

  /**
   * Método responsável por calcular o valor da hash de validação do
   * código PIX.
   * 
   * @param string $payload
   *   Os dados do código PIX
   * 
   * @return string
   *   O CRC de validação
   */
  private function getCRC16(string $payload) {
    // Adiciona os dados gerais no payload
    $payload .= self::ID_CRC16 . '04';

    // Dados definidos pelo BACEN
    $polinomio = 0x1021;
    $resultado = 0xFFFF;

    // Calcula o dígito verificador
    if (($length = strlen($payload)) > 0) {
      for ($offset = 0; $offset < $length; $offset++) {
        $resultado ^= (ord($payload[$offset]) << 8);
        for ($bitwise = 0; $bitwise < 8; $bitwise++) {
          if (($resultado <<= 1) & 0x10000) {
            $resultado ^= $polinomio;
          }

          $resultado &= 0xFFFF;
        }
      }
    }

    // Retorna o código CRC16 de 4 caracteres
    return $this->getPaymentCode(
      self::ID_CRC16, strtoupper(dechex($resultado))
    );
  }

  /**
   * Obtém a imagem do QR Code.
   *
   * @return mixed
   *   A imagem contendo o QR Code no formato PNG ou SVG
   */
  public function getQRCode()
  {
    static $qrcodeData;

    // Recupera o código de pagamento PIX
    $pixcode = $this->getPayload();
    
    // Renderiza o QR Code
    $qrCode = new QrCode($pixcode);
    $qrcodeImage = (new Output\Png)->output(
      $qrCode,  // O código a ser transformado em QR Code
      400       // A largura e altura do código
    );
    $imageType = "image/png";
    
    $qrcodeData or $qrcodeData = "data:{$imageType}" .
      ';base64,' . base64_encode($qrcodeImage);
    
    return $qrcodeData;    
  }
}