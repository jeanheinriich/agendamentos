-- =====================================================================
-- FUNÇÕES AUXILIARES
-- =====================================================================
-- Funções auxiliares utilizadas em várias partes do sistema.
-- ---------------------------------------------------------------------
-- Definição de funções auxiliares genéricas que permitem desempenhar   
-- pequenas tarefas e que podem ser utilizadas em várias partes.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Remover acentuação de um texto
-- ---------------------------------------------------------------------
-- Remeve caracteres acentuados de um texto, permitindo que a busca por
-- um determinado valor ocorra indistamente à acentuação da palavra.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.unaccented(text)
RETURNS text AS
$BODY$
SELECT translate($1,'áàâãäéèêëíìïóòôõöúùûüÁÀÂÃÄÉÈÊËÍÌÏÓÒÔÕÖÚÙÛÜçÇ',
                    'aaaaaeeeeiiiooooouuuuAAAAAEEEEIIIOOOOOUUUUcC');
$BODY$
LANGUAGE 'sql' IMMUTABLE STRICT;

-- ---------------------------------------------------------------------
-- Nome do mês
-- ---------------------------------------------------------------------
-- Obtém o nome do mês com base no número do mês, onde Janeiro
-- corresponde ao primeiro mês.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.monthName (monthNumber integer) 
RETURNS varchar AS $$
DECLARE
  result  varchar;
BEGIN
  IF ( (monthNumber < 1) OR (monthNumber > 12) ) THEN
    -- Disparamos uma exceção
    RAISE EXCEPTION '% não é um número de mês válido', monthNumber
    USING HINT = 'Os meses devem estar entre 1 e 12';
  END IF;

  SELECT INTO result 
    CASE monthNumber
      WHEN  1 THEN 'Janeiro'
      WHEN  2 THEN 'Fevereiro'
      WHEN  3 THEN 'Março'
      WHEN  4 THEN 'Abril'
      WHEN  5 THEN 'Maio'
      WHEN  6 THEN 'Junho'
      WHEN  7 THEN 'Julho'
      WHEN  8 THEN 'Agosto'
      WHEN  9 THEN 'Setembro'
      WHEN 10 THEN 'Outubro'
      WHEN 11 THEN 'Novembro'
      WHEN 12 THEN 'Dezembro'
    END;
  RETURN result;
END;
$$ LANGUAGE plpgsql IMMUTABLE STRICT;

-- ---------------------------------------------------------------------
-- Dia da semana
-- ---------------------------------------------------------------------
-- Obtém o nome do dia da semana com base no número do dia, onde Domingo
-- corresponde ao primeiro dia da semana.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.dayOfWeekName (dayOfWeek integer)
RETURNS varchar AS $$
DECLARE
  result  varchar;
BEGIN
  IF ( (dayOfWeek < 0) OR (dayOfWeek > 6) ) THEN
    -- Disparamos uma exceção
    RAISE EXCEPTION '% não é um número de dia da semana válido', dayOfWeek
    USING HINT = 'Os números dos dias devem estar entre 0 e 6';
  END IF;

  SELECT INTO result 
    CASE dayOfWeek
      WHEN  0 THEN 'Domingo'
      WHEN  1 THEN 'Segunda-Feira'
      WHEN  2 THEN 'Terça-Feira'
      WHEN  3 THEN 'Quarta-Feira'
      WHEN  4 THEN 'Quinta-Feira'
      WHEN  5 THEN 'Sexta-Feira'
      WHEN  6 THEN 'Sábado'
    END;
  RETURN result;
END;
$$ LANGUAGE plpgsql IMMUTABLE STRICT;

-- ---------------------------------------------------------------------
-- Função: xor
-- Descrição: Retorna a operação booleana XOR entre dois valores
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.XOR(a boolean, b boolean)
RETURNS boolean AS
$BODY$
  SELECT (a and not b) or (b and not a);
$BODY$
LANGUAGE 'sql' IMMUTABLE STRICT;

-- ---------------------------------------------------------------------
-- Função: deleteElementOfArray
-- Descrição: Elimina um registro de uma matriz pelo seu índice
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.deleteElementOfArray(anyarray, int)
  RETURNS anyarray AS $$
  SELECT $1[1:$2-1] || $1[$2+1:2147483647];
$$ 
LANGUAGE 'sql' IMMUTABLE;

-- ---------------------------------------------------------------------
-- É numérico
-- ---------------------------------------------------------------------
-- Determina se um valor contido em um texto é um número válido.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.isNumeric(text)
RETURNS boolean AS $$
  SELECT $1 ~ '^[0-9]+$'
$$ LANGUAGE 'sql';

-- ---------------------------------------------------------------------
-- É ano bissexto
-- ---------------------------------------------------------------------
-- Determina se um ano é bissexto. Chama-se ano bissexto o ano ao qual é
-- acrescentado um dia extra, ficando com 366 dias, um dia a mais do que
-- os anos normais de 365 dias, ocorrendo a cada quatro anos.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.isLeapYear(year integer)
RETURNS boolean AS $$
BEGIN
  RETURN (( year % 4 = 0 and year % 100 != 0 ) or ( year % 400 = 0 ) );
