-- =====================================================================
-- Boletos bancários
-- =====================================================================
-- Funções auxiliares utilizada na geração de boletos bancários
-- =====================================================================

-- ---------------------------------------------------------------------
-- Determina o digito verificador usando módulo 10
-- ---------------------------------------------------------------------
-- Função que retorna o dígito verificador calculado pelo módulo 10.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.checkSumMod10(value varchar)
RETURNS char AS $$
DECLARE
  -- A soma conforme estamos computando os dígitos de verificação
  total  integer;
  weight integer;
  sizeOf integer;
  pos integer;
  
  aux char(2);
  aux1 char(1);
  aux2 char(1);
  
  remainder integer;
  j integer;
  h integer;
BEGIN
  total  := 0;
  weight := 2;
  pos    := 0;
  sizeOf := length(value);
  h := sizeOf;
  j := 1;
  
  -- Loop através do número de base
  WHILE (j <= sizeOf) LOOP
    -- Cada dígito do número é multiplicado por seu peso
    pos := CAST(substr(value,h,1) AS integer) * weight;
    
    WHILE (pos > 9) LOOP
      aux  := cast(pos AS char(2));
      aux1 := substr(aux, 1, 1);
      aux2 := substr(aux, 2, 1);
      pos  := int4(aux1) + int4(aux2);
    END LOOP;
    total := total + pos;
    
    -- Incrememtamos o peso
    IF (weight = 2) THEN
      weight := 1;
    ELSE
      weight := weight + 1;
    END IF;
    
    -- Incrementamos os contadores
    j:= j+1;
    h:= h-1;
  END LOOP;
  
  remainder := mod(total, 10);
  remainder := 10 - remainder;
  
  IF (remainder >= 10) THEN
    RETURN '0';
  ELSE
    RETURN trim(to_char(remainder, '9'))::char;
  END IF;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Determina o digito verificador usando módulo 11
-- ---------------------------------------------------------------------
-- Função que retorna o dígito verificador calculado pelo módulo 11 no
-- padrão Febraban.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.checkSumMod11(value varchar,
  maxFactor integer, ifTen char, ifZero char)
RETURNS char AS $$
DECLARE
  -- A soma conforme estamos computando os dígitos de verificação
  total  integer;
  weight  integer;
  sizeOf  integer;
  pos  integer;
  
  remainder  integer;
  DAC  char;
  j  integer;
  h  integer;
BEGIN
  total  := 0;
  weight := 2;
  pos    := 0;
  sizeOf := length(value);
  h := sizeOf;
  j := 1;

  IF (maxFactor IS NULL) THEN
    maxFactor := 9;
  END IF;
  
  -- Loop através do número de base
  WHILE (j <= sizeOf) LOOP
    -- Cada dígito do número é multiplicado por seu peso
    pos   := CAST(substr(value,h,1) AS integer) * weight;
    total := total + pos;
    
    -- Incrememtamos o peso
    IF (weight = maxFactor) THEN
      weight := 1;
    END IF;
    weight := weight + 1;
    
    -- Incrementamos os contadores
    j:= j+1;
    h:= h-1;
  END LOOP;
  
  -- Calcula o resto da divisão
  remainder := mod((total * 10), 11);

  -- Conforme o resto, determinamos algumas condições
  CASE (remainder)
    WHEN 0 THEN
      DAC := ifZero;
    WHEN 10 THEN
      DAC := ifTen;
    ELSE
      DAC := trim(to_char(remainder, '9'))::char;
  END CASE;

  RETURN DAC;
END;
$$ LANGUAGE 'plpgsql';

-- Exemplo Itaú
-- SELECT erp.checkSumMod11('3419166700000123451101234567880057123457000', 9, '1', '1');
-- Exemplo Bradesco
-- Com dígito 'P'
-- SELECT erp.checkSumMod11('1900000000001', 7, 'P', '0');
-- Com dígito '0'
-- SELECT erp.checkSumMod11('1900000000006', 7, 'P', '0');
-- Com outro dígito
-- SELECT erp.checkSumMod11('1900000000002', 7, 'P', '0');

