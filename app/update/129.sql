UPDATE `admin_channel` SET `code` = 'alipay_software' WHERE `code` = 'alipay_pc';
DELETE FROM `admin_channel` WHERE `code` = 'alipay_app';
UPDATE `ypay_account` 
SET `code` = 'alipay_software' 
WHERE `code` IN ('alipay_pc', 'alipay_app');
UPDATE `admin_channel` SET `code` = 'wxpay_software',`name` = '微信软件版',`info`='微信软件版' WHERE `code` = 'wxpay_zg';
DELETE FROM `admin_channel` WHERE `code` = 'wxpay_app';
UPDATE `ypay_account` 
SET `code` = 'wxpay_software' 
WHERE `code` IN ('wxpay_zg', 'wxpay_app');
UPDATE `admin_channel` SET `code` = 'qqpay_software',`name` = 'QQ软件版',`info`='QQ软件版' WHERE `code` = 'qqpay_zg';
UPDATE `ypay_account` 
SET `code` = 'qqpay_software' 
WHERE `code` = 'qqpay_zg';