END;
$$ LANGUAGE 'plpgsql' VOLATILE CALLED ON NULL INPUT SECURITY INVOKER;

-- ---------------------------------------------------------------------
-- Constroi a data válida à partir dos dia/mês/ano.
-- ---------------------------------------------------------------------
-- Função que construi uma data em função do dia, mês e ano.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.buidDate(year integer, month integer,
  day integer)
RETURNS date AS
$$
DECLARE
  maxDaysInMonth  smallint;
BEGIN
  CASE
    WHEN month IN (1, 3, 5, 7, 8, 10, 12) THEN
      maxDaysInMonth := 31;
    WHEN month IN (4, 6, 9, 11) THEN
      maxDaysInMonth := 30;
    WHEN month = 2 AND public.isLeapYear(year) THEN
      maxDaysInMonth := 29;
    ELSE
      maxDaysInMonth := 28;
  END CASE;

  IF (day > maxDaysInMonth) THEN
    day := maxDaysInMonth;
  END IF;

  RETURN (year::text || '-' || month::text || '-' || day::text)::Date;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Determina o CRC16
-- ---------------------------------------------------------------------
-- Função que calcula o CRC de 16 bits de um texto informado de maneira
-- a termos um valor único de 5 dígitos.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.crc16(value text) RETURNS integer AS $$
DECLARE
  crc integer;
  length integer;
  i integer;
  j integer;
  c CHAR(1);
  crc_table integer[256];
  poly integer := 4129;
BEGIN
  IF value = '' THEN
    RETURN 0;
  END IF;

  FOR i IN 0..255 LOOP
    crc_table[i] := i << 8;
    FOR j IN 0..7 LOOP
      IF (crc_table[i] & 32768) = 32768 THEN
        crc_table[i] := (crc_table[i] << 1) # poly;
      ELSE
        crc_table[i] := crc_table[i] << 1;
      END IF;
    END LOOP;
  END LOOP;

  crc := 0;
  length := LENGTH(value);
  FOR i IN 1..length LOOP
    c := SUBSTRING(value FROM i FOR 1);
    crc := (crc << 8) # crc_table[((crc >> 8) & 255) # ASCII(c)];
  END LOOP;
  RETURN crc & 65535;
END;
$$ IMMUTABLE LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- Determina o CRC32
-- ---------------------------------------------------------------------
-- Função que calcula o CRC de 32 bits de um texto informado de maneira
-- a termos um valor único de 10 dígitos.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.crc32(value text) RETURNS bigint AS $$
DECLARE
  tmp bigint;
  i int;
  j int;
  byte_length int;
  binary_string bytea;
