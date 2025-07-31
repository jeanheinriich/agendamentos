-- Acrescentamos a informação de não cobrar multa por fidelidade em um
-- contrato
ALTER TABLE erp.contracts
  ADD COLUMN notChargeLoyaltyBreak boolean NOT NULL DEFAULT false;

ALTER TABLE erp.subsidiaries
  ALTER COLUMN district DROP NOT NULL;

-- Gatilho para lidar com modificações no contrato
CREATE OR REPLACE FUNCTION erp.contractTransaction()
RETURNS trigger AS $BODY$
DECLARE
  amount  integer;
BEGIN
  -- Faz a atualização de itens de contrato em função de modificações à
  -- nível de contrato. Faz uso da variável especial TG_OP para verificar
  -- a operação executada e de TG_WHEN para determinar o instante em que
  -- isto ocorre.
  IF (TG_OP = 'INSERT') THEN

    -- Retornamos o novo contrato
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    IF (TG_WHEN = 'AFTER') THEN
      -- Verifica se foi marcado para não cobrar multa por quebra de
      -- contrato
      IF (OLD.notChargeLoyaltyBreak <> NEW.notChargeLoyaltyBreak) THEN
        -- Precisamos forçar para que todos os demais itens deste
        -- contrato cobrem (ou não) a multa por quebra do período de
        -- fidelidade em função do que está estipulado no contrato
        UPDATE erp.installations
           SET notChargeLoyaltyBreak = NEW.notChargeLoyaltyBreak
         WHERE contractID = OLD.contractID;
      END IF;
    END IF;

    -- Retornamos o novo contrato
    RETURN NEW;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER contractsTransactionTriggerAfter
  AFTER INSERT OR UPDATE ON erp.contracts
  FOR EACH ROW EXECUTE PROCEDURE erp.contractTransaction();

-- Atualizamos as stored procedures envolvidas

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
         C.notChargeLoyaltyBreak AS notChargeLoyaltyBreakInAllContract,
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
  IF ((installation.loyaltyPeriod > 0) AND (installation.notChargeLoyaltyBreak = FALSE) AND (installation.notChargeLoyaltyBreakInAllContract = FALSE)) THEN
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


-- ---------------------------------------------------------------------
-- Inicia o fechamento dos valores de cada item de contrato
-- ---------------------------------------------------------------------
-- Função que determina os valores a serem cobrados de cada item de
-- contrato e gera as faturas com os valores.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.startMonthlyCalculations(FstartDate date,
  FcontractorID integer, Fcooperative boolean, FuserID integer) RETURNS boolean AS
$$
DECLARE
  -- A data de término do período a ser faturado
  FendDate  date;

  -- Os dados do item de contrato
  installation  record;

  -- Os dados da tarifa a ser cobrada
  contractCharge  record;

  -- O último cliente
  lastCustomerID  integer;
  lastSubsidiaryID  integer;

  -- O último contrato
  lastContractID  integer;

  -- O complemento do próximo mês para determinar o dia de vencimento
  nextMonth  integer;
  nextYear   integer;

  -- O ID da apuração
  FascertainedPeriodID  integer;

  -- Os manipuladores da fatura
  createNewInvoice  boolean;
  newInvoiceID  integer;

  -- A informação da tarifa do meio de pagamento
  lastDefinedMethod  integer;
  basicFare  numeric(12,2);

  -- Cálculo da multa por quebra da fidelização
  monthsLeft  smallint;
  monthPrice  numeric(12,2);
  totalValue  numeric(12,2);
  fineValue   numeric(12,2);

  hasInstallations  boolean;
  invoiceValue  numeric(12,2);

  amountOfRemainingBillings  integer;
  continueWithBillingProcess  boolean;
