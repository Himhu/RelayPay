DELETE FROM `ypay_order` WHERE `account_id`= 0;
ALTER TABLE `ypay_order` CHANGE `pla_type` `pay_type` INT(11) NOT NULL DEFAULT '1';