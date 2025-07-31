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
 * A classe que obtém os valores atualizados de indicadores financeiros
 * através do provedor Debit (http://www.debit.com.br).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\FinancialIndicators\Providers;

use DateTime;
use Core\FinancialIndicators\Indicators\Indicator;
use Core\FinancialIndicators\Indicators\{IGPDI, IGPM, IPC, IPCA, INPC, ICV};
use Core\FinancialIndicators\Providers\AbstractProvider;
use Core\FinancialIndicators\Providers\ProviderInterface;
use DOMDocument;
use DOMNodeList;
use DOMXPath;
use UnexpectedValueException;

class DebitProvider
  extends AbstractProvider
  implements ProviderInterface
{
  /**
   * O nome do provedor de indicadores financeiros.
   *
   * @var string
   */
  protected $name = 'Debit';

  /**
   * A URL base para acesso ao provedor.
   *
   * @var string
   */
  protected $baseURL = 'http://www.debit.com.br';

  /**
   * Faz o parse do conteúdo deste provedor.
   *
   * @param string $content
   *   O conteúdo obtido à partir da página do provedor.
   *
   * @throws UnexpectedValueException
   *   Em caso de algum problema na estrutura do documento
   *
   * @return array
   *   Os dados obtidos.
   */
  protected function parse(string $content): array
  {
    libxml_use_internal_errors(true);

    // Convertemos todos os carateres especiais para utf-8
    $content = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');

    // Criamos um novo documento na especificação HTML 1.0 e codificação
    // UTF-8
    $doc = new DomDocument('1.0', 'UTF-8');

    // Aqui carregamos o conteúdo sem adicionar tags html/body e também
    // a declaração doctype
    $doc->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Utilizamos DOMXPath para localizar o conteúdo
    $xpath = new DOMXPath($doc);

    // A página contém um div especial (mdl-grid) com alguns cards os
    // quais contém a informação que desejamos. Separamos eles.
    $cardNodeList = $xpath->query("//div[@class='mdl-grid']/div[contains(@class,'mdl-card')]");
    if ($cardNodeList->length !== 6) {
      throw new UnexpectedValueException("Não foi possível obter os "
        . "cartões de indicadores. Ocorreu alguma mudança na estrutura "
        . "do documento."
      );
    }

    // Vamos mapear os cards conforme a ordem em que os índices aparecem
    // nesta página
    $map = [
      0 => IGPDI::class,
      1 => IGPM::class,
      2 => IPC::class,
      3 => IPCA::class,
      4 => INPC::class,
      5 => ICV::class
    ];

    $data = [];

    // Percorremos a lista de índices e, através do nome do índice,
    // processamos o respectivo card para extrair os dados desejados
    foreach ($map as $i => $className) {
      // Obtemos o título do indicador
      $title = $xpath->evaluate('string(descendant::h2)',
        $cardNodeList[$i]
      );

      // Obtemos as tabelas que contém as informações de cada indicador
      $tables = $xpath->query('descendant::table', $cardNodeList[$i]);
      if ($tables->length > 0) {
        $data = $data + $this->parseIndicator(
          $title, $tables, $className
        );
      } else {
        throw new UnexpectedValueException("Não foi possível obter as "
          . "tabelas do indicador {$className}. Ocorreu alguma "
          . "mudança na estrutura do documento."
        );
      }
    }

    return $data;
  }

  /**
   * Analisa um indicator dentro da porção da página que contém
   * visualmente a informação desejada. Cada indicador está num
   * card, com as informações separadas em divs.
   *
   * @param string $indicatorName
   *   O nome do indicador sendo analisado
   * @param DOMNodeList $childNodes
   *   Os nós com as informações do indicador 
   * @param string $className
   *   O nome da classe que irá conter a informação do indicador
   *
   * @return array
   */
  protected function parseIndicator(string $indicatorName,
    DOMNodeList $childNodes, string $className): array
  {
    $rows = [];
    foreach ($childNodes as $node) {
      $date = $this->getDateFromMonthYearFormat(
        $node->childNodes[1]->childNodes[0]->textContent
      );
      $percentage = str_replace('%', '',
        $node->childNodes[1]->childNodes[1]->textContent
      );
      $percentage = (float) trim(str_replace(',', '.', $percentage));

      $indicatorObject = new $className($date, $percentage);

      $rows[$indicatorObject::getCode()][] = $indicatorObject;
    }

    return $rows;
  }

  /**
   * Obtém a data à partir do ano e mês.
   *
   * @param string $monthYearString
   *   O texto com o ano e o mês
   *
   * @throws UnexpectedValueException
   *   Em caso de algum problema na estrutura do documento
   *
   * @return DateTime
   */
  private function getDateFromMonthYearFormat(
    string $monthYearString
  ): DateTime
  {
    $monthList = [
      'Jan' => '01', 'Fev' => '02', 'Mar' => '03', 'Abr' => '04',
      'Mai' => '05', 'Jun' => '06', 'Jul' => '07', 'Ago' => '08',
      'Set' => '09', 'Out' => '10', 'Nov' => '11', 'Dez' => '12'
    ];

    if (preg_match('/[ADFJMNOS][a-z]{2}\/[12][0-9]{3}\b/',
      $monthYearString, $matchs)) {
      list($monthName, $year) = explode('/', $matchs[0]);
      $monthName = trim($monthName);
      $year = trim($year);

      if (array_key_exists($monthName, $monthList)) {
        $month = $monthList[$monthName];
      } else {
        throw new UnexpectedValueException("Erro processando mês com "
          . "valor inválido: " . $monthName . " em " . $monthYearString
        );
      }
    } else {
      throw new UnexpectedValueException("Erro processando mês/ano com "
        . "valor inválido: " . $monthYearString
      );
    }

    return
      new DateTime("{$year}-{$month}-01")
    ;
  }

  /**
   * Obtém os valores mais recentes de cada indicador financeiro.
   *
   * @return array
   */
  public function getLatestIndicators(): array
  {
    $indicators = [];

    // Obtemos os dados do provedor
    $uri = $this->getURI('aluguel10.php');
    $parsedData = $this->getProviderContent($uri);

    // Percorremos os dados, atribuindo os valores de cada indicador
    foreach ($parsedData as $code => $data) {
      if (count($data) > 0) {
        $indicators[$code] = $data[0];
      }

      foreach ($data as $indicator) {
        if ( ((new DateTime)->format('m') === $indicator->getDate()->format('m'))
             && ((new DateTime)->format('y') === $indicator->getDate()->format('y')) ) {
          $indicators[$code] = $indicator;

          break;
        }
      }
    }

    return $indicators;
  }

  /**
   * Obtém os indicadores para o mês atual.
   *
   * @return array
   */
  public function getIndicatorsForCurrentMonth(): array
  {
    $indicators = [];

    // Obtemos os dados do provedor
    $uri = $this->getURI('aluguel10.php');
    $parsedData = $this->getProviderContent($uri);

    // Percorremos os dados, atribuindo os valores de cada indicador
    foreach ($parsedData as $code => $data) {
      $indicators[$code] = [];

      foreach ($data as $indicator) {
        if ( ((new DateTime)->format('m') === $indicator->getDate()->format('m'))
             && ((new DateTime)->format('y') === $indicator->getDate()->format('y')) ) {
          $indicators[$code] = $indicator;

          break;
        }
      }
    }

    return $indicators;
  }

  /**
   * Obtém os índices disponíveis através do código do indicador
   * financeiro.
   *
   * @param int $indicatorCode
   *   O código do indicador financeiro
   *
   * @return array
   *   Os índices deste indicador financeiro
   */
  public function getIndexesFromIndicatorCode(
    int $indicatorCode
  ): array
  {
    // Obtemos os dados do provedor
    $uri = $this->getURI('aluguel10.php');
    $parsedData = $this->getProviderContent($uri);

    $indexes = array_filter($parsedData, function ($indexList, $code) use ($indicatorCode) {
      return $code == $indicatorCode;
    }, ARRAY_FILTER_USE_BOTH);

    if (!$indexes) {
      return [];
    }

    return $indexes[$indicatorCode];
  }

  /**
   * Obtém o índice atual através do código do indicador
   * financeiro.
   *
   * @param int $indicatorCode
   *   O código do indicador financeiro
   *
   * @return null|Indicator
   *   O índice atual deste indicador financeiro, ou nulo se o mesmo não
   *   estiver disponível
   */
  public function getCurrentIndexFromIndicatorCode(
    int $indicatorCode
  ): ?Indicator
  {
    // Obtemos os dados do provedor
    $uri = $this->getURI('aluguel10.php');
    $parsedData = $this->getProviderContent($uri);

    foreach ($parsedData[$indicatorCode] as $indicator) {
      if ( ((new DateTime)->format('m') === $indicator->getDate()->format('m'))
             && ((new DateTime)->format('y') === $indicator->getDate()->format('y')) ) {
        return $indicator;
      }
    }

    return null;
  }
}
