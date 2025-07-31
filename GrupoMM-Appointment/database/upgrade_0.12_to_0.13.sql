-- Acrescentamos a informação do tipo do valor cobrado para os juros de
-- mora nos planos
ALTER TABLE erp.plans
  ADD COLUMN arrearInterestType integer NOT NULL DEFAULT 2;

-- Adicionamos a chave extrangeira para os tipos de valores para os
-- juros de mora na tabela de planos
ALTER TABLE erp.plans
  ADD CONSTRAINT plans_arrearinteresttype_fkey
      FOREIGN KEY (arrearInterestType)
        REFERENCES erp.measureTypes(measureTypeID)
        ON DELETE RESTRICT;

-- Acrescentamos a informação do tipo do valor cobrado para os juros de
-- mora nos boletos
ALTER TABLE erp.bankingBilletPayments
  ADD COLUMN arrearInterestType integer NOT NULL DEFAULT 2;

-- Acrescentamos a informação de parcelamento
ALTER TABLE erp.payments
  ADD COLUMN parcel integer NOT NULL DEFAULT 0;
ALTER TABLE erp.payments
  ADD COLUMN numberOfParcels integer NOT NULL DEFAULT 0;

-- Acrescentamos a informação de formatar os boletos de uma condição de
-- como carnê de pagamentos
ALTER TABLE erp.paymentConditions
  ADD COLUMN formatAsBooklet boolean DEFAULT FALSE;


-- ---------------------------------------------------------------------
-- Gera um carnê de pagamentos
-- ---------------------------------------------------------------------
-- Modificamos esta função para permitir incluir o início do período a
-- ser cobrado, bem como de outros valores presentes em contrato.
-- ---------------------------------------------------------------------
DROP FUNCTION erp.createCarnet(FcontractorID integer,
  FcustomerID int, FsubsidiaryID int, FfirstDueDate date,
  FnumberOfParcels int, FuserID integer, Finstallations integer array);

CREATE OR REPLACE FUNCTION erp.createCarnet(FcontractorID integer,
  FcustomerID int, FsubsidiaryID int, FstartDate date, FnumberOfParcels int,
  FfirstDueDate date, FuserID integer, Finstallations integer array)
RETURNS integer AS
$$
DECLARE
  -- Os parâmetros para cálculo de cada mensalidade e do valor total a
  -- ser cobrado
  parcelNumber  int;
  referenceDate  date;
  startDateOfPeriod  date;
  endDateOfPeriod  date;
  startDateOfBillingPeriod  date;
  monthlyValue  numeric;
  discountTotal  numeric;
  finalValue  numeric;
  daysToConsider  smallint;
  dueDate  date;

  -- O cálculo do valor de mensalidade por dia
  daysInPeriod  smallint;
  dayPrice  numeric;

  -- A análise de subsídios aplicados
  subsidyRecord  record;
  startOfSubsidy  date;
  endOfSubsidy  date;
  daysInSubsidy  smallint;
  discountValue  numeric;

  -- As informações do meio de pagamento
  FpaymentMethodID  integer;
  FdefinedMethodID  integer;

  -- Os padrâmetros de multa, juros de mora e instrução do boleto
  FfineValue  numeric(8,4);
  FarrearInterestType  integer;
  FarrearInterest  numeric(8,4);
  FinstructionID  integer;
  FinstructionDays  integer;

  -- O ID do último contrato
  lastContractID  integer;

  -- Os dados da instalação e de valores a serem cobrados
  installation  record;

  -- A análise de outras mensalidades presentes em contrato
  monthlyFeesRecord  record;

  -- O ID do carnê e da fatura
  newCarnetID  int;
  newInvoiceID  integer;

  -- Os dados dos boletos a serem gerados
  billets  jsonb[];
  billet  jsonb;
  billing  jsonb;
  billings  jsonb[];
  billed  jsonb;
  billeds  jsonb[];

  -- Os dados da fatura
  invoice  record;

  -- Parâmetris do boleto a ser gerado
  FbillingCounter  integer;
  ourNumber  varchar(12);
  paymentSituationID  integer;
  droppedTypeID  integer;