BEGIN
  -- Inicializamos as variáveis de processamento
  FendDate := FstartDate + interval '1 month' - interval '1 day';
  nextMonth := to_char(FstartDate + interval '1 month', 'MM')::integer;
  nextYear  := to_char(FstartDate + interval '1 month', 'YYYY')::integer;
  lastCustomerID := 0;
  lastSubsidiaryID := 0;
  lastContractID := 0;
  lastDefinedMethod := 0;
  hasInstallations := false;

  -- Recupera as informações de itens de contrato habilitados para
  -- cobrança, e também as informações pertinentes do contrato ao qual
  -- ele pertence
  FOR installation IN
    SELECT I.installationID AS id,
           I.installationNumber AS number,
           I.monthprice,
           C.contractID,
           C.active AS contractActive,
           erp.getContractNumber(C.createdat) AS contractNumber,
           SUB.numberofmonths,
           C.customerID,
           C.customerPayerID,
           C.subsidiaryPayerID,
           C.unifyBilling,
           C.chargeAnyTariffs,
           C.planID,
           CASE
             WHEN P.dueDateOnlyInWorkingdays THEN erp.getNextWorkDay(public.buidDate(nextYear, nextMonth, D.day), S.cityid)
             ELSE public.buidDate(nextYear, nextMonth, D.day)
           END AS dueDate,
           P.loyaltyPeriod,
           P.loyaltyFine,
           I.lastDayOfBillingPeriod,
           C.notChargeLoyaltyBreak AS notChargeLoyaltyBreakInAllContract,
           I.notChargeLoyaltyBreak,
           I.startDate,
           I.endDate,
           CASE
             WHEN I.endDate IS NOT NULL THEN EXTRACT(year FROM age(I.endDate, I.startDate))*12 + EXTRACT(month FROM age(I.endDate, I.startDate))
             ELSE 0
           END AS duration,
           CASE
             WHEN C.prepaid THEN C2.paymentMethodID
             ELSE C1.paymentMethodID
           END AS paymentMethodID,
           CASE
             WHEN C.prepaid THEN C2.definedMethodID
             ELSE C1.definedMethodID
           END AS definedMethodID,
           CASE
             WHEN C.prepaid THEN C2.name
             ELSE C1.name
           END AS paymentMethodName
      FROM erp.installations AS I
     INNER JOIN erp.contracts AS C ON (I.contractID = C.contractID)
     INNER JOIN erp.subscriptionPlans AS SUB ON (C.subscriptionPlanID = SUB.subscriptionPlanID)
     INNER JOIN erp.entities AS CLI ON (C.customerID = CLI.entityID)
     INNER JOIN erp.entitiesTypes AS ET ON (CLI.entityTypeID = ET.entityTypeID)
     INNER JOIN erp.plans AS P ON (I.planID = P.planID)
     INNER JOIN erp.dueDays AS D ON (C.dueDayID = D.dueDayID)
     INNER JOIN erp.subsidiaries AS S ON (C.subsidiaryID = S.subsidiaryID)
     INNER JOIN erp.paymentConditions AS C1 ON (C.paymentConditionID = C1.paymentConditionID)
     INNER JOIN erp.definedMethods AS D1 ON (C1.definedMethodID = D1.definedMethodID)
     INNER JOIN erp.paymentConditions AS C2 ON (C.additionalPaymentConditionID = C2.paymentConditionID)
     INNER JOIN erp.definedMethods AS D2 ON (C2.definedMethodID = D2.definedMethodID)
     WHERE I.contractorID = FcontractorID
       AND C.deleted = false
       -- AND C.active = true
       AND I.startDate IS NOT NULL
       AND I.startDate <= FendDate
       -- AND (I.endDate IS NULL OR I.endDate >= FstartDate)
       AND ET.cooperative = Fcooperative
     ORDER BY C.customerPayerid, C.subsidiaryPayerid, C.unifybilling, C.contractID, I.installationid
  LOOP
    -- Indicamos que temos itens de contrato
    hasInstallations := true;
    -- RAISE NOTICE 'Processando o item de contrato nº %', installation.number;

    -- Analisamos se temos períodos ainda a serem apurados para este
    -- item de contrato
    IF ((installation.endDate IS NULL) OR (installation.endDate >= FstartDate)) THEN
      -- Apuramos os valores do período para este item de contrato
      SELECT INTO FascertainedPeriodID
             erp.performedServiceInPeriod(FStartDate, installation.id, FuserID, NULL);
    ELSE
      -- Indica que não temos períodos apurados
      FascertainedPeriodID := 0;
    END IF;

    continueWithBillingProcess := false;
    IF (FascertainedPeriodID = 0) THEN
      -- Verificamos se temos algum valor a ser cobrado
      SELECT count(*) INTO amountOfRemainingBillings
        FROM erp.billings
       WHERE billingDate <= FendDate
         AND billings.installationID = installation.id
         AND billings.contractorID = FcontractorID
         AND invoiced = FALSE
         AND invoiceID IS NULL;
      IF (amountOfRemainingBillings > 0) THEN
        continueWithBillingProcess := true;
      END IF;

      -- RAISE NOTICE 'Não temos mensalidade para o item de contrato nº %', installation.number;
    ELSE
      continueWithBillingProcess := true;
      -- RAISE NOTICE 'Apurado valores da mensalidade para o item de contrato nº % com ID %',
      --   installation.number,
      --   FascertainedPeriodID;
    END IF;

    IF (continueWithBillingProcess) THEN
      -- Analisa a criação de uma nova fatura
      createNewInvoice := false;
      IF (installation.unifyBilling) THEN
        -- Devemos unificar as cobranças em uma única fatura para todos
        -- os itens de contrato de um mesmo cliente, independente do
        -- contrato ao qual pertencem, então analisa se estamos no mesmo
        -- cliente
        -- RAISE NOTICE 'lastCustomerID % <> % installation.contractID',
        --   lastCustomerID,
        --   installation.contractID;
        IF ( (lastCustomerID <> installation.customerPayerID) AND
             (lastSubsidiaryID <> installation.subsidiaryPayerID) ) THEN
          -- Criamos uma nova fatura, pois mudamos de cliente pagador
          createNewInvoice := true;
          -- RAISE NOTICE 'Precisamos criar uma fatura para o cliente nº %', installation.customerPayerID;
        END IF;
      ELSE
        -- Criamos uma nova fatura a cada novo item de contrato
        createNewInvoice := true;
        -- RAISE NOTICE 'Precisamos criar uma fatura para o item de contrato nº %', installation.number;
      END IF;
      lastCustomerID := installation.customerPayerID;
      lastSubsidiaryID := installation.subsidiaryPayerID;
      
      IF (createNewInvoice) THEN
        -- Precisamos criar uma nova fatura
        INSERT INTO erp.invoices (contractorID, customerID, subsidiaryID,
          invoiceDate, referenceMonthYear, dueDate, paymentMethodID,
          definedMethodID, underAnalysis) VALUES (FcontractorID,
          installation.customerPayerID, installation.subsidiaryPayerID,
          CURRENT_DATE, to_char(FstartDate, 'MM/YYYY'),
          installation.dueDate, installation.paymentMethodID,
          installation.definedMethodID, TRUE)
        RETURNING invoiceID INTO newInvoiceID;
        -- RAISE NOTICE 'Criada a fatura nº %', newInvoiceID;
      END IF;

      -- Atualizamos a informação do número da fatura no período cobrado,
      -- se existir
      UPDATE erp.billedPeriods
         SET invoiceID = newInvoiceID
       WHERE installationID = installation.id
         AND invoiceID IS NULL;

      -- Caso o serviço tenha sido contratado com fidelização será
      -- necessário analisar a cobrança de multa
      IF ((installation.loyaltyPeriod > 0) AND (installation.notChargeLoyaltyBreak = FALSE) AND (installation.notChargeLoyaltyBreakInAllContract = FALSE)) THEN
        -- Temos um período de fidelidade, então verifica se o item de
        -- contrato já foi encerrado
        IF (installation.endDate IS NOT NULL) THEN
          -- O item de contrato foi encerrado, então verificamos se ele
          -- ocorreu dentro do período de apuração
          IF ((installation.endDate >= FStartDate) AND (installation.endDate <= FendDate)) THEN
            -- O encerramento ocorreu dentro do período apurado, então
            -- analiza se o tempo de duração efetiva do contrato foi
            -- inferior ao período mínimo de fidelização
            IF (installation.duration < installation.loyaltyPeriod) THEN
              -- Precisamos cobrar a multa. A cobrança é proporcional ao
              -- período que falta para completar o período da fidelização,
              -- então determinamos quantos meses ainda faltam
              monthsLeft := installation.loyaltyPeriod - installation.duration;
              -- RAISE NOTICE 'O item de contrato nº % teve duração de % meses',
              --   installation.number,
              --   monthsLeft;

              -- Determinamos a tarifa vigente no início deste período
              SELECT INTO monthPrice
                     readjustment.monthPrice
                FROM erp.readjustmentsOnInstallations AS readjustment
               WHERE readjustment.installationID = installation.id
                 AND readjustment.readjustedAt <= FstartDate
               ORDER BY readjustment.readjustedAt DESC
               FETCH FIRST ROW ONLY;
              IF NOT FOUND THEN
                -- Caso o item de contrato tenha sido criado neste mês,
                -- a consulta acima não irá retornar um valor. Neste
                -- caso, considera o mês corrente
                SELECT INTO monthPrice
                       readjustment.monthPrice
                  FROM erp.readjustmentsOnInstallations AS readjustment
                 WHERE readjustment.installationID = installation.id
                   AND readjustment.readjustedAt < (FstartDate + interval '1 month')
                 ORDER BY readjustment.readjustedAt DESC
                 FETCH FIRST ROW ONLY;
                IF NOT FOUND THEN
                  -- Disparamos uma exceção
                  RAISE EXCEPTION 'Não foi possível obter a mensalidade para o item de contrato % para o período com início em %',
                    installation.number,
                    FstartDate
                  USING HINT = 'Por favor, verifique os valores da mensalidade para este item de contrato';
                END IF;
              END IF;
              -- RAISE NOTICE 'A mensalidade é %', monthPrice;

              -- Calculamos o valor total que é a quantidade de meses que
              -- faltam vezes o valor da mensalidade vigente
              totalValue := ROUND(monthsLeft * monthPrice, 2);

              -- A multa é uma porcentagem deste valor
              fineValue := ROUND(totalValue * installation.loyaltyFine / 100, 2);

              -- Lançamos esta multa nos registros de valores cobrados
              INSERT INTO erp.billings (contractorID, contractID,
                     installationID, billingDate, name, value, invoiceID,
                     addMonthlyAutomatic, createdByUserID, updatedByUserID)
              VALUES (FcontractorID, installation.contractID, installation.id,
                      FendDate, 'Quebra do período fidelidade',
                      fineValue, newInvoiceID, true, FuserID, FuserID);
              RAISE NOTICE 'Inserida uma multa de % no item de contrato nº %',
                fineValue,
                installation.number;
            END IF;
          END IF;
        END IF;
      END IF;

      -- Precisamos incluir tarifas de valores cobrados mensalmente
      -- presentes no plano
      FOR contractCharge IN
        SELECT contractCharges.contractChargeID,
               contractCharges.name,
               contractCharges.chargeValue,
               billingTypes.ratePerEquipment
          FROM erp.contractCharges
         INNER JOIN erp.billingTypes USING (billingTypeID)
         WHERE contractCharges.contractID = installation.contractID
           AND billingTypes.billingFormatID = 3
           AND billingTypes.billingMomentID = 5
      LOOP
        IF (contractCharge.ratePerEquipment) THEN
          -- Lançamos esta tarifa nos registros de valores cobrados para
          -- cada item de contrato
          INSERT INTO erp.billings (contractorID, contractID,
                 installationID, billingDate, name, value, invoiceID,
                 addMonthlyAutomatic, isMonthlyPayment, createdByUserID,
                 updatedByUserID)
          VALUES (FcontractorID, installation.contractID, installation.id,
                  FendDate, contractCharge.name, contractCharge.chargeValue,
                  newInvoiceID, TRUE, TRUE, FuserID, FuserID);
          RAISE NOTICE 'Inserida a cobrança de % no valor de % no item de contrato nº %',
            contractCharge.name,
            contractCharge.chargeValue,
            installation.number;
        ELSE
          -- Lançamos esta tarifa nos registros de valores cobrados uma
          -- única vez para este contrato
          IF (lastContractID <> installation.contractID) THEN
            INSERT INTO erp.billings (contractorID, contractID,
                   installationID, billingDate, name, value, invoiceID,
                   addMonthlyAutomatic, isMonthlyPayment, createdByUserID,
                   updatedByUserID)
            VALUES (FcontractorID, installation.contractID, NULL, FendDate,
                    contractCharge.name, contractCharge.chargeValue,
                    newInvoiceID, TRUE, TRUE, FuserID, FuserID);
            RAISE NOTICE 'Inserida a cobrança de % no valor de % no contato nº %',
              contractCharge.name,
              contractCharge.chargeValue,
              installation.contractNumber;

            lastContractID := installation.contractID;
          END IF;
        END IF;
      END LOOP;

      -- Precisamos incluir o número da fatura em todos os lançamentos
      -- deste item de contrato que estejam em aberto (ainda não foram
      -- cobrados). Serão incluídos também os valores renegociados e
      -- abonados, mas eles não serão considerados para efeito de
      -- cálculo. Isto é feito para que seus valores sejam ocultos
      -- quando o fechamento for concluído
      UPDATE erp.billings
         SET invoiceID = newInvoiceID
       WHERE billingDate <= FendDate
         AND billings.installationID = installation.id
         AND billings.contractorID = FcontractorID
         AND invoiced = FALSE
         AND invoiceID IS NULL;
    END IF;
  END LOOP;

  IF (NOT hasInstallations) THEN
    RAISE NOTICE 'Não temos itens de contrato habilitados para este período.';

    RETURN false;
  END IF;

  -- Por último, determinamos os valores totais de cada nota com base
  -- nos valores calculados
  UPDATE erp.invoices
     SET invoiceValue = ROUND(billings.total, 2)
    FROM (
      SELECT invoiceID,
             SUM(value) as total
        FROM erp.billings
       WHERE granted = false
         AND renegotiated = false
         AND invoiced = false
         AND contractorID = FcontractorID
      GROUP BY invoiceID
     ) AS billings
   WHERE invoices.invoiceID = billings.invoiceID
     AND invoices.contractorID = FcontractorID;

  -- Indica que tudo deu certo
  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