-- ---------------------------------------------------------------------
-- Determina o digito verificador do código de barras
-- ---------------------------------------------------------------------
-- Função que retorna o dígito verificador do código de barras no padrão
-- Febraban, calculado pelo módulo 11.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.checkSumDAC(value varchar)
RETURNS char AS $$
DECLARE
  -- A soma conforme estamos computando os dígitos de verificação
  total  integer;
  weight  integer;
  sizeOf  integer;
  pos  integer;

  remainder  integer;
  digit  integer;
  j  integer;
  h  integer;
BEGIN
  total  := 0;
  weight := 2;
  pos    := 0;
  sizeOf := length(value);
  h := sizeOf;
  j := 1;

  -- Loop através do número de base
  WHILE (j <= sizeOf) LOOP
    -- Cada dígito do número é multiplicado por seu peso
    pos   := CAST(substr(value,h,1) AS integer) * weight;
    total := total + pos;
    
    -- Incrememtamos o peso
    IF (weight = 9) THEN
      weight := 1;
    END IF;
    weight := weight + 1;
    
    -- Incrementamos os contadores
    j:= j+1;
    h:= h-1;
  END LOOP;
  
  -- Calculamos o dígito verificador
  remainder := mod(total, 11);
  digit := 11 - remainder;
  
  IF ( (remainder = 0) OR (remainder = 1) OR (remainder = 10) ) THEN
    RETURN '1';
  END IF;

  RETURN trim(to_char(digit, '9'))::char;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém o número de identificação do título no banco (nosso número)
-- ---------------------------------------------------------------------
-- Função que obtém o número de identificação do título no banco, também
-- chamad de "nosso número", baseado nas regras da instituição pela qual
-- o boleto será emitido.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.buildBankIdentificationNumber(bankID char(3),
  agency varchar, account varchar, walletNumber varchar,
  sequentialNumber integer, invoiceID integer, parameters json)
RETURNS varchar AS $$
DECLARE
  ourNumber  varchar;
  documentNumber  varchar;
  customerCode  varchar;
  DAC  varchar;