BEGIN
  -- Inicializamos as variáveis de processamento
  parcelNumber := 1;
  referenceDate := FstartDate;
  dueDate := FfirstDueDate;
  lastContractID := 0;
  billets := jsonb '{ }';

  IF (FnumberOfParcels > 0) THEN
    LOOP
      -- Estamos processando cada período da cobrança antecipada, então
      -- precisamos analisar os períodos sendo cobrados e construir o
      -- valor a ser cobrado baseado nos valores computados a cada mês
      -- em cada item de contrato informado, montando as respectivas
      -- parcelas do carnê
      RAISE NOTICE 'Número da parcela: %', parcelNumber;
      RAISE NOTICE 'Data de referência: %', TO_CHAR(referenceDate, 'DD/MM/YYYY');

      -- Inicializamos o registro de valores desta parcela
      billet := format(
        '{"parcel":%s,"dueDate":"%s","referenceMonth":"%s","billings":[],"billeds":[]}',
        parcelNumber,
        dueDate,
        to_char(referenceDate, 'MM/YYYY')
      )::jsonb;
      billings := jsonb '{ }';
      billeds := jsonb '{ }';

      -- Recupera as informações das instalações para as quais estamos
      -- emitindo o carnê
      FOR installation IN
        SELECT I.installationID AS id,
               I.installationNumber AS number,
               C.signatureDate,
               C.startTermAfterInstallation,
               P.prorata,
               I.startDate,
               I.monthprice,
               I.contractID,
               I.lastDayOfBillingPeriod
          FROM erp.installations AS I
         INNER JOIN erp.contracts AS C ON (I.contractID = C.contractID)
         INNER JOIN erp.plans AS P ON (P.planID = I.planID)
         WHERE I.contractorID = FcontractorID
           AND C.deleted = false
           AND C.active = true
           AND I.endDate IS NULL
           AND I.installationID = ANY(Finstallations)
         ORDER BY C.customerPayerid, C.subsidiaryPayerid, C.unifybilling, C.contractID, I.installationid
      LOOP
        -- Para cada parcela sendo calculada, analisamos se devemos ou
        -- não cobrar o período para cada um dos itens de contrato
        -- informados, de forma que conseguimos construir o valor final
        -- desta parcela corretamente
        RAISE NOTICE 'Número do item de contrato: %', installation.number;
        RAISE NOTICE 'Data da assinatura do contrato: %', TO_CHAR(installation.signatureDate, 'DD/MM/YYYY');
        RAISE NOTICE 'Data do início do item de contrato: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');
        RAISE NOTICE 'Iniciar cobrança após instalação: %', CASE WHEN installation.startTermAfterInstallation THEN 'Sim' ELSE 'Não' END;
        RAISE NOTICE 'Cobrar proporcional ao serviço prestado: %', CASE WHEN installation.prorata THEN 'Sim' ELSE 'Não' END;

        -- Determinamos o período de cobrança em um mês
        startDateOfPeriod := referenceDate;
        endDateOfPeriod := startDateOfPeriod + interval '1 month' - interval '1 day';
        RAISE NOTICE 'Período de % à %', startDateOfPeriod, endDateOfPeriod;

        -- Determinamos à partir de qual data devemos cobrar
        IF (installation.prorata) THEN
          -- Devemos cobrar proporcionalmente, então determinamos quando
          -- isto ocorre
          IF (installation.startTermAfterInstallation) THEN
            IF (installation.startDate IS NULL) THEN
              -- Como a instalação não ocorreu ainda, então consideramos
              -- o início do período mesmo
              RAISE NOTICE 'Instalação não ocorreu, considerando o período inteiro';
              startDateOfBillingPeriod := startDateOfPeriod;
            ELSE
              -- Verificamos se o início do item de contrato ocorreu
              -- durante o período que estamos analisado
              IF (installation.startDate >= startDateOfPeriod) THEN
                -- Consideramos a data de instalação
                RAISE NOTICE 'Consideramos a data de instalação';
                startDateOfBillingPeriod := installation.startDate;
              ELSE
                -- Como a instalação se deu antes do início do período que
                -- iremos cobrar, então consideramos o início do período
                -- mesmo
                RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          ELSE
            IF (installation.signatureDate IS NULL) THEN
              -- Como o contrato não foi assinado ainda, então consideramos
              -- o início do período mesmo
              RAISE NOTICE 'Contrato não foi assinado, considerando o período inteiro';
              startDateOfBillingPeriod := baseDate;
            ELSE
              -- Verificamos se a assintatura ocorreu durante o período
              -- sendo analisado
              IF (installation.signatureDate >= startDateOfPeriod) THEN
                -- Consideramos a data de assinatura
                RAISE NOTICE 'Consideramos a data de assinatura do contrato';
                startDateOfBillingPeriod := installation.signatureDate;
              ELSE
                -- Como a assinatura do contrato se deu antes do início do
                -- período que iremos cobrar, então consideramos o início
                -- do período mesmo
                RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          END IF;
        ELSE
          -- Devemos cobrar integralmente, então o início se dá sempre no
          -- início do período apurado
          RAISE NOTICE 'Consideramos o período inteiro';
          startDateOfBillingPeriod := startDateOfPeriod;
        END IF;

        -- Verificamos se já foram realizadas cobranças de períodos
        -- neste item de contrato
        IF (installation.lastDayOfBillingPeriod IS NOT NULL) THEN
          -- Precisamos levar em consideração também o último período já
          -- cobrado se ele for superior ao período que estamos cobrando
          IF ((installation.lastDayOfBillingPeriod + interval '1 day') > startDateOfBillingPeriod) THEN
            startDateOfBillingPeriod := installation.lastDayOfBillingPeriod + interval '1 day';
            RAISE NOTICE 'Consideramos o período iniciando em %', startDateOfBillingPeriod;
          END IF;
        END IF;

        -- Calculamos a quantidade de dias no período
        daysInPeriod := DATE_PART('day',
            endDateOfPeriod::timestamp - startDateOfPeriod::timestamp
          ) + 1;
        RAISE NOTICE 'Este período possui % dias', daysInPeriod;

        -- Calculamos o valor diário com base na mensalidade
        dayPrice = installation.monthPrice / daysInPeriod;
        RAISE NOTICE 'O valor diário é %', ROUND(dayPrice, 2);

        -- Verificamos se precisamos cobrar algum período nesta parcela
        -- para esta instalação
        IF (startDateOfBillingPeriod <= endDateOfPeriod) THEN
          IF (installation.prorata) THEN
            IF (startDateOfBillingPeriod = startDateOfPeriod) THEN
              -- Cobramos o valor integral da mensalidade
              monthlyValue := installation.monthPrice;
              RAISE NOTICE 'Cobrando valor integral da mensalidade';
            ELSE
              -- Cobramos o valor proporcional

              -- Calculamos a quantidade de dias a serem cobrados
              daysToConsider := DATE_PART('day',
                  endDateOfPeriod::timestamp - startDateOfBillingPeriod::timestamp
                ) + 1;
              
              -- O serviço será prestado por uma parte do mês
              monthlyValue := ROUND(daysToConsider * dayPrice, 2);
            END IF;
          ELSE
            -- Cobramos sempre o valor integral da mensalidade
            monthlyValue := installation.monthPrice;
            RAISE NOTICE 'Cobrando valor integral da mensalidade';
          END IF;

          IF (monthlyValue > 0.00) THEN
            -- Acrescentamos esta valor a ser cobrado nesta mensalidade
            RAISE NOTICE 'O valor da mensalidade calculada é %', ROUND(monthlyValue, 2);
            billings := billings || Array[
              format(
                '{"contractID":%s,"installationID":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                installation.contractid,
                installation.id,
                format(
                  'Mensalidade de %s à %s',
                  TO_CHAR(startDateOfBillingPeriod, 'DD/MM/YYYY'),
                  TO_CHAR(endDateOfPeriod, 'DD/MM/YYYY')
                ),
                monthlyValue,
                startDateOfBillingPeriod,
                endDateOfPeriod
              )::jsonb
            ];

            -- Agora analisamos quaisquer subsídios ou bonificações
            -- existentes de forma a concedermos os respectivos
            -- descontos, se pertinente
            discountTotal := 0;
            FOR subsidyRecord IN
              SELECT performedSubsidies.subsidyID AS ID,
                     performedSubsidies.bonus,
                     performedSubsidies.performedPeriod,
                     performedSubsidies.subsidyPeriod * performedSubsidies.performedPeriod AS subsidedPeriod,
                     performedSubsidies.discountType,
                     performedSubsidies.discountValue
                FROM (
                  SELECT S.subsidyID,
                         S.bonus,
                         public.intervalOfPeriod(startDateOfBillingPeriod, endDateOfPeriod) AS performedPeriod,
                         ('[' || S.periodStartedAt || ',' || COALESCE(S.periodEndedAt || ']', ')'))::public.intervalOfPeriod AS subsidyPeriod,
                         S.discountType,
                         S.discountValue
                    FROM erp.subsidies AS S
                   WHERE S.installationID = installation.id
                     AND (
                       (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                       (S.periodEndedAt >= startDateOfBillingPeriod)
                      )
                   ORDER BY S.bonus DESC, S.periodStartedAt
                  ) AS performedSubsidies
            LOOP
              -- Calculamos os valores deste desconto
              startOfSubsidy := lower(subsidyRecord.subsidedPeriod);
              endOfSubsidy   := upper(subsidyRecord.subsidedPeriod);
              IF (startOfSubsidy IS NOT NULL) THEN
                daysInSubsidy  := DATE_PART('day',
                    endOfSubsidy::timestamp - startOfSubsidy::timestamp
                  ) + 1;
                RAISE NOTICE 'Período com % dias', daysInSubsidy;
                IF subsidyRecord.bonus THEN
                  -- Aplicamos 100% de desconto no período
                  IF (daysInSubsidy = daysInPeriod) THEN
                    -- O desconto foi concedido pelo mês inteiro
                    discountValue := monthPrice;
                  ELSE
                    -- O desconto foi concedido por uma parte do mês
                    discountValue := ROUND(daysInSubsidy * dayPrice, 2);
                  END IF;
                ELSE
                  -- Precisamos calcular o desconto em função do período
                  IF (subsidyRecord.discountType = 1) THEN
                    -- O desconto é um valor fixo em reais por dia
                    discountValue :=
                      ROUND(daysInSubsidy * subsidyRecord.discountValue, 2)
                    ;
                  ELSE
                    -- O desconto é uma porcentagem do valor do período
                    discountValue :=
                      ROUND(
                        (
                          (daysInSubsidy * dayPrice) *
                          subsidyRecord.discountValue / 100
                        ),
                        2
                      )
                    ;
                  END IF;
                END IF;

                RAISE NOTICE 'Adicionando desconto no item de contrato';
                billings := billings || Array[
                  format(
                    '{"contractID":%s,"installationID":%s,"monthPrice":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                    installation.contractid,
                    installation.id,
                    installation.monthprice,
                    format(
                      'Desconto de %s à %s',
                      TO_CHAR(startOfSubsidy, 'DD/MM/YYYY'),
                      TO_CHAR(endOfSubsidy, 'DD/MM/YYYY')
                    ),
                    ROUND((0 - discountValue), 2),
                    startDateOfBillingPeriod,
                    endDateOfPeriod
                  )::jsonb
                ];

                discountTotal := discountTotal + discountValue;
              END IF;
            END LOOP;

            -- Calculamos o valor final
            finalValue := monthlyValue - discountTotal;
            IF (finalValue < 0.00) THEN
              finalValue := 0.00;
            END IF;

            billeds := billeds || Array[
              format(
                '{"contractID":%s,"installationID":%s,"monthPrice":%s,"monthlyValue":%s,"discountValue":%s,"finalValue":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                installation.contractid,
                installation.id,
                installation.monthprice,
                monthlyValue,
                discountTotal,
                finalValue,
                startDateOfBillingPeriod,
                endDateOfPeriod
              )::jsonb
            ];

            -- Inicializamos o período cobrado
            billed := format(
              '{"contractID":%s,"installationID":%s,"monthPrice":%s,"monthlyValue":%s,"discountValue":0.00,"finalValue":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
              installation.contractid,
              installation.id,
              installation.monthprice,
              monthlyValue,
              monthlyValue,
              startDateOfBillingPeriod,
              endDateOfPeriod
            )::jsonb;

            -- Agora analisamos quaisquer outros valores presentes no
            -- contrato e que precisam ser computados
            FOR monthlyFeesRecord IN
              SELECT C.name,
                     C.chargeValue AS value
                FROM erp.contractCharges AS C
               INNER JOIN erp.billingTypes AS B USING (billingTypeID)
               WHERE C.contractID = installation.contractid
                 AND B.billingFormatID = 3
                 AND B.billingMomentID = 5
                 AND B.ratePerEquipment = true
            LOOP
              RAISE NOTICE 'Adicionando a cobrança de % do item de contrato para ser cobrada', monthlyFeesRecord.name;
              billings := billings || Array[
                format(
                  '{"contractID":%s,"installationID":%s,"monthPrice":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                  installation.contractid,
                  installation.id,
                  installation.monthprice,
                  monthlyFeesRecord.name,
                  monthlyFeesRecord.value,
                  startDateOfBillingPeriod,
                  endDateOfPeriod
                )::jsonb
              ];
            END LOOP;
          END IF;
        END IF;
      END LOOP;

      -- Adicionamos os valores a serem cobrados
      IF (array_length(billings, 1) > 0) THEN
        RAISE NOTICE 'Temos % itens a serem cobrados nesta parcela', array_length(billings, 1);
        RAISE NOTICE 'billings: %', billings;

        -- Adicionamos os valores a serem cobrados no boleto desta parcela
        billet := jsonb_set(
          billet, '{ billings }', to_jsonb(billings), true
        );
        billet := jsonb_set(
          billet, '{ billeds }', to_jsonb(billeds), true
        );
        RAISE NOTICE 'Adicionando o boleto ao carnê: %', billet;

        -- Adicionamos o boleto ao carnê
        billets := billets || billet;
      -- ELSE
      --   RAISE NOTICE 'Não temos itens a serem cobrados nesta parcela';
      END IF;
      
      -- Incrementamos a quantidade de parcelas que fizemos
      parcelNumber := parcelNumber + 1;

      -- Avançamos para o próximo mês
      referenceDate := referenceDate + interval '1 month';
      dueDate := dueDate + interval '1 month';

      -- Repetimos este processo até determinar parcela seja superior a
      -- quantidade de parcelas a serem emitidas
      EXIT WHEN parcelNumber > FnumberOfParcels;
    END LOOP;

    IF (array_length(billets, 1) > 0) THEN
      RAISE NOTICE 'Processando os boletos';

      -- Recuperamos a informação da cobrança a ser gerada utilizando como
      -- referência a primeira instalação informada
      SELECT INTO FpaymentMethodID, FdefinedMethodID, FfineValue, FarrearInterestType, FarrearInterest, FinstructionID, FinstructionDays
             C1.paymentMethodID,
             C1.definedMethodID,
             P.fineValue,
             P.arrearInterestType,
             P.arrearInterest,
             D1.parameters::jsonb->'instructionID' AS instructionID,
             D1.parameters::jsonb->'instructionDays' AS instructionDays
        FROM erp.installations AS I
       INNER JOIN erp.contracts AS C ON (I.contractID = C.contractID)
       INNER JOIN erp.plans AS P ON (I.planID = P.planID)
       INNER JOIN erp.paymentConditions AS C1 ON (C.paymentConditionID = C1.paymentConditionID)
       INNER JOIN erp.definedMethods AS D1 ON (C1.definedMethodID = D1.definedMethodID)
       WHERE I.installationID = Finstallations[1];

      -- Criamos o identificador de nosso carnê
      INSERT INTO erp.carnets (contractorID, createdAt, createdByUserID)
           VALUES (FcontractorID, CURRENT_TIMESTAMP, FuserID)
      RETURNING carnetID INTO newCarnetID;

      FOREACH billet IN ARRAY billets
      LOOP
        -- Fazemos a inserção dos boletos no banco de dados

        -- Precisamos criar uma nova fatura a cada mês
        RAISE NOTICE 'Parcela: %', billet->>'parcel';
        RAISE NOTICE 'referenceMonthYear: %', billet->>'referenceMonth';
        RAISE NOTICE 'dueDate: %', (billet->>'dueDate')::Date;
        INSERT INTO erp.invoices (contractorID, customerID, subsidiaryID,
                    invoiceDate, referenceMonthYear, dueDate, paymentMethodID,
                    definedMethodID, carnetID)
             VALUES (FcontractorID, FcustomerID, FsubsidiaryID,
                    CURRENT_DATE, (billet->>'referenceMonth')::text,
                    (billet->>'dueDate')::Date, FpaymentMethodID,
                    FdefinedMethodID, newCarnetID)
        RETURNING invoiceID INTO newInvoiceID;

        -- Adicionamos cada valor cobrado
        FOR billing IN
          SELECT * FROM jsonb_array_elements(billet->'billings')
        LOOP
          -- Inserimos os valores a serem cobrados nesta parcela
          -- Lançamos esta mensalidade nos registros de valores cobrados
          -- para esta instalação
          RAISE NOTICE 'Adicionando lançamento na fatura';
          RAISE NOTICE 'contractID: %', billing->>'contractID';
          RAISE NOTICE 'installationID: %', billing->>'installationID';
          RAISE NOTICE 'billingDate: %', (billing->>'endDateOfPeriod')::Date;
          RAISE NOTICE 'name: %', (billing->>'name')::text;
          RAISE NOTICE 'value: %', (billing->>'value')::numeric;
          INSERT INTO erp.billings (contractorID, contractID,
                 installationID, billingDate, name, value, invoiceID,
                 invoiced, addMonthlyAutomatic, isMonthlyPayment,
                 createdByUserID, updatedByUserID)
          VALUES (FcontractorID, (billing->>'contractID')::int,
                  (billing->>'installationID')::int,
                  (billing->>'endDateOfPeriod')::Date,
                  (billing->>'name')::text,
                  (billing->>'value')::numeric, newInvoiceID, TRUE,
                  TRUE, TRUE, FuserID, FuserID);
        END LOOP; -- Billing

        -- Adicionamos cada período cobrado
        FOR billed IN
          SELECT * FROM jsonb_array_elements(billet->'billeds')
        LOOP
          -- Lançamos o período cobrado
          RAISE NOTICE 'Adicionando período cobrado';
          RAISE NOTICE 'startDateOfPeriod: %', (billed->>'startDateOfPeriod')::Date;
          RAISE NOTICE 'endDateOfPeriod: %', (billed->>'endDateOfPeriod')::Date;
          RAISE NOTICE 'monthPrice: %', billed->>'monthPrice';
          RAISE NOTICE 'monthlyValue: %', billed->>'monthlyValue';
          RAISE NOTICE 'discountValue: %', billed->>'discountValue';
          RAISE NOTICE 'finalValue: %', billed->>'finalValue';
          INSERT INTO erp.billedPeriods (contractorID, installationID,
                 invoiceID, referenceMonthYear, startDate, endDate,
                 monthPrice, grossvalue, discountValue, finalValue)
          VALUES (FcontractorID, (billed->>'installationID')::int,
                 newInvoiceID, billet->>'referenceMonth',
                 (billed->>'startDateOfPeriod')::Date,
                 (billed->>'endDateOfPeriod')::Date,
                 (billed->>'monthPrice')::numeric,
                 (billed->>'monthlyValue')::numeric,
                 (billed->>'discountValue')::numeric,
                 (billed->>'finalValue')::numeric);
        END LOOP; -- Billing

        -- Por último, determinamos os valores totais desta fatura com
        -- base nos valores calculados
        UPDATE erp.invoices
           SET invoiceValue = ROUND(billings.total, 2)
          FROM (
            SELECT invoiceID,
                   SUM(value) as total
              FROM erp.billings
             WHERE invoiceID = newInvoiceID
            GROUP BY invoiceID
           ) AS billings
         WHERE invoices.invoiceID = billings.invoiceID
           AND invoices.contractorID = FcontractorID;
      END LOOP; -- Billet

      -- Lançamos os valores de cada fatura gerada para este carnê para
      -- cobrança
      FOR invoice IN
        SELECT I.contractorID,
               I.invoiceID,
               I.dueDate,
               I.invoiceValue,
               I.paymentMethodID,
               I.definedMethodID,
               A.bankID,
               A.agencyNumber,
               A.accountNumber,
               A.wallet
          FROM erp.invoices AS I
         INNER JOIN erp.definedMethods AS D USING (definedMethodID)
         INNER JOIN erp.accounts AS A USING (accountID)
         WHERE I.carnetID = newCarnetID
      LOOP
        -- Atualizamos o contador de boletos emitidos
        UPDATE erp.definedMethods
           SET billingCounter = billingCounter + 1 
         WHERE definedMethodID = 1
        RETURNING billingCounter INTO FbillingCounter;

        -- Determinamos o número de identificação do boleto no banco
        ourNumber := erp.buildBankIdentificationNumber(invoice.bankID, invoice.agencyNumber, invoice.accountNumber, invoice.wallet, FbillingCounter);

        -- Determinamos a situação do boleto
        IF (invoice.invoiceValue > 0) THEN
          paymentSituationID := 1;
          droppedTypeID := 1;
        ELSE
          paymentSituationID := 2;
          droppedTypeID := 4;
        END IF;

        -- Inserimos o boleto para cobrança
        INSERT INTO erp.bankingBilletPayments (contractorID, invoiceID,
               dueDate, valueToPay, paymentMethodID, paymentSituationID,
               definedMethodID, bankCode, agencyNumber, accountNumber,
               wallet, billingCounter, ourNumber, fineValue,
               arrearInterestType, arrearInterest, instructionID,
               instructionDays, droppedTypeID)
        VALUES (invoice.contractorID, invoice.invoiceID, invoice.dueDate,
               invoice.invoiceValue, invoice.paymentMethodID,
               paymentSituationID, invoice.definedMethodID, invoice.bankID,
               invoice.agencyNumber, invoice.accountNumber, invoice.wallet,
               FbillingCounter, ourNumber, FfineValue, FarrearInterestType,
               FarrearInterest, FinstructionID, FinstructionDays,
               droppedTypeID);
      END LOOP; -- Invoice
    ELSE
      -- RAISE NOTICE 'Não temos parcelas a serem cobradas';
      RETURN null;
    END IF;

    -- Indica que tudo deu certo, retornando o número do carnê
    RETURN newCarnetID;
  END IF;

  RETURN null;
END;
$$ LANGUAGE 'plpgsql';

-- Exemplo:
-- SELECT erp.createCarnet(1,42, 45, '2022-03-01'::Date, 3, '2022-04-15'::Date, 2, '{35}') AS carnetID;

-- ---------------------------------------------------------------------
-- Gera uma cobrança antecipada
-- ---------------------------------------------------------------------
-- Stored Procedure que determina os valores a serem cobrados em cada
-- parcela de uma cobrança antecipada, gerando uma cobrança única que
-- contém todas as parcelas e fazendo os devidos registros de períodos
-- cobrados.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.createPrepayment(FcontractorID integer,
  FcustomerID int, FsubsidiaryID int, FstartDate date, FnumberOfParcels int,
  FdueDate date, FvalueToPay numeric(12,2),
  Finstallations integer array, FpaymentConditionID integer,
  FpaymentMethodID integer, FdefinedMethodID integer, FuserID integer)
RETURNS integer AS
$$
DECLARE
  -- Os parâmetros para cálculo de cada mensalidade e do valor total a
  -- ser cobrado
  parcelNumber  int;
  referenceDate  date;
  startDateOfPeriod  date;
  endDateOfPeriod  date;
  startDateOfBillingPeriod  date;
  monthlyValue  numeric;
  discountTotal  numeric;
  finalValue  numeric;
  daysToConsider  smallint;

  -- O cálculo do valor de mensalidade por dia
  daysInPeriod  smallint;
  dayPrice  numeric;

  -- A análise de subsídios aplicados
  subsidyRecord  record;
  startOfSubsidy  date;
  endOfSubsidy  date;
  daysInSubsidy  smallint;
  discountValue  numeric;

  -- O ID do último contrato
  lastContractID  integer;

  -- Os dados da instalação e de valores a serem cobrados
  installation  record;

  -- A análise de outras mensalidades presentes em contrato
  monthlyFeesRecord  record;

  -- O ID da fatura
  newInvoiceID  integer;

  -- Os dados das parcelas a serem cobradas
  parcels  jsonb[];
  parcel  jsonb;
  billing  jsonb;
  billings  jsonb[];
  billed  jsonb;
  billeds  jsonb[];

  -- Os dados da fatura
  invoice  record;
  FdefinedMethod  varchar;

  -- Parâmetros da cobrança a ser gerada
  paymentSituationID  integer;
  droppedTypeID  integer;
BEGIN
  -- Inicializamos as variáveis de processamento
  parcelNumber := 1;
  referenceDate := FstartDate;
  lastContractID := 0;
  parcels := jsonb '{ }';

  IF (FnumberOfParcels > 0) THEN
    LOOP
      -- Estamos processando cada período da cobrança antecipada, então
      -- precisamos analisar os períodos sendo cobrados e construir o
      -- valor a ser cobrado baseado nos valores computados a cada mês
      -- em cada item de contrato informado, montando as respectivas
      -- parcelas
      RAISE NOTICE 'Número da parcela: %', parcelNumber;
      RAISE NOTICE 'Data de referência: %', TO_CHAR(referenceDate, 'DD/MM/YYYY');

      -- Inicializamos o registro de valores desta parcela
      parcel := format(
        '{"parcel":%s,"referenceMonth":"%s","billings":[],"billeds":[]}',
        parcelNumber,
        to_char(referenceDate, 'MM/YYYY')
      )::jsonb;
      billings := jsonb '{ }';
      billeds := jsonb '{ }';

      -- Recupera as informações das instalações para as quais estamos
      -- emitindo a cobrança
      FOR installation IN
        SELECT I.installationID AS id,
               I.installationNumber AS number,
               C.signatureDate,
               C.startTermAfterInstallation,
               P.prorata,
               I.startDate,
               I.monthprice,
               I.contractID,
               I.lastDayOfBillingPeriod
          FROM erp.installations AS I
         INNER JOIN erp.contracts AS C ON (I.contractID = C.contractID)
         INNER JOIN erp.plans AS P ON (P.planID = I.planID)
         WHERE I.contractorID = FcontractorID
           AND C.deleted = false
           AND C.active = true
           AND I.endDate IS NULL
           AND I.installationID = ANY(Finstallations)
         ORDER BY C.customerPayerid, C.subsidiaryPayerid, C.unifybilling, C.contractID, I.installationid
      LOOP
        -- Para cada parcela sendo calculada, analisamos se devemos ou
        -- não cobrar o período para cada um dos itens de contrato
        -- informados, de forma que conseguimos construir o valor final
        -- desta parcela corretamente
        RAISE NOTICE 'Número do item de contrato: %', installation.number;
        RAISE NOTICE 'Data da assinatura do contrato: %', TO_CHAR(installation.signatureDate, 'DD/MM/YYYY');
        RAISE NOTICE 'Data do início do item de contrato: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');
        RAISE NOTICE 'Iniciar cobrança após instalação: %', CASE WHEN installation.startTermAfterInstallation THEN 'Sim' ELSE 'Não' END;
        RAISE NOTICE 'Cobrar proporcional ao serviço prestado: %', CASE WHEN installation.prorata THEN 'Sim' ELSE 'Não' END;

        -- Determinamos o período de cobrança em um mês
        startDateOfPeriod := referenceDate;
        endDateOfPeriod := startDateOfPeriod + interval '1 month' - interval '1 day';
        RAISE NOTICE 'Período de % à %', startDateOfPeriod, endDateOfPeriod;

        -- Determinamos à partir de qual data devemos cobrar
        IF (installation.prorata) THEN
          -- Devemos cobrar proporcionalmente, então determinamos quando
          -- isto ocorre
          IF (installation.startTermAfterInstallation) THEN
            IF (installation.startDate IS NULL) THEN
              -- Como a instalação não ocorreu ainda, então consideramos
              -- o início do período mesmo
              RAISE NOTICE 'Instalação não ocorreu, considerando o período inteiro';
              startDateOfBillingPeriod := startDateOfPeriod;
            ELSE
              -- Verificamos se o início do item de contrato ocorreu
              -- durante o período que estamos analisado
              IF (installation.startDate >= startDateOfPeriod) THEN
                -- Consideramos a data de instalação
                RAISE NOTICE 'Consideramos a data de instalação';
                startDateOfBillingPeriod := installation.startDate;
              ELSE
                -- Como a instalação se deu antes do início do período que
                -- iremos cobrar, então consideramos o início do período
                -- mesmo
                RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          ELSE
            IF (installation.signatureDate IS NULL) THEN
              -- Como o contrato não foi assinado ainda, então consideramos
              -- o início do período mesmo
              RAISE NOTICE 'Contrato não foi assinado, considerando o período inteiro';
              startDateOfBillingPeriod := baseDate;
            ELSE
              -- Verificamos se a assintatura ocorreu durante o período
              -- sendo analisado
              IF (installation.signatureDate >= startDateOfPeriod) THEN
                -- Consideramos a data de assinatura
                RAISE NOTICE 'Consideramos a data de assinatura do contrato';
                startDateOfBillingPeriod := installation.signatureDate;
              ELSE
                -- Como a assinatura do contrato se deu antes do início do
                -- período que iremos cobrar, então consideramos o início
                -- do período mesmo
                RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          END IF;
        ELSE
          -- Devemos cobrar integralmente, então o início se dá sempre no
          -- início do período apurado
          RAISE NOTICE 'Consideramos o período inteiro';
          startDateOfBillingPeriod := startDateOfPeriod;
        END IF;

        -- Verificamos se já foram realizadas cobranças de períodos
        -- neste item de contrato
        IF (installation.lastDayOfBillingPeriod IS NOT NULL) THEN
          -- Precisamos levar em consideração também o último período já
          -- cobrado se ele for superior ao período que estamos cobrando
          IF ((installation.lastDayOfBillingPeriod + interval '1 day') > startDateOfBillingPeriod) THEN
            startDateOfBillingPeriod := installation.lastDayOfBillingPeriod + interval '1 day';
            RAISE NOTICE 'Consideramos o período iniciando em %', startDateOfBillingPeriod;
          END IF;
        END IF;

        -- Calculamos a quantidade de dias no período
        daysInPeriod := DATE_PART('day',
            endDateOfPeriod::timestamp - startDateOfPeriod::timestamp
          ) + 1;
        RAISE NOTICE 'Este período possui % dias', daysInPeriod;

        -- Calculamos o valor diário com base na mensalidade
        dayPrice = installation.monthPrice / daysInPeriod;
        RAISE NOTICE 'O valor diário é %', ROUND(dayPrice, 2);

        -- Verificamos se precisamos cobrar algum período nesta parcela
        -- para esta instalação
        IF (startDateOfBillingPeriod <= endDateOfPeriod) THEN
          IF (installation.prorata) THEN
            IF (startDateOfBillingPeriod = startDateOfPeriod) THEN
              -- Cobramos o valor integral da mensalidade
              monthlyValue := installation.monthPrice;
              RAISE NOTICE 'Cobrando valor integral da mensalidade';
            ELSE
              -- Cobramos o valor proporcional

              -- Calculamos a quantidade de dias a serem cobrados
              daysToConsider := DATE_PART('day',
                  endDateOfPeriod::timestamp - startDateOfBillingPeriod::timestamp
                ) + 1;
              
              -- O serviço será prestado por uma parte do mês
              monthlyValue := ROUND(daysToConsider * dayPrice, 2);
            END IF;
          ELSE
            -- Cobramos sempre o valor integral da mensalidade
            monthlyValue := installation.monthPrice;
            RAISE NOTICE 'Cobrando valor integral da mensalidade';
          END IF;

          IF (monthlyValue > 0.00) THEN
            -- Acrescentamos esta valor a ser cobrado nesta mensalidade
            RAISE NOTICE 'O valor da mensalidade calculada é %', ROUND(monthlyValue, 2);
            billings := billings || Array[
              format(
                '{"contractID":%s,"installationID":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                installation.contractid,
                installation.id,
                format(
                  'Mensalidade de %s à %s',
                  TO_CHAR(startDateOfBillingPeriod, 'DD/MM/YYYY'),
                  TO_CHAR(endDateOfPeriod, 'DD/MM/YYYY')
                ),
                monthlyValue,
                startDateOfBillingPeriod,
                endDateOfPeriod
              )::jsonb
            ];

            -- Agora analisamos quaisquer subsídios ou bonificações
            -- existentes de forma a concedermos os respectivos
            -- descontos, se pertinente
            discountTotal := 0;
            FOR subsidyRecord IN
              SELECT performedSubsidies.subsidyID AS ID,
                     performedSubsidies.bonus,
                     performedSubsidies.performedPeriod,
                     performedSubsidies.subsidyPeriod * performedSubsidies.performedPeriod AS subsidedPeriod,
                     performedSubsidies.discountType,
                     performedSubsidies.discountValue
                FROM (
                  SELECT S.subsidyID,
                         S.bonus,
                         public.intervalOfPeriod(startDateOfBillingPeriod, endDateOfPeriod) AS performedPeriod,
                         ('[' || S.periodStartedAt || ',' || COALESCE(S.periodEndedAt || ']', ')'))::public.intervalOfPeriod AS subsidyPeriod,
                         S.discountType,
                         S.discountValue
                    FROM erp.subsidies AS S
                   WHERE S.installationID = installation.id
                     AND (
                       (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                       (S.periodEndedAt >= startDateOfBillingPeriod)
                      )
                   ORDER BY S.bonus DESC, S.periodStartedAt
                  ) AS performedSubsidies
            LOOP
              -- Calculamos os valores deste desconto
              startOfSubsidy := lower(subsidyRecord.subsidedPeriod);
              endOfSubsidy   := upper(subsidyRecord.subsidedPeriod);
              IF (startOfSubsidy IS NOT NULL) THEN
                daysInSubsidy  := DATE_PART('day',
                    endOfSubsidy::timestamp - startOfSubsidy::timestamp
                  ) + 1;
                RAISE NOTICE 'Período com % dias', daysInSubsidy;
                IF subsidyRecord.bonus THEN
                  -- Aplicamos 100% de desconto no período
                  IF (daysInSubsidy = daysInPeriod) THEN
                    -- O desconto foi concedido pelo mês inteiro
                    discountValue := monthPrice;
                  ELSE
                    -- O desconto foi concedido por uma parte do mês
                    discountValue := ROUND(daysInSubsidy * dayPrice, 2);
                  END IF;
                ELSE
                  -- Precisamos calcular o desconto em função do período
                  IF (subsidyRecord.discountType = 1) THEN
                    -- O desconto é um valor fixo em reais por dia
                    discountValue :=
                      ROUND(daysInSubsidy * subsidyRecord.discountValue, 2)
                    ;
                  ELSE
                    -- O desconto é uma porcentagem do valor do período
                    discountValue :=
                      ROUND(
                        (
                          (daysInSubsidy * dayPrice) *
                          subsidyRecord.discountValue / 100
                        ),
                        2
                      )
                    ;
                  END IF;
                END IF;

                RAISE NOTICE 'Adicionando desconto no item de contrato';
                billings := billings || Array[
                  format(
                    '{"contractID":%s,"installationID":%s,"monthPrice":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                    installation.contractid,
                    installation.id,
                    installation.monthprice,
                    format(
                      'Desconto de %s à %s',
                      TO_CHAR(startOfSubsidy, 'DD/MM/YYYY'),
                      TO_CHAR(endOfSubsidy, 'DD/MM/YYYY')
                    ),
                    ROUND((0 - discountValue), 2),
                    startDateOfBillingPeriod,
                    endDateOfPeriod
                  )::jsonb
                ];

                discountTotal := discountTotal + discountValue;
              END IF;
            END LOOP;

            -- Calculamos o valor final
            finalValue := monthlyValue - discountTotal;
            IF (finalValue < 0.00) THEN
              finalValue := 0.00;
            END IF;

            billeds := billeds || Array[
              format(
                '{"contractID":%s,"installationID":%s,"monthPrice":%s,"monthlyValue":%s,"discountValue":%s,"finalValue":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                installation.contractid,
                installation.id,
                installation.monthprice,
                monthlyValue,
                discountTotal,
                finalValue,
                startDateOfBillingPeriod,
                endDateOfPeriod
              )::jsonb
            ];

            -- Inicializamos o período cobrado
            billed := format(
              '{"contractID":%s,"installationID":%s,"monthPrice":%s,"monthlyValue":%s,"discountValue":0.00,"finalValue":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
              installation.contractid,
              installation.id,
              installation.monthprice,
              monthlyValue,
              monthlyValue,
              startDateOfBillingPeriod,
              endDateOfPeriod
            )::jsonb;

            -- Agora analisamos quaisquer outros valores presentes no
            -- contrato e que precisam ser computados
            FOR monthlyFeesRecord IN
              SELECT C.name,
                     C.chargeValue AS value
                FROM erp.contractCharges AS C
               INNER JOIN erp.billingTypes AS B USING (billingTypeID)
               WHERE C.contractID = installation.contractid
                 AND B.billingFormatID = 3
                 AND B.billingMomentID = 5
                 AND B.ratePerEquipment = true
            LOOP
              RAISE NOTICE 'Adicionando a cobrança de % do item de contrato para ser cobrada', monthlyFeesRecord.name;
              billings := billings || Array[
                format(
                  '{"contractID":%s,"installationID":%s,"monthPrice":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                  installation.contractid,
                  installation.id,
                  installation.monthprice,
                  monthlyFeesRecord.name,
                  monthlyFeesRecord.value,
                  startDateOfBillingPeriod,
                  endDateOfPeriod
                )::jsonb
              ];
            END LOOP;
          END IF;
        END IF;
      END LOOP;

      -- Adicionamos os valores a serem cobrados
      IF (array_length(billings, 1) > 0) THEN
        RAISE NOTICE 'Temos % itens a serem cobrados nesta parcela', array_length(billings, 1);
        RAISE NOTICE 'billings: %', billings;

        -- Adicionamos os valores a serem cobrados nesta parcela
        parcel := jsonb_set(
          parcel, '{ billings }', to_jsonb(billings), true
        );
        parcel := jsonb_set(
          parcel, '{ billeds }', to_jsonb(billeds), true
        );
        RAISE NOTICE 'Adicionando a parcela: %', parcel;

        -- Adicionamos a parcela à cobrança
        parcels := parcels || parcel;
      -- ELSE
      --   RAISE NOTICE 'Não temos itens a serem cobrados nesta parcela';
      END IF;
      
      -- Incrementamos a quantidade de parcelas que fizemos
      parcelNumber := parcelNumber + 1;

      -- Avançamos para o próximo mês
      referenceDate := referenceDate + interval '1 month';

      -- Repetimos este processo até determinar parcela seja superior a
      -- quantidade de parcelas a serem emitidas
      EXIT WHEN parcelNumber > FnumberOfParcels;
    END LOOP;

    IF (array_length(parcels, 1) > 0) THEN
      RAISE NOTICE 'Processando as parcelas';

      -- Precisamos criar uma nova fatura única que irá englobar todas
      -- as parcelas sendo cobradas
      RAISE NOTICE 'dueDate: %', FdueDate;
      RAISE NOTICE 'valueToPay: %', FvalueToPay;

      IF (FdefinedMethodID = 0) THEN
        FdefinedMethodID := NULL;
      END IF;
      INSERT INTO erp.invoices (contractorID, customerID, subsidiaryID,
                  invoiceDate, dueDate, paymentMethodID,
                  definedMethodID)
           VALUES (FcontractorID, FcustomerID, FsubsidiaryID,
                  CURRENT_DATE, FdueDate, FpaymentMethodID,
                  FdefinedMethodID)
      RETURNING invoiceID INTO newInvoiceID;

      FOREACH parcel IN ARRAY parcels
      LOOP
        -- Fazemos a inserção das parcelas no banco de dados
        RAISE NOTICE 'Parcela: %', parcel->>'parcel';
        RAISE NOTICE 'referenceMonthYear: %', parcel->>'referenceMonth';

        -- Adicionamos cada valor cobrado
        FOR billing IN
          SELECT * FROM jsonb_array_elements(parcel->'billings')
        LOOP
          -- Inserimos os valores a serem cobrados nesta parcela
          -- Lançamos esta mensalidade nos registros de valores cobrados
          -- para esta instalação
          RAISE NOTICE 'Adicionando lançamento na fatura';
          RAISE NOTICE 'contractID: %', billing->>'contractID';
          RAISE NOTICE 'installationID: %', billing->>'installationID';
          RAISE NOTICE 'billingDate: %', (billing->>'endDateOfPeriod')::Date;
          RAISE NOTICE 'name: %', (billing->>'name')::text;
          RAISE NOTICE 'value: %', (billing->>'value')::numeric;
          INSERT INTO erp.billings (contractorID, contractID,
                 installationID, billingDate, name, value, invoiceID,
                 invoiced, addMonthlyAutomatic, isMonthlyPayment,
                 createdByUserID, updatedByUserID)
          VALUES (FcontractorID, (billing->>'contractID')::int,
                  (billing->>'installationID')::int,
                  (billing->>'endDateOfPeriod')::Date,
                  (billing->>'name')::text,
                  (billing->>'value')::numeric, newInvoiceID, TRUE,
                  TRUE, TRUE, FuserID, FuserID);
        END LOOP; -- Billing

        -- Adicionamos cada período cobrado
        FOR billed IN
          SELECT * FROM jsonb_array_elements(parcel->'billeds')
        LOOP
          -- Lançamos o período cobrado
          RAISE NOTICE 'Adicionando período cobrado';
          RAISE NOTICE 'startDateOfPeriod: %', (billed->>'startDateOfPeriod')::Date;
          RAISE NOTICE 'endDateOfPeriod: %', (billed->>'endDateOfPeriod')::Date;
          RAISE NOTICE 'monthPrice: %', billed->>'monthPrice';
          RAISE NOTICE 'monthlyValue: %', billed->>'monthlyValue';
          RAISE NOTICE 'discountValue: %', billed->>'discountValue';
          RAISE NOTICE 'finalValue: %', billed->>'finalValue';
          INSERT INTO erp.billedPeriods (contractorID, installationID,
                 invoiceID, referenceMonthYear, startDate, endDate,
                 monthPrice, grossvalue, discountValue, finalValue)
          VALUES (FcontractorID, (billed->>'installationID')::int,
                 newInvoiceID, parcel->>'referenceMonth',
                 (billed->>'startDateOfPeriod')::Date,
                 (billed->>'endDateOfPeriod')::Date,
                 (billed->>'monthPrice')::numeric,
                 (billed->>'monthlyValue')::numeric,
                 (billed->>'discountValue')::numeric,
                 (billed->>'finalValue')::numeric);
        END LOOP; -- Billing

        -- Por último, determinamos os valores totais desta fatura com
        -- base nos valores calculados
        UPDATE erp.invoices
           SET invoiceValue = ROUND(billings.total, 2)
          FROM (
            SELECT invoiceID,
                   SUM(value) as total
              FROM erp.billings
             WHERE invoiceID = newInvoiceID
            GROUP BY invoiceID
           ) AS billings
         WHERE invoices.invoiceID = billings.invoiceID
           AND invoices.contractorID = FcontractorID;
      END LOOP; -- Parcels

      -- Lançamos os valores da fatura gerada para cobrança
      FOR invoice IN
        SELECT I.contractorID,
               I.invoiceID,
               I.dueDate,
               I.invoiceValue,
               I.paymentMethodID
          FROM erp.invoices AS I
         WHERE I.invoiceID = newInvoiceID
      LOOP
        -- Inserimos a fatura para cobrança
        INSERT INTO erp.payments (contractorID, invoiceID, dueDate,
               valueToPay, paymentMethodID, paymentSituationID)
        VALUES (invoice.contractorID, invoice.invoiceID, invoice.dueDate,
               FvalueToPay, invoice.paymentMethodID, 1);
      END LOOP; -- Invoice
    ELSE
      RAISE NOTICE 'Não temos valores a serem cobrados';
      RETURN null;
    END IF;

    -- Indica que tudo deu certo, retornando o número da cobrança
    RETURN newInvoiceID;
  ELSE
    RETURN null;
  END IF;
END;
$$ LANGUAGE 'plpgsql';

-- Exemplo de uso
-- SELECT erp.createPrepayment(1, 42, 45, '2022-04-01'::Date, 3, '2022-03-21'::Date, 285.00, '{31}', 1, 1, 0, 2) AS prepaymentID;

-- ---------------------------------------------------------------------
-- Gera as parcelas de um carnê de acordo
-- ---------------------------------------------------------------------
-- Stored Procedure que insere os valores a serem cobrados em cada
-- parcela de um carnê para um acordo. O valor das parcelas é informado
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.createArrangementCarnet(FcontractorID integer,
  FcustomerID int, FsubsidiaryID int, FcontractID int, FstartDate date,
  Fvalue numeric(12,2), FnumberOfParcels int, FuserID integer)
RETURNS integer AS
$$
DECLARE
  -- As informações do meio de pagamento
  FpaymentMethodID  integer;
  FdefinedMethodID  integer;

  -- O ID do carnê gerado
  newCarnetID  int;

  -- O ID da fatura gerada
  newInvoiceID  integer;

  -- O contador de mêses para as faturas emitidas
  interaction  int;

  -- A data do vencimento
  dueDate  date;

  -- O valor do carnê
  carnetValue  numeric;

  -- Os dados da fatura
  invoice  record;

  -- O número sequencial do boleto
  FbillingCounter  integer;

  -- Nosso número
  ourNumber  varchar(12);

  -- Os padrâmetros de multa, juros de mora e instrução do boleto
  FfineValue  numeric(8,4);
  FarrearInterestType  integer;
  FarrearInterest  numeric(8,4);
  FinstructionID  integer;
  FinstructionDays  integer;
BEGIN
  -- Recuperamos a informação da cobrança a ser gerada
  SELECT INTO FpaymentMethodID, FdefinedMethodID, FfineValue, FarrearInterestType, FarrearInterest, FinstructionID, FinstructionDays
         C1.paymentMethodID,
         C1.definedMethodID,
         P.fineValue,
         P.arrearInterestType,
         P.arrearInterest,
         D1.parameters::jsonb->'instructionID' AS instructionID,
         D1.parameters::jsonb->'instructionDays' AS instructionDays
    FROM erp.contracts AS C
   INNER JOIN erp.plans AS P ON (C.planID = P.planID)
   INNER JOIN erp.paymentConditions AS C1 ON (C.paymentConditionID = C1.paymentConditionID)
   INNER JOIN erp.definedMethods AS D1 ON (C1.definedMethodID = D1.definedMethodID)
   WHERE C.contractID = FcontractID;

  -- Criamos o identificador de nosso carnê
  INSERT INTO erp.carnets (contractorID, createdAt, createdByUserID)
  VALUES (FcontractorID, CURRENT_TIMESTAMP, FuserID)
  RETURNING carnetID INTO newCarnetID;

  -- Inicializamos as variáveis de processamento
  interaction := 1;
  dueDate := FstartDate;

  LOOP
    IF (interaction > 1) THEN
      -- Avançamos mais um mês
      dueDate := (dueDate
        + interval '1 month')::DATE
      ;
    END IF;

    -- Precisamos criar uma nova fatura
    INSERT INTO erp.invoices (contractorID, customerID, subsidiaryID,
      invoiceDate, referenceMonthYear, dueDate, paymentMethodID,
      definedMethodID, carnetID, invoiceValue) VALUES (FcontractorID,
      FcustomerID, FsubsidiaryID, CURRENT_DATE,
      to_char(dueDate, 'MM/YYYY'), dueDate, FpaymentMethodID,
      FdefinedMethodID, newCarnetID, Fvalue)
    RETURNING invoiceID INTO newInvoiceID;

    -- Lançamos esta mensalidade nos registros de valores cobrados
    INSERT INTO erp.billings (contractorID, contractID, billingDate,
           name, value, invoiceID, invoiced, addMonthlyAutomatic,
           isMonthlyPayment, createdByUserID, updatedByUserID)
    VALUES (FcontractorID, FcontractID, dueDate, 'Acordo negociação (Parcela ' || interaction::text || ' de ' || FnumberOfParcels || ')',
            Fvalue, newInvoiceID, TRUE, FALSE, FALSE, FuserID, FuserID);
    RAISE NOTICE 'Inserida a cobrança da parcela % no valor de %',
      (interaction + 1),
      Fvalue;

    -- Incrementamos a quantidade de interações que fizemos
    interaction := interaction + 1;

    -- Repetimos este processo até determinar interação seja superior a
    -- quantidade de parcelas a serem emitidas
    EXIT WHEN interaction > FnumberOfParcels;
  END LOOP;

  -- Verifica se as faturas geraram algum valor a ser cobrado
  SELECT INTO carnetValue
         SUM(I.invoiceValue)
    FROM erp.invoices AS I
   WHERE I.carnetID = newCarnetID;
  IF (carnetValue = 0.00) THEN
    -- Disparamos uma exceção
    RAISE EXCEPTION 'Não foi possível obter mensalidades para a geração do carnê nº % com % parcelas e início em %',
      newCarnetID,
      FnumberOfParcels,
      FstartDate
      USING HINT = 'Por favor, verifique os períodos já cobrados';

    RETURN NULL;
  END IF;

  -- Lançamos os valores de cada fatura para cobrança
  FOR invoice IN
    SELECT I.contractorID,
           I.invoiceID,
           I.dueDate,
           I.invoiceValue,
           I.paymentMethodID,
           I.definedMethodID,
           A.bankID,
           A.agencyNumber,
           A.accountNumber,
           A.wallet
      FROM erp.invoices AS I
     INNER JOIN erp.definedMethods AS D USING (definedMethodID)
     INNER JOIN erp.accounts AS A USING (accountID)
     WHERE I.carnetID = newCarnetID
  LOOP
    -- Atualizamos o contador de boletos emitidos
    UPDATE erp.definedMethods
       SET billingCounter = billingCounter + 1 
     WHERE definedMethodID = 1
    RETURNING billingCounter INTO FbillingCounter;

    -- Determinamos o número de identificação do boleto no banco
    ourNumber := erp.buildBankIdentificationNumber(invoice.bankID, invoice.agencyNumber, invoice.accountNumber, invoice.wallet, FbillingCounter);

    -- Inserimos o boleto para cobrança
    INSERT INTO erp.bankingBilletPayments (contractorID, invoiceID,
           dueDate, valueToPay, paymentMethodID, paymentSituationID,
           definedMethodID, bankCode, agencyNumber, accountNumber,
           wallet, billingCounter, ourNumber, fineValue,
           arrearInterestType, arrearInterest, instructionID,
           instructionDays, droppedTypeID)
    VALUES (invoice.contractorID, invoice.invoiceID, invoice.dueDate,
           invoice.invoiceValue, invoice.paymentMethodID,
           1, invoice.definedMethodID, invoice.bankID,
           invoice.agencyNumber, invoice.accountNumber, invoice.wallet,
           FbillingCounter, ourNumber, FfineValue, FarrearInterestType,
           FarrearInterest, FinstructionID, FinstructionDays, 1);

    -- Indicamos que todas os lançamentos foram cobrados
    UPDATE erp.billings
       SET invoiced = TRUE
     WHERE invoiceID = invoice.invoiceID;
  END LOOP;

  -- Indica que tudo deu certo, retornando o número do carnê
  RETURN newCarnetID;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Conclui o fechamento dos valores, enviando para cobrança
-- ---------------------------------------------------------------------
-- Função que envia para cobrança os valores das faturas geradas no
-- processo de fechamento mensal.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.finishMonthlyCalculations(FcontractorID integer)
RETURNS boolean AS
$$
DECLARE
  -- Os dados da fatura
  invoice  record;

  -- O número sequencial do boleto
  FbillingCounter  integer;

  -- Os parâmetros de multa e juros de mora
  FfineValue  numeric(8,4);
  FarrearInterestType  integer;
  FarrearInterest  numeric(8,4);

  -- Nosso número
  ourNumber  varchar(12);

  -- A sitaução do valor cobrado
  paymentSituationID  integer;
  droppedTypeID  integer;
BEGIN
  -- Lançamos os valores de cada fatura para cobrança
  FOR invoice IN
    SELECT I.contractorID,
           I.invoiceID,
           I.customerID,
           I.subsidiaryID,
           I.dueDate,
           I.invoiceValue,
           I.paymentMethodID,
           I.definedMethodID,
           A.bankID,
           A.agencyNumber,
           A.accountNumber,
           A.wallet,
           (D.parameters::jsonb->'instructionID')::text::integer AS instructionID,
           (D.parameters::jsonb->'instructionDays')::text::integer AS instructionDays
      FROM erp.invoices AS I
     INNER JOIN erp.definedMethods AS D USING (definedMethodID)
     INNER JOIN erp.accounts AS A USING (accountID)
     WHERE I.contractorID = FcontractorID
       AND I.underAnalysis = TRUE
  LOOP
    -- Recuperamos os parâmetros de multa e juros de mora
    SELECT INTO FfineValue, FarrearInterestType, FarrearInterest
           P.fineValue,
           P.arrearInterestType,
           P.arrearInterest
      FROM erp.contracts AS C
     INNER JOIN erp.plans AS P ON (C.planID = P.planID)
     WHERE C.customerID = invoice.customerID
       AND C.subsidiaryID = invoice.subsidiaryID
     ORDER BY C.enddate NULLS FIRST
     FETCH FIRST ROW ONLY;
    IF NOT FOUND THEN
      -- Disparamos uma exceção
      RAISE EXCEPTION 'Não foi possível obter as taxas de juros e mora para o cliente ID %',
        invoice.customerID
      USING HINT = 'Por favor, verifique os contratos deste cliente';

      RETURN FALSE;
    END IF;

    -- Atualizamos o contador de boletos emitidos
    UPDATE erp.definedMethods
       SET billingCounter = billingCounter + 1 
     WHERE definedMethodID = invoice.definedMethodID
    RETURNING billingCounter INTO FbillingCounter;

    -- Determinamos o número de identificação do boleto no banco
    ourNumber := erp.buildBankIdentificationNumber(invoice.bankID, invoice.agencyNumber, invoice.accountNumber, invoice.wallet, FbillingCounter);

    -- Determinamos a situação do boleto
    IF (invoice.invoiceValue > 0) THEN
      paymentSituationID := 1;
      droppedTypeID := 1;
    ELSE
      paymentSituationID := 2;
      droppedTypeID := 4;
    END IF;

    -- Verificamos o tipo de pagamento
    IF (invoice.paymentMethodID = 5) THEN
      -- Inserimos o boleto para cobrança
      INSERT INTO erp.bankingBilletPayments (contractorID, invoiceID,
             dueDate, valueToPay, paymentMethodID, paymentSituationID,
             definedMethodID, bankCode, agencyNumber, accountNumber,
             wallet, billingCounter, ourNumber, fineValue,
             arrearInterestType, arrearInterest, instructionID,
             instructionDays, droppedTypeID)
      VALUES (invoice.contractorID, invoice.invoiceID, invoice.dueDate,
             invoice.invoiceValue, invoice.paymentMethodID,
             paymentSituationID, invoice.definedMethodID, invoice.bankID,
             invoice.agencyNumber, invoice.accountNumber, invoice.wallet,
             FbillingCounter, ourNumber, FfineValue, FarrearInterestType,
             FarrearInterest, invoice.instructionID,
             invoice.instructionDays, droppedTypeID);
    ELSE
      -- Inserimos apenas a cobrança
      INSERT INTO erp.payments (contractorID, invoiceID,
             dueDate, valueToPay, paymentMethodID, paymentSituationID)
      VALUES (invoice.contractorID, invoice.invoiceID, invoice.dueDate,
             invoice.invoiceValue, invoice.paymentMethodID,
             paymentSituationID);
    END IF;

    -- Indicamos que todas os lançamentos foram cobrados
    UPDATE erp.billings
       SET invoiced = TRUE
     WHERE invoiceID = invoice.invoiceID;
    
    -- Por último, indica que a fatura foi fechada e cobrada
    UPDATE erp.invoices
       SET underAnalysis = FALSE
     WHERE invoiceID = invoice.invoiceID;
  END LOOP;

  -- Indica que tudo deu certo, retornando TRUE
  RETURN TRUE;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Calcula os valores referentes ao serviço prestado em um item de
-- contrato até a data atual
-- ---------------------------------------------------------------------
-- Stored Procedure que determina os valores a serem cobrados em um item
-- de contrato em função dos registros de instalação, calculando os
-- perídos para os quais efetivamente ocorreu a prestação do serviço e o
-- respectivo valor computado, e também consideração bonificações e/ou
-- subsídios definidos, calculando então o valor final. Porém não
-- registra os valores apurados no sistema.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.performedServiceUntilToday(
  FinstallationID integer, FuserID integer)
RETURNS jsonb AS
$$
DECLARE
  -- O período que iremos processar
  startDateOfPeriod  date;
  endDateOfPeriod  date;

  -- A data base do período a ser processado
  baseDate  date;

  -- As informações do item de contrato sendo analizado
  installation  record;

  -- A data do final do último período já processado
  lastDayOfCalculatedPeriod  date;

  -- A data do início do período de cobrança efetiva (desconsiderado
  -- períodos para os quais já foram apurados os valores)
  effectiveDate  date;

  -- O valor cobrado mensalmente
  monthPrice  numeric(12,2);

  -- O cálculo do valor de mensalidade por dia
  daysInPeriod  smallint;
  dayPrice  numeric;

  -- A quantidade de serviços qualificados para cobrança
  amountOfQualifyServices  integer;

  -- O último período apurado para determinação de ocorrências de
  -- sobreposição de dias de serviço prestado em veículos diferentes
  lastPerformedPeriod  public.intervalOfPeriod;
  performedPeriod  public.intervalOfPeriod;

  -- O período do serviço executado
  startOfPerformedService  date;
  endOfPerformedService  date;
  daysInPerformedService  smallint;
  grossValue  numeric(12,2);
  billedBefore  boolean;

  -- Os registros de instalação apurados
  installationRecord  record;

  -- Os registros de subsídios a serem aplicados
  subsidyRecord  record;

  -- O período do subsídio aplicado
  startOfSubsidy  date;
  endOfSubsidy  date;
  daysInSubsidy  smallint;
  discountValue  numeric(12,2);

  -- O período apurado
  ascertainedPeriodFinal  jsonb;
  periodTotal  record;

  -- O nome da tabela temporária
  tempName  varchar(12);
BEGIN
  -- Determina a base do período a ser processado como sendo o início do
  -- mês corrente
  baseDate := date_trunc('month', CURRENT_DATE);

  -- Determina o final do período a ser calculado como sendo o dia atual
  endDateOfPeriod := CURRENT_DATE;

  -- Recuperamos as informações do item de contrato sendo analisado
  RAISE NOTICE 'Recuperando informações do item de contrato';
  SELECT INTO installation
         I.contractorID,
         I.installationNumber AS number,
         C.signatureDate,
         C.startTermAfterInstallation,
         C.monthPrice AS defaultMonthPrice,
         P.prorata,
         I.startDate,
         I.endDate,
         I.lastDayOfCalculatedPeriod,
         I.lastDayOfBillingPeriod
    FROM erp.installations AS I
   INNER JOIN erp.contracts AS C ON (C.contractID = I.contractID)
   INNER JOIN erp.plans AS P ON (C.planID = I.planID)
   WHERE I.installationID = FinstallationID;

  RAISE NOTICE 'Número da instalação: %', installation.number;
  RAISE NOTICE 'Data da assinatura do contrato: %', TO_CHAR(installation.signatureDate, 'DD/MM/YYYY');
  RAISE NOTICE 'Data da instalação: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');
  RAISE NOTICE 'Iniciar cobrança após instalação: %', CASE WHEN installation.startTermAfterInstallation THEN 'Sim' ELSE 'Não' END;
  RAISE NOTICE 'Cobrar proporcional ao serviço prestado: %', CASE WHEN installation.prorata THEN 'Sim' ELSE 'Não' END;
  lastDayOfCalculatedPeriod := installation.lastDayOfCalculatedPeriod;
  RAISE NOTICE 'Término do último período apurado: %', CASE WHEN lastDayOfCalculatedPeriod IS NULL THEN 'Não disponível' ELSE TO_CHAR(lastDayOfCalculatedPeriod, 'DD/MM/YYYY') END;

  -- Verifica se já temos algum processamento realizado
  IF (lastDayOfCalculatedPeriod IS NULL) THEN
    -- Ainda não foi apurado nenhum período para este item de contrato

    -- Verifica se a cobrança deve ser proporcional
    IF (installation.prorata) THEN
      -- Devemos cobrar proporcionalmente, então determinamos quando
      -- isto ocorre
      IF (installation.startTermAfterInstallation) THEN
        -- O início da vigência ocorre após a instalação do equipamento
        effectiveDate := installation.startDate;
      ELSE
        -- O início da vigência ocorre na data de assinatura do contrato
        effectiveDate := installation.signatureDate;
      END IF;
    ELSE
      -- Devemos cobrar integralmente, então o início se dá sempre no
      -- início do mês
      effectiveDate := baseDate;
    END IF;

    lastDayOfCalculatedPeriod := effectiveDate - interval '1 day';
    RAISE NOTICE 'Considerando início do período de cobrança como sendo %', TO_CHAR(effectiveDate, 'DD/MM/YYYY');
  END IF;

  IF (endDateOfPeriod > installation.endDate) THEN
    endDateOfPeriod := installation.endDate;
  END IF;

  -- Determina se o período já foi processado
  IF (lastDayOfCalculatedPeriod >= endDateOfPeriod) THEN
    -- Já processamos este período, então simplesmente ignora
    RAISE NOTICE 'Esta instalação já teve valores apurados até %. Ignorando.',
      to_char(lastDayOfCalculatedPeriod, 'DD/MM/YYYY');

    RETURN NULL;
  END IF;

  -- Determina o início do período a ser calculado
  IF (lastDayOfCalculatedPeriod < baseDate) THEN
    -- O último período calculado é inferior à data que se deseja
    -- calcular, então inicia no primeiro dia da data-base informada
    startDateOfPeriod := baseDate;
  ELSE
    -- O último período calculado é igual ou superior à data base que se
    -- deseja calcular, então utiliza o dia seguinte à esta data como
    -- sendo a data do período a ser calculado
    startDateOfPeriod := lastDayOfCalculatedPeriod + interval '1 day';
  END IF;

  RAISE NOTICE 'O período a ser processado é de % até %', to_char(startDateOfPeriod, 'DD/MM/YYYY'), to_char(endDateOfPeriod, 'DD/MM/YYYY');

  -- Selecionar a tarifa vigente no início deste período
  IF (installation.startDate > startDateOfPeriod) THEN
    -- Recuperamos à partir da data de instalação, já que esta ocorreu
    -- durante o período que estamos apurando
    startOfPerformedService := installation.startDate;
  ELSE
    startOfPerformedService := startDateOfPeriod;
  END IF;
  SELECT INTO monthPrice
         readjustment.monthPrice
    FROM erp.readjustmentsOnInstallations AS readjustment
   WHERE readjustment.installationID = FinstallationID
     AND readjustment.readjustedAt <= startOfPerformedService
   ORDER BY readjustment.readjustedAt DESC
   FETCH FIRST ROW ONLY;
  IF NOT FOUND THEN
    -- Caso o item de contrato tenha sido criada neste mês, a consulta
    -- acima não irá retornar um valor. Neste caso, considera o
    -- mês corrente
    SELECT INTO monthPrice
           readjustment.monthPrice
      FROM erp.readjustmentsOnInstallations AS readjustment
     WHERE readjustment.installationID = FinstallationID
       AND readjustment.readjustedAt < (startOfPerformedService + interval '1 month')
     ORDER BY readjustment.readjustedAt DESC
     FETCH FIRST ROW ONLY;
    IF NOT FOUND THEN
      -- Utilizamos o valor do contrato
      monthPrice := installation.defaultMonthPrice;
    END IF;
  END IF;
  RAISE NOTICE 'A mensalidade considerada é %', monthPrice;

  -- Determinamos a quantidade de dias e o valor diário
  daysInPeriod := DATE_PART('day',
      (baseDate + interval '1 month' - interval '1 day')::timestamp - baseDate::timestamp
    ) + 1;
  RAISE NOTICE 'Este período possui % dias', daysInPeriod;
  dayPrice = monthPrice / daysInPeriod;
  RAISE NOTICE 'O valor diário é %', ROUND(dayPrice, 2);

  -- Determinamos se temos serviços qualificados para serem cobrados
  SELECT count(*) INTO amountOfQualifyServices
    FROM erp.installationRecords AS R
   WHERE R.installationID = FinstallationID
     AND (
       (R.uninstalledAt IS NULL AND R.installedAt <= endDateOfPeriod) OR
       (R.uninstalledAt >= startDateOfPeriod AND R.installedAt <= endDateOfPeriod)
      );
  IF (amountOfQualifyServices = 0) THEN
    RAISE NOTICE 'Não temos um período qualificado entre % e % para o item de contrato %', startDateOfPeriod, endDateOfPeriod, FinstallationID;
    RETURN jsonb '{ }';
  END IF;

  -- Criamos uma tabela temporária que irá armazenar os registros de
  -- detalhamento do período acertado, de forma que no final computados
  -- todos eles para obter o valor final do período acertado
  SELECT INTO tempName
         array_to_string(
           array(
           SELECT substr('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', trunc(random() * 62)::integer + 1, 1)
             FROM generate_series(1, 12)), ''
         );
  EXECUTE format(
    'CREATE TEMPORARY TABLE %s (
            recordID        integer,
            periodStartedAt date
                            NOT NULL,
            periodEndedAt   date
                            NOT NULL,
            duration        smallint
                            NOT NULL,
            periodValue     numeric(12,2)
                            NOT NULL,
            subsidyID       integer
                            DEFAULT NULL,
            billedBefore    boolean
                            NOT NULL
                            DEFAULT FALSE
            );',
      tempName
  );

  -- Verifica se a cobrança deve ser proporcional
  lastPerformedPeriod := null;
  IF (installation.prorata) THEN
    -- Recupera os registros de equipamentos instalados, os quais, de
    -- alguma forma, pertencem ao período informado
    FOR installationRecord IN
      SELECT installationRecordID AS ID,
             vehicleID,
             equipmentID,
             ascertainedPeriod,
             performedService,
             ascertainedPeriod * performedService AS calculatedPeriod
        FROM (
          SELECT R.installationRecordID,
                 R.vehicleID,
                 R.equipmentID,
                 public.intervalOfPeriod(startDateOfPeriod, endDateOfPeriod) AS ascertainedPeriod,
                 ('[' || public.getStartDate(R.installedat, startDateOfPeriod) || ',' || COALESCE(public.getEndDate(R.uninstalledat, endDateOfPeriod) || ']', ')'))::public.intervalOfPeriod AS performedService
            FROM erp.installationRecords AS R
           WHERE R.installationID = FinstallationID
             AND (
               (R.uninstalledAt IS NULL AND R.installedAt <= endDateOfPeriod) OR
               (R.uninstalledAt >= startDateOfPeriod AND R.installedAt <= endDateOfPeriod)
              )
           ORDER BY R.installedAt
          ) AS performedServices
    LOOP
      -- Determinamos um período em que ocorreu a prestação do serviço.
      -- É com base neste valor que será realizada a cobrança da
      -- mensalidade deste item de contrato. Todavia, pode ocorrer mais
      -- de um período num mesmo mês de apuração se ocorrer mudança de
      -- equipamento de rastreamento e/ou de veículo. Para isto,
      -- adicionamos cada período apurado numa tabela para sabermos os
      -- detalhes.
      IF (lastPerformedPeriod IS NOT NULL) THEN
        -- Precisamos analisar sobreposição de períodos
        IF (lastPerformedPeriod && installationRecord.performedService) THEN
          -- Ocorreu sobreposição do período anterior com o novo período
          -- e precisamos desconsiderar do período anterior os dias que
          -- se sobrepõe ao deste novo período. Isto ocorre quando temos
          -- a troca do veículo em um item de contrato e o rastreador do
          -- novo veículo é instalado antes de ocorrer a retirada do
          -- rastreador do veículo antigo. Modificamos o período anterior
          -- primeiramente
          performedPeriod := lastPerformedPeriod
            - installationRecord.performedService
          ;


          IF (isempty(performedPeriod)) THEN
            -- O período foi anulado, então não cobramos
            EXECUTE format(
              'UPDATE %s
                  SET periodEndedAt = ''%s'',
                      duration = 0,
                      periodValue = 0.00
                WHERE periodStartedAt = ''%s''
                  AND periodEndedAt = ''%s'';',
              tempName,
              endOfPerformedService,
              lower(lastPerformedPeriod),
              upper(lastPerformedPeriod)
            );
          ELSE
            -- Calculamos os valores deste período modificado
            startOfPerformedService := lower(performedPeriod);
            endOfPerformedService   := upper(performedPeriod) - interval '1 day';
            daysInPerformedService  := DATE_PART('day',
                endOfPerformedService::timestamp - startOfPerformedService::timestamp
              ) + 1;
            IF (daysInPerformedService = daysInPeriod) THEN
              -- O serviço for prestado pelo mês inteiro
              grossValue := monthPrice;
            ELSE
              -- O serviço for prestado por uma parte do mês
              grossValue := ROUND(daysInPerformedService * dayPrice, 2);
            END IF;

            RAISE NOTICE 'Modificando o último período de serviço prestado para de % à % com % dias e custando %',
              to_char(startOfPerformedService, 'DD/MM/YYYY'),
              to_char(endOfPerformedService, 'DD/MM/YYYY'),
              daysInPerformedService,
              grossValue
            ;

            -- Modificamos no banco de dados
            EXECUTE format(
              'UPDATE %s
                  SET periodEndedAt = ''%s'',
                      duration = %s,
                      periodValue = %s
                WHERE periodStartedAt = ''%s''
                  AND periodEndedAt = ''%s'';',
              tempName,
              endOfPerformedService,
              daysInPerformedService,
              grossValue,
              lower(lastPerformedPeriod),
              upper(lastPerformedPeriod)
            );
          END IF;
        END IF;
      END IF;

      -- Calculamos os valores deste período
      startOfPerformedService := lower(installationRecord.performedService);
      endOfPerformedService   := upper(installationRecord.performedService);
      daysInPerformedService  := DATE_PART('day',
          endOfPerformedService::timestamp - startOfPerformedService::timestamp
        ) + 1;
      IF (daysInPerformedService = daysInPeriod) THEN
        -- O serviço for prestado pelo mês inteiro
        grossValue := monthPrice;
      ELSE
        -- O serviço for prestado por uma parte do mês
        grossValue := ROUND(daysInPerformedService * dayPrice, 2);
      END IF;

      RAISE NOTICE 'Prestado serviços de % à % com % dias e custando %',
        to_char(startOfPerformedService, 'DD/MM/YYYY'),
        to_char(endOfPerformedService, 'DD/MM/YYYY'),
        daysInPerformedService,
        grossValue
      ;

      -- Analisamos se este período pertence ao período já cobrados deste
      -- cliente
      billedBefore := false;
      IF (installation.lastDayOfBillingPeriod >= endDateOfPeriod) THEN
        -- O período inteiro não deve ser cobrado, pois já foi faturado
        -- faturado antecipadamente (provavelmente por se tratar de
        -- pagamento por carnê ou cartão de crédito parcelado e/ou por
        -- que o plano deste cliente é pré-pago). Desta forma indicamos
        -- que este trecho não deve ser cobrado (apesasr de computado)
        RAISE NOTICE 'O último dia cobrado (%) é maior do que o último dia do período (%)', installation.lastDayOfBillingPeriod, endDateOfPeriod;
        billedBefore := true;
      END IF;

      -- Inserimos este período apurado
      EXECUTE format(
        'INSERT INTO %s (recordID, periodStartedAt, periodEndedAt,
                duration, periodValue, billedBefore)
         VALUES (%s, ''%s'', ''%s'', %s, %s, %s);',
        tempName,
        installationRecord.ID,
        startOfPerformedService,
        endOfPerformedService,
        daysInPerformedService,
        grossValue,
        CASE WHEN billedBefore THEN 'true' ELSE 'false' END
      );

      -- Definimos este como sendo o último período processado
      lastPerformedPeriod := installationRecord.performedService;

      -- Agora determinamos quaisquer subsídios ou bonificações existentes
      -- neste período, de forma a concedermos os respectivos descontos.
      FOR subsidyRecord IN
        SELECT performedSubsidies.subsidyID AS ID,
               performedSubsidies.bonus,
               performedSubsidies.performedPeriod,
               performedSubsidies.subsidyPeriod * performedSubsidies.performedPeriod AS subsidedPeriod,
               performedSubsidies.discountType,
               performedSubsidies.discountValue
          FROM (
            SELECT S.subsidyID,
                   S.bonus,
                   public.intervalOfPeriod(startOfPerformedService, endOfPerformedService) AS performedPeriod,
                   ('[' || S.periodStartedAt || ',' || COALESCE(S.periodEndedAt || ']', ')'))::public.intervalOfPeriod AS subsidyPeriod,
                   S.discountType,
                   S.discountValue
              FROM erp.subsidies AS S
             WHERE S.installationID = FinstallationID
               AND (
                 (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                 (S.periodEndedAt >= startDateOfPeriod)
                )
             ORDER BY S.bonus DESC, S.periodStartedAt
            ) AS performedSubsidies
      LOOP
        -- Calculamos os valores deste desconto
        startOfSubsidy := lower(subsidyRecord.subsidedPeriod);
        endOfSubsidy   := upper(subsidyRecord.subsidedPeriod);
        daysInSubsidy  := DATE_PART('day',
            endOfSubsidy::timestamp - startOfSubsidy::timestamp
          ) + 1;
        IF subsidyRecord.bonus THEN
          -- Aplicamos 100% de desconto no período
          IF (daysInSubsidy = daysInPeriod) THEN
            -- O desconto foi concedido pelo mês inteiro
            discountValue := monthPrice;
          ELSE
            -- O desconto foi concedido por uma parte do mês
            discountValue := ROUND(daysInSubsidy * dayPrice, 2);
          END IF;
        ELSE
          -- Precisamos calcular o desconto em função do período
          IF (subsidyRecord.discountType = 1) THEN
            -- O desconto é um valor fixo em reais por dia
            discountValue :=
              ROUND(daysInSubsidy * subsidyRecord.discountValue, 2)
            ;
          ELSE
            -- O desconto é uma porcentagem do valor do período
            discountValue :=
              ROUND(
                (
                  (daysInSubsidy * dayPrice) *
                  subsidyRecord.discountValue / 100
                ),
                2
              )
            ;
          END IF;
        END IF;

        RAISE NOTICE 'Subsídio de % à % do tipo % e valor %',
          to_char(startOfSubsidy, 'DD/MM/YYYY'),
          to_char(endOfSubsidy, 'DD/MM/YYYY'),
          subsidyRecord.discountType,
          discountValue
        ;

        -- Inserimos os subsídios existente no mesmo período apurado
        EXECUTE format(
          'INSERT INTO %s (recordID, periodStartedAt, periodEndedAt,
                  duration, periodValue, subsidyID)
           VALUES (%s, ''%s'', ''%s'', %s, %s, %s);',
          tempName,
          installationRecord.ID,
          startOfPerformedService,
          endOfPerformedService,
          daysInPerformedService,
          discountValue,
          subsidyRecord.id
        );
      END LOOP;
    END LOOP;
  ELSE
    -- A cobrança sempre é integral, então não é preciso calcular os
    -- períodos de serviços prestados. Recupera os registros de
    -- equipamentos instalados, os quais, de alguma forma, pertencem ao
    -- período informado, considerando 1 mês desde o início do período e
    -- inputa o valor integral da mensalidade no último veículo em que
    -- tivemos um equipamento instalado
    FOR installationRecord IN
      SELECT DISTINCT ON (I.installationID)
               R.vehicleID,
               R.equipmentID
          FROM erp.installations AS I
         INNER JOIN erp.installationRecords AS R USING (installationID)
         WHERE I.installationID = FinstallationID
           AND (
                 (R.uninstalledAt IS NULL AND R.installedAt <= endDateOfPeriod) OR
                 (R.uninstalledAt >= startDateOfPeriod AND R.installedAt <= endDateOfPeriod)
               )
         ORDER BY I.installationID, R.uninstalledAt NULLS FIRST, R.installedAt DESC
    LOOP
      -- Devemos cobrar integralmente o mês, pois temos um rastreador
      -- instalado
      daysInPerformedService  := DATE_PART('day',
          endDateOfPeriod::timestamp - startDateOfPeriod::timestamp
        ) + 1;
      RAISE NOTICE 'Prestado serviços de % à % com % dias e custando %',
        to_char(startDateOfPeriod, 'DD/MM/YYYY'),
        to_char(endDateOfPeriod, 'DD/MM/YYYY'),
        daysInPerformedService,
        monthPrice
      ;

      -- Analisamos se este período pertence ao período já cobrados deste
      -- cliente
      billedBefore := false;
      IF (installation.lastDayOfBillingPeriod > endDateOfPeriod) THEN
        -- O período inteiro não deve ser cobrado, pois já foi faturado
        -- faturado antecipadamente (provavelmente por se tratar de
        -- pagamento por carnê ou cartão de crédito parcelado e/ou por
        -- que o plano deste cliente é pré-pago). Desta forma indicamos
        -- que este trecho não deve ser cobrado (apesasr de computado)
        billedBefore := true;
      END IF;

      -- Inserimos este período apurado
      EXECUTE format(
        'INSERT INTO %s (recordID, periodStartedAt, periodEndedAt,
                duration, periodValue, billedBefore)
         VALUES (%s, ''%s'', ''%s'', %s, %s, %s);',
        tempName,
        installationRecord.ID,
        startDateOfPeriod,
        endDateOfPeriod,
        daysInPerformedService,
        monthPrice,
        CASE WHEN billedBefore THEN 'true' ELSE 'false' END
      );

      -- Agora determinamos quaisquer subsídios ou bonificações existentes
      -- neste período, de forma a concedermos os respectivos descontos.
      FOR subsidyRecord IN
        SELECT performedSubsidies.subsidyID AS ID,
               performedSubsidies.bonus,
               performedSubsidies.performedPeriod,
               performedSubsidies.subsidyPeriod * performedSubsidies.performedPeriod AS subsidedPeriod,
               performedSubsidies.discountType,
               performedSubsidies.discountValue
          FROM (
            SELECT S.subsidyID,
                   S.bonus,
                   public.intervalOfPeriod(startDateOfPeriod, endDateOfPeriod) AS performedPeriod,
                   ('[' || S.periodStartedAt || ',' || COALESCE(S.periodEndedAt || ']', ')'))::public.intervalOfPeriod AS subsidyPeriod,
                   S.discountType,
                   S.discountValue
              FROM erp.subsidies AS S
             WHERE S.installationID = FinstallationID
               AND (
                 (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                 (S.periodEndedAt >= startDateOfPeriod)
                )
             ORDER BY S.bonus DESC, S.periodStartedAt
            ) AS performedSubsidies
      LOOP
        -- Calculamos os valores deste desconto
        startOfSubsidy := lower(subsidyRecord.subsidedPeriod);
        endOfSubsidy   := upper(subsidyRecord.subsidedPeriod);
        daysInSubsidy  := DATE_PART('day',
            endOfSubsidy::timestamp - startOfSubsidy::timestamp
          ) + 1;
        IF subsidyRecord.bonus THEN
          -- Aplicamos 100% de desconto no período
          IF (daysInSubsidy = daysInPeriod) THEN
            -- O desconto foi concedido pelo mês inteiro
            discountValue := monthPrice;
          ELSE
            -- O desconto foi concedido por uma parte do mês
            discountValue := ROUND(daysInSubsidy * dayPrice, 2);
          END IF;
        ELSE
          -- Precisamos calcular o desconto em função do período
          IF (subsidyRecord.discountType = 1) THEN
            -- O desconto é um valor fixo em reais por dia
            discountValue :=
              ROUND(daysInSubsidy * subsidyRecord.discountValue, 2)
            ;
          ELSE
            -- O desconto é uma porcentagem do valor do período
            discountValue :=
              ROUND(
                (
                  (daysInSubsidy * dayPrice) *
                  subsidyRecord.discountValue / 100
                ),
                2
              )
            ;
          END IF;
        END IF;

        RAISE NOTICE 'Subsídio de % à % do tipo % e valor %',
          to_char(startOfSubsidy, 'DD/MM/YYYY'),
          to_char(endOfSubsidy, 'DD/MM/YYYY'),
          subsidyRecord.discountType,
          discountValue
        ;

        -- Inserimos os subsídios existente no mesmo período apurado
        EXECUTE format(
          'INSERT INTO %s (recordID, periodStartedAt, periodEndedAt,
                  duration, periodValue, subsidyID)
           VALUES (%s, ''%s'', ''%s'', %s, %s, %s);',
          tempName,
          installationRecord.ID,
          startOfSubsidy,
          endOfSubsidy,
          daysInSubsidy,
          discountValue,
          subsidyRecord.id
        );
      END LOOP;
    END LOOP;
  END IF;

  -- Totalizamos os valores do período calculado
  EXECUTE format(
    'SELECT ascertainedPeriod.startedAt AS startDate,
            ascertainedPeriod.endedAt AS endDate,
            ascertainedPeriod.grossValue,
            ascertainedPeriod.duration AS ascertainedDays,
            ascertainedPeriod.discountValue,
            greatest(
             (ascertainedPeriod.grossValue - ascertainedPeriod.discountValue),
             0.00
            ) AS finalValue
      FROM (
        SELECT CASE
                 WHEN totaledPeriod.duration = %s AND totaledPeriod.grossValue = 0.00 THEN 0.00
                 WHEN totaledPeriod.duration = %s AND totaledPeriod.grossValue > 0.00 THEN %s
                 ELSE totaledPeriod.grossValue
               END AS grossValue,
               totaledPeriod.duration,
               totaledPeriod.discountValue,
               totaledPeriod.startedAt,
               totaledPeriod.endedAt
          FROM (
            SELECT SUM(
                     CASE
                       WHEN P.billedBefore = TRUE THEN 0.00
                       WHEN P.subsidyID IS NULL THEN periodValue
                       ELSE 0.00
                     END
                   ) AS grossValue,
                   SUM(
                     CASE
                       WHEN P.subsidyID IS NULL THEN duration
                       ELSE 0.00
                     END
                   ) AS duration,
                   SUM(
                     CASE
                       WHEN P.subsidyID IS NULL THEN 0.00
                       ELSE P.periodValue
                     END
                   ) AS discountValue,
                   MIN(P.periodStartedAt) AS startedAt,
                   MAX(P.periodEndedAt) AS endedAt
              FROM %s AS P
            ) AS totaledPeriod
        ) AS ascertainedPeriod;',
    daysInPeriod,
    daysInPeriod,
    monthPrice,
    tempName
  ) INTO periodTotal;
  RAISE NOTICE '%', periodTotal;

  -- Removemos a tabela temporária
  EXECUTE format(
    'DROP TABLE %s;',
    tempName
  );
  
  -- Inicializamos o período acertado para este item de contrato
  ascertainedPeriodFinal := format(
    '{"startdate":"%s","enddate":"%s","monthprice":%s,"grossvalue":%s,"ascertaineddays":%s,"discountvalue":%s,"finalvalue":%s}',
    periodTotal.startDate,
    periodTotal.endDate,
    monthPrice,
    periodTotal.grossValue,
    periodTotal.ascertainedDays,
    periodTotal.discountValue,
    periodTotal.finalValue
  )::jsonb;

  RETURN ascertainedPeriodFinal;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém a relação de dados de contato de cobranças em aberto