BEGIN
  IF value = '' THEN
    RETURN 0;
  END IF;

  i = 0;
  -- Iniciamos com a máscara 0xFFFFFFFF
  tmp = 4294967295;
  byte_length = bit_length(value) / 8;
  binary_string = decode(replace(value, E'\\\\', E'\\\\\\\\'), 'escape');
  LOOP
    tmp = (tmp # get_byte(binary_string, i))::bigint;
    i = i + 1;
    j = 0;
    LOOP
      tmp = ((tmp >> 1) # (3988292384 * (tmp & 1)))::bigint;
      j = j + 1;
      IF j >= 8 THEN
        EXIT;
      END IF;
    END LOOP;
    IF i >= byte_length THEN
      EXIT;
    END IF;
  END LOOP;
  RETURN (tmp # 4294967295);
END
$$ IMMUTABLE LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- Obtém a variante para uma placa
-- ---------------------------------------------------------------------
-- Função que obtém a variação entre placa no padrão Mercosul e o padrão
-- antigo, permitindo a busca independente da maneira como foi colocado.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.getPlateVariant(plate varchar)
RETURNS varchar AS
$$
DECLARE
  fiveCharOriginal  varchar;
  fiveCharModified  varchar;
BEGIN
  plate := UPPER(TRIM(plate));

  IF (LENGTH(plate) <> 7) THEN
    RETURN plate;
  END IF;

  fiveCharOriginal := SUBSTRING(plate, 5, 1);

  IF (fiveCharOriginal ~ '^[0-9\.]+$') THEN
    CASE (fiveCharOriginal)
      WHEN '0' THEN fiveCharModified:= 'A';
      WHEN '1' THEN fiveCharModified:= 'B';
      WHEN '2' THEN fiveCharModified:= 'C';
      WHEN '3' THEN fiveCharModified:= 'D';
      WHEN '4' THEN fiveCharModified:= 'E';
      WHEN '5' THEN fiveCharModified:= 'F';
      WHEN '6' THEN fiveCharModified:= 'G';
      WHEN '7' THEN fiveCharModified:= 'H';
      WHEN '8' THEN fiveCharModified:= 'I';
      WHEN '9' THEN fiveCharModified:= 'J';
    END CASE;
  ELSE
    CASE (fiveCharOriginal)
      WHEN 'A' THEN fiveCharModified:= '0';
      WHEN 'B' THEN fiveCharModified:= '1';
      WHEN 'C' THEN fiveCharModified:= '2';
      WHEN 'D' THEN fiveCharModified:= '3';
      WHEN 'E' THEN fiveCharModified:= '4';
      WHEN 'F' THEN fiveCharModified:= '5';
      WHEN 'G' THEN fiveCharModified:= '6';
      WHEN 'H' THEN fiveCharModified:= '7';
      WHEN 'I' THEN fiveCharModified:= '8';
      WHEN 'J' THEN fiveCharModified:= '9';
      ELSE fiveCharModified := fiveCharOriginal;
    END CASE;
  END IF;

  RETURN SUBSTRING(plate, 1, 4) || fiveCharModified || SUBSTRING(plate, 6, 2);
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém se um texto está escrito em maiúsculas
-- ---------------------------------------------------------------------
-- Função que obtém se a maior parte de um texto está escrito em letras
-- maiúsculas.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.mostlyInUppercase(
  value text, percentage int)
RETURNS boolean AS
$$
DECLARE
  totalChars  int;
  inUppercase  int;
  fiveCharOriginal  varchar;
  fiveCharModified  varchar;
BEGIN
  IF (value IS NULL) THEN
    RETURN FALSE;
  END IF;

  IF (TRIM(value) = '') THEN
    RETURN FALSE;
  END IF;

  totalChars := length(value);
  inUppercase := length(regexp_replace(value, '[^[:upper:]]', '', 'g'));

  RETURN ((inUppercase * 100) / totalChars) >= percentage;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém a distância em metros entre duas coordenadas
-- ---------------------------------------------------------------------
-- Função que obtém a distância em metros entre duas coordenadas
-- geográficas.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.distance(
  lat1 double precision,
  lon1 double precision,
  lat2 double precision,
  lon2 double precision
)
RETURNS double precision AS
$$
DECLARE
  distance double precision;
BEGIN
  SELECT INTO distance
    (6371000 * acos(
      cos(radians(lat1)) * cos(radians(lat2)) *
      cos(radians(lon2) - radians(lon1)) +
      sin(radians(lat1)) * sin(radians(lat2))
    ));
RETURN distance;
END
$$
LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- Determina o dígito verificador de um ICCID
-- ---------------------------------------------------------------------
-- Função que calcula o dígito verificador de um ICCID
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.luhn(iccid text) RETURNS char AS $$
DECLARE
  iccidWithoutDigit text;
  reverseIccid text;
  i integer;
  digit integer;
  sum integer;
  verificationDigit integer;
BEGIN
  IF iccid IS NULL THEN
    RETURN NULL;
  END IF;

  IF (length(iccid) < 19) OR (length(iccid) > 20) THEN
    RAISE 'O ICCID deve ter 19 ou 20 dígitos'
      USING ERRCODE = 'restrict_violation';
  END IF;

  IF (length(iccid) = 20) THEN
    -- Remove o último dígito
    iccidWithoutDigit := substring(iccid, 1, 19);
  ELSE
    iccidWithoutDigit := iccid;
  END IF;

  reverseIccid := reverse(iccidWithoutDigit);

  sum := 0;
  FOR i IN 1..length(reverseIccid) LOOP
    digit := ASCII(SUBSTRING(reverseIccid FROM i FOR 1)) - ASCII('0');

    IF i % 2 = 1 THEN
      digit := digit * 2;
      IF digit > 9 THEN
        digit := digit - 9;
      END IF;
    END IF;

    sum := sum + digit;
  END LOOP;

  verificationDigit := (10 - (sum % 10)) % 10;

  RETURN chr(ASCII('0') + verificationDigit);
END;
$$ IMMUTABLE LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- Obtém um ICCID válido
-- ---------------------------------------------------------------------
-- Função que obtém a informação de um ICCID válido
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.validICCID(iccid text) RETURNS char AS $$
DECLARE
  iccidWithoutDigit text;
  verificationDigit char;
BEGIN
  IF iccid IS NULL THEN
    RETURN NULL;
  END IF;

  IF (length(iccid) < 19) OR (length(iccid) > 20) THEN
    RAISE NOTICE 'O ICCID deve ter 19 ou 20 dígitos'
      USING ERRCODE = 'restrict_violation';
  END IF;

  verificationDigit := public.luhn(iccid);
  IF (length(iccid) = 20) THEN
    -- Remove o último dígito
    iccidWithoutDigit := substring(iccid, 1, 19);

    -- Verifica se o dígito verificador é válido
    IF (verificationDigit <> substring(iccid, 20, 1)) THEN
      RAISE NOTICE 'O ICCID % não é válido', iccid;
      RETURN NULL;
    END IF;
  ELSE
    iccidWithoutDigit := iccid;
  END IF;

  RETURN iccidWithoutDigit || verificationDigit;
END;
$$ IMMUTABLE LANGUAGE plpgsql;