BEGIN
  CASE bankID
    WHEN '237' THEN
      -- Banco Bradesco

      -- Primeiramente, formatamos os números

      -- Completa com zeros o número da carteira, se necessário
      IF (length(trim(walletNumber)) < 2) THEN
        walletNumber := LPAD(trim(walletNumber)::text, 2, '0');
      END IF;

      -- Completa com zeros o número sequencial
      ourNumber := sequentialNumber::varchar;
      IF (length(trim(ourNumber)) < 11) THEN
        ourNumber := LPAD(trim(ourNumber)::text, 11, '0');
      END IF;

      -- Calcula o DAC usando o módulo 11 com base 7 no padrão Bradesco
      DAC := erp.checkSumMod11(walletNumber || ourNumber, 7, 'P', '0');

      RETURN ourNumber || DAC;
    WHEN '341' THEN
      -- Banco Itaú
      -- Conferir

      -- Completa com zeros o nósso número, se necessário
      IF (length(trim(walletNumber)) < 3) THEN
        walletNumber := LPAD(trim(walletNumber)::text, 3, '0');
      END IF;

      -- Completa com zeros o nósso número, se necessário
      IF (length(trim(sequentialNumber)) < 8) THEN
        sequentialNumber := LPAD(trim(sequentialNumber)::text, 8, '0');
      END IF;
      
      -- Completa com zeros o número da agência, se necessário
      IF (length(trim(agency)) < 4) THEN
        agency := LPAD(trim(agency)::text, 4, '0');
      END IF;
      
      -- Retira o dígito verificador da conta, se necessário e completa com
      -- zeros
      IF (position('-' in account) > 0) THEN
        account = substr(account, 1, position('-' in account) - 1);
      END IF;
      IF (length(account) < 5) THEN
        account := LPAD(account::text, 5, '0');
      END IF;

      IF (walletNumber IN ('107', '122', '142', '143', '196', '198')) THEN
        IF ( (walletNumber = '198') AND (parameters->>'customerCode' IS NULL) ) THEN
          -- Caso não tenha sido informado o código do cliente e a
          -- carteira seja 198, dispara uma excessão
          RAISE EXCEPTION 'Não foi possível obter o número do código de barras para o banco emissor %',
            bankID
          USING HINT = 'O código do cliente não foi informado';
        END IF;

        -- Completa com zeros o número sequencial
        documentNumber := invoiceID::varchar;
        IF (length(trim(documentNumber)) < 7) THEN
          documentNumber := LPAD(trim(documentNumber)::text, 7, '0');
        END IF;

        -- Completa com zeros o código do cliente
        customerCode := parameters->>'customerCode';
        IF (length(trim(customerCode)) < 5) THEN
          customerCode := LPAD(trim(customerCode)::text, 5, '0');
        END IF;

        -- Calcula o DAC usando o módulo 10
        DAC := erp.checkSumMod10(walletNumber || ourNumber || documentNumber || customerCode);
      ELSE
        IF (walletNumber NOT IN ('126', '131', '146', '150', '168')) THEN
          -- Calcula o DAC usando o módulo 10
          DAC := erp.checkSumMod10(agency || account || walletNumber || ourNumber);
        ELSE
          -- Calcula o DAC usando o módulo 10
          DAC := erp.checkSumMod10(walletNumber || ourNumber);
        END IF;
      END IF;
      
      RETURN walletNumber || '/' || ourNumber || '-' || DAC;
    ELSE
      -- Disparamos uma exceção
      RAISE EXCEPTION 'Não foi possível obter o número de identificação do título no banco %',
        bankID
      USING HINT = 'O banco não está homologado';

      RETURN NULL;
  END CASE;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém o campo livre do boleto
-- ---------------------------------------------------------------------
-- Função que obtém os dígitos da faixa livre do código de um boleto
-- definido da posição 20 à 44, com base nas regras da instituição pela
-- qual o boleto será emitido, conforme determinado pela FEBRABAN.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getFreeField(bankID char(3),
  agency varchar, account varchar, walletNumber varchar,
  sequentialNumber integer, invoiceID integer, parameters json)
RETURNS varchar AS $$
DECLARE
  accountDAC  varchar;
  ourNumber  varchar;
  freeField  varchar;
  documentNumber  varchar;
  customerCode  varchar;
  DAC  varchar;
