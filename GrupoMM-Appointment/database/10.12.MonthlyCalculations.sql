-- =====================================================================
-- Fechamentos mensais
-- =====================================================================
-- O controle das operações de fechamentos realizadas mensalmente para
-- calcular os valores a serem cobrados de cada cliente.
-- =====================================================================

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
      -- RAISE NOTICE 'Número da parcela: %', parcelNumber;
      -- RAISE NOTICE 'Data de referência: %', TO_CHAR(referenceDate, 'DD/MM/YYYY');

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
         INNER JOIN erp.plans AS P ON (C.planID = P.planID)
         WHERE C.deleted = false
           AND C.active = true
           AND I.endDate IS NULL
           AND I.installationID = FinstallationID
         ORDER BY C.customerPayerid, C.subsidiaryPayerid, C.unifybilling, C.contractID, I.installationid
      LOOP
        -- Para cada parcela sendo calculada, analisamos se devemos ou
        -- não cobrar o período para cada um dos itens de contrato
        -- informados, de forma que conseguimos construir o valor final
        -- corretamente
        -- RAISE NOTICE 'Número do item de contrato: %', installation.number;
        -- RAISE NOTICE 'Data da assinatura do contrato: %', TO_CHAR(installation.signatureDate, 'DD/MM/YYYY');
        -- RAISE NOTICE 'Data do início do item de contrato: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');
        -- RAISE NOTICE 'Iniciar cobrança após instalação: %', CASE WHEN installation.startTermAfterInstallation THEN 'Sim' ELSE 'Não' END;
        -- RAISE NOTICE 'Cobrar proporcional ao serviço prestado: %', CASE WHEN installation.prorata THEN 'Sim' ELSE 'Não' END;

        -- Determinamos o período de cobrança em um mês
        startDateOfPeriod := referenceDate;
        endDateOfPeriod := startDateOfPeriod + interval '1 month' - interval '1 day';
        -- RAISE NOTICE 'Período de % à %', startDateOfPeriod, endDateOfPeriod;

        -- Determinamos à partir de qual data devemos cobrar
        IF (installation.prorata) THEN
          -- Devemos cobrar proporcionalmente, então determinamos quando
          -- isto ocorre
          IF (installation.startTermAfterInstallation) THEN
            IF (installation.startDate IS NULL) THEN
              -- Como a instalação não ocorreu ainda, então consideramos
              -- o início do período mesmo
              -- RAISE NOTICE 'Instalação não ocorreu, considerando o período inteiro';
              startDateOfBillingPeriod := startDateOfPeriod;
            ELSE
              -- Verificamos se o início do item de contrato ocorreu
              -- durante o período que estamos analisado
              IF (installation.startDate >= startDateOfPeriod) THEN
                -- Consideramos a data de instalação
                -- RAISE NOTICE 'Consideramos a data de instalação';
                startDateOfBillingPeriod := installation.startDate;
              ELSE
                -- Como a instalação se deu antes do início do período que
                -- iremos cobrar, então consideramos o início do período
                -- mesmo
                -- RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          ELSE
            IF (installation.signatureDate IS NULL) THEN
              -- Como o contrato não foi assinado ainda, então consideramos
              -- o início do período mesmo
              -- RAISE NOTICE 'Contrato não foi assinado, considerando o período inteiro';
              startDateOfBillingPeriod := baseDate;
            ELSE
              -- Verificamos se a assintatura ocorreu durante o período
              -- sendo analisado
              IF (installation.signatureDate >= startDateOfPeriod) THEN
                -- Consideramos a data de assinatura
                -- RAISE NOTICE 'Consideramos a data de assinatura do contrato';
                startDateOfBillingPeriod := installation.signatureDate;
              ELSE
                -- Como a assinatura do contrato se deu antes do início do
                -- período que iremos cobrar, então consideramos o início
                -- do período mesmo
                -- RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          END IF;
        ELSE
          -- Devemos cobrar integralmente, então o início se dá sempre no
          -- início do período apurado
          -- RAISE NOTICE 'Consideramos o período inteiro';
          startDateOfBillingPeriod := startDateOfPeriod;
        END IF;

        -- Verificamos se já foram realizadas cobranças de períodos
        -- neste item de contrato
        IF (installation.lastDayOfBillingPeriod IS NOT NULL) THEN
          -- Precisamos levar em consideração também o último período já
          -- cobrado se ele for superior ao período que estamos cobrando
          IF ((installation.lastDayOfBillingPeriod + interval '1 day') > startDateOfBillingPeriod) THEN
            startDateOfBillingPeriod := installation.lastDayOfBillingPeriod + interval '1 day';
            -- RAISE NOTICE 'Consideramos o período iniciando em %', startDateOfBillingPeriod;
          END IF;
        END IF;

        -- Calculamos a quantidade de dias no período
        daysInPeriod := DATE_PART('day',
            endDateOfPeriod::timestamp - startDateOfPeriod::timestamp
          ) + 1;
        -- RAISE NOTICE 'Este período possui % dias', daysInPeriod;

        -- Calculamos o valor diário com base na mensalidade
        dayPrice = installation.monthPrice / daysInPeriod;
        -- RAISE NOTICE 'O valor diário é %', ROUND(dayPrice, 2);

        -- Verificamos se precisamos cobrar algum período nesta parcela
        -- para esta instalação
        IF (startDateOfBillingPeriod <= endDateOfPeriod) THEN
          IF (installation.prorata) THEN
            IF (startDateOfBillingPeriod = startDateOfPeriod) THEN
              -- Cobramos o valor integral da mensalidade
              monthlyValue := installation.monthPrice;
              -- RAISE NOTICE 'Cobrando valor integral da mensalidade';
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
            -- RAISE NOTICE 'Cobrando valor integral da mensalidade';
          END IF;

          IF (monthlyValue > 0.00) THEN
            -- Acrescentamos esta valor a ser cobrado
            -- RAISE NOTICE 'O valor da mensalidade calculada é %', ROUND(monthlyValue, 2);
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
                         CASE WHEN S.discountType = 1 THEN
                           S.discountValue / EXTRACT(DAY FROM (DATE_TRUNC('MONTH', startDateOfBillingPeriod + INTERVAL '1 MONTH') - INTERVAL '1 DAY'))
                         ELSE
                           S.discountValue
                         END AS discountValue
                    FROM erp.subsidies AS S
                   WHERE S.installationID = FinstallationID
                     AND (
                       (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                       (S.periodEndedAt >= startDateOfBillingPeriod)
                      )
                   ORDER BY S.bonus DESC, S.periodStartedAt
                  ) AS performedSubsidies
            LOOP
              -- RAISE NOTICE 'Subsídio de % à %', lower(subsidyRecord.subsidedPeriod), upper(subsidyRecord.subsidedPeriod);
              -- RAISE NOTICE 'Período de serviço prestado de % à %', lower(subsidyRecord.performedPeriod), upper(subsidyRecord.performedPeriod);
              -- RAISE NOTICE 'Tipo de desconto: %', subsidyRecord.discountType;
              -- RAISE NOTICE 'Valor do desconto: %', subsidyRecord.discountValue;
              -- Calculamos os valores deste desconto
              startOfSubsidy := lower(subsidyRecord.subsidedPeriod);
              endOfSubsidy   := upper(subsidyRecord.subsidedPeriod);
              IF (startOfSubsidy IS NOT NULL) THEN
                daysInSubsidy  := DATE_PART('day',
                    endOfSubsidy::timestamp - startOfSubsidy::timestamp
                  ) + 1;
                -- RAISE NOTICE 'Período com % dias', daysInSubsidy;
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

                -- RAISE NOTICE 'Adicionando desconto no item de contrato';
                serviceData.name  := format(
                  'Desconto de %s à %s',
                  TO_CHAR(startOfSubsidy, 'DD/MM/YYYY'),
                  TO_CHAR(endOfSubsidy, 'DD/MM/YYYY')
                );
                serviceData.value := ROUND((0 - discountValue), 2);

                RETURN NEXT serviceData;
              END IF;
            END LOOP;

            -- Agora analisamos quaisquer outros valores de cobranças
            -- mensais presentes no contrato e que precisam ser
            -- computados
            FOR monthlyFeesRecord IN
              SELECT B.name,
                     C.chargeValue AS value
                FROM erp.contractCharges AS C
               INNER JOIN erp.billingTypes AS B USING (billingTypeID)
               WHERE C.contractID = installation.contractid
                 AND B.billingMoments @> array[5]
                 AND B.inAttendance = false
                 AND B.ratePerEquipment = true
            LOOP
              -- RAISE NOTICE 'Adicionando a cobrança de % do item de contrato para ser cobrada', monthlyFeesRecord.name;
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