-- ---------------------------------------------------------------------
-- Stored Procedure que obtém os dados de clientes com valores em aberto
-- e seus respectivos telefones de contato.
-- ---------------------------------------------------------------------
CREATE TYPE erp.billingContactData AS
(
  name    varchar(100),
  phone1  varchar(16),
  phone2  varchar(16)
);

CREATE OR REPLACE FUNCTION erp.getBillingPhoneList(FcontractorID integer)
RETURNS SETOF erp.billingContactData AS
$$
DECLARE
  customer  record;
  phone  record;
  phoneData  erp.billingContactData%rowtype;
  count  integer;
BEGIN
  -- Recupera os nomes dos clientes com faturas em atraso que estejam à
  -- receber
  FOR customer IN
    SELECT C.name,
           I.customerID AS id,
           I.subsidiaryID
      FROM erp.payments AS P
     INNER JOIN erp.invoices AS I USING (invoiceID)
     INNER JOIN erp.entities AS C ON (I.customerID = C.entityID)
     WHERE P.paymentSituationID = 1
       AND P.dueDate < (CURRENT_DATE - interval '3 days')
       AND P.sentToDunningBureau = FALSE
     GROUP BY name, customerID, subsidiaryID
     ORDER BY name, customerID, subsidiaryID
  LOOP
    phoneData.name   := customer.name;

    -- Para cada cliente, recupera os telefones
    count := 0;
    phoneData.phone1 := '';
    phoneData.phone2 := '';
    FOR phone IN
      SELECT phoneNumber AS number
        FROM erp.phones
       WHERE entityID = customer.id
         AND subsidiaryID = customer.subsidiaryID
       LIMIT 2
    LOOP
      count := count + 1;
      IF (count = 1) THEN
        phoneData.phone1 := phone.number;
      ELSE
        phoneData.phone2 := phone.number;
      END IF;
    END LOOP;

    RETURN NEXT phoneData;
  END LOOP;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Dados do contrato