BEGIN
  CASE bankID
    WHEN '237' THEN
      -- Banco Bradesco

      -- Primeiramente, formatamos os números

      -- Retira o dígito verificador da agência
      IF (position('-' in agency) > 0) THEN
        agency := substr(agency, 1, position('-' in agency) - 1);
      END IF;

      -- Completa com zeros o número da agência, se necessário
      IF (length(agency) < 4) THEN
        agency := LPAD(agency::text, 4, '0');
      END IF;

      -- Retira o dígito verificador da conta
      IF (position('-' in account) > 0) THEN
        account := substr(account, 1, position('-' in account) - 1);
      END IF;

      -- Completa com zeros o número da conta, se necessário
      IF (length(account) < 7) THEN
        account := LPAD(account::text, 7, '0');
      END IF;

      -- Completa com zeros o número da carteira, se necessário
      IF (length(trim(walletNumber)) < 2) THEN
        walletNumber := LPAD(trim(walletNumber)::text, 2, '0');
      END IF;

      -- Completa com zeros o número sequencial
      ourNumber := sequentialNumber::varchar;
      IF (length(trim(ourNumber)) < 11) THEN
        ourNumber := LPAD(trim(ourNumber)::text, 11, '0');
      END IF;
      
      freeField := agency || walletNumber || ourNumber || account || '0';
    WHEN '341' THEN
      -- Banco Itaú

      -- Primeiramente, formatamos os números

      -- Retira o dígito verificador da agência
      IF (position('-' in agency) > 0) THEN
        agency := substr(agency, 1, position('-' in agency) - 1);
      END IF;

      -- Completa com zeros o número da agência, se necessário
      IF (length(agency) < 4) THEN
        agency := LPAD(agency::text, 4, '0');
      END IF;

      -- Retira o dígito verificador da conta
      IF (position('-' in account) > 0) THEN
        account := substr(account, 1, position('-' in account) - 1);
      END IF;

      -- Completa com zeros o número da conta, se necessário
      IF (length(account) < 5) THEN
        account := LPAD(account::text, 5, '0');
      END IF;

      -- Completa com zeros o número da carteira, se necessário
      IF (length(trim(walletNumber)) < 3) THEN
        walletNumber := LPAD(trim(walletNumber)::text, 3, '0');
      END IF;

      -- Completa com zeros o número sequencial
      ourNumber := sequentialNumber::varchar;
      IF (length(trim(ourNumber)) < 8) THEN
        ourNumber := LPAD(trim(ourNumber)::text, 8, '0');
      END IF;

      IF (walletNumber IN ('107', '122', '142', '143', '196', '198')) THEN
        IF ( (walletNumber = '198') AND (parameters->>'customerCode' IS NULL) ) THEN
          -- Caso não tenha sido informado o código do cliente e a
          -- carteira seja 198, dispara uma excessão
          RAISE EXCEPTION 'Não foi possível obter o número do código de barras para o banco emissor %',
            bankID
          USING HINT = 'O código do cliente não foi informado';
        END IF;

        -- Completa com zeros o número sequencial
        documentNumber := invoiceID::varchar;
        IF (length(trim(documentNumber)) < 7) THEN
          documentNumber := LPAD(trim(documentNumber)::text, 7, '0');
        END IF;

        -- Completa com zeros o código do cliente
        customerCode := parameters->>'customerCode';
        IF (length(trim(customerCode)) < 5) THEN
          customerCode := LPAD(trim(customerCode)::text, 5, '0');
        END IF;

        -- Calcula o DAC usando o módulo 11 com base 9 no padrão Itaú
        DAC := erp.checkSumMod11(walletNumber || ourNumber || documentNumber || customerCode, 9, '1', '1');

        freeField := walletNumber || ourNumber || documentNumber || customerCode || DAC || '0';
      ELSE
        IF (walletNumber NOT IN ('126', '131', '146', '150', '168')) THEN
          -- Calcula o DAC usando o módulo 11 com base 9 no padrão Itaú
          DAC := erp.checkSumMod11(agency || account || walletNumber || ourNumber, 9, '1', '1');
        ELSE
          -- Calcula o DAC usando o módulo 11 com base 9 no padrão Itaú
          DAC := erp.checkSumMod11(agency || account, 9, '1', '1');
        END IF;

        accountDAC := erp.checkSumMod10(agency || account);

        -- Precisamos acrescentar o DV da conta
        freeField := walletNumber || ourNumber || DAC || agency || account || accountDAC || '000';
      END IF;
    ELSE
      -- Disparamos uma exceção
      RAISE EXCEPTION 'Não foi possível obter o número do código de barras para o banco emissor %',
        bankID
      USING HINT = 'O banco não está homologado';

      RETURN NULL;
  END CASE;
  
  RETURN trim(freeField);
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém a linha digitável de um boleto
-- ---------------------------------------------------------------------
-- Função que obtém a linha digitável de um boleto (IPTE).
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getDigitableLine(bankID char(3),
  agency varchar, account varchar, walletNumber varchar,
  sequentialNumber integer, invoiceID integer, dueDate date,
  valueToPay numeric, parameters json)