-- SELECT * FROM erp.toBePerformedService(35, '2022-03-01', 3);

-- ---------------------------------------------------------------------
-- Calcula os valores referentes ao serviço prestado em um item de
-- contrato
-- ---------------------------------------------------------------------
-- Stored Procedure que determina os valores a serem cobrados em um item
-- de contrato em função dos registros de instalação, calculando os
-- perídos para os quais efetivamente ocorreu a prestação do serviço e o
-- respectivo valor computado, e também consideração bonificações e/ou
-- subsídios definidos, calculando então o valor final.
-- ---------------------------------------------------------------------
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
  -- RAISE NOTICE 'Recuperando informações do item de contrato';
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
   INNER JOIN erp.plans AS P ON (C.planID = P.planID)
   WHERE I.installationID = FinstallationID;

  -- RAISE NOTICE 'Número do item de contrato: %', installation.number;
  -- RAISE NOTICE 'Data da assinatura do contrato: %', TO_CHAR(installation.signatureDate, 'DD/MM/YYYY');
  -- RAISE NOTICE 'Data do início do item de contrato: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');
  -- RAISE NOTICE 'Iniciar cobrança após instalação: %', CASE WHEN installation.startTermAfterInstallation THEN 'Sim' ELSE 'Não' END;
  -- RAISE NOTICE 'Cobrar proporcional ao serviço prestado: %', CASE WHEN installation.prorata THEN 'Sim' ELSE 'Não' END;
  lastDayOfCalculatedPeriod := installation.lastDayOfCalculatedPeriod;
  -- RAISE NOTICE 'Término do último período apurado: %', CASE WHEN lastDayOfCalculatedPeriod IS NULL THEN 'Não disponível' ELSE TO_CHAR(lastDayOfCalculatedPeriod, 'DD/MM/YYYY') END;

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
          -- RAISE NOTICE 'Instalação não ocorreu, considerando o período inteiro';
          effectiveDate := baseDate;
        ELSE
          -- O início da vigência ocorre após a instalação do equipamento
          effectiveDate := installation.startDate;
        END IF;
      ELSE
        IF (installation.signatureDate IS NULL) THEN
          -- Como o contrato não foi assinado ainda, então consideramos
          -- o início do período mesmo
          -- RAISE NOTICE 'Contrato não foi assinado, considerando o período inteiro';
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
    -- RAISE NOTICE 'Considerando início do período de cobrança como sendo %', TO_CHAR(effectiveDate, 'DD/MM/YYYY');
  END IF;

  IF (endDateOfPeriod > installation.endDate) THEN
    endDateOfPeriod := installation.endDate;
  END IF;

  -- Determina se o período já foi processado
  IF (lastDayOfCalculatedPeriod >= endDateOfPeriod) THEN
    -- Já processamos este período, então simplesmente ignora
    -- RAISE NOTICE 'Este item de contrato já teve valores apurados até %. Ignorando.',
    --   to_char(lastDayOfCalculatedPeriod, 'DD/MM/YYYY');

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

  -- RAISE NOTICE 'O período a ser processado é de % até %', to_char(startDateOfPeriod, 'DD/MM/YYYY'), to_char(endDateOfPeriod, 'DD/MM/YYYY');

  -- Selecionar a tarifa vigente no início deste período
  IF (installation.startDate > startDateOfPeriod) THEN
    -- Recuperamos à partir da data de instalação, já que esta ocorreu
    -- durante o período que estamos apurando
    startOfPerformedService := installation.startDate;
    -- RAISE NOTICE 'Recuperando a tarifa à partir de %', to_char(installation.startDate, 'DD/MM/YYYY');
  ELSE
    startOfPerformedService := startDateOfPeriod;
    -- RAISE NOTICE 'Recuperando a tarifa à partir de %', to_char(startDateOfPeriod, 'DD/MM/YYYY');
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
  -- RAISE NOTICE 'A mensalidade considerada é %', monthPrice;

  -- Determinamos a quantidade de dias e o valor diário
  daysInPeriod := DATE_PART('day',
      (baseDate + interval '1 month' - interval '1 day')::timestamp - baseDate::timestamp
    ) + 1;
  -- RAISE NOTICE 'Este período possui % dias', daysInPeriod;
  dayPrice = monthPrice / daysInPeriod;
  -- RAISE NOTICE 'O valor diário é %', ROUND(dayPrice, 2);

  -- Determinamos se temos serviços qualificados para serem cobrados
  SELECT count(*) INTO amountOfQualifyServices
    FROM erp.installationRecords AS R
   WHERE R.installationID = FinstallationID
     AND (
       (R.uninstalledAt IS NULL AND R.installedAt <= endDateOfPeriod) OR
       (R.uninstalledAt >= startDateOfPeriod AND R.installedAt <= endDateOfPeriod)
      );
  IF (amountOfQualifyServices = 0) THEN
    -- RAISE NOTICE 'Não temos um período qualificado entre % e % para o item de contrato %', startDateOfPeriod, endDateOfPeriod, FinstallationID;
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
    -- RAISE NOTICE 'Calculando valores proporcionalmente';
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

            -- RAISE NOTICE 'Modificando o último período de serviço prestado para de % à % com % dias e custando %',
            --   to_char(startOfPerformedService, 'DD/MM/YYYY'),
            --   to_char(endOfPerformedService, 'DD/MM/YYYY'),
            --   daysInPerformedService,
            --   grossValue
            -- ;

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

      -- RAISE NOTICE 'Prestado serviços de % à % com % dias e custando %',
      --   to_char(startOfPerformedService, 'DD/MM/YYYY'),
      --   to_char(endOfPerformedService, 'DD/MM/YYYY'),
      --   daysInPerformedService,
      --   grossValue
      -- ;

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
                   CASE WHEN S.discountType = 1 THEN
                     S.discountValue / EXTRACT(DAY FROM (DATE_TRUNC('MONTH', startOfPerformedService + INTERVAL '1 MONTH') - INTERVAL '1 DAY'))
                   ELSE
                     S.discountValue
                   END AS discountValue
              FROM erp.subsidies AS S
             WHERE S.installationID = FinstallationID
               AND (
                 (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                 (S.periodEndedAt >= startDateOfPeriod)
                )
             ORDER BY S.bonus DESC, S.periodStartedAt
            ) AS performedSubsidies
      LOOP
        IF (isempty(subsidyRecord.subsidedPeriod)) THEN
          -- RAISE NOTICE 'Não há subsídio do perído de % à %',
          --   to_char(startOfPerformedService, 'DD/MM/YYYY'),
          --   to_char(endOfPerformedService, 'DD/MM/YYYY')
          -- ;
          discountValue := 0.00;
        ELSE
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

          -- RAISE NOTICE 'Subsídio de % à % do tipo % e valor %',
          --   to_char(startOfSubsidy, 'DD/MM/YYYY'),
          --   to_char(endOfSubsidy, 'DD/MM/YYYY'),
          --   subsidyRecord.discountType,
          --   discountValue
          -- ;
        END IF;

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
      -- RAISE NOTICE 'Prestado serviços de % à % com % dias e custando %',
      --   to_char(startDateOfPeriod, 'DD/MM/YYYY'),
      --   to_char(endDateOfPeriod, 'DD/MM/YYYY'),
      --   daysInPerformedService,
      --   monthPrice
      -- ;

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
                   CASE WHEN S.discountType = 1 THEN
                     S.discountValue / EXTRACT(DAY FROM (DATE_TRUNC('MONTH', startDateOfPeriod + INTERVAL '1 MONTH') - INTERVAL '1 DAY'))
                   ELSE
                     S.discountValue
                   END AS discountValue
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

        -- RAISE NOTICE 'Subsídio de % à % do tipo % e valor %',
        --   to_char(startOfSubsidy, 'DD/MM/YYYY'),
        --   to_char(endOfSubsidy, 'DD/MM/YYYY'),
        --   subsidyRecord.discountType,
        --   discountValue
        -- ;

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
  FinstallationID integer)
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
  -- RAISE NOTICE 'Recuperando informações do item de contrato';
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
   INNER JOIN erp.plans AS P ON (C.planID = P.planID)
   WHERE I.installationID = FinstallationID;

  -- RAISE NOTICE 'Número do item de contrato: %', installation.number;
  -- RAISE NOTICE 'Data da assinatura do contrato: %', TO_CHAR(installation.signatureDate, 'DD/MM/YYYY');
  -- RAISE NOTICE 'Data do início do item de contrato: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');
  -- RAISE NOTICE 'Iniciar cobrança após instalação: %', CASE WHEN installation.startTermAfterInstallation THEN 'Sim' ELSE 'Não' END;
  -- RAISE NOTICE 'Cobrar proporcional ao serviço prestado: %', CASE WHEN installation.prorata THEN 'Sim' ELSE 'Não' END;
  lastDayOfCalculatedPeriod := installation.lastDayOfCalculatedPeriod;
  -- RAISE NOTICE 'Término do último período apurado: %', CASE WHEN lastDayOfCalculatedPeriod IS NULL THEN 'Não disponível' ELSE TO_CHAR(lastDayOfCalculatedPeriod, 'DD/MM/YYYY') END;

  -- Verifica se já temos algum processamento realizado
  IF (lastDayOfCalculatedPeriod IS NULL) THEN
    -- Ainda não foi apurado nenhum período para este item de contrato

    -- Verifica se a cobrança deve ser proporcional
    IF (installation.prorata) THEN
      -- Devemos cobrar proporcionalmente, então determinamos quando
      -- isto ocorre
      IF (installation.startTermAfterInstallation) THEN
        IF (installation.startDate IS NULL) THEN
          -- RAISE NOTICE 'Instalação não ocorreu, considerando o período inteiro';
          effectiveDate := baseDate;
        ELSE
          -- O início da vigência ocorre após a instalação do equipamento
          -- RAISE NOTICE 'Consideramos a data de instalação';
          effectiveDate := installation.startDate;
        END IF;
      ELSE
        IF (installation.signatureDate IS NULL) THEN
          -- Como o contrato não foi assinado ainda, então consideramos
          -- o início do período mesmo
          -- RAISE NOTICE 'Contrato não foi assinado, considerando o início do período';
          effectiveDate := baseDate;
        ELSE
          -- O início da vigência ocorre na data de assinatura do contrato
          -- RAISE NOTICE 'Consideramos a data de assinatura';
          effectiveDate := installation.signatureDate;
        END IF;
      END IF;
    ELSE
      -- Devemos cobrar integralmente, então o início se dá sempre no
      -- início do mês
      -- RAISE NOTICE 'Consideramos o início do período';
      effectiveDate := baseDate;
    END IF;

    lastDayOfCalculatedPeriod := effectiveDate - interval '1 day';
    -- RAISE NOTICE 'Considerando início do período de cobrança como sendo %', TO_CHAR(effectiveDate, 'DD/MM/YYYY');
  END IF;

  IF (endDateOfPeriod > installation.endDate) THEN
    -- RAISE NOTICE 'Considerando término como sendo a data de desinstalação';
    endDateOfPeriod := installation.endDate;
  END IF;

  -- Determina se o período já foi processado
  IF (lastDayOfCalculatedPeriod >= endDateOfPeriod) THEN
    -- Já processamos este período, então simplesmente ignora
    -- RAISE NOTICE 'Esta instalação já teve valores apurados até %. Ignorando.',
    --   to_char(lastDayOfCalculatedPeriod, 'DD/MM/YYYY');

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

  -- RAISE NOTICE 'O período a ser processado é de % até %', to_char(startDateOfPeriod, 'DD/MM/YYYY'), to_char(endDateOfPeriod, 'DD/MM/YYYY');

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
  -- RAISE NOTICE 'A mensalidade considerada é %', monthPrice;

  -- Determinamos a quantidade de dias e o valor diário
  daysInPeriod := DATE_PART('day',
      (baseDate + interval '1 month' - interval '1 day')::timestamp - baseDate::timestamp
    ) + 1;
  -- RAISE NOTICE 'Este período possui % dias', daysInPeriod;
  dayPrice = monthPrice / daysInPeriod;
  -- RAISE NOTICE 'O valor diário é %', ROUND(dayPrice, 2);

  -- Determinamos se temos serviços qualificados para serem cobrados
  SELECT count(*) INTO amountOfQualifyServices
    FROM erp.installationRecords AS R
   WHERE R.installationID = FinstallationID
     AND (
       (R.uninstalledAt IS NULL AND R.installedAt <= endDateOfPeriod) OR
       (R.uninstalledAt >= startDateOfPeriod AND R.installedAt <= endDateOfPeriod)
      );
  IF (amountOfQualifyServices = 0) THEN
    -- RAISE NOTICE 'Não temos um período qualificado entre % e % para o item de contrato %', startDateOfPeriod, endDateOfPeriod, FinstallationID;
    RETURN jsonb '{ }';
  ELSE
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

              -- RAISE NOTICE 'Modificando o último período de serviço prestado para de % à % com % dias e custando %',
              --   to_char(startOfPerformedService, 'DD/MM/YYYY'),
              --   to_char(endOfPerformedService, 'DD/MM/YYYY'),
              --   daysInPerformedService,
              --   grossValue
              -- ;

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

        -- RAISE NOTICE 'Prestado serviços de % à % com % dias e custando %',
        --   to_char(startOfPerformedService, 'DD/MM/YYYY'),
        --   to_char(endOfPerformedService, 'DD/MM/YYYY'),
        --   daysInPerformedService,
        --   grossValue
        -- ;

        -- Analisamos se este período pertence ao período já cobrados deste
        -- cliente
        billedBefore := false;
        IF (installation.lastDayOfBillingPeriod >= endDateOfPeriod) THEN
          -- O período inteiro não deve ser cobrado, pois já foi faturado
          -- faturado antecipadamente (provavelmente por se tratar de
          -- pagamento por carnê ou cartão de crédito parcelado e/ou por
          -- que o plano deste cliente é pré-pago). Desta forma indicamos
          -- que este trecho não deve ser cobrado (apesasr de computado)
          -- RAISE NOTICE 'O último dia cobrado (%) é maior do que o último dia do período (%)', installation.lastDayOfBillingPeriod, endDateOfPeriod;
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
                     CASE WHEN S.discountType = 1 THEN
                       S.discountValue / EXTRACT(DAY FROM (DATE_TRUNC('MONTH', startOfPerformedService + INTERVAL '1 MONTH') - INTERVAL '1 DAY'))
                     ELSE
                       S.discountValue
                     END AS discountValue
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
          IF (startOfSubsidy IS NOT NULL) THEN
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

            -- RAISE NOTICE 'Subsídio de % à % do tipo % e valor %',
            --   to_char(startOfSubsidy, 'DD/MM/YYYY'),
            --   to_char(endOfSubsidy, 'DD/MM/YYYY'),
            --   subsidyRecord.discountType,
            --   discountValue
            -- ;

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
          END IF;
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
        -- RAISE NOTICE 'Prestado serviços de % à % com % dias e custando %',
        --   to_char(startDateOfPeriod, 'DD/MM/YYYY'),
        --   to_char(endDateOfPeriod, 'DD/MM/YYYY'),
        --   daysInPerformedService,
        --   monthPrice
        -- ;

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
                     CASE WHEN S.discountType = 1 THEN
                       S.discountValue / EXTRACT(DAY FROM (DATE_TRUNC('MONTH', startDateOfPeriod + INTERVAL '1 MONTH') - INTERVAL '1 DAY'))
                     ELSE
                       S.discountValue
                     END AS discountValue
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
          IF (startOfSubsidy IS NOT NULL) THEN
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

            -- RAISE NOTICE 'Subsídio de % à % do tipo % e valor %',
            --   to_char(startOfSubsidy, 'DD/MM/YYYY'),
            --   to_char(endOfSubsidy, 'DD/MM/YYYY'),
            --   subsidyRecord.discountType,
            --   discountValue
            -- ;

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
          END IF;
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
    -- RAISE NOTICE '%', periodTotal;

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
  END IF;
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
  -- RAISE NOTICE 'Recuperando informações do item de contrato';
  SELECT INTO installation
         I.contractorID,
         I.contractID,
         C.notchargeloyaltybreak AS notchargeloyaltybreakinallcontract,
         I.installationNumber AS number,
         P.loyaltyPeriod,
         P.loyaltyFine,
         I.monthPrice,
         I.notChargeLoyaltyBreak,
         I.startDate,
         I.endDate
    FROM erp.installations AS I
   INNER JOIN erp.contracts AS C ON (C.contractID = I.contractID)
   INNER JOIN erp.plans AS P ON (C.planID = P.planID)
   WHERE I.installationID = FinstallationID;

  -- RAISE NOTICE 'Número do item de contrato: %', installation.number;
  -- RAISE NOTICE 'Data do início: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');

  -- Determina a ocorrência de término do contrato e duração
  FendDate := CASE WHEN installation.endDate IS NULL THEN FendDateOfPeriod ELSE installation.endDate END;
  duration := EXTRACT(year FROM age(FendDate, installation.startDate))*12 + EXTRACT(month FROM age(FendDate, installation.startDate));
  -- RAISE NOTICE 'Data de término: %', TO_CHAR(FendDate, 'DD/MM/YYYY');
  -- RAISE NOTICE 'O item de contrato nº % teve duração de % meses',
  --   installation.number,
  --   duration;

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
        -- RAISE NOTICE 'Faltaram % meses para o término do período de fidelidade',
        --  monthsLeft;

        -- Calculamos o valor total que é a quantidade de meses que
        -- faltam vezes o valor da mensalidade vigente
        totalValue := ROUND(monthsLeft * installation.monthPrice, 2);

        -- A multa é uma porcentagem deste valor
        fineValue := ROUND(totalValue * installation.loyaltyFine / 100, 2);

        -- Lançamos esta multa para ser cobrada
        -- RAISE NOTICE 'Inserida uma multa de % no item de contrato nº %',
        --   fineValue,
        --   installation.number;
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
    SELECT B.name,
           C.chargeValue AS value
      FROM erp.contractCharges AS C
     INNER JOIN erp.billingTypes AS B USING (billingTypeID)
     WHERE C.contractID = installation.contractID
       AND B.billingMoments @> array[3, 4]
       AND B.inAttendance = false
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
     INNER JOIN erp.plans AS P ON (C.planID = P.planID)
     INNER JOIN erp.dueDays AS D ON (C.dueDayID = D.dueDayID)
     INNER JOIN erp.subsidiaries AS S ON (C.subsidiaryID = S.subsidiaryID)
     INNER JOIN erp.paymentConditions AS C1 ON (C.paymentConditionID = C1.paymentConditionID)
     INNER JOIN erp.definedMethods AS D1 ON (C1.definedMethodID = D1.definedMethodID)
     INNER JOIN erp.paymentConditions AS C2 ON (C.additionalPaymentConditionID = C2.paymentConditionID)
     INNER JOIN erp.definedMethods AS D2 ON (C2.definedMethodID = D2.definedMethodID)
     WHERE I.contractorID = FcontractorID
       AND C.deleted = false
       AND C.active = true
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
      IF ((installation.loyaltyPeriod > 0) AND (installation.notChargeLoyaltyBreak = FALSE)) THEN
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
              -- RAISE NOTICE 'Inserida uma multa de % no item de contrato nº %',
              --   fineValue,
              --   installation.number;
            END IF;
          END IF;
        END IF;
      END IF;

      -- Precisamos incluir tarifas de valores cobrados mensalmente
      -- presentes no plano
      FOR contractCharge IN
        SELECT C.contractChargeID,
               B.name,
               C.chargeValue,
               B.ratePerEquipment
          FROM erp.contractCharges AS C
         INNER JOIN erp.billingTypes AS B USING (billingTypeID)
         WHERE C.contractID = installation.contractID
           AND B.billingMoments @> array[5]
           AND B.inAttendance = false
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
          -- RAISE NOTICE 'Inserida a cobrança de % no valor de % no item de contrato nº %',
          --   contractCharge.name,
          --   contractCharge.chargeValue,
          --   installation.number;
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
            -- RAISE NOTICE 'Inserida a cobrança de % no valor de % no contato nº %',
            --   contractCharge.name,
            --   contractCharge.chargeValue,
            --   installation.contractNumber;

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
    -- RAISE NOTICE 'Não temos itens de contrato habilitados para este período.';

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