CREATE OR REPLACE FUNCTION erp.getInstallationsData(FcontractID integer, FincludeFinish boolean)
RETURNS SETOF erp.installationData AS
$$
DECLARE
  installationData  erp.installationData%rowtype;
  row          record;
  query        varchar;
  finishFilter varchar;
  vehicleData  record;
BEGIN
  IF (FcontractID IS NULL) THEN
    FcontractID = 0;
  END IF;
  IF (FincludeFinish IS NULL) THEN
    FincludeFinish = FALSE;
  END IF;

  finishFilter := '';
  IF (NOT FincludeFinish) THEN
    finishFilter := 'AND customer.blocked = false '
      || 'AND installations.endDate IS NULL '
      || 'AND subsidiary.blocked = false '
      || 'AND contracts.active = true';
  END IF;

  -- Monta a consulta
  query := format('SELECT contracts.contractID,
                          contractor.blocked AS contractorBlocked,
                          contracts.signaturedate,
                          contracts.startTermAfterInstallation,
                          contracts.active AS contractActive,
                          installations.installationID,
                          installations.installationNumber,
                          installations.startDate,
                          installations.endDate,
                          CASE
                            WHEN installations.endDate IS NULL THEN FALSE
                            ELSE TRUE
                          END AS finish,
                          vehicle.vehicleID,
                          vehicle.plate,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID
                                     AND uninstalledAt IS NULL) AS tracked,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID) AS containsTrackingData
                     FROM erp.contracts
                    INNER JOIN erp.entities AS contractor ON (contracts.contractorID = contractor.entityID)
                    INNER JOIN erp.entities AS customer ON (contracts.customerID = customer.entityID)
                    INNER JOIN erp.subsidiaries AS subsidiary ON (contracts.subsidiaryID = subsidiary.subsidiaryID)
                    INNER JOIN erp.installations ON (contracts.contractID = installations.contractID)
                    INNER JOIN erp.getMostRecentVehicleOnInstallation(contracts.contractorID, installations.installationid) AS vehicle ON (installations.installationID = vehicle.installationID)
                    WHERE contracts.contractID = %s
                      AND contracts.deleted = false
                      AND customer.deleted = false
                      AND subsidiary.deleted = false %s
                    ORDER BY 8 NULLS FIRST, 9 ASC, 7',
                  FContractID, finishFilter);
  FOR row IN EXECUTE query
  LOOP
    installationData.contractID           := row.contractID;
    installationData.installationID       := row.installationID;
    installationData.installationNumber   := row.installationNumber;
    installationData.startDate            := row.startDate;
    installationData.endDate              := row.endDate;
    installationData.noTracker            := NOT row.tracked;
    installationData.containsTrackingData := row.containsTrackingData;
    installationData.finish               := row.finish;
    installationData.vehicleID            := row.vehicleID;
    installationData.plate                := row.plate;

    RETURN NEXT installationData;
  END loop;
END
$$
LANGUAGE 'plpgsql';