RETURNS varchar AS $$
DECLARE
  freeField  varchar;
  block20to24  varchar;
  block25to34  varchar;
  block35to44  varchar;
  DAC  varchar;
  part1  varchar;
  part2  varchar;
  part3  varchar;
  part4  varchar;
  dueDateFactor varchar;
  valueFilled  varchar;
BEGIN
  -- Obtemos o campo livre
  freeField := erp.getFreeField(bankID, agency, account, walletNumber, sequentialNumber, invoiceID, parameters);

  -- Divide as posições do código Febraban de 20 a 44 em 3 blocos de
  -- 5, 10 e 10 caracteres cada.
  block20to24 := substr(freeField, 1, 5);
  block25to34 := substr(freeField, 6, 10);
  block35to44 := substr(freeField, 16, 10);

  -- Calcula o primeiro bloco
  DAC := erp.checkSumMod10(bankID || '9' || block20to24);
  part1 = bankID || '9' || block20to24 || DAC;

  -- Inclui um ponto na sua 6ª posição (parte1)
  part1 = substr(part1, 1, 5) || '.' || substr(part1, 6);

  -- Calcula o segundo bloco
  DAC := erp.checkSumMod10(block25to34);
  part2 = block25to34 || DAC;

  -- Inclui um ponto na sua 6ª posição (parte2)
  part2 = substr(part2, 1, 5) || '.' || substr(part2, 6);

  -- Calcula o terceiro bloco
  DAC := erp.checkSumMod10(block35to44);
  part3 = block35to44 || DAC;

  -- Inclui um ponto na sua 6ª posição (parte3)
  part3 = substr(part3, 1, 5) || '.' || substr(part3, 6);

  -- Calcula o fator de vencimento, expresso por meio de 4 dígitos, e
  -- que é utilizado para identificar a data de vencimento do título
  IF dueDate < '2025-02-22'::date THEN
    -- Consideramos a data base de 07/10/1997
    dueDateFactor = dueDate - '1997-10-07'::date;
  ELSE
    -- Consideramos a data base de 22/02/2025 e que o fator de
    -- vencimento inicia em 1000
    dueDateFactor = dueDate - '2025-02-22'::date + 1000;
  END IF;
  IF (length(dueDateFactor) < 4) THEN
    dueDateFactor := LPAD(dueDateFactor::text, 4, '0');
  END IF;

  -- Formata o valor
  valueFilled := trim(translate(translate(to_char(valueToPay, '00000000D99'), ',', ''), '.', ''));

  -- Calcula o DAC do código de barras
  DAC := erp.checkSumDAC(bankID || '9' || dueDateFactor || valueFilled || freeField);

  -- Calcula o quarto bloco
  part4 := dueDateFactor || valueFilled;

  RETURN part1 || ' ' || part2 || ' ' || part3 || ' ' || DAC || ' ' || part4;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém os números do código de barras de um boleto
-- ---------------------------------------------------------------------
-- Função que obtém o número usado para gerar o código de barras de um
-- boleto no padrão Febraban.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.buildBarCodeNumber(bankID char(3),
  agency varchar, account varchar, walletNumber varchar,
  ourNumber varchar, dueDate date, slipValue numeric(12,2))
RETURNS varchar AS $$
DECLARE
  barcode       varchar;
  field1        varchar;
  field2        varchar;
  field3        varchar;
  field4        varchar;
  DAC           char;
  DAC1          char;
  DAC2          char;
  dueDateFactor varchar;
  slipValueTXT  varchar;
