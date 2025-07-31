-- Acrescentamos a informação de subtipo de um modelo
ALTER TABLE erp.vehicleModels
  ADD COLUMN vehicleSubtypeID integer DEFAULT NULL;

ALTER TABLE erp.vehicleModels
  ADD CONSTRAINT vehiclemodels_vehiclesubtypeid_fkey
      FOREIGN KEY (vehicleSubtypeID)
        REFERENCES erp.vehicleSubtypes(vehicleSubtypeID)
        ON DELETE RESTRICT;

-- Modifica todas as motos como sendo moto
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 4 WHERE vehicletypeid = 2;

UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Captur%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Clio%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Duster%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Fluence%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 2 WHERE name ILIKE 'Kangoo%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Kwid%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Laguna%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Logan%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 2 WHERE name ILIKE 'Master%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Megane%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Sandero%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Scénic%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Stepway%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 2 WHERE name ILIKE 'Trafic%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Twingo%';

UPDATE erp.vehiclemodels SET vehicleSubtypeID = 3 WHERE name ILIKE 'Amarok%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Crossfox%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Fox%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Fusca%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Gol%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 2 WHERE name ILIKE 'Gol Furg%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Jetta%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 2 WHERE name ILIKE 'Kombi%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Logus%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Parati%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Passat%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Pointer%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Polo%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Quantum%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Santana%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 3 WHERE name ILIKE 'Saveiro%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Spacefox%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'up!%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Voyage%';

UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 7 AND name ILIKE 'A1 %';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 7 AND name ILIKE 'A3 %';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 7 AND name ILIKE 'A4 %';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 7 AND name ILIKE 'A5 %';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 7 AND name ILIKE 'A6 %';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 7 AND name ILIKE 'A7 %';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 7 AND name ILIKE 'A8 %';

UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Agile%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Astra%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Blazer%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Camaro%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Celta%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Cobalt%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Corsa%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Cruze%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 3 WHERE name ILIKE 'D-10 %';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 3 WHERE name ILIKE 'D-20 %';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Kadett%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Meriva%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 3 WHERE name ILIKE 'Montana%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Monza%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Omega%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Onix%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Opala%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Prisma%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 3 WHERE name ILIKE 'S10 %';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Vectra%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'Zafira%';

UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Argo%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Brava%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Bravo%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Cronos%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 2 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Doblo%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 2 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Ducato%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Elba%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 2 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Fiorino%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Idea%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Marea%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Mobi%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Palio%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Premio%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Punto%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Siena%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Stilo%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 3 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Strada%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Tempra%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Tipo%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 3 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Toro%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Uno%';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 2 WHERE vehicletypeperbrandid = 27 AND name ILIKE 'Uno Furg%';

UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'HB20 %';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'HB20X %';
UPDATE erp.vehiclemodels SET vehicleSubtypeID = 1 WHERE name ILIKE 'HB20S %';