-- ---------------------------------------------------------------------
-- Atualiza o valor de uma fatura
-- ---------------------------------------------------------------------
-- Função que calcula novamente o valor de uma fatura
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.updateInvoiceValue(FcontractorID integer,
  FinvoiceID int) RETURNS bool AS
$$
BEGIN
  -- Determinamos o valor total da nota com base nos valores calculados
  UPDATE erp.invoices
     SET invoiceValue = ROUND(billings.total, 2)
    FROM (
      SELECT invoiceID,
             SUM(value) as total
        FROM (
          SELECT invoiceID,
                 CASE
                   WHEN granted THEN 0.00
                   ELSE value
                 END AS value
            FROM erp.billings
           WHERE renegotiated = false
             AND invoiced = false
             AND contractorID = FcontractorID
             AND invoiceID = FinvoiceID
          ) AS summation
        GROUP BY invoiceID
     ) AS billings
   WHERE invoices.invoiceID = billings.invoiceID
     AND invoices.contractorID = FcontractorID;

  RETURN TRUE;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Descarta o fechamento dos valores de cada item de contrato
-- ---------------------------------------------------------------------
-- Função que descarta os valores do fechamento em aberto.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.discardMonthlyCalculations(FcontractorID integer) RETURNS boolean AS
$$
DECLARE
  -- Os dados da fatura
  invoice  record;
  billing  record;
  FlastDayOfCalculatedPeriod  date;
  lastInvoiceID  integer;