BEGIN
  CASE bankID
    WHEN '237' THEN
      IF (length(ourNumber) < 8) THEN
        ourNumber := LPAD(ourNumber::text, 11, '0');
      END IF;
      
      -- Completa com zeros o número da agência, se necessário
      IF (length(agency) < 4) THEN
        agency := LPAD(agency::text, 4, '0');
      END IF;
      
      -- Retira o dígito verificador da conta, se necessário e completa com
      -- zeros
      IF (position('-' in account) > 0) THEN
        account = substr(account, 1, position('-' in account) - 1);
      END IF;
      IF (length(account) < 5) THEN
        account := LPAD(account::text, 5, '0');
      END IF;
      
      -- Remove pontuação desnecessária
      ourNumber := translate(ourNumber, '-', '');
      ourNumber := translate(ourNumber, '/', '');
      ourNumber := translate(ourNumber, '.', '');
      
      -- Calcula o fator de vencimento
      dueDateFactor = dueDate - '1997-10-07'::date;
      IF (length(dueDateFactor) < 4) THEN
        dueDateFactor := LPAD(dueDateFactor::text, 4, '0');
      END IF;
      
      -- Converte o valor
      slipValueTXT := trim(translate(to_char(slipValue::real, '00000000D99'), ',', ''));
      
      -- Calcula o DAC1 e DAC2
      DAC1 := erp.checkSumMod10(agency || account || walletNumber || ourNumber);
      DAC2 := erp.checkSumMod10(agency || account);
      
      -- Monta o número do código de barras
      barcode := bankID || '9' || dueDateFactor || slipValueTXT || walletNumber || ourNumber || DAC1 || agency || account || DAC2 || '000';
      
      -- Calcula o DAC do código de barras
      DAC := erp.checkSumMod11(barcode, 9, '1', '1');
      
      -- Insere o DAC no código de barras
      barcode := substr(barcode, 1, 4) || DAC || substr(barcode, 5, 40);
    WHEN '341' THEN
      -- Banco Itaú
      -- Conferir
      IF (length(ourNumber) < 8) THEN
        ourNumber := LPAD(ourNumber::text, 8, '0');
      END IF;
      
      -- Completa com zeros o número da agência, se necessário
      IF (length(agency) < 4) THEN
        agency := LPAD(agency::text, 4, '0');
      END IF;
      
      -- Retira o dígito verificador da conta, se necessário e completa com
      -- zeros
      IF (position('-' in account) > 0) THEN
        account = substr(account, 1, position('-' in account) - 1);
      END IF;
      IF (length(account) < 5) THEN
        account := LPAD(account::text, 5, '0');
      END IF;
      
      -- Remove pontuação desnecessária
      ourNumber := translate(ourNumber, '-', '');
      ourNumber := translate(ourNumber, '/', '');
      ourNumber := translate(ourNumber, '.', '');
      
      -- Calcula o fator de vencimento
      dueDateFactor = dueDate - '1997-10-07'::date;
      IF (length(dueDateFactor) < 4) THEN
        dueDateFactor := LPAD(dueDateFactor::text, 4, '0');
      END IF;
      
      -- Converte o valor
      slipValueTXT := trim(translate(to_char(slipValue::real, '00000000D99'), ',', ''));
      
      -- Calcula o DAC1 e DAC2
      DAC1 := erp.checkSumMod10(agency || account || walletNumber || ourNumber);
      DAC2 := erp.checkSumMod10(agency || account);
      
      -- Monta o número do código de barras
      barcode := bankID || '9' || dueDateFactor || slipValueTXT || walletNumber || ourNumber || DAC1 || agency || account || DAC2 || '000';
      
      -- Calcula o DAC do código de barras
      DAC := erp.checkSumMod11(barcode, 9, '1', '1');
      
      -- Insere o DAC no código de barras
      barcode := substr(barcode, 1, 4) || DAC || substr(barcode, 5, 40);
    ELSE
      -- Disparamos uma exceção
      RAISE EXCEPTION 'Não foi possível obter o número do código de barras para o banco emissor %',
        bankID
      USING HINT = 'O banco não está homologado';

      RETURN NULL;
  END CASE;
  
  RETURN trim(barcode);
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém a linha digitável através do código de barras de um boleto
-- ---------------------------------------------------------------------
-- Função que obtém a linha digitável de um boleto bancário em função do
-- número do código de barras do boleto.
-- 
-- Considerando o seguinte conteúdo do Código de Barras:
--   34196166700000123451101234567880057123457000
--   onde:
--          341 = Código do Banco
--            9 = Código da Moeda
--            6 = DAC do Código de Barras
--         1667 = Fator de Vencimento (01/05/2002)
--   0000012345 = Valor do Título (123,45)
-- 110123456788 = Carteira / Nosso Número / DAC (110/12345678-8)
--   0057123457 = Agência / Conta Corrente / DAC (0057/12345-7)
--          000 = Posições Livres (zeros)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.buildIPTE(barCodeNumber varchar)
RETURNS varchar AS $$
DECLARE
  ipte   varchar;
  bankID varchar;
  moneyID varchar;
  DAC char;
  dueDateFactor varchar;
  slipValue varchar;
  walletNumber varchar;
  ourNumber varchar;
  DAC1 char;
  agency varchar;
  account varchar;
  Field1 varchar;
  Field2 varchar;
  Field3 varchar;
  Field4 varchar;