-- ---------------------------------------------------------------------
-- Altera a stored procedure que recupera as informações de contratos e
-- instalações para o gerenciamento de contratos, incluindo um campo
-- para excluir contratos encerrados.
-- ---------------------------------------------------------------------
DROP FUNCTION erp.getContractsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FcontractID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Factive boolean,
  FtoCarnet boolean, FOrder varchar, Skip integer, LimitOf integer);

CREATE OR REPLACE FUNCTION erp.getContractsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FcontractID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Factive boolean,
  FtoCarnet boolean, FonlyActive boolean, FOrder varchar, Skip integer,
  LimitOf integer)
RETURNS SETOF erp.contractData AS
$$
DECLARE
  contractData erp.contractData%rowtype;
  row          record;
  vehicleData  record;
  query        varchar;
  field        varchar;
  filter       varchar;
  limits       varchar;
  blockedLevel integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FcustomerID IS NULL) THEN
    FcustomerID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FcontractID IS NULL) THEN
    FcontractID = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'customer.name ASC, subsidiary.subsidiaryid ASC, contracts.signaturedate ASC';
  END IF;
  IF (FtoCarnet IS NULL) THEN
    FtoCarnet = FALSE;
  END IF;
  IF (FonlyActive IS NULL) THEN
    FonlyActive = FALSE;
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';
  
  IF (FcontractID > 0) THEN
    filter := format(' AND contracts.contractID = %s',
                    FcontractID);
  ELSE
    -- Realiza a filtragem por cliente
    IF (FcustomerID > 0) THEN
      filter := format(' AND contracts.customerID = %s',
                      FcustomerID);
      IF (FsubsidiaryID > 0) THEN
        filter := filter || format(' AND contracts.subsidiaryID = %s',
                                  FsubsidiaryID);
      END IF;
    END IF;
  END IF;

  IF (FtoCarnet) THEN
    -- Incluímos apenas contratos cuja forma de pagamento seja em carnê
    filter := filter || ' AND contracts.paymentConditionID IN '
      || '('
      ||   'SELECT Carnets.paymentConditionID FROM '
      ||   '('
      ||     'SELECT COND.paymentconditionid, '
      ||            'string_to_array(COND.paymentinterval, ''/'') AS parcels '
      ||       'FROM erp.paymentconditions AS COND '
      ||      'WHERE COND.paymentmethodid = 5 '
      ||        'AND COND.paymentformid = 2 '
      ||        'AND COND.timeunit = ''MONTH'''
      ||   ') AS Carnets '
      ||   'WHERE array_length(Carnets.parcels, 1) > 1 '
      ||     'AND (Carnets.parcels::INT[])[1] > 0'
      || ')'
    ;
  END IF;

  IF (FonlyActive) THEN
    -- Incluímos apenas contratos que não estejam encerrados
    filter := filter || ' AND contracts.endDate IS NULL AND installations.endDate IS NULL';
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      IF (FsearchField = 'plate') THEN
        -- Localizamos instalações em que este veículo esteve associado
        FsearchValue := UPPER(FsearchValue);
        filter := filter || 
          format(' AND installations.installationID IN ('
            || 'SELECT I.installationID'
            || '  FROM erp.vehicles AS V '
            || ' INNER JOIN erp.installationRecords AS I USING (vehicleID)'
            || ' WHERE V.plate ILIKE ''%%%s%%'''
            || ' GROUP BY I.installationID)',
          FsearchValue);
      ELSE
        -- Determina o campo onde será realizada a pesquisa
        CASE (FsearchField)
          WHEN 'contractNumber' THEN
            field := 'erp.getContractNumber(contracts.createdat)';
          ELSE
            field := 'installations.installationNumber';
        END CASE;
        -- Monta o filtro
        filter := filter || format(' AND %s ILIKE ''%%%s%%''',
                                    field, FsearchValue);
      END IF;
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  IF (Factive IS NOT NULL) THEN
    IF (Factive = TRUE) THEN
      -- Adiciona a opção de filtragem de contratos ativos
      filter := filter || ' AND contracts.active = true';
    ELSE
      -- Adiciona a opção de filtragem de contratos inativos
      filter := filter || ' AND contracts.active = false';
    END IF;
  END IF;

  -- Monta a consulta
  query := format('SELECT contracts.contractID,
                          contracts.contractorID,
                          contractor.name AS contractorName,
                          contractor.blocked AS contractorBlocked,
                          contracts.customerID,
                          customer.name AS customerName,
                          customer.blocked AS customerBlocked,
                          customer.entityTypeID AS customerTypeID,
                          customerType.name AS customerTypeName,
                          customerType.cooperative,
                          customerType.juridicalperson,
                          contracts.subsidiaryID,
                          subsidiary.name AS subsidiaryName,
                          subsidiary.blocked AS subsidiaryBlocked,
                          subsidiary.affiliated,
                          erp.getContractNumber(contracts.createdat) AS contractNumber,
                          contracts.planID,
                          plans.name AS planName,
                          contracts.subscriptionPlanID,
                          dueDays.day AS dueDay,
                          contracts.signaturedate,
                          contracts.enddate AS contractenddate,
                          contracts.paymentConditionID,
                          paymentConditions.name AS paymentConditionName,
                          CASE
                            WHEN paymentConditions.timeunit = ''MONTH'' AND paymentConditions.paymentformid = 2 AND paymentConditions.paymentmethodid = 5 THEN array_upper(string_to_array(paymentConditions.paymentinterval, ''/'')::int[], 1)
                            ELSE subscriptionPlans.numberOfMonths
                          END AS numberOfParcels,
                          contracts.monthprice AS contractPrice,
                          contracts.active AS contractActive,
                          installations.installationID,
                          installations.installationNumber,
                          installations.monthprice,
                          installations.startDate,
                          installations.endDate,
                          installations.dateOfNextReadjustment,
                          installations.lastDayOfCalculatedPeriod,
                          installations.lastDayOfBillingPeriod,
                          CASE
                            WHEN installations.lastDayOfBillingPeriod IS NULL THEN ((date_trunc(''month'', (CURRENT_DATE + interval ''1 day'')) + interval ''1 month'') + (dueDays.day - 1) * interval ''1 day'')::Date
                            ELSE ((date_trunc(''month'', (installations.lastDayOfBillingPeriod + interval ''1 day'')) + interval ''1 month'') + (dueDays.day - 1) * interval ''1 day'')::Date
                          END AS firstDueDate,
                          vehicle.vehicleID,
                          vehicle.plate,
                          vehicle.vehicleTypeID,
                          vehicle.vehicleTypeName,
                          vehicle.vehicleBrandID,
                          vehicle.vehicleBrandName,
                          vehicle.vehicleModelID,
                          vehicle.vehicleModelName,
                          vehicle.vehicleColorID,
                          vehicle.vehicleColorName,
                          vehicle.blocked AS vehicleBlocked,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID
                                     AND uninstalledAt IS NULL) AS tracked,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID) AS containsTrackingData,
                          count(*) OVER() AS fullcount
                     FROM erp.contracts
                    INNER JOIN erp.subscriptionPlans USING (subscriptionPlanID)
                    INNER JOIN erp.entities AS contractor ON (contracts.contractorID = contractor.entityID)
                    INNER JOIN erp.entities AS customer ON (contracts.customerID = customer.entityID)
                    INNER JOIN erp.entitiesTypes AS customerType ON (customer.entityTypeID = customerType.entityTypeID)
                    INNER JOIN erp.subsidiaries AS subsidiary ON (contracts.subsidiaryID = subsidiary.subsidiaryID)
                    INNER JOIN erp.dueDays ON (contracts.dueDayID = dueDays.dueDayID)
                    INNER JOIN erp.paymentConditions ON (contracts.paymentConditionID = paymentConditions.paymentConditionID)
                    INNER JOIN erp.plans ON (contracts.planID = plans.planID)
                    INNER JOIN erp.installations ON (contracts.contractID = installations.contractID)
                    INNER JOIN erp.getMostRecentVehicleOnInstallation(contracts.contractorID, installations.installationid) AS vehicle ON (installations.installationID = vehicle.installationID)
                    WHERE contracts.contractorID = %s
                      AND contracts.deleted = false
                      AND customer.deleted = false
                      AND subsidiary.deleted = false %s
                    ORDER BY %s %s',
                  fContractorID, filter, FOrder, limits);
  -- RAISE NOTICE 'Query: %', query;
  FOR row IN EXECUTE query
  LOOP
    contractData.contractID                 := row.contractID;
    contractData.contractorID               := row.contractorID;
    contractData.contractorName             := row.contractorName;
    contractData.contractorBlocked          := row.contractorBlocked;
    contractData.customerID                 := row.customerID;
    contractData.customerName               := row.customerName;
    contractData.customerBlocked            := row.customerBlocked;
    contractData.customerTypeID             := row.customerTypeID;
    contractData.customerTypeName           := row.customerTypeName;
    contractData.juridicalperson            := row.juridicalperson;
    contractData.cooperative                := row.cooperative;
    contractData.subsidiaryID               := row.subsidiaryID;
    contractData.subsidiaryName             := row.subsidiaryName;
    contractData.subsidiaryBlocked          := row.subsidiaryBlocked;
    contractData.affiliated                 := row.affiliated;
    contractData.contractNumber             := row.contractNumber;
    contractData.planID                     := row.planID;
    contractData.planName                   := row.planName;
    contractData.dueDay                     := row.dueDay;
    contractData.signatureDate              := row.signatureDate;
    contractData.contractEndDate            := row.contractEndDate;
    contractData.paymentConditionID         := row.paymentConditionID;
    contractData.paymentConditionName       := row.paymentConditionName;
    contractData.numberOfParcels            := row.numberOfParcels;
    contractData.contractPrice              := row.contractPrice;
    contractData.contractActive             := row.contractActive;
    contractData.installationID             := row.installationID;
    contractData.installationNumber         := row.installationNumber;
    contractData.noTracker                  := NOT row.tracked;
    contractData.containsTrackingData       := row.containsTrackingData;
    contractData.monthPrice                 := row.monthPrice;
    contractData.startDate                  := row.startDate;
    contractData.endDate                    := row.endDate;
    contractData.dateOfNextReadjustment     := row.dateOfNextReadjustment;
    contractData.lastDayOfCalculatedPeriod  := row.lastDayOfCalculatedPeriod;
    contractData.lastDayOfBillingPeriod     := row.lastDayOfBillingPeriod;
    contractData.firstDueDate               := row.firstDueDate;
    -- RAISE NOTICE 'Contractor %', row.contractorBlocked;
    -- RAISE NOTICE 'Vehicle %', row.vehicleBlocked;
    -- RAISE NOTICE 'Subsidiary %', row.subsidiaryBlocked;
    contractData.vehicleID                  := row.vehicleID;
    contractData.plate                      := row.plate;
    contractData.vehicleTypeID              := row.vehicleTypeID;
    contractData.vehicleTypeName            := row.vehicleTypeName;
    contractData.vehicleBrandID             := row.vehicleBrandID;
    contractData.vehicleBrandName           := row.vehicleBrandName;
    contractData.vehicleModelID             := row.vehicleModelID;
    contractData.vehicleModelName           := row.vehicleModelName;
    contractData.vehicleColorID             := row.vehicleColorID;
    contractData.vehicleColorName           := row.vehicleColorName;
    contractData.vehicleBlocked             := row.vehicleBlocked;

    -- Determina o nível de bloqueio. O nível de bloqueio mais baixo é o
    -- da instalação, seguido do contrato, da unidade/filial do cliente,
    -- da empresa e por último o do contratante
    blockedLevel := 0;
    IF (row.endDate IS NOT NULL) THEN
      -- A instalação foi encerrada
      blockedLevel := blockedLevel|1;
    END IF;
    IF ( (row.contractEndDate IS NOT NULL) OR
         (row.contractActive = FALSE) ) THEN
      -- O contrato está encerrado ou foi inativado
      blockedLevel := blockedLevel|2;
    END IF;
    IF (row.subsidiaryBlocked) THEN
      -- A unidade/filial do cliente foi inativada
      blockedLevel := blockedLevel|4;
    END IF;
    IF (row.customerBlocked) THEN
      -- O cliente foi bloqueado
      blockedLevel := blockedLevel|8;
    END IF;
    IF (row.contractorBlocked) THEN
      -- O contratante foi bloqueado
      blockedLevel := blockedLevel|16;
    END IF;
    contractData.blockedLevel := blockedLevel;
    contractData.fullcount    := row.fullcount;

    RETURN NEXT contractData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- Alteramos getBillingData
