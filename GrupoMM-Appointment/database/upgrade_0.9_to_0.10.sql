-- Renomeamos a tabela billingMomments para billingMoments pois está
-- com a grafia errada
ALTER TABLE erp.billingMomments
  RENAME TO billingMoments;

ALTER TABLE erp.billingTypes
  RENAME COLUMN billingMommentID TO billingMomentID;

ALTER TABLE erp.billingMoments
  RENAME COLUMN billingMommentID TO billingMomentID;