BEGIN
  -- Descobrimos primeiramente as faturas abertas e, com base nesta
  -- informação, desfazemos o que precisa
  FOR invoice IN
    SELECT invoiceID AS id
      FROM erp.invoices
     WHERE contractorID = FcontractorID
       AND underanalysis = true
     ORDER BY invoicedate
  LOOP
    -- RAISE NOTICE 'Processando a fatura nº %', invoice.id;

    -- Descartamos os períodos de valores apurados para este fechamento
    FOR billing IN
      SELECT billingID AS id,
             installationID,
             ascertainedperiodID
        FROM erp.billings
       WHERE invoiceID = invoice.id
         AND ascertainedperiodID IS NOT NULL
    LOOP
      -- Apagamos os detalhes de valores apurados
      DELETE FROM erp.ascertainedperioddetails
       WHERE ascertainedperiodid = billing.ascertainedperiodID;

      -- Apagamos os totais dos valores apurados
      DELETE FROM erp.ascertainedperiods
       WHERE ascertainedperiodid = billing.ascertainedperiodID;

      -- Apagamos as informações de períodos cobrados desta fatura
      DELETE FROM erp.billedPeriods
       WHERE invoiceid = invoice.id
         AND installationID = billing.installationID;

      -- Descobrimos a informação do último período apurado para este
      -- item de contrato
      SELECT INTO FlastDayOfCalculatedPeriod
             enddate
        FROM erp.ascertainedperiods
       WHERE installationid = billing.installationID
       ORDER BY startDate DESC FETCH FIRST ROW ONLY;
      IF NOT FOUND THEN
        -- Não temos outro período apurado ainda, então deixa nulo
        FlastDayOfCalculatedPeriod := NULL;
      END IF;

      -- Atualizamos a data do último dia do período de apuração dos
      -- valores e do período cobrado para este item de contrato
      UPDATE erp.installations
         SET lastDayOfCalculatedPeriod = FlastDayOfCalculatedPeriod
       WHERE installationID = billing.installationID;

      -- Atualizamos a data do último dia do período cobrado
      UPDATE erp.installations
         SET lastDayOfBillingPeriod = billed.lastDay
        FROM (SELECT enddate AS lastDay
                FROM erp.billedPeriods
               WHERE installationid = billing.installationID
               ORDER BY startDate DESC FETCH FIRST ROW ONLY) AS billed
       WHERE installationID = billing.installationID;
    END LOOP;

    -- Descartamos quaisquer valores adicionados pelo processo de
    -- fechamento
    DELETE FROM erp.billings
     WHERE invoiceID = invoice.id
       AND addMonthlyAutomatic = true;

    -- Alteramos as cobranças para indicar que elas não fazem mais parte
    -- de um fechamento
    UPDATE erp.billings
       SET invoiceID = NULL
     WHERE invoiceID = invoice.id;
  END LOOP;

  -- Por último, apagamos as faturas
  DELETE FROM erp.invoices
   WHERE contractorID = FcontractorID
     AND underanalysis = true;

  -- Retornamos os índices de incremento
  SELECT max(invoiceID) INTO lastInvoiceID
    FROM erp.invoices;
  lastInvoiceID := lastInvoiceID + 1;
  EXECUTE 'ALTER SEQUENCE erp.invoices_invoiceid_seq RESTART WITH ' || lastInvoiceID || ';';

  -- Indica que tudo deu certo
  RETURN true;
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
           ((D.parameters::jsonb - 'instructionID') - 'instructionDays')::json AS parameters,
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
    ourNumber := erp.buildBankIdentificationNumber(invoice.bankID,
      invoice.agencyNumber, invoice.accountNumber, invoice.wallet,
      FbillingCounter, invoice.invoiceID, invoice.parameters);

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
             wallet, billingCounter, parameters, ourNumber, fineValue,
             arrearInterestType, arrearInterest, instructionID,
             instructionDays, droppedTypeID)
      VALUES (invoice.contractorID, invoice.invoiceID, invoice.dueDate,
             invoice.invoiceValue, invoice.paymentMethodID,
             paymentSituationID, invoice.definedMethodID, invoice.bankID,
             invoice.agencyNumber, invoice.accountNumber, invoice.wallet,
             FbillingCounter, invoice.parameters, ourNumber, FfineValue,
             FarrearInterestType, FarrearInterest, invoice.instructionID,
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
-- Corrige a informação da ausência de informações na tabela de controle
-- de reajustes por item de contrato
-- ---------------------------------------------------------------------
-- Função que popula a tabela de reajustes para uma determinado item de
-- contrato
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.populateReadjustmentsOnInstallations(FinstallationID integer) RETURNS boolean AS
$$
DECLARE
  -- Os dados do item de contrato
  FstartDate  date;
  FcontractorID  integer;
  FcontractID  integer;
  FplanID  integer;
  FcreatedByUserID  integer;
  FmonthPrice  numeric(12,2);
  readjustmentperiod  smallint;
  FeffectivePriceDate  date;
  FdateOfNextReadjustment  date;
  interaction  integer;
BEGIN
  -- Selecionamos a data de início do item de contrato
  SELECT INTO FcontractorID, FstartDate, FplanID, FcontractID, FmonthPrice, FcreatedByUserID
         contractorID,
         startDate,
         planID,
         contractID,
         monthPrice,
         createdByUserID
    FROM erp.installations
   WHERE installationID = FinstallationID;

  -- Recuperamos o período de reajuste do contrato
  SELECT INTO readjustmentPeriod
         P.readjustmentPeriod
    FROM erp.plans AS P
   WHERE P.planID = FplanID
     AND P.contractorID = FcontractorID;

  IF (readjustmentPeriod <= 0) THEN
    -- Disparamos uma exceção
    RAISE EXCEPTION 'Não foi possível determinar a duração do plano ID % informado.', FplanID
    USING HINT = 'O período de reajuste informado no plano é inválido.';
  END IF;

  FeffectivePriceDate := FstartDate;
  interaction := 0;

  LOOP
    IF (interaction > 0) THEN
      -- Fazemos com que a data de início de vigência desta
      -- mensalidade seja a data do último reajuste calculado
      FeffectivePriceDate := FdateOfNextReadjustment;
    END IF;

    -- Calculamos a data do próximo reajuste
    FdateOfNextReadjustment := (FeffectivePriceDate
      + interval '1 month' * readjustmentPeriod)::DATE;

    interaction := interaction + 1;

    -- Inserimos no histórico de reajustes estas informações
    INSERT INTO erp.readjustmentsOnInstallations (contractID,
      installationID, monthPrice, readjustedAt,
      readjustedByUserID) VALUES
      (FcontractID, FinstallationID, FmonthPrice, FeffectivePriceDate,
       FcreatedByUserID);

    -- Repetimos este processo até determinar uma data que seja
    -- posterior ao dia atual
    EXIT WHEN FdateOfNextReadjustment > CURRENT_DATE;
  END LOOP;

  -- Agora atualiza a data de início da vigência no item de contrato
  UPDATE erp.installations
     SET effectivePriceDate = FeffectivePriceDate
   WHERE installationID = FinstallationID;

  -- Indica que tudo deu certo
  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Recupera dados para geração do PDF
-- ---------------------------------------------------------------------
-- Função que recupera os dados para geração do PDF do fechamento em
-- formato JSON, de forma a acelerar o processamento
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getMonthlyCalculations(FcontractorID integer)
  RETURNS SETOF jsonb AS
$$
DECLARE
  -- Os dados da fatura
  invoice  record;
  invoiceData  jsonb;

  -- A análise dos valores cobrados de cada fatura
  billing  record;
  invoiceContent  jsonb;
  lastContent  jsonb;
  otherValues  jsonb;
  lastInstallationID  integer;
  totalOfInstallation  numeric(12, 2);
  numberOfInstallationsOnInvoice  integer;
  totalOfInstallations  integer;
  totalInGeneral  numeric(12, 2);
  totalInServices  numeric(12, 2);
  totalInEventual  numeric(12, 2);
  otherCount  integer;
  eventualCount  integer;
  periodCount  integer;
  monthlyCount  integer;

  -- Os períodos apurados
  ascertainedPeriod  record;
  periodDetail  record;
BEGIN
  -- Inicializamos os totalizadores
  totalOfInstallations := 0;
  totalInGeneral := 0.00;
  totalInServices := 0.00;
  totalInEventual := 0.00;

  -- Recupera as informações das faturas abertas
  FOR invoice IN
    SELECT invoices.invoiceID AS id,
           invoices.customerID,
           invoices.subsidiaryID,
           customers.name as customerName,
           entitiestypes.juridicalPerson,
           subsidiaries.name AS subsidiaryName,
           subsidiaries.nationalRegister,
           subsidiaries.regionalDocumentType,
           documenttypes.name AS regionalDocumentTypeName,
           subsidiaries.regionalDocumentNumber,
           subsidiaries.regionaldocumentstate,
           invoices.referenceMonthYear,
           to_char(invoices.invoiceDate, 'DD/MM/YYYY') AS invoiceDate,
           to_char(invoices.dueDate, 'DD/MM/YYYY') AS dueDate,
           invoices.invoiceValue,
           invoices.paymentMethodID,
           paymentmethods.name AS paymentMethodName
      FROM erp.invoices
     INNER JOIN erp.entities AS customers ON (invoices.customerID = customers.entityID)
     INNER JOIN erp.subsidiaries ON (invoices.subsidiaryID = subsidiaries.subsidiaryID)
     INNER JOIN erp.documenttypes ON (subsidiaries.regionaldocumenttype = documenttypes.documenttypeid)
     INNER JOIN erp.entitiesTypes USING (entityTypeID)
     INNER JOIN erp.paymentMethods USING (paymentMethodID)
     INNER JOIN erp.definedMethods USING (definedMethodID)
     WHERE invoices.contractorID = FcontractorID
       AND invoices.underanalysis = true
    ORDER BY customers.name, invoices.invoiceID
  LOOP
    -- Montamos os dados da fatura
    invoiceData := row_to_json(invoice);

    -- Criamos um JSON com os valores que não fazem parte de nenhuma dos
    -- itens de contrato mas que é cobrado nesta fatura
    otherValues := jsonb '{ }';

    -- Zeramos os totalizadores desta fatura
    numberOfInstallationsOnInvoice := 0;
    totalOfInstallation := 0.00;

    -- Recuperamos os valores cobrados em cada fatura
    lastInstallationID := 0;
    invoiceContent = jsonb '{ }';
    FOR billing IN
      SELECT billings.billingID AS id,
             billings.installationID,
             installations.installationNumber,
             installations.customerID,
             installations.startDate,
             installations.endDate,
             billings.billingDate,
             billings.granted,
             CASE
               WHEN billings.renegotiationID IS NOT NULL AND billings.numberOfInstallments > 0 THEN 'Renegociação de ' || billings.name || ' (Parcela ' || billings.installmentNumber || ' de ' || billings.numberOfInstallments || ')'
               WHEN billings.renegotiationID IS NOT NULL AND billings.numberOfInstallments = 0 THEN 'Renegociação de ' || billings.name
               WHEN billings.numberOfInstallments > 0 THEN billings.name || ' (Parcela ' || billings.installmentNumber || ' de ' || billings.numberOfInstallments || ')'
               ELSE billings.name
             END AS name,
             CASE
               WHEN billings.granted THEN 0.00
               ELSE billings.value
             END AS value,
             billings.ascertainedPeriodID,
             billings.isMonthlyPayment
        FROM erp.billings
        LEFT JOIN erp.installations ON (billings.installationID = installations.installationID)
       WHERE billings.contractorid = FcontractorID
         AND billings.invoiceid = invoice.id
         AND billings.renegotiated = false
       ORDER BY billings.installationID NULLS LAST, billings.ascertainedPeriodID NULLS LAST, billings.billingdate
    LOOP
      IF (billing.installationID IS NOT NULL) THEN
        IF (lastInstallationID <> billing.installationID) THEN
          IF (lastInstallationID > 0) THEN
            -- Atribuímos os totalizadores no último registro. Para isto,
            -- recuperamos o conteúdo atual
            EXECUTE format('SELECT (%L)::jsonb#>''{ %s }''',
                invoiceContent,
                lastInstallationID
              )
              INTO lastContent;
            -- Modificamos seu valor
            lastContent := lastContent || jsonb_build_object('total', format('%s', totalOfInstallation));
            -- E atribuímos novamente
            EXECUTE format('SELECT jsonb_set(%L, ''{ %s }'', %L);',
                invoiceContent,
                lastInstallationID,
                lastContent
              )
              INTO invoiceContent;
          END IF;

          -- Mudamos para o novo item de contrato
          lastInstallationID := billing.installationID;

          -- Zeramos o totalizador
          totalOfInstallation := 0.00;
          otherCount := 0;
          monthlyCount := 0;
          eventualCount := 0;

          -- Incrementamos o contador de itens de contrato
          numberOfInstallationsOnInvoice := numberOfInstallationsOnInvoice + 1;

          -- Criamos um novo conjunto de valores para este item de
          -- contrato
          -- RAISE NOTICE 'Antes %', invoiceContent;
          EXECUTE format('SELECT jsonb_set(%L, ''{ %s }'', '
              || 'jsonb ''{ '
              ||   '"number": "%s", '
              ||   '"customerID": "%s", '
              ||   '"startDate": "%s", '
              ||   '"servicesValues": { }, '
              ||   '"monthlyValues": { }, '
              ||   '"eventualValues": { }, '
              ||   '"total": "0.00"'
              || '}'', true);',
              invoiceContent,
              lastInstallationID,
              billing.installationNumber,
              billing.customerID,
              to_char(billing.startDate, 'DD/MM/YYYY')
            )
            INTO invoiceContent;
          -- RAISE NOTICE 'Depois %', invoiceContent;
        END IF;

        IF (billing.isMonthlyPayment) THEN
          -- Estes valores são dos serviços prestados
          IF (billing.ascertainedPeriodID IS NOT NULL) THEN
            -- Precisamos recuperar a informação do período apurado para
            -- acrescentar nesta parte
            SELECT INTO ascertainedPeriod
                   to_char(startDate, 'DD/MM/YY') AS startedAt,
                   to_char(endDate, 'DD/MM/YY') AS endedAt,
                   grossValue,
                   discountValue
              FROM erp.ascertainedPeriods
             WHERE ascertainedPeriodID = billing.ascertainedPeriodID
             FETCH FIRST ROW ONLY;
            IF NOT FOUND THEN
              -- Não conseguimos recuperar o período apurado, então
              -- disparamos uma exceção
              RAISE EXCEPTION 'Não foi possível obter o período apurado ID % para o item de contrato nº %',
                billing.ascertainedPeriodID,
                billing.installationNumber
              USING HINT = 'Por favor, verifique os valores processados';
            END IF;

            IF (billing.granted) THEN
              EXECUTE format('SELECT jsonb_set(%L, ''{ %s, servicesValues }'', '
                  || 'jsonb ''{ '
                  ||   '"startedAt": "%s", '
                  ||   '"endedAt": "%s", '
                  ||   '"grossValue": "%s", '
                  ||   '"discountValue": "%s", '
                  ||   '"finalValue": "%s", '
                  ||   '"granted": true, '
                  ||   '"periods": { } '
                  || '}'', true);',
                  invoiceContent,
                  lastInstallationID,
                  ascertainedPeriod.startedAt,
                  ascertainedPeriod.endedAt,
                  0.00,
                  0.00,
                  0.00
                )
                INTO invoiceContent;
            ELSE
              EXECUTE format('SELECT jsonb_set(%L, ''{ %s, servicesValues }'', '
                  || 'jsonb ''{ '
                  ||   '"startedAt": "%s", '
                  ||   '"endedAt": "%s", '
                  ||   '"grossValue": "%s", '
                  ||   '"discountValue": "%s", '
                  ||   '"finalValue": "%s", '
                  ||   '"granted": false, '
                  ||   '"periods": { } '
                  || '}'', true);',
                  invoiceContent,
                  lastInstallationID,
                  ascertainedPeriod.startedAt,
                  ascertainedPeriod.endedAt,
                  ascertainedPeriod.grossValue,
                  ascertainedPeriod.discountValue,
                  billing.value
                )
                INTO invoiceContent;
            END IF;

            -- Agora recuperamos a informação dos detalhes deste período
            -- apurado
            periodCount := 0;
            FOR periodDetail IN
              SELECT to_char(period.periodStartedAt, 'DD/MM') AS startedAt,
                     to_char(period.periodEndedAt, 'DD/MM') AS endedAt,
                     vehicles.plate,
                     equipments.serialNumber,
                     equipmentBrands.name AS equipmentBrandName,
                     equipmentModels.name AS equipmentModelName
                FROM erp.ascertainedPeriodDetails AS period
               INNER JOIN erp.vehicles USING (vehicleID)
               INNER JOIN erp.equipments USING (equipmentID)
               INNER JOIN erp.equipmentModels USING (equipmentModelID)
               INNER JOIN erp.equipmentBrands USING (equipmentBrandID)
               WHERE period.ascertainedPeriodID = billing.ascertainedPeriodID
               ORDER BY period.periodStartedAt, period.subsidyid NULLS FIRST
            LOOP
              -- Acrescentamos o detalhamento de cada período, com a
              -- informação do veículo e equipamento utilizado e o
              -- período apurado
              EXECUTE format('SELECT jsonb_set(%L, ''{ %s, servicesValues, periods, %s }'', '
                  || 'jsonb ''{ '
                  ||   '"startedAt": "%s", '
                  ||   '"endedAt": "%s", '
                  ||   '"plate": "%s", '
                  ||   '"serialNumber": "%s", '
                  ||   '"brand": "%s", '
                  ||   '"model": "%s" '
                  || '}'', true);',
                  invoiceContent,
                  lastInstallationID,
                  periodCount,
                  periodDetail.startedAt,
                  periodDetail.endedAt,
                  periodDetail.plate,
                  periodDetail.serialNumber,
                  periodDetail.equipmentBrandName,
                  periodDetail.equipmentModelName
                )
                INTO invoiceContent;

                -- Incrementamos o período
                periodCount := periodCount + 1;
            END LOOP;
          ELSE
            IF (billing.granted) THEN
              -- Não acrescenta valores abonados
            ELSE
              -- Esta é uma mensalidade cobrada em função de um valor
              -- presente no contrato
              EXECUTE format('SELECT jsonb_set(%L, ''{ %s, monthlyValues, %s }'', '
                  || 'jsonb ''{ '
                  ||   '"name": "%s", '
                  ||   '"value": %s '
                  || '}'', true);',
                  invoiceContent,
                  lastInstallationID,
                  monthlyCount,
                  billing.name,
                  billing.value
                )
                INTO invoiceContent;

              -- Incrementamos a mensalidade
              monthlyCount := monthlyCount + 1;
            END IF;
          END IF;

          totalInServices := totalInServices + billing.value;
        ELSE
          IF (billing.granted) THEN
            -- Não acrescenta valores abonados
          ELSE
            -- Estes são valores eventuais
            EXECUTE format('SELECT jsonb_set(%L, ''{ %s, eventualValues, %s }'', '
                || 'jsonb ''{ '
                ||   '"day": "%s", '
                ||   '"name": "%s", '
                ||   '"value": %s '
                || '}'', true);',
                invoiceContent,
                lastInstallationID,
                eventualCount,
                to_char(billing.billingDate, 'DD'),
                billing.name,
                billing.value
              )
              INTO invoiceContent;

            -- Incrementamos o contador destes dados
            eventualCount := eventualCount + 1;
            totalInEventual := totalInEventual + billing.value;
          END IF;
        END IF;

        -- Incrementamos os totalizadores
        totalOfInstallation := totalOfInstallation + billing.value;
      ELSE
        -- Este é um valor que não faz parte de nenhuma dos itens de
        -- contrato, então é adicionado no final
        EXECUTE format('SELECT jsonb_set(%L, ''{ %s }'', '
            || 'jsonb ''{ '
            ||   '"name": "%s", '
            ||   '"value": %s '
            || '}'', true);',
            otherValues,
            otherCount,
            billing.name,
            billing.value
          )
          INTO otherValues;

          -- Incrementamos o contador destes dados
          otherCount := otherCount + 1;
          totalInEventual := totalInEventual + billing.value;
      END IF;
    END LOOP;

    IF (lastInstallationID > 0) THEN
      -- Atribuímos os totalizadores no último registro. Para isto,
      -- recuperamos o conteúdo atual
      EXECUTE format('SELECT (%L)::jsonb#>''{ %s }''',
          invoiceContent,
          lastInstallationID
        )
        INTO lastContent;
      -- Modificamos seu valor
      lastContent := lastContent || jsonb_build_object('total', format('%s', totalOfInstallation));
      -- E atribuímos novamente
      EXECUTE format('SELECT jsonb_set(%L, ''{ %s }'', %L);',
          invoiceContent,
          lastInstallationID,
          lastContent
        )
        INTO invoiceContent;
    END IF;

    -- Incluimos o conteúdo
    invoiceData = jsonb_set(invoiceData, '{ content }', invoiceContent, true);

    -- Precisamos incluir os outros valores desta fatura
    invoiceData = jsonb_set(invoiceData, '{ otherValues }', otherValues, true);

    -- Precisamos incluir a quantidade de itens de contrato nesta fatura
    invoiceData := invoiceData
      || ('{ "numberofinstallationsoninvoice": "'
      || numberOfInstallationsOnInvoice || '" }')::jsonb;

    -- Incrementamos os totalizadores
    totalInGeneral := totalInGeneral + (invoiceData->>'invoicevalue')::numeric;
    totalOfInstallations := totalOfInstallations + numberOfInstallationsOnInvoice;

    RETURN NEXT invoiceData;
  END LOOP;

  -- Ao final, incluímos o total de tudo
  RETURN NEXT ('{ "totalOfInstallations": "'
      || totalOfInstallations || '", "totalInGeneral": "'
      || totalInGeneral || '", "totalInServices": "'
      || totalInServices || '", "totalInEventual": "'
      || totalInEventual || '" }')::jsonb;
END;
$$ LANGUAGE 'plpgsql';
