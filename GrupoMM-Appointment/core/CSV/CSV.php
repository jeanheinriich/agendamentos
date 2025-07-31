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
 * Uma classe para permitir a leitura de um arquivo CSV.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\CSV;

/**
 * XMod\Common\CSV
 * @link http://www.csvreader.com/csv_format.php
 * @todo Might need option to replace characters like " with '
 * @todo Needs option to escape non printable characters
 *   Escape codes: \### and \o### Octal, \x## Hex, \d### Decimal, and \u#### Unicode
 * @author David Neilsen <david@panmedia.co.nz>
 */

use XMod\Common\Args;
use XMod\Helper\MimeType;

class CSV {
  /**
   * @var type If true, quote all cells in the CSV.
   */
  public $quoteAll = false;

  /**
   * @var string[] An array of regular expressions that if present in a
   *   cell, the cell is quoted.
   */
  public $quoteChars = array(',', '"', "\n", "\r");

  /**
   * @var string[] An array of regular expressions that if present will be
   *   removed.
   */
  public $trimChars = array();

  /**
   * @var string[] An array of regular expressions that if present will be
   *   replaced with their escaped version.
   */
  public $escapeChars = array('"' => '""');

  /**
   * @var string Quote character to use (where applicable).
   */
  public $quote = '"';

  /**
   * @var string End of line (EOL) character to use.
   */
  public $eol = "\r\n";

  /**
   * @var string Cell seperator to use.
   */
  public $seperator = ',';

  /**
   * @var boolean If true, all EOL charaters (line feed, carriage return) are
   *   replaced with the specified EOL character(s).
   */
  public $normaliseEOL = true;

  /**
   * Creates an instance of CSV the output in an Excel CSV format.
   *
   * @return XMod\Format\CSV
   */
  public static function newExcel() {
      $csv = new static;
      $csv->setQuoteChars(',', '"', "\n", "\r");
      $csv->setEscapeChars(array('"' => '""'));
      $csv->setTrimChars();
      $csv->setEOL("\r\n");
      $csv->setQuote('"');
      $csv->setSeperator(',');
      $csv->setNormaliseEOL(true);
      return $csv;
  }

  /**
   * Tests a file to check if it is a CSV file. If the file does not exists
   * the result is undefined.
   *
   * @param string $file File path be checked.
   * @return boolean True if given file is a CSV file, otherwise false.
   */
  public static function isCSV($file) {
      return MimeType::is($file, 'text/plain', 'text/csv');
  }

  /**
   * Sends the correct header to force a browser to download a CSV file.
   *
   * @param string $name Name of the file to download.
   */
  public static function download($name) {
      header('Content-type: text/csv');
      header('Cache-Control: no-store, no-cache');
      header('Content-Disposition: attachment; filename="' . $name . '"');
  }

  /**
   * Attempts to detect the column delimiter in a string.
   *
   * @param string $string
   * @param string[] $characters Acceptable delimiters.
   * @return string
   */
  public static function detectDelimiter($string,
          $characters = array(',', '|', "\t")) {
      $characters = array_flip($characters);
      foreach ($characters as $char => $value) {
          $characters[$char] = substr_count($string, $char);
      }

      $max = max($characters);
      if ($max === 0) {
          return null;
      }
      foreach ($characters as $char => $value) {
          if ($value === $max) {
              break;
          }
      }
      return $char;
  }

  /**
   * Escapes and returns a value to the classes settings.
   *
   * @param mixed $value
   * @return string
   */
  public function escape($value) {
      // Normalise EOLs
      if ($this->normaliseEOL === true) {
          // @todo Convert this to a single regular expression
          // Normalise line feeds
          $value = preg_replace('/(?<!\r)\n/', $this->eol, $value);
          // Normalise carriage returns
          $value = preg_replace('/\r(?!\n)/', $this->eol, $value);
          // Normalise carriage return line feeds
          $value = str_replace("\r\n", $this->eol, $value);
      }

      // Trim illegal characters
      foreach ($this->trimChars as $char) {
          $value = preg_replace("/$char/", '', $value);
      }

      // Escape characters
      foreach ($this->escapeChars as $char => $escapedChar) {
          $value = str_replace($char, $escapedChar, $value);
      }

      // Quote cell
      $quote = false;
      if ($this->quoteAll === true) {
          $quote = true;
      } else {
          foreach ($this->quoteChars as $char) {
              if (preg_match("/$char/", $value) === 1) {
                  $quote = true;
                  break;
              }
          }
      }

      if ($quote === true) {
          $value = $this->quote . $value . $this->quote;
      }

      return $value;
  }

  /**
   * Converts an array of data, or an array of arguments to a CSV string.
   *
   * @param mixed[]... $data
   * @return string
   */
  public function implode($data) {
      $result = array();
      foreach (new Args(func_get_args()) as $value) {
          $result[] = $this->escape($value);
      }
      return implode($this->seperator, $result);
  }

  /**
   * Converts an array of data, or an array of arguments to a CSV string. The
   * ends with the EOL character.
   *
   * @param mixed[]... $data
   * @return string
   */
  public function row($data) {
      return $this->implode(func_get_args()) . $this->eol;
  }

  /**
   * Converts an array of arrays to a CSV string.
   *
   * @param mixed[][] $data
   * @param boolean $header If true the keys from the first array are output
   *   to the first row of the CSV.
   */
  public function data($data, $header = false) {
      $result = '';
      if ($header === true && empty($data) === false) {
          $result .= $this->row(array_keys($data[0]));
      }
      foreach ($data as $row) {
          $result .= $this->row($row);
      }
      return $result;
  }

  /**
   * Reads a file and returns and array made from the CSV data.
   *
   * @todo Needs rewriting for custom delimiters, new lines, and escape methods.
   * @param string $file Path to the file.
   * @param string $delimiter
   * @return string[][]
   */
  public function readFile($file) {
    $csv = array();
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $value) {
      $csv[] = str_getcsv($value, $this->seperator, $this->quote);
    }
    
    return $csv;
  }

  /**
   * @param boolean $quoteAll
   */
  public function setQuoteAll($quoteAll) {
    $this->quoteAll = $quoteAll;
  }

  /**
   * @param string[] $quoteChars
   */
  public function setQuoteChars() {
    $quoteChars = new Args(func_get_args());
    $this->quoteChars = $quoteChars->toArray();
  }

  /**
   * @param string[]... $trimChars
   */
  public function setTrimChars() {
    $trimChars = new Args(func_get_args());
    $this->trimChars = $trimChars->toArray();
  }

  /**
   * @param string[]... $escapeChars
   */
  public function setEscapeChars() {
    $escapeChars = new Args(func_get_args());
    $this->escapeChars = $escapeChars->toAssocArray();
  }

  /**
   * @param string $quote
   */
  public function setQuote($quote) {
    $this->quote = $quote;
  }

  /**
   * @param string $eol
   */
  public function setEOL($eol) {
    $this->eol = $eol;
  }

  /**
   * @param string $seperator
   */
  public function setSeperator($seperator) {
    $this->seperator = $seperator;
  }

  /**
   * @param boolean $normaliseEOL
   */
  public function setNormaliseEOL($normaliseEOL) {
    $this->normaliseEOL = $normaliseEOL;
  }
}