CREATE OR REPLACE FUNCTION erp.getBillingsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FinvoiceID integer,
  FsearchValue varchar(100), FsearchField varchar(20),
  FinMonthlyCalculation boolean, FOrder varchar, Skip integer,
  LimitOf integer)
RETURNS SETOF erp.billingData AS
$$
DECLARE
  billingData  erp.billingData%rowtype;
  row          record;
  vehicleData  record;
  query        varchar;
  field        varchar;
  filter       varchar;
  limits       varchar;
  blockedLevel integer;
  Finvoiced    varchar;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FcustomerID IS NULL) THEN
    FcustomerID = 0;
  END IF;
  IF (FinvoiceID IS NULL) THEN
    FinvoiceID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FinMonthlyCalculation IS NULL) THEN
    FinMonthlyCalculation = FALSE;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'customers.name, installations.installationID, billings.billingDate, billings.installmentNumber';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';
  
  -- Realiza a filtragem por cliente
  IF (FcustomerID > 0) THEN
    filter := format(' AND customers.entityID = %s',
                    FcustomerID);
    IF (FsubsidiaryID > 0) THEN
      filter := filter || format(' AND subsidiaries.subsidiaryID = %s',
                                 FsubsidiaryID);
    END IF;
  END IF;

  IF (FinvoiceID > 0) THEN
    -- Visualizamos apenas os valores que estão em uma fatura
    filter := filter || format(' AND billings.invoiceID = %s',
                               FinvoiceID);
    Finvoiced := 'true';
  ELSE
    Finvoiced := 'false';
  END IF;

  IF (FinMonthlyCalculation) THEN
    -- Visualizamos apenas os valores que estão no processo de análise
    -- para o faturamento
    filter := filter || format(' AND billings.invoiceID IS NOT NULL');
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      -- Determina o campo onde será realizada a pesquisa
      CASE (FsearchField)
        WHEN 'plate' THEN
          -- Localizamos instalações em que este veículo esteve associado
          FsearchValue := UPPER(FsearchValue);
          filter := filter || 
            format(' AND installations.installationID IN ('
              || 'SELECT I.installationID'
              || '  FROM erp.vehicles AS V '
              || ' INNER JOIN erp.installationRecords AS I USING (vehicleID)'
              || ' WHERE V.plate ILIKE ''%%%s%%'''
              || ' GROUP BY I.installationID)',
            FsearchValue);
        WHEN 'installationid' THEN
          -- Localizamos pelo ID da instalação
          filter := filter ||
            format(' AND installations.installationid = %s', FsearchValue);
        WHEN 'contractNumber' THEN
          -- Localizamos pelo número do contrato
          field := 'erp.getContractNumber(contracts.createdat)';
          filter := filter ||
            format(' AND %s ILIKE ''%%%s%%''', field, FsearchValue);
        ELSE
          -- Localizamos pelo número da instalação
          field := 'installations.installationNumber';
          filter := filter ||
            format(' AND %s ILIKE ''%%%s%%''', field, FsearchValue);
      END CASE;
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  -- Monta a consulta
  query := format('
    SELECT billings.billingID,
           contracts.customerID,
           customers.name AS customerName,
           entitiesTypes.cooperative,
           entitiesTypes.juridicalperson,
           contracts.subsidiaryID,
           subsidiaries.name AS subsidiaryName,
           subsidiaries.affiliated,
           billings.contractID,
           erp.getContractNumber(contracts.createdat) AS contractNumber,
           contracts.planid,
           plans.name AS planName,
           dueDays.day AS dueDay,
           billings.installationID,
           installations.installationNumber,
           billings.billingDate,
           CASE
             WHEN billings.renegotiationid IS NOT NULL AND billings.numberofinstallments > 0 THEN ''Renegociação de '' || billings.name || '' (Parcela '' || billings.installmentNumber || '' de '' || billings.numberofinstallments || '')''
             WHEN billings.renegotiationid IS NOT NULL AND billings.numberofinstallments = 0 THEN ''Renegociação de '' || billings.name
             WHEN billings.numberofinstallments > 0 THEN billings.name || '' (Parcela '' || billings.installmentNumber || '' de '' || billings.numberofinstallments || '')''
             ELSE billings.name
           END AS name,
           billings.value AS billingValue,
           billings.installmentNumber,
           billings.numberOfInstallments,
           billings.granted,
           billings.reasonforgranting,
           billings.renegotiated,
           billings.renegotiationid,
           CASE
             WHEN billings.invoiceID IS NULL THEN FALSE
             ELSE TRUE
           END AS inMonthlyCalculation,
           billings.ascertainedPeriodID,
           billings.invoiceID,
           count(*) OVER() AS fullcount
      FROM erp.billings
     INNER JOIN erp.contracts ON (billings.contractID = contracts.contractID)
     INNER JOIN erp.plans ON (contracts.planID = plans.planID)
     INNER JOIN erp.dueDays ON (contracts.dueDayID = dueDays.dueDayID)
     INNER JOIN erp.entities AS customers ON (contracts.customerID = customers.entityID)
     INNER JOIN erp.entitiesTypes ON (customers.entityTypeID = entitiesTypes.entityTypeID)
     INNER JOIN erp.subsidiaries ON (contracts.subsidiaryID = subsidiaries.subsidiaryID)
      LEFT JOIN erp.installations ON (billings.installationID = installations.installationID)
     WHERE contracts.contractorID = %s
       AND billings.invoiced = %s
       AND contracts.deleted = false
       AND customers.deleted = false
       AND subsidiaries.deleted = false %s
     ORDER BY %s %s;',
    fContractorID, Finvoiced, filter, FOrder, limits);
  -- RAISE NOTICE 'Query IS %', query;
  FOR row IN EXECUTE query
  LOOP
    billingData.billingID            := row.billingID;
    billingData.customerID           := row.customerID;
    billingData.customerName         := row.customerName;
    billingData.cooperative          := row.cooperative;
    billingData.juridicalperson      := row.juridicalperson;
    billingData.subsidiaryID         := row.subsidiaryID;
    billingData.subsidiaryName       := row.subsidiaryName;
    billingData.affiliated           := row.affiliated;
    billingData.contractID           := row.contractID;
    billingData.contractNumber       := row.contractNumber;
    billingData.planID               := row.planID;
    billingData.planName             := row.planName;
    billingData.dueDay               := row.dueDay;
    billingData.installationID       := row.installationID;
    billingData.installationNumber   := row.installationNumber;
    billingData.billingDate          := row.billingDate;
    billingData.name                 := row.name;
    billingData.billingValue         := row.billingValue;
    billingData.installmentNumber    := row.installmentNumber;
    billingData.numberOfInstallments := row.numberOfInstallments;
    billingData.granted              := row.granted;
    billingData.reasonforgranting    := row.reasonforgranting;
    billingData.renegotiated         := row.renegotiated;
    billingData.renegotiationID      := row.renegotiationID;
    billingData.inMonthlyCalculation := row.inMonthlyCalculation;
    billingData.ascertainedPeriodID  := row.ascertainedPeriodID;
    billingData.invoiceID            := row.invoiceID;
    billingData.fullcount            := row.fullcount;

    -- Localizamos o veículo
    SELECT DISTINCT ON (I.installationID)
           R.vehicleID,
           V.plate
      INTO vehicleData
      FROM erp.installations AS I
     INNER JOIN erp.installationRecords AS R USING (installationID)
     INNER JOIN erp.vehicles AS V USING (vehicleID)
     WHERE I.installationID = row.installationID
     ORDER BY I.installationID, R.uninstalledAt NULLS FIRST, R.installedAt DESC;
    IF NOT FOUND THEN
      billingData.vehicleID = NULL;
      billingData.plate     = NULL;
    ELSE
      billingData.vehicleID = vehicleData.vehicleID;
      billingData.plate     = vehicleData.plate;
    END IF;

    RETURN NEXT billingData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Calcula os valores referentes ao serviço a ser prestado em um item de