BEGIN
  IF (length(barCodeNumber) <> 44) THEN
    ipte := 'ERRO';
  ELSE
    -- Recover data from Bar Code Number
    bankID        := substr(barCodeNumber, 1, 3);
    moneyID       := substr(barCodeNumber, 4, 1);
    DAC           := substr(barCodeNumber, 5, 1);
    dueDateFactor := substr(barCodeNumber, 6, 4);
    slipValue     := substr(barCodeNumber,10,10);
    walletNumber  := substr(barCodeNumber,20, 3);
    ourNumber     := substr(barCodeNumber,23, 8);
    DAC1          := substr(barCodeNumber,31, 1);
    agency        := substr(barCodeNumber,32, 4);
    account       := substr(barCodeNumber,36, 6);
    
    -- RAISE NOTICE 'bankID = %', bankID;
    -- RAISE NOTICE 'moneyID = %', moneyID;
    -- RAISE NOTICE 'DAC = %', DAC;
    -- RAISE NOTICE 'dueDateFactor = %', dueDateFactor;
    -- RAISE NOTICE 'slipValue = %', slipValue;
    -- RAISE NOTICE 'walletNumber = %', walletNumber;
    -- RAISE NOTICE 'ourNumber = %', ourNumber;
    -- RAISE NOTICE 'DAC1 = %', DAC1;
    -- RAISE NOTICE 'agency = %', agency;
    -- RAISE NOTICE 'account = %', account;
    CASE bankID
      WHEN '341' THEN
        -- Field 1
        Field1 := bankID || moneyID || walletNumber || substr(ourNumber, 1, 2);
        Field1 := Field1 || erp.checkSumMod10(Field1);
        
        -- Field 2
        Field2 := substr(ourNumber, 3, 6) || DAC1 || substr(agency, 1, 3);
        Field2 := Field2 || erp.checkSumMod10(Field2);
        
        -- Field 3
        Field3 := substr(agency, 4, 1) || account || '000';
        Field3 := Field3 || erp.checkSumMod10(Field3);
        
        -- Field 4
        Field4 := dueDateFactor || slipValue;
        
        ipte   := substr(field1, 1, 5) || '.' || substr(field1, 6, 5) || ' ' || substr(field2, 1, 5) || '.' || substr(field2, 6, 6) || ' ' || substr(field3, 1, 5) || '.' || substr(field3, 6, 6) || ' ' || DAC || ' ' || field4;
      ELSE
        ipte := 'Não definido';
    END CASE;
  END IF;
  
  RETURN trim(ipte);
END;
$$ LANGUAGE 'plpgsql';