-- contrato levando em consideração um período a ser cobrado.
-- ---------------------------------------------------------------------
-- Stored Procedure que determina os valores a serem cobrados em um item
-- de contrato em função dos serviços que ainda serão prestados, levando
-- em consideração períodos já cobrados, bem como valores de assessórios
-- e outros valores presentes em contrato e de subsídios consedidos.
-- ---------------------------------------------------------------------
CREATE TYPE erp.performedServiceData AS
(
  referenceMonthYear  varchar(7),
  startDateOfPeriod   date,
  endDateOfPeriod     date,
  name                varchar(100),
  value               numeric(12,2)
);

CREATE OR REPLACE FUNCTION erp.toBePerformedService(FinstallationID integer,
  FstartDate date, FnumberOfParcels int)
RETURNS SETOF erp.performedServiceData AS
$$
DECLARE
  serviceData  erp.performedServiceData%rowtype;
  installation  record;

  -- Os parâmetros para cálculo de cada mensalidade e do valor total a
  -- ser cobrado
  parcelNumber  int;
  referenceDate  date;
  startDateOfPeriod  date;
  endDateOfPeriod  date;
  startDateOfBillingPeriod  date;
  monthlyValue  numeric;
  daysToConsider  smallint;

  -- O cálculo do valor de mensalidade por dia
  daysInPeriod  smallint;
  dayPrice  numeric;

  -- A análise de subsídios aplicados
  subsidyRecord  record;
  startOfSubsidy  date;
  endOfSubsidy  date;
  daysInSubsidy  smallint;
  discountValue  numeric;

  -- A análise de outras mensalidades presentes em contrato
  monthlyFeesRecord  record;
BEGIN
  -- Inicializamos as variáveis de processamento
  parcelNumber := 1;
  referenceDate := FstartDate;

  IF (FnumberOfParcels > 0) THEN
    LOOP
      -- Estamos processando cada período da cobrança antecipada, então
      -- precisamos analisar os períodos sendo cobrados e construir o
      -- valor a ser cobrado baseado nos valores computados a cada mês em
      -- cada item de contrato informado
      RAISE NOTICE 'Número da parcela: %', parcelNumber;
      RAISE NOTICE 'Data de referência: %', TO_CHAR(referenceDate, 'DD/MM/YYYY');

      -- Recupera as informações dos itens de contrato informados
      FOR installation IN
        SELECT I.installationID AS id,
               I.installationNumber AS number,
               C.signatureDate,
               C.startTermAfterInstallation,
               P.prorata,
               I.startDate,
               I.monthprice,
               I.contractID,
               I.lastDayOfBillingPeriod
          FROM erp.installations AS I
         INNER JOIN erp.contracts AS C ON (I.contractID = C.contractID)
         INNER JOIN erp.plans AS P ON (P.planID = I.planID)
         WHERE C.deleted = false
           AND C.active = true
           AND I.endDate IS NULL
           AND I.installationID = FinstallationID
         ORDER BY C.customerPayerid, C.subsidiaryPayerid, C.unifybilling, C.contractID, I.installationid
      LOOP
        -- Para cada parcela sendo calculada, analisamos se devemos ou não
        -- cobrar o período para cada um dos itens de contrato informados,
        -- de forma que conseguimos construir o valor final corretamente
        RAISE NOTICE 'Número do item de contrato: %', installation.number;
        RAISE NOTICE 'Data da assinatura do contrato: %', TO_CHAR(installation.signatureDate, 'DD/MM/YYYY');
        RAISE NOTICE 'Data do início do item de contrato: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');
        RAISE NOTICE 'Iniciar cobrança após instalação: %', CASE WHEN installation.startTermAfterInstallation THEN 'Sim' ELSE 'Não' END;
        RAISE NOTICE 'Cobrar proporcional ao serviço prestado: %', CASE WHEN installation.prorata THEN 'Sim' ELSE 'Não' END;

        -- Determinamos o período de cobrança em um mês
        startDateOfPeriod := referenceDate;
        endDateOfPeriod := startDateOfPeriod + interval '1 month' - interval '1 day';
        RAISE NOTICE 'Período de % à %', startDateOfPeriod, endDateOfPeriod;

        -- Determinamos à partir de qual data devemos cobrar
        IF (installation.prorata) THEN
          -- Devemos cobrar proporcionalmente, então determinamos quando
          -- isto ocorre
          IF (installation.startTermAfterInstallation) THEN
            IF (installation.startDate IS NULL) THEN
              -- Como a instalação não ocorreu ainda, então consideramos o
              -- início do período mesmo
              RAISE NOTICE 'Instalação não ocorreu, considerando o período inteiro';
              startDateOfBillingPeriod := startDateOfPeriod;
            ELSE
              -- Verificamos se o início do item de contrato ocorreu
              -- durante o período que estamos analisado
              IF (installation.startDate >= startDateOfPeriod) THEN
                -- Consideramos a data de instalação
                RAISE NOTICE 'Consideramos a data de instalação';
                startDateOfBillingPeriod := installation.startDate;
              ELSE
                -- Como a instalação se deu antes do início do período que
                -- iremos cobrar, então consideramos o início do período
                -- mesmo
                RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          ELSE
            IF (installation.signatureDate IS NULL) THEN
              -- Como o contrato não foi assinado ainda, então consideramos
              -- o início do período mesmo
              RAISE NOTICE 'Contrato não foi assinado, considerando o período inteiro';
              startDateOfBillingPeriod := baseDate;
            ELSE
              -- Verificamos se a assintatura ocorreu durante o período
              -- sendo analisado
              IF (installation.signatureDate >= startDateOfPeriod) THEN
                -- Consideramos a data de assinatura
                RAISE NOTICE 'Consideramos a data de assinatura do contrato';
                startDateOfBillingPeriod := installation.signatureDate;
              ELSE
                -- Como a assinatura do contrato se deu antes do início do
                -- período que iremos cobrar, então consideramos o início
                -- do período mesmo
                RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          END IF;
        ELSE
          -- Devemos cobrar integralmente, então o início se dá sempre no
          -- início do período apurado
          RAISE NOTICE 'Consideramos o período inteiro';
          startDateOfBillingPeriod := startDateOfPeriod;
        END IF;

        -- Verificamos se já foram realizadas cobranças de períodos neste
        -- item de contrato
        IF (installation.lastDayOfBillingPeriod IS NOT NULL) THEN
          -- Precisamos levar em consideração também o último período já
          -- cobrado se ele for superior ao período que estamos cobrando
          IF ((installation.lastDayOfBillingPeriod + interval '1 day') > startDateOfBillingPeriod) THEN
            startDateOfBillingPeriod := installation.lastDayOfBillingPeriod + interval '1 day';
            RAISE NOTICE 'Consideramos o período iniciando em %', startDateOfBillingPeriod;
          END IF;
        END IF;

        -- Calculamos a quantidade de dias no período
        daysInPeriod := DATE_PART('day',
            endDateOfPeriod::timestamp - startDateOfPeriod::timestamp
          ) + 1;
        RAISE NOTICE 'Este período possui % dias', daysInPeriod;

        -- Calculamos o valor diário com base na mensalidade
        dayPrice = installation.monthPrice / daysInPeriod;
        RAISE NOTICE 'O valor diário é %', ROUND(dayPrice, 2);

        -- Verificamos se precisamos cobrar algum período nesta parcela
        -- para esta instalação
        IF (startDateOfBillingPeriod <= endDateOfPeriod) THEN
          IF (installation.prorata) THEN
            IF (startDateOfBillingPeriod = startDateOfPeriod) THEN
              -- Cobramos o valor integral da mensalidade
              monthlyValue := installation.monthPrice;
              RAISE NOTICE 'Cobrando valor integral da mensalidade';
            ELSE
              -- Cobramos o valor proporcional

              -- Calculamos a quantidade de dias a serem cobrados
              daysToConsider := DATE_PART('day',
                  endDateOfPeriod::timestamp - startDateOfBillingPeriod::timestamp
                ) + 1;
              
              -- O serviço será prestado por uma parte do mês
              monthlyValue := ROUND(daysToConsider * dayPrice, 2);
            END IF;
          ELSE
            -- Cobramos sempre o valor integral da mensalidade
            monthlyValue := installation.monthPrice;
            RAISE NOTICE 'Cobrando valor integral da mensalidade';
          END IF;

          IF (monthlyValue > 0.00) THEN
            -- Acrescentamos esta valor a ser cobrado
            RAISE NOTICE 'O valor da mensalidade calculada é %', ROUND(monthlyValue, 2);
            serviceData.referenceMonthYear := to_char(referenceDate, 'MM/YYYY');
            serviceData.startDateOfPeriod  := startDateOfBillingPeriod;
            serviceData.endDateOfPeriod    := endDateOfPeriod;
            -- serviceData.contractID         := installation.contractid;
            -- serviceData.installationID     := installation.id;
            -- serviceData.installationNumber := installation.number;
            serviceData.name               := format(
              'Mensalidade de %s à %s',
              TO_CHAR(startDateOfBillingPeriod, 'DD/MM/YYYY'),
              TO_CHAR(endDateOfPeriod, 'DD/MM/YYYY')
            );
            serviceData.value              := ROUND(monthlyValue, 2);

            RETURN NEXT serviceData;

            -- Agora analisamos quaisquer subsídios ou bonificações
            -- existentes de forma a concedermos os respectivos descontos,
            -- se pertinente
            FOR subsidyRecord IN
              SELECT performedSubsidies.subsidyID AS ID,
                     performedSubsidies.bonus,
                     performedSubsidies.performedPeriod,
                     performedSubsidies.subsidyPeriod * performedSubsidies.performedPeriod AS subsidedPeriod,
                     performedSubsidies.discountType,
                     performedSubsidies.discountValue
                FROM (
                  SELECT S.subsidyID,
                         S.bonus,
                         public.intervalOfPeriod(startDateOfBillingPeriod, endDateOfPeriod) AS performedPeriod,
                         ('[' || S.periodStartedAt || ',' || COALESCE(S.periodEndedAt || ']', ')'))::public.intervalOfPeriod AS subsidyPeriod,
                         S.discountType,
                         S.discountValue
                    FROM erp.subsidies AS S
                   WHERE S.installationID = FinstallationID
                     AND (
                       (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                       (S.periodEndedAt >= startDateOfBillingPeriod)
                      )
                   ORDER BY S.bonus DESC, S.periodStartedAt
                  ) AS performedSubsidies
            LOOP
              -- Calculamos os valores deste desconto
              startOfSubsidy := lower(subsidyRecord.subsidedPeriod);
              endOfSubsidy   := upper(subsidyRecord.subsidedPeriod);
              IF (startOfSubsidy IS NOT NULL) THEN
                daysInSubsidy  := DATE_PART('day',
                    endOfSubsidy::timestamp - startOfSubsidy::timestamp
                  ) + 1;
                RAISE NOTICE 'Período com % dias', daysInSubsidy;
                IF subsidyRecord.bonus THEN
                  -- Aplicamos 100% de desconto no período
                  IF (daysInSubsidy = daysInPeriod) THEN
                    -- O desconto foi concedido pelo mês inteiro
                    discountValue := monthPrice;
                  ELSE
                    -- O desconto foi concedido por uma parte do mês
                    discountValue := ROUND(daysInSubsidy * dayPrice, 2);
                  END IF;
                ELSE
                  -- Precisamos calcular o desconto em função do período
                  IF (subsidyRecord.discountType = 1) THEN
                    -- O desconto é um valor fixo em reais por dia
                    discountValue :=
                      ROUND(daysInSubsidy * subsidyRecord.discountValue, 2)
                    ;
                  ELSE
                    -- O desconto é uma porcentagem do valor do período
                    discountValue :=
                      ROUND(
                        (
                          (daysInSubsidy * dayPrice) *
                          subsidyRecord.discountValue / 100
                        ),
                        2
                      )
                    ;
                  END IF;
                END IF;

                RAISE NOTICE 'Adicionando desconto no item de contrato';
                serviceData.name  := format(
                  'Desconto de %s à %s',
                  TO_CHAR(startOfSubsidy, 'DD/MM/YYYY'),
                  TO_CHAR(endOfSubsidy, 'DD/MM/YYYY')
                );
                serviceData.value := ROUND((0 - discountValue), 2);

                RETURN NEXT serviceData;
              END IF;
            END LOOP;

            -- Agora analisamos quaisquer outros valores presentes no
            -- contrato e que precisam ser computados
            FOR monthlyFeesRecord IN
              SELECT C.name,
                     C.chargeValue AS value
                FROM erp.contractCharges AS C
               INNER JOIN erp.billingTypes AS B USING (billingTypeID)
               WHERE C.contractID = installation.contractid
                 AND B.billingFormatID = 3
                 AND B.billingMomentID = 5
                 AND B.ratePerEquipment = true
            LOOP
              RAISE NOTICE 'Adicionando a cobrança de % do item de contrato para ser cobrada', monthlyFeesRecord.name;
              serviceData.name  := monthlyFeesRecord.name;
              serviceData.value := monthlyFeesRecord.value;

              RETURN NEXT serviceData;
            END LOOP;
          END IF;
        END IF;
      END LOOP;
      
      -- Incrementamos a quantidade de parcelas que fizemos
      parcelNumber := parcelNumber + 1;

      -- Avançamos para o próximo mês
      referenceDate := referenceDate + interval '1 month';

      -- Repetimos este processo até determinar parcela seja superior a
      -- quantidade de parcelas a serem emitidas
      EXIT WHEN parcelNumber > FnumberOfParcels;
    END LOOP;
  END IF;
END;
$$ LANGUAGE 'plpgsql';

-- Atualizamos a função de geração dos valores de fechamento
DROP FUNCTION erp.performedServiceInPeriod(baseDate date,
  FinstallationID integer, FuserID integer);

CREATE OR REPLACE FUNCTION erp.performedServiceInPeriod(baseDate date,
  FinstallationID integer, FuserID integer, endDate date) RETURNS integer AS
$$
DECLARE
  -- As informações do item de contrato
  installation  record;

  -- A data do final do último período já processado
  lastDayOfCalculatedPeriod  date;

  -- O indicativo de que devemos adicionar uma nova cobrança
  addBilling  boolean;

  -- A data do início do período de cobrança efetiva (desconsiderado
  -- períodos para os quais já foram apurados os valores)
  effectiveDate  date;

  -- O valor cobrado mensalmente
  monthPrice  numeric(12,2);

  -- O cálculo do valor de mensalidade por dia
  dayPrice  numeric;
  daysInPeriod  smallint;

  -- O período que iremos processar
  startDateOfPeriod  date;
  endDateOfPeriod  date;

  -- Os registros de instalação apurados
  installationRecord  record;

  -- Os registros de subsídios a serem aplicados
  subsidyRecord  record;

  -- O período do serviço executado
  startOfPerformedService  date;
  endOfPerformedService  date;
  daysInPerformedService  smallint;
  grossValue  numeric(12,2);
  billedBefore  boolean;

  -- O último período apurado para determinação de ocorrências de
  -- sobreposição de dias de serviço prestado em veículos diferentes
  lastPerformedPeriod  public.intervalOfPeriod;
  performedPeriod  public.intervalOfPeriod;

  -- O período do subsídio aplicado
  startOfSubsidy  date;
  endOfSubsidy  date;
  daysInSubsidy  smallint;
  discountValue  numeric(12,2);

  -- O ID do período acertado
  newAscertainedPeriodID  erp.ascertainedPeriods.ascertainedPeriodID%TYPE;

  -- A quantidade de serviços qualificados para cobrança
  amountOfQualifyServices  integer;
BEGIN
  IF (endDate IS NOT NULL) THEN
    endDateOfPeriod := endDate;
  ELSE
    -- Determina o final do período a ser calculado como sendo o final do
    -- mês para o qual estamos cobrando
    endDateOfPeriod := baseDate + interval '1 month' - interval '1 day';
  END IF;

  -- Recuperamos as informações do item de contrato sendo analisado
  RAISE NOTICE 'Recuperando informações do item de contrato';
  SELECT INTO installation
         I.contractorID,
         I.installationNumber AS number,
         C.signatureDate,
         C.startTermAfterInstallation,
         P.prorata,
         I.startDate,
         I.endDate,
         I.lastDayOfCalculatedPeriod,
         I.lastDayOfBillingPeriod
    FROM erp.installations AS I
   INNER JOIN erp.contracts AS C ON (C.contractID = I.contractID)
   INNER JOIN erp.plans AS P ON (C.planID = I.planID)
   WHERE I.installationID = FinstallationID;

  RAISE NOTICE 'Número do item de contrato: %', installation.number;
  RAISE NOTICE 'Data da assinatura do contrato: %', TO_CHAR(installation.signatureDate, 'DD/MM/YYYY');
  RAISE NOTICE 'Data do início do item de contrato: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');
  RAISE NOTICE 'Iniciar cobrança após instalação: %', CASE WHEN installation.startTermAfterInstallation THEN 'Sim' ELSE 'Não' END;
  RAISE NOTICE 'Cobrar proporcional ao serviço prestado: %', CASE WHEN installation.prorata THEN 'Sim' ELSE 'Não' END;
  lastDayOfCalculatedPeriod := installation.lastDayOfCalculatedPeriod;
  RAISE NOTICE 'Término do último período apurado: %', CASE WHEN lastDayOfCalculatedPeriod IS NULL THEN 'Não disponível' ELSE TO_CHAR(lastDayOfCalculatedPeriod, 'DD/MM/YYYY') END;

  -- Verifica se já temos algum processamento realizado
  IF (lastDayOfCalculatedPeriod IS NULL) THEN
    -- Ainda não foi apurado nenhum período para este item de contrato

    -- Verifica se a cobrança deve ser proporcional
    IF (installation.prorata) THEN
      -- Devemos cobrar proporcionalmente, então determinamos quando
      -- isto ocorre
      IF (installation.startTermAfterInstallation) THEN
        IF (installation.startDate IS NULL) THEN
          -- Como a instalação não ocorreu ainda, então consideramos o
          -- início do período mesmo
          RAISE NOTICE 'Instalação não ocorreu, considerando o período inteiro';
          effectiveDate := baseDate;
        ELSE
          -- O início da vigência ocorre após a instalação do equipamento
          effectiveDate := installation.startDate;
        END IF;
      ELSE
        IF (installation.signatureDate IS NULL) THEN
          -- Como o contrato não foi assinado ainda, então consideramos
          -- o início do período mesmo
          RAISE NOTICE 'Contrato não foi assinado, considerando o período inteiro';
          effectiveDate := baseDate;
        ELSE
          -- O início da vigência ocorre na data de assinatura do contrato
          effectiveDate := installation.signatureDate;
        END IF;
      END IF;
    ELSE
      -- Devemos cobrar integralmente, então o início se dá sempre no
      -- início do período apurado
      effectiveDate := baseDate;
    END IF;

    lastDayOfCalculatedPeriod := effectiveDate - interval '1 day';
    RAISE NOTICE 'Considerando início do período de cobrança como sendo %', TO_CHAR(effectiveDate, 'DD/MM/YYYY');
  END IF;

  IF (endDateOfPeriod > installation.endDate) THEN
    endDateOfPeriod := installation.endDate;
  END IF;

  -- Determina se o período já foi processado
  IF (lastDayOfCalculatedPeriod >= endDateOfPeriod) THEN
    -- Já processamos este período, então simplesmente ignora
    RAISE NOTICE 'Este item de contrato já teve valores apurados até %. Ignorando.',
      to_char(lastDayOfCalculatedPeriod, 'DD/MM/YYYY');

    RETURN NULL;
  END IF;

  -- Determina o início do período a ser calculado
  IF (lastDayOfCalculatedPeriod < baseDate) THEN
    -- O último período calculado é inferior à data que se deseja
    -- calcular, então inicia no primeiro dia da data-base informada
    startDateOfPeriod := baseDate;
  ELSE
    -- O último período calculado é igual ou superior à data base que se
    -- deseja calcular, então utiliza o dia seguinte à esta data como
    -- sendo a data do período a ser calculado
    startDateOfPeriod := lastDayOfCalculatedPeriod + interval '1 day';
  END IF;

  RAISE NOTICE 'O período a ser processado é de % até %', to_char(startDateOfPeriod, 'DD/MM/YYYY'), to_char(endDateOfPeriod, 'DD/MM/YYYY');

  -- Selecionar a tarifa vigente no início deste período
  IF (installation.startDate > startDateOfPeriod) THEN
    -- Recuperamos à partir da data de instalação, já que esta ocorreu
    -- durante o período que estamos apurando
    startOfPerformedService := installation.startDate;
  ELSE
    startOfPerformedService := startDateOfPeriod;
  END IF;
  SELECT INTO monthPrice
         readjustment.monthPrice
    FROM erp.readjustmentsOnInstallations AS readjustment
   WHERE readjustment.installationID = FinstallationID
     AND readjustment.readjustedAt <= startOfPerformedService
   ORDER BY readjustment.readjustedAt DESC
   FETCH FIRST ROW ONLY;
  IF NOT FOUND THEN
    -- Caso o item de contrato tenha sido criada neste mês, a consulta
    -- acima não irá retornar um valor. Neste caso, considera o
    -- mês corrente
    SELECT INTO monthPrice
           readjustment.monthPrice
      FROM erp.readjustmentsOnInstallations AS readjustment
     WHERE readjustment.installationID = FinstallationID
       AND readjustment.readjustedAt < (startOfPerformedService + interval '1 month')
     ORDER BY readjustment.readjustedAt DESC
     FETCH FIRST ROW ONLY;
    IF NOT FOUND THEN
      -- Disparamos uma exceção
      RAISE EXCEPTION 'Não foi possível obter a mensalidade para o item de contrato % para o período com início em %',
        installation.number,
        startDateOfPeriod
      USING HINT = 'Por favor, verifique os valores da mensalidade para este item de contrato';

      RETURN NULL;
    END IF;
  END IF;
  RAISE NOTICE 'A mensalidade considerada é %', monthPrice;

  -- Determinamos a quantidade de dias e o valor diário
  daysInPeriod := DATE_PART('day',
      (baseDate + interval '1 month' - interval '1 day')::timestamp - baseDate::timestamp
    ) + 1;
  RAISE NOTICE 'Este período possui % dias', daysInPeriod;
  dayPrice = monthPrice / daysInPeriod;
  RAISE NOTICE 'O valor diário é %', ROUND(dayPrice, 2);

  -- Determinamos se temos serviços qualificados para serem cobrados
  SELECT count(*) INTO amountOfQualifyServices
    FROM erp.installationRecords AS R
   WHERE R.installationID = FinstallationID
     AND (
       (R.uninstalledAt IS NULL AND R.installedAt <= endDateOfPeriod) OR
       (R.uninstalledAt >= startDateOfPeriod AND R.installedAt <= endDateOfPeriod)
      );
  IF (amountOfQualifyServices = 0) THEN
    RAISE NOTICE 'Não temos um período qualificado entre % e % para o item de contrato %', startDateOfPeriod, endDateOfPeriod, FinstallationID;
    RETURN 0;
  END IF;

  -- Criamos um novo período acertado para este item de contrato e
  -- registramos os valores iniciais
  INSERT INTO erp.ascertainedPeriods (contractorID, installationID,
    referenceMonthYear, startDate, endDate, monthPrice) VALUES
   (installation.contractorID, FinstallationID, to_char(startDateOfPeriod, 'MM/YYYY'),
    startDateOfPeriod, endDateOfPeriod, monthPrice)
  RETURNING ascertainedPeriodID INTO newAscertainedPeriodID;

  -- Verifica se a cobrança deve ser proporcional
  lastPerformedPeriod := null;
  IF (installation.prorata) THEN
    -- Recupera os registros de equipamentos instalados, os quais, de
    -- alguma forma, pertencem ao período informado, considerando 1 mês
    -- desde o início do período
    FOR installationRecord IN
      SELECT installationRecordID AS ID,
             vehicleID,
             equipmentID,
             ascertainedPeriod,
             performedService,
             ascertainedPeriod * performedService AS calculatedPeriod
        FROM (
          SELECT R.installationRecordID,
                 R.vehicleID,
                 R.equipmentID,
                 public.intervalOfPeriod(startDateOfPeriod, endDateOfPeriod) AS ascertainedPeriod,
                 ('[' || public.getStartDate(R.installedat, startDateOfPeriod) || ',' || COALESCE(public.getEndDate(R.uninstalledat, endDateOfPeriod) || ']', ')'))::public.intervalOfPeriod AS performedService
            FROM erp.installationRecords AS R
           WHERE R.installationID = FinstallationID
             AND (
               (R.uninstalledAt IS NULL AND R.installedAt <= endDateOfPeriod) OR
               (R.uninstalledAt >= startDateOfPeriod AND R.installedAt <= endDateOfPeriod)
              )
           ORDER BY R.installedAt
          ) AS performedServices
    LOOP
      -- Determinamos um período em que ocorreu a prestação do serviço.
      -- É com base neste valor que será realizada a cobrança da
      -- mensalidade deste item de contrato. Todavia, pode ocorrer mais
      -- de um período num mesmo mês de apuração se ocorrer mudança de
      -- equipamento de rastreamento e/ou de veículo. Para isto,
      -- adicionamos cada período apurado numa tabela para sabermos os
      -- detalhes.
      IF (lastPerformedPeriod IS NOT NULL) THEN
        -- Precisamos analisar sobreposição de períodos
        IF (lastPerformedPeriod && installationRecord.performedService) THEN
          -- Ocorreu sobreposição do período anterior com o novo período
          -- e precisamos desconsiderar do período anterior os dias que
          -- se sobrepõe ao deste novo período. Isto ocorre quando temos
          -- a troca do veículo em um item de contrato e o rastreador do
          -- novo veículo é instalado antes de ocorrer a retirada do
          -- rastreador do veículo antigo. Modificamos o período anterior
          -- primeiramente
          performedPeriod := lastPerformedPeriod
            - installationRecord.performedService
          ;

          IF (isempty(performedPeriod)) THEN
            -- O período foi anulado, então não cobramos
            UPDATE erp.ascertainedPeriodDetails
               SET periodEndedAt = endOfPerformedService,
                   duration = 0,
                   periodValue = 0.00
             WHERE periodStartedAt = lower(lastPerformedPeriod)
               AND periodEndedAt = upper(lastPerformedPeriod)
               AND ascertainedPeriodID = newAscertainedPeriodID;
          ELSE
            -- Calculamos os valores deste período modificado
            startOfPerformedService := lower(performedPeriod);
            endOfPerformedService   := upper(performedPeriod) - interval '1 day';
            daysInPerformedService  := DATE_PART('day',
                endOfPerformedService::timestamp - startOfPerformedService::timestamp
              ) + 1;
            IF (daysInPerformedService = daysInPeriod) THEN
              -- O serviço for prestado pelo mês inteiro
              grossValue := monthPrice;
            ELSE
              -- O serviço for prestado por uma parte do mês
              grossValue := ROUND(daysInPerformedService * dayPrice, 2);
            END IF;

            RAISE NOTICE 'Modificando o último período de serviço prestado para de % à % com % dias e custando %',
              to_char(startOfPerformedService, 'DD/MM/YYYY'),
              to_char(endOfPerformedService, 'DD/MM/YYYY'),
              daysInPerformedService,
              grossValue
            ;

            -- Modificamos no banco de dados
            UPDATE erp.ascertainedPeriodDetails
               SET periodEndedAt = endOfPerformedService,
                   duration = daysInPerformedService,
                   periodValue = grossValue
             WHERE periodStartedAt = lower(lastPerformedPeriod)
               AND periodEndedAt = upper(lastPerformedPeriod)
               AND ascertainedPeriodID = newAscertainedPeriodID;
          END IF;
        END IF;
      END IF;

      -- Calculamos os valores deste período
      startOfPerformedService := lower(installationRecord.performedService);
      endOfPerformedService   := upper(installationRecord.performedService);
      daysInPerformedService  := DATE_PART('day',
          endOfPerformedService::timestamp - startOfPerformedService::timestamp
        ) + 1;
      IF (daysInPerformedService = daysInPeriod) THEN
        -- O serviço for prestado pelo mês inteiro
        grossValue := monthPrice;
      ELSE
        -- O serviço for prestado por uma parte do mês
        grossValue := ROUND(daysInPerformedService * dayPrice, 2);
      END IF;

      RAISE NOTICE 'Prestado serviços de % à % com % dias e custando %',
        to_char(startOfPerformedService, 'DD/MM/YYYY'),
        to_char(endOfPerformedService, 'DD/MM/YYYY'),
        daysInPerformedService,
        grossValue
      ;

      -- Analisamos se este período pertence ao período já cobrados deste
      -- cliente
      billedBefore := false;
      IF (installation.lastDayOfBillingPeriod >= endDateOfPeriod) THEN
        -- O período inteiro não deve ser cobrado, pois já foi faturado
        -- faturado antecipadamente (provavelmente por se tratar de
        -- pagamento por carnê ou cartão de crédito parcelado e/ou por
        -- que o plano deste cliente é pré-pago). Desta forma indicamos
        -- que este trecho não deve ser cobrado (apesasr de computado)
        billedBefore := true;
      END IF;

      -- Inserimos este período apurado
      INSERT INTO erp.ascertainedPeriodDetails (ascertainedPeriodID,
        vehicleID, equipmentID, installationRecordID, periodStartedAt,
        periodEndedAt, duration, periodValue, billedBefore) VALUES
       (newAscertainedPeriodID,
        installationRecord.vehicleID,
        installationRecord.equipmentID,
        installationRecord.ID,
        startOfPerformedService,
        endOfPerformedService,
        daysInPerformedService,
        grossValue,
        billedBefore);

      -- Definimos este como sendo o último período processado
      lastPerformedPeriod := installationRecord.performedService;

      -- Agora determinamos quaisquer subsídios ou bonificações existentes
      -- neste período, de forma a concedermos os respectivos descontos.
      FOR subsidyRecord IN
        SELECT performedSubsidies.subsidyID AS ID,
               performedSubsidies.bonus,
               performedSubsidies.performedPeriod,
               performedSubsidies.subsidyPeriod * performedSubsidies.performedPeriod AS subsidedPeriod,
               performedSubsidies.discountType,
               performedSubsidies.discountValue
          FROM (
            SELECT S.subsidyID,
                   S.bonus,
                   public.intervalOfPeriod(startOfPerformedService, endOfPerformedService) AS performedPeriod,
                   ('[' || S.periodStartedAt || ',' || COALESCE(S.periodEndedAt || ']', ')'))::public.intervalOfPeriod AS subsidyPeriod,
                   S.discountType,
                   S.discountValue
              FROM erp.subsidies AS S
             WHERE S.installationID = FinstallationID
               AND (
                 (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                 (S.periodEndedAt >= startDateOfPeriod)
                )
             ORDER BY S.bonus DESC, S.periodStartedAt
            ) AS performedSubsidies
      LOOP
        -- Calculamos os valores deste desconto
        startOfSubsidy := lower(subsidyRecord.subsidedPeriod);
        endOfSubsidy   := upper(subsidyRecord.subsidedPeriod);
        daysInSubsidy  := DATE_PART('day',
            endOfSubsidy::timestamp - startOfSubsidy::timestamp
          ) + 1;
        IF subsidyRecord.bonus THEN
          -- Aplicamos 100% de desconto no período
          IF (daysInSubsidy = daysInPeriod) THEN
            -- O desconto foi concedido pelo mês inteiro
            discountValue := monthPrice;
          ELSE
            -- O desconto foi concedido por uma parte do mês
            discountValue := ROUND(daysInSubsidy * dayPrice, 2);
          END IF;
        ELSE
          -- Precisamos calcular o desconto em função do período
          IF (subsidyRecord.discountType = 1) THEN
            -- O desconto é um valor fixo em reais por dia
            discountValue :=
              ROUND(daysInSubsidy * subsidyRecord.discountValue, 2)
            ;
          ELSE
            -- O desconto é uma porcentagem do valor do período
            discountValue :=
              ROUND(
                (
                  (daysInSubsidy * dayPrice) *
                  subsidyRecord.discountValue / 100
                ),
                2
              )
            ;
          END IF;
        END IF;

        RAISE NOTICE 'Subsídio de % à % do tipo % e valor %',
          to_char(startOfSubsidy, 'DD/MM/YYYY'),
          to_char(endOfSubsidy, 'DD/MM/YYYY'),
          subsidyRecord.discountType,
          discountValue
        ;

        -- Inserimos os subsídios existente no mesmo período apurado
        INSERT INTO erp.ascertainedPeriodDetails (ascertainedPeriodID,
          vehicleID, equipmentID, installationRecordID, periodStartedAt,
          periodEndedAt, duration, periodValue, subsidyID) VALUES
         (newAscertainedPeriodID,
          installationRecord.vehicleID,
          installationRecord.equipmentID,
          installationRecord.ID,
          startOfPerformedService,
          endOfPerformedService,
          daysInPerformedService,
          discountValue,
          subsidyRecord.id);
      END LOOP;
    END LOOP;
  ELSE
    -- A cobrança sempre é integral, então não é preciso calcular os
    -- períodos de serviços prestados. Recupera os registros de
    -- equipamentos instalados, os quais, de alguma forma, pertencem ao
    -- período informado, considerando 1 mês desde o início do período e
    -- inputa o valor integral da mensalidade no último veículo em que
    -- tivemos um equipamento instalado
    FOR installationRecord IN
      SELECT DISTINCT ON (I.installationID)
               R.vehicleID,
               R.equipmentID
          FROM erp.installations AS I
         INNER JOIN erp.installationRecords AS R USING (installationID)
         WHERE I.installationID = FinstallationID
           AND (
                 (R.uninstalledAt IS NULL AND R.installedAt <= endDateOfPeriod) OR
                 (R.uninstalledAt >= startDateOfPeriod AND R.installedAt <= endDateOfPeriod)
               )
         ORDER BY I.installationID, R.uninstalledAt NULLS FIRST, R.installedAt DESC
    LOOP
      -- Devemos cobrar integralmente o mês, pois temos um rastreador
      -- instalado
      daysInPerformedService  := DATE_PART('day',
          endDateOfPeriod::timestamp - startDateOfPeriod::timestamp
        ) + 1;
      RAISE NOTICE 'Prestado serviços de % à % com % dias e custando %',
        to_char(startDateOfPeriod, 'DD/MM/YYYY'),
        to_char(endDateOfPeriod, 'DD/MM/YYYY'),
        daysInPerformedService,
        monthPrice
      ;

      -- Analisamos se este período pertence ao período já cobrados deste
      -- cliente
      billedBefore := false;
      IF (installation.lastDayOfBillingPeriod > endDateOfPeriod) THEN
        -- O período inteiro não deve ser cobrado, pois já foi faturado
        -- faturado antecipadamente (provavelmente por se tratar de
        -- pagamento por carnê ou cartão de crédito parcelado e/ou por
        -- que o plano deste cliente é pré-pago). Desta forma indicamos
        -- que este trecho não deve ser cobrado (apesasr de computado)
        billedBefore := true;
      END IF;

      -- Inserimos este período apurado
      INSERT INTO erp.ascertainedPeriodDetails (ascertainedPeriodID,
        vehicleID, equipmentID, installationRecordID, periodStartedAt,
        periodEndedAt, duration, periodValue, billedBefore) VALUES
       (newAscertainedPeriodID,
        installationRecord.vehicleID,
        installationRecord.equipmentID,
        installationRecord.ID,
        startDateOfPeriod,
        endDateOfPeriod,
        daysInPerformedService,
        monthPrice,
        billedBefore);

      -- Agora determinamos quaisquer subsídios ou bonificações existentes
      -- neste período, de forma a concedermos os respectivos descontos.
      FOR subsidyRecord IN
        SELECT performedSubsidies.subsidyID AS ID,
               performedSubsidies.bonus,
               performedSubsidies.performedPeriod,
               performedSubsidies.subsidyPeriod * performedSubsidies.performedPeriod AS subsidedPeriod,
               performedSubsidies.discountType,
               performedSubsidies.discountValue
          FROM (
            SELECT S.subsidyID,
                   S.bonus,
                   public.intervalOfPeriod(startDateOfPeriod, endDateOfPeriod) AS performedPeriod,
                   ('[' || S.periodStartedAt || ',' || COALESCE(S.periodEndedAt || ']', ')'))::public.intervalOfPeriod AS subsidyPeriod,
                   S.discountType,
                   S.discountValue
              FROM erp.subsidies AS S
             WHERE S.installationID = FinstallationID
               AND (
                 (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                 (S.periodEndedAt >= startDateOfPeriod)
                )
             ORDER BY S.bonus DESC, S.periodStartedAt
            ) AS performedSubsidies
      LOOP
        -- Calculamos os valores deste desconto
        startOfSubsidy := lower(subsidyRecord.subsidedPeriod);
        endOfSubsidy   := upper(subsidyRecord.subsidedPeriod);
        daysInSubsidy  := DATE_PART('day',
            endOfSubsidy::timestamp - startOfSubsidy::timestamp
          ) + 1;
        IF subsidyRecord.bonus THEN
          -- Aplicamos 100% de desconto no período
          IF (daysInSubsidy = daysInPeriod) THEN
            -- O desconto foi concedido pelo mês inteiro
            discountValue := monthPrice;
          ELSE
            -- O desconto foi concedido por uma parte do mês
            discountValue := ROUND(daysInSubsidy * dayPrice, 2);
          END IF;
        ELSE
          -- Precisamos calcular o desconto em função do período
          IF (subsidyRecord.discountType = 1) THEN
            -- O desconto é um valor fixo em reais por dia
            discountValue :=
              ROUND(daysInSubsidy * subsidyRecord.discountValue, 2)
            ;
          ELSE
            -- O desconto é uma porcentagem do valor do período
            discountValue :=
              ROUND(
                (
                  (daysInSubsidy * dayPrice) *
                  subsidyRecord.discountValue / 100
                ),
                2
              )
            ;
          END IF;
        END IF;

        RAISE NOTICE 'Subsídio de % à % do tipo % e valor %',
          to_char(startOfSubsidy, 'DD/MM/YYYY'),
          to_char(endOfSubsidy, 'DD/MM/YYYY'),
          subsidyRecord.discountType,
          discountValue
        ;

        -- Inserimos os subsídios existente no mesmo período apurado
        INSERT INTO erp.ascertainedPeriodDetails (ascertainedPeriodID,
          vehicleID, equipmentID, installationRecordID, periodStartedAt,
          periodEndedAt, duration, periodValue, subsidyID) VALUES
         (newAscertainedPeriodID,
          installationRecord.vehicleID,
          installationRecord.equipmentID,
          installationRecord.ID,
          startOfSubsidy,
          endOfSubsidy,
          daysInSubsidy,
          discountValue,
          subsidyRecord.id);
      END LOOP;
    END LOOP;
  END IF;

  -- Totalizamos os valores do período calculado
  UPDATE erp.ascertainedPeriods
     SET grossValue = ascertainedPeriod.grossValue,
         ascertainedDays = ascertainedPeriod.duration,
         discountValue = ascertainedPeriod.discountValue,
         finalValue = greatest(
             (ascertainedPeriod.grossValue - ascertainedPeriod.discountValue),
             0.00
           ),
         startDate = ascertainedPeriod.startedAt,
         endDate = ascertainedPeriod.endedAt
    FROM (
      SELECT CASE
               WHEN totaledPeriod.duration = daysInPeriod AND totaledPeriod.grossValue = 0.00 THEN 0.00
               WHEN totaledPeriod.duration = daysInPeriod AND totaledPeriod.grossValue > 0.00 THEN monthPrice
               ELSE totaledPeriod.grossValue
             END AS grossValue,
             totaledPeriod.duration,
             totaledPeriod.discountValue,
             totaledPeriod.startedAt,
             totaledPeriod.endedAt
        FROM (
          SELECT SUM(
                   CASE
                     WHEN P.billedBefore = TRUE THEN 0.00
                     WHEN P.subsidyID IS NULL THEN periodValue
                     ELSE 0.00
                   END
                 ) AS grossValue,
                 SUM(
                   CASE
                     WHEN P.subsidyID IS NULL THEN duration
                     ELSE 0.00
                   END
                 ) AS duration,
                 SUM(
                   CASE
                     WHEN P.subsidyID IS NULL THEN 0.00
                     ELSE P.periodValue
                   END
                 ) AS discountValue,
                 MIN(P.periodStartedAt) AS startedAt,
                 MAX(P.periodEndedAt) AS endedAt
            FROM erp.ascertainedPeriodDetails AS P
           WHERE P.ascertainedPeriodID = newAscertainedPeriodID
          ) AS totaledPeriod
      ) AS ascertainedPeriod
   WHERE ascertainedPeriodID = newAscertainedPeriodID;

  -- Lançamos este valor apurado nos registros de valores cobrados
  INSERT INTO erp.billings (contractorID, contractID, installationID,
         billingDate, name, value, ascertainedPeriodID, addMonthlyAutomatic,
         isMonthlyPayment, createdByUserID, updatedByUserID)
  SELECT A.contractorID,
         I.contractID,
         A.installationID,
         A.endDate,
         'Mensalidade ' || A.referenceMonthYear,
         A.finalValue,
         A.ascertainedPeriodID,
         TRUE,
         TRUE,
         FuserID,
         FuserID
    FROM erp.ascertainedPeriods AS A
   INNER JOIN erp.installations AS I USING (installationID)
   WHERE A.ascertainedPeriodID = newAscertainedPeriodID;

  -- Verifica se precisa atualiza a informação do período cobrado
  IF ( (installation.lastDayOfBillingPeriod IS NULL) OR
       (installation.lastDayOfBillingPeriod < startDateOfPeriod) ) THEN
    -- Ainda não foi cobrado nenhum período para este item de contrato
    -- e/ou o último período cobrado é inferior ao que calculamos agora,
    -- então lançamos a informação do último período já cobrado
    INSERT INTO erp.billedPeriods (contractorID, installationID,
           referenceMonthYear, startDate, endDate, monthPrice,
           grossValue, discountValue, finalValue)
    SELECT A.contractorID,
           A.installationID,
           A.referenceMonthYear,
           A.startDate,
           A.endDate,
           A.monthPrice,
           A.grossValue,
           A.discountValue,
           A.finalValue
      FROM erp.ascertainedPeriods AS A
     WHERE A.ascertainedPeriodID = newAscertainedPeriodID;
  END IF;

  -- Por último, informamos no item de contrato a data do último período
  -- apurado
  UPDATE erp.installations
     SET lastDayOfCalculatedPeriod = endDateOfPeriod
   WHERE installationID = FinstallationID;

  RETURN newAscertainedPeriodID;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém os valores referentes à rescisão contractual
-- ---------------------------------------------------------------------
-- Stored Procedure que determina a necessidade de cobrança de multa por
-- quebra de fidelidade e de valores de encerramento do contrato.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.contractTerminationValues(
  FinstallationID integer, FstartDateOfPeriod date, FendDateOfPeriod date)
RETURNS SETOF erp.performedServiceData AS
$$
DECLARE
  serviceData  erp.performedServiceData%rowtype;
  installation  record;
  billing  record;

  -- A data de encerramento do contrato
  FendDate  date;
  duration  integer;

  -- Cálculo da multa por quebra da fidelização
  monthsLeft  smallint;
  monthPrice  numeric(12,2);
  totalValue  numeric(12,2);
  fineValue   numeric(12,2);
BEGIN
  -- Recuperamos as informações do item de contrato sendo analisado
  RAISE NOTICE 'Recuperando informações do item de contrato';
  SELECT INTO installation
         I.contractorID,
         I.contractID,
         I.installationNumber AS number,
         P.loyaltyPeriod,
         P.loyaltyFine,
         I.monthPrice,
         I.notChargeLoyaltyBreak,
         I.startDate,
         I.endDate
    FROM erp.installations AS I
   INNER JOIN erp.contracts AS C ON (C.contractID = I.contractID)
   INNER JOIN erp.plans AS P ON (P.planID = I.planID)
   WHERE I.installationID = FinstallationID;

  RAISE NOTICE 'Número do item de contrato: %', installation.number;
  RAISE NOTICE 'Data do início: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');

  -- Determina a ocorrência de término do contrato e duração
  FendDate := CASE WHEN installation.endDate IS NULL THEN FendDateOfPeriod ELSE installation.endDate END;
  duration := EXTRACT(year FROM age(FendDate, installation.startDate))*12 + EXTRACT(month FROM age(FendDate, installation.startDate));
  RAISE NOTICE 'Data de término: %', TO_CHAR(FendDate, 'DD/MM/YYYY');
  RAISE NOTICE 'O item de contrato nº % teve duração de % meses',
    installation.number,
    duration;

  -- Caso o serviço tenha sido contratado com fidelização será
  -- necessário analisar a cobrança de multa
  IF ((installation.loyaltyPeriod > 0) AND (installation.notChargeLoyaltyBreak = FALSE)) THEN
    -- Temos um período de fidelidade, então analisa a questão da multa
    IF ((FendDate >= FstartDateOfPeriod) AND (FendDate <= FendDateOfPeriod)) THEN
      -- O encerramento ocorreu dentro do período apurado, então
      -- analiza se o tempo de duração efetiva do contrato foi
      -- inferior ao período mínimo de fidelização
      IF (duration < installation.loyaltyPeriod) THEN
        -- Precisamos cobrar a multa. A cobrança é proporcional ao
        -- período que falta para completar o período da fidelização,
        -- então determinamos quantos meses ainda faltam
        monthsLeft := installation.loyaltyPeriod - duration;
        RAISE NOTICE 'Faltaram % meses para o término do período de fidelidade',
          monthsLeft;

        -- Calculamos o valor total que é a quantidade de meses que
        -- faltam vezes o valor da mensalidade vigente
        totalValue := ROUND(monthsLeft * installation.monthPrice, 2);

        -- A multa é uma porcentagem deste valor
        fineValue := ROUND(totalValue * installation.loyaltyFine / 100, 2);

        -- Lançamos esta multa para ser cobrada
        RAISE NOTICE 'Inserida uma multa de % no item de contrato nº %',
          fineValue,
          installation.number;
        serviceData.referenceMonthYear := to_char(FstartDateOfPeriod, 'MM/YYYY');
        serviceData.startDateOfPeriod  := FstartDateOfPeriod;
        serviceData.endDateOfPeriod    := FendDateOfPeriod;
        serviceData.name               := 'Quebra do período fidelidade';
        serviceData.value              := ROUND(fineValue, 2);

        RETURN NEXT serviceData;
      END IF;
    END IF;
  END IF;

  -- Retorna valores a serem cobrados por término do contrato
  FOR billing IN
    SELECT C.name,
           C.chargeValue AS value
      FROM erp.contractCharges AS C
     INNER JOIN erp.billingtypes AS T USING (billingTypeID)
     WHERE C.contractID = installation.contractID
       AND T.billingMomentID IN (3, 4)
  LOOP
    -- Lançamos esta cobrança
    serviceData.referenceMonthYear := to_char(FstartDateOfPeriod, 'MM/YYYY');
    serviceData.startDateOfPeriod  := FstartDateOfPeriod;
    serviceData.endDateOfPeriod    := FendDateOfPeriod;
    serviceData.name               := billing.name;
    serviceData.value              := billing.value;

    RETURN NEXT serviceData;
  END LOOP;
END;
$$ LANGUAGE 'plpgsql